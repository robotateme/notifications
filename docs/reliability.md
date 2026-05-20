# Reliability

## Delivery Model

Сервис рассчитан на at-least-once окружение:

- HTTP create flow защищен idempotency key.
- Queue job может быть выполнен повторно.
- Duplicate job не вызывает provider повторно, если notification уже `sent` или `delivered`.
- Domain events публикуются через transactional outbox.
- `trace_id` протаскивается из HTTP в notification, queue job, outbox и Kafka payload.

## Traceability

HTTP API принимает опциональный заголовок:

```text
X-Trace-Id: trace-order-1001
```

Если заголовок отсутствует, сервис генерирует UUID trace id на входе. Trace id сохраняется:

- в `notification_messages.trace_id`;
- в serialized `SendNotificationJob`;
- в `outbox_messages.trace_id`;
- в top-level Kafka payload `trace_id`;
- в domain event data `trace_id`.

Это дает один correlation id для синхронного HTTP-запроса, фоновой очереди, outbox publisher и Kafka-события.

## Idempotency

Single notification:

- внешний `idempotency_key` валидируется по длине до 120 символов;
- оригинальный ключ не сохраняется;
- в БД и Redis-lock хранится SHA-256 fingerprint длиной 64 символа;
- повторный запрос возвращает уже созданную запись;
- job повторно не ставится.

Bulk notification:

- внешний ключ расширяется до per-recipient ключа;
- per-recipient ключ также хранится только как SHA-256 fingerprint;
- повтор bulk-запроса не создает дубликаты по получателям.

Incoming Kafka events:

- consumer-side обработка проходит через inbox table;
- пара `event_id` + `consumer_name` входящего сообщения уникальна;
- уже обрабатываемый или обработанный `event_id` для того же consumer не вызывает handler повторно;
- failed inbox message можно обработать повторно после следующей доставки.

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

## Provider Last Step

Строгая гарантия ровно одной физической отправки невозможна только силами нашей БД, если внешний provider не поддерживает идемпотентность. Проблемный сценарий:

1. Сервис вызвал provider.
2. Provider принял сообщение.
3. Процесс упал до сохранения статуса `sent`.
4. Queue retry повторил job.

Чтобы закрыть этот last-step gap на уровне бизнес-логики, каждый вызов `NotificationDeliveryGateway` получает стабильный provider idempotency key:

```text
notification-delivery:<notification-uuid>
```

Реальный SMS/Email adapter должен передавать этот ключ во внешний provider как idempotency/request key. Тогда повторный retry с тем же notification не создает вторую физическую отправку на стороне provider.

Если provider не поддерживает idempotency key, система может гарантировать только at-least-once попытку отправки и защиту от повторной отправки после сохраненного `sent`.

## DLQ

После пятой ошибки публикации outbox message получает статус `dead`.

`dead` сообщения:

- больше не выбираются publisher-ом;
- сохраняют `last_error`;
- сохраняют количество `attempts`;
- могут быть просмотрены через paginated CLI `make outbox-dead LIMIT=50 PAGE=1`;
- могут быть возвращены в retry flow через `make outbox-retry-dead ID=<outbox-id>`.
- доступны для мониторинга через Prometheus-compatible метрику `notifications_outbox_dead_messages`.

Сценарий закреплен тестом:

```bash
docker compose exec laravel.test php artisan test --filter=outbox_message_is_moved_to_dead_status_after_retry_limit
```

Операционные команды:

```bash
make outbox-dead
make outbox-dead LIMIT=100 PAGE=2
make outbox-retry-dead ID=1
```

Метрики:

```bash
curl http://localhost/metrics
```

## Inbox Pattern

Inbox pattern закрывает идемпотентность consumer-side Kafka процессов и дополняет at-least-once модель брокера бизнесовой exactly-once обработкой по паре `event_id` + `consumer_name`.

Состав:

- `inbox_messages` table;
- `IncomingMessage` DTO;
- `InboxMessageRepository` port;
- `ProcessInboxMessageHandler`;
- `EloquentInboxMessageRepository`.

Статусы входящего сообщения:

- `processing` - сообщение принято и обрабатывается, параллельный повтор не запускает handler;
- `processed` - обработчик успешно завершился;
- `failed` - обработчик завершился ошибкой, сообщение может быть обработано повторно при следующей доставке.

Повтор уже обрабатываемого или обработанного `event_id` для того же consumer не вызывает бизнес-обработчик повторно.

Сценарий закреплен тестом:

```bash
docker compose exec laravel.test php artisan test tests/Feature/InboxMessageIntegrationTest.php
```

Подробное описание: [Inbox pattern](inbox.md).

## Priority

Transactional уведомления отправляются в высокоприоритетную очередь:

- `transactional` -> `notifications-high`
- `marketing` -> `notifications`

Worker слушает очереди в порядке:

```text
notifications-high,notifications
```
