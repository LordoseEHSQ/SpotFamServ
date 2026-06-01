# Sprint 2 – Core E2E: Spotify → Wobie via ESP32

**Milestone:** Sprint 2 – Core E2E (#3) · **Status:** In Arbeit (Code fertig; E2E + Tag offen) · **Ziel-Release:** `v0.2.0`

## Sprint-Goal
Eine RFID-Karte am ESP32 startet die gebundene Playlist auf der Wobie Box (End-to-End):
gültiges Spotify-Token serverseitig, Default-Device pro Profil, geflashter ESP32 mit korrektem
Reader-Feedback.

## Acceptance Criteria
- [ ] **#8** Spotify-Token serverseitig gespeichert: `status=connected`, `validate=valid`, Geräteliste abrufbar. _(User-Consent via Tunnel – blockiert)_
- [ ] **#9** Default-Device pro Profil setzbar (`PUT /profiles/{id}/default-device`), `default_device_name` korrekt; Playback ohne `device_id` spielt auf der Wobie Box.
- [ ] **#10** Realer Scan einer gebundenen Karte spielt die Playlist; Reader signalisiert Erfolg korrekt. _(Hardware-Flash – blockiert)_
- [x] Firmware-Outcome-Case-Bug behoben (lowercase), Reader-Feedback korrekt.
- [x] Backend-Tests grün (17), PHPStan Level 8 sauber; Frontend baut.

## WorkPackages
| Issue | Titel | Code-Stand |
|---|---|---|
| #8 | Spotify-Login serverseitig abschließen | Backend implementierungsreif; #8-Härtung (Activity-Log connect/refresh, Display-Name-Fix, fehlende Exception) umgesetzt. Verbleibend: **manueller OAuth-Consent (User/Tunnel)**. |
| #9 | Wobie-Discovery + Default-Device | Dedizierter Endpunkt `PUT/DELETE /profiles/{id}/default-device` + UseCase `SetDefaultDevice`, Spalte `default_device_name`, Frontend-Auswahl, Stale-ID-Re-Resolve. |
| #10 | ESP32 flashen + E2E-Test | Firmware-Outcome-Case-Fix + `secrets.h.example`-IP. Verbleibend: **physisches Flashen + realer Scan (Hardware)**. |

## Decisions
- **D-009:** Default-Device über dedizierten Endpunkt + UI (nicht über `AssignDevice`/Wizard).
- **D-010:** Outcome-Case-Mismatch firmware-seitig auf lowercase fixen (Backend = SSoT).
- **Schema:** Additive Spalte `family_profile.default_device_name` (Migration `Version20260601090000`),
  bewusste Ergänzung zur ursprünglichen Plan-Annahme „keine Migration" — begründet durch
  Namens-Persistenz (Anzeige) + Stale-ID-Re-Resolve ohne Modul-Zyklus FamilyProfile↔Device.

## Verifikation (lokal, PHP 8.5.6-Container)
- `php vendor/bin/phpunit` → **OK (17 tests, 45 assertions)**.
- `php vendor/bin/phpstan analyse --memory-limit=1G` → **No errors** (Level 8).
- `pnpm build` (Frontend) → grün; `vitest run --passWithNoTests` → grün.
- `nelmio:apidoc:dump` regeneriert; `openapi.yaml`-Diff rein additiv (neuer default-device-Pfad) → oasdiff unkritisch.

## Blind Spots / Risiken (offen)
- **R2 Stale Spotify device_id:** Re-Resolve per Name implementiert, aber abhängig von gesetztem
  `default_device_name` und einem gleichnamigen, verfügbaren Gerät.
- **R3 Discovery braucht sichtbare Wobie Box:** Kein IP-Scan (`192.168.1.10` ist im Code irrelevant).
- **R5 Premium/Scope-Fehler** → generisches `playback_failed` am Reader nicht differenzierbar (bekannt).
- **Sprint-übergreifend:** DHCP-IP nicht reserviert, kein Healthcheck-Alerting, Rollback manuell,
  `pnpm` fehlt auf dem Pi (Frontend-`dist/` separat bauen), `APP_ENV=dev` auf dem Pi.

## Offen bis „Sprint Done"
- OAuth-Consent (#8) + ESP32-Flash & realer E2E (#10) durch User/Hardware.
- PR-Merge, CI 5/5 grün, Tag `v0.2.0` (triggert Pi-Deploy), Sprint-03-Starter.
