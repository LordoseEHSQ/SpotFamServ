# Plan: Pi-Auto-Deploy (tag-getriggert) + Backup + Quality Gates

**Erstellt:** 2026-06-01
**Status:** In Progress (Plan – wartet auf Bestaetigung, NICHT umgesetzt)

## Scope
Auf jeden getaggten Release (`vX.Y.Z`) zieht der Pi automatisch die neue Version von
GitHub und deployt sie idempotent (build/up/composer/migrate), **ohne Daten zu ueberschreiben**
(DB-Volume + git-ignorierte Secrets bleiben; pg_dump als Backup vor Migration). Zusaetzlich
werden Quality Gates etabliert (Branch Protection, oasdiff auf PRs, CI als required check).

## Bestaetigte Entscheidungen (User, 2026-06-01)
- **Trigger:** nur getaggte Releases `vX.Y.Z` (nicht jeder main-Merge).
- **Branch Protection:** ja – `main` schuetzen, CI required, oasdiff erzwingen.
- **Backup:** automatischer `pg_dump` VOR jeder Migration, letzte N rotierend.
- **Deploy-Mechanismus:** OFFEN → siehe Decision D-A (Empfehlung: pull-basiert).

---

## 4-Lens-Analyse (Pflicht)

### Lens 1 – Runtime & Sprache
- Pi: Debian 13 (trixie), aarch64, systemd vorhanden, `git` installiert, Docker 29.5.2 + Compose v2.
- Deploy-Logik in **bash + systemd** (timer/service) oder GH-Actions-Runner – keine neue Sprachlaufzeit.
- Backend-Runtime unveraendert (PHP 8.5.6 / Symfony 7.4). Migrationen laufen im `app`-Container.

### Lens 2 – Frameworks & Abhaengigkeiten
- Keine neuen App-Dependencies. Deploy-Skript nutzt vorhandenes `docker compose`, `composer`, `bin/console`.
- Conditional `composer install` nur wenn `composer.lock` sich aendert (Performance auf SD-Karte, vgl. L-006).
- `doctrine:migrations:migrate` idempotent (nur ausstehende Migrationen).

### Lens 3 – Build, CI/CD & Tooling
- **Pi-Umstellung Pflicht:** aktuell rsync-Kopie ohne `.git/` → auf **git-Clone** umstellen (read-only Deploy-Key).
- Auto-Deploy via gewaehltem Mechanismus (D-A). Trigger = Tag-Erkennung (`git fetch --tags`, neuestes `v*`).
- GitHub Actions: oasdiff/API-Drift auch verlaesslich auf PRs; Node20-Deprecation der Actions beheben (annotation).
- Branch Protection als required status checks (Backend 8.4/8.5, Frontend, Trivy, API-Drift).

### Lens 4 – Security & Compliance
- **Read-only Deploy-Key** (least privilege) auf dem Pi statt Account-Token; nur dieses Repo, nur Pull.
- Secrets bleiben ausschliesslich auf dem Pi (`backend/.env.local`, `.env`), nie in git, nie im Dump-Pfad oeffentlich.
- pg_dump-Backups lokal, restriktive Permissions (0600), Rotation; enthalten verschluesselte Tokens (libsodium) – kein Klartext-Secret.
- Deploy laeuft als User `lars` (Docker-Gruppe), nicht als root.
- Branch Protection verhindert Umgehung der Gates (heute moeglich – direkter Push auf main).

---

## Cross-Module Antworten
1. **Upstream:** GitHub `main`/Tags speisen den Pi. Risiko: ein kaputter Tag wird deployt → nur CI-gruene, getaggte Staende; Backup davor.
2. **Downstream:** ESP32 + Browser konsumieren das Pi-Backend. Deploy darf laufende Sessions kurz unterbrechen (Container-Restart) – akzeptabel, dokumentieren.
3. **Audit:** Infra-/Deploy-Aenderung → Decision-Log-pflichtig (D-A..D-D).
4. **API-Vertrag:** keine Shape-Aenderung; oasdiff-Gate wird gestaerkt (PR-Pflicht).
5. **Feature-Flags:** nicht noetig; additive Infrastruktur.

---

## Offene Architekturentscheidung

