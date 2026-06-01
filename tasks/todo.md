# TODO – Working-Memory (Cache)

> **GitHub ist die Single Source of Truth.** Diese Datei ist ein schneller, token-sparsamer
> Cache der offenen Arbeit. Bei Session-Start mit GitHub abgleichen (`gh issue list`).
> Sprints = Milestones, WorkPackages = Issues mit Label `work-package`.

Board: https://github.com/users/LordoseEHSQ/projects/2 · Milestones: `/milestones`

## Sprint 0 – Foundations & Governance (Milestone 1) — ABGESCHLOSSEN
- [x] #11 Governance-Gerüst (Labels, Templates, Board, Milestones)
- [x] #12 Working-Memory + Versionierung + Workflow-Rules

## Sprint 1 – Deploy & Quality Gates (Milestone 2) — ABGESCHLOSSEN (v0.1.0)
- [x] #3 Pi auf git-Clone + read-only Deploy-Key
- [x] #4 Idempotentes Deploy- + Backup-Skript (pg_dump)
- [x] #5 Auto-Deploy-Trigger (D-008 = systemd-Pull)
- [x] #6 Branch Protection (main) + CI-Härtung
- [x] #7 restart: unless-stopped

## Sprint 2 – Core E2E: Spotify→Wobie via ESP32 (Milestone 3)
- [ ] #8 Spotify-Login abschließen  (priority:high) — **blockiert: braucht Nutzer-Consent im Browser**
- [ ] #9 Wobie-Box-Discovery + Default-Device  (priority:high) — hängt an #8
- [ ] #10 ESP32 flashen + End-to-End-Test  (priority:high) — **blockiert: physisches Gerät + serieller Zugriff**

## Offene Entscheidungen
- _(keine blockierenden)_ — D-008 (Deploy-Mechanismus) entschieden: systemd-Pull.

## Legende
`[ ]` offen · `[~]` in Arbeit · `[x]` erledigt (Issue closed)
