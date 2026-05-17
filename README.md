# Notification Service

## Требования

- Docker и Docker Compose
- Make

## Запуск

```bash
make up
```

Приложение будет доступно на `http://localhost`.
Kafka UI: `http://localhost:8081`.
Kafka REST Proxy: `http://localhost:8082`.

Стек поднимает приложение, PostgreSQL, Redis, Kafka, Kafka REST Proxy, Kafka UI,
queue worker и outbox publisher.

## Очередь уведомлений

```bash
make queue-logs
```

`transactional` уведомления попадают в `notifications-high`, `marketing` - в
`notifications`.

## Outbox publisher

```bash
make outbox-logs
```

Outbox publisher публикует pending domain events из таблицы `outbox_messages`
в Kafka через Kafka REST Proxy.

Разовый запуск publisher:

```bash
make outbox
```

## Проверка API

```bash
curl -X POST http://localhost/api/notifications/bulk \
  -H 'Content-Type: application/json' \
  -d '{
    "idempotency_key": "campaign-42",
    "channel": "email",
    "priority": "marketing",
    "message": "Service window starts tonight.",
    "recipients": ["customer@example.com"]
  }'
```

История уведомлений подписчика:

```bash
curl http://localhost/api/subscribers/customer@example.com/notifications
```

## API описание

OpenAPI spec: `docs/openapi.yaml`.

## Полезные команды

```bash
make status
make logs
make test
make pint
make validate
make down
```
