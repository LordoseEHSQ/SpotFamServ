"""
Platinen-Generator SpotFam Reader Rev A.

- Laedt die Footprints aus ../footprints (Kopien aus den KiCad-Bibliotheken,
  ESP32-S3 aus espressif/kicad-libraries), setzt sie gemaess PLACEMENT und
  vergibt die Netze aus design.py.
- Zeichnet Umriss, Antennen-Sperrzone und GND-Flaechen.
- Legt die kritischen Leiterbahnen (USB, 5 V, 3,3 V) aus ROUTES.
- Der Rest wird mit Freerouting geroutet (siehe README).

Koordinaten in PLACEMENT/ROUTES sind relativ zur linken oberen Platinenecke (mm).
Ausgabeformat: KiCad-7-Board (wird von KiCad 7 und 8 geoeffnet).
"""

import math
import os
import re
import uuid

from design import PARTS

HERE = os.path.dirname(__file__)
FP_DIR = os.path.normpath(os.path.join(HERE, "..", "footprints"))
NS = uuid.UUID("6f1c2a52-3d8e-4b6a-9b1e-5f0c2d7e9a10")

BOARD_W, BOARD_H = 88.9, 63.5   # Sammelkartenformat (2,5 x 3,5 Zoll), quer
OX, OY = 100.0, 80.0            # Lage der Platine auf dem Blatt
CORNER_R = 3.0
# Antennen-Sperrzone: Modulantenne liegt buendig an der Oberkante
ANT_X0, ANT_X1, ANT_D = 31.45, 57.45, 6.75


def uid(*p):
    return str(uuid.uuid5(NS, "/".join(map(str, p))))


def fmt(v):
    s = f"{v:.4f}".rstrip("0").rstrip(".")
    return "0" if s in ("-0", "") else s


# ---------------------------------------------------------------------------
# Bestueckungsplan: ref -> (x, y, rotation_grad)
# ---------------------------------------------------------------------------
PLACEMENT = {
    # Modul: Antenne an die Oberkante (Footprint-Ursprung = Modulmitte ohne Antenne)
    "U1": (44.45, 15.75, 0),
    # USB-C an der linken Kante, Oeffnung nach links
    "J1": (3.65, 27.0, 270),
    "R1": (11.2, 21.6, 0),
    "R2": (11.2, 32.6, 0),
    "U3": (17.0, 27.0, 0),          # ESD, Durchfluss links -> rechts
    # Versorgung oben links
    "U2": (16.0, 11.0, 0),          # AMS1117, Tab (3V3) zeigt nach rechts
    "C1": (12.85, 16.2, 0),         # 10 uF am Eingang, direkt unter VIN
    "C2": (24.4, 11.0, 270),        # 22 uF am Ausgang
    "C3": (29.0, 9.6, 270),         # 22 uF an U1 Pin 2
    "C4": (32.2, 9.6, 270),         # 100 nF an U1 Pin 2
    "D2": (4.0, 15.5, 270),         # Power-LED
    "R6": (4.0, 19.0, 270),
    # EN / Status links neben dem Modul
    "R3": (29.5, 13.4, 0),
    "C5": (29.5, 15.2, 0),
    "R5": (29.5, 17.6, 0),
    "D1": (29.5, 19.4, 180),
    # BOOT-Pull-up rechts unten am Modul
    "R4": (57.2, 26.0, 0),
    # TFT-Header unter dem Modul
    "J4": (34.29, 31.5, 90),
    # Steckverbinder rechts
    "J2": (62.0, 10.5, 0),          # PN532 JST-XH
    "C6": (79.0, 13.0, 270),
    "R7": (79.0, 17.0, 270),
    "R8": (81.5, 17.0, 270),
    "J3": (62.0, 19.5, 90),         # OLED
    "JP1": (63.3, 23.5, 0),
    "JP2": (68.4, 23.5, 0),
    "J7": (62.0, 29.5, 90),         # Erweiterung
    "J5": (62.0, 36.5, 90),         # UART
    # Taster unten
    "SW1": (20.0, 49.5, 0),
    "SW2": (32.0, 49.5, 0),
    "SW3": (44.0, 49.5, 0),
    "SW4": (56.0, 49.5, 0),
    "J6": (70.0, 46.0, 90),         # Panel-Taster
    "SW5": (4.5, 39.0, 0),          # RESET
    "SW6": (72.0, 37.0, 0),         # BOOT
    # Befestigungsloecher
    "H1": (4.0, 4.0, 0),
    "H2": (BOARD_W - 4.0, 4.0, 0),
    "H3": (4.0, BOARD_H - 4.0, 0),
    "H4": (BOARD_W - 4.0, BOARD_H - 4.0, 0),
}

