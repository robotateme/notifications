DC := docker compose
APP := laravel.test
WWWGROUP := $(shell id -g)
WWWUSER := $(shell id -u)
RESET := \033[0m
BOLD := \033[1m
GREEN := \033[32m
YELLOW := \033[33m
CYAN := \033[36m

export WWWGROUP
export WWWUSER

.PHONY: help up down restart logs app-logs queue-logs outbox-logs shell install migrate fresh fresh-seed test test-unit test-feature phpstan pint validate openapi load-test load-test-docker load-report outbox outbox-dead outbox-retry-dead queue status ps

help:
	@printf "$(BOLD)Команды:$(RESET)\n"
	@printf "  $(CYAN)%-30s$(RESET) %s\n" "make up" "Поднять локальный стек"
	@printf "  $(CYAN)%-30s$(RESET) %s\n" "make down" "Остановить контейнеры"
	@printf "  $(CYAN)%-30s$(RESET) %s\n" "make restart" "Перезапустить стек"
	@printf "  $(CYAN)%-30s$(RESET) %s\n" "make logs" "Смотреть все логи"
	@printf "  $(CYAN)%-30s$(RESET) %s\n" "make app-logs" "Смотреть логи приложения"
	@printf "  $(CYAN)%-30s$(RESET) %s\n" "make queue-logs" "Смотреть логи queue worker"
	@printf "  $(CYAN)%-30s$(RESET) %s\n" "make outbox-logs" "Смотреть логи outbox publisher"
	@printf "  $(CYAN)%-30s$(RESET) %s\n" "make shell" "Открыть shell в app-контейнере"
	@printf "  $(CYAN)%-30s$(RESET) %s\n" "make install" "Поставить Composer-зависимости"
	@printf "  $(CYAN)%-30s$(RESET) %s\n" "make migrate" "Накатить миграции"
	@printf "  $(CYAN)%-30s$(RESET) %s\n" "make fresh" "Пересоздать схему"
	@printf "  $(CYAN)%-30s$(RESET) %s\n" "make fresh-seed" "Пересоздать схему и seed-данные"
	@printf "  $(CYAN)%-30s$(RESET) %s\n" "make test" "Прогнать тесты"
	@printf "  $(CYAN)%-30s$(RESET) %s\n" "make test-unit" "Прогнать unit-тесты"
	@printf "  $(CYAN)%-30s$(RESET) %s\n" "make test-feature" "Прогнать feature-тесты"
	@printf "  $(CYAN)%-30s$(RESET) %s\n" "make phpstan" "Прогнать PHPStan на level 8"
	@printf "  $(CYAN)%-30s$(RESET) %s\n" "make pint" "Отформатировать PHP-код"
	@printf "  $(CYAN)%-30s$(RESET) %s\n" "make validate" "Проверить OpenAPI, composer.json, compose config и PHPStan"
	@printf "  $(CYAN)%-30s$(RESET) %s\n" "make openapi" "Проверить docs/openapi.yaml"
	@printf "  $(CYAN)%-30s$(RESET) %s\n" "make load-test" "Прогнать k6 локально"
	@printf "  $(CYAN)%-30s$(RESET) %s\n" "make load-test-docker" "Прогнать k6 через Docker"
	@printf "  $(CYAN)%-30s$(RESET) %s\n" "make load-report" "Показать последний HTML-отчет k6"
	@printf "  $(CYAN)%-30s$(RESET) %s\n" "make outbox" "Опубликовать pending outbox один раз"
	@printf "  $(CYAN)%-30s$(RESET) %s\n" "make outbox-dead" "Показать dead outbox, LIMIT=50 PAGE=1"
	@printf "  $(CYAN)%-30s$(RESET) %s\n" "make outbox-retry-dead ID=1" "Вернуть dead outbox в pending"
	@printf "  $(CYAN)%-30s$(RESET) %s\n" "make queue" "Запустить queue worker в foreground"
	@printf "  $(CYAN)%-30s$(RESET) %s\n" "make status" "Показать контейнеры"

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

phpstan:
	$(DC) exec $(APP) ./vendor/bin/phpstan analyse --memory-limit=1G

pint:
	$(DC) exec $(APP) ./vendor/bin/pint app src tests routes config database/migrations

validate: openapi phpstan
	composer validate --strict --no-check-publish
	$(DC) config --quiet

openapi:
	python3 -c "import yaml; yaml.safe_load(open('docs/openapi.yaml', encoding='utf-8'))"

load-test:
	@command -v k6 >/dev/null 2>&1 || (printf "$(YELLOW)k6 не найден. Запусти 'make load-test-docker' или поставь k6 локально.$(RESET)\n" && exit 127)
	@mkdir -p reports/load
	@run_id=$${REPORT_RUN_ID:-$$(date +%Y%m%d-%H%M%S)}; \
	REPORT_DIR=$${REPORT_DIR:-reports/load} REPORT_RUN_ID=$$run_id k6 run tests/load/notifications.js; \
	status=$$?; \
	printf "$(GREEN)Отчеты сохранены в reports/load/notifications-$$run_id.{html,json} и reports/load/latest.{html,json}$(RESET)\n"; \
	exit $$status

load-test-docker:
	@mkdir -p reports/load
	@run_id=$${REPORT_RUN_ID:-$$(date +%Y%m%d-%H%M%S)}; \
	docker run --rm --network host --user "$$(id -u):$$(id -g)" \
		-e BASE_URL=$${BASE_URL:-http://localhost/api} \
		-e RATE=$${RATE:-5} \
		-e DURATION=$${DURATION:-30s} \
		-e PRE_ALLOCATED_VUS=$${PRE_ALLOCATED_VUS:-5} \
		-e MAX_VUS=$${MAX_VUS:-20} \
		-e BULK_RECIPIENTS=$${BULK_RECIPIENTS:-10} \
		-e THINK_TIME_SECONDS=$${THINK_TIME_SECONDS:-0.2} \
		-e REPORT_DIR=/reports \
		-e REPORT_RUN_ID=$$run_id \
		-v "$(CURDIR)/tests/load:/scripts:ro" \
		-v "$(CURDIR)/reports/load:/reports" \
		grafana/k6 run /scripts/notifications.js; \
	status=$$?; \
	printf "$(GREEN)Отчеты сохранены в reports/load/notifications-$$run_id.{html,json} и reports/load/latest.{html,json}$(RESET)\n"; \
	exit $$status

load-report:
	@latest=$$(ls -t reports/load/*.html 2>/dev/null | head -n 1); \
	test -n "$$latest" || (printf "$(YELLOW)Отчеты k6 не найдены в reports/load$(RESET)\n" && exit 1); \
	printf "$(GREEN)Последний отчет k6: $$latest$(RESET)\n"; \
	if command -v xdg-open >/dev/null 2>&1; then xdg-open "$$latest" >/dev/null 2>&1 || true; fi

outbox:
	$(DC) exec $(APP) php artisan outbox:publish --limit=100

outbox-dead:
	$(DC) exec $(APP) php artisan outbox:dead --limit=$${LIMIT:-50} --page=$${PAGE:-1}

outbox-retry-dead:
	@test -n "$(ID)" || (printf "$(YELLOW)Используй: make outbox-retry-dead ID=<outbox-id>$(RESET)\n" && exit 1)
	$(DC) exec $(APP) php artisan outbox:retry-dead $(ID)

queue:
	$(DC) exec $(APP) php artisan queue:work --queue=notifications-high,notifications --tries=3

status:
	$(DC) ps

ps: status
