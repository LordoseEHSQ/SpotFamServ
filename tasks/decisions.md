## Decision Log

Format je Eintrag: Kontext, Optionen, Entscheidung, Begründung, Status.

---

### D-001 | 2026-06-01 | Governance

**Kontext:** Abbildung von Sprints in GitHub.
**Optionen:** A) nur Milestones · B) Milestones + Projects-v2-Board (Iterations).
**Entscheidung:** B – Milestones + Projects-v2-Board (#2).
**Begründung:** User-Wunsch nach Übersicht/Board. Hinweis: echte Iteration-Felder sind via `gh`-CLI nicht anlegbar → ggf. manuell im UI; Sprint-Zuordnung primär über Milestone.
**Status:** Accepted

---

### D-002 | 2026-06-01 | Governance

**Kontext:** Bedeutung von „relevante Modelle" je WorkPackage.
**Entscheidung:** AI/LLM-Modelle – je WorkPackage Empfehlung + Begründung (Reasoning-Tiefe vs. Routine vs. Kosten).
**Status:** Accepted

---

### D-003 | 2026-06-01 | Governance

**Kontext:** Zweites Gehirn / Wissens-Layer.
**Entscheidung:** Obsidian als reiner Viewer über die Repo-Markdown-Dateien (Vault = Repo/`docs/`), Free-Version, Sync via git. `docs/PROJECT_MAP.md` = Home/Token-sparsamer Einstieg.
**Begründung:** GitHub bleibt SSoT; kein zweiter Speicher → kein Drift. Free reicht (kein Obsidian-Sync nötig).
**Status:** Accepted

---

### D-004 | 2026-06-01 | Versionierung

**Kontext:** Start-Version.
**Entscheidung:** `v0.1.0` (pre-1.0 MVP).
**Status:** Accepted

---

### D-005 | 2026-06-01 | Deploy

**Kontext:** Wann auf den Pi deployen.
**Entscheidung:** Nur getaggte Releases `vX.Y.Z` (nicht jeder main-Merge).
**Begründung:** Kontrollierte Versionen, natürliche Backup-Punkte.
**Status:** Accepted

---

### D-006 | 2026-06-01 | Quality Gates

**Kontext:** Schutz von `main`.
**Entscheidung:** Branch Protection: PR-Pflicht, required CI-Checks inkl. API-Drift, kein direkter Push.
**Status:** Accepted

---

### D-007 | 2026-06-01 | Backup

**Kontext:** Datensicherheit bei Deploys/Migrationen.
**Entscheidung:** Postgres-Volume bleibt + automatischer `pg_dump` VOR jeder Migration, letzte N rotierend.
**Status:** Accepted

---

### D-008 | 2026-06-01 | Deploy-Mechanismus

**Kontext:** Wie das tag-getriggerte Deploy technisch läuft (Pi hinter Heim-NAT).
**Optionen:** A) systemd-Timer Pull · B) GitHub Actions + Tailscale-SSH · C) self-hosted Runner.
**Entscheidung:** A – systemd-Timer Pull auf dem Pi (alle 2 Min, neuester `v*`-Tag).
**Begründung:** Kein Inbound nötig (Heim-NAT), kein GitHub-Secret, simpel und robust.
**Status:** Accepted

---

### D-009 | 2026-06-01 | Device/Playback (Sprint 2, #9)

