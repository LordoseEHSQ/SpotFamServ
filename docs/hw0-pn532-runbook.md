# HW-0 Runbook: PN532/HW-147 am ESP32 verifizieren

> **Status-Gate:** HW-0 ist ein **physischer** Schritt und blockiert Sprint-Done
> (siehe `tasks/plan-esp-consumer-provisioning-ota.md`). Dieser Runbook fuehrt die
> Verifikation durch und liefert die Evidence-Tabelle, die der Plan verlangt.
> Erst nach erfolgreichem HW-0 darf die Zielfirmware (PN532, NVS, Captive Portal,
> OTA) implementiert werden.

Werkzeug: `firmware/spotfam_pn532_probe/spotfam_pn532_probe.ino` (wegwerfbares
Diagnose-Sketch, kompiliert in CI gegen `esp32:esp32@3.3.8` +
`Adafruit PN532@1.3.4` + `Adafruit BusIO@1.17.4`).

## 0. Voraussetzungen

- ESP32-WROOM-32 DevKit, PN532/HW-147-Modul, bekannte Testkarte.
- Referenz-UID vom Pi-PN532 fuer dieselbe Karte (Plan-Log: `E3D43735`).
- **DIP-Schalter am HW-147 passend zum gewaehlten Bus** stellen. NICHT aus
  Erinnerung raten: Modulaufdruck/Datenblatt pruefen.
  - HW-147 typischerweise: beide DIP OFF = SPI, ein definierter Schaltzustand = I2C.
    Tatsaechliche Stellung am Modul ablesen und unten dokumentieren.

## 1. USB-Durchreichung WSL2 (nur falls aus WSL2 geflasht wird)

WSL2 sieht USB-Geraete nicht automatisch. Einmalig am **Windows-Host**
(PowerShell als Admin):

```powershell
winget install usbipd
usbipd list                              # BUSID des CP210x/CH340 notieren
usbipd bind   --busid <BUSID>
usbipd attach --wsl --busid <BUSID>
```

Pruefen in WSL2: `ls /dev/ttyUSB*` bzw. `arduino-cli board list`.
Alternativ: direkt auf Windows mit Arduino IDE / `arduino-cli` flashen.

## 2. Bus waehlen und kompilieren

Im Sketch genau einen Bus aktivieren (Default I2C):

```c
#define PN532_BUS_I2C   1   // I2C: SDA=21, SCL=22
#define PN532_BUS_SPI   0   // SPI: SS=5, SCK=18, MISO=19, MOSI=23
```

```bash
cd firmware/spotfam_pn532_probe
arduino-cli compile --fqbn esp32:esp32:esp32 .
```

## 3. Flashen und Serial lesen

```bash
arduino-cli upload  -p /dev/ttyUSB0 --fqbn esp32:esp32:esp32 .
arduino-cli monitor -p /dev/ttyUSB0 -c baudrate=115200
```

Erwartete Ausgabe:

```
=== SpotFam PN532 HW-0 Probe ===
[BUS] I2C (SDA=21, SCL=22)
[OK] PN532 gefunden. Chip PN532, Firmware 1.6
[READY] Karte auflegen ...
[CARD] UID=E3D43735 (len=4 Bytes)
```

- `[FAIL] Kein PN532 gefunden` → DIP-Modus passt nicht zum gewaehlten Bus,
  Verdrahtung/Pull-ups/Stromversorgung pruefen, ggf. anderen Bus testen.

## 4. UID-Vergleich (Akzeptanz)

Der `UID=`-String muss **exakt** dem Pi-PN532-UID-String fuer dieselbe Karte
entsprechen (gleiches kanonisches Format: Grossbuchstaben-Hex ohne Trenner).
Abweichung = Akzeptanzkriterium 3 nicht erfuellt → stoppen, Ursache klaeren.

## 5. Evidence-Tabelle (ausfuellen und in den Plan uebernehmen)

| Pruefpunkt | Ergebnis |
| --- | --- |
| Datum | |
| Board-Modell (ESP32) | |
| PN532/HW-147-Modulaufdruck | |
| Gewaehlter Bus + Begruendung | |
| DIP-Schalterstellung (real abgelesen) | |
| Pinout VCC/GND/(SDA,SCL \| SCK,MISO,MOSI,SS)/IRQ/RST | |
| PN532 erkannt (Firmware-Version) | |
| Test-UID Pi | `E3D43735` |
| Test-UID ESP (Probe) | |
| UID identisch? | |
| ESP32-Flashgroesse | |
| OTA-faehige Partitionstabelle vorhanden? | |
| Offene Hardware-Abweichungen | |

## 6. Danach

Bei vollstaendig gruener Tabelle:
1. Evidence in `tasks/plan-esp-consumer-provisioning-ota.md` (Verifikations-Log)
   und Gate A eintragen.
2. Bus-/Pinout-Entscheidung in `tasks/decisions.md` festhalten.
3. Erst dann Phase 1 (NVS/Provisioning-Firmware) starten.
