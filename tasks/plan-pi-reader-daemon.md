# Plan: Pi-Leser als Reader + Enrollment-Station

**Erstellt:** 2026-06-02
**Status:** Plan – wartet auf Bestaetigung, NICHT umgesetzt

## Scope
Der am Pi angeschlossene RFID-Leser wird als vollwertiger Reader nutzbar: ein kleiner
Daemon auf dem Pi liest Karten und postet an `/api/v1/readers/scan` – exakt wie ein ESP.
Zusaetzlich dient er als **Enrollment-Station**: unbekannte Karte scannen → UID erscheint
sofort im Frontend zur Zuordnung (Scan-to-Enroll), statt UID manuell abzutippen.

## Bestaetigte Entscheidungen (User, 2026-06-02)
- Pi-Leser soll genutzt werden (heute by-design ignoriert: „Reader = ESP, nicht Pi").
- Diese Regel wird damit bewusst erweitert (Pi als zusaetzlicher Reader-Typ).

## Betroffene Bereiche
- **Neu:** `firmware/pi_reader/` oder `tools/pi-reader/` — Daemon (Sprache offen, s. D-P1).
- **Pi/Infra** — systemd-Service oder Container fuer den Daemon; Hardware-Zugriff (SPI/USB).
- `Scan/` Backend — unveraendert (nutzt den bestehenden `/scan`-Vertrag). Ggf. Auto-Upsert `reader_device` (siehe Plan Reader→Box, D-R1).
- **Frontend (optional, Enrollment-UX):** „Letzte unbekannte UID uebernehmen" auf der Karten-Seite (`CardsPage`/`ScanLogsPage`), Polling auf `scan-events`.

## Hardware (bestaetigt, User 2026-06-02): HW-147 = PN532

Der Pi-Leser ist ein **HW-147**, also ein **NXP-PN532-NFC/RFID-Modul** (13,56 MHz, MIFARE
Classic 1K/4K, NTAG u.a.). Wichtig: gleiche Frequenz/Kartenfamilie wie der **MFRC522 am ESP**
→ liest dieselben Karten, UID ist die physische Karten-UID. Das Modul kann **I2C, SPI oder
UART/HSU** (Onboard-Umschalter; Default HSU). Python-Anbindung am Pi sauber via
**Adafruit CircuitPython PN532** (`adafruit-circuitpython-pn532` + Blinka).

### D-P1 — PN532-Interface auf dem Pi (kleine Restentscheidung)
- **A) I2C:** wenigste Adern (SDA/SCL + 3V3/GND), Umschalter auf I2C. Empfehlung (einfachste Verkabelung, stabil fuer einen Leser).
- **B) UART/HSU:** Board-Default; Pi-UART freigeben (serielle Konsole deaktivieren).
- **C) SPI:** mehr Adern; nur falls parallel viel SPI-Last.
→ Empfehlung **A (I2C)**, sofern keine Pin-Konflikte. Finalisierung beim Verkabeln.

### D-P2 — Daemon-Laufzeit
- **A) Host-systemd-Dienst (Python):** direkter I2C/GPIO-Zugriff via Blinka, einfachste Anbindung. **Empfehlung.**
- **B) Container:** muss `/dev/i2c-*` durchreichen → mehr Reibung; kein Mehrwert hier.

## 4-Lens-Analyse (Pflicht)

### Lens 1 – Runtime & Sprache
- Pi: Debian 13 (trixie), aarch64, systemd, Python 3 vorhanden. **Python-Daemon** mit Adafruit CircuitPython PN532.
- Keine Beruehrung der Backend-Runtime.

### Lens 2 – Frameworks & Abhaengigkeiten
- Neue (kleine) Dependencies nur auf dem Pi-Daemon: `adafruit-circuitpython-pn532` + `adafruit-blinka` (Hardware-Zugriff), `requests` fuer HTTP. In venv, nicht systemweit. Kein Impact auf Backend-`composer.lock`.
- I2C/SPI/UART in Raspberry Pi OS aktivieren (`raspi-config`/`config.txt`), je nach D-P1.

