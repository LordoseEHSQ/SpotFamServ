# Admin-Authentifizierung – Runbook

Stand: 2026-06-04 · Plan: `tasks/plan-admin-auth.md` · Decisions: **D-026**, **D-027**

## Überblick

Der gesamte Web-/Admin-Bereich ist per **Session-Login** geschützt: Symfony legt eine Server-Session
an und setzt ein **HttpOnly-Cookie** (`SPOTFAM_SESSID`) — **kein** Token in `localStorage`.

Zusätzlich gilt **CSRF-Schutz** nach dem **Double-Submit**-Muster: Cookie `XSRF-TOKEN` plus Request-Header
`X-XSRF-TOKEN` mit demselben Wert bei mutierenden Requests.

**Bewusst nicht im MVP:** OIDC/OAuth für Admins, Multi-User, Passwort-Reset per UI. Maschinen-Clients
(ESP-Reader, Flash-Agent) authentifizieren weiter per **`X-API-Key`**, nicht per Session.

Architektur-Entscheidungen: **D-026** (Session-Auth + Endpoint-Modell), **D-027** (authentifizierter
Firmware-Upload über die Web-UI).

## Admin-Account anlegen oder ändern

Idempotent — legt den Admin an oder aktualisiert das Passwort (gehasht, nie Klartext in der DB):

```bash
cd backend
php bin/console app:admin:upsert --username=<u> --password=<pw>
```

Alternativ aus Umgebungsvariablen **`ADMIN_USERNAME`** / **`ADMIN_PASSWORD`** (z. B. in
`backend/.env.local` auf dem Pi, nicht committen).

## Env- und Deploy-Voraussetzungen

1. **Migration** `admin_user` ausführen:
   ```bash
   php bin/console doctrine:migrations:migrate --no-interaction
   ```
2. **`FLASH_AGENT_API_KEY`** setzen (Backend + Flash-Agent `secrets.env`, Header `X-API-Key`).
3. **`FIRMWARE_DIR`** — Default `backend/var/firmware`; Verzeichnis muss **auf dem Host existieren**
   (Symfony legt `var/firmware` nicht automatisch an).
4. Nach Deploy: `app:admin:upsert` einmalig ausführen, damit Login möglich ist.

## Endpunkt-Schutzmodell

| Zugriff | Endpunkte / Muster |
|---------|-------------------|
| **Public** (kein `ROLE_ADMIN`) | `POST` readers scan/next/previous · `POST` claims/`{code}`/activate · `GET` readers/firmware/manifest · Provisioning-Agent: detect, jobs/next, jobs/`{id}`/status · `GET` spotify/callback · `GET`/`POST` auth/login · `GET` auth/csrf |
| **`ROLE_ADMIN`** (Session + CSRF bei Mutations) | **Gesamter übriger** `/api/v1`-Bereich inkl. Reader-Station-UI-APIs, Profile, Spotify-Admin, **`POST` provisioning/artifacts** (Upload), `auth/logout`, `auth/me`, … |

Maschinen-Routen bleiben bei **`X-API-Key`** (Reader-Key bzw. `FLASH_AGENT_API_KEY`); CSRF gilt dort nicht.

## CSRF-Flow für Clients (SPA)

1. **`GET /api/v1/auth/csrf`** — setzt Cookie `XSRF-TOKEN`.
2. Mutierende Requests (`POST`, `PUT`, `PATCH`, `DELETE`) mit Header **`X-XSRF-TOKEN`** = Cookie-Wert
   und **`credentials: 'include'`** (Cookie mitsenden).

Ausnahme: Maschinen-Endpunkte mit `X-API-Key` (kein CSRF).

## Login-Flow Frontend

Reihenfolge beim ersten Besuch bzw. nach Logout:

1. `GET /api/v1/auth/csrf`
2. `POST /api/v1/auth/login` (JSON: username/password; CSRF-Header + Cookies)
3. `GET /api/v1/auth/me` — Session prüfen; bei `401` → Login-Seite

Logout: `POST /api/v1/auth/logout` (mit CSRF + Session-Cookie).

## Cookie-Härtung

| Eigenschaft | Wert |
|-------------|------|
| Name | `SPOTFAM_SESSID` |
| HttpOnly | ja |
| SameSite | `Lax` |
| Secure | auto (HTTPS → Secure-Cookie) |

## Grenzen und Risiken (ehrlich)

- **Single-Admin:** genau ein Account; kein Multi-User, keine Rollenverwaltung.
- **LAN ohne TLS:** Login-Passwort wandert im Klartext über HTTP — für Heim-LAN oft akzeptiert,
  für Produktion **HTTPS oder SSH-Tunnel** empfohlen; mit HTTPS setzt Secure=auto das Secure-Flag.
- **Kein Passwort-Reset:** nur erneutes `app:admin:upsert` (oder Env + Command).
- **CSRF-Mitigation** ersetzt kein TLS; SameSite=Lax schützt vor einfachen Cross-Site-POSTs, nicht
  vor kompromittierten Clients im selben Origin.
