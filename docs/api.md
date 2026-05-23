# API

- OpenAPI: [openapi.yaml](openapi.yaml)
- Postman: [postman_collection.json](postman_collection.json)

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
  -H 'X-Trace-Id: trace-order-1001' \
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

## Bulk Notification

```bash
curl -X POST http://localhost/api/notifications/bulk \
  -H 'Content-Type: application/json' \
  -H 'X-Trace-Id: trace-campaign-42' \
  -d '{
    "idempotency_key": "campaign-42",
    "channel": "email",
    "priority": "marketing",
    "body": "Sale starts today.",
    "recipients": ["first@example.com", "second@example.com"]
  }'
```

## Delivery Status

```bash
curl -X POST http://localhost/api/notifications/{notification}/delivery-status \
  -H 'Content-Type: application/json' \
  -d '{"status": "delivered"}'
```

Статусы:

- `delivered`
- `dropped`

## Subscriber History

```bash
curl http://localhost/api/subscribers/customer@example.com/notifications
```
