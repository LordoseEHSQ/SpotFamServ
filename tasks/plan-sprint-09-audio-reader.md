# Plan: Sprint 09 – Audio-Extraktor Härtung + Reader-Lifecycle

**Erstellt:** 2026-06-09  
**Status:** Bestätigt – Dry-Run eingearbeitet  
**Branch/Worktree:** `feat/sprint-09-audio-reader` / `../SpotFamServ-sprint-09`  
**Ziel-Version:** v0.9.0  

---

## Scope

### Block B – Audio-Extraktor Härtung (v0.7.1-Backlog)

| # | Item | Bereich |
|---|------|---------|
| B1 | Warteschlange-Card immer rendern (auch leer), Loading/Error/Empty-State | Frontend |
| B2 | Toast-System (`sonner`) einführen: Submit-Erfolg, `failed`, `done` | Frontend |
| B3 | „Erneut versuchen" je `failed`-Zeile (Re-Submit mit gleicher URL/Format) | Frontend |
| B4 | yt-dlp-Rohfehler-Icon `text-destructive` + Fehlertext aufklappbar | Frontend |
| B5 | `failed`/`canceled`-Job dismissbar via `DELETE /jobs/{id}` (DB-Delete) | Backend + Frontend |
| B6 | Deploy-Härtung L-034: vendor-Volume-Entscheidung (echte Compose-Änderung) | Infra |

### Block C – Reader-Lifecycle

| # | Item | Bereich |
|---|------|---------|
| C1 | API-Key-Rotation UI: Button in Reader-Zeile, Plain-Key einmalig anzeigen + Warnung | Frontend |
| C2 | Reader-Löschen: `DELETE /readers/{readerId}` (Reader + ScanEvents via UUID + Claims) | Backend + Frontend |
| C3 | PHP-FPM `pm.max_children=10` via vollständige `www.conf` im Dockerfile | Infra |

---

## Ist-Stand-Korrektur (Dry-Run W1)

Folgende Annahmen im Draft waren falsch – korrigiert:

- `useCancelAudioJob` hat **bereits** `onSuccess`-Invalidation für `audioExtractorKeys.jobs()`. Kein Neubau nötig.
- `JobRow` zeigt bereits `job.error` als Text. **B4** ist daher nur: Icon von `text-muted-foreground` → `text-destructive`, Fehlertext per `<details>` aufklappbar (nicht ein Neubau).

---

## Architektur-Entscheidungen

### B5: Dismiss-Semantik für `DELETE /jobs/{id}` (W3 eingearbeitet)

