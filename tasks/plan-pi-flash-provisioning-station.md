# Plan: Pi-Flash- & Provisioning-Station (ESP am Pi, sichtbar/steuerbar im Server)

**Erstellt:** 2026-06-04
**Status:** BESTAETIGT (User, 2026-06-04: Host-Agent ja, Gate 0 auf Pi, Live via WebSocket/SSE).
Implementierung erst nach Gate 0 (nativer Flash bewiesen) UND Dry-Run/Modell-Gate. Decision: D-021.

## Ziel (in einem Satz)
Ein ESP wird per USB an den Raspberry Pi gesteckt, der SpotFamServ-Server **erkennt
das Geraet automatisch**, zeigt Chip/MAC/Flash/Status idiotensicher in der Web-UI,
flasht auf Knopfdruck eine **versionierte, gehashte Firmware**, protokolliert alles
sauber (Audit/journald) und bindet den Reader anschliessend ans Backend — robust
gegenueber verschiedenen ESP-Varianten und zukunftssicher zur bestehenden OTA-/Claim-Architektur.

## Abgrenzung / Nicht-Ziele (erste Schnitte)
- Kein beliebiger Firmware-Upload durch Web-Nutzer (nur registrierte Artefakte).
- Kein paralleles Massen-Flashen vieler Geraete gleichzeitig im MVP (1 Geraet/Port).
- Kein Ersatz des Captive-Portal-/OTA-Pfads; die Station ergaenzt den Hersteller-/Werkstatt-Flash.
- Keine Audio-/Spotify-Aenderungen.

---

## Kritischer Befund vorab (nicht schoenreden)
1. **Fundament unbewiesen:** Bisher ist nur *widerlegt*, dass Flashen ueber WSL2/usbipd
   funktioniert (CP210x-RX bricht ueber usbip ab: `urb stopped -32`, `returned no data`).
   Dass **nativer Pi-Flash** funktioniert, ist **noch nicht verifiziert**. Eine ganze
   Web-Station auf einem ungetesteten Flash-Fundament zu bauen, ist riskant.
   → **Gate 0** unten ist hartes Vorab-Gate.
2. **Sicherheit:** Web-getriggertes Flashen ist ein Code-Execution-Pfad auf dem Pi.
   Das MUSS streng eingegrenzt werden (Admin-only, nur gehashte/registrierte Artefakte,
   Agent verifiziert Herkunft+Hash, Audit, Rate-Limit). Sonst wird die Station zur Hintertuer.
3. **Privilege-Trennung:** Der Docker-`app`-Container darf **nicht** USB/`esptool` bekommen.
   Hardware-Zugriff gehoert in einen unprivilegierten Host-Dienst (konsistent mit D-P2 A).
4. **Variantenrisiko:** „verschiedene ESP-Varianten sicher" heisst: erkennen, gegen eine
   Whitelist pruefen, bei Unbekanntem **verweigern statt raten**. Falscher Flash auf falschen
   Chip muss technisch unmoeglich sein.

---

## Zielbild / Architektur

```
                          Raspberry Pi (Host, natives Linux)
  ┌───────────────────────────────────────────────────────────────────────┐
  │  ESP (USB) ──► Flash-Agent (Python, systemd, unprivilegiert, Gruppe     │
  │                 dialout)                                                │
  │   - Geraete-Discovery (pyserial list_ports + udev-Hotplug)             │
  │   - Chip-ID via esptool (chip_id/read_mac/flash_id) -> Variantenmatrix  │
  │   - Flash via esptool/arduino-cli (nur registriertes, sha256-geprueftes │
  │     Artefakt)                                                          │
  │   - optional: serielle NVS-Erstkonfig (Hersteller-Provisioning)        │
  │   - meldet Status/Progress/Logs an Backend (X-API-Key)                  │
  └───────────────────────────────┬───────────────────────────────────────┘
                                   │ HTTP (api/v1/provisioning/*)
  ┌────────────────────────────────▼──────────────────────────────────────┐
  │  Backend (Symfony, Docker, KEIN USB)                                    │
  │   - Provisioning-Station-API + Entities (Station, DetectedDevice,       │
  │     FlashJob) + ActivityLog                                             │
  │   - Firmware-Artefakt-Registry (board/channel/version/sha256/sig-ready) │
  │     -> teilt sich Pipeline mit OTA-Manifest                            │
  │   - Auth: Agent per API-Key; Web nur eingeloggte Admins                │
  └────────────────────────────────┬──────────────────────────────────────┘
                                    │ /api/v1 (relativ, gleicher Origin)
  ┌─────────────────────────────────▼─────────────────────────────────────┐
  │  Frontend (React SPA): Seite „Reader-Station / Provisioning"            │
  │   - Live erkannte Geraete (Port, Chip, MAC, Flash), Status            │
  │   - „Flashen" (Version/Channel waehlen) + Fortschritt + Ergebnis      │
  │   - Idiotensicher: klare Zustaende, verstaendliche Fehler, Detail-Log  │
  │   - Danach: Reader sichtbar + Claim-/Zuordnungs-Integration            │
  └────────────────────────────────────────────────────────────────────── ┘
```

