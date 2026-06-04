"""Laufzeit-Konfiguration des Flash-Agents.

Alle Parameter kommen ausschliesslich aus Umgebungsvariablen
(siehe ``secrets.example.env``). Kein Hard-Coding von Secrets.
"""

from __future__ import annotations

import os
from dataclasses import dataclass


@dataclass(frozen=True)
class Config:
    """Unveraenderliche Laufzeit-Konfiguration, befuellt aus Umgebungsvariablen."""

    backend_base_url: str
    flash_agent_api_key: str
    firmware_dir: str

    # Verhalten – Defaults konservativ, per ENV ueberschreibbar.
    poll_interval_s: float = 5.0
    http_timeout_s: float = 10.0
    esptool_bin: str = "esptool"
    flash_baud: int = 460800
    log_level: str = "INFO"

    @staticmethod
    def from_env() -> "Config":
        """Liest Konfiguration aus Umgebungsvariablen; wirft SystemExit bei Fehlern."""

        backend_base_url = os.environ.get("BACKEND_BASE_URL", "").rstrip("/")
        flash_agent_api_key = os.environ.get("FLASH_AGENT_API_KEY", "")
        firmware_dir = os.environ.get("FIRMWARE_DIR", "")

        missing = [
            name
            for name, value in (
                ("BACKEND_BASE_URL", backend_base_url),
                ("FLASH_AGENT_API_KEY", flash_agent_api_key),
                ("FIRMWARE_DIR", firmware_dir),
            )
            if not value
        ]
        if missing:
            raise SystemExit(
                "Fehlende Pflicht-Umgebungsvariablen: "
                + ", ".join(missing)
                + " (siehe secrets.example.env)."
            )

        def _float_env(name: str, default: float) -> float:
            raw = os.environ.get(name)
            if raw is None or raw.strip() == "":
                return default
            try:
                return float(raw)
            except ValueError:
                raise SystemExit(
                    f"Umgebungsvariable {name} ist keine Zahl: {raw!r}"
                )

        def _int_env(name: str, default: int) -> int:
            raw = os.environ.get(name)
            if raw is None or raw.strip() == "":
                return default
            try:
                return int(raw)
            except ValueError:
                raise SystemExit(
                    f"Umgebungsvariable {name} ist keine Ganzzahl: {raw!r}"
                )

        return Config(
            backend_base_url=backend_base_url,
            flash_agent_api_key=flash_agent_api_key,
            firmware_dir=firmware_dir,
            poll_interval_s=_float_env("POLL_INTERVAL_S", 5.0),
            http_timeout_s=_float_env("HTTP_TIMEOUT_S", 10.0),
            esptool_bin=os.environ.get("ESPTOOL_BIN", "esptool"),
            flash_baud=_int_env("FLASH_BAUD", 460800),
            log_level=os.environ.get("LOG_LEVEL", "INFO").upper(),
        )
