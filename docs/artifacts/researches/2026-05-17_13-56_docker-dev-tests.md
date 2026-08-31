---
title: Docker для dev и тестов
date: 2026-05-17 18:08
mode: normal
decision_mode: recommend_and_ask
status: draft
reviewer: none
sources:
  rules: docs/rules.md
  arch: docs/arch.md
---

# Docker для dev и тестов

## Суть

Исследовали Docker-настройку для локальной разработки и тестов YogaLoka: один compose-файл `docker/docker-compose.dev.yml`, все Docker-артефакты в корневой папке `docker/`, полный локальный стенд для HTTP, очередей, PostgreSQL, Redis, MinIO/S3, почты, Temporal, Temporal UI и Centrifugo.

Проблема не сводится к compose-файлу: текущий проект - API-first backend на PHP 8.5, Spiral, RoadRunner и Cycle ORM (`docs/arch.md:5`), HTTP работает через RoadRunner (`docs/arch.md:91`), очереди и Temporal являются отдельными runtime-входами (`docs/arch.md:150`, `docs/arch.md:164`), а внешние эффекты должны идти через transactional outbox (`docs/arch.md:241`, `docs/rules.md:67`). Поэтому выбран вариант полной настройки: Docker + env/test/RoadRunner/config-доработки, чтобы приложение реально использовало поднятые сервисы.

## Решение

Выбран единый Docker dev/test stack:

```text
make up
  -> app-http: PHP 8.5 + RoadRunner HTTP
  -> queue-worker: RoadRunner jobs worker
  -> temporal-worker: Temporal worker
  -> postgres: app, test и temporal databases
  -> redis: cache/session/lock
  -> minio: S3-compatible storage
  -> mailpit: SMTP + web UI
  -> temporal + temporal-ui
  -> centrifugo: API + admin UI

make test / CI
  -> тот же compose
  -> минимальный test profile/target: postgres, redis, minio, mailpit, test-runner
```

### Архитектурные решения

| Решение | Выбранный вариант | Источник/основание | Риск и закрытие |
|---|---|---|---|
| Расположение compose | `docker/docker-compose.dev.yml` | Ответ пользователя; требование хранить Docker в `docker/` | Команды длиннее, закрывается `Makefile`. |
| Локальный запуск | `make up` поднимает все контейнеры | Ответ пользователя | Стенд тяжелее, но локально он должен быть всегда поднят. |
| CI | Тот же compose, но минимальный test profile/target | Ответ пользователя | Нужно не стартовать UI/Temporal/Centrifugo без тестовой необходимости. |
| Полнота настройки | Docker + конфиги приложения | Ответ пользователя; `env()` допустим только в `app/config/*.php` (`docs/rules.md:46`) | Реализация шире compose, но иначе сервисы будут подняты и не использованы. |
| HTTP | RoadRunner app container | Архитектура HTTP-потока через RoadRunner (`docs/arch.md:91`) | RoadRunner binary ставить внутри Docker image, не брать host `./rr`. |
| Workers | Отдельные `queue-worker` и `temporal-worker` | Queue и Temporal описаны как отдельные входы (`docs/arch.md:150`, `docs/arch.md:164`) | Больше контейнеров, но проще рестарты и логи. |
| Очереди | RoadRunner jobs in-memory на первом шаге | Текущий queue config использует RoadRunner pipeline `memory` (`app/config/queue.php:42`) | Redis-backed очередь не включать: в текущем config нет Redis queue connector. |
| Redis | Cache/session/lock, не queue broker | Ответ пользователя после уточнения | Для очередей Redis вынести в отдельное исследование, если понадобится. |
| PostgreSQL | Один PostgreSQL 18, базы `yoga_loka`, `yoga_loka_test`, `temporal` | Ответ пользователя | Major 18 может ломать старые volumes; для новой dev-настройки риск принят. |
| Test isolation | Отдельная БД `yoga_loka_test` | Ответ пользователя | Тесты не портят dev-БД; нужен `make reset-test`. |
| S3 | MinIO, buckets `yoga-loka` и `yoga-loka-test` | Ответ пользователя; S3 config сейчас только пример (`app/config/storage.php:49`) | MinIO repo archived; используем pin конкретного Quay image tag и фиксируем риск. |
| Почта | Mailpit | Ответ пользователя; mailer config использует DSN (`app/config/mailer.php:15`) | Письма dev/test ловятся локально, не уходят наружу. |
| Temporal | Temporal server + UI, task queue `default`, данные в БД `temporal` | `.env.sample` уже содержит `TEMPORAL_TASK_QUEUE=default` (`.env.sample:80`) | Текущий `Ping` может использовать другое имя очереди; при реализации привести к `default`. |
| Centrifugo | API + admin UI, dev-секреты | Правило: Centrifugo через свой HTTP-клиент (`docs/rules.md:66`) | Секреты только локальные; не переносить в production. |
| Composer/vendor | Проект монтируется целиком вместе с `vendor/`; Composer всегда через Docker | Ответ пользователя | Host `vendor/` виден IDE; Composer запускается контейнерным PHP, чтобы не ловить расхождение PHP/ext. |
| Init | Автоматически создать БД и buckets; миграции отдельной командой | Ответ пользователя | Меньше магии при старте; нужна `make migrate`. |
| Docs | Кратко в README, подробно в `docker/README.md` | Ответ пользователя | Команды не теряются, детали не раздувают корневой README. |

