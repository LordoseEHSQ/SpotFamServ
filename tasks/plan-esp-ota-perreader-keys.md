# Plan: Pro-Reader-Keys + ESP-Provisioning + OTA-Updates

**Erstellt:** 2026-06-02
**Status:** Plan – wartet auf Bestaetigung, NICHT umgesetzt

## Scope
Geraete-Onboarding und Fernwartung sicher und nutzerfreundlich machen:
(1) **Pro-Reader-API-Keys** statt eines geteilten Shared Secrets,
(2) **Provisioning** neuer ESPs per USB am Pi (flashen + registrieren),
(3) **OTA-Updates** der ESP-Firmware ueber WLAN, vom Fam-Server angestossen.
Bewusst gestaffelt: Key-Haertung ist Voraussetzung fuer sicheres OTA.

## Bestaetigte Entscheidungen (User, 2026-06-02)
- Alle drei Bausteine sind gewuenscht; Pro-Reader-Keys explizit als Teil von OTA.
- Ziel: neue Geraete leicht „bespielen", Server „updated" sie – aber nicht hackbar.

## Wichtige Realitaets-Korrektur (vom User akzeptiert)
Ein **blanker ESP erscheint nicht von selbst** beim Anstecken (keine Firmware → kein WLAN).
Daher zweiphasig: **Phase A** initiales Flashen+Registrieren per USB am Pi, **Phase B** danach
reine OTA-Updates ueber WLAN. „Plug → erscheint im Server" gilt erst NACH Phase A.

## Betroffene Bereiche
- `firmware/spotfam_reader/` — OTA-Client (ArduinoOTA oder HTTP-Update), Versions-String.
- **Backend:** Reader-Registrierung (`reader_device` anlegen), Pro-Reader-Key-Pruefung, Firmware-Verteilung (Endpunkt + Version/Manifest), Signatur-Verifikation.
- `Scan/` — `ScanController::validateReaderAuth` von Single-Key auf Pro-Reader-Key (`ReaderDevice::validateApiKey` existiert bereits, ungenutzt!).
- **Pi/Provisioning-Tool** — `arduino-cli`-Flash + Key-Injektion + Backend-Registrierung.
- **Frontend** — Reader-Liste (Status/Version), „Update verfuegbar"/„Geraet hinzufuegen".

## Teil 1 — Pro-Reader-Keys (Voraussetzung, kleinster Aufwand)
**Fakt:** `ReaderDevice.apiKeyHash` + `validateApiKey()` sind vorhanden, aber `ScanController`
prueft nur den globalen `READER_API_KEY` (Z. 98-109). → Pro-Reader-Auth ist halbfertig.
- Bei Registrierung: zufaelligen Key generieren, `password_hash` in `reader_device.api_key_hash`, Klartext **einmalig** ausgeben (zum Flashen).
- `validateReaderAuth`: `reader_id` aus Body → `ReaderDevice` laden → `validateApiKey()`. Globaler Key bleibt optional als Uebergangs-Fallback (Decision D-K1).
- Nutzen: ein kompromittierter/verlorener ESP = **ein** Key sperren, nicht alle re-flashen.

## Teil 2 — Provisioning (Phase A, USB am Pi)
- Tool am Pi: ESP an USB → `arduino-cli` Flash mit generierter `secrets.h` (WLAN, `BACKEND_BASE_URL`, neuer `READER_ID`, neuer Pro-Reader-Key) → parallel `reader_device` im Backend anlegen.
- Liefert „Geraet hinzufuegen"-Flow im Server (loest D-R1 aus Plan Reader→Box konsistent: explizite Registrierung).

