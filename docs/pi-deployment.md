# Raspberry Pi Deployment – SpotFamServ

Referenz/Runbook für den Betrieb des Backends + Frontends auf dem Pi.
Stand: 2026-06-01. Begleitende Stolpersteine: siehe `tasks/lessons.md` (L-001..L-010).

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
| Wobie Box IP | `192.168.1.10` |

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
| Container | `db` (postgres:17-alpine), `app` (php:8.5.6-fpm-alpine), `nginx:alpine` |
| Repo-Pfad | `/home/lars/SpotFamServ` |

## Architektur auf dem Pi

- nginx (Port `8080`) liefert die statische **SPA** unter `/` und routet `/api` an Symfony (php-fpm).
- Frontend nutzt relative API-Basis `/api/v1` → gleicher Origin, kein CORS.
- Secrets: `backend/.env.local` (Spotify Client-ID/Secret, READER_API_KEY), Root-`.env` (Redirect-URI, FRONTEND_URL, READER_API_KEY).

## Deployment-Runbook (aus WSL)

```bash
# 1) Code übertragen (Excludes beachten – siehe L-001)
rsync -az --delete \
  --exclude '.git/' --exclude 'pi-image/' \
  --exclude 'backend/vendor/' --exclude 'backend/var/cache/' --exclude 'backend/var/log/' \
  --exclude 'frontend/node_modules/' --exclude 'frontend/dist/' --exclude '*.log' \
  /home/lars/SpotFamServ/ lars@192.168.1.91:/home/lars/SpotFamServ/

# 2) Frontend in WSL bauen (statisch, arch-unabhängig) und dist/ mitschieben
cd frontend && pnpm build
rsync -az --delete frontend/dist/ lars@192.168.1.91:/home/lars/SpotFamServ/frontend/dist/

# 3) Auf dem Pi: bauen + starten (lange Schritte abgekoppelt, siehe L-008)
ssh lars@192.168.1.91 'cd ~/SpotFamServ && docker compose build app'
ssh lars@192.168.1.91 'cd ~/SpotFamServ && docker compose up -d'   # ggf. erneut, falls DB-Init-Race (L-003)

# 4) Dev-Mount: vendor füllen + migrieren (L-006)
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

## Offene Härtung / Risiken

- **Auto-Start fehlt:** `restart: unless-stopped` je Service ergänzen (L-007).
- **DHCP-IP nicht fix:** Reservierung für MAC `dc-a6-32-5a-73-9e` setzen, sonst brechen ESP32 (`firmware/.../secrets.h` → `BACKEND_BASE_URL`) und Bookmarks.
- **`APP_ENV=dev`** auf dem Pi: Debug/Profiler aktiv, nicht prod-gehärtet.
- **Spotify Premium** für Wiedergabe-Steuerung nötig; Wobie Box muss zum Discovery-Zeitpunkt online sein.
