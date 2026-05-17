DC := docker compose
APP := laravel.test

.PHONY: help up down restart logs app-logs queue-logs outbox-logs shell install migrate fresh test pint validate openapi outbox queue status

help:
	@echo "Available commands:"
	@echo "  make up          Start the full local stack"
	@echo "  make down        Stop containers"
	@echo "  make restart     Restart the stack"
	@echo "  make logs        Follow all logs"
	@echo "  make app-logs    Follow app logs"
	@echo "  make queue-logs  Follow queue worker logs"
	@echo "  make outbox-logs Follow outbox publisher logs"
	@echo "  make shell       Open a shell in the app container"
	@echo "  make install     Install Composer dependencies in container"
	@echo "  make migrate     Run migrations"
	@echo "  make fresh       Recreate schema"
	@echo "  make test        Run automated tests"
	@echo "  make pint        Format PHP code"
	@echo "  make validate    Validate composer.json and compose config"
	@echo "  make outbox      Publish pending outbox messages once"
	@echo "  make queue       Run queue worker in the foreground"
	@echo "  make status      Show containers"

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

test:
	$(DC) exec $(APP) php artisan test

pint:
	$(DC) exec $(APP) ./vendor/bin/pint app src tests routes config database/migrations

validate:
	composer validate --strict --no-check-publish
	$(DC) config --quiet

outbox:
	$(DC) exec $(APP) php artisan outbox:publish --limit=100

queue:
	$(DC) exec $(APP) php artisan queue:work --queue=notifications-high,notifications --tries=3

status:
	$(DC) ps
