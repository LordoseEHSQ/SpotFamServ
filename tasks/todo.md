# TODO – Working-Memory (Cache)

> **GitHub ist die Single Source of Truth.** Diese Datei ist ein schneller, token-sparsamer
> Cache der offenen Arbeit. Bei Session-Start mit GitHub abgleichen (`gh issue list`).
> Sprints = Milestones, WorkPackages = Issues mit Label `work-package`.

Board: https://github.com/users/LordoseEHSQ/projects/2 · Milestones: `/milestones`

## Sprint 0 – Foundations & Governance (Milestone 1)
- [~] #11 Governance-Gerüst (Labels, Templates, Board, Milestones) — in Arbeit
- [~] #12 Working-Memory + Versionierung + Workflow-Rules — in Arbeit

## Sprint 1 – Deploy & Quality Gates (Milestone 2)
- [ ] #3 Pi auf git-Clone + read-only Deploy-Key  (priority:high)
- [ ] #4 Idempotentes Deploy- + Backup-Skript (pg_dump)  (priority:high)
- [ ] #5 Auto-Deploy-Trigger wählen (D-A: A/B/C OFFEN)  (priority:medium)
- [ ] #6 Branch Protection (main) + CI-Härtung  (priority:high)
- [ ] #7 restart: unless-stopped  (priority:medium)

## Sprint 2 – Core E2E: Spotify→Wobie via ESP32 (Milestone 3)
- [ ] #8 Spotify-Login abschließen  (priority:high)
- [ ] #9 Wobie-Box-Discovery + Default-Device  (priority:high)
- [ ] #10 ESP32 flashen + End-to-End-Test  (priority:high)

## Offene Entscheidungen (blockierend)
- **D-A** Deploy-Mechanismus (systemd-Pull / Tailscale-Push / self-hosted Runner) → blockiert #5.

## Legende
`[ ]` offen · `[~]` in Arbeit · `[x]` erledigt (Issue closed)
