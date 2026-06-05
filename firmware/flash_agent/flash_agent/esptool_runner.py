"""esptool-Subprozess-Wrapper fuer den Flash-Agent.

Kapselt alle esptool-Aufrufe als Argument-Arrays (KEIN ``shell=True``,
KEIN String-Kommando) → keine Command-Injection moeglich.

Unterstuetzt esptool v5.x (Subcommands mit Bindestrich: ``chip-id``,
``flash-id``, ``write-flash``).
"""

from __future__ import annotations

import logging
import re
import subprocess
from dataclasses import dataclass
from pathlib import Path
from typing import Callable

log = logging.getLogger("spotfam.flash_agent.esptool_runner")


@dataclass
class ChipInfo:
    """Informationen ueber den erkannten ESP32-Chip."""

    chip: str             # Family-Bezeichner, z.B. "esp32"
    chip_description: str # Vollstaendige Bezeichnung, z.B. "ESP32-D0WD-V3"
    mac: str              # MAC-Adresse, z.B. "78:EE:4C:01:6B:04"
    flash_size: str       # Flash-Groesse, z.B. "4MB"


class EsptoolError(Exception):
    """Wird geworfen wenn esptool mit Fehler beendet oder Parsing fehlschlaegt."""


def _run_esptool(args: list[str], timeout: int = 30) -> str:
    """Fuehrt esptool als Subprozess aus und gibt stdout+stderr zurueck.

    Argument-Array garantiert injektionssichere Ausfuehrung.

    Args:
        args:    Vollstaendige Kommandoliste, z.B.
                 ``["esptool", "--port", "/dev/ttyUSB0", "chip-id"]``.
        timeout: Timeout in Sekunden.

    Returns:
        Kombinierte Ausgabe (stdout + stderr) als String.

    Raises:
        EsptoolError: Bei Timeout, Prozess-Fehler oder Nicht-0-Exit.
    """
    log.debug("esptool Aufruf: %s", args)
    try:
        result = subprocess.run(
            args,
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            timeout=timeout,
            check=False,  # Wir pruefen returncode manuell, um Ausgabe zu loggen.
        )
    except FileNotFoundError:
        raise EsptoolError(
            f"esptool-Binaer nicht gefunden: {args[0]!r}. "
            "Bitte esptool installieren oder ESPTOOL_BIN setzen."
        )
    except subprocess.TimeoutExpired:
        raise EsptoolError(
            f"esptool Timeout nach {timeout}s: {' '.join(args)}"
        )

    output = result.stdout.decode("utf-8", errors="replace")
    log.debug("esptool Ausgabe (exit=%d):\n%s", result.returncode, output)

    if result.returncode != 0:
        raise EsptoolError(
            f"esptool beendet mit Exit-Code {result.returncode}:\n{output}"
        )
    return output


def _parse_chip_description(output: str) -> str:
    """Extrahiert die Chip-Bezeichnung aus ``chip-id``-Ausgabe.

    Unterstuetzt beide esptool-Formate:
    - esptool v5.3.x: ``Chip type:          ESP32-D0WD-V3 (revision v3.1)``
    - esptool <=v5.2 / legacy: ``Chip is ESP32-D0WD-V3 (revision v3.1)``

    Das Revision-Suffix in Klammern wird abgeschnitten. Die Zeile
    ``Detecting chip type... ESP32`` matcht bewusst NICHT (kein Doppelpunkt
    direkt nach ``type``), damit nicht die unvollstaendige Familienbezeichnung
    statt der vollen Chip-Bezeichnung gewonnen wird.
    """
    m = re.search(
        r"(?i)chip (?:type:\s*|is\s+)([^\(\n]+?)(?:\s*\(|\s*$)",
        output,
        re.MULTILINE,
    )
    if m:
        return m.group(1).strip()
    raise EsptoolError(
        "Chip-Bezeichnung nicht in esptool-Ausgabe gefunden.\n"
        f"Ausgabe:\n{output}"
    )


def _parse_mac(output: str) -> str:
    """Extrahiert die MAC-Adresse aus esptool-Ausgabe.

    Format: ``MAC: 78:ee:4c:01:6b:04`` → ``"78:EE:4C:01:6B:04"``
    """
    m = re.search(r"(?i)\bMAC:\s*([0-9a-f]{2}(?::[0-9a-f]{2}){5})\b", output)
    if m:
        return m.group(1).upper()
    raise EsptoolError(
        "MAC-Adresse nicht in esptool-Ausgabe gefunden.\n"
        f"Ausgabe:\n{output}"
    )


def _parse_flash_size(output: str) -> str:
    """Extrahiert die Flash-Groesse aus ``flash-id``-Ausgabe.

    Format: ``Detected flash size: 4MB``
    """
    m = re.search(r"(?i)detected flash size:\s*(\S+)", output)
    if m:
        return m.group(1)
    # Fallback: Flash-Groesse manchmal auch als "Auto-detected Flash size: 4MB"
    m = re.search(r"(?i)flash size[:\s]+(\d+\s*MB)", output)
    if m:
        return m.group(1).replace(" ", "")
    # Gar nichts gefunden – kein harter Fehler, "unknown" als Fallback.
    log.warning("Flash-Groesse nicht erkannt; verwende 'unknown'.")
    return "unknown"


