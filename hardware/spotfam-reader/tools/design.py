"""
SpotFam Reader Rev A - Single Source of Truth fuer Bauteile, Pins und Netze.

Alle KiCad-Dateien (Schaltplan, Platine, BOM) werden aus diesen Daten von
generate.py erzeugt. Aenderungen an Pinbelegung/Bauteilen IMMER hier machen
und danach `python3 tools/generate.py` ausfuehren.

Pin-Nummern muessen zu den Pad-Nummern der angegebenen KiCad-Standard-
Footprints passen (KiCad 8, offizielle Bibliotheken).
"""

from dataclasses import dataclass, field

NC = None  # Pin bewusst nicht verbunden (no_connect im Schaltplan)


@dataclass
class Part:
    ref: str
    value: str
    footprint: str
    symbol: str                      # Name des eingebetteten Symbols
    left: list                       # [(pin_nummer, pin_name, netz)]
    right: list = field(default_factory=list)
    lcsc: str = ""                   # LCSC-Nummer fuer JLCPCB-Bestueckung
    assembly: str = "JLC"            # "JLC" = SMT bei JLCPCB, "Hand" = selbst loeten
    mpn: str = ""
    note: str = ""


# ---------------------------------------------------------------------------
# GPIO-Belegung ESP32-S3-WROOM-1 (Modul-Pad -> Funktion)
# Strapping-Pins IO0, IO3, IO45, IO46 sind bis auf BOOT (IO0) frei gelassen.
# IO35-37 bleiben frei (bei Octal-PSRAM-Varianten intern belegt).
# ---------------------------------------------------------------------------
U1 = Part(
    ref="U1", value="ESP32-S3-WROOM-1-N8R2", symbol="ESP32-S3-WROOM-1",
    footprint="RF_Module:ESP32-S3-WROOM-1",
    lcsc="C2913204", mpn="ESP32-S3-WROOM-1-N8R2",
    left=[
        ("2", "3V3", "+3V3"),
        ("1", "GND", "GND"),
        ("40", "GND", "GND"),
        ("41", "GND", "GND"),
        ("3", "EN", "EN"),
        ("27", "IO0/BOOT", "BOOT"),
        ("13", "IO19/USB_D-", "USB_DN"),
        ("14", "IO20/USB_D+", "USB_DP"),
        ("36", "RXD0/IO44", "UART_RX"),
        ("37", "TXD0/IO43", "UART_TX"),
        ("4", "IO4", "BTN_NEXT"),
        ("5", "IO5", "BTN_PREV"),
        ("6", "IO6", "BTN_VOLUP"),
        ("7", "IO7", "BTN_VOLDN"),
        ("12", "IO8", "I2C_SDA"),
        ("17", "IO9", "I2C_SCL"),
        ("9", "IO16", "PN532_IRQ"),
        ("10", "IO17", "PN532_RST"),
        ("8", "IO15", "LED_STATUS"),
        ("39", "IO1", "EXP_IO1"),
        ("38", "IO2", "EXP_IO2"),
    ],
    right=[
        ("18", "IO10/FSPICS0", "SPI_CS"),
        ("19", "IO11/FSPID", "SPI_MOSI"),
        ("20", "IO12/FSPICLK", "SPI_SCK"),
        ("21", "IO13/FSPIQ", "SPI_MISO"),
        ("22", "IO14", "TFT_DC"),
        ("23", "IO21", "TFT_RST"),
        ("24", "IO47", "TFT_BL"),
        ("11", "IO18", "EXP_IO18"),
        ("31", "IO38", "EXP_IO38"),
        ("15", "IO3", NC),
        ("16", "IO46", NC),
        ("25", "IO48", NC),
        ("26", "IO45", NC),
        ("28", "IO35", NC),
        ("29", "IO36", NC),
        ("30", "IO37", NC),
        ("32", "IO39", NC),
        ("33", "IO40", NC),
        ("34", "IO41", NC),
        ("35", "IO42", NC),
    ],
)

# USB-C (nur USB 2.0, 16 Pins). Pad-Namen gemaess KiCad-Footprint HRO TYPE-C-31-M-12.
J1 = Part(
    ref="J1", value="USB-C", symbol="USB_C_16P",
    footprint="Connector_USB:USB_C_Receptacle_HRO_TYPE-C-31-M-12",
    lcsc="C165948", mpn="TYPE-C-31-M-12",
    left=[
        ("A4", "VBUS", "+5V"), ("A9", "VBUS", "+5V"),
        ("B4", "VBUS", "+5V"), ("B9", "VBUS", "+5V"),
        ("A5", "CC1", "CC1"), ("B5", "CC2", "CC2"),
        ("A8", "SBU1", NC), ("B8", "SBU2", NC),
    ],
    right=[
        ("A6", "D+", "USB_DP"), ("B6", "D+", "USB_DP"),
        ("A7", "D-", "USB_DN"), ("B7", "D-", "USB_DN"),
        ("A1", "GND", "GND"), ("A12", "GND", "GND"),
        ("B1", "GND", "GND"), ("B12", "GND", "GND"),
        ("S1", "SHIELD", "GND"),
    ],
)

