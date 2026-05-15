# Notification Service

## Localhost запуск

```bash
composer install
cp .env.example .env
php artisan key:generate
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
```

Приложение будет доступно на `http://localhost`.

## Очередь уведомлений

```bash
./vendor/bin/sail artisan queue:work --queue=notifications --tries=3
```

## Проверка API

```bash
curl -X POST http://localhost/api/notifications \
  -H 'Content-Type: application/json' \
  -d '{
    "idempotency_key": "order-1001-email",
    "channel": "email",
    "recipient": "customer@example.com",
    "subject": "Order shipped",
    "body": "Your order is on the way."
  }'
```

Kafka UI: `http://localhost:8081`.
