## Lessons Log

Einträge sind immutabel nach Erstellung. Bei Wiederholung eines Musters: Vorkommen-Count erhöhen, nicht neu anlegen.

---

### L-001 | 2026-06-01 | Deploy/rsync

**Fehlermuster:** `rsync` des ganzen Repos auf den Pi hing minutenlang – ein lokaler `pi-image/`-Ordner (2,7 GB OS-Image) wurde mitübertragen.
**Root Cause:** rsync ohne Exclude-Liste; große lokale Artefakte (Build-/Image-/Cache-Ordner) gehören nicht ins Deploy.
**Regel:** rsync zum Pi IMMER mit Excludes: `.git/ pi-image/ backend/vendor/ backend/var/cache/ backend/var/log/ frontend/node_modules/ frontend/dist/ *.log`. Vor großen Transfers `du -sh` auf Quelle prüfen.
**Vorkommen:** 1
**Status:** Aktiv

---

### L-002 | 2026-06-01 | Docker/Compose

**Fehlermuster:** Makefile-Targets (`docker-compose ...`) liefen auf dem Pi ins Leere.
**Root Cause:** Makefile nutzt Compose **v1** (`docker-compose`); der Pi hat nur das **v2-Plugin** (`docker compose`).
**Regel:** Auf dem Pi Compose v2 verwenden (`docker compose`) bzw. `COMPOSE='docker compose' make ...`. Compose-Befehle nicht hart auf v1 verdrahten.
**Vorkommen:** 1
**Status:** Aktiv

---

### L-003 | 2026-06-01 | DB/Compose

**Fehlermuster:** Erstes `docker compose up -d` brach ab: „dependency db failed to start: container unhealthy". Beim zweiten Versuch lief alles.
**Root Cause:** Beim Erststart dauern Postgres-`initdb` + `init.sql` länger als die Healthcheck-Retries → `depends_on: condition: service_healthy` schlug zu früh fehl. Kein echter Defekt.
**Regel:** Erststart-Race kennen: `up` einfach erneut ausführen, sobald die DB healthy ist. Optional `healthcheck.start_period` erhöhen.
**Vorkommen:** 1
**Status:** Aktiv

---

### L-004 | 2026-06-01 | Spotify/OAuth

**Fehlermuster:** OAuth-Login auf dem headless Pi nicht durchführbar – Spotify-Redirect geht auf `127.0.0.1:8080`, das aber auf dem Browser-Rechner (Windows/WSL) landet, nicht auf dem Pi.
**Root Cause:** Spotify erlaubt ohne TLS nur Loopback-Redirects. Der Pi hat keinen Browser; der Redirect muss den Pi erreichen.
**Regel:** OAuth einmalig über SSH-Local-Forward `ssh -L 127.0.0.1:8080:localhost:8080 lars@<pi>`; `SPOTIFY_REDIRECT_URI` und `FRONTEND_URL` auf `http://127.0.0.1:8080`. Token liegt danach serverseitig auf dem Pi; Alltag läuft tunnel-frei über die Pi-LAN-IP.
**Vorkommen:** 1
**Status:** Aktiv

---

### L-005 | 2026-06-01 | Shell/Tooling

**Fehlermuster:** Background-`ssh -N -L ...`-Tunnel starb sofort (77 ms), Log leer – Selbst-Kill.
**Root Cause:** `pkill -f "127.0.0.1:8080:localhost:8080"` matchte die eigene Wrapper-Kommandozeile (enthielt dasselbe Muster) und killte den frisch gestarteten Prozess mit.
**Regel:** `pkill -f <muster>` nie so wählen, dass es die eigene laufende Kommandozeile trifft. Prozesse gezielt per PID beenden oder ein eindeutiges Muster nutzen.
**Vorkommen:** 1
**Status:** Aktiv

---

### L-006 | 2026-06-01 | Deploy/Dev-Mount

