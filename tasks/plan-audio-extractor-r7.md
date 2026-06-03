# Plan – v0.4.1: R7 robust lösen (data/audio-Schreibrechte) + Lessons

> Plan-vor-Code (planning-discipline). **Vom User in der Vorgänger-Session vorab freigegeben**
> ("ja … gib mir den Prompt um ALLES autonom in einem neuen Chat durchlaufen zu lassen").
> Umsetzung autonom im Folge-Chat. SemVer: Bugfix → **v0.4.1** (PATCH).

## Problem (verifiziert auf dem Pi)
Der Bind-Mount `./data/audio` wird von Docker **root:root** angelegt. Der php-fpm-Worker läuft als
**www-data (uid 82)** → `Permission denied` beim Schreiben → Extraktion bricht zur Laufzeit, der
Healthcheck (`/profiles`) merkt es **nicht**. Auf dem Pi am 2026-06-03 reproduziert und manuell per
`docker compose exec app chown www-data:www-data /data/audio` geheilt (persistiert).

Der in v0.4.0 ausgelieferte „Fix" in `pi-deploy.sh` (`chmod 0777` durch den Deploy-User) ist
**unzureichend**:
1. **Greift erst beim übernächsten Deploy** – `pi-deploy.sh` wird *vor* dem `git checkout` des neuen
   Tags ausgeführt, d. h. die neue Skriptversion läuft frühestens beim nächsten Tag.
2. **Scheitert am Normalfall** – der Deploy-User (uid 1000) kann ein root-eigenes Verzeichnis nicht
   `chmod`en; es bleibt bei einer WARN, nicht bei einer Heilung.

## Ziel / Definition of Done
- `data/audio` ist nach jedem Container-Start **automatisch** für www-data schreibbar – ohne
  manuellen Eingriff, auf frischem Pi wie lokal, im **selben** Deploy, der den Fix ausliefert.
- Lokaler Build + Self-Heal-Test grün; Pi nach v0.4.1 verifiziert (inkl. aktivem Break-&-Heal-Test).
- Lessons + Decision dokumentiert; CHANGELOG/Version gepflegt; CI grün; Tag `v0.4.1`.

## Lösungsentscheidung: Container-Entrypoint (statt Deploy-Skript)
Ein Wrapper-Entrypoint im `app`-Image chownt `/data/audio` bei **jedem** Start als root und ruft
dann den Original-`docker-php-entrypoint` auf. Vorteile vs. Deploy-Skript:
- **Wirkt im selben Deploy**: Dockerfile-Änderung → `need_build=true` → Image-Rebuild → `up -d`
  rekonstruiert den Container → Entrypoint läuft sofort. (Kein „erst nächster Tag".)
- **Self-Healing bei jedem Start** (Reboot, `restart`, recreate), unabhängig vom Deploy-Pfad.
- **Funktioniert im root-eigenen Normalfall** (Entrypoint läuft als root; php-fpm-Master ist ohnehin root).

### Änderungen
1. **Neu `backend/docker-entrypoint.sh`** (POSIX sh):
   ```sh
   #!/bin/sh
   set -e
   # R7: Audio-Storage-Bind-Mount fuer www-data (uid 82) schreibbar machen.
   # Laeuft als root beim Container-Start; Mount kann root-owned sein (Docker-Default).
   if [ -d /data/audio ]; then
     chown www-data:www-data /data/audio 2>/dev/null || true
   fi
   exec docker-php-entrypoint "$@"
   ```
   - `|| true` + `exec`: ein fehlschlagendes chown (read-only/named volume) darf den Start **nicht**
     crashen (Fail-safe gegen DoS-on-start).
   - **Nicht-rekursiv reicht**: Löschen/Anlegen von Dateien hängt an der Schreibberechtigung des
     *Verzeichnisses*, nicht am Datei-Owner; root-erzeugte Dateien sind i. d. R. 0644 (lesbar für
     Download). Rekursives chown bei jedem Start wäre verschwendet.
2. **`backend/Dockerfile`** (am Ende):
   ```dockerfile
   COPY docker-entrypoint.sh /usr/local/bin/spotfam-entrypoint.sh
   RUN chmod +x /usr/local/bin/spotfam-entrypoint.sh
   ENTRYPOINT ["/usr/local/bin/spotfam-entrypoint.sh"]
   CMD ["php-fpm"]
   ```
   (Bisher kein eigener ENTRYPOINT → Default `docker-php-entrypoint`/`php-fpm` wird sauber gewrappt.)
3. **`deploy/pi-deploy.sh`**: den schwachen Block **5b entfernen** (Entrypoint übernimmt R7); ein
   `mkdir -p "$AUDIO_DIR"` vor `up -d` darf als deterministischer Mount-Quellpfad bleiben (harmlos).
4. **`frontend/package.json`** → `0.4.1`. **`CHANGELOG.md`** → `## [0.4.1]`-Sektion.
5. **`tasks/lessons.md`** L-023 + L-024 (s. u.), **`tasks/decisions.md`** D-021.

## 4-Lens-Analyse (planning-discipline – Pflicht)
- **Lens 1 – Runtime & Sprache:** `php:8.5.6-fpm-alpine`; Entrypoint ist POSIX-sh (busybox auf
  Alpine), `chown` aus busybox – auf **x86_64 und arm64** vorhanden. php-fpm-Master läuft als root,
  Worker als www-data (Image-Default `www.conf`). Keine Versionsabhängigkeit.
- **Lens 2 – Frameworks & Abhängigkeiten:** Keine neuen Deps, kein Composer-/Lock-Impact
  (`need_composer=false`). Symfony unberührt. Entrypoint wrappt nur den offiziellen
  `docker-php-entrypoint`.
- **Lens 3 – Build, CI/CD & Tooling:** Dockerfile-Änderung → `need_build=true` → Pi baut `app` neu
  (arm64, ~1–2 min). **Blind Spot B2:** die CI-Jobs „Backend" bauen das **App-Image nicht** (sie
  laufen PHPUnit/PHPStan in einem PHP-Container) → ein kaputter Entrypoint **besteht die CI** und
  bräche erst auf dem Pi. Gegenmaßnahme: **lokaler Build+Run-Test ist Pflicht vor dem Tag** (+
  `sh -n`). `release-web-image.yml` baut bei `v*` das Web-Image (multi-arch) – Frontend-Bump nötig.