**Kontext:** Das Default-Spotify-Device pro Profil (`family_profile.default_spotify_device_id`) ist
bislang **ausschließlich** über den Setup-Wizard-Step `default_speaker` setzbar. `AssignDevice`
(Governance-Inventar `spotify_device`) synchronisiert es **nicht**; `default_device_name` ist im
Profile-Controller hardcoded `null`. Es braucht einen klaren Weg, das Default außerhalb des Wizards zu setzen.
**Optionen:**
1. A) Wizard-Pfad belassen, nur Anzeige-Bug fixen — Vorteile: minimal · Nachteile: UX bleibt umständlich, kein API-Weg.
2. B) Dedizierter Endpunkt `PUT /api/v1/profiles/{id}/default-device` + UI — Vorteile: sauber entkoppelt, testbar, openapi-dokumentiert · Nachteile: mehr Code (UseCase, DTO, Frontend, oasdiff).
3. C) `AssignDevice` setzt `default_spotify_device_id` mit — Vorteile: ein Klick · Nachteile: koppelt Governance an Playback-Ziel (semantisch fragwürdig).
**Entscheidung:** B – dedizierter Endpunkt `PUT /profiles/{id}/default-device` + UI (ProfileDetailPage/DevicesPage).
**Begründung:** Trennt Inventar/Governance (`spotify_device`) sauber vom Playback-Ziel (`default_spotify_device_id`),
ist als API testbar und über OpenAPI/oasdiff vertraglich abgesichert.
**Konsequenzen:** Neuer UseCase `SetDefaultDevice` + DTO + Route + Frontend-Call + openapi.yaml-Eintrag.
`default_device_name` wird serverseitig aufgelöst (kein hardcoded null).
**Status:** Accepted

---

### D-010 | 2026-06-01 | Firmware/Scan-Vertrag (Sprint 2, #10)

**Kontext:** Die ESP32-Firmware prüft `outcome=="SUCCESS"`/`"DEBOUNCED"` (uppercase), das Backend sendet
kanonisch lowercase (`ScanOutcome.php`, Frontend `ScanLogsPage.tsx` konsistent). Folge: erfolgreiches
Playback und Debounce werden am ESP32 als Fehler (4 Blinks) signalisiert.
**Optionen:**
1. A) Firmware auf lowercase anpassen — Vorteile: kein Vertragsbruch, Backend bleibt SSoT · Nachteile: Re-Flash nötig (ohnehin Teil von #10).
2. B) Backend auf uppercase — Nachteile: bricht Frontend + OpenAPI-Vertrag, mehr Consumer betroffen.
**Entscheidung:** A – Firmware auf lowercase (`success`/`debounced`) anpassen.
**Begründung:** Backend-lowercase ist die Quelle der Wahrheit (Enum + Frontend + OpenAPI). Fix gehört auf den Consumer.
**Status:** Accepted

---

### D-011 | 2026-06-01 | Spotify-App-Config: DB als Source of Truth über die Oberfläche

**Kontext:** Die System-Einstellungen speichern Client-ID/Secret/Redirect in der DB
(`SpotifyAppConfiguration`), aber der Laufzeit-Flow (`SpotifyHttpApiClient`, `GetSpotifyAuthorizationUrl`,
`SpotifyOAuthController`) las ausschließlich die env-Parameter `%spotify.*%`. Folge: UI-Eingaben waren
gespeichert, aber für OAuth/Token/Playback **wirkungslos** (Defekt, nicht Bedienfehler).
**Optionen (Präzedenz):**
1. DB nur wenn vollständig (`isComplete()`), sonst ganzheitlich env – kein Vermischen.
2. Pro-Feld-Fallback (DB-Feld vor env-Feld) – Risiko: neue Client-ID mit altem Secret kombiniert.
**Entscheidung:**
- Präzedenz **Option 1** (DB-Config gewinnt nur als vollständige Einheit, sonst env-Fallback).
- Neuer `SpotifyCredentialsProvider` (Port + Infra) liefert effektive Credentials **pro Request** (kein Prozess-Cache → UI-Save greift ohne Neustart).
- **Scopes bleiben code-seitig** (kanonische Liste), UI-Feld `scope_defaults` nicht für OAuth verwendet (falsche Scopes brächen Playback still).
- **„Validieren" prüft real** gegen Spotify (client_credentials-Grant) statt nur Presence.
- **Redirect-URI in der UI editierbar** (Loopback-Default), env bleibt Fallback.
**Begründung:** Macht die Settings-Seite zur echten Konfigurationsquelle statt Fassade; env bleibt für
Bootstrap/Dev erhalten; risikoarm rückrollbar (env-Pfad intakt).
**Konsequenzen:** Konstruktor-Signatur `SpotifyHttpApiClient` geändert (nur via DI genutzt);
`ValidateSpotifyAppConfig` + `SpotifyApiClientInterface::checkClientCredentials()` erweitert; kein Schema.
**Status:** Accepted