### Warum Host-Agent statt USB im Container (Architekturentscheidung)
- **Least Privilege/Sicherheit:** Kein web-facing Container mit Roh-USB + Flash-Tooling.
- **Konsistenz:** Folgt D-P2 A (Pi-Reader ist bereits Host-systemd-Dienst).
- **Robustheit:** Docker-USB-Hotplug (`/dev/ttyUSB*` neu nach Replug) ist fragil; nativer
  Host-Stack + udev ist stabil.
- **Zukunft:** Derselbe Agent kann spaeter mehrere Stationen/Boards bedienen.

### Datenfluss „Flashen" (MVP)
1. Agent erkennt Geraet, liest Chip-ID/MAC/Flash → meldet `detected` ans Backend.
2. Web zeigt Geraet; Admin waehlt Firmware-Version/Channel und klickt „Flashen".
3. Backend erstellt `FlashJob` (pending) mit Artefakt-Referenz + sha256 (+ erwartetem Chip).
4. Agent holt Job (Poll), laedt Artefakt, **verifiziert sha256 und Chip-Match**.
5. Agent flasht (esptool), meldet Progress (z. B. Prozent/Schritte) und Endergebnis.
6. Backend persistiert Job-Status + ActivityLog; Web zeigt Erfolg/Fehler verstaendlich.
7. Optional (spaeter): Agent injiziert NVS-Erstkonfig oder Geraet geht in Captive Portal;
   Reader wird via bestehendem Claim-Flow gebunden.

---

## 4-Lens-Analyse

### Lens 1 — Runtime & Sprache
- Agent: Python 3 (wie `pi_reader`), `pyserial`, `esptool` (pip, gepinnt), optional `arduino-cli`.
  Pi arm64/Debian 13 — esptool/arduino-cli laufen nativ.
- Backend: Symfony 7.4 LTS / PHP 8.5 unveraendert.
- Frontend: React/Vite SPA unveraendert.
- Risiko: esptool/arduino-cli-Versionspinning fuer Reproduzierbarkeit (Build = Flash = OTA gleiche Toolchain).

### Lens 2 — Frameworks & Abhaengigkeiten
- Neue Host-Deps: `esptool`, `pyserial` (Agent). Begruendung: Standard fuer ESP-Flash/Detect.
- Backend moeglichst Bordmittel (Doctrine-Entities). **Live-Status via WebSocket/SSE** (D-021).
  Ehrliche Kosten: das bringt zusaetzliche Infra in den Stack — Symfony-idiomatisch ein
  **Mercure-SSE-Hub** (eigener Container, auch auf dem Pi/arm64) oder ein dedizierter WS-Dienst.
  Trade-off bewusst akzeptiert (geringere Latenz statt Polling-Einfachheit).
- Artefakt-Speicher: lokales, versioniertes Verzeichnis auf dem Pi + DB-Registry; CI liefert Artefakte.

### Lens 3 — Build, CI/CD & Tooling
- CI erweitert: Firmware-Artefakte pro Board/Channel bauen, sha256 erzeugen (knuepft an
  bestehenden `Firmware Compile (ESP32)`-Job an).