### Порты

Порты брать из `~/.ports`: файл требует минимальный свободный prefix (`~/.ports:34`, `~/.ports:36`), вычисление host-порта по prefix и стандартному порту (`~/.ports:40`) и переменные в compose/env (`~/.ports:49`, `~/.ports:51`). Префиксы `1`-`5` уже заняты (`~/.ports:59`, `~/.ports:72`, `~/.ports:84`, `~/.ports:97`, `~/.ports:107`), поэтому выбран prefix `6`.

| Сервис | Host port | Container port |
|---|---:|---:|
| app/http | `68080` | `8080` |
| postgres | `65432` | `5432` |
| redis | `66379` | `6379` |
| centrifugo | `68000` | `8000` |
| minio | `69000` | `9000` |
| minio console | `69001` | `9001` |
| mailpit smtp | `61025` | `1025` |
| mailpit web | `68025` | `8025` |
| temporal | `67233` | `7233` |
| temporal ui | `68233` | `8080` |

При реализации добавить секцию `[yoga-loka-spiral-2]` в `~/.ports`, а в compose публиковать ports через переменные с fallback, например `${APP_HOST_PORT:-68080}:8080`.

### Проверенные версии и образы

Проверка выполнена 2026-05-17 по официальным Docker Hub/GitHub/Quay API.

| Компонент | Выбранный tag | Источник | Причина |
|---|---|---|---|
| PHP | `php:8.5-cli-bookworm` | https://registry.hub.docker.com/v2/repositories/library/php/tags/8.5-cli-bookworm | Проект требует PHP `>=8.5 <8.6` (`composer.json:18`). |
| PostgreSQL | `postgres:18.3-bookworm` | https://registry.hub.docker.com/v2/repositories/library/postgres/tags/18.3-bookworm | Пользователь выбрал PostgreSQL 18; tag закреплён без `latest`. |
| Redis | `redis:8.6.3-trixie` | https://registry.hub.docker.com/v2/repositories/library/redis/tags?page_size=20&name=8.6.3 и https://github.com/redis/redis/releases/tag/8.6.3 | Latest stable Redis release `8.6.3`; `bookworm` tag не найден, выбран опубликованный Debian-based `trixie`. |
| Mailpit | `axllent/mailpit:v1.30.0` | https://github.com/axllent/mailpit/releases/tag/v1.30.0 и https://registry.hub.docker.com/v2/repositories/axllent/mailpit/tags?page_size=5&name=v1.30.0 | Catch-all SMTP + web UI, tag опубликован. |
| Centrifugo | `centrifugo/centrifugo:v6.7.2` | https://github.com/centrifugal/centrifugo/releases/tag/v6.7.2 и https://registry.hub.docker.com/v2/repositories/centrifugo/centrifugo/tags?page_size=5&name=v6.7.2 | Latest stable release, tag опубликован. |
| Temporal server | `temporalio/auto-setup:1.29.6` | https://registry.hub.docker.com/v2/repositories/temporalio/auto-setup/tags?page_size=10&name=1.29 | Для dev проще auto-setup; latest server release `1.31.0` есть, но auto-setup tag `1.31.0` не найден. |
| Temporal UI | `temporalio/ui:2.49.1` | https://github.com/temporalio/ui/releases/tag/v2.49.1 и https://registry.hub.docker.com/v2/repositories/temporalio/ui/tags?page_size=20&name=2.49.1 | Latest UI release, tag опубликован. |
| MinIO | `quay.io/minio/minio:RELEASE.2025-09-07T16-13-09Z.hotfix.7aa24e772` | https://quay.io/api/v1/repository/minio/minio/tag/?limit=10 и https://github.com/minio/minio | Пользователь выбрал MinIO; GitHub repo archived, поэтому pin берётся из Quay registry tags, не `latest`. |

