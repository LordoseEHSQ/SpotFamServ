#!/usr/bin/env bash
# Tag-getriggertes Pull-Deploy auf dem Raspberry Pi.
# Idempotent: deployt nur, wenn ein neuerer v*-Tag vorliegt als der aktuelle Stand.
# Ablauf: fetch tags -> (falls neu) backup -> checkout Tag -> conditional build/composer
#         -> up -d -> migrate -> cache:clear -> healthcheck.
set -euo pipefail

REPO_DIR="${REPO_DIR:-/home/lars/SpotFamServ}"
HEALTH_URL="${HEALTH_URL:-http://localhost:8080/api/v1/profiles}"
cd "$REPO_DIR"

log() { echo "[$(date -Is)] [deploy] $*"; }

# 1) Tags holen
git fetch --tags --prune --quiet origin

LATEST_TAG="$(git tag -l 'v*' --sort=-v:refname | head -n1)"
if [ -z "$LATEST_TAG" ]; then
  log "Kein v*-Tag vorhanden – nichts zu deployen."
  exit 0
fi

CURRENT_REF="$(git rev-parse HEAD)"
TARGET_REF="$(git rev-parse "${LATEST_TAG}^{commit}")"
CURRENT_TAG="$(git describe --tags --exact-match 2>/dev/null || echo '(kein Tag)')"

if [ "$CURRENT_REF" = "$TARGET_REF" ]; then
  log "Bereits auf $LATEST_TAG ($CURRENT_TAG) – nichts zu tun."
  exit 0
fi

log "Deploy: $CURRENT_TAG ($CURRENT_REF) -> $LATEST_TAG ($TARGET_REF)"

# 2) Backup VOR jeder Aenderung
"$REPO_DIR/deploy/pi-backup.sh"

# 3) Was aendert sich? (fuer conditional build/composer)
CHANGED="$(git diff --name-only "$CURRENT_REF" "$TARGET_REF" 2>/dev/null || echo '')"
need_build=false; need_composer=false
echo "$CHANGED" | grep -qE '^(backend/Dockerfile|docker-compose\.yml|backend/composer\.(json|lock))' && need_build=true
echo "$CHANGED" | grep -qE '^backend/composer\.lock' && need_composer=true

# 4) Ziel-Tag auschecken (untracked Secrets/.env.local/dist/vendor bleiben erhalten)
git checkout -f "$LATEST_TAG"

# 5) Frontend-Build (statisch) – nur falls frontend sich geaendert hat und Node verfuegbar ist.
if echo "$CHANGED" | grep -qE '^frontend/' && command -v pnpm >/dev/null 2>&1; then
  log "Frontend-Aenderung erkannt – baue dist/"
  ( cd frontend && pnpm install --frozen-lockfile && pnpm build ) || log "WARN: Frontend-Build fehlgeschlagen (altes dist bleibt)."
fi

# 6) Image bauen (nur bei relevanten Aenderungen)
if [ "$need_build" = true ]; then
  log "Baue app-Image (Dockerfile/compose/composer geaendert)"
  docker compose build app
fi

# 7) Container starten/aktualisieren
docker compose up -d

# 8) Auf DB-Healthy warten
for i in $(seq 1 30); do
  if docker compose exec -T db pg_isready -U spotfam -d spotfam >/dev/null 2>&1; then break; fi
  sleep 2
done

# 9) Composer nur bei lock-Aenderung (Dev-Bind-Mount, vgl. lessons L-006)
if [ "$need_composer" = true ]; then
  log "composer.lock geaendert – composer install"
  docker compose exec -T app composer install --no-interaction --prefer-dist
fi

# 10) Migrationen (idempotent: nur ausstehende)
log "Migrationen"
docker compose exec -T app php bin/console doctrine:migrations:migrate --no-interaction

# 11) Cache leeren
docker compose exec -T app php bin/console cache:clear >/dev/null 2>&1 || true

# 12) Healthcheck
CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$HEALTH_URL" || echo 000)"
if [ "$CODE" = "200" ]; then
  log "OK – $LATEST_TAG live, Health $CODE."
else
  log "FEHLER – Health $CODE nach Deploy $LATEST_TAG. Backup unter backups/. Manuelle Pruefung noetig."
  exit 1
fi
