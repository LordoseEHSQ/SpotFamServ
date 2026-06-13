// ===========================================================================
// SpotFam RFID Reader  –  ESP32 + PN532/HW-147
//
// Liest RFID-Karten per PN532/I2C und meldet sie an das SpotFam-Backend.
// Spotify-Tokens bleiben ausschliesslich im Backend.
//
// Konfiguration:
// - Produktiv: NVS-Namespace `spotfam` (Flash-Agent / Claim-Flow)
// - Dev/Factory-Fallback: secrets.h (git-ignoriert)
// ===========================================================================
#include <ArduinoJson.h>
#include <HTTPClient.h>
#include <Preferences.h>
#include <Update.h>
#include <WiFi.h>
#include <DNSServer.h>
#include <WebServer.h>
#include <Wire.h>
#include <esp_task_wdt.h>
#include <Adafruit_PN532.h>
#include <mbedtls/sha256.h>

#include "config.h"
#include "secrets.h"

#ifndef READER_CLAIM_CODE
#define READER_CLAIM_CODE ""
#endif

#ifndef FW_CHANNEL
#define FW_CHANNEL DEFAULT_FW_CHANNEL
#endif

static Adafruit_PN532 nfc(PIN_PN532_IRQ, PIN_PN532_RST);
static Preferences prefs;
static DNSServer dnsServer;
static WebServer setupServer(80);

struct ReaderConfig {
  String wifiSsid;
  String wifiPassword;
  String backendUrl;
  String readerId;
  String readerApiKey;
  String claimCode;
  String fwChannel;
};

static ReaderConfig cfg;
static bool pn532Ready = false;
static bool portalActive = false;
static unsigned long lastScanMs = 0;
static unsigned long lastPn532InitMs = 0;
static unsigned long lastBackendAttemptMs = 0;
static unsigned long lastOtaCheckMs = 0;
static unsigned long lastBtnNextMs = 0;
static unsigned long lastBtnPrevMs = 0;
static unsigned long lastBtnVolUpMs = 0;
static unsigned long lastBtnVolDownMs = 0;
static bool cardWasPresent = false;
static unsigned long cardAbsentSinceMs = 0;

static String nvsString(const char *key, const char *fallback) {
  String value = prefs.getString(key, "");
  return value.length() > 0 ? value : String(fallback);
}

static String nvsStringAlias(const char *primaryKey, const char *legacyKey, const char *fallback) {
  String value = prefs.getString(primaryKey, "");
  if (value.length() > 0) return value;
  value = prefs.getString(legacyKey, "");
  return value.length() > 0 ? value : String(fallback);
}

static void loadConfig() {
  prefs.begin("spotfam", false);
  cfg.wifiSsid = nvsString("wifi_ssid", WIFI_SSID);
  cfg.wifiPassword = nvsStringAlias("wifi_password", "wifi_pass", WIFI_PASSWORD);
  cfg.backendUrl = nvsString("backend_url", BACKEND_BASE_URL);
  cfg.readerId = nvsString("reader_id", READER_ID);
  cfg.readerApiKey = nvsStringAlias("reader_api_key", "reader_key", READER_API_KEY);
  cfg.claimCode = nvsString("claim_code", READER_CLAIM_CODE);
  cfg.fwChannel = nvsStringAlias("fw_channel", "ota_channel", FW_CHANNEL);
  if (cfg.claimCode.length() > 0 && !cfg.claimCode.startsWith("DEIN_")) {
    cfg.readerId = "";
    cfg.readerApiKey = "";
  }
  cfg.backendUrl.trim();
  while (cfg.backendUrl.endsWith("/")) {
    cfg.backendUrl.remove(cfg.backendUrl.length() - 1);
  }
  if (cfg.fwChannel.length() == 0) {
    cfg.fwChannel = DEFAULT_FW_CHANNEL;
  }
}

