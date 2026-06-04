# Plan: ESP32 Consumer-Provisioning, PN532-Reader und OTA

**Erstellt:** 2026-06-03  
**Status:** Software-Schnitt teilweise umgesetzt — CI-Firmware-Baseline grün; HW-0 blockiert Sprint-Done

## Scope
SpotFamServ soll ESP32-Reader so einbinden, dass sie perspektivisch wie ein Consumer-Produkt funktionieren: einmaliger Hersteller-/Entwickler-Flash per USB, danach Einrichtung per Captive Portal, Kopplung ans Backend per Claim-Flow und Firmware-Updates per OTA. Der ESP-Reader verwendet denselben RFID-Reader-Typ wie der Pi: HW-147/PN532; der bestehende MFRC522/SPI-Firmwarestand ist daher nicht die Zielhardware.

Nicht im ersten Umsetzungsschnitt: direkte Sonos-/Bose-Steuerung, Bluetooth-Audio, paralleles Spotify-Multiroom mit einem Account.

## Verifizierte Ausgangslage
- `firmware/spotfam_reader/spotfam_reader.ino` nutzt aktuell `MFRC522` und `SPI`.
- `firmware/spotfam_reader/config.h` dokumentiert aktuell MFRC522-Pins (`SS=5`, `RST=22`, VSPI `SCK=18`, `MISO=19`, `MOSI=23`).
- `firmware/spotfam_reader/secrets.h` enthaelt aktuell WLAN, Backend-URL, `READER_ID` und `READER_API_KEY` als Compile-Time-Konfiguration.
- Backend-Reader-APIs fuer Scan/Next/Previous existieren bereits (`/api/v1/readers/scan|next|previous`) und wurden mit dem Pi-Reader erfolgreich E2E verifiziert.
- Backend hat bereits `ReaderDevice`, per-Reader-API-Key-Hashing und Admin-Endpunkte zum Generieren/Rotieren von Reader-Keys; der neue Claim-Flow darf dieses Modell nicht duplizieren.
- `ScanController` akzeptiert fuer Reader mit eigenem Key nur den per-Reader-Key, ansonsten den globalen `READER_API_KEY` als Rueckfall. Diese Rueckwaertskompatibilitaet ist fuer Pi-Reader/Bestandsgeraete zu erhalten.
- Die Frontend-Reader-Seite kennt Reader/Box-Zuordnung, aber noch keinen echten "Reader hinzufuegen"-Flow; ausserdem liefert das Backend `has_api_key`, waehrend der aktuelle Frontend-DTO diesen Wert nicht typisiert.
- Nutzerfakt 2026-06-03: ESP-Geraete sollen den gleichen RFID-Reader-Typ verwenden wie der Pi: HW-147/PN532.

## Kritischer Review-Befund
- Das Zielbild aus D-018 ist richtig, aber als Implementierung zu gross fuer einen ungegateten Schnitt. Hardware-Bus, Secret-Modell, Claim-API, Captive Portal und OTA duerfen nicht in einem Big-Bang zusammenfallen.
- Der bestehende Backend-Stand ist weiter als der Draft zunaechst impliziert: per-Reader-Key-Hashing existiert bereits. Der Claim-Flow soll `ReaderDevice` und die bestehende Key-Logik erweitern, nicht daneben ein zweites Provisioning-Secret-Modell einfuehren.
- OTA mit blosser Hash-Pruefung ist nur Integritaet gegen kaputte Downloads, keine Herkunftssicherung. Fuer einen Consumer-Anspruch muss mindestens klar entschieden werden: signiertes Manifest/Firmware jetzt oder bewusstes MVP-Risiko mit lokaler Backend-Auslieferung.
- Captive Portal ist nutzerfreundlich, aber sicherheitlich schwach, wenn es als offenes AP dauerhaft erreichbar waere. Es darf nur zeitlich/zustandsbasiert aktiv sein und darf keine Secrets loggen.
- Der ESP-API-Key in NVS ist ein widerrufbarer Bearer-Token, kein Hardware-Sicherheitsanker. Physischer Zugriff auf den ESP kann den Key kompromittieren; deshalb sind Rotation, Revoke und kein Spotify-Token auf dem ESP Pflicht.
- Phase 0 ist nicht optional: Ohne real verifizierten PN532-Bus, DIP-Modus, Pinout und UID-Gleichheit darf keine Loet-/Gehause-/Produktannahme getroffen werden.

