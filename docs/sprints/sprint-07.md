# Sprint 7 – Audio-Extraktor: Refactor, Warteschlange, Quellen/Formate

**Milestone:** Sprint 07 – Audio-Extraktor (Refactor, Queue, Quellen/Formate) (#8) · **Status:** ✅ **DONE** – PR #73 squash-merged, CI grün, **`v0.7.0` auf dem Pi deployed (Health 200)**
**Ziel-Release:** `v0.7.0`
**Branch:** `feat/sprint-07-audio-refactor` · **Worktree:** `../SpotFamServ-sprint-07`
**Zeitraum:** 2026-06-05
**Plan:** `tasks/plan-sprint-07-audio-extractor-refactor.md`

---

## Sprint-Ziel

Den synchronen Audio-Extraktor (blockiert php-fpm bis ~5 min) in einen stabilen, **asynchronen**
Dienst überführen: Warteschlange statt Blockade, Quota + Concurrency-Schutz, mehr legale
Formate/Quellen und brauchbare Observability. Harte Grenze: **nur DRM-freie/legale Quellen**,
kein Spotify-/DRM-Ripping.

---

## WorkPackages – Ergebnis

| WP | Issue | Titel | Status | Milestone |
|---|---|---|---|---|
| A | #69 | Stabilitäts-Fundament (R7-Entrypoint, Quota, Lock, deterministisches Output-Handling) | ✅ Code | Sprint 07 |
| B | #70 | Asynchrone Warteschlange (Messenger+Doctrine, AudioJob, 202+job_id, /jobs-API, Worker, Polling-UI) | ✅ Code | Sprint 07 |
| C | #71 | Quellen & Formate (opus/flac/m4a/aac + geführte legale Quelltypen) | ✅ Code | Sprint 07 |
| D | #72 | Observability & Doku (Job-Logs/Fehlercodes, Runbook) | ✅ Code | Sprint 07 |

---

## Acceptance Criteria – Zwischenstand

| Kriterium | Status | Anmerkung |
|---|---|---|
| `POST /extract` blockiert php-fpm nicht mehr (202 + `job_id`) | ✅ | enqueued via Messenger, sofort 202 |
| Async-Verarbeitung im dedizierten Worker (1 Prozess) | ✅ | `messenger-worker`-Service, `messenger:consume async` |
| Job-Status-API (`GET /jobs`, `GET /jobs/{id}`, `DELETE /jobs/{id}`) | ✅ | + Polling-UI (2s, stoppt bei terminal) |
| R7-Entrypoint heilt Storage-Rechte self-healing | ✅ | `docker-entrypoint.sh` chownt `/data/audio` (uid 82), `pi-deploy` chmod-Block entfernt |
| Storage-Quota erzwungen (pre + post), 507 | ✅ | im Worker (Endgröße erst nach Download bekannt) |
| Concurrency/Update-Race-Lock, 409 | ✅ | `symfony/lock` (flock), Extraktion ⟂ `yt-dlp -U` |
| Deterministisches Output-Handling | ✅ | `findOutputFile` sortiert nach mtime |
| Neue Formate opus/flac/m4a/aac | ✅ | yt-dlp `--audio-format`; Bitrate nur lossy |
| Geführte legale Quelltypen | ✅ | UI-Guidance (Backend erzwingt Legalität nicht; SSRF bewusst abgelehnt) |
| Observability (Job-Lifecycle-Logs + Fehlercodes) | ✅ | start/done/failed mit Dauer, Host statt voller URL; 409/507/422/502/404 gemappt |
| Backend grün (PHPStan L8/lint:container/PHPUnit) | ✅ | volle Suite **183** Tests, PHPStan L8 0 Fehler, Container lint OK |
| Frontend grün (tsc/vitest/build) | ✅ | – |
| OpenAPI additiv (3 neue Pfade, 0 entfernt) | ✅ | lokal regeneriert; 201→202 unkritisch (Spec hatte nur `default`-Responses) |
| CI grün | ✅ | PR #73: Backend 8.4/8.5, Frontend, **oasdiff (additiv)**, Trivy, ESP32-Compile, Flash-Agent, Web-Image |
| Migrationen gegen echte DB | ✅ | Pi-Deploy: `audio_job` real migriert (1 migration, 3 SQL); `messenger_messages` via `auto_setup` angelegt |
| v0.6.0-Migration (`system_configuration`) auf Pi verifiziert | ✅ | bereits am 2026-06-05 10:15 auf dem Pi gelaufen (Migrationsliste geprüft) |
| Tag v0.7.0 | ✅ | getaggt, gepusht, Auto-Deploy (systemd-Timer) live → Health 200 |
| Worker konsumiert `async` zur Laufzeit | ✅ | „[OK] Consuming messages from transport async"; `/data/audio` gehört www-data (R7-Entrypoint griff) |
| Realer Extraktions-E2E (202→done→Datei) | ⏳ | noch offen: braucht ROLE_ADMIN-Login + legale URL über die UI |

---

## Technische Erkenntnisse / Abweichungen vom Plan

### 201→202 ist kein oasdiff-Breaking
Die generierte `openapi.yaml` deklarierte für `/extract` nur eine `default`-Response (kein
explizites `201`). Der Statuscode-Wechsel ist damit **kein** Vertragsbruch; die `/jobs`-Pfade
sind rein additiv. Kein `err-ignore`, kein Blind-Suppress nötig.

### AudioJob: selbst-vergebene UUID statt Doctrine-CustomIdGenerator
`AudioJob` setzt seine UUID im Konstruktor (`Uuid::v7()`, wie `ActivityLog`), statt sie erst
beim Flush vom `UuidGenerator` zu erhalten. Vorteil: die `id` existiert sofort → `CreateAudioJob`
kann ohne Flush dispatchen, und die Entität ist ohne DB unit-testbar (In-Memory-Repo).

### Quota wird im Worker erzwungen, nicht beim Request
Bei 202 ist die Endgröße der Datei unbekannt. Daher prüft der Worker **zweistufig**: vor dem
Download (aktueller Gesamtstand) und nach dem Speichern (Rollback per `delete` + 507, falls die
Datei das Limit reißt). Ein Request-Zeit-Check allein wäre wirkungslos.

### Messenger retry=0 (deterministische Fehler)
yt-dlp-Fehler sind meist deterministisch. `max_retries: 0` + `try/catch` im Handler: jeder Fehler
landet als `AudioJob.status=failed` (Single Source of Truth fürs UI), die Message wird ge-ack’t
statt in den Failure-Transport zu thrashen.

### Lossy vs. lossless Bitrate
`supportsBitrate()` ist jetzt formatabhängig: mp3/opus/m4a/aac honorieren eine Bitrate,
wav/flac (lossless) verwerfen sie vor dem Subprozess. Validator/Config sind datengetrieben –
neue Enum-Cases fließen automatisch in Validierung und `/config`.

---

## Decisions

- **D-032** Async-Warteschlange via Symfony Messenger + Doctrine-Transport; `POST /extract` → 202 + `job_id`; Polling-UI.
- **D-033** Worker als eigener Deploy-Service (genau 1 Konsument); `max_retries: 0`; Concurrency/Update-Race-Lock.
- **D-034** Storage-Quota (pre+post, im Worker) + R7-Entrypoint (Storage-Rechte self-healing).
- **D-035** Formate erweitern (opus/flac/m4a/aac); legal-only; SSRF-Allow-List bewusst **abgelehnt** (Single-Tenant), Risiko dokumentiert.

(Volltext in `tasks/decisions.md`.)

---

## Deploy-Ergebnis (v0.7.0, Pi, 2026-06-05 17:26 CEST)

- Auto-Deploy via systemd-Timer (`spotfam-deploy.timer`, alle 2 Min) zog `v0.7.0`: app-Image neu
  gebaut (R7-Entrypoint), `messenger-worker`-Image gebaut, `audio_job` migriert, **Health 200**.
- Verifiziert: 4 Container up; Worker konsumiert `async`; `messenger_messages` (auto_setup) +
  `audio_job` existieren; `/data/audio` gehört `www-data:www-data` (Entrypoint, trotz Host-`chmod`-WARN);
  `/audio-extractor/config` + `/jobs` liefern 401 (ROLE_ADMIN aktiv).
- **Befund – transienter Worker-Crash-Loop (self-healing, 3 Restarts):** Der Dev-Bind-Mount
  `./backend:/var/www/html` überlagert das Image-`vendor/` mit dem Host-`vendor/`. `pi-deploy.sh`
  startet `up -d` **vor** `composer install` → beim ersten Worker-Boot fehlte `symfony/messenger`
  im Host-`vendor` (`LogicException: Messenger component is not installed`). Nach `composer install`
  + `restart: unless-stopped` lief der Worker sauber an. Kein Datenverlust, aber bei **jedem** Deploy
  mit `composer.lock`-Änderung reproduzierbar (php-fpm: kurzes 500-Fenster). → Härtung in v0.7.1
  (Reihenfolge `composer install` vor `up -d`, oder Worker-`vendor` nicht bind-mounten). Siehe L-034.

## Noch offen

- **Realer Extraktions-E2E** (`POST /extract` → 202 → `done` → Datei): über die UI mit
  ROLE-ADMIN-Login + legaler URL durchspielen. Worker-Log live: `docker compose logs -f messenger-worker`.
- **Legalität wird nicht erzwungen** (SSRF-Allow-List bewusst abgelehnt) – Verantwortung beim Admin.

---

## Bekannte Grenzen (ehrlich)

- Verifikation lief **lokal** (PHP 8.5.6-Container: PHPUnit, PHPStan, lint:container, debug:messenger,
  mapping:info; Frontend tsc/vitest/build; OpenAPI lokal regeneriert). GitHub-CI steht mit dem PR aus.
- **Cancel ist best-effort:** nur `pending`-Jobs lassen sich abbrechen. Ein bereits laufender
  yt-dlp-Subprozess wird nicht hart unterbrochen (409 statt Fake-Cancel).
- **Legalität wird nicht erzwungen:** die Quellen-Guidance ist rein informativ; ohne SSRF-Allow-List
  liegt die Verantwortung für jede URL beim (Admin-)Nutzer.
- **Worker-Progress** ist grob (pending=0 / running→Balken); kein feingranulares yt-dlp-Prozent-Streaming.
