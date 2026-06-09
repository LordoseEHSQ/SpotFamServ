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

# jq wird für den automatischen Firmware-Download benötigt; idempotent installieren
if ! command -v jq >/dev/null 2>&1; then
    echo "Installiere jq (wird für GitHub-Release-Download benötigt)…"
    sudo apt-get install -y --no-install-recommends jq
fi

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
# ESPTOOL_BIN aus secrets.env ist bereits exportiert (Schritt 3).
# Zusätzlich venv/bin in PATH setzen, damit das CLI-Entrypoint 'esptool' direkt auffindbar ist.
export PATH="$AGENT_DIR/.venv/bin:$PATH"
.venv/bin/python -m flash_agent detect && echo "Flash-Agent OK"

# ─── 8. Firmware-Artefakt aus letztem GitHub-Release (optional) ────────────
cd "$REPO_DIR"
FW_FILENAME="spotfam_reader.ino.merged.bin"
FW_DEST="$FIRMWARE_DIR/$FW_FILENAME"

if command -v curl >/dev/null && command -v jq >/dev/null; then
    REPO_SLUG="LordoseEHSQ/SpotFamServ"
    RELEASE_JSON="$(curl -fsSL "https://api.github.com/repos/${REPO_SLUG}/releases/latest" 2>/dev/null || true)"
    if [ -n "$RELEASE_JSON" ]; then
        ASSET_URL="$(echo "$RELEASE_JSON" | jq -r '.assets[] | select(.name == "'"$FW_FILENAME"'") | .browser_download_url' | head -1)"
        TAG_NAME="$(echo "$RELEASE_JSON" | jq -r '.tag_name')"
        if [ -n "$ASSET_URL" ] && [ "$ASSET_URL" != "null" ]; then
            echo ""
            echo "Lade Firmware-Artefakt aus Release ${TAG_NAME}…"
            curl -fsSL -o "$FW_DEST" "$ASSET_URL"
            FW_VERSION="${TAG_NAME#v}"
            if docker compose ps --status running app 2>/dev/null | grep -q app; then
                docker compose exec -T app php bin/console app:provisioning:register-artifact \
                    --board=esp32-wroom-32 \
                    --channel=stable \
                    --firmware-version="$FW_VERSION" \
                    --file="$FW_FILENAME" \
                    --expected-chip=ESP32-D0WD-V3 \
                    && echo "Firmware-Artefakt registriert (${FW_VERSION})."
            else
                echo "HINWEIS: Docker-Stack läuft nicht – Artefakt liegt in $FW_DEST."
                echo "  Nach 'docker compose up -d' registrieren:"
                echo "  docker compose exec -T app php bin/console app:provisioning:register-artifact \\"
                echo "    --board=esp32-wroom-32 --channel=stable --firmware-version=${FW_VERSION} \\"
                echo "    --file=${FW_FILENAME} --expected-chip=ESP32-D0WD-V3"
            fi
        else
            echo "HINWEIS: Kein Release-Asset ${FW_FILENAME} gefunden – manueller Upload in Firmware-Station."
        fi
    else
        echo "HINWEIS: GitHub-Release-API nicht erreichbar – Firmware manuell hochladen."
    fi
else
    echo "HINWEIS: curl/jq fehlen – Firmware-Artefakt nicht automatisch geladen."
fi

# ─── 9. Abschluss ────────────────────────────────────────────────────────────
echo ""
echo "=== Setup abgeschlossen ==="
echo "Nächste Schritte:"
echo "  1. ESP32 per USB am Pi anschließen"
echo "  2. http://192.168.1.91:8080/provisioning öffnen"
echo "  3. Firmware-Artefakt hochladen oder aus CI-Release wählen"
echo "  4. systemctl status spotfam-flash-agent  →  muss 'active (running)' zeigen"