## Ответы на вопросы

| Вопрос | Ответ пользователя | Решение |
|---|---|---|
| Где лежит compose? | `В docker/` | `docker/docker-compose.dev.yml`. |
| Docker поднимает app или только infra? | `App + infra` | Поднимать приложение, workers и инфраструктуру. |
| Profiles нужны? | Для локалки полный стенд, для CI удобен profile | Локально всё без profiles, CI через минимальный test profile/target. |
| Локальный `up -d` что поднимает? | `Все контейнеры` | Поднимать весь dev-стенд. |
| Как изолировать тестовые данные? | `Отдельная БД` | `yoga_loka_test` в общем Postgres. |
| PostgreSQL version | `PostgreSQL 18` | Закрепить `postgres:18.3-bookworm`. |
| S3 provider | `MinIO` | Использовать MinIO вместо LocalStack. |
| Сохранять dev-данные? | `Dev сохранять` | Named volumes для dev state. |
| Queue backend | `RoadRunner jobs`; Redis-backed queue отложить | RoadRunner jobs in-memory сейчас. |
| Redis usage | Cache/session/lock + не queue | Redis не становится broker очередей в первом шаге. |
| Temporal DB | `Отдельная БД` | База `temporal` в общем Postgres. |
| Workers | `Queue + Temporal` | Отдельные queue и temporal worker containers. |
| Почта | `Mailpit` | SMTP + web UI. |
| Порты | `Возьми в ~/.ports` | Prefix `6`, секцию добавить в `~/.ports`. |
| Env strategy | `.env` в корне для dev, `phpunit.xml` для test | Не плодить `.env.dev/.env.test`; test overrides в `phpunit.xml`. |
| Можно менять конфиги приложения? | `Полная настройка с конфигами` | Менять `.env.sample`, `phpunit.xml`, `.rr.yaml`, `app/config/*.php` при реализации. |
| Buckets | `Два bucket` | `yoga-loka`, `yoga-loka-test`. |
| Centrifugo | `API + admin UI` | Включить API/admin UI с dev-секретами. |
| Код/vendor | Монтировать весь проект с `vendor/` | Bind mount `../:/app`; Composer запускать через Docker. |
| Composer локально/CI | `Всегда через Docker` | `make composer-install`, `make test`, `make phpstan` работают в контейнере. |
| Команды | `Makefile годится` | Добавить базовый Makefile. |
| Документация | `README + docker README` | Коротко в README, подробно в `docker/README.md`. |
| Init | `DB + buckets` | Базы и buckets авто, миграции отдельной командой. |
| Temporal task queue | `default` | `TEMPORAL_TASK_QUEUE=default`. |
| Healthchecks | `Строго для infra` | Healthchecks для критичной инфраструктуры. |
| RoadRunner binary | `Из Docker build` | Не использовать host binary. |
| Make targets | `Базовый набор` | `up`, `down`, `restart`, `composer-install`, `test`, `phpstan`, `shell`, `logs`, `migrate`, `reset-test`. |

## Итог

Следующий шаг - `eda-plan` на реализацию полного Docker dev/test stack. План должен исходить из одного compose-файла `docker/docker-compose.dev.yml`, полного локального стенда, минимального CI test profile/target, PostgreSQL 18 с тремя БД, Redis без очередей, RoadRunner jobs in-memory, MinIO с двумя buckets, Mailpit, Temporal auto-setup `1.29.6`, Temporal UI `2.49.1`, Centrifugo `6.7.2`, портового prefix `6` из `~/.ports` и полной синхронизации Docker с `.env.sample`, `phpunit.xml`, `.rr.yaml` и `app/config/*.php`.
