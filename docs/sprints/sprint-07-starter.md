# Sprint-Starter-Prompt – Sprint 07: Audio-Extraktor – Refactor, Warteschlange, Quellen/Formate

Rolle: Lead-Engineer für **SpotFamServ**. Antworte deutsch, kritisch, ohne Gefälligkeits-Ja.

Lies zuerst (verbindlich):
- `tasks/plan-sprint-07-audio-extractor-refactor.md` (DER Plan – maßgeblich)
- `tasks/plan-audio-extractor.md`, `tasks/plan-audio-extractor-r7.md` (Vorgeschichte)
- `docs/PROJECT_MAP.md`, `tasks/todo.md`, `tasks/decisions.md` (D-019/D-020), `tasks/lessons.md`
  (L-020/L-021/L-022)
- Rules: `planning-discipline`, `sprint-workflow`, `chat-isolation-swarm`, `parallel-branch-workflow`

Worktree/Branch:
- `git worktree add ../SpotFamServ-sprint-07 -b feat/sprint-07-audio-refactor origin/main`

Harte Scope-Grenze (nicht verhandelbar):
- **Kein Spotify-/DRM-Ripping, keine Umgehung von Kopierschutz.** Nur DRM-freie/legale Quellen
  (YouTube-CC, Direkt-Dateien, Podcast-RSS, Internet Archive, public-domain). Diese Grenze ist Teil
  des Auftrags und bleibt bestehen.

Verifizierter Stand (Pi v0.5.8):
- Audio-Extraktor läuft **synchron** (kein Messenger/Queue), blockiert php-fpm-Worker bis ~5 min,
  kein Quota, R7-Entrypoint fehlt (Storage-Rechte bisher per Hand/Deploy-chmod geheilt).
- Exponiert nur mp3/wav; yt-dlp kann zusätzlich opus/flac/m4a/aac und viele legale Quellen.
- Endpoints hinter `ROLE_ADMIN` (seit v0.5.0). `symfony/messenger` ist NICHT installiert.

Ziel dieses Sprints (Milestone „Sprint 07"):
1. Stabilität (Phase A): R7-Entrypoint, Quota, Concurrency, deterministisches Output-Handling.
2. Async-Warteschlange (Phase B): Messenger + Doctrine-Transport, `AudioJob`-Entity,
   `POST /extract`→202+job_id, Job-Status-API, Worker als Deploy-Service, Polling-UI.
3. Quellen/Formate (Phase C): neue Formate + geführte legale Quelltypen.
4. Observability + Doku (Phase D).

Zu bestätigen (Entscheidungen im Plan): **D-032** (Messenger/Doctrine-Queue, 202+job_id),
**D-033** (Worker-Deploy-Service, max_jobs=1), **D-034** (Quota + R7-Entrypoint),
**D-035** (Formate erweitern, legal-only).

Blockierend (User):
- API-Breaking-Change `POST /extract` 201→202 bewusst (Single-Tenant) – oasdiff-Strategie sauber,
  kein Blind-Ignore.
- Deploy: neuer Worker-Service + Messenger-Transport-Migration; Pi erreichbar.

Subagenten-Plan (parallel): (1) Backend Messenger+Job-Entity+Handler+Tests, (2) Frontend
Job-UI/Polling, (3) Stabilität (Entrypoint/Quota/Concurrency). Seriell: Worker-Deploy + E2E.

Erste Aktion:
1. Worktree anlegen, Milestone „Sprint 07" + WorkPackage-Issues anlegen.
2. Plan kritisch reviewen (Messenger-Transport-Wahl, SSRF/URL-Härtung, oasdiff-Vertrag,
   Worker-Memory-Limits) und auf Bestätigung der Entscheidungen warten.
3. Erst danach Schwarm/Implementierung. Reihenfolge A → B → C → D, Test-vor-Done je Phase.