# ---------------------------------------------------------------------------
# S-Expression-Helfer
# ---------------------------------------------------------------------------
_TOK = re.compile(r'\(|\)|"(?:\\.|[^"\\])*"|[^\s()"]+')


def parse(text):
    stack = [[]]
    for m in _TOK.finditer(text):
        t = m.group()
        if t == "(":
            stack.append([])
        elif t == ")":
            x = stack.pop()
            stack[-1].append(x)
        else:
            stack[-1].append(t)
    return stack[0][0]


def dump(node, indent=0):
    if not isinstance(node, list):
        return node
    simple = all(not isinstance(c, list) or not any(isinstance(cc, list) for cc in c) for c in node)
    if simple or indent > 2:
        return "(" + " ".join(dump(c, indent + 1) for c in node) + ")"
    pad = "  " * (indent + 1)
    return "(" + " ".join(dump(c) for c in node if not isinstance(c, list)) + "".join(
        "\n" + pad + dump(c, indent + 1) for c in node if isinstance(c, list)) + ")"


def unq(s):
    return s[1:-1] if s.startswith('"') else s


def q(s):
    return '"' + str(s).replace('"', '\\"') + '"'


def child(node, name):
    for c in node:
        if isinstance(c, list) and c and c[0] == name:
            return c
    return None


def rot(x, y, a):
    """KiCad-Drehung (positiver Winkel = gegen den Uhrzeigersinn, y nach unten)."""
    r = math.radians(a)
    return x * math.cos(r) + y * math.sin(r), -x * math.sin(r) + y * math.cos(r)


# ---------------------------------------------------------------------------
# Footprints laden und platzieren
# ---------------------------------------------------------------------------
def load_fp(fp_id):
    lib, name = fp_id.split(":")
    with open(os.path.join(FP_DIR, f"{lib}__{name}.kicad_mod")) as fh:
        return parse(fh.read())


def nets_of(part):
    return {num: net for num, _, net in part.left + part.right if net}


