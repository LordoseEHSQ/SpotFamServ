# PROJECT MAP – SpotFamServ

> Token-sparsamer Einstieg: Was gibt es, was tut es, wo nachschauen.
> Zugleich Obsidian-Home (Vault = Repo). GitHub = Single Source of Truth fürs Tracking.

## Was ist das Projekt?
RFID-gesteuerte Spotify-Wiedergabe für die Familie: Ein **ESP32 (WROOM-32)** liest RFID-Tags
und schickt HTTP-Requests an ein **Symfony-Backend** (Docker, auf einem Raspberry Pi), das per
**Spotify Web API** die Wiedergabe auf einem **beliebigen Spotify-Connect-fähigen Wiedergabegerät**
(austauschbares Connect-Target, kein spezifisches Produkt) steuert.

## Architektur (Kurz)
`ESP32 → HTTP /api/v1/readers/{scan|next|previous} → Backend (Symfony/PHP 8.5) → Spotify API → Spotify-Connect-Gerät`
Frontend = React/Vite SPA (nginx, gleicher Origin, `/api/v1`).

## Verzeichnis-Landkarte
- `backend/` — Symfony 7.4 / PHP 8.5. Modulstruktur unter `backend/src/Module/`:
  - `Scan/` — RFID-Scan + Reader-Control (next/prev), PlaybackSessionStore.
  - `Spotify/` — OAuth, API-Client, Playback, Device-Discovery.
  - `AudioExtractor/` — yt-dlp/ffmpeg-Extraktion, **async** via Messenger (`AudioJob`, `/extract`→202+job_id,
    `/jobs`-Status-API, Worker `messenger-worker`). Quota+Lock (D-033/D-034), Formate mp3/wav/opus/flac/m4a/aac.
  - (+ Profile/RFID/Setup/Device-Endpunkte). Routen: `php bin/console debug:router`.
- `frontend/` — React SPA (Profile, RFID-Bindings, Spotify-Setup, Devices, Activity, Audio-Extraktor mit Job-Polling).
- `firmware/spotfam_reader/` — ESP32-Sketch (`.ino`), `config.h` (Pins), `secrets.h` (git-ignoriert).
- `docker/` — nginx + postgres-Init. `docker-compose.yml` — Stack (app/messenger-worker/nginx/db).
- `docs/` — `installation.md` (frische Pi-Installation), `pi-deployment.md` (Pi-Specs+Runbook),
  `SPOTIFY_INTEGRATION.md`, `sprints/`, dieses Map.
- `tasks/` — `plan-*.md` (Pläne), `lessons.md` (L-001..), `decisions.md` (D-*), `todo.md` (Working-Memory).
- `.github/` — CI-Workflow, `ISSUE_TEMPLATE/` (work-package, bug).
- `.cursor/rules/` — Standing-Regeln (planning-discipline, project-architecture, branch-workflow, sprint-workflow, chat-isolation-swarm).

## Wo finde ich was?
- **Aktuelle Arbeit:** `tasks/todo.md` + GitHub Issues/Milestones (Board #2).
- **Warum etwas so ist:** `tasks/decisions.md`.
- **Stolpersteine/Was schiefging:** `tasks/lessons.md`.
- **Frisch installieren:** `docs/installation.md`.
- **Pi betreiben/deployen:** `docs/pi-deployment.md`.
- **Prozess/Regeln:** `.cursor/rules/`.

## Standing-Regeln (Kurz)
1. **Erst planen, immer** (Plan-Datei + 4-Lens + Dry-Run), dann Code.
2. **Branches/Worktrees**, nie direkt auf `main`; Squash-Merge via PR.
3. **Sprints/WorkPackages/Bugs in GitHub**; Sprint done = Akzeptanzkriterien erfüllt + Tag.
4. **SemVer** (`vX.Y.Z`), Tag am Sprint-Ende → triggert Pi-Deploy.
5. **GATE: Ein Chat pro Sprint + Subagenten-Schwarm + Starter-Prompt** beim Übergang
   (`chat-isolation-swarm.mdc`, Starter unter `docs/sprints/sprint-NN-starter.md`).
