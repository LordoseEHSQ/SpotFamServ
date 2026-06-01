# Plan: Governance – Sprints, WorkPackages, Issues, Versionierung, Working-Memory

**Erstellt:** 2026-06-01
**Status:** In Progress (Plan – wartet auf Bestaetigung, NICHT umgesetzt)

## Scope
Einen leichtgewichtigen, durchgaengigen Prozess etablieren: Plaene werden in **Sprints**
zerlegt, darunter **WorkPackages**; alles lebt in **GitHub als Single Source of Truth**
(Sprints, WorkPackages, Bugs). Pro WorkPackage werden die relevantesten Modelle benannt.
Aenderungen werden je Sprint dokumentiert; ein Sprint ist erst „done", wenn seine
Akzeptanzkriterien erfuellt sind. Dazu: SemVer-Versionierung, mein Working-Memory
(tasks/todo + lessons) und optional Obsidian als Viewer.

## Begriffshierarchie
```
Plan (tasks/plan-*.md)
  └─ Sprint            → GitHub Milestone (+ optional Project-v2-Iteration)
       └─ WorkPackage  → GitHub Issue (Label: work-package), enthaelt: Ziel,
       │                  Akzeptanzkriterien, Relevante Modelle, Plan-Verweis, DoD
       └─ Bug/Problem  → GitHub Issue (Label: bug)
```
**Sprint = Done**, wenn: alle WorkPackage-Issues closed + Milestone-Akzeptanzkriterien
gehakt + CI gruen + getaggter Release `vX.Y.Z`.

---

## 4-Lens-Analyse (Pflicht; bei Prozess-Themen ehrlich adaptiert)

### Lens 1 – Runtime & Sprache
- Reines Tooling/Prozess, keine App-Laufzeit betroffen. Werkzeuge: `gh` (Token: repo/project/workflow vorhanden), git, Markdown. Obsidian = lokaler Markdown-Viewer (Free).

### Lens 2 – Frameworks & Abhaengigkeiten
- Keine Code-Dependency. „Dependency" = GitHub-Verfuegbarkeit + `gh`-CLI. Issue-Templates unter `.github/ISSUE_TEMPLATE/` als strukturgebende Konvention.

### Lens 3 – Build, CI/CD & Tooling
- Versionierung (SemVer-Tags) koppelt an das tag-getriggerte Pi-Deploy (siehe `plan-pi-autodeploy-quality-gates.md`).
- Optional `gh`-Sync-Skript todo.md ↔ Issues. Sprint-Doku via PR-Verlinkung (`Closes #N`) + CHANGELOG + `docs/sprints/`.

### Lens 4 – Security & Compliance
- Keine Secrets in Issues/Plaenen/Obsidian. Privates Repo → Tracking nicht oeffentlich. `gh`-Token-Scope ist breit (admin:org) – fuer dieses Repo ausreichend, nicht erweitern.

---

## Cross-Module Antworten
1. **Upstream:** Plaene speisen Sprints/WorkPackages. Risiko Drift Plan↔GitHub → Plan referenziert Issue-Nummern, GitHub ist SSoT.
2. **Downstream:** Entwicklung konsumiert WorkPackages; Deploy konsumiert Tags. WorkPackage muss testbare Akzeptanzkriterien liefern.
3. **Audit:** Prozess-Entscheidungen → `tasks/decisions.md` (D-Eintraege).
4. **API-Vertrag:** n/a (kein Code).
5. **Feature-Flags:** n/a.

---

## Versionierung
- **SemVer** `vMAJOR.MINOR.PATCH`. Vorschlag Start: `v0.1.0` (pre-1.0 MVP).
- Patch = Fix; Minor = Feature/Sprint-Inkrement; Major = Breaking/Meilenstein.
- Tag am Sprint-Ende (nach erfuellten Akzeptanzkriterien) → loest Pi-Deploy aus.

## Mein Working-Memory (schneller Nachschlag ohne Vollkontext)
- `tasks/todo.md` – offene Items je aktivem Sprint (Cache; GitHub = SSoT; Refresh bei Session-Start).
- `tasks/lessons.md` – existiert (L-001..L-008).
- `tasks/plan-*.md` + `tasks/decisions.md` – Plaene/Entscheidungen.
- Session-Start-Protokoll (engineering-discipline): lessons + todo lesen, Stand melden.

## Dry-Run (neuer Planungs-Gate, in jedem Plan Pflicht)
Vor Ausfuehrung: Schritte simulieren statt blind ausfuehren.
- Befehle mit echtem Dry-Run nutzen, wo moeglich: `rsync -n`, `git push --dry-run`,
  `gh ... ` zuerst als read-only Probe, `docker compose config`.
- Sonst: schriftlicher Walkthrough mit erwarteten betroffenen Objekten/Dateien,
  erwartetem Ergebnis, Fehlermodi + Rollback. Ergebnis in Plan-Abschnitt „Dry-Run".

## Obsidian (Vorschlag, kritisch)
- Vault = Ordner aus Markdown. **Vault auf `docs/` (oder Repo-Root) zeigen** → Obsidian ist reiner
  Viewer/Graph ueber dieselben Dateien, **keine zweite Quelle, kein Drift**.
