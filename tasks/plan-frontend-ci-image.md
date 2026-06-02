# Plan: Frontend-Deploy via CI-gebautes Image (Bug #20, D-012)

**Erstellt:** 2026-06-02
**Status:** In Progress (Entscheidungen freigegeben 2026-06-02)
**Branch/Worktree:** `feat/frontend-ci-image` / `../SpotFamServ-ci-image`
**Issue:** #20 · **Decision:** D-012 (Option A), D-013 (O-1..O-4) · **Lessons:** L-009, L-011, L-012

## Freigegebene Entscheidungen (2026-06-02) → D-013
- **O-1 = public** (User). SPA-Bundle ohne Secrets; kein PAT auf dem Pi. Einmalig Package-Visibility=public setzen.
- **O-2 = `vX.Y.Z` + `latest` + `sha-<short>`** (Empfehlung). Reproduzierbar + Komfort/Debug.
- **O-3 = `${WEB_IMAGE_TAG}` injiziert** (Empfehlung). `pi-deploy.sh` setzt exakten git-Tag; Fallback `latest`.
- **O-4 = bake_keep** (korrigierte Empfehlung). `default.conf` ins Image backen **und** Bind-Mount behalten;
  nur den `frontend/dist`-Bind-Mount entfernen. Begründung: deckt sich mit der Topologie-Notiz, risikoärmer,
  Config bleibt git-getrieben (folgt dem Tag), lokale Config-Edits ohne Rebuild; Image bleibt self-contained.

---

## Scope
Frontend wird nicht mehr auf dem Pi gebaut, sondern in CI zu einem nginx-Image
(`ghcr.io/lordoseehsq/spotfamserv-web:<tag>`) gebacken (multi-arch inkl. arm64), das die
SPA (`frontend/dist`) **und** `default.conf` enthält. Der Pi macht für das Web nur noch
`docker compose pull && up -d`. Der bisherige git-ignorierte `frontend/dist`-Bind-Mount entfällt;
der `backend/public:ro`-Mount (nötig für `$realpath_root` im fastcgi-Proxy) bleibt.

## Betroffene Bereiche
- `docker/frontend/Dockerfile` (NEU) — Build-Stage (Node→pnpm→dist) + Runtime-Stage (nginx + dist + default.conf).
- `.dockerignore` (NEU, Repo-Root) — Build-Context schlank + **Secrets aus Context fernhalten** (Security).
- `.github/workflows/release-web-image.yml` (NEU) — tag-getriggert (`v*`) buildx → GHCR-Push; PR-Validierung (build-only).
- `docker-compose.yml` — `nginx`-Service auf GHCR-Image, `frontend/dist`-Mount raus, `backend/public:ro` bleibt; `default.conf`-Mount: siehe offene Entscheidung O-4.
- `deploy/pi-deploy.sh` — Schritt 5 (Frontend-Build) raus; `WEB_IMAGE_TAG` setzen + `docker compose pull` (mit Retry) ergänzen.
- `frontend/package.json` — Version auf Release-Tag bumpen (`0.2.1` → `0.2.2`), da `__APP_VERSION__` Build-Zeit-Define.
- `docs/pi-deployment.md`, `CHANGELOG.md`, `tasks/lessons.md` (ggf. L-013), `tasks/decisions.md` (D-013/D-014 für offene Punkte).
- Optional lokal: `docker-compose.override.yml` (git-ignoriert) für Dev-Ergonomie — abhängig von O-4.

---

## Cross-Module Antworten
1. **Upstream** — Wer speist nginx/Web? Bisher: lokaler Pi-Build (`pnpm`), der **still fehlschlug** (L-011).
   Neu: CI-Job ist der Producer; der Pi konsumiert nur das Image. Bruchrisiko: Image für einen Tag
   existiert noch nicht in GHCR, wenn der Pi-Timer schon zieht → `pull` schlägt fehl. **Mitigation:**
   bounded Retry im `pull` + idempotenter 2-Min-Timer (self-healing beim nächsten Tick).
2. **Downstream** — Konsumenten: (a) Browser (SPA unter `/`), (b) **ESP32 + API-Clients** über
   `/api` → fastcgi `app:9000`. Die API-Route lebt im **selben** nginx-Server-Block. Risiko: beim
   Image-Schnitt die API-Proxy-Konfig oder den `backend/public:ro`-Mount kaputt machen → API tot.
   **Mitigation:** `default.conf` 1:1 ins Image backen, `backend/public:ro` zwingend behalten,
   nach Deploy `/api/v1/profiles` UND SPA-Marker prüfen (L-011: Bundle prüfen, nicht Label).
