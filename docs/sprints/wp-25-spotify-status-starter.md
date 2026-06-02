# Starter-Prompt – WP #25 (Spotify-Status Refresh-getrieben)

> GATE 2 (`chat-isolation-swarm.mdc`): Diesen Block in einen **neuen Chat** einfügen.
> Prinzip: Verweise statt Volltext (token-sparsam).

---

Rolle: Lead-Engineer für **SpotFamServ** (ESP32 + RFID → Symfony-Backend → Spotify Web API → Wobie Box).
Sprache: Deutsch, präzise, kritisch, kein Gefälligkeits-Ja.
Verhalten: **silent arbeiten, nur End-Zusammenfassung** (Rule `.cursor/rules/communication-style.mdc`).

**Lies zuerst (verbindlich, dann erst handeln):**
`docs/PROJECT_MAP.md` · `tasks/todo.md` · `tasks/decisions.md` (D-011, D-013, **D-014-Vorschlag im Plan**) ·
`tasks/lessons.md` (L-004, L-009, L-011, L-013, L-014) · `tasks/plan-spotify-status-reauth.md` ·
`docs/pi-deployment.md` · Rules `.cursor/rules/`
(`planning-discipline`, `sprint-workflow`, `chat-isolation-swarm`, `parallel-branch-workflow`, `project-architecture`, `communication-style`).

**Verifizierter Stand (Release `v0.2.2`, auf dem Pi live):**
- Backend v0.2.2, Frontend via CI-Image aus GHCR (`ghcr.io/lordoseehsq/spotfamserv-web`, public, multi-arch).
- `main` geschützt (PR + 5 Required-Checks). Pi `192.168.1.91` (User `lars`), Auto-Deploy via systemd-Timer auf `v*`-Tags.
- WP #25 ist vorbereitet: Issue offen (Milestone „Sprint 2"), Plan geschrieben, Branch+Worktree existieren bereits.

**Ziel (WP #25):** Spotify-Status soll die **echte** Re-Auth-Notwendigkeit zeigen statt des 1h-Access-Token-Takts.
Status-Enum `connected | reauth_required | not_connected`, **eine** Quelle, persistiertes `needs_reauth`-Flag
(gesetzt bei echtem Refresh-Fehler `invalid_grant`, gelöscht bei Re-Consent). Details + Akzeptanzkriterien:
`tasks/plan-spotify-status-reauth.md` und Issue #25.

**Arbeitsweise (Branch existiert schon — NICHT neu von main abzweigen):**
```bash
cd ~/SpotFamServ && git fetch origin
git worktree add ../SpotFamServ-status-reauth feat/spotify-status-reauth
# ab jetzt ausschließlich in ../SpotFamServ-status-reauth arbeiten
```
Commits fokussiert; **Schema-Migration als separater `schema`-Commit** (Backup D-007 greift auf dem Pi).
Lokal in Docker testen wie in CI; nach Docker-Runs root-owned Dateien beim Cleanup beachten (L-008/L-014).

**Blockierend (braucht User/Hardware):**
- Verifikation des `reauth_required`-Pfads end-to-end braucht einen echten Refresh-Fehler (z. B. App-Zugriff
  im Spotify-Konto entziehen) — sonst nur per Unit-Test/forciertem Fehler abdeckbar.
- Re-Consent nach Fix läuft (falls nötig) über SSH-Loopback-Tunnel (L-004).

**Subagenten-Schwarm (parallel, soweit unabhängig):**
- A (`explore`): Backend-Refresh-Fehlerpfade (`SpotifyHttpApiClient.refreshToken`, `ExceptionSubscriber`,
  `SpotifyTokenInvalidException`) — saubere Klassifizierung „dauerhaft vs. transient" (Plan-R4).
- B (`explore`): Vollständigkeit aller Frontend-`spotify_status`-Consumer + Setup-Wizard-Verhalten (AK4).
- Seriell: Domain+Migration → TokenManager/Resolver → Controller-Entdopplung → Frontend → OpenAPI/Tests.

**Erste Aktion (GATE Plan-vor-Code):**
`tasks/plan-spotify-status-reauth.md` prüfen/aktualisieren, **Decision D-014 (Option B empfohlen) dem User
zur Freigabe vorlegen** und auf Bestätigung warten. Erst danach implementieren.

**Abschluss:** grüne CI (inkl. oasdiff) → PR squash-merge → Patch-Tag (z. B. `v0.2.3`) → Pi-Verifikation
**am Effekt** (L-011: Status-Verhalten real prüfen, nicht nur Label) → Issue #25 schließen.
