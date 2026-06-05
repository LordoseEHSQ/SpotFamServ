# Sprint 6 – Reader-Station: UX, System-Config-DB, Flash-Zeit-NVS

**Milestone:** Sprint 06 – Reader-Station: UX, System-Config-DB, Flash-Zeit-NVS (#6) · **Status:** Done (PR #68 squash-merged, Tag `v0.6.0`)
**Release:** `v0.6.0`
**Branch:** `feat/sprint-06-reader-config-db` · **Worktree:** `../SpotFamServ-sprint-06`
**Zeitraum:** 2026-06-05
**Plan:** `tasks/plan-sprint-06-reader-station-config-db.md`

---

## Sprint-Ziel

Reader-Station bedienbar machen (UI-Bugs raus), systemweite Konfiguration in die DB heben
(WLAN/Backend-URL/OTA-Kanal/Frontend-URL statt nur Env), und die Reader-Config zur Flash-Zeit
per NVS-Partition auf den ESP32 injizieren – ohne `secrets.h`-Handarbeit.

---

## WorkPackages – Ergebnis

| WP | Issue | Titel | Status | Milestone |
|---|---|---|---|---|
| A | #63 | Provisioning-UI Bugfixes/UX (Chip-Match, flashSize, Dialog, Geräte-Panel) | ✅ Code | Sprint 06 |
| B | #64 | Systemweite Konfiguration in DB (Reader-Netzwerk + Frontend-URL, Env-Fallback) | ✅ Code | Sprint 06 |
| C | #65 | Flash-Zeit-NVS-Injektion (reader-config + vendored NVS-Generator + Read-back) | ✅ Code | Sprint 06 |
| D | #66 | Reader-Firmware NVS-first (WLAN-Join + Self-Claim) auf PN532 | ⏭️ Ausgelagert | Sprint 08 (PN532) |
| E | #67 | Realer RFID-E2E (Scan→Playback) auf PN532-Hardware | ⏭️ Ausgelagert | Sprint 08 (PN532) |

> D+E sind bewusst aus Sprint 06 herausgelöst (D-031): Der Config-Layer der Firmware soll erst
> mit der PN532-Migration (D-018) geschrieben werden, um Doppelarbeit am MFRC522-Sketch zu vermeiden.

---

## Acceptance Criteria – Zwischenstand

| Kriterium | Status | Anmerkung |
|---|---|---|
| Provisioning-UI ohne False-Positive-Chip-Blocks | ✅ | familienbasierter `chipMatch.ts`, vitest 11 |
| `flashSize` korrekt angezeigt | ✅ | Typ `number`→`string` |
| System-Config in DB (DB-then-env je Feld) | ✅ | `system_configuration`-Singleton, Provider + Tests |
| Flash-Zeit-NVS byte-genau == esp-idf | ✅ | 0 Diff-Bytes gegen offizielles Tool, pytest 69 |
| NVS-Injektion + Read-back-Verify im Agent | ✅ | gated über Config-Vollständigkeit + `INJECT_READER_CONFIG` |
| Backend grün (PHPStan L8 / lint / PHPUnit) | ✅ | volle Suite **147** Tests (inkl. ReaderFirmware-Regressionsfix) |
| Frontend grün (tsc / vitest / build) | ✅ | – |
| OpenAPI additiv (2 neue Pfade, 0 entfernt) | ✅ | lokal + CI (oasdiff) grün |
| CI grün | ✅ | PR #68: Backend 8.4/8.5, Frontend, oasdiff, Flash-Agent, Trivy, ESP32-Compile |
| Migration gegen echte DB | ⏳ | nur statisch geprüft; Laufzeit erst beim Pi-Deploy (Tag-Trigger) |
| Tag v0.6.0 | ✅ | Squash-Merge → main, Tag gesetzt |

---

## Technische Erkenntnisse / Abweichungen vom Plan

### C3 (per-Job-UI-Checkbox „Config mitflashen") zurückgestellt (D-036)
Ein per-Job-Flag hätte eine **zweite Migration** auf `provisioning_flash_job` + breites Plumbing
erfordert. Stattdessen: Injektion gegated über (a) Config-Vollständigkeit (`reader-config.complete`)
und (b) Agent-Env-Flag `INJECT_READER_CONFIG`. Steuer-UI = die System-Card aus Phase B.

### Vendored NVS-Generator statt Pip-Dependency (D-036)
`flash_agent/nvs.py` repliziert die String-/Namespace-Teilmenge von esp-idf `nvs_partition_gen.py`
**byte-genau** (verifiziert per Subprozess-Vergleich gegen das offizielle Tool, 0 Diff). Kein
zusätzliches Pip-Paket auf dem Pi.

### Regressionsfund beim Sprint-Abschluss
Der Konstruktor-Eingriff an `ReaderFirmwareController` (OTA-Kanal aus DB) brach dessen Unit-Test
(3 Fehler) – erst durch die **volle** Backend-Suite beim Closeout gefunden, nicht durch die
Teilmengen-Prüfung während der Implementierung. Fix: Test injiziert den Provider-Mock. (Lesson L-030.)

---

## Decisions

- **D-028** NVS-Injektion als primärer Config-Pfad.
- **D-029** Single `SystemConfiguration`-Entity (typisiertes Singleton) statt Key/Value-Store.
- **D-030** Maschinen-Keys (READER_API_KEY etc.) bleiben env-kanonisch (keine DB-Rotation in diesem Sprint).
- **D-031** Phase D/E aus Sprint 06 zurückgestellt bis PN532-Firmware-Migration.
- **D-036** vendored NVS-Generator + Injektions-Gate (statt per-Job-UI-Flag) + NVS-Key-Vertrag.
  (Ursprünglich D-032; umnummeriert wegen Reservierung D-032…D-035 durch den Sprint-07-Plan.)

---

## Blockierend (User/Hardware)

- **Migration-Laufzeit:** `Version20260605090000_system_configuration` ist nur statisch geprüft;
  erstmalige Ausführung erst beim Deploy/Pi (Partial-Unique-Index + verschlüsselte TEXT-Spalten).
- **Geräte-autoritative NVS-Verifikation:** dass der ESP das NVS *liest*, ist erst mit der
  NVS-fähigen Reader-Firmware (Phase D / PN532) prüfbar – Read-back ist nur struktur-/CRC-konsistent.
- **PN532-Hardware:** D+E (Sprint 08) brauchen die PN532-Migration + reale Hardware.
- **Tag v0.6.0:** erst nach CI grün auf `main` (Squash-Merge via PR).

---

## Bekannte Grenzen (ehrlich)

- CI lief **nur lokal** (PHPStan L8, lint:container, PHPUnit 147, pytest 69, tsc/vitest/build, oasdiff-Ersatz
  via lokaler OpenAPI-Regeneration). GitHub-CI (inkl. Trivy/oasdiff-Action) steht mit dem PR aus.
- Sprint lief ohne vorab angelegte GitHub-Issues; Milestone + WP-Issues wurden beim Closeout
  **retroaktiv** angelegt (Regelabweichung von `sprint-workflow`, dokumentiert).
