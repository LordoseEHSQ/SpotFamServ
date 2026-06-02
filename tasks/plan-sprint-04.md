# Plan: Sprint 4 – Card-UX (DataGrid) & Playback-Reliability

**Erstellt:** 2026-06-02
**Status:** Plan – wartet auf User-Bestätigung, NICHT umgesetzt
**Sprint-Branch:** `feat/sprint-04-card-ux-playback` (ein Sprint = ein Branch, WP als Commits)
**Worktree:** `../SpotFamServ-sprint-04` (von `origin/main` @ `3746bd0`)
**User-Weichen (2026-06-02, AskQuestion):** Reader→Gerät bevorzugt + Fallback Profil-Default ·
ein gemeinsamer Sprint · shadcn-Table (keine neue Dep) · nur Playlists · eigene Seite `/profiles/:id/cards` als DataGrid.

---

## Auslöser (User, 2026-06-02)
1. **UX schlecht:** Kartenverwaltung soll ein **DataGrid** sein, in dem direkt der Name der gebundenen
   Playlist sichtbar ist; die vielen **Popup-Modals** sollen weg → alles in einer Ansicht.
2. **Bug:** Beim Scannen am Pi-Leser spielt **kein Song**.

## Diagnose Playback-Bug (faktenbasiert, am Pi verifiziert 2026-06-02)
Quelle: DB-Abfragen auf dem Pi + Backend-Code-Analyse (Subagent), nicht geraten.
- **U1 (hart belegt):** Kein Profil hat `default_spotify_device_id` (Lars + Julius leer). Der Scan ruft
  `StartPlayback(profile, uri, device_id=null)` → ohne Default zwingend `SpotifyNoDeviceException`
  (`StartPlayback.php:35-38`). → Selbst ein perfekter Scan spielt nichts.
- **U2 (Architektur-Lücke):** Sprint-3 baute ein **Reader→Gerät-Mapping** (`SetReaderDefaultDevice`,
  `reader_device.default_spotify_device_id`), aber `ProcessScan`/`StartPlayback` **nutzen es nicht** –
  nur das Profil-Default. Reader-ID wird beim Scan lediglich geloggt (`ProcessScan.php:42-44`).
- **U3 (Reliability):** Der Pi-Daemon läuft nur als manueller `setsid`-Prozess (kein systemd),
  `pi_reader.py` ist **nicht im Repo** committet, feuert im **Dauertakt** (788× dieselbe UID → `debounced`-Spam)
  und hat seit 17:41 Uhr nichts mehr geloggt → der Abend-Scan kam nie im Backend an
  (Outcomes je: nur `debounced` + `unknown_card`, **nie** `success`/`no_device`).
- **Korrekt vorhanden:** Karte `E3D43735`→Profil Lars, Binding→Playlist `5Hjb…`, Spotify-Token Lars. Token/Binding sind NICHT die Ursache.

**Konsequenz:** Der Bug ist mehrschichtig: Gerätewahl-Logik (U1/U2) **und** Zustellungs-/Daemon-Reliability (U3).
Beide müssen adressiert werden, sonst bleibt E2E unzuverlässig.

## UX-Befund (Ist-Stand, aktueller `main`)
- Vorhanden: `@tanstack/react-query`, Tailwind v4, shadcn-Komponenten (`Table`, `Dialog`, `Select`,
  `AlertDialog`, `Badge`). **Kein** DataGrid installiert (kein MUI/ag-grid/react-table).
- `CardsPage` = Legacy (Inline-Styles, handgerollte Overlay-Modals). Kartenliste liefert **keinen**
  Playlist-Namen (`RfidCardController::cardToArray` nur id/uid/label) → Tabelle bräuchte den Binding-Namen
  pro Karte (sonst N+1).
- Vorbilder im Stack: `DevicesPage`/`ProfilesPage` (shadcn-Table + inline Aktionen + AlertDialog).

---

## Sprint-Ziel & WorkPackages
**Ziel:** Karte am Pi scannen → gebundene Playlist spielt zuverlässig auf dem gewünschten Gerät; und die
Kartenverwaltung ist ein modal-freies DataGrid mit sichtbarem Playlist-Namen.

