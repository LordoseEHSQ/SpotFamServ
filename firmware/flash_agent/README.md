# SpotFam Flash-Agent

Automatisiertes ESP32-Provisioning-Werkzeug fuer den Raspberry Pi.
Erkennt angeschlossene ESP32-Geraete per USB-Serial, fragt das SpotFam-Backend
nach ausstehenden Flash-Jobs und flasht Firmware sicher per **esptool v5**.

> Architektur: ESP32 (USB) → Pi (Flash-Agent) → esptool → ESP32-Flash.
> Provisioning-Status wird dem Backend via HTTP gemeldet.

Entscheidungs-Bezug: **D-024** (Auth FLASH_AGENT_API_KEY), **D-025** (lokaler
Artefakt-Transfer, sha256, Chip-Whitelist).

---

## Sicherheitsdesign

| Massnahme | Umsetzung |
|---|---|
| Keine Command-Injection | esptool immer als Argument-Array (`subprocess.run(list)`, **kein** `shell=True`) |
| Kein Binary-Download | Artefakte liegen lokal in `FIRMWARE_DIR`; Agent loest nur Dateinamen auf |
| Path-Traversal-Schutz | Kein `/`, `..`, Null-Byte im Dateinamen; realpath-Kindcheck |
| sha256-Verifikation | Vor jedem Flash; Mismatch → sofortiger Abbruch |
| Chip-Whitelist | Unbekannte/abweichende Chips werden **verweigert**, nicht geraten |
| Port-Lock | fcntl exklusiv, nicht-blockierend – kein gleichzeitiger Flash |
| Least-Privilege | systemd-Unit laeuft als `lars`, Gruppe `dialout` (kein root) |

---

## Haertevoraussetzungen (Hardware-Verifikation offen)

Dieser Code wurde **ohne echte Hardware** entwickelt. Vor Produktivbetrieb:

1. **esptool erkennt ESP32**: `esptool --port /dev/ttyUSB0 chip-id` gibt
   `Chip is ESP32-D0WD-V3 ...` aus.
2. **Flash-Baud passt**: 460800 funktioniert auf dem Pi; bei Instabilitaet
   auf 115200 reduzieren (`FLASH_BAUD=115200`).
3. **sha256-Hashes stimmen**: Der Hash muss vom Build-System generiert und
   im Backend hinterlegt sein, bevor ein Job erstellt wird.
4. **Dialout-Gruppe**: `sudo usermod -aG dialout lars` + neu einloggen.

---

## 1. Voraussetzungen

```bash
# esptool v5 installieren (System oder venv):
pip install "esptool>=5,<6"

# Benutzer in dialout-Gruppe (serieller Port ohne root):
sudo usermod -aG dialout "$USER"
# Neu einloggen, damit die Gruppe greift.

# Geraet pruefen:
ls /dev/ttyUSB*
esptool --port /dev/ttyUSB0 chip-id
```

---

## 2. Installation (venv)

```bash
cd /home/lars/SpotFamServ/firmware/flash_agent
python3 -m venv .venv
source .venv/bin/activate
pip install --upgrade pip
pip install -r requirements.txt
```

---

## 3. Konfiguration (Secrets)

```bash
cp secrets.example.env secrets.env
# secrets.env mit echten Werten fuellen:
#   BACKEND_BASE_URL, FLASH_AGENT_API_KEY, FIRMWARE_DIR
```

`secrets.env` ist git-ignoriert und darf **nie** committet werden.

---

## 4. CLI-Verwendung

### `detect` – Ports + Chip erkennen (kein Backend)

```bash
# Mit aktivem venv:
set -a; source secrets.env; set +a
python -m flash_agent detect
# Ausgabe:
# Port: /dev/ttyUSB0
#   Chip:        ESP32-D0WD-V3
#   Familie:     esp32
#   MAC:         78:EE:4C:01:6B:04
#   Flash:       4MB
```

### `flash --dry-run` – Befehl anzeigen ohne Ausfuehren