- **Lens 4 – Security & Compliance:** chown root→www-data = least-privilege (Storage gehört dem
  Service-User statt root). Keine neue Angriffsfläche; fixer Pfad `/data/audio` (keine Injection);
  `|| true` verhindert Start-DoS. Keine Secrets. `composer audit`/Trivy unverändert relevant.

## Cross-Module-5-Fragen (engineering-discipline)
1. **Wer hängt dran?** Nur das `app`-Image + Audio-Extractor-Storage. Kein API-/Schema-Kontrakt
   betroffen (oasdiff non-breaking, keine Migration).
2. **Failure-Modes / Blast-Radius?** Schlimmstfall: fehlerhafter Entrypoint → app-Container
   crash-loopt → Seite down. Mitigation: `set -e`+`|| true`+`exec`, `sh -n`, **lokaler Run-Test**.
   Deploy ist recoverable (Backup vor Checkout; nächster Timer-Tick retryt).
3. **Rückwärtskompatibilität?** Voll – additiver Entrypoint-Wrapper, gleiches CMD `php-fpm`.
4. **Security?** Siehe Lens 4 – Verbesserung (least-privilege), keine Regression.
5. **Observability / Woher wissen wir, dass es geht?** Aktiver **Break-&-Heal-Test** (Perms
   absichtlich auf root zurücksetzen → `restart app` → www-data-Schreibtest + echte Extraktion).

## Blind Spots (explizit – vom User gefordert)
- **B1 – „Ein-Deploy-Versatz":** Deploy-Skript-Fixes wirken erst beim nächsten Tag → Entrypoint
  (Image, recreate im selben Deploy) ist deshalb das richtige Vehikel.
- **B2 – CI testet das App-Image nicht** → kaputter Entrypoint kommt durch die CI. Pflicht: lokaler
  `docker-compose build app` + Start + Self-Heal-Test, `sh -n docker-entrypoint.sh`.
- **B3 – „Schon geheilter Pi" maskiert Regression:** der Pi schreibt bereits (manueller Fix). Die
  Verifikation MUSS aktiv die Rechte brechen und die Selbstheilung beweisen – nicht den grünen
  Ist-Zustand bestätigen.
