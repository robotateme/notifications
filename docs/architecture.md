# Архитектура

Проект организован по DDD/CQRS/Hexagonal principles.

## Слои

```text
app/
  Http/          Laravel HTTP layer: controllers, requests, presenters
  Jobs/          Laravel queue jobs
  Models/        Eloquent persistence records
  Providers/     DI bindings

src/
  Domain/        Aggregate, enums, value objects, domain events
  Application/   Commands, handlers, ports
  Infrastructure Adapters for persistence, queue, Kafka, idempotency
```

## Dependency Rule

- `Domain` не зависит от Laravel, Application или Infrastructure.
- `Application` зависит от Domain и ports.
- `Infrastructure` реализует Application ports.
- `app` связывает Laravel framework с Application use cases.

Это закреплено архитектурным тестом:

```bash
docker compose exec laravel.test php artisan test tests/Unit/ArchitectureBoundaryTest.php
```

## Domain

Ключевые элементы:

- `Notification` - aggregate root.
- `NotificationId` - UUID value object.
- `NotificationPayload` - payload value object.
- `Timestamp` - time value object.
- `NotificationChannel`, `NotificationPriority`, `NotificationStatus` - enums.
- Domain events: queued, sent, delivered, dropped.

Domain не генерирует UUID и не использует Eloquent/Carbon/Laravel facade.

## Application

Application слой содержит use cases:

- `CreateNotificationHandler`
- `CreateBulkNotificationsHandler`
- `SendQueuedNotificationHandler`
- `ConfirmNotificationDeliveryHandler`
- `PublishOutboxMessagesHandler`

Ports:

- `NotificationRepository`
- `NotificationQueue`
- `NotificationDeliveryGateway`
- `DomainEventPublisher`
- `OutboxMessageRepository`
- `MessageBroker`
- `IdempotencyGuard`
- `NotificationIdGenerator`
- `TransactionManager`

## Infrastructure

Infrastructure слой содержит адаптеры:

- Eloquent repositories и mappers.
- Eloquent casts для value objects.
- Laravel queue adapter.
- Kafka broker adapter через `kcat`.
- Outbox repository с claim locking и retry.
- Cache lock based idempotency guard.
- UUID generator на `ramsey/uuid`.

## HTTP Layer

HTTP слой тонкий:

- FormRequest валидирует входные данные и собирает command DTO.
- Controller вызывает handler.
- Presenter превращает domain object в JSON response.
