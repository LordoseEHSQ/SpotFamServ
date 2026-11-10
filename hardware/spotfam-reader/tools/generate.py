#!/usr/bin/env python3
"""
Erzeugt aus design.py:
  ../spotfam-reader.kicad_sch   Schaltplan (Netze ueber Labels an jedem Pin)
  ../spotfam-reader.kicad_pcb   Platine: Umriss, Bohrungen, Antennen-Sperrzone,
                                GND-Flaeche, Platzierungshilfen
  ../spotfam-reader.kicad_pro   Projekt mit JLCPCB-tauglichen Designregeln
  ../bom/jlcpcb-bom.csv         BOM im JLCPCB-Format (nur SMT-Teile)
  ../bom/full-bom.csv           Komplette Stueckliste inkl. Handloet-Teile
  ../NETLIST.md                 Lesbare Netzliste zur Kontrolle

Bewusst ohne Abhaengigkeiten (nur Python-Standardbibliothek).
Die Footprints selbst holt KiCad beim "Update PCB from Schematic" (F8)
aus den offiziellen Bibliotheken.
"""

import csv
import json
import os
import sys
import uuid
from collections import defaultdict

sys.path.insert(0, os.path.dirname(__file__))
from design import PARTS  # noqa: E402

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
# Platine (ohne Footprints - die kommen per F8 aus dem Schaltplan)
# ---------------------------------------------------------------------------
BOARD_W, BOARD_H = 80.0, 55.0
OX, OY = 100.0, 80.0  # Ursprung auf dem Blatt
CORNER_R = 3.0
ANT_W, ANT_D = 26.0, 8.0  # Antennen-Sperrzone (Modulantenne 18 mm + Rand)


