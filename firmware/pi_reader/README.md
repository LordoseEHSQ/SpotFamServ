# SpotFam Pi-Leser (PN532 / HW-147)

Zweiter RFID-Leser fuer SpotFam, der **direkt auf dem Raspberry Pi** laeuft –
als unprivilegierter Python-`systemd`-Dienst. Liest 13,56-MHz-Karten
(MIFARE Classic / NTAG) ueber einen **NXP PN532 (Modul HW-147)** am **I2C-Bus**
und meldet jeden Scan per HTTP an das SpotFam-Backend.

Funktional identisch zur ESP32/MFRC522-Firmware (`firmware/spotfam_reader/`):
gleiche Backend-API, **gleiches UID-Format**. Unterschied: kein WLAN/ESP, sondern
lokaler Host-Dienst; keine Vor/Zurueck-Taster (nur Scan-to-Play / Scan-to-Enroll).

> Architektur: PN532 → Pi (Python) → HTTP → Backend → Spotify Web API → Spotify-Connect-Gerät.
> Es liegen **keine Spotify-Tokens** auf dem Leser.

Bezug: Decision **D-017** (Pi-Leser = HW-147 = PN532), **D-P1 A** (I2C),
**D-P2 A** (Python-Host-`systemd`-Dienst, kein Container – wegen direktem
Hardware-/I2C-Zugriff).

---

## ⚠️ Hardware-Verifikation offen

Dieser Code wurde **ohne reale Hardware** geschrieben und ist daher **nicht
end-to-end getestet**. Vor Produktivbetrieb verifizieren:

1. **PN532 wird am I2C erkannt** (`i2cdetect`, siehe unten – erwartet `0x24`).
2. **UID-Gleichheit ESP ↔ Pi:** Dieselbe bekannte Karte an MFRC522 **und** PN532
   halten und pruefen, dass im Backend-Scan-Log (`card_uid_raw`) **derselbe
   String** erscheint (z. B. `04A1B2C3D4E5F6`). Falls nicht identisch →
   Byte-Reihenfolge/Laenge in `normalize_uid()` anpassen (siehe Code-Kommentar).
3. **Scan-to-Play / Scan-to-Enroll** funktioniert (bekannte Karte startet
   Playlist; unbekannte Karte erzeugt `unknown_card` und erscheint im Frontend).

---

## UID-Format (muss zur ESP-Firmware passen)

Die ESP-Firmware (`firmware/spotfam_reader/spotfam_reader.ino`, `uidToHex()`)
erzeugt das **kanonische** Format:

- Hex, **GROSSBUCHSTABEN**
- exakt **2 Zeichen pro Byte** (fuehrende Null)
- **ohne Trennzeichen**
- Byte-Reihenfolge **wie vom Leser geliefert** (keine Umkehr)
- Laenge = `2 × Anzahl UID-Bytes` (4-Byte- und 7-Byte-UIDs unveraendert)

`pi_reader.py::normalize_uid()` repliziert das 1:1
(`"".join(f"{b:02X}" for b in uid_bytes)`). Die Adafruit-PN532-Lib liefert die
UID via `read_passive_target()` als `bytearray` in derselben natuerlichen
Reihenfolge wie `MFRC522::Uid.uidByte`. **Annahme** (siehe Verifikation oben):
beide Leser liefern Reihenfolge/Laenge identisch.

---

## 1. Verkabelung (I2C)

PN532-Modul (HW-147) **auf I2C-Modus stellen** (DIP-Schalter/Lötjumper am Modul
beachten – je nach Variante `SET0=0 / SET1=1`).

| PN532 | Raspberry Pi (40-Pin) | BCM    |
| ----- | --------------------- | ------ |
| VCC   | Pin 1 (3V3)           | 3V3    |
| GND   | Pin 6 (GND)           | GND    |
| SDA   | Pin 3 (SDA1)          | GPIO 2 |
| SCL   | Pin 5 (SCL1)          | GPIO 3 |

> **3V3, nicht 5V** an VCC (Pi-I2C ist 3V3-Logik). Viele HW-147-Module
> akzeptieren 3,3 V – Datenblatt der konkreten Variante pruefen.

---

## 2. I2C am Pi aktivieren

```bash
sudo raspi-config        # Interface Options -> I2C -> enable
# oder headless:
sudo raspi-config nonint do_i2c 0
```

`/boot/firmware/config.txt` (bzw. aelter `/boot/config.txt`) muss enthalten:

```ini
dtparam=i2c_arm=on
```

Tools + Geraet pruefen (PN532 meldet sich i. d. R. auf Adresse `0x24`):

```bash
sudo apt-get install -y i2c-tools
sudo reboot            # falls I2C frisch aktiviert
i2cdetect -y 1         # 0x24 sollte erscheinen
```

Benutzer in die `i2c`-Gruppe (Zugriff ohne root):

```bash
sudo usermod -aG i2c "$USER"
# neu einloggen, damit die Gruppe greift
```

---

## 3. Installation (venv + Abhaengigkeiten)

```bash
cd /home/lars/SpotFamServ/firmware/pi_reader
python3 -m venv .venv
source .venv/bin/activate
pip install --upgrade pip
pip install -r requirements.txt
```

---

## 4. Konfiguration (Secrets)

```bash
cp secrets.example.env secrets.env
# secrets.env mit echten Werten fuellen:
#   BACKEND_BASE_URL, READER_ID (muss im Backend registriert sein), READER_API_KEY
```

`secrets.env` ist git-ignoriert und darf **nie** committet werden.

Manueller Test (mit aktivem venv, Hardware angeschlossen):

```bash
set -a; source secrets.env; set +a
python pi_reader.py
# Karte auflegen -> Logzeile "scan ... outcome=..." erwarten
```

---

## 5. systemd-Dienst (Autostart)

```bash
# Pfade in der Unit ggf. anpassen (User/Verzeichnis/venv)
sudo cp spotfam-pi-reader.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now spotfam-pi-reader.service

# Status & Logs
systemctl status spotfam-pi-reader.service
journalctl -u spotfam-pi-reader -f
```

Der Dienst laeuft **unprivilegiert** (User `lars`, Zusatzgruppen `i2c`/`gpio`),
startet bei Absturz automatisch neu (`Restart=on-failure`) und loggt nach
journald.

---

## 6. Backend-Vertrag

- `POST /api/v1/readers/scan` mit `{ "reader_id": "...", "card_uid": "..." }`,
  Header `X-API-Key: <READER_API_KEY>`. Antwort: `{ "outcome": "...", "message": "..." }`.
- Unbekannte Karte → `outcome = "unknown_card"`, erscheint im Frontend unter
  **Scan-Logs** und im **Scan-to-Enroll** der Karten-Seite.

## Troubleshooting

| Symptom | Ursache / Fix |
| --- | --- |
| `i2cdetect` zeigt kein `0x24` | I2C nicht aktiv, Modul nicht im I2C-Modus, Verkabelung/3V3 pruefen |
| `PN532-Init fehlgeschlagen` im Log | Treiber/venv, I2C-Rechte (`i2c`-Gruppe), Adresse |
| `outcome=unknown_reader` | `READER_ID` im Backend registrieren |
| UID ≠ ESP-UID | Byte-Reihenfolge/Laenge in `normalize_uid()` anpassen (siehe Verifikation) |
