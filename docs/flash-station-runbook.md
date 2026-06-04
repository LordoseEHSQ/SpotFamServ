# Flash-Station (Reader-Station) – Runbook

Stand: 2026-06-04 · Plan: `tasks/plan-pi-flash-provisioning-station.md` · Decisions: D-021–D-025

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
  --version=1.0.0 \
  --file=<dateiname-in-FIRMWARE_DIR> \
  --expected-chip=ESP32-D0WD-V3
```

Der Command berechnet **sha256** und **Größe** und legt einen `FlashArtifact`-Datensatz an.
Ohne Registrierung ist kein Flash-Job möglich (kein freier Upload — **D-025**).

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
- **Kein freier Firmware-Upload** über die Web-UI — nur vorab registrierte Artefakte
  (**D-025**).
- Vor dem Flash: **sha256** der Datei + **Chip-Whitelist** (Mismatch → Abbruch in UI/Agent).
- **esptool** nur als **Argument-Array** (keine Shell-Interpolation).

Details: `tasks/decisions.md` (**D-024**, **D-025**).

## Strom / Bricking

- Ungedämpftes USB oder schwaches Kabel → abgebrochener Flash; **qualitatives Kabel** und
  bei mehreren ESPs **Hub mit eigener Stromversorgung**.
- `write_flash` mit Verify (Flash-Hash); ESP32-ROM-Bootloader bleibt per erneutem Flash
  erreichbar (kein „hartes" Brick im Normalfall).

## Bekannte Grenzen

- **HW-0 (PN532 löten + UID lesen)** wurde bewusst übersprungen (**D-022**). Der funktionale
  RFID-Pfad (Karte → UID → Scan/Play) ist **nicht verifiziert**; bewiesen sind nur **Flash-Pfad**
  und **Chip-Detection**.
- **Live-Status = Polling** (~2–5 s), kein WebSocket/SSE (**D-023**).
- **Artefakt-Upload-UI fehlt** — Registrierung nur per Console-Command
  `app:provisioning:register-artifact`.
- **Chip-Whitelist** startet mit `ESP32-D0WD*`; andere Chips werden abgelehnt, bis erweitert.
