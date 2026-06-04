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
**Status:** Superseded by D-S3b (Deploy-Teil), 2026-06-02

---

### D-018 | 2026-06-03 | ESP32 Consumer-Provisioning & OTA

**Kontext:** ESP32-Reader sollen nicht nur als Entwicklerhardware funktionieren, sondern perspektivisch als einfache Consumer-Loesung. Der Nutzer bestaetigte fuer ESP-Geraete denselben RFID-Reader-Typ wie am Pi: HW-147/PN532. Der bestehende ESP-Firmwarestand nutzt dagegen MFRC522/SPI und `secrets.h`.
**Optionen:**
1. `secrets.h` pro Geraet und USB-Flash bei jeder Aenderung — Vorteile: einfach fuer Entwickler; Nachteile: nicht consumer-tauglich, fehleranfaellig, Secrets im lokalen Build, kein sauberer Field-Update-Pfad.
2. Serielle Provisionierung nach USB-Erstflash — Vorteile: robuster als `secrets.h`; Nachteile: weiterhin PC/Tooling fuer normale Einrichtung noetig.
3. Captive Portal + Backend-Claim + NVS + OTA — Vorteile: Nutzer braucht nach Hersteller-Erstflash keinen PC, klare Kopplung ans Backend, Updates drahtlos; Nachteile: mehr Firmware-/Backend-/UI-Aufwand und Sicherheitsdesign noetig.
**Entscheidung:** Option 3 als Zielbild. USB bleibt nur fuer den initialen Hersteller-/Entwickler-Flash. Danach richtet der Nutzer den Reader ueber ein Captive Portal ein, koppelt ihn per kurzlebigem Backend-Claim und erhaelt Firmware-Updates per OTA.
**Begruendung:** Das ist der einzige Ansatz, der zur Consumer-Anforderung "wirklich einfach, wenig Fehlerquellen" passt. `secrets.h` und serielle Provisionierung bleiben maximal fuer Entwicklung/Fallback, nicht fuer den Produktfluss.
**Konsequenzen:** ESP-Firmware muss von MFRC522/SPI auf PN532/HW-147 geplant werden (Bus-Entscheidung I2C vs. SPI separat verifizieren). Backend braucht Claim-/Provisioning-Endpunkte und OTA-Manifest/Artefakt-Strategie. UI braucht einen gefuehrten "Reader hinzufuegen"-Flow. OTA muss gegen falsche Firmware, Downgrade und Teil-Downloads abgesichert werden.
**Status:** Accepted

---

### D-S3b | 2026-06-02 | Früher Feature-Deploy + Interim-Tag (revidiert D-S3)

