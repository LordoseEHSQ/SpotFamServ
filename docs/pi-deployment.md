# Raspberry Pi Deployment – SpotFamServ

Referenz/Runbook für den Betrieb des Backends + Frontends auf dem Pi.
Stand: 2026-06-01. Begleitende Stolpersteine: siehe `tasks/lessons.md` (L-001..L-010).

Für eine frische Installation ab leerem Raspberry Pi zuerst `docs/installation.md` verwenden.
Diese Datei hier ist die Betriebs-/Referenzdoku für den eingerichteten Zielstand.

> **Ab `v0.1.0`: Deployment ist automatisiert (Pull-basiert).** Der Pi ist ein read-only
> git-Clone und zieht per `systemd`-Timer neue `v*`-Tags. Maßgebliches Runbook:
> [`deploy/README.md`](../deploy/README.md). Der manuelle rsync-Abschnitt weiter unten ist
> historisch (Erstaufsetzung) und nicht mehr der Normalweg.

## Auto-Deploy (maßgeblich, ab v0.1.0)

- Repo auf dem Pi: git-Clone, Remote per read-only Deploy-Key (`~/.ssh/spotfam_deploy`,
  `core.sshCommand` im Repo gesetzt).
- Timer `spotfam-deploy.timer` (alle 2 Min) → `spotfam-deploy.service` → `deploy/pi-deploy.sh`.
- Release auslösen: `git tag vX.Y.Z && git push origin vX.Y.Z` (von der Dev-Maschine).
- Backups: `backups/db-<tag>-<ts>.sql.gz` (Rotation `KEEP=7`).
- Status/Logs: `systemctl list-timers spotfam-deploy.timer` · `journalctl -u spotfam-deploy.service`.

## Hardware / OS

| Eigenschaft | Wert |
|---|---|
| Modell | Raspberry Pi 4 Model B Rev 1.1 |
| CPU / Arch | 4 Kerne, `aarch64` (arm64) |
| RAM | 3,7 GiB |
| Disk (root) | 57 GB (SD `/dev/mmcblk0p2`), beim Start ~4 GB belegt |
| OS | Debian GNU/Linux 13 (trixie) – Raspberry Pi OS Lite 64-bit |
| Hostname | `spotfam` |
| LAN-IP | `192.168.1.91` (DHCP – **nicht reserviert**, siehe Risiken) |
| Pi-MAC | `dc-a6-32-5a-73-9e` (für DHCP-Reservierung) |
| Connect-Wiedergabegerät IP (Beispiel) | `192.168.1.10` |

## Zugang

- SSH: Key-Auth (mein Key liegt in `~/.ssh/authorized_keys`) **und** Passwort-Auth aktiv (`passwordauthentication yes`).
- User `lars`, `sudo` ohne Passwort.
- Verbinden: `ssh lars@192.168.1.91` (mDNS `spotfam.local` war unzuverlässig → IP nutzen).
- Beim Neuaufsetzen alten Host-Key entfernen: `ssh-keygen -R 192.168.1.91`.

## Installierter Stack (auf dem Pi)

| Komponente | Version |
|---|---|
| Docker Engine | 29.5.2 (via `get.docker.com`) |
| Docker Compose | v5.1.4 (**v2-Plugin** – `docker compose`, NICHT `docker-compose`) |
| Container | `db` (postgres:17-alpine), `app` (php:8.5.6-fpm-alpine), `messenger-worker` (gleiches app-Image), `nginx:alpine` |
| Repo-Pfad | `/home/lars/SpotFamServ` |

## Architektur auf dem Pi

- nginx (Port `8080`) liefert die statische **SPA** unter `/` und routet `/api` an Symfony (php-fpm).
- Frontend nutzt relative API-Basis `/api/v1` → gleicher Origin, kein CORS.
- **Das nginx-/Web-Image (SPA + `default.conf`) wird in CI gebaut und aus GHCR gezogen** (D-012/D-013,
  `ghcr.io/lordoseehsq/spotfamserv-web:<tag>`, public, multi-arch). Der Pi baut das Frontend **nicht**
  mehr (kein Node/pnpm nötig). `pi-deploy.sh` pinnt `WEB_IMAGE_TAG` auf den deployten `v*`-Tag und macht
  `docker compose pull nginx`. Der `frontend/dist`-Bind-Mount entfällt; `default.conf` + `backend/public:ro`
  bleiben gemountet. App-Image (Backend) wird weiterhin lokal auf dem Pi gebaut.