- Agent: `py_compile`/Lint wie `pi_reader`; Unit-Tests fuer Variantenmatrix/Chip-Mapping ohne Hardware.
- Backend: PHPUnit fuer Station-API + ActivityLog; OpenAPI additive Endpunkte (oasdiff non-breaking).
- Deploy: Agent als systemd-Unit ins Repo (analog `spotfam-pi-reader.service`), Runbook in `docs/`.

### Lens 4 — Security & Compliance
- Web-Flash nur fuer authentifizierte Admins; Agent↔Backend per API-Key (revozierbar).
- **Kein freier Binary-Upload** ueber die Station-API: nur registrierte Artefakte mit sha256;
  Agent flasht nur, wenn Hash und Ziel-Chip passen.
- Audit: Geraet erkannt, Flash gestartet/erfolgreich/fehlgeschlagen, NVS-Provisioning, Reset → ActivityLog.
- Keine Spotify-Tokens auf ESP; per-Reader-Key bleibt Pflicht. NVS-Erstkonfig (spaeter) loggt keine Secrets.
- Signatur der Firmware: Entscheidung gemeinsam mit OTA-Gate E (Hash-only MVP vs. signiert).

---

## Cross-Module-Antworten
1. **Upstream:** Hardware-Fakten (Pi-USB, ESP-Chip-Typ/Flash, Auto-Reset) + registrierte Artefakte.
   Falscher Chip/Artefakt-Mismatch muss hart abgelehnt werden.
2. **Downstream:** Erzeugt provisionierte Reader, die ueber bestehende `scan|next|previous`-APIs
   laufen. Claim-/ReaderDevice-Modell wird wiederverwendet, nicht dupliziert.
3. **Audit:** Ja — Flash/Provisioning/Reset sind sicherheitsrelevant → ActivityLog Pflicht.
4. **API-Vertrag:** Neue additive Endpunkte `/api/v1/provisioning/*`. Bestehende Vertraege unveraendert.
5. **Feature-Flags:** Station hinter Admin-UI/Flag, bis Gate 0 (nativer Flash) bewiesen ist.

---

## Bindende Gates
- **Gate 0 — Nativer Flash bewiesen (HART, Vorbedingung fuer ALLES):** Auf dem Pi (oder
  alternativ nativ Windows) gelingt EIN manueller `arduino-cli upload`/`esptool write_flash`
  des Probe-Sketches inkl. UID-Lesen (= HW-0 auf realer Toolchain). Ohne Gate 0 keine Station.
- **Gate A — Agent-Protokoll:** Vertrag Agent↔Backend (detect/jobs/progress/logs) additiv festgelegt.
- **Gate B — Artefakt-Registry:** Board/Channel/Version/sha256(/Signatur-Entscheid) definiert,
  CI erzeugt Artefakte reproduzierbar.
- **Gate C — Sicherheit:** Admin-Auth, kein freier Upload, Hash-/Chip-Verifikation, Audit, Rate-Limit.
- **Gate D — Variantenmatrix:** Unterstuetzte Chips/Boards-Whitelist; Unbekanntes wird verweigert.

## Dry-Run & Modell-Gate (ABSOLUTER BLOCKER, vor Implementierung)
- Vor Code: Dry-Run/Blind-Spot-Review mit dem staerksten verfuegbaren Reasoning-Modell,
  Befunde hier einarbeiten. Umsetzung mit Sonnet/GPT-5.5; reine Doku/Uebersetzung mit Haiku
  (oder benanntem Fallback). (Gemaess `planning-discipline.mdc`.)

---

## Umsetzungsschnitte (Phasen)
- **Phase 0 — Gate 0:** Nativer Flash + UID-Lesen real beweisen (Pi bevorzugt). Evidence in `docs/hw0-pn532-runbook.md`.
- **Phase 1 — Flash-Agent MVP (Host):** Discovery + Chip-ID + Flash eines lokal vorliegenden,
  gehashten Artefakts; strukturierte Logs (journald); CLI-Dry-Run ohne Backend. Variantenmatrix-Kern + Tests.
- **Phase 2 — Backend Station-API:** Entities (`Station`, `DetectedDevice`, `FlashJob`),
  Endpunkte detect/jobs/status/logs, Auth (API-Key), ActivityLog, OpenAPI, PHPUnit.