## Betroffene Bereiche
- Firmware ESP32 - Wechsel von MFRC522/SPI-Annahme auf PN532/HW-147, Provisioning, NVS-Konfiguration, OTA, Status-/Recovery-Logik.
- Backend Symfony - Reader-Claim/Provisioning-Endpunkte, API-Key-Ausstellung, OTA-Manifest/Versionierung, Audit/Activity-Logs.
- Frontend React - gefuehrter "Reader hinzufuegen"-Flow, Claim-Code/QR, Statusanzeige, OTA-/Firmware-Version sichtbar.
- Hardware/Doku - PN532-Buswahl, finales Pinout vor Loeten, Reset-/Provisioning-Taster, LED-Signale, Fertigungs-/Onboarding-Anleitung.
- CI/Release - Firmware-Build-Artefakte, Versionsmanifest, OTA-Auslieferung, Tests/Checks fuer Firmware-Konfiguration.

## 4-Lens-Analyse

### Lens 1 - Runtime & Sprache
- Firmware: ESP32-WROOM-32, Arduino Framework/C++ bleibt wahrscheinlich der risikoarme Pfad, weil aktueller Code bereits Arduino nutzt.
- PN532-Library: Adafruit PN532 oder kompatible Arduino-PN532-Library evaluieren; Entscheidung erst nach Minimal-Prototyp auf realem HW-147.
- Pi/Backend: Symfony 7.4 LTS/PHP 8.5.6 bleibt unveraendert; Pi arm64 bleibt Runtime fuer Backend, nicht fuer ESP-Builds.
- OTA-Risiko: ESP32-Partitionstabelle muss OTA-faehig sein; Flash-Groesse des konkreten Boards pruefen.

### Lens 2 - Frameworks & Abhaengigkeiten
- Firmware-Abhaengigkeiten werden erweitert: PN532-Library, Preferences/NVS, WebServer/DNSServer oder WiFiManager-aehnliche Loesung, OTA-Update-Client.
- Neue Firmware-Dependencies nur nach Begruendung: Captive-Portal-Library spart Aufwand, erhoeht aber Update-/Security-Oberflaeche.
- Backend moeglichst ohne neue externe Dependencies: Claim-Code, API-Key-Hashing und OTA-Manifest koennen mit Symfony-Bordmitteln umgesetzt werden.
- Lock-/Tooling-Impact: Arduino-Libs muessen reproduzierbar dokumentiert werden; wenn `arduino-cli` genutzt wird, Library-Versionen pinnen.

### Lens 3 - Build, CI/CD & Tooling
- Firmware-Build gehoert mittelfristig in CI, nicht auf den Pi.
- OTA-Artefakte muessen versioniert und signiert oder mindestens hash-validiert werden.
- Release-Prozess muss Firmware-Version, Backend-Version und OTA-Manifest konsistent halten.
- Lokaler Entwicklerfluss: USB-Erstflash bleibt moeglich; danach OTA fuer Regressionstests.
- Tests: Backend-Unit/Integration fuer Claim/Provisioning/Manifest; Firmware mindestens Compile-Check plus manuelle Hardware-Verifikation.

### Lens 4 - Security & Compliance
- Keine Spotify-Tokens auf dem ESP; das bleibt harte Architekturgrenze.
- `READER_API_KEY` darf nicht mehr in `secrets.h` kompiliert werden; Provisioning schreibt ein per Backend erzeugtes Secret in NVS.
- Claim-Codes muessen kurzlebig, einmalig verwendbar und auditierbar sein.
- OTA muss Downgrade/Mismatch verhindern: Board-Typ, Firmware-Kanal, Version und Hash pruefen.
- Captive Portal darf nur im unprovisionierten oder explizit per Reset aktivierten Zustand offen sein.

## Cross-Module Antworten
1. **Upstream:** Upstream sind Hardware-Fakten (ESP32-Board, HW-147/PN532, Busmodus), WLAN-Daten und Backend-Reader-Claims. Falscher Busmodus oder falsches Pinout bricht die Firmware; daher erst Hardware-Minimaltest vor Loeten.
2. **Downstream:** Backend-Scanpfad und Spotify-Playback konsumieren weiterhin `reader_id` und `card_uid`. Bestehende Pi-Reader duerfen durch Claim-/OTA-Erweiterungen nicht brechen.
3. **Audit:** Ja. Reader-Claim, API-Key-Ausstellung, Re-Provisioning, OTA-Update und Factory-Reset sind sicherheitsrelevante State-Aenderungen und brauchen Audit-/Activity-Log.
4. **API-Vertrag:** Ja, neue additive Endpunkte fuer Provisioning/Claim/OTA-Manifest. Bestehende Scan/Next/Previous-Response-Shapes bleiben unveraendert.
5. **Feature-Flags:** Provisioning/OTA sollte initial hinter klarer Admin-/Dev-UI oder Feature-Flag laufen, bis ein echter ESP-Hardwaretest abgeschlossen ist.

