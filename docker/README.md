# Docker dev/test стенд

## Состав

Compose-файл `docker/docker-compose.dev.yml` поднимает:

- `app-http` - RoadRunner HTTP + RoadRunner jobs RabbitMQ consumer в одном runtime.
- `temporal-worker` - отдельный RoadRunner runtime для Temporal worker.
- `test-runner` - минимальный test profile для `make test`.
- `postgres` и `postgres-init` - PostgreSQL 18.3 и повторяемое создание баз.
- `redis` - Redis 8.6 для cache/session и RoadRunner KV.
- `minio` и `minio-init` - S3-compatible storage и repeatable bucket bootstrap.
- `mailpit` - SMTP и web UI для локальной почты.
- `rabbitmq` - брокер очередей для RoadRunner jobs и доставок outbox.
- `temporal` и `temporal-ui` - Temporal Server и UI.
- `centrifugo` - realtime-сервис с dev admin/API config.

## Порты

Порты зарегистрированы в `~/.ports` в секции `[yoga-loka-spiral-2]`.
Часть портов из исходного плана была больше максимума TCP `65535`, поэтому
используется валидная схема `6xxxx`:

| Сервис | Host |
|---|---:|
| app-http | `60080` |
| postgres | `65432` |
| redis | `60379` |
| centrifugo | `60000` |
| minio API | `60900` |
| minio console | `60901` |
| mailpit SMTP | `61025` |
| mailpit web | `60825` |
| rabbitmq AMQP | `60672` |
| rabbitmq management | `61672` |
| temporal gRPC | `62333` |
| temporal UI | `62334` |

## Runtime-решения

PHP image строится на `ubuntu:26.04` и штатных пакетах Ubuntu `php8.5-*`.
Ondrej PHP PPA не используется, потому что для Ubuntu 26.04 suite `resolute`
не опубликован, а пакеты PPA для `noble` конфликтуют с библиотеками 26.04.

RoadRunner binary устанавливается в `/usr/local/bin/rr`, чтобы bind mount
репозитория не скрывал исполняемый файл. В image проверяются `redis`,
`pdo_pgsql`, `imagick`, `gd`, `php8.5-redis`, `rr --version`, jobs и workers команды.

Обработка изображений модуля `Media` использует Intervention Image v4. В образ ставятся
apt-пакеты `php8.5-imagick` (доступен в `resolute/universe`) и `php8.5-gd`. Основной драйвер —
imagick, GD остаётся фолбэком: драйвер выбирается значением `MEDIA_IMAGE_PROCESSING_DRIVER`
(`imagick` по умолчанию, `gd` — фолбэк) из `app/config/media.php`. Если под текущим базовым
образом apt-пакет imagick станет недоступен, альтернатива — собрать расширение через PECL с
`libmagickwand-dev`.

RoadRunner jobs читает три очереди назначения RabbitMQ — `mail`, `media` и
`notifications`. Memory pipeline остаётся подключением по умолчанию: очередь
каждой доставки задаёт маршрут outbox явно, поэтому подключение по умолчанию
используется только отправкой без объявленной очереди.

Temporal использует две базы: `temporal` и `temporal_visibility`. Это нужно
для корректной visibility-схемы `temporalio/auto-setup`.

MinIO закреплён digest-ом arm64-доступного образа релиза
`RELEASE.2025-09-07T16-13-09Z`. Hotfix-тег из плана не имеет arm64 manifest.

## Данные

Named volumes:

- `postgres-data` - dev/test/Temporal базы.
- `redis-data` - Redis AOF.
- `minio-data` - dev/test buckets.
- `rabbitmq-data` - очереди и metadata RabbitMQ.
- `temporal-data` - runtime config Temporal.
- `app-runtime`, `temporal-worker-runtime`, `test-runtime` - runtime-директории Spiral.
- `centrifugo-data` - локальные данные Centrifugo.

`make down` останавливает контейнеры и сохраняет volumes.

Полный сброс dev-стенда:

```bash
docker compose -f docker/docker-compose.dev.yml --env-file .env -p yoga-loka-spiral-2 down -v
```

Сброс только тестовых данных:

```bash
make reset-test
```

Команда поднимает тестовые сервисы и очищает только тестовые ресурсы: базовую
базу `yoga_loka_test` и базовый bucket `yoga-loka-test`, а при параллельном
запуске — ещё и worker-ресурсы `yoga_loka_test_1..4` / `yoga-loka-test-1..4`.
Перед очисткой скрипт проверяет точные имена баз и bucket-ов. Миграции
`reset-test` не выполняет — это делает `docker/test/migrate-test-databases.sh`.

## Env

`.env.sample` содержит Docker dev/test значения. Makefile всегда передаёт
корневой `.env` через `--env-file .env`.

Внутри compose приложение использует Docker hostnames:

- `postgres:5432`
- `redis:6379`
- `minio:9000`
- `mailpit:1025`
- `rabbitmq:5672`
- `temporal:7233`
- `centrifugo:8000`

