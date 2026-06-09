# Sprint 10 – Starter-Prompt

**Vorheriger Sprint:** Sprint 09 – Audio-UX Härtung + Reader-Lifecycle (`v0.9.0`)  
**Branch-Ausgangspunkt:** `main` nach Squash-Merge von `feat/sprint-09-audio-reader`

---

## Aktueller verifizierter Stand

- **v0.9.0 getaggt + CI grün** (squash-merged, PHPUnit 195/195, PHPStan L6, oasdiff clean)
- **Audio-Extraktor:** Warteschlange immer sichtbar, Toasts (sonner), Retry, Dismiss, Fehler-UX
- **Reader-Lifecycle:** Löschen (DELETE /readers/{readerId}), API-Key-Rotation UI
- **Infra:** L-034-Fix (vendor-Volume), PHP-FPM pm.max_children=10, APP_ENV=prod, pi-deploy.sh bereinigt

## Offene Punkte (Blockierer für User/Hardware)

1. **Pi-Deploy (User-Gate):** Nach dem ersten Auto-Deploy muss einmalig manuell ausgeführt werden:
   ```bash
   docker-compose down -v && docker-compose up --build -d
   ```
   Nötig weil das neue anonyme vendor-Volume erst durch `down -v` neu aus dem Image initialisiert wird.

2. **OTA E2E** (aus Sprint 08 offen): Firmware-Artefakt hochladen → ESP prüft beim nächsten Manifest-Check ob Update verfügbar. Hardware-Action nötig.

3. **Zweiter ESP** (aus Sprint 08 offen): Via Onboarding-Wizard provisionieren.

## Vorgeschlagene Sprint-10-Ziele

**Option A – Pi-Stabilität & Monitoring**
- Health-Dashboard: System-Status, Worker-Status, Disk-Usage `/data/audio`
- `pm.max_children` dynamisch aus DB-Config (SystemConfiguration)
- Log-Level konfigurierbar

**Option B – Karten-UX**
- Karten-Seite: Bulk-Delete, Sortierung, Filter
- Card-Detail-Seite: Binding-History
- Unbekannte Karte → Direktzuweisung aus Scan-Log

**Option C – OTA & zweiter Reader (Hardware-fokussiert)**
- OTA E2E verifizieren (Artefakt hochladen → ESP updated automatisch)
- Zweiten ESP32 provisionieren + E2E testen
- Manifest-Intervall in Firmware-Config konfigurierbar (aktuell hardcoded 60 min)

**Option D – Audio-Extraktor v0.7.1-Rest**
- DELETE /jobs/{id} für `done`-Jobs (aktuell 409)
- Quota/Größenlimit für `data/audio`
- Worker-Restart-Verhalten verbessern

## Erste Aktion (Sprint 10)

```bash
cd ~/SpotFamServ && git fetch origin
git worktree add ../SpotFamServ-sprint-10 -b feat/sprint-10-<thema> origin/main
```

Dann plan-sprint-10-<thema>.md schreiben + User-Bestätigung einholen.

## Referenzen

- `docs/PROJECT_MAP.md` · `tasks/todo.md` · `tasks/decisions.md` · `tasks/lessons.md`
- `tasks/plan-sprint-09-audio-reader.md` (Sprint 09 Plan mit Dry-Run-Befunden)
- `docs/pi-deployment.md` (inkl. User-Gate Sprint 09)
- Regeln: `planning-discipline.mdc`, `sprint-workflow.mdc`, `chat-isolation-swarm.mdc`
