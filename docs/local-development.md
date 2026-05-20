# Локальный запуск

Требования: Docker, Docker Compose, Make.

## Старт

```bash
make up
```

Поднимаются:

- `laravel.test` - API;
- `pgsql` - PostgreSQL;
- `redis` - queue/cache locks;
- `kafka` и `kafka-ui`;
- `queue.worker` - отправка уведомлений;
- `outbox.publisher` - публикация outbox events в Kafka.

Адреса:

- API: `http://localhost/api`
- Kafka UI: `http://localhost:8081`
- Metrics: `http://localhost/metrics`

## Основные команды

```bash
make status
make logs
make test
make validate
make down
```

## БД

```bash
make migrate
make fresh
make fresh-seed
```

## Ручные процессы

```bash
make queue
make outbox
make outbox-dead LIMIT=50 PAGE=1
make outbox-retry-dead ID=<outbox-id>
```

Worker слушает очереди в порядке:

```text
notifications-high,notifications
```
