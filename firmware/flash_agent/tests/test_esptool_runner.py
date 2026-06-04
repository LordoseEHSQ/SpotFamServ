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

# Reale esptool v5.3.0 chip-id-Ausgabe (auf dem Pi gegen echte Hardware erfasst).
# Enthaelt bewusst die Zeile "Detecting chip type... ESP32", die NICHT als
# Chip-Bezeichnung fehlinterpretiert werden darf.
CHIP_ID_OUTPUT = """\
Serial port /dev/ttyUSB0:
Connecting.......
Detecting chip type... ESP32
Connected to ESP32 on /dev/ttyUSB0:
Chip type:          ESP32-D0WD-V3 (revision v3.1)
Features:           Wi-Fi, BT, Dual Core + LP Core, 240MHz, Vref calibration in eFuse, Coding Scheme None
Crystal frequency:  40MHz
MAC:                78:ee:4c:01:6b:04
Uploading stub flasher...
Running stub flasher...
Stub flasher running.
Warning: ESP32 has no chip ID. Reading MAC address instead.
MAC:                78:ee:4c:01:6b:04
Hard resetting via RTS pin...
"""

# Legacy-Format (esptool <= v5.2): "Chip is ...".
CHIP_ID_OUTPUT_LEGACY = """\
esptool.py v5.2.0
Serial port /dev/ttyUSB0
Connecting....
Chip is ESP32-D0WD-V3 (revision v3.1)
MAC: 78:ee:4c:01:6b:04
"""

CHIP_ID_OUTPUT_NO_REVISION = """\
Serial port /dev/ttyUSB0:
Chip type:          ESP32-D0WD
MAC:                AA:BB:CC:DD:EE:FF
"""

# Reale esptool v5.3.0 flash-id-Ausgabe.
FLASH_ID_OUTPUT = """\
esptool v5.3.0
Serial port /dev/ttyUSB0:
Connecting.......
Detecting chip type... ESP32
Connected to ESP32 on /dev/ttyUSB0:
Flash Memory Information:
=========================
Manufacturer: 85
Device: 2016
Detected flash size: 4MB
Flash voltage set by a strapping pin: 3.3V
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
    def test_v5_3_chip_type_with_revision(self):
        # Reale v5.3.0-Ausgabe "Chip type: ..." inkl. "Detecting chip type..."-Zeile.
        result = _parse_chip_description(CHIP_ID_OUTPUT)
        assert result == "ESP32-D0WD-V3"

    def test_legacy_chip_is(self):
        result = _parse_chip_description(CHIP_ID_OUTPUT_LEGACY)
        assert result == "ESP32-D0WD-V3"

    def test_without_revision(self):
        result = _parse_chip_description(CHIP_ID_OUTPUT_NO_REVISION)
        assert result == "ESP32-D0WD"

    def test_detecting_line_not_mistaken(self):
        # Nur die "Detecting chip type... ESP32"-Zeile (kein Doppelpunkt) → kein Match.
        with pytest.raises(EsptoolError, match="Chip-Bezeichnung"):
            _parse_chip_description("Detecting chip type... ESP32\n")

    def test_raises_on_missing(self):
        with pytest.raises(EsptoolError, match="Chip-Bezeichnung"):
            _parse_chip_description("esptool v5.3.0\nkeine Chip-Zeile hier")


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