### Lens 3 – Build, CI/CD & Tooling
- Daemon ist Teil des Repos, wird per Auto-Deploy auf den Pi gezogen, aber **nicht** im Docker-Stack (Host-Dienst). systemd-Unit + Install-Hinweis.
- Wenn der Daemon einen eigenen `READER_ID`+Key bekommt: Secrets git-ignoriert (wie ESP `secrets.h`).

### Lens 4 – Security & Compliance
- Daemon authentisiert wie ein Reader (X-API-Key). Mit Plan OTA/Per-Reader-Key bekommt er einen **eigenen** Key (kein geteiltes Shared Secret).
- Laeuft als unprivilegierter User mit Zugriff auf die noetige Geraetegruppe (`spi`/`input`/`dialout`), nicht als root.

## Cross-Module Antworten
1. **Upstream:** neuer Producer fuer `/scan` – nutzt exakt den bestehenden Vertrag, kein Eingriff noetig.
2. **Downstream:** Backend/Spotify unveraendert. Enrollment-UX konsumiert `GET /readers/scan-events` (existiert bereits).
3. **Audit:** Scans werden ohnehin in `scan_event` geloggt. Neuer Reader = neue `reader_id` (Auto-Upsert, s. D-R1).
4. **API-Vertrag:** keine Backend-Shape-Aenderung. Optionale Enrollment-UI nutzt vorhandene Endpunkte.
5. **Feature-Flags:** nicht noetig.

## Geplante Umsetzung (nach Bestaetigung + D-P1/D-P2)
1. Python-Daemon (Adafruit PN532): Karte lesen → UID-Bytes auf kanonisches Hex (Grossbuchstaben, ohne Trenner) normalisieren → POST `/scan` mit eigener `reader_id` + Key, Retry/Reconnect, Logging.
2. `secrets`-Datei (git-ignoriert) fuer `BACKEND_BASE_URL`, `READER_ID`, Key.
3. systemd-Unit (D-P2 A) + kurze Install-Doku (`firmware/pi_reader/README.md`): SPI/USB aktivieren, Gruppe, enable/start.
4. **Enrollment-UX (optional, empfohlen):** auf der Karten-Seite „Zuletzt gescannte unbekannte UID" anzeigen + Ein-Klick „Karte anlegen mit dieser UID" (Polling `scan-events`, filtert `unknown_card`).
5. Doku in `docs/` + Verweis aus `pi-deployment.md`.

## Akzeptanzkriterien
1. Karte am Pi-Leser → `scan_event` im Backend, gleiche Wirkung wie ESP-Scan.
2. UID-Format des Pi-Lesers ist **identisch** zur ESP-Normalisierung (gleiche Karte = gleiche UID-Zeichenkette).
3. Daemon startet nach Pi-Reboot automatisch (systemd `enable`), reconnektet bei Netz-/Leserfehler.
4. (Falls Enrollment-UI) Unbekannte Karte am Pi-Leser erscheint binnen Sekunden im Frontend und ist per Klick anlegbar.

## Definition of Done
- [ ] Daemon getestet gegen echtes Backend (Scan → success/unknown_card)
- [ ] UID-Normalisierung verifiziert gegen eine ESP-bekannte Karte (Byte-fuer-Byte gleich)
- [ ] systemd-Unit: Autostart + Reconnect verifiziert
- [ ] Install-Doku + `pi-deployment.md`-Verweis
- [ ] (Falls UI) Enrollment-Flow E2E getestet
- [ ] Cross-Module-Checkliste beantwortet

## Risiken / Offene Fragen
- **UID-Abgleich PN532 vs. MFRC522:** beide lesen dieselbe physische Karten-UID, aber 4- vs. 7-Byte-Laenge und Byte-Reihenfolge der Lib pruefen. **Pflicht-Verifikation:** eine bereits am ESP angelegte Karte am Pi-Leser scannen → UID-String muss byte-fuer-byte identisch sein. Sonst Normalisierung anpassen.
- Interface-Wahl (D-P1) erst beim Verkabeln final; I2C empfohlen.
- Daemon ist Host-Dienst (nicht im Compose-Stack) → eigener Update-/Restart-Pfad; im Auto-Deploy mitdenken.
- Abgrenzung zu Plan OTA: hier KEIN ESP-Provisioning, nur Pi-eigener Leser.

## Verifikations-Log
{Beim Umsetzen ausfuellen}

## Abgeschlossen
{Datum + Summary wenn fertig}