- **Phase 3 — Frontend Station-Seite:** Live-Geraeteliste, Flash-Aktion, Fortschritt, idiotensichere
  Zustaende/Fehler, ausklappbares Detail-Log.
- **Phase 4 — Artefakt-Registry + CI:** Firmware-Artefakte pro Board/Channel bauen + sha256;
  Integration mit OTA-Manifest; Signatur-Entscheidung (Gate E gemeinsam mit OTA).
- **Phase 5 — Serielles Hersteller-Provisioning:** NVS-Erstkonfig direkt nach Flash (WLAN/Backend/
  reader_id/api_key) ueber serielles Protokoll; Auto-Bind via Claim. (Setzt ESP-Zielfirmware voraus.)
- **Phase 6 — Haertung/Recovery/Doku:** weitere Varianten, Strom-/Concurrency-Haertung, Reset-Pfade,
  Runbook + Nutzer-Doku.

---

## Akzeptanzkriterien
1. ESP an Pi-USB wird automatisch erkannt; Web zeigt Port, Chip-Typ, MAC, Flash-Groesse, Status.
2. Flash startet per Web-Knopf; Fortschritt ist sichtbar; Erfolg/Fehler ist fuer Laien verstaendlich.
3. Nur registrierte, sha256-gepruefte Artefakte werden geflasht; Chip-Mismatch wird hart abgelehnt.
4. Unbekannte/unsupported Chips werden klar abgelehnt, nicht „blind" geflasht.
5. Jeder relevante Schritt steht im ActivityLog (Audit) und in journald (Detail).
6. Der Docker-`app`-Container hat keinerlei USB-/esptool-Zugriff.
7. Nach Flash (+ optional Provisioning) ist der Reader im Backend sichtbar und ueber bestehende APIs nutzbar.
8. Bestehende Reader-/Scan-/Claim-/OTA-Vertraege bleiben unveraendert (additive API).

## Definition of Done
- [ ] Plan bestaetigt.
- [ ] Dry-Run/Modell-Gate erfuellt, Befunde eingearbeitet.
- [ ] Gate 0 (nativer Flash) real bewiesen und dokumentiert.
- [ ] Agent + Backend-API + Frontend implementiert, getestet (Unit/Integration), OpenAPI aktuell.
- [ ] Sicherheits-Gates (Auth, kein freier Upload, Hash/Chip-Verifikation, Audit) erfuellt.
- [ ] Variantenmatrix + „verweigern statt raten" getestet.
- [ ] Runbook + Nutzer-Doku; `tasks/decisions.md` + `tasks/lessons.md` aktualisiert.

## Risiken / offene Fragen
- **Gate 0 ungeklaert:** Nativer Pi-Flash noch nicht bewiesen (nur usbip widerlegt). Falls Pi-Flash
  auch zickt → erst Hardware/Toolchain klaeren, bevor Station gebaut wird.
- **Live-Updates (entschieden D-021: WebSocket/SSE):** bringt zusaetzliche Infra (Mercure-Hub/WS-Dienst)
  auf den Pi. Risiko: ein weiterer Container/Dienst, der laufen/abgesichert/deployt werden muss.
- **Variantenbreite:** ESP32/S2/S3/C3 haben unterschiedliche Offsets/Reset-Eigenheiten; Whitelist
  startet klein (ESP32-WROOM-32) und waechst kontrolliert.
- **Strom/USB am Pi:** Mehrere ESP/instabile Ports → ggf. powered Hub; Concurrency 1 Job/Port.
- **Boards ohne BOOT-Taster** (wie dein AZ-Board, nur RST): native Auto-Reset noetig; Fallback GPIO0→GND.
- **Signatur:** Ohne Signatur ist Hash nur Integritaet, keine Herkunft (gemeinsam mit OTA-Gate E entscheiden).

## Protokollierte Entscheidung
- **D-021** (`tasks/decisions.md`): Host-Agent (Python systemd) + Backend ohne USB; Gate 0 zuerst auf Pi;
  Live via WebSocket/SSE; kein freier Firmware-Upload, nur registrierte sha256-gepruefte Artefakte +
  Chip-Match.
```
