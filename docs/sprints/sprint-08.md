# Sprint 08 – PN532 Reader-Firmware, OTA & Reader Admin UI (v0.8.0)

**Zeitraum:** 2026-06-07 – 2026-06-09  
**Release:** `v0.8.0`  
**Branch:** `feat/pn532-reader-ota`  
**Chat:** [PN532 Reader + OTA + Admin UI](b59e8f36-18d3-45b6-822a-c2fba5cc5143)  
**Status:** Code fertig, Tests grün, PR offen → merge pending

---

## Ziel

Vollständig funktionierenden ESP32-RFID-Reader auf PN532/I2C-Basis in Betrieb nehmen:
- Firmware: NVS-Provisioning, Captive Portal, OTA-Pull-Check
- Backend: Reader-Claim-System, Diagnostik-Felder, OTA-Manifest/Download
- Frontend: Reader-Zentrale mit Status/Firmware/Last-Scan, 5-Schritt-Onboarding-Wizard

---

## Akzeptanzkriterien (erfüllt)

- [x] ESP32 (AZ-Delivery WROOM-32) mit PN532 (I2C) erkennt Karten und schickt Scans an Backend
- [x] Provisioning via Captive Portal + Claim-Code funktioniert E2E (Karte → Spotify → Wiedergabe)
- [x] Backend speichert `last_seen_at`, `firmware_version`, `board`, `fw_channel`, `last_ip` pro Reader
- [x] `GET /readers` liefert Diagnose-Felder + `last_scan` inline
- [x] `GET /readers/scan-events` unterstützt Filter `reader_device_id` (indiziert, nicht JSON-Scan)
- [x] Manifest-Endpoint akzeptiert `reader_id` → Heartbeat + installierte Firmware-Version
- [x] `ReadersPage` zeigt Status (last_seen_at relativ), Firmware-Version, letzten Scan + Operator-Hinweis
- [x] 5-Schritt-Onboarding-Wizard: Claim → USB-Flash (optional) → Portal → Polling → Done
- [x] Board-Default in ProvisioningPage korrigiert: `esp32-wroom-32` (war `spotfam_reader`)
- [x] PHPUnit 33/33, PHPStan Level 6 clean, TypeScript 0 Fehler, Firmware kompiliert

---

## Deliverables

### Firmware (`firmware/spotfam_reader/` v0.8.2)
- PN532/I2C via Adafruit-Lib statt MFRC522/SPI
- NVS-basierte Konfiguration (SSID, Passwort, Backend-URL, Claim-Code, Reader-ID, API-Key, FW-Channel)
- Captive-Portal-AP bei erstem Start / ungültiger Config
- Claim-Aktivierung gegen Backend (`/readers/claims/{code}/activate`)
- OTA-Pull-Check alle 60 min gegen `/readers/firmware/manifest?reader_id=…`
- Firmware-Version + `reader_id` in Manifest-Request → Backend-Heartbeat

### Backend (neue Migration + Code)
- `Version20260608180000_reader_device_diagnostics`: 5 nullable Spalten auf `reader_device`
- `ReaderDevice.touchSeen()`: atomic update von `last_seen_at` + optionaler Firmware-Metadaten
- `ScanController.scan()`: ruft `touchSeen($ip)` bei jedem Scan auf
- `ReaderFirmwareController.manifest()`: akzeptiert `reader_id`, aktualisiert Reader als Heartbeat + speichert `firmware_version` aus `current_version`-Param
- `RedeemReaderClaim`: persistiert `firmware_version`, `board`, `fw_channel` bei Claim-Aktivierung
- `GET /readers`: neue Felder + `last_scan` inline (1 Query via `ListScanEvents`)
- `GET /readers/scan-events`: neuer Filter `reader_device_id`, exponiert `reader_id` + `message` (sanitized)
- `ScanEvent::getDetails()`: neuer Getter
- `ListScanEvents` + `ScanEventRepositoryInterface` + `DoctrineScanEventRepository`: `readerDeviceId`-Filter

### Frontend
- `ReadersPage.tsx`: vollständig neu gebaut — Row mit Status-Indikator (last_seen_at relativ), Firmware-Badge, Outcome-Badge + Operator-Hint, Aufklappzeile für vollständige Diagnose, Box-Zuweisungs-Dialog, Onboarding-Wizard
- `readers.ts` + `scan.ts` DTOs: neue Felder
- `ProvisioningPage.tsx`: Board-Default `esp32-wroom-32`

### Dry-Run-Befunde (eingearbeitet)
- **C-1** firmware_version nur aus Claim + Manifest-Heartbeat (nicht Scan-Body)
- **C-2** Board-Default-Fix + bestehende Artefakte-Migration dokumentiert
- **C-3** `ScanEvent::getDetails()` als Getter ergänzt
- **H-4** `readerDeviceId`-Spaltenfilter statt JSON-Scan

---

## Bekannte Einschränkungen / Folgearbeit

- **OTA E2E nicht getestet:** Es existiert noch kein Firmware-Artefakt im Backend. OTA-Flow (Artefakt hochladen → ESP checkt → lädt) ist code-seitig fertig aber hardware-seitig unverifiziert.
- **Zweiter ESP nicht provisioniert:** Onboarding-Wizard code-fertig, aber nur mit dem einen bereits provisionierten ESP getestet.
- **Pi-Deploy-Instabilität:** Pi-Volume-Mount (`./backend`) + tag-gesteuerter Deploy-Mechanismus setzen HEAD immer wieder auf den letzten Release-Tag zurück. Workaround: `docker-compose.override.yml` mit separatem Frontend-Dist-Verzeichnis. Saubere Lösung: PR → Squash-Merge → `v0.8.0`-Tag → offizieller Pi-Deploy.
- **Online/Offline-Heuristik:** Manifest-Check-Intervall 60 min → Reader gilt als offline zwischen Scans und Manifest-Check. UI zeigt „Zuletzt gesehen vor X" (kein binäres on/off) — akzeptabel bis Heartbeat-Intervall reduziert wird.
- **`pm.max_children=5` (PHP-FPM):** Nach cold-cache (z. B. `cache:clear`) können ESP-Scans 499-Fehler bekommen. Empfehlung: auf 10 erhöhen.
