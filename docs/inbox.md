# Inbox Pattern

Inbox pattern нужен для входящих Kafka events. Он защищает consumer-side процессы от повторного выполнения бизнес-действия, если брокер или consumer доставили одно и то же сообщение больше одного раза.

## Задача

Kafka и queue-процессы обычно работают в at-least-once модели: сообщение может прийти повторно после retry, timeout, rebalance consumer group или падения процесса между обработкой и commit offset.

Inbox переносит гарантию идемпотентности в базу данных:

- каждый входящий event имеет стабильный `event_id`;
- обработчик имеет стабильный `consumer_name`;
- пара `event_id` + `consumer_name` хранится в `inbox_messages` с unique constraint;
- бизнес-обработчик запускается только если сообщение еще не было успешно обработано;
- повтор `processed` сообщения быстро завершается без повторного side effect;
- `failed` сообщение можно обработать повторно при следующей доставке.

## Поток Обработки

1. Kafka consumer получает сообщение.
2. Consumer собирает `IncomingMessage` из `event_id`, `consumer_name`, topic, key и payload.
3. `ProcessInboxMessageHandler` вызывает `InboxMessageRepository::handleOnce`.
4. Repository открывает DB transaction и блокирует строку по `event_id`.
5. Если сообщение уже `processing` или `processed`, handler не вызывается.
6. Если сообщение новое или ранее `failed`, статус становится `processing`.
7. Бизнес-обработчик выполняет side effects.
8. При успехе inbox запись становится `processed`.
9. При ошибке запись становится `failed`, ошибка сохраняется в `last_error`, исключение пробрасывается наружу.

## Состояния

- `processing` - сообщение принято в работу, повторная доставка не запускает handler параллельно.
- `processed` - обработка успешно завершена.
- `failed` - обработка завершилась ошибкой и может быть повторена.

## Код

- `database/migrations/2026_05_20_000000_create_inbox_messages_table.php` - таблица `inbox_messages`.
- `app/Models/InboxMessage.php` - Eloquent record.
- `src/Application/Notifications/Inbox/IncomingMessage.php` - DTO входящего сообщения.
- `src/Application/Notifications/Ports/InboxMessageRepository.php` - Application port.
- `src/Application/Notifications/Commands/ProcessInboxMessageHandler.php` - use case для обработки входящего сообщения.
- `src/Infrastructure/Notifications/Events/EloquentInboxMessageRepository.php` - PostgreSQL/Eloquent реализация.
- `src/Infrastructure/Notifications/Events/InboxMessageStatus.php` - infrastructure enum статусов.

## Проверка

```bash
docker compose exec laravel.test php artisan test tests/Feature/InboxMessageIntegrationTest.php
```

Тесты проверяют два ключевых сценария:

- повторный `event_id` для того же consumer не вызывает обработчик второй раз;
- один `event_id` может быть обработан разными consumers независимо;
- `failed` сообщение может быть обработано повторно и перейти в `processed`.

## Связь С Outbox

Outbox и Inbox решают разные стороны надежной интеграции:

- Outbox гарантирует публикацию domain events из сервиса в Kafka.
- Inbox гарантирует идемпотентную обработку Kafka events, которые приходят в сервис.

Вместе они дают устойчивую модель для асинхронных процессов без протекания Kafka-деталей в Domain.
