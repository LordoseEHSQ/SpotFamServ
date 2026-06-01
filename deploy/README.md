# Pi Auto-Deploy (tag-getriggert, Pull-basiert)

Mechanismus (Decision D-A = A): Der Pi pollt GitHub per systemd-Timer und deployt
automatisch den neuesten `v*`-Tag. Kein Inbound noetig (Pi hinter Heim-NAT).

## Komponenten
- `pi-deploy.sh` – idempotentes Deploy (fetch tags → backup → checkout neuester `v*`
  → conditional build/composer → up → migrate → healthcheck).
- `pi-backup.sh` – `pg_dump` vor jeder Migration, Rotation (`KEEP`, default 7), nach `backups/`.
- `systemd/spotfam-deploy.{service,timer}` – Timer alle 2 Min.

## Einmalige Einrichtung auf dem Pi
```bash
# 1) Repo ist git-Clone mit read-only Deploy-Key (siehe WP #3).
# 2) Skripte ausfuehrbar:
chmod +x /home/lars/SpotFamServ/deploy/*.sh

# 3) systemd-Units installieren:
sudo cp /home/lars/SpotFamServ/deploy/systemd/spotfam-deploy.service /etc/systemd/system/
sudo cp /home/lars/SpotFamServ/deploy/systemd/spotfam-deploy.timer   /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now spotfam-deploy.timer

# Status / Logs:
systemctl status spotfam-deploy.timer
journalctl -u spotfam-deploy.service -n 50 --no-pager
```

## Release ausloesen (von der Dev-Maschine)
```bash
git tag v0.2.0 && git push origin v0.2.0
# Innerhalb von ~2 Min zieht der Pi v0.2.0 und deployt.
```

## Manuell deployen (ohne auf den Timer zu warten)
```bash
ssh lars@<pi> '/home/lars/SpotFamServ/deploy/pi-deploy.sh'
```
