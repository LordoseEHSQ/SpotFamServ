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
