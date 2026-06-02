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
