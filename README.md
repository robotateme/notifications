# Notification Service

[![CI](https://github.com/robotateme/notifications/actions/workflows/ci.yml/badge.svg)](https://github.com/robotateme/notifications/actions/workflows/ci.yml)

Микросервис уведомлений для массовой отправки Email/SMS/Push сообщений с приоритетами, идемпотентностью, outbox-публикацией событий в Kafka и отслеживанием статусов доставки.

## Быстрый Старт

Требования:

- Docker и Docker Compose
- Make

Запуск локального окружения:

```bash
make up
```

После старта:

- API: `http://localhost/api`
- Kafka UI: `http://localhost:8081`
- OpenAPI: [docs/openapi.yaml](docs/openapi.yaml)
- Postman: [docs/postman_collection.json](docs/postman_collection.json)

Проверка проекта:

```bash
make validate
make test
```

Остановка:

```bash
make down
```

## Документация

- [Локальный запуск и команды](docs/local-development.md)
- [API и OpenAPI](docs/api.md)
- [Архитектура](docs/architecture.md)
- [Reliability, Outbox, DLQ](docs/reliability.md)
- [Inbox pattern для Kafka consumer](docs/inbox.md)

## Основные Возможности

- Массовая отправка Email/SMS уведомлений.
- Single notification API для Email/SMS/Push.
- Приоритетные очереди: `transactional` обгоняет `marketing`.
- Статусы доставки: `queued`, `sent`, `delivered`, `dropped`.
- История уведомлений по подписчику.
- Idempotency key для защиты от повторного создания уведомлений.
- Transactional outbox для публикации domain events в Kafka.
- Inbox pattern для идемпотентной обработки входящих Kafka events.
- Retry, claim locking и dead-letter status для outbox.
- Интеграционные и архитектурные тесты.

## Стек

- PHP 8.4
- Laravel 13
- PostgreSQL
- Redis queue/cache locks
- Apache Kafka
- Kafka UI
- Docker Compose
