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

## Sprint 2 – Core E2E: Spotify→Wobie via ESP32 (Milestone 3) — CODE FERTIG, E2E BLOCKIERT
Releases: v0.2.0 (Sprint-2-Code), v0.2.1 (Spotify-Config-Fix), v0.2.2 (Frontend-CI-Image).
- [~] #8 Spotify-Login — Code fertig. **Verbleibend (User): OAuth-Consent via SSH-Tunnel.**
- [~] #9 Default-Device — Backend+Frontend fertig.
- [~] #10 ESP32 + E2E — Firmware-Fix fertig. **Verbleibend (Hardware): ESP32 flashen + realer Scan.**

## Sprint 3 – Multi-Raum & Reader-Lifecycle (Milestone 4) — GESCHLOSSEN (v0.2.3–v0.2.5, 2026-06-02)
Retroaktiv geschlossen per D-S4-VER. Doku: `docs/sprints/sprint-03.md`.
- [x] #25 Spotify-Status refresh-getrieben (D-014, v0.2.3)
- [x] #34 Pi-Leser (PN532) + Scan-to-Create + UID-Lookup (v0.2.4 + v0.2.5)
- [x] #35 Pro-Reader-API-Keys (v0.2.4)
- [x] #36 Terminologie Wobie→Connect (v0.2.4)
- [x] #33 Reader→Box-Mapping Logik (v0.2.4); Pi-E2E hardware-blockiert → Sprint 4 WP1+WP3
Altlasten absorbiert in Sprint 4: ProcessScan nutzt Reader-Gerät NICHT (WP1); kein systemd + kein Debounce (WP3).

## Sprint 4 – Card-UX & Playback-Reliability (Milestone 5) — IN ARBEIT
Branch: `feat/sprint-04-card-ux-playback` · Worktree: `../SpotFamServ-sprint-04` · Ziel: `v0.3.0`
Plan: `tasks/plan-sprint-04.md`. Doku: `docs/sprints/sprint-04.md`.
- [x] #39 WP1: device_source-Logging + Reader-Stale-Test (Kern-Logik war bereits v0.2.5)
- [x] #40 WP2: rfid-cards +binding API (WP2a) + CardsPage DataGrid (WP2b)
- [x] #41 WP3: secrets.example.env + .gitignore-Fix (Daemon/Unit waren bereits v0.2.5)
- [x] #42 WP4: pi-deployment.md Onboarding-Runbook (UI war bereits vorhanden)
- [ ] E2E: Deploy v0.3.0 auf Pi + Gerät setzen + realer Scan (Hardware/User-blockiert)
Blockiert: Connect-Gerät online; systemd-Install am Pi; realer Scan.
Nächste Schritte: PR erstellen → CI grün → Squash-Merge → v0.3.0-Tag → Pi-Deploy.

## Feature: Audio-Extraktor (legale Quellen) — CODE GEMERGED, PI-DEPLOY OFFEN
PR #47 squash-gemergt nach `main` (2026-06-03). Kein Issue/Milestone (ad-hoc-Feature).
Doku: `tasks/plan-audio-extractor.md`, D-019/D-020 (`tasks/decisions.md`), L-020/L-021/L-022.
Starter für Folge-Chat: `docs/sprints/audio-extractor-deploy-starter.md`.
- [x] Backend-Modul (yt-dlp/ffmpeg via symfony/process), Persistenz, Self-Update, RFC-7807
- [x] Frontend `/tools/audio-extractor` (Extraktion + Datei-Mgmt + Update-Button)
- [x] Lokal x86_64 verifiziert: 26 Unit-Tests, PHPStan L8, E2E grün, **echte YouTube-Extraktion
      (Big Buck Bunny CC-BY) → MP3 in 25s** (Bot-Schutz-Vorbehalt damit aufgelöst)
- [ ] **Release-Tag `vX.Y.Z`** (triggert Pi-Deploy) — bewusst aufgeschoben bis nach Test
- [ ] **Pi/arm64 verifizieren**: Image-Build (yt-dlp zipapp braucht python3), E2E, Self-Update
- [ ] **R7 Host-Bind-Mount-Rechte** auf Pi: `./data/audio` für uid 82 (www-data) schreibbar
- [ ] Optional: Worktree `../SpotFamServ-audio-extractor` + Branch `feat/audio-extractor` aufräumen

## Bugs (GitHub)
- [x] #18 Spotify-App-Credentials aus UI ignoriert → SpotifyCredentialsProvider. Gefixt v0.2.1.
- [x] #20 Frontend auf Pi nie gebaut → CI-Image GHCR. Gefixt v0.2.2 (D-012/D-013).

## Offene Entscheidungen
Alle D-001–D-015 entschieden. Sprint-4-Entscheidungen (D-S4-*) in `tasks/plan-sprint-04.md`.

## Legende
`[ ]` offen · `[~]` in Arbeit · `[x]` erledigt (Issue closed)
