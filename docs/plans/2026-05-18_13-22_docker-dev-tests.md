---
title: Docker dev/test stack
date: 2026-05-18 13:22
mode: normal
plan_size: normal
decision_mode: recommend_and_ask
status: meta-reviewed
reviewer: none
meta_reviewers:
  - gpt-5.4-mini
  - gpt-5.3-codex
  - gpt-5.5
test_strategy: after_each_phase
logging_strategy: debug_precise
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  research: docs/researches/2026-05-17_13-56_docker-dev-tests.md
---

# План реализации

## Задача

Сделать полноценный Docker-стенд для локальной разработки и тестов YogaLoka: приложение на PHP 8.5 с RoadRunner HTTP и RoadRunner jobs в одном runtime для memory-очереди, отдельный Temporal worker, PostgreSQL, Redis, MinIO, Mailpit, Temporal, Temporal UI и Centrifugo. Результат готов, когда разработчик запускает dev-стенд через `make up`, команды Composer и проверки выполняются внутри Docker, тесты ходят в отдельную PostgreSQL-базу, а документация содержит рабочий порядок запуска.

## Контекст

Проект является API-first backend на PHP 8.5, Spiral, RoadRunner и Cycle ORM. HTTP-запросы обслуживает RoadRunner, очереди и Temporal являются отдельными runtime-входами, а конфигурация окружения должна идти через `app/config/*.php` и DI.

Сейчас в репозитории нет Docker-файлов и `Makefile`. Есть `.env.sample`, `phpunit.xml`, `.rr.yaml`, `composer.json`, `composer.lock` и README. `README.md` пустой.

`composer.json` требует `php >=8.5 <8.6`, `ext-mbstring`, `ext-sockets`, Spiral Framework, RoadRunner bridge, Temporal bridge, storage и mailer-компоненты. В `composer.lock` есть `league/flysystem` версии `3.33.0`, но нет S3-адаптера `league/flysystem-aws-s3-v3`.

`.env.sample` сейчас настроен на SQLite, локальные значения БД, `CACHE_STORAGE=rr-local`, `QUEUE_CONNECTION=in-memory`, `MAILER_DSN=null`, старые переменные Temporal infrastructure и `TEMPORAL_ADDRESS=127.0.0.1:7233`.

`phpunit.xml` сейчас использует SQLite, `QUEUE_CONNECTION=sync`, `CACHE_STORAGE=local` и не содержит тестовые значения PostgreSQL, MinIO, Mailpit, Temporal и Centrifugo. В тестах есть `CacheConfigBindingTest`, который ожидает `CACHE_STORAGE=local`, поэтому тестовая конфигурация кэша остаётся локальной до отдельного изменения этих тестов.

`.rr.yaml` уже запускает HTTP на `0.0.0.0:8080`, memory KV, metrics и jobs pool. В `jobs.consume` нет pipeline, а Temporal-секция закомментирована. `app/config/queue.php` содержит RoadRunner memory pipeline `memory` с connector name `local` и не содержит Redis-backed очередь.

`RoadRunner jobs memory` хранит задачи внутри одного RoadRunner runtime. Поэтому отдельный `queue-worker` не получит задачи, поставленные `app-http`. Рабочая схема для этого плана: HTTP и jobs consumer работают в одном RoadRunner runtime/container, а Redis не используется как брокер очереди.

`app/config/database.php` уже поддерживает `pgsql`, если расширение `pdo_pgsql` установлено. `app/config/storage.php` содержит только локальное storage и закомментированный пример S3. `app/config/cache.php` содержит `rr-local`, `local`, `file`, но не содержит Redis storage. `app/config/session.php` использует файловые сессии, хотя Spiral поддерживает `Spiral\Session\Handler\CacheHandler`. `app/config/mailer.php` принимает DSN из `MAILER_DSN`. `app/src/Endpoint/Temporal/Ping.php` закреплён за worker `my-task-queue`, а `.env.sample` задаёт `TEMPORAL_TASK_QUEUE=default`.

Файл `~/.ports` требует выделять минимальный свободный prefix для проекта. Prefix `1`-`5` занят, поэтому для `yoga-loka-spiral-2` используется prefix `6`.

