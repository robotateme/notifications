# Reliability

Сервис живет в at-least-once мире: HTTP-запрос, queue job или Kafka event могут повториться.

Коротко:

```text
Outbox - наши события не потерять до Kafka.
Inbox  - чужие события из Kafka не выполнить дважды.
```

## Путь уведомления

```text
API
 -> Notification: queued
 -> outbox: notification.queued
 -> Laravel Queue
 -> Email/SMS/Push provider
 -> Notification: sent
 -> outbox: notification.sent
 -> Kafka
```

Kafka получает события о статусах, а не сами уведомления на отправку.

## Outbox

Outbox нужен, чтобы БД и Kafka не разъехались.

Когда меняется `Notification`, сервис в той же DB transaction пишет событие в `outbox_messages`. Если транзакция упала, откатываются и бизнес-изменение, и событие.

`outbox.publisher` читает `outbox_messages` и публикует события в Kafka:

```text
outbox_messages -> outbox.publisher -> Kafka
```

Если Kafka недоступна, событие остается в БД и будет отправлено позже.

## DLQ

DLQ реализован как статус `dead` в `outbox_messages`.

После 5 ошибок событие больше не публикуется автоматически. В записи остаются `attempts` и `last_error`.

```bash
make outbox-dead LIMIT=50 PAGE=1
make outbox-retry-dead ID=<outbox-id>
```

## Inbox

Inbox нужен на входе из Kafka. Он хранит пару:

```text
event_id + consumer_name
```

Если event уже `processed` или сейчас `processing`, handler второй раз не запускается. Если прошлый запуск упал и статус `failed`, event можно обработать снова.

Подробнее: [inbox.md](inbox.md).

## Остальная защита

- API `idempotency_key` хранится как SHA-256 fingerprint.
- Повторный API-запрос возвращает уже созданную notification и не ставит новую job.
- Queue job не вызывает provider повторно, если notification уже `sent` или `delivered`.
- Provider получает стабильный ключ `notification-delivery:<notification-uuid>`.
- `X-Trace-Id` связывает HTTP, queue, outbox и Kafka.