**Kontext:** User will Scan-to-Create real am Pi nutzen → früher Deploy nötig (vor Sprint-Ende).
Widerspricht D-S3=A (Merge + `v0.3.0` erst am Sprint-Ende).
**Befund (empirisch):** `pi-deploy.sh:18` wählt `git tag -l 'v*' --sort=-v:refname | head -1`. Git-Versionsort
ohne `versionsort.suffix` sortiert `v0.3.0-rc.1` **über** `v0.3.0` → ein RC-Tag würde den finalen
`v0.3.0`-Deploy blockieren. Verifiziert in Scratch-Repo.
**Optionen:** (a) `v0.3.0` jetzt → verbrennt Sprint-Minor vor Sprint-Done (verstößt SemVer „Minor=Sprint").
(b) `v0.3.0-rc.1` → bricht spätere `v0.3.0`-Auswahl (siehe Befund). (c) Interim-Patch `v0.2.4` → sortiert
über `v0.2.3`, unter `v0.3.0`; Tooling-kompatibel; SemVer-unsauber (Feature unter Patch).
**Entscheidung:** **(c) `v0.2.4`** als Interim-Feature-Release. **Regelkonform via `main`**: Branch
verifizieren → **Squash-Merge nach `main`** → Tag `v0.2.4` auf `main` → Pi zieht diese Version.
(Revidiert: früher Vorschlag „Tag auf Branch-HEAD ohne main-Merge" verworfen — Sonderweg, der `main`
umgeht; User hat zu Recht den Standardweg eingefordert, 2026-06-02.)
Sprint-Abschluss weiterhin: finaler `v0.3.0` (sortiert über `v0.2.4`).
**Begründung:** Standard-Workflow (`parallel-branch-workflow.mdc`); `v0.2.4` hält `v0.3.0` für den echten
Sprint-Close frei und sortiert tooling-korrekt. SemVer-Impurität (Feature unter Patch) = kleineres Übel
für ein Interim. `-rc`-Suffix bleibt verboten (git-Versionsort-Falle).
**Konsequenzen:** Lookup-Endpoint = additive API (oasdiff non-breaking, keine Migration). Voraussetzung
vor Merge: CI grün (PHPStan L8, PHPUnit, tsc, oasdiff). Lessons-Eintrag zur git-Versionsort-Falle.
**Status:** Accepted (User, 2026-06-02)

---

### D-S4 | 2026-06-02 | Sprint-4-Weichen (Card-UX & Playback)

**Kontext:** User-Feedback – Kartenverwaltung UX schlecht (will DataGrid statt Popups, Playlist-Name sichtbar)
+ Bug „beim Scan am Pi spielt kein Song". Fünf Weichen per AskQuestion entschieden (User, 2026-06-02):
- **D-S4a Gerätewahl beim Scan = Reader→Gerät bevorzugt, Fallback Profil-Default.** Begründung: Multi-Raum-tauglich
  (Karte = wessen Musik, Reader/Raum = welches Gerät). Erfordert Code in `ProcessScan`/`StartPlayback` (nutzt
  das in Sprint 3 gebaute, bisher ungenutzte `reader_device`-Mapping).
- **D-S4b Scope = ein gemeinsamer Sprint 4** (Card-UX + Playback-Fix zusammen).
- **D-S4c DataGrid = vorhandene shadcn-`Table` + Tailwind** (keine neue Dependency; konsistent mit DevicesPage/ProfilesPage).
- **D-S4d Bindung vorerst nur Playlists** (keine Track/Song-Bindung; Backend kann nur Playlist). UI benennt „Playlist".
- **D-S4e Kartenverwaltung bleibt eigene Seite** `/profiles/:id/cards`, neu als DataGrid (nicht in TabRfid integriert).
**Begründung:** Robusteste Gerätewahl + minimaler Dependency-/Risiko-Footprint; Song-Bindung als separater, größerer Schritt vermieden.
**Restpunkte entschieden (User, 2026-06-02):**
- **D-S4-VER = A:** Sprint 3 retroaktiv schließen (Stand `v0.2.5`); Sprint 4 endet mit **`v0.3.0`** (Minor=Sprint).
- **D-S4-DEV = explizit:** Wenn weder Reader- noch Profil-Gerät gesetzt → klare Meldung „kein Wiedergabegerät konfiguriert" (kein Auto-Wählen).
- **D-S4-GH = full:** GitHub Milestone „Sprint 4" + WP-Issues anlegen; Planungs-Docs committen/pushen.
**Status:** Accepted (User, 2026-06-02) – Implementierung im frischen Sprint-4-Chat (Starter: `docs/sprints/sprint-04-starter.md`)

---

### D-019 | 2026-06-03 | Audio-Extractor-Plugin (yt-dlp/ffmpeg) hinter Feature-Toggle

**Kontext:** User-Wunsch, Musik zum CD-Brennen zugänglich zu machen. Zwei Varianten gefordert:
(1) Spotify-Audio extrahieren, (2) YouTube-/URL-Audio extrahieren. Optionales Plugin via Feature-Toggle.
**Harte Grenze:** Variante 1 (**Spotify-Ripping**) ist **abgelehnt** – erfordert DRM-Umgehung (§95a UrhG /
DMCA §1201), die Web API liefert keine Audiodaten. User-Begründung „Premium = mir gehört die Musik" ist
rechtlich falsch (Streaming-Lizenz ≠ Eigentum; §53-Privatkopie greift nicht bei umgangenem Kopierschutz).
**Entscheidung (User-Freigabe 2026-06-03):** Nur Variante 2 als Modul `AudioExtractor` (Ports & Adapters),
`yt-dlp` + `ffmpeg`, gedacht für **legale Quellen** (eigene/CC/Public-Domain). Verantwortung für die URL
beim Nutzer; UI-Hinweis statt technischer Sperre.
- **D-A Sync-MVP:** synchron im php-fpm-Request (kein Messenger/Queue). Begründung: Single-User-Heim-Setup,
  „lightweight". Mitigation gegen Worker-Blockade: Process-Timeout (240s), Dauergrenze (1800s),
  `set_time_limit`, nginx `fastcgi_read_timeout 300s`. Upgrade auf Async später ohne API-Bruch möglich.
- **D-B yt-dlp via pip** (`pip install -U yt-dlp` im Image-Build, isolierte venv), **nicht** apk –
  weil YouTube yt-dlp regelmäßig bricht und das apk-Paket veraltet. Build-Zeit-Pin, kein Laufzeit-Auto-Update.
- **D-C keine Persistenz:** Temp-Datei → Stream → Löschen (`deleteFileAfterSend`). Kein Schema, kein Storage.
- **D-D Auth = Default-AUS:** Feature folgt dem aktuell offenen API-Stand; einziger Schutz ist `AUDIO_EXTRACTOR_ENABLED=0`.
  Bewusst dokumentiert, nicht heimlich. Echte Admin-Auth ist projektweit offen (separates Thema).
- **D-E Formate:** nur MP3 (128/192/256/320 kbps) + WAV (PCM). Kein Opus/FLAC (lightweight).
**Security:** `symfony/process` mit Argument-Array (kein Shell-String → keine Command-Injection); URL als
einziges positionales Argument; nur http(s)-Scheme erlaubt (SSRF/`file://`-Abwehr); stderr gekürzt zurück.
**Konsequenzen:** Neue Dependency `symfony/process`; `Dockerfile` um ffmpeg/python3/yt-dlp erweitert
(arm64/Pi-tauglich); nginx-Timeout erhöht; Frontend `postBlob` + Seite `/tools/audio-extractor` + bedingte
Nav; OpenAPI additiv (2 Endpunkte). **Rollback:** Code-/Dockerfile-Revert; Feature war default AUS → risikolos.
**Status:** Teilweise superseded by D-020 (Feature-Flag/Persistenz/Engine), 2026-06-03

---

### D-020 | 2026-06-03 | Audio-Extractor: vollwertiges Feature mit Persistenz + Update-Modus (revidiert D-019)

**Kontext:** User-Anweisung, das Plugin zu einem normalen Feature zu machen: Feature-Flag raus,
„Update Modus" für schnelles Aktualisieren, Dateien im Benutzerbereich speichern und über das
Web-Interface herunter- bzw. löschbar. Klärungsfragen (Update-Bedeutung, Storage-Ort, -Modell) wurden
übersprungen → autonome Entscheidung nach Empfehlung (L-019), Annahmen dokumentiert.
**Entscheidungen (revidieren D-019):**
- **Feature-Toggle entfernt** (revidiert D-D): immer aktives Feature, keine `AUDIO_EXTRACTOR_ENABLED`,
  keine bedingte Nav/404. Schutz durch Default-AUS entfällt; API bleibt offen wie der Rest (MVP-Stand).
- **Update-Modus = yt-dlp-Self-Update über Web** (`POST /audio-extractor/update` → `yt-dlp -U`,
  Versionsanzeige in `/config`). **Revidiert D-B:** statt pip-venv das offizielle **Release-Binary**
  (zipapp), weil nur das `yt-dlp -U` in-place unterstützt; architekturunabhängig (python3-zipapp,
  x86_64 + arm64/Pi), liegt www-data-beschreibbar unter `/opt/yt-dlp`. **Risiko:** bei offener API kann
  jeder im LAN ein Update (Download + Ausführung offizieller Releases) auslösen → Heim-LAN-akzeptabel.
- **Persistenz** (revidiert D-C, „keine Persistenz" verworfen): gemeinsamer Benutzerbereich auf einem
  **Host-Verzeichnis** (`${AUDIO_STORAGE_HOST_DIR:-./data/audio}` → Container `/data/audio`), per
  Dateisystem erreichbar (CD-Brennen). **Kein DB-Schema** – Liste = Dateisystem-Scan (lightweight).
- **„Benutzer"-Trennung:** ein **gemeinsamer** Bereich (das System hat keine echte User-Auth; nur
  FamilyProfiles). Pro-Profil-Trennung bewusst verworfen (mehr Code, kein echter Mehrwert hier).
- **Datei-Management:** Liste/Download/Delete über `/audio-extractor/files[/{name}]`.
**Security:** `FilesystemAudioStorage` mit harter Path-Traversal-Abwehr (Name ≠ Pfad; Separatoren/`..`/
Null-Byte abgewiesen; realpath muss direktes Kind des Storage-Dirs sein). Download/Delete nur über
validierten Namen. Subprozess weiterhin Argument-Array (keine Injection).
**Offen/Risiko:** Kein hartes Quota (R6) → nur Anzeige der belegten Gesamtgröße; SD/Platte kann
volllaufen. Hartes Limit später bei Bedarf.
**Konsequenzen:** `MediaEngineInterface` + `AudioStorageInterface` neu; Controller-Endpunkte erweitert
(additive OpenAPI); Dockerfile auf Binary umgestellt (+`curl`, `python3` bleibt, kein pip/venv);
compose-Volume `/data/audio`; `.gitignore` `/data/audio` + `backend/var/audio`; FE: Dateiliste +
Download-Link + Delete + Update-Button. 26 Tests grün, PHPStan L8, Lint ok, FE-Build grün.
**Status:** Accepted (autonom nach übersprungener Rückfrage; User-Korrektur jederzeit möglich)

---

### D-021 | 2026-06-04 | Flash-/Provisioning-Station (ESP am Pi, im Server sichtbar)

**Kontext:** ESP soll an Pi-USB gesteckt, im SpotFamServ-Server erkannt, geflasht und
provisioniert werden (idiotensicher, geloggt, mehrere ESP-Varianten). Plan:
`tasks/plan-pi-flash-provisioning-station.md`.
**Optionen:** A) USB/`esptool` direkt im Docker-`app`-Container · B) unprivilegierter
Host-Agent (Python systemd) auf dem Pi, Backend ohne USB; Web steuert, Agent fuehrt aus.
**Entscheidung (User, 2026-06-04):** **B** — Host-Agent + Backend ohne USB (Erweiterung von D-P2 A).
**Begruendung:** Least Privilege/Sicherheit (kein web-facing Container mit Roh-USB + Flash-Tooling),
konsistent mit bestehendem Pi-Reader-Muster, robuster als Docker-USB-Hotplug.
**Weitere bestaetigte Weichen (User, 2026-06-04):**
- **Gate 0 zuerst auf dem Pi:** nativer Flash + UID-Lesen real beweisen, bevor die Station gebaut wird.
- **Live-Status via WebSocket/SSE** (nicht Polling). Konsequenz/Kosten: zusaetzliche Infra noetig
  (Symfony-idiomatisch: Mercure-SSE-Hub als Container, oder dedizierter WS-Dienst) — bewusst akzeptiert.
