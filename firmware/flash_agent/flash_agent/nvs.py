"""Vendored NVS-Partition-Generator (nur Namespace + String-Entries).

Erzeugt ein ESP-IDF-NVS-kompatibles Partitions-Binary aus String-Key-Value-Paaren,
damit der Flash-Agent WLAN/Backend/OTA-Konfiguration zur Flash-Zeit als NVS in den
ESP32 schreiben kann (Sprint 06 / D-028, D-031).

WARUM VENDORED (D, User-Entscheidung 2026-06-05): kein Pip-Dependency auf dem Pi.
Diese Datei ist eine *getreue Teilmenge* von ESP-IDF
``components/nvs_flash/nvs_partition_generator/nvs_partition_gen.py`` (Apache-2.0,
Espressif), reduziert auf das, was wir brauchen: NVS-Format V2, Namespace-Eintrag (U8)
und String-Einträge (Typ SZ). Verschlüsselung, BLOBs, Multipage und übrige Primitivtypen
sind bewusst entfernt.

Korrektheits-Gate: ``tests/test_nvs.py`` vergleicht die Ausgabe byte-genau gegen das
offizielle esp-idf-Tool (sofern installiert) UND prüft alle CRCs unabhängig via zlib.

EHRLICHE GRENZE: Dass der ESP das NVS am Ende *liest*, ist erst mit der NVS-fähigen
Reader-Firmware (Phase D / PN532-Migration) verifizierbar. Bis dahin: Read-back via
esptool + Re-Parse (struktur-/CRC-konsistent, nicht geräte-autoritativ).
"""

from __future__ import annotations

import struct
import zlib

# --- Format-Konstanten (ESP-IDF NVS) ---
PAGE_SIZE = 4096
HEADER_SIZE = 32
BITMAP_OFFSET = 32
BITMAP_SIZE = 32
FIRST_ENTRY_OFFSET = 64
ENTRY_SIZE = 32
MAX_ENTRIES = 126

# Page-States
STATE_ACTIVE = 0xFFFFFFFE
STATE_FULL = 0xFFFFFFFC

# Page-Version
VERSION2 = 0xFE

# Entry-Typen
TYPE_U8 = 0x01
TYPE_SZ = 0x21
CHUNK_ANY = 0xFF

# String-Limit (NVS V2): max_blob_size für Strings
MAX_STRING_LEN = 4000


class NvsError(Exception):
    """Fehler bei der NVS-Generierung (z.B. Key zu lang, Daten zu groß)."""


def _crc32(data: bytes) -> int:
    """ESP-IDF-CRC: zlib.crc32 mit Init 0xFFFFFFFF (kein finales XOR über das hinaus)."""
    return zlib.crc32(data, 0xFFFFFFFF) & 0xFFFFFFFF


