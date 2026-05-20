# Notification Service

[![CI](https://github.com/robotateme/notifications/actions/workflows/ci.yml/badge.svg)](https://github.com/robotateme/notifications/actions/workflows/ci.yml)

Микросервис уведомлений для Email/SMS/Push: API принимает уведомления, Laravel Queue отправляет их provider-у, Kafka получает события о статусах через outbox.

## Быстрый старт

```bash
docker compose up -d --build
```

То же самое через Make:

```bash
make up
make validate
make test
```

После старта:

- API: `http://localhost/api`
- Kafka UI: `http://localhost:8081`
- OpenAPI: [docs/openapi.yaml](docs/openapi.yaml)

Остановка:

```bash
make down
```

## Что внутри

- Bulk API для массовой отправки Email/SMS и single API для Email/SMS/Push.
- Приоритеты: `transactional` идет в `notifications-high`, `marketing` - в `notifications`.
- Статусы: `queued`, `sent`, `delivered`, `dropped`.
- Idempotency key для защиты от дублей API.
- Outbox для надежной публикации событий в Kafka.
- Inbox для идемпотентной обработки входящих Kafka events.
- Retry/DLQ для outbox.
- Trace id для связи HTTP, queue, outbox и Kafka.
- Интеграционные тесты покрывают API, queue worker, provider mock, outbox retry/DLQ и inbox.

## Документация

- [Локальный запуск](docs/local-development.md)
- [API](docs/api.md)
- [Архитектура](docs/architecture.md)
- [Reliability и Outbox](docs/reliability.md)
- [Inbox](docs/inbox.md)

## Стек

PHP 8.4, Laravel 13, PostgreSQL, Redis, Kafka, Docker Compose.
