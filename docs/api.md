# API

Полная OpenAPI спецификация находится в [openapi.yaml](openapi.yaml).

Проверка спецификации:

```bash
make openapi
```

## Endpoints

```text
POST /api/notifications
POST /api/notifications/bulk
GET  /api/notifications/{notification}
POST /api/notifications/{notification}/delivery-status
GET  /api/subscribers/{subscriber}/notifications
```

## Single Notification

```bash
curl -X POST http://localhost/api/notifications \
  -H 'Content-Type: application/json' \
  -d '{
    "idempotency_key": "order-1001-email",
    "channel": "email",
    "priority": "marketing",
    "recipient": "customer@example.com",
    "subject": "Order shipped",
    "body": "Your order is on the way.",
    "payload": {"order_id": 1001}
  }'
```

Если `idempotency_key` уже использовался, API вернет существующее уведомление без повторной постановки в очередь.

## Bulk Notification

```bash
curl -X POST http://localhost/api/notifications/bulk \
  -H 'Content-Type: application/json' \
  -d '{
    "idempotency_key": "campaign-42",
    "channel": "email",
    "priority": "marketing",
    "body": "Sale starts today.",
    "recipients": ["first@example.com", "second@example.com"]
  }'
```

Для bulk-запроса idempotency key расширяется до per-recipient ключа, чтобы повтор запроса не создавал дубликаты по каждому получателю.

## Priority

```json
{
  "channel": "sms",
  "priority": "transactional",
  "body": "Your access code is 123456.",
  "recipients": ["+15555550100"]
}
```

`transactional` уведомления попадают в `notifications-high`, `marketing` - в `notifications`.

## Delivery Status

```bash
curl -X POST http://localhost/api/notifications/{notification}/delivery-status \
  -H 'Content-Type: application/json' \
  -d '{"status": "delivered"}'
```

Допустимые статусы callback-а:

- `delivered`
- `dropped`

Для `dropped` можно передать `error`.

## Subscriber History

```bash
curl http://localhost/api/subscribers/customer@example.com/notifications
```

Возвращает все известные уведомления подписчика с текущими статусами и временными метками.