def detect_chip(port: str, esptool_bin: str = "esptool") -> ChipInfo:
    """Erkennt Chip-Typ, MAC und Flash-Groesse am seriellen Port.

    Fuehrt ``chip-id`` und ``flash-id`` als separate esptool-Aufrufe aus
    und parsed deren Ausgaben robust.

    Args:
        port:       Serieller Port, z.B. ``"/dev/ttyUSB0"``.
        esptool_bin: Pfad oder Name des esptool-Binaers.

    Returns:
        :class:`ChipInfo` mit Chip-Bezeichnung, MAC und Flash-Groesse.

    Raises:
        EsptoolError: Bei Subprozess-Fehler oder Parse-Fehler.
    """
    # chip-id: Chip-Bezeichnung + MAC.
    chip_output = _run_esptool(
        [esptool_bin, "--port", port, "chip-id"],
        timeout=20,
    )
    chip_description = _parse_chip_description(chip_output)
    mac = _parse_mac(chip_output)

    # flash-id: Flash-Groesse.
    flash_output = _run_esptool(
        [esptool_bin, "--port", port, "flash-id"],
        timeout=20,
    )
    flash_size = _parse_flash_size(flash_output)

    # Family-Bezeichner aus Whitelist (lazy import vermeidet Zirkularitaet).
    from flash_agent.variants import chip_family
    family = chip_family(chip_description) or "unknown"

    info = ChipInfo(
        chip=family,
        chip_description=chip_description,
        mac=mac,
        flash_size=flash_size,
    )
    log.info(
        "Chip erkannt: port=%s chip=%s mac=%s flash=%s",
        port,
        chip_description,
        mac,
        flash_size,
    )
    return info


def flash(
    port: str,
    image_path: Path,
    baud: int = 460800,
    esptool_bin: str = "esptool",
    progress_cb: Callable[[int], None] | None = None,
) -> None:
    """Flasht eine Firmware-Datei auf den ESP32.

    Verwendet ``write-flash 0x0`` (Merged-Binary). esptool verifiziert den
    Flash-Hash am Ende automatisch.

    Subprozess als Argument-Array → keine Injection moeglich.

    Args:
        port:        Serieller Port.
        image_path:  Absoluter Pfad zur ``merged.bin``-Datei.
        baud:        Baud-Rate (Standard: 460800).
        esptool_bin: Pfad oder Name des esptool-Binaers.
        progress_cb: Optionaler Callback ``f(percent: int)``; wird bei jedem
                     erkannten Fortschritts-Prozent aus der esptool-Ausgabe
                     aufgerufen (0–100).

    Raises:
        EsptoolError: Bei Subprozess-Fehler oder Flash-Fehler.
    """
    cmd = [
        esptool_bin,
        "--port", port,
        "--baud", str(baud),
        "write-flash",
        "0x0",
        str(image_path),
    ]
    log.info("Flash-Befehl: %s", " ".join(cmd))

    try:
        proc = subprocess.Popen(
            cmd,
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
        )
    except FileNotFoundError:
        raise EsptoolError(
            f"esptool-Binaer nicht gefunden: {esptool_bin!r}."
        )

    output_lines: list[str] = []
    assert proc.stdout is not None

    for raw_line in proc.stdout:
        line = raw_line.decode("utf-8", errors="replace").rstrip()
        output_lines.append(line)
        log.debug("esptool: %s", line)

        if progress_cb is not None:
            # Zeilen wie "Writing at 0x00020000... (20 %)"
            m = re.search(r"\(\s*(\d+)\s*%\s*\)", line)
            if m:
                try:
                    progress_cb(int(m.group(1)))
                except Exception:
                    pass

    proc.wait()
    full_output = "\n".join(output_lines)

    if proc.returncode != 0:
        raise EsptoolError(
            f"Flash fehlgeschlagen (exit={proc.returncode}):\n{full_output}"
        )

    log.info("Flash erfolgreich abgeschlossen: port=%s image=%s", port, image_path)


def flash_at_offset(
    port: str,
    image_path: Path,
    offset: int,
    baud: int = 460800,
    esptool_bin: str = "esptool",
) -> None:
    """Schreibt eine Binärdatei an einen bestimmten Flash-Offset (z.B. NVS @0x9000).

    Subprozess als Argument-Array → keine Injection. esptool verifiziert den Hash.

    Raises:
        EsptoolError: Bei Subprozess- oder Flash-Fehler.
    """
    _run_esptool(
        [
            esptool_bin,
            "--port", port,
            "--baud", str(baud),
            "write-flash",
            hex(offset),
            str(image_path),
        ],
        timeout=60,
    )
    log.info("Flash @%s erfolgreich: port=%s image=%s", hex(offset), port, image_path)


def read_flash(
    port: str,
    offset: int,
    size: int,
    out_path: Path,
    baud: int = 460800,
    esptool_bin: str = "esptool",
) -> bytes:
    """Liest ``size`` Bytes ab ``offset`` aus dem Flash und gibt sie zurück (Read-back-Verify).

    Raises:
        EsptoolError: Bei Subprozess-Fehler oder wenn die Datei nicht gelesen werden kann.
    """
    _run_esptool(
        [
            esptool_bin,
            "--port", port,
            "--baud", str(baud),
            "read-flash",
            hex(offset),
            hex(size),
            str(out_path),
        ],
        timeout=60,
    )
    try:
        return out_path.read_bytes()
    except OSError as exc:
        raise EsptoolError(f"Read-back-Datei nicht lesbar: {exc}")
