#!/bin/sh
set -e
# R7 (D-021/L-023): Audio-Storage-Bind-Mount fuer www-data (uid 82) schreibbar machen.
# Docker legt Bind-Mounts root-owned an; der php-fpm-Worker UND der Messenger-Worker laufen
# als www-data. Laeuft als root beim Container-Start, ist idempotent und self-healing
# (Reboot/restart/recreate). Ein fehlschlagendes chown (read-only Mount/named volume) darf
# den Start NICHT crashen -> `|| true` + `exec` (Fail-safe gegen DoS-on-start).
if [ -d /data/audio ]; then
    chown www-data:www-data /data/audio 2>/dev/null || true
fi

exec docker-php-entrypoint "$@"
