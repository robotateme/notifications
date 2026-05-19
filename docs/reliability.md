# Reliability

## Delivery Model

Сервис рассчитан на at-least-once окружение:

- HTTP create flow защищен idempotency key.
- Queue job может быть выполнен повторно.
- Duplicate job не вызывает provider повторно, если notification уже `sent` или `delivered`.
- Domain events публикуются через transactional outbox.

## Idempotency

Single notification:

- внешний `idempotency_key` сохраняется вместе с notification;
- повторный запрос возвращает уже созданную запись;
- job повторно не ставится.

Bulk notification:

- внешний ключ расширяется до per-recipient ключа;
- повтор bulk-запроса не создает дубликаты по получателям.

## Transactional Outbox

Создание notification и запись outbox event выполняются в одной DB transaction через `TransactionManager`.

Если запись outbox падает:

- notification откатывается;
- outbox event не остается в базе;
- job в очередь не ставится.

Сценарий закреплен тестом:

```bash
docker compose exec laravel.test php artisan test --filter=notification_creation_is_rolled_back_when_outbox_write_fails
```

## Outbox Publisher

Publisher flow:

1. Выбирает доступные `pending`, `failed` или expired `processing` сообщения.
2. Атомарно claim-ит записи через `FOR UPDATE SKIP LOCKED`.
3. Переводит записи в `processing`.
4. Публикует payload в Kafka.
5. Переводит запись в `published` или `failed`.

Claim lease хранится в `available_at`, чтобы зависший publisher не блокировал сообщение навсегда.

## Retry

Outbox retry использует backoff:

```text
min(300, 10 * attempts)
```

Laravel notification jobs используют:

```text
tries = 3
backoff = 10
```

## DLQ

После пятой ошибки публикации outbox message получает статус `dead`.

`dead` сообщения:

- больше не выбираются publisher-ом;
- сохраняют `last_error`;
- сохраняют количество `attempts`;
- могут быть разобраны оператором вручную через БД/Kafka UI/логи.

Сценарий закреплен тестом:

```bash
docker compose exec laravel.test php artisan test --filter=outbox_message_is_moved_to_dead_status_after_retry_limit
```

## Priority

Transactional уведомления отправляются в высокоприоритетную очередь:

- `transactional` -> `notifications-high`
- `marketing` -> `notifications`

Worker слушает очереди в порядке:

```text
notifications-high,notifications
```