- **WP1 – Playback-Zielgerät beim Scan (U1+U2).** `ProcessScan`→`StartPlayback` so erweitern, dass die
  Gerätewahl ist: **(a)** Reader→Gerät-Mapping des scannenden Readers, sonst **(b)** Profil-Default.
  Stale-ID-Re-Resolve-per-Name für beide Pfade (wie #33). `scan_event.details` um `device_source`
  (`reader|profile`) + `device_id` erweitern (Diagnose). PHPUnit für beide Pfade + „kein Gerät irgendwo".
- **WP2 – Kartenverwaltung als DataGrid (modal-frei).**
  - Backend additiv: `GET …/rfid-cards` liefert pro Karte optionales `binding: {id, name} | null`
    (vermeidet N+1). OpenAPI additiv → oasdiff non-breaking. PHPUnit.
  - Frontend: `CardsPage` neu als shadcn-`Table` mit Spalten UID · Label · **Playlist** · Aktionen.
    Anlegen inline (vorhandenes Panel beibehalten/aufräumen), Bearbeiten inline, Löschen via `AlertDialog`,
    Binding via vorhandenem Spotify-Playlist-Select (v0.2.5) – **keine fixed-overlay-Modals** mehr.
    Scan-to-Create (Polling) bleibt erhalten.
- **WP3 – Pi-Reader-Daemon Reliability (U3).** `firmware/pi_reader/` ins Repo: `pi_reader.py`,
  `secrets.env.example` (git-ignored real), `spotfam-reader.service` (systemd, `Restart=always`),
  `README.md`. **Entprellung lokal**: nur bei *neuem* Karten-Auflegen senden statt Dauertakt
  (Karten-Präsenz-Tracking) → eliminiert `debounced`-Spam. Auf dem Pi systemd-Service statt `setsid`.
- **WP4 – Gerät konfigurierbar + Onboarding.** Sicherstellen, dass der User Reader→Gerät und/oder
  Profil-Default **im UI** setzen kann (verifizieren: `ReadersPage`/`StepDefaultSpeaker` vorhanden) und
  kurz dokumentieren. Ohne gesetztes Gerät kann WP1 nichts spielen (Fallback ist auch leer).

### Bewusst NICHT in Sprint 4
- Track/Song-Bindung (User: nur Playlists). USB-Provisioning + signiertes OTA (aus Sprint-3-Defer, eigener Sprint).

---

## 4-Lens-Analyse (Pflicht)

### Lens 1 – Runtime & Sprache
- Backend unverändert PHP 8.5 / Symfony 7.4. Frontend Node 22 / Vite / React.
- Pi-Daemon: Python 3.13 / venv (`--system-site-packages`) auf Debian 13 aarch64, **systemd-Host-Dienst**
  (nicht im Docker-Stack). Berührt Backend-Runtime nicht.

### Lens 2 – Frameworks & Abhängigkeiten
- Frontend: **keine neue Dependency** (shadcn-`Table` + react-query vorhanden) → kein Lockfile-Impact.
- Backend: **keine neuen Composer-Deps** (binding-in-list + Gerätewahl nutzen Vorhandenes) → kein Audit-Impact.
- Pi-Daemon: bereits genutzte Adafruit-Libs, nur auf dem Pi. Keine CI-Build-Auswirkung.

### Lens 3 – Build, CI/CD & Tooling
- WP2 ändert eine **Response-Shape** (`rfid-cards` +`binding`) → additiv/optional, oasdiff **non-breaking**
  (Pflicht: als nullable Zusatzfeld, kein required). PHPStan L8 + PHPUnit + tsc + `pnpm build` grün.
- WP1 ändert keine Public-API-Shape (nur internes Verhalten + `scan_event.details`-Inhalt).
- WP3: Daemon im Repo, per Pull auf den Pi; **nicht** im Compose-Build. systemd-Unit-Install ist ein
  einmaliger Pi-Schritt (Runbook). Optional `ruff` später.
- **Release-Tag: OFFEN (siehe D-S4-VER).** Vorschlag `v0.3.0` (Minor=Sprint) + Sprint-3 retroaktiv schließen.

### Lens 4 – Security & Compliance
- `binding.name` ist für authentifizierte UI-User ohnehin via Binding-Endpoint sichtbar → keine neue Exposure.
- Gerätewahl: keine neuen Secrets; Device-IDs nicht sensibel. Reader-Auth unverändert (globaler `READER_API_KEY`).
- Daemon-Key bleibt git-ignored (`secrets.env`); systemd-Unit läuft als User `lars` (least-privilege, kein root).

## Cross-Module-5-Fragen
1. **Upstream:** /scan-Vertrag unverändert (ESP + Pi-Daemon). WP3 ändert nur die *Sende-Frequenz* des Pi-Daemons, nicht den Vertrag.
2. **Downstream:** Spotify Web API – WP1 wählt device_id neu; beide Pfade MÜSSEN den Stale-ID-Re-Resolve nutzen, sonst Playback-Bruch nach Box-Reconnect.
3. **Audit:** `scan_event.details` bekommt `device_source`+`device_id` (bessere Fehlerdiagnose). Bindings/Devices als Config-State.
4. **API-Vertrag:** nur `rfid-cards` +`binding` (additiv, nullable) → oasdiff prüfen. Sonst keine Shape-Änderung.
5. **Feature-Flags:** nicht nötig (additiv/UX, rückwärtskompatibel).

---

## Offene Entscheidungen (vor Code final, NICHT raten)

### D-S4-VER – ENTSCHIEDEN = A (User, 2026-06-02)
Sprint 3 **retroaktiv schließen** (todo/sprint-03.md/Milestone/CHANGELOG nachziehen, Stand `v0.2.5`);
Sprint 4 endet mit **`v0.3.0`** (Minor=Sprint). → Erste Aufgabe im Sprint-4-Chat: Sprint-3-Bookkeeping.

### D-S4-DEV – ENTSCHIEDEN = explizit (User, 2026-06-02)
Wenn weder Reader- noch Profil-Gerät gesetzt → klare UI-/Outcome-Meldung „kein Wiedergabegerät
konfiguriert" + Onboarding-Hinweis (WP4). **Kein Auto-Wählen** (Raum-Risiko).

### D-S4-GH – ENTSCHIEDEN = full (User, 2026-06-02)
GitHub Milestone „Sprint 4" + WP-Issues WP1–WP4 angelegt; Planungs-Docs committed/gepusht.

---

## Subagenten-Schwarm (nach Bestätigung)
- **Parallel (unabhängig):**
  - WP1 Backend Gerätewahl (`ProcessScan`/`StartPlayback`) + Tests.
  - WP2a Backend `rfid-cards` +binding (+OpenAPI/Test).
  - WP3 Pi-Daemon ins Repo + systemd + Entprellung (Code; Pi-Install seriell/Hardware).
- **Seriell / abhängig:**
  - WP2b Frontend DataGrid (braucht WP2a-Shape).
  - WP4 Verifikation Geräte-UI + Doku.
  - **E2E am Pi** (Deploy → Gerät setzen → realer Scan spielt Song): Hardware/User-blockiert, am Ende.

## Blockierend (User/Hardware)
- Ein **Spotify-Connect-Gerät muss online** sein, um es als Reader-/Profil-Default zu wählen (sonst leere Geräteliste).
- **Realer Test-Scan am Pi** nötig, um WP1+WP3 live zu bestätigen (warum kam der Abend-Scan nicht an?).
- Pi-Reboot/Service-Install für systemd-Unit (WP3).

## Risiken
- **Leeres Gerät bleibt der Show-Stopper:** WP1 fixt die *Logik*, aber ohne ein vom User gesetztes Gerät spielt weiterhin nichts (D-S4-DEV/WP4).
- **oasdiff:** `binding`-Feld muss strikt additiv/nullable sein, sonst Breaking-Gate.
- **Daemon-Entprellung:** Fehlerhafte Präsenz-Erkennung könnte Scans verschlucken → Gegentest mit bekannter Karte.
- **L-015 (bekannt):** Schema-/Code-Release-Fenster; hier nur additive Änderungen, geringes Risiko.

## Definition of Done
- [ ] Realer Pi-Scan spielt die gebundene Playlist auf dem gewählten Gerät (live verifiziert, `scan_event.outcome=success`).
- [ ] `CardsPage` ist DataGrid (Playlist-Name sichtbar), **ohne** Overlay-Popups; tsc/build grün.
- [ ] `rfid-cards`+binding additiv (oasdiff non-breaking), PHPStan L8 + PHPUnit grün.
- [ ] Pi-Daemon im Repo + systemd-Service aktiv, `debounced`-Spam eliminiert.
- [ ] CI grün auf `main`; Doku (`CHANGELOG.md`, `docs/sprints/sprint-04.md`, Decisions) gepflegt.
- [ ] Working-Memory aktualisiert (inkl. Sprint-3-Altlast bereinigt, D-S4-VER).
- [ ] Tag gesetzt (per D-S4-VER) → Pi-Deploy verifiziert.

## Verifikations-Log
- 2026-06-02: Pi-DB geprüft – kein Profil-Default-Device; Outcomes nur debounced/unknown_card; Daemon läuft, loggt seit 17:41 nichts. Karte/Binding/Token Lars vorhanden.
