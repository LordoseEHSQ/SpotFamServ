# Installation – Spotify Familien Server / SpotFamServ

Diese Anleitung beschreibt eine frische Installation des SpotFamServ-Servers auf einem
Raspberry Pi. Sie ist die Einstiegsdoku für „von leerem Pi bis laufende Weboberfläche“.

Detail-Runbooks bleiben getrennt:

- `docs/pi-deployment.md` beschreibt den bereits eingerichteten Pi und Betriebsdetails.
- `deploy/README.md` beschreibt nur den tag-getriggerten Auto-Deploy-Mechanismus.
- `docs/SPOTIFY_INTEGRATION.md` enthält zusätzliche Spotify-Hintergründe.

## Zielzustand

Nach der Installation läuft auf dem Pi:

- `nginx` auf Port `8080` für die React-SPA und `/api`.
- `app` als Symfony/PHP-FPM-Backend.
- `messenger-worker` für asynchrone Audio-Extraktion.
- `db` als PostgreSQL 17.
- Optional ein `systemd`-Timer, der neue `v*`-Tags automatisch deployt.

Der normale Zugriff im LAN ist:

```bash
http://<pi-ip>:8080
```

Spotify-OAuth läuft wegen Spotifys HTTP-Regeln nicht über die LAN-IP, sondern einmalig
über einen SSH-Loopback-Tunnel auf `127.0.0.1:8080`.

## 1. Voraussetzungen

### Hardware

Verifizierter Zielstand:

| Bereich | Voraussetzung |
|---|---|
| Gerät | Raspberry Pi 4B oder stärker empfohlen |
| CPU-Architektur | `arm64` / `aarch64` |
| RAM | 4 GB empfohlen; 2 GB können für Basisbetrieb reichen, sind aber nicht verifiziert |
| Speicher | Mindestens 16 GB SD/SSD, besser 32 GB+ |
| Netzwerk | Stabiles LAN/WLAN; DHCP-Reservierung empfohlen |
| Spotify | Spotify-Premium-Konto für Playback-Steuerung |
| Wiedergabe | Ein Spotify-Connect-fähiges Zielgerät im selben Spotify-Konto |

Nicht sinnvoll:

- 32-bit Raspberry Pi OS. Die Images und Annahmen sind auf `arm64` ausgelegt.
- Instabile DHCP-IP. ESP32-Reader und Bookmarks zeigen auf die Pi-IP.
- Pi ohne funktionierende Uhrzeit/DNS. OAuth, GitHub, Docker Pulls und TLS können sonst scheitern.

### Pi OS

Verifizierte Basis:

- Raspberry Pi OS Lite 64-bit auf Debian 13 `trixie`.
- SSH aktiviert.
- Ein normaler User mit `sudo`-Rechten, in dieser Doku beispielhaft `lars`.
- Hostname optional `spotfam`.

Prüfen:

```bash
uname -m
cat /etc/os-release
timedatectl status
ip addr
```

Erwartung:

- `uname -m` zeigt `aarch64`.
- OS ist 64-bit.
- Zeit ist synchronisiert.
- Der Pi hat eine feste oder per DHCP reservierte LAN-IP.

Empfohlene Basispakete:

```bash
sudo apt update
sudo apt install -y ca-certificates curl git openssh-client openssh-server gnupg
```

## 2. Docker und Compose installieren

SpotFamServ wird auf dem Pi per Docker Compose betrieben. Wichtig ist die Compose-v2-Plugin-Syntax:

```bash
docker compose version
```

Nicht verwenden:

```bash
docker-compose
```

Installation über Docker Convenience Script:

```bash
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker "$USER"
```

Danach neu einloggen oder die Shell neu starten. Prüfen:

```bash
docker version
docker compose version
docker run --rm hello-world
```

Hinweis: Das Convenience Script ist bequem, aber nicht die einzige valide Docker-Installation.
Entscheidend ist, dass `docker compose` als v2-Plugin funktioniert.