- **B4 – Entrypoint-Crash = Down:** Fail-safe (`|| true`, `exec`), Syntaxcheck, Run-Test.
- **B5 – Lokaler Nebeneffekt:** Host-`data/audio` wird uid 82 → lokal evtl. `sudo` zum Löschen.
  Akzeptiert/dokumentiert.
- **B6 – Entfernen von 5b:** nichts anderes hängt daran; `mkdir -p` bleibt optional als Mount-Quelle.
- **B7 – Kein Quota / WAV-Größe:** vorbestehend, **out of scope** v0.4.1, in todo notieren.
- **B8 – Web-Image-Timing:** `:v0.4.1` muss vor Pi-Pull gebaut sein – Retry (5×30s) im Deploy deckt das ab.
- **B9 – `docker compose exec -T` schluckt stdin** und fraß in der Vorsession Heredoc-Folgezeilen →
  in SSH-Heredocs `</dev/null` an jede `exec`-/`curl`-Zeile (L-024).

## Lessons (im Folge-Chat zu committen)
- **L-023 – R7 / Bind-Mount-Rechte & Deploy-Skript-Versatz:** Docker legt Bind-Mounts root-owned an
  → Service-User (www-data/uid 82) kann nicht schreiben; Healthcheck auf `/profiles` verdeckt das.
  Deploy-Skript-Änderungen greifen erst beim übernächsten Deploy und `chmod` durch den Deploy-User
  scheitert an root-Dirs. **Regel:** Schreibrechte für gemountete Service-Verzeichnisse über einen
  **Container-Entrypoint (root → chown Service-User, dann exec Original-Entrypoint)** sicherstellen,
  nicht über das Deploy-Skript. Healthchecks müssen den **schreibenden** Pfad treffen.
- **L-024 – `docker compose exec -T` und stdin:** `exec` konsumiert stdin; in einem `ssh 'bash -s'
  <<EOF`-Heredoc verschluckt der erste `exec` die restlichen Zeilen. **Regel:** in Heredocs/Skripten
  `</dev/null` an `docker compose exec` (und nachfolgende `curl`) hängen oder Schritte getrennt
  aufrufen.

## Decision (im Folge-Chat zu committen)
- **D-021 – R7 via Container-Entrypoint (ersetzt Deploy-Skript-`chmod`):** Storage-Schreibrechte
  werden durch einen `app`-Image-Entrypoint hergestellt (idempotent, self-healing, im selben Deploy
  wirksam). Begründung: Deploy-Skript-Versatz + chmod-durch-Deploy-User-Limitation (siehe L-023).

## Schrittfolge (autonom)
1. Worktree `../SpotFamServ-v0.4.1 -b fix/audio-storage-perms origin/main`.
2. `backend/docker-entrypoint.sh` anlegen; Dockerfile (ENTRYPOINT/CMD); `pi-deploy.sh` 5b entfernen.
3. `frontend/package.json` 0.4.1; CHANGELOG; L-023/L-024; D-021; todo.md.
4. **Lokal verifizieren:** `sh -n backend/docker-entrypoint.sh`; `docker-compose build app`;
   `docker-compose up -d`; Break-&-Heal: `docker compose exec -T app chown root:root /data/audio`
   → `docker compose restart app` → `exec -u www-data` Schreibtest **WRITE_OK** → echte Extraktion
   (legale CC-Quelle) → download/delete. PHPUnit/PHPStan (App-Code unverändert → schnell).
5. PR `fix(audio-extractor): R7 via container entrypoint` → CI grün → Squash-Merge.
6. Tag `v0.4.1` auf Merge-Commit → push (Web-Image-Build abwarten).
7. **Pi-Verifikation (SSH 192.168.1.91):** auf `v0.4.1`; `data/audio` jetzt www-data; **aktiver
   Break-&-Heal** (`chown root` via container → `docker compose restart app` → www-data-Schreibtest
   + echte Extraktion). E2E download/update/delete. `</dev/null` an alle `exec`/`curl` im Heredoc.
8. todo.md final; ggf. Worktrees/Branches aufräumen.

## Out of scope (notieren, nicht umsetzen)
Quota/Größenlimit für `data/audio`; asynchrone Extraktion (Queue) statt synchron; YouTube-Bot-Schutz-Härtung.
