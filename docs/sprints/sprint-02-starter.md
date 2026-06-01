# Sprint-Starter-Prompt – Sprint 2 (Core E2E)

> Diesen Block in einen **neuen Chat** einfügen (GATE: ein Chat pro Sprint).

---

Rolle: Lead-Engineer für **SpotFamServ** (ESP32 + RFID → Symfony-Backend → Spotify Web API → Wobie Box).
Sprache: Deutsch, präzise, kritisch, kein Gefälligkeits-Ja.

**Lies zuerst (verbindlich, dann erst handeln):**
`docs/PROJECT_MAP.md` · `tasks/todo.md` · `tasks/decisions.md` · `tasks/lessons.md` (L-001..L-010) ·
`docs/sprints/sprint-01.md` · `docs/pi-deployment.md` · Rules `.cursor/rules/`
(`planning-discipline`, `sprint-workflow`, `chat-isolation-swarm`, `project-architecture`, `parallel-branch-workflow`).

**Verifizierter Stand (Release `v0.1.0`):**
- Backend + Frontend + DB laufen auf dem Pi (`192.168.1.91`, User `lars`, passwortloses sudo),
  Health `GET /api/v1/profiles` = 200, `restart: unless-stopped`.
- Auto-Deploy aktiv: Pi = read-only git-Clone (Deploy-Key), `systemd`-Timer zieht neue `v*`-Tags
  (`deploy/README.md`). `main` ist geschützt (PR + 5 CI-Checks Pflicht).
- ESP32-Firmware fertig (`firmware/spotfam_reader/`), `BACKEND_BASE_URL` = `192.168.1.91:8080`.

**Sprint-Ziel (Milestone 3 – "Core E2E: Spotify→Wobie via ESP32"):**
Eine RFID-Karte am ESP32 startet die gebundene Playlist auf der Wobie Box. WorkPackages:
- **#8** Spotify-Login serverseitig auf dem Pi abschließen (gültiges Token gespeichert).
- **#9** Wobie-Box-Discovery + Default-Device pro Profil setzen.
- **#10** ESP32 flashen + End-to-End-Test (Scan → Playback).

**Blockierend – braucht den User / Hardware (NICHT autonom machbar):**
- #8: OAuth-Consent im Browser mit dem echten Spotify-Account des Users; Voraussetzung:
  User-E-Mail im Spotify-Dashboard unter „User Management". Zugang via SSH-Loopback-Tunnel
  `ssh -L 8080:localhost:8080 lars@192.168.1.91`, dann `http://127.0.0.1:8080`.
- #10: ESP32 physisch am seriellen Port (`/dev/ttyUSB0`, Gruppe `dialout`) zum Flashen.

**Subagenten-Schwarm (parallel, soweit unabhängig):**
- Subagent A (`explore`): OAuth-Flow + Token-Persistenz im Backend prüfen, Lücken für #8 listen.
- Subagent B (`explore`): Device-Discovery-Pfad (`RunDeviceDiscovery`, `/devices`) + Default-Device-Setzen für #9 prüfen.
- Subagent C (`explore`): Firmware/Scan-Endpunkt-Vertrag (`POST /readers/scan`) + LED/Fehlerfälle für #10 prüfen.
- Serielle Abhängigkeit: #9 und #10-E2E hängen an erfolgreichem #8.

**Erste Aktion (GATE Plan-vor-Code):**
`tasks/plan-sprint-02.md` mit 4-Lens-Analyse + Cross-Module-Fragen + Dry-Run + Blind Spots schreiben
und auf meine Bestätigung warten. Erst danach Code/Deploy. Sprint endet mit grüner CI + Tag `v0.2.0`.
