# Надежность

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

## Outbox

Когда меняется `Notification`, сервис в той же DB transaction пишет event в `outbox_messages`.
Транзакция упала - откатились и данные, и event.

`outbox.publisher` читает `outbox_messages` и публикует события в Kafka:

```text
outbox_messages -> outbox.publisher -> Kafka
```

Если Kafka недоступна, событие остается в БД и будет отправлено позже.

### Проверка падения Kafka

Kafka не гасим контейнером. В тестах мокается `MessageBroker`: `publish()` кидает
`Kafka is unavailable.`.

Outbox уходит в `failed`, получает `available_at` для retry, потом публикуется следующим прогоном.

```bash
docker compose exec laravel.test php artisan test tests/Feature/NotificationDeliveryIntegrationTest.php
```

## DLQ

После 5 ошибок автоматика больше не трогает event. Для разбора есть `attempts` и `last_error`.

```bash
make outbox-dead LIMIT=50 PAGE=1
make outbox-retry-dead ID=<outbox-id>
```

## Inbox

Ключ дедупликации:

```text
event_id + consumer_name
```

Если event уже `processed` или сейчас `processing`, handler второй раз не запускается.
Если прошлый запуск упал и статус `failed`, event можно прогнать снова.

## Остальная защита

- API `idempotency_key` хранится как SHA-256 fingerprint.
- Повторный API-запрос возвращает уже созданную notification и не ставит новую job.
- Queue job не вызывает provider повторно, если notification уже `sent` или `delivered`.
- Provider получает стабильный ключ `notification-delivery:<notification-uuid>`.
- `X-Trace-Id` связывает HTTP, queue, outbox и Kafka.
