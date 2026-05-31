# Plan: Stack-Modernisierung + CI-Pipeline (PHP, PHPStan L8, API-Drift, Security)

**Erstellt:** 2026-05-31
**Status:** Implementiert auf Branch `chore/stack-modernization` (lokal verifiziert; GitHub-Actions-Lauf steht noch aus)

**Bestätigte Entscheidungen:** D-1 = Symfony **7.4 LTS** · D-2 = **L8 + Baseline** · D-3 = **nelmio/api-doc-bundle** · D-4 = PHP **8.5.6** · Scope = alles in einem Branch

## Scope
Backend-Stack auf einen aktuellen, supporteten Stand bringen (PHP + Symfony),
eine GitHub-Actions-Pipeline einführen, PHPStan auf Level 8 etablieren,
API-Drift über OpenAPI + oasdiff verhindern und Security-Themen (CVEs,
Supply-Chain, Secrets) in die Pipeline integrieren.

---

## 0. Faktenbasis (recherchiert 2026-05-31)

| Komponente | Ist-Zustand | Aktuell/Supportet | Bewertung |
|---|---|---|---|
| PHP (Constraint) | `>=8.3` | 8.5.6 (Security, 2026-05-07) | veraltet, breit |
| PHP (Laufzeit Docker) | `php:8.3-fpm-alpine` | 8.5.6 | **8.5.5 ist bereits abgelöst** |
| Symfony | `^7.2` gemischt mit `^7.4` | 7.4 LTS / 8.0 | **7.3 EOL seit 01/2026** |
| Doctrine ORM | `^3.0` | 3.x | aktuell |
| PHPUnit | `^11.0` | 11/12 | ok |
| PHPStan | **nicht installiert** | 2.x | Makefile-Target `stan` läuft ins Leere |
| Postgres | `15-alpine` | 17 | veraltet, aber supportet bis 2027 |
| CI | **keine** | GitHub Actions | fehlt vollständig |
| OpenAPI-Spec | **keine** | — | Voraussetzung für API-Drift fehlt |
| Frontend (sekundär) | React 18 / Vite 5 / vitest 2 / TS 5.6 | React 19 / Vite 7 / vitest 3 | out of scope (PHP-Fokus) |

**Konsequenzen:**
- „PHP 8.5.5" als Ziel ist bereits veraltet → Pin auf **8.5.6**.
- Reines PHP-Upgrade ohne Symfony-Upgrade ist inkonsistent: PHP 8.5 wird nur
  von Symfony 7.4/8.0 offiziell getestet. 7.3 erhält keine Fixes mehr.

---

## 1. Die 4 Lenses (Analyse, ob der Stack „aktuell" ist)

### Lens 1 – Runtime & Sprache
- **PHP**: Constraint `>=8.3`, Laufzeit 8.3-Alpine. Ziel: 8.5.6, Constraint `>=8.4`
  (8.4 als untere Grenze hält Build-Matrix sinnvoll; 8.5 als primäre Laufzeit).
- **Node (Frontend)**: lokal 20.18 — zu alt für aktuelle Tooling-Engines
  (firecrawl-cli verlangte >=22). Für CI/Frontend Node 22 LTS pinnen.

### Lens 2 – Frameworks & Abhängigkeiten
- **Symfony**: inkonsistente Constraints (`^7.2`/`^7.4`), 7.3 EOL. Entscheidung
  7.4 LTS vs. 8.0 nötig (siehe Decisions D-1).
- **Doctrine/PHPUnit**: aktuell genug, mit Symfony-Upgrade re-locken.
- **Risiko**: `composer update` zieht Symfony 7.4/8.0 → Deprecation-Brüche möglich.

### Lens 3 – Build, CI/CD & Tooling
- **CI fehlt komplett.** Kein Lint, kein Static Analysis, kein automatisierter Test-Run.
- **PHPStan nicht installiert**, kein `phpstan.neon`, kein Baseline.
- **Tests**: nur 3 Testdateien für ~144 Quelldateien → sehr dünne Absicherung.
- **Wiederverwendbar**: `Makefile` (`test`, `stan`, `build`), `phpunit.xml.dist`,
  `Dockerfile`, Composer-Scripts.

### Lens 4 – Security & Compliance
- PHP 8.3-Laufzeit verpasst 8.5.x-Security-Fixes (mehrere CVEs in 8.5.6).
- Kein Dependency-Scanning (`composer audit`), kein Secret-Scanning, kein SAST.
- `TokenEncryptionService` (libsodium) vorhanden → Krypto im Code, muss in
  Security-Tests/Review abgedeckt sein.
