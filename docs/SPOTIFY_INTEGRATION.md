# Spotify-Integration (Backend MVP)

## Übersicht

Alle Spotify-Anfragen laufen über den Port `SpotifyApiClientInterface`; keine direkten HTTP-Aufrufe in Controllern. Token-Handling, OAuth-State und Fehler sind gekapselt.

## App-Credentials: Quelle & Präzedenz (D-011)

- **Single Source of Truth:** die in den System-Einstellungen gespeicherte DB-Konfiguration
  (`SpotifyAppConfiguration`, Endpunkte `/api/v1/system/spotify`). Das Client Secret liegt verschlüsselt
  (`spotify_encrypted_string`) in der DB.
- **Auflösung zur Laufzeit:** `SpotifyCredentialsProvider` (`SpotifyCredentialsProviderInterface`) liefert pro
  Request die effektiven Credentials. Reihenfolge:
  1. **DB-Config, wenn vollständig** (Client ID **und** Secret **und** Redirect URI gesetzt) → `source = db`.
  2. **sonst env-Fallback** (`SPOTIFY_CLIENT_ID`/`SECRET`/`SPOTIFY_REDIRECT_URI`) → `source = env`.
  Es wird **ganzheitlich** umgeschaltet (kein Vermischen von DB- und env-Feldern), damit nie eine neue
  Client ID mit einem alten Secret kombiniert wird. Kein Prozess-Cache → ein UI-Save greift ohne Neustart.
- **Konsumenten:** `SpotifyHttpApiClient` (Token-Tausch/Refresh), `GetSpotifyAuthorizationUrl` (Consent-URL),
  `SpotifyOAuthController` (Callback-Redirect) ziehen Client-ID/Secret/Redirect ausschließlich aus dem Provider.
- **Scopes** bleiben code-seitig (kanonische Liste in `SpotifyCredentialsProvider::DEFAULT_SCOPES`); das UI-Feld
  `scope_defaults` wird für den OAuth-Flow bewusst nicht verwendet.
- **Validierung:** `POST /api/v1/system/spotify/validate` prüft die effektiven Credentials **real** gegen Spotify
  (client_credentials-Grant, `checkClientCredentials()`), nicht nur deren Vorhandensein.
- **Hinweis APP_SECRET:** Das DB-Secret wird mit aus `APP_SECRET` abgeleitetem Schlüssel ver-/entschlüsselt.
  Ein Wechsel von `APP_SECRET` macht ein gespeichertes Secret unlesbar → in dem Fall neu eintragen.

## Token-Speicherung und Verschlüsselung

- **Speicherort:** Tabelle `spotify_account_link`; Spalten `access_token` und `refresh_token` mit Doctrine-Type `spotify_encrypted_string`.
- **Verschlüsselung:** `TokenEncryptionService` (XChaCha20-Poly1305, Sodium). Schlüssel wird aus `APP_SECRET` abgeleitet (`sodium_crypto_generichash(..., SODIUM_CRYPTO_SECRETBOX_KEYBYTES)`). Für Produktion kann ein eigener Key über eine eigene Env-Variable eingeführt werden.
- **Ablauf:** Beim Schreiben in die DB wandelt der Custom Type den Klartext in Chiffretext um; beim Lesen wird entschlüsselt. Die Entity arbeitet im Speicher immer mit Klartext.

## Scopes

- Beim Aufruf von `GetSpotifyAuthorizationUrl` werden die benötigten Scopes fest mitgesendet (user-read-private, user-read-email, playlist-*, user-modify-playback-state, user-read-playback-state).
- Die von Spotify zurückgegebene `scope`-Zeichenkette wird in `spotify_account_link.scopes` gespeichert.
- Eine explizite Prüfung der Scopes vor jedem API-Call erfolgt im MVP nicht. Bei 403-Antworten von Spotify wird `SpotifyScopeMissingException` geworfen und als Problem+JSON (403) ans Frontend zurückgegeben.

## Callback- und State-Validierung