3. **Audit** — Infrastruktur-/Deploy-Mechanik-Änderung. Kein DB-Audit-Eintrag. Decision-Log:
   D-012 deckt Grundsatz; zusätzliche Entscheidungen (GHCR-Sichtbarkeit, Tag-Schema, Image-Referenz,
   default.conf-Bake) als D-013/D-014 dokumentieren.
4. **API-Vertrag** — Keine Response-Shape-Änderung. `openapi.yaml` unberührt → kein oasdiff-Impact
   (läuft ohnehin nur auf PR, nicht auf Tag-Push). `default.conf`-`/api`-Routing bleibt unverändert.
5. **Feature-Flags** — Nicht nötig. Gating erfolgt über den Release-Tag (D-005).

---

## Architektur / technischer Schnitt

### Dockerfile (`docker/frontend/Dockerfile`, Build-Context = Repo-Root)
```dockerfile
# Build-Stage IMMER nativ auf der Runner-Arch (amd64) – NIE unter QEMU.
FROM --platform=$BUILDPLATFORM node:22-alpine AS build
WORKDIR /app
RUN corepack enable
COPY frontend/package.json frontend/pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile
COPY frontend/ ./
RUN pnpm build            # erzeugt /app/dist; __APP_VERSION__ aus package.json (Build-Zeit)

# Runtime-Stage: multi-arch (nginx:alpine ist multi-arch), KEIN RUN auf Target-Arch.
FROM nginx:alpine
COPY --from=build /app/dist /usr/share/nginx/html
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
```
**Kernpunkt arm64:** `--platform=$BUILDPLATFORM` pinnt die Node-Stage auf die Runner-Arch (amd64).
Die Runtime-Stage hat **kein `RUN`** → die arm64-Variante ist nur `nginx:alpine`(arm64) + arch-neutrale
COPY-Schichten (statische JS/CSS/HTML). Kein QEMU-Node-Build → schnell. `dist` ist arch-unabhängig.

### `.dockerignore` (Repo-Root, NEU – Security-kritisch)
Muss mindestens ausschließen: `.git`, `**/node_modules`, `backend/vendor`, `backend/var`,
`frontend/dist`, `pi-image`, `backups`, `**/*.env*` (außer `*.example`), `**/secrets.h`, `*.log`.
→ Verhindert, dass `.env`, `backend/.env.local`, `firmware/.../secrets.h` in den Build-Context/Layer
geraten (sonst Secret-Leak ins öffentliche Image).

### CI-Workflow (`.github/workflows/release-web-image.yml`, NEU)
- Trigger: `push: tags: ['v*']` (Build+Push) **und** `pull_request` mit `paths: [frontend/**, docker/**]`
  (nur Build-Validierung, **kein** Push, single-arch amd64 für Speed).
- `permissions: { contents: read, packages: write }`.
- Steps: `actions/checkout` → `docker/setup-qemu-action` (defensiv) → `docker/setup-buildx-action`
  → (nur Tag) `docker/login-action` GHCR (`username: ${{ github.actor }}`, `password: ${{ secrets.GITHUB_TOKEN }}`)
  → `docker/metadata-action` (Tags, Lowercase-Owner) → `docker/build-push-action`
  (`platforms: linux/amd64,linux/arm64`, `push: ${{ tag }}`, `context: .`, `file: docker/frontend/Dockerfile`).
- Bestehende `ci.yml` bleibt unverändert (triggert nicht auf Tags) → die 5 Required-Checks bleiben gleich.

### `docker-compose.yml` (nginx-Service)
```yaml
nginx:
  image: ghcr.io/lordoseehsq/spotfamserv-web:${WEB_IMAGE_TAG:-latest}
  volumes:
    - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro  # BLEIBT (O-4 bake_keep, git-getrieben)
    - ./backend/public:/var/www/html/public:ro                       # BLEIBT (realpath_root für fastcgi)
    # ./frontend/dist  -> ENTFERNT (kommt aus dem Image)
```