Research зафиксировал `php:8.5-cli-bookworm`, но это решение заменено после уточнения пользователя. Базовый образ приложения должен быть `ubuntu:26.04`, потому что пользователь выбрал последнюю Ubuntu и пакетную установку PHP/extensions через APT. Остальные Docker-образы остаются закреплёнными: `postgres:18.3-bookworm`, `redis:8.6.3-trixie`, `axllent/mailpit:v1.30.0`, `centrifugo/centrifugo:v6.7.2`, `temporalio/auto-setup:1.29.6`, `temporalio/ui:2.49.1`, `quay.io/minio/minio:RELEASE.2025-09-07T16-13-09Z.hotfix.7aa24e772`.

Через Composer metadata 2026-05-18 проверен S3-адаптер `league/flysystem-aws-s3-v3`. Совместимая с текущим Flysystem 3 последняя стабильная версия: `3.34.0`. Версия `4.x` требует Flysystem 4 и расширяет объём апгрейда.

Для Redis нужен именно PHP extension `ext-redis`. Он ставится пакетно как `php8.5-redis` после подключения Ondrej PHP PPA. Сборка из исходников и `pecl install` не используются.

## Принятые решения

- Режим плана: `normal`. Источник: ответ пользователя `1` на вопрос о размере плана.
- Стратегия тестов: `after_each_phase`. Источник: `docs/settings.yaml`.
- Стратегия логирования: `debug_precise`. Источник: `docs/settings.yaml`.
- Docker-артефакты размещаются в `docker/`, основной compose-файл называется `docker/docker-compose.dev.yml`. Источник: research с ответом пользователя.
- Локальный `make up` поднимает весь dev-стенд: `app-http`, `temporal-worker`, `postgres`, `redis`, `minio`, `minio-init`, `mailpit`, `temporal`, `temporal-ui` и `centrifugo`. Источник: research с ответом пользователя, уточнено мета-ревью и ответом пользователя `пункт 1`.
- Отдельный `queue-worker` не создаётся в этой фазе. RoadRunner jobs memory работает только внутри одного RoadRunner runtime, поэтому HTTP и jobs consumer запускаются вместе в `app-http`. Источник: мета-ревью и ответ пользователя `пункт 1`.
- CI и `make test` используют тот же compose-файл и минимальный test profile/target: `test-runner`, `postgres`, `redis`, `minio`, `minio-init`, `mailpit`. UI-сервисы, Temporal UI и Centrifugo в test profile не входят. Источник: research и мета-ревью.
- PostgreSQL использует образ `postgres:18.3-bookworm` и три базы: `yoga_loka`, `yoga_loka_test`, `temporal`. Источник: research с ответом пользователя и проверенными образами.
- Создание PostgreSQL-баз выполняется idempotent ensure-скриптом, который можно запускать повторно на существующем named volume. Один только `docker-entrypoint-initdb.d` не считается достаточным. Источник: мета-ревью.
- Redis используется для cache/session/lock и не становится брокером очередей. Источник: research с ответом пользователя.
- Redis подключается через пакетный `ext-redis`: пакет `php8.5-redis` из Ondrej PHP PPA. Источник: ответ пользователя.
- Для Redis cache добавляется проектный cache storage на базе `Symfony\Component\Cache\Adapter\RedisAdapter` и `Symfony\Component\Cache\Psr16Cache`; session переводится на `Spiral\Session\Handler\CacheHandler` с Redis storage. Источник: мета-ревью и фактическая структура Spiral Cache/Session.
- RoadRunner lock использует Redis через RoadRunner lock plugin, совместимый с установленным `ext-redis`. Источник: research с ответом пользователя про Redis для lock.
- Очередь остаётся RoadRunner jobs in-memory через pipeline `memory`, Redis queue connector не добавляется. Источник: research, мета-ревью и ответ пользователя `пункт 1`.
- Имена RoadRunner queue pipeline, connector и consume синхронизируются: connection `in-memory`, pipeline `memory`, connector name `local`, consume указывает фактическую очередь, которую создаёт RoadRunner. Источник: мета-ревью и текущий `app/config/queue.php`.
- MinIO используется как S3-compatible storage с bucket-ами `yoga-loka` и `yoga-loka-test`. Источник: research с ответом пользователя.
- `STORAGE_DEFAULT` в dev/test env переводится на S3 bucket, иначе MinIO остаётся неиспользованным. Источник: мета-ревью.
- Для MinIO добавляется Composer-пакет `league/flysystem-aws-s3-v3:3.34.0`. Источник: подтверждение пользователя `1` и Composer metadata 2026-05-18.
- Почта идёт в Mailpit через SMTP DSN. Источник: research с ответом пользователя и текущий `app/config/mailer.php`.
- Temporal использует task queue `default`, а ping workflow переводится с `my-task-queue` на `default`. Источник: research с ответом пользователя и текущий конфликт между `.env.sample` и `Ping`.
- RoadRunner binary устанавливается внутри Docker image в `/usr/local/bin/rr`; host `./rr` не используется. Источник: research с ответом пользователя и мета-ревью.
- PHP runtime строится на `ubuntu:26.04` и пакетах `php8.5-cli`, `php8.5-redis`, `php8.5-pgsql`, `php8.5-mbstring`, `php8.5-sockets`, `php8.5-xml`, `php8.5-curl`, `php8.5-zip` и Composer. Источник: ответ пользователя и проверка Ubuntu 26.04.
- Ondrej PHP PPA добавляется в Dockerfile как обязательный источник PHP-пакетов. Источник: прямое указание пользователя.
- Для PHP-пакетов добавляется APT pinning к Ondrej PHP PPA, чтобы `php8.5-*` пакеты брались из ожидаемого источника, а не случайно смешивались со штатными пакетами Ubuntu. Источник: инженерное уточнение после пользовательского решения.
- RoadRunner runtime-конфиги разделяются: `app-http` использует конфиг HTTP + jobs memory consumer, `temporal-worker` использует отдельный Temporal-конфиг. Источник: мета-ревью.
- Composer и проверки запускаются через Docker, чтобы PHP-версия и расширения были едиными для разработки и CI. Источник: research с ответом пользователя.
- Порты используют prefix `6`: app `68080`, postgres `65432`, redis `66379`, centrifugo `68000`, minio `69000`, minio console `69001`, mailpit smtp `61025`, mailpit web `68025`, temporal `67233`, temporal ui `68233`. Источник: research и `~/.ports`.
- Compose читает переменные из явного env-файла: dev-значения фиксируются в `.env.sample`, а Makefile передаёт корневой `.env` через `--env-file`. Источник: мета-ревью.
- Dev-данные сохраняются в named volumes, тестовые данные изолируются в базе `yoga_loka_test` и bucket-е `yoga-loka-test`. Источник: research с ответом пользователя.
- `make reset-test` очищает только `yoga_loka_test` и `yoga-loka-test` через SQL reset и MinIO client, без удаления dev-БД и dev-bucket-а. Источник: мета-ревью.
- Миграции выполняются отдельной командой `make migrate`, а создание баз и buckets выполняется автоматически и повторяемо. Источник: research с ответом пользователя и мета-ревью.
- Документация делится на короткий блок в README и подробный `docker/README.md`. Источник: research с ответом пользователя.