static bool hasRuntimeConfig() {
  bool hasWifi = cfg.wifiSsid.length() > 0 && !cfg.wifiSsid.startsWith("DEIN_");
  bool hasReaderKey = cfg.readerApiKey.length() > 0 && !cfg.readerApiKey.startsWith("DEIN_");
  bool hasClaim = cfg.claimCode.length() > 0 && !cfg.claimCode.startsWith("DEIN_");
  return hasWifi
    && cfg.backendUrl.length() > 0
    && ((cfg.readerId.length() > 0 && hasReaderKey) || hasClaim);
}

static void handlePortalRoot() {
  String html = F(
    "<!doctype html><html><head><meta name='viewport' content='width=device-width,initial-scale=1'>"
    "<title>SpotFam Reader Setup</title></head><body>"
    "<h1>SpotFam Reader Setup</h1>"
    "<form method='post' action='/save'>"
    "<label>WLAN SSID<br><input name='ssid' required></label><br><br>"
    "<label>WLAN Passwort<br><input name='pass' type='password'></label><br><br>"
    "<label>Backend URL<br><input name='backend' value='http://192.168.1.91:8080' required></label><br><br>"
    "<label>Claim-Code<br><input name='claim' required></label><br><br>"
    "<label>Firmware-Kanal<br><input name='channel' value='stable'></label><br><br>"
    "<button type='submit'>Speichern und neu starten</button>"
    "</form></body></html>"
  );
  setupServer.send(200, "text/html; charset=utf-8", html);
}

static void handlePortalSave() {
  String ssid = setupServer.arg("ssid");
  String pass = setupServer.arg("pass");
  String backend = setupServer.arg("backend");
  String claim = setupServer.arg("claim");
  String channel = setupServer.arg("channel");
  ssid.trim();
  backend.trim();
  claim.trim();
  channel.trim();

  if (ssid.length() == 0 || backend.length() == 0 || claim.length() == 0) {
    setupServer.send(400, "text/plain", "SSID, Backend URL und Claim-Code sind Pflichtfelder.");
    return;
  }

  prefs.putString("wifi_ssid", ssid);
  prefs.putString("wifi_password", pass);
  prefs.putString("backend_url", backend);
  prefs.putString("claim_code", claim);
  prefs.putString("fw_channel", channel.length() > 0 ? channel : DEFAULT_FW_CHANNEL);
  prefs.remove("reader_id");
  prefs.remove("reader_api_key");

  setupServer.send(200, "text/plain", "Gespeichert. Reader startet neu.");
  delay(500);
  ESP.restart();
}

static void startProvisioningPortal() {
  char suffix[7];
  snprintf(suffix, sizeof(suffix), "%06llX", ESP.getEfuseMac() & 0xFFFFFFULL);
  String apName = String("SpotFam-Reader-") + suffix;

  WiFi.mode(WIFI_AP);
  WiFi.softAP(apName.c_str());
  dnsServer.start(53, "*", WiFi.softAPIP());
  setupServer.on("/", HTTP_GET, handlePortalRoot);
  setupServer.on("/save", HTTP_POST, handlePortalSave);
  setupServer.onNotFound(handlePortalRoot);
  setupServer.begin();
  portalActive = true;
  Serial.printf("[SETUP] Portal aktiv: SSID=%s URL=http://%s\n", apName.c_str(), WiFi.softAPIP().toString().c_str());
  blink(2, 500, 200);
}

static void blink(int times, int onMs, int offMs) {
  for (int i = 0; i < times; i++) {
    digitalWrite(PIN_LED, HIGH);
    delay(onMs);
    digitalWrite(PIN_LED, LOW);
    if (i < times - 1) delay(offMs);
  }
}

static String uidToHex(const uint8_t *uid, uint8_t len) {
  String out;
  for (uint8_t i = 0; i < len; i++) {
    if (uid[i] < 0x10) out += '0';
    out += String(uid[i], HEX);
  }
  out.toUpperCase();
  return out;
}

