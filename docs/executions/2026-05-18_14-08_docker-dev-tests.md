---
plan: docs/plans/2026-05-18_13-22_docker-dev-tests.md
started: 2026-05-18 14:08
status: completed
---

# Журнал: Docker dev/test stack

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Docker image и compose-стенд | `.dockerignore`, `docker/Dockerfile`, `docker/docker-compose.dev.yml`, `docker/postgres/ensure-databases.sh`, `docker/minio/ensure-buckets.sh`, `docker/rr/http-jobs.yaml`, `docker/rr/temporal-worker.yaml`, `docker/centrifugo/config.json` | `docker compose -f docker/docker-compose.dev.yml --env-file .env config`; `docker compose -f docker/docker-compose.dev.yml --env-file .env build app-http`; `docker compose -f docker/docker-compose.dev.yml --env-file .env up -d postgres postgres-init redis minio minio-init mailpit temporal centrifugo`; `docker compose -f docker/docker-compose.dev.yml --env-file .env ps -a`; `docker compose -f docker/docker-compose.dev.yml --env-file .env exec -T temporal temporal operator cluster health --address temporal:7233` | выполнено |
| 2 | Синхронизация env, RoadRunner и app config | `.env.sample`, `.env`, `composer.json`, `composer.lock`, `phpunit.xml`, `app/config/cache.php`, `app/config/session.php`, `app/config/storage.php`, `app/src/Endpoint/Temporal/Ping.php`, `app/src/Infrastructure/Cache/RedisCacheStorage.php`, `app/src/Infrastructure/Framework/Kernel.php`, `docker/smoke/redis-cache.php` | `composer update league/flysystem-aws-s3-v3 --with-dependencies` внутри Docker; `composer validate`; `composer install`; `apt-cache policy php8.5-cli php8.5-redis`; `php -m`; `dpkg -l`; `php app.php configure --quiet`; `php app.php temporal:info`; `php app.php migrate --help`; `docker compose up -d app-http temporal-worker`; `curl -i http://127.0.0.1:60080`; `php docker/smoke/redis-cache.php` | выполнено |
| 3 | Makefile, порты и команды разработки | `Makefile`, `.env.sample`, `.env`, `~/.ports` | `make down`; `make up`; `make logs`; `make shell CMD='php -v'`; `make reset-test` | выполнено |
| 4 | Тестовый и CI-профиль | `docker/test/run-tests.sh`, `tests/Feature/Infrastructure/DockerRuntimeSmokeTest.php`, `app/src/Infrastructure/Configuration/Cache/CacheStorageConfig.php`, `app/src/Infrastructure/Cache/RedisCacheStorage.php` | `make test`; `make phpstan` | выполнено |
| 5 | Документация и эксплуатационная проверка | `README.md`, `docker/README.md`, `docs/arch.md`, `docs/plans/2026-05-18_13-22_docker-dev-tests.md` | `make composer-install`; проверка URL `60080`, `60825`, `60901`, `62334`, `60000`; финально `make test && make phpstan` | выполнено |

## Заметки

- Часть портов из плана нельзя использовать в Docker, потому что TCP-порт не может быть больше `65535`: `68080`, `66379`, `68000`, `69000`, `69001`, `68025`, `67233`, `68233`. Для запускаемого стенда выбрана валидная схема `6xxxx`: app `60080`, redis `60379`, centrifugo `60000`, minio `60900`, minio console `60901`, mailpit web `60825`, temporal `62333`, temporal ui `62334`; секция `~/.ports` обновлена теми же значениями.
- RoadRunner lock plugin по официальной документации RoadRunner хранит локи in-memory и не имеет Redis storage-конфигурации. В стенде включён lock plugin через RPC, а Redis подключён для cache/session и RoadRunner KV.
- Temporal `auto-setup` требует отдельную visibility-схему. При использовании одной БД `temporal` для `DBNAME` и `VISIBILITY_DBNAME` сервер стартовал с ошибками `relation "executions_visibility" does not exist`; поэтому добавлена отдельная БД `temporal_visibility`.
- Ondrej PHP PPA не публикует suite `resolute` для `ubuntu:26.04`. Попытка закрепить PPA suite на `noble` тоже не подходит: пакеты зависят от библиотек Ubuntu 24.04 (`libxml2`, `libzip4t64`, `libicu74`) и не ставятся на 26.04. Чтобы сохранить выбранный базовый образ `ubuntu:26.04` и пакетную установку PHP/extensions, Dockerfile использует нативные пакеты Ubuntu 26.04 `php8.5-*`, включая `php8.5-redis`.
- Закреплённый в плане MinIO hotfix-тег `RELEASE.2025-09-07T16-13-09Z.hotfix.7aa24e772` не имеет `linux/arm64/v8` manifest. Для локального запуска на этой машине используется официальный arm64-доступный образ того же релиза `RELEASE.2025-09-07T16-13-09Z`, закреплённый digest `sha256:14cea493d9a34af32f524e538b8346cf79f3321eff8e708c1e2960462bd8936e`.
- PHPUnit завершается кодом `0`, но помечает существующие тесты как `Risky` из-за зарегистрированных error/exception handlers Spiral/YiiErrorHandler. Это не связано с Docker-изменениями и требует отдельной задачи, если нужно убрать предупреждения.
- После уточнения пользователя внутренний HTTP listener `app-http` оставлен на контейнерном `8080`; наружу compose публикует только `127.0.0.1:60080 -> 8080`.

## Изменения в docs

- `README.md`: добавлен короткий порядок запуска, Makefile-команды и URL локальных сервисов.
- `docker/README.md`: добавлена подробная документация по compose-стенду, volumes, env, диагностике и отклонениям от плана.
- `docs/arch.md`: добавлен блок про локальный Docker-runtime, RoadRunner jobs memory и инфраструктурные сервисы.
- `docs/plans/2026-05-18_13-22_docker-dev-tests.md`: отмечены выполненные шаги и добавлена ссылка на этот журнал.