- Secrets: `backend/.env.local` (Spotify Client-ID/Secret, READER_API_KEY), Root-`.env` (Redirect-URI, FRONTEND_URL, READER_API_KEY).
- **Audio-Extraktion läuft asynchron** (Sprint 07 / D-032): `app` (php-fpm) reiht einen `AudioJob` ein
  und antwortet 202; der **`messenger-worker`** (genau 1 Prozess, `messenger:consume async`) führt die
  Extraktion aus. Beide teilen das app-Image und den `/data/audio`-Bind-Mount; der Entrypoint chownt
  diesen self-healing auf `www-data` (uid 82). Worker neustart: `docker compose restart messenger-worker`,
  Logs: `docker compose logs -f messenger-worker`.

### Audio-Worker-Runbook (Sprint 07)

- **Transport-Tabelle:** `messenger_messages` wird durch `php bin/console messenger:setup-transports`
  angelegt – `pi-deploy.sh` führt das nach den Migrationen und **vor** dem Worker-Konsum aus (idempotent).
- **Festhängende/abgebrochene Jobs:** Es gibt keine harte Unterbrechung eines laufenden yt-dlp-Prozesses;
  Cancel wirkt nur auf `pending`. Hängt der Worker, hilft `docker compose restart messenger-worker`
  (das `--time-limit=3600`/`--memory-limit=128M` beendet den Prozess ohnehin periodisch self-healing).
- **Quota voll (507):** `/data/audio` aufräumen (über die UI „Gespeicherte Dateien" oder
  `DELETE /api/v1/audio-extractor/files/{name}`); Limit via `AUDIO_EXTRACTOR_MAX_TOTAL_BYTES`.
- **Stale Jobs in der Queue:** Failure-Transport `failed` ist konfiguriert, wird bei `max_retries: 0`
  aber praktisch nicht befüllt (Fehler werden als `AudioJob.status=failed` festgehalten).

## Deployment (maßgeblich: tag-getriggert)

Normalweg ist **Auto-Deploy** (oben): `git tag vX.Y.Z && git push origin vX.Y.Z`. CI baut das
Web-Image und pusht es nach GHCR; der Pi-Timer zieht den Tag, holt das Image und startet neu.
Manuell ohne Timer: `ssh lars@192.168.1.91 '/home/lars/SpotFamServ/deploy/pi-deploy.sh'`.

**Wichtig (Reihenfolge):** Das Web-Image entsteht erst parallel zum Tag in CI. `pi-deploy.sh`
zieht das Image mit Retry (5×30 s); ist es beim ersten Tick noch nicht in GHCR, deployt der
nächste Timer-Tick (2 Min) erneut. Rollback = neuer höherer Tag vom älteren Commit; ad-hoc auf
dem Pi: `export WEB_IMAGE_TAG=v0.2.1 && docker compose up -d nginx`.

> **Frontend wird NICHT mehr auf dem Pi (oder per rsync) gebaut.** Der frühere WSL-Schritt
> `pnpm build` + `rsync dist/` entfällt (war Root Cause von L-011). Web-Image kommt aus CI/GHCR.

### Historisch: manuelle Erstaufsetzung (vor Auto-Deploy/CI-Image)

```bash
# 1) Code übertragen (Excludes beachten – siehe L-001)
rsync -az --delete \
  --exclude '.git/' --exclude 'pi-image/' \
  --exclude 'backend/vendor/' --exclude 'backend/var/cache/' --exclude 'backend/var/log/' \
  --exclude 'frontend/node_modules/' --exclude 'frontend/dist/' --exclude '*.log' \
  /home/lars/SpotFamServ/ lars@192.168.1.91:/home/lars/SpotFamServ/

# 2) Auf dem Pi: app bauen + starten (lange Schritte abgekoppelt, siehe L-008)
ssh lars@192.168.1.91 'cd ~/SpotFamServ && docker compose build app'
ssh lars@192.168.1.91 'cd ~/SpotFamServ && export WEB_IMAGE_TAG=$(git -C ~/SpotFamServ describe --tags --abbrev=0) && docker compose pull nginx && docker compose up -d'

# 3) Dev-Mount: vendor füllen + migrieren (L-006)
ssh lars@192.168.1.91 'cd ~/SpotFamServ && docker compose exec -T app composer install --no-interaction'
ssh lars@192.168.1.91 'cd ~/SpotFamServ && docker compose exec -T app php bin/console doctrine:migrations:migrate --no-interaction'
```

## URLs

- Alltag (LAN): `http://192.168.1.91:8080`
- Spotify-OAuth (einmalig, Loopback-Zwang): SSH-Tunnel + `http://127.0.0.1:8080` (siehe L-004)
  ```bash
  ssh -N -L 127.0.0.1:8080:localhost:8080 lars@192.168.1.91
  ```
