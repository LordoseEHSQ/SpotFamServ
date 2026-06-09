# Sprint 09 – Starter-Prompt

**Vorheriger Sprint:** Sprint 08 – PN532 Reader-Firmware, OTA & Reader Admin UI (`v0.8.0`)  
**Branch-Ausgangspunkt:** `main` nach Squash-Merge von `feat/pn532-reader-ota`

---

## Aktueller verifizierter Stand

- **ESP32 Reader** (`esp-f61155e31112`): provisioniert, verbunden, scannt Karten, Spotify spielt
- **Backend v0.8.0**: Reader-Diagnostik (last_seen_at, firmware_version, board, fw_channel, last_ip), OTA-Manifest-Heartbeat, scan-events-Filter
- **Frontend**: Reader-Zentrale mit Status, Firmware-Badge, Operator-Hints, 5-Schritt-Onboarding-Wizard
- **Firmware v0.8.2**: PN532/I2C, NVS-Provisioning, Captive Portal, OTA-Pull-Check
- **Pi**: PostgreSQL-Migration gelaufen, docker-compose.override.yml für Frontend-Dist vorhanden

## Offene Punkte (Blockierer für User/Hardware)

1. **Pi Deploy-Mechanismus:** Pi springt bei Auto-Deploy auf letzten Release-Tag zurück (L-035). Nach Sprint-08-Merge + `v0.8.0`-Tag läuft der Pi-Deploy sauber durch (Tag → pi-deploy.sh).
2. **OTA E2E:** Firmware-Artefakt muss via Provisioning-Upload hochgeladen werden, dann prüft ESP beim nächsten Manifest-Check (60 min) ob Update verfügbar ist. Hardware-Action: 60 min warten oder ESP neustarten.
3. **Zweiter ESP:** Via Onboarding-Wizard (`/readers` → „Reader einrichten") provisionieren, USB-Kabel in Pi, dann Captive-Portal befüllen.

## Vorgeschlagene Sprint-09-Ziele

Wähle nach Priorität:

**Option A – OTA & zweiter Reader (ESP-fokussiert)**
- OTA E2E verifizieren (Artefakt hochladen → ESP updated automatisch)
- Zweiten ESP32 provisionieren + E2E testen
- ESP-Manifest-Intervall in Firmware-Config konfigurierbar machen (aktuell hardcoded 60 min)

**Option B – Audio-Extraktor Härtung (v0.7.1-Backlog)**
- UX: Warteschlangen-Card immer rendern, Error-State, Toast-System (`sonner`)
- „Erneut versuchen" je failed-Zeile, yt-dlp-Fehlertext übersetzen
- Deploy-Härtung L-034 (Worker-Crash-Loop)

**Option C – Reader-Lifecycle-Vollständigkeit**
- API-Key-Rotation UI (Reader-Detail-Seite)
- Reader-Lösch-Funktion (inkl. Scan-Events-Cleanup)
- `pm.max_children` PHP-FPM auf 10 erhöhen (L-036-Follow-up)

## Erste Aktion (Sprint 09)

```bash
cd ~/SpotFamServ && git fetch origin
git worktree add ../SpotFamServ-sprint-09 -b feat/sprint-09-<thema> origin/main
```

Dann plan-<thema>.md schreiben + User-Bestätigung einholen.

## Referenzen

- `docs/PROJECT_MAP.md` · `tasks/todo.md` · `tasks/decisions.md` · `tasks/lessons.md`
- `docs/sprints/sprint-08.md` (Sprint 08 Closeout)
- `docs/esp-reader-provisioning.md` (ESP-Provisioning-Runbook)
- `docs/flash-station-runbook.md` (Flash-Station-Runbook)
- Regeln: `planning-discipline.mdc`, `sprint-workflow.mdc`, `chat-isolation-swarm.mdc`
