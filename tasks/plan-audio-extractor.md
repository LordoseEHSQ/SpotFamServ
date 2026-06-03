# Plan: Audio-Extractor-Plugin (URL → MP3/WAV) hinter Feature-Toggle

**Typ:** Feature (neues Modul) · **Branch:** `feat/audio-extractor`
**Status:** FREIGEGEBEN (2026-06-03) – Umsetzung läuft
**Datum:** 2026-06-03

**Freigegebene Entscheidungen (v1):** D-A Sync-MVP ✓ · D-B `pip install -U yt-dlp` ✓ ·
D-C keine Persistenz ✓ · D-D Auth=Default-AUS ✓ · D-E nur MP3+WAV ✓

---

## REVISION 2 | 2026-06-03 | Umbau zu vollwertigem Feature (User-Anweisung)

Der User hat das Feature umgewidmet (Klärungsfragen bewusst übersprungen → autonome Umsetzung
nach Empfehlung, L-019). Änderungen gegenüber v1:
- **Feature-Toggle entfernt** → normales, immer aktives Feature (kein `AUDIO_EXTRACTOR_ENABLED`,
  kein 404-bei-AUS, keine bedingte Nav). Ersetzt D-D.
- **Update-Modus** (neu): yt-dlp über das Web-Interface aktualisierbar (`POST /audio-extractor/update`,
  `yt-dlp -U`) + Versionsanzeige in `/config`. **Ersetzt D-B:** statt pip-venv jetzt das offizielle
  **yt-dlp-Release-Binary** (zipapp, self-update-fähig, architekturunabhängig via python3,
  www-data-beschreibbar unter `/opt/yt-dlp`). Sicherheit: bei offener API kann jeder im LAN ein
  yt-dlp-Update auslösen (lädt offizielles Release + führt es aus) → im Heim-LAN akzeptabel, dokumentiert.
- **Persistenz** (neu, **ersetzt D-C**): extrahierte Dateien werden in einem gemeinsamen Benutzerbereich
  gespeichert (Host-Verzeichnis `${AUDIO_STORAGE_HOST_DIR:-./data/audio}` → Container `/data/audio`),
  per Dateisystem erreichbar (CD-Brennen). **Kein DB-Schema** – Liste = Dateisystem-Scan.
- **Datei-Management** (neu): `GET /audio-extractor/files` (Liste + Gesamtgröße), `GET .../files/{name}`
  (Download), `DELETE .../files/{name}` (Löschen). Frontend: Tabelle mit Download-/Lösch-Aktion.
- **Security-Zusatz:** Path-Traversal-Abwehr in `FilesystemAudioStorage` (Name ist nie ein Pfad;
  Separatoren/`..`/Null-Byte abgewiesen; realpath-Containment-Check). Download/Delete nur über validierten Namen.
- **Risiko R6 (neu):** Kein hartes Quota → Platte/SD kann volllaufen. Aktuell nur Anzeige der belegten
  Gesamtgröße in der UI; hartes Limit später bei Bedarf. **Risiko R2 (yt-dlp-Verfall)** ist durch den
  Update-Button entschärft (kein Image-Neubau nötig).
- **Risiko R7 (Permissions, unverifiziert):** Host-Bind-Mount `./data/audio` behält Host-Ownership
  (uid 1000); php-fpm-Worker läuft als `www-data` (uid 82) → Schreiben/Self-Update kann scheitern
  (analog L-006 vendor-Mount). Setup-Schritt nötig: Host-Dir für den Container-User beschreibbar machen
  (`chmod 0777 data/audio` oder `chown 82:82`), oder per `AUDIO_STORAGE_HOST_DIR` ein passendes Verzeichnis
  setzen. **Erst nach echtem Image-Build/E2E verifizierbar.**
**Verifiziert:** 26 PHPUnit-Tests grün (Validierung, Storage inkl. 6 Traversal-Fälle, Controller alle
Endpunkte), PHPStan L8 sauber, `lint:container` ok, `pnpm build` grün, OpenAPI additiv.
**Unverifiziert (Image nicht gebaut):** yt-dlp-Binary-Download + ffmpeg auf arm64, echte Extraktion,
`yt-dlp -U` als www-data, Host-Mount-Permissions (R7).

