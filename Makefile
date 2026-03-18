# Spotify Familien Server – development tasks
# Uses docker-compose v1 (standalone) as docker compose plugin is not installed.
# To upgrade: sudo apt-get install docker-compose-plugin

COMPOSE ?= docker-compose

.PHONY: up down build migrate migrate-diff fresh test stan frontend-dev build-frontend install install-backend install-frontend help

# ── Docker ──────────────────────────────────────────────────────────────────

up:
	$(COMPOSE) up -d

down:
	$(COMPOSE) down

build:
	$(COMPOSE) build

logs:
	$(COMPOSE) logs -f

ps:
	$(COMPOSE) ps

# ── Backend ──────────────────────────────────────────────────────────────────

install-backend:
	cd backend && composer install

migrate:
	$(COMPOSE) exec -T app php bin/console doctrine:migrations:migrate --no-interaction

migrate-diff:
	$(COMPOSE) exec -T app php bin/console doctrine:migrations:diff

cc:
	$(COMPOSE) exec -T app php bin/console cache:clear

fresh: down
	docker volume rm spotfamserv_spotfam_db_data 2>/dev/null || true
	$(MAKE) up
	@echo "Warte auf DB-Start..."
	sleep 6
	$(MAKE) migrate

# ── Backend tests & static analysis ─────────────────────────────────────────

test:
	$(COMPOSE) exec -T app php bin/phpunit

stan:
	$(COMPOSE) exec -T app sh -c "composer stan 2>/dev/null || php vendor/bin/phpstan analyse"

# ── Frontend ─────────────────────────────────────────────────────────────────

install-frontend:
	cd frontend && pnpm install

frontend-dev:
	cd frontend && pnpm dev

build-frontend:
	cd frontend && pnpm build

# ── Full install ─────────────────────────────────────────────────────────────

install: install-backend install-frontend

# ── Default ──────────────────────────────────────────────────────────────────

.DEFAULT_GOAL := help
help:
	@echo ""
	@echo "Spotify Familien Server – verfügbare Targets:"
	@echo "  up               Container starten (detached)"
	@echo "  down             Container stoppen"
	@echo "  build            Images neu bauen"
	@echo "  logs             Live-Logs aller Container"
	@echo "  ps               Container-Status"
	@echo "  migrate          DB-Migrationen ausführen"
	@echo "  migrate-diff     Neue Migration generieren"
	@echo "  cc               Symfony Cache leeren"
	@echo "  fresh            Alles neu (Volume löschen, up, migrate)"
	@echo "  test             PHPUnit-Tests"
	@echo "  stan             PHPStan statische Analyse"
	@echo "  install          Backend + Frontend installieren"
	@echo "  frontend-dev     Vite Dev-Server starten"
	@echo "  build-frontend   Frontend production build"
	@echo ""
	@echo "  Tipp: COMPOSE=docker-compose make up  (default)"
	@echo "        COMPOSE='docker compose' make up  (mit Plugin)"