- Kein API-Vertrag (OpenAPI) → Drift unkontrollierbar, keine Consumer-Garantie.

---

## 2. Betroffene Bereiche
- `backend/composer.json`, `backend/composer.lock` — PHP-/Symfony-Constraints, PHPStan.
- `backend/Dockerfile`, `docker-compose.yml` — PHP-/Postgres-Image-Pin.
- `backend/phpstan.neon(.dist)` + `backend/phpstan-baseline.neon` — neu.
- `.github/workflows/ci.yml` — neu (Lint, Stan, Test, Audit, API-Drift).
- `backend/` OpenAPI-Spec-Quelle (nelmio/api-doc-bundle ODER handgepflegt) — neu.
- `Makefile` — Targets ergänzen (audit, openapi-dump).

## 3. Cross-Module Antworten
1. **Upstream**: Build/Compose speisen die Laufzeit. PHP-/Symfony-Upgrade kann
   Deprecations triggern → Upstream = Composer-Resolution; absichern via Lockfile-Diff.
2. **Downstream**: Frontend + Firmware konsumieren die HTTP-API. Ohne OpenAPI-Baseline
   ist Drift nicht messbar → erst Baseline erzeugen, dann oasdiff als Gate.
3. **Audit**: Pipeline-/Stack-Änderung ist Infrastruktur — Decision-Log-pflichtig (D-1..D-4).
4. **API-Vertrag**: aktuell keiner. Ziel: OpenAPI als Single Source of Truth, in CI geprüft.
5. **Feature-Flags**: nicht nötig; Pipeline ist additiv, kein Runtime-Verhalten geändert.

---

## 4. Offene Architekturentscheidungen (Bestätigung erforderlich)

### D-1 — Symfony-Zielversion
- **A) Symfony 7.4 LTS** — minimal-invasiv, Support bis ~11/2028, PHP 8.5-kompatibel.
- **B) Symfony 8.0** — „most current", aber mehr Breaking Changes, kürzerer Support.
- **Empfehlung:** A (7.4 LTS) — passt zu „risikoarm/minimal" und liefert PHP-8.5-Support.

### D-2 — PHPStan „Level 8 erreichen" — Bedeutung
- **A) L8 konfiguriert + Baseline** der Alt-Fehler; Gate verhindert NEUE L8-Fehler.
  Pipeline sofort grün, technische Schuld sichtbar getrackt.
- **B) L8 mit 0 Fehlern** (Baseline = leer) — erfordert Fix aller Findings über
  ~144 Dateien; potenziell großer, separater Arbeitsblock.
- **Empfehlung:** A jetzt, B als Folge-Epic (Baseline schrittweise abbauen).

### D-3 — OpenAPI-Quelle für oasdiff-Baseline
- **A) nelmio/api-doc-bundle** — generiert Spec aus Attributen/Code (neue Dependency).
- **B) Handgepflegtes `openapi.yaml`** — keine Dependency, aber manuelle Drift-Gefahr.
- **Empfehlung:** A — Spec bleibt nah am Code; oasdiff vergleicht generierte Specs.

### D-4 — PHP-Version-Pin
- 8.5.6 (statt 8.5.5, da Security-Release). Build-Matrix 8.4 + 8.5.
- **Empfehlung:** annehmen.

---

## 5. Wiederverwendbare Pipeline-Elemente (Antwort auf „Welche noch nutzbar?")
- `Makefile`: `test`, `stan`, `build`, `migrate` → als CI-Steps/Todo-Anchor.
- `backend/phpunit.xml.dist` → Test-Runner-Konfig direkt nutzbar.
- `backend/Dockerfile` → Basis für CI-Build (PHP-Pin anheben), spätere Pi-Multi-Arch.
- Composer-Scripts-Block → `scripts.stan`, `scripts.audit` ergänzen.
- `docker/postgres/init.sql` + Healthcheck → Service-Container in CI.
- **Nicht nutzbar/fehlt:** Workflows, phpstan-Konfig, PHP-CS-Fixer/Linter, OpenAPI.

---

## 6. Akzeptanzkriterien
1. `composer.json` pinnt einheitliche, supportete Symfony-Minor + PHP-Constraint.
2. `Dockerfile`/Compose nutzen PHP 8.5.6 und Postgres-Zielversion; `make build` grün.
3. `phpstan analyse` läuft auf Level 8 ohne Fehler (ggf. via Baseline).
4. GitHub-Actions-Workflow läuft bei Push/PR: install → lint → stan → phpunit → audit.
5. `composer audit` ohne offene Advisories (oder dokumentierte Ausnahmen).
6. OpenAPI-Spec wird in CI erzeugt; oasdiff-Action meldet Breaking Changes auf PRs.
7. Bestehende 3 Tests laufen weiter grün.

