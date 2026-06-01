# Sprint 1 – Deploy & Quality Gates

**Milestone:** Sprint 1 – Deploy & Quality Gates
**Status:** Abgeschlossen · **Release:** `v0.1.0` (2026-06-01)

## Sprint-Goal
Reproduzierbares, automatisiertes und abgesichertes Deployment auf den Pi: Neue
getaggte Versionen landen ohne manuelle Schritte und ohne Datenverlust auf der Hardware,
und `main` ist gegen ungeprüfte Änderungen geschützt.

## Acceptance Criteria (alle erfüllt)
- [x] Pi läuft als read-only git-Clone (Deploy-Key), nicht mehr als rsync-Kopie.
- [x] Ein Tag `v*` auf `main` wird vom Pi automatisch (≤ ~2 Min) deployt.
- [x] Vor jeder Migration wird ein `pg_dump`-Backup erstellt (rotiert).
- [x] Stack startet nach Reboot automatisch (`restart: unless-stopped`).
- [x] `main` ist geschützt: PR-Pflicht + grüne CI-Checks erforderlich.

## WorkPackages
| Issue | Titel | Ergebnis |
|---|---|---|
| #3 | Pi auf git-Clone + read-only Deploy-Key | in-place `git init` → `reset --hard v0.1.0`, Secrets/dist erhalten; Deploy-Key (id 153147247, read-only) |
| #4 | Idempotentes Deploy- + Backup-Skript | `deploy/pi-deploy.sh`, `deploy/pi-backup.sh` (pg_dump, Rotation) |
| #5 | Auto-Deploy-Trigger (D-008) | systemd-Timer (2 Min) + oneshot-Service auf dem Pi |
| #6 | Branch Protection + CI-Härtung | 5 required Checks, PR-Pflicht, linear; Node24-Opt-in |
| #7 | restart: unless-stopped | app/nginx/db |

## Verifikation (auf dem Pi)
- `systemctl is-active spotfam-deploy.timer` → `active`; Service-Result `success`.
- `./deploy/pi-deploy.sh` auf `v0.1.0` → korrektes No-op ("Bereits auf v0.1.0").
- `./deploy/pi-backup.sh` → Dump unter `backups/db-v0.1.0-*.sql.gz`.
- `GET http://localhost:8080/api/v1/profiles` → `200` (nach Cold-Start-Warmup).

## Decisions
- **D-008:** Deploy-Mechanismus = systemd-Pull (kein Inbound nötig, kein CI-Secret).

## Blind Spots / Risiken (offen)
- **DHCP-IP nicht reserviert** (`192.168.1.91`): Bei IP-Wechsel müssen Deploy-Key-Pfad
  (lokal) und SSH-Tunnel angepasst werden. Empfehlung: DHCP-Reservierung im Router.
- **Kein Healthcheck-Alerting:** Schlägt ein Auto-Deploy fehl, sieht man es nur im
  Journal (`journalctl -u spotfam-deploy.service`). Kein Push-Alert.
- **Rollback ist manuell:** Bei fehlerhaftem Deploy bleibt das `pg_dump`-Backup, aber
  ein automatischer Code-Rollback (Checkout des Vorgänger-Tags) ist nicht implementiert.
- **Frontend-Build auf dem Pi:** `pnpm` ist auf dem Pi nicht installiert → `dist/` wird
  nicht automatisch neu gebaut. Frontend-Änderungen müssen separat als `dist/` bereitgestellt
  oder `pnpm` auf dem Pi nachgezogen werden.