## Architektur-Zielbild
1. ESP startet ohne Config als Access Point `SpotFam-Reader-<short-id>`.
2. Captive Portal fragt Heim-WLAN und Backend-Adresse oder findet Backend optional per mDNS/QR.
3. Frontend erzeugt im eingeloggten SpotFamServ einen kurzlebigen Reader-Claim.
4. Nutzer gibt Claim-Code im Captive Portal ein oder scannt einen QR.
5. ESP tauscht Claim gegen `reader_id` und API-Key; Backend speichert Reader als bekanntes Geraet.
6. ESP speichert WLAN, Backend-URL, `reader_id`, API-Key und Firmware-Kanal in NVS.
7. ESP rebootet, verbindet sich ins Heim-WLAN und nutzt die bestehenden Reader-APIs.
8. OTA laeuft spaeter ueber ein Backend-Manifest; Update nur bei passendem Board/Firmware-Kanal und gueltigem Hash.

## Bindende Stop-Gates vor Umsetzung
1. **Gate A - Hardware-Fakt:** PN532/HW-147-Busmodus, Pinout, DIP-Schalter und UID-Gleichheit gegen Pi sind dokumentiert. Ohne Gate A keine Loet-/Gehause-/Pinout-Finalisierung.
2. **Gate B - Provisioning-Vertrag:** Backend-Claim-API, Ablaufzeit, Einmalnutzung, ReaderDevice-Erzeugung und API-Key-Ausgabe sind als additive API festgelegt. Bestehende Scan/Next/Previous-Endpunkte bleiben unveraendert.
3. **Gate C - Secret-Speicherung:** Firmware schreibt WLAN, Backend-URL, `reader_id` und API-Key in NVS; `secrets.h` bleibt nur Dev-/Factory-Fallback und wird nicht Produktfluss.
4. **Gate D - Recovery:** Reset-Taster-/LED-Verhalten ist vor OTA definiert, damit ein fehlkonfigurierter Reader ohne PC wieder erreichbar wird.
5. **Gate E - OTA-Herkunft:** Entscheidung Hash-only-MVP vs. signiertes Manifest/Firmware ist explizit dokumentiert, bevor OTA produktiv aktiviert wird.

## Dry-Run & Blind-Spot-Review (ABSOLUTER BLOCKER)
- Nach Plan-Bestaetigung und vor Implementierung MUSS ein Dry-Run mit dem staerksten verfuegbaren Reasoning-Modell/Subagenten durchgefuehrt werden.
- Dry-Run-Fragen:
  1. Welche Annahmen sind noch unbewiesen (Hardware, API, Security, UX, Deploy)?
  2. Welche bestehenden Pi-/Reader-/Scan-Pfade koennten regressieren?
  3. Welche Tests oder OpenAPI-Pruefungen fehlen fuer den ersten Umsetzungsschnitt?
  4. Wo koennte der Plan zu gross oder in der falschen Reihenfolge sein?
  5. Welche Sicherheitsrisiken sind vor Code-Start entscheidungsreif?
- Kritische Befunde werden vor Implementierung in diesem Plan ergaenzt.
- Umsetzung MUSS danach mit Sonnet oder GPT-5.5 erfolgen, sofern der Nutzer kein anderes verfuegbares Modell explizit vorgibt.
- Reine Dokumentation/Uebersetzung MUSS mit Haiku erfolgen, sofern verfuegbar; wenn Haiku nicht verfuegbar ist, MUSS der kleinste geeignete schnelle Modell-Fallback benannt werden.
- ABSOLUTER BLOCKER: Keine Code-, Schema-, Infra-, Firmware- oder UI-Implementierung starten, solange Dry-Run fehlt, falsch modelliert wurde, nicht dokumentiert wurde oder kritische Befunde offen sind.
- ABSOLUTER BLOCKER: Sprint nicht als abgeschlossen markieren, wenn Modell-Gates, Dry-Run-Dokumentation oder Fallback-Benennung fehlen.
- Aktueller Status: Dry-Run mit `claude-4.5-opus-high-thinking` durchgefuehrt; kritische Befunde sind unten eingearbeitet.

