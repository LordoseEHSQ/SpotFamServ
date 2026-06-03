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

## Feature: Audio-Extraktor (legale Quellen) — v0.4.0 LIVE AUF PI, v0.4.1 GEPLANT
PR #47/#48/#49 gemergt; Tag `v0.4.0` (Commit `8a52e09`) **auf Pi live + verifiziert** (2026-06-03/04).
Doku: `tasks/plan-audio-extractor.md` + `tasks/plan-audio-extractor-r7.md`, D-019/D-020 (D-021 geplant),
L-020–L-022 (L-023/L-024 geplant). Starter v0.4.1: `docs/sprints/v0.4.1-r7-starter.md`.
- [x] Backend-Modul + Frontend + Self-Update; lokal x86_64 verifiziert (26 Tests, PHPStan L8, E2E)
- [x] **Pi/arm64 v0.4.0 verifiziert**: App-Image baut auf aarch64; echte YouTube-Extraktion
      (Big Buck Bunny CC-BY) → 15,2 MB MP3 in 63s; download/update/delete grün
- [x] R7 auf Pi **manuell geheilt** (`data/audio` → www-data/uid 82, persistiert)
- [ ] **v0.4.1**: R7 robust via Container-Entrypoint (ersetzt schwachen `pi-deploy.sh`-chmod);
      siehe Plan `tasks/plan-audio-extractor-r7.md` + Starter. Autonom im Folge-Chat.
- [ ] Out of scope (offen): Quota/Größenlimit für `data/audio`; asynchrone Extraktion (Queue)
- [ ] Aufräumen: Worktree `../SpotFamServ-audio-extractor` + Branch `feat/audio-extractor` (gemergt)

## Bugs (GitHub)
- [x] #18 Spotify-App-Credentials aus UI ignoriert → SpotifyCredentialsProvider. Gefixt v0.2.1.
- [x] #20 Frontend auf Pi nie gebaut → CI-Image GHCR. Gefixt v0.2.2 (D-012/D-013).

## Offene Entscheidungen
Alle D-001–D-015 entschieden. Sprint-4-Entscheidungen (D-S4-*) in `tasks/plan-sprint-04.md`.

## Legende
`[ ]` offen · `[~]` in Arbeit · `[x]` erledigt (Issue closed)