---

### D-012 | 2026-06-02 | Frontend-Deploy auf den Pi (Bug #20)

**Kontext:** `pi-deploy.sh` baut das Frontend auf dem Pi (Schritt 5), aber der Pi hat **kein Node/pnpm** →
Build wird still übersprungen, `frontend/dist` blieb seit v0.1.0 unverändert (Sprint-2-UI fehlte auf dem Pi).
**Optionen:** A) CI baut nginx-Image mit fertigem `dist` (multi-arch arm64) → Pi zieht Image ·
B) CI baut `dist` als Release-Artefakt → Skript lädt/entpackt · C) Node+pnpm auf dem Pi installieren.
**Entscheidung:** **A – CI-gebautes Image.** Kein Build-Toolchain auf dem Runtime-Gerät; Pi macht nur
`docker compose pull && up -d`. Versionslabel dynamisch aus `package.json` (bereits umgesetzt, PR #21).
**Begründung:** Sauberste Trennung Build (CI) vs. Laufzeit (Pi); reproduzierbar, arm64-tauglich; behebt
zugleich den Host-Bind-Mount-Hack für `dist`.
**Konsequenzen (offen, eigener PR):** Frontend-`Dockerfile` (nginx + dist), CI-Job mit buildx→GHCR,
`docker-compose.yml` nginx auf Image statt Bind-Mount, `pi-deploy.sh` auf `pull` umstellen,
Registry-Auth auf dem Pi. **Sofort-Stopgap (erledigt):** dist manuell gebaut + auf Pi kopiert.
**Status:** Accepted (umgesetzt in `feat/frontend-ci-image`, Detail-Entscheidungen → D-013)

---

### D-013 | 2026-06-02 | Frontend-CI-Image: Detail-Entscheidungen (Bug #20, Umsetzung von D-012)

**Kontext:** D-012 legt „CI-gebautes Image" als Prinzip fest. Für die Umsetzung waren vier Punkte offen.
**Optionen & Entscheidungen:**
1. **GHCR-Sichtbarkeit:** public vs. private. → **public.** Das SPA-Bundle ist Browser-öffentlich und
   enthält keine Secrets (relative `/api/v1`, keine Tokens gebacken); private bringt ≈0 Confidentiality,
   kostet aber PAT-Login + Token-Rotation auf dem Pi (L-009). Einmalig Package-Visibility=public setzen.
2. **Tag-Schema:** → **`vX.Y.Z` (immutable) + `latest` + `sha-<short>`.** Reproduzierbarer Deploy über
   den festen `vX.Y.Z`-Tag; `latest`/`sha` für Komfort/Debug.
3. **Image-Referenz in compose:** fester Tag vs. `latest`. → **`${WEB_IMAGE_TAG:-latest}`.** `pi-deploy.sh`
   injiziert den exakten deployten `v*`-Tag → laufendes Web-Image ist an den git-Tag gekoppelt (starke
   Konsistenz); manuelle Läufe fallen auf `latest` zurück.
4. **`default.conf`-Handling:** backen + Mount entfernen vs. backen + Mount behalten. → **backen + Bind-Mount
   behalten** (nur `frontend/dist`-Mount entfernen). Deckt sich mit der verifizierten Topologie, ist
   risikoärmer, hält die nginx-Config git-getrieben (folgt dem Tag) und lokal ohne Image-Rebuild editierbar;
   das Image bleibt trotzdem self-contained (Config gebacken, zur Laufzeit vom Mount überschattet).
**Begründung:** Maximiert Reproduzierbarkeit + Konsistenz bei minimalem Betriebs-/Secret-Aufwand und
kleinstem Diff gegenüber dem bestehenden Stack.
**Konsequenzen:** `release-web-image.yml`, `docker/frontend/Dockerfile`, Root-`.dockerignore`,
`docker-compose.yml` (nginx→Image, dist-Mount raus), `deploy/pi-deploy.sh` (pull+Retry, `WEB_IMAGE_TAG`),
`frontend/package.json`-Bump. **Rollback:** echter Rollback = neuer höherer Tag vom älteren Commit
(`pi-deploy.sh` zieht stets den neuesten `v*`); ad-hoc `WEB_IMAGE_TAG=v0.2.1 docker compose up -d nginx`.
**Status:** Accepted

---

### D-014 | 2026-06-02 | Spotify-Status: Refresh-getrieben statt Access-Token-Takt (#25)

**Kontext:** Die UI zeigte nach Ablauf des 1h-Access-Tokens fälschlich „abgelaufen", obwohl der
`SpotifyTokenManager` den Token automatisch per Refresh erneuert. Der Access-Token-Zeitstempel ist
kein sinnvoller Status-Indikator; relevant ist allein, ob eine **echte Neu-Autorisierung** nötig ist.
**Optionen:**
- A) Ohne Persistenz: „Refresh-Token vorhanden" ⇒ `connected`; Re-Auth-Bedarf wird erst sichtbar, wenn
  eine echte Aktion scheitert. Vorteil: kein Schema. Nachteil: ein dauerhaft kaputter Refresh-Token
  (revoked / `invalid_grant` / APP_SECRET-Wechsel) bleibt unsichtbar, bis der User zufällig etwas auslöst.
