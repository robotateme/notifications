# Локальный Запуск

## Требования

- Docker и Docker Compose
- Make

## Первый Старт

```bash
make up
```

Команда собирает app image и поднимает:

- `laravel.test` - HTTP API;
- `pgsql` - PostgreSQL;
- `redis` - queue/cache locks;
- `kafka` - Kafka broker;
- `kafka-ui` - UI для Kafka;
- `queue.worker` - Laravel queue worker;
- `outbox.publisher` - периодическая публикация outbox events.

Адреса:

- API: `http://localhost/api`
- Kafka UI: `http://localhost:8081`

## Команды

```bash
make status
make logs
make app-logs
make queue-logs
make outbox-logs
```

Схема БД:

```bash
make migrate
make fresh
make fresh-seed
```

Тесты и проверки:

```bash
make test
make test-unit
make test-feature
make validate
make openapi
make pint
```

Точечная проверка inbox pattern:

```bash
docker compose exec laravel.test php artisan test tests/Feature/InboxMessageIntegrationTest.php
```

Ручной запуск процессов:

```bash
make queue
make outbox
make outbox-dead
make outbox-retry-dead ID=1
```

Остановка:

```bash
make down
```

## Очереди

- `notifications-high` - transactional уведомления.
- `notifications` - marketing уведомления.

Worker слушает обе очереди:

```bash
php artisan queue:work --queue=notifications-high,notifications --tries=3
```

## Kafka

Outbox publisher публикует domain events в Kafka через `kcat` внутри app-контейнера.

Разовая публикация:

```bash
make outbox
```

Kafka UI доступен на `http://localhost:8081`.

Входящие Kafka events должны обрабатываться через inbox pattern. В проекте уже есть Application handler и Infrastructure repository для атомарной дедубликации по `event_id`; отдельный long-running consumer command можно добавить поверх этого контракта без изменения Domain.
