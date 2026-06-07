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
- `rabbitmq` - брокер очередей для RoadRunner jobs и transactional outbox.
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
`pdo_pgsql`, `php8.5-redis`, `rr --version`, jobs и workers команды.

RoadRunner jobs по умолчанию используют RabbitMQ pipeline. Memory pipeline
остаётся в конфигурации только для локальных экспериментов и обратной
совместимости.

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

Эта команда проверяет, что очищаются только база `yoga_loka_test` и bucket
`yoga-loka-test`.

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

RabbitMQ pipeline настраивается через env:

- `RABBITMQ_QUEUE_NAME`
- `RABBITMQ_QUEUE_PREFETCH`
- `RABBITMQ_QUEUE_DURABLE`
- `RABBITMQ_EXCHANGE_NAME`
- `RABBITMQ_EXCHANGE_TYPE`
- `RABBITMQ_EXCHANGE_DURABLE`
- `RABBITMQ_ROUTING_KEY`
- `RABBITMQ_REQUEUE_ON_FAIL`

## Команды

```bash
make up
make composer-install
make migrate
make test
make phpstan
make logs
make shell
```

Разово переложить pending outbox-события в RabbitMQ:

```bash
make shell CMD='php app.php outbox:relay 100'
```

Запустить relay в постоянном режиме:

```bash
make shell CMD='php app.php outbox:relay 100 --loop --sleep=1'
```

Постоянный relay должен быть один. Несколько процессов `outbox:relay --loop`
одновременно не поддерживаются, пока в выборке outbox-событий нет отдельного
контракта `SKIP LOCKED`.

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
Они должны добавляться отдельными Job через outbox. Если внешний сервис
поддерживает idempotency key, использовать `outboxId`.

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
