# Changelog

## [Unreleased]

### Sprint 2 – Core E2E (Spotify → Wobie via ESP32)

#### Neu
- **Dedizierter Default-Device-Endpunkt (#9, D-009)** – `PUT /api/v1/profiles/{id}/default-device`
  (Body `device_id`, optional `device_name`) und `DELETE …/default-device`. Neuer UseCase
  `SetDefaultDevice`, entkoppelt von der Device-Governance (`AssignDevice`). Neue Spalte
  `family_profile.default_device_name` (Migration `Version20260601090000`) persistiert den
  Anzeigenamen; `FamilyProfileController` liefert ihn jetzt aus (vorher hardcoded `null`).
- **Frontend: Standardlautsprecher setzen** – Im Profil-Tab „Lautsprecher" können erkannte
  Spotify-Geräte direkt als Standard gesetzt/entfernt werden (`useSetDefaultDevice`).
- **Stale-Device-Re-Resolve (R2)** – `StartPlayback` löst eine veraltete (ephemere) Spotify-
  `device_id` bei `SpotifyNoDeviceException` einmalig über den gespeicherten Gerätenamen neu auf,
  aktualisiert die ID und wiederholt die Wiedergabe.
- **#8-Härtung** – `ExchangeSpotifyCode` schreibt `spotify_connected` (ActivityLog) und ruft
  `markValidated()` (Display-Name direkt nach Consent). `SpotifyTokenManager` schreibt
  `spotify_token_refreshed` bei jedem Refresh.
- **Backend-Tests** – Neue Unit-Tests für `SetDefaultDevice`, `StartPlayback` (inkl. Stale-Re-Resolve)
  und `ProcessScan` (Outcome-Mapping success/unknown_card/no_device/token_invalid).

#### Behoben
- **Firmware-Outcome-Case-Bug (#10, D-010, Showstopper)** – Die ESP32-Firmware prüfte
  `outcome=="SUCCESS"`/`"DEBOUNCED"` (uppercase), das Backend liefert lowercase. Folge:
  erfolgreiches Playback wurde am Reader als Fehler (4 Blinks) signalisiert. `signalResult()`
  vergleicht jetzt `success`/`debounced`.
- **`spotify_user_display_name`** – `FamilyProfileController` gab die Spotify-User-ID statt des
  Display-Namens zurück; nutzt jetzt `getSpotifyDisplayName()` (Fallback User-ID).
- **Fehlende `SpotifyProfileNotFoundException`** – Die in `DisconnectSpotify` referenzierte Klasse
  existierte nicht (potenzieller Fatal Error / PHPStan-Finding). Ergänzt und im `ExceptionSubscriber`
  auf HTTP 404 gemappt.
- **`firmware/secrets.h.example`** – Beispiel-IP von `192.168.1.143` auf die Pi-IP `192.168.1.91` korrigiert.

## [v0.1.0] – 2026-06-01 (Foundation)

> Erster getaggter Release. Backend + Frontend laufen auf dem Pi, ESP32-Reader-Firmware
> vorhanden, Governance-Prozess etabliert, automatisiertes Pull-Deploy aktiv.

### Neu
- **Tag-getriggertes Pi-Auto-Deploy (Pull-basiert, Decision D-008)** – Der Pi ist ein
  read-only git-Clone (Deploy-Key) und pollt per `systemd`-Timer (alle 2 Min) auf neue
  `v*`-Tags. `deploy/pi-deploy.sh` ist idempotent (fetch tags → `pg_dump`-Backup →
  checkout neuester Tag → conditional build/composer → `up -d` → migrate → Healthcheck),
  `deploy/pi-backup.sh` sichert vor jeder Migration mit Rotation (`backups/`, `KEEP=7`).
  Units in `deploy/systemd/`, Runbook in `deploy/README.md`.
- **`restart: unless-stopped`** für `app`/`nginx`/`db` (Auto-Start nach Pi-Reboot).
- **Branch Protection auf `main`** – PR-Pflicht, 5 required CI-Checks (Backend 8.4/8.5,
  Frontend, Trivy, oasdiff), lineare History, kein Force-Push.
- **CI-Härtung** – Node24-Opt-in (`FORCE_JAVASCRIPT_ACTIONS_TO_NODE24`) vor der
  Node20-Abkündigung (2026-06-16).
- **ESP32-RFID-Reader-Firmware (`firmware/spotfam_reader/`)** – Arduino-Sketch für ESP32 + MFRC522: liest Karten, sendet `POST /api/v1/readers/scan` ans Backend, zwei Taster lösen `/next` und `/previous` aus. Geheimnisse (WLAN, API-Key, Backend-URL) liegen in einer git-ignorierten `secrets.h` (Vorlage: `secrets.h.example`); Pinout/Verhalten in `config.h`. UID-Format: Uppercase-Hex ohne Trenner. Toolchain: arduino-cli + esp32:esp32@3.3.8.
- **Reader-Steuer-Endpunkte `POST /api/v1/readers/next` und `/previous`** – Wirken auf das Profil der aktuellen Wiedergabe-Session. Neue `PlaybackSessionStoreInterface` + `CachePlaybackSessionStore` (cache.app, TTL 6 h) merkt sich beim erfolgreichen Scan das zuletzt abspielende Profil pro Reader (Fallback global). Neuer UseCase `ProcessReaderControl`, neuer Outcome `no_session`.
- **`READER_API_KEY` jetzt wirksam verdrahtet** – Über `backend/.env.local` (Dev) bzw. `docker-compose.yml` (Pi) gesetzt; die Reader-Endpunkte verlangen `X-API-Key`/`Bearer`. Zuvor wurde der Key nicht an den App-Container durchgereicht (stiller Default = offen).
- **Spotify API-Restriction Handling (Nov 2024)** – Spotify hat seit November 2024 den API-Zugriff auf Podcast/Hörbuch-Inhalte und Playlist-Schreiboperationen für nicht-genehmigte Apps gesperrt. Das System kommuniziert diese Einschränkungen nun klar im UI: Playlist-Detail zeigt "Inhalt nicht über API verfügbar" mit erläuterndem Text, Playlist-Erstellen-Dialog enthält einen Hinweis auf die erforderliche Spotify Developer Extended Quota Genehmigung.
- **`playlist-read-collaborative` Scope hinzugefügt** – Neue Autorisierungs-URL enthält jetzt auch den `playlist-read-collaborative` Scope für kooperative Playlists.
- **Spotify-Verbindung trennen** – Im Tab „Spotify" des Teilnehmerprofils gibt es jetzt einen roten „Trennen"-Button mit Bestätigungsdialog. Er ruft `DELETE /api/v1/profiles/{id}/spotify/disconnect` auf und löscht das gespeicherte OAuth-Token. Danach kann die Verbindung mit frischen Berechtigungen neu autorisiert werden.
- **Backend: `DisconnectSpotify` UseCase + `DELETE /disconnect` Endpoint** – Neuer UseCase und Endpoint für das Entfernen der `SpotifyAccountLink`-Entität. Repository-Interface und -Implementierung um `delete()`-Methode erweitert.
- **Hinweis-Banner bei fehlenden Scopes** – Im Spotify-Tab erscheint ein Amber-Banner der erklärt, wann und wie das Trennen+Neuverbinden nötig ist (z. B. bei 403 auf Playlist-Funktionen).
- **Pi-Deployment (Raspberry Pi 4B, Debian 13, aarch64)** – Stack (app/nginx/db) läuft via Docker Compose v2 auf dem Pi; Frontend statisch über nginx (gleicher Origin, `/api/v1`). Runbook + Specs in `docs/pi-deployment.md`. Spotify-OAuth via SSH-Loopback-Tunnel (`127.0.0.1:8080`).
- **Governance/Prozess** – Sprints (GitHub Milestones), WorkPackages + Bugs (GitHub Issues, Templates), Projects-v2-Board (#2) als Single Source of Truth. SemVer-Versionierung (Start `v0.1.0`), Tag am Sprint-Ende → triggert Pi-Deploy. Working-Memory im Repo: `tasks/{plan-*,lessons,decisions,todo}.md`, `docs/PROJECT_MAP.md`. Standing-Regeln unter `.cursor/rules/` (planning-discipline mit Plan-/4-Lens-/Dry-Run-Pflicht, project-architecture, parallel-branch-workflow, sprint-workflow).

### Geändert
- **`show_dialog=true` bei Spotify-Autorisierung** – Der Spotify-OAuth-Consent-Dialog wird nun immer angezeigt, auch wenn der Nutzer bereits eingeloggt ist. Damit werden fehlende Scopes bei Reconnect zuverlässig erteilt.
- **`decodeAndMapErrors`: 403 differenziert gemappt** – Spotify-interne 403-Fehler werden jetzt unterschieden: "Insufficient client scope" → `SpotifyScopeMissingException` (HTTP 403), generisches "Forbidden" → `SpotifyApiException` (HTTP 422). Verhindert falsch-positive "fehlende Berechtigung"-Meldungen.
- **`getPlaylistTracks`: `fields`-Parameter entfernt, `additional_types` ergänzt** – Der `fields`-Filter verursachte bei bestimmten Playlist-Typen 403-Fehler in der Spotify API. Ersetzt durch `additional_types=track,episode`.
- **`client.ts`: 204-Antworten korrekt verarbeitet** – HTTP 204 (No Content) löst keinen JSON-Parse-Fehler mehr aus.

### Behoben
- **PlaylistList: Playlist-Namen wieder sichtbar** – Das `owner_id`-Badge (Spotify-User-ID wie `31ord36in...`) wurde vollständig aus der Playlist-Liste entfernt. Es verdeckte zuvor durch seine Breite den eigentlichen Playlist-Namen im Flex-Layout, sodass nur die kryptische ID sichtbar war.
- **Playlist-Erstellung: 403-Fehler klar kommuniziert** – Wenn das Spotify-Token der Verbindung die Scopes `playlist-modify-private` / `playlist-modify-public` nicht enthält (z. B. weil die Verbindung vor dem Hinzufügen dieser Scopes autorisiert wurde), zeigt der Erstell-Dialog jetzt eine verständliche Fehlermeldung mit Handlungsanweisung (Spotify-Verbindung neu autorisieren) statt eines generischen 500-Fehlers.
- **Backend: SpotifyApiClient mappt 403 auf SpotifyScopeMissingException** – In `SpotifyHttpApiClient::createPlaylist()` und `addTracksToPlaylist()` wird ein HTTP-403 von Spotify jetzt als `SpotifyScopeMissingException` geworfen. Dies führt zu einem sauberen HTTP 403 Problem+JSON in der API-Antwort statt eines unbehandelten 500-Fehlers.

### Profilgebundener Musikarbeitsbereich „Mini Spotify" + globale Spotify-App-Konfiguration (2026-03-18)

#### Überblick

Erweiterung des bestehenden Admin-/Governance-Systems um einen vollständig integrierten Musikarbeitsbereich pro Teilnehmerprofil. Kernstück ist der neue Tab **„Musik"** im Teilnehmerprofil, der eine dreispaltige Arbeitsfläche mit Playlist-Verwaltung, Spotify-Suche und Mini-Player bietet. Ergänzt wird dies durch eine zentrale **globale Spotify-App-Konfiguration** (trennt Client Credentials von teilnehmerbezogenen Tokens), neue Player-API-Endpunkte im Backend sowie vollständige ActivityLog-Integration für alle Musik-Aktionen.

---

#### Architektonische Entscheidung: Trennung App-Credentials vs. Benutzer-Tokens

Klare Separation zwischen:
- **`SpotifyAppConfiguration`** (systemweit, 1× pro Installation): Client ID, Client Secret (verschlüsselt), Redirect URI, Scope-Defaults, Validierungsstatus
- **`SpotifyAccountLink`** (pro Teilnehmer, bereits vorhanden): Access/Refresh-Token, Spotify User ID, Scopes, neu: `spotify_display_name`, `last_validated_at`

#### Entscheidung: Mini-Player-Platzierung

Der Mini-Player wird als **festes rechtes Panel im Musik-Tab** umgesetzt – nicht als globales Sticky-Panel. Begründung: Der Player ist kontextspezifisch für den Teilnehmer, der gerade bearbeitet wird. Ein globaler Player würde den Governance-Kontext (Profil, Gerät, Regeln) verschleiern.

---

#### Backend – Neue Entität: SpotifyAppConfiguration

- Datei: `backend/src/Module/Spotify/Domain/SpotifyAppConfiguration.php`
- Tabelle: `spotify_app_configuration` (Singleton-Semantik via `is_active`)
- Felder: `spotify_client_id`, `spotify_client_secret` (verschlüsselt via `spotify_encrypted_string`), `redirect_uri`, `scope_defaults`, `config_status` (unconfigured/configured/validated/error), `last_check_at`, `last_check_note`, `is_active`
- Methode `isComplete()`: prüft Client ID + Secret + Redirect URI
- Methode `recordCheck()`: setzt Status + Zeitstempel nach Validierungsversuch

#### Backend – Repository & Port

- `SpotifyAppConfigRepositoryInterface` (Application Port)
- `DoctrineSpotifyAppConfigRepository` (Infrastructure)
- Registriert in `services.yaml`

#### Backend – SpotifyAccountLink erweitert

- Neues Feld `spotify_display_name` (VARCHAR 255, nullable)
- Neues Feld `last_validated_at` (TIMESTAMP, nullable)
- Methode `markValidated(?string $displayName)` – setzt beide Felder in einem Aufruf
- `ValidateSpotifyConnection` UseCase jetzt `final class` statt `final readonly class` (wegen Repository-Injektion), speichert Display Name bei Validierung

#### Backend – Neue DTOs

- `SpotifyTrackDto`: id, name, uri, artists, albumName, albumCoverUrl, durationMs
- `SpotifyPlaybackStateDto`: isPlaying, progressMs, currentTrack, deviceId, deviceName, deviceType, contextUri, contextType, volumePercent
- `SpotifyPlaylistTracksDto`: items (SpotifyTrackDto[]), total, offset, limit
- `SpotifySearchResultDto` erweitert: jetzt auch `tracks` (TrackItems mit type, artists, album_cover_url etc.)

#### Backend – SpotifyApiClientInterface & SpotifyHttpApiClient erweitert

Neue Methoden:
- `getCurrentPlayback()` → `?SpotifyPlaybackStateDto` (GET /me/player)
- `pausePlayback()` (PUT /me/player/pause)
- `nextTrack()` (POST /me/player/next)
- `previousTrack()` (POST /me/player/previous)
- `getPlaylistTracks()` (GET /playlists/{id}/tracks mit fields-Parameter)
- `createPlaylist()` (POST /users/{userId}/playlists)
- `addTracksToPlaylist()` (POST /playlists/{id}/tracks)

Bugfix: Inline FQCN `\App\Module\...\SpotifyNoDeviceException` in `put()` durch Import am Dateikopf ersetzt.

#### Backend – Neue UseCases

- `GetCurrentPlayback` – Holt aktuellen Player-Status
- `PausePlayback` – Pausiert Wiedergabe
- `SkipToNext` – Nächster Titel
- `SkipToPrevious` – Vorheriger Titel
- `GetPlaylistTracks` – Tracks einer Playlist (paginiert)
- `CreateSpotifyPlaylist` – Erstellt neue Spotify-Playlist, schreibt ActivityLog
- `AddTracksToPlaylist` – Fügt Tracks hinzu, schreibt ActivityLog
- `GetSpotifyAppConfig` – Liest aktive DB-Konfig oder fällt auf env vars zurück
- `SaveSpotifyAppConfig` – Speichert Konfig, schreibt ActivityLog
- `ValidateSpotifyAppConfig` – Prüft Konfigurationsvollständigkeit, schreibt ActivityLog

#### Backend – Neuer Controller: SpotifySystemController

Endpunkte:
- `GET /api/v1/system/spotify` – Aktuelle App-Konfiguration lesen (Client Secret wird **nicht** zurückgegeben, nur `has_client_secret`)
- `PUT /api/v1/system/spotify` – Konfiguration speichern
- `POST /api/v1/system/spotify/validate` – Konfiguration validieren

#### Backend – SpotifyController erweitert

Neue Endpunkte:
- `GET  /api/v1/profiles/{id}/spotify/player` – Aktueller Wiedergabestatus
- `POST /api/v1/profiles/{id}/spotify/player/pause` – Pause
- `POST /api/v1/profiles/{id}/spotify/player/next` – Nächster Titel
- `POST /api/v1/profiles/{id}/spotify/player/previous` – Vorheriger Titel
- `GET  /api/v1/profiles/{id}/spotify/playlists/{playlistId}/tracks` – Playlist-Tracks
- `POST /api/v1/profiles/{id}/spotify/playlists/{playlistId}/tracks` – Tracks hinzufügen
- `POST /api/v1/profiles/{id}/spotify/playlists/create` – Playlist erstellen

Bugfix: Search-Endpunkt gibt jetzt korrekte 400-Response mit `error`-Schlüssel statt `playlists`.

#### Backend – ActivityLog: Neue Typen

- `TYPE_PLAYLIST_CREATED`, `TYPE_PLAYLIST_CHANGED`, `TYPE_PLAYBACK_PAUSED`
- `TYPE_PLAYBACK_NEXT`, `TYPE_PLAYBACK_PREVIOUS`, `TYPE_SEARCH_EXECUTED`

#### Backend – Migration

- Datei: `backend/migrations/Version20250318100000_spotify_music.php`
- Neue Tabelle `spotify_app_configuration` (UUID PK, verschlüsseltes Secret, Index auf `is_active`)
- Erweiterung `spotify_account_link` um `spotify_display_name`, `last_validated_at`
- Migration erfolgreich ausgeführt (8 SQL-Queries)

---

#### Frontend – API-Client: spotify.ts

- Datei: `frontend/src/api/endpoints/spotify.ts` (neu strukturiert)
- `spotifySystemApi`: getConfig, saveConfig, validate (System-Konfiguration)
- `spotifyMusicApi`: getPlaylists, getPlaylistTracks, createPlaylist, addTracks, search, getPlayer, play, pause, next, previous
- `spotifyApi` (Legacy): rückwärtskompatible Exporte für Setup-Wizard (getStatus, validate, getDevices etc.)
- Neue Typen: `SpotifyAppConfigDto`, `SpotifyTrackItem`, `SpotifyPlaybackState`, `SpotifyPlayerResponse`, `SpotifySearchResponse` (mit tracks + playlists)

#### Frontend – Neue Hooks

- `useSpotifyAppConfig` + `useSaveSpotifyAppConfig` + `useValidateSpotifyAppConfig` – System-Konfig
- `useSpotifyPlayer` – Polling (5s Intervall), `usePlaySpotify`, `usePauseSpotify`, `useNextTrack`, `usePreviousTrack`
- `useSpotifyPlaylists` + `useSpotifyPlaylistTracks` + `useCreateSpotifyPlaylist` + `useAddTracksToPlaylist`
- `useSpotifySearch` – Debounced, ab 2 Zeichen

#### Frontend – Neue Komponenten

- `components/music/MiniPlayer.tsx` – Kompakter Player mit Cover, Titel, Künstler, Fortschritt, Steuerung; Governance-Hinweise (kein Lautsprecher, Spotify nicht verbunden)
- `components/music/PlaylistList.tsx` – Sidebar-Playlist-Liste mit Suche, Erstell-Dialog, Refresh
- `components/music/PlaylistDetail.tsx` – Trackliste mit Cover, Künstler, Album, Dauer; Play-Button pro Track und für gesamte Playlist
- `components/music/SpotifySearch.tsx` – Suchpanel mit Tracks + Playlists; Inline-Play + „Zu Playlist hinzufügen"-Dialog
- `components/music/MusicTab.tsx` – Dreispaltiges Layout: Playlist-Sidebar (links), Detail/Suche (Mitte), Mini-Player + Status (rechts); Governance-Guards für fehlende Sys-Konfig oder nicht verbundenen Spotify-Account

#### Frontend – Neue Seite: SystemPage

- `pages/SystemPage.tsx` – Systemeinstellungen mit Spotify-App-Konfigurationsformular
- Zeigt: Konfigurationsstatus-Badge, Client ID, Redirect URI, Secret-Eingabe mit Sichtbarkeits-Toggle
- Warnung wenn Konfiguration aus Env-Vars gelesen wird
- Validieren-Button mit Ergebnisanzeige
- Status-Grid (Client ID ✓, Secret ✓, Redirect URI ✓, Vollständig ✓)

#### Frontend – ProfileDetailPage erweitert

- Neuer Tab **„Musik"** (Icon: Headphones) mit `MusicTab`-Komponente
- Importiert `MusicTab` aus `components/music/MusicTab`

#### Frontend – Navigation & Routes

- Neue Route `/system` → `SystemPage`
- Neuer Sidebar-Eintrag „Systemeinstellungen" mit `SlidersHorizontal`-Icon in Gruppe „System"

---

#### Offene Punkte

- Lautstärkeregelung im Mini-Player: Spotify erlaubt `PUT /me/player/volume` – sinnvoll erst mit Device-Tracking
- Hörzeit-Regeln: Player-Endpunkte blockieren noch nicht bei Tageslimit-Überschreitung – Backend-Prüfung in `StartPlayback` geplant
- Suche speichert keine History im ActivityLog (nur explizite Aktionen werden geloggt)
- `spotify_app_configuration`: Wenn sowohl DB-Konfig als auch Env-Vars vorhanden, hat DB Vorrang – Hinweis im UI vorhanden
- Tests für neue UseCases (CreateSpotifyPlaylist, AddTracksToPlaylist, GetCurrentPlayback etc.) noch ausstehend

### Professionelles Admin-Refactoring: Governance-UI, Geräteverwaltung, Aktivitätslog (2026-03-18)

#### Überblick
Vollständiges Frontend-Refactoring in Richtung einer professionellen, governance-fähigen Admin-Anwendung. Einführung von shadcn/ui + Tailwind v4 als Design-System, neuer Informationsarchitektur, Geräteverwaltung mit Discovery-Transparenz und Device-Governance, Teilnehmerverwaltung als klassische Stammdatenpflege sowie einem systemweiten Aktivitäts-Log. Im Backend: drei neue Entitäten, eine Migration, zwei neue Module (Device, ActivityLog) mit vollständigen UseCases und REST-Controllern.

---

#### Frontend – Neue Dependencies

- **tailwindcss v4** (`@tailwindcss/vite`): Vite-Plugin-basierte Integration, keine `tailwind.config.js` nötig
- **Radix UI Primitives**: Tabs, Dialog, AlertDialog, Select, Tooltip, DropdownMenu, Label, ScrollArea, Avatar, Checkbox, Switch, Progress
- **class-variance-authority**, **clsx**, **tailwind-merge**: Klassenzusammenführung für shadcn/ui-Komponenten
- **lucide-react**: Einheitliche Icon-Bibliothek
- **zod** + **@hookform/resolvers**: Vorbereitet für validierte Formulare (react-hook-form)

#### Frontend – Design-System

- **`src/index.css`**: Vollständige CSS-Variable-Palette via `@theme {}` (Tailwind v4): Background, Foreground, Primary, Secondary, Muted, Accent, Destructive, Border, Ring, Sidebar, Success, Warning, Info. Alle Werte in OKLCH für hohe Farbtreue.
- **`src/lib/utils.ts`**: `cn()`-Utility (clsx + tailwind-merge), `formatDate()` und `formatDateRelative()` Hilfsfunktionen

#### Frontend – Neue UI-Komponenten (`src/components/ui/`)

Manuell implementierte shadcn/ui-kompatible Komponenten:
- `button.tsx`: 6 Varianten (default, destructive, outline, secondary, ghost, link), 4 Größen
- `badge.tsx`: 8 Varianten inkl. success, warning, info, muted
- `card.tsx`: Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter
- `input.tsx`, `textarea.tsx`, `label.tsx`: Formular-Grundelemente
- `separator.tsx`: Horizontal/vertikal
- `tabs.tsx`: Radix-basiert, Underline-Style für Admin-Look
- `dialog.tsx`, `alert-dialog.tsx`: Modal-Dialoge mit Overlay und Keyboard-Handling
- `table.tsx`: Professionelle Datentabelle
- `select.tsx`: Vollständiges Radix Select mit Scroll-Buttons
- `tooltip.tsx`: Radix Tooltip mit Provider
- `avatar.tsx`: Radix Avatar mit Fallback
- `skeleton.tsx`: Lade-Platzhalter
- `switch.tsx`, `scroll-area.tsx`, `progress.tsx`: Radix-basierte Komponenten

#### Frontend – Layout & Navigation

- **`Layout.tsx`** vollständig neu: Professionelle Sidebar (56px Breite) mit gruppierten Navigationsbereichen (Verwaltung, Betrieb, System), aktiver Route-Hervorhebung, App-Header mit Icon und Versionsnummer, ScrollArea für lange Navigationen

#### Frontend – Neue Seiten & Routen

- **`/profiles`** (`ProfilesPage.tsx`): Datensatzliste als professionelle Tabelle mit Spalten Name, Status, Spotify-Verbindung, Standardlautsprecher, Setup-Status, Letzte Aktivität. Suchfeld, Neu-Dialog, Lösch-Bestätigung per AlertDialog, Direkt-Navigation ins Detail.
- **`/profiles/:id`** (`ProfileDetailPage.tsx`): Detailformular mit 6 Tabs:
  - *Allgemein*: Name/Beschreibung bearbeiten, Metadaten, Dirty-Check
  - *Spotify*: Verbindungsstatus, Validierung, OAuth-Redirect
  - *Lautsprecher*: Standardgerät anzeigen, Live-Geräteliste über Spotify-API laden
  - *RFID-Karten*: Kartentabelle
  - *Hörzeit*: MVP-Platzhalter
  - *Aktivität*: Teilnehmer-spezifischer Aktivitätsverlauf aus `activity_log`
- **`/devices`** (`DevicesPage.tsx`): Zentrale Geräteverwaltung mit:
  - Discovery-Panel (Ergebnisanzeige, Trigger-Button, transparenter Hinweis auf Spotify-API-Beschränkung)
  - Gerätedatensatzliste mit Verfügbarkeits- und Zuweisungs-Badges
  - Gerätedetail-Panel (Split-View) mit Tabs Übersicht/Erkennung/Governance
  - Governance-Dialog mit Zuweisungsformular und Konflikt-Bestätigung (AlertDialog)
- **`/activity`** (`ActivityPage.tsx`): Systemweiter Aktivitätsverlauf mit Severity-Filter, Zeitstempel, Profil-Tag und Ereignistyp-Label
- **`/`** (`DashboardPage.tsx`): Überarbeitetes Dashboard mit Stat-Cards (Teilnehmer, Geräte, Aktivitäten, RFID), Teilnehmer-Status-Übersicht, letzte Aktivitäten, letzter Discovery-Run

#### Frontend – Neue Hooks & API-Endpunkte

- **`src/api/endpoints/devices.ts`**: Typen und API-Funktionen für SpotifyDevice, DeviceDiscoveryRun, AssignDevice
- **`src/api/endpoints/activity.ts`**: Typen und API-Funktionen für ActivityLog
- **`src/hooks/useDevices.ts`**: `useDevices`, `useDevice`, `useLatestDiscoveryRun`, `useDiscoveryRuns`, `useTriggerDiscovery`, `useAssignDevice`
- **`src/hooks/useActivity.ts`**: `useActivity` mit Profil- und Severity-Filter
- **`src/api/endpoints/profiles.ts`**: Erweitert um `status`, `spotify_status`, `spotify_user_display_name`, `default_device_name`, `setup_complete`, `setup_percent`, `last_activity_at`

---

#### Backend – Neue Migration

- **`Version20250318000000_device_governance.php`**: 19 SQL-Statements
  - `family_profile`: Neues Feld `status VARCHAR(32) DEFAULT 'active'`
  - `spotify_device`: Neue Tabelle mit Governance-Feldern (`assignment_mode`, `assignment_updated_at`, `assignment_note`, `discovery_status`, `last_discovery_run_id`, etc.)
  - `device_discovery_run`: Discovery-Protokoll-Tabelle mit `raw_payload JSONB`
  - `activity_log`: Aktivitäts-Log mit `details JSONB`, GIN-Indizes auf JSONB-Felder
  - Alle FK-Constraints mit `NOT VALID` (kein Full-Table-Lock)
  - Sinnvolle Indizes: BTREE auf FKs, Sortierfelder; GIN auf JSONB; Partial Indexes vorbereitet

#### Backend – Neues Modul: Device

- **`SpotifyDevice` Entity**: Persistiertes Geräteobjekt mit vollständiger Governance-Logik (`assign()`, `markSeen()`, `hasConflict()`, `isAssignedTo()`). Zustandskonstanten für `assignment_mode` (unassigned/assigned/reserved/locked/shared) und `discovery_status`.
- **`DeviceDiscoveryRun` Entity**: Discovery-Lauf-Protokoll mit `finish()`-Methode
- **`SpotifyDeviceRepositoryInterface`** + `DoctrineSpotifyDeviceRepository`
- **`DeviceDiscoveryRunRepositoryInterface`** + `DoctrineDeviceDiscoveryRunRepository`
- **`RunDeviceDiscovery` UseCase**: Iteriert alle/ein Profil(e), ruft Spotify-API ab, persistiert neue und aktualisierte Geräte, schreibt ActivityLog-Eintrag, schließt Discovery-Run ab
- **`AssignDevice` UseCase**: Prüft Konflikte (`hasConflict()`), verlangt `force=true` bei Übernahme-Konflikt (`409 Conflict`), schreibt ActivityLog
- **`DeviceController`**: REST-Endpunkte `GET /devices`, `GET /devices/{id}`, `PUT /devices/{id}/assign`, `POST /devices/discover`, `GET /devices/discovery-runs/latest`, `GET /devices/discovery-runs`

#### Backend – Neues Modul: ActivityLog

- **`ActivityLog` Entity**: Vollständige Typen- und Severity-Konstanten. Profil- und Entity-Referenz, JSONB-Details
- **`ActivityLogRepositoryInterface`** + `DoctrineActivityLogRepository`: Paginiert, nach Profil und Severity filterbar
- **`ActivityLogController`**: `GET /api/v1/activity-log` mit `profile_id`, `severity`, `limit`, `offset` Query-Parametern

---

#### Offene Punkte (nächste Iteration)

- **FamilyProfile-Controller**: `status`-Feld und erweiterte DTO-Felder (`spotify_status`, `default_device_name`, etc.) sind im Frontend vorbereitet, aber das Backend gibt noch die alten Felder zurück. Der `FamilyProfileController` muss entsprechend erweitert werden.
- **ActivityLog-Integration im bestehenden Code**: `ProcessScan`-UseCase sollte ActivityLog-Einträge schreiben; `ValidateSpotifyConnection` ebenfalls.
- **Hörzeit-Regeln**: Tab existiert im Frontend, Backend-Implementierung fehlt noch.
- **Tests**: Neue UseCases (RunDeviceDiscovery, AssignDevice) brauchen Unit-Tests.
- **Setup-Wizard**: Vorhandener Wizard noch ohne Tailwind-Styling – kann in nächster Iteration vereinheitlicht werden.

### Fixed – Docker & Infrastruktur (2026-03-15)
- **Makefile**: `docker compose` (Plugin-Syntax) auf `docker-compose` (Standalone v1.29.2) umgestellt; Variable `COMPOSE` eingeführt für einfaches Umschalten; neue Targets `logs`, `ps`, `cc` (Cache Clear) hinzugefügt; alle `exec`-Befehle nutzen jetzt `$(COMPOSE) exec -T`; Hilfemeldung erweitert.
- **composer.json**: `symfony/flex` und `symfony/runtime` in `allow-plugins` eingetragen – verhinderte `composer install` im Docker-Build ohne interaktive Eingabe.
- **Dockerfile**: `libsodium-dev` und `sodium` PHP-Extension hinzugefügt (Pflicht für Token-Verschlüsselung); `cache:clear` aus Build entfernt (DB-Abhängigkeit im Build-Kontext); Build-Ablauf auf `composer install --no-scripts --no-autoloader` + `dump-autoload --optimize` vereinfacht.
- **docker-compose.yml**: PostgreSQL-Port von `5432` auf `5433` gemappt (Konflikt mit laufendem `averiq_postgres`-Container auf 5432).
- **TokenEncryptionService**: `SODIUM_CRYPTO_SECRETBOX_NPUBBYTES` als Klassen-Konstante entfernt – Symfony's Reflection-Mechanismus konnte die Extension-Konstante beim DI-Container-Compile nicht auflösen; durch Integer-Literal `24` ersetzt.
- **services.yaml**: `StepHandlerInterface`-Eintrag mit `abstract: true` entfernt – Interfaces dürfen nicht als getaggte Services registriert werden.
- **SpotifyHttpApiClient**: Syntax-Fehler in `decodeAndMapErrors()`-Signatur behoben (fehlendes `$` vor `_context`-Parameter).
- **symfony/uid**: Als Abhängigkeit ergänzt (`symfony/uid ^7.4`) – `UuidFactory` war nicht auflösbar.
- **Ergebnis**: System startet vollständig (`db`, `app`, `nginx`); 3 Migrationen erfolgreich ausgeführt; `GET /api/v1/profiles` antwortet mit HTTP 200.


### Architektur-Härtung: Vollständiger Refactoring-Durchgang (2026-03-15)

#### Überblick
Einmalige, vollständige Architektur-Härtung des gesamten Backends und Frontends. Alle kritischen und hohen Findings aus dem vorherigen Architektur-Review wurden in einem einzigen Refactoring-Durchgang umgesetzt. Der MVP-Scope (RFID-Scan, Spotify-Playback, Setup-Wizard, Profile-CRUD) bleibt funktional unverändert.

#### Backend — Repository-Interfaces (Ports & Adapters)

**8 neue Repository-Interfaces** unter `Application/Port/` angelegt (bisher injizierte jeder UseCase direkt die konkrete Doctrine-Klasse):
- `FamilyProfile/Application/Port/FamilyProfileRepositoryInterface`
- `Rfid/Application/Port/RfidCardRepositoryInterface`
- `Rfid/Application/Port/CardPlaylistBindingRepositoryInterface`
- `Spotify/Application/Port/SpotifyAccountLinkRepositoryInterface`
- `Spotify/Application/Port/SpotifyPlaylistReferenceRepositoryInterface`
- `SetupWizard/Application/Port/ProfileSetupSessionRepositoryInterface`
- `Scan/Application/Port/ReaderDeviceRepositoryInterface`
- `Scan/Application/Port/ScanEventRepositoryInterface` (verschoben aus `Application/`)

Alle 8 Doctrine-Repositories implementieren das jeweilige Interface nun via `implements`. `services.yaml` wurde um die Interface→Service-Bindings ergänzt.

**21 Use Cases** wurden auf die neuen Interfaces umgestellt (Infra-Imports entfernt, nur noch Port-Interfaces im Application-Layer sichtbar):
- FamilyProfile: `Create`, `Update`, `Delete`, `Get`, `List`
- Rfid: `Create`, `Get`, `Update`, `Delete`, `List`, `GetCardPlaylistBinding`, `SetCardPlaylistBinding`
- Spotify: `ExchangeSpotifyCode`, `GetSpotifyStatus`, `CreatePlaylistReference`, `ListPlaylistReferences`, `StartPlayback`
- SetupWizard: `GetWizardState`, `GetCompleteness`, `SetCurrentStep`, `SubmitStep`
- Scan: `ListScanEvents`, `ProcessScan`

`SpotifyTokenManager` (Infrastructure) verwendet jetzt `SpotifyAccountLinkRepositoryInterface` statt der konkreten Repository-Klasse.

#### Backend — Cross-Modul-Entkopplung (`ProcessScan`)

`ProcessScan` injizierte direkt drei Repositories aus fremden Modulen (Rfid, Spotify). Gelöst durch das Ports & Adapters Pattern:
- **Neues Port-Interface** `Scan/Application/Port/ScanCardResolverInterface` mit `resolveCard(string $cardUid): ?ScanCardContext`
- **Neues Value Object** `Scan/Domain/ScanCardContext` (cardId, profileId, playlistUri)
- **Neuer Adapter** `Rfid/Infrastructure/Scan/RfidScanCardResolver` implementiert das Interface — kennt Rfid- und Spotify-Repositories, liegt im Rfid-Modul

`ProcessScan` injiziert nun ausschließlich `ScanCardResolverInterface` + `ReaderDeviceRepositoryInterface` + `ScanEventRepositoryInterface` — kein Cross-Modul-Wissen mehr.

#### Backend — Reader-Lookup aus Controller in UseCase verschoben

`ScanController` injizierte bisher `DoctrineReaderDeviceRepository` direkt. Der Reader-Lookup (readerId → readerDeviceId) wurde in `ProcessScan` verlagert. Der Controller übergibt nur noch den rohen `reader_id`-String aus dem Request-Body. `ScanController` ist damit deutlich dünner.

#### Backend — Privater `logScan()`-Helper in `ProcessScan`

Sieben identische `scanEventRepository->append()`-Aufrufe wurden in einem privaten `logScan()`-Helper konsolidiert. Sauberer, wartbarer Code.

#### Backend — Domain-Exception-Hierarchie bereinigt

Bisher erbten Spotify-Exceptions von `HttpException` (Application/Shared) — Domain-Layer hatte HTTP-Status-Code-Wissen:
- **Neue abstrakte Basis** `Spotify/Domain/Exception/SpotifyDomainException extends \RuntimeException` — kein HTTP-Bezug
- **5 konkrete Domain-Exceptions** bereinigt: `SpotifyNotConnectedException`, `SpotifyTokenInvalidException`, `SpotifyNoDeviceException`, `SpotifyScopeMissingException`, `SpotifyOAuthStateException` — alle `extends SpotifyDomainException`
- **`StepValidationException`** bereinigt: `extends \DomainException` statt `HttpException`
- **`SpotifyException`** (alte Basis) als `@deprecated` Alias erhalten für Rückwärtskompatibilität
- **`ExceptionSubscriber`** erweitert: vollständiges Mapping Domain-Exception FQCN → HTTP-Status-Code (404, 401, 422, 403, 400)
- **`ProblemJsonResponse`** um `fromDomainException()`-Methode erweitert; interne `build()`-Methode für beide Pfade

#### Backend — `SubmitStep`: Strategy-Pattern

Der große `switch`-Block in `SubmitStep` wurde durch das Strategy-Pattern ersetzt:
- **Neues Interface** `SetupWizard/Application/StepHandler/StepHandlerInterface` mit `supports(string $stepKey): bool` + `handle(string $profileId, string $stepKey, array $payload): void`
- **5 konkrete Handler**: `ProfileStepHandler`, `SpotifyValidateStepHandler`, `DefaultSpeakerStepHandler`, `PlaybackTestStepHandler`, `PassthroughStepHandler`
- `SubmitStep` injiziert `iterable $handlers` via DI-Tagged-Iterator `setup_wizard.step_handler`
- `services.yaml` mit DI-Tagging für alle Handler ergänzt

#### Backend — `FamilyProfileRequest` (DTO-Konsolidierung)

`FamilyProfileCreateRequest` und `FamilyProfileUpdateRequest` waren identisch. Zusammengeführt zu `FamilyProfileRequest`. `FamilyProfileController` angepasst. Beide alten Dateien gelöscht.

#### Backend — Fehlende Getter ergänzt

- `ReaderDevice`: `getName(): ?string` ergänzt
- `ScanEvent`: `getReaderDeviceId(): ?string`, `getRfidCardId(): ?string`, `getFamilyProfileId(): ?string` ergänzt

#### Backend — `routes/api.yaml` bereinigt

Doppelte `api_v1`-Key-Definitionen und überflüssige Controller-Einträge entfernt. Alle Routen laufen über Attribute-Routing und `routes.yaml`.

#### Frontend — TypeScript-Typfehler behoben

`CardPlaylistBindingDto` in `api/endpoints/rfid.ts` war syntaktisch ungültig (`interface ... | null`). Korrigiert zu `type CardPlaylistBindingDto = { ... } | null`.

#### Frontend — Tote Seite gelöscht

`pages/ProfileSetupPage.tsx` war nicht geroutet und wurde gelöscht.

#### Frontend — `STEP_LABELS` zentralisiert

Neue Datei `features/setup-wizard/stepLabels.ts` als single source of truth für Step-Labels. `WizardStepper.tsx` und `StepSummary.tsx` importieren daraus (vorher je eigene lokale Konstante).

#### Frontend — `useRfidCards`-Hook extrahiert

Neue Datei `hooks/useRfidCards.ts` mit stabilen Query-Keys (`rfidCardKeys`) und allen RFID-Mutations. `CardsPage.tsx` nutzt diese Hooks statt lokaler Mutations/Queries.

#### Frontend — Query-Key-Konsolidierung

`ScanLogsPage` nutzt `useProfiles()` aus `hooks/useProfiles.ts` statt eigenem `useQuery(['profiles'], ...)`. `SetupWizardPage` nutzt `useProfile(profileId)` statt eigenem `useQuery`.

#### Frontend — `handleValidateSpotify` als Mutation

`SetupWizardPage`: `handleValidateSpotify` war ein direkter Promise-Aufruf (`.then().catch()`). Umgebaut zu `useMutation` mit `mutationFn: () => spotifyApi.validate(profileId!)` und `onSuccess`-Callback.



### Hinzugefügt

- **Projekt-Scaffold (MVP)**
  - Root: `docker-compose.yml` (app, nginx, PostgreSQL 15), `.env.example`, `Makefile`, `README.md`, `CHANGELOG.md`.
  - Docker: Nginx-Konfiguration, PostgreSQL-Init (uuid-ossp), PHP 8.3-FPM Dockerfile im Backend.
  - Backend (Symfony 7, PHP 8.3, Doctrine ORM, PostgreSQL):
    - Modulstruktur unter `src/Module/`: Admin, FamilyProfile, Spotify, Rfid, Scan, SetupWizard, Shared.
    - Shared: `HttpException`, `NotFoundException`, `ProblemJsonResponse`, `ExceptionSubscriber` für RFC 7807.
    - Admin: Entity `AdminUser`, `DoctrineAdminUserRepository`.
    - FamilyProfile: Entity `FamilyProfile`, CRUD Use Cases, `FamilyProfileController` (GET list, GET one, POST, PUT, DELETE), DTOs für Create/Update.
    - Spotify: Entity `SpotifyAccountLink`, `GetSpotifyStatus`, `SpotifyController` (GET status).
    - Rfid: Entity `RfidCard`, `ListRfidCardsByProfile`, `RfidCardController` (GET list).
    - Scan: Entity `ScanEvent`, `ProcessScan`, `ScanController` (POST /readers/scan), Scan-Event-Logging (outcome unknown_card im MVP).
    - SetupWizard: Entities `ProfileSetupSession`, `ProfileSetupStepStatus`, `GetWizardState`, `SetupWizardController` (GET state).
    - Doctrine-Mappings für alle Module, erste Migration (admin_user, family_profile, spotify_account_link, rfid_card, scan_event, profile_setup_session, profile_setup_step_status).
    - REST-Routen unter `/api/v1` mit Attribute-Routing; Parameter `uuid_regex` für UUID-Requirements.
  - Frontend (React 18, TypeScript, Vite, React Router, TanStack Query):
    - App-Shell mit `Layout` (Sidebar: Dashboard, Profile, Scan-Logs); Login-Seite ohne Auth-Logik.
    - Routen: `/`, `/login`, `/profiles`, `/profiles/:profileId`, `/profiles/:profileId/edit`, `/profiles/:profileId/setup`, `/profiles/:profileId/cards`, `/scan-logs`.
    - API-Client (`api/client.ts`) und Endpoints: profiles, setup, spotify, rfid.
    - Hooks: `useProfiles`, `useProfile`, `useCreateProfile`, `useUpdateProfile`, `useDeleteProfile`.
    - Seiten: Login, Dashboard, Profiles (Liste), ProfileDetail, ProfileSetup, Cards, ScanLogs (Platzhalter-Inhalte).
  - Tests: PHPUnit-Bootstrap, `ListFamilyProfilesTest` (Unit).

### Technische Details

- Backend: Kein Auth auf API im Scaffold (firewall `api` mit PUBLIC_ACCESS); Admin-Login und JWT/Session folgen in einer späteren Phase.
- Setup-Wizard: GET `/profiles/{id}/setup` liefert 404, wenn für das Profil noch keine `ProfileSetupSession` existiert; Session-Erstellung beim ersten Öffnen oder bei Profil-Erstellung kann in der nächsten Implementierung ergänzt werden.
- Scan-Endpoint: POST `/api/v1/readers/scan` mit `reader_id`, `card_uid`; speichert Event mit outcome `unknown_card` und gibt JSON zurück.

### Spotify-Integration (MVP Backend)

- **OAuth:** Authorization Code Flow; `GetSpotifyAuthorizationUrl` erzeugt URL mit State; State wird im Cache (TTL 600s) gespeichert und enthält die `profileId`. Callback GET `/api/v1/spotify/callback` tauscht Code gegen Tokens, speichert/aktualisiert `SpotifyAccountLink`, leitet auf Frontend weiter.
- **Token-Speicherung:** Access- und Refresh-Token werden mit symmetrischer Verschlüsselung (XChaCha20-Poly1305, Key aus APP_SECRET abgeleitet) in der Datenbank gespeichert. Doctrine Custom Type `spotify_encrypted_string` mit `TokenEncryptionService`; `EncryptedStringTypeInitializer` setzt den Encryptor pro Request.
- **Token-Refresh:** `SpotifyTokenManager::getValidLinkForProfile()` liefert einen gültigen Link; bei Ablauf (oder < 5 Min Rest) wird automatisch refresht und persistiert. Refresh-Token-Rotation wird unterstützt (Spotify kann neuen Refresh-Token zurückgeben).
- **Scopes:** Beim Auth-Request werden die benötigten Scopes gesendet; bei der Token-Antwort wird `scope` in `spotify_account_link.scopes` gespeichert. Keine automatische Scope-Prüfung im MVP; bei 403 von Spotify wird `SpotifyScopeMissingException` geworfen.
- **Fehler-Mapping:** `SpotifyTokenInvalidException` (401), `SpotifyNoDeviceException` (404/422), `SpotifyScopeMissingException` (403), `SpotifyNotConnectedException` (404), `SpotifyOAuthStateException` (400). Alle erben von `HttpException` und werden vom `ExceptionSubscriber` als Problem+JSON (RFC 7807) zurückgegeben.
- **API-Endpoints:** GET `authorization-url`, GET `status`, POST `validate`, GET `playlists`, GET `search?q=`, GET `devices`, POST `playback/start` (Body: `context_uri`, optional `device_id`). Playback verwendet bei fehlendem `device_id` das Standardgerät des Profils (`family_profile.default_spotify_device_id`).
- **Neue Dateien:** Domain-Exceptions, DTOs, Ports (`SpotifyApiClientInterface`, `TokenEncryptionInterface`, `SpotifyTokenManagerInterface`, `OAuthStateManagerInterface`), `SpotifyHttpApiClient`, `SpotifyTokenManager`, `SpotifyOAuthStateManager`, Use Cases, erweiterter `SpotifyController`, `SpotifyOAuthController`; Migration für `scopes`-Spalte; Config `config/packages/spotify.yaml`; Env: `SPOTIFY_CLIENT_ID`, `SPOTIFY_CLIENT_SECRET`, `SPOTIFY_REDIRECT_URI`, `FRONTEND_URL`.
- **Tests:** Unit-Tests für `GetSpotifyAuthorizationUrl` und `SpotifyTokenManager` (Mock von StateManager/Repository/ApiClient).

### Setup-Wizard (MVP)

- **Backend:**
  - Schritt-Konstanten in `WizardSteps` (profile, spotify_connect, spotify_validate, devices, default_speaker, playback_test, playlist, rfid_bind, summary); Status `pending`, `completed`, `failed`, `requires_attention` in `ProfileSetupStepStatus`.
  - Session get-or-create: `findOrCreateSession(profileId)` legt bei erstem Aufruf Session und alle Schritt-Statuszeilen (pending) an – Wizard ist fortsetzbar.
  - `GetWizardState`: liefert aktuellen Schritt, Session-Status und alle Schritte inkl. payload; wirft nicht mehr bei fehlender Session.
  - `SubmitStep`: nimmt step_key, status, payload; bei status completed wird schrittabhängige Logik ausgeführt (Profil aktualisieren, Spotify validieren, Standardgerät setzen, Playback testen usw.). Bei Fehler: Schritt wird auf failed gesetzt, `StepValidationException` mit steps zurückgegeben (422).
  - `SetCurrentStep`: setzt current_step für Navigation (z. B. Zurück).
  - `GetCompleteness`: Prozent und pro Schritt status/payload für Anzeige.
  - APIs: GET `/profiles/{id}/setup`, PUT/POST `/profiles/{id}/setup/step`, PUT `/profiles/{id}/setup/current-step`, GET `/profiles/{id}/setup/completeness`.
- **Frontend:**
  - `SetupWizardPage`: lädt State, Geräte/Playlists/Spotify-Status nach Bedarf, rendert aktuellen Schritt; Stepper mit Klick-Navigation (nur zugelassene Schritte).
  - Schritt-Komponenten: StepProfile, StepSpotifyConnect, StepSpotifyValidate, StepDevices, StepDefaultSpeaker, StepPlaybackTest, StepPlaylist, StepRfidBind, StepSummary; jeweils Loading/Error und Weiter/Überspringen wo sinnvoll.
  - Abschluss: StepSummary zeigt Checkliste und „Setup abschließen“; bei Abschluss wird Schritt summary mit status completed abgesendet, Session-Status auf completed gesetzt.
  - API-Client: setupApi (getState, submitStep, setCurrentStep, getCompleteness), WIZARD_STEPS; spotifyApi um getAuthorizationUrl, validate, getDevices, getPlaylists, startPlayback erweitert.

### RFID-Karten-Modul und Reader-Scan-Flow (MVP)

- **Ziel:** Reader sendet Scan → Karte wird erkannt → Profil und gebundene Playlist → Standard-Lautsprecher → Spotify-Playback starten → Scan-Event loggen.
- **Backend – Domain & DB:**
  - `ScanOutcome`: Konstanten für Erfolg/Fehler (success, unknown_card, no_binding, no_device, token_invalid, playback_failed, debounced, invalid_request, unknown_reader).
  - Entities: `ReaderDevice` (reader_id, name, api_key_hash), `CardPlaylistBinding` (rfid_card_id, spotify_playlist_reference_id), `SpotifyPlaylistReference` (family_profile_id, spotify_playlist_id, name, owner_id); `ScanEvent` um reader_device_id, rfid_card_id, family_profile_id erweitert.
  - Migration: Tabellen reader_device, spotify_playlist_reference, card_playlist_binding; Scan-Event-Spalten ergänzt.
- **Backend – Use Cases & API:**
  - Rfid: CreateRfidCard, GetRfidCard, UpdateRfidCard, DeleteRfidCard (entfernt Bindung), GetCardPlaylistBinding, SetCardPlaylistBinding.
  - Spotify: ListPlaylistReferences, CreatePlaylistReference; SpotifyPlaylistReference um getOwnerId() ergänzt.
  - Scan: ProcessScan mit Debounce (5 s), Kartenauflösung, Bindung/Playlist-Referenz, StartPlayback, Logging aller Outcomes; ListScanEvents (Limit/Offset, optional profileId).
  - Repositories: DoctrineRfidCardRepository (findByCardUid), DoctrineCardPlaylistBindingRepository, DoctrineSpotifyPlaylistReferenceRepository (findByIdAndProfile), DoctrineReaderDeviceRepository, ScanEventRepository (append mit neuen Parametern, findRecentScan, findRecent).
- **Backend – Controller:**
  - RfidCardController: GET list, GET one, POST create, PUT update, DELETE, GET/PUT binding.
  - SpotifyController: GET/POST playlist-references.
  - ScanController: POST /readers/scan (Body: reader_id optional, card_uid pflicht); GET /readers/scan-events (profile_id, limit, offset). Reader-Auth (MVP): wenn READER_API_KEY gesetzt, Header X-API-Key oder Authorization: Bearer erforderlich.
- **Konfiguration:** services.yaml: ScanController mit $readerApiKey (env default:reader_api_key:READER_API_KEY); Parameter reader_api_key: ''. .env.example um READER_API_KEY ergänzt.
- **Frontend:**
  - API: rfidApi (list, get, create, update, delete, getBinding, setBinding), spotifyApi (listPlaylistReferences, createPlaylistReference), scanApi (listEvents).
  - CardsPage: Liste mit Anlegen/Bearbeiten/Löschen, Playlist-Bindung (Modal mit Auswahl der Playlist-Referenzen); TanStack Query/Mutations.
  - ScanLogsPage: Tabelle mit Scan-Events (Zeit, Card UID, Outcome), Filter nach Profil, Pagination.
- **Request-Format Reader:** POST /api/v1/readers/scan JSON: { "reader_id": "optional", "card_uid": "required" }. Debounce: gleiche card_uid innerhalb 5 s → Outcome debounced, wird trotzdem geloggt.