## Dry-Run-Befunde und Verträge

### Claim-Format und Claim-API-Vertrag
- Claim-Code: 8 Zeichen, menschenlesbar, Zeichensatz `A-Z2-9` ohne `0`, `1`, `I`, `O`; Eingabe case-insensitive, Speicherung normalisiert uppercase.
- TTL: 10 Minuten ab Erstellung.
- Einmalnutzung: Claim darf genau einmal eingelöst werden; danach wird `used_at` gesetzt.
- Plain-API-Key wird nur in der erfolgreichen Aktivierungsantwort an den ESP ausgegeben, nie dauerhaft im Frontend angezeigt und nie im Backend gespeichert.
- `POST /api/v1/readers/claims`: Admin-/Frontend-Flow erzeugt Claim. Request optional `{ "reader_name": "...", "fw_channel": "stable" }`. Response `201` enthält `{ "claim_code": "...", "expires_at": "...", "backend_url": "..." }`.
- `POST /api/v1/readers/claims/{claimCode}/activate`: ESP löst Claim ein. Request `{ "device_nonce": "...", "board": "esp32-wroom-32", "firmware_version": "x.y.z" }`. Response `201` enthält `{ "reader_id": "...", "api_key": "...", "fw_channel": "stable" }`.
- Fehlercodes: `400 invalid_request`, `404 unknown_claim`, `410 expired_claim`, `409 claim_already_used`, `422 unsupported_board`, `429 too_many_attempts`.
- Persistenz: Claim-Entity/-Tabelle mit `claim_code_hash`, `expires_at`, `used_at`, `reader_id`, `created_at`; kein Plain-Claim-Code-Logging.
- Aktivierung muss atomar/transaktional sein, damit parallele Einlösungen nicht zwei API-Keys erzeugen.

### NVS-Schema
- Namespace: `spotfam`.
- Gespeicherte Keys: `wifi_ssid`, `wifi_password`, `backend_url`, `reader_id`, `reader_api_key`, `fw_channel`, `board`, optional `provisioned_at`.
- Niemals speichern: Spotify Access Tokens, Spotify Refresh Tokens, Spotify Client Secret, Backend-Admin-Credentials, Plain Claim-Code nach erfolgreicher Aktivierung, globale `READER_API_KEY` als Produktfluss.
- Reset-Verhalten:
  - kurz drücken: Status/Retry, keine NVS-Löschung.
  - lang drücken: WLAN/Backend/Claim-Flow erneut starten; bestehende Reader-ID/API-Key zunächst behalten, bis neuer Claim erfolgreich ist.
  - sehr lang drücken / Factory Reset: gesamter `spotfam`-Namespace wird gelöscht; Gerät startet unprovisioniert im Setup-AP.
  - Nach Factory Reset muss der alte per-Reader-Key im Backend manuell oder automatisch widerrufen werden.

### QR- und Captive-Portal-Payload
- MVP-Entscheidung: QR enthält mindestens Backend-URL und Claim-Code. Code-only reicht nicht, weil der ESP wissen muss, welches Backend er kontaktieren soll.
- Mindestformat: `{ "backend_url": "http://192.168.1.91:8080", "claim_code": "ABCD2345" }`.
- Captive Portal ist nur im unprovisionierten Zustand oder nach explizitem Reset aktiv.
- Portal fragt WLAN-SSID, WLAN-Passwort, Backend-URL und Claim-Code ab; Backend-URL und Claim-Code dürfen per QR vorausgefüllt werden.
- WLAN-Passwort und API-Key dürfen nicht geloggt oder in HTML/JS erneut ausgespielt werden.
- Sicherheitsgrenze: Captive Portal ist kein Hochsicherheitskanal. MVP-Risiko wird begrenzt durch kurzes Setup-Fenster, kurzlebigen Claim, per-Reader-Key, Rotation/Revoke und keine Spotify-Secrets auf dem ESP.

