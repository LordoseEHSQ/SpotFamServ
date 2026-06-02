# Sprint-4-Starter-Prompt (in NEUEN Chat einfügen)

Rolle: Lead-Engineer für **SpotFamServ** (ESP32/Pi-RFID → Symfony-Backend → Spotify → Connect-Gerät).
Antworte deutsch, kritisch, ohne Gefälligkeits-Ja. Wenn du raten müsstest, frag.

Lies zuerst (verbindlich): `tasks/plan-sprint-04.md` (vollständiger Plan + Diagnose), `docs/PROJECT_MAP.md`,
`tasks/todo.md`, `tasks/decisions.md` (insb. D-S4a–e, D-S3b), `tasks/lessons.md`, und die Rules unter
`.cursor/rules/` (`planning-discipline`, `sprint-workflow`, `chat-isolation-swarm`, `parallel-branch-workflow`).

Worktree/Branch: arbeite in `../SpotFamServ-sprint-04` auf `feat/sprint-04-card-ux-playback`
(bereits von `origin/main` @ `3746bd0` angelegt). Nicht auf `main` committen.

## Verifizierter Stand
- `main` @ `v0.2.5` live auf dem Pi (`192.168.1.91`), Auto-Deploy via `v*`-Tag, CI grün.
- **Playback-Bug am Pi diagnostiziert (Fakten in `plan-sprint-04.md`):** kein Profil-Default-Gerät;
  Scan-Outcomes nur `debounced`/`unknown_card`; Reader→Gerät-Mapping wird vom Scan-Flow ignoriert;
  Pi-Daemon läuft nur als manueller `setsid`-Prozess (nicht im Repo), feuert im Dauertakt, lieferte
  den Abend-Scan nicht. Karte/Binding/Token für Profil „Lars" sind korrekt vorhanden.
- **Sprint-3-Altlast:** nie mit `v0.3.0` geschlossen; `tasks/todo.md` veraltet (#34 faktisch live). → D-S4-VER klären.

## Sprint-Ziel
Karte am Pi scannen → gebundene Playlist spielt zuverlässig auf dem gewünschten Gerät; Kartenverwaltung
als modal-freies **DataGrid** mit sichtbarem Playlist-Namen.

## WorkPackages (Detail im Plan)
- **WP1** Playback-Zielgerät beim Scan: Reader→Gerät bevorzugt, Fallback Profil-Default (+ Stale-Re-Resolve, `device_source`-Log, Tests).
- **WP2** Kartenverwaltung DataGrid: Backend `rfid-cards`+`binding{id,name}` (additiv/oasdiff) · Frontend shadcn-`Table`, keine Overlay-Modals.
- **WP3** Pi-Reader-Daemon ins Repo + systemd-Service + lokale Entprellung (kein `debounced`-Spam).
- **WP4** Reader-/Profil-Gerät im UI setzbar + Onboarding-Doku.

## Entschiedene Weichen (User, 2026-06-02)
Reader→Gerät bevorzugt + Fallback Profil-Default · ein gemeinsamer Sprint · shadcn-Table (keine neue Dep) ·
nur Playlist-Bindung · Kartenverwaltung bleibt eigene Seite `/profiles/:id/cards` als DataGrid.

## Blockierend (User/Hardware)
- Spotify-Connect-Gerät muss **online** sein, um es als Default/Reader-Gerät zu wählen.
- Realer **Test-Scan am Pi** für WP1+WP3-E2E (klären, warum der Abend-Scan nicht ankam).
- systemd-Unit-Install + ggf. Pi-Reboot (WP3).

## Offen vor Code (NICHT raten)
- **D-S4-VER:** Sprint 3 retroaktiv schließen + Sprint 4 = `v0.3.0` (Vorschlag A) vs. Sprint 3 `v0.3.0` / Sprint 4 `v0.4.0`.
- **D-S4-DEV:** Verhalten ohne jedes Gerät → explizit melden statt auto-wählen (Vorschlag).
- **D-S4-GH:** Milestone + WP-Issues anlegen (nach Bestätigung).

## Subagenten-Plan
Parallel: WP1-Backend · WP2a-Backend (`rfid-cards`+binding) · WP3-Daemon-Code.
Seriell: WP2b-Frontend (braucht WP2a-Shape) · WP4 · E2E am Pi (Hardware).

## Erste Aktion
Plan `tasks/plan-sprint-04.md` gegen aktuellen Stand prüfen, D-S4-VER/DEV/GH mit User klären, dann GitHub
Milestone+Issues anlegen und mit dem Subagenten-Schwarm starten. Plan-vor-Code-GATE beachten.
