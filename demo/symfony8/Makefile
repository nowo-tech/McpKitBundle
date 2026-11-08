.PHONY: help up down composer-install down-dev build install shell logs test test-coverage cache-clear update-bundle ensure-up restart verify test-conformance

help:
	@echo "Symfony 8 Demo - MCP Kit Bundle"
	@echo "  make up               Start the FrankenPHP container (worker mode)"
	@echo "  make down             Stop containers"
	@echo "  make down-dev         Stop containers (keep volumes; REQ-MAKE-007)"
	@echo "  make restart          Restart containers"
	@echo "  make build            Build image (no cache)"
	@echo "  make install          Install dependencies"
	@echo "  make shell            Open shell in php container"
	@echo "  make logs             Stream container logs"
	@echo "  make test             Run tests"
	@echo "  make test-coverage    Run tests with coverage"
	@echo "  make update-bundle    Update nowo-tech/mcp-kit-bundle and clear cache"
	@echo "  make verify           HTTP 200 on the demo home"
	@echo "  make test-conformance Official MCP conformance runner (pinned), both revisions, per-check baseline"

composer-install:
	@echo "Installing Composer dependencies (ephemeral container)..."
	$(DC) run --rm --no-deps --entrypoint "" -e COMPOSER_MEMORY_LIMIT=-1 php \
		composer install --no-interaction

up:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	@$(MAKE) composer-install
	$(DC) up -d
	@echo "Waiting for FrankenPHP to stay up..."
	@ok=0; \
	for i in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15; do \
		if $(COMPOSE) exec -T php true 2>/dev/null; then ok=1; break; fi; \
		sleep 2; \
	done; \
	if [ "$$ok" != "1" ]; then \
		echo "Demo php container failed to stay up. Recent logs:"; \
		$(COMPOSE) logs --tail=100 php || true; \
		exit 1; \
	fi
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	echo "Demo started at: http://localhost:$$PORT"

ensure-up:
	@if ! $(COMPOSE) exec -T php true 2>/dev/null; then \
		echo "Starting demo container..."; \
		$(MAKE) up; \
	fi

down:
	$(COMPOSE) down

# Stop containers without removing volumes (REQ-MAKE-007)
down-dev:
	$(COMPOSE) down --remove-orphans

restart: ensure-up
	$(COMPOSE) restart

build:
	$(COMPOSE) build --no-cache

install: ensure-up
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	$(COMPOSE) exec -T php composer install --no-interaction
	$(COMPOSE) exec -T php php bin/console cache:clear

shell: ensure-up
	$(COMPOSE) exec php sh

logs:
	$(COMPOSE) logs -f

test: ensure-up
	$(COMPOSE) exec -T -e APP_ENV=test php php bin/phpunit

test-coverage: ensure-up
	$(COMPOSE) exec -T -e APP_ENV=test php php bin/phpunit --coverage-text

cache-clear: ensure-up
	$(COMPOSE) exec -T php php bin/console cache:clear

update-bundle: ensure-up
	$(COMPOSE) exec -T php composer update nowo-tech/mcp-kit-bundle --no-interaction
	$(COMPOSE) exec -T php php bin/console cache:clear --env=dev
	$(COMPOSE) exec -T php php bin/console assets:install public --no-interaction

verify: ensure-up
	@code=$$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:$${PORT:-8219} 2>/dev/null || echo "000"); \
	if [ "$$code" != "200" ]; then echo "Demo check failed: $$code"; exit 1; fi; \
	echo "Demo check OK ($$code)"

# Official MCP conformance runner, pinned exactly in conformance/package-lock.json (owner decision Q-O7: a required
# gate with a per-check baseline). The Node image is pinned by digest.
# Boots the demo in the conformance profile (MCP_CONFORMANCE=1: Caddy adds the conformance token, worker mode) and runs
# the requirement sets of both protocol revisions, then the TypeScript SDK e2e. Bumping either is a deliberate PR.
CONFORMANCE_REVISIONS ?= 2026-07-28 2025-11-25
# node:22-alpine, pinned by digest.
NODE_IMAGE ?= node@sha256:0e1754758c2df4cfa3e68d59e2e1b7374bb7fd9ea6328eaaacf15cc934af8202

test-conformance:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	@$(MAKE) composer-install
	MCP_CONFORMANCE=1 $(COMPOSE) up -d --build --force-recreate
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); PORT=$${PORT:-8219}; \
	i=0; until curl -s -o /dev/null -w '%{http_code}' -X POST http://127.0.0.1:$$PORT/mcp | grep -q '^4'; do \
		i=$$((i+1)); if [ $$i -ge 60 ]; then echo "Demo did not answer"; $(COMPOSE) logs --tail=50 php; $(COMPOSE) down; exit 1; fi; sleep 2; \
	done; \
	status=0; \
	for rev in $(CONFORMANCE_REVISIONS); do \
		echo "== MCP conformance, requirement set $$rev"; \
		docker run --rm --network host --user "$$(id -u):$$(id -g)" -e HOME=/tmp -e npm_config_cache=/tmp/npm \
			-v "$(CURDIR)/../../tests/Conformance:/baseline:ro" -v "$(CURDIR)/conformance:/w" -w /w $(NODE_IMAGE) \
			sh -c "npm ci --no-fund --no-audit --loglevel=error && ./node_modules/.bin/conformance server \
			--url http://localhost:$$PORT/mcp --requirements $$rev \
			--expected-failures /baseline/expected-failures-$$rev.yml" || status=1; \
	done; \
	echo "== TypeScript SDK e2e (official client, pinned in e2e-sdk/package-lock.json)"; \
	docker run --rm --network host --user "$$(id -u):$$(id -g)" -e HOME=/tmp -e npm_config_cache=/tmp/npm \
		-e MCP_URL=http://localhost:$$PORT/mcp -v "$(CURDIR)/e2e-sdk:/w" -w /w $(NODE_IMAGE) \
		sh -c 'npm ci --no-fund --no-audit --loglevel=error && node e2e.mjs' || status=1; \
	$(COMPOSE) down; \
	exit $$status

# REQ-MAKE-008: update-deps (optional: monorepo helper absent on standalone GitHub Actions checkout)
SERVICE_PHP := php
# Prefer Compose V2 plugin (GitHub Actions / modern Docker Desktop); fall back to docker-compose V1 (REQ-MAKE-010).
# Resolve `docker` via `command -v` so the local `docker/` directory cannot shadow the CLI when PATH has empty components.
DOCKER_BIN := $(shell command -v docker 2>/dev/null || echo docker)
COMPOSE ?= $(shell $(DOCKER_BIN) compose version >/dev/null 2>&1 && echo "$(DOCKER_BIN) compose" || echo "docker-compose")
DC = :; $(COMPOSE)
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/../..)
-include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-update-deps.mk
