#!/usr/bin/env python3
"""
Erzeugt aus design.py:
  ../spotfam-reader.kicad_sch   Schaltplan (Netze ueber Labels an jedem Pin)
  ../spotfam-reader.kicad_pcb   Platine: alle Footprints platziert und mit Netzen,
                                Umriss, Antennen-Sperrzone, GND-Flaechen,
                                vorverlegte Versorgungsbahnen (siehe pcb.py)
  ../spotfam-reader.kicad_pro   Projekt mit JLCPCB-tauglichen Designregeln
  ../bom/jlcpcb-bom.csv         BOM im JLCPCB-Format (nur SMT-Teile)
  ../bom/full-bom.csv           Komplette Stueckliste inkl. Handloet-Teile
  ../NETLIST.md                 Lesbare Netzliste zur Kontrolle

Bewusst ohne Abhaengigkeiten (nur Python-Standardbibliothek).
Footprints liegen als Kopie in ../footprints, damit die Generierung ohne
KiCad-Installation reproduzierbar ist.
"""

import csv
import json
import os
import sys
import uuid
from collections import defaultdict

sys.path.insert(0, os.path.dirname(__file__))
from design import PARTS  # noqa: E402
import pcb  # noqa: E402

ROOT = os.path.normpath(os.path.join(os.path.dirname(__file__), ".."))
PROJECT = "spotfam-reader"
NS = uuid.UUID("6f1c2a52-3d8e-4b6a-9b1e-5f0c2d7e9a10")  # stabile UUIDs -> saubere Git-Diffs
G = 2.54


def uid(*parts):
    return str(uuid.uuid5(NS, "/".join(str(p) for p in parts)))


def q(s):
    return '"' + str(s).replace("\\", "\\\\").replace('"', '\\"') + '"'


def f(v):
    s = f"{v:.4f}".rstrip("0").rstrip(".")
    return "0" if s in ("-0", "") else s


FONT = "(effects (font (size 1.27 1.27)))"
FONT_HIDE = "(effects (font (size 1.27 1.27)) (hide yes))"


# ---------------------------------------------------------------------------
# Symbole
# ---------------------------------------------------------------------------
def symbol_name(p):
    if p.symbol.startswith(("Conn_", "SolderJumper")):
        return f"{p.symbol}_{p.ref}"
    return p.symbol


