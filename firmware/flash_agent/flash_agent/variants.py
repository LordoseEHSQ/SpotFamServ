"""Chip-Varianten-Whitelist fuer den Flash-Agent.

Mappt erkannte ``chip_description``-Strings (aus esptool-Ausgabe) auf
Board-Familien. Unbekannte oder nicht unterstuetzte Chips werden
VERWEIGERT – kein Raten, kein Flash ins Ungewisse.

Erweiterung: neue Chips in ``_CHIP_FAMILIES`` eintragen.
"""

from __future__ import annotations

import re


# Whitelist: Regex-Pattern → Family-Bezeichner.
# Muster gegen chip_description (Gross/Klein ignoriert) pruefen.
# Erste Uebereinstimmung gewinnt.
_CHIP_FAMILIES: list[tuple[re.Pattern[str], str]] = [
    # Klassischer WROOM-32 (D0WD und D0WD-V3 sind dieselbe Plattform).
    (re.compile(r"ESP32-D0WD", re.IGNORECASE), "esp32"),
    # WROOM-32E / WROOM-32UE (D0WDR2-V3).
    (re.compile(r"ESP32-D0WDR2", re.IGNORECASE), "esp32"),
    # S-Series (Platzhalter fuer kuenftige Erweiterung, noch NICHT unterstuetzt).
    # (re.compile(r"ESP32-S3", re.IGNORECASE), "esp32s3"),
]


def chip_family(chip_description: str) -> str | None:
    """Gibt die Familie des Chips zurueck, oder ``None`` wenn unbekannt.

    Args:
        chip_description: Vollstaendige Chip-Bezeichnung wie von esptool
                          gemeldet, z.B. ``"ESP32-D0WD-V3"``.

    Returns:
        Familienbezeichner (z.B. ``"esp32"``) oder ``None``.
    """
    for pattern, family in _CHIP_FAMILIES:
        if pattern.search(chip_description):
            return family
    return None


def is_supported(chip_description: str) -> bool:
    """Gibt ``True`` zurueck, wenn der Chip in der Whitelist steht.

    Unbekannte/nicht unterstuetzte Chips → ``False`` → Flash VERWEIGERN.
    """
    return chip_family(chip_description) is not None


def matches(expected: str, actual: str) -> bool:
    """Prueft ob ``actual`` zum erwarteten Chip ``expected`` passt.

    Vergleich ist robuster Substring/Pattern-Match (nicht exakter String-
    Vergleich), damit Revisions-Suffixe (``,  revision v3.1``) nicht zu
    falschen Ablehnungen fuehren.

    Beide Seiten werden aber zuerst gegen die Whitelist geprueft –
    ein unbekannter ``expected``-String fuer ein zu flashendes Artefakt
    schlaegt immer fehl.

    Beispiele:
        matches("ESP32-D0WD-V3", "ESP32-D0WD-V3")  → True
        matches("ESP32-D0WD",    "ESP32-D0WD-V3")  → True  (Subset-Match)
        matches("ESP32-D0WD-V3", "ESP32-S3")       → False
        matches("ESP32-S3",      "ESP32-D0WD-V3")  → False

    Args:
        expected: ``expectedChip`` aus dem Job-Artefakt.
        actual:   ``chip_description`` vom erkannten Geraet.

    Returns:
        ``True`` bei Uebereinstimmung, sonst ``False``.
    """
    # Beide muessen in der Whitelist sein.
    fam_expected = chip_family(expected)
    fam_actual = chip_family(actual)
    if fam_expected is None or fam_actual is None:
        return False

    # Gleiche Familie reicht fuer den Basis-Match.
    if fam_expected != fam_actual:
        return False

    # Zusaetzliche Praezision: erwartet als Praefix des tatsaechlichen Strings
    # (case-insensitiv, ohne Leerzeichen), damit ein fuer WROOM-32 gebautes
    # Artefakt nicht auf einem D0WDR2 landet, falls beide in derselben Familie
    # waren.  Im aktuellen Stand sind beide in "esp32" → dieser Check faengt
    # kuenftige Feingliederung ab.
    actual_norm = actual.strip().upper()
    expected_norm = expected.strip().upper()
    return actual_norm.startswith(expected_norm) or expected_norm.startswith(actual_norm)
