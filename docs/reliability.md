# Reliability

Сервис работает в at-least-once мире: HTTP-запросы, queue jobs и Kafka-сообщения могут повторяться. Поэтому в проекте есть две простые защиты:

- **Outbox** - чтобы наши события не потерялись до Kafka.
- **Inbox** - чтобы чужие Kafka-события не выполнились у нас дважды.

## Путь уведомления

API-запрос не отправляет уведомление напрямую через Kafka.

```text
API
 -> Notification со статусом queued
 -> outbox event notification.queued
 -> Laravel queue job
 -> Email/SMS/Push provider
 -> Notification со статусом sent
 -> outbox event notification.sent
 -> Kafka
```

Kafka получает не само уведомление на отправку, а события о его состоянии:

```text
notification.queued
notification.sent
notification.delivered
notification.dropped
```

## Outbox

Outbox нужен, чтобы БД и Kafka не разъехались.

Когда меняется `Notification`, сервис в той же DB transaction пишет event в `outbox_messages`. Если транзакция прошла, у нас есть и бизнес-изменение, и событие, которое надо отправить в Kafka. Если транзакция упала, не остается ни частичного изменения, ни “висячего” события.

Примеры атомарных изменений:

- создали notification -> записали `notification.queued`;
- provider принял отправку -> записали `notification.sent`;
- delivery callback подтвердил доставку -> записали `notification.delivered`;
- notification была отклонена -> записали `notification.dropped`.

Отдельный процесс `outbox.publisher` читает `outbox_messages` и публикует события в Kafka:

```text
outbox_messages -> outbox.publisher -> Kafka
```

Если Kafka недоступна, событие остается в БД и будет отправлено позже. Если Kafka работает, событие уйдет почти сразу.

После 5 ошибок событие получает статус `dead` и больше не публикуется автоматически.

```bash
make outbox-dead LIMIT=50 PAGE=1
make outbox-retry-dead ID=<outbox-id>
```

## Inbox

Inbox нужен на входе из Kafka.

Kafka может доставить одно и то же сообщение повторно. Например, consumer обработал event, но упал до commit offset. После рестарта Kafka отдаст тот же event еще раз.

Inbox сохраняет пару:

```text
event_id + consumer_name
```

Если event уже `processed` или сейчас `processing`, handler второй раз не запускается. Если прошлый запуск упал и статус `failed`, сообщение можно обработать повторно.

Коротко:

```text
Producer service: outbox -> не потерять событие до Kafka
Consumer service: inbox -> не выполнить событие дважды после Kafka
```

Подробности: [inbox.md](inbox.md).

## Idempotency

- API `idempotency_key` хранится только как SHA-256 fingerprint.
- Повторный single/bulk запрос возвращает уже созданные notifications и не ставит новые jobs.
- Queue job не вызывает provider повторно, если notification уже `sent` или `delivered`.
- Каждый вызов provider-а получает стабильный ключ `notification-delivery:<notification-uuid>`.

## Traceability

API принимает `X-Trace-Id`; если заголовка нет, сервис генерирует UUID. Trace id сохраняется в notification, queue job, outbox row и Kafka payload.

## Priority

- `transactional` -> `notifications-high`
- `marketing` -> `notifications`

Worker слушает очереди в порядке:

```text
notifications-high,notifications
```
