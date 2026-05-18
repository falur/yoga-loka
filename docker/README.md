# Docker dev/test стенд

## Состав

Compose-файл `docker/docker-compose.dev.yml` поднимает:

- `app-http` - RoadRunner HTTP + RoadRunner jobs memory consumer в одном runtime.
- `temporal-worker` - отдельный RoadRunner runtime для Temporal worker.
- `test-runner` - минимальный test profile для `make test`.
- `postgres` и `postgres-init` - PostgreSQL 18.3 и повторяемое создание баз.
- `redis` - Redis 8.6 для cache/session и RoadRunner KV.
- `minio` и `minio-init` - S3-compatible storage и repeatable bucket bootstrap.
- `mailpit` - SMTP и web UI для локальной почты.
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
| temporal gRPC | `62333` |
| temporal UI | `62334` |

## Runtime-решения

PHP image строится на `ubuntu:26.04` и штатных пакетах Ubuntu `php8.5-*`.
Ondrej PHP PPA не используется, потому что для Ubuntu 26.04 suite `resolute`
не опубликован, а пакеты PPA для `noble` конфликтуют с библиотеками 26.04.

RoadRunner binary устанавливается в `/usr/local/bin/rr`, чтобы bind mount
репозитория не скрывал исполняемый файл. В image проверяются `redis`,
`pdo_pgsql`, `php8.5-redis`, `rr --version`, jobs и workers команды.

RoadRunner jobs остаются memory pipeline. Отдельный queue worker не создаётся:
memory-задачи доступны только внутри того RoadRunner runtime, который их
поставил. Поэтому HTTP и jobs consumer запущены вместе в `app-http`.

Temporal использует две базы: `temporal` и `temporal_visibility`. Это нужно
для корректной visibility-схемы `temporalio/auto-setup`.

MinIO закреплён digest-ом arm64-доступного образа релиза
`RELEASE.2025-09-07T16-13-09Z`. Hotfix-тег из плана не имеет arm64 manifest.

## Данные

Named volumes:

- `postgres-data` - dev/test/Temporal базы.
- `redis-data` - Redis AOF.
- `minio-data` - dev/test buckets.
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
- `temporal:7233`
- `centrifugo:8000`

Dev `STORAGE_DEFAULT=s3`, test `STORAGE_DEFAULT=s3-test`. Test cache остаётся
`CACHE_STORAGE=local`, потому что существующие unit-тесты проверяют этот режим.

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