**Neu:** DELETE behandelt alle States:
- `pending` → cancel (Status `canceled`, bleibt in DB – unverändertes Verhalten) → Response **200 + AudioJobDto**
- `failed` / `canceled` → Hard-Delete der DB-Zeile → Response **204 (kein Body)**
- `done` → **NICHT** dismissbar via diese Route (done-Jobs gehören zu „Gespeicherte Dateien"; die Job-Zeile kann nach done-Transition nach kurzer Zeit aus dem Queue-View verschwinden, ohne Delete). Explizite Entscheidung: `done`-Jobs bleiben in DB bis sie natürlich ablaufen oder manuell über einen künftigen Cleanup-Task entfernt werden.
- `running` → 409 (unverändert)

**W3-Fix:** API-Client + Hook müssen 200/204 robust handhaben. `cancelJob`-Hook gibt bei Hard-Delete `undefined` zurück (kein Body). `onSuccess`-Invalidation läuft in beiden Pfaden. OpenAPI-Spec: beide Response-Codes dokumentieren (200 mit Schema, 204 ohne). oasdiff ist für diesen Endpunkt blind (kein dokumentiertes Schema im jetzigen Spec) – wird nicht als Sicherheitsnachweis gewertet.

**Neuer UseCase:** `DismissAudioJob` (Hard-Delete für `failed`/`canceled`).  
**Neues Port-Method (W5):** `AudioJobRepositoryInterface::delete(AudioJob $job): void`.  
Controller wählt UseCase je nach Job-State: `pending` → `CancelAudioJob`, `failed`/`canceled` → `DismissAudioJob`.

### C2: Reader-Löschen (K1 eingearbeitet)

**K1-Fix:** `ScanEvent.reader_device_id` ist eine UUID (`ReaderDevice.id`), **nicht** der logische `readerId`-String. Cleanup-Reihenfolge:

1. `ReaderDeviceRepositoryInterface::findByReaderId(string $readerId): ?ReaderDevice` (existiert) → UUID holen
2. `ScanEventRepositoryInterface::deleteByReaderDeviceId(string $uuid): void` (neu, korrekter Spaltenname)
3. `ReaderClaimRepositoryInterface::deleteByReaderId(string $readerId): void` (neu, W5)
4. `ReaderDeviceRepositoryInterface::delete(ReaderDevice $device): void` (neu, W5)

**Orphan-Events (bekannte Lücke):** ScanEvents mit `reader_device_id = NULL` (fehlgeschlagene Scans vor Registrierung) werden über diesen Cleanup **nicht** erfasst. Wird dokumentiert und akzeptiert (home-use, kein PII-Risiko). Kein Block.

**Port-Methoden neu (W5 gesamt):**
- `AudioJobRepositoryInterface::delete(AudioJob $job): void`
- `ScanEventRepositoryInterface::deleteByReaderDeviceId(string $uuid): void`
- `ReaderClaimRepositoryInterface::deleteByReaderId(string $readerId): void`
- `ReaderDeviceRepositoryInterface::delete(ReaderDevice $device): void`

### C3: PHP-FPM pm.max_children (K2 eingearbeitet)

**K2-Fix:** Die `www.conf` muss den vollständigen Pool definieren, nicht nur die `pm.*`-Direktiven. Sonst hört php-fpm nicht mehr auf Port 9000 → 502.

Vollständige `backend/docker/www.conf`:
```ini
[www]
user = www-data
group = www-data
listen = 9000
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 5
```

`Dockerfile`: `COPY docker/www.conf /usr/local/etc/php-fpm.d/www.conf`

Verifikation nach Deploy: `docker compose exec app php-fpm -tt 2>&1 | grep max_children`

**M3-Note:** Pi 4B hat 4 GB RAM. 10 × ~30 MB PHP-Worker = ~300 MB, akzeptabel.

**M4-Note:** Spätere reine `www.conf`-Änderungen lösen keinen Rebuild aus (pi-deploy.sh prüft nur Dockerfile/compose/composer). Lösung: auch `backend/docker/` in den Trigger aufnehmen (wird im Rahmen von B6 mit erledigt).

### B6: Deploy-Härtung L-034 (W4 eingearbeitet)

**W4-Fix:** Es gibt keine separate Pi-`docker-compose.yml`. Das Bind-Mount `./backend:/var/www/html` existiert in der Root-`docker-compose.yml` für `app` und `messenger-worker` und läuft auch auf dem Pi. L-034 ist **nicht Doku-only**.

**Entscheidung (D-S9-01):** vendor-Isolation via **anonymes Volume** für `vendor` im Compose:
```yaml
services:
  app:
    volumes:
      - ./backend:/var/www/html
      - /var/www/html/vendor   # anonymes Volume überlagert den Bind-Mount für vendor
  messenger-worker:
    volumes:
      - ./backend:/var/www/html
      - /var/www/html/vendor
```

Das `vendor`-Verzeichnis im anonymen Volume wird beim ersten Container-Start aus dem Image befüllt. `composer install` im Haupt-Deploy-Schritt (pi-deploy.sh Schritt 9) schreibt weiterhin in den Bind-Mount – das anonyme Volume **überschattet** den Bind-Mount-Vendor. Das ist **falsch** – die Composer-Ausgabe wäre unsichtbar im Container.

**Alternative (D-S9-01 korrigiert):** Bind-Mount entfernen, stattdessen Deployment via Image-Build (kein Bind-Mount auf `/var/www/html`). Das war schon immer die Produktions-Intention. `pi-deploy.sh` führt dann `docker compose up --build -d` ohne Bind-Mount durch. Secrets/Config via Env-Variablen (bereits der Fall). Das löst L-034 strukturell.

**Vorgehen B6:**
1. `docker-compose.yml`: Bind-Mount `./backend:/var/www/html` aus `app` + `messenger-worker` entfernen
2. `pi-deploy.sh`: `composer install`-Schritt entfernen (läuft jetzt im Dockerfile-Build)
3. `APP_ENV=dev`-Hardcodierung (W4-Nebenbefund): auf `APP_ENV=${APP_ENV:-prod}` umstellen
4. Deploy-Trigger um `backend/docker/` erweitern (M4-Fix)
5. Doku: `docs/pi-deployment.md` aktualisieren

⚠️ **WICHTIG:** Das ist eine Breaking Change im Deployment-Prozess. Nach Merge muss auf dem Pi ein `docker compose down && docker compose up --build -d` ausgeführt werden (kein Auto-Deploy reicht, da Volumes sich ändern). Das ist ein **User-Gate beim Pi-Deploy**.

### C1: API-Key-Rotation UI (W6 eingearbeitet)

**W6-Fix:** Rotation invalidiert sofort den auf dem ESP32 geflashten Key → Reader fällt aus bis Reflasch. UI muss vor der Rotation eine **explizite Bestätigungswarnung** zeigen:

> „Durch Rotation wird der bestehende API-Key des Readers sofort ungültig. Der Reader (`{reader_id}`) kann keine Karten mehr scannen, bis er mit dem neuen Key neu geflasht wird. Fortfahren?"

Der Plain-Key wird nach der Rotation **einmalig** in einem Dialog angezeigt (Copy-Button) und danach nie wieder gezeigt. Die Backend-Endpunkte (`POST /{readerId}/api-key`, `DELETE /{readerId}/api-key`) existieren bereits.

---

## 4-Lens-Analyse

### Lens 1 – Runtime & Sprache
- PHP 8.5.6 / Symfony 7.4 LTS: stabil, keine Änderung.
- React/Vite: Frontend läuft mit Node für den Build-Step (x86_64 lokal, Output statisch → Pi/arm64 egal).
- `sonner`: client-only, keine native Abhängigkeit, arm64-irrelevant.

### Lens 2 – Frameworks & Abhängigkeiten
- **`sonner` (neue npm-Dependency):** ~11 kB gzip, MIT-Lizenz, keine Peer-Konflikte mit shadcn/Radix/Tailwind. `<Toaster />` wird in `frontend/src/App.tsx` am App-Root gemountet.
- Keine neuen PHP-Packages.
- `package-lock.json` + `package.json` +1. `composer.lock` unverändert.
- Drei neue Port-Methoden in bestehenden Interfaces – kein neues Package, keine Lock-Änderung.

### Lens 3 – Build, CI/CD & Tooling
- **PHPStan L6**: alle neuen UseCases und Port-Methoden vollständig typsicher.
- **PHPUnit**: neue Tests für `DismissAudioJob` (alle Branches), `DeleteReader` (inkl. UUID-Auflösung, Claim-Cleanup).
- **oasdiff**: `DELETE /readers/{readerId}` additiv. `DELETE /jobs/{id}` – Spec auf 200/204 ergänzen. oasdiff nicht als Sicherheitsnachweis für Response-Body-Änderung.
- **Vitest**: Dismiss-Button nur bei `failed`/`canceled`, Cancel nur bei `pending`, 409-Toast, Toast-Mock.
- **pi-deploy.sh**: Bind-Mount-Entfernung, Compose-Build-Trigger erweitert.

### Lens 4 – Security & Compliance
- Alle neuen Endpoints unter `^/api/v1` → Catch-all ROLE_ADMIN (security.yaml). Keine Public-Pattern-Überlappung.
- Hard-Delete: 404 bei wiederholtem Call (akzeptabel, M1).
- `done`-Jobs: nicht dismissbar → kein unbeabsichtigtes Löschen von Job-Ergebnissen.
- API-Key-Rotation: Bestätigungs-Dialog verhindert versehentliche Rotation.

---

## Test-Matrix (W7 eingearbeitet)

### PHPUnit – `DismissAudioJob`
- `pending` → cancel-Pfad (Status=canceled, kein Delete)
- `failed` → Hard-Delete (404 bei wiederholtem Aufruf)
- `canceled` → Hard-Delete
- `done` → 409 (not dismissable via this endpoint)
- `running` → 409
- not-found → 404

### PHPUnit – `DeleteReader`
- happy path: reader + ScanEvents (via `reader_device_id`/UUID) + Claims gelöscht
- reader not found → 404
- reader ohne ScanEvents → ok
- reader ohne Claims → ok
- Orphan-Events (`reader_device_id = NULL`) bleiben → kein Fehler, dokumentiert

### PHPUnit – Controller `DELETE /jobs/{id}`
- pending: Response 200 + AudioJobDto
- failed: Response 204, kein Body
- canceled: Response 204, kein Body

### Vitest – `AudioExtractorPage`
- Warteschlange-Card sichtbar wenn `jobs.length === 0`
- Warteschlange-Card zeigt Error-State wenn `/jobs`-Query fehlschlägt
- Dismiss-Button bei `failed` vorhanden; bei `pending` nicht
- Cancel-Button bei `pending` vorhanden; bei `failed` nicht
- Toast erscheint bei Submit-Erfolg (mock `sonner`)

---

## WorkPackages (GitHub Issues)

| Label | Titel | Parallelisierung |
|-------|-------|-----------------|
| WP-B1234 | Audio-UX: Warteschlange always-visible, Toasts, Retry, Fehler-UX | Frontend-Schwarm |
| WP-B5 | DismissAudioJob + DELETE /jobs/{id} 200/204 | Backend → Frontend |
| WP-B6 | L-034: Bind-Mount entfernen, APP_ENV, pi-deploy.sh | Infra |
| WP-C1 | Reader API-Key-Rotation UI + Warn-Dialog | Frontend-Schwarm |
| WP-C2 | DeleteReader (UUID-Fix, Port-Methoden, Tests) | Backend → Frontend |
| WP-C3 | PHP-FPM www.conf vollständig + pi-deploy.sh Trigger | Infra (mit B6) |

Parallelisierbar in Schwärmen: Backend (B5+C2), Frontend (B1-B4+C1), Infra (B6+C3).

---

## Umsetzungsreihenfolge

1. **Infra** (B6 + C3): Compose-Bind-Mount-Entfernung, vollständige `www.conf`, pi-deploy.sh-Fixes
2. **Backend** (B5 + C2): Port-Methoden, UseCases, Controller, Tests, PHPStan
3. **Frontend** (B1-B4 + B5-Client + C1 + C2-Client): Audio-UX, sonner, Dismiss, Reader-Lifecycle
4. oasdiff, PHPUnit, Vitest, tsc → alle grün
5. Commit + PR + Squash-Merge → v0.9.0-Tag

---

## User-Gate (nach Merge)

Pi-Deploy erfordert manuellen Schritt (wegen Bind-Mount-Entfernung + Volume-Änderung):
```bash
docker compose down
docker compose up --build -d
```
Auto-Deploy via systemd-Trigger reicht **nicht** – User muss manuell eingreifen.

---

## Definition of Done

- [ ] PHPUnit ≥ bestehende Anzahl + neue Tests laut Testmatrix
- [ ] PHPStan Level 6 clean
- [ ] `lint:container` grün
- [ ] TypeScript build + Vitest clean
- [ ] oasdiff: keine Breaking Changes; `DELETE /jobs/{id}` auf 200/204 dokumentiert
- [ ] Warteschlange-Card immer sichtbar, Error-State bei Query-Fehler
- [ ] Toasts bei Submit, `done`, `failed` (sonner, `<Toaster/>` in App.tsx)
- [ ] failed/canceled-Job dismiss: Zeile weg, DB-Row gelöscht (204)
- [ ] Retry-Button bei failed-Zeile funktional
- [ ] Reader-Löschen: Reader + ScanEvents (via UUID) + Claims weg
- [ ] API-Key-Rotation mit Warn-Dialog + einmalige Plain-Key-Anzeige
- [ ] `pm.max_children=10` verifiziert (`docker compose exec app php-fpm -tt 2>&1 | grep max_children`)
- [ ] Bind-Mount aus Compose entfernt, `APP_ENV` nicht mehr hardkodiert
- [ ] CHANGELOG.md + `docs/sprints/sprint-09.md` aktualisiert
- [ ] GitHub-Milestone Sprint 09 closed, alle WP-Issues closed
- [ ] User-Gate für Pi-Deploy dokumentiert

---

## Befunde des Dry-Run

### KRITISCH (eingearbeitet)
- **K1** – C2: `ScanEvent.reader_device_id` ist UUID, nicht readerId-String → UUID-Auflösung via `findByReaderId` vor Delete. ✅ Eingearbeitet.
- **K2** – C3: Unvollständige `www.conf` → 502. Vollständige Pool-Definition in Plan + Datei. ✅ Eingearbeitet.

### WICHTIG (eingearbeitet)
- **W1** – Falsche Ist-Stand-Annahmen korrigiert (onSuccess-Invalidation existiert; Fehlertext schon gerendert). ✅
- **W2** – `done`-Dismiss explizit ausgeschlossen (bleibt in DB). ✅
- **W3** – 200/204-Response-Semantik definiert, API-Client angepasst, Spec dokumentiert. ✅
- **W4** – B6 als echte Compose-Entscheidung: Bind-Mount-Entfernung + APP_ENV + User-Gate. ✅
- **W5** – Alle vier fehlenden Port-Methoden explizit benannt. ✅
- **W6** – API-Key-Rotation-Warndialog (Reader fällt aus). ✅
- **W7** – Vollständige Testmatrix. ✅
- **W8** – Lens 1+2 ergänzt, `sonner` in Lens 2, `<Toaster/>` Mounting spezifiziert. ✅
