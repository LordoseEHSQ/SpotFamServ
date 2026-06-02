// ===========================================================================
// SpotFam RFID Reader  –  ESP32 + MFRC522
//
// Liest RFID-Karten und meldet sie an das SpotFam-Backend, das daraufhin die
// gebundene Playlist auf einem Spotify-Connect-Geraet startet.
// Zwei Taster steuern Vor/Zurueck der laufenden Wiedergabe.
//
// Architektur: ESP -> HTTP -> Backend -> Spotify Web API -> Spotify-Connect-Geraet.
// Es liegen KEINE Spotify-Tokens auf dem ESP (sicher by design).
//
// Konfiguration: secrets.h (Geheimnisse) + config.h (Pinout/Verhalten).
// ===========================================================================
#include <WiFi.h>
#include <HTTPClient.h>
#include <SPI.h>
#include <MFRC522.h>
#include <ArduinoJson.h>

#include "config.h"
#include "secrets.h"

static MFRC522 rfid(PIN_RC522_SS, PIN_RC522_RST);

static unsigned long lastScanMs   = 0;
static unsigned long lastBtnNextMs = 0;
static unsigned long lastBtnPrevMs = 0;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

// Kanonisches UID-Format: Grossbuchstaben-Hex, 2 Zeichen pro Byte, OHNE Trenner.
// Genau diese Zeichenkette muss beim Anlegen der Karte im Backend gespeichert
// werden (Enrollment ueber den Scan-Log: card_uid_raw uebernehmen).
static String uidToHex(const MFRC522::Uid &uid) {
  String out;
  for (byte i = 0; i < uid.size; i++) {
    if (uid.uidByte[i] < 0x10) out += '0';
    out += String(uid.uidByte[i], HEX);
  }
  out.toUpperCase();
  return out;
}

static void blink(int times, int onMs, int offMs) {
  for (int i = 0; i < times; i++) {
    digitalWrite(PIN_LED, HIGH);
    delay(onMs);
    digitalWrite(PIN_LED, LOW);
    if (i < times - 1) delay(offMs);
  }
}

// LED-Feedback aus HTTP-Status + outcome-Feld des Backends.
// Das Backend liefert outcome kanonisch in lowercase (siehe ScanOutcome.php).
static void signalResult(int httpCode, const String &outcome) {
  if (httpCode == 200 && outcome == "success") {
    blink(1, 600, 0);                 // Erfolg: ein langer Blink
  } else if (outcome == "debounced") {
    blink(2, 80, 80);                 // ignorierter Doppelscan: zwei kurze
  } else {
    blink(4, 120, 120);              // Fehler: vier schnelle Blinks
  }
}

static void ensureWifi() {
  if (WiFi.status() == WL_CONNECTED) return;
  Serial.printf("[WiFi] verbinde mit %s ...\n", WIFI_SSID);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - start < WIFI_CONNECT_TIMEOUT_MS) {
    delay(300);
    digitalWrite(PIN_LED, !digitalRead(PIN_LED));  // sanftes Blinken beim Verbinden
  }
  digitalWrite(PIN_LED, LOW);
  if (WiFi.status() == WL_CONNECTED) {
    Serial.printf("[WiFi] verbunden, IP=%s\n", WiFi.localIP().toString().c_str());
  } else {
    Serial.println("[WiFi] FEHLGESCHLAGEN");
  }
}

// Sendet POST <path> mit JSON-Body. Gibt outcome zurueck (oder "" bei Netzfehler).
static String postJson(const char *path, const String &jsonBody) {
  ensureWifi();
  if (WiFi.status() != WL_CONNECTED) return "";

  HTTPClient http;
  String url = String(BACKEND_BASE_URL) + path;
  if (!http.begin(url)) {
    Serial.println("[HTTP] begin() fehlgeschlagen");
    return "";
  }
  http.setTimeout(HTTP_TIMEOUT_MS);
  http.addHeader("Content-Type", "application/json");
  if (strlen(READER_API_KEY) > 0) {
    http.addHeader("X-API-Key", READER_API_KEY);
  }

  int code = http.POST(jsonBody);
  String payload = http.getString();
  Serial.printf("[HTTP] %s -> %d: %s\n", path, code, payload.c_str());

  String outcome = "";
  if (payload.length() > 0) {
    JsonDocument doc;
    if (deserializeJson(doc, payload) == DeserializationError::Ok) {
      outcome = doc["outcome"] | "";
    }
  }
  http.end();
  signalResult(code, outcome);
  return outcome;
}

static void sendScan(const String &cardUid) {
  JsonDocument doc;
  doc["reader_id"] = READER_ID;
  doc["card_uid"]  = cardUid;
  String body;
  serializeJson(doc, body);
  postJson("/api/v1/readers/scan", body);
}

static void sendControl(const char *path) {
  JsonDocument doc;
  doc["reader_id"] = READER_ID;
  String body;
  serializeJson(doc, body);
  postJson(path, body);
}

// ---------------------------------------------------------------------------
// Setup / Loop
// ---------------------------------------------------------------------------
void setup() {
  Serial.begin(SERIAL_BAUD);
  delay(200);

  pinMode(PIN_LED, OUTPUT);
  digitalWrite(PIN_LED, LOW);
  pinMode(PIN_BTN_NEXT, INPUT_PULLUP);
  pinMode(PIN_BTN_PREV, INPUT_PULLUP);

  SPI.begin();             // VSPI: SCK=18, MISO=19, MOSI=23
  rfid.PCD_Init();
  Serial.println("[RFID] MFRC522 initialisiert");

  ensureWifi();
  blink(3, 60, 60);        // Boot abgeschlossen
}

void loop() {
  ensureWifi();

  // --- Taster: NEXT ---
  if (digitalRead(PIN_BTN_NEXT) == LOW && millis() - lastBtnNextMs > BUTTON_DEBOUNCE_MS) {
    lastBtnNextMs = millis();
    Serial.println("[BTN] NEXT");
    sendControl("/api/v1/readers/next");
  }

  // --- Taster: PREV ---
  if (digitalRead(PIN_BTN_PREV) == LOW && millis() - lastBtnPrevMs > BUTTON_DEBOUNCE_MS) {
    lastBtnPrevMs = millis();
    Serial.println("[BTN] PREV");
    sendControl("/api/v1/readers/previous");
  }

  // --- RFID ---
  if (rfid.PICC_IsNewCardPresent() && rfid.PICC_ReadCardSerial()) {
    if (millis() - lastScanMs > SCAN_COOLDOWN_MS) {
      lastScanMs = millis();
      String uid = uidToHex(rfid.uid);
      Serial.printf("[RFID] Karte: %s\n", uid.c_str());
      sendScan(uid);
    }
    rfid.PICC_HaltA();
    rfid.PCD_StopCrypto1();
  }

  delay(20);
}
