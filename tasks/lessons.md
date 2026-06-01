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
