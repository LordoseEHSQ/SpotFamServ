"""CLI-Subcommands fuer den Flash-Agent.

Subcommands:
  detect          Listet Ports und erkennt Chips (kein Backend).
  flash           Resolve + sha256 + Chip-Match + Flash (oder --dry-run).
  run             Dauerhafte Hauptschleife gegen das Backend.

Verwendung:
  python -m flash_agent detect
  python -m flash_agent flash --port /dev/ttyUSB0 --artifact merged.bin \\
                               --expected-chip ESP32-D0WD-V3 [--dry-run]
  python -m flash_agent run
"""

from __future__ import annotations

import argparse
import logging
import os
import sys


def _setup_logging(level: str = "INFO") -> None:
    logging.basicConfig(
        level=level.upper(),
        format="%(asctime)s %(levelname)s %(name)s: %(message)s",
        stream=sys.stdout,
    )


def cmd_detect(args: argparse.Namespace) -> int:
    """Listet verfuegbare Ports und erkennt Chips ohne Backend-Zugriff."""
    _setup_logging(os.environ.get("LOG_LEVEL", "WARNING"))

    from flash_agent.detect import list_candidate_ports
    from flash_agent.esptool_runner import EsptoolError, detect_chip

    esptool_bin = os.environ.get("ESPTOOL_BIN", "esptool")

    ports = list_candidate_ports(filter_known=not args.all_ports)
    if not ports:
        print("Keine Kandidaten-Ports gefunden.")
        return 0

    for port in ports:
        print(f"\nPort: {port}")
        try:
            info = detect_chip(port, esptool_bin=esptool_bin)
            print(f"  Chip:        {info.chip_description}")
            print(f"  Familie:     {info.chip}")
            print(f"  MAC:         {info.mac}")
            print(f"  Flash:       {info.flash_size}")
        except EsptoolError as exc:
            print(f"  Fehler: {exc}")

    return 0


def cmd_flash(args: argparse.Namespace) -> int:
    """Loest Artefakt auf, prueft sha256 + Chip-Match und flasht (oder dry-run)."""
    _setup_logging(os.environ.get("LOG_LEVEL", "INFO"))

    from flash_agent.artifacts import ArtifactError, resolve, verify_sha256
    from flash_agent.esptool_runner import EsptoolError, flash
    from flash_agent.variants import is_supported, matches

    firmware_dir = args.firmware_dir or os.environ.get("FIRMWARE_DIR", ".")
    esptool_bin = os.environ.get("ESPTOOL_BIN", "esptool")
    baud = int(os.environ.get("FLASH_BAUD", "460800"))

    # --- Artefakt aufloesen ---
    try:
        image_path = resolve(args.artifact, firmware_dir)
    except (ArtifactError, FileNotFoundError) as exc:
        print(f"Fehler: {exc}", file=sys.stderr)
        return 1

    # --- sha256 pruefen (nur wenn angegeben) ---
    if args.sha256:
        if not verify_sha256(image_path, args.sha256):
            print(
                f"Fehler: sha256-Mismatch fuer {image_path}. Abbruch.",
                file=sys.stderr,
            )
            return 1
        print(f"sha256 OK: {args.sha256[:16]}...")

    # --- Chip-Match pruefen ---
    if not is_supported(args.expected_chip):
        print(
            f"Fehler: Chip '{args.expected_chip}' nicht in der Whitelist.",
            file=sys.stderr,
        )
        return 1

    # Bei --dry-run: esptool-Kommando ausgeben, NICHT ausfuehren.
    flash_cmd = [
        esptool_bin,
        "--port", args.port,
        "--baud", str(baud),
        "write-flash",
        "0x0",
        str(image_path),
    ]

    if args.dry_run:
        print("Dry-run – wuerde ausfuehren:")
        print("  " + " ".join(flash_cmd))
        return 0

    # --- Flash ausfuehren ---
    try:
        flash(
            port=args.port,
            image_path=image_path,
            baud=baud,
            esptool_bin=esptool_bin,
            progress_cb=lambda p: print(f"  Fortschritt: {p}%", flush=True),
        )
    except EsptoolError as exc:
        print(f"Flash-Fehler: {exc}", file=sys.stderr)
        return 1

    print("Flash erfolgreich.")
    return 0


def cmd_run(args: argparse.Namespace) -> int:
    """Startet die dauerhafte Hauptschleife gegen das Backend."""
    from flash_agent.agent import run
    from flash_agent.config import Config

    config = Config.from_env()
    _setup_logging(config.log_level)
    try:
        run(config)
    except KeyboardInterrupt:
        print("\nBeendet.")
    return 0


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        prog="flash_agent",
        description="SpotFam Flash-Agent – ESP32-Provisioning-Werkzeug.",
    )
    sub = parser.add_subparsers(dest="command", required=True)

    # --- detect ---
    p_detect = sub.add_parser("detect", help="Ports erkennen und Chip lesen.")
    p_detect.add_argument(
        "--all-ports",
        action="store_true",
        help="Alle seriellen Ports zeigen, nicht nur bekannte VID:PID.",
    )
    p_detect.set_defaults(func=cmd_detect)

    # --- flash ---
    p_flash = sub.add_parser(
        "flash",
        help="Firmware flashen (oder --dry-run fuer Vorschau).",
    )
    p_flash.add_argument("--port", required=True, help="Serieller Port (z.B. /dev/ttyUSB0).")
    p_flash.add_argument(
        "--artifact",
        required=True,
        help="Dateiname des Firmware-Artefakts (relativ zu FIRMWARE_DIR).",
    )
    p_flash.add_argument(
        "--expected-chip",
        required=True,
        help="Erwartete Chip-Bezeichnung (z.B. ESP32-D0WD-V3).",
    )
    p_flash.add_argument(
        "--sha256",
        default=None,
        help="Erwarteter sha256-Hash der Artefakt-Datei (optional, aber empfohlen).",
    )
    p_flash.add_argument(
        "--firmware-dir",
        default=None,
        help="Verzeichnis mit Firmware-Artefakten (Standard: $FIRMWARE_DIR oder '.').",
    )
    p_flash.add_argument(
        "--dry-run",
        action="store_true",
        help="Nur esptool-Befehl ausgeben, NICHT ausfuehren.",
    )
    p_flash.set_defaults(func=cmd_flash)

    # --- run ---
    p_run = sub.add_parser("run", help="Dauerhafte Hauptschleife gegen das Backend.")
    p_run.set_defaults(func=cmd_run)

    return parser


def main() -> int:
    parser = build_parser()
    args = parser.parse_args()
    return args.func(args)