- Spotify-Dashboard Redirect-URI muss exakt sein: `http://127.0.0.1:8080/api/v1/spotify/callback`

## E2E-Runbook: Scan → Spotify → Connect-Gerät (Sprint 2)

Reihenfolge ist zwingend: **#8 → #9 → #10** (ohne Token kein Discovery/Playback).

1. **#8 – Spotify-Token (einmalig, Loopback-Zwang, L-004):**
   - Pi prüfen: `backend/.env.local` enthält `SPOTIFY_CLIENT_ID`/`SPOTIFY_CLIENT_SECRET`
     (OAuth liest **nur** Env, nicht die DB-`spotify_app_configuration` – R1!), Root-`.env`
     `SPOTIFY_REDIRECT_URI=http://127.0.0.1:8080/api/v1/spotify/callback`, `FRONTEND_URL=http://127.0.0.1:8080`,
     `APP_SECRET` stabil (Änderung macht gespeicherte Tokens unlesbar).
   - Spotify-Dashboard: Redirect-URI exakt eintragen + eigene E-Mail unter „User Management" (Development Mode).
   - Tunnel: `ssh -N -L 127.0.0.1:8080:localhost:8080 lars@192.168.1.91` → Browser `http://127.0.0.1:8080`
     → Profil → „Mit Spotify verbinden" → Consent.
   - Verifizieren: `GET /api/v1/profiles/{id}/spotify/status` = `connected`; `GET …/spotify/devices` liefert ≥1 Gerät.
2. **#9 – Default-Device:** Connect-Wiedergabegerät einschalten (Spotify Connect aktiv).
   - Discovery: `POST /api/v1/devices/discover` (oder Profil-Tab „Lautsprecher" → „Geräte abrufen").
   - Standard setzen: im Tab „Lautsprecher" auf „Als Standard" — ruft `PUT /profiles/{id}/default-device`.
   - Verifizieren: `GET /profiles/{id}` zeigt `default_spotify_device_id` + `default_device_name`;
     `POST …/spotify/playback/start` mit `context_uri` und **ohne** `device_id` spielt auf dem Connect-Gerät.
3. **#10 – ESP32 flashen + Scan:** ESP32 an `/dev/ttyUSB0` (Gruppe `dialout`).
   - `firmware/spotfam_reader/secrets.h`: `BACKEND_BASE_URL=http://192.168.1.91:8080`, `READER_API_KEY`
     identisch zu Root-`.env` auf dem Pi. Flash via `arduino-cli`.
   - Dry-Run vor Flash (Backend-Kette prüfen):
     ```bash
     curl -X POST http://192.168.1.91:8080/api/v1/readers/scan \
       -H "Content-Type: application/json" -H "X-API-Key: <READER_API_KEY>" \
       -d '{"reader_id":"wohnzimmer-1","card_uid":"<BEKANNTE_UID>"}'
     # Erwartung: {"outcome":"success","message":"Playback started."}
     ```
   - Realer Scan: Serial-Monitor zeigt `-> 200: {"outcome":"success",...}`, Reader-LED 1× langer Blink,
     Connect-Gerät spielt hörbar.

4. **Sprint-4 – Wiedergabegerät sicherstellen (D-S4-DEV):**
   Damit ein Scan tatsächlich spielt, muss **mindestens einer** der folgenden Wege gesetzt sein:
   - **Reader→Gerät:** Frontend → „RFID-Leser" → Reader auswählen → Lautsprecher-Dropdown → Speichern.
     (Gespeichert in `reader_device.default_spotify_device_id` + `default_device_name`.)
   - **Profil-Default:** Frontend → Profil → Tab „Lautsprecher" → Gerät abrufen → „Als Standard" setzen.
     (Gespeichert in `family_profile.default_spotify_device_id` + `default_device_name`.)
   Priorität beim Scan: Reader→Gerät **vor** Profil-Default.
   Wenn beides leer: Outcome `no_device`, keine Wiedergabe, Hinweis im UI.
   → **Das Connect-Gerät muss beim Setzen online sein** (Spotify-Discovery-Zeitpunkt).

## Offene Härtung / Risiken

- **Auto-Start fehlt:** `restart: unless-stopped` je Service ergänzen (L-007).
- **DHCP-IP nicht fix:** Reservierung für MAC `dc-a6-32-5a-73-9e` setzen, sonst brechen ESP32 (`firmware/.../secrets.h` → `BACKEND_BASE_URL`) und Bookmarks.
- **`APP_ENV=dev`** auf dem Pi: Debug/Profiler aktiv, nicht prod-gehärtet.
- **Spotify Premium** für Wiedergabe-Steuerung nötig; das Connect-Wiedergabegerät muss zum Discovery-Zeitpunkt online sein.
