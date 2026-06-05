# Starter-Prompt – v0.7.1: Audio-Extraktor UX-Feedback + Deploy-Härtung

Rolle: Lead-Engineer für **SpotFamServ**. Antworte deutsch, kritisch, ohne Gefälligkeits-Ja.

Eigener frischer Chat (GATE chat-isolation-swarm). Patch-Release, kein neuer Sprint.

Lies zuerst (verbindlich, Verweise statt Volltext):
- `tasks/todo.md` → Abschnitt **„Backlog v0.7.1"** (maßgeblich für Scope)
- `tasks/lessons.md` → **L-034** (Deploy-Crash-Loop) · `tasks/decisions.md` (D-032…D-035)
- `docs/sprints/sprint-07.md` (was v0.7.0 lieferte + Deploy-Ergebnis) · `docs/PROJECT_MAP.md`
- Rules: `planning-discipline`, `sprint-workflow`, `chat-isolation-swarm`, `parallel-branch-workflow`
- UX-Zielbild: `~/.cursor/projects/home-lars-SpotFamServ/assets/audio-extractor-ux-mockup.png`
- Frontend-Brennpunkte: `frontend/src/pages/AudioExtractorPage.tsx`,
  `frontend/src/hooks/useAudioExtractor.ts`

Worktree/Branch (von aktuellem origin/main):
- `git worktree add ../SpotFamServ-v071 -b feat/v0.7.1-audio-ux origin/main`

Verifizierter Stand (main = v0.7.0, live auf Pi, Health 200):
- Async-Pipeline funktioniert end-to-end (UI→202→Worker→`failed`/`done`, Queue leer). Bewiesen.
- yt-dlp + Pi-IP ok; Download-Toolkette serverseitig bewiesen (öffentliches Video → mp3).
- **UX-Bug:** Warteschlange ist faktisch unsichtbar. `{jobs.length>0 && …}` + **kein** Loading/
  **Error**/Empty-State der `/jobs`-Query → bei leerer Liste oder 401/500 sieht der User NICHTS.
  Kein Toast-System im Frontend (kein `sonner`). Fehlgeschlagene Jobs geben keinerlei prominentes
  Feedback (`failed`-Fehlertext nur als `text-xs` in einer evtl. nicht gerenderten Card).
- **Deploy-Bug (L-034):** messenger-worker crasht beim Deploy (3×, self-healing), weil der
  Dev-Bind-Mount `./backend:/var/www/html` das Image-`vendor` überlagert und `pi-deploy.sh`
  `up -d` **vor** `composer install` macht.

Scope v0.7.1 (rein additiv, kein API-Bruch):
1. **UX P0:** Warteschlange immer sichtbar (Loading/Error/Empty-States) + Toast (`sonner`) für
   Submit/`failed`/`done`. Behebt „ich sehe nichts".
2. **UX P1:** „Erneut versuchen" je `failed`-Zeile; yt-dlp-Rohfehler übersetzen (Rohtext aufklappbar);
   `failed`/`canceled` schließbar (`DELETE /jobs/{id}`); `failed`-Icon `text-destructive`.
3. **Deploy-Härtung:** `composer install` vor `up -d` ziehen ODER Worker-`vendor` nicht bind-mounten.

Harte Scope-Grenze (unverändert): kein Spotify-/DRM-Ripping, nur legale/DRM-freie Quellen.

Blockierend (User/HW):
- Realer E2E bis `done` braucht ROLE_ADMIN-Login + ein wirklich abrufbares CC-/legales Video
  (das zuvor getestete YouTube-Video war `restricted` → kein Bug).
- Deploy/Tag erst nach CI grün; Pi muss erreichbar sein (tag-getriggerter Auto-Deploy via systemd).

Erste Aktion:
1. Worktree anlegen, Milestone/Issue(s) für v0.7.1 anlegen (Label `work-package`).
2. Plan `tasks/plan-v0.7.1-audio-ux.md` schreiben (4-Lens; `sonner`-Dependency begründen) und auf
   Bestätigung warten. **Vorab klären:** liefert `/jobs` im eingeloggten Browser real 200 + Jobs?
   (bestätigt, ob der fehlende Error-State allein das Symptom erklärt).
3. Erst danach implementieren. Test-vor-Done (Frontend tsc/vitest/build; Backend nur falls berührt).