static String absoluteUrl(const String &urlOrPath) {
  if (urlOrPath.startsWith("http://") || urlOrPath.startsWith("https://")) {
    return urlOrPath;
  }
  if (urlOrPath.startsWith("/")) {
    return cfg.backendUrl + urlOrPath;
  }
  return cfg.backendUrl + "/" + urlOrPath;
}

static String sha256Hex(const uint8_t hash[32]) {
  static const char *hex = "0123456789abcdef";
  String out;
  out.reserve(64);
  for (int i = 0; i < 32; i++) {
    out += hex[(hash[i] >> 4) & 0x0F];
    out += hex[hash[i] & 0x0F];
  }
  return out;
}

static bool ensureWifi() {
  if (WiFi.status() == WL_CONNECTED) return true;
  if (cfg.wifiSsid.length() == 0) {
    Serial.println("[WiFi] keine SSID konfiguriert");
    blink(3, 80, 80);
    return false;
  }

  Serial.printf("[WiFi] verbinde mit %s ...\n", cfg.wifiSsid.c_str());
  WiFi.mode(WIFI_STA);
  WiFi.begin(cfg.wifiSsid.c_str(), cfg.wifiPassword.c_str());

  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - start < WIFI_CONNECT_TIMEOUT_MS) {
    delay(300);
    digitalWrite(PIN_LED, !digitalRead(PIN_LED));
  }
  digitalWrite(PIN_LED, LOW);

  if (WiFi.status() == WL_CONNECTED) {
    Serial.printf("[WiFi] verbunden, IP=%s\n", WiFi.localIP().toString().c_str());
    return true;
  }

  Serial.println("[WiFi] FEHLGESCHLAGEN");
  blink(4, 80, 80);
  return false;
}

static int requestJson(const char *method, const String &path, const String &body, JsonDocument *responseDoc) {
  if (!ensureWifi()) return -1;
  if (cfg.backendUrl.length() == 0) {
    Serial.println("[HTTP] keine Backend-URL konfiguriert");
    return -1;
  }

  HTTPClient http;
  String url = absoluteUrl(path);
  if (!http.begin(url)) {
    Serial.println("[HTTP] begin() fehlgeschlagen");
    return -1;
  }
  http.setTimeout(HTTP_TIMEOUT_MS);
  http.addHeader("Content-Type", "application/json");
  if (cfg.readerApiKey.length() > 0) {
    http.addHeader("X-API-Key", cfg.readerApiKey);
  }

  int code = 0;
  if (strcmp(method, "POST") == 0) {
    code = http.POST(body);
  } else {
    code = http.GET();
  }

  String payload = http.getString();
  Serial.printf("[HTTP] %s %s -> %d\n", method, path.c_str(), code);

  if (responseDoc != nullptr && payload.length() > 0) {
    DeserializationError err = deserializeJson(*responseDoc, payload);
    if (err != DeserializationError::Ok) {
      Serial.printf("[HTTP] JSON unlesbar: %s\n", err.c_str());
    }
  }
  http.end();
  return code;
}

static bool activateClaimIfNeeded() {
  if (cfg.readerId.length() > 0 && cfg.readerApiKey.length() > 0) return true;
  if (cfg.claimCode.length() == 0) return false;
  if (millis() - lastBackendAttemptMs < BACKEND_RETRY_MS) return false;
  lastBackendAttemptMs = millis();

  JsonDocument body;
  body["device_nonce"] = WiFi.macAddress();
  body["board"] = FIRMWARE_BOARD;
  body["firmware_version"] = FIRMWARE_VERSION;
  String serialized;
  serializeJson(body, serialized);

  JsonDocument response;
  String path = "/api/v1/readers/claims/" + cfg.claimCode + "/activate";
  int code = requestJson("POST", path, serialized, &response);
  if (code != 201) {
    Serial.printf("[CLAIM] Aktivierung fehlgeschlagen: HTTP %d\n", code);
    blink(5, 80, 80);
    return false;
  }

  cfg.readerId = response["reader_id"] | "";
  cfg.readerApiKey = response["api_key"] | "";
  cfg.fwChannel = response["fw_channel"] | cfg.fwChannel;
  if (cfg.readerId.length() == 0 || cfg.readerApiKey.length() == 0) {
    Serial.println("[CLAIM] Antwort ohne reader_id/api_key");
    return false;
  }

  prefs.putString("reader_id", cfg.readerId);
  prefs.putString("reader_api_key", cfg.readerApiKey);
  prefs.putString("fw_channel", cfg.fwChannel);
  prefs.remove("claim_code");
  cfg.claimCode = "";
  Serial.printf("[CLAIM] aktiviert, reader_id=%s\n", cfg.readerId.c_str());
  blink(2, 250, 120);
  return true;
}