## 3. GitHub-Zugriff vorbereiten

Der Pi soll das Repo lesen können. Für Dauerbetrieb ist ein read-only Deploy-Key sinnvoll.

Auf dem Pi:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/spotfam_deploy -C "spotfam-pi-deploy"
cat ~/.ssh/spotfam_deploy.pub
```

Den öffentlichen Key in GitHub beim Repository als Deploy Key eintragen:

- Zugriff: read-only reicht.
- Schreibrechte sind für den Pi nicht nötig.

SSH-Konfiguration testen:

```bash
GIT_SSH_COMMAND='ssh -i ~/.ssh/spotfam_deploy -o IdentitiesOnly=yes' \
  git ls-remote git@github.com:LordoseEHSQ/SpotFamServ.git
```

Wenn das fehlschlägt, ist der Deploy-Key oder Repository-Zugriff noch nicht korrekt.

## 4. Repository klonen

Standardpfad auf dem Pi:

```bash
cd /home/lars
GIT_SSH_COMMAND='ssh -i ~/.ssh/spotfam_deploy -o IdentitiesOnly=yes' \
  git clone git@github.com:LordoseEHSQ/SpotFamServ.git
cd /home/lars/SpotFamServ
git config core.sshCommand 'ssh -i ~/.ssh/spotfam_deploy -o IdentitiesOnly=yes'
```

Prüfen:

```bash
git fetch --tags origin
git tag -l 'v*' --sort=-v:refname | head -n 5
```

Der Pi deployt später normalerweise den neuesten `v*`-Tag, nicht irgendeinen Zwischenstand
auf `main`.

## 5. Konfiguration und Secrets

Secrets werden nicht committed. Diese Dateien sind absichtlich git-ignoriert:

- `.env`
- `backend/.env.local`
- `firmware/spotfam_reader/secrets.h`

Root-Umgebung anlegen:

```bash
cd /home/lars/SpotFamServ
cp .env.example .env
```

`.env` anpassen:

```dotenv
APP_ENV=dev
APP_SECRET=<lange-zufaellige-zeichenkette>
SPOTIFY_REDIRECT_URI=http://127.0.0.1:8080/api/v1/spotify/callback
FRONTEND_URL=http://127.0.0.1:8080
READER_API_KEY=<lange-zufaellige-reader-api-key>
```

Backend-Secrets anlegen:

```bash
cat > backend/.env.local <<'EOF'
SPOTIFY_CLIENT_ID=<spotify-client-id>
SPOTIFY_CLIENT_SECRET=<spotify-client-secret>
READER_API_KEY=<gleiche-reader-api-key-wie-in-root-env>
EOF
chmod 600 .env backend/.env.local
```

Wichtig:

- `APP_SECRET` nach der Ersteinrichtung nicht leichtfertig ändern. Gespeicherte verschlüsselte Werte
  und Tokens können dadurch unlesbar werden.
- `SPOTIFY_REDIRECT_URI` muss exakt zur Spotify-App passen.
- `FRONTEND_URL` ist für den OAuth-Erstfluss auf `127.0.0.1:8080` gesetzt. Der spätere Alltag läuft
  trotzdem über `http://<pi-ip>:8080`.
- `READER_API_KEY` muss später auch in Reader-/Firmware-Konfigurationen landen.

## 6. Spotify Developer App

Im Spotify Developer Dashboard eine App anlegen:

```text
https://developer.spotify.com/dashboard
```

Redirect URI exakt eintragen:

```text
http://127.0.0.1:8080/api/v1/spotify/callback
```

Nicht eintragen:

- `localhost`, wenn die App `127.0.0.1` erwartet.
- `http://<pi-ip>:8080/...`; Spotify erlaubt HTTP-Redirects praktisch nur für Loopback.

Wenn die Spotify-App im Development Mode ist, muss die eigene Spotify-E-Mail unter
„User Management“ freigeschaltet sein.

