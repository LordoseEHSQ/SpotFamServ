# Plan – Sprint 06: Reader-Station-UX + Konfiguration in die DB (Systemeinstellungen)

> Status: ENTWURF (Plan-vor-Code-GATE). Im Sprint-Chat kritisch prüfen, Entscheidungen
> D-028…D-031 bestätigen, DANN umsetzen. GitHub = SSoT (Milestone „Sprint 06").

## Ziel
1. Reader-Station (Provisioning-UI) konsumententauglich: Flash-Dialog sauber, kein falscher
   Chip-Mismatch, angeschlossenes Gerät klar erkennbar.
2. Konfiguration raus aus Variablen-/Secret-Dateien, rein in die DB unter „Systemeinstellungen"
   (WLAN, Backend-URL, Maschinen-Keys, Frontend-URL, OTA-Kanal). `secrets.h`/`.env`-Werte werden
   zu Dev-/Bootstrap-Fallbacks degradiert.
3. Reader nach Flash **automatisch im WLAN** + self-claiming – ohne PC, ohne `secrets.h`.

## Nicht-Ziele
- Captive Portal als Primärpfad (wird durch Flash-Zeit-NVS-Injektion ersetzt, siehe D-028).
- Audio-Extraktor (separater Sprint 07).
- Multi-User-Admin, HTTPS-Terminierung (eigene spätere Tickets).

---

## Verifizierter Ausgangsbefund (aus Code-Analyse, READ-ONLY-Schwarm)

### Reader-Station UI – `frontend/src/pages/ProvisioningPage.tsx`
- **Bug Chip-Mismatch (Z. 299–301):**
  `selectedArtifact.expectedChip.toLowerCase() !== device.chip.toLowerCase()`.
  Vergleich gegen die **Familie** `device.chip` (`"esp32"`) statt `device.chipDescription`
  (`"ESP32-D0WD-V3"`). Der Python-Agent macht es korrekt via
  `variants.matches(expected, chip_description)` (`firmware/flash_agent/flash_agent/agent.py:116`).
  Falsch-positiver Block → Flashen unmöglich.
- **Dialog-Overflow:** `DialogContent className="max-w-md"` (Z. 330); `DialogDescription`
  Identitätsstring (Port·chipDescription·MAC) einzeilig ohne `break-words`/`min-w-0`;
  Mismatch-Banner-`<span>` (Z. 374) ohne `min-w-0 break-words`; `SelectItem`-Labels (Z. 361) lang.
- **`flashSize`-Typ-Bug:** TS-Typ `number`, Backend/Agent liefern String `"4MB"` →
  `formatBytes(number)` ergibt NaN/Falschanzeige (`provisioning.ts:18`, `ProvisioningPage.tsx:620`).
- **Geräte-Identität:** nur flache Tabelle; kein „aktuell angeschlossen"-Panel.

### Firmware/WLAN – `firmware/spotfam_reader/`
- Reader nutzt **compile-time** `secrets.h` (`WIFI_SSID/PASSWORD`, `BACKEND_BASE_URL`,
  `READER_ID`, `READER_API_KEY`) und direktes `WiFi.begin()` in `setup()`/`loop()`.
- **Kein** NVS/Preferences, **kein** Captive Portal im Firmware-Code (nur in Doku/Backend geplant).
- Geflashte Test-Firmware war die **PN532-Probe** (kein WiFi) → „im WLAN nach Flash" = NEIN.
- PN532 am ESP ist **HW-0-blockiert** (D-022, nicht gelötet) → funktionaler Scan-Pfad unverifiziert.

### Settings-in-DB – bereits vorhandenes Muster (Template)
- `/system` (`frontend/src/pages/SystemPage.tsx`) existiert, aktuell nur Spotify-Karte.
- Spotify-Stack als Blaupause: `SpotifyAppConfiguration`-Entity (Secret via Doctrine-Type
  `spotify_encrypted_string` + `APP_SECRET`), Singleton-Repo (`findActive`), `SpotifyCredentialsProvider`
  (DB-vollständig → `db`, sonst `env`, **kein** Feld-Mix), Use Cases Get/Save/Validate + ActivityLog,
  `/api/v1/system/spotify` (GET/PUT/validate, `ROLE_ADMIN`), React-Card + Hooks.
- **Kein** generischer KV-Store. Empfehlung: pro-Domäne-Entities nach Spotify-Muster.

### Config-Inventar (Kandidaten DB vs. bleibt Env)
- **Bleibt Env (hart):** `APP_SECRET`, `DATABASE_URL`, `POSTGRES_*`, Volume-Mounts (`*_HOST_DIR`,
  `FIRMWARE_DIR`, `*_STORAGE_DIR`), `WEB_IMAGE_TAG`, CI-Variablen, ESP-Pinout/`config.h`.
- **Nach DB (dieser Sprint):** WLAN SSID/Key (verschlüsselt), `BACKEND_BASE_URL` (Default für
  Reader/Claims), globaler `READER_API_KEY`-Fallback, `FLASH_AGENT_API_KEY`, `FRONTEND_URL`,
  OTA-Kanal (heute hardcoded `'stable'` in `ReaderFirmwareController.php:16`).

---

## Architektur-Entscheidung (Kern): WLAN-Config via Flash-Zeit-NVS, nicht Captive Portal

**Problem (Henne-Ei):** Ein Gerät, das noch nicht im WLAN ist, kann WLAN-Credentials nicht
„aus der DB ziehen". Es braucht einen Erst-Eingabekanal.

**Vorgeschlagene Entscheidung D-028 (PRIMÄR):**
Die WLAN-/Reader-Config wird **zur Flash-Zeit** als **NVS-Partition** aufs Gerät geschrieben.
Ablauf:
1. Admin pflegt WLAN/Backend/OTA-Kanal **einmal** in „Systemeinstellungen" (DB).
2. Beim Flash erzeugt der **Flash-Agent** (läuft schon auf dem Pi, USB-Zugriff) aus den
   Backend-Settings eine NVS-Binär-Partition (`esp-idf nvs_partition_gen.py`/Äquivalent) und
   schreibt sie per `esptool write_flash` an den NVS-Offset – zusätzlich zur Firmware.
3. Die **Reader-Firmware** liest Config NVS-first (`Preferences`, Namespace `spotfam`), Fallback
   auf `secrets.h` nur in Dev-Builds.
4. Reader bootet → joint WLAN → self-claim → erhält per-Reader-`reader_id`+API-Key, schreibt sie
   zurück in NVS.

**Begründung gegen Captive Portal als Primärpfad:** nutzt vorhandene Flash-Station-Infra,
keine zusätzliche AP-/Portal-Firmware-Komplexität, kein Endkunden-WLAN-Wechsel nötig (Single-Home).
Captive Portal bleibt als **Zukunft/Fallback** (D-028b) für „Gerät wechselt Heimnetz ohne USB".

**Harte Abhängigkeit / ehrliche Grenze:** Der vollständige E2E („Karte → Play über geflashten
ESP im WLAN") braucht (a) die **echte Reader-Firmware** (heute MFRC522, Ziel PN532) und (b) **HW-0**
(PN532 gelötet, D-022). Beides ist hardware-blockiert. Daher ist Sprint 06 so geschnitten, dass
Phasen A–D **ohne** HW-0 vollständig lauffähig/testbar sind; Phase E (realer Scan) ist der einzige
HW-blockierte Rest.

---

## Phasenplan (autonom abarbeitbar, klare Reihenfolge)

### Phase A — Reader-Station-UX-Fixes (klein, sofort, keine HW)
- A1: Chip-Match korrigieren: gegen `device.chipDescription` vergleichen; Normalisierung analog
  `variants.matches` (Familie-Match erlauben, z. B. `expectedChip`=`ESP32` matcht `ESP32-D0WD-V3`).
  Warnung+Disable nur bei echtem Mismatch. Banner zeigt `chipDescription`.
- A2: Dialog-Overflow: `max-w-md`→`sm:max-w-lg`; Identität als mehrzeiliges Key-Value-Grid statt
  Inline-`·`-Kette; `break-words min-w-0` auf Description + Mismatch-Banner; `SelectItem`-Labels
  `truncate` + Tooltip/title.
- A3: „Aktuell erkanntes Gerät"-Panel oben (Port, chip, chipDescription, MAC, flashSize, status,
  lastSeenAt); hervorgehoben wenn genau 1 Gerät.
- A4: `flashSize`-Typ fixen: Backend liefert String → TS-Typ `string`, Anzeige ohne `formatBytes`
  (oder Backend liefert Bytes-`number` konsistent; eine Quelle der Wahrheit festlegen).
- A5: Frontend-Test (vitest/RTL) für Chip-Match-Logik (Familie vs. Description, Positiv/Negativ).
- **DoD A:** Upload→Auswahl→Flash ohne falschen Block; Dialog bricht nicht; 1 Gerät klar sichtbar.

### Phase B — „Systemeinstellungen" erweitern (Backend + Frontend, keine HW)
Nach Spotify-Muster, je Domäne eigene verschlüsselte Singleton-Entity + Provider(DB→Env-Fallback)
+ `/api/v1/system/<domain>` (`ROLE_ADMIN`, Secrets nie im GET) + Card in `SystemPage`.
- B1: **ReaderNetworkConfiguration**: `wifiSsid`, `wifiPassword`(verschlüsselt), `backendBaseUrl`,
  `otaChannel` (`stable|beta|dev`), `isComplete()`. Provider mit Env-Fallback
  (`secrets.h` ist nicht Env – Env-Fallback hier = Defaults/leeres Objekt; `secrets.h` bleibt
  reiner Firmware-Dev-Fallback).
- B2: **MachineKeysConfiguration**: globaler `readerApiKey`-Fallback (verschlüsselt) +
  `flashAgentApiKey` (verschlüsselt) mit UI-Rotation. Provider liefert DB→Env-Fallback; bestehende
  `services.yaml`-Bindings (`%env(READER_API_KEY)%`, `%env(FLASH_AGENT_API_KEY)%`) auf Provider
  umstellen. **Achtung:** Agents/ESP brauchen weiterhin lokale Kopie (Push/Export, kein Live-Pull).
- B3: **SystemUrlsConfiguration** (oder Feld in ReaderNetwork): `frontendUrl`. `SpotifyOAuthController`
  + `spotify.yaml` auf Provider umstellen, Env als Fallback.
- B4: OTA-Kanal aus DB lesen (`ReaderFirmwareController` statt hardcoded `'stable'`).
- B5: Migration(en) für die neuen Tabellen; ActivityLog-Typen; Doctrine-Mapping registrieren
  (Lesson L-A: Mapping nicht vergessen, sonst 500 wie v0.5.2); PHPStan L8; Unit-Tests je Provider
  (DB-vollständig→db, unvollständig→env).
- B6: Frontend: neue Cards in `SystemPage` (Reader-Netzwerk, Maschinen-Keys mit „rotieren", URLs),
  Hooks + `api/endpoints/system*.ts`; Env-Quelle-Warnbanner analog Spotify.
- **DoD B:** Alle Zielwerte in `/system` editierbar; GET ohne Secrets; `source: db|env` korrekt;
  CI grün (PHPUnit, PHPStan, oasdiff, pnpm).

### Phase C — Flash-Zeit-NVS-Injektion (Flash-Agent + Backend, keine HW nötig zum Bauen/Testen)
- C1: Backend-Endpoint `GET /api/v1/provisioning/reader-config` (Agent-Auth `X-API-Key`
  FLASH_AGENT_API_KEY): liefert die für ein Gerät zu schreibende NVS-Key-Value-Liste aus
  ReaderNetworkConfiguration (+ frisch erzeugte `reader_id`/Claim-Code je Flash). OpenAPI + oasdiff.
- C2: Flash-Agent: NVS-CSV→Binär erzeugen (`nvs_partition_gen.py` oder eigenständige Implementierung;
  Dependency begründen) + `esptool write_flash <nvs_offset> nvs.bin` nach dem Firmware-Write.
  Concurrency/Port-Lock wie bestehend. Unit-Tests (NVS-Gen + Offsets + esptool-Argv gemockt).
- C3: Frontend Flash-Dialog: Checkbox „Reader-Konfiguration (WLAN etc.) mitflashen" (Default an,
  wenn ReaderNetworkConfiguration vollständig); Hinweis wenn unvollständig.
- C4: Partition-Table/Offset dokumentieren; NVS-Offset muss zum Firmware-Partition-Layout passen
  (Risiko: Standard-`nvs` vs. Custom). Runbook ergänzen.
- **DoD C:** Flash-Job schreibt Firmware **und** NVS; verifiziert per `esptool read_flash`/Dump im
  Test, dass NVS die WLAN-Keys enthält (ohne Klartext-Logging des Passworts).

### Phase D — Reader-Firmware: NVS-first-Config (Firmware, ohne PN532 testbar)
- D1: `spotfam_reader` Config-Layer: `Preferences`/NVS Namespace `spotfam` lesen
  (`wifi_ssid`, `wifi_password`, `backend_url`, `reader_id`, `reader_api_key`, `fw_channel`),
  Fallback `secrets.h` nur `#ifdef DEV_FALLBACK`. `secrets.h` aus Produktivpfad entfernen.
- D2: Self-Claim-Flow: wenn `reader_id`/Key fehlen → `POST /readers/claims/{code}/activate`
  (Code aus NVS), Ergebnis in NVS persistieren. Recovery/Reset (langer Tastendruck → NVS-Wipe).
- D3: CI-Firmware-Compile auf NVS-Variante umstellen; WiFi-Join ohne echte Hardware nicht E2E,
  aber Compile + (optional) WLAN-Join-Smoke am Dev-ESP (kein PN532 nötig).
- **DoD D:** Firmware kompiliert, liest NVS, joint WLAN am Dev-ESP (ohne PN532), self-claim grün
  gegen Backend. **Beweist „Gerät nach Flash im WLAN = JA".**

### Phase E — Realer RFID-E2E (HW-0-blockiert, nur wenn PN532 gelötet)
- E1: PN532 am ESP (I2C/SPI final), Karte→UID→`/readers/scan`→Play. Nur nach D-022-Auflösung.
- **DoD E:** Karte am geflashten ESP startet Wiedergabe auf Connect-Gerät.

---

## 4-Lens-Analyse
**Lens 1 – Runtime & Sprache:** PHP 8.5.6/Symfony 7.4 LTS (Backend), React 18/Vite (FE),
Arduino-ESP32-Core 3.3.8 (Firmware), Python 3 + esptool v5.3 (Agent), Pi arm64/Debian 13.
NVS-Gen: `esp-idf` Tool (Python) ODER pure-Python-Reimplementierung; arm64-tauglich (läuft auf Pi).
**Lens 2 – Frameworks & Abhängigkeiten:** Doctrine ORM (neue Entities/Migrationen), vorhandener
`spotify_encrypted_string`-Type wiederverwenden (kein neues Crypto). Neue Dep nur für NVS-Gen
(begründen, Lock-Impact prüfen; bevorzugt vendored Script statt pip-Dep auf dem Pi). FE: shadcn
Dialog/Select bereits da.
**Lens 3 – Build, CI/CD & Tooling:** CI erweitern (FE-Test Chip-Match, flash-agent NVS-Tests,
Firmware-Compile NVS-Variante), `oasdiff` für neue System-/Provisioning-Endpoints, Trivy.
Deploy: Migrationen + neue Env-Bootstraps; `release-web-image` unverändert.
**Lens 4 – Security & Compliance:** WLAN-Passwort + API-Keys **verschlüsselt at rest**
(`APP_SECRET`); GET-Endpoints geben Secrets nie zurück (`has_*`-Flags). NVS-Passwort nie loggen.
Maschinen-Endpoints bleiben `X-API-Key` (nicht Session). Least-Privilege: Agent-Config-Endpoint
nur mit FLASH_AGENT_API_KEY. API-Vertrag additiv (keine Breaking Changes).

## Cross-Module-5-Fragen
1. **Wer konsumiert die neuen Settings?** Backend-Provider (OAuth, Reader-APIs, OTA), Flash-Agent
   (NVS-Gen), Firmware (NVS-Read). 2. **Brechen bestehende Pfade?** Nein – Env bleibt Fallback;
   Provider liefern Env wenn DB leer. 3. **Migrationsreihenfolge?** Schema vor Code-Nutzung; Provider
   mit Fallback verhindert Lücke. 4. **Rollback?** Env-Werte bleiben gültig; DB-Row löschen →
   Env-Fallback. 5. **Geheimnis-Leak-Fläche?** Nur verschlüsselte Spalten + Agent-Export; kein
   Klartext in GET/Logs/Frontend.

## Vorgeschlagene Entscheidungen (im Chat bestätigen → `tasks/decisions.md`)
- **D-028** Reader-WLAN/Config via Flash-Zeit-NVS-Injektion (Primär), Captive Portal nur Zukunft (D-028b).
- **D-029** Settings als pro-Domäne verschlüsselte Singleton-Entities nach Spotify-Muster; kein KV-Store.
- **D-030** Maschinen-Keys (READER/FLASH_AGENT) in DB mit UI-Rotation; Agents/ESP via Export/NVS,
  kein Live-DB-Pull.
- **D-031** `secrets.h`/relevante `.env`-Werte → Dev-/Bootstrap-Fallback degradiert, nicht mehr Produktivquelle.

## Risiken / offene Punkte
- NVS-Offset/Partition-Layout-Mismatch → Gerät liest Config nicht (C4 mitigiert per read-back-Test).
- NVS-Gen-Tooling auf arm64 (vendored Script bevorzugt).
- Firmware ist MFRC522; PN532-Umbau + HW-0 bleiben echte Blocker für Phase E.
- Key-Rotation-Koordination (DB-Key ändern ⇒ Agent/ESP müssen neuen Key bekommen).

## Test-vor-Done
PHPUnit (Provider/UseCases), PHPStan L8, FE-Unit (Chip-Match), flash-agent pytest (NVS-Gen),
Firmware-Compile (NVS-Variante), `esptool` read-back im Agent-Test, CI grün, Deploy + Health 200.

## Subagenten-Plan
- Parallel: (1) Backend-Settings-Entities/Provider/Tests, (2) Frontend Cards+Hooks+UX-Fixes,
  (3) Flash-Agent NVS-Gen+Tests. Seriell danach: Firmware-NVS (D) + Integration. HW-Schritte (E)
  im Hauptchat mit User.
