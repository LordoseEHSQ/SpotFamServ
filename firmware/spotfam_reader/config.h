// ---------------------------------------------------------------------------
// config.h  –  Hardware-Pinout & Verhalten (kein Geheimnis, darf ins Repo)
// Board-Default: ESP32-WROOM-32 DevKit (FQBN esp32:esp32:esp32)
// ---------------------------------------------------------------------------
#pragma once

// --- PN532/HW-147 (I2C) -----------------------------------------------------
// Zielhardware: dasselbe PN532/HW-147-Modul wie am Pi.
#define PIN_PN532_SDA   21
#define PIN_PN532_SCL   22
#define PIN_PN532_IRQ   -1   // -1 = I2C-Polling ohne IRQ-Draht
#define PIN_PN532_RST   -1   // -1 = Reset nicht verdrahtet

// --- Taster (gegen GND, interner Pull-up; aktiv LOW) -----------------------
// Buttons bleiben standardmaessig deaktiviert, bis die Pins am AZ-Delivery-
// Board real bestaetigt sind. GPIO0/BOOT darf hier nie verwendet werden.
#define SPOTFAM_ENABLE_BUTTONS 0
#define PIN_BTN_NEXT    25
#define PIN_BTN_PREV    26

// --- Status-LED (onboard) --------------------------------------------------
#define PIN_LED         2

// --- Firmware/OTA -----------------------------------------------------------
#define FIRMWARE_VERSION "0.8.2"
#define FIRMWARE_BOARD   "esp32-wroom-32"
#define DEFAULT_FW_CHANNEL "stable"

// --- Verhalten -------------------------------------------------------------
#define SERIAL_BAUD            115200
#define WIFI_CONNECT_TIMEOUT_MS 20000  // max. Wartezeit auf WLAN beim Start
#define HTTP_TIMEOUT_MS         8000   // pro Request
#define BUTTON_DEBOUNCE_MS      250    // Entprellung Taster
#define SCAN_COOLDOWN_MS        1500   // lokale Sperre nach Karten-Scan
                                       // (Backend entprellt zusaetzlich 5 s)
#define PN532_READ_TIMEOUT_MS   1000
#define PN532_REINIT_MS         30000
#define BACKEND_RETRY_MS        5000
#define OTA_CHECK_INTERVAL_MS   3600000UL