Dev `STORAGE_DEFAULT=s3`, test `STORAGE_DEFAULT=s3-test`. Test cache остаётся
`CACHE_STORAGE=local`, потому что существующие unit-тесты проверяют этот режим.
Test queue остаётся `QUEUE_CONNECTION=sync`, чтобы `make test` не требовал
RabbitMQ там, где тест не проверяет очередь явно.

Приложение объявляет три очереди назначения — `mail`, `media` и `notifications`.
Имя очереди, exchange и routing key каждой собираются как `<префикс>_<очередь>`,
поэтому pipeline настраивается через общие env:

- `RABBITMQ_QUEUE_PREFIX`
- `RABBITMQ_QUEUE_PREFETCH`
- `RABBITMQ_QUEUE_DURABLE`
- `RABBITMQ_EXCHANGE_TYPE`
- `RABBITMQ_EXCHANGE_DURABLE`
- `RABBITMQ_REQUEUE_ON_FAIL`

## Команды

```bash
make up
make composer-install
make migrate
make test-unit      # быстрый suite Unit без kernel и внешних сервисов
make test-kernel    # suite Kernel (Spiral kernel/container) после reset
make test-feature   # suite Feature после reset, параллельно через ParaTest
make warmup         # пересобрать cache/cycle.php в тестовых runtime-каталогах
make test           # полный gate: один reset, один прогон Unit,Kernel,Feature (ParaTest)
make test-coverage  # покрытие через PCOV (порог 100%)
make qa             # стиль, PHPStan и один coverage-run без пересборки образа
make qa-build       # тот же QA с пересборкой образа
make phpstan
make logs
make shell
```

Параллельный запуск управляется `TEST_PARALLEL_PROCESSES` (1..4, по умолчанию 4).
Worker-и ParaTest изолированы по ресурсам: базы `yoga_loka_test_1..4`, bucket-ы
`yoga-loka-test-1..4` и runtime-каталоги `runtime/testing-1..4`. Подготовку
ресурсов выполняет `docker/test/prepare-parallel-resources.sh` (валидация лимита
и очистка БД) и `docker/minio/ensure-buckets.sh reset-test` (очистка bucket-ов);
миграции тестовых баз — единый владелец `docker/test/migrate-test-databases.sh`.
Покрытие собирается PCOV (`pcov.enabled=0` по умолчанию, coverage-команда включает
его через `php -d pcov.enabled=1` и пробрасывает в worker-ы ParaTest).

Выполнить один проход relay (создать доставки по маршрутам и отправить их в очереди):

```bash
make shell CMD='php app.php outbox:relay'
```

Запустить relay в постоянном режиме:

```bash
make shell CMD='php app.php outbox:relay --loop --sleep=1'
```

Размер пачки прохода задаёт приложение в секции конфигурации `outbox`, аргумента у команды нет.
В составе runtime постоянный relay один (`docs/arch.md`, раздел «Runtime»). Три пачечные выборки
прохода relay берут строки через `FOR UPDATE SKIP LOCKED`, поэтому второй процесс не заберёт чужую
строку и не задвоит доставку.

Показать состояние обмена — немаршрутизированные события, доставки по статусам и возраст
старейшей незакрытой доставки:

```bash
make shell CMD='php app.php outbox:status'
```

Команда только читает и на пустых таблицах не падает: счётчики печатаются нулями, а обе строки
возраста — прочерком «—».

Для one-shot команды в app-контейнере:

```bash
make shell CMD='php app.php temporal:info'
```

## Диагностика

Проверить compose:

```bash
docker compose -f docker/docker-compose.dev.yml --env-file .env config
docker compose -f docker/docker-compose.dev.yml --env-file .env ps
```

Проверить RoadRunner и Temporal:

```bash
curl -i http://127.0.0.1:60080
docker compose -f docker/docker-compose.dev.yml --env-file .env logs --tail=120 app-http temporal-worker
docker compose -f docker/docker-compose.dev.yml --env-file .env exec -T temporal temporal operator cluster health --address temporal:7233
```

Проверить RabbitMQ:

```bash
docker compose -f docker/docker-compose.dev.yml --env-file .env logs --tail=120 rabbitmq
docker compose -f docker/docker-compose.dev.yml --env-file .env exec -T rabbitmq rabbitmq-diagnostics -q ping
```

RabbitMQ management UI доступен на `http://127.0.0.1:61672`.
Dev-логин: `yoga_loka`, пароль берётся из `RABBITMQ_PASSWORD`.

Реальные email, push, Centrifugo и webhooks этот runtime не отправляет сам.
Они добавляются отдельными Job через outbox. Если внешний сервис поддерживает
idempotency key, использовать `outboxDeliveryId` из полезной нагрузки Job.

Проверить Redis cache:

```bash
docker compose -f docker/docker-compose.dev.yml --env-file .env run --rm app-http php docker/smoke/redis-cache.php
```

Проверить PHP runtime:

```bash
docker compose -f docker/docker-compose.dev.yml --env-file .env run --rm app-http php -m
docker compose -f docker/docker-compose.dev.yml --env-file .env run --rm app-http dpkg -l | grep php8.5-redis
```

Init-контейнеры пишут русскоязычный debug-вывод: начало операции, уже
существующий ресурс, создание ресурса и ошибку без секретов.