def place(part, x, y, a, net_codes):
    """Gibt (footprint_tree, pads[], courtyard_bbox) in Platinenkoordinaten zurueck."""
    src = load_fp(part.footprint)
    ax, ay = OX + x, OY + y
    pin_nets = nets_of(part)
    out = ["footprint", q(part.footprint), ["layer", '"F.Cu"'], ["tstamp", uid("fp", part.ref)],
           ["at", fmt(ax), fmt(ay)] + ([fmt(a)] if a else [])]
    pads, crt = [], []
    n = 0
    for c in src[2:]:
        if not isinstance(c, list):
            continue
        tag = c[0]
        if tag in ("version", "generator", "tedit", "layer", "tstamp", "uuid", "zone", "property"):
            continue
        n += 1
        c = [e for e in c if not (isinstance(e, list) and e and e[0] in ("tstamp", "uuid"))]
        if tag == "attr":
            c = ["attr", "exclude_from_pos_files", "exclude_from_bom"] if "virtual" in c else c
        elif tag == "fp_text":
            if c[1] == "reference":
                c[2] = q(part.ref)
            elif c[1] == "value":
                c[2] = q(part.value)
            at = child(c, "at")
            ang = (float(at[3]) if len(at) > 3 and at[3] != "unlocked" else 0) + a
            at[3:] = [fmt(ang % 360)] if ang % 360 else []
            c.append(["tstamp", uid("fp", part.ref, n)])
        elif tag == "pad":
            num = unq(c[1])
            at = child(c, "at")
            px, py = float(at[1]), float(at[2])
            pang = float(at[3]) if len(at) > 3 else 0
            ang = (pang + a) % 360
            at[3:] = [fmt(ang)] if ang else []
            size = child(c, "size")
            sx, sy = float(size[1]), float(size[2])
            rx, ry = rot(px, py, a)
            if round(ang) % 180 == 90:
                sx, sy = sy, sx
            layers = child(c, "layers")
            net = pin_nets.get(num)
            if net:
                c.append(["net", str(net_codes[net]), q(net)])
            c.append(["tstamp", uid("pad", part.ref, n)])
            pads.append({"ref": part.ref, "num": num, "net": net, "x": x + rx, "y": y + ry,
                         "w": sx, "h": sy, "thru": c[2] in ("thru_hole", "np_thru_hole"),
                         "layers": layers})
        elif tag in ("fp_line", "fp_rect"):
            layer = child(c, "layer")
            if layer and unq(layer[1]) == "F.CrtYd":
                for key in ("start", "end"):
                    e = child(c, key)
                    crt.append(rot(float(e[1]), float(e[2]), a))
            c.append(["tstamp", uid("fp", part.ref, n)])
        elif tag in ("fp_circle", "fp_arc", "fp_poly"):
            layer = child(c, "layer")
            if tag == "fp_circle" and layer and unq(layer[1]) == "F.CrtYd":
                cen, end = child(c, "center"), child(c, "end")
                r = math.hypot(float(end[1]) - float(cen[1]), float(end[2]) - float(cen[2]))
                crt += [rot(float(cen[1]) - r, float(cen[2]) - r, a), rot(float(cen[1]) + r, float(cen[2]) + r, a)]
            c.append(["tstamp", uid("fp", part.ref, n)])
        out.append(c)
    # Verknuepfung mit dem Schaltplan-Symbol (fuer F8)
    out.insert(5, ["path", q("/" + uid("sym", part.ref))])
    out.insert(5, ["property", '"Sheetfile"', q("spotfam-reader.kicad_sch")])
    out.insert(5, ["property", '"Sheetname"', '""'])
    if crt:
        xs, ys = [p[0] for p in crt], [p[1] for p in crt]
        bbox = (x + min(xs), y + min(ys), x + max(xs), y + max(ys))
    else:
        bbox = (min(p["x"] - p["w"] / 2 for p in pads) - 0.25, min(p["y"] - p["h"] / 2 for p in pads) - 0.25,
                max(p["x"] + p["w"] / 2 for p in pads) + 0.25, max(p["y"] + p["h"] / 2 for p in pads) + 0.25)
    return out, pads, bbox


def net_codes():
    names = sorted({net for p in PARTS for _, _, net in p.left + p.right if net})
    return {n: i + 1 for i, n in enumerate(names)}


def build():
    codes = net_codes()
    fps, pads, boxes = [], [], {}
    for p in PARTS:
        x, y, a = PLACEMENT[p.ref]
        fp, pp, bb = place(p, x, y, a, codes)
        fps.append(fp)
        pads += pp
        boxes[p.ref] = bb
    return codes, fps, pads, boxes


def pad(pads, ref, num):
    for p in pads:
        if p["ref"] == ref and p["num"] == num:
            return p["x"], p["y"]
    raise KeyError(f"{ref}.{num}")