### OTA-Manifest-Spec
- Endpunkt: `GET /api/v1/readers/firmware/manifest?board=esp32-wroom-32&channel=stable&current_version=x.y.z`.
- Response `200` bei Update: `{ "board": "esp32-wroom-32", "channel": "stable", "version": "1.0.1", "min_version": "1.0.0", "download_url": "/api/v1/readers/firmware/esp32-wroom-32/stable/1.0.1.bin", "sha256": "...", "signature": null, "released_at": "..." }`.
- Response `204`: kein Update verfügbar.
- MVP: SHA-256 ist Pflicht gegen kaputte oder teilweise Downloads.
- Signatur ist vor Consumer-Release Pflichtentscheidung: entweder signiertes Manifest/Firmware implementieren oder Hash-only explizit als lokales MVP-Risiko akzeptieren.
- ESP akzeptiert Manifest nur, wenn `board` exakt passt und `channel` zum gespeicherten `fw_channel` passt.
- Downgrade verboten: `version` muss größer als `current_version` sein.
- `current_version < min_version` ist ein inkompatibler Zustand und darf nicht blind updaten.
- Fehlgeschlagenes OTA darf laufende Firmware nicht unbrauchbar machen.

### Hardware-Gate HW-0 vor Sprint-Done
Der Nutzer muss physisch verifizieren und dokumentieren:
- Exaktes PN532/HW-147-Modul, DIP-Schalterstellung, gewählter Bus und Begründung.
- ESP32-Pinout: VCC, GND, SCL/SDA oder SCK/MISO/MOSI/SS, IRQ/RST falls verwendet.
- PN532 wird vom ESP32 erkannt.
- Eine bekannte Karte wird gelesen.
- UID derselben Karte ist identisch zum Pi-PN532-UID-String.
- Scan über `/api/v1/readers/scan` funktioniert mit bestehendem Backend-Vertrag.
- Next/Previous-Taster funktionieren oder werden explizit als späterer Schritt markiert.
- LED-/Statussignale für Boot, WLAN, Backend-Fehler und Scan-Erfolg sind nachvollziehbar.
- ESP32-Flashgröße und OTA-fähige Partitionstabelle sind verifiziert.
- Test-OTA installiert eine Test-Firmware; falscher Hash oder falscher Board-Typ wird abgelehnt.
- Evidence in Plan/Doku: Datum, Board-Modell, Busmodus, DIP-Stellung, Pinout-Tabelle, Test-UID Pi vs. ESP, Ergebnis je Akzeptanzkriterium, offene Hardware-Abweichungen.

### Blocker-Klassifikation aus Dry-Run
**BLOCKER vor Code:**
- Keine Firmware-Implementierung vor verifiziertem PN532/HW-147 am ESP32.
- Keine Pinout-/Löt-/Gehäuseentscheidung vor DIP-/Bus-/UID-Verifikation.
- Keine OTA-Firmwarearbeit vor Prüfung von Flashgröße und OTA-Partitionstabelle.
- Keine Claim-Implementierung ohne finalen Claim-API-Vertrag.

**BLOCKER vor Merge:**
- Claim-Endpunkte inklusive Tests und OpenAPI dokumentiert.
- Claim-Einmalnutzung, TTL und Fehlercodes getestet.
- Bestehende `/readers/scan|next|previous` Response-Shapes unverändert.
- Frontend-DTO enthält `has_api_key`.
- OTA-Manifest-Endpunkt inklusive Board-/Channel-/Version-Regeln spezifiziert und getestet.
- Keine Plain-Secrets in Logs, Frontend-Persistenz oder Backend-Speicherung.

**BLOCKER vor Sprint-Done/Release:**
- Hardware-Gate HW-0 vollständig erfüllt.
- ESP-Provisioning am echten Gerät getestet.
- Scan, Reboot, Reset und OTA am echten Gerät getestet.
- OTA-Herkunftsentscheidung getroffen: Signatur umgesetzt oder Hash-only bewusst als MVP-Risiko dokumentiert.
- Firmware-Build reproduzierbar, mindestens Compile-Check in CI. *(Software-Teil erledigt: CI-Job `Firmware Compile (ESP32)`; HW-0 bleibt blockierend.)*
- Nutzer-/Hardware-Doku aktualisiert.

**Nicht-blockierende Risiken:**
- Captive Portal ist im MVP lokal angreifbar; Risiko durch kurzes Setup-Fenster begrenzen.
- NVS ist kein Hardware-Sicherheitsanker; Risiko durch per-Reader-Key, Rotation und Revoke begrenzen.
- mDNS/Discovery ist optional; manuelle Backend-URL bleibt Pflicht-Fallback.
- QR-Format kann später erweitert werden, solange Backend-URL + Claim-Code stabil bleiben.

**Korrektur aus Dry-Run:** CI-grün + Merge zählt als Software-Fortschritt, aber nicht als Sprint-Done, solange HW-0 nicht physisch erfüllt ist.

