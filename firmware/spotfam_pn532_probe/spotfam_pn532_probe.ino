// ===========================================================================
// SpotFam PN532 HW-0 Probe  -  Diagnose-Sketch (KEINE Produktiv-Firmware)
//
// Zweck: HW-0 verifizieren (siehe tasks/plan-esp-consumer-provisioning-ota.md):
//   1. PN532/HW-147 wird vom ESP32 erkannt (Firmware-Version lesbar).
//   2. Eine bekannte Karte wird gelesen.
//   3. Die ausgegebene UID ist identisch zum Pi-PN532-UID-String.
//
// Dieses Sketch loetet/finalisiert nichts. Es ist ein wegwerfbares Pruefwerkzeug,
// um Busmodus (I2C vs. SPI), DIP-Stellung und UID-Gleichheit am realen Modul zu
// belegen, bevor die Zielfirmware entsteht.
//
// UID-Format: identisch zu spotfam_reader (uidToHex): Grossbuchstaben-Hex,
// 2 Zeichen pro Byte, OHNE Trenner. So ist der String direkt mit dem Pi
// vergleichbar (Referenz aus Plan-Verifikationslog: E3D43735).
//
// Bibliothek (Probe): Adafruit PN532 1.3.4 + Adafruit BusIO 1.17.4.
// Finale Library-Wahl wird erst nach erfolgreichem HW-0 entschieden.
// ===========================================================================
#include <Wire.h>
#include <SPI.h>
#include <Adafruit_PN532.h>

// ---------------------------------------------------------------------------
// Buswahl: genau EINE Variante aktiv lassen.
// HW-147-DIP-Schalter muss zum gewaehlten Bus passen (NICHT aus Erinnerung
// raten, Modulaufdruck/Datenblatt pruefen).
// ---------------------------------------------------------------------------
#define PN532_BUS_I2C   1
#define PN532_BUS_SPI   0

#if (PN532_BUS_I2C + PN532_BUS_SPI) != 1
#error "Genau einen Bus aktivieren: PN532_BUS_I2C oder PN532_BUS_SPI"
#endif

// --- Pinout (anpassen an reale Verdrahtung; im HW-0-Log dokumentieren) ------
// I2C-Standard ESP32-WROOM-32 DevKit: SDA=21, SCL=22.
#define PN532_I2C_SDA   21
#define PN532_I2C_SCL   22
// IRQ=-1 => Bibliothek pollt PN532-Status rein ueber I2C (robust ohne IRQ-Draht).
// Nur setzen, wenn IRQ tatsaechlich verdrahtet ist.
#define PN532_IRQ       -1   // optional; -1 = I2C-Polling, keine IRQ-Leitung noetig
#define PN532_RESET     -1   // optional; -1 wenn nicht verdrahtet

// SPI (VSPI): SCK=18, MISO=19, MOSI=23, SS frei waehlbar.
#define PN532_SPI_SS    5

#define SERIAL_BAUD     115200

#if PN532_BUS_I2C
static Adafruit_PN532 nfc(PN532_IRQ, PN532_RESET);
#else
static Adafruit_PN532 nfc(PN532_SPI_SS);
#endif

// Kanonisches UID-Format: exakt wie spotfam_reader.ino::uidToHex.
static String uidToHex(const uint8_t *uid, uint8_t len) {
  String out;
  for (uint8_t i = 0; i < len; i++) {
    if (uid[i] < 0x10) out += '0';
    out += String(uid[i], HEX);
  }
  out.toUpperCase();
  return out;
}

void setup() {
  Serial.begin(SERIAL_BAUD);
  while (!Serial && millis() < 3000) { delay(10); }
  delay(200);

  Serial.println();
  Serial.println("=== SpotFam PN532 HW-0 Probe ===");
#if PN532_BUS_I2C
  Serial.printf("[BUS] I2C (SDA=%d, SCL=%d)\n", PN532_I2C_SDA, PN532_I2C_SCL);
  Wire.begin(PN532_I2C_SDA, PN532_I2C_SCL);
#else
  Serial.printf("[BUS] SPI (SS=%d, SCK=18, MISO=19, MOSI=23)\n", PN532_SPI_SS);
#endif

  nfc.begin();

  uint32_t version = nfc.getFirmwareVersion();
  if (!version) {
    Serial.println("[FAIL] Kein PN532 gefunden. Pruefen: DIP-Modus passt zum");
    Serial.println("       gewaehlten Bus? Verdrahtung VCC/GND/SDA-SCL bzw.");
    Serial.println("       SCK/MISO/MOSI/SS? Pull-ups bei I2C? Stromversorgung?");
    return;
  }

  Serial.printf("[OK] PN532 gefunden. Chip PN5%02X, Firmware %d.%d\n",
                (version >> 24) & 0xFF,
                (version >> 16) & 0xFF,
                (version >> 8) & 0xFF);
  nfc.SAMConfig();
  Serial.println("[READY] Karte auflegen ...");
}

void loop() {
  uint8_t uid[7] = {0};
  uint8_t uidLen = 0;

  if (nfc.readPassiveTargetID(PN532_MIFARE_ISO14443A, uid, &uidLen, 1000)) {
    String hex = uidToHex(uid, uidLen);
    Serial.printf("[CARD] UID=%s (len=%u Bytes)\n", hex.c_str(), uidLen);
    Serial.println("       -> Mit Pi-UID vergleichen (Plan-Log).");
    delay(1500);  // simple Entprellung gegen Mehrfachausgabe
  }
}