### `deploy/pi-deploy.sh`
- Schritt 5 (pnpm-Build) ersatzlos streichen.
- Vor `up -d`: `export WEB_IMAGE_TAG="$LATEST_TAG"` + `docker compose pull` mit bounded Retry
  (z. B. 5×, 30 s) → toleriert die CI-Build-Latenz; bei Dauerfehler sauberer `exit 1`,
  nächster Timer-Tick (2 Min) zieht erneut. `docker compose build app` bleibt (Backend baut lokal).

---

## 4-Lens-Analyse
**Lens 1 – Runtime & Sprache:** Node 22-alpine (Build, amd64-nativ) · nginx:alpine multi-arch
(amd64+arm64) · Ziel Pi 4B aarch64/Debian 13, Docker 29.5.2, Compose v2 (L-002). Runtime-Stage ohne
`RUN` → keine arm64-Native-Code-Ausführung im Build nötig. pnpm via `corepack` (kein Extra-Setup).
**Lens 2 – Frameworks & Abhängigkeiten:** Keine neue App-Dependency. Neue **CI-Actions** (Lieferkette):
`docker/setup-buildx-action`, `docker/setup-qemu-action`, `docker/login-action`, `docker/metadata-action`,
`docker/build-push-action` — etablierte, gepflegte Actions; auf gepinnte Major-Versionen setzen.
Vite-`define`/`pnpm-lock.yaml` unverändert.
**Lens 3 – Build, CI/CD & Tooling:** Neuer tag-getriggerter Workflow getrennt von `ci.yml`.
buildx + GHCR. `GITHUB_TOKEN` mit `packages: write` (kein PAT für den Push nötig, da Repo-Owner =
Package-Owner). PR-Build-Validierung als Frühwarnung. Pi: `pull` statt Build.
**Lens 4 – Security & Compliance:** (a) `.dockerignore` verhindert Secret-Leak in den (öffentlichen)
Image-Layer. (b) GHCR-Sichtbarkeit (O-1): public = kein Token, aber Image weltlesbar; das SPA-Bundle
enthält **keine** Secrets (relative `/api/v1`, keine Tokens gebacken) → Confidentiality-Gewinn von
private ≈ 0, Betriebskosten real (PAT-Handling wie L-009). (c) Image-Tags immutable per `vX.Y.Z`
für Reproduzierbarkeit. (d) Trivy scannt aktuell nur FS, nicht das Image — optional späterer
Image-Scan (out of scope).

---

## Offene Entscheidungen (brauche Freigabe vor Code)
- **O-1 — GHCR public oder private?**
  *Empfehlung:* **public**. Das SPA-Bundle ist ohnehin Browser-öffentlich, enthält keine Secrets;
  spart PAT-Login + Token-Rotation auf dem Pi (L-009). Einmaliger Schritt: Package-Visibility in
  GitHub auf „public" stellen (erster Push legt es ggf. als private an).
  *Falls private:* Pi braucht `docker login ghcr.io` mit Read-PAT (Secret-Handling wie L-009).
- **O-2 — Image-Tag-Schema?**
  *Empfehlung:* `vX.Y.Z` (immutable) **+** `latest` **+** `sha-<short>` (Debug). Reproduzierbarer
  Deploy über den festen `vX.Y.Z`-Tag; `latest` nur als Fallback/Komfort.
- **O-3 — Image-Referenz in compose: fester Tag (vom Deploy-Skript injiziert) vs. `latest`?**
  *Empfehlung:* `image: ...:${WEB_IMAGE_TAG:-latest}`; `pi-deploy.sh` injiziert den exakten `vX.Y.Z`
  (= deployter git-Tag) → laufendes Web-Image ist an den Backend-Tag gekoppelt (starke Konsistenz).
  Manuelle Läufe ohne Var fallen auf `latest` zurück (dokumentieren).
- **O-4 — `default.conf` ins Image backen UND Bind-Mount entfernen, oder Mount behalten?**
  *Empfehlung:* **backen + Bind-Mount entfernen** (Single Source = Image; Config folgt dem Release).
  Konsequenz lokal: nginx-Config-Änderungen brauchen Image-Rebuild; lokale Frontend-Arbeit läuft
  ohnehin über `pnpm dev` (Vite-Proxy → :8080). Für prod-nahe lokale Tests optional git-ignoriertes
  `docker-compose.override.yml` (nginx:alpine + Bind-Mounts).
  *Alternative (risikoärmer, minimaler Diff):* `default.conf`-Bind-Mount in compose **behalten**
  (kommt auf dem Pi aus dem getrackten git-Stand, überschattet die gebackene Version) — nur den
  `frontend/dist`-Mount entfernen, exakt wie in der Topologie-Notiz formuliert.

