DC := docker compose
APP := laravel.test
WWWGROUP := $(shell id -g)
WWWUSER := $(shell id -u)

export WWWGROUP
export WWWUSER

.PHONY: help up down restart logs app-logs queue-logs outbox-logs shell install migrate fresh fresh-seed test test-unit test-feature pint validate openapi load-test load-test-docker load-report outbox outbox-dead outbox-retry-dead queue status ps

help:
	@echo "Команды:"
	@echo "  make up           Поднять локальный стек"
	@echo "  make down         Остановить контейнеры"
	@echo "  make restart      Перезапустить стек"
	@echo "  make logs         Смотреть все логи"
	@echo "  make app-logs     Смотреть логи приложения"
	@echo "  make queue-logs   Смотреть логи queue worker"
	@echo "  make outbox-logs  Смотреть логи outbox publisher"
	@echo "  make shell        Открыть shell в app-контейнере"
	@echo "  make install      Поставить Composer-зависимости"
	@echo "  make migrate      Накатить миграции"
	@echo "  make fresh        Пересоздать схему"
	@echo "  make fresh-seed   Пересоздать схему и seed-данные"
	@echo "  make test         Прогнать тесты"
	@echo "  make test-unit    Прогнать unit-тесты"
	@echo "  make test-feature Прогнать feature-тесты"
	@echo "  make pint         Отформатировать PHP-код"
	@echo "  make validate     Проверить composer.json, compose config и OpenAPI"
	@echo "  make openapi      Проверить docs/openapi.yaml"
	@echo "  make load-test    Прогнать k6 локально"
	@echo "  make load-test-docker Прогнать k6 через Docker"
	@echo "  make load-report  Показать последний HTML-отчет k6"
	@echo "  make outbox       Опубликовать pending outbox один раз"
	@echo "  make outbox-dead  Показать dead outbox, LIMIT=50 PAGE=1"
	@echo "  make outbox-retry-dead ID=1 Вернуть dead outbox в pending"
	@echo "  make queue        Запустить queue worker в foreground"
	@echo "  make status       Показать контейнеры"

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

load-test:
	@command -v k6 >/dev/null 2>&1 || (echo "k6 не найден. Запусти 'make load-test-docker' или поставь k6 локально." && exit 127)
	@mkdir -p reports/load
	@run_id=$${REPORT_RUN_ID:-$$(date +%Y%m%d-%H%M%S)}; \
	REPORT_DIR=$${REPORT_DIR:-reports/load} REPORT_RUN_ID=$$run_id k6 run tests/load/notifications.js; \
	status=$$?; \
	echo "Отчеты сохранены в reports/load/notifications-$$run_id.{html,json} и reports/load/latest.{html,json}"; \
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
	echo "Отчеты сохранены в reports/load/notifications-$$run_id.{html,json} и reports/load/latest.{html,json}"; \
	exit $$status

load-report:
	@latest=$$(ls -t reports/load/*.html 2>/dev/null | head -n 1); \
	test -n "$$latest" || (echo "Отчеты k6 не найдены в reports/load" && exit 1); \
	echo "Последний отчет k6: $$latest"; \
	if command -v xdg-open >/dev/null 2>&1; then xdg-open "$$latest" >/dev/null 2>&1 || true; fi

outbox:
	$(DC) exec $(APP) php artisan outbox:publish --limit=100

outbox-dead:
	$(DC) exec $(APP) php artisan outbox:dead --limit=$${LIMIT:-50} --page=$${PAGE:-1}

outbox-retry-dead:
	@test -n "$(ID)" || (echo "Используй: make outbox-retry-dead ID=<outbox-id>" && exit 1)
	$(DC) exec $(APP) php artisan outbox:retry-dead $(ID)

queue:
	$(DC) exec $(APP) php artisan queue:work --queue=notifications-high,notifications --tries=3

status:
	$(DC) ps

ps: status