class _Page:
    """Eine 4096-Byte-NVS-Page (Header + Entry-State-Bitmap + Entries)."""

    def __init__(self, page_num: int) -> None:
        self.entry_num = 0
        self.buf = bytearray(b"\xff" * PAGE_SIZE)
        self.bitmap = bytearray(b"\xff" * BITMAP_SIZE)
        self._set_header(page_num)

    def _set_header(self, page_num: int) -> None:
        header = bytearray(b"\xff" * HEADER_SIZE)
        struct.pack_into("<I", header, 0, STATE_ACTIVE)
        struct.pack_into("<I", header, 4, page_num)
        header[8] = VERSION2
        crc = _crc32(bytes(header[4:28]))
        struct.pack_into("<I", header, 28, crc)
        self.buf[0:HEADER_SIZE] = header

    def mark_full(self) -> None:
        struct.pack_into("<I", self.buf, 0, STATE_FULL)

    def _write_bitmap(self) -> None:
        bitnum = self.entry_num * 2
        byte_idx = bitnum // 8
        bit_offset = bitnum & 7
        self.bitmap[byte_idx] &= ~(1 << bit_offset) & 0xFF
        self.buf[BITMAP_OFFSET:BITMAP_OFFSET + BITMAP_SIZE] = self.bitmap

    def _write_entries(self, data: bytes, entry_count: int) -> None:
        offset = FIRST_ENTRY_OFFSET + ENTRY_SIZE * self.entry_num
        self.buf[offset:offset + len(data)] = data
        for _ in range(entry_count):
            self._write_bitmap()
            self.entry_num += 1

    @staticmethod
    def _set_entry_crc(entry: bytearray) -> None:
        crc_data = bytearray(28)
        crc_data[0:4] = entry[0:4]
        crc_data[4:28] = entry[8:32]
        struct.pack_into("<I", entry, 4, _crc32(bytes(crc_data)))

    def free_entries(self) -> int:
        return MAX_ENTRIES - self.entry_num

    def write_namespace(self, name: str, ns_index: int) -> None:
        """Namespace-Definitions-Eintrag (ns_index=0, Typ U8, data=zugewiesener Index)."""
        entry = bytearray(b"\xff" * ENTRY_SIZE)
        entry[0] = 0  # ns_index 0 für Namespace-Definitionen
        entry[1] = TYPE_U8
        entry[2] = 0x01  # span
        entry[3] = CHUNK_ANY
        self._set_key(entry, name)
        struct.pack_into("<B", entry, 24, ns_index)
        self._set_entry_crc(entry)
        self._write_entries(bytes(entry), 1)

    def write_string(self, key: str, value: str, ns_index: int) -> None:
        """String-Eintrag (Typ SZ). value wird mit '\\0' terminiert (wie esp-idf)."""
        data = value.encode("utf-8") + b"\x00"
        datalen = len(data)
        if datalen > MAX_STRING_LEN:
            raise NvsError(f"String '{key}' zu lang: {datalen} > {MAX_STRING_LEN}")

        rounded = (datalen + 31) & ~31
        data_entry_count = rounded // 32
        total_entry_count = data_entry_count + 1  # +1 Header-Entry

        entry = bytearray(b"\xff" * ENTRY_SIZE)
        entry[0] = ns_index
        entry[1] = TYPE_SZ
        entry[2] = total_entry_count  # span inkl. Header
        entry[3] = CHUNK_ANY
        self._set_key(entry, key)
        struct.pack_into("<H", entry, 24, datalen)  # data size @24 (2 Byte)
        struct.pack_into("<I", entry, 28, _crc32(data))  # data crc @28 (4 Byte)
        self._set_entry_crc(entry)

        data_padded = data + b"\xff" * (rounded - datalen)
        self._write_entries(bytes(entry), 1)
        self._write_entries(bytes(data_padded), data_entry_count)

    @staticmethod
    def _set_key(entry: bytearray, key: str) -> None:
        key_bytes = key.encode("utf-8")
        if len(key_bytes) > 15:
            raise NvsError(f"NVS-Key '{key}' > 15 Bytes (max 15 + Nullbyte).")
        entry[8:24] = b"\x00" * 16
        entry[8:8 + len(key_bytes)] = key_bytes

    def get_data(self) -> bytes:
        return bytes(self.buf)