## Целевой алгоритм

1. Разработчик создаёт корневой `.env` из `.env.sample`, затем запускает `make up`.
2. Make вызывает `docker compose -f docker/docker-compose.dev.yml --env-file <env-file>` с project name и переменными портов prefix `6`.
3. Compose собирает PHP image на базе `ubuntu:26.04`, подключает Ondrej PHP PPA, ставит PHP 8.5 и extensions через APT, затем кладёт RoadRunner binary в `/usr/local/bin/rr`.
4. Build проверяет `php -v`, `php -m`, наличие `redis` и `pdo_pgsql`, `rr --version` и наличие RoadRunner plugins `http`, `jobs`, `kv`, `temporal`, `metrics`, `lock`.
5. PostgreSQL стартует, healthcheck ждёт готовности, ensure-скрипт приводит базы `yoga_loka`, `yoga_loka_test` и `temporal` к существующему состоянию.
6. MinIO стартует, `minio-init` приводит bucket-ы `yoga-loka` и `yoga-loka-test` к существующему состоянию.
7. Redis, Mailpit, Temporal, Temporal UI и Centrifugo стартуют с закреплёнными портами и dev-секретами.
8. `app-http` запускает RoadRunner HTTP на `0.0.0.0:8080` внутри контейнера и jobs memory consumer в том же runtime. Наружу compose публикует только `127.0.0.1:60080`. Контейнер получает внутренние Docker hostnames: `postgres`, `redis`, `minio`, `mailpit`, `temporal`, `centrifugo`.
9. `temporal-worker` запускает отдельный RoadRunner runtime с Temporal worker на task queue `default`.
10. Приложение использует PostgreSQL для БД, Redis для cache/session/lock, MinIO как default storage, Mailpit для SMTP и Temporal по адресу `temporal:7233`.
11. Debug-логирование пишет в stdout контейнеров точные шаги старта, имена операций, healthcheck-состояния, reset-test шаги и ошибки без секретов.
12. Разработчик запускает `make composer-install`, `make migrate`, `make test`, `make phpstan`, `make shell`, `make logs` и получает одинаковое поведение на host и в CI.
13. Тесты запускаются в Docker, используют `DB_CONNECTION=pgsql`, базу `yoga_loka_test`, тестовый bucket `yoga-loka-test`, Mailpit и тестовые настройки кэша, которые не ломают текущие unit-тесты.