## Umsetzungsschnitte

### Phase 0 - Hardware- und Bus-Prototyp
- PN532/HW-147 am ESP32 ohne Loeten testen.
- Busentscheidung I2C vs. SPI mit realem Modul treffen.
- UID einer bekannten Karte gegen Pi-PN532 vergleichen.
- LED-/Button-Pins finalisieren.
- Werkzeug bereit: `firmware/spotfam_pn532_probe/` (Diagnose-Sketch, I2C/SPI umschaltbar,
  UID im Pi-Format) + Runbook `docs/hw0-pn532-runbook.md`. Ausfuehrung ist physisch
  durch den Nutzer; aus WSL2 ist der ESP32 mangels USB-Durchreichung nicht erreichbar.

### Phase 1 - Firmware-Konfiguration ohne `secrets.h`
- NVS/Preferences einfuehren.
- Development-Fallback behalten, aber nicht als Produktfluss.
- Config-State-Machine: unprovisioned, provisioning, provisioned, error.

### Phase 2 - Captive Portal
- AP-Modus nur wenn unprovisioniert oder Reset-Taster lange gedrueckt.
- Minimal UI: WLAN, Backend, Claim-Code, Status.
- Fehler klar anzeigen: WLAN falsch, Backend nicht erreichbar, Claim abgelaufen.

### Phase 3 - Backend-Claim und Frontend-Onboarding
- Backend-Endpunkte fuer Claim erstellen, Einmalnutzung, Ablauf, API-Key-Ausgabe.
- Bestehende `ReaderDevice`-/per-Reader-Key-Logik wiederverwenden: Claim erzeugt oder claimt einen Reader und gibt den Plain-Key genau einmal an den ESP aus.
- Bestehenden globalen `READER_API_KEY`-Rueckfall fuer Bestandsreader nicht entfernen; provisionierte Reader mit eigenem Key bleiben isoliert validierbar/revozierbar.
- Frontend-Flow "Reader hinzufuegen" mit Claim-Code/QR und Status.
- Reader nach Provisioning einem Profil/Raum/Geraet zuordnen.

### Phase 4 - OTA
- Firmware-Version im ESP und Backend sichtbar machen.
- OTA-Manifest im Backend oder statisches Release-Manifest definieren.
- Board-Typ, Firmware-Kanal, SemVer/Build-Nummer und Mindestversion gegen Downgrade pruefen.
- Download mindestens mit SHA-256 gegen Teil-/Korruptionsfehler pruefen; fuer Consumer-Release Signatur oder signiertes Manifest entscheiden.
- Rollback-/Failure-Verhalten dokumentieren: fehlgeschlagener Download darf die laufende Firmware nicht unbrauchbar machen.

### Phase 5 - Consumer-Recovery
- Reset-Taster: kurz = Status/Retry, lang = Provisioning neu starten, sehr lang = Factory Reset.
- LED-Muster fuer Boot, Portal, WLAN, Backend, Scan-Erfolg, Fehler.
- Runbook fuer "Reader verbindet nicht" und "OTA fehlgeschlagen".

## Akzeptanzkriterien
1. Ein unprovisionierter ESP32-PN532-Reader oeffnet ein SpotFam-Setup-WLAN und laesst sich ohne Serial Monitor einrichten.
2. Nach Provisioning existiert im Backend ein Reader mit eindeutiger `reader_id` und gehashtem/verwaltetem API-Key.
3. Ein Kartenscan vom ESP erzeugt denselben UID-String wie der Pi-PN532 fuer dieselbe Karte.
4. Scan, Next und Previous funktionieren ueber die bestehenden Backend-Endpunkte.
5. WLAN-Fehler, Backend-Fehler und abgelaufener Claim sind fuer Nutzer unterscheidbar.
6. OTA kann eine Test-Firmware installieren und lehnt ein Artefakt mit falschem Hash/Board-Typ ab.
7. Factory Reset entfernt lokale Provisioning-Daten und startet wieder im Setup-Modus.
8. Bestehende Pi-Reader und bestehende `scan|next|previous`-Response-Shapes bleiben unveraendert.
9. Plain-API-Keys werden weder im Backend gespeichert noch im Frontend dauerhaft angezeigt; sie erscheinen nur im Claim-/Provisioning-Austausch.