---

## Akzeptanzkriterien
1. Push eines `v*`-Tags baut + pusht `ghcr.io/lordoseehsq/spotfamserv-web:<tag>` als multi-arch
   (amd64 **und** arm64; `docker buildx imagetools inspect` zeigt beide).
2. Der Node/Vite-Build läuft in CI **nicht** unter QEMU (Build-Stage = `$BUILDPLATFORM`/amd64);
   Gesamt-Buildzeit im akzeptablen Rahmen (Richtwert < ~6 Min).
3. `docker compose config` (lokal & Pi-Sicht) ist valide; `nginx` referenziert das GHCR-Image,
   `frontend/dist`-Mount ist weg, `backend/public:ro` ist vorhanden.
4. `pi-deploy.sh` enthält keinen pnpm-Build mehr, zieht das Web-Image (mit Retry) und ist idempotent.
5. Nach Deploy auf dem Pi: SPA zeigt den korrekten Sprint-2-Stand — verifiziert über **Bundle-Marker**
   (`curl .../assets/index-*.js | grep <feature-marker>`), nicht nur das Versionslabel (L-011).
6. `/api/v1/profiles` liefert weiter `200` (API-Proxy unbeschädigt).
7. Bestehende CI (`ci.yml`, 5 Required-Checks) bleibt grün; Branch-Protection unverändert.
8. `frontend/package.json`-Version == Release-Tag (`0.2.2`).

## Definition of Done
- [ ] Implementierung komplett (Dockerfile, .dockerignore, Workflow, compose, pi-deploy.sh, package.json-Bump)
- [ ] CI grün; Tag-Build erzeugt multi-arch Image in GHCR (verifiziert via `imagetools inspect`)
- [ ] Pi zieht Image automatisch; SPA-Bundle-Marker + `/api`-Healthcheck verifiziert
- [ ] API-Vertrag unberührt (keine openapi-Änderung)
- [ ] Bestehende Tests/Checks laufen weiter
- [ ] Cross-Module-Checkliste beantwortet (oben)
- [ ] Docs: `docs/pi-deployment.md` (Runbook auf pull), `CHANGELOG.md`, Decisions D-013/D-014, ggf. L-013
- [ ] PR squash-merge → Tag `v0.2.2` → Issue #20 schließen

---

## Risiken / Blind Spots
- **R1 – Build/Pull-Race (Reihenfolge):** Pi-Timer triggert auf den git-Tag, das Image entsteht erst
  parallel in CI. Pull vor Existenz → Fehler. *Mitigation:* bounded Retry im pull + self-healing Timer.
  *Rest-Risiko:* erstes Deploy nach Tag verzögert sich um ein paar Timer-Ticks.
- **R2 – arm64-Build-Zeit:** Nur entschärft, wenn Build-Stage `$BUILDPLATFORM` nutzt **und** Runtime-Stage
  kein `RUN` enthält. Jede `RUN`-Zeile in der Runtime-Stage würde QEMU-arm64 erzwingen → vermeiden.
- **R3 – GHCR-Auth (O-1):** Bei „private" scheitert der Pi-Pull ohne Login; PAT-Secret-Handling (L-009).
  Bei „public" Package-Visibility-Schritt nicht vergessen (erster Push = private default).
- **R4 – Bind-Mount-Migration (L-012):** Umstieg Mount→Image. Da der Mount **entfernt** und der
  Container von compose **neu erstellt** wird (Image+Volume-Änderung), greift die L-012-Inode-Falle
  hier nicht. Verifikation trotzdem über Bundle-Marker (L-011), nicht über das Label. Alt-`frontend/dist`
  auf dem Pi bleibt verwaist (harmlos, git-ignoriert) — optional aufräumen.
- **R5 – package.json-Version-Drift:** Label kommt aus `package.json` (Build-Zeit). Tag ohne vorherigen
  Bump → falsches Label. *Mitigation:* Bump ist fester Release-Ritus (Akzeptanzkriterium 8); optional
  später Version aus git-Tag als `--build-arg` ableiten (out of scope, in L-013 vermerken).
