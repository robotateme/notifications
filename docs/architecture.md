# Архитектура

```text
app/              HTTP, Jobs, Eloquent models, Service Providers
src/Domain        Aggregate, enums, value objects, domain events
src/Application   Commands, handlers, ports
src/Infrastructure Adapters: Eloquent, Redis, Queue, Kafka, Outbox, Inbox
```

## Правила

- `Domain` не зависит от Laravel, Application или Infrastructure.
- `Application` зависит от Domain и ports.
- `Infrastructure` реализует ports.
- `app` связывает Laravel с use cases.

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

## Outbox / Inbox

- Outbox: наши события не потерять до Kafka.
- Inbox: чужие события из Kafka не выполнить дважды.

[reliability.md](reliability.md), [inbox.md](inbox.md).
