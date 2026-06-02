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

## Sprint 2 – Core E2E: Spotify→Wobie via ESP32 (Milestone 3) — IN ARBEIT
Releases: v0.2.0 (Sprint-2-Code), v0.2.1 (Spotify-Config-Fix), v0.2.2 (Frontend-CI-Image #20). Alle auf Pi deployed.
- [~] #8 Spotify-Login — Code fertig + Config-Fix (#18) live; Pi: Config `source=db`, real validiert.
      **Verbleibend (nur User): OAuth-Consent im Browser mit dem Box-Konto via SSH-Tunnel.** Technisch sonst entsperrt.
- [~] #9 Default-Device — Backend+Frontend fertig (`PUT/DELETE /profiles/{id}/default-device`,
      `SetDefaultDevice`, `default_device_name`, Stale-ID-Re-Resolve). UI seit Stopgap auf dem Pi sichtbar.
- [~] #10 ESP32 + E2E — Firmware-Fix (lowercase outcome) + secrets.h.example-IP fertig.
      **Verbleibend (Hardware): ESP32 an /dev/ttyUSB0 flashen + realer Scan.**

## Bugs (GitHub)
- [x] #18 Spotify-App-Credentials aus UI wurden zur Laufzeit ignoriert → `SpotifyCredentialsProvider` (DB vor env). Gefixt, v0.2.1.
- [x] #20 Frontend wird auf dem Pi nie gebaut (kein Node/pnpm) → Sprint-Stände erreichen die UI nicht.
      **Gelöst (v0.2.2, D-012/D-013):** CI baut Web-Image (multi-arch) → GHCR (public); Pi zieht nur noch.
      Verifiziert live: nginx=ghcr.io/lordoseehsq/spotfamserv-web (arm64), `/`=200, Bundle 0.2.2, `/api`=200.
      Folge-Lessons: L-013 (GHCR private-default), L-014 (pi-deploy self-update / langsamer app-rebuild).

## Offene Entscheidungen
- D-009 (Default-Device-Endpunkt), D-010 (Firmware lowercase), D-011 (Spotify-Config DB=SoT),
  D-012 (Frontend-Deploy via CI-gebautes Image) — alle entschieden. Keine blockierenden offen.

## Legende
`[ ]` offen · `[~]` in Arbeit · `[x]` erledigt (Issue closed)