- **R6 – Rollback:** `pi-deploy.sh` zieht stets den **neuesten** `v*`-Tag → echtes Rollback = neuer
  höherer Tag vom älteren Commit (z. B. Revert→`v0.2.3`). Ad-hoc auf dem Pi:
  `export WEB_IMAGE_TAG=v0.2.1 && docker compose up -d nginx` (alte Image-Tags bleiben immutable in GHCR).
- **R7 – Lokale Dev-Ergonomie (O-4):** Image-basierte nginx in der Haupt-compose ändert den lokalen
  prod-nahen Flow. *Mitigation:* `pnpm dev` (primär) + optionales git-ignoriertes Override.
- **R8 – Secret-Leak im Build-Context:** Ohne `.dockerignore` landen `.env`/`secrets.h` im (public!)
  Image-Layer. *Mitigation:* striktes Root-`.dockerignore` als Teil dieses PRs (kein optionaler Schritt).
- **R9 – GITHUB_TOKEN-Push-Rechte:** Erst-Push legt das Package an; bei user-eigenem Repo i. d. R. ok
  mit `packages: write`. Falls Org-Policy blockt → Fallback PAT. Beim ersten Tag-Build verifizieren.

## Dry-Run

### CI (Tag-Push `v0.2.2`)
1. `release-web-image.yml` startet (Trigger `tags: v*`).
2. checkout → setup-qemu → setup-buildx → GHCR-login (`GITHUB_TOKEN`).
3. metadata-action: Tags `:v0.2.2`, `:latest`, `:sha-xxxxxxx`, Owner→lowercase.
4. build-push: Build-Stage amd64-nativ (pnpm build), Runtime multi-arch (amd64+arm64) push.
5. Erwartung: `docker buildx imagetools inspect ghcr.io/lordoseehsq/spotfamserv-web:v0.2.2`
   listet beide Plattformen.

### Pi (systemd-Timer, ≤ 2 Min nach Tag)
1. `pi-deploy.sh`: `git fetch --tags` → `LATEST_TAG=v0.2.2` ≠ HEAD → Backup → `checkout -f v0.2.2`.
2. **Kein** pnpm-Build mehr.
3. `need_build`? (Dockerfile/compose/composer geändert) → ggf. `docker compose build app`.
4. `export WEB_IMAGE_TAG=v0.2.2` → `docker compose pull` (Retry bis Image da) → `docker compose up -d`
   (recreate nginx auf neues Image, ohne dist-Mount).
5. DB-healthy-Warte → ggf. composer/migrate → `cache:clear`.
6. Healthcheck `/api/v1/profiles` == 200.
7. Manuell verifizieren: SPA-Bundle-Marker (L-011) + Versionslabel `0.2.2`.

## Verifikations-Log
- Verifiziert: `docker-compose config` | mit/ohne `WEB_IMAGE_TAG` | nginx→`ghcr.io/lordoseehsq/spotfamserv-web:v0.2.2`
  bzw. Fallback `:latest`; nur `default.conf`+`backend/public:ro`-Mounts, kein `dist`-Mount | 2026-06-02
- Verifiziert: `bash -n deploy/pi-deploy.sh` | Syntax OK | 2026-06-02
- Verifiziert: `docker buildx build --platform linux/amd64 -f docker/frontend/Dockerfile` | Build grün,
  `spotfam-frontend@0.2.2 build`, vite-Bundle erzeugt, COPY dist + default.conf | 2026-06-02
- Verifiziert: Image-Inhalt | `default.conf` mit `/api`+`fastcgi_pass app:9000`+SPA-Fallback gebacken;
  `dist` vorhanden; Marker `0.2.2` im JS-Bundle | 2026-06-02
- Hinweis: `nginx -t` standalone schlägt fehl (Upstream `app` nur im Compose-Netz auflösbar) – erwartetes,
  unverändertes Verhalten (config byte-identisch zur bisherigen Bind-Mount-Version), kein Regress.
- Lokal NICHT verifizierbar (braucht CI/GitHub): multi-arch arm64-Build + GHCR-Push; Pi-Pull/Deploy.

## Abgeschlossen
- (offen – wartet auf grüne CI + Tag v0.2.2 + Pi-Deploy-Verifikation)
