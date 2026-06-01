// ---------------------------------------------------------------------------
// config.h  –  Hardware-Pinout & Verhalten (kein Geheimnis, darf ins Repo)
// Board-Default: ESP32-WROOM-32 DevKit (FQBN esp32:esp32:esp32)
// ---------------------------------------------------------------------------
#pragma once

// --- MFRC522 (SPI / VSPI-Default-Pins) -------------------------------------
#define PIN_RC522_SS    5    // SDA/SS
#define PIN_RC522_RST   22   // RST
// SCK=18, MISO=19, MOSI=23  (VSPI, fest durch SPI.begin())

// --- Taster (gegen GND, interner Pull-up; aktiv LOW) -----------------------
#define PIN_BTN_NEXT    25
#define PIN_BTN_PREV    26

// --- Status-LED (onboard) --------------------------------------------------
#define PIN_LED         2

// --- Verhalten -------------------------------------------------------------
#define SERIAL_BAUD            115200
#define WIFI_CONNECT_TIMEOUT_MS 20000  // max. Wartezeit auf WLAN beim Start
#define HTTP_TIMEOUT_MS         8000   // pro Request
#define BUTTON_DEBOUNCE_MS      250    // Entprellung Taster
#define SCAN_COOLDOWN_MS        1500   // lokale Sperre nach Karten-Scan
                                       // (Backend entprellt zusaetzlich 5 s)
