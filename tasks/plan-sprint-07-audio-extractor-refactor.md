# Plan – Sprint 07: Audio-Extraktor – Refactor, Warteschlange, Quellen/Formate

> Status: ENTWURF (Plan-vor-Code-GATE). Im Sprint-Chat prüfen, D-032…D-035 bestätigen, DANN umsetzen.
> GitHub = SSoT (Milestone „Sprint 07").

## Ziel
1. Stabiler, eleganter Audio-Extraktor: blockiert keine php-fpm-Worker mehr, robust gegen
   Timeouts/Abbrüche, mit Quota und sauberer Observability.
2. **Asynchrone Warteschlange** (Job-Queue) mit Status/Polling statt synchronem Request.
3. Mehr **legale** Quellen + Ausgabeformate.

## Harte Scope-Grenze (nicht verhandelbar, unabhängig von Eigentümer/Gesetzeslage)
- **Kein Spotify-/DRM-Ripping, keine Umgehung technischer Kopierschutzmaßnahmen.** Spotify-Inhalte
  sind DRM-geschützt; deren Extraktion wird in diesem Projekt nicht geplant/gebaut. Quellen sind
  ausschließlich DRM-freie/legale URLs (z. B. YouTube-CC, Direkt-Dateien, Podcast-RSS,
  Internet Archive, public-domain). Diese Grenze bleibt bestehen (vgl. D-019).

## Nicht-Ziele
- Reader/Provisioning (Sprint 06).
- Pro-Profil-Storage (in D-020 verworfen, bleibt verworfen sofern nicht neu begründet).

---

## Verifizierter Ausgangsbefund (Code-Analyse)

### Architektur (Ports & Adapters, `backend/src/Module/AudioExtractor/`)
- Domain: `AudioFormat`(mp3|wav), `ExtractedAudio`, `StoredAudioFile`, 2 Exceptions
  (→422/502). **Keine Doctrine-Entity** (Dateisystem-Storage).
- Application: einziger Use Case `ExtractAudio` (Validierung URL/Format/Bitrate).
- Infra: `YtDlpFfmpegExtractor` (Symfony `Process`, Timeout 240s), `FilesystemAudioStorage`
  (`/data/audio`, Traversal-Schutz, Kollisions-Suffix, **kein Quota**), `AudioExtractorController`
  (6 Endpoints: `/config`, `POST /extract`, `GET/DELETE /files[/{name}]`, `POST /update`).
- Frontend: `AudioExtractorPage.tsx` + `audioExtractor.ts` + `useAudioExtractor.ts`,
  Route `/tools/audio-extractor`.
- Auth: seit v0.5.0 hinter `ROLE_ADMIN` (Catch-all), nicht mehr offen (D-020 insoweit überholt).

### Wie ein Job heute läuft (synchron) + Risiken
`Browser POST /extract → nginx (fastcgi_read_timeout 300s) → php-fpm-Worker (set_time_limit 300s)
→ ExtractAudio → yt-dlp+ffmpeg-Subprozess (Process-Timeout 240s) → FilesystemStorage → 201`.
- **php-fpm-Worker bis ~5 min blockiert** (Pool klein → andere API-Calls hungern). [Hoch]
- nginx 504 ab 300s; Frontend-`fetch` ohne `AbortSignal`. [Mittel]
- yt-dlp/YouTube-Verfall → nur per `POST /update` (yt-dlp -U) mitigiert. [Hoch]
- **R7-Storage-Permissions** Bind-Mount `./data/audio` root-owned → Schreibfehler; Pi manuell
  geheilt, geplanter Container-Entrypoint **nicht implementiert** (`backend/docker-entrypoint.sh` fehlt). [Hoch]
- **Kein Quota** (nur Anzeige `total_size_bytes`); WAV unbegrenzt groß. [Mittel]
- **Keine Concurrency-Kontrolle** → mehrere yt-dlp parallel möglich. [Mittel]
- `findOutputFile` = `glob()[0]` willkürlich bei Mehrtreffern. [Niedrig]

### Queue-Status
- `symfony/messenger` **nicht** in `composer.json require`, keine `messenger.yaml`, keine Messages.
  Doctrine ORM vorhanden → Doctrine-Transport möglich ohne Redis.
- D-019/D-A: bewusst synchron, „Async später ohne API-Bruch (202 + Job-ID)" vorgesehen.

### Formate/Quellen
- Exponiert: nur mp3 (128/192/256/320) + wav. yt-dlp kann via `--audio-format` zusätzlich
  aac/flac/m4a/opus/vorbis/alac. App beschränkt Extraktoren **nicht** (nur http/https + Generic),
  `--no-playlist` erzwingt Einzelelement. Kein Quell-Typ-UX (nur ein URL-Feld).

---

## Phasenplan

### Phase A — Stabilitäts-Fundament (vor Queue, klein)
- A1: **R7-Entrypoint** umsetzen: `backend/docker-entrypoint.sh` setzt Owner/Mode von `/data/audio`
  (www-data) idempotent beim Containerstart; ersetzt schwachen `pi-deploy.sh`-chmod (löst v0.4.1).
- A2: **Quota**: harte Obergrenze (Gesamtgröße + optional Datei-Anzahl), konfigurierbar
  (Default z. B. 2 GB); Überschreitung → 422 mit klarer Meldung; optional LRU-Cleanup-Policy
  (separat, Default aus).
- A3: **Concurrency-Limit** (1 aktiver Extraktions-Job) – wird mit Queue (Phase B) sauber gelöst,
  bis dahin Lock.
- A4: `findOutputFile` deterministisch (neueste Datei / exakter Template-Match).
- **DoD A:** Storage-Rechte robust ohne Handarbeit; Quota greift; Tests grün.

### Phase B — Asynchrone Warteschlange (Kern)
- B1: `composer require symfony/messenger symfony/doctrine-messenger` (Begründung + Lock-Impact).
  `messenger.yaml` mit **Doctrine-Transport** (kein Redis-Zwang auf dem Pi).
- B2: **Job-Lifecycle**: Doctrine-Entity `AudioJob` (`id`, `url`, `format`, `bitrate`, `status`
  pending|running|done|failed|canceled, `progress`, `error`, `resultFile`, Timestamps). Migration +
  Mapping registrieren (Lesson: sonst 500).
- B3: `ExtractAudioMessage` + Handler ruft bestehenden `ExtractAudio` auf; Status-/Progress-Updates
  in `AudioJob` (Progress aus yt-dlp `--newline`/`--progress-template` parsen, optional).
- B4: **API-Umbau (additiv/versioniert):** `POST /extract` → **202** + `job_id`; `GET /jobs`,
  `GET /jobs/{id}`, optional `DELETE /jobs/{id}` (cancel). Bestehende `GET/DELETE /files`
  unverändert. OpenAPI + `oasdiff` (Breaking-Change-Strategie dokumentieren: altes sync-Verhalten
  entfällt → bewusst, da Single-Tenant; ggf. `err-ignore` vermeiden durch sauberes Schema).
- B5: **Worker** als Deploy-Unit: `messenger:consume` als eigener Compose-Service **oder**
  systemd-Unit auf dem Pi (`max_jobs`, `time_limit`, Auto-Restart). `--limit`/`--memory-limit`
  gegen Leaks.
- B6: **Frontend**: Extract legt Job an → Liste mit Live-Status (Polling ~2–5 s, konsistent mit
  D-023); Download erst bei `done`; Cancel-Button; Fehlertext aus Job.
- **DoD B:** php-fpm-Worker sofort frei; Job läuft im Hintergrund; UI zeigt Fortschritt/Ergebnis;
  Worker überlebt Neustart; CI grün.

### Phase C — Quellen & Formate (legal)
- C1: **Formate** erweitern: `AudioFormat` um `opus`, `flac`, `m4a`, `aac` (ffmpeg-Postproc);
  Bitrate-Logik je Format (verlustbehaftet vs. -frei); WAV/FLAC-Größenwarnung. FE-Select erweitern.
- C2: **Quell-UX**: Eingabe-Hints/Validierung für Quelltypen (Direkt-URL, YouTube, Podcast-RSS,
  Internet Archive); optional RSS-Feed → Episodenliste (eine Episode wählen). Klarer Hinweis
  „nur legale/DRM-freie Quellen".
- C3: optional **Playlist-Unterstützung** als bewusste Einzel-Job-Fanout (mehrere `AudioJob`s),
  `--no-playlist` gezielt aufheben nur wenn Nutzer Playlist-Modus wählt.
- **DoD C:** mind. 2 neue Formate + 1 neuer geführter Quelltyp; Tests; Doku.

### Phase D — Observability & Doku
- D1: Strukturierte Job-Logs/Fehlercodes (statt nur gekürztem stderr→502); yt-dlp-stderr im Job
  speichern (für Admin sichtbar), Secrets/keine PII.
- D2: Runbook + CHANGELOG + decisions/lessons; `docs/PROJECT_MAP.md` Eintrag.
- **DoD D:** Admin sieht je Job Status/Fehlerursache; Doku vollständig.

---

## 4-Lens-Analyse
**Lens 1 – Runtime & Sprache:** PHP 8.5.6/Symfony 7.4 LTS; yt-dlp-Release-Binary (`/opt/yt-dlp`,
self-update); ffmpeg im Image; Pi arm64. Messenger-Worker = langlebiger PHP-Prozess (Memory-Limit
beachten).
**Lens 2 – Frameworks & Abhängigkeiten:** Neu `symfony/messenger` + `symfony/doctrine-messenger`
(Doctrine ORM bereits vorhanden → kein Redis). Begründung: entkoppelt Extraktion vom Request,
Standard-Symfony, geringe neue Fläche. Lock-Impact prüfen, `composer audit`.
**Lens 3 – Build, CI/CD & Tooling:** CI: PHPUnit (Handler/Job-Lifecycle gemockt), PHPStan L8,
`oasdiff` (neue Job-Endpoints), Trivy, pnpm build/test. Neuer Worker-Service in Compose +
Deploy/systemd; `messenger:setup-transports`/Migration im Deploy.
**Lens 4 – Security & Compliance:** `ROLE_ADMIN` bleibt; nur http/https-URLs; **kein DRM/Spotify**;
Quota gegen DoS/Plattenfüllung; Worker least-privilege (www-data, eigener Service); yt-dlp-Update
nur authentifiziert. SSRF-Betrachtung: URL-Eingabe → yt-dlp; interne IPs/Schemes ggf. einschränken.

## Cross-Module-5-Fragen
1. **Konsumenten?** Frontend (Polling), Admin. 2. **Bricht etwas?** `POST /extract` ändert
   Antwort (201→202) – bewusst, Single-Tenant; FE wird mitgezogen. `GET/DELETE /files` stabil.
3. **Reihenfolge?** Messenger-Transport-Tabelle + `AudioJob`-Migration vor Worker-Start.
4. **Rollback?** Worker stoppen → Jobs bleiben pending; sync-Fallback optional behalten.
5. **Leak-Fläche?** yt-dlp-stderr kann URLs/Tokens enthalten → gefiltert speichern.

## Vorgeschlagene Entscheidungen (bestätigen → `tasks/decisions.md`)
- **D-032** Async-Queue via Symfony Messenger + Doctrine-Transport; `POST /extract`→202+job_id;
  Polling-UI.
- **D-033** Worker als eigener Deploy-Service (Compose/systemd) mit `max_jobs=1`, Auto-Restart.
- **D-034** Hartes Storage-Quota + R7-Entrypoint (löst v0.4.1) statt Deploy-chmod.
- **D-035** Formate erweitern (opus/flac/m4a/aac); Quell-UX legal-only; Spotify/DRM bleibt ausgeschlossen.

## Risiken / offene Punkte
- yt-dlp-Fragilität (YouTube-Änderungen) bleibt extern; Mitigation = Update-Pfad, nicht Garantie.
- API-Breaking-Change (201→202): bewusst; oasdiff-Strategie sauber lösen, kein Blind-Ignore.
- Worker-Memory-Leaks bei langer Laufzeit → `--limit`/`--memory-limit`/Restart.
- FLAC/WAV-Größe vs. Quota/SD-Karte.

## Test-vor-Done
PHPUnit (Job-Lifecycle, Handler, Quota, Storage), PHPStan L8, FE-Unit/Polling, `oasdiff`,
echte E2E-Extraktion (legale CC-Quelle) lokal + auf Pi, Worker-Restart-Test, CI grün, Deploy+Health.

## Subagenten-Plan
- Parallel: (1) Backend Messenger+Job-Entity+Handler+Tests, (2) Frontend Job-UI/Polling,
  (3) Stabilität (Entrypoint/Quota/Concurrency). Seriell: Worker-Deploy + E2E-Verifikation.
