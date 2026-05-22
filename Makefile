DC := docker compose
APP := laravel.test
WWWGROUP := $(shell id -g)
WWWUSER := $(shell id -u)

export WWWGROUP
export WWWUSER

.PHONY: help up down restart logs app-logs queue-logs outbox-logs shell install migrate fresh fresh-seed test test-unit test-feature pint validate openapi outbox outbox-dead outbox-retry-dead queue status ps

help:
	@echo "Available commands:"
	@echo "  make up           Start the full local stack"
	@echo "  make down         Stop containers"
	@echo "  make restart      Restart the stack"
	@echo "  make logs         Follow all logs"
	@echo "  make app-logs     Follow app logs"
	@echo "  make queue-logs   Follow queue worker logs"
	@echo "  make outbox-logs  Follow outbox publisher logs"
	@echo "  make shell        Open a shell in the app container"
	@echo "  make install      Install Composer dependencies in container"
	@echo "  make migrate      Run migrations"
	@echo "  make fresh        Recreate schema"
	@echo "  make fresh-seed   Recreate schema and seed data"
	@echo "  make test         Run automated tests"
	@echo "  make test-unit    Run unit tests"
	@echo "  make test-feature Run feature tests"
	@echo "  make pint         Format PHP code"
	@echo "  make validate     Validate composer.json, compose config and OpenAPI"
	@echo "  make openapi      Validate docs/openapi.yaml"
	@echo "  make outbox       Publish pending outbox messages once"
	@echo "  make outbox-dead  List dead outbox messages, supports LIMIT=50 PAGE=1"
	@echo "  make outbox-retry-dead ID=1 Return a dead outbox message to pending"
	@echo "  make queue        Run queue worker in the foreground"
	@echo "  make status       Show containers"

up:
	$(DC) up -d --build

down:
	$(DC) down

restart:
	$(DC) down
	$(DC) up -d --build

logs:
	$(DC) logs -f

app-logs:
	$(DC) logs -f $(APP)

queue-logs:
	$(DC) logs -f queue.worker

outbox-logs:
	$(DC) logs -f outbox.publisher

shell:
	$(DC) exec $(APP) bash

install:
	$(DC) exec $(APP) composer install

migrate:
	$(DC) exec $(APP) php artisan migrate

fresh:
	$(DC) exec $(APP) php artisan migrate:fresh

fresh-seed:
	$(DC) exec $(APP) php artisan migrate:fresh --seed

test:
	@$(DC) stop queue.worker outbox.publisher >/dev/null
	@status=0; $(DC) exec $(APP) php artisan test || status=$$?; \
	$(DC) start queue.worker outbox.publisher >/dev/null; \
	exit $$status

test-unit:
	$(DC) exec $(APP) php artisan test tests/Unit

test-feature:
	@$(DC) stop queue.worker outbox.publisher >/dev/null
	@status=0; $(DC) exec $(APP) php artisan test tests/Feature || status=$$?; \
	$(DC) start queue.worker outbox.publisher >/dev/null; \
	exit $$status

pint:
	$(DC) exec $(APP) ./vendor/bin/pint app src tests routes config database/migrations

validate: openapi
	composer validate --strict --no-check-publish
	$(DC) config --quiet

openapi:
	python3 -c "import yaml; yaml.safe_load(open('docs/openapi.yaml', encoding='utf-8'))"

outbox:
	$(DC) exec $(APP) php artisan outbox:publish --limit=100

outbox-dead:
	$(DC) exec $(APP) php artisan outbox:dead --limit=$${LIMIT:-50} --page=$${PAGE:-1}

outbox-retry-dead:
	@test -n "$(ID)" || (echo "Usage: make outbox-retry-dead ID=<outbox-id>" && exit 1)
	$(DC) exec $(APP) php artisan outbox:retry-dead $(ID)

queue:
	$(DC) exec $(APP) php artisan queue:work --queue=notifications-high,notifications --tries=3

status:
	$(DC) ps

ps: status
