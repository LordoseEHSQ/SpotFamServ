"""Sichere Artefakt-Aufloesung und sha256-Verifikation.

Sicherheitsprinzipien (D-025):
- Filename-Validierung: kein ``/``, kein ``..``, kein Null-Byte.
- realpath-Kindcheck: ``resolve(firmware_dir / filename)`` muss
  direktes Kind von ``firmware_dir`` sein.
- sha256-Pruefung VOR dem Flash; Mismatch → Abbruch.
"""

from __future__ import annotations

import hashlib
import logging
from pathlib import Path

log = logging.getLogger("spotfam.flash_agent.artifacts")


class ArtifactError(Exception):
    """Wird geworfen wenn ein Artefakt ungueltig oder nicht vertrauenswuerdig ist."""


def resolve(filename: str, firmware_dir: str | Path) -> Path:
    """Loest einen Dateinamen gegen ``firmware_dir`` auf.

    Prueft:
    1. Kein ``/`` im Dateinamen (kein Pfad-Separator).
    2. Kein ``..`` (keine Verzeichnis-Traversal).
    3. Kein Null-Byte.
    4. realpath des Ergebnisses muss direktes Kind von ``firmware_dir`` sein.

    Args:
        filename:     Relativer Dateiname aus dem Job-Artefakt (z.B. ``"merged.bin"``).
        firmware_dir: Lokales Verzeichnis, in dem Firmware-Artefakte liegen.

    Returns:
        Absoluter, verifizierter :class:`~pathlib.Path` zur Artefakt-Datei.

    Raises:
        ArtifactError: Bei Validierungsfehler oder Path-Traversal.
        FileNotFoundError: Wenn die Datei nicht existiert.
    """
    # Null-Byte-Check (verhindert C-Ebene-Tricks).
    if "\x00" in filename:
        raise ArtifactError("Ungueltige Artefakt-Datei: Null-Byte im Dateinamen.")

    # Kein Pfad-Separator erlaubt (weder Unix noch Windows).
    if "/" in filename or "\\" in filename:
        raise ArtifactError(
            f"Ungueltige Artefakt-Datei: Pfad-Separator im Namen: {filename!r}"
        )

    # Kein `..` erlaubt.
    if ".." in filename.split("/"):
        raise ArtifactError(
            f"Ungueltige Artefakt-Datei: '..' im Namen: {filename!r}"
        )
    # Nochmal als expliziter String-Check.
    if ".." in filename:
        raise ArtifactError(
            f"Ungueltige Artefakt-Datei: '..' Sequenz im Namen: {filename!r}"
        )

    firmware_path = Path(firmware_dir).resolve()
    candidate = (firmware_path / filename).resolve()

    # realpath-Kindcheck: candidate muss direktes Kind von firmware_path sein.
    # ``candidate.parent`` (nicht nur startswith) verhindert z.B.
    # ``/firmware_dir_extra/file`` wenn firmware_dir ist ``/firmware_dir``.
    if candidate.parent != firmware_path:
        raise ArtifactError(
            f"Pfad-Traversal-Versuch: {filename!r} zeigt ausserhalb von "
            f"{firmware_path}"
        )

    if not candidate.exists():
        raise FileNotFoundError(
            f"Artefakt nicht gefunden: {candidate}"
        )

    log.debug("Artefakt aufgeloest: %s", candidate)
    return candidate


def verify_sha256(path: Path, expected_hex: str) -> bool:
    """Prueft den sha256-Hash einer Datei gegen den erwarteten Hex-String.

    Args:
        path:         Pfad zur Datei.
        expected_hex: Erwarteter sha256-Hash als Hex-String (64 Zeichen).

    Returns:
        ``True`` wenn der Hash uebereinstimmt, sonst ``False``.
    """
    h = hashlib.sha256()
    with open(path, "rb") as f:
        for chunk in iter(lambda: f.read(65536), b""):
            h.update(chunk)
    actual = h.hexdigest().lower()
    expected = expected_hex.strip().lower()
    match = actual == expected
    if not match:
        log.error(
            "sha256-Mismatch fuer %s: erwartet=%s tatsaechlich=%s",
            path,
            expected,
            actual,
        )
    return match