U2 = Part(
    ref="U2", value="AMS1117-3.3", symbol="AMS1117",
    footprint="Package_TO_SOT_SMD:SOT-223-3_TabPin2",
    lcsc="C6186", mpn="AMS1117-3.3",
    left=[("3", "VIN", "+5V"), ("1", "GND", "GND")],
    right=[("2", "VOUT", "+3V3")],
)

U3 = Part(
    ref="U3", value="USBLC6-2SC6", symbol="USBLC6-2SC6",
    footprint="Package_TO_SOT_SMD:SOT-23-6",
    lcsc="C7519", mpn="USBLC6-2SC6", note="ESD-Schutz USB",
    left=[("1", "IO1", "USB_DN"), ("3", "IO2", "USB_DP"), ("2", "GND", "GND")],
    right=[("6", "IO1", "USB_DN"), ("4", "IO2", "USB_DP"), ("5", "VBUS", "+5V")],
)


def two_pin(ref, value, fp, n1, n2, lcsc="", assembly="JLC", symbol="R", names=("1", "2"), note="", mpn=""):
    return Part(ref=ref, value=value, footprint=fp, symbol=symbol,
                left=[("1", names[0], n1)], right=[("2", names[1], n2)],
                lcsc=lcsc, assembly=assembly, note=note, mpn=mpn)


R0603 = "Resistor_SMD:R_0603_1608Metric"
C0603 = "Capacitor_SMD:C_0603_1608Metric"
C0805 = "Capacitor_SMD:C_0805_2012Metric"
LED0603 = "LED_SMD:LED_0603_1608Metric"
SW6MM = "Button_Switch_THT:SW_PUSH_6mm"

PASSIVES = [
    # USB-C: CC-Pulldowns, sonst liefern C-zu-C-Kabel keine Spannung
    two_pin("R1", "5.1k", R0603, "CC1", "GND", "C23186", note="USB-C CC1"),
    two_pin("R2", "5.1k", R0603, "CC2", "GND", "C23186", note="USB-C CC2"),
    # EN-RC gemaess Espressif-Hardware-Design-Guide
    two_pin("R3", "10k", R0603, "+3V3", "EN", "C25804", note="EN Pull-up"),
    two_pin("C5", "1uF", C0603, "EN", "GND", "C15849", symbol="C", note="EN Verzoegerung"),
    two_pin("R4", "10k", R0603, "+3V3", "BOOT", "C25804", note="BOOT Pull-up"),
    # Versorgung
    two_pin("C1", "10uF", C0805, "+5V", "GND", "C15850", symbol="C", note="LDO Eingang"),
    two_pin("C2", "22uF", C0805, "+3V3", "GND", "C45783", symbol="C", note="LDO Ausgang"),
    two_pin("C3", "22uF", C0805, "+3V3", "GND", "C45783", symbol="C", note="Modul Bulk, nah an U1 Pin 2"),
    two_pin("C4", "100nF", C0603, "+3V3", "GND", "C14663", symbol="C", note="Modul HF, nah an U1 Pin 2"),
    two_pin("C6", "100nF", C0603, "+3V3", "GND", "C14663", symbol="C", note="Nah an J2 (PN532)"),
    # I2C-Pull-ups (Bus geht per Kabel in den Deckel)
    two_pin("R7", "4.7k", R0603, "+3V3", "I2C_SDA", "C23162", note="I2C SDA Pull-up"),
    two_pin("R8", "4.7k", R0603, "+3V3", "I2C_SCL", "C23162", note="I2C SCL Pull-up"),
    # LEDs
    two_pin("R5", "1k", R0603, "LED_STATUS", "LED_STAT_A", "C21190"),
    two_pin("D1", "LED gruen", LED0603, "GND", "LED_STAT_A", "C72043", symbol="LED", names=("K", "A"), note="Status (IO15)"),
    two_pin("R6", "1k", R0603, "+3V3", "LED_PWR_A", "C21190"),
    two_pin("D2", "LED rot", LED0603, "GND", "LED_PWR_A", "C2286", symbol="LED", names=("K", "A"), note="Power"),
    # Taster - THT 6x6 mm, selbst loeten
    two_pin("SW1", "NEXT", SW6MM, "BTN_NEXT", "GND", assembly="Hand", symbol="SW"),
    two_pin("SW2", "PREV", SW6MM, "BTN_PREV", "GND", assembly="Hand", symbol="SW"),
    two_pin("SW3", "VOL+", SW6MM, "BTN_VOLUP", "GND", assembly="Hand", symbol="SW"),
    two_pin("SW4", "VOL-", SW6MM, "BTN_VOLDN", "GND", assembly="Hand", symbol="SW"),
    two_pin("SW5", "RESET", SW6MM, "EN", "GND", assembly="Hand", symbol="SW"),
    two_pin("SW6", "BOOT", SW6MM, "BOOT", "GND", assembly="Hand", symbol="SW"),
]