**Fehlermuster:** App-Container startete, aber ohne `vendor/` (500er), obwohl der Dockerfile `composer install` ausführt.
**Root Cause:** Der Dev-Bind-Mount `./backend:/var/www/html` überdeckt zur Laufzeit das im Image gebaute `vendor/`. Da `vendor/` bewusst nicht übertragen wird, fehlt es im Mount.
**Regel:** Bei Dev-Bind-Mount nach `up` einmal `docker compose exec -T app composer install` ausführen (schreibt vendor in den gemounteten Host-Pfad). Für reines Prod-Deploy ohne Mount stattdessen das gebackene Image nutzen.
**Vorkommen:** 1
**Status:** Aktiv

---

### L-007 | 2026-06-01 | Ops/Reboot

**Fehlermuster:** Nach Pi-Reboot würde der Stack nicht von allein hochkommen.
**Root Cause:** Container haben keine `restart`-Policy.
**Regel:** Für Dauerbetrieb in `docker-compose.yml` je Service `restart: unless-stopped` setzen.
**Vorkommen:** 1
**Status:** Aktiv

---

### L-008 | 2026-06-01 | Net/SSH

**Fehlermuster:** Lange Pi-Befehle (`docker compose build`, `composer install`) brachen über SSH mit „client_loop: send disconnect: Broken pipe" ab.
**Root Cause:** Bei WLAN-Hiccup stirbt die SSH-Session und reißt den client-seitigen Befehl mit.
**Regel:** Lange Pi-Operationen abgekoppelt starten (`setsid bash -c "... > /tmp/x.log 2>&1"`) und per separater SSH-Session pollen. `ServerAliveInterval` setzen.
**Vorkommen:** 1
**Status:** Aktiv

---

### L-009 | 2026-06-01 | Deploy/Git

