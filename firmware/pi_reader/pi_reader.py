#!/usr/bin/env python3
"""SpotFam Pi-Leser – PN532 (HW-147) als RFID-Reader.

Liest 13,56-MHz-Karten (MIFARE Classic / NTAG) ueber einen NXP PN532 am
I2C-Bus des Raspberry Pi, normalisiert die UID exakt wie die ESP32/MFRC522-
Firmware und meldet jeden Scan per HTTP an das SpotFam-Backend
(``POST /api/v1/readers/scan``). Das Backend startet daraufhin die gebundene
Playlist bzw. legt fuer unbekannte Karten einen Scan-Event ``unknown_card`` an
(Grundlage fuer das Scan-to-Enroll im Frontend).

Architektur (analog ESP): PN532 -> HTTP -> Backend -> Spotify -> Spotify-Connect-Geraet.
Es liegen KEINE Spotify-Tokens auf dem Pi-Leser (sicher by design).

Decision-Bezug: D-017 (Pi-Leser = HW-147 = PN532), D-P1 A (I2C),
D-P2 A (Python-Host-systemd-Dienst, kein Container, wegen Hardware-Zugriff).

Konfiguration ueber Umgebungsvariablen (siehe ``secrets.example.env`` /
``secrets.py``). Logging geht nach stdout und wird von systemd/journald erfasst.

HINWEIS (Hardware-Verifikation offen): Dieser Code wurde OHNE echte Hardware
geschrieben. Vor Produktivbetrieb muss verifiziert werden, dass eine bekannte
Karte an PN532 und MFRC522 denselben UID-String liefert (siehe README).
"""

from __future__ import annotations

import logging
import os
import sys
import time
from dataclasses import dataclass

import requests

# Adafruit-Stack (Blinka stellt board/busio auf dem Pi bereit). Importe sind
# bewusst lokal gehalten, damit ``py_compile`` / Unit-Tests auch ohne
# installierte Hardware-Treiber funktionieren (siehe _init_reader()).


# ---------------------------------------------------------------------------
# Konfiguration
# ---------------------------------------------------------------------------
@dataclass(frozen=True)
class Config:
    """Laufzeit-Konfiguration, ausschliesslich aus Umgebungsvariablen."""

    backend_base_url: str
    reader_id: str
    api_key: str

    # Verhalten (Defaults bewusst konservativ, per ENV ueberschreibbar).
    http_timeout_s: float = 8.0
    poll_interval_s: float = 0.2  # Wartezeit zwischen Karten-Abfragen
    scan_cooldown_s: float = 1.5  # lokale Entprellung gleicher Karte
    retry_backoff_start_s: float = 1.0
    retry_backoff_max_s: float = 30.0

    @staticmethod
    def from_env() -> "Config":
        base_url = os.environ.get("BACKEND_BASE_URL", "").rstrip("/")
        reader_id = os.environ.get("READER_ID", "")
        api_key = os.environ.get("READER_API_KEY", "")

        missing = [
            name
            for name, value in (
                ("BACKEND_BASE_URL", base_url),
                ("READER_ID", reader_id),
                ("READER_API_KEY", api_key),
            )
            if not value
        ]
        if missing:
            raise SystemExit(
                "Fehlende Pflicht-Umgebungsvariablen: "
                + ", ".join(missing)
                + " (siehe secrets.example.env)."
            )

        def _float_env(name: str, default: float) -> float:
            raw = os.environ.get(name)
            if raw is None or raw.strip() == "":
                return default
            try:
                return float(raw)
            except ValueError:
                raise SystemExit(f"Umgebungsvariable {name} ist keine Zahl: {raw!r}")

        return Config(
            backend_base_url=base_url,
            reader_id=reader_id,
            api_key=api_key,
            http_timeout_s=_float_env("HTTP_TIMEOUT_S", 8.0),
            poll_interval_s=_float_env("POLL_INTERVAL_S", 0.2),
            scan_cooldown_s=_float_env("SCAN_COOLDOWN_S", 1.5),
            retry_backoff_start_s=_float_env("RETRY_BACKOFF_START_S", 1.0),
            retry_backoff_max_s=_float_env("RETRY_BACKOFF_MAX_S", 30.0),
        )


