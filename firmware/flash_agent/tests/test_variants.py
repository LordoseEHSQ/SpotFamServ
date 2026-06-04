"""Tests fuer variants.py: Chip-Whitelist, is_supported, matches."""

from __future__ import annotations

import pytest

from flash_agent.variants import chip_family, is_supported, matches


class TestChipFamily:
    def test_d0wd_v3(self):
        assert chip_family("ESP32-D0WD-V3") == "esp32"

    def test_d0wd_classic(self):
        assert chip_family("ESP32-D0WD") == "esp32"

    def test_d0wdr2(self):
        assert chip_family("ESP32-D0WDR2-V3") == "esp32"

    def test_case_insensitive(self):
        assert chip_family("esp32-d0wd-v3") == "esp32"

    def test_unknown_returns_none(self):
        assert chip_family("ESP32-S3") is None

    def test_completely_unknown(self):
        assert chip_family("UnbekannterChip-XYZ") is None


class TestIsSupported:
    def test_known_chip(self):
        assert is_supported("ESP32-D0WD-V3") is True

    def test_known_chip_classic(self):
        assert is_supported("ESP32-D0WD") is True

    def test_unknown_chip(self):
        assert is_supported("ESP8266") is False

    def test_empty_string(self):
        assert is_supported("") is False

    def test_esp32_s3_not_supported(self):
        assert is_supported("ESP32-S3") is False

    def test_esp32_c3_not_supported(self):
        assert is_supported("ESP32-C3") is False


class TestMatches:
    def test_exact_match(self):
        assert matches("ESP32-D0WD-V3", "ESP32-D0WD-V3") is True

    def test_expected_prefix_of_actual(self):
        # Erwartet "ESP32-D0WD", tatsaechlich "ESP32-D0WD-V3" -> Muss passen
        # (D0WD ist Praefix von D0WD-V3).
        assert matches("ESP32-D0WD", "ESP32-D0WD-V3") is True

    def test_actual_prefix_of_expected(self):
        # Umgekehrt: erwartet "ESP32-D0WD-V3", erkannt "ESP32-D0WD".
        assert matches("ESP32-D0WD-V3", "ESP32-D0WD") is True

    def test_different_family(self):
        assert matches("ESP32-D0WD-V3", "ESP32-S3") is False

    def test_unknown_expected(self):
        assert matches("ESP8266", "ESP32-D0WD-V3") is False

    def test_unknown_actual(self):
        assert matches("ESP32-D0WD-V3", "ESP8266") is False

    def test_both_unknown(self):
        assert matches("ESP8266", "ESP8266") is False

    def test_case_insensitive(self):
        assert matches("esp32-d0wd-v3", "ESP32-D0WD-V3") is True