## Фазы выполнения

### 1. Docker image и compose-стенд

Цель: создать воспроизводимый Docker runtime для приложения и инфраструктуры.

Что сделать:
- Создать `docker/Dockerfile` для PHP runtime на `ubuntu:26.04`.
- Подключить Ondrej PHP PPA в Dockerfile через `software-properties-common` и `add-apt-repository ppa:ondrej/php`.
- Добавить APT pinning для PHP-пакетов Ondrej PHP PPA.
- Установить PHP 8.5 и extensions пакетами APT: `php8.5-cli`, `php8.5-pgsql`, `php8.5-mbstring`, `php8.5-sockets`, `php8.5-redis`, `php8.5-xml`, `php8.5-curl`, `php8.5-zip` и другие пакеты, которые потребуются Composer-зависимостям в текущем lock-файле.
- Не добавлять сборку `ext-redis` из исходников и не использовать `pecl install`; Redis extension должен приходить из пакета `php8.5-redis`.
- Настроить Composer внутри image и установку RoadRunner binary в `/usr/local/bin/rr`, чтобы bind mount проекта не скрывал binary.
- Проверять на build-этапе `apt-cache policy php8.5-cli php8.5-redis`, `php -v`, `php -m | grep redis`, `php -m | grep pdo_pgsql`, `dpkg -l | grep php8.5-redis`, `rr --version` и наличие RoadRunner plugins `http`, `jobs`, `kv`, `temporal`, `metrics`, `lock`.
- Создать `docker/docker-compose.dev.yml` с сервисами `app-http`, `temporal-worker`, `postgres`, `postgres-init`, `redis`, `minio`, `minio-init`, `mailpit`, `temporal`, `temporal-ui`, `centrifugo`.
- Добавить отдельные RoadRunner runtime-конфиги в `docker/rr/`: один для HTTP + jobs memory consumer, второй для Temporal worker.
- Добавить named volumes для PostgreSQL, Redis, MinIO, Temporal и runtime-директорий, которые должны переживать перезапуск.
- Добавить healthchecks для PostgreSQL, Redis, MinIO, Mailpit, Temporal и Centrifugo.
- Добавить idempotent `postgres-init` для создания баз `yoga_loka`, `yoga_loka_test`, `temporal` на новом и существующем PostgreSQL volume.
- Добавить конфиг Centrifugo в `docker/centrifugo/` с API и admin UI для dev-секретов.
- Добавить `minio-init` для idempotent создания bucket-ов `yoga-loka` и `yoga-loka-test`.
- Добавить debug-вывод init-контейнеров на русском языке: старт операции, успешное создание, уже существующий ресурс, ошибка без вывода секретов.

Результат: compose-файл собирает image и поднимает всю инфраструктуру с закреплёнными образами, healthchecks и повторяемым init.

Сценарии тестирования:
- Сборка PHP image проходит без обращения к host PHP.
- В контейнере есть `redis`, `pdo_pgsql`, Composer и `/usr/local/bin/rr`.
- PostgreSQL создаёт все три базы на новом volume и подтверждает их на существующем volume.
- MinIO создаёт оба bucket-а на новом volume и подтверждает их на существующем volume.
- Runtime-сервисы получают статус healthy, а init-контейнеры завершаются с кодом `0`.
- `app-http` видит внутренние Docker hostnames.

