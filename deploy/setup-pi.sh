#!/usr/bin/env bash
# deploy/setup-pi.sh – Idempotentes Pi-Setup für den SpotFam Flash-Agent.
# Kann beliebig oft ausgeführt werden (set -euo pipefail sichert atomare Abbrüche).
#
# Voraussetzungen:
#   - Docker installiert (für den Backend-Stack)
#   - Python 3 installiert
#   - firmware/flash_agent/secrets.env ausgefüllt (FLASH_AGENT_API_KEY darf nicht leer sein)
#
# Ausführen vom Repo-Root:
#   bash deploy/setup-pi.sh
set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# ─── 1. Voraussetzungen ───────────────────────────────────────────────────────
command -v docker >/dev/null || { echo "FEHLER: Docker nicht installiert."; exit 1; }
command -v python3 >/dev/null || { echo "FEHLER: Python3 fehlt."; exit 1; }

# ─── 2. Flash-Agent: venv + Abhängigkeiten ───────────────────────────────────
AGENT_DIR="$REPO_DIR/firmware/flash_agent"
cd "$AGENT_DIR"

python3 -m venv .venv
.venv/bin/pip install --quiet -r requirements.txt

# ─── 3. secrets.env prüfen ───────────────────────────────────────────────────
if [ ! -f secrets.env ]; then
    cp secrets.example.env secrets.env
    echo ""
    echo "AKTION ERFORDERLICH: $AGENT_DIR/secrets.env ausfüllen,"
    echo "  insbesondere FLASH_AGENT_API_KEY, dann Skript erneut ausführen."
    exit 1
fi

# shellcheck source=/dev/null
source secrets.env

if [ -z "${FLASH_AGENT_API_KEY:-}" ]; then
    echo "FEHLER: FLASH_AGENT_API_KEY ist leer in secrets.env. Eintragen, dann erneut ausführen."
    exit 1
fi

# ─── 4. FIRMWARE_DIR anlegen ─────────────────────────────────────────────────
FIRMWARE_DIR="${FIRMWARE_DIR:-/home/lars/SpotFamServ/backend/var/firmware}"
mkdir -p "$FIRMWARE_DIR"

# ─── 5. dialout-Gruppe ───────────────────────────────────────────────────────
if ! groups "$USER" | grep -q '\bdialout\b'; then
    sudo usermod -aG dialout "$USER"
    echo "HINWEIS: Bitte neu einloggen (oder 'newgrp dialout'), damit die dialout-Gruppe aktiv wird."
fi

# ─── 6. systemd-Unit installieren und aktivieren ─────────────────────────────
UNIT_SRC="$REPO_DIR/deploy/systemd/spotfam-flash-agent.service"
UNIT_DST="/etc/systemd/system/spotfam-flash-agent.service"

sudo cp "$UNIT_SRC" "$UNIT_DST"
sudo systemctl daemon-reload
sudo systemctl enable --now spotfam-flash-agent.service

# ─── 7. Smoke-Test ───────────────────────────────────────────────────────────
echo ""
echo "Führe Smoke-Test aus (flash_agent detect)…"
.venv/bin/python -m flash_agent detect && echo "Flash-Agent OK"

# ─── 8. Abschluss ────────────────────────────────────────────────────────────
echo ""
echo "=== Setup abgeschlossen ==="
echo "Nächste Schritte:"
echo "  1. ESP32 per USB am Pi anschließen"
echo "  2. http://192.168.1.91:8080/provisioning öffnen"
echo "  3. Firmware-Artefakt hochladen oder aus CI-Release wählen"
echo "  4. systemctl status spotfam-flash-agent  →  muss 'active (running)' zeigen"