- Free-Version reicht (lokaler Vault). Geraete-Sync ueber git (kostenlos), nicht Obsidian-Sync (paid).
- Nutzen: Backlinks/Graph zwischen Plan↔Lessons↔Sprints. Kosten: `[[wikilinks]]`-Konvention + Index-Note.
- **Kritik:** Nur sinnvoll, wenn der Graph wirklich genutzt wird; sonst decken GitHub + repo-Markdown alles ab.

---

## Dry-Run dieses Plans (geplante Umsetzung, simuliert)
1. Labels anlegen: `gh label create work-package`, `sprint`, `priority:high|med|low` (bug existiert).
2. Sprint 1 = `gh api ... /milestones` (Titel, Due, Body=Ziel+Akzeptanzkriterien).
3. WorkPackages = `gh issue create --milestone "Sprint 1" --label work-package` aus Template.
4. (Opt. D1-B) `gh project create` + Iteration-Feld.
5. Issue-Templates `.github/ISSUE_TEMPLATE/work-package.yml` + `bug.yml`.
6. `tasks/todo.md` + `docs/sprints/sprint-01.md` anlegen.
7. Start-Tag `v0.1.0` setzen (nach erstem erfuellten Sprint).
→ Erwartete betroffene Objekte: GitHub-Labels/Milestone/Issues + 3–4 neue Repo-Dateien. Keine Code-/Schema-Aenderung.

## Blinde Flecken (explizit)
1. **Drei Quellen (GitHub / repo-Markdown / Obsidian) → Drift.** Aufloesung: GitHub = SSoT fuers Tracking; repo-Markdown = Arbeitsgedaechtnis/Docs; Obsidian = Viewer ueber repo. Eine Quelle je Concern.
2. **Prozess-Overhead vs. Projektgroesse.** Fuer ein kleines/Solo-Projekt kann Sprints+Issues+Projects+Dry-Runs+Versioning die Velocity erdruecken. Empfehlung: schlank starten (Milestones+Issues), Board nur bei Bedarf.
3. **„Relevante Modelle" je WorkPackage** nur wertvoll, wenn Modellwahl Kosten/Qualitaet steuert; Auswahl auf verfuegbare Modell-Liste beschraenkt. Interpretation offen (AI vs Domaenenmodelle).
4. **GitHub-/Netz-Abhaengigkeit:** Tracking online; offline nur repo-Cache.
5. **todo.md ↔ Issues Sync** manuell → Disziplin oder `gh`-Sync-Skript.
6. **Sprint nativ** nur via Projects-v2-Iterations; Milestones kennen nur Faelligkeit.
7. **Ohne Issue-Templates** driften WorkPackage-Inhalte → Templates Pflicht.

## Offene Entscheidungen (Bestaetigung noetig)
- **D1 Sprint-Repraesentation:** A) Milestones-only (schlank) · B) Milestones + Projects-v2-Board (Iterations).
- **D2 „Modelle" je WorkPackage:** A) AI/LLM-Modell-Empfehlung · B) Domaenen-/Datenmodelle.
- **D3 Obsidian:** A) ja, Viewer ueber repo (free) · B) nein.
- **D4 Start-Version:** A) `v0.1.0` · B) `v1.0.0`.

## Akzeptanzkriterien
1. Jeder kuenftige Plan wird in Sprint(s) + WorkPackages zerlegt, die in GitHub liegen.
2. Jedes WorkPackage-Issue hat Ziel, testbare Akzeptanzkriterien, relevante Modelle, Plan-Verweis.
3. Bugs entstehen als GitHub-Issues (Label `bug`).
4. Sprint-Doku existiert (CHANGELOG + `docs/sprints/sprint-NN.md`), Sprint-Done an Akzeptanzkriterien gebunden.
5. SemVer-Tags eingefuehrt und mit Deploy gekoppelt.
6. `tasks/todo.md` + `tasks/lessons.md` als verlaessliches Working-Memory gepflegt.
7. (falls D3-A) Obsidian-Vault dokumentiert, ohne zweite Quelle zu erzeugen.

## Definition of Done
- [ ] Labels + Issue-Templates + erstes Milestone (Sprint 1) angelegt
- [ ] WorkPackage-Issues aus aktuellem Stand erzeugt
- [ ] Versionsschema + Start-Tag dokumentiert
- [ ] `tasks/todo.md` + `docs/sprints/` initialisiert
- [ ] Governance als Rule (`.cursor/rules/sprint-workflow.mdc`) festgeschrieben
- [ ] `tasks/decisions.md` mit D1..D4 aktualisiert
- [ ] (opt.) Obsidian-Vault-Anleitung in `docs/`

## Risiken / Offene Fragen
- D1..D4 unbestaetigt → blockiert Umsetzung.
- Overhead-Risiko (s. Blind Spot 2) – Ceremony proportional halten.
- Sync-Drift (Blind Spot 1/5) – Auffanglogik noetig.

## Verifikations-Log
{Beim Umsetzen ausfuellen}

## Abgeschlossen
{Datum + Summary wenn fertig}