static bool initPn532() {
  if (pn532Ready && millis() - lastPn532InitMs < PN532_REINIT_MS) return true;
  lastPn532InitMs = millis();

  Serial.printf("[RFID] PN532 I2C init SDA=%d SCL=%d\n", PIN_PN532_SDA, PIN_PN532_SCL);
  Wire.end();
  delay(10);
  Wire.begin(PIN_PN532_SDA, PIN_PN532_SCL);
  nfc.begin();
  uint32_t version = nfc.getFirmwareVersion();
  if (!version) {
    pn532Ready = false;
    Serial.println("[RFID] PN532 nicht gefunden");
    blink(6, 60, 60);
    return false;
  }

  nfc.SAMConfig();
  pn532Ready = true;
  Serial.printf("[RFID] PN532 gefunden. Chip PN5%02X, Firmware %d.%d\n",
                (version >> 24) & 0xFF,
                (version >> 16) & 0xFF,
                (version >> 8) & 0xFF);
  return true;
}

static void signalScanResult(int httpCode, const String &outcome) {
  if (httpCode == 200 && outcome == "success") {
    blink(1, 600, 0);
  } else if (outcome == "debounced") {
    blink(2, 80, 80);
  } else {
    blink(4, 120, 120);
  }
}

static void sendScan(const String &cardUid) {
  if (!activateClaimIfNeeded()) {
    Serial.println("[SCAN] Reader nicht provisioniert");
    blink(5, 100, 100);
    return;
  }

  JsonDocument doc;
  doc["reader_id"] = cfg.readerId;
  doc["card_uid"] = cardUid;
  String body;
  serializeJson(doc, body);

  JsonDocument response;
  int code = requestJson("POST", "/api/v1/readers/scan", body, &response);
  String outcome = response["outcome"] | "";
  Serial.printf("[SCAN] uid=%s outcome=%s\n", cardUid.c_str(), outcome.c_str());
  signalScanResult(code, outcome);
}

static void sendControl(const char *path) {
  if (!activateClaimIfNeeded()) return;
  JsonDocument doc;
  doc["reader_id"] = cfg.readerId;
  String body;
  serializeJson(doc, body);
  JsonDocument response;
  requestJson("POST", path, body, &response);
}

static int compareSemver(const String &a, const String &b) {
  int ai[3] = {0, 0, 0};
  int bi[3] = {0, 0, 0};
  sscanf(a.c_str(), "%d.%d.%d", &ai[0], &ai[1], &ai[2]);
  sscanf(b.c_str(), "%d.%d.%d", &bi[0], &bi[1], &bi[2]);
  for (int i = 0; i < 3; i++) {
    if (ai[i] < bi[i]) return -1;
    if (ai[i] > bi[i]) return 1;
  }
  return 0;
}

