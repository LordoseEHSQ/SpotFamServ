#!/usr/bin/env bash
# pg_dump-Backup der SpotFamServ-Datenbank (vor Deploys/Migrationen).
# Rotation: behaelt die letzten $KEEP Dumps. Idempotent, sicher mehrfach aufrufbar.
set -euo pipefail

REPO_DIR="${REPO_DIR:-/home/lars/SpotFamServ}"
BACKUP_DIR="${BACKUP_DIR:-$REPO_DIR/backups}"
KEEP="${KEEP:-7}"

cd "$REPO_DIR"
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

TS="$(date +%Y%m%d-%H%M%S)"
REF="$(git describe --tags --always 2>/dev/null || echo nogit)"
OUT="$BACKUP_DIR/db-${REF}-${TS}.sql.gz"

if ! docker compose ps db --status running >/dev/null 2>&1; then
  echo "[backup] WARN: db-Container laeuft nicht – ueberspringe Dump." >&2
  exit 0
fi

echo "[backup] pg_dump -> $OUT"
docker compose exec -T db pg_dump -U spotfam spotfam | gzip > "$OUT"
chmod 600 "$OUT"

# Rotation: aelteste ueber KEEP hinaus entfernen
ls -1t "$BACKUP_DIR"/db-*.sql.gz 2>/dev/null | tail -n +"$((KEEP + 1))" | xargs -r rm -f

echo "[backup] OK ($(du -h "$OUT" | cut -f1)); behalte letzte $KEEP."
