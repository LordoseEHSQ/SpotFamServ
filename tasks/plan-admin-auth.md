# Plan: Admin-Auth (Session) + authentifizierter Firmware-Upload

**Erstellt:** 2026-06-04
**Status:** BESTAETIGT per AskQuestion (User, 2026-06-04): Auth-Umfang = gesamter Admin-Bereich;
Mechanismus = Symfony Session-Login mit Admin-User (Passwort aus Env, beim Deploy gesetzt) + Login-Seite;
Web-Upload = ja, nur authentifiziert (revidiert D-025); Deploy direkt nach Fertigstellung.
Decisions: D-026 (Auth), D-027 (Web-Upload).

## Ziel
Der gesamte Web-/Admin-Bereich (readers, profiles, cards, devices, audio-extractor, activity,
reader-station) wird hinter Session-Login gestellt. Maschinen-Endpunkte (ESP-Reader, Flash-Agent,
Spotify-OAuth-Callback) bleiben ueber ihre bestehende `X-API-Key`/Public-Logik erreichbar.
Zusaetzlich: authentifizierter Firmware-Upload ueber die Web-UI (statt nur Console-Command).

## Kritischer Befund (Ist-Stand, real geprueft)
- Es existiert bereits `App\Module\Admin\Domain\AdminUser` (UserInterface) + `app_admin_provider`
  (Entity-Provider auf `username`) + `password_hashers: auto`. Firewall `api` ist aktuell
  `stateless: true` + `PUBLIC_ACCESS` (json_login auskommentiert). => Auth nur "anschalten", nicht neu bauen.
- Es fehlt: Migration fuer `admin_user`, Login/Logout/Me-Endpunkte, Command zum Anlegen des Admin-Users,
  Frontend-Login.
- Bestehende Tests sind **Unit-Tests** (`extends TestCase`, Controller direkt instanziiert, KEIN
  HTTP-Kernel/Firewall). => Firewall bricht die Test-Suite NICHT. (Verifiziert: keine WebTestCase/
  KernelTestCase im Repo.)
- **Risiko/Abwaegung:** Session-Cookie + json_login ohne CSRF-Token. Mitigation: SameSite=Lax
  (Symfony-Default) blockt Cross-Site-POST-Cookies; Heim-Single-User-LAN. CSRF-Token bewusst nicht
  im MVP (haelt SPA einfach). Dokumentiert in D-026.

## Architektur / Endpunkt-Klassifikation
**Public (kein Login; behalten ihre eigene X-API-Key/OAuth-Logik):**
- `POST /api/v1/auth/login` (json_login check_path)
- `GET /api/v1/spotify/callback` (OAuth-Redirect)
- `^/api/v1/readers/(scan|next|previous)` (ESP, X-API-Key)
- `POST /api/v1/readers/claims/{code}/activate` (ESP-Erstbindung)
- `GET /api/v1/readers/firmware/manifest` (ESP-OTA)
- Provisioning-Agent: `POST /devices/detect`, `GET /jobs/next`, `POST /jobs/{id}/status` (X-API-Key)
> Der Subagent MUSS die reale Routenliste (`debug:router`) gegenpruefen und JEDEN ESP-/Agent-Endpunkt
> public lassen; alles andere unter `^/api/v1` = `ROLE_ADMIN`. „Im Zweifel public lassen" ist FALSCH:
> nur die o.g. Maschinen-Endpunkte sind public.

**Geschuetzt (ROLE_ADMIN, Catch-all `^/api/v1`):** alles uebrige inkl. `GET /api/v1/auth/me`,
alle Provisioning-Admin-Endpunkte (devices, artifacts, jobs erstellen/lesen) und der neue Upload.

## Auth-Contract (Frontend nutzt das)
- `POST /api/v1/auth/login` body `{username,password}` -> 204 (Set-Cookie Session) | 401
- `POST /api/v1/auth/logout` -> 204
- `GET  /api/v1/auth/me` -> 200 `{username, roles}` | 401
- Entry-Point liefert 401 (kein Redirect).

## Upload-Contract (D-027)
- `POST /api/v1/provisioning/artifacts` (multipart: `file`, `board`, `channel`, `version`, `expectedChip`)
  -> 201 `{id, board, channel, version, expectedChip, sha256, sizeBytes, filename}` | 400 Validierung.
  Server: Dateiname sanitisieren (kein `/`,`..`,Null-Byte), Groessenlimit, in `FIRMWARE_DIR` ablegen,
  sha256+Groesse berechnen, `FlashArtifact` upserten. Logik mit `RegisterArtifactCommand` teilen
  (gemeinsamer UseCase `RegisterArtifact`). Agent prueft weiterhin Chip+Hash vor Flash (D-025 bleibt fuer Agent).

## 4-Lens
- **Lens 1 Runtime:** Symfony 7.4/PHP 8.4+8.5 unveraendert; Session-Storage = Default (File) im Container.
  Frontend React/Vite unveraendert.
- **Lens 2 Frameworks/Deps:** KEINE neuen Deps (symfony/security-bundle ist da, AdminUser/Provider existieren).
  Frontend: nur eigener Auth-Context + ProtectedRoute (kein neues Lib).
- **Lens 3 Build/CI:** PHPStan L8, PHPUnit (Unit), oasdiff (Auth+Upload = additive Pfade -> non-breaking).
  Frontend tsc/build. Migration `admin_user` neu.
- **Lens 4 Security:** Least Privilege (Maschinen-Endpunkte bleiben key-basiert, Mensch=Session);
  Passwort nur gehasht (auto-Hasher); Admin-User-Anlage via Command/Env beim Deploy; CSRF via SameSite=Lax
  (akzeptiert); Upload nur authentifiziert + serverseitige Validierung (kein freier Binary-Pfad).

## Umsetzung
1. Backend: security.yaml (stateful, json_login, logout, entry_point 401, access_control), AuthController
   (`/auth/me`, login/logout via Firewall), Migration `admin_user`, Command `app:admin:upsert`
   (--username/--password oder Env ADMIN_USERNAME/ADMIN_PASSWORD), Unit-Tests.
2. Backend: `RegisterArtifact` UseCase + `POST /provisioning/artifacts` (Upload) + OpenAPI-Dump + Tests.
3. Frontend: Auth-Context (me/login/logout), Login-Seite, ProtectedRoute, `credentials:'include'`,
   401->Login, Logout-Button/Username im Layout.
4. Frontend: Upload-Dialog in Reader-Station.
5. CI gruen, Doku/CHANGELOG, PR, Squash-Merge.
6. Deploy Pi: Migrationen, `app:admin:upsert` (Admin-PW), `FLASH_AGENT_API_KEY`, FIRMWARE_DIR, Agent-Service.

## Akzeptanz
- Ohne Login: Web-/Admin-Endpunkte -> 401; mit Login -> 200. Maschinen-Endpunkte unveraendert erreichbar.
- Upload nur eingeloggt; hochgeladene Firmware erscheint als registriertes Artefakt; Agent prueft Chip+Hash.
- CI gruen (PHPStan/PHPUnit/oasdiff non-breaking/FE-build). Bestehende Vertraege/Tests unveraendert.

## Bewusste Grenzen
- Single-Admin (ein Account aus Env). Mehrbenutzer = spaeter.
- Kein CSRF-Token (SameSite=Lax). Kein Passwort-Reset-Flow (Command genuegt).