def symbol_geometry(p):
    """Liefert Box-Halbbreite, Pin-Positionen {pin: (x, y, angle)} in Symbolkoordinaten (y nach oben)."""
    longest = max([len(n) for _, n, _ in p.left + p.right] + [1])
    two_sided = bool(p.left) and bool(p.right)
    if len(p.left) + len(p.right) == 2 and two_sided:
        half_w = 2 * G  # kleine Box fuer R/C/LED/Taster
    else:
        chars = longest * (2 if two_sided else 1) + 4
        half_w = max(2, int((chars * 0.75 * 1.27 / 2) / G + 2)) * G
    rows = max(len(p.left), len(p.right), 1)
    top = ((rows - 1) // 2 + (rows - 1) % 2) * G
    pins = {}
    for i, (num, _, _) in enumerate(p.left):
        pins[num] = (-half_w - G, top - i * G, 0)
    for i, (num, _, _) in enumerate(p.right):
        pins[num] = (half_w + G, top - i * G, 180)
    bottom = top - (rows - 1) * G
    return half_w, top + G, bottom - G, pins


def lib_symbol(p):
    name = symbol_name(p)
    half_w, y_top, y_bot, pins = symbol_geometry(p)
    two_pin = len(p.left) + len(p.right) == 2
    out = [f'(symbol {q("spotfam:" + name)} (pin_names (offset 0.508){" (hide yes)" if two_pin else ""})'
           f' (exclude_from_sim no) (in_bom yes) (on_board yes)']
    out.append(f'(property "Reference" {q("".join(c for c in p.ref if c.isalpha()))} (at 0 {f(y_top + 1.27)} 0) {FONT})')
    out.append(f'(property "Value" {q(name)} (at 0 {f(y_bot - 1.27)} 0) {FONT})')
    out.append(f'(property "Footprint" "" (at 0 0 0) {FONT_HIDE})')
    out.append(f'(property "Datasheet" "" (at 0 0 0) {FONT_HIDE})')
    out.append(f'(symbol {q(name + "_0_1")} (rectangle (start {f(-half_w)} {f(y_top)}) (end {f(half_w)} {f(y_bot)})'
               f' (stroke (width 0.254) (type default)) (fill (type background))))')
    pin_s = []
    names = {num: n for num, n, _ in p.left + p.right}
    for num, (x, y, a) in pins.items():
        pin_s.append(f'(pin passive line (at {f(x)} {f(y)} {a}) (length 2.54)'
                     f' (name {q(names[num])} {FONT}) (number {q(num)} {FONT}))')
    out.append(f'(symbol {q(name + "_1_1")} ' + " ".join(pin_s) + ")")
    out.append(")")
    return " ".join(out)


# ---------------------------------------------------------------------------
# Schaltplan
# ---------------------------------------------------------------------------
def layout_positions():
    """Feste Anordnung auf A3 (420 x 297 mm), alles im 2,54-mm-Raster."""
    pos = {}
    pos["U1"] = (190.5, 130.81)
    pos["J1"] = (45.72, 63.5)
    pos["U3"] = (45.72, 116.84)
    pos["U2"] = (45.72, 152.4)
    # rechte Spalten: Steckverbinder
    col_x = [322.58, 383.54]
    conns = ["J2", "J3", "JP1", "JP2", "J4", "J5", "J6", "J7"]
    y = [50.8, 50.8]
    for i, ref in enumerate(conns):
        c = i % 2
        pos[ref] = (col_x[c], y[c])
        y[c] += 45.72 if ref in ("J4", "J2", "J7") else 33.02
    # Passive in Reihen unten
    small = [p.ref for p in PARTS if len(p.left) + len(p.right) == 2]
    x0, y0 = 30.48, 205.74
    for i, ref in enumerate(small):
        r, c = divmod(i, 8)
        pos[ref] = (x0 + c * 45.72, y0 + r * 15.24)
    for i, p in enumerate([p for p in PARTS if p.ref.startswith("H")]):
        pos[p.ref] = (320.04 + i * 20.32, 270.51)
    return pos


def gen_schematic():
    pos = layout_positions()
    root = uid("root")
    lines = [
        "(kicad_sch (version 20231120) (generator \"eeschema\") (generator_version \"8.0\")",
        f"(uuid {q(root)})",
        '(paper "A3")',
        '(title_block (title "SpotFam Reader") (date "2026-11-10") (rev "A")'
        ' (comment 1 "ESP32-S3-WROOM-1, USB-C, PN532 (I2C), 4 Taster, OLED/TFT vorbereitet")'
        ' (comment 2 "Generiert aus tools/design.py - Aenderungen dort vornehmen"))',
        "(lib_symbols",
    ]
    seen = set()
    for p in PARTS:
        n = symbol_name(p)
        if n not in seen:
            seen.add(n)
            lines.append(lib_symbol(p))
    lines.append(")")

    for p in PARTS:
        X, Y = pos[p.ref]
        half_w, y_top, y_bot, pins = symbol_geometry(p)
        su = uid("sym", p.ref)
        lines.append(f'(symbol (lib_id {q("spotfam:" + symbol_name(p))}) (at {f(X)} {f(Y)} 0) (unit 1)'
                     f' (exclude_from_sim no) (in_bom {"no" if p.assembly == "-" else "yes"}) (on_board yes) (dnp no)'
                     f' (uuid {q(su)})')
        lines.append(f'(property "Reference" {q(p.ref)} (at {f(X)} {f(Y - y_top - 1.27)} 0) {FONT})')
        lines.append(f'(property "Value" {q(p.value)} (at {f(X)} {f(Y - y_bot + 1.27)} 0) {FONT})')
        lines.append(f'(property "Footprint" {q(p.footprint)} (at {f(X)} {f(Y)} 0) {FONT_HIDE})')
        lines.append(f'(property "Datasheet" "" (at {f(X)} {f(Y)} 0) {FONT_HIDE})')
        lines.append(f'(property "LCSC" {q(p.lcsc)} (at {f(X)} {f(Y)} 0) {FONT_HIDE})')
        lines.append(f'(property "Assembly" {q(p.assembly)} (at {f(X)} {f(Y)} 0) {FONT_HIDE})')
        for num in pins:
            lines.append(f'(pin {q(num)} (uuid {q(uid("pin", p.ref, num))}))')
        lines.append(f'(instances (project {q(PROJECT)} (path {q("/" + root)} (reference {q(p.ref)}) (unit 1))))')
        lines.append(")")

        nets = {num: net for num, _, net in p.left + p.right}
        for num, (px, py, a) in pins.items():
            sx, sy = X + px, Y - py
            net = nets[num]
            if net is None:
                lines.append(f'(no_connect (at {f(sx)} {f(sy)}) (uuid {q(uid("nc", p.ref, num))}))')
                continue
            ang, just = (180, "right") if a == 0 else (0, "left")
            lines.append(f'(label {q(net)} (at {f(sx)} {f(sy)} {ang})'
                         f' (effects (font (size 1.27 1.27)) (justify {just} bottom))'
                         f' (uuid {q(uid("lbl", p.ref, num))}))')

    lines.append('(sheet_instances (path "/" (page "1")))')
    lines.append(")")
    return "\n".join(lines) + "\n"


# ---------------------------------------------------------------------------
# Projekt / Designregeln (JLCPCB Standard 2-Lagen)
# ---------------------------------------------------------------------------
def gen_project():
    def cls(name, track, clear, via_d=0.6, via_drill=0.3, prio=0):
        return {
            "name": name, "clearance": clear, "track_width": track,
            "via_diameter": via_d, "via_drill": via_drill,
            "microvia_diameter": 0.3, "microvia_drill": 0.1,
            "diff_pair_width": 0.2, "diff_pair_gap": 0.25, "diff_pair_via_gap": 0.25,
            "wire_width": 6, "bus_width": 12, "line_style": 0,
            "pcb_color": "rgba(0, 0, 0, 0.000)", "schematic_color": "rgba(0, 0, 0, 0.000)",
            "priority": prio,
        }

    pro = {
        "meta": {"filename": f"{PROJECT}.kicad_pro", "version": 1},
        "board": {
            "design_settings": {
                "rules": {
                    "min_clearance": 0.15,
                    "min_track_width": 0.15,
                    "min_via_diameter": 0.5,
                    "min_through_hole_diameter": 0.3,
                    "min_hole_to_hole": 0.5,
                    "min_copper_edge_clearance": 0.3,
                    "min_silk_clearance": 0.0,
                },
            }
        },
        "net_settings": {
            "meta": {"version": 3},
            "classes": [
                cls("Default", 0.25, 0.2, prio=2147483647),
                cls("Power", 0.6, 0.25, via_d=0.8, via_drill=0.4, prio=0),
                cls("USB", 0.3, 0.2, prio=1),
            ],
            "netclass_patterns": [
                {"netclass": "Power", "pattern": "+5V"},
                {"netclass": "Power", "pattern": "+3V3"},
                {"netclass": "Power", "pattern": "GND"},
                {"netclass": "USB", "pattern": "USB_D*"},
            ],
        },
        "sheets": [[uid("root"), "Root"]],
    }
    return json.dumps(pro, indent=2) + "\n"


# ---------------------------------------------------------------------------
# BOM + Netzliste
# ---------------------------------------------------------------------------
def gen_bom():
    groups = defaultdict(list)
    for p in PARTS:
        if p.assembly == "-":
            continue
        groups[(p.value, p.footprint, p.lcsc, p.assembly, p.mpn)].append(p.ref)
    os.makedirs(os.path.join(ROOT, "bom"), exist_ok=True)
    with open(os.path.join(ROOT, "bom", "jlcpcb-bom.csv"), "w", newline="") as fh:
        w = csv.writer(fh)
        w.writerow(["Comment", "Designator", "Footprint", "LCSC Part #"])
        for (val, fp, lcsc, asm, _), refs in sorted(groups.items(), key=lambda kv: kv[1][0]):
            if asm == "JLC":
                w.writerow([val, ",".join(refs), fp.split(":")[1], lcsc])
    with open(os.path.join(ROOT, "bom", "full-bom.csv"), "w", newline="") as fh:
        w = csv.writer(fh)
        w.writerow(["Menge", "Referenzen", "Wert", "MPN", "Footprint", "LCSC", "Bestueckung"])
        for (val, fp, lcsc, asm, mpn), refs in sorted(groups.items(), key=lambda kv: kv[1][0]):
            w.writerow([len(refs), ",".join(refs), val, mpn, fp, lcsc,
                        "JLCPCB SMT" if asm == "JLC" else "selbst loeten"])


def gen_netlist_md():
    nets = defaultdict(list)
    for p in PARTS:
        for num, name, net in p.left + p.right:
            if net:
                nets[net].append(f"{p.ref}.{num}" + (f" ({name})" if not name.isdigit() else ""))
    out = ["# Netzliste SpotFam Reader Rev A", "",
           "Generiert aus `tools/design.py`. Nicht von Hand bearbeiten.", "",
           "| Netz | Anschluesse |", "|---|---|"]
    for net in sorted(nets):
        out.append(f"| `{net}` | {', '.join(nets[net])} |")
    return "\n".join(out) + "\n", nets


def main():
    board, pads, boxes = pcb.gen_board()
    errors = pcb.check_routes(pads)
    if errors:
        print("\n".join(errors))
        sys.exit(1)
    files = {
        f"{PROJECT}.kicad_sch": gen_schematic(),
        f"{PROJECT}.kicad_pcb": board,
        f"{PROJECT}.kicad_pro": gen_project(),
    }
    md, nets = gen_netlist_md()
    files["NETLIST.md"] = md
    for name, content in files.items():
        with open(os.path.join(ROOT, name), "w") as fh:
            fh.write(content)
    gen_bom()

    # Plausibilitaet: jedes Netz braucht mind. 2 Anschluesse
    single = {n: v for n, v in nets.items() if len(v) < 2}
    if single:
        print("WARNUNG: Netze mit nur einem Anschluss:", single)
        sys.exit(1)
    print(f"OK: {len(PARTS)} Bauteile, {len(nets)} Netze")


if __name__ == "__main__":
    main()