# ---------------------------------------------------------------------------
# Vorverlegte Leiterbahnen: Versorgung kurz und breit.
# (netz, lage, breite, [(x, y), ...]) in Platinenkoordinaten
# ---------------------------------------------------------------------------
ROUTES = [
    # 3,3 V: LDO-Tab -> C2 -> C3 -> C4 -> U1 Pin 2
    ("+3V3", "F.Cu", 0.6, [(19.15, 10.05), (25.5, 10.05), (26.9, 8.65), (29.0, 8.65),
                           (32.2, 8.76), (35.7, 8.76)]),
    # 5 V: USB-C VBUS (A4/B9) -> C1 -> AMS1117 VIN
    ("+5V", "F.Cu", 0.6, [(7.7, 24.55), (9.0, 24.55), (9.0, 17.5), (10.3, 16.2), (11.9, 16.2),
                          (11.9, 14.25), (12.85, 13.3)]),
]
VIAS = []  # (netz, x, y)


def seg_point_dist(ax, ay, bx, by, px, py):
    dx, dy = bx - ax, by - ay
    L = dx * dx + dy * dy
    t = 0 if L == 0 else max(0, min(1, ((px - ax) * dx + (py - ay) * dy) / L))
    return math.hypot(ax + t * dx - px, ay + t * dy - py)


def check_routes(pads, clearance=0.2):
    """Einfache Abstandspruefung: Leiterbahnen gegen fremde Pads (Pads als Rechteck)."""
    errors = []
    for net, layer, w, pts in ROUTES:
        for (ax, ay), (bx, by) in zip(pts, pts[1:]):
            for p in pads:
                if p["net"] == net:
                    continue
                on_layer = p["thru"] or layer in " ".join(p["layers"])
                if not on_layer:
                    continue
                # Abstand Segment -> Rechteck naeherungsweise ueber Stuetzpunkte
                best = 1e9
                for i in range(41):
                    t = i / 40
                    x, y = ax + t * (bx - ax), ay + t * (by - ay)
                    dx = max(abs(x - p["x"]) - p["w"] / 2, 0)
                    dy = max(abs(y - p["y"]) - p["h"] / 2, 0)
                    best = min(best, math.hypot(dx, dy))
                if best - w / 2 < clearance:
                    errors.append(f"{net} ({ax},{ay})-({bx},{by}) zu nah an {p['ref']}.{p['num']} ({p['net']}): {best - w / 2:.2f} mm")
    return errors