Проверка:
- `docker compose -f docker/docker-compose.dev.yml --env-file .env config`
- `docker compose -f docker/docker-compose.dev.yml --env-file .env build app-http`
- `docker compose -f docker/docker-compose.dev.yml --env-file .env up -d postgres postgres-init redis minio minio-init mailpit temporal centrifugo`
- `docker compose -f docker/docker-compose.dev.yml --env-file .env ps`

### 2. Синхронизация env, RoadRunner и app config

Цель: связать поднятые контейнеры с приложением, тестами и worker runtime.

Что сделать:
- Обновить `.env.sample` под Docker dev: PostgreSQL, Redis, MinIO, Mailpit, Temporal, Centrifugo, host-порты prefix `6`, внутренние Docker hostnames и dev-секреты.
- Добавить явный env-файл для compose: Makefile передаёт корневой `.env` через `--env-file`; `.env.sample` остаётся шаблоном для разработчика.
- Обновить `phpunit.xml` под Docker test: PostgreSQL база `yoga_loka_test`, `STORAGE_DEFAULT=s3-test`, тестовый bucket `yoga-loka-test`, Mailpit и `CACHE_STORAGE=local` для совместимости с текущим `CacheConfigBindingTest`.
- Создать RoadRunner-конфиг для `app-http`: HTTP на `0.0.0.0:8080` внутри контейнера, jobs memory consumer в том же runtime, metrics на container-safe address.
- Создать RoadRunner-конфиг для `temporal-worker`: Temporal address `temporal:7233`, task queue `default`, без HTTP и jobs consumer.
- Синхронизировать `app/config/queue.php` и RoadRunner jobs config: connection `in-memory`, pipeline `memory`, connector name `local`, consume указывает фактическую очередь.
- Перевести `App\Endpoint\Temporal\Ping` на worker `default`.
- Добавить S3 server и buckets в `app/config/storage.php`: dev bucket `yoga-loka`, test bucket `yoga-loka-test`, endpoint MinIO, path-style endpoint, credentials из env.
- Перевести `STORAGE_DEFAULT` в dev env на `s3` и в test env на `s3-test`.
- Добавить Composer dependency `league/flysystem-aws-s3-v3:3.34.0` и обновить lock-файл через Docker Composer.
- Добавить Redis cache storage в `app/config/cache.php` через проектный класс, который создаёт `Symfony\Component\Cache\Adapter\RedisAdapter` из Redis DSN и отдаёт PSR-16 cache через `Symfony\Component\Cache\Psr16Cache`.
- Перевести dev `CACHE_STORAGE` на Redis storage; test `CACHE_STORAGE` оставить `local` до отдельного изменения текущих unit-тестов.
- Перевести `app/config/session.php` на `Spiral\Session\Handler\CacheHandler` с Redis storage для dev.
- Добавить Redis-backed lock-настройку через RoadRunner lock plugin; Redis не подключать к очередям.
- Синхронизировать mailer DSN с Mailpit: `smtp://mailpit:1025`.
- Настроить debug-логирование так, чтобы RoadRunner, workers и init-команды писали подробные события старта, подключения и ошибок в stdout без паролей, токенов и ключей.

Результат: приложение в контейнерах использует поднятую инфраструктуру, а тестовый запуск изолирован от dev-базы и dev-bucket-а.

Сценарии тестирования:
- Приложение стартует через RoadRunner с Docker env.
- Jobs memory consumer работает в том же runtime, что HTTP.
- Temporal worker стартует на task queue `default`.
- `php app.php temporal:info` показывает зарегистрированный workflow на нужной очереди.
- Storage config создаёт S3 adapter для dev и test bucket.
- Redis cache storage создаётся и выполняет запись/чтение через `ext-redis`.
- Session config использует cache handler на Redis storage в dev.
- PHPUnit получает PostgreSQL test database из `phpunit.xml`, но текущие cache unit-тесты остаются совместимыми с `CACHE_STORAGE=local`.

