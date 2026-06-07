# Plan: Installationsdokumentation SpotifyFam Server

**Erstellt:** 2026-06-07  
**Status:** Done

## Scope
Es wird eine saubere, verständliche Installationsanleitung für eine frische SpotFamServ-/SpotifyFam-Server-Installation geschrieben. Fokus ist der Raspberry Pi als Zielsystem inklusive notwendiger Pi-OS-Voraussetzungen, Docker/Compose, Repo/Secrets, Erststart, Spotify-OAuth, Auto-Deploy und Basis-Verifikation.

Nicht Teil des Scopes: Codeänderungen, Schemaänderungen, neue Dependencies, Hardware-Firmware-Umbau oder produktive Secret-Werte.

## Betroffene Bereiche
- `docs/` — neue oder erweiterte Installationsdoku für Greenfield-Setup.
- `README.md` — optionaler kurzer Verweis auf die maßgebliche Installationsdoku.
- `docs/pi-deployment.md` / `deploy/README.md` — optional: Entdopplung oder Link-Klärung, falls die neue Doku diese Runbooks referenziert.

## 4-Lens-Analyse
1. **Lens 1 – Runtime & Sprache:** Keine Runtime-Änderung. Die Doku muss die bestehenden Zielversionen korrekt nennen: Raspberry Pi OS/Debian 13 64-bit, arm64/aarch64, Docker Engine, Docker Compose v2-Plugin, Symfony/PHP im Container, PostgreSQL im Container. Node/pnpm sind für den Pi-Normalbetrieb nicht Voraussetzung.
2. **Lens 2 – Frameworks & Abhängigkeiten:** Keine neuen Frameworks oder Dependencies. Die Anleitung muss klar zwischen Runtime-Dependencies auf dem Pi und Entwicklungsdependencies auf der Dev-Maschine unterscheiden.
3. **Lens 3 – Build, CI/CD & Tooling:** Die Doku muss den aktuellen Deploymentpfad beschreiben: Web-Image kommt aus GHCR/CI, Pi baut das Frontend nicht, App-Image wird lokal gebaut, Auto-Deploy läuft tag-getriggert per `systemd`-Timer und `docker compose`.
4. **Lens 4 – Security & Compliance:** Secrets dürfen nur als Platzhalter dokumentiert werden. Anleitung muss `backend/.env.local`, Root-`.env`, Deploy-Key, Spotify Redirect URI, `READER_API_KEY`, SSH-Zugriff, keine Secret-Commits und optionales HTTPS-/LAN-Risiko klar benennen.

## Cross-Module Antworten
1. **Upstream:** Upstream sind bestehende Runbooks (`docs/pi-deployment.md`, `deploy/README.md`, `tasks/lessons.md`, `README.md`). Die neue Doku darf diese nicht widersprechen, sondern soll sie als detaillierte Referenzen verlinken.
2. **Downstream:** Downstream sind Betreiber/User, künftige Agenten und Sprint-Hand-offs. Klare Schrittfolge, Prüfbefehle und Fehlerhinweise sind nötig, damit Folgearbeiten nicht aus verstreuten Notizen rekonstruiert werden müssen.
3. **Audit:** Keine Runtime- oder State-Änderung. Kein Audit-Eintrag nötig. Falls eine dauerhafte Doku-Entscheidung getroffen wird, genügt ein kurzer Hinweis in der Plan-Abschlusssektion.
4. **API-Vertrag:** Keine API-Änderung.
5. **Feature-Flags:** Nicht relevant.

## Akzeptanzkriterien
1. Eine Person kann anhand der Doku erkennen, welches Pi OS und welche OS-Einstellungen vorausgesetzt sind.
2. Die Doku beschreibt eine frische Installation von leerem Pi bis laufender Weboberfläche unter `http://<pi-ip>:8080`.
3. Die Doku trennt klar zwischen Erstinstallation, Auto-Deploy, Spotify-OAuth, ESP32/Reader-Anbindung und Verifikation.
4. Secret-Werte werden ausschließlich als Platzhalter oder Variablennamen genannt.
5. Bestehende Runbooks bleiben verlinkt und werden nicht widersprüchlich dupliziert.
6. Mindestens eine manuelle Verifikation prüft Links/Inhalt per Lesen/Suche.

## Definition of Done
- [x] Installationsdoku geschrieben.
- [x] README oder Projektkarte verweist auf die Installationsdoku, falls sinnvoll.
- [x] Bestehende Deployment-Runbooks bleiben konsistent.
- [x] Keine Secrets oder lokalen Werte versehentlich dokumentiert, außer bereits öffentliche Beispielwerte wie LAN-IP aus bestehendem Runbook.
- [x] Manuelle Verifikation dokumentiert.
- [x] Plan-Abschlusssektion aktualisiert.

## Risiken / Offene Fragen
- Der aktuelle Pi-Zielstand ist Debian 13/Raspberry Pi OS Lite 64-bit. Falls du bewusst eine andere unterstützte OS-Basis willst, muss die Doku das abbilden.
- GitHub-Deploy-Key-Details können je nach Repo-Zugriff variieren; die Doku sollte hier Prinzip und sichere Platzhalter beschreiben.
- Der Produktname ist uneinheitlich: README sagt „Spotify Familien Server“, Repo heißt `SpotFamServ`. Empfehlung: Doku nennt beide beim Einstieg und verwendet danach `SpotFamServ`.

## Geplanter Doku-Aufbau
1. Zielbild und Varianten: lokale Entwicklung vs. Pi-Installation.
2. Pi-OS-Voraussetzungen: Modell, Architektur, OS, Netzwerk, SSH, User/Sudo, Zeit/DNS, Speicher.
3. Basis-Pakete und Docker Compose v2.
4. Repo-Klon und Deploy-Key.
5. Konfiguration: `.env`, `backend/.env.local`, Spotify-App, Redirect URI, Reader-Key.
6. Erststart: `docker compose build`, `up`, Composer/vendor bei Bind-Mount, Migrationen, Healthcheck.
7. Auto-Deploy via systemd-Timer.
8. Spotify-Ersteinrichtung via SSH-Loopback-Tunnel.
9. ESP32/Reader-Anbindung.
10. Verifikation, Betrieb, Backup, Update, Troubleshooting.

## Verifikations-Log
- Verifiziert: Doku-Einstieg auffindbar | `README.md`, `docs/PROJECT_MAP.md`, `docs/pi-deployment.md` verlinken `docs/installation.md` | OK | 2026-06-07
- Verifiziert: Secret-Hygiene | Suche nach versehentlichen konkreten `SPOTIFY_CLIENT_SECRET`-/`READER_API_KEY`-Werten in Markdown; neue Doku nutzt Platzhalter | OK | 2026-06-07
- Verifiziert: Editor-/IDE-Diagnostik | `ReadLints` für geänderte Markdown-Dateien | keine Fehler | 2026-06-07
- Verifiziert: Git-Diff | `git status --short` und `git diff` im Worktree `SpotFamServ-install-docs` geprüft | nur geplante Doku-/Planänderungen | 2026-06-07
- Automatisierte Tests: nicht ausgeführt; reine Markdown-/Dokuänderung ohne Codepfad.

## Abgeschlossen
- 2026-06-07: `docs/installation.md` als Greenfield-Installationsanleitung für den Raspberry Pi erstellt. README, Projektkarte und Pi-Runbook verweisen auf die neue Einstiegsdoku; bestehende Betriebsrunbooks bleiben erhalten.
