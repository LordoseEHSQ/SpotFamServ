# Plan: Sprint 2 – Core E2E (Spotify → Wobie via ESP32)

**Erstellt:** 2026-06-01
**Status:** In Progress (Plan bestätigt 2026-06-01; autonomer Code-Teil läuft, #8-Consent/#10-Flash bleiben User/Hardware)
**Milestone:** Sprint 2 (#3 "Core E2E") · **Release-Ziel:** Tag `v0.2.0`
**Branch/Worktree (nach Bestätigung):** `feat/sprint-02-core-e2e` in `../SpotFamServ-sprint-02`

---

## Scope
Eine RFID-Karte am ESP32 startet die gebundene Playlist auf der Wobie Box (E2E).
WorkPackages #8 (Spotify-Token serverseitig), #9 (Wobie-Discovery + Default-Device pro Profil),
#10 (ESP32 flashen + End-to-End-Test). Der Codeanteil ist klein und gezielt; der Großteil ist
**Operativ/Hardware** (manueller OAuth-Consent, physisches Flashen) und liegt beim User.

**Verifizierter Code-Stand (durch Explore-Schwarm, 2026-06-01):** Backend-Pfade für OAuth,
Token-Persistenz, Device-Discovery, Default-Device-Fallback und Scan→Playback sind **vorhanden
und verdrahtet**. Es geht primär um Lücken-Schließen + einen kritischen Firmware-Bug, nicht um Neubau.

---

## Betroffene Bereiche
- **Firmware** (`firmware/spotfam_reader/`) — Outcome-Case-Fix (Showstopper), `secrets.h.example`-IP.
- **Backend/Device** (`Module/Device`, `Module/FamilyProfile`) — Default-Device außerhalb Wizard setzbar machen + `default_device_name` auflösen (#9).
- **Backend/Spotify** (`Module/Spotify`) — optionale Härtung (Activity-Log, Display-Name-Bug) (#8).
- **Backend/Scan** (`Module/Scan`) — optional differenzierte Outcomes/Tests (#10).
- **Frontend** (`frontend/src`) — Default-Device-Auswahl außerhalb Wizard, Status-Anzeige-Fix (#9).
- **Tests** (`backend/tests/`) — fehlen für Device/Scan/Token-Refresh komplett.
- **Docs/OpenAPI/CHANGELOG** — Vertrag + Runbook + Sprint-Doku.
- **Ops/Pi** — Env-Konfig, Token-Lauf, Flashen (User/Hardware).

---

## 4-Lens-Analyse (Pflicht)

### Lens 1 – Runtime & Sprache
- Backend: PHP 8.5.6 / Symfony 7.4 LTS in Docker (`app` php-fpm), Postgres 17, nginx. Pi = arm64/Debian 13.
- Firmware: ESP32 WROOM-32, Arduino/`arduino-cli`, MFRC522.
- **Keine Runtime-/Versions-Änderung geplant.** Kein neuer Sprachstand, kein neues Base-Image.
- Risiko: `APP_SECRET` ist Schlüssel für die Token-Verschlüsselung (XChaCha20/Sodium). Änderung
  macht gespeicherte Tokens **unlesbar** → auf dem Pi stabil halten.

### Lens 2 – Frameworks & Abhängigkeiten
- **Keine neuen Dependencies** vorgesehen (weder composer noch npm). Lock-Impact: keiner.
- **Eine additive Migration (Abweichung von der ersten Annahme, bewusst):** Ursprünglich als „kein
  Schema" geplant. Bei der Umsetzung von D-009 + R2 wurde entschieden, `family_profile.default_device_name`
  (VARCHAR 255, nullable) zu persistieren statt den Namen cross-modul aus `spotify_device` zu lesen.
  Begründung: vermeidet einen Modul-Zyklus FamilyProfile↔Device (Device→FamilyProfile existiert bereits),
  ermöglicht Namens-Anzeige offline und Stale-ID-Re-Resolve per Name. Migration `Version20260601090000`
  ist rein additiv (nullable) → kein Datenverlust; `pg_dump`-Backup läuft vor Migration (D-007).
  `default_spotify_device_id` existierte bereits (`Version20250315000000.php:34`).

### Lens 3 – Build, CI/CD & Tooling
- `main` geschützt: PR + **5 required CI-Checks** (inkl. API-Drift `oasdiff`).
- **API-Drift-Gate:** Jede Änderung an `backend/openapi.yaml` muss konsistent + dokumentiert sein.
  Neue Endpunkte (#9) → openapi.yaml gepflegt, sonst rot.
- PHPStan-Baseline enthält bereits bekannte Issues (u.a. fehlende `SpotifyProfileNotFoundException`
  in `DisconnectSpotify.php`); neue Issues vermeiden, möglichst Baseline verkleinern.
- Frontend-`dist/`: `pnpm` ist auf dem Pi **nicht** installiert → Frontend-Build muss in WSL erfolgen
  und `dist/` separat bereitgestellt werden (Sprint-01-Blind-Spot). Betrifft jede #9-Frontend-Änderung.
- Compose v2 auf dem Pi (`docker compose`, nicht `docker-compose`) — L-002.
- Release: Tag `v0.2.0` triggert systemd-Pull-Deploy (D-008). Tag erst nach grüner CI + Merge.

### Lens 4 – Security & Compliance
- API ist komplett `PUBLIC_ACCESS` (`security.yaml:20-21`). Für Pi-LAN akzeptiert, **nicht** prod-gehärtet.
  Explizit als Rest-Risiko führen, nicht in diesem Sprint lösen.
- `READER_API_KEY` muss in Root-`.env` (Pi) **und** `firmware/.../secrets.h` **identisch** sein; `hash_equals`-Prüfung.
- Secrets nie committen: `backend/.env.local`, Root-`.env`, `firmware/.../secrets.h` (git-ignoriert).
- Spotify-Tokens verschlüsselt at-rest (Sodium). Scopes ausreichend für Playback + Discovery (verifiziert).
- `composer audit` + Trivy laufen via CI; keine neuen Deps → Angriffsfläche unverändert.
- Spotify "Development Mode": User-E-Mail muss im Dashboard unter "User Management" stehen, sonst kein Consent.

---

## Cross-Module Antworten (5 Fragen)
1. **Upstream:** Wer speist die Bereiche? ESP32 → `POST /readers/scan`; Spotify-OAuth → Token; Wizard/Frontend → Default-Device.
   Der Firmware-Outcome-Fix ändert **nur** den Consumer (ESP32), nicht das Backend → kein Upstream-Bruch.
2. **Downstream:** Scan-Response wird konsumiert von Firmware (`signalResult`) **und** `ScanLogsPage.tsx` (lowercase, konsistent).
   `default_spotify_device_id` wird konsumiert von `StartPlayback` + Frontend-Status. Fix von `default_device_name`
   verbessert Frontend-Anzeige; kein Consumer bricht. Neuer #9-Endpunkt: Frontend muss ihn aufrufen (Consumer-Update).
3. **Audit:** Zustands-/Config-Änderungen, die Audit brauchen: OAuth-Connect, Token-Refresh, Device-Default-Set.
   ActivityLog-Konstanten existieren teils, werden aber nicht geschrieben → in #8/#9 ergänzen.
4. **API-Vertrag:** Scan-Vertrag bleibt **unverändert** (Fix nur firmware-seitig). Falls #9 neuen Endpunkt
   (`PUT /profiles/{id}/default-device`) bekommt → `openapi.yaml` + oasdiff-Gate bedienen. `default_device_name`-Fix
   ändert Wert (null→string), nicht die Shape → unkritisch.
5. **Feature-Flags:** Nicht erforderlich (Single-Tenant-Heimprojekt, kein Staged-Rollout).

---

## Getroffene Entscheidungen (bestätigt 2026-06-01 → siehe tasks/decisions.md)

- **D-009 = B:** Dedizierter Endpunkt `PUT /api/v1/profiles/{id}/default-device` + UI (ProfileDetailPage/DevicesPage).
  `default_device_name` wird serverseitig aufgelöst.
- **D-010 = A:** Firmware auf lowercase (`success`/`debounced`) anpassen; Backend bleibt Quelle der Wahrheit.
- **Scope = vollständig:** Zusätzlich zu E2E + Showstopper-Fix kommen Tests, OpenAPI-Schemas,
  Stale-ID-Handling (R2) und die #8-Härtung mit in diesen Sprint.
- **#8-Härtung = in Sprint 2:** Activity-Log bei Connect/Refresh, Display-Name-Bug, fehlende
  `SpotifyProfileNotFoundException`.

---

## Akzeptanzkriterien (testbar)
1. **#8:** `GET /api/v1/profiles/{id}/spotify/status` → `connected`; DB-Zeile `spotify_account_link`
   mit verschlüsselten Tokens + `expires_at` in der Zukunft; `POST .../spotify/validate` → `valid:true`.
2. **#8:** `GET /api/v1/profiles/{id}/spotify/devices` liefert ≥1 Gerät (bestätigt Token + Scopes live).
3. **#9:** Default-Device ist pro Profil setzbar (gem. D-009) und `GET /api/v1/profiles/{id}` liefert
   `default_spotify_device_id` **und** korrekten `default_device_name` (nicht mehr hardcoded null).
4. **#9:** `POST .../playback/start` **ohne** `device_id` spielt auf der Wobie Box (nutzt Default).
5. **#10:** Firmware signalisiert bei `outcome:"success"` Erfolg (1 langer Blink), bei `debounced` 2 kurze.
6. **#10 (E2E):** Realer Scan einer gebundenen Karte → Wobie Box spielt die Playlist hörbar; Serial-Log
   zeigt `-> 200: {"outcome":"success",...}`.
7. **Qualität:** Neue automatisierte Tests grün (Default-Device-Logik + Scan-Outcome); CI 5/5 grün.

---

## Dry-Run (geplante E2E-Sequenz)
1. **Vorbereitung Pi (Ops):** `backend/.env.local` (CLIENT_ID/SECRET, READER_API_KEY) + Root-`.env`
   (`SPOTIFY_REDIRECT_URI=http://127.0.0.1:8080/api/v1/spotify/callback`, `FRONTEND_URL=http://127.0.0.1:8080`)
   gesetzt; `APP_SECRET` stabil. Spotify-Dashboard: Redirect-URI exakt + User-E-Mail in "User Management".
2. **#8 OAuth (User, blockierend):** `ssh -L 127.0.0.1:8080:localhost:8080 lars@192.168.1.91` →
   `http://127.0.0.1:8080` → Profil → "Spotify verbinden" → Consent. Verifikation via Status/Validate (AK 1+2).
3. **#9 Discovery + Default:** Wobie Box einschalten (Spotify Connect aktiv) → `POST /api/v1/devices/discover`
   (oder Wizard) → Default-Device setzen (gem. D-009) → AK 3+4 prüfen (Playback ohne device_id).
4. **#10 Dry-Run vor Flash:** `curl -X POST .../readers/scan -H "X-API-Key: <KEY>" -d '{"reader_id":"...","card_uid":"<UID>"}'`
   → erwartet `{"outcome":"success",...}` (Backend-Kette grün, unabhängig von LED).
5. **#10 Flash (User, blockierend):** ESP32 an `/dev/ttyUSB0` → `secrets.h` (BACKEND_BASE_URL=192.168.1.91:8080,
   READER_API_KEY) → `arduino-cli` flashen → realer Scan → AK 5+6.
6. **Abschluss:** CHANGELOG + `docs/sprints/sprint-02.md` + Sprint-03-Starter; PR → Squash-Merge → Tag `v0.2.0` → Auto-Deploy.

---

## Serielle Abhängigkeiten
- **#8 → #9 → #10-E2E:** Ohne gültiges Token scheitert Discovery (`SpotifyNotConnectedException`) und
  Scan (`token_invalid`). Ohne Default-Device scheitert Scan-Playback (`no_device`).
- **Parallel autonom machbar (vor/ohne Token):** Firmware-Outcome-Fix (#10), `default_device_name`-Fix (#9),
  Tests, OpenAPI/Docs, optionale #8-Härtung. Diese können sofort nach Bestätigung gebaut werden.

---

## Definition of Done
- [ ] D-009 + D-010 entschieden und in `tasks/decisions.md` eingetragen.
- [ ] Firmware-Outcome-Fix umgesetzt; `secrets.h.example`-IP korrigiert.
- [ ] #9-Lösung (gem. D-009) implementiert; `default_device_name` aufgelöst.
- [ ] Automatisierte Tests für Default-Device-Auflösung + Scan-Outcome-Mapping, grün.
- [ ] Bestehende Tests + PHPStan grün; keine neuen Baseline-Einträge.
- [ ] `openapi.yaml` konsistent (oasdiff-Gate grün), falls Endpunkt geändert.
- [ ] Docs: `docs/SPOTIFY_INTEGRATION.md` (korrekter Default-Set-Pfad), `docs/pi-deployment.md` (E2E-Runbook),
      `CHANGELOG.md`, `docs/sprints/sprint-02.md`.
- [ ] **User-verifiziert (Hardware/Ops):** OAuth-Token gespeichert (#8), realer Scan spielt Wobie (#10 E2E).
- [ ] PR gemerged, CI 5/5 grün, Tag `v0.2.0` gesetzt, Auto-Deploy erfolgreich.
- [ ] Sprint-03-Starter (`docs/sprints/sprint-03-starter.md`) + Working-Memory aktualisiert.

---

## Risiken / Offene Fragen / Blind Spots
- **R1 (Hoch, #8):** Credentials Env-vs-DB. OAuth liest **nur** Env (`%spotify.client_id%`), ignoriert DB-Tabelle
  `spotify_app_configuration`. Wenn auf dem Pi nur via System-UI konfiguriert wurde → OAuth schlägt fehl.
  Mitigation: `backend/.env.local` auf dem Pi prüfen, BEVOR der Consent-Lauf startet.
- **R2 (Hoch, #9):** **Stale Spotify device_id.** Die gespeicherte ID ist session/ephemeral; nach Wobie-Reboot
  kann sie ungültig werden → `no_device`, ohne dass das Backend re-resolved. Optionaler Re-Resolve per Gerätename
  ("Wobie") ist eine Design-Entscheidung — für diesen Sprint mind. als Risiko dokumentieren.
- **R3 (Mittel, #9):** Discovery erfordert, dass die Wobie Box zum Zeitpunkt in Spotify **sichtbar** ist
  (kein IP-Scan; `192.168.1.10` ist im Code irrelevant). Offline → leere Liste → Default nicht setzbar.
- **R4 (Mittel, #9):** Zwei Gerätemodelle (`spotify_device`/Governance vs. `default_spotify_device_id`/Playback)
  sind entkoppelt. D-009 muss klären, ob/wie sie sich beeinflussen.
- **R5 (Mittel, #10):** Premium/Scope-Fehler werden generisch als `playback_failed` gemeldet (in `ProcessScan`
  per `catch \Throwable`) → am ESP nicht differenzierbar. Optional eigenes Outcome; sonst als bekannte Einschränkung führen.
- **R6 (Niedrig):** `default_device_name` hardcoded `null` (`FamilyProfileController.php:92`) → Frontend-Status
  falsch-negativ ("Nicht zugeordnet"). Fix gehört zu #9.
- **R7 (Niedrig):** `spotify_user_display_name` zeigt User-ID statt Display-Name (`FamilyProfileController.php:94`).
  Kosmetischer #8-Fix.
- **Blind Spots (Sprint-übergreifend, aus sprint-01):** DHCP-IP `192.168.1.91` **nicht reserviert** → IP-Wechsel
  bricht ESP32 (`secrets.h`) + Tunnel; kein Healthcheck-Alerting; Rollback manuell; `pnpm` fehlt auf dem Pi
  (Frontend-`dist/` separat bauen); `APP_ENV=dev` auf dem Pi (Profiler aktiv).
- **Offene Fragen 1–3 (D-009, #8-Härtung, Scope):** entschieden (B / in / vollständig) — siehe oben.

---

## Verifikations-Log
- 2026-06-01 | Explore-Schwarm A/B/C | Code-Bestandsaufnahme #8/#9/#10 | Ergebnis: Backend-Pfade vorhanden;
  kritischer Firmware-Outcome-Case-Bug identifiziert; #9 Default-Set nur via Wizard; Env-vs-DB-Risiko. | Quelle: Subagent-Berichte.
- 2026-06-01 | Backend | PHPUnit + PHPStan im PHP-8.5.6-Container | `OK (17 tests, 45 assertions)`, PHPStan Level 8 `No errors`.
- 2026-06-01 | Frontend | `pnpm build` (tsc -b + vite) + `vitest run` | Build grün, keine TS-Fehler, keine Test-Failures.
- 2026-06-01 | API-Vertrag | `nelmio:apidoc:dump` regeneriert | `openapi.yaml`-Diff rein additiv (default-device-Pfad), oasdiff-breaking unkritisch.
- _Offen (User/Hardware):_ OAuth-Consent (#8) + realer E2E-Scan auf Wobie (#10).

## Abgeschlossen
_(wird bei Sprint-Abschluss ausgefüllt)_
