# Sprint-Starter-Prompt – Sprint 06: Reader-Station-UX + Konfiguration in die DB

Rolle: Lead-Engineer für **SpotFamServ** (ESP32 + RFID → Symfony-Backend → Spotify → Connect-Gerät).
Antworte deutsch, kritisch, ohne Gefälligkeits-Ja. Wenn du raten müsstest, frag – außer der Plan
sagt es bereits.

Lies zuerst (verbindlich):
- `tasks/plan-sprint-06-reader-station-config-db.md` (DER Plan – maßgeblich)
- `docs/PROJECT_MAP.md`, `tasks/todo.md`, `tasks/decisions.md`, `tasks/lessons.md`
- `docs/flash-station-runbook.md`, `docs/esp-reader-provisioning.md`
- Rules: `planning-discipline`, `sprint-workflow`, `chat-isolation-swarm`, `parallel-branch-workflow`,
  `project-architecture`

Worktree/Branch (nicht im Hauptcheckout auf `main` arbeiten):
- `git worktree add ../SpotFamServ-sprint-06 -b feat/sprint-06-reader-config-db origin/main`

Verifizierter Stand (Pi auf v0.5.8, Health 200):
- Flash-Station E2E verifiziert (Upload→Job→esptool→success), Footer zeigt korrekt v0.5.8.
- Reader-Station-UI hat drei bestätigte Defekte: falscher Chip-Mismatch
  (`ProvisioningPage.tsx:301` vergleicht `device.chip` statt `device.chipDescription`),
  Dialog-Overflow (`max-w-md` + nicht umbrechende Description), keine „aktuell angeschlossen"-Ansicht,
  `flashSize`-Typbug (number vs. String `"4MB"`).
- WLAN: Reader-Firmware nutzt compile-time `secrets.h`, **kein NVS/Captive-Portal**. Geflasht wurde
  bisher nur die PN532-**Probe** (kein WiFi). „Gerät nach Flash im WLAN" = aktuell NEIN.
- „Systemeinstellungen" (`/system`) existiert (nur Spotify-Karte). Spotify-DB-Muster
  (verschlüsselte Singleton-Entity + Provider DB→Env-Fallback) ist die Blaupause; kein KV-Store.
- HW-0 (PN532 gelötet) bleibt offen (D-022).

Ziel dieses Sprints (Milestone „Sprint 06"):
1. Reader-Station-UX-Fixes (Phase A). 2. „Systemeinstellungen" um Reader-Netzwerk (WLAN/Backend/OTA),
Maschinen-Keys, Frontend-URL erweitern – Werte aus DB statt Secret-/Env-Dateien (Phasen B).
3. Flash-Zeit-NVS-Injektion: Flash-Agent schreibt Config aus DB als NVS-Partition (Phase C).
4. Reader-Firmware NVS-first → joint nach Flash automatisch WLAN + self-claim (Phase D).
5. Realer RFID-E2E nur falls HW-0 aufgelöst (Phase E).

Zu bestätigen (Entscheidungen im Plan): **D-028** (WLAN via Flash-Zeit-NVS statt Captive Portal),
**D-029** (pro-Domäne-Settings-Entities), **D-030** (Maschinen-Keys in DB + Rotation),
**D-031** (`secrets.h`/`.env` → Dev-/Bootstrap-Fallback).

Blockierend (User/Hardware):
- Phase E (realer Scan) braucht gelöteten PN532 (HW-0/D-022).
- Key-Rotation: nach Änderung in DB müssen Agent (`secrets.env`) und ESP (NVS) den neuen Key erhalten.
- Deploy: neue Migrationen + Secrets/Bootstrap; Pi muss erreichbar sein.

Subagenten-Plan (parallel): (1) Backend-Settings-Entities/Provider/Tests, (2) Frontend
Cards+Hooks+UX-Fixes, (3) Flash-Agent NVS-Gen+Tests. Seriell: Firmware-NVS (D) + Integration.
HW-Schritte (E) im Hauptchat mit User.

Erste Aktion:
1. Worktree anlegen, GitHub-Stand gegen `tasks/todo.md` abgleichen, Milestone „Sprint 06" +
   WorkPackage-Issues (Label `work-package`) anlegen.
2. `tasks/plan-sprint-06-reader-station-config-db.md` kritisch reviewen (NVS-Offset/Partition-Layout,
   Key-Rotation, oasdiff-Additiv-Vertrag) und auf User-Bestätigung der Entscheidungen warten.
3. Erst danach Schwarm/Implementierung. Reihenfolge A → B → C → D → (E nur mit HW-0).
4. Pro Phase: Test-vor-Done, CI grün, dann erst nächste Phase.
