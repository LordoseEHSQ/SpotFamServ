# Changelog

## [Unreleased]

## [0.5.4] – 2026-06-04 — Fix: Provisioning Jobs-Routing-Kollision

### Fixed
- **`GET /provisioning/jobs/next` warf 500** (`Could not convert database value "next" to uuid`):
  Die Admin-Route `GET /provisioning/jobs/{jobId}` fing den Agent-Pfad `/jobs/next` ab und
  versuchte `"next"` als UUID zu laden. Behoben über Route-`priority` auf `/jobs/next` (matcht
  jetzt vor `/jobs/{jobId}`), ohne den OpenAPI-Vertrag zu ändern. Via `router:match` verifiziert
  (`/jobs/next` → `get_next_job`, `/jobs/<uuid>` → `get_job`). Damit funktioniert die
  Job-Abfrage des Flash-Agents.

## [0.5.3] – 2026-06-04 — Fix: esptool-v5.3-Chip-Erkennung

### Fixed
- **Flash-Agent erkennt ESP32 real:** Der Chip-Bezeichnungs-Parser erwartete das Format
  `Chip is …`, esptool **v5.3.0** gibt jedoch `Chip type: …` aus. Folge: Chip-Erkennung
  schlug bei jeder Runde fehl (`Chip-Bezeichnung nicht in esptool-Ausgabe gefunden`), der
  Agent meldete nie ein Gerät → Reader-Station blieb leer. Parser unterstützt jetzt beide
  Formate; Test-Fixtures auf die **reale** esptool-v5.3.0-Ausgabe (auf dem Pi erfasst)
  umgestellt, inkl. Absicherung gegen die `Detecting chip type…`-Zeile. (Code war ohne
  echte Hardware entwickelt – jetzt gegen ESP32-D0WD-V3 verifiziert.)

## [0.5.2] – 2026-06-04 — Fix: Provisioning-Doctrine-Mapping

### Fixed
- **Reader-Station funktionsfähig:** Das Doctrine-ORM-Mapping für das `Provisioning`-Modul
  (`DetectedDevice` / `FlashArtifact` / `FlashJob`) fehlte in `doctrine.yaml`. Folge: alle
  DB-gestützten Provisioning-Endpunkte (`/provisioning/devices`, `/devices/detect`, Jobs)
  warfen zur Laufzeit `500` (`classNotFoundInNamespaces`). In den Unit-Tests (ohne DB/HTTP)
  unentdeckt geblieben. Mapping-Eintrag ergänzt.

## [0.5.1] – 2026-06-04 — Fix: Deploy-Healthcheck

### Fixed
- **Auto-Deploy-Healthcheck repariert:** Neuer öffentlicher `GET /api/v1/health` (DB-Ping, kein Auth).
  Nach Aktivierung der projektweiten Admin-Auth (v0.5.0) pollte `pi-deploy.sh` das jetzt geschützte
  `/api/v1/profiles` → `401` und markierte jeden Deploy fälschlich als „FEHLER". `pi-deploy.sh`
  (Default-`HEALTH_URL`) und die systemd-Unit (`Environment=HEALTH_URL`) zeigen nun auf `/health`.

## [0.5.0] – 2026-06-04 — Reader-Station + Admin-Auth