### D-A — Deploy-Mechanismus (Bestaetigung noetig)
- **A) Pull-basiert (systemd-Timer auf dem Pi):** Pi pollt GitHub (~alle 2 Min), bei neuem `v*`-Tag: pull + deploy.
  - + Kein Inbound noetig (Pi hinter Heim-NAT), simpel, robust, kein GH-Secret.
  - − Bis zu ~2 Min Verzoegerung; Logs liegen auf dem Pi.
  - **Empfehlung.**
- **B) Push via GitHub Actions + Tailscale-SSH:** Actions deployt nach Tag sofort per SSH.
  - + Sofortiges Deploy, zentrale Logs in Actions.
  - − Tailscale-Setup + SSH-Key als GH-Secret + Pi muss im Tailnet sein (mehr Angriffsflaeche).
- **C) Self-hosted GH-Runner auf dem Pi:** Deploy-Job laeuft lokal.
  - + Native Actions-Integration.
  - − Persistenter Runner-Dienst + Token-Pflege; mehr Wartung auf dem Pi.

---

## Geplante Umsetzung (nach Bestaetigung)
1. **Pi auf git-Clone umstellen:** Repo neu klonen, vorhandene `*.env*`-Secrets uebernehmen, Deploy-Key (read-only) hinterlegen.
2. **Deploy-Skript** `deploy/pi-deploy.sh` (idempotent): backup → fetch tags → checkout neuester `v*` → conditional build/composer → migrate → up -d → healthcheck → bei Fehler Rollback-Hinweis.
3. **Backup** `deploy/pi-backup.sh`: `pg_dump` in `backups/db-<tag>-<ts>.sql.gz` (0600), letzte N (z.B. 7) behalten.
4. **Auto-Trigger** je nach D-A (systemd timer+service ODER GH-Workflow+Tailscale ODER Runner).
5. **restart: unless-stopped** je Service in `docker-compose.yml` (Auto-Start nach Reboot, vgl. L-007).
6. **Branch Protection** via `gh api` auf `main`: PR-Pflicht, required checks, kein Force-Push.
7. **CI-Haertung:** oasdiff zuverlaessig auf PRs; Node20→Node24-Deprecation beheben.
8. **Release-Doku** + Versionsschema in `docs/pi-deployment.md` ergaenzen.

## Akzeptanzkriterien
1. Tag `vX.Y.Z` auf main → Pi deployt automatisch die exakt getaggte Version.
2. DB-Daten + Secrets bleiben ueber Deploys erhalten; vor jeder Migration existiert ein frischer Dump.
3. Fehlgeschlagener Deploy laesst den alten Stand lauffaehig (kein Half-State) oder meldet klar Rollback.
4. Direkter Push auf `main` ist blockiert; Merge nur via PR mit gruenen required checks inkl. API-Drift.
5. Nach Pi-Reboot startet der Stack automatisch.
6. `deploy/pi-deploy.sh` ist idempotent (zweimal laufen = kein Unterschied/keine Fehler).

## Definition of Done
- [ ] Pi als git-Clone mit read-only Deploy-Key, Secrets erhalten
- [ ] Deploy- + Backup-Skript getestet (inkl. Fehlerfall/Rollback-Pfad)
- [ ] Auto-Trigger (D-A) verifiziert: echter Tag → echtes Deploy
- [ ] Branch Protection aktiv + verifiziert (Push auf main wird abgelehnt)
- [ ] oasdiff laeuft auf PRs; CI-Deprecations behoben
- [ ] `docs/pi-deployment.md` + `tasks/decisions.md` aktualisiert
- [ ] Bestehende CI weiterhin gruen

## Risiken / Offene Fragen
- **D-A unbestaetigt** – blockiert Schritt 4.
- Migration auf Prod-Daten ohne Staging: Backup mindert, aber destruktive Migrationen bleiben Risiko → Migrationen reviewen.
- SD-Karten-I/O langsam (vgl. L-006) → conditional composer/build wichtig.
- Tag-basiertes Deploy erfordert disziplinierten Release-Flow (Tagging-Konvention dokumentieren).
- Pi-IP per DHCP nicht reserviert (ESP32/Bookmarks) – separater Haertungspunkt.

## Verifikations-Log
{Beim Umsetzen ausfuellen}

## Abgeschlossen
{Datum + Summary wenn fertig}
