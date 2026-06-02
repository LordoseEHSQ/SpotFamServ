# Spotify Familien Server

Privat gehostetes Familien-Musiksystem: Ein Admin verwaltet Familienprofile mit Spotify-Anbindung, Standard-Lautsprechern und RFID-Karten. Beim Scan einer Karte startet die gebundene Playlist auf dem Profil-Lautsprecher.

## Tech Stack

- **Backend:** Symfony 7.4 (LTS), PHP 8.4+ (Docker-Image/Pi: 8.5.6), Doctrine ORM 3, PostgreSQL 17
- **Frontend:** React 18, TypeScript, Vite, React Router, TanStack Query, React Hook Form
- **Infrastruktur:** Docker Compose (PHP-FPM, Nginx, PostgreSQL)

## Voraussetzungen

- Docker & Docker Compose
- PHP 8.4+ und Composer (für lokale Backend-Entwicklung ohne Docker; Docker-Stack nutzt PHP 8.5.6)
- Node 20+ und pnpm (Frontend)

## Schnellstart

```bash
# Umgebung
cp .env.example .env
# .env anpassen: APP_SECRET, ggf. SPOTIFY_* und DATABASE_URL

# Mit Docker
make up
make migrate

# Backend lokal (ohne Docker-App-Container)
make install-backend
cd backend && php bin/console doctrine:migrations:migrate

# Frontend
make install-frontend
make frontend-dev
```

- **Backend-API:** http://localhost:8080
- **Frontend (Vite):** http://localhost:5173 (Proxy auf Backend)

## Projektstruktur

```
SpotFamServ/
├── backend/          # Symfony modularer Monolith
│   ├── config/
│   ├── migrations/
│   ├── src/Module/   # Admin, FamilyProfile, Spotify, Rfid, Scan, SetupWizard, Shared
│   └── public/
├── frontend/         # React SPA
│   └── src/
│       ├── routes/
│       ├── features/
│       ├── api/
│       └── components/
├── docker/
└── docker-compose.yml
```

## API

REST unter `/api/v1`. Siehe OpenAPI-Dokumentation (geplant) oder `backend/config/routes/`.

## Lizenz

Privat.