def gen_pcb():
    L = [
        '(kicad_pcb (version 20240108) (generator "pcbnew") (generator_version "8.0")',
        "(general (thickness 1.6) (legacy_teardrops no))",
        '(paper "A4")',
        '(title_block (title "SpotFam Reader") (date "2026-11-10") (rev "A"))',
        """(layers
  (0 "F.Cu" signal) (31 "B.Cu" signal)
  (32 "B.Adhes" user "B.Adhesive") (33 "F.Adhes" user "F.Adhesive")
  (34 "B.Paste" user) (35 "F.Paste" user)
  (36 "B.SilkS" user "B.Silkscreen") (37 "F.SilkS" user "F.Silkscreen")
  (38 "B.Mask" user) (39 "F.Mask" user)
  (40 "Dwgs.User" user "User.Drawings") (41 "Cmts.User" user "User.Comments")
  (42 "Eco1.User" user "User.Eco1") (43 "Eco2.User" user "User.Eco2")
  (44 "Edge.Cuts" user) (45 "Margin" user)
  (46 "B.CrtYd" user "B.Courtyard") (47 "F.CrtYd" user "F.Courtyard")
  (48 "B.Fab" user) (49 "F.Fab" user))""",
        "(setup (pad_to_mask_clearance 0) (allow_soldermask_bridges_in_footprints no))",
        '(net 0 "")',
        '(net 1 "GND")',
    ]

    def line(x1, y1, x2, y2, layer, w=0.05, key=None):
        return (f"(gr_line (start {f(x1)} {f(y1)}) (end {f(x2)} {f(y2)})"
                f' (stroke (width {f(w)}) (type default)) (layer {q(layer)}) (uuid {q(uid("ln", key or (x1, y1, x2, y2, layer)))}))')

    def arc(sx, sy, mx, my, ex, ey, key):
        return (f"(gr_arc (start {f(sx)} {f(sy)}) (mid {f(mx)} {f(my)}) (end {f(ex)} {f(ey)})"
                f' (stroke (width 0.05) (type default)) (layer "Edge.Cuts") (uuid {q(uid("arc", key))}))')

    def text(t, x, y, layer, size=1.0, key=None):
        return (f"(gr_text {q(t)} (at {f(x)} {f(y)} 0) (layer {q(layer)}) (uuid {q(uid('txt', key or t))})"
                f" (effects (font (size {f(size)} {f(size)}) (thickness {f(size * 0.15)})) (justify left)))")

    def rect(x1, y1, x2, y2, layer, key):
        return (f"(gr_rect (start {f(x1)} {f(y1)}) (end {f(x2)} {f(y2)})"
                f' (stroke (width 0.1) (type dash)) (fill none) (layer {q(layer)}) (uuid {q(uid("rect", key))}))')

    x0, y0, x1, y1, r = OX, OY, OX + BOARD_W, OY + BOARD_H, CORNER_R
    k = 0.70710678 * r
    # Umriss mit abgerundeten Ecken
    L += [
        line(x0 + r, y0, x1 - r, y0, "Edge.Cuts"), line(x1, y0 + r, x1, y1 - r, "Edge.Cuts"),
        line(x1 - r, y1, x0 + r, y1, "Edge.Cuts"), line(x0, y1 - r, x0, y0 + r, "Edge.Cuts"),
        arc(x1 - r, y0, x1 - r + k, y0 + r - k, x1, y0 + r, "tr"),
        arc(x1, y1 - r, x1 - r + k, y1 - r + k, x1 - r, y1, "br"),
        arc(x0 + r, y1, x0 + r - k, y1 - r + k, x0, y1 - r, "bl"),
        arc(x0, y0 + r, x0 + r - k, y0 + r - k, x0 + r, y0, "tl"),
    ]

    # Antennen-Sperrzone oben mittig, Modulantenne buendig an die Oberkante legen
    ax0 = OX + (BOARD_W - ANT_W) / 2
    ant = [(ax0, y0), (ax0 + ANT_W, y0), (ax0 + ANT_W, y0 + ANT_D), (ax0, y0 + ANT_D)]
    pts = " ".join(f"(xy {f(a)} {f(b)})" for a, b in ant)
    L.append(
        f'(zone (net 0) (net_name "") (layers "F.Cu" "B.Cu") (uuid {q(uid("zone", "antenna"))}) (name "Antenne_Keepout")'
        " (hatch edge 0.5) (connect_pads (clearance 0)) (min_thickness 0.25) (filled_areas_thickness no)"
        " (keepout (tracks not_allowed) (vias not_allowed) (pads not_allowed) (copperpour not_allowed) (footprints allowed))"
        f" (fill (thermal_gap 0.5) (thermal_bridge_width 0.5)) (polygon (pts {pts})))"
    )

    # GND-Flaeche auf beiden Lagen (wird nach dem Routen mit B gefuellt)
    m = 0.5
    gnd = [(x0 + m, y0 + m), (x1 - m, y0 + m), (x1 - m, y1 - m), (x0 + m, y1 - m)]
    gpts = " ".join(f"(xy {f(a)} {f(b)})" for a, b in gnd)
    for layer in ("F.Cu", "B.Cu"):
        L.append(
            f'(zone (net 1) (net_name "GND") (layer {q(layer)}) (uuid {q(uid("zone", "gnd", layer))}) (name {q("GND_" + layer[0])})'
            " (hatch edge 0.5) (priority 0) (connect_pads (clearance 0.3)) (min_thickness 0.25) (filled_areas_thickness no)"
            " (fill (thermal_gap 0.5) (thermal_bridge_width 0.5)) (polygon (pts " + gpts + ")))"
        )

    # Platzierungshilfen auf User.Drawings
    areas = [
        ("U1 ESP32-S3 (Antenne an Oberkante)", ax0 + 4, y0, ax0 + ANT_W - 4, y0 + 26.5, "u1"),
        ("J1 USB-C (Buchse buendig links)", x0, y0 + 20, x0 + 10, y0 + 32, "j1"),
        ("U2/U3/C1/C2 Versorgung + ESD", x0 + 11, y0 + 18, x0 + 25, y0 + 34, "pwr"),
        ("SW1-SW4 Taster", x0 + 8, y1 - 12, x1 - 8, y1 - 2, "sw"),
        ("J2 PN532 / J3 OLED / J4 TFT", x1 - 22, y0 + 6, x1 - 2, y0 + 40, "conn"),
        ("SW5 RST / SW6 BOOT / J5 UART", x0 + 2, y0 + 36, x0 + 22, y0 + 42, "dbg"),
    ]
    for t, a, b, c, d, key in areas:
        L.append(rect(a, b, c, d, "Dwgs.User", key))
        L.append(text(t, a + 0.5, b + 1.2, "Dwgs.User", 0.8, key))

    for i, (hx, hy) in enumerate([(x0 + 4, y0 + 4), (x1 - 4, y0 + 4), (x0 + 4, y1 - 4), (x1 - 4, y1 - 4)], 1):
        L.append(f"(gr_circle (center {f(hx)} {f(hy)}) (end {f(hx + 1.6)} {f(hy)})"
                 f' (stroke (width 0.1) (type dash)) (fill none) (layer "Dwgs.User") (uuid {q(uid("hole", i))}))')
        L.append(text(f"H{i}", hx + 2, hy, "Dwgs.User", 0.8, f"h{i}"))

    L.append(text("SpotFam Reader Rev A", x0 + 30, y1 - 15, "F.SilkS", 1.2, "title"))
    L.append(text("Footprints: Tools > Update PCB from Schematic (F8)", x0, y1 + 3, "Cmts.User", 1.0, "hint"))
    L.append(")")
    return "\n".join(L) + "\n"


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
    files = {
        f"{PROJECT}.kicad_sch": gen_schematic(),
        f"{PROJECT}.kicad_pcb": gen_pcb(),
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
