# Plan: Sprint 3 – Multi-Raum & Reader-Lifecycle

**Erstellt:** 2026-06-02
**Status:** Plan – wartet auf User-Bestätigung, NICHT vollständig umgesetzt
**Milestone:** #4 „Sprint 3 – Multi-Raum & Reader-Lifecycle"
**Sprint-Branch:** `feat/sprint-03-reader-lifecycle` (ein Sprint = ein Branch, WP1..WPn als Commits)
**WorkPackages:** #33 Reader→Box abschließen · #34 Pi-Leser (PN532) · #35 Pro-Reader-Keys · #36 Terminologie „Wobie→Connect-Gerät"
**Verbindet:** `plan-reader-box-mapping.md` · `plan-pi-reader-daemon.md` · `plan-esp-ota-perreader-keys.md`

## Begriffs-Klarstellung (User, 2026-06-02)
„Wobie Box" ist **kein** spezifisches Gerät, sondern ein beliebiges **Spotify-Connect-fähiges
Wiedergabegerät** (austauschbares Connect-Target). Deckt sich mit D-015/D-016 (alle Zielboxen
Connect-fähig, Bluetooth verworfen). Konsequenz: WP #36 generisiert die Doku-/Code-Sprache.

## Sprint-Ziel & Scope
1. **Reader→Box-Mapping (D-015) produktiv** (#33): Sprint-Branch mergen, Pi-Migration, echtes
   Multi-Raum-E2E (Leser A→Box X, Leser B→Box Y; ohne Mapping = Profil-Default).
2. **Pi-Leser (PN532/HW-147) nutzbar** (#34): Python-Daemon (Adafruit CircuitPython PN532, I2C)
   → `/api/v1/readers/scan`; Scan-to-Enroll-UX; UID-Gleichheit zum MFRC522 verifiziert.
3. **Härtung Stufe 1: Pro-Reader-Keys** (#35): `ScanController::validateReaderAuth` auf
   `ReaderDevice::validateApiKey` umstellen (Fallback-Phase D-K1 B).
4. **Terminologie-Bereinigung** (#36): „Wobie" → generisches Connect-Gerät.

### Bewusst NICHT in Sprint 3 (Scope-Schnitt, Bestätigung nötig)
- **USB-Provisioning (Phase A)** und **signiertes OTA (Phase B)** aus `plan-esp-ota-perreader-keys.md`
  Teil 2/3. Begründung: OTA ist laut eigenem Plan „eigener Sprint, höchstes Risiko"; Provisioning hängt
  an Pro-Reader-Keys + Hardware. → Vorschlag: **Sprint 4**. Damit bleibt Sprint 3 „done"-fähig.

## Aktueller verifizierter Stand
- **CI von #32 GRÜN** (Backend 8.4/8.5, Frontend, oasdiff, Trivy, Web-Image). Fix-Commit `a52a63e`:
  PHPStan-L8 `missingType.iterableValue` in `ReaderDeviceController::readerToArray()` durch
  `array{...}`-Shape behoben. **Lokal verifiziert** im `php:8.5.6`-Container: PHPStan grün,
  PHPUnit 32/93 OK, `composer audit` clean. PR #32 = `MERGEABLE / CLEAN`.
- Backend/Frontend/DB laufen auf dem Pi (`192.168.1.91`), Auto-Deploy via `v*`-Tag, `main` geschützt.
- Sprint 2 offen (Hardware/User): #8 OAuth-Consent je Account, #10 ESP32 flashen + realer Scan.

## 4-Lens-Analyse (Pflicht)

### Lens 1 – Runtime & Sprache
- Backend unverändert: PHP 8.5 / Symfony 7.4, Doctrine-Migration. CI verifiziert zusätzlich PHP 8.4.
- **Neu (Pi-Host, NICHT im Docker-Stack):** Python 3 Daemon auf Debian 13/aarch64 (systemd).
  Berührt die Backend-Runtime nicht; eigener Update-/Restart-Pfad (Risiko: aus Auto-Deploy ausgeklammert).

### Lens 2 – Frameworks & Abhängigkeiten
- Backend: **keine** neuen Composer-Deps (Reader→Box + Pro-Reader-Keys nutzen Vorhandenes:
  `password_*`, Device-Discovery). `composer.lock` unverändert → kein Audit-Impact.
- Pi-Daemon: kleine Python-Deps **nur auf dem Pi** in venv: `adafruit-circuitpython-pn532`,
  `adafruit-blinka`, `requests`. Kein Einfluss auf Backend-Lockfiles/CI.

### Lens 3 – Build, CI/CD & Tooling
- #33: additive Migration ist bereits im Branch; Pi-Migration via Tag-Deploy. oasdiff bereits grün
  (Reader-Response-Shape/Endpunkte im Branch dokumentiert).
- #35: ggf. Response-/OpenAPI-Anpassung bei Registrierungs-Anzeige → oasdiff-Gate beachten.
- #34: Daemon liegt im Repo, wird per Pull auf den Pi gezogen, aber **nicht** im Compose-Stack
  (systemd-Host-Dienst). Kein CI-Build nötig; ggf. ruff/py-lint später.
- Sprint endet mit Tag **`v0.3.0`** (Minor) → triggert Pi-Deploy.

### Lens 4 – Security & Compliance
- #35 ist der sicherheitsrelevante Kern: least-privilege pro Reader; **Fallback-Phase (D-K1 B)**
  zwingend, sonst sperrt die Umstellung den bestehenden ESP (sendet nur globalen Key) aus.
- Klartext-Key nur einmalig bei Registrierung sichtbar, **nie geloggt**. ESP-`secrets.h`/Pi-Daemon-Key
  git-ignoriert (wie bisher).
- Keine neuen Klartext-Tokens; Device-IDs nicht sensibel.

## Cross-Module-5-Fragen
1. **Upstream:** Producer von `/scan` sind ESP (#10) und neu der Pi-Daemon (#34) – beide nutzen den
   **bestehenden** `/scan`-Vertrag additiv. Pro-Reader-Key (#35) ändert die **Auth** des Producers →
   breaking für bestehende ESPs ohne Fallback (D-K1 B mitigiert).
2. **Downstream:** Spotify Web API konsumiert Device-IDs. Reader-Box-Override muss denselben
   Stale-ID-Re-Resolve-per-Name nutzen wie Profil-Default (sonst Playback-Bruch nach Box-Reconnect) –
   bereits im Branch adressiert, im E2E (#33 AK5) zu verifizieren.
3. **Audit:** Reader-Default (Config-State) → D-015 dokumentiert. Pro-Reader-Key-Rotation/-Sperre →
   D-K1 + Audit-Log. Scans landen ohnehin in `scan_event`.
4. **API-Vertrag:** Reader-Response-Shape + `GET/PUT/DELETE /readers...` (im Branch, oasdiff grün).
   #35 ggf. additive Felder → oasdiff prüfen. #34 keine Shape-Änderung.
5. **Feature-Flags:** nicht nötig (rein additiv, rückwärtskompatibel). Optional OTA später hinter Flag.

## Entscheidungen (User 2026-06-02, eingefroren)
- **D-S3 = A (strikt ein PR):** alle WPs auf #32 sammeln, **einmal** am Sprint-Ende mergen + `v0.3.0`.
  → **Kein Merge von #32 vor Sprint-Ende.**
- **Scope = defer:** USB-Provisioning + signiertes OTA → **Sprint 4** (nicht in Sprint 3).
- **Wobie (#36) = Historie behalten:** nur produktive Doku/Code-Kommentare generisieren; historische
  Titel/Sprint-Doku/Decisions (inkl. Milestone #3) bleiben als Audit-Spur unverändert.

## Offene Entscheidungen (Detail-Restpunkte, beim Umsetzen final)

### D-S3 – Merge-Strategie: ENTSCHIEDEN = A (siehe oben)
**Widerspruch:** Die harte Regel lautet „EIN Sprint = EIN Branch (WP1..WPn) → ein PR → gemerged".
Zugleich verlangt Sprint-Ziel 1 „CI grün → **mergen** → Pi-Migration → E2E", was einen **Zwischen-Merge**
von #32 (nur Reader→Box) **vor** Sprint-Ende impliziert.
- **A) Strikt ein PR:** Reader→Box, Pi-Leser, Pro-Reader-Keys als Commits auf #32 sammeln, **einmal**
  am Sprint-Ende mergen + `v0.3.0`. Konsequenz: Multi-Raum-E2E erst nach Sprint-Ende auf dem Pi
  testbar (oder lokal/manuell), kein Zwischen-Release. Konfliktfrei mit der Regel.
- **B) Zwischen-Merge:** #32 jetzt mergen + Zwischen-Tag (z.B. `v0.3.0-rc`/`v0.2.4`) für Pi-E2E,
  Rest (Pi-Leser, Keys) in **neuem** Branch/PR. Verstößt gegen „ein Sprint = ein PR" und „keine
  parallelen Einzel-PRs", liefert aber früh testbaren Stand auf dem Pi.
- **Empfehlung: A** (Regelkonform). Multi-Raum-E2E hängt ohnehin an Hardware (mehrere Accounts/Boxen),
  ist also nicht sofort möglich → kein realer Zeitverlust durch späteren Merge.
→ **Bitte entscheiden.** Ohne Entscheidung: kein Merge von #32.

### D-R1 – Herkunft der `reader_device`-Zeilen (aus plan-reader-box-mapping)
Empfehlung **A) Auto-Upsert beim ersten Scan** (niedrigste Friktion); final mit Provisioning (Sprint 4) abstimmen.

### D-K1 – Auth-Migration Single→Pro-Reader-Key
Empfehlung **B) Fallback-Phase** (globaler Key temporär gültig), sonst ESP-Lockout.

### D-P1 / D-P2 – PN532-Interface (I2C empfohlen) / Daemon-Laufzeit (Host-systemd) – final beim Verkabeln.

## Geplante Reihenfolge (Subagenten-Schwarm, nach Bestätigung)
- **Parallel (unabhängig):**
  - WP #35 Pro-Reader-Keys (kleinster, kein Hardware-Bedarf für Code) – `explore`+Umsetzung.
  - WP #34 Pi-Leser-Daemon: Code/systemd/UID-Normalisierung schreiben (Hardware-Verifikation blockiert).
  - WP #36 Terminologie-Bereinigung (mechanisch, Review).
- **Seriell / blockiert:**
  - WP #33 E2E + Pi-Migration: hängt an Merge-Entscheidung (D-S3) + Hardware (Accounts/Boxen).
  - Provisioning/OTA: Folge-Sprint (hängt an #35).

## Dry-Run / Risiken
- **L-015 (bekannt):** Schema+lesender Code im selben Release → kurzes transientes 500-Fenster beim
  Pi-Auto-Deploy von #33/`v0.3.0`. Erwartbar, nach `migrate` weg; kein Rollback-Grund. Verifikation
  **nach** Deploy-Ende.
- **L-014 (bekannt):** Deploy-Skript-Änderungen greifen erst ab dem nächsten Release; falls `pi-deploy.sh`
  in diesem Sprint angefasst wird, im einführenden Release einmal manuell nachziehen.
- **ESP-Lockout (#35):** ohne D-K1-Fallback sperrt die Key-Umstellung den bestehenden ESP aus.
- **UID-Mismatch (#34):** PN532 vs. MFRC522 4-/7-Byte + Reihenfolge → Pflicht-Verifikation mit bekannter Karte.
- **Spotify-Grenze:** ein Account spielt nur auf einem Gerät gleichzeitig → echtes paralleles Multi-Raum
  nur über verschiedene Profile/Accounts.

## Definition of Done (Sprint)
- [ ] WP #33–#36 (Sprint-3-Scope) closed; Akzeptanzkriterien verifiziert + dokumentiert
- [ ] CI grün auf `main`; oasdiff 0-Bruch
- [ ] Doku gepflegt: `CHANGELOG.md`, `docs/sprints/sprint-03.md`, betroffene Decisions (D-S3, D-K1, D-R1, D-P1/2)
- [ ] Working-Memory aktualisiert (`todo.md`, `decisions.md`, `lessons.md`)
- [ ] Tag `v0.3.0` gesetzt → Pi-Deploy erfolgreich verifiziert
- [ ] Starter-Prompt für Folge-Sprint (`docs/sprints/sprint-04-starter.md`) erzeugt

## Verifikations-Log
- 2026-06-02: CI-Fix #32 (PHPStan-L8) lokal im `php:8.5.6`-Container verifiziert (PHPStan grün,
  PHPUnit 32/93, composer audit clean), gepusht (`a52a63e`), Remote-CI grün, PR #32 CLEAN.

## Abgeschlossen
{Datum + Summary wenn fertig}
