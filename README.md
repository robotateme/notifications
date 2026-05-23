# Notification Service

[![CI](https://github.com/robotateme/notifications/actions/workflows/ci.yml/badge.svg)](https://github.com/robotateme/notifications/actions/workflows/ci.yml)

Микросервис уведомлений для Email/SMS/Push: API принимает запрос, queue отправляет provider-у,
outbox кладет события в Kafka.

## Быстрый старт

```bash
make up
make validate
make test
```

- API: `http://localhost/api`
- Kafka UI: `http://localhost:8081`
- OpenAPI: [docs/openapi.yaml](docs/openapi.yaml)

```bash
make down
```

## Фичи

- Bulk API для массовой отправки Email/SMS и single API для Email/SMS/Push.
- Приоритеты: `transactional` идет в `notifications-high`, `marketing` - в `notifications`.
- Статусы: `queued`, `sent`, `delivered`, `dropped`.
- Idempotency key, чтобы не плодить дубли.
- Outbox, чтобы не терять события до Kafka.
- Inbox, чтобы не выполнять входящие Kafka events дважды.
- Retry/DLQ для outbox.
- Trace id, чтобы видеть путь запроса через HTTP, queue, outbox и Kafka.

## Документация

- [Локальный запуск](docs/local-development.md)
- [API](docs/api.md)
- [Тестирование](docs/testing.md)
- [Архитектура](docs/architecture.md)
- [Надежность и Outbox](docs/reliability.md)
- [Inbox](docs/inbox.md)
- [Нагрузка](docs/load-testing.md)

## Стек

PHP 8.4, Laravel 13, PostgreSQL, Redis, Kafka, Docker Compose.