## Teil 3 — OTA (Phase B, hoechstes Risiko)
- ESP holt/empfaengt neue Firmware ueber WLAN. **Signierte Binaries** (z.B. ESP32 Secure-Update / signiertes Manifest), ESP verifiziert vor dem Flashen.
- Versionierung + Rollback (A/B-Partition oder „letzte funktionierende" behalten), Brick-Schutz.
- Server: Firmware-Artefakt + Version je Reader-Hardware, Endpunkt mit Pro-Reader-Auth.

## 4-Lens-Analyse (Pflicht)

### Lens 1 – Runtime & Sprache
- ESP32 Arduino-Core (vorhanden). OTA via `ArduinoOTA`/`HTTPUpdate` (Core-Bestandteil). `arduino-cli` am Pi vorhanden.
- Backend PHP 8.5/Symfony 7.4; Firmware-Binary-Ausgabe als statische/kontrollierte Route.

### Lens 2 – Frameworks & Abhaengigkeiten
- ESP: ggf. zusaetzliche Lib fuer Signatur-Pruefung (begruenden). Backend: keine schweren neuen Deps; Key-Hashing via PHP-`password_*` (vorhanden).
- Build der Firmware-Binaries: in CI mit `arduino-cli` (multi-target falls verschiedene Boards).

### Lens 3 – Build, CI/CD & Tooling
- CI baut + **signiert** Firmware-Artefakte, legt sie versioniert ab (GHCR-Release/Artefakt, analog D-012-Muster fuer Web-Image).
- Provisioning-Tool + OTA-Endpunkt brauchen Tests; OpenAPI fuer neue Endpunkte.
- Reader-Registrierung = Schema/Endpunkt → oasdiff + Migration.

### Lens 4 – Security & Compliance
- **Kern dieses Plans.** Pro-Reader-Key (least privilege), **signierte** OTA (sonst Fernwartung = Backdoor), TLS empfohlen fuer Firmware-Download (Integritaet zusaetzlich zur Signatur).
- Klartext-Key nur einmalig bei Registrierung sichtbar; nie geloggt. ESP-`secrets.h` bleibt git-ignoriert.
- Optional ESP32 Flash Encryption/Secure Boot (irreversibel) — separat bewerten, nicht Teil des MVP dieses Plans.

## Cross-Module Antworten
1. **Upstream:** Provisioning-Tool/CI erzeugen Firmware + Keys. Risiko: kompromittierte Signaturkette → Schad-Firmware. Mitigation: Key-Material nur in CI-Secret/HSM-Aequivalent, Signatur-Pruefung am ESP.
2. **Downstream:** ESPs konsumieren OTA + nutzen Pro-Reader-Key beim Scan. Umstellung der Auth ist breaking fuer bestehende ESPs → Migration: Fallback-Key-Phase (D-K1), dann re-flashen.
3. **Audit:** Registrierung/Key-Rotation/OTA-Push sind security-relevante State-Aenderungen → Audit-Log-pflichtig.
4. **API-Vertrag:** neue Endpunkte (Registrierung, Firmware/Version, OTA). openapi.yaml + Frontend.
5. **Feature-Flags:** OTA hinter Flag/Opt-in pro Reader sinnvoll (vorsichtiger Rollout).

## Geplante Umsetzung (gestaffelt, je nach Bestaetigung)
1. **Teil 1 zuerst (eigener PR):** Pro-Reader-Key in `validateReaderAuth` verdrahten + Registrierungs-/Key-Generierung. Klein, hoher Sicherheitsgewinn, entkoppelt von OTA.
2. **Teil 2:** Provisioning-Tool am Pi + „Geraet hinzufuegen"-UI.
3. **Teil 3:** OTA-Client im Sketch + signierte CI-Artefakte + Server-Endpunkt + Versions-/Rollback-Logik + UI.

## Akzeptanzkriterien
1. Jeder Reader hat einen eigenen Key; Scan mit fremdem/altem Key wird mit 401 abgelehnt.
2. Ein gesperrter/geloeschter Reader kann nicht mehr scannen, andere Reader unbeeintraechtigt.
3. Neuer ESP per USB am Pi: nach Provisioning erscheint er im Server und scannt erfolgreich.
4. OTA: Server stoesst Update an → ESP flasht **nur** signierte Firmware; manipulierte/fremde Binaries werden abgelehnt.
5. Fehlgeschlagenes OTA laesst den ESP funktionsfaehig (Rollback/letzte gute Version).

## Definition of Done
- [ ] Pro-Reader-Key-Auth getestet (gueltig/ungueltig/gesperrt)
- [ ] Provisioning E2E: blanker ESP → registriert + scannt
- [ ] OTA E2E inkl. Signatur-Reject + Rollback-Fall
- [ ] CI baut+signiert Firmware reproduzierbar
- [ ] openapi.yaml + Frontend + Doku (`docs/`) aktualisiert
- [ ] Decision-Log (Keys, OTA-Mechanismus, Signatur) gepflegt
- [ ] Cross-Module-Checkliste beantwortet

## Risiken / Offene Fragen
- **OTA ist das groesste/riskanteste Thema** — eigener Sprint, nicht mit Teil 1/2 vermischen.
- **D-K1 (Auth-Migration):** Uebergang Single-Key → Pro-Reader-Key. A) Hard-Cut (alle ESPs neu flashen) · B) Fallback-Phase (globaler Key bleibt temporaer gueltig). Empfehlung B fuer unterbrechungsfreien Umstieg.
- **D-O1 (OTA-Mechanismus):** ArduinoOTA (Push im LAN) vs. HTTP-Pull-Update (ESP fragt Server). Pull passt besser zum bestehenden HTTP-only-Modell + Auth.
- **Signatur-Kette:** Schluesselverwaltung in CI; ohne Signatur ist OTA eine Backdoor → nicht ohne Signatur ausliefern.
- Verschiedene Board-Varianten → Firmware-Matrix; Versions-/Hardware-Zuordnung je `reader_device` noetig.

## Verifikations-Log
{Beim Umsetzen ausfuellen}

## Abgeschlossen
{Datum + Summary wenn fertig}