def generate_nvs_partition(
    entries: dict[str, str],
    namespace: str,
    size: int,
) -> bytes:
    """Erzeugt ein NVS-Partitions-Binary (genau ``size`` Bytes) mit String-Entries.

    Args:
        entries:   Key→Wert (alle Strings). Keys max 15 Byte.
        namespace: NVS-Namespace (z.B. "spotfam"). Max 15 Byte.
        size:      Partitionsgröße in Bytes (Vielfaches von 4096, >= 3 Pages).

    Returns:
        Binary der Länge ``size``: aktive Page(s) mit Daten, Rest 0xFF (freie Pages).

    Raises:
        NvsError: Bei ungültiger Größe, zu langen Keys/Werten oder Page-Overflow.
    """
    if size <= 0 or size % PAGE_SIZE != 0:
        raise NvsError(f"NVS-Größe muss positives Vielfaches von {PAGE_SIZE} sein: {size}")
    max_pages = size // PAGE_SIZE
    if max_pages < 3:
        raise NvsError(f"NVS-Partition braucht >= 3 Pages, hat {max_pages}.")
    if not namespace:
        raise NvsError("Namespace darf nicht leer sein.")

    pages: list[_Page] = [_Page(0)]

    def cur() -> _Page:
        return pages[-1]

    def ensure_space(needed_entries: int) -> None:
        if cur().free_entries() < needed_entries:
            if len(pages) >= max_pages - 1:  # eine Page für freien Reserve-Bereich lassen
                raise NvsError("NVS-Daten passen nicht in die Partition (zu viele Pages).")
            cur().mark_full()
            pages.append(_Page(len(pages)))

    # Namespace-Eintrag (1 Entry), dann String-Einträge.
    ns_index = 1
    ensure_space(1)
    cur().write_namespace(namespace, ns_index)

    for key, value in entries.items():
        data = value.encode("utf-8") + b"\x00"
        rounded = (len(data) + 31) & ~31
        needed = rounded // 32 + 1
        if needed > MAX_ENTRIES:
            raise NvsError(f"Wert für '{key}' passt nicht in eine Page.")
        ensure_space(needed)
        cur().write_string(key, value, ns_index)

    # Pages zu genau `size` Bytes mit 0xFF (freie/uninitialisierte Pages) auffüllen.
    out = bytearray()
    for page in pages:
        out += page.get_data()
    out += b"\xff" * (size - len(out))
    return bytes(out)


def parse_nvs_partition(data: bytes, namespace: str) -> dict[str, str]:
    """Liest String-Key-Value-Paare eines Namespaces aus einem NVS-Binary.

    Bewusst tolerant und auf das beschränkt, was ``generate_nvs_partition`` schreibt
    (Namespace-Eintrag + String-Entries). Dient dem Read-back-Verify nach dem Flash
    und prüft dabei Header-/Entry-/Daten-CRCs.

    Raises:
        NvsError: Bei CRC-Fehler oder strukturell unlesbarem Binary.
    """
    result: dict[str, str] = {}
    ns_name_by_index: dict[int, str] = {}
    target_ns_index: int | None = None

    for page_start in range(0, len(data), PAGE_SIZE):
        page = data[page_start:page_start + PAGE_SIZE]
        if len(page) < PAGE_SIZE:
            break
        state = struct.unpack_from("<I", page, 0)[0]
        if state not in (STATE_ACTIVE, STATE_FULL):
            continue  # uninitialisierte/freie Page überspringen

        header_crc = struct.unpack_from("<I", page, 28)[0]
        if _crc32(bytes(page[4:28])) != header_crc:
            raise NvsError(f"Page-Header-CRC fehlerhaft @0x{page_start:x}.")

        i = 0
        while i < MAX_ENTRIES:
            off = FIRST_ENTRY_OFFSET + i * ENTRY_SIZE
            entry = page[off:off + ENTRY_SIZE]
            ns_index = entry[0]
            etype = entry[1]
            span = entry[2]
            if ns_index == 0xFF or span == 0xFF or span == 0:
                i += 1
                continue

            crc_data = bytes(entry[0:4]) + bytes(entry[8:32])
            if _crc32(crc_data) != struct.unpack_from("<I", entry, 4)[0]:
                raise NvsError(f"Entry-CRC fehlerhaft @0x{page_start + off:x}.")

            key = entry[8:24].split(b"\x00", 1)[0].decode("utf-8", "replace")

            if etype == TYPE_U8 and ns_index == 0:
                ns_name_by_index[entry[24]] = key
                if key == namespace:
                    target_ns_index = entry[24]
                i += 1
            elif etype == TYPE_SZ:
                datalen = struct.unpack_from("<H", entry, 24)[0]
                data_crc = struct.unpack_from("<I", entry, 28)[0]
                data_off = off + ENTRY_SIZE
                raw = page[data_off:data_off + datalen]
                if _crc32(bytes(raw)) != data_crc:
                    raise NvsError(f"Daten-CRC fehlerhaft für Key '{key}'.")
                value = raw.rstrip(b"\x00").decode("utf-8", "replace")
                if ns_index == target_ns_index:
                    result[key] = value
                i += span
            else:
                i += span if span else 1

    return result