## 7. Definition of Done
- [x] Stack-Constraints aktualisiert + `composer.lock` neu (Symfony 7.4.*, keine 8.x-Leakage)
- [x] PHP-/Postgres-Image gepinnt (8.5.6 / pg17), Build lokal verifiziert
- [x] PHPStan L8 + Baseline (64 Findings), lokal grün
- [~] CI-Workflow geschrieben + actionlint-clean; GitHub-Lauf steht aus
- [x] `composer audit` sauber ("No security vulnerability advisories found")
- [x] OpenAPI-Baseline (`backend/openapi.yaml`) + oasdiff-Gate (lokal verifiziert)
- [x] Security-Stages dokumentiert (composer audit + Trivy fs vuln/secret)
- [x] Bestehende Tests grün, keine Regression (final-Mock-Bug behoben)

## 8. Risiken / Offene Fragen
- **Symfony-Upgrade-Deprecations** könnten Laufzeitfehler erzeugen → in CI fangen,
  ggf. `symfony/deprecation-contracts` schrittweise abarbeiten.
- **PHPStan L8 mit leerer Baseline** ist potenziell sehr groß (Risiko Scope-Creep).
- **oasdiff braucht stabile Spec-Erzeugung**; ohne nelmio muss Spec manuell gepflegt werden.
- **Working Tree ist dirty** (laufende Scan/Reader-Feature-Arbeit, untracked `firmware/`)
  → Stack-Arbeit auf eigenem Branch, nicht mit Feature-Code mischen.
- **Docker-Build in WSL2** muss verfügbar sein, um den 8.5-Build lokal zu verifizieren.

## 9. Vorgeschlagene Reihenfolge (nach Bestätigung)
1. Branch `chore/stack-modernization`.
2. composer-Constraints + Symfony-Upgrade, `composer update`, Tests grün.
3. PHP-/Postgres-Image-Pin, `make build`/`up` verifizieren.
4. PHPStan installieren, `phpstan.neon`, Level 8 + Baseline.
5. CI-Workflow (install/lint/stan/test/audit).
6. OpenAPI-Quelle + Spec-Dump + oasdiff-Action.
7. Security-Stages (audit, optional CodeQL/Trivy) + Doku.

## Verifikations-Log
- Verifiziert: Docker-Image | `docker build` PHP 8.5.6 | Build OK (opcache ist im Image eingebaut, aus Install-Liste entfernt) | 2026-05-31
- Verifiziert: composer-Resolution | `composer update` im Container, Platform-Pin 8.5.6 | Symfony 7.4.13, keine v8-Leakage | 2026-05-31
- Verifiziert: PHPStan L8 | `phpstan analyse` | Baseline 64 Findings, danach "No errors" | 2026-05-31
- Verifiziert: Tests | `phpunit` | 5 Tests / 13 Assertions grün (Interface-Mocks statt final-Klassen) | 2026-05-31
- Verifiziert: PHPUnit-Konfig | Schema-Validierung | `<coverage>` aus `<source>` gelöst, Warnung weg | 2026-05-31
- Verifiziert: OpenAPI | `nelmio:apidoc:dump` | 613 Zeilen, OpenAPI 3.0.0 | 2026-05-31
- Verifiziert: API-Drift | `oasdiff breaking` | self=clean, entfernter Pfad=ERR/exit1 | 2026-05-31
- Verifiziert: Workflow-Syntax | actionlint (docker) | exit 0, keine Findings | 2026-05-31

## Restrisiken / offen
- GitHub-Actions-Lauf selbst (setup-php 8.5, oasdiff-action, trivy-action, frontend-Job) ist lokal NICHT verifizierbar.
- Trivy-Gate (CRITICAL/HIGH, vuln+secret) kann beim ersten Lauf über Frontend-Lockfile rot werden → Schwellen ggf. justieren.
- PHPStan-Baseline = 64 technische Schulden; Abbau ist Folge-Epic (D-2 Variante B).
- Symfony-7.4→8.0 weiterhin offen (bewusst zurückgestellt zugunsten LTS).
- Frontend (React 18/Vite 5) nicht modernisiert – außerhalb des PHP-Fokus.

## Abgeschlossen
- 2026-05-31: Backend-Stack auf PHP 8.5.6 + Symfony 7.4 LTS gehoben, PHPStan L8 (Baseline),
  GitHub-Actions-Pipeline (backend-Matrix, frontend, api-drift, security), OpenAPI + oasdiff,
  Trivy. Lokal vollständig verifiziert; PR-Lauf ausstehend.