## Rechtlicher Rahmen (verbindlich, nicht verhandelbar)
- **Kein Spotify-Ripping.** DRM-Umgehung (§95a UrhG / DMCA §1201) wird nicht gebaut. Die
  Spotify Web API liefert keine Audiodaten – es gibt keinen „sauberen" Weg, und der unsaubere
  ist illegal. Ein Premium-Abo ist eine Streaming-Lizenz, **kein Eigentum** an den Werken;
  die Privatkopie-Schranke (§53 UrhG) greift bei umgangenem Kopierschutz ausdrücklich nicht.
- **Dieses Feature ist für legale Quellen** gedacht (eigene Uploads, Creative-Commons,
  Public Domain, Inhalte mit Download-Erlaubnis). Das Tool erzwingt das nicht; die
  Verantwortung für die jeweilige URL liegt beim Nutzer. UI zeigt einen kurzen Hinweis.
- Konsequenz fürs Design: bewusst **kein** „Suche/Browse"-Komfort, der zum Massendownload
  einlädt. Reine 1-URL-→-1-Datei-Konvertierung.

## Problem / Ziel
Lightweight-Plugin: Nutzer fügt eine Medien-URL (primär YouTube) ein, Backend extrahiert die
Audiospur via `yt-dlp` und transkodiert mit `ffmpeg` in **MP3 (wählbare Bitrate)** oder
**WAV (PCM)**. Datei wird zum Download zurückgegeben. Aktivierbar/deaktivierbar über ein
Feature-Toggle. Standardmäßig **AUS**.

## Akzeptanzkriterien (testbar)
1. Bei `AUDIO_EXTRACTOR_ENABLED=0` (Default): Backend-Endpunkte liefern **404**, Frontend-Nav
   zeigt den Punkt **nicht**, Route ist nicht erreichbar.
2. Bei `=1`: POST mit gültiger http(s)-URL + Format → Antwort enthält die fertige Audiodatei
   (Download) oder einen Fehler als Problem+JSON.
3. Format-Optionen: `mp3` mit Bitrate-Auswahl (128/192/256/320 kbps) **oder** `wav` (PCM 16-bit).
4. Ungültige/nicht-http(s)-URL → **422** mit klarer Meldung, **kein** Subprozess-Start.
5. Kommando wird **ohne Shell** und mit Argument-Array ausgeführt (keine String-Interpolation)
   → keine Command-Injection.
6. PHPUnit grün (Use-Case mit gemocktem Process-Port; Controller-Test enabled/disabled),
   PHPStan Level 8 sauber, Frontend-Build grün.
7. `ffmpeg` + `yt-dlp` sind im `app`-Image vorhanden und auf **aarch64 (Pi 4B)** lauffähig.

## Architektur-Entscheidung: synchron vs. asynchron
**Empfehlung MVP: synchron mit erhöhtem Timeout.** Begründung:
- Single-User-Heim-Setup, kein Multi-Tenant-Lastproblem.
- Kein Messenger/Queue im Stack → Async = Greenfield (Worker, Transport, Status-Entity,
  Polling-Endpoint, Frontend-Polling) = viel „Schnickschnack", widerspricht der Vorgabe.
- **Kosten:** nginx + php-fpm Timeout müssen hoch (z. B. 300s), 1 belegter php-fpm-Worker pro
  laufendem Job. Akzeptabel bei 1 Nutzer. **Risiko:** sehr lange Videos blockieren den Worker.
  → Mitigation: harte Längen-/Timeout-Grenze (z. B. yt-dlp `--match-filter duration<1800`,
  Process-Timeout 240s).
- Upgrade-Pfad auf Async ist später möglich, ohne API-Vertrag zu brechen (gleicher Endpunkt,
  später 202 + Job-ID). **Nicht Teil dieses MVP.**

## Lösungsskizze
Neues Modul `backend/src/Module/AudioExtractor/` nach Ports-&-Adapters-Muster:
- **Application/Port/`MediaExtractorInterface`** – `extract(MediaRequest): MediaResult` (Pfad zur
  Temp-Datei + Dateiname + MIME). Mockbar in Tests.
- **Application/`ExtractAudio`** (`final readonly`, `__invoke`) – validiert Format/Bitrate,
  ruft Port, mappt Domänenfehler.
- **Infrastructure/Process/`YtDlpFfmpegExtractor`** – nutzt **`symfony/process`** (neue
  Dependency), ruft `yt-dlp` mit `-x --audio-format mp3/wav --audio-quality ...` bzw.
  trennt Download + `ffmpeg`-Transkodierung. Schreibt in `sys_get_temp_dir()`, keine Persistenz.