**Fehlermuster:** rsync-Kopie auf dem Pi soll ohne Datenverlust ein git-Clone werden (gleicher Pfad nötig, sonst ändert sich der Compose-Projektname → DB-Volume „weg").
**Root Cause:** `docker compose`-Projektname = Verzeichnis-Basename → ein anderer Pfad erzeugt ein neues Volume.
**Regel:** In-place konvertieren: im selben Pfad `git init` + `remote add` + `fetch` + `git reset --hard <tag>`. `reset --hard` überschreibt nur getrackte Dateien; git-ignorierte Secrets (`.env`, `backend/.env.local`, `frontend/dist`) bleiben erhalten. Vorher prüfen: `git check-ignore -v <secret-files>`. Zusätzlich Secrets nach `~/spotfam-secrets-backup` kopieren.
**Vorkommen:** 1
**Status:** Aktiv

---

### L-010 | 2026-06-01 | Deploy/Healthcheck

**Fehlermuster:** Direkt nach `docker compose up -d` lieferte der Healthcheck `000` (curl-Timeout), obwohl der Stack korrekt war.
**Root Cause:** Symfony-Cache-Warmup nach Container-Recreate dauert auf dem Pi mehrere Sekunden; ein einzelner Curl mit `set -e` bricht das ganze Skript ab.
**Regel:** Healthcheck mit Retry-Schleife (z. B. 5× alle 4 s) statt einem Versuch; Curl-Exit nicht unter `set -e` hart werten. Der erste Request nach Recreate ist absichtlich der Warmup.
**Vorkommen:** 1
**Status:** Aktiv

---

### L-011 | 2026-06-02 | Deploy/Frontend

**Fehlermuster:** Frontend-Änderungen (ganze Sprint-2-UI) erreichten den Pi nie; UI zeigte weiter v0.1.0.
**Root Cause:** `pi-deploy.sh` baut `frontend/dist` nur, wenn `frontend/` im Tag-Diff liegt **und** `pnpm` vorhanden ist. Der Pi hat **kein Node/pnpm** → Build wird still übersprungen (`|| log "WARN: altes dist bleibt"`). `frontend/dist` ist git-ignoriert, kommt also auch nicht per Pull. Zusätzlich war das Versionslabel hartcodiert (`Layout.tsx`) und verdeckte das Problem.
**Regel:** Frontend NICHT auf dem Runtime-Gerät bauen. Artefakt/Image in CI bauen und ausliefern (D-012, CI-Image). Versionslabel immer aus `package.json` ableiten (nie hartcodieren), damit der ausgelieferte Stand sichtbar ist. Bei Verdacht: ausgeliefertes Bundle prüfen (`curl .../assets/index-*.js | grep <feature-marker>`), nicht das Label.
**Vorkommen:** 1
**Status:** Aktiv

---

### L-012 | 2026-06-02 | Docker/Bind-Mount

**Fehlermuster:** Nach Ersetzen eines bind-gemounteten Verzeichnisses per `mv dist dist.old && tar -x` lieferte nginx weiter das ALTE Bundle.
**Root Cause:** Docker bind-mountet den **Inode**, den der Pfad bei Container-Start hatte. `mv` des gemounteten Verzeichnisses verschiebt diesen Inode (nach `dist.old`); der laufende Container folgt dem alten Inode, nicht dem Pfad.
**Regel:** Bind-gemountete Verzeichnisse **in place** ersetzen (Inhalt leeren + neu befüllen), NICHT umbenennen/verschieben. Wenn doch passiert: konsumierenden Container neu starten (`docker compose restart <svc>`), dann re-resolved der Mount den Pfad auf den neuen Inode.
**Vorkommen:** 1
**Status:** Aktiv

---

### L-013 | 2026-06-02 | CI/GHCR

**Fehlermuster:** Nach dem ersten `v*`-Tag-Build lag das Web-Image in GHCR vor, aber der Pi-Pull
schlug fehl – das Package war **private**. Kein Umschalten per `gh`/REST möglich.
**Root Cause:** GHCR legt ein neu gepushtes Package **private** an. Der vorhandene PAT hatte keinen
`read:packages`/`write:packages`-Scope, und für die Sichtbarkeit eines **User**-Packages gibt es
**keinen** REST-Endpoint (404) – Änderung nur über die Package-Settings-UI.
**Regel:** Nach dem **ersten** Image-Push einmalig die Package-Visibility in der UI auf **public** setzen
(`/users/<owner>/packages/container/<name>/settings`) – danach bleiben alle künftigen Pushes public.
Vor Verlass auf Pi-Auto-Deploy Pullbarkeit prüfen (`docker buildx imagetools inspect <ref>` ohne Login).
Alternative bei „private": Pi-`docker login ghcr.io` mit Read-PAT (Secret-Handling wie L-009).
**Vorkommen:** 1
**Status:** Aktiv

---

### L-014 | 2026-06-02 | Deploy/Self-Update

**Fehlermuster:** Der v0.2.2-Deploy zog `spotfamserv-web:latest` statt `:v0.2.2` und ohne die neue
Pull-Retry-Logik – obwohl `pi-deploy.sh` genau das jetzt enthält.
**Root Cause:** `pi-deploy.sh` checkt mitten im Lauf eine **neuere Version seiner selbst** aus
(`git checkout -f <tag>`). Der bereits laufende bash-Prozess führt aber die **alte** Skriptlogik aus
(Datei war beim Start geladen). Der `nginx`-Pull lief daher nur als Nebeneffekt von `docker compose up -d`
gegen die neue compose-Datei → `WEB_IMAGE_TAG` ungesetzt → `:latest`. Funktionierte nur, weil `:latest`
== v0.2.2-Inhalt und public. Zusätzlich: Da `docker-compose.yml` im Diff lag, rebuildete das app-Image
komplett (inkl. `COPY` von 86M vendor + `dump-autoload`) → Pi-Deploy ~25 min.
**Regel:** Änderungen an `pi-deploy.sh`/Deploy-Logik greifen erst **ab dem nächsten** Release; nicht auf
neue Skriptlogik im einführenden Release verlassen. Bei kritischen Deploy-Skript-Änderungen nach dem
Checkout einmalig manuell `pi-deploy.sh` (bzw. `WEB_IMAGE_TAG=<tag> docker compose up -d nginx`) ausführen.
Für schnellere Deploys ein `backend/.dockerignore` (vendor/var/cache) erwägen (Folge-WP).
**Vorkommen:** 1
**Status:** Aktiv

---