def gen_board():
    codes, fps, pads, boxes = build()
    L = [
        "(kicad_pcb (version 20221018) (generator pcbnew)",
        "(general (thickness 1.6))",
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
        "(setup (pad_to_mask_clearance 0))",
        '(net 0 "")',
    ]
    for name, code in sorted(codes.items(), key=lambda kv: kv[1]):
        L.append(f"(net {code} {q(name)})")

    def X(v):
        return fmt(OX + v)

    def Y(v):
        return fmt(OY + v)

    def gline(x1, y1, x2, y2, layer, w, key):
        return (f"(gr_line (start {X(x1)} {Y(y1)}) (end {X(x2)} {Y(y2)}) (stroke (width {fmt(w)}) (type solid))"
                f" (layer {q(layer)}) (tstamp {uid('ln', key)}))")

    def garc(s, m, e, key):
        return (f"(gr_arc (start {X(s[0])} {Y(s[1])}) (mid {X(m[0])} {Y(m[1])}) (end {X(e[0])} {Y(e[1])})"
                f' (stroke (width 0.05) (type solid)) (layer "Edge.Cuts") (tstamp {uid("arc", key)}))')

    W, H, r = BOARD_W, BOARD_H, CORNER_R
    k = r * (1 - 0.70710678)
    L += [
        gline(r, 0, W - r, 0, "Edge.Cuts", 0.05, "t"), gline(W, r, W, H - r, "Edge.Cuts", 0.05, "r"),
        gline(W - r, H, r, H, "Edge.Cuts", 0.05, "b"), gline(0, H - r, 0, r, "Edge.Cuts", 0.05, "l"),
        garc((W - r, 0), (W - k, k), (W, r), "tr"), garc((W, H - r), (W - k, H - k), (W - r, H), "br"),
        garc((r, H), (k, H - k), (0, H - r), "bl"), garc((0, r), (k, k), (r, 0), "tl"),
    ]

    def zone_pts(pts):
        return "(polygon (pts " + " ".join(f"(xy {X(a)} {Y(b)})" for a, b in pts) + "))"

    L.append(
        f'(zone (net 0) (net_name "") (layers "F.Cu" "B.Cu") (tstamp {uid("zone", "ant")}) (name "Antenne_Keepout")'
        " (hatch edge 0.5) (connect_pads (clearance 0)) (min_thickness 0.25) (filled_areas_thickness no)"
        " (keepout (tracks not_allowed) (vias not_allowed) (pads not_allowed) (copperpour not_allowed) (footprints allowed))"
        " (fill (thermal_gap 0.5) (thermal_bridge_width 0.5)) "
        + zone_pts([(ANT_X0, 0), (ANT_X1, 0), (ANT_X1, ANT_D), (ANT_X0, ANT_D)]) + ")")
    m = 0.4
    for layer in ("F.Cu", "B.Cu"):
        L.append(
            f'(zone (net {codes["GND"]}) (net_name "GND") (layer {q(layer)}) (tstamp {uid("zone", "gnd", layer)})'
            f' (name {q("GND_" + layer[0])}) (hatch edge 0.5) (connect_pads (clearance 0.3)) (min_thickness 0.25)'
            " (filled_areas_thickness no) (fill (thermal_gap 0.5) (thermal_bridge_width 0.5)) "
            + zone_pts([(m, m), (W - m, m), (W - m, H - m), (m, H - m)]) + ")")

    for fp in fps:
        L.append(dump(fp))

    for i, (net, layer, w, pts) in enumerate(ROUTES):
        for j, ((ax, ay), (bx, by)) in enumerate(zip(pts, pts[1:])):
            L.append(f"(segment (start {X(ax)} {Y(ay)}) (end {X(bx)} {Y(by)}) (width {fmt(w)}) (layer {q(layer)})"
                     f" (net {codes[net]}) (tstamp {uid('seg', i, j)}))")
    for i, (net, x, y) in enumerate(VIAS):
        L.append(f'(via (at {X(x)} {Y(y)}) (size 0.6) (drill 0.3) (layers "F.Cu" "B.Cu") (net {codes[net]})'
                 f" (tstamp {uid('via', i)}))")

    def gtext(t, x, y, layer, size, key):
        return (f"(gr_text {q(t)} (at {X(x)} {Y(y)}) (layer {q(layer)}) (tstamp {uid('txt', key)})"
                f" (effects (font (size {fmt(size)} {fmt(size)}) (thickness {fmt(size * 0.15)})) (justify left)))")

    L.append(gtext("SpotFam Reader Rev A", 22, 41.5, "F.SilkS", 1.2, "title"))
    L.append(gtext("NEXT", 21.2, 57.6, "F.SilkS", 1.0, "s1"))
    L.append(gtext("PREV", 33.2, 57.6, "F.SilkS", 1.0, "s2"))
    L.append(gtext("VOL+", 45.2, 57.6, "F.SilkS", 1.0, "s3"))
    L.append(gtext("VOL-", 57.2, 57.6, "F.SilkS", 1.0, "s4"))
    L.append(gtext("RST", 6.0, 46.6, "F.SilkS", 1.0, "s5"))
    L.append(gtext("BOOT", 73.0, 34.6, "F.SilkS", 1.0, "s6"))
    L.append(gtext("PN532: GND 3V3 SDA SCL IRQ RST", 59.0, 15.0, "F.SilkS", 0.8, "j2"))
    L.append(gtext("OLED: JP1/JP2 vor Gebrauch bruecken", 59.0, 26.2, "F.SilkS", 0.8, "j3"))
    L.append(")")
    return "\n".join(L) + "\n", pads, boxes
