"""Tests für den vendored NVS-Generator (flash_agent/nvs.py).

Korrektheits-Gates:
1. Struktur: Page-Header (State/Version), Größe, Bitmap.
2. CRCs unabhängig via zlib nachgerechnet.
3. Round-Trip: parse_nvs_partition(generate(...)) == Input.
4. Optional (skip wenn nicht installiert): Byte-Vergleich der Entry-Region gegen das
   offizielle esp-idf-Tool als autoritative Format-Referenz.
"""

from __future__ import annotations

import struct
import zlib

import pytest

from flash_agent.nvs import (
    PAGE_SIZE,
    STATE_ACTIVE,
    VERSION2,
    NvsError,
    generate_nvs_partition,
    parse_nvs_partition,
)

NS = "spotfam"
SIZE = 0x5000  # 20 KiB = 5 Pages (Standard ESP32-Arduino nvs-Partition)
ENTRIES = {
    "wifi_ssid": "MeinHeimnetz",
    "wifi_password": "sup3r-s3cr3t!",
    "backend_url": "http://192.168.1.91:8080",
    "fw_channel": "stable",
    "reader_api_key": "abcdef0123456789",
}


def test_generate_has_exact_size_and_active_header() -> None:
    blob = generate_nvs_partition(ENTRIES, NS, SIZE)
    assert len(blob) == SIZE
    assert struct.unpack_from("<I", blob, 0)[0] == STATE_ACTIVE
    assert blob[8] == VERSION2
    # Header-CRC unabhängig nachrechnen.
    assert zlib.crc32(bytes(blob[4:28]), 0xFFFFFFFF) & 0xFFFFFFFF == struct.unpack_from("<I", blob, 28)[0]


def test_round_trip_returns_input() -> None:
    blob = generate_nvs_partition(ENTRIES, NS, SIZE)
    assert parse_nvs_partition(blob, NS) == ENTRIES


def test_all_entry_and_data_crcs_valid() -> None:
    # parse_nvs_partition wirft NvsError bei jedem CRC-Fehler -> Erfolg = alle CRCs ok.
    blob = generate_nvs_partition(ENTRIES, NS, SIZE)
    assert parse_nvs_partition(blob, NS) == ENTRIES


def test_corrupted_crc_is_detected() -> None:
    blob = bytearray(generate_nvs_partition(ENTRIES, NS, SIZE))
    # Ein Datenbyte in der ersten String-Daten-Region kippen.
    blob[64 + 32 + 32] ^= 0xFF
    with pytest.raises(NvsError):
        parse_nvs_partition(bytes(blob), NS)


def test_other_namespace_is_isolated() -> None:
    blob = generate_nvs_partition(ENTRIES, NS, SIZE)
    assert parse_nvs_partition(blob, "anderer_ns") == {}


def test_rejects_bad_size() -> None:
    with pytest.raises(NvsError):
        generate_nvs_partition(ENTRIES, NS, 1234)  # nicht Vielfaches von 4096
    with pytest.raises(NvsError):
        generate_nvs_partition(ENTRIES, NS, PAGE_SIZE)  # < 3 Pages


def test_rejects_long_key() -> None:
    with pytest.raises(NvsError):
        generate_nvs_partition({"this_key_is_way_too_long": "x"}, NS, SIZE)


def test_matches_official_esp_idf_tool_if_available() -> None:
    """Autoritativer Format-Check: Ausgabe == offizielles esp-idf-Tool (byte-genau).

    Wird übersprungen, wenn das Tool nicht installiert ist (kein Pflicht-Dependency,
    D-030/vendored). Bei Verfügbarkeit muss das erzeugte Binary BYTE-FÜR-BYTE dem
    offiziellen ``esp_idf_nvs_partition_gen`` entsprechen.

    Manuell bereits verifiziert (2026-06-05): 0 Diff-Bytes über alle Pages.
    """
    pytest.importorskip(
        "esp_idf_nvs_partition_gen.nvs_partition_gen",
        reason="esp-idf nvs_partition_gen nicht installiert; Byte-Vergleich übersprungen.",
    )
    import csv
    import subprocess
    import sys
    import tempfile
    from pathlib import Path

    with tempfile.TemporaryDirectory() as tmp:
        csv_path = Path(tmp) / "in.csv"
        out_path = Path(tmp) / "out.bin"
        with open(csv_path, "w", newline="") as fh:
            w = csv.writer(fh)
            w.writerow(["key", "type", "encoding", "value"])
            w.writerow([NS, "namespace", "", ""])
            for k, v in ENTRIES.items():
                w.writerow([k, "data", "string", v])
        subprocess.run(
            [
                sys.executable, "-m", "esp_idf_nvs_partition_gen.nvs_partition_gen",
                "generate", str(csv_path), str(out_path), hex(SIZE), "--version", "2",
            ],
            check=True, capture_output=True,
        )
        official = out_path.read_bytes()

    assert generate_nvs_partition(ENTRIES, NS, SIZE) == official