- Kein freier Firmware-Upload ueber Web; nur registrierte, sha256-gepruefte Artefakte; Agent prueft
  Hash UND Ziel-Chip; unbekannte Chips werden verweigert.
**Status:** Accepted (Plan bestaetigt) — Implementierung erst nach Gate 0 + Dry-Run/Modell-Gate.

---

### D-022 | 2026-06-04 | HW-0 PN532-Test bewusst uebersprungen (User-Override)

**Kontext:** Flash-Station-Umsetzung (D-021). Nativer Pi-Flash + Chip-Detection sind real
bewiesen (Plan-Log `plan-esp-consumer-provisioning-ota.md`: ESP32-D0WD-V3, 4 MB, MAC
78:EE:4C:01:6B:04). Der PN532 ist noch nicht an den ESP geloetet.
**Entscheidung (User, 2026-06-04):** HW-0-Teil „PN532-Erkennung + UID-Gleichheit" wird
**uebersprungen**; gruenes Licht fuer Umsetzung der Flash-Station.
**Begruendung/Abgrenzung:** Die Flash-Station benoetigt nur den (bewiesenen) Flash-Pfad +
Chip-Detection + ein Artefakt (Probe-`.bin`). Sie wird gegen diese realen Faktoren gebaut/getestet.
**Bewusst akzeptiertes Risiko:** Der funktionale Reader-Pfad (Karte -> korrekte UID, Scan->Play)
bleibt UNVERIFIZIERT, bis geloetet wird. End-to-End „geflashter Reader liest Karten" ist offen.
**Modell-Gate bleibt (ABSOLUTER BLOCKER, NICHT uebersprungen):** Dry-Run mit staerkstem Modell vor
Code; Umsetzung mit Sonnet/GPT-5.5; Doku mit Haiku bzw. Fallback `composer-2.5-fast`.
**Status:** Accepted (User, 2026-06-04)
