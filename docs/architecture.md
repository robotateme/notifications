# Архитектура

Проект разделен по DDD/Hexagonal подходу, чтобы бизнес-логика не зависела от Laravel, БД и Kafka.

```text
app/              HTTP, Jobs, Eloquent models, Service Providers
src/Domain        Aggregate, enums, value objects, domain events
src/Application   Commands, handlers, ports
src/Infrastructure Adapters: Eloquent, Redis, Queue, Kafka, Outbox, Inbox
```

## Правило зависимостей

- `Domain` не зависит от Laravel, Application или Infrastructure.
- `Application` зависит от Domain и ports.
- `Infrastructure` реализует ports.
- `app` связывает Laravel с use cases.

Проверка:

```bash
docker compose exec laravel.test php artisan test tests/Unit/ArchitectureBoundaryTest.php
```

## Основной поток

```text
HTTP API
 -> Application handler
 -> Domain Notification
 -> PostgreSQL
 -> Laravel Queue
 -> Delivery Gateway
 -> Outbox
 -> Kafka
```

Kafka здесь получает события о статусах notification, а не сами задачи на отправку.

## Outbox / Inbox

- Outbox: наши события не потерять до Kafka.
- Inbox: чужие события из Kafka не выполнить дважды.

Подробности: [reliability.md](reliability.md), [inbox.md](inbox.md).