## Definition of Done
- [ ] Plan vom Nutzer bestaetigt.
- [ ] Dry-Run/Blind-Spot-Review mit staerkstem verfuegbarem Reasoning-Modell durchgefuehrt und Befunde eingearbeitet.
- [ ] Bus-/Pinout-Entscheidung mit realem PN532/HW-147 verifiziert und dokumentiert.
- [x] Firmware kompiliert reproduzierbar (CI-Job `Firmware Compile (ESP32)`; Baseline MFRC522-Sketch, nicht PN532/Provisioning).
- [ ] Backend-Tests fuer Claim, API-Key-Ausgabe, Ablauf und OTA-Manifest.
- [ ] Frontend-Flow manuell verifiziert.
- [ ] ESP-Hardwaretest: Provisioning, Scan, Reboot, OTA, Reset.
- [ ] OpenAPI aktualisiert, falls neue Backend-Endpunkte entstehen.
- [ ] Doku fuer Nutzer-Onboarding und Hardware-Pinout geschrieben.
- [ ] `tasks/decisions.md` und `tasks/lessons.md` aktualisiert.

## Risiken / Offene Fragen
- **PN532-Buswahl:** I2C ist weniger Pins, hatte am Pi aber Lockup-Diagnose; SPI ist oft robuster, braucht aber mehr Leitungen und korrekten DIP-Modus. Entscheidung erst nach Test mit dem echten Modul.
- **HW-147-DIP-Modus:** Nicht aus Erinnerung konfigurieren. Modulaufdruck/Datenblatt/faktischer Test muessen den DIP-Zustand bestaetigen.
- **OTA-Sicherheit:** Ohne Signatur ist Hash-Pruefung nur Integritaet, keine echte Herkunftssicherung. Fuer Consumer-Anspruch Signatur ernsthaft bewerten und Gate E vor Produktiv-OTA schliessen.
- **Captive-Portal-Sicherheit:** Setup-AP kann WLAN-Daten abgreifbar machen, wenn er offen/lange aktiv ist. Portal nur in Setup/Reset, kurze Aktivfenster, keine Logs mit WLAN-Passwort oder API-Key.
- **Physischer Zugriff:** API-Key in ESP-NVS ist bei physischem Zugriff potentiell extrahierbar. Risiko wird ueber per-Reader-Keys, Rotation/Revoke und begrenzte Backend-Berechtigung behandelt.
- **Backend-Erreichbarkeit:** LAN-IP ist einfach, mDNS/Discovery waere nutzerfreundlicher, aber plattformabhaengiger.
- **Secrets im bestehenden Repo:** Aktuelle lokale `secrets.h` enthaelt echte Werte und bleibt git-ignoriert; Produktfluss muss davon weg.
- **Flash-Partition:** OTA braucht passende Partitionstabelle; konkrete ESP32-Boards pruefen.
- **Scope-Risiko:** Wenn Phase 0 Hardwareprobleme zeigt, werden Captive Portal und OTA nicht "trotzdem" implementiert; dann Plan stoppen und Hardwareentscheidung neu treffen.