Проверка:
- `docker compose -f docker/docker-compose.dev.yml --env-file .env run --rm app-http composer validate`
- `docker compose -f docker/docker-compose.dev.yml --env-file .env run --rm app-http composer install`
- `docker compose -f docker/docker-compose.dev.yml --env-file .env run --rm app-http apt-cache policy php8.5-cli php8.5-redis`
- `docker compose -f docker/docker-compose.dev.yml --env-file .env run --rm app-http php -m | grep redis`
- `docker compose -f docker/docker-compose.dev.yml --env-file .env run --rm app-http dpkg -l | grep php8.5-redis`
- `docker compose -f docker/docker-compose.dev.yml --env-file .env run --rm app-http php app.php configure --quiet`
- `docker compose -f docker/docker-compose.dev.yml --env-file .env run --rm app-http php app.php temporal:info`
- `docker compose -f docker/docker-compose.dev.yml --env-file .env run --rm app-http php app.php migrate --help`
- `docker compose -f docker/docker-compose.dev.yml --env-file .env logs app-http temporal-worker`

### 3. Makefile, порты и команды разработки

Цель: дать короткий и единый интерфейс для локальной разработки, проверок и CI.

Что сделать:
- Создать корневой `Makefile` с целями `up`, `down`, `restart`, `composer-install`, `test`, `phpstan`, `shell`, `logs`, `migrate`, `reset-test`.
- Добавить в Makefile единые переменные compose file, env file, project name, app service и test profile.
- Сделать `make composer-install` единственным документированным способом установки зависимостей для проекта.
- Сделать `make test` запуском тестов через Docker с test profile/target.
- Сделать `make phpstan` запуском `composer phpstan` внутри Docker.
- Сделать `make reset-test` очисткой только тестовой БД `yoga_loka_test` и тестового bucket-а `yoga-loka-test`: SQL reset схемы/таблиц через `psql` и очистка bucket-а через MinIO client.
- Добавить в `reset-test` явные проверки, что target database равен `yoga_loka_test`, а target bucket равен `yoga-loka-test`.
- Добавить секцию `[yoga-loka-spiral-2]` в `~/.ports` с prefix `6`, абсолютным путём проекта и всеми host-портами.
- Добавить `.env`-совместимые переменные портов в `.env.sample`, чтобы compose использовал `${APP_HOST_PORT:-68080}` и аналогичные fallback-значения.
- Добавить debug-вывод Makefile-команд: имя цели, compose project, service, итоговый код команды; секреты не выводить.

Результат: разработчик управляет стендом через Makefile, а host-порты зафиксированы в локальном реестре портов.

Сценарии тестирования:
- `make up` поднимает весь стек.
- `make down` останавливает стек и сохраняет named volumes.
- `make reset-test` не трогает dev-БД и dev-bucket.
- `make logs` показывает логи нужных сервисов.
- Повторный `make up` не ломается из-за уже существующих баз и buckets.

Проверка:
- `make up`
- `make logs`
- `make shell`
- `make reset-test`
- `make down`

### 4. Тестовый и CI-профиль

Цель: сделать проверки проекта воспроизводимыми в Docker и не зависящими от host PHP.

Что сделать:
- Добавить test profile/target в compose для `test-runner` и минимального набора инфраструктуры: PostgreSQL, Redis, MinIO, `minio-init`, Mailpit.
- Не включать UI-сервисы, Temporal UI и Centrifugo в test profile.
- Настроить `test-runner` на запуск `composer test`.
- Обеспечить очистку тестовой базы перед тестовым запуском через `make reset-test` и команду внутри `make test`.
- Добавить миграционный шаг для тестовой базы перед PHPUnit.
- Проверить существующие тесты `tests/Unit/DemoTest.php`, `tests/TestCase.php`, `tests/App/TestKernel.php` и `tests/Unit/Infrastructure/Configuration/CacheConfigBindingTest.php` на совместимость с Docker test env.
- Добавить минимальный smoke-тест конфигурации, который подтверждает boot приложения с Docker test env без подключения к dev-БД.
- Добавить smoke-проверку storage boot: получить `Spiral\Storage\StorageInterface`, открыть dev/test bucket по имени и выполнить безопасную запись/чтение/удаление тестового объекта в `yoga-loka-test`.
- Добавить smoke-проверку Redis cache в dev runtime: запись и чтение значения через cache storage, без влияния на `CACHE_STORAGE=local` в PHPUnit.
- Проверять HTTP runtime без добавления нового публичного API-контракта: выполнить запрос к `http://127.0.0.1:68080` и принять только ответ RoadRunner/Spiral от приложения, включая ожидаемый 404, но не connection refused и не пустой ответ.
- Логи тестового запуска оставить подробными: старт сброса тестовой БД, старт миграций, запуск PHPUnit, запуск PHPStan, итоговый статус.