- B) **Persistiertes `needs_reauth`-Flag** auf `spotify_account_link`: gesetzt bei dauerhaftem
  Refresh-Fehler (`SpotifyTokenInvalidException`/`invalid_grant`), gelöscht bei erfolgreichem Refresh
  und bei Re-Consent. Status = `not_connected` (kein Link) / `reauth_required` (Flag) / `connected`.
**Entscheidung:** **B.** Akkurat und proaktiv: surfacet echten Re-Auth-Bedarf, ohne den User vom
Access-Token-Takt zu behelligen. Transiente Fehler (Netz/5xx → `SpotifyApiException`) setzen das Flag
**nicht** (kein false-positive). Kosten: additive Mini-Migration + Wiring.
**Konsequenzen:** Domain-Feld + Methoden, Migration `Version20260602120000_spotify_needs_reauth`
(separater Commit), `SpotifyTokenManager.refreshAndPersist` (set/clear + ActivityLog
`spotify_reauth_required`), `ExchangeSpotifyCode` (clear), `GetSpotifyStatus::resolve()` als **einzige**
Status-Quelle (Duplikat in `FamilyProfileController` entfernt), Frontend-Enum `expired`→`reauth_required`
(alle Consumer + Labels), Release v0.2.3. **Rollback:** Migration-`down()` droppt die Spalte additiv;
Status fällt ohne Flag auf das alte Verhalten zurück. **Status:** Accepted

---

### D-015 | 2026-06-02 | Reader/Playback (Multi-Raum)

**Kontext:** Bisher bestimmt allein das **Profil der gescannten Karte** den Lautsprecher
(`family_profile.default_spotify_device_id`); der Reader-Standort ist irrelevant. Für Multi-Raum
("Karte spielt dort, wo ich sie scanne") muss der **Reader** die Box bestimmen können.
**Optionen:**
1. A) So lassen (Profil bestimmt Box) — Vorteile: nichts zu tun · Nachteile: kein Raum-Kontext.
2. B) Reader→Box-Mapping (`reader_device.default_spotify_device_id`), Override beim Scan —
   Vorteile: echtes Multi-Raum, rückwärtskompatibel (`StartPlayback` akzeptiert bereits explizites
   `deviceId`) · Nachteile: Schema + Endpunkt + UI.
**Entscheidung:** **B** (User, 2026-06-02). Zusätzlich bestätigt: **alle Zielboxen sind/werden
Spotify-Connect-fähig** → kein Bluetooth-/`raspotify`-Zwischenschritt nötig.
**Begründung:** Minimaler, additiver Eingriff; Profil-Default bleibt Fallback.
**Konsequenzen:** Plan `tasks/plan-reader-box-mapping.md`. **Harte Grenze dokumentiert:** ein
Spotify-Account spielt nur auf **einem** Gerät gleichzeitig → echtes paralleles Multi-Raum nur über
**verschiedene Profile/Accounts**. Offene Unter-Entscheidungen in den Plänen: D-R1 (Herkunft der
`reader_device`-Zeilen), D-P1/D-P2 (Pi-Leser-Hardware/Laufzeit), D-K1/D-O1 (Auth-Migration, OTA-Mechanismus).
**Status:** Accepted

