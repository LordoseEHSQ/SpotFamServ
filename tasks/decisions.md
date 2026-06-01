## Decision Log

Format je Eintrag: Kontext, Optionen, Entscheidung, Begründung, Status.

---

### D-001 | 2026-06-01 | Governance

**Kontext:** Abbildung von Sprints in GitHub.
**Optionen:** A) nur Milestones · B) Milestones + Projects-v2-Board (Iterations).
**Entscheidung:** B – Milestones + Projects-v2-Board (#2).
**Begründung:** User-Wunsch nach Übersicht/Board. Hinweis: echte Iteration-Felder sind via `gh`-CLI nicht anlegbar → ggf. manuell im UI; Sprint-Zuordnung primär über Milestone.
**Status:** Accepted

---

### D-002 | 2026-06-01 | Governance

**Kontext:** Bedeutung von „relevante Modelle" je WorkPackage.
**Entscheidung:** AI/LLM-Modelle – je WorkPackage Empfehlung + Begründung (Reasoning-Tiefe vs. Routine vs. Kosten).
**Status:** Accepted

---

### D-003 | 2026-06-01 | Governance

**Kontext:** Zweites Gehirn / Wissens-Layer.
**Entscheidung:** Obsidian als reiner Viewer über die Repo-Markdown-Dateien (Vault = Repo/`docs/`), Free-Version, Sync via git. `docs/PROJECT_MAP.md` = Home/Token-sparsamer Einstieg.
**Begründung:** GitHub bleibt SSoT; kein zweiter Speicher → kein Drift. Free reicht (kein Obsidian-Sync nötig).
**Status:** Accepted

---

### D-004 | 2026-06-01 | Versionierung

**Kontext:** Start-Version.
**Entscheidung:** `v0.1.0` (pre-1.0 MVP).
**Status:** Accepted

---

### D-005 | 2026-06-01 | Deploy

**Kontext:** Wann auf den Pi deployen.
**Entscheidung:** Nur getaggte Releases `vX.Y.Z` (nicht jeder main-Merge).
**Begründung:** Kontrollierte Versionen, natürliche Backup-Punkte.
**Status:** Accepted

---

### D-006 | 2026-06-01 | Quality Gates

**Kontext:** Schutz von `main`.
**Entscheidung:** Branch Protection: PR-Pflicht, required CI-Checks inkl. API-Drift, kein direkter Push.
**Status:** Accepted

---

### D-007 | 2026-06-01 | Backup

**Kontext:** Datensicherheit bei Deploys/Migrationen.
**Entscheidung:** Postgres-Volume bleibt + automatischer `pg_dump` VOR jeder Migration, letzte N rotierend.
**Status:** Accepted

---

### D-008 | 2026-06-01 | Deploy-Mechanismus

**Kontext:** Wie das tag-getriggerte Deploy technisch läuft (Pi hinter Heim-NAT).
**Optionen:** A) systemd-Timer Pull · B) GitHub Actions + Tailscale-SSH · C) self-hosted Runner.
**Entscheidung:** A – systemd-Timer Pull auf dem Pi (alle 2 Min, neuester `v*`-Tag).
**Begründung:** Kein Inbound nötig (Heim-NAT), kein GitHub-Secret, simpel und robust.
**Status:** Accepted

---

### D-009 | 2026-06-01 | Device/Playback (Sprint 2, #9)

**Kontext:** Das Default-Spotify-Device pro Profil (`family_profile.default_spotify_device_id`) ist
bislang **ausschließlich** über den Setup-Wizard-Step `default_speaker` setzbar. `AssignDevice`
(Governance-Inventar `spotify_device`) synchronisiert es **nicht**; `default_device_name` ist im
Profile-Controller hardcoded `null`. Es braucht einen klaren Weg, das Default außerhalb des Wizards zu setzen.
**Optionen:**
1. A) Wizard-Pfad belassen, nur Anzeige-Bug fixen — Vorteile: minimal · Nachteile: UX bleibt umständlich, kein API-Weg.
2. B) Dedizierter Endpunkt `PUT /api/v1/profiles/{id}/default-device` + UI — Vorteile: sauber entkoppelt, testbar, openapi-dokumentiert · Nachteile: mehr Code (UseCase, DTO, Frontend, oasdiff).
3. C) `AssignDevice` setzt `default_spotify_device_id` mit — Vorteile: ein Klick · Nachteile: koppelt Governance an Playback-Ziel (semantisch fragwürdig).
**Entscheidung:** B – dedizierter Endpunkt `PUT /profiles/{id}/default-device` + UI (ProfileDetailPage/DevicesPage).
**Begründung:** Trennt Inventar/Governance (`spotify_device`) sauber vom Playback-Ziel (`default_spotify_device_id`),
ist als API testbar und über OpenAPI/oasdiff vertraglich abgesichert.
**Konsequenzen:** Neuer UseCase `SetDefaultDevice` + DTO + Route + Frontend-Call + openapi.yaml-Eintrag.
`default_device_name` wird serverseitig aufgelöst (kein hardcoded null).
**Status:** Accepted

---

### D-010 | 2026-06-01 | Firmware/Scan-Vertrag (Sprint 2, #10)

**Kontext:** Die ESP32-Firmware prüft `outcome=="SUCCESS"`/`"DEBOUNCED"` (uppercase), das Backend sendet
kanonisch lowercase (`ScanOutcome.php`, Frontend `ScanLogsPage.tsx` konsistent). Folge: erfolgreiches
Playback und Debounce werden am ESP32 als Fehler (4 Blinks) signalisiert.
**Optionen:**
1. A) Firmware auf lowercase anpassen — Vorteile: kein Vertragsbruch, Backend bleibt SSoT · Nachteile: Re-Flash nötig (ohnehin Teil von #10).
2. B) Backend auf uppercase — Nachteile: bricht Frontend + OpenAPI-Vertrag, mehr Consumer betroffen.
**Entscheidung:** A – Firmware auf lowercase (`success`/`debounced`) anpassen.
**Begründung:** Backend-lowercase ist die Quelle der Wahrheit (Enum + Frontend + OpenAPI). Fix gehört auf den Consumer.
**Status:** Accepted
