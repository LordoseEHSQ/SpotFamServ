# Sprint 4 – Card-UX & Playback-Reliability

**Milestone:** Sprint 4 – Card-UX & Playback-Reliability (#5) · **Status:** Code fertig; E2E + Tag ausstehend
**Ziel-Release:** `v0.3.0`
**Branch:** `feat/sprint-04-card-ux-playback` · **Worktree:** `../SpotFamServ-sprint-04`
**Zeitraum:** 2026-06-02 (Sprint-Start)
**Plan:** `tasks/plan-sprint-04.md`

---

## Sprint-Ziel

Karte am Pi scannen → gebundene Playlist spielt zuverlässig auf dem gewünschten Gerät;
Kartenverwaltung als modal-freies DataGrid mit sichtbarem Playlist-Namen.

---

## WorkPackages – Ergebnis

| WP | Issue | Titel | Status | Commits |
|---|---|---|---|---|
| WP1 | #39 | Gerätewahl beim Scan (Reader→Gerät + Fallback) | ✅ Code | `fedfa44` |
| WP2 | #40 | DataGrid (Backend+Frontend) | ✅ Code | `1b30013` + `2fbdd9a` |
| WP3 | #41 | Pi-Daemon vollständig | ✅ Code | `fedfa44` |
| WP4 | #42 | Gerät im UI + Onboarding | ✅ Code | `751cf39` |
| E2E | – | Pi-Deploy + realer Scan | ⏳ Hardware | – |

---

## Acceptance Criteria – Zwischenstand

| Kriterium | Status | Anmerkung |
|---|---|---|
| Realer Pi-Scan spielt Playlist (`outcome=success`) | ⏳ | Hardware-blockiert; Gerät + systemd-Install ausstehend |
| `CardsPage` DataGrid ohne Overlay-Modals | ✅ | tsc+build grün, shadcn-Table, Playlist-Spalte |
| `rfid-cards`+binding additiv (oasdiff non-breaking) | ✅ | PHPUnit 4 neue Tests |
| Pi-Daemon im Repo + systemd-ready | ✅ | secrets.example.env, .gitignore-Fix |
| CI grün auf main | ⏳ | PR noch ausstehend |
| Doku gepflegt | ✅ | CHANGELOG, sprint-03.md, pi-deployment.md |
| Tag v0.3.0 | ⏳ | Nach CI grün + Pi-E2E |

---

## Technische Erkenntnisse (Abweichungen vom Plan)

### WP1 war bereits implementiert (v0.2.5)
`ProcessScan` übergab Reader-Device-ID bereits an `StartPlayback`; `StartPlayback` priorisiert
bereits Reader-Gerät → Fallback Profil-Default → Stale-Re-Resolve auf beiden Pfaden.
Tests für Reader→Box-Mapping, no_device, Profil-Fallback waren vorhanden.
Plandiagnose (Sprint-3-Chat) lag vor dem Sprint-3-Merge (d984677). **Ergänzt:** nur
`device_source`-Logging + Reader-Stale-Re-Resolve-Test.

### WP3 war fast vollständig (v0.2.5)
`pi_reader.py` implementiert bereits Karten-Präsenz-Entprellung (nicht Dauer-Takt):
`last_uid = None` wenn Karte weg → Neuauflegen wird erkannt. Der 788×-Spam kam vom
alten `setsid`-Prozess ohne diesen Code. **Ergänzt:** nur `secrets.example.env`.

### WP4-UI war vollständig vorhanden
`ReadersPage` + `ProfileDetailPage` mit Geräte-Zuweisung + „Kein Standardlautsprecher
konfiguriert."-Anzeige seit v0.2.4. **Ergänzt:** nur `pi-deployment.md`-Runbook.

---

## Commits (Branch `feat/sprint-04-card-ux-playback`)

| Commit | Typ | Beschreibung |
|---|---|---|
| `5057014` | docs | Plan + Starter-Prompt + Entscheidungen Sprint 4 |
| `6bec15d` | chore | Sprint-3-Bookkeeping retroaktiv (D-S4-VER) |
| `fedfa44` | feat | WP1 device_source-Logging + WP3 secrets.example.env |
| `751cf39` | docs | WP4 Gerätewahl-Onboarding in pi-deployment.md |
| `1b30013` | feat | WP2a rfid-cards +binding API (Backend) |
| `2fbdd9a` | feat | WP2b CardsPage DataGrid (Frontend) |

---

## Blockierend (User/Hardware)

- **Connect-Gerät online:** Zum Setzen von Reader-/Profil-Default-Device.
- **Pi-Daemon systemd-Install:** `sudo cp spotfam-pi-reader.service /etc/systemd/system/ && sudo systemctl enable --now spotfam-pi-reader`. Danach `setsid`-Prozess beenden.
- **Realer Scan:** Mit bekannter, gebundener Karte; `outcome=success` in Scan-Logs verifizieren.
- **v0.3.0-Tag:** Erst nach CI grün auf `main` (Squash-Merge via PR).

---

## Lessons

- L-016 (implizit): Footer-Versionsfix war in v0.2.5 enthalten.
- Plandiagnose-Zeitpunkt beachten: Sprint-N-Analyse bezieht sich auf Pre-Sprint-N-Merge-Stand.