---

### D-016 | 2026-06-02 | Architektur-Grenze (verworfen: Bluetooth-Audio vom ESP)

**Kontext:** Idee, den ESP32 per Bluetooth (A2DP) direkt auf eine Box streamen zu lassen statt über
Spotify Connect.
**Entscheidung:** **Verworfen.** Der ESP bleibt reiner Trigger; Audio streamt Spotifys Cloud auf die
Connect-Box.
**Begründung:** Auf dem ESP liegt kein Spotify-Audio (DRM/Widevine, lizenzierter Client nötig); ESP32
kann den Stream weder beziehen noch dekodieren. A2DP-Source brächte nur schlechte SBC-Qualität, würde
die gesamte Spotify-Connect-Integration (Qualität, Multiroom, Account-Steuerung) opfern und ist
fragiler. Das bestehende „dummer Trigger"-Modell ist einfacher und korrekt.
**Status:** Accepted

---

### D-017 | 2026-06-02 | Hardware (Pi-Leser)

**Kontext:** Welcher RFID-Leser hängt am Pi (bestimmt Daemon-Sprache/Lib/UID-Handling)?
**Fakt (User):** Der Pi-Leser ist ein **HW-147** = **NXP-PN532-Modul** (13,56 MHz, MIFARE Classic 1K/4K,
NTAG; I2C/SPI/UART, Default HSU).
**Entscheidung:** Pi-Daemon in **Python** mit **Adafruit CircuitPython PN532** (+ Blinka), Interface
bevorzugt **I2C** (Restentscheidung D-P1 beim Verkabeln). Kein HID-Sonderfall.
**Begründung:** PN532 liest dieselbe Kartenfamilie wie der MFRC522 am ESP → UID nach Hex-Normalisierung
identisch (zu verifizieren mit bekannter Karte); Adafruit-Lib deckt alle drei Interfaces ab.
**Konsequenzen:** Plan `tasks/plan-pi-reader-daemon.md` aktualisiert; Pflicht-Verifikation UID-Gleichheit
PN532↔MFRC522 (4- vs. 7-Byte, Byte-Reihenfolge).
**Status:** Accepted

---

### D-S3 | 2026-06-02 | Workflow (Sprint 3)

**Kontext:** Spannung zwischen der harten Regel „EIN Sprint = EIN Branch (WP1..WPn als Commits) → ein
PR → gemerged" und Sprint-Ziel 1 „CI grün → **mergen** → Pi-Migration → E2E", das einen Zwischen-Merge
von PR #32 (nur Reader→Box) vor Sprint-Ende impliziert.
**Optionen:**
- A) Strikt ein PR: alle Sprint-3-WPs auf `feat/sprint-03-reader-lifecycle` sammeln, **einmal** am
  Sprint-Ende mergen + Tag `v0.3.0`. Multi-Raum-E2E auf dem Pi erst nach Sprint-Ende (hängt ohnehin an
  Hardware: mehrere Premium-Accounts + Connect-Boxen). Konfliktfrei mit der Regel.
- B) Zwischen-Merge von #32 jetzt + Zwischen-Tag, Rest in neuem Branch/PR. Verstößt gegen „ein Sprint =
  ein PR" / „keine parallelen Einzel-PRs".
**Entscheidung:** **A** (User, 2026-06-02). Kein Merge von #32 vor Sprint-Ende.
**Begründung:** Regelkonform; Multi-Raum-E2E ist hardware-blockiert → später Merge kostet keine reale Zeit.
**Konsequenzen:** Provisioning/OTA (Teil 2/3 aus `plan-esp-ota-perreader-keys.md`) → **Sprint 4**.
Terminologie „Wobie": nur produktive Doku/Kommentare generisieren, Historie bleibt als Audit-Spur.
**Status:** Accepted