Результат: `make test` и `make phpstan` выполняются внутри Docker, а тесты используют выделенную PostgreSQL-базу и тестовые сервисы без зависимости от host PHP.

Сценарии тестирования:
- Тестовая БД создаётся и очищается независимо от dev-БД.
- PHPUnit запускается с Docker env.
- PHPStan запускается в том же PHP runtime, что приложение.
- Composer scripts `test`, `phpstan`, `phpstan-rules:test` работают внутри контейнера.
- Storage smoke подтверждает доступ к `yoga-loka-test`.
- Redis smoke подтверждает работу `ext-redis` и Redis cache storage.

Проверка:
- `make composer-install`
- `make reset-test`
- `make test`
- `make phpstan`

### 5. Документация и эксплуатационная проверка

Цель: зафиксировать рабочий порядок запуска и обслуживания стенда.

Что сделать:
- Обновить README коротким блоком: требования, первый запуск, основные команды, URL сервисов.
- Создать `docker/README.md` с подробным описанием сервисов, портов, volumes, env-переменных, команд Makefile, reset test data, миграций и отладки.
- Описать, что dev-секреты Centrifugo, MinIO и PostgreSQL используются только локально.
- Описать правило: Composer, tests и PHPStan запускаются через Docker.
- Описать, что Redis используется для cache/session/lock и не является брокером очередей.
- Описать, что RoadRunner jobs memory работает в `app-http`, а отдельный `queue-worker` появится только после перехода на внешний queue broker.
- Описать, что Ondrej PHP PPA является источником PHP-пакетов в Docker image.
- Описать, что `ext-redis` ставится пакетно через `php8.5-redis` из Ondrej PHP PPA.
- Описать порядок диагностики по debug-логам контейнеров и Makefile-команд.
- Описать, какие volumes удалять для полного сброса dev-стенда.

Результат: новый разработчик может поднять стенд и выполнить проверки по документации без дополнительного исследования.

Сценарии тестирования:
- Первый запуск по README приводит к работающему RoadRunner HTTP runtime.
- URL Mailpit, MinIO console, Temporal UI и Centrifugo admin открываются на host-портах prefix `6`.
- Документация содержит команды для миграций, тестов, PHPStan и сброса тестовых данных.
- Документация явно разделяет dev reset и test reset.

Проверка:
- Выполнить команды из README в указанном порядке.
- Проверить HTTP runtime через `curl -i http://127.0.0.1:68080`.
- Открыть `http://127.0.0.1:68025`, `http://127.0.0.1:69001`, `http://127.0.0.1:68233`, `http://127.0.0.1:68000`.
- Финально выполнить `make test` и `make phpstan`.

## Тесты

Стратегия: `after_each_phase`. После каждой фазы исполнитель актуализирует тесты и проверочные команды для изменённого участка, затем запускает только релевантные проверки этой фазы. В конце плана запускаются полные `make test` и `make phpstan`.

Ключевые проверки:
- Docker config и build: `docker compose -f docker/docker-compose.dev.yml --env-file .env config`, `docker compose -f docker/docker-compose.dev.yml --env-file .env build app-http`.
- Runtime smoke: `make up`, `docker compose ps`, логи `app-http`, `temporal-worker`.
- PHP runtime и extensions: `apt-cache policy php8.5-cli php8.5-redis`, `php -v`, `php -m | grep redis`, `php -m | grep pdo_pgsql`, `dpkg -l | grep php8.5-redis`.
- RoadRunner binary: `/usr/local/bin/rr --version` и проверка plugins `http`, `jobs`, `kv`, `temporal`, `metrics`, `lock`.
- Composer: `make composer-install`, `composer validate` внутри Docker.
- Database: создание `yoga_loka`, `yoga_loka_test`, `temporal`, миграции для dev и test.
- Redis: cache write/read через Redis storage, session handler boot через `CacheHandler`, lock smoke через выбранную Redis-backed lock-настройку.
- Storage: создание bucket-ов `yoga-loka`, `yoga-loka-test`, boot S3 adapter и безопасная запись/чтение/удаление объекта в `yoga-loka-test`.
- Temporal: `php app.php temporal:info` и smoke запуска `temporal-worker`.
- Test suite: `make test`.
- Static analysis: `make phpstan`.
- Documentation run-through: команды README выполняются в указанном порядке.

