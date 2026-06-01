# Plan: Spotify-App-Config als Single Source of Truth über die Oberfläche

**Typ:** Bugfix + Refactor (Architektur) · **Branch:** `fix/spotify-config-db-source-of-truth`
**Status:** ENTWURF – wartet auf Freigabe (Plan-vor-Code-Gate)
**Datum:** 2026-06-01

## Problem (verifiziert, nicht vermutet)
Die System-Einstellungen schreiben Client-ID/Secret/Redirect in die DB
(`SpotifyAppConfiguration`, Secret verschlüsselt), aber der **Laufzeit-Flow benutzt sie nicht**.
Belegte Einspritzpunkte (alle aus env-Parametern `%spotify.*%`):
- `SpotifyHttpApiClient::__construct($clientId, $clientSecret)` → `exchangeCode()`/`refreshToken()` (Token-Tausch + Refresh).
- `GetSpotifyAuthorizationUrl::__construct($clientId, $redirectUri)` → Consent-URL (Scopes hart codiert).
- `SpotifyOAuthController::__construct($redirectUri)` → Callback/`ExchangeSpotifyCode`.

Die DB-Config wird nur in `GetSpotifyAppConfig`/`ValidateSpotifyAppConfig` **zur Anzeige/Presence-Prüfung** gelesen.
→ Eingaben in der UI sind gespeichert, aber **wirkungslos**. Der Login läuft weiter gegen die env-Client-ID.

## Ziel / Akzeptanzkriterien (testbar)
1. Speichert man Client-ID/Secret/Redirect in den System-Einstellungen, **benutzt der echte OAuth-Login + Token-Refresh + alle Spotify-API-Calls diese Werte** – ohne Neustart/Deploy.
2. Ist **keine** vollständige DB-Config vorhanden, greift **env als Fallback** (Bootstrap/Dev bleibt lauffähig).
3. „Validieren" in der UI prüft die Credentials **real gegen Spotify** (nicht nur Presence) und zeigt klares OK/Fehler. *(abhängig von Entscheidung D-C)*
4. Die UI zeigt eindeutig an, **welche Quelle aktiv ist** (DB vs. env) und warnt, solange nur env aktiv ist.
5. PHPUnit grün, PHPStan Level 8 sauber, Frontend-Build + Vitest grün, OpenAPI ohne ungewollten Breaking-Diff.

## Lösungsskizze
Neuer **`SpotifyCredentialsProvider`** (Application-Port + Infrastructure-Impl), liefert zur Laufzeit
die *effektiven* Credentials als Value Object `SpotifyCredentials{clientId, clientSecret, redirectUri, scopes}`:
- `findActive()` vorhanden **und** `isComplete()` → DB-Werte (Secret entschlüsselt; Scopes aus `scope_defaults` falls gesetzt, sonst Default-Liste).
- sonst → env (`%spotify.*%`) + Default-Scopes.

Rewiring (Auflösung zur **Aufrufzeit**, nicht im Konstruktor, da DB sich ändern kann):
- `SpotifyHttpApiClient`: Provider injizieren, in `exchangeCode()`/`refreshToken()` `clientId/secret` daraus.
- `GetSpotifyAuthorizationUrl`: Provider injizieren, `clientId`/`redirectUri`/`scopes` daraus.
- `SpotifyOAuthController` + `ExchangeSpotifyCode`: `redirectUri` aus Provider (muss zu authorize passen).
- `services.yaml`: o. g. drei Services auf Provider umstellen; env-Parameter bleiben (Provider-Fallback).
- *(D-C)* `SpotifyApiClientInterface::checkClientCredentials()` ergänzen (client_credentials-Grant) → echte Validierung.

**Kein neues Schema.** Tabelle `spotify_app_configuration` existiert bereits (Migration `Version20250318100000`).

## 4-Lens-Analyse
**Lens 1 – Runtime & Sprache:** PHP 8.5/Symfony 7.4, keine neuen Sprach-Features. Secret-Entschlüsselung via vorhandenem
`spotify_encrypted_string` (Sodium/`APP_SECRET`). Pi-arm64 unverändert.
**Lens 2 – Frameworks & Abhängigkeiten:** Keine neuen Dependencies. Reines DI-Rewiring + 1 neuer Service + 1 VO.
`SpotifyHttpApiClient` bleibt `final`/Infrastructure; Provider hängt an `SpotifyAppConfigRepositoryInterface` (Infrastructure→Infrastructure, kein Domain-Coupling). **Konstruktor-Signatur von `SpotifyHttpApiClient` ändert sich → DI + evtl. direkte Instanziierungen/Tests anpassen.**
**Lens 3 – Build, CI/CD & Tooling:** Neue Unit-Tests (Provider-Präzedenz). Bestehende Tests, die `SpotifyHttpApiClient`/`GetSpotifyAuthorizationUrl` konstruieren, anpassen. OpenAPI: Endpunkte unverändert (nur Verhalten) → kein oasdiff-Breaking. PHPStan L8.
**Lens 4 – Security & Compliance:** Secret wird zur Laufzeit entschlüsselt im Speicher gehalten – nie loggen, nie im GET zurückgeben (bleibt `has_client_secret`-Bool). `APP_SECRET`-Stabilität ist Voraussetzung (ändert es sich, ist das DB-Secret unlesbar → Fallback/Fehlermeldung nötig). Echte Validierung (D-C) sendet Secret nur an Spotify-Token-Endpoint (TLS).

