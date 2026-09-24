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
make test-unit   # быстрый локальный цикл без kernel и внешних сервисов
make test        # полный тестовый gate через Docker
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
- `make phpstan` - запустить статический анализ внутри Docker.
- `make shell` - открыть shell в app-контейнере.
- `make shell CMD='php -v'` - выполнить команду в app-контейнере без интерактива.
- `make logs` - вывести последние логи сервисов.
- `make reset-test` - поднять тестовые сервисы и очистить тестовые БД и bucket-ы (без миграций).

## Тесты

Тесты разделены на три suite-а, чтобы быстрый локальный цикл не поднимал Spiral
kernel и внешние сервисы:

- `make test-unit` - быстрый запуск suite `Unit` (только `PHPUnit\Framework\TestCase`),
  без reset, БД, MinIO, Redis и kernel. Перед запуском `docker/test/assert-unit-suite-is-light.sh`
  проверяет, что в `tests/Unit` нет kernel-зависимостей.
- `make test-kernel` - suite `Kernel` (`tests/Kernel`, тесты на `Tests\TestCase` с kernel/container),
  после reset, миграций и прогрева Cycle schema.
- `make test-feature` - suite `Feature` после reset; запускается параллельно через ParaTest.
- `make warmup` - пересобрать `cache/cycle.php` в тестовых runtime-каталогах после
  изменений Entity или Cycle config.
- `make test` - полный тестовый gate: один reset и один прогон suite-ов `Unit`, `Kernel`,
  `Feature` через ParaTest, без двойного запуска.
- `make test-coverage` - покрытие через PCOV (ParaTest, suite-ы `Unit,Kernel,Feature`,
  Clover в `runtime/coverage/clover.xml`, порог 100%).
- `make qa` - стиль, PHPStan и один coverage-run без пересборки образа.
- `make qa-build` - тот же QA, но сначала пересобирает Docker image (например, после
  изменения `docker/Dockerfile`).

Параллельный запуск управляется переменной `TEST_PARALLEL_PROCESSES` (допустимые
значения `1`..`4`, по умолчанию `4`). Каждый worker ParaTest получает свою тестовую
базу (`yoga_loka_test_1` ... `yoga_loka_test_4`), свой MinIO bucket
(`yoga-loka-test-1` ... `yoga-loka-test-4`) и свой runtime-каталог
(`runtime/testing-1` ... `runtime/testing-4`); непараллельные запуски используют
базовые `yoga_loka_test` и `yoga-loka-test`. Значение вне диапазона `1..4` завершает
подготовку ресурсов до любой очистки.

Базовые классы для DB-тестов:

- `Tests\DatabaseTestCase` - обычный DB-тест: оборачивается в транзакцию с rollback
  в `tearDown()`, чистит ORM heap, по умолчанию подменяет storage fake-реализацией.
- `Tests\NonTransactionalDatabaseTestCase` - тесты, где нужен реальный commit, проход
  outbox relay, статусы доставок, console flow или явный транзакционный сценарий
  (без общего rollback).
- `Tests\RealStorageTestCase` - тесты, где реальный MinIO/S3 является предметом проверки
  (fake storage отключён, созданные объекты bucket чистятся в `tearDown()`).

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

Обмен держит пакет `gian-tiaga/spiral-outbox`. Внешние действия вроде email,
push, Centrifugo и webhooks не вызываются прямо из Handler-ов: Handler пишет
интеграционное событие в PostgreSQL в своей транзакции, а relay после commit-а
создаёт доставку на каждый маршрут события и отправляет её задачей в очередь
назначения RabbitMQ.

Один проход relay:

```bash
make shell CMD='php app.php outbox:relay'
```

Постоянный процесс для dev/prod:

```bash
make shell CMD='php app.php outbox:relay --loop --sleep=1'
```

Аргументов у команды нет — только опции `--loop` и `--sleep`; размер пачки
прохода задаёт приложение в секции конфигурации `outbox`.

В составе runtime постоянный relay один (`docs/arch.md`, раздел «Runtime»). Это
решение о составе процессов, а не ограничение выборки: три пачечные выборки
прохода relay берут строки через `FOR UPDATE SKIP LOCKED`, поэтому второй
процесс не заберёт чужую строку и не задвоит доставку.

Реальные email, push, Centrifugo и webhooks добавляются отдельными Job. Каждый
такой Job получает `outboxDeliveryId` в полезной нагрузке и использует его как
ключ идемпотентности, в том числе для внешнего сервиса, если тот поддерживает
idempotency key.

Подробности по контейнерам, volumes, env и диагностике: `docker/README.md`.