- **Infrastructure/Http/`AudioExtractorController`** (`/api/v1/audio-extractor/...`):
  - `GET /audio-extractor/config` → `{ enabled, formats, bitrates, maxDurationSeconds }`
    (Frontend liest hieraus den Toggle; bei disabled trotzdem erreichbar, liefert `enabled:false`).
  - `POST /audio-extractor/extract` → bei disabled **404**; sonst `BinaryFileResponse` (Download)
    + `unlink` nach Versand (oder StreamedResponse + temp-cleanup).
- **Feature-Toggle:** `config/packages/audio_extractor.yaml` mit
  `%env(bool:AUDIO_EXTRACTOR_ENABLED)%`, default `0` in `services.yaml`/`.env.example`.
  Controller/Use-Case prüfen das Flag früh.

**Frontend** (`frontend/src/`):
- `api/endpoints/audioExtractor.ts` + Erweiterung `api/client.ts` um `postBlob()` (Blob-Download,
  da Client bisher nur JSON kann).
- `hooks/useAudioExtractor.ts` (Config-Query + Extract-Mutation).
- `pages/AudioExtractorPage.tsx` (Input-URL, Select Format/Bitrate, Button, Status, Rechtshinweis)
  via vorhandene `Input`/`Select`/`Button`/`Card`.
- Route `/tools/audio-extractor` + Nav-Eintrag nur rendern, wenn `config.enabled === true`.

**Docker:** `backend/Dockerfile` erweitern: `apk add --no-cache ffmpeg yt-dlp python3`
(yt-dlp ist im Alpine-community-Repo, arm64 vorhanden). Alternativ `pip install yt-dlp`
für aktuellere Version – siehe Risiko R2.

## 4-Lens-Analyse
**Lens 1 – Runtime & Sprache:** PHP 8.5/Symfony 7.4 unverändert. Neue Laufzeit-Abhängigkeiten
im Container: `ffmpeg`, `yt-dlp` (Python). **Pi aarch64:** beide als Alpine-arm64-Pakete
verfügbar; CI muss arm64-Image bauen/testen. ffmpeg-Transkodierung ist CPU-lastig → auf Pi 4B
spürbar, daher Längengrenze.
**Lens 2 – Frameworks & Abhängigkeiten:** **Neue Composer-Dependency `symfony/process`**
(begründet: Subprozess sicher ohne Shell ausführen; `exec()` wäre unsicher/untypisiert).
Keine neuen Frontend-Libs (vorhandene UI-Primitives + `postBlob`). Lock-Impact minimal.
**Lens 3 – Build, CI/CD & Tooling:** Unit-Test Use-Case (gemockter Port), Controller-Test
enabled/disabled. **Echte ffmpeg/yt-dlp-Integration NICHT in CI** (Netzabhängig/flaky) – optional
separater, manueller Job. OpenAPI (`openapi.yaml`) um neue Endpunkte ergänzen → oasdiff: additiv,
kein Breaking. Dockerfile-Change → Image-Build in CI prüfen.
**Lens 4 – Security & Compliance:**
- **Command-Injection:** `symfony/process` mit Argument-Array, niemals Shell-String. URL als
  einzelnes Argument.
- **SSRF/Schema:** nur `http`/`https` erlauben; `file://`, `ftp://` etc. ablehnen. yt-dlp ggf.
  auf bekannte Extraktoren beschränken (Konfig-Option, optional).
- **Ressourcen-DoS:** Process-Timeout, Dauer-/Größenlimit, Temp-Dir-Aufräumen garantieren
  (auch bei Fehler, `finally`).
- **Auth:** API ist aktuell offen (MVP-Projektstand). Feature ist standardmäßig AUS. Wenn aktiv,
  ist es so „offen" wie der Rest der Admin-API. **Bewusst dokumentieren**, nicht heimlich.
- **Lizenz/Recht:** siehe „Rechtlicher Rahmen". UI-Hinweis Pflicht.
- **composer audit / Trivy:** `symfony/process` ist Kernkomponente, geringes CVE-Risiko;
  `yt-dlp`/`ffmpeg` im Image regelmäßig patchen.

