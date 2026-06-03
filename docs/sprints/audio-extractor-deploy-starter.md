# Starter-Prompt – Audio-Extraktor auf den Pi bringen

> GATE-2-Hand-off (`chat-isolation-swarm.mdc`). In einen NEUEN, frischen Chat einfügen.
> Prinzip: Verweise statt Volltext. Dies ist kein nummerierter Sprint, sondern der
> Deploy-/Verifikations-Hand-off für ein bereits in `main` gemergtes Feature.

---

Rolle: Lead-Engineer für **SpotFamServ** (ESP32 + RFID → Backend → Spotify → Wobie Box).

Lies zuerst (verbindlich): `docs/PROJECT_MAP.md`, `tasks/todo.md` (Abschnitt „Feature:
Audio-Extraktor"), `tasks/plan-audio-extractor.md`, `tasks/decisions.md` (D-019/D-020),
`tasks/lessons.md` (L-020/L-021/L-022) und die Rules unter `.cursor/rules/` (insb.
`planning-discipline`, `sprint-workflow`, `project-architecture`, `chat-isolation-swarm`).

Verifizierter Stand:
- PR #47 squash-gemergt nach `main` (Commit 51f854b, 2026-06-03). CI war grün
  (Backend 8.4/8.5, Frontend, oasdiff, Trivy, Firmware, Web-Image).
- **Lokal (x86_64/WSL2) vollständig verifiziert**: `docker-compose build app` ok
  (yt-dlp 2026.03.17 zipapp + ffmpeg 8.0.1, `/opt/yt-dlp` + `/data/audio` www-data-owned);
  26 Unit-Tests, PHPStan L8 sauber; E2E grün über nginx (config/extract/list/download/
  delete/update); **echte YouTube-Extraktion** (Big Buck Bunny, CC-BY/Blender) → 15,2 MB
  MP3 in ~25 s, HTTP 201. Bot-Schutz hat aus dieser Umgebung NICHT geblockt.
- **NOCH NICHT verifiziert**: alles auf dem Pi (arm64).

Ziel dieses Chats: Audio-Extraktor produktiv auf den Pi (192.168.1.91, arm64, Debian 13)
bringen und dort verifizieren.
1. SemVer-Entscheidung + **Release-Tag `vX.Y.Z`** auf `main` (Minor: neues Feature) →
   triggert Pi-Deploy (systemd-Pull, siehe `docs/pi-deployment.md`).
2. Pi-Image-Build prüfen: yt-dlp ist arch-unabhängiges zipapp (braucht nur python3) →
   sollte auf arm64 laufen, ist aber unbestätigt. ffmpeg/python3/curl im `app`-Image.
3. **R7**: Host-Bind-Mount `./data/audio:/data/audio` muss für Container-User www-data
   (uid 82) schreibbar sein. Setup-Schritt auf Pi: Verzeichnis anlegen + Rechte (z. B.
   `chown -R 82:82 data/audio` oder `chmod 0777`).
4. Pi-E2E: `/tools/audio-extractor` aufrufen, extract (legale Quelle) → download → delete,
   Self-Update-Button (`yt-dlp -U`, schreibt nach `/opt/yt-dlp` → www-data muss dürfen).

Blockierend (braucht User/Hardware):
- Pi-Zugang/SSH und ggf. Neustart des Stacks auf dem Pi.
- Entscheidung über den konkreten Versions-Tag.

Bekannte Restrisiken / Stolpersteine:
- Kein committetes `backend/.env`; Stack bootet aus realer Docker-Env. Für Tests/Console
  temporär `cp .env.example .env` (gitignored, vor Commit entfernen) — siehe L-020.
- yt-dlp-`--match-filter` verwirft Quellen mit unbekannter Dauer → als zwei OR-Flags
  gelöst (`duration<N` + `!duration`), siehe L-021.
- `BinaryFileResponse` braucht expliziten `Content-Type` (kein `symfony/mime`), siehe L-022.
- Synchrone Extraktion (kein Queue): lange Läufe blockieren einen php-fpm-Worker bis Timeout
  (nginx `fastcgi_read_timeout` 300s, `AUDIO_EXTRACTOR_TIMEOUT_SECONDS` 240s).
- Lokaler Test-Stack lief im Worktree `../SpotFamServ-audio-extractor` (Branch
  `feat/audio-extractor`). Nach erfolgreichem Pi-Deploy aufräumen.

Erste Aktion: Stand gegen GitHub/`main` abgleichen, Versions-Tag vorschlagen und auf meine
Bestätigung warten (Tag = Deploy-wirksam, Plan-vor-Deploy-GATE). Erst danach taggen/deployen.
