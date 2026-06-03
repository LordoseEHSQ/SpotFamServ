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

### L-015 | 2026-06-02 | Deploy/Schema-Code-Reihenfolge

**Fehlermuster:** Während des v0.2.3-Deploys lieferte `/api/v1/profiles` kurz **HTTP 500**, obwohl
der Release korrekt war. `root=200`, `api=500` für ein paar Minuten.
**Root Cause:** Schema-Änderung (Spalte `needs_reauth`) + Code, der die Spalte über das Doctrine-Mapping
hydratisiert, im selben Release. `pi-deploy.sh`-Reihenfolge ist `up -d` (startet **neuen** App-Code)
**vor** `doctrine:migrations:migrate`. Im Fenster dazwischen selektiert der neue Code die noch fehlende
Spalte → SQL-Fehler → 500. Die Pull-Retry-Wartezeit auf das CI-Web-Image (hier 2 Versuche × 30 s)
**verbreitert** dieses Fenster zusätzlich, weil `up -d`/migrate erst nach dem Pull laufen.
**Regel:** Bei „neues Schema + Code, der es liest" ist ein kurzes 500-Fenster im Auto-Deploy
**erwartbar und transient** (kein Rollback-Grund) – nach `migrate` ist es weg (hier: „OK – v0.2.3 live,
Health 200"). Verifikation immer **nach** Deploy-Ende (Log-Zeile abwarten), nicht mitten im Lauf.
Für echte Zero-Downtime später: additive Migration in einem **vorgelagerten** Release deployen
(expand), Code erst im **Folge**-Release lesen (contract) – derzeit für das Heimsystem nicht nötig.
**Vorkommen:** 1
**Status:** Aktiv

---

### L-016 | 2026-06-02 | Release-Tag ohne package.json-Bump → Footer hinkt

**Fehlermuster:** Nach Deploy `v0.2.4` zeigte der SPA-Footer weiter „0.2.3". Kein Cache-Problem.
**Root Cause:** `__APP_VERSION__ = JSON.stringify(pkg.version)` (vite.config), aber `frontend/package.json`
wurde beim Release nicht hochgezählt (blieb 0.2.3). Der Footer spiegelt die **package.json-Version**,
NICHT den git-Tag/Image-Tag. Der ausgelieferte Code war korrekt v0.2.4 (Bundle-Hash + neue Endpoints/Strings
nachgewiesen).
**Regel (Release-Checkliste, PFLICHT):** Vor jedem Release-Tag `frontend/package.json` `version` auf den
Ziel-Tag setzen (ohne führendes „v"), committen, DANN taggen. Footer ist sonst kein verlässlicher
Deploy-Indikator – stattdessen Bundle-Hash / sichtbare UI-Features prüfen.
**Behebung:** Mit Release `v0.2.5` `frontend/package.json` auf `0.2.5` gebumpt → Footer stimmt wieder.
Release-Checkliste (package.json-Bump vor Tag) ist damit etabliert.
**Vorkommen:** 1
**Status:** Behoben

---

---

### L-017 | 2026-06-03 | systemd/lgpio

**Fehlermuster:** `spotfam-pi-reader.service` startete, aber `PN532-Init fehlgeschlagen ([Errno 2] No such file or directory: '.lgd-nfy-3')` in Dauerschleife.
**Root Cause:** `PrivateTmp=true` im systemd-Service isoliert den `/tmp`-Namespace des Prozesses. `lgpio` (I2C-Treiber-Abstraktionsschicht) erstellt Named Pipes (`.lgd-nfy*`) für die Kommunikation mit seinem internen Daemon – die beim direkten Aufruf im Working Directory landen, unter `PrivateTmp` aber in einem privaten tmpfs-Namespace, auf den `lgd` nicht zugreifen kann. Zusätzlich gehört `StartLimitIntervalSec` in `[Unit]`, nicht `[Service]` (systemd ignoriert es dort mit Warning).
**Regel:** Für Prozesse die `lgpio`/`pigpio`/GPIO-Bibliotheken mit eigenem Daemon-Protokoll nutzen: kein `PrivateTmp=true`, kein `ProtectSystem=full`. `StartLimitIntervalSec`/`Burst` in `[Unit]`. Beim Schreiben von systemd-Units für Hardware-nahe Python-Dienste erst ohne Hardening testen, dann schrittweise hinzufügen.
**Vorkommen:** 1
**Status:** Aktiv

---

### L-018 | 2026-06-03 | ESP/RFID-Hardware

**Fehlermuster:** ESP32-Planung ging implizit vom bestehenden MFRC522/SPI-Firmwarestand aus, obwohl der Nutzer fuer die ESP-Geraete denselben Reader-Typ wie am Pi bestaetigt hat: HW-147/PN532.
**Root Cause:** Vorhandenen Code (`spotfam_reader.ino`, `config.h`) als Hardware-Wahrheit behandelt statt die aktuelle Produkt-/Hardware-Anforderung gegen den Nutzerfakt zu verifizieren.
**Regel:** Vor ESP-Pinout, Loeten oder Firmware-Plan immer explizit den realen Reader-Typ als Fakt festhalten. PN532/HW-147 und MFRC522 sind nicht austauschbar: Bibliothek, Bus, Pinout und UID-Verifikation muessen im Plan getrennt bewertet werden.
**Vorkommen:** 1
**Status:** Aktiv

---

### L-019 | 2026-06-03 | Prozess/Autonomie

**Fehlermuster:** Nach bestaetigtem Plan wurde erneut auf Nutzerfreigabe fuer den naechsten Schritt gewartet, statt autonom bis zum naechsten echten Gate/Blocker weiterzuarbeiten. Zusaetzlich waren Dry-Run- und Modellwahl-Gates nicht als harte Blocker verankert.
**Root Cause:** Plan-vor-Code wurde faelschlich als fortlaufende Stop-Erlaubnis interpretiert; es fehlte eine explizite Regel fuer Planbestaetigung -> Dry-Run -> autonome Umsetzung -> Stop nur bei Blocker.
**Regel:** Nach Planbestaetigung ist Dry-Run/Blind-Spot-Review mit staerkstem verfuegbarem Reasoning-Modell absoluter Blocker vor Code. Danach autonom mit Sonnet oder GPT-5.5 umsetzen, bis echter Hardware-/Security-/Planabweichungs-/Test-Blocker erreicht ist. Reine Doku/Uebersetzung mit Haiku, falls verfuegbar; sonst kleinsten schnellen Fallback benennen.
**Vorkommen:** 1
**Status:** Aktiv

---

### L-020 | 2026-06-03 | Symfony/Env-Prozessoren ohne committetes `.env`

**Fehlermuster:** Neue Parameter `%env(bool:AUDIO_EXTRACTOR_ENABLED)%` hätten den Container-Boot zum
Absturz gebracht, sobald die Env-Variable fehlt – es gibt **kein committetes `backend/.env`** (Projekt
nutzt `.env.dev`/`.env.test` + reale Docker-Env; CI kopiert `.env.example`→`.env`).
**Root Cause:** `bool:`/`int:`-Prozessoren verlangen einen Wert; ohne `.env`-Default und ohne gesetzte
Variable wirft Symfony beim Resolve. Lokale Tests scheitern zudem an Host-PHP 8.3 (Plattform-Pin 8.5.6 →
`platform_check.php` Fatal) → Tests/PHPStan/Console müssen im `php:8.5.6-*`-Container laufen.
**Regel:** Neue Env-Parameter IMMER mit Fallback verdrahten:
`%env(<typ>:default:<fallback_param>:VAR_NAME)%` plus `<fallback_param>` unter `parameters:`. Nie auf ein
`.env` verlassen. Verifikation neuer DI-Verdrahtung mit `php bin/console lint:container` im 8.5-Container
(`docker run --rm -v "$PWD":/app -w /app php:8.5.6-cli-alpine ...`), Tests brauchen ein temporäres
`.env` (gitignored: `cp .env.example .env`, vor Commit wieder entfernen).
**Vorkommen:** 1
**Status:** Aktiv

---

### L-021 | 2026-06-03 | yt-dlp `--match-filter` verwirft Quellen mit unbekannter Dauer

**Fehlermuster:** `--match-filter "duration < 1800"` blockierte im E2E **legitime** Direkt-Dateien
(generic extractor, `duration=NA`) komplett – „does not pass filter, skipping". Der `?`-Suffix
(`duration<1800?`) und ein einzelnes `duration<1800|!duration` halfen NICHT (`|` ist kein OR
innerhalb eines Filterausdrucks).
**Root Cause:** Vergleichsoperatoren in `--match-filter` verwerfen Einträge, deren Feld fehlt.
ODER-Logik entsteht nur durch **mehrere** `--match-filter`-Flags, nicht durch `|` im selben String.
**Regel:** Dauer-Limit immer als zwei Flags verdrahten: `--match-filter "duration<N"`
**plus** `--match-filter "!duration"` (kurz genug ODER Dauer unbekannt). Der Prozess-Timeout
bleibt der harte Backstop für unbekannt-lange Downloads. Solche Filter NUR per echtem
E2E gegen eine Quelle mit `duration=NA` verifizieren – Unit-Tests fangen das nicht.
**Vorkommen:** 1
**Status:** Aktiv

---

### L-022 | 2026-06-03 | `BinaryFileResponse` → 500 ohne `symfony/mime`

**Fehlermuster:** Download-Endpunkt warf HTTP 500: „You cannot guess the mime type as the Mime
component is not installed." `BinaryFileResponse::prepare()` ruft `getMimeType()`, sobald kein
`Content-Type`-Header gesetzt ist – `symfony/mime` ist im Projekt nicht installiert.
**Root Cause:** Ohne expliziten `Content-Type` rät HttpFoundation den MIME-Typ via `symfony/mime`.
**Regel:** Bei `BinaryFileResponse` IMMER `Content-Type` explizit setzen (wir kennen das Format
ohnehin), statt eine neue Dependency aufzunehmen. Download-Pfade per E2E testen, nicht nur per
Unit-Test (der Mock-Pfade die `prepare()`/`ResponseListener`-Kette nicht durchläuft).
**Vorkommen:** 1
**Status:** Aktiv
