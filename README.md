# YogaLoka Spiral backend

API-first backend на PHP 8.5, Spiral Framework, RoadRunner, Cycle ORM и Temporal.

## Локальный запуск в Docker

1. Создайте `.env`, если его ещё нет:

```bash
cp .env.sample .env
```

2. Поднимите dev-стенд:

```bash
make up
```

3. Установите зависимости и при необходимости выполните миграции:

```bash
make composer-install
make migrate
```

4. Запустите проверки:

```bash
make test
make phpstan
```

Все Composer-команды и проверки запускаются внутри Docker, чтобы PHP 8.5,
расширения и RoadRunner были одинаковыми у всех разработчиков.

## Makefile

- `make up` - собрать image и поднять dev-стенд.
- `make down` - остановить контейнеры без удаления named volumes.
- `make restart` - перезапустить стенд.
- `make composer-install` - установить зависимости внутри Docker.
- `make migrate` - выполнить миграции dev-базы.
- `make test` - очистить test-данные и запустить тестовый профиль.
- `make phpstan` - запустить статический анализ внутри Docker.
- `make shell` - открыть shell в app-контейнере.
- `make shell CMD='php -v'` - выполнить команду в app-контейнере без интерактива.
- `make logs` - вывести последние логи сервисов.
- `make reset-test` - очистить только `yoga_loka_test` и bucket `yoga-loka-test`.

## Dev-сервисы

- API RoadRunner: `http://127.0.0.1:60080`
- PostgreSQL: `127.0.0.1:65432`
- Redis: `127.0.0.1:60379`
- Centrifugo admin/API: `http://127.0.0.1:60000`
- MinIO API: `http://127.0.0.1:60900`
- MinIO console: `http://127.0.0.1:60901`
- Mailpit SMTP: `127.0.0.1:61025`
- Mailpit UI: `http://127.0.0.1:60825`
- RabbitMQ AMQP: `127.0.0.1:60672`
- RabbitMQ management UI: `http://127.0.0.1:61672`
- Temporal gRPC: `127.0.0.1:62333`
- Temporal UI: `http://127.0.0.1:62334`

## Outbox и очередь

Внешние действия вроде email, push, Centrifugo и webhooks не вызываются прямо из
Handler-ов. Handler сохраняет outbox-сообщение в PostgreSQL, а relay после
commit-а перекладывает его в RabbitMQ.

Разовый запуск relay:

```bash
make shell CMD='php app.php outbox:relay 100'
```

Постоянный процесс для dev/prod:

```bash
make shell CMD='php app.php outbox:relay 100 --loop --sleep=1'
```

Запускайте только один постоянный relay-процесс. Текущая выборка использует
обычный `FOR UPDATE` без `SKIP LOCKED`, поэтому несколько relay-процессов
одновременно не являются поддерживаемым режимом.

Реальные email, push, Centrifugo и webhooks добавляются отдельными Job. Каждый
такой Job должен использовать `outboxId` как ключ идемпотентности, если внешний
сервис это поддерживает.

Подробности по контейнерам, volumes, env и диагностике: `docker/README.md`.
