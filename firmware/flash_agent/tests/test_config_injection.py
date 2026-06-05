"""Tests für die Flash-Zeit-NVS-Injektion (agent._inject_reader_config, Sprint 06 C2b).

esptool wird gemockt; der echte NVS-Generator/-Parser läuft im Round-Trip,
sodass die Verifikationslogik (inkl. Mismatch-Erkennung) geprüft wird.
"""

from __future__ import annotations

from pathlib import Path

import pytest

from flash_agent import agent as agent_mod
from flash_agent.agent import (
    ConfigInjectionError,
    _inject_reader_config,
    _reader_config_to_nvs_entries,
)
from flash_agent.backend_client import ReaderConfig
from flash_agent.config import Config


def _config() -> Config:
    return Config(
        backend_base_url="http://x",
        flash_agent_api_key="",
        firmware_dir="/tmp",
        nvs_offset=0x9000,
        nvs_size=0x5000,
        nvs_namespace="spotfam",
    )


def _reader_cfg() -> ReaderConfig:
    return ReaderConfig(
        wifi_ssid="Heimnetz",
        wifi_password="s3cr3t",
        backend_base_url="http://192.168.1.91:8080",
        ota_channel="stable",
        reader_api_key="reader-key-123",
        complete=True,
    )


def test_entries_mapping_includes_reader_key() -> None:
    entries = _reader_config_to_nvs_entries(_reader_cfg())
    assert entries["wifi_ssid"] == "Heimnetz"
    assert entries["wifi_pass"] == "s3cr3t"
    assert entries["backend_url"] == "http://192.168.1.91:8080"
    assert entries["ota_channel"] == "stable"
    assert entries["reader_key"] == "reader-key-123"
    for key in entries:
        assert len(key.encode()) <= 15


def test_entries_mapping_omits_missing_reader_key() -> None:
    cfg = _reader_cfg()
    cfg.reader_api_key = None
    entries = _reader_config_to_nvs_entries(cfg)
    assert "reader_key" not in entries


def test_inject_round_trip_success(monkeypatch: pytest.MonkeyPatch) -> None:
    written: dict[str, bytes] = {}

    def fake_flash_at_offset(*, port, image_path, offset, baud, esptool_bin):  # type: ignore[no-untyped-def]
        written["bin"] = Path(image_path).read_bytes()

    def fake_read_flash(*, port, offset, size, out_path, baud, esptool_bin):  # type: ignore[no-untyped-def]
        # Simuliert das Gerät: liefert exakt die geschriebenen Bytes zurück.
        return written["bin"]

    monkeypatch.setattr(agent_mod, "flash_at_offset", fake_flash_at_offset)
    monkeypatch.setattr(agent_mod, "read_flash", fake_read_flash)

    # Darf nicht werfen.
    _inject_reader_config("/dev/ttyUSB0", _reader_cfg(), _config())
    assert "bin" in written and len(written["bin"]) == 0x5000


def test_inject_detects_readback_mismatch(monkeypatch: pytest.MonkeyPatch) -> None:
    def fake_flash_at_offset(*, port, image_path, offset, baud, esptool_bin):  # type: ignore[no-untyped-def]
        pass

    def fake_read_flash(*, port, offset, size, out_path, baud, esptool_bin):  # type: ignore[no-untyped-def]
        # Gerät liefert eine leere/0xFF-Partition zurück -> Mismatch.
        return b"\xff" * size

    monkeypatch.setattr(agent_mod, "flash_at_offset", fake_flash_at_offset)
    monkeypatch.setattr(agent_mod, "read_flash", fake_read_flash)

    with pytest.raises(ConfigInjectionError):
        _inject_reader_config("/dev/ttyUSB0", _reader_cfg(), _config())
