# Inbox Pattern

Inbox нужен, когда сервис читает события из Kafka.

Kafka может прислать одно и то же сообщение больше одного раза. Это нормально для at-least-once доставки. Без inbox бизнес-логика может выполниться дважды.

Пример:

```text
1. Consumer получил event
2. Обработал его
3. Упал до commit offset
4. Kafka прислала тот же event снова
```

Inbox не дает повторно выполнить handler для того же события.

## Как работает

У каждого входящего события должен быть стабильный `event_id`. У каждого обработчика есть `consumer_name`.

Сервис хранит пару:

```text
event_id + consumer_name
```

в таблице `inbox_messages`.

Поток:

```text
Kafka event
 -> inbox_messages: processing
 -> business handler
 -> inbox_messages: processed
```

Если handler упал:

```text
inbox_messages: failed
```

Такое сообщение можно обработать снова при следующей доставке.

Если тот же event уже `processing` или `processed`, handler не запускается.

## Зачем consumer_name

Один и тот же `event_id` может быть нужен разным consumers.

Например:

```text
event_id = abc-123
consumer_name = delivery-status-consumer
consumer_name = analytics-consumer
```

Для каждого consumer-а обработка независимая.

## Где код

- `database/migrations/2026_05_20_000000_create_inbox_messages_table.php`
- `app/Models/InboxMessage.php`
- `src/Application/Notifications/Inbox/IncomingMessage.php`
- `src/Application/Notifications/Commands/ProcessInboxMessageHandler.php`
- `src/Application/Notifications/Ports/InboxMessageRepository.php`
- `src/Infrastructure/Notifications/Events/EloquentInboxMessageRepository.php`

Проверка:

```bash
docker compose exec laravel.test php artisan test tests/Feature/InboxMessageIntegrationTest.php
```

Коротко:

```text
Outbox - наши события не потерять до Kafka.
Inbox - чужие события из Kafka не выполнить дважды.
```
