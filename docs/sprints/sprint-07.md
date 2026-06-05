# Sprint 7 – Audio-Extraktor: Refactor, Warteschlange, Quellen/Formate

**Milestone:** Sprint 07 – Audio-Extraktor (Refactor, Queue, Quellen/Formate) (#8) · **Status:** PR #73 offen, **CI grün** (Squash-Merge + Pi-Deploy + Tag v0.7.0 ausstehend)
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
| Migrationen gegen echte DB | ⏳ | `audio_job` + `messenger_messages` nur statisch geprüft; Laufzeit erst beim Pi-Deploy |
| v0.6.0-Migration (`system_configuration`) auf Pi verifiziert | ⏳ | offen aus Sprint 06; Closeout-Schritt |
| Tag v0.7.0 | ⏳ | nach CI grün + Squash-Merge |

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

## Blockierend (User/Hardware)

- **Deploy:** neuer `messenger-worker`-Service + `messenger:setup-transports` (Transport-Tabelle).
  `pi-deploy.sh` ist angepasst (setup-transports vor consume); reale Ausführung erst beim Pi-Deploy.
- **Migration-Laufzeit:** `audio_job` + `messenger_messages` nur statisch geprüft. Zusätzlich stehen
  gestapelte Migrationen an (Sprint-06 `system_configuration` ist auf dem Pi ggf. noch nicht gelaufen).
- **API-Wechsel 201→202** ist bewusst (Single-Tenant); Frontend ist mit umgestellt.
- **Tag v0.7.0:** erst nach CI grün auf `main` (Squash-Merge via PR).

---

## Bekannte Grenzen (ehrlich)

- Verifikation lief **lokal** (PHP 8.5.6-Container: PHPUnit, PHPStan, lint:container, debug:messenger,
  mapping:info; Frontend tsc/vitest/build; OpenAPI lokal regeneriert). GitHub-CI steht mit dem PR aus.
- **Cancel ist best-effort:** nur `pending`-Jobs lassen sich abbrechen. Ein bereits laufender
  yt-dlp-Subprozess wird nicht hart unterbrochen (409 statt Fake-Cancel).
- **Legalität wird nicht erzwungen:** die Quellen-Guidance ist rein informativ; ohne SSRF-Allow-List
  liegt die Verantwortung für jede URL beim (Admin-)Nutzer.
- **Worker-Progress** ist grob (pending=0 / running→Balken); kein feingranulares yt-dlp-Prozent-Streaming.
