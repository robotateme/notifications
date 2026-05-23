# Нагрузка

k6 гоняет HTTP API под нагрузкой. Сценарий: [tests/load/notifications.js](../tests/load/notifications.js).

## Запуск

```bash
make up
make load-test-docker
```

Локальный k6:

```bash
make load-test
```

Если `k6` не стоит локально, используй `make load-test-docker`.

- `notifications-<run-id>.html` - отчет для просмотра в браузере;
- `notifications-<run-id>.json` - summary k6;
- `latest.html` и `latest.json` - последний прогон.

```bash
make load-report
```

## Параметры

База: `BASE_URL=http://localhost/api`, `RATE=5`, `DURATION=30s`.

Больше нагрузки:

```bash
RATE=30 DURATION=3m PRE_ALLOCATED_VUS=20 MAX_VUS=100 make load-test-docker
```

Env: `BASE_URL`, `RATE`, `DURATION`, `PRE_ALLOCATED_VUS`, `MAX_VUS`, `BULK_RECIPIENTS`,
`REPORT_RUN_ID`.

## Профиль

- 70% - создание одиночных уведомлений через `POST /api/notifications`;
- 20% - создание bulk-уведомлений через `POST /api/notifications/bulk`;
- 10% - чтение истории подписчика через `GET /api/subscribers/{subscriber}/notifications`;
- часть single-запросов шлет callback в `POST /api/notifications/{notification}/delivery-status`.

Пороги:

- доля HTTP-ошибок меньше 1%;
- `p95` всех HTTP-запросов меньше 750 ms;
- `p99` всех HTTP-запросов меньше 1500 ms;
- `p95` bulk-запросов меньше 1500 ms;
- доля ошибок delivery callback меньше 2%.