## Cross-Module-5-Fragen
1. **Wer konsumiert?** Spotify-Modul intern (OAuth, TokenManager, API-Client, Playback via Scan/Reader). Kein anderes Modul liest Credentials direkt.
2. **Vertrag/Schnittstellen?** Interner Provider-Port; keine öffentliche API-Änderung. HTTP-Endpunkte `/system/spotify` bleiben gleich.
3. **Fehlerpfade?** DB-Config unvollständig → env-Fallback. Secret nicht entschlüsselbar (APP_SECRET-Wechsel) → definierter Fehler + Fallback-Strategie. Spotify lehnt Credentials ab → `SpotifyTokenInvalidException` (bereits gemappt).
4. **Migration/State?** Kein Schema. Bestehende DB-Config des Users wird nach Fix **sofort wirksam** → muss vollständig/korrekt sein, sonst Fallback.
5. **Rollback?** Reiner Code-Revert; env-Pfad bleibt funktionsfähig, daher risikoarm rückrollbar.

## Dry-Run (Soll-Ablauf nach Fix, auf dem Pi)
1. User trägt in System-Einstellungen neue Client-ID/Secret + Redirect ein → DB `configured`.
2. „Validieren" → client_credentials-Grant gegen Spotify → `validated` (D-C).
3. Profil → „Mit Spotify verbinden" → `GetSpotifyAuthorizationUrl` baut URL mit **DB-Client-ID** + Redirect.
4. Consent im Browser (Box-Konto) → Callback → `ExchangeSpotifyCode` tauscht mit **DB-Secret** → Token gespeichert.
5. Refresh/Playback nutzen ebenfalls DB-Credentials. env wird nicht mehr berührt.

## Blind Spots / Risiken
- **R1:** `SpotifyHttpApiClient`-Konstruktoränderung bricht stillschweigend DI/Tests → vor Merge `grep` auf direkte `new SpotifyHttpApiClient(`.
- **R2:** OAuth-Consent ist **pro Profil** (`GetSpotifyAuthorizationUrl($profileId)`); bei einem gemeinsamen Box-Konto muss pro Profil einmal autorisiert werden. **Nicht Teil dieses Fixes** – nur dokumentieren.
- **R3:** Caching: Provider darf Config **nicht** prozessweit cachen, sonst greift ein UI-Save erst nach Neustart. Pro Request frisch lesen (oder gezielt invalidieren).
- **R4:** Secret-Klartext nie in Logs/ActivityLog/Exceptions.
- **R5:** Scope-Drift: Falsche Scopes in der DB würden Playback brechen → Default-Liste code-seitig (D-B).

## Offene Entscheidungen (brauchen Freigabe)
- **D-C-A Präzedenz:** DB nur wenn `isComplete()` (ganzheitlich), sonst env. *(Empfehlung)*
- **D-C-B Scopes:** Kanonische Scope-Liste bleibt code-seitig; UI-Feld `scope_defaults` ignorieren/ausblenden. *(Empfehlung; Sicherheit)*
- **D-C-C Echte Validierung:** „Validieren" macht client_credentials-Grant gegen Spotify (statt Presence-Check). *(Empfehlung)*
- **D-C-D env behalten:** env als Fallback erhalten (Bootstrap/Dev). *(Empfehlung)*
- **D-C-E Redirect editierbar:** Redirect-URI in UI editierbar, Loopback-Default vorbefüllt. *(Empfehlung)*

## Definition of Done
- Provider + Rewiring umgesetzt, DB = Source of Truth, env = Fallback.
- Unit-Tests Provider-Präzedenz + angepasste Bestandstests grün; PHPStan L8; FE-Build/Vitest grün.
- UI zeigt aktive Quelle + Warnung bei env-only; (D-C) echte Validierung.
- Docs: `SPOTIFY_INTEGRATION.md` (Config-Quelle/Präzedenz) + `decisions.md` (D-011) + `CHANGELOG`.
- Bug-Issue (GitHub, Label `bug`) verlinkt, PR `Closes #<bug>`, CI 5/5 grün.
