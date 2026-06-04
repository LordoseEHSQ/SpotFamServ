"""SpotFam Flash-Agent – ESP32-Provisioning-Dienst.

Erkennt angeschlossene ESP32-Geraete, fragt das SpotFam-Backend nach
ausstehenden Flash-Jobs und flasht die Firmware per esptool v5.

Entscheidungs-Bezug: D-024 (Auth via FLASH_AGENT_API_KEY),
D-025 (Artefakt-Transfer lokal + sha256 + Chip-Match).
"""

__version__ = "0.1.0"
