# SpotFam Reader – Platine Rev A

Eigene Platine für den SpotFam-Reader: **ESP32-S3-WROOM-1** direkt bestückt,
USB-C, 3,3-V-Regler, ESD-Schutz, vier Taster, Stecker für den PN532 im
Gehäusedeckel, vorbereitete Display-Anschlüsse (I2C-OLED **oder** SPI-TFT).

> **Status: ungeprüfter Entwurf.** Die Dateien wurden ohne KiCad generiert.
> Schaltplan und Netzliste sind maschinell gegen `tools/design.py` geprüft,
> **ERC, DRC, Platzierung und Routing fehlen noch.** Vor einer Bestellung
> unbedingt die Schritte unten durchgehen.

## Dateien

| Datei | Inhalt |
|---|---|
| `tools/design.py` | **Single Source of Truth**: Bauteile, Pins, Netze, LCSC-Nummern |
| `tools/generate.py` | Erzeugt alle Dateien unten (`python3 tools/generate.py`) |
| `spotfam-reader.kicad_sch` | Schaltplan. Netze hängen über Labels an den Pins |
| `spotfam-reader.kicad_pcb` | Platinenumriss 80 × 55 mm, Antennen-Sperrzone, GND-Flächen, Platzierungshilfen |
| `spotfam-reader.kicad_pro` | Projekt mit Netzklassen (Power, USB, Default) und JLCPCB-Regeln |
| `NETLIST.md` | Lesbare Netzliste zum Gegenlesen |
| `bom/jlcpcb-bom.csv` | BOM im JLCPCB-Format (nur SMD-Teile) |
| `bom/full-bom.csv` | Komplette Stückliste inkl. selbst zu lötender Teile |

Bei Änderungen an Pinbelegung oder Bauteilen gilt: `design.py` ändern,
`generate.py` ausführen, dann in KiCad **F8** drücken. Den Schaltplan nicht
von Hand umzeichnen, sonst laufen Doku und KiCad auseinander.

## GPIO-Belegung (ESP32-S3)

| Funktion | GPIO | Bemerkung |
|---|---|---|
| USB D− / D+ | 19 / 20 | Nativer USB (Flashen, Serial). Kein USB-UART-Chip nötig |
| Taster NEXT / PREV | 4 / 5 | aktiv LOW gegen GND, `INPUT_PULLUP` |
| Taster VOL+ / VOL− | 6 / 7 | aktiv LOW gegen GND, `INPUT_PULLUP` |
| I2C SDA / SCL | 8 / 9 | PN532 **und** OLED am selben Bus, 4,7 kΩ Pull-ups auf der Platine |
| PN532 IRQ / RSTO | 16 / 17 | optional, im Stecker J2 vorbereitet |
| Status-LED | 15 | über 1 kΩ, aktiv HIGH |
| SPI CS / MOSI / SCK / MISO | 10 / 11 / 12 / 13 | FSPI-Standardpins, nur für TFT |
| TFT DC / RST / BL | 14 / 21 / 47 | nur für TFT. BL direkt am GPIO (max. ca. 20 mA) |
| UART0 TX / RX | 43 / 44 | Debug-Header J5 |
| Frei (J7) | 1, 2, 18, 38 | z. B. Drehencoder |
| BOOT | 0 | Taster SW6 + 10 kΩ Pull-up |
| EN / Reset | EN | Taster SW5, RC 10 kΩ / 1 µF |

Die Strapping-Pins 3, 45 und 46 sind bewusst unbenutzt. GPIO 35–37 bleiben
frei, damit später auch Modulvarianten mit Octal-PSRAM passen.

## Steckverbinder

- **J2 PN532** (JST-XH 6-polig): 1 GND · 2 3V3 · 3 SDA · 4 SCL · 5 IRQ · 6 RSTO.
  Den HW-147 mit **3,3 V** versorgen, nicht mit 5 V: Seine I2C-Pegel hängen an
  der Versorgung, und der ESP32-S3 ist nicht 5-V-tolerant. DIP-Schalter am
  HW-147 auf I2C stellen (siehe `docs/hw0-pn532-runbook.md`).
- **J3 OLED** (Stiftleiste 4-polig): P1 · P2 · SCL · SDA.
  Bei OLED-Modulen ist die Reihenfolge von GND und VCC je nach Händler vertauscht. Deshalb:
  - **GND, VCC, SCL, SDA** (häufigste Variante): JP1 1–2 und JP2 1–2 brücken
  - **VCC, GND, SCL, SDA**: JP1 2–3 und JP2 2–3 brücken
  - Erst messen, dann brücken. Pro Jumper immer nur **eine** Seite.
- **J4 TFT** (9-polig): GND · 3V3 · SCK · MOSI · MISO · CS · DC · RST · BL.
  Nur bestücken, wenn ein SPI-TFT kommt. J3 und J4 können parallel genutzt werden.