## 7. Erster Start

Für den ersten Start den neuesten Release-Tag bestimmen:

```bash
cd /home/lars/SpotFamServ
git fetch --tags origin
export WEB_IMAGE_TAG="$(git tag -l 'v*' --sort=-v:refname | head -n 1)"
test -n "$WEB_IMAGE_TAG"
git checkout -f "$WEB_IMAGE_TAG"
```

Backend-Image bauen, Web-Image ziehen und Stack starten:

```bash
docker compose build app messenger-worker
docker compose pull nginx
docker compose up -d
```

Beim allerersten Start kann Postgres länger brauchen. Wenn `up` wegen `db`-Healthcheck abbricht,
kurz warten und denselben Befehl erneut ausführen:

```bash
docker compose up -d
```

Weil `backend/` als Dev-Bind-Mount in den Container gemountet ist, kann `vendor/` nach dem Start
im Host-Pfad fehlen. Deshalb einmal Composer im Container ausführen:

```bash
docker compose exec -T app composer install --no-interaction --prefer-dist
```

Datenbank und Messenger-Transport vorbereiten:

```bash
docker compose exec -T app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec -T app php bin/console messenger:setup-transports --no-interaction
docker compose exec -T app php bin/console cache:clear
```

Healthcheck:

```bash
curl -i http://localhost:8080/api/v1/health
```

Erwartung: HTTP `200`.

Von einem anderen Gerät im LAN:

```bash
http://<pi-ip>:8080
```

## 8. Auto-Deploy per systemd aktivieren

Wenn der erste Start funktioniert, den tag-getriggerten Pull-Deploy aktivieren:

```bash
cd /home/lars/SpotFamServ
chmod +x deploy/*.sh
sudo cp deploy/systemd/spotfam-deploy.service /etc/systemd/system/
sudo cp deploy/systemd/spotfam-deploy.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now spotfam-deploy.timer
```

Status prüfen:

```bash
systemctl list-timers spotfam-deploy.timer
systemctl status spotfam-deploy.timer
journalctl -u spotfam-deploy.service -n 80 --no-pager
```

Release auslösen von der Entwicklungsmaschine:

```bash
git tag vX.Y.Z
git push origin vX.Y.Z
```

Der Timer prüft alle zwei Minuten auf neue `v*`-Tags. Das Web-Image wird in CI gebaut und aus
GHCR gezogen; der Pi baut das Frontend nicht.

Manuell deployen:

```bash
ssh lars@<pi-ip> '/home/lars/SpotFamServ/deploy/pi-deploy.sh'
```

## 9. Spotify-OAuth erstmalig verbinden

Auf dem Entwicklungsrechner oder Laptop einen SSH-Tunnel öffnen:

```bash
ssh -N -L 127.0.0.1:8080:localhost:8080 lars@<pi-ip>
```

Dann im Browser auf demselben Rechner:

```text
http://127.0.0.1:8080
```

In der Weboberfläche:

1. Admin/Login durchführen.
2. Familienprofil anlegen oder öffnen.
3. „Mit Spotify verbinden“ starten.
4. Spotify-Consent abschließen.
5. Wiedergabegerät einschalten.
6. Geräte abrufen und Standardgerät setzen.

Verifikation:

- Spotify-Status des Profils zeigt `connected`.
- Geräteabruf liefert mindestens ein Spotify-Connect-Gerät.
- Ein Playback-Test startet hörbar auf dem gewählten Gerät.

Der SSH-Tunnel ist nur für OAuth nötig. Im Alltag reicht die LAN-URL `http://<pi-ip>:8080`.

## 10. Reader / ESP32 anbinden

Der Reader sendet HTTP-Requests an den Server. Für den klassischen ESP32-Sketch muss
`firmware/spotfam_reader/secrets.h` passend gesetzt sein:

