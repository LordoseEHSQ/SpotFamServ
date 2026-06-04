"""Tests fuer esptool_runner: Output-Parsing ohne echte Hardware.

Alle esptool-Subprozesse werden per monkeypatch ersetzt.
"""

from __future__ import annotations

import pytest

from flash_agent.esptool_runner import (
    EsptoolError,
    _parse_chip_description,
    _parse_flash_size,
    _parse_mac,
    detect_chip,
)


# ---------------------------------------------------------------------------
# Parsing-Unit-Tests (kein Subprozess noetig)
# ---------------------------------------------------------------------------

CHIP_ID_OUTPUT = """\
esptool.py v5.2.0
Serial port /dev/ttyUSB0
Connecting....
Chip is ESP32-D0WD-V3 (revision v3.1)
Features: WiFi, BT, Dual Core, 240MHz, VRef calibration in efuse, Coding Scheme None
Crystal is 26MHz
MAC: 78:ee:4c:01:6b:04
Uploading stub...
Running stub...
Stub running...
Chip ID: 0x0001234567abcdef
Hard resetting via RTS pin...
"""

CHIP_ID_OUTPUT_NO_REVISION = """\
esptool.py v5.2.0
Serial port /dev/ttyUSB0
Chip is ESP32-D0WD
MAC: AA:BB:CC:DD:EE:FF
"""

FLASH_ID_OUTPUT = """\
esptool.py v5.2.0
Serial port /dev/ttyUSB0
Connecting....
Manufacturer: ef
Device: 4016
Detected flash size: 4MB
Hard resetting via RTS pin...
"""

FLASH_ID_OUTPUT_UNKNOWN = """\
esptool.py v5.2.0
Serial port /dev/ttyUSB0
Connecting....
Manufacturer: ef
Device: 4016
"""


class TestParseChipDescription:
    def test_standard_with_revision(self):
        result = _parse_chip_description(CHIP_ID_OUTPUT)
        assert result == "ESP32-D0WD-V3"

    def test_without_revision(self):
        result = _parse_chip_description(CHIP_ID_OUTPUT_NO_REVISION)
        assert result == "ESP32-D0WD"

    def test_raises_on_missing(self):
        with pytest.raises(EsptoolError, match="Chip-Bezeichnung"):
            _parse_chip_description("esptool.py v5.2.0\nkeine Chip-Zeile hier")


class TestParseMac:
    def test_lowercase_input(self):
        result = _parse_mac(CHIP_ID_OUTPUT)
        assert result == "78:EE:4C:01:6B:04"

    def test_uppercase_in_output(self):
        output = "MAC: AA:BB:CC:DD:EE:FF\n"
        assert _parse_mac(output) == "AA:BB:CC:DD:EE:FF"

    def test_raises_on_missing(self):
        with pytest.raises(EsptoolError, match="MAC-Adresse"):
            _parse_mac("kein mac hier")


class TestParseFlashSize:
    def test_standard(self):
        result = _parse_flash_size(FLASH_ID_OUTPUT)
        assert result == "4MB"

    def test_missing_returns_unknown(self):
        result = _parse_flash_size(FLASH_ID_OUTPUT_UNKNOWN)
        assert result == "unknown"

    def test_alternate_format(self):
        output = "Flash size: 8 MB\n"
        result = _parse_flash_size(output)
        assert result == "8MB"


# ---------------------------------------------------------------------------
# detect_chip Integration (monkeypatched Subprozess)
# ---------------------------------------------------------------------------

class TestDetectChip:
    def test_returns_chipinfo(self, monkeypatch):
        """detect_chip gibt korrektes ChipInfo zurueck wenn esptool erfolgreich ist."""

        call_count = {"n": 0}

        def fake_run_esptool(args: list[str], timeout: int = 30) -> str:
            call_count["n"] += 1
            if "chip-id" in args:
                return CHIP_ID_OUTPUT
            if "flash-id" in args:
                return FLASH_ID_OUTPUT
            raise AssertionError(f"Unerwarteter Aufruf: {args}")

        monkeypatch.setattr(
            "flash_agent.esptool_runner._run_esptool", fake_run_esptool
        )

        from flash_agent.esptool_runner import ChipInfo
        info = detect_chip("/dev/ttyUSB0", esptool_bin="esptool")

        assert isinstance(info, ChipInfo)
        assert info.chip_description == "ESP32-D0WD-V3"
        assert info.chip == "esp32"
        assert info.mac == "78:EE:4C:01:6B:04"
        assert info.flash_size == "4MB"
        assert call_count["n"] == 2  # chip-id + flash-id

    def test_raises_on_esptool_error(self, monkeypatch):
        """detect_chip propagiert EsptoolError."""

        def fake_run_esptool(args: list[str], timeout: int = 30) -> str:
            raise EsptoolError("Verbindung fehlgeschlagen")

        monkeypatch.setattr(
            "flash_agent.esptool_runner._run_esptool", fake_run_esptool
        )

        with pytest.raises(EsptoolError):
            detect_chip("/dev/ttyUSB0")