static bool performOta(const String &downloadUrl, const String &expectedSha256, int expectedSize) {
  HTTPClient http;
  String url = absoluteUrl(downloadUrl);
  if (!http.begin(url)) return false;
  http.setTimeout(HTTP_TIMEOUT_MS);
  if (cfg.readerApiKey.length() > 0) {
    http.addHeader("X-API-Key", cfg.readerApiKey);
  }

  int code = http.GET();
  if (code != 200) {
    Serial.printf("[OTA] Download HTTP %d\n", code);
    http.end();
    return false;
  }

  int contentLength = http.getSize();
  int updateSize = expectedSize > 0 ? expectedSize : contentLength;
  if (updateSize <= 0 || !Update.begin(updateSize)) {
    Serial.printf("[OTA] Update.begin fehlgeschlagen: %s\n", Update.errorString());
    http.end();
    return false;
  }

  mbedtls_sha256_context sha;
  mbedtls_sha256_init(&sha);
  mbedtls_sha256_starts(&sha, 0);

  WiFiClient *stream = http.getStreamPtr();
  uint8_t buffer[1024];
  int writtenTotal = 0;
  unsigned long lastDataMs = millis();
  while (http.connected() && (contentLength < 0 || writtenTotal < contentLength)) {
    size_t available = stream->available();
    if (available > 0) {
      int readLen = stream->readBytes(buffer, min(available, sizeof(buffer)));
      if (readLen > 0) {
        mbedtls_sha256_update(&sha, buffer, readLen);
        size_t written = Update.write(buffer, readLen);
        if (written != (size_t) readLen) {
          Serial.printf("[OTA] Schreibfehler: %s\n", Update.errorString());
          Update.abort();
          http.end();
          mbedtls_sha256_free(&sha);
          return false;
        }
        writtenTotal += readLen;
        lastDataMs = millis();
      }
    } else {
      if (millis() - lastDataMs > HTTP_TIMEOUT_MS) {
        Serial.println("[OTA] Timeout beim Download");
        Update.abort();
        http.end();
        mbedtls_sha256_free(&sha);
        return false;
      }
      delay(10);
    }
  }

  uint8_t hash[32];
  mbedtls_sha256_finish(&sha, hash);
  mbedtls_sha256_free(&sha);
  String actualSha256 = sha256Hex(hash);
  String expected = expectedSha256;
  expected.toLowerCase();
  if (actualSha256 != expected) {
    Serial.println("[OTA] sha256 mismatch");
    Update.abort();
    http.end();
    return false;
  }

  esp_task_wdt_delete(NULL);
  if (!Update.end(true)) {
    Serial.printf("[OTA] Update.end fehlgeschlagen: %s\n", Update.errorString());
    http.end();
    return false;
  }

  http.end();
  Serial.printf("[OTA] Update erfolgreich (%d Bytes), reboot\n", writtenTotal);
  blink(3, 250, 100);
  ESP.restart();
  return true;
}

static void checkOta(bool force = false) {
  if (!force && millis() - lastOtaCheckMs < OTA_CHECK_INTERVAL_MS) return;
  lastOtaCheckMs = millis();
  if (!activateClaimIfNeeded()) return;

  String path = "/api/v1/readers/firmware/manifest?board=" + String(FIRMWARE_BOARD)
    + "&channel=" + cfg.fwChannel
    + "&current_version=" + String(FIRMWARE_VERSION)
    + (cfg.readerId.length() > 0 ? ("&reader_id=" + cfg.readerId) : String(""));

  JsonDocument manifest;
  int code = requestJson("GET", path, "", &manifest);
  if (code == 204) {
    Serial.println("[OTA] kein Update");
    return;
  }
  if (code != 200) {
    Serial.printf("[OTA] Manifest HTTP %d\n", code);
    return;
  }

  String board = manifest["board"] | "";
  String channel = manifest["channel"] | "";
  String version = manifest["version"] | "";
  String minVersion = manifest["min_version"] | "0.0.0";
  String downloadUrl = manifest["download_url"] | "";
  String sha256 = manifest["sha256"] | "";
  int sizeBytes = manifest["size_bytes"] | 0;

  if (board != FIRMWARE_BOARD || channel != cfg.fwChannel || downloadUrl.length() == 0 || sha256.length() != 64) {
    Serial.println("[OTA] Manifest ungueltig");
    return;
  }
  if (compareSemver(version, FIRMWARE_VERSION) <= 0 || compareSemver(FIRMWARE_VERSION, minVersion) < 0) {
    Serial.println("[OTA] Version abgelehnt");
    return;
  }

  Serial.printf("[OTA] Update %s verfuegbar\n", version.c_str());
  performOta(downloadUrl, sha256, sizeBytes);
}