HDR = "Connector_PinHeader_2.54mm:PinHeader_1x{n:02d}_P2.54mm_Vertical"

CONNECTORS = [
    Part(ref="J2", value="PN532 (HW-147, I2C)", symbol="Conn_06",
         footprint="Connector_JST:JST_XH_B6B-XH-A_1x06_P2.50mm_Vertical",
         assembly="Hand", note="Kabel zum Reader im Deckel",
         left=[("1", "GND", "GND"), ("2", "VCC 3V3", "+3V3"), ("3", "SDA", "I2C_SDA"),
               ("4", "SCL", "I2C_SCL"), ("5", "IRQ", "PN532_IRQ"), ("6", "RSTO", "PN532_RST")]),
    Part(ref="J3", value="OLED I2C", symbol="Conn_04", footprint=HDR.format(n=4),
         assembly="Hand", note="Pin 1/2 per JP1/JP2 auf GND/VCC einstellbar",
         left=[("1", "P1 (GND/VCC)", "OLED_P1"), ("2", "P2 (VCC/GND)", "OLED_P2"),
               ("3", "SCL", "I2C_SCL"), ("4", "SDA", "I2C_SDA")]),
    Part(ref="JP1", value="OLED P1", symbol="SolderJumper3",
         footprint="Jumper:SolderJumper_3_Open_RoundedPad1.0x1.5mm",
         assembly="Hand", note="1-2 = GND (Standard SSD1306), 2-3 = VCC",
         left=[("1", "A", "GND"), ("2", "C", "OLED_P1"), ("3", "B", "+3V3")]),
    Part(ref="JP2", value="OLED P2", symbol="SolderJumper3",
         footprint="Jumper:SolderJumper_3_Open_RoundedPad1.0x1.5mm",
         assembly="Hand", note="1-2 = VCC (Standard SSD1306), 2-3 = GND",
         left=[("1", "A", "+3V3"), ("2", "C", "OLED_P2"), ("3", "B", "GND")]),
    Part(ref="J4", value="TFT SPI", symbol="Conn_09", footprint=HDR.format(n=9),
         assembly="Hand", note="Optional, fuer spaeteres SPI-TFT (ST7789/ILI9341)",
         left=[("1", "GND", "GND"), ("2", "VCC 3V3", "+3V3"), ("3", "SCK", "SPI_SCK"),
               ("4", "MOSI/SDA", "SPI_MOSI"), ("5", "MISO", "SPI_MISO"), ("6", "CS", "SPI_CS"),
               ("7", "DC", "TFT_DC"), ("8", "RST", "TFT_RST"), ("9", "BL", "TFT_BL")]),
    Part(ref="J5", value="UART Debug", symbol="Conn_03", footprint=HDR.format(n=3),
         assembly="Hand", note="Optional",
         left=[("1", "GND", "GND"), ("2", "TX (ESP)", "UART_TX"), ("3", "RX (ESP)", "UART_RX")]),
    Part(ref="J6", value="Panel-Taster", symbol="Conn_05", footprint=HDR.format(n=5),
         assembly="Hand", note="Optional, parallel zu SW1-SW4 fuer Gehaeusetaster",
         left=[("1", "GND", "GND"), ("2", "NEXT", "BTN_NEXT"), ("3", "PREV", "BTN_PREV"),
               ("4", "VOL+", "BTN_VOLUP"), ("5", "VOL-", "BTN_VOLDN")]),
    Part(ref="J7", value="Erweiterung", symbol="Conn_06", footprint=HDR.format(n=6),
         assembly="Hand", note="Optional, freie GPIOs (z.B. Drehencoder)",
         left=[("1", "3V3", "+3V3"), ("2", "GND", "GND"), ("3", "IO1", "EXP_IO1"),
               ("4", "IO2", "EXP_IO2"), ("5", "IO18", "EXP_IO18"), ("6", "IO38", "EXP_IO38")]),
]

MOUNTING = [
    Part(ref=f"H{i}", value="M3", symbol="MountingHole",
         footprint="MountingHole:MountingHole_3.2mm_M3", assembly="-", left=[])
    for i in range(1, 5)
]

PARTS = [U1, J1, U2, U3] + PASSIVES + CONNECTORS + MOUNTING
