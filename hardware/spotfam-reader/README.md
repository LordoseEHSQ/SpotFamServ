# SpotFam Reader – Platine Rev A

Eigene Platine für den SpotFam-Reader: **ESP32-S3-WROOM-1** direkt bestückt,
USB-C, 3,3-V-Regler, ESD-Schutz, vier Taster, Stecker für den PN532 im
Gehäusedeckel, vorbereitete Display-Anschlüsse (I2C-OLED **oder** SPI-TFT).

> **Status: platziert, nicht fertig geroutet.** Die Dateien wurden ohne KiCad
> generiert. Schaltplan, Netzliste und Pad-Zuordnung sind maschinell gegen
> `tools/design.py` geprüft, ebenso die Abstände der vorverlegten
> Versorgungsbahnen. **Routing der übrigen Netze, ERC und DRC fehlen noch.**
> Vor einer Bestellung unbedingt die Schritte unten durchgehen.

## Dateien

| Datei | Inhalt |
|---|---|
| `tools/design.py` | **Single Source of Truth**: Bauteile, Pins, Netze, LCSC-Nummern |
| `tools/generate.py` | Erzeugt alle Dateien unten (`python3 tools/generate.py`) |
| `tools/pcb.py` | Bestückungsplan (`PLACEMENT`) und vorverlegte Leiterbahnen (`ROUTES`) |
| `footprints/` | Kopien der verwendeten Footprints (KiCad-Bibliothek; ESP32-S3 aus `espressif/kicad-libraries`) |
| `spotfam-reader.kicad_sch` | Schaltplan. Netze hängen über Labels an den Pins |
| `spotfam-reader.kicad_pcb` | Platinenumriss 88,9 × 63,5 mm (Sammelkartenformat, quer), alle Bauteile platziert und mit Netzen, Antennen-Sperrzone, GND-Flächen, vorverlegte 5-V- und 3,3-V-Bahnen |
| `spotfam-reader.kicad_pro` | Projekt mit Netzklassen (Power, USB, Default) und JLCPCB-Regeln |
| `NETLIST.md` | Lesbare Netzliste zum Gegenlesen |
| `bom/jlcpcb-bom.csv` | BOM im JLCPCB-Format (nur SMD-Teile) |
| `bom/full-bom.csv` | Komplette Stückliste inkl. selbst zu lötender Teile |

Bei Änderungen an Pinbelegung oder Bauteilen gilt: `design.py` ändern und
`generate.py` ausführen. Den Schaltplan nicht von Hand umzeichnen, sonst laufen
Doku und KiCad auseinander.

**Achtung:** `generate.py` überschreibt auch `spotfam-reader.kicad_pcb`. Sobald
in KiCad geroutet wurde, ist die `.kicad_pcb` das Original. Danach Änderungen
nur noch in KiCad machen (Schaltplan → **F8**), nicht mehr neu generieren.

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

## Nächste Schritte in KiCad (7 oder 8)

Alle Bauteile sind platziert und mit Netzen versehen:
- **U1 (ESP32-S3)** mit der Antenne bündig an der Oberkante, darunter eine Sperrzone ohne Kupfer.
- **USB-C (J1)** an der linken Kante, daneben der ESD-Schutz U3.
- **Versorgung (U2, C1, C2)** oben links, **C3/C4** direkt an U1 Pin 2.
- **Taster SW1–SW4** unten, **Stecker** rechts, **TFT-Header J4** unter dem Modul.

Die 5-V-Bahn (USB → C1 → Regler) und die 3,3-V-Bahn (Regler → C2 → C3 → C4 →
Modul) sind schon verlegt, 0,6 mm breit. GND läuft über die Kupferflächen auf
beiden Lagen.

1. `spotfam-reader.kicad_pro` öffnen. Im Schaltplan den **ERC** laufen lassen.
   Hinweise zu Pin-Typen sind normal, alle Pins sind als `passive` definiert.
2. Im Leiterplatteneditor **Freerouting** installieren:
   *Werkzeuge → Plugin and Content Manager → Plugins → „Freerouting“*.
   Danach *Werkzeuge → Externe Plugins → Freerouting* starten. Die
   vorhandenen Bahnen bleiben erhalten.
3. **B** drücken (Zonen füllen), dann **DRC** (*Prüfen → Designregelprüfung*).
4. Ergebnis ansehen und kritische Stellen gegebenenfalls von Hand nachziehen:
   - **USB D+/D−** (J1 → U3 → U1): möglichst kurz, beide Leitungen nebeneinander.
     Der ESP32-S3 kann nur USB Full Speed (12 Mbit/s). Impedanz und
     Längenausgleich sind dort unkritisch, ein paar Millimeter Umweg schaden nicht.
   - Keine Bahn unter der Antenne. Die Sperrzone verhindert das ohnehin.
   - Möglichst wenige Durchkontaktierungen in der GND-Fläche direkt unter dem Modul.
5. Fertigungsdaten erzeugen (siehe unten).

Optional zur Kontrolle: *Werkzeuge → Update PCB from Schematic (F8)*. Es
sollte nichts ändern. Das Modul-Footprint stammt aus der Espressif-Bibliothek,
trägt aber die Bibliotheks-ID `RF_Module:ESP32-S3-WROOM-1`. F8 darf es nicht
ersetzen, also **„Replace footprints with those specified in symbol“ nicht
anhaken.**

## Bestellung bei JLCPCB

**Leiterplatte:** 2 Lagen, 1,6 mm, HASL bleifrei. Gerber über
*Datei → Fertigungsausgaben → Gerber* (Lagen F/B.Cu, F/B.Mask, F/B.SilkS,
F/B.Paste, Edge.Cuts) plus Bohrdatei (Excellon, mm, PTH/NPTH getrennt).
Alles zippen und hochladen.

**SMD-Bestückung (PCBA):** sinnvoll für U1, U2, U3, J1, alle R/C/LEDs.
- BOM: `bom/jlcpcb-bom.csv`
- CPL (Bestückungsdatei): *Datei → Fertigungsausgaben → Bauteilpositionen*
  (CSV, mm, nur Oberseite).
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