void setup() {
  Serial.begin(SERIAL_BAUD);
  delay(200);
  pinMode(PIN_LED, OUTPUT);
  digitalWrite(PIN_LED, LOW);

#if SPOTFAM_ENABLE_BUTTONS
  pinMode(PIN_BTN_NEXT, INPUT_PULLUP);
  pinMode(PIN_BTN_PREV, INPUT_PULLUP);
  pinMode(PIN_BTN_VOL_UP, INPUT_PULLUP);
  pinMode(PIN_BTN_VOL_DOWN, INPUT_PULLUP);
#endif

  loadConfig();
  Serial.printf("[BOOT] SpotFam Reader %s board=%s channel=%s\n",
                FIRMWARE_VERSION, FIRMWARE_BOARD, cfg.fwChannel.c_str());
  if (!hasRuntimeConfig()) {
    startProvisioningPortal();
    return;
  }
  ensureWifi();
  initPn532();
  activateClaimIfNeeded();
  checkOta(true);
  blink(3, 60, 60);
}

void loop() {
  if (portalActive) {
    dnsServer.processNextRequest();
    setupServer.handleClient();
    delay(10);
    return;
  }

  ensureWifi();
  activateClaimIfNeeded();
  checkOta(false);

#if SPOTFAM_ENABLE_BUTTONS
  if (digitalRead(PIN_BTN_NEXT) == LOW && millis() - lastBtnNextMs > BUTTON_DEBOUNCE_MS) {
    lastBtnNextMs = millis();
    Serial.println("[BTN] NEXT");
    sendControl("/api/v1/readers/next");
  }
  if (digitalRead(PIN_BTN_PREV) == LOW && millis() - lastBtnPrevMs > BUTTON_DEBOUNCE_MS) {
    lastBtnPrevMs = millis();
    Serial.println("[BTN] PREV");
    sendControl("/api/v1/readers/previous");
  }
  if (digitalRead(PIN_BTN_VOL_UP) == LOW && millis() - lastBtnVolUpMs > BUTTON_DEBOUNCE_MS) {
    lastBtnVolUpMs = millis();
    Serial.println("[BTN] VOL+");
    sendControl("/api/v1/readers/volume-up");
  }
  if (digitalRead(PIN_BTN_VOL_DOWN) == LOW && millis() - lastBtnVolDownMs > BUTTON_DEBOUNCE_MS) {
    lastBtnVolDownMs = millis();
    Serial.println("[BTN] VOL-");
    sendControl("/api/v1/readers/volume-down");
  }
#endif

  if (initPn532()) {
    uint8_t uid[7] = {0};
    uint8_t uidLen = 0;
    bool cardNowPresent = nfc.readPassiveTargetID(
        PN532_MIFARE_ISO14443A, uid, &uidLen, PN532_READ_TIMEOUT_MS);

    if (cardNowPresent) {
      cardAbsentSinceMs = 0;
      if (!cardWasPresent) {
        cardWasPresent = true;
        if (millis() - lastScanMs > SCAN_COOLDOWN_MS) {
          lastScanMs = millis();
          String cardUid = uidToHex(uid, uidLen);
          Serial.printf("[RFID] Karte: %s (len=%u)\n", cardUid.c_str(), uidLen);
          sendScan(cardUid);
        }
      }
    } else {
      if (cardWasPresent) {
        if (cardAbsentSinceMs == 0) cardAbsentSinceMs = millis();
        if (millis() - cardAbsentSinceMs > CARD_REMOVE_DEBOUNCE_MS) {
          cardWasPresent = false;
          cardAbsentSinceMs = 0;
          Serial.println("[RFID] Karte entfernt - Pause");
          sendControl("/api/v1/readers/pause");
        }
      }
    }
  }

  delay(20);
}
