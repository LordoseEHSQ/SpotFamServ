# Sprint-Starter-Prompt – Sprint 3 (Multi-Raum & Reader-Lifecycle)

> Diesen Block in einen **neuen Chat** einfügen (GATE: ein Chat pro Sprint).

---

Rolle: Lead-Engineer für **SpotFamServ** (ESP32 + RFID → Symfony-Backend → Spotify Web API → Wobie Box).
Sprache: Deutsch, präzise, kritisch, kein Gefälligkeits-Ja.

**Lies zuerst (verbindlich, dann erst handeln):**
`docs/PROJECT_MAP.md` · `tasks/todo.md` · `tasks/decisions.md` (insb. **D-015 Reader→Box, D-016 Bluetooth verworfen, D-017 Pi-Leser=PN532**) ·
`tasks/lessons.md` · `docs/sprints/sprint-02.md` · `docs/pi-deployment.md` ·
**die drei Pläne** `tasks/plan-reader-box-mapping.md`, `tasks/plan-pi-reader-daemon.md`, `tasks/plan-esp-ota-perreader-keys.md` ·
Rules `.cursor/rules/` (`planning-discipline`, `sprint-workflow`, `chat-isolation-swarm`, `project-architecture`, `parallel-branch-workflow`).

**Verifizierter Stand (letzter Release `v0.2.3`):**
- Backend/Frontend/DB laufen auf dem Pi (`192.168.1.91`), Auto-Deploy via `v*`-Tag (`deploy/README.md`), `main` geschützt.
- **EIN Sprint-Branch aus dem Vorchat (noch NICHT gemergt, CI-Status prüfen!):**
  `feat/sprint-03-reader-lifecycle` mit einem PR. Enthält als WPs: README-Versionsfix,
  Planungs-Doku + Decisions D-015..D-017, und Reader→Box **implementiert**
  (Schema+Code+Frontend+Tests+OpenAPI+Docs).
  - Reader→Box **lokal NICHT voll getestet** (Host-PHP 8.3 < benötigt 8.4/8.5; nur `php -l`/`tsc -b`/Linter grün).
    → CI muss PHPUnit/PHPStan/Frontend/oasdiff bestätigen; Migration erreicht den Pi erst per Tag-Deploy.
- **Sprint 2 weiterhin offen (Hardware/User, blockiert echtes E2E):**
  - #8 OAuth-Consent im Browser je Profil-Account (SSH-Loopback-Tunnel) – technisch sonst entsperrt.
  - #10 ESP32 flashen (`/dev/ttyUSB0`) + realer Scan.

**Sprint-Ziel (Milestone anlegen – "Multi-Raum & Reader-Lifecycle"):**
1. **Reader→Box abschließen:** CI grün, Sprint-Branch mergen, Pi-Migration + echtes Multi-Raum-E2E (Leser A→Box X, Leser B→Box Y; Leser ohne Mapping = Profil-Default). Plan: `plan-reader-box-mapping.md`.
2. **Pi-Leser nutzbar (PN532/HW-147):** Python-Daemon (Adafruit CircuitPython PN532, I2C), postet an `/api/v1/readers/scan`; + Scan-to-Enroll-UX. Plan: `plan-pi-reader-daemon.md`.
3. **Härtung/Lifecycle (gestaffelt):** Pro-Reader-Keys (vorhandenes `ReaderDevice::validateApiKey` verdrahten) → USB-Provisioning am Pi → **signiertes** OTA. Plan: `plan-esp-ota-perreader-keys.md`.

**Blockierend – braucht User/Hardware (NICHT autonom):**
- Spotify: **je Profil ein eigenes Premium-Konto** (bestätigt) – OAuth-Consent je Account; mehrere Connect-Boxen online für Multi-Raum-E2E.
- PN532: Verkabelung + Interface (I2C empfohlen) in Raspberry Pi OS aktivieren; **UID-Gleichheit zum MFRC522 mit einer bekannten Karte verifizieren** (4- vs. 7-Byte/Byte-Reihenfolge).
- ESP32 am USB zum (Re-)Flashen/Provisioning; OTA-Signaturschlüssel-Verwaltung.

**Subagenten-Schwarm (parallel, soweit unabhängig):**
- A (`explore`/Umsetzung): CI-Fehler des Sprint-Branch-PR triagieren + fixen, Merge-Readiness.
- B (`explore`): Pi-Leser-Daemon – PN532-Lib/Interface, UID-Normalisierung, systemd-Unit.
- C (`explore`): Pro-Reader-Key-Verdrahtung in `ScanController::validateReaderAuth` (kleinster, zuerst).
- Seriell: Provisioning hängt an Pro-Reader-Keys; OTA hängt an Provisioning.

**Erste Aktion (GATE Plan-vor-Code):**
Zuerst GitHub-Milestone „Sprint 3" + WorkPackage-Issues (Label `work-package`) anlegen (SSoT), den EINEN Sprint-Branch-PR
(`feat/sprint-03-reader-lifecycle`) prüfen/mergen, dann `tasks/plan-sprint-03.md` (4-Lens + Cross-Module + Dry-Run) schreiben
und auf User-Bestätigung warten. Erst danach Code.
Sprint endet mit grüner CI + Tag (Minor-Bump, z.B. `v0.3.0`).