## Логирование

Стратегия: `debug_precise`. Все новые runtime-скрипты, init-контейнеры и Makefile-команды дают подробный debug-вывод на русском языке: имя операции, сервис, целевой ресурс, начало, успешное завершение, повторный запуск для уже существующего ресурса и ошибка.

Секреты не попадают в логи: пароли PostgreSQL, access keys MinIO, токены Centrifugo и encrypter key не печатаются. RoadRunner, jobs consumer в `app-http` и Temporal worker пишут в stdout/stderr контейнеров, чтобы `make logs` показывал диагностику без входа в контейнер.

## Документация и эксплуатация

Обновить:
- `README.md`: короткий путь первого запуска, команды Makefile, URL dev-сервисов.
- `docker/README.md`: полная схема контейнеров, порты, volumes, env, миграции, тесты, сброс данных, debug-диагностика.
- `.env.sample`: все Docker dev/test переменные и host-порты prefix `6`.
- `.env`: явный env-файл, который Makefile передаёт в compose.
- `~/.ports`: локальная секция проекта `yoga-loka-spiral-2`.

Эксплуатационные условия:
- Dev volumes сохраняются между `make down` и `make up`.
- Полный сброс dev-данных делается отдельной явно описанной командой удаления volumes.
- Test reset очищает только `yoga_loka_test` и `yoga-loka-test`.
- Production secrets и production env не меняются этим планом.

## Изменения после мета-ревью

### После моделей
- **+ Добавлено:** реальное подключение Redis к cache/session/lock и отказ от декоративного Redis-сервиса.
- **+ Добавлено:** пакетная установка `ext-redis` через `php8.5-redis` на `ubuntu:26.04`.
- **+ Добавлено:** Ondrej PHP PPA как обязательный источник PHP-пакетов с APT pinning и проверкой `apt-cache policy`.
- **+ Добавлено:** решение по RoadRunner jobs memory: HTTP и jobs consumer работают в одном runtime, отдельный `queue-worker` не создаётся.
- **+ Добавлено:** раздельные RoadRunner runtime-конфиги для `app-http` и `temporal-worker`.
- **+ Добавлено:** idempotent `postgres-init`, `minio-init`, конкретный `reset-test`, Temporal smoke и Storage smoke.
- **~ Изменено:** test profile стал минимальным и не включает UI-сервисы, Temporal UI и Centrifugo.
- **~ Изменено:** `STORAGE_DEFAULT` явно переводится на S3 buckets для dev/test.
- **~ Изменено:** compose получает переменные через явный env-файл, а не только через `.env.sample`.
- **− Убрано:** отдельный `queue-worker` из рабочей схемы этой фазы.
- **Отклонено:** предложение заменить `ext-redis` на `predis/predis`, потому что пользователь подтвердил требование использовать именно PHP extension.
- **~ Изменено после уточнения пользователя:** базовый образ приложения заменён с `php:8.5-cli-bookworm` на `ubuntu:26.04`, Redis extension переведён с source-build на пакет `php8.5-redis`, а PHP-пакеты берутся из Ondrej PHP PPA.

## Прогресс выполнения
Журнал: `docs/executions/2026-05-18_14-08_docker-dev-tests.md`

- [x] Шаг 1: Docker image и compose-стенд
- [x] Шаг 2: Синхронизация env, RoadRunner и app config
- [x] Шаг 3: Makefile, порты и команды разработки
- [x] Шаг 4: Тестовый и CI-профиль
- [x] Шаг 5: Документация и эксплуатационная проверка

Журнал выполнения: `docs/executions/2026-05-18_14-08_docker-dev-tests.md`