- **J5 UART**, **J6 Panel-Taster** (parallel zu SW1–SW4, falls die Taster im
  Gehäuse sitzen statt auf der Platine), **J7 Erweiterung**: optional.

## Nächste Schritte in KiCad (8.x)

1. Projekt `spotfam-reader.kicad_pro` öffnen, Schaltplan öffnen, **ERC** laufen lassen.
   Erwartbar sind höchstens Hinweise zu Pin-Typen (alle Pins sind als `passive` definiert).
2. Leiterplatteneditor öffnen und **Tools → Update PCB from Schematic (F8)**.
   KiCad holt die Footprints aus den Standardbibliotheken. Meldet es einen
   fehlenden Footprint oder eine fehlende Pad-Nummer, den Namen in
   `design.py` korrigieren und neu generieren.
   Kritisch zu prüfen sind die Pad-Namen der USB-C-Buchse (A1…B12, S1) und das GND-Pad 41 am Modul.
3. Bauteile nach den gestrichelten Bereichen auf *User.Drawings* platzieren:
   - **U1 mit der Antenne bündig an die Oberkante**, Antennenfläche genau über der Sperrzone.
   - **C3 und C4 direkt an U1 Pin 2 (3V3)**, C1 und C2 direkt an U2.
   - J1 (USB-C) bündig an die linke Kante, U3 (ESD) zwischen J1 und U1.
     D+ und D− kurz und parallel führen, mit gleicher Länge.
   - SW1–SW4 entlang der Unterkante. Wenn das Gehäuse andere Positionen
     verlangt, die Taster verschieben oder J6 nutzen.
4. Routen, von Hand oder mit Freerouting, dann **B** (Zonen füllen) und **DRC**.
5. Fertigungsdaten erzeugen (siehe unten).

## Bestellung bei JLCPCB

**Leiterplatte:** 2 Lagen, 1,6 mm, HASL bleifrei. Gerber über
*Datei → Fertigungsausgaben → Gerber* (Lagen F/B.Cu, F/B.Mask, F/B.SilkS,
F/B.Paste, Edge.Cuts) plus Bohrdatei (Excellon, mm, PTH/NPTH getrennt).
Alles zippen und hochladen.

**SMD-Bestückung (PCBA):** sinnvoll für U1, U2, U3, J1, alle R/C/LEDs.
- BOM: `bom/jlcpcb-bom.csv`
- CPL (Bestückungsdatei): erst nach der Platzierung erzeugbar
  (*Datei → Fertigungsausgaben → Bauteilpositionen*, CSV, mm, nur Oberseite).
  Spalten für JLCPCB umbenennen: `Designator, Mid X, Mid Y, Layer, Rotation`.
- **Die LCSC-Nummern vor der Bestellung im JLCPCB-Katalog prüfen** (Bestand,
  Basic- oder Extended-Part). Gesichert ist C2913204 für das ESP32-S3-WROOM-1-N8R2.
  Die übrigen Nummern sind gängige Standardteile, aber nicht einzeln gegen den
  aktuellen Katalog geprüft.
- Die Drehung der Bauteile in der JLCPCB-Vorschau kontrollieren, vor allem bei
  U2, U3 und J1. Abweichende Drehungen zwischen KiCad und JLCPCB sind der
  häufigste Bestückungsfehler.

**Selbst löten:** Taster SW1–SW6 (THT 6×6 mm), J2 (JST-XH), Stiftleisten
J3–J7, Lötbrücken JP1/JP2.

## Firmware-Anpassungen für den ESP32-S3

Die aktuelle Firmware (`firmware/spotfam_reader`, 0.10.x) ist auf den
ESP32-WROOM-32 ausgelegt. Für diese Platine sind nötig:

1. `config.h`: Pins gemäß Tabelle oben (`PIN_PN532_SDA 8`, `PIN_PN532_SCL 9`,
   `PIN_BTN_NEXT 4`, `PIN_BTN_PREV 5`, `PIN_BTN_VOL_UP 6`,
   `PIN_BTN_VOL_DOWN 7`, `PIN_LED 15`). Optional `PIN_PN532_IRQ 16` und
   `PIN_PN532_RST 17`.
2. `FIRMWARE_BOARD` auf einen neuen Board-Schlüssel setzen, z. B. `esp32-s3`,
   damit OTA-Manifeste nicht auf die falsche Hardware gehen.
3. Build: FQBN `esp32:esp32:esp32s3` mit `CDCOnBoot=cdc`, sonst kommt
   `Serial` nicht über USB-C. Der Release-Workflow
   (`.github/workflows/release-firmware.yml`) braucht einen zweiten Build-Job.
4. Backend: Board `esp32-s3` im Firmware-Manifest zulassen, sonst kommt
   `unsupported_board` (422). Die Flash-Station erkennt den nativen USB
   des S3 bereits (`303A:1001` in `flash_agent/detect.py`).
