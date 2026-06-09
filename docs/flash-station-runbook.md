# Flash-Station (Reader-Station) – Runbook

Stand: 2026-06-08 · Plan: `tasks/plan-pn532-reader-firmware-ota.md` · Decisions: D-021–D-025

## Überblick

Der **Flash-Agent** auf dem Pi (Host, systemd) übernimmt USB-Erkennung, Chip-Detection und
esptool-Flash; das **Symfony-Backend** in Docker hat keinen USB-Zugriff und steuert Jobs,
Artefakt-Registry und Audit; die **Web-UI** („Reader-Station") startet Flashes und zeigt den
Fortschritt per **HTTP-Polling** (kein WebSocket — siehe **D-023**).

```
  [Browser] --HTTP--> [nginx + PHP Backend (Docker)]
                            |
                     REST /api/v1/provisioning/*
                            |
                     [Flash-Agent (Host, systemd)]
                            |
                     USB --> [ESP32]
```

## Komponenten & Pfade

| Bereich | Pfad |
|---------|------|
| Flash-Agent (Python) | `firmware/flash_agent/` |
| Backend-Modul | `backend/src/Module/Provisioning/` |
| Frontend-Seite | `frontend/src/pages/ProvisioningPage.tsx` |
| systemd-Unit (Vorlage) | `deploy/systemd/spotfam-flash-agent.service` |
| Runbook | `docs/flash-station-runbook.md` (diese Datei) |

API-Basis: `/api/v1/provisioning/*` (sieben Endpunkte: drei Agent-, vier Admin-Routen).

## Voraussetzungen am Pi

- **Python 3** (für venv + Agent).
- **esptool v5.x** im PATH (`esptool version`).
- Benutzer des Agents in Gruppe **`dialout`** (serielle Ports `/dev/ttyUSB*` / `ttyACM*`).
- Verzeichnis **`backend/var/firmware`** (bzw. per Env **`FIRMWARE_DIR`**) muss **existieren** —
  Symfony legt `var/` nicht automatisch an; ohne Verzeichnis schlagen Registrierung/Flash fehl.
- Agent- und Backend-**`FIRMWARE_DIR`** müssen auf dasselbe Host-Verzeichnis zeigen (gemeinsame
  Artefakt-Ablage, **D-025**).

## Agent installieren

```bash
cd /home/lars/SpotFamServ/firmware/flash_agent
python3 -m venv .venv
.venv/bin/pip install -r requirements.txt
cp secrets.example.env secrets.env
# secrets.env bearbeiten: BACKEND_BASE_URL, FLASH_AGENT_API_KEY, FIRMWARE_DIR, ggf. POLL_INTERVAL_S, FLASH_BAUD
chmod 600 secrets.env
```

**`secrets.env` (Pflicht):**

| Variable | Zweck |
|----------|--------|
| `BACKEND_BASE_URL` | Backend ohne Trailing-Slash (Pi: z. B. `http://127.0.0.1:8080`) |
| `FLASH_AGENT_API_KEY` | Dedizierter Agent-Key (**D-024**), Header `X-API-Key` |
| `FIRMWARE_DIR` | Gemeinsames Artefakt-Verzeichnis (z. B. `/home/lars/SpotFamServ/backend/var/firmware`) |
| `POLL_INTERVAL_S` | Optional, Scan-Intervall |
| `FLASH_BAUD` | Optional, esptool-Baud |

**systemd** (Pfade in der Unit anpassen, dann):

```bash
sudo cp deploy/systemd/spotfam-flash-agent.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now spotfam-flash-agent.service
journalctl -u spotfam-flash-agent -f
```

**CLI (Diagnose, ohne Daemon):**

```bash
.venv/bin/python -m flash_agent detect
.venv/bin/python -m flash_agent flash --dry-run --port /dev/ttyUSB0 --artifact <datei.bin>
```

## Artefakt registrieren

Firmware-Datei **vorher** nach `FIRMWARE_DIR` kopieren (nur Dateiname, kein Unterpfad mit `/`).

Im Backend-Container bzw. auf dem Host mit PHP:

```bash
cd backend
php bin/console app:provisioning:register-artifact \
  --board=esp32-wroom-32 \
  --channel=stable \
  --firmware-version=1.0.0 \
  --file=<dateiname-in-FIRMWARE_DIR> \
  --expected-chip=ESP32-D0WD-V3
```

Der Command berechnet **sha256** und **Größe** und legt einen `FlashArtifact`-Datensatz an.
Ohne Registrierung ist kein Flash-Job möglich (kein freier Upload — **D-025**).

### PN532-Reader-Firmware bauen

Die Ziel-Firmware fuer ESP32-WROOM-32 + PN532/HW-147 wird mit OTA-faehiger
4-MB-Partition gebaut:

```bash
cd firmware/spotfam_reader
cp secrets.h.example secrets.h # nur fuer lokalen Dev-/CI-Fallback, echte Werte nie committen
arduino-cli compile --fqbn esp32:esp32:esp32:PartitionScheme=no_fs --export-binaries .
```

Das Artefakt danach in `FIRMWARE_DIR` kopieren und registrieren:

```bash
php backend/bin/console app:provisioning:register-artifact \
  --board=esp32-wroom-32 \
  --channel=stable \
  --firmware-version=0.8.0 \
  --file=<exportiertes-bin> \
  --expected-chip=ESP32-D0WD-V3
```

`PartitionScheme=no_fs` ist Pflicht fuer OTA: zwei App-Slots, kein SPIFFS.

## Ablauf Flashen

1. ESP32 per USB am Pi anschließen (gutes Kabel; bei mehreren Geräten **powered USB-Hub**).
2. Agent meldet das Gerät → erscheint in der UI **Reader-Station** (Polling).
3. Registriertes **Artefakt** wählen → **Flashen** starten.
4. Fortschritt in der UI (Job-Status per Polling) und im Journal (`spotfam-flash-agent`).

**Concurrency:** maximal **ein aktiver Job** (`pending`/`running`) pro erkanntem Gerät;
zweiter Start → **HTTP 409**. Der Agent arbeitet Flashes seriell (Port-Lock).

## Sicherheit

- **`FLASH_AGENT_API_KEY`** in Produktion setzen und rotierbar halten; getrennt vom
  `READER_API_KEY` (**D-024**).
- **Firmware-Upload nur authentifiziert** (Admin, `ROLE_ADMIN`) über die Web-UI bzw. API;
  Server berechnet sha256 + registriert (**D-027**, ersetzt das frühere „kein Upload" aus D-025).
- Vor dem Flash: **sha256** der Datei + **Chip-Whitelist** (Mismatch → Abbruch in UI/Agent).
- **esptool** nur als **Argument-Array** (keine Shell-Interpolation).

Details: `tasks/decisions.md` (**D-024**, **D-025**, **D-027**).

## Troubleshooting (real aufgetretene Stolpersteine v0.5.1–v0.5.7)

| Symptom | Ursache | Fix |
|---|---|---|
| Deploy meldet Fehler trotz laufender App | Healthcheck pollte geschützten Endpunkt (401) | öffentlicher `GET /api/v1/health` (v0.5.1) |
| `/provisioning/devices` → 500 | Doctrine-Mapping `Provisioning` fehlte | Mapping in `doctrine.yaml` (v0.5.2) |
| Agent meldet nie ein Gerät | esptool **v5.3** gibt `Chip type:` statt `Chip is` | Parser akzeptiert beide Formate (v0.5.3) |
| `GET /jobs/next` → 500 (`"next"` als UUID) | Admin-Route `/jobs/{jobId}` fängt `/jobs/next` | Route-`priority` auf `/jobs/next` (v0.5.4) |
| Upload → HTTP 413 | nginx `client_max_body_size` Default 1 MB | `client_max_body_size 16m` (v0.5.5) |
| Upload → HTTP 500 (`Datei konnte nicht gespeichert werden`) | PHP `upload_max_filesize=2M` < Artefakt **und** Backend-`FIRMWARE_DIR` leer | PHP-Limits 16M (v0.5.6) + `FIRMWARE_DIR` in `backend/.env.local` setzen |
| Jeder Flash-Job → `[Errno 9] Bad file descriptor` | `PortLock` hielt FD ohne File-Referenz (GC schloss FD) | File-Objekt halten + im `__exit__` schließen (v0.5.7) |
| Agent: `esptool` not found (systemd) | venv-Pfad nicht im Service-Env | `ESPTOOL_BIN=<venv>/bin/esptool` in `secrets.env` |

**Backend-`FIRMWARE_DIR` (Container):** muss in `backend/.env.local` gesetzt sein (z. B.
`FIRMWARE_DIR=/var/www/html/var/firmware`) und auf dasselbe Host-Volume zeigen wie der Agent
(`/home/lars/SpotFamServ/backend/var/firmware`). Sonst landet der Upload im Nirgendwo bzw.
der Agent findet die Datei nicht.

## Strom / Bricking

- Ungedämpftes USB oder schwaches Kabel → abgebrochener Flash; **qualitatives Kabel** und
  bei mehreren ESPs **Hub mit eigener Stromversorgung**.
- `write_flash` mit Verify (Flash-Hash); ESP32-ROM-Bootloader bleibt per erneutem Flash
  erreichbar (kein „hartes" Brick im Normalfall).

## Bekannte Grenzen

- **HW-0 (PN532 UID + Backend-Scan)** ist noch nicht vollstaendig gruen. Am 2026-06-08 wurde
  PN532-Erkennung am ESP beobachtet (`PN532 gefunden`), aber UID-Gleichheit Pi↔ESP, echter
  Backend-Scan, Power-Cycle und OTA-Test bleiben Pflicht-Evidence.
- **Live-Status = Polling** (~2–5 s), kein WebSocket/SSE (**D-023**).
- **Artefakt-Upload** jetzt über authentifizierte Web-UI/API (**D-027**); Console-Command
  `app:provisioning:register-artifact` bleibt als Alternative.
- **Chip-Whitelist** startet mit `ESP32-D0WD*`; andere Chips werden abgelehnt, bis erweitert.
- **OTA-Herkunft:** Manifest/Binary nutzen SHA-256 gegen Korruption. `signature: null` ist ein
  lokales LAN-MVP-Risiko, keine Herkunftssicherung fuer Consumer-Release.

> **Verifiziert (2026-06-05):** Erster echter End-to-End-Flash (Upload → Job → esptool-`write_flash`)
> gegen ESP32-D0WD-V3 erfolgreich (`success`, progress 100). Der **RFID-Funktionspfad** (PN532,
> Karte → UID → Play) bleibt weiterhin offen (HW-0, **D-022**).