```bash
# Artefakt-Datei vorbereiten:
echo "dummy" > /tmp/firmware/merged.bin
SHA=$(sha256sum /tmp/firmware/merged.bin | awk '{print $1}')

FIRMWARE_DIR=/tmp/firmware python -m flash_agent flash \
    --port /dev/ttyUSB0 \
    --artifact merged.bin \
    --expected-chip ESP32-D0WD-V3 \
    --sha256 "$SHA" \
    --dry-run
# Ausgabe:
# sha256 OK: <16-Zeichen>...
# Dry-run – wuerde ausfuehren:
#   esptool --port /dev/ttyUSB0 --baud 460800 write-flash 0x0 /tmp/firmware/merged.bin
```

### `flash` – Tatsaechlicher Flash-Vorgang

```bash
FIRMWARE_DIR=/pfad/zu/artefakten python -m flash_agent flash \
    --port /dev/ttyUSB0 \
    --artifact merged.bin \
    --expected-chip ESP32-D0WD-V3 \
    --sha256 <sha256-hex>
```

### `run` – Dauerhafte Hauptschleife gegen Backend

```bash
set -a; source secrets.env; set +a
python -m flash_agent run
# Logzeilen gehen nach stdout / journald.
```

---

## 5. systemd-Dienst (Autostart)

```bash
# Pfade in der Unit ggf. anpassen (User/Verzeichnis/venv):
sudo cp /home/lars/SpotFamServ/deploy/systemd/spotfam-flash-agent.service \
        /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now spotfam-flash-agent.service

# Status und Logs:
systemctl status spotfam-flash-agent.service
journalctl -u spotfam-flash-agent -f
```

---

## 6. Tests (ohne Hardware)

```bash
cd /home/lars/SpotFamServ/firmware/flash_agent
source .venv/bin/activate
python3 -m pytest tests/ -v
```

Getestet werden (alles ohne echte Hardware):
- esptool-Ausgabe-Parsing (chip-id, flash-id)
- Variantenmatrix (supported/unsupported/match)
- Artefakt-Aufloesung mit Path-Traversal-Abwehr
- sha256-Verifikation (treffer + mismatch)
- CLI `flash --dry-run` (Befehl ausgeben, kein Flash)
- Chip-Mismatch → Abbruch
- sha256-Mismatch → Abbruch

---

## 7. Backend-API-Vertrag

Der Flash-Agent ist ausschliesslich **Client** – er implementiert das Backend nicht.

| Endpunkt | Methode | Beschreibung |
|---|---|---|
| `/api/v1/provisioning/devices/detect` | POST | Geraet melden, deviceId erhalten |
| `/api/v1/provisioning/jobs/next` | GET | Naechsten Job abfragen (204 = kein Job) |
| `/api/v1/provisioning/jobs/{jobId}/status` | POST | Status melden (running/success/failed) |

Auth-Header: `X-API-Key: <FLASH_AGENT_API_KEY>` (D-024).

---

## 8. Chip-Whitelist (variants.py)

Aktuell unterstuetzt:
- `ESP32-D0WD` / `ESP32-D0WD-V3` → Familie `esp32` (klassischer WROOM-32)
- `ESP32-D0WDR2-V3` → Familie `esp32`

Erweiterung: `_CHIP_FAMILIES` in `flash_agent/variants.py` um neue Muster ergaenzen.
Unbekannte Chips werden **immer verweigert** (keine Whitelist-Eintragung → kein Flash).

---

## Troubleshooting

| Symptom | Ursache / Fix |
|---|---|
| `esptool-Binaer nicht gefunden` | esptool nicht im PATH; `ESPTOOL_BIN` setzen |
| `Chip-Erkennung fehlgeschlagen` | Kabel/Port, Auto-Reset via RTS, Baud zu hoch |
| `sha256-Mismatch` | Datei korrupt oder falscher Hash im Backend |
| `Chip-Mismatch` | Falsches Artefakt fuer diesen Chip; `expectedChip` im Backend pruefen |
| `Port gesperrt` | Anderer Flash-Prozess laeuft; Lock-Datei in `/tmp` pruefen |
| `dialout-Berechtigung` | `sudo usermod -aG dialout lars` + neu einloggen |