- **State:** Beim Start des OAuth-Flows erzeugt `SpotifyOAuthStateManager::createState(profileId)` einen Zufalls-String und speichert die `profileId` im Cache unter `spotify_oauth_state_{state}` mit TTL 600 Sekunden.
- **Callback:** GET `/api/v1/spotify/callback?code=...&state=...`. Der Controller ruft `OAuthStateManager::consumeState(state)` auf: liefert die zugehörige `profileId` und löscht den State (einmalige Verwendung). Bei fehlendem oder abgelaufenem State wird `SpotifyOAuthStateException` geworfen.
- **Profil-Kontext:** Die Zuordnung zum Profil erfolgt ausschließlich über den State; die Redirect-URI ist für alle Profile identisch (Backend-URL).
- **Nach erfolgreichem Connect (ab Sprint 2):** `ExchangeSpotifyCode` ruft `markValidated($displayName)` (speichert `spotify_display_name` + `last_validated_at`) und schreibt einen ActivityLog-Eintrag `spotify_connected`. Ein separater manueller `validate`-Call ist für die Statusanzeige nicht mehr nötig. Der Token-Refresh schreibt `spotify_token_refreshed` (Severity `debug`).

## Fehler an das Frontend

- Domain-Exceptions (`SpotifyException` und Unterklassen, `SpotifyNotConnectedException` als `NotFoundException`) erben von `HttpException`.
- `ExceptionSubscriber` fängt alle `HttpException` und baut eine RFC-7807-Problem+JSON-Antwort (type, title, status, detail, instance). Keine rohen Spotify-Fehlermeldungen; Texte sind intern formuliert.
- Typische Mappings: 401 → `SpotifyTokenInvalidException`, 403 → `SpotifyScopeMissingException`, 404/422 „device“ → `SpotifyNoDeviceException`, kein Link → `SpotifyNotConnectedException`.

## Test-Playback / Kein Gerät

- **Playback starten:** Use Case `StartPlayback`. Wenn kein `device_id` übergeben wird, wird `family_profile.default_spotify_device_id` verwendet. Ist keines gesetzt, wird `SpotifyNoDeviceException` geworfen (422).
- **Geräte laden:** GET `/profiles/{id}/spotify/devices` liefert die aktuell von Spotify gemeldete Geräteliste.
- **Standardgerät setzen (ab Sprint 2, D-009):** Dedizierter Endpunkt, entkoppelt von der Device-Governance (`AssignDevice`):
  - `PUT /api/v1/profiles/{id}/default-device` mit Body `{ "device_id": "<spotify_device_id>", "device_name": "<anzeigename, optional>" }`
  - `DELETE /api/v1/profiles/{id}/default-device` entfernt das Standardgerät.
  - Persistiert `default_spotify_device_id` **und** `default_device_name` (Migration `Version20260601090000`). Der Setup-Wizard-Schritt `default_speaker` setzt weiterhin nur die ID.
- **Stale-Device-Re-Resolve (ab Sprint 2, R2):** Spotify-`device_id`s sind ephemer (ändern sich z. B. nach Reboot des Connect-Geräts). Schlägt `StartPlayback` mit `SpotifyNoDeviceException` fehl, versucht der UseCase **einmal**, anhand des gespeicherten `default_device_name` ein aktuell verfügbares Gerät gleichen Namens zu finden, aktualisiert die gespeicherte ID und wiederholt. Findet sich kein Namens-Match, wird der Fehler durchgereicht.

## Token-Refresh

- `SpotifyTokenManager::getValidLinkForProfile(profileId)` lädt den Link und prüft `expires_at`. Ist die Restlaufzeit < 5 Minuten, wird `SpotifyApiClient::refreshToken(refresh_token)` aufgerufen, die Entity aktualisiert und gespeichert. Bei ungültigem Refresh-Token wirft der Client `SpotifyTokenInvalidException`; der Manager reicht sie durch.

## Test-Strategie

- **Unit:** Use Cases mit gemockten Ports (z. B. `GetSpotifyAuthorizationUrl`, `SpotifyTokenManager`) – vorhanden unter `tests/Module/Spotify/`.
- **Integration:** Controller-Tests mit gemocktem `SpotifyApiClientInterface` und Test-DB; OAuth-Callback mit festem State im Cache.
- **E2E (optional):** Kein echter Spotify-Call; Playback/Devices mit Mock-Server oder ausgelassen.

## Konfiguration / Env

- `SPOTIFY_CLIENT_ID`, `SPOTIFY_CLIENT_SECRET`: Spotify Dashboard.
- `SPOTIFY_REDIRECT_URI`: Exakt die Backend-Callback-URL (z. B. `http://localhost:8080/api/v1/spotify/callback`), muss im Spotify Dashboard eingetragen sein.
- `FRONTEND_URL`: Basis-URL des Frontends für Redirects nach dem Callback.