> Zwei Features: die Flash-/Provisioning-Station („Reader-Station") und projektweite Admin-Auth
> (Session-Login mit HttpOnly-Cookie + CSRF, kein localStorage, kein OIDC). Maschinen-Endpunkte
> (ESP-Reader, Flash-Agent) bleiben per `X-API-Key` erreichbar. HW-0 (PN532 löten + funktionaler
> RFID-Scan) bleibt bewusst offen (D-022): bewiesen sind Flash-Pfad + Chip-Detection, nicht der Scan.

### Added
- **Admin-Authentifizierung:** Session-Login (HttpOnly-Cookie, kein localStorage) für den gesamten
  Web-/Admin-Bereich; Login-Seite, Logout, Route-Schutz, 401-Handling.
- **CSRF-Schutz:** Double-Submit-Token (`XSRF-TOKEN`-Cookie + `X-XSRF-TOKEN`-Header); Maschinen-Endpunkte
  (ESP-Reader, Flash-Agent) bleiben per `X-API-Key` ausgenommen.
- **Auth-API + Admin-Command:** Endpunkte `/api/v1/auth/login|logout|me|csrf`; Console-Command
  `app:admin:upsert` (Admin-Account aus Env).
- **Authentifizierter Firmware-Upload (Web-UI):** `POST /api/v1/provisioning/artifacts` (nur eingeloggt;
  Server berechnet sha256/Größe, Agent prüft weiter Chip+Hash).
- **Flash-/Provisioning-Station (Reader-Station):** ESP32 per USB am Pi erkennen, registrierte
  sha256-geprüfte Firmware flashen, Status live per Polling verfolgen.
- **Backend – Modul `Provisioning`:** Entities `DetectedDevice` / `FlashArtifact` / `FlashJob`;
  sieben additive Endpunkte unter `/api/v1/provisioning/*` (Agent via `FLASH_AGENT_API_KEY`,
  Web/Admin offen); ActivityLog-Audit; Console-Command `app:provisioning:register-artifact`.
- **Flash-Agent (`firmware/flash_agent/`):** unprivilegierter Python-Host-Dienst (systemd) für
  Geräte-Discovery, Chip-Detection und Flash via esptool; Chip-Whitelist, sha256-Verify,
  Port-Lock; CLI `detect` / `flash --dry-run` / `run`.
- **Frontend:** Seite „Reader-Station" mit Live-Geräteliste (Polling), Flash-Dialog mit
  Chip-Mismatch-Sperre und Job-Fortschritt.
- **CI:** Job „Flash-Agent (Python)" (`py_compile` + `pytest`).

## [0.4.0] – 2026-06-03 — Audio-Extractor + ESP32-Reader-Provisioning

> Erster Tag mit dem Audio-Extractor. Liefert alles seit `v0.3.2` aus: Audio-Extractor,
> ESP32-Reader-Provisioning (#28/#46) sowie die zuvor unter „Unreleased" geführten Sprint-4-
> Punkte (WP1/WP2). Lokal x86_64 verifiziert inkl. echter YouTube-Extraktion (CC-BY); Pi/arm64
> wird durch dieses Deploy erstmals gebaut. R7 (data/audio-Schreibrechte) im Deploy-Skript gelöst.

### Audio-Extractor (Feature mit Persistenz + Update-Modus) (D-019/D-020)
- **Neues Backend-Modul `AudioExtractor`** (Ports & Adapters): URL → `yt-dlp` (Audio) → `ffmpeg` (Transkodierung) → **persistente Ablage im Benutzerbereich**. Normales Feature, **kein Toggle** (D-020 revidiert D-019). Synchron (Plan D-A), Schutzgrenzen `AUDIO_EXTRACTOR_TIMEOUT_SECONDS` (240) + `AUDIO_EXTRACTOR_MAX_DURATION_SECONDS` (1800) gegen blockierte php-fpm-Worker; nginx `fastcgi_read_timeout 300s`.
- **Endpunkte (additiv, oasdiff non-breaking):** `GET /config` (Formate/Bitraten/Limits/Engine-Version), `POST /extract` (extrahiert + speichert, 201 mit Datei-Metadaten), `GET /files` (Liste + Gesamtgröße), `GET /files/{name}` (Download), `DELETE /files/{name}` (Löschen), `POST /update` (yt-dlp-Self-Update).
- **Persistenz:** gemeinsamer Host-Bereich `${AUDIO_STORAGE_HOST_DIR:-./data/audio}` → Container `/data/audio`, per Dateisystem erreichbar (CD-Brennen). **Kein DB-Schema** – Liste = Dateisystem-Scan. `.gitignore` für `/data/audio` + `backend/var/audio`.
- **Update-Modus:** yt-dlp als self-update-fähiges **Release-Binary** (zipapp, D-020 revidiert pip aus D-B), `yt-dlp -U` über `POST /update`, Versionsanzeige im UI-Header.
- **Formate:** MP3 (128/192/256/320 kbps) + WAV (PCM). Nur **legale Quellen** (eigene/CC/Public-Domain); Spotify-Ripping bewusst **nicht** umgesetzt (DRM-Umgehung, §95a UrhG). UI-Rechtshinweis statt technischer Sperre.
- **Security:** `symfony/process` mit Argument-Array (keine Command-Injection), nur http(s)-Scheme (SSRF-Abwehr), stderr gekürzt; **Path-Traversal-Abwehr** im Storage (Name ≠ Pfad, realpath-Containment). Domain-Exceptions im `ExceptionSubscriber` gemappt (422/502). Offen: kein hartes Quota (nur Größenanzeige).
- **Dependency:** `symfony/process ^7.4`. **Docker:** `backend/Dockerfile` um `ffmpeg`, `python3`, `curl` + yt-dlp-Binary (`/opt/yt-dlp`, www-data-beschreibbar) erweitert (arm64/Pi-tauglich); compose-Volume `/data/audio`.
- **Frontend:** Seite `/tools/audio-extractor` (Extraktions-Formular, Dateiliste mit Download/Delete, Engine-Update-Button + Versionsanzeige), statischer Nav-Eintrag „Werkzeuge → Audio-Extractor".
- **Tests:** 26 PHPUnit-Tests (Validierungs-Boundary, Storage inkl. 6 Path-Traversal-Fälle, Controller alle Endpunkte), PHPStan Level 8 sauber, `lint:container` ok, `pnpm build` grün.

### ESP32-Reader-Provisioning (Software-Schnitt, HW-0 offen)
- **Backend:** Kurzlebige Reader-Claims (`POST/GET /api/v1/readers/claims`, `POST …/activate`) mit gehashtem Code, Einmalnutzung, per-Reader-API-Key-Ausstellung und Activity-Log; `GET /readers` liefert `has_api_key`.
- **OTA:** Minimalvertrag `GET /api/v1/readers/firmware/manifest` (Board/Kanal/SemVer; `204` ohne Artefakt).
- **Frontend:** „Reader hinzufügen“ mit Claim-Code, Captive-Portal-Payload und Status-Polling bis `claimed`.
- **Doku:** `docs/esp-reader-provisioning.md` (Runbook); `docs/reader-box-mapping.md` um ESP vs. Pi/Legacy ergänzt.
- **CI:** Job `Firmware Compile (ESP32)` — reproduzierbarer Baseline-Compile des MFRC522-Sketches (`arduino-cli`, `esp32:esp32@3.3.8`); PN532/Portal/NVS/OTA-Client ausstehend, HW-0 offen.
- **HW-0-Werkzeug:** Diagnose-Sketch `firmware/spotfam_pn532_probe/` (I2C/SPI umschaltbar, PN532-Erkennung, UID im Pi-Format) + Runbook `docs/hw0-pn532-runbook.md` mit USB-Passthrough- und Evidence-Schritten; CI kompiliert die Probe mit (`Adafruit PN532@1.3.4`/`Adafruit BusIO@1.17.4`). Physische HW-0-Ausführung bleibt offen (aus WSL2 kein serieller Port).

### WP2 – Kartenverwaltung als DataGrid (#40)

#### Backend (WP2a)
- **`GET /api/v1/profiles/{profileId}/rfid-cards` liefert jetzt `binding: {id, name} | null`** – additiv/nullable, oasdiff non-breaking. Vermeidet N+1: neuer UseCase `ListRfidCardsWithBindings` macht 3 SQL-Queries total (Cards + Bindings-Batch + PlaylistRef-Batch). Neue Interface-Methoden `findByCardIds` (CardPlaylistBinding) und `findByIds` (SpotifyPlaylistReference), je mit 1 SQL-Query.
- OpenAPI `GET rfid-cards` 200-Response mit `binding`-Schema dokumentiert.
- PHPUnit: 4 neue Tests (`ListRfidCardsWithBindingsTest`): leer, ohne Binding, mit Binding, gemischt.

#### Frontend (WP2b)
- **`CardsPage` vollständig neu als shadcn-Table DataGrid.** Spalten: UID (monospace) · Label (inline edit: Click → Input → Enter/Blur speichert) · Playlist (Binding-Badge) · Aktionen.
- **Kein fixed-overlay-Modal mehr.** Label-Bearbeitung inline; Binding-Änderung via Row-Select; Löschen via `AlertDialog`; Anlegen via expandierbarem Footer-Panel.
- **Scan-to-Create** erhalten (Polling nur bei aktivem Scan-Modus).
- `tsc --noEmit` + `pnpm build` grün.

### WP1 – Playback-Diagnose-Logging (#39)
- `ProcessScan` loggt `device_source` (`'reader'|'profile'`) + `device_id` in `scan_event.details` beim `SUCCESS`- und `NO_DEVICE`-Outcome. Ermöglicht Pi-Diagnose ohne DB-Query.
- `StartPlaybackTest`: neuer Test `test_reresolves_stale_reader_device_by_name_and_retries` (Reader-Gerät Stale-Re-Resolve, kein `profileRepo->save()` da Caller die Mapping-Ownership hat).
- Kern-Logik (Reader→Gerät → Fallback Profil-Default + Stale-Re-Resolve) war bereits in v0.2.5 vollständig implementiert.

### WP3 – Pi-Reader-Daemon vollständig (#41)
- `firmware/pi_reader/secrets.example.env` hinzugefügt (war im README referenziert, fehlte).
- `.gitignore`-Fix: `!secrets.example.env` (war durch `secrets.*.env`-Glob mitignoriert).
- Daemon-Code, systemd-Unit und Karten-Präsenz-Entprellung waren bereits vollständig in v0.2.5 vorhanden.

### WP4 – Gerätewahl-Onboarding (#42)
- `docs/pi-deployment.md`: Neuer Runbook-Abschnitt „Wiedergabegerät sicherstellen" (D-S4-DEV): Reader→Gerät (ReadersPage) vs. Profil-Default (ProfileDetailPage), Priorität, `no_device`-Outcome-Hinweis.
- Frontend: `ReadersPage` (Reader→Gerät-Select) + `ProfileDetailPage` (Standardlautsprecher mit „Kein Standardlautsprecher konfiguriert."-Anzeige) waren bereits vollständig vorhanden.

---

## [v0.2.3 – v0.2.5] – 2026-06-02 (Sprint 3: Reader-Lifecycle, Pi-Leser, Spotify-Status)

> Sprint 3 retroaktiv geschlossen per D-S4-VER. Milestones: Sprint 3 (#4) closed.
> Einzel-Tags: v0.2.3 (Spotify-Status), v0.2.4 (Sprint 3 Interim), v0.2.5 (Playlist-Binding-Fix).

### v0.2.5 – Playlist-Binding-Fix (#34 / #38)
- **Playlist-Bindings aus echter Spotify-Bibliothek** – Binding-UI holte Playlists bisher aus
  der gespeicherten DB-Bibliothek statt aus der aktuellen Spotify-Bibliothek des Profils.
  Behoben: API-Call gegen echte Spotify-Bibliothek; Footer-Versionsfix (L-016).

### v0.2.4 – Sprint 3 Interim: Reader-Lifecycle, Pi-Leser, Pro-Reader-Keys, Wobie→Connect

### v0.2.3 – Spotify-Status refresh-getrieben (#25, D-014)

### Feature – Reader→Box-Mapping / Multi-Raum (D-015)

#### Hinzugefügt
- **Jeder RFID-Leser kann einer festen Box (Spotify-Connect-Gerät) zugewiesen werden.** Ein Scan
  spielt dann auf der Box des Lesers statt auf dem Standard-Lautsprecher des Karten-Profils
  (Multi-Raum). Ohne Zuweisung bleibt das bisherige Verhalten (Profil-Default) – additiv und
  rückwärtskompatibel.
  - Schema: `reader_device.default_spotify_device_id` + `default_device_name`
    (Migration `Version20260602140000_reader_default_device`, additiv/nullable, kein Datenverlust).
  - **Leser registrieren sich beim ersten Scan automatisch** (`ProcessScan`, D-R1 A) und werden so im
    Admin-UI konfigurierbar – ohne Auth-Gewicht (kein API-Key beim Self-Register).
  - `StartPlayback` akzeptiert nun optional einen Gerätenamen und re-resolved eine **stale** Box-ID
    einmalig per Name (wie beim Profil-Default), damit Playback nach Box-Reconnect nicht bricht.
  - Neue Endpunkte: `GET /api/v1/readers` (Liste), `PUT|DELETE /api/v1/readers/{readerId}/default-device`.
  - Frontend: neue Seite **„RFID-Leser"** (Box zuweisen/entfernen, Box-Auswahl aus dem Geräte-Inventar).
- **Bekannte Grenze (Spotify):** ein Account spielt nur auf einem Gerät gleichzeitig. Echtes
  paralleles Multi-Raum funktioniert über **verschiedene Profile/Accounts** (jedes Profil eigenes
  Premium-Konto). Dieselbe Karte/dasselbe Profil kann nicht gleichzeitig in zwei Räumen spielen.

### Fix – Spotify-Status: irreführendes „abgelaufen" (#25, D-014)

#### Behoben
- **Status spiegelt jetzt echten Re-Auth-Bedarf, nicht den Access-Token-Takt.** Bisher zeigte die UI
  nach Ablauf des 1h-Access-Tokens „abgelaufen", obwohl der `SpotifyTokenManager` automatisch per
  Refresh-Token erneuert. Neuer Status: `connected | reauth_required | not_connected`.
  - Neues persistiertes Flag `spotify_account_link.needs_reauth` (Migration
    `Version20260602120000_spotify_needs_reauth`, additiv, Default `false`).
  - `SpotifyTokenManager` setzt das Flag nur bei **dauerhaftem** Refresh-Fehler
    (`SpotifyTokenInvalidException`/`invalid_grant`) + ActivityLog `spotify_reauth_required`; transiente
    5xx/Netzfehler setzen es **nicht**. Erfolgreicher Refresh und Re-Consent (`ExchangeSpotifyCode`) löschen es.
  - `GetSpotifyStatus::resolve()` ist die **einzige** Status-Quelle; die Duplikat-Logik in
    `FamilyProfileController` wurde entfernt.
  - Frontend: Enum `expired`→`reauth_required` in allen Consumern (Profile/Dashboard/MusicTab/Activity),
    klare Labels („Neu verbinden" / „Neu-Autorisierung erforderlich").

### Deploy – Frontend via CI-gebautes Image (#20, D-012/D-013)

#### Geändert
- **Frontend wird nicht mehr auf dem Pi gebaut.** Das Web-Image (SPA `frontend/dist` + nginx +
  `default.conf`) wird in CI gebaut und nach GHCR gepusht (multi-arch amd64+arm64). Behebt L-011
  (Pi hat kein Node/pnpm → Sprint-Stände erreichten die UI nie).
  - Neuer Workflow `.github/workflows/release-web-image.yml`: tag-getriggert (`v*`) buildx→GHCR-Push
    (`ghcr.io/lordoseehsq/spotfamserv-web:<tag>` + `latest` + `sha-<short>`), auf PRs nur Build-Validierung.
  - Neues `docker/frontend/Dockerfile`: Build-Stage `--platform=$BUILDPLATFORM` (Node/Vite nativ auf
    amd64, **nie** unter QEMU) + Runtime-Stage `nginx:alpine` ohne `RUN` (arm64 = nur COPY-Layer).
  - Neues Root-`.dockerignore`: hält Secrets (`.env`, `backend/.env.local`, `secrets.h`) aus dem
    (öffentlichen) Build-Context/Image-Layer.
  - `docker-compose.yml`: `nginx` zieht das GHCR-Image (`${WEB_IMAGE_TAG:-latest}`), `frontend/dist`-
    Bind-Mount entfernt; `default.conf`- und `backend/public:ro`-Mounts bleiben.
  - `deploy/pi-deploy.sh`: pnpm-Build-Schritt entfernt; zieht das Web-Image (mit Retry gegen die
    CI-Build-Latenz) und pinnt `WEB_IMAGE_TAG` auf den deployten `v*`-Tag.
  - GHCR-Package ist **public** (SPA-Bundle ohne Secrets → kein Pi-Login/PAT nötig).

### Fix – Spotify-App-Config über die Oberfläche (D-011)

#### Behoben
- **System-Einstellungen waren wirkungslos** – Client-ID/Secret/Redirect aus der UI
  (`SpotifyAppConfiguration`) wurden zur Laufzeit ignoriert; OAuth/Token-Refresh/Playback nutzten
  ausschließlich die env-Werte. Neuer `SpotifyCredentialsProvider` liefert die effektiven Credentials
  jetzt pro Request: **DB-Config (Source of Truth) vor env-Fallback**. `SpotifyHttpApiClient`,
  `GetSpotifyAuthorizationUrl` und `SpotifyOAuthController` beziehen Client-ID/Secret/Redirect/Scopes
  daraus. Ein UI-Save greift ohne Neustart.

#### Neu/Geändert
- **Echte Credential-Validierung** – „Validieren" prüft Client-ID/Secret real gegen Spotify
  (client_credentials-Grant) statt nur deren Vorhandensein; neue Methode
  `SpotifyApiClientInterface::checkClientCredentials()`.
- **Präzedenz** – DB-Config gewinnt nur, wenn vollständig (ID + Secret + Redirect); sonst env.
  Kein Vermischen von DB- und env-Feldern.
- **Scopes** bleiben code-seitig (kanonische Liste in `SpotifyCredentialsProvider`).
- **Tests** – Provider-Präzedenz (DB/env/unvollständig) und `ValidateSpotifyAppConfig` (OK/abgelehnt/unvollständig).

### Sprint 2 – Core E2E (Spotify → Wobie via ESP32)

#### Neu
- **Dedizierter Default-Device-Endpunkt (#9, D-009)** – `PUT /api/v1/profiles/{id}/default-device`
  (Body `device_id`, optional `device_name`) und `DELETE …/default-device`. Neuer UseCase
  `SetDefaultDevice`, entkoppelt von der Device-Governance (`AssignDevice`). Neue Spalte
  `family_profile.default_device_name` (Migration `Version20260601090000`) persistiert den
  Anzeigenamen; `FamilyProfileController` liefert ihn jetzt aus (vorher hardcoded `null`).
- **Frontend: Standardlautsprecher setzen** – Im Profil-Tab „Lautsprecher" können erkannte
  Spotify-Geräte direkt als Standard gesetzt/entfernt werden (`useSetDefaultDevice`).
- **Stale-Device-Re-Resolve (R2)** – `StartPlayback` löst eine veraltete (ephemere) Spotify-
  `device_id` bei `SpotifyNoDeviceException` einmalig über den gespeicherten Gerätenamen neu auf,
  aktualisiert die ID und wiederholt die Wiedergabe.
- **#8-Härtung** – `ExchangeSpotifyCode` schreibt `spotify_connected` (ActivityLog) und ruft
  `markValidated()` (Display-Name direkt nach Consent). `SpotifyTokenManager` schreibt
  `spotify_token_refreshed` bei jedem Refresh.
- **Backend-Tests** – Neue Unit-Tests für `SetDefaultDevice`, `StartPlayback` (inkl. Stale-Re-Resolve)
  und `ProcessScan` (Outcome-Mapping success/unknown_card/no_device/token_invalid).

#### Behoben
- **Firmware-Outcome-Case-Bug (#10, D-010, Showstopper)** – Die ESP32-Firmware prüfte
  `outcome=="SUCCESS"`/`"DEBOUNCED"` (uppercase), das Backend liefert lowercase. Folge:
  erfolgreiches Playback wurde am Reader als Fehler (4 Blinks) signalisiert. `signalResult()`
  vergleicht jetzt `success`/`debounced`.
- **`spotify_user_display_name`** – `FamilyProfileController` gab die Spotify-User-ID statt des
  Display-Namens zurück; nutzt jetzt `getSpotifyDisplayName()` (Fallback User-ID).
- **Fehlende `SpotifyProfileNotFoundException`** – Die in `DisconnectSpotify` referenzierte Klasse
  existierte nicht (potenzieller Fatal Error / PHPStan-Finding). Ergänzt und im `ExceptionSubscriber`
  auf HTTP 404 gemappt.
- **`firmware/secrets.h.example`** – Beispiel-IP von `192.168.1.143` auf die Pi-IP `192.168.1.91` korrigiert.

## [v0.1.0] – 2026-06-01 (Foundation)

> Erster getaggter Release. Backend + Frontend laufen auf dem Pi, ESP32-Reader-Firmware
> vorhanden, Governance-Prozess etabliert, automatisiertes Pull-Deploy aktiv.

### Neu
- **Tag-getriggertes Pi-Auto-Deploy (Pull-basiert, Decision D-008)** – Der Pi ist ein
  read-only git-Clone (Deploy-Key) und pollt per `systemd`-Timer (alle 2 Min) auf neue
  `v*`-Tags. `deploy/pi-deploy.sh` ist idempotent (fetch tags → `pg_dump`-Backup →
  checkout neuester Tag → conditional build/composer → `up -d` → migrate → Healthcheck),
  `deploy/pi-backup.sh` sichert vor jeder Migration mit Rotation (`backups/`, `KEEP=7`).
  Units in `deploy/systemd/`, Runbook in `deploy/README.md`.
- **`restart: unless-stopped`** für `app`/`nginx`/`db` (Auto-Start nach Pi-Reboot).
- **Branch Protection auf `main`** – PR-Pflicht, 5 required CI-Checks (Backend 8.4/8.5,
  Frontend, Trivy, oasdiff), lineare History, kein Force-Push.
- **CI-Härtung** – Node24-Opt-in (`FORCE_JAVASCRIPT_ACTIONS_TO_NODE24`) vor der
  Node20-Abkündigung (2026-06-16).
- **ESP32-RFID-Reader-Firmware (`firmware/spotfam_reader/`)** – Arduino-Sketch für ESP32 + MFRC522: liest Karten, sendet `POST /api/v1/readers/scan` ans Backend, zwei Taster lösen `/next` und `/previous` aus. Geheimnisse (WLAN, API-Key, Backend-URL) liegen in einer git-ignorierten `secrets.h` (Vorlage: `secrets.h.example`); Pinout/Verhalten in `config.h`. UID-Format: Uppercase-Hex ohne Trenner. Toolchain: arduino-cli + esp32:esp32@3.3.8.
- **Reader-Steuer-Endpunkte `POST /api/v1/readers/next` und `/previous`** – Wirken auf das Profil der aktuellen Wiedergabe-Session. Neue `PlaybackSessionStoreInterface` + `CachePlaybackSessionStore` (cache.app, TTL 6 h) merkt sich beim erfolgreichen Scan das zuletzt abspielende Profil pro Reader (Fallback global). Neuer UseCase `ProcessReaderControl`, neuer Outcome `no_session`.
- **`READER_API_KEY` jetzt wirksam verdrahtet** – Über `backend/.env.local` (Dev) bzw. `docker-compose.yml` (Pi) gesetzt; die Reader-Endpunkte verlangen `X-API-Key`/`Bearer`. Zuvor wurde der Key nicht an den App-Container durchgereicht (stiller Default = offen).
- **Spotify API-Restriction Handling (Nov 2024)** – Spotify hat seit November 2024 den API-Zugriff auf Podcast/Hörbuch-Inhalte und Playlist-Schreiboperationen für nicht-genehmigte Apps gesperrt. Das System kommuniziert diese Einschränkungen nun klar im UI: Playlist-Detail zeigt "Inhalt nicht über API verfügbar" mit erläuterndem Text, Playlist-Erstellen-Dialog enthält einen Hinweis auf die erforderliche Spotify Developer Extended Quota Genehmigung.
- **`playlist-read-collaborative` Scope hinzugefügt** – Neue Autorisierungs-URL enthält jetzt auch den `playlist-read-collaborative` Scope für kooperative Playlists.
- **Spotify-Verbindung trennen** – Im Tab „Spotify" des Teilnehmerprofils gibt es jetzt einen roten „Trennen"-Button mit Bestätigungsdialog. Er ruft `DELETE /api/v1/profiles/{id}/spotify/disconnect` auf und löscht das gespeicherte OAuth-Token. Danach kann die Verbindung mit frischen Berechtigungen neu autorisiert werden.
- **Backend: `DisconnectSpotify` UseCase + `DELETE /disconnect` Endpoint** – Neuer UseCase und Endpoint für das Entfernen der `SpotifyAccountLink`-Entität. Repository-Interface und -Implementierung um `delete()`-Methode erweitert.
- **Hinweis-Banner bei fehlenden Scopes** – Im Spotify-Tab erscheint ein Amber-Banner der erklärt, wann und wie das Trennen+Neuverbinden nötig ist (z. B. bei 403 auf Playlist-Funktionen).
- **Pi-Deployment (Raspberry Pi 4B, Debian 13, aarch64)** – Stack (app/nginx/db) läuft via Docker Compose v2 auf dem Pi; Frontend statisch über nginx (gleicher Origin, `/api/v1`). Runbook + Specs in `docs/pi-deployment.md`. Spotify-OAuth via SSH-Loopback-Tunnel (`127.0.0.1:8080`).
- **Governance/Prozess** – Sprints (GitHub Milestones), WorkPackages + Bugs (GitHub Issues, Templates), Projects-v2-Board (#2) als Single Source of Truth. SemVer-Versionierung (Start `v0.1.0`), Tag am Sprint-Ende → triggert Pi-Deploy. Working-Memory im Repo: `tasks/{plan-*,lessons,decisions,todo}.md`, `docs/PROJECT_MAP.md`. Standing-Regeln unter `.cursor/rules/` (planning-discipline mit Plan-/4-Lens-/Dry-Run-Pflicht, project-architecture, parallel-branch-workflow, sprint-workflow).

### Geändert
- **`show_dialog=true` bei Spotify-Autorisierung** – Der Spotify-OAuth-Consent-Dialog wird nun immer angezeigt, auch wenn der Nutzer bereits eingeloggt ist. Damit werden fehlende Scopes bei Reconnect zuverlässig erteilt.
- **`decodeAndMapErrors`: 403 differenziert gemappt** – Spotify-interne 403-Fehler werden jetzt unterschieden: "Insufficient client scope" → `SpotifyScopeMissingException` (HTTP 403), generisches "Forbidden" → `SpotifyApiException` (HTTP 422). Verhindert falsch-positive "fehlende Berechtigung"-Meldungen.
- **`getPlaylistTracks`: `fields`-Parameter entfernt, `additional_types` ergänzt** – Der `fields`-Filter verursachte bei bestimmten Playlist-Typen 403-Fehler in der Spotify API. Ersetzt durch `additional_types=track,episode`.
- **`client.ts`: 204-Antworten korrekt verarbeitet** – HTTP 204 (No Content) löst keinen JSON-Parse-Fehler mehr aus.

### Behoben
- **PlaylistList: Playlist-Namen wieder sichtbar** – Das `owner_id`-Badge (Spotify-User-ID wie `31ord36in...`) wurde vollständig aus der Playlist-Liste entfernt. Es verdeckte zuvor durch seine Breite den eigentlichen Playlist-Namen im Flex-Layout, sodass nur die kryptische ID sichtbar war.
- **Playlist-Erstellung: 403-Fehler klar kommuniziert** – Wenn das Spotify-Token der Verbindung die Scopes `playlist-modify-private` / `playlist-modify-public` nicht enthält (z. B. weil die Verbindung vor dem Hinzufügen dieser Scopes autorisiert wurde), zeigt der Erstell-Dialog jetzt eine verständliche Fehlermeldung mit Handlungsanweisung (Spotify-Verbindung neu autorisieren) statt eines generischen 500-Fehlers.
- **Backend: SpotifyApiClient mappt 403 auf SpotifyScopeMissingException** – In `SpotifyHttpApiClient::createPlaylist()` und `addTracksToPlaylist()` wird ein HTTP-403 von Spotify jetzt als `SpotifyScopeMissingException` geworfen. Dies führt zu einem sauberen HTTP 403 Problem+JSON in der API-Antwort statt eines unbehandelten 500-Fehlers.

### Profilgebundener Musikarbeitsbereich „Mini Spotify" + globale Spotify-App-Konfiguration (2026-03-18)

#### Überblick

Erweiterung des bestehenden Admin-/Governance-Systems um einen vollständig integrierten Musikarbeitsbereich pro Teilnehmerprofil. Kernstück ist der neue Tab **„Musik"** im Teilnehmerprofil, der eine dreispaltige Arbeitsfläche mit Playlist-Verwaltung, Spotify-Suche und Mini-Player bietet. Ergänzt wird dies durch eine zentrale **globale Spotify-App-Konfiguration** (trennt Client Credentials von teilnehmerbezogenen Tokens), neue Player-API-Endpunkte im Backend sowie vollständige ActivityLog-Integration für alle Musik-Aktionen.

---

#### Architektonische Entscheidung: Trennung App-Credentials vs. Benutzer-Tokens

Klare Separation zwischen:
- **`SpotifyAppConfiguration`** (systemweit, 1× pro Installation): Client ID, Client Secret (verschlüsselt), Redirect URI, Scope-Defaults, Validierungsstatus
- **`SpotifyAccountLink`** (pro Teilnehmer, bereits vorhanden): Access/Refresh-Token, Spotify User ID, Scopes, neu: `spotify_display_name`, `last_validated_at`

#### Entscheidung: Mini-Player-Platzierung

Der Mini-Player wird als **festes rechtes Panel im Musik-Tab** umgesetzt – nicht als globales Sticky-Panel. Begründung: Der Player ist kontextspezifisch für den Teilnehmer, der gerade bearbeitet wird. Ein globaler Player würde den Governance-Kontext (Profil, Gerät, Regeln) verschleiern.

---

#### Backend – Neue Entität: SpotifyAppConfiguration

- Datei: `backend/src/Module/Spotify/Domain/SpotifyAppConfiguration.php`
- Tabelle: `spotify_app_configuration` (Singleton-Semantik via `is_active`)
- Felder: `spotify_client_id`, `spotify_client_secret` (verschlüsselt via `spotify_encrypted_string`), `redirect_uri`, `scope_defaults`, `config_status` (unconfigured/configured/validated/error), `last_check_at`, `last_check_note`, `is_active`
- Methode `isComplete()`: prüft Client ID + Secret + Redirect URI
- Methode `recordCheck()`: setzt Status + Zeitstempel nach Validierungsversuch

#### Backend – Repository & Port

- `SpotifyAppConfigRepositoryInterface` (Application Port)
- `DoctrineSpotifyAppConfigRepository` (Infrastructure)
- Registriert in `services.yaml`

#### Backend – SpotifyAccountLink erweitert

- Neues Feld `spotify_display_name` (VARCHAR 255, nullable)
- Neues Feld `last_validated_at` (TIMESTAMP, nullable)
- Methode `markValidated(?string $displayName)` – setzt beide Felder in einem Aufruf
- `ValidateSpotifyConnection` UseCase jetzt `final class` statt `final readonly class` (wegen Repository-Injektion), speichert Display Name bei Validierung

#### Backend – Neue DTOs

- `SpotifyTrackDto`: id, name, uri, artists, albumName, albumCoverUrl, durationMs
- `SpotifyPlaybackStateDto`: isPlaying, progressMs, currentTrack, deviceId, deviceName, deviceType, contextUri, contextType, volumePercent
- `SpotifyPlaylistTracksDto`: items (SpotifyTrackDto[]), total, offset, limit
- `SpotifySearchResultDto` erweitert: jetzt auch `tracks` (TrackItems mit type, artists, album_cover_url etc.)

#### Backend – SpotifyApiClientInterface & SpotifyHttpApiClient erweitert

Neue Methoden:
- `getCurrentPlayback()` → `?SpotifyPlaybackStateDto` (GET /me/player)
- `pausePlayback()` (PUT /me/player/pause)
- `nextTrack()` (POST /me/player/next)
- `previousTrack()` (POST /me/player/previous)
- `getPlaylistTracks()` (GET /playlists/{id}/tracks mit fields-Parameter)
- `createPlaylist()` (POST /users/{userId}/playlists)
- `addTracksToPlaylist()` (POST /playlists/{id}/tracks)

Bugfix: Inline FQCN `\App\Module\...\SpotifyNoDeviceException` in `put()` durch Import am Dateikopf ersetzt.

#### Backend – Neue UseCases

- `GetCurrentPlayback` – Holt aktuellen Player-Status
- `PausePlayback` – Pausiert Wiedergabe
- `SkipToNext` – Nächster Titel
- `SkipToPrevious` – Vorheriger Titel
- `GetPlaylistTracks` – Tracks einer Playlist (paginiert)
- `CreateSpotifyPlaylist` – Erstellt neue Spotify-Playlist, schreibt ActivityLog
- `AddTracksToPlaylist` – Fügt Tracks hinzu, schreibt ActivityLog
- `GetSpotifyAppConfig` – Liest aktive DB-Konfig oder fällt auf env vars zurück
- `SaveSpotifyAppConfig` – Speichert Konfig, schreibt ActivityLog
- `ValidateSpotifyAppConfig` – Prüft Konfigurationsvollständigkeit, schreibt ActivityLog

#### Backend – Neuer Controller: SpotifySystemController

Endpunkte:
- `GET /api/v1/system/spotify` – Aktuelle App-Konfiguration lesen (Client Secret wird **nicht** zurückgegeben, nur `has_client_secret`)
- `PUT /api/v1/system/spotify` – Konfiguration speichern
- `POST /api/v1/system/spotify/validate` – Konfiguration validieren

#### Backend – SpotifyController erweitert

Neue Endpunkte:
- `GET  /api/v1/profiles/{id}/spotify/player` – Aktueller Wiedergabestatus
- `POST /api/v1/profiles/{id}/spotify/player/pause` – Pause
- `POST /api/v1/profiles/{id}/spotify/player/next` – Nächster Titel
- `POST /api/v1/profiles/{id}/spotify/player/previous` – Vorheriger Titel
- `GET  /api/v1/profiles/{id}/spotify/playlists/{playlistId}/tracks` – Playlist-Tracks
- `POST /api/v1/profiles/{id}/spotify/playlists/{playlistId}/tracks` – Tracks hinzufügen
- `POST /api/v1/profiles/{id}/spotify/playlists/create` – Playlist erstellen

Bugfix: Search-Endpunkt gibt jetzt korrekte 400-Response mit `error`-Schlüssel statt `playlists`.

#### Backend – ActivityLog: Neue Typen

- `TYPE_PLAYLIST_CREATED`, `TYPE_PLAYLIST_CHANGED`, `TYPE_PLAYBACK_PAUSED`
- `TYPE_PLAYBACK_NEXT`, `TYPE_PLAYBACK_PREVIOUS`, `TYPE_SEARCH_EXECUTED`

#### Backend – Migration

- Datei: `backend/migrations/Version20250318100000_spotify_music.php`
- Neue Tabelle `spotify_app_configuration` (UUID PK, verschlüsseltes Secret, Index auf `is_active`)
- Erweiterung `spotify_account_link` um `spotify_display_name`, `last_validated_at`
- Migration erfolgreich ausgeführt (8 SQL-Queries)

---

#### Frontend – API-Client: spotify.ts

- Datei: `frontend/src/api/endpoints/spotify.ts` (neu strukturiert)
- `spotifySystemApi`: getConfig, saveConfig, validate (System-Konfiguration)
- `spotifyMusicApi`: getPlaylists, getPlaylistTracks, createPlaylist, addTracks, search, getPlayer, play, pause, next, previous
- `spotifyApi` (Legacy): rückwärtskompatible Exporte für Setup-Wizard (getStatus, validate, getDevices etc.)
- Neue Typen: `SpotifyAppConfigDto`, `SpotifyTrackItem`, `SpotifyPlaybackState`, `SpotifyPlayerResponse`, `SpotifySearchResponse` (mit tracks + playlists)

#### Frontend – Neue Hooks

- `useSpotifyAppConfig` + `useSaveSpotifyAppConfig` + `useValidateSpotifyAppConfig` – System-Konfig
- `useSpotifyPlayer` – Polling (5s Intervall), `usePlaySpotify`, `usePauseSpotify`, `useNextTrack`, `usePreviousTrack`
- `useSpotifyPlaylists` + `useSpotifyPlaylistTracks` + `useCreateSpotifyPlaylist` + `useAddTracksToPlaylist`
- `useSpotifySearch` – Debounced, ab 2 Zeichen

#### Frontend – Neue Komponenten

- `components/music/MiniPlayer.tsx` – Kompakter Player mit Cover, Titel, Künstler, Fortschritt, Steuerung; Governance-Hinweise (kein Lautsprecher, Spotify nicht verbunden)
- `components/music/PlaylistList.tsx` – Sidebar-Playlist-Liste mit Suche, Erstell-Dialog, Refresh
- `components/music/PlaylistDetail.tsx` – Trackliste mit Cover, Künstler, Album, Dauer; Play-Button pro Track und für gesamte Playlist
- `components/music/SpotifySearch.tsx` – Suchpanel mit Tracks + Playlists; Inline-Play + „Zu Playlist hinzufügen"-Dialog
- `components/music/MusicTab.tsx` – Dreispaltiges Layout: Playlist-Sidebar (links), Detail/Suche (Mitte), Mini-Player + Status (rechts); Governance-Guards für fehlende Sys-Konfig oder nicht verbundenen Spotify-Account

#### Frontend – Neue Seite: SystemPage

- `pages/SystemPage.tsx` – Systemeinstellungen mit Spotify-App-Konfigurationsformular
- Zeigt: Konfigurationsstatus-Badge, Client ID, Redirect URI, Secret-Eingabe mit Sichtbarkeits-Toggle
- Warnung wenn Konfiguration aus Env-Vars gelesen wird
- Validieren-Button mit Ergebnisanzeige
- Status-Grid (Client ID ✓, Secret ✓, Redirect URI ✓, Vollständig ✓)

#### Frontend – ProfileDetailPage erweitert

- Neuer Tab **„Musik"** (Icon: Headphones) mit `MusicTab`-Komponente
- Importiert `MusicTab` aus `components/music/MusicTab`

#### Frontend – Navigation & Routes

- Neue Route `/system` → `SystemPage`
- Neuer Sidebar-Eintrag „Systemeinstellungen" mit `SlidersHorizontal`-Icon in Gruppe „System"

---

#### Offene Punkte

- Lautstärkeregelung im Mini-Player: Spotify erlaubt `PUT /me/player/volume` – sinnvoll erst mit Device-Tracking
- Hörzeit-Regeln: Player-Endpunkte blockieren noch nicht bei Tageslimit-Überschreitung – Backend-Prüfung in `StartPlayback` geplant
- Suche speichert keine History im ActivityLog (nur explizite Aktionen werden geloggt)
- `spotify_app_configuration`: Wenn sowohl DB-Konfig als auch Env-Vars vorhanden, hat DB Vorrang – Hinweis im UI vorhanden
- Tests für neue UseCases (CreateSpotifyPlaylist, AddTracksToPlaylist, GetCurrentPlayback etc.) noch ausstehend

### Professionelles Admin-Refactoring: Governance-UI, Geräteverwaltung, Aktivitätslog (2026-03-18)

#### Überblick
Vollständiges Frontend-Refactoring in Richtung einer professionellen, governance-fähigen Admin-Anwendung. Einführung von shadcn/ui + Tailwind v4 als Design-System, neuer Informationsarchitektur, Geräteverwaltung mit Discovery-Transparenz und Device-Governance, Teilnehmerverwaltung als klassische Stammdatenpflege sowie einem systemweiten Aktivitäts-Log. Im Backend: drei neue Entitäten, eine Migration, zwei neue Module (Device, ActivityLog) mit vollständigen UseCases und REST-Controllern.

---

#### Frontend – Neue Dependencies

- **tailwindcss v4** (`@tailwindcss/vite`): Vite-Plugin-basierte Integration, keine `tailwind.config.js` nötig
- **Radix UI Primitives**: Tabs, Dialog, AlertDialog, Select, Tooltip, DropdownMenu, Label, ScrollArea, Avatar, Checkbox, Switch, Progress
- **class-variance-authority**, **clsx**, **tailwind-merge**: Klassenzusammenführung für shadcn/ui-Komponenten
- **lucide-react**: Einheitliche Icon-Bibliothek
- **zod** + **@hookform/resolvers**: Vorbereitet für validierte Formulare (react-hook-form)

#### Frontend – Design-System

- **`src/index.css`**: Vollständige CSS-Variable-Palette via `@theme {}` (Tailwind v4): Background, Foreground, Primary, Secondary, Muted, Accent, Destructive, Border, Ring, Sidebar, Success, Warning, Info. Alle Werte in OKLCH für hohe Farbtreue.
- **`src/lib/utils.ts`**: `cn()`-Utility (clsx + tailwind-merge), `formatDate()` und `formatDateRelative()` Hilfsfunktionen

#### Frontend – Neue UI-Komponenten (`src/components/ui/`)

Manuell implementierte shadcn/ui-kompatible Komponenten:
- `button.tsx`: 6 Varianten (default, destructive, outline, secondary, ghost, link), 4 Größen
- `badge.tsx`: 8 Varianten inkl. success, warning, info, muted
- `card.tsx`: Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter
- `input.tsx`, `textarea.tsx`, `label.tsx`: Formular-Grundelemente
- `separator.tsx`: Horizontal/vertikal
- `tabs.tsx`: Radix-basiert, Underline-Style für Admin-Look
- `dialog.tsx`, `alert-dialog.tsx`: Modal-Dialoge mit Overlay und Keyboard-Handling
- `table.tsx`: Professionelle Datentabelle
- `select.tsx`: Vollständiges Radix Select mit Scroll-Buttons
- `tooltip.tsx`: Radix Tooltip mit Provider
- `avatar.tsx`: Radix Avatar mit Fallback
- `skeleton.tsx`: Lade-Platzhalter
- `switch.tsx`, `scroll-area.tsx`, `progress.tsx`: Radix-basierte Komponenten

#### Frontend – Layout & Navigation

- **`Layout.tsx`** vollständig neu: Professionelle Sidebar (56px Breite) mit gruppierten Navigationsbereichen (Verwaltung, Betrieb, System), aktiver Route-Hervorhebung, App-Header mit Icon und Versionsnummer, ScrollArea für lange Navigationen

#### Frontend – Neue Seiten & Routen

- **`/profiles`** (`ProfilesPage.tsx`): Datensatzliste als professionelle Tabelle mit Spalten Name, Status, Spotify-Verbindung, Standardlautsprecher, Setup-Status, Letzte Aktivität. Suchfeld, Neu-Dialog, Lösch-Bestätigung per AlertDialog, Direkt-Navigation ins Detail.
- **`/profiles/:id`** (`ProfileDetailPage.tsx`): Detailformular mit 6 Tabs:
  - *Allgemein*: Name/Beschreibung bearbeiten, Metadaten, Dirty-Check
  - *Spotify*: Verbindungsstatus, Validierung, OAuth-Redirect
  - *Lautsprecher*: Standardgerät anzeigen, Live-Geräteliste über Spotify-API laden
  - *RFID-Karten*: Kartentabelle
  - *Hörzeit*: MVP-Platzhalter
  - *Aktivität*: Teilnehmer-spezifischer Aktivitätsverlauf aus `activity_log`
- **`/devices`** (`DevicesPage.tsx`): Zentrale Geräteverwaltung mit:
  - Discovery-Panel (Ergebnisanzeige, Trigger-Button, transparenter Hinweis auf Spotify-API-Beschränkung)
  - Gerätedatensatzliste mit Verfügbarkeits- und Zuweisungs-Badges
  - Gerätedetail-Panel (Split-View) mit Tabs Übersicht/Erkennung/Governance
  - Governance-Dialog mit Zuweisungsformular und Konflikt-Bestätigung (AlertDialog)
- **`/activity`** (`ActivityPage.tsx`): Systemweiter Aktivitätsverlauf mit Severity-Filter, Zeitstempel, Profil-Tag und Ereignistyp-Label
- **`/`** (`DashboardPage.tsx`): Überarbeitetes Dashboard mit Stat-Cards (Teilnehmer, Geräte, Aktivitäten, RFID), Teilnehmer-Status-Übersicht, letzte Aktivitäten, letzter Discovery-Run

#### Frontend – Neue Hooks & API-Endpunkte

- **`src/api/endpoints/devices.ts`**: Typen und API-Funktionen für SpotifyDevice, DeviceDiscoveryRun, AssignDevice
- **`src/api/endpoints/activity.ts`**: Typen und API-Funktionen für ActivityLog
- **`src/hooks/useDevices.ts`**: `useDevices`, `useDevice`, `useLatestDiscoveryRun`, `useDiscoveryRuns`, `useTriggerDiscovery`, `useAssignDevice`
- **`src/hooks/useActivity.ts`**: `useActivity` mit Profil- und Severity-Filter
- **`src/api/endpoints/profiles.ts`**: Erweitert um `status`, `spotify_status`, `spotify_user_display_name`, `default_device_name`, `setup_complete`, `setup_percent`, `last_activity_at`

---

#### Backend – Neue Migration

- **`Version20250318000000_device_governance.php`**: 19 SQL-Statements
  - `family_profile`: Neues Feld `status VARCHAR(32) DEFAULT 'active'`
  - `spotify_device`: Neue Tabelle mit Governance-Feldern (`assignment_mode`, `assignment_updated_at`, `assignment_note`, `discovery_status`, `last_discovery_run_id`, etc.)
  - `device_discovery_run`: Discovery-Protokoll-Tabelle mit `raw_payload JSONB`
  - `activity_log`: Aktivitäts-Log mit `details JSONB`, GIN-Indizes auf JSONB-Felder
  - Alle FK-Constraints mit `NOT VALID` (kein Full-Table-Lock)
  - Sinnvolle Indizes: BTREE auf FKs, Sortierfelder; GIN auf JSONB; Partial Indexes vorbereitet

#### Backend – Neues Modul: Device

- **`SpotifyDevice` Entity**: Persistiertes Geräteobjekt mit vollständiger Governance-Logik (`assign()`, `markSeen()`, `hasConflict()`, `isAssignedTo()`). Zustandskonstanten für `assignment_mode` (unassigned/assigned/reserved/locked/shared) und `discovery_status`.
- **`DeviceDiscoveryRun` Entity**: Discovery-Lauf-Protokoll mit `finish()`-Methode
- **`SpotifyDeviceRepositoryInterface`** + `DoctrineSpotifyDeviceRepository`
- **`DeviceDiscoveryRunRepositoryInterface`** + `DoctrineDeviceDiscoveryRunRepository`
- **`RunDeviceDiscovery` UseCase**: Iteriert alle/ein Profil(e), ruft Spotify-API ab, persistiert neue und aktualisierte Geräte, schreibt ActivityLog-Eintrag, schließt Discovery-Run ab
- **`AssignDevice` UseCase**: Prüft Konflikte (`hasConflict()`), verlangt `force=true` bei Übernahme-Konflikt (`409 Conflict`), schreibt ActivityLog
- **`DeviceController`**: REST-Endpunkte `GET /devices`, `GET /devices/{id}`, `PUT /devices/{id}/assign`, `POST /devices/discover`, `GET /devices/discovery-runs/latest`, `GET /devices/discovery-runs`

#### Backend – Neues Modul: ActivityLog

- **`ActivityLog` Entity**: Vollständige Typen- und Severity-Konstanten. Profil- und Entity-Referenz, JSONB-Details
- **`ActivityLogRepositoryInterface`** + `DoctrineActivityLogRepository`: Paginiert, nach Profil und Severity filterbar
- **`ActivityLogController`**: `GET /api/v1/activity-log` mit `profile_id`, `severity`, `limit`, `offset` Query-Parametern

---

#### Offene Punkte (nächste Iteration)

- **FamilyProfile-Controller**: `status`-Feld und erweiterte DTO-Felder (`spotify_status`, `default_device_name`, etc.) sind im Frontend vorbereitet, aber das Backend gibt noch die alten Felder zurück. Der `FamilyProfileController` muss entsprechend erweitert werden.
- **ActivityLog-Integration im bestehenden Code**: `ProcessScan`-UseCase sollte ActivityLog-Einträge schreiben; `ValidateSpotifyConnection` ebenfalls.
- **Hörzeit-Regeln**: Tab existiert im Frontend, Backend-Implementierung fehlt noch.
- **Tests**: Neue UseCases (RunDeviceDiscovery, AssignDevice) brauchen Unit-Tests.
- **Setup-Wizard**: Vorhandener Wizard noch ohne Tailwind-Styling – kann in nächster Iteration vereinheitlicht werden.

### Fixed – Docker & Infrastruktur (2026-03-15)
- **Makefile**: `docker compose` (Plugin-Syntax) auf `docker-compose` (Standalone v1.29.2) umgestellt; Variable `COMPOSE` eingeführt für einfaches Umschalten; neue Targets `logs`, `ps`, `cc` (Cache Clear) hinzugefügt; alle `exec`-Befehle nutzen jetzt `$(COMPOSE) exec -T`; Hilfemeldung erweitert.
- **composer.json**: `symfony/flex` und `symfony/runtime` in `allow-plugins` eingetragen – verhinderte `composer install` im Docker-Build ohne interaktive Eingabe.
- **Dockerfile**: `libsodium-dev` und `sodium` PHP-Extension hinzugefügt (Pflicht für Token-Verschlüsselung); `cache:clear` aus Build entfernt (DB-Abhängigkeit im Build-Kontext); Build-Ablauf auf `composer install --no-scripts --no-autoloader` + `dump-autoload --optimize` vereinfacht.
- **docker-compose.yml**: PostgreSQL-Port von `5432` auf `5433` gemappt (Konflikt mit laufendem `averiq_postgres`-Container auf 5432).
- **TokenEncryptionService**: `SODIUM_CRYPTO_SECRETBOX_NPUBBYTES` als Klassen-Konstante entfernt – Symfony's Reflection-Mechanismus konnte die Extension-Konstante beim DI-Container-Compile nicht auflösen; durch Integer-Literal `24` ersetzt.
- **services.yaml**: `StepHandlerInterface`-Eintrag mit `abstract: true` entfernt – Interfaces dürfen nicht als getaggte Services registriert werden.
- **SpotifyHttpApiClient**: Syntax-Fehler in `decodeAndMapErrors()`-Signatur behoben (fehlendes `$` vor `_context`-Parameter).
- **symfony/uid**: Als Abhängigkeit ergänzt (`symfony/uid ^7.4`) – `UuidFactory` war nicht auflösbar.
- **Ergebnis**: System startet vollständig (`db`, `app`, `nginx`); 3 Migrationen erfolgreich ausgeführt; `GET /api/v1/profiles` antwortet mit HTTP 200.


### Architektur-Härtung: Vollständiger Refactoring-Durchgang (2026-03-15)

#### Überblick
Einmalige, vollständige Architektur-Härtung des gesamten Backends und Frontends. Alle kritischen und hohen Findings aus dem vorherigen Architektur-Review wurden in einem einzigen Refactoring-Durchgang umgesetzt. Der MVP-Scope (RFID-Scan, Spotify-Playback, Setup-Wizard, Profile-CRUD) bleibt funktional unverändert.

#### Backend — Repository-Interfaces (Ports & Adapters)

**8 neue Repository-Interfaces** unter `Application/Port/` angelegt (bisher injizierte jeder UseCase direkt die konkrete Doctrine-Klasse):
- `FamilyProfile/Application/Port/FamilyProfileRepositoryInterface`
- `Rfid/Application/Port/RfidCardRepositoryInterface`
- `Rfid/Application/Port/CardPlaylistBindingRepositoryInterface`
- `Spotify/Application/Port/SpotifyAccountLinkRepositoryInterface`
- `Spotify/Application/Port/SpotifyPlaylistReferenceRepositoryInterface`
- `SetupWizard/Application/Port/ProfileSetupSessionRepositoryInterface`
- `Scan/Application/Port/ReaderDeviceRepositoryInterface`
- `Scan/Application/Port/ScanEventRepositoryInterface` (verschoben aus `Application/`)

Alle 8 Doctrine-Repositories implementieren das jeweilige Interface nun via `implements`. `services.yaml` wurde um die Interface→Service-Bindings ergänzt.

**21 Use Cases** wurden auf die neuen Interfaces umgestellt (Infra-Imports entfernt, nur noch Port-Interfaces im Application-Layer sichtbar):
- FamilyProfile: `Create`, `Update`, `Delete`, `Get`, `List`
- Rfid: `Create`, `Get`, `Update`, `Delete`, `List`, `GetCardPlaylistBinding`, `SetCardPlaylistBinding`
- Spotify: `ExchangeSpotifyCode`, `GetSpotifyStatus`, `CreatePlaylistReference`, `ListPlaylistReferences`, `StartPlayback`
- SetupWizard: `GetWizardState`, `GetCompleteness`, `SetCurrentStep`, `SubmitStep`
- Scan: `ListScanEvents`, `ProcessScan`

`SpotifyTokenManager` (Infrastructure) verwendet jetzt `SpotifyAccountLinkRepositoryInterface` statt der konkreten Repository-Klasse.

#### Backend — Cross-Modul-Entkopplung (`ProcessScan`)

`ProcessScan` injizierte direkt drei Repositories aus fremden Modulen (Rfid, Spotify). Gelöst durch das Ports & Adapters Pattern:
- **Neues Port-Interface** `Scan/Application/Port/ScanCardResolverInterface` mit `resolveCard(string $cardUid): ?ScanCardContext`
- **Neues Value Object** `Scan/Domain/ScanCardContext` (cardId, profileId, playlistUri)
- **Neuer Adapter** `Rfid/Infrastructure/Scan/RfidScanCardResolver` implementiert das Interface — kennt Rfid- und Spotify-Repositories, liegt im Rfid-Modul

`ProcessScan` injiziert nun ausschließlich `ScanCardResolverInterface` + `ReaderDeviceRepositoryInterface` + `ScanEventRepositoryInterface` — kein Cross-Modul-Wissen mehr.

#### Backend — Reader-Lookup aus Controller in UseCase verschoben

`ScanController` injizierte bisher `DoctrineReaderDeviceRepository` direkt. Der Reader-Lookup (readerId → readerDeviceId) wurde in `ProcessScan` verlagert. Der Controller übergibt nur noch den rohen `reader_id`-String aus dem Request-Body. `ScanController` ist damit deutlich dünner.

#### Backend — Privater `logScan()`-Helper in `ProcessScan`

Sieben identische `scanEventRepository->append()`-Aufrufe wurden in einem privaten `logScan()`-Helper konsolidiert. Sauberer, wartbarer Code.

#### Backend — Domain-Exception-Hierarchie bereinigt

Bisher erbten Spotify-Exceptions von `HttpException` (Application/Shared) — Domain-Layer hatte HTTP-Status-Code-Wissen:
- **Neue abstrakte Basis** `Spotify/Domain/Exception/SpotifyDomainException extends \RuntimeException` — kein HTTP-Bezug
- **5 konkrete Domain-Exceptions** bereinigt: `SpotifyNotConnectedException`, `SpotifyTokenInvalidException`, `SpotifyNoDeviceException`, `SpotifyScopeMissingException`, `SpotifyOAuthStateException` — alle `extends SpotifyDomainException`
- **`StepValidationException`** bereinigt: `extends \DomainException` statt `HttpException`
- **`SpotifyException`** (alte Basis) als `@deprecated` Alias erhalten für Rückwärtskompatibilität
- **`ExceptionSubscriber`** erweitert: vollständiges Mapping Domain-Exception FQCN → HTTP-Status-Code (404, 401, 422, 403, 400)
- **`ProblemJsonResponse`** um `fromDomainException()`-Methode erweitert; interne `build()`-Methode für beide Pfade

#### Backend — `SubmitStep`: Strategy-Pattern

Der große `switch`-Block in `SubmitStep` wurde durch das Strategy-Pattern ersetzt:
- **Neues Interface** `SetupWizard/Application/StepHandler/StepHandlerInterface` mit `supports(string $stepKey): bool` + `handle(string $profileId, string $stepKey, array $payload): void`
- **5 konkrete Handler**: `ProfileStepHandler`, `SpotifyValidateStepHandler`, `DefaultSpeakerStepHandler`, `PlaybackTestStepHandler`, `PassthroughStepHandler`
- `SubmitStep` injiziert `iterable $handlers` via DI-Tagged-Iterator `setup_wizard.step_handler`
- `services.yaml` mit DI-Tagging für alle Handler ergänzt

#### Backend — `FamilyProfileRequest` (DTO-Konsolidierung)

`FamilyProfileCreateRequest` und `FamilyProfileUpdateRequest` waren identisch. Zusammengeführt zu `FamilyProfileRequest`. `FamilyProfileController` angepasst. Beide alten Dateien gelöscht.

#### Backend — Fehlende Getter ergänzt

- `ReaderDevice`: `getName(): ?string` ergänzt
- `ScanEvent`: `getReaderDeviceId(): ?string`, `getRfidCardId(): ?string`, `getFamilyProfileId(): ?string` ergänzt

#### Backend — `routes/api.yaml` bereinigt

Doppelte `api_v1`-Key-Definitionen und überflüssige Controller-Einträge entfernt. Alle Routen laufen über Attribute-Routing und `routes.yaml`.

#### Frontend — TypeScript-Typfehler behoben

`CardPlaylistBindingDto` in `api/endpoints/rfid.ts` war syntaktisch ungültig (`interface ... | null`). Korrigiert zu `type CardPlaylistBindingDto = { ... } | null`.

#### Frontend — Tote Seite gelöscht

`pages/ProfileSetupPage.tsx` war nicht geroutet und wurde gelöscht.

#### Frontend — `STEP_LABELS` zentralisiert

Neue Datei `features/setup-wizard/stepLabels.ts` als single source of truth für Step-Labels. `WizardStepper.tsx` und `StepSummary.tsx` importieren daraus (vorher je eigene lokale Konstante).

#### Frontend — `useRfidCards`-Hook extrahiert

Neue Datei `hooks/useRfidCards.ts` mit stabilen Query-Keys (`rfidCardKeys`) und allen RFID-Mutations. `CardsPage.tsx` nutzt diese Hooks statt lokaler Mutations/Queries.

#### Frontend — Query-Key-Konsolidierung

`ScanLogsPage` nutzt `useProfiles()` aus `hooks/useProfiles.ts` statt eigenem `useQuery(['profiles'], ...)`. `SetupWizardPage` nutzt `useProfile(profileId)` statt eigenem `useQuery`.

#### Frontend — `handleValidateSpotify` als Mutation

`SetupWizardPage`: `handleValidateSpotify` war ein direkter Promise-Aufruf (`.then().catch()`). Umgebaut zu `useMutation` mit `mutationFn: () => spotifyApi.validate(profileId!)` und `onSuccess`-Callback.



### Hinzugefügt

- **Projekt-Scaffold (MVP)**
  - Root: `docker-compose.yml` (app, nginx, PostgreSQL 15), `.env.example`, `Makefile`, `README.md`, `CHANGELOG.md`.
  - Docker: Nginx-Konfiguration, PostgreSQL-Init (uuid-ossp), PHP 8.3-FPM Dockerfile im Backend.
  - Backend (Symfony 7, PHP 8.3, Doctrine ORM, PostgreSQL):
    - Modulstruktur unter `src/Module/`: Admin, FamilyProfile, Spotify, Rfid, Scan, SetupWizard, Shared.
    - Shared: `HttpException`, `NotFoundException`, `ProblemJsonResponse`, `ExceptionSubscriber` für RFC 7807.
    - Admin: Entity `AdminUser`, `DoctrineAdminUserRepository`.
    - FamilyProfile: Entity `FamilyProfile`, CRUD Use Cases, `FamilyProfileController` (GET list, GET one, POST, PUT, DELETE), DTOs für Create/Update.
    - Spotify: Entity `SpotifyAccountLink`, `GetSpotifyStatus`, `SpotifyController` (GET status).
    - Rfid: Entity `RfidCard`, `ListRfidCardsByProfile`, `RfidCardController` (GET list).
    - Scan: Entity `ScanEvent`, `ProcessScan`, `ScanController` (POST /readers/scan), Scan-Event-Logging (outcome unknown_card im MVP).
    - SetupWizard: Entities `ProfileSetupSession`, `ProfileSetupStepStatus`, `GetWizardState`, `SetupWizardController` (GET state).
    - Doctrine-Mappings für alle Module, erste Migration (admin_user, family_profile, spotify_account_link, rfid_card, scan_event, profile_setup_session, profile_setup_step_status).
    - REST-Routen unter `/api/v1` mit Attribute-Routing; Parameter `uuid_regex` für UUID-Requirements.
  - Frontend (React 18, TypeScript, Vite, React Router, TanStack Query):
    - App-Shell mit `Layout` (Sidebar: Dashboard, Profile, Scan-Logs); Login-Seite ohne Auth-Logik.
    - Routen: `/`, `/login`, `/profiles`, `/profiles/:profileId`, `/profiles/:profileId/edit`, `/profiles/:profileId/setup`, `/profiles/:profileId/cards`, `/scan-logs`.
    - API-Client (`api/client.ts`) und Endpoints: profiles, setup, spotify, rfid.
    - Hooks: `useProfiles`, `useProfile`, `useCreateProfile`, `useUpdateProfile`, `useDeleteProfile`.
    - Seiten: Login, Dashboard, Profiles (Liste), ProfileDetail, ProfileSetup, Cards, ScanLogs (Platzhalter-Inhalte).
  - Tests: PHPUnit-Bootstrap, `ListFamilyProfilesTest` (Unit).

### Technische Details

- Backend: Kein Auth auf API im Scaffold (firewall `api` mit PUBLIC_ACCESS); Admin-Login und JWT/Session folgen in einer späteren Phase.
- Setup-Wizard: GET `/profiles/{id}/setup` liefert 404, wenn für das Profil noch keine `ProfileSetupSession` existiert; Session-Erstellung beim ersten Öffnen oder bei Profil-Erstellung kann in der nächsten Implementierung ergänzt werden.
- Scan-Endpoint: POST `/api/v1/readers/scan` mit `reader_id`, `card_uid`; speichert Event mit outcome `unknown_card` und gibt JSON zurück.

### Spotify-Integration (MVP Backend)

- **OAuth:** Authorization Code Flow; `GetSpotifyAuthorizationUrl` erzeugt URL mit State; State wird im Cache (TTL 600s) gespeichert und enthält die `profileId`. Callback GET `/api/v1/spotify/callback` tauscht Code gegen Tokens, speichert/aktualisiert `SpotifyAccountLink`, leitet auf Frontend weiter.
- **Token-Speicherung:** Access- und Refresh-Token werden mit symmetrischer Verschlüsselung (XChaCha20-Poly1305, Key aus APP_SECRET abgeleitet) in der Datenbank gespeichert. Doctrine Custom Type `spotify_encrypted_string` mit `TokenEncryptionService`; `EncryptedStringTypeInitializer` setzt den Encryptor pro Request.
- **Token-Refresh:** `SpotifyTokenManager::getValidLinkForProfile()` liefert einen gültigen Link; bei Ablauf (oder < 5 Min Rest) wird automatisch refresht und persistiert. Refresh-Token-Rotation wird unterstützt (Spotify kann neuen Refresh-Token zurückgeben).
- **Scopes:** Beim Auth-Request werden die benötigten Scopes gesendet; bei der Token-Antwort wird `scope` in `spotify_account_link.scopes` gespeichert. Keine automatische Scope-Prüfung im MVP; bei 403 von Spotify wird `SpotifyScopeMissingException` geworfen.
- **Fehler-Mapping:** `SpotifyTokenInvalidException` (401), `SpotifyNoDeviceException` (404/422), `SpotifyScopeMissingException` (403), `SpotifyNotConnectedException` (404), `SpotifyOAuthStateException` (400). Alle erben von `HttpException` und werden vom `ExceptionSubscriber` als Problem+JSON (RFC 7807) zurückgegeben.
- **API-Endpoints:** GET `authorization-url`, GET `status`, POST `validate`, GET `playlists`, GET `search?q=`, GET `devices`, POST `playback/start` (Body: `context_uri`, optional `device_id`). Playback verwendet bei fehlendem `device_id` das Standardgerät des Profils (`family_profile.default_spotify_device_id`).
- **Neue Dateien:** Domain-Exceptions, DTOs, Ports (`SpotifyApiClientInterface`, `TokenEncryptionInterface`, `SpotifyTokenManagerInterface`, `OAuthStateManagerInterface`), `SpotifyHttpApiClient`, `SpotifyTokenManager`, `SpotifyOAuthStateManager`, Use Cases, erweiterter `SpotifyController`, `SpotifyOAuthController`; Migration für `scopes`-Spalte; Config `config/packages/spotify.yaml`; Env: `SPOTIFY_CLIENT_ID`, `SPOTIFY_CLIENT_SECRET`, `SPOTIFY_REDIRECT_URI`, `FRONTEND_URL`.
- **Tests:** Unit-Tests für `GetSpotifyAuthorizationUrl` und `SpotifyTokenManager` (Mock von StateManager/Repository/ApiClient).

### Setup-Wizard (MVP)

- **Backend:**
  - Schritt-Konstanten in `WizardSteps` (profile, spotify_connect, spotify_validate, devices, default_speaker, playback_test, playlist, rfid_bind, summary); Status `pending`, `completed`, `failed`, `requires_attention` in `ProfileSetupStepStatus`.
  - Session get-or-create: `findOrCreateSession(profileId)` legt bei erstem Aufruf Session und alle Schritt-Statuszeilen (pending) an – Wizard ist fortsetzbar.
  - `GetWizardState`: liefert aktuellen Schritt, Session-Status und alle Schritte inkl. payload; wirft nicht mehr bei fehlender Session.
  - `SubmitStep`: nimmt step_key, status, payload; bei status completed wird schrittabhängige Logik ausgeführt (Profil aktualisieren, Spotify validieren, Standardgerät setzen, Playback testen usw.). Bei Fehler: Schritt wird auf failed gesetzt, `StepValidationException` mit steps zurückgegeben (422).
  - `SetCurrentStep`: setzt current_step für Navigation (z. B. Zurück).
  - `GetCompleteness`: Prozent und pro Schritt status/payload für Anzeige.
  - APIs: GET `/profiles/{id}/setup`, PUT/POST `/profiles/{id}/setup/step`, PUT `/profiles/{id}/setup/current-step`, GET `/profiles/{id}/setup/completeness`.
- **Frontend:**
  - `SetupWizardPage`: lädt State, Geräte/Playlists/Spotify-Status nach Bedarf, rendert aktuellen Schritt; Stepper mit Klick-Navigation (nur zugelassene Schritte).
  - Schritt-Komponenten: StepProfile, StepSpotifyConnect, StepSpotifyValidate, StepDevices, StepDefaultSpeaker, StepPlaybackTest, StepPlaylist, StepRfidBind, StepSummary; jeweils Loading/Error und Weiter/Überspringen wo sinnvoll.
  - Abschluss: StepSummary zeigt Checkliste und „Setup abschließen“; bei Abschluss wird Schritt summary mit status completed abgesendet, Session-Status auf completed gesetzt.
  - API-Client: setupApi (getState, submitStep, setCurrentStep, getCompleteness), WIZARD_STEPS; spotifyApi um getAuthorizationUrl, validate, getDevices, getPlaylists, startPlayback erweitert.

### RFID-Karten-Modul und Reader-Scan-Flow (MVP)

- **Ziel:** Reader sendet Scan → Karte wird erkannt → Profil und gebundene Playlist → Standard-Lautsprecher → Spotify-Playback starten → Scan-Event loggen.
- **Backend – Domain & DB:**
  - `ScanOutcome`: Konstanten für Erfolg/Fehler (success, unknown_card, no_binding, no_device, token_invalid, playback_failed, debounced, invalid_request, unknown_reader).
  - Entities: `ReaderDevice` (reader_id, name, api_key_hash), `CardPlaylistBinding` (rfid_card_id, spotify_playlist_reference_id), `SpotifyPlaylistReference` (family_profile_id, spotify_playlist_id, name, owner_id); `ScanEvent` um reader_device_id, rfid_card_id, family_profile_id erweitert.
  - Migration: Tabellen reader_device, spotify_playlist_reference, card_playlist_binding; Scan-Event-Spalten ergänzt.
- **Backend – Use Cases & API:**
  - Rfid: CreateRfidCard, GetRfidCard, UpdateRfidCard, DeleteRfidCard (entfernt Bindung), GetCardPlaylistBinding, SetCardPlaylistBinding.
  - Spotify: ListPlaylistReferences, CreatePlaylistReference; SpotifyPlaylistReference um getOwnerId() ergänzt.
  - Scan: ProcessScan mit Debounce (5 s), Kartenauflösung, Bindung/Playlist-Referenz, StartPlayback, Logging aller Outcomes; ListScanEvents (Limit/Offset, optional profileId).
  - Repositories: DoctrineRfidCardRepository (findByCardUid), DoctrineCardPlaylistBindingRepository, DoctrineSpotifyPlaylistReferenceRepository (findByIdAndProfile), DoctrineReaderDeviceRepository, ScanEventRepository (append mit neuen Parametern, findRecentScan, findRecent).
- **Backend – Controller:**
  - RfidCardController: GET list, GET one, POST create, PUT update, DELETE, GET/PUT binding.
  - SpotifyController: GET/POST playlist-references.
  - ScanController: POST /readers/scan (Body: reader_id optional, card_uid pflicht); GET /readers/scan-events (profile_id, limit, offset). Reader-Auth (MVP): wenn READER_API_KEY gesetzt, Header X-API-Key oder Authorization: Bearer erforderlich.
- **Konfiguration:** services.yaml: ScanController mit $readerApiKey (env default:reader_api_key:READER_API_KEY); Parameter reader_api_key: ''. .env.example um READER_API_KEY ergänzt.
- **Frontend:**
  - API: rfidApi (list, get, create, update, delete, getBinding, setBinding), spotifyApi (listPlaylistReferences, createPlaylistReference), scanApi (listEvents).
  - CardsPage: Liste mit Anlegen/Bearbeiten/Löschen, Playlist-Bindung (Modal mit Auswahl der Playlist-Referenzen); TanStack Query/Mutations.
  - ScanLogsPage: Tabelle mit Scan-Events (Zeit, Card UID, Outcome), Filter nach Profil, Pagination.
- **Request-Format Reader:** POST /api/v1/readers/scan JSON: { "reader_id": "optional", "card_uid": "required" }. Debounce: gleiche card_uid innerhalb 5 s → Outcome debounced, wird trotzdem geloggt.