```c
#define WIFI_SSID "<wlan-name>"
#define WIFI_PASSWORD "<wlan-passwort>"
#define BACKEND_BASE_URL "http://<pi-ip>:8080"
#define READER_API_KEY "<gleiche-reader-api-key-wie-auf-dem-server>"
```

Die Pi-IP sollte stabil sein. Sonst zeigt der geflashte Reader nach einem DHCP-Wechsel auf die
falsche Adresse.

Backend-Kette ohne Hardware testen:

```bash
curl -X POST http://<pi-ip>:8080/api/v1/readers/scan \
  -H "Content-Type: application/json" \
  -H "X-API-Key: <READER_API_KEY>" \
  -d '{"reader_id":"wohnzimmer-1","card_uid":"<bekannte-card-uid>"}'
```

Mögliche Ergebnisse:

- `success`: Playback wurde gestartet.
- `no_device`: Es ist kein Reader- oder Profil-Standardgerät gesetzt.
- Auth-Fehler: `READER_API_KEY` stimmt nicht überein oder fehlt.

## 11. Betrieb und Wartung

Wichtige Befehle:

```bash
cd /home/lars/SpotFamServ
docker compose ps
docker compose logs -f app
docker compose logs -f nginx
docker compose logs -f messenger-worker
docker compose restart messenger-worker
```

Backups:

- `deploy/pi-deploy.sh` ruft vor Deploys `deploy/pi-backup.sh` auf.
- Backups liegen unter `backups/`.
- Standard-Rotation: letzte 7 Dumps.

Audio-Dateien:

- Standard-Hostpfad: `data/audio`.
- Nicht committen.
- Bei Quota-/Speicherproblemen über UI oder API aufräumen.

## 12. Troubleshooting

### `docker-compose` nicht gefunden

Das ist erwartbar. Dieses Projekt nutzt Compose v2:

```bash
docker compose ...
```

### Erster Start bricht wegen DB-Healthcheck ab

Postgres braucht beim ersten `initdb` manchmal länger. Kurz warten und wiederholen:

```bash
docker compose up -d
```

### App startet, aber Symfony meldet fehlende Vendor-Dateien

Composer im Container ausführen:

```bash
docker compose exec -T app composer install --no-interaction --prefer-dist
```

### OAuth landet nicht auf dem Pi

Das ist fast immer ein fehlender oder falscher SSH-Tunnel. Richtig ist:

```bash
ssh -N -L 127.0.0.1:8080:localhost:8080 lars@<pi-ip>
```

Browser-URL:

```text
http://127.0.0.1:8080
```

Spotify Redirect URI:

```text
http://127.0.0.1:8080/api/v1/spotify/callback
```

### Weboberfläche zeigt nicht den erwarteten Stand

Der Pi baut das Frontend nicht. Das Web-Image muss in GHCR für den deployten Tag existieren.
Logs prüfen:

```bash
journalctl -u spotfam-deploy.service -n 120 --no-pager
docker compose images
```

### Reader erreicht den Server nicht

Prüfen:

- Stimmt `BACKEND_BASE_URL` mit der aktuellen Pi-IP überein?
- Hat der Router eine DHCP-Reservierung für den Pi?
- Ist Port `8080` im LAN erreichbar?
- Ist `READER_API_KEY` auf Reader und Server identisch?

## 13. Sicherheitsnotizen

Diese Installation ist für ein privates LAN ausgelegt.

Pflicht:

- Keine Secrets committen.
- `.env`, `backend/.env.local` und Firmware-Secrets restriktiv behandeln.
- Deploy-Key read-only halten.
- `APP_SECRET` stabil und zufällig wählen.
- Router-DHCP-Reservierung setzen, statt IPs zu raten.

Offene Härtung je nach Einsatz:

- HTTPS vor die Weboberfläche setzen.
- `APP_ENV=prod` sauber durchtesten und aktivieren.
- Passwort-/Admin-Policy härten.
- Backups regelmäßig extern sichern.
