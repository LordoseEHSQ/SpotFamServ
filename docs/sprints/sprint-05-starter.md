# Sprint-Starter-Prompt - Sprint 05: ESP32 Consumer-Provisioning, PN532 und OTA

Rolle: Lead-Engineer fuer **SpotFamServ** (ESP32/Pi-RFID -> Symfony-Backend -> Spotify -> Connect-Geraet). Antworte deutsch, kritisch, ohne Gefaelligkeits-Ja. Wenn du raten muesstest, frag.

Lies zuerst verbindlich:
- `docs/PROJECT_MAP.md`
- `tasks/todo.md`
- `tasks/decisions.md` (insb. `D-018`)
- `tasks/lessons.md` (insb. `L-018`)
- `tasks/plan-esp-consumer-provisioning-ota.md`
- `docs/sprints/sprint-04.md`
- Rules unter `.cursor/rules/`: `planning-discipline`, `sprint-workflow`, `chat-isolation-swarm`, `parallel-branch-workflow`, `project-architecture`

Arbeite nicht im Hauptcheckout auf `main`. Worktree/Branch fuer diesen Chat:
- Worktree: `../SpotFamServ-esp-provisioning`
- Branch: `feat/esp-consumer-provisioning-ota`

Verifizierter Stand:
- Pi-PN532-Reader funktioniert wieder; Journal zeigte `PN532 erkannt` und fuer UID `E3D43735` `outcome=success message=Playback started`.
- Sprint-4-Code/Release-Stand ist erledigt, aber `tasks/todo.md` kann noch veralteten E2E-Blocker enthalten; zu Beginn gegen GitHub/Pi-Stand abgleichen.
- Bestehende ESP-Firmware `firmware/spotfam_reader/spotfam_reader.ino` nutzt aktuell **MFRC522/SPI** und `secrets.h`.
- Nutzerfakt: ESP-Geraete sollen denselben RFID-Reader-Typ nutzen wie der Pi: **HW-147/PN532**.
- `tasks/plan-esp-consumer-provisioning-ota.md` ist als Draft angelegt und muss im neuen Chat kritisch geprueft/bestaetigt oder ueberarbeitet werden.
- `tasks/decisions.md` enthaelt `D-018`: Zielbild ist Captive Portal + Backend-Claim + NVS + OTA; USB nur fuer initialen Hersteller-/Entwickler-Flash.
- `tasks/lessons.md` enthaelt `L-018`: Nicht vom bestehenden MFRC522-Code auf Zielhardware schliessen; PN532/HW-147 separat planen/verifizieren.

Ziel dieses Sprints/Chats:
ESP32-Reader so planen und danach umsetzen, dass sie perspektivisch consumer-tauglich sind: einfache Einrichtung ohne PC nach Erstflash, PN532/HW-147 als Reader, keine produktive `secrets.h`-Konfiguration, Backend-Claim fuer Reader/API-Key, NVS-Konfiguration, OTA-Update-Pfad, klare Recovery-/Reset-Logik.

Harte Grenzen:
- Kein Loeten, bevor PN532-Bus, Pinout und DIP-Modus final verifiziert sind.
- Keine Spotify-Tokens auf dem ESP.
- Keine direkte Sonos-/Bose-Integration in diesem Sprint.
- Bestehende Pi-Reader- und Scan/Next/Previous-APIs duerfen nicht brechen.
- Plan-vor-Code-GATE bleibt verbindlich: keine Firmware-/Backend-/Frontend-Implementierung ohne bestaetigten Plan.

Blockierend / braucht User oder Hardware:
- Realer ESP32-WROOM-32 und HW-147/PN532 muessen fuer Phase-0-Test verfuegbar sein.
- Nutzer muss ggf. DIP-Schalter/Verkabelung am HW-147 pruefen.
- USB-Erstflash ist fuer Entwickler/Hersteller noetig; Consumer-Flow soll danach ohne PC funktionieren.
- Entscheidung I2C vs. SPI fuer PN532 am ESP ist offen und muss mit echtem Modul getestet werden.

Subagenten-Plan:
- Parallel `explore`: Backend-Reader-Modell, API-Key-/ReaderDevice-Flows, vorhandene Provisioning-/Settings-Muster finden.
- Parallel `explore`: Frontend-Reader-/Devices-/Settings-UI und Onboarding-Muster finden.
- Parallel `explore`: Firmware-Build-Setup, Arduino-Libs, aktuelle ESP-Pinbelegung und moegliche PN532/OTA-Libraries inventarisieren.
- Danach zentral synthetisieren: Plan scharfziehen, WorkPackages schneiden, Risiken/Entscheidungen aktualisieren.
- Hardware-Tests und Flashen bleiben im Hauptchat koordiniert, nicht blind an Subagenten auslagern.

Erste Aktion im neuen Chat:
1. In `../SpotFamServ-esp-provisioning` arbeiten und Status pruefen.
2. `tasks/plan-esp-consumer-provisioning-ota.md`, `D-018`, `L-018` lesen.
3. Plan kritisch reviewen: Ist Consumer-Flow wirklich einfach genug? Ist Phase 0 klein genug? Fehlen Security-/OTA-/Recovery-Details?
4. Falls Plan geaendert werden muss: Plan-Datei aktualisieren und auf User-Bestaetigung warten.
5. Erst nach User-Bestaetigung mit Subagenten-Schwarm/Implementierung beginnen.