## Cross-Module-5-Fragen
1. **Wer konsumiert?** Nur das Frontend-Tool. Kein anderes Backend-Modul hängt davon ab;
   keine Kopplung an Spotify/Rfid/Scan.
2. **Vertrag/Schnittstellen?** Zwei neue additive Endpunkte unter `/api/v1/audio-extractor/`.
   Keine Änderung bestehender Verträge.
3. **Fehlerpfade?** Ungültige URL → 422. Disabled → 404. yt-dlp/ffmpeg-Fehler (Video weg,
   Geo-Block, Format nicht verfügbar) → 502/422 mit Klartext aus stderr (gekürzt, kein Leak).
   Timeout → 504/422. Temp-Cleanup immer.
4. **Migration/State?** **Kein Schema, keine Persistenz.** Dateien sind flüchtig (Temp).
5. **Rollback?** Code-Revert + Dockerfile-Revert. Feature war default AUS → null Risiko für
   Bestand. yt-dlp/ffmpeg im Image stören andere Module nicht.

## Dry-Run (Soll-Ablauf, Feature aktiviert)
1. Nutzer öffnet `/tools/audio-extractor`, fügt YouTube-URL ein, wählt `mp3 / 256 kbps`.
2. Frontend `POST /api/v1/audio-extractor/extract { url, format, bitrate }`.
3. Controller prüft Toggle (an) + URL-Schema → Use-Case → `YtDlpFfmpegExtractor`.
4. `yt-dlp -x --audio-format mp3 --audio-quality 256K -o <tmp> <url>` (Array-Args, Timeout 240s).
5. `BinaryFileResponse` mit `Content-Disposition: attachment` → Browser-Download.
6. Temp-Datei wird nach Versand entfernt.

## Blind Spots / Risiken
- **R1 (Timeout):** Lange Videos blockieren php-fpm-Worker → Dauergrenze + Process-Timeout hart setzen.
- **R2 (yt-dlp-Verfall):** YouTube bricht yt-dlp regelmäßig. Alpine-Paket kann veralten →
  Entscheidung nötig: `apk`-Version (stabil, evtl. veraltet) vs. `pip install -U yt-dlp` beim
  Build (aktueller, aber Build-Var). **Kein Auto-Update zur Laufzeit** (Reproduzierbarkeit/Sicherheit).
- **R3 (nginx-Timeout):** `fastcgi_read_timeout` in `docker/nginx/default.conf` anheben, sonst 504.
- **R4 (Image-Größe):** ffmpeg + python vergrößern das `app`-Image deutlich → für Heim-Pi ok,
  notieren. Alternative Sidecar-Container bewusst **verworfen** (zu viel Komplexität, „lightweight").
- **R5 (Recht):** Missbrauchspotenzial bleibt; mitigiert durch Default-AUS + Hinweis, nicht technisch.

## Offene Entscheidungen (brauchen Freigabe)
- **D-A Sync-MVP:** synchron + Timeout statt Async/Queue. *(Empfehlung)*
- **D-B yt-dlp-Quelle:** `apk add yt-dlp` (stabil) vs. `pip install -U yt-dlp` (aktuell). *(Empfehlung: pip, wegen R2)*
- **D-C Persistenz:** keine – reiner Stream/Download, Temp wird gelöscht. *(Empfehlung)*
- **D-D Auth:** Feature folgt aktuellem offenen API-Stand, nur Default-AUS als Schutz. *(Empfehlung, bis Admin-Auth existiert)*
- **D-E Formate:** mp3 (128/192/256/320) + wav (PCM16). Reicht das, oder zusätzlich opus/flac? *(Empfehlung: nur mp3+wav, lightweight)*

## Definition of Done
- Modul `AudioExtractor` (Port, Use-Case, Extractor, Controller) + Feature-Toggle umgesetzt,
  Default AUS.
- `symfony/process` ergänzt; `Dockerfile` um ffmpeg/yt-dlp erweitert; nginx-Timeout angepasst.
- Frontend-Page + `postBlob` + bedingte Nav/Route.
- PHPUnit (Use-Case + Controller enabled/disabled) grün, PHPStan L8, FE-Build grün.
- OpenAPI ergänzt; `decisions.md` (Sync-MVP + Rechtsgrenze) + `lessons.md` (yt-dlp-Verfall) +
  `CHANGELOG` gepflegt.
- GitHub: WorkPackage-Issue (Label `work-package`) + PR `Closes #<n>`, CI grün.