log = logging.getLogger("spotfam.pi_reader")


# ---------------------------------------------------------------------------
# UID-Normalisierung
# ---------------------------------------------------------------------------
def normalize_uid(uid_bytes: bytes) -> str:
    """Formatiert eine rohe Karten-UID byte-fuer-byte identisch zur ESP-Firmware.

    Referenz: ``firmware/spotfam_reader/spotfam_reader.ino`` -> ``uidToHex()``::

        for (byte i = 0; i < uid.size; i++) {
          if (uid.uidByte[i] < 0x10) out += '0';   // fuehrende Null
          out += String(uid.uidByte[i], HEX);       // 2 Hex-Zeichen
        }
        out.toUpperCase();                           // GROSSBUCHSTABEN

    Daraus folgt das kanonische Format:
      * Hex, GROSSBUCHSTABEN
      * exakt 2 Zeichen pro Byte (fuehrende Null)
      * OHNE Trennzeichen
      * Byte-Reihenfolge wie vom Leser geliefert (keine Umkehr)
      * Laenge = 2 * Anzahl UID-Bytes (4-Byte- und 7-Byte-UIDs werden
        unveraendert uebernommen, da die ESP-Schleife ueber ``uid.size`` laeuft)

    Die Adafruit-PN532-Bibliothek liefert die UID via ``read_passive_target``
    als ``bytearray`` in derselben natuerlichen Byte-Reihenfolge wie der
    MFRC522 (``uid.uidByte``), daher genuegt ein 1:1-Hex-Encoding.

    ANNAHME / offener Verifikationspunkt: Byte-Reihenfolge und -Laenge von
    PN532 und MFRC522 sind identisch. Das muss mit einer bekannten Karte an
    beiden Lesern gegengeprueft werden (identischer String erwartet).
    """
    return "".join(f"{b:02X}" for b in uid_bytes)


# ---------------------------------------------------------------------------
# Backend-Client
# ---------------------------------------------------------------------------
class BackendClient:
    """Duenner HTTP-Client fuer den Reader-Scan-Endpunkt."""

    def __init__(self, config: Config) -> None:
        self._config = config
        self._session = requests.Session()
        self._session.headers.update(
            {
                "Content-Type": "application/json",
                # Backend akzeptiert X-API-Key ODER Authorization: Bearer.
                # X-API-Key entspricht der ESP-Firmware (postJson()).
                "X-API-Key": config.api_key,
            }
        )

    def send_scan(self, card_uid: str) -> str:
        """Meldet einen Scan. Gibt das ``outcome`` zurueck.

        Wirft ``requests.RequestException`` bei Netz-/Verbindungsfehlern, damit
        die Hauptschleife mit Backoff reagieren kann. HTTP-Fehlerstatus (4xx/5xx)
        werden NICHT als Exception behandelt, sondern als ``outcome`` geloggt –
        sie sind kein Verbindungsproblem und sollen die Schleife nicht bremsen.
        """
        url = f"{self._config.backend_base_url}/api/v1/readers/scan"
        payload = {"reader_id": self._config.reader_id, "card_uid": card_uid}
        response = self._session.post(
            url, json=payload, timeout=self._config.http_timeout_s
        )
        outcome = ""
        try:
            body = response.json()
            outcome = str(body.get("outcome", ""))
            message = body.get("message", "")
        except ValueError:
            message = response.text

        log.info(
            "scan reader_id=%s uid=%s http=%s outcome=%s message=%s",
            self._config.reader_id,
            card_uid,
            response.status_code,
            outcome or "?",
            message,
        )
        return outcome