## Verifikations-Log
- Verifiziert: Bestehende ESP-Firmware nutzt MFRC522/SPI | Code-Review `firmware/spotfam_reader/spotfam_reader.ino` und `config.h` | Ergebnis: Zielhardware weicht ab | 2026-06-03
- Verifiziert: Nutzer bestaetigt PN532/HW-147 auch fuer ESP-Geraete | Chat-Kontext | Ergebnis: Plan muss PN532 statt MFRC522 priorisieren | 2026-06-03
- Verifiziert: Pi-PN532 E2E-Scan funktioniert | Journal `spotfam-pi-reader` zeigte `outcome=success message=Playback started` fuer UID `E3D43735` | Ergebnis: Backend-Scanpfad ist fuer PN532-UIDs grundsaetzlich brauchbar | 2026-06-03
- Verifiziert: Dry-Run/Blind-Spot-Review | Subagent `claude-4.5-opus-high-thinking` | Ergebnis: Plan um Claim-Vertrag, NVS, QR/Payload, OTA-Manifest, HW-0 und Blocker-Klassifikation erweitert | 2026-06-03
- Verifiziert: Backend-Claim/Manifest fokussiert | `./vendor/bin/phpunit tests/Module/Scan/Infrastructure/Http/ReaderClaimControllerTest.php tests/Module/Scan/Infrastructure/Http/ReaderFirmwareControllerTest.php tests/Module/Scan/Infrastructure/Http/ScanControllerTest.php` | Ergebnis: 17 Tests, 39 Assertions gruen | 2026-06-03
- Verifiziert: Backend gesamt | `./vendor/bin/phpunit` | Ergebnis: 63 Tests, 190 Assertions gruen | 2026-06-03
- Verifiziert: Backend statische Analyse | `composer stan` | Ergebnis: PHPStan gruen, keine Fehler | 2026-06-03
- Verifiziert: Composer Metadata/Security | `composer validate --no-check-publish` und `composer audit` | Ergebnis: gueltig, keine Advisories | 2026-06-03
- Verifiziert: Frontend Build | `pnpm build` | Ergebnis: TypeScript/Vite Build gruen; bekannter Chunk-Size-Hinweis | 2026-06-03
- Verifiziert: Frontend Tests | `pnpm exec vitest run --passWithNoTests` | Ergebnis: gruen, keine Testdateien vorhanden | 2026-06-03
- Verifiziert: Doku-Modell-Gate | Haiku nicht verfuegbar, Fallback `composer-2.5-fast` fuer reine Doku genutzt | Ergebnis: `docs/esp-reader-provisioning.md`, `docs/reader-box-mapping.md`, `CHANGELOG.md` aktualisiert | 2026-06-03
- Verifiziert: Firmware-Build reproduzierbar (Software-Teil) | CI-Job `Firmware Compile (ESP32)` in `.github/workflows/ci.yml`: `arduino-cli`, `esp32:esp32@3.3.8`, `ArduinoJson@7.4.3`, `MFRC522@1.4.12`, `secrets.h.example` → `secrets.h`, `arduino-cli compile --fqbn esp32:esp32:esp32 .` in `firmware/spotfam_reader` | Ergebnis: Baseline-Compile des bestehenden MFRC522-Sketches grün (lokal: 1053224 B Program Storage ~80 %, 48496 B RAM ~14 %); kein PN532, kein Captive Portal, kein NVS, kein OTA-Client | 2026-06-03
- Verifiziert: PN532-HW-0-Probe kompiliert reproduzierbar (Software-Teil) | `arduino-cli compile --fqbn esp32:esp32:esp32` fuer `firmware/spotfam_pn532_probe` in beiden Bus-Varianten (I2C: 317864 B/24 %, SPI: 317888 B/24 %); CI-Job `Firmware Compile (ESP32)` um Probe + `Adafruit PN532@1.3.4`/`Adafruit BusIO@1.17.4` erweitert | Ergebnis: HW-0-Pruefwerkzeug steht bereit; reine Hardware-Ausfuehrung bleibt offen | 2026-06-03
- Verifiziert: Nativer Pi-Flash-Pfad funktioniert (Gate-0-Toolchain/Transport) | Pi (192.168.1.91), `esptool v5.2.0`, `write-flash 0x0 merged.bin` (Hash verified) + pyserial-Reset/Read | Ergebnis: Flash+Boot+Serial vom Pi OK; Chip ESP32-D0WD-V3 rev v3.1, 4 MB Flash, MAC 78:EE:4C:01:6B:04, Auto-Reset via RTS ok (kein BOOT-Taster noetig) | 2026-06-04
- Verifiziert/Entscheidung: WSL2/usbipd fuer CP210x ungeeignet | usbip-Transport bricht RX ab (`urb -32`, `returned no data`) trotz usbipd-Update | Ergebnis: Flash-Pfad ist nativer Host (Pi), nicht WSL/usbip; stuetzt D-021 (Host-Agent) | 2026-06-04
- Offen: HW-0 PN532-Erkennung + UID | PN532/HW-147 noch nicht an ESP geloetet (DIP=I2C); Probe meldet `[FAIL] Kein PN532 gefunden` bis Verdrahtung steht | Ergebnis: nach Loeten (I2C: VCC->3V3, GND, SDA->GPIO21, SCL->GPIO22) Karte auflegen, UID gegen Pi-Referenz `E3D43735` pruefen | 2026-06-04
- Hinweis: Probe auf `PN532_IRQ=-1` umgestellt (I2C-Polling ohne IRQ-Draht); lokal in `firmware/spotfam_pn532_probe` geaendert, noch nicht committet | 2026-06-04

## Abgeschlossen (Software-Schnitt, ohne HW-0)
- Backend-Claim/Manifest, Frontend „Reader hinzufügen“, Doku-Runbook, CI-Firmware-Baseline-Compile (MFRC522).
- **Nicht abgeschlossen:** HW-0, ESP-Firmware (PN532, Captive Portal, NVS, OTA-Client), E2E am echten ESP.
