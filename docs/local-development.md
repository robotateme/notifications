# Локальный запуск

Требования: Docker, Docker Compose, Make.

## Старт

```bash
make up
```

- `laravel.test` - API;
- `pgsql` - PostgreSQL;
- `redis` - queue/cache locks;
- `kafka` и `kafka-ui`;
- `queue.worker` - отправка уведомлений;
- `outbox.publisher` - публикация outbox events в Kafka.

- API: `http://localhost/api`
- Kafka UI: `http://localhost:8081`
- Metrics: `http://localhost/metrics`

## Команды

```bash
make status
make logs
make test
make validate
```

## Docker права

App-контейнеры `laravel.test`, `queue.worker` и `outbox.publisher` запускаются не от `root`.
Через `make` UID/GID берутся с хоста, поэтому файлы в проекте остаются твоими.

```bash
make restart
```

При прямом запуске `docker compose` передай UID/GID явно:

```bash
WWWUSER=$(id -u) WWWGROUP=$(id -g) docker compose up -d --build
```

Без UID/GID compose стартует, но права на bind mount уже не гарантируются.

Laravel внутри слушает `8000`, снаружи это `http://localhost`.

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