# ---------------------------------------------------------------------------
# PN532-Reader (Hardware)
# ---------------------------------------------------------------------------
def _init_reader():
    """Initialisiert den PN532 am I2C-Bus und gibt das Reader-Objekt zurueck.

    Importe der Hardware-Bibliotheken sind absichtlich hier lokal, damit das
    Modul auch auf Systemen ohne Blinka/PN532 importierbar und per
    ``py_compile`` pruefbar bleibt.
    """
    import board  # type: ignore[import-not-found]
    import busio  # type: ignore[import-not-found]
    from adafruit_pn532.i2c import PN532_I2C  # type: ignore[import-not-found]

    i2c = busio.I2C(board.SCL, board.SDA)
    pn532 = PN532_I2C(i2c, debug=False)

    ic, ver, rev, support = pn532.firmware_version
    log.info("PN532 erkannt: IC=0x%02x firmware=%d.%d", ic, ver, rev)

    # Karte konfigurieren, um MiFare-Karten zu lesen.
    pn532.SAM_configuration()
    return pn532


def _read_uid(pn532, timeout_s: float) -> bytes | None:
    """Liest eine Karten-UID oder gibt ``None`` zurueck, wenn keine Karte da ist."""
    uid = pn532.read_passive_target(timeout=timeout_s)
    if uid is None:
        return None
    return bytes(uid)


# ---------------------------------------------------------------------------
# Hauptschleife
# ---------------------------------------------------------------------------
def run(config: Config) -> None:
    backend = BackendClient(config)

    pn532 = None
    backoff = config.retry_backoff_start_s
    last_uid: str | None = None
    last_scan_ts = 0.0

    log.info(
        "SpotFam Pi-Leser startet: reader_id=%s backend=%s",
        config.reader_id,
        config.backend_base_url,
    )

    while True:
        # (Re)Initialisierung des Lesers mit exponentiellem Backoff.
        if pn532 is None:
            try:
                pn532 = _init_reader()
                backoff = config.retry_backoff_start_s
            except Exception as exc:  # Hardware-/Treiberfehler
                log.error(
                    "PN532-Init fehlgeschlagen (%s); neuer Versuch in %.1fs",
                    exc,
                    backoff,
                )
                time.sleep(backoff)
                backoff = min(backoff * 2, config.retry_backoff_max_s)
                continue

        # Karte lesen.
        try:
            uid_bytes = _read_uid(pn532, config.poll_interval_s)
        except Exception as exc:  # Lesefehler -> Leser neu initialisieren
            log.error("Lesefehler am PN532 (%s); reinitialisiere Leser", exc)
            pn532 = None
            time.sleep(backoff)
            backoff = min(backoff * 2, config.retry_backoff_max_s)
            continue

        if uid_bytes is None:
            # Keine Karte im Feld -> Entprellungs-Status zuruecksetzen, damit
            # dieselbe Karte nach Entfernen erneut gewertet wird.
            last_uid = None
            continue

        card_uid = normalize_uid(uid_bytes)
        now = time.monotonic()

        # Lokale Entprellung: gleiche Karte innerhalb des Cooldowns ignorieren.
        # (Das Backend entprellt zusaetzlich serverseitig.)
        if card_uid == last_uid and (now - last_scan_ts) < config.scan_cooldown_s:
            continue

        last_uid = card_uid
        last_scan_ts = now

        # Scan melden; Netzfehler -> Backoff, aber Leser bleibt initialisiert.
        try:
            backend.send_scan(card_uid)
            backoff = config.retry_backoff_start_s
        except requests.RequestException as exc:
            log.error(
                "Scan-Upload fehlgeschlagen (%s); erneuter Versuch in %.1fs",
                exc,
                backoff,
            )
            # Entprellung zuruecksetzen, damit der Scan nach Erholung erneut
            # gesendet werden kann.
            last_uid = None
            time.sleep(backoff)
            backoff = min(backoff * 2, config.retry_backoff_max_s)


def main() -> int:
    logging.basicConfig(
        level=os.environ.get("LOG_LEVEL", "INFO").upper(),
        format="%(asctime)s %(levelname)s %(name)s: %(message)s",
        stream=sys.stdout,
    )
    config = Config.from_env()
    try:
        run(config)
    except KeyboardInterrupt:
        log.info("Beendet (KeyboardInterrupt).")
        return 0
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
