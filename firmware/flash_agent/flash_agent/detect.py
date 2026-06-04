"""Port-Discovery: findet angeschlossene USB-Seriel-Geraete.

``pyserial`` (``serial.tools.list_ports``) ist ein optionaler Import –
das Modul bleibt per ``py_compile`` pruefbar und in Tests ohne Hardware
verwendbar.
"""

from __future__ import annotations

import logging

log = logging.getLogger("spotfam.flash_agent.detect")

# VID:PID-Paare gaengiger ESP32-USB-Bruecken.
# Quelle: Silicon Labs CP2102 (10C4:EA60), WCH CH340 (1A86:7523),
#         FTDI FT232 (0403:6001), CH9102 (1A86:55D4).
_KNOWN_USB_SERIAL_IDS: set[tuple[int, int]] = {
    (0x10C4, 0xEA60),  # Silicon Labs CP2102/CP2104
    (0x10C4, 0xEA61),  # Silicon Labs CP2102N
    (0x1A86, 0x7523),  # WCH CH340
    (0x1A86, 0x55D4),  # WCH CH9102
    (0x0403, 0x6001),  # FTDI FT232RL
    (0x0403, 0x6010),  # FTDI FT2232
    (0x0403, 0x6011),  # FTDI FT4232
    (0x303A, 0x1001),  # Espressif USB JTAG/Serial (ESP32-S3/C3 built-in)
}


def list_candidate_ports(filter_known: bool = True) -> list[str]:
    """Gibt eine Liste von Kandidaten-Ports zurueck.

    Filtert optional nach bekannten USB-Serial-VID:PID-Paaren (CP210x, CH340,
    FTDI, Espressif built-in). Wenn keine bekannten Geraete gefunden werden,
    werden alle seriellen Ports zurueckgegeben (als Fallback).

    Args:
        filter_known: Wenn ``True``, werden nur bekannte VID:PID zurueckgegeben.
                      Bei ``False`` alle verfuegbaren Ports.

    Returns:
        Liste von Port-Pfaden (z.B. ``["/dev/ttyUSB0", "/dev/ttyUSB1"]``).
    """
    try:
        from serial.tools import list_ports  # type: ignore[import-not-found]
    except ImportError:
        log.warning(
            "pyserial nicht installiert; Port-Discovery nicht verfuegbar. "
            "Bitte 'pip install pyserial' ausfuehren."
        )
        return []

    all_ports = list_ports.comports()

    if not filter_known:
        result = [p.device for p in all_ports]
        log.debug("Alle Ports: %s", result)
        return result

    known = [
        p.device
        for p in all_ports
        if (p.vid, p.pid) in _KNOWN_USB_SERIAL_IDS
    ]

    if known:
        log.debug("Bekannte ESP32-USB-Serial-Ports: %s", known)
        return known

    # Fallback: alle Ports, die "USB" im Namen tragen.
    fallback = [
        p.device
        for p in all_ports
        if p.device and ("USB" in p.device.upper() or "ACM" in p.device.upper())
    ]
    if fallback:
        log.debug("Fallback-Ports (USB/ACM): %s", fallback)
        return fallback

    log.debug("Keine Kandidaten-Ports gefunden.")
    return []
