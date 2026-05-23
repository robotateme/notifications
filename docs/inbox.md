# Inbox

Kafka может прислать одно сообщение повторно: consumer обработал event, но упал до commit offset.
После рестарта прилетит тот же event.

Без inbox бизнес-логика выполнится дважды.

Сервис хранит пару:

```text
event_id + consumer_name
```

в `inbox_messages`.

Поток:

```text
Kafka event
 -> inbox: processing
 -> business handler
 -> inbox: processed
```

Если handler упал, статус станет `failed`. Такой event можно прогнать снова.

Если event уже `processing` или `processed`, handler не запускается.

## Файлы

- `src/Application/Notifications/Commands/ProcessInboxMessageHandler.php`
- `src/Application/Notifications/Ports/InboxMessageRepository.php`
- `src/Infrastructure/Notifications/Events/EloquentInboxMessageRepository.php`
- `database/migrations/2026_05_20_000000_create_inbox_messages_table.php`

```bash
docker compose exec laravel.test php artisan test tests/Feature/InboxMessageIntegrationTest.php
```
