# Тесты

- PHPUnit - unit и feature/integration тесты приложения;
- k6 - нагрузочный сценарий для HTTP API.

## Быстрый прогон

```bash
make up
make validate
make test
```

> Важно: `make test` останавливает только `queue.worker` и `outbox.publisher`, потом поднимает их
> обратно. Так PHPUnit сам контролирует queue/outbox и тесты не плавают.

## PHPUnit

```bash
make test
make test-unit
make test-feature
```

Один файл:

```bash
docker compose exec laravel.test php artisan test tests/Feature/NotificationApiTest.php
```

## PHPStan

```bash
make phpstan
```

Уровень 8. Проверяется продуктовый код: `app`, `config`, `database`, `routes`, `src`.
`make validate` тоже гоняет PHPStan.

- `tests/Unit` - доменные правила, gateway/broker adapter-ы и архитектурные границы;
- `tests/Feature` - API, очередь отправки уведомлений, provider mock, inbox, outbox retry/DLQ.

## Нагрузочные тесты

```bash
make load-test-docker
```

`make load-test` требует локальный `k6`. Без него запускай Docker-вариант.

Параметры через env:

```bash
RATE=30 DURATION=3m PRE_ALLOCATED_VUS=20 MAX_VUS=100 make load-test-docker
```

Сценарий: [load-testing.md](load-testing.md).

## Отчеты

- `notifications-<run-id>.html` - HTML-отчет для просмотра;
- `notifications-<run-id>.json` - сырой summary;
- `latest.html` и `latest.json` - последний прогон.

```bash
make load-report
```

## Outbox и Kafka

Падение Kafka проверяем моками, без остановки контейнера.

```bash
docker compose exec laravel.test php artisan test tests/Feature/NotificationDeliveryIntegrationTest.php
```

- `MessageBroker::publish()` кидает `Kafka is unavailable.`;
- outbox уходит в `failed` и получает retry;
- следующий publish отправляет тот же event;
- после 5 ошибок event уходит в `dead`;
- возврат из `dead`: `make outbox-retry-dead ID=<outbox-id>`.

Reliability: [reliability.md](reliability.md).

## Перед PR

```bash
make validate
make test
```

Нагрузка:

```bash
make load-test-docker
make load-report
```
