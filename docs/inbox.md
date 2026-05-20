# Inbox

Inbox нужен, когда сервис читает события из Kafka.

Kafka может прислать одно сообщение повторно: consumer обработал event, но упал до commit offset. После рестарта Kafka отдаст тот же event еще раз.

Без inbox бизнес-логика может выполниться дважды.

## Как работает

Входящее сообщение имеет `event_id`, обработчик имеет `consumer_name`.

Сервис хранит пару:

```text
event_id + consumer_name
```

в таблице `inbox_messages`.

Поток:

```text
Kafka event
 -> inbox: processing
 -> business handler
 -> inbox: processed
```

Если handler упал, статус становится `failed`. Такой event можно обработать снова.

Если event уже `processing` или `processed`, handler не запускается.

## Код

- `src/Application/Notifications/Commands/ProcessInboxMessageHandler.php`
- `src/Application/Notifications/Ports/InboxMessageRepository.php`
- `src/Infrastructure/Notifications/Events/EloquentInboxMessageRepository.php`
- `database/migrations/2026_05_20_000000_create_inbox_messages_table.php`

Проверка:

```bash
docker compose exec laravel.test php artisan test tests/Feature/InboxMessageIntegrationTest.php
```
