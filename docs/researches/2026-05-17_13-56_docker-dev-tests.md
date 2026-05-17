---
title: Docker для dev и тестов
date: 2026-05-17 13:56
mode: normal
decision_mode: recommend_and_ask
status: blocked
reviewer: none
sources:
  rules: docs/rules.md
  arch: docs/arch.md
---

# Docker для dev и тестов

## Суть

Исследуется будущая Docker-настройка для локальной разработки и тестов проекта YogaLoka: один compose-файл с именем `docker-compose.dev.yml`, Docker-артефакты в корневой папке `docker/`, сервисы для почты, S3, PostgreSQL, Redis, Temporal, Temporal UI, Centrifugo, очередей и HTTP.

Готовность для следующего шага: закрыть развилки по расположению compose-файла, составу сервисов, режиму запуска приложения, S3-эмулятору, очередям, версии PostgreSQL и разделению dev/test данных. Без этих ответов финальное решение будет слишком рискованным: оно затронет env-переменные, RoadRunner, хранение данных, тестовые сценарии и будущую стоимость поддержки.

## Решение

Предварительно выбранная рамка: делать не только набор внешних сервисов, а полноценный локальный dev/test stack вокруг Spiral + RoadRunner, но с профилями Compose для тяжёлых сервисов. Это соответствует архитектуре API-first backend на PHP 8.5, Spiral, RoadRunner и Cycle ORM (`docs/arch.md:5`), HTTP-потоку через RoadRunner (`docs/arch.md:91`), отдельным входам для Queue (`docs/arch.md:150`) и Temporal (`docs/arch.md:164`), а также outbox-подходу для email/Centrifugo/webhooks (`docs/arch.md:241`).

Предварительная схема:

```text
developer/test
  -> app/http container: PHP 8.5 + Composer + RoadRunner
     -> PostgreSQL: app database
     -> Redis: cache/session/queue backend if selected
     -> S3 emulator: LocalStack S3 or alternative
     -> Mailpit: SMTP + web UI for emails
     -> Temporal server + Temporal UI
     -> Centrifugo
     -> RoadRunner jobs / selected queue backend
```

Факты проекта:

| Факт | Источник | Влияние на Docker-решение |
|---|---|---|
| Режим исследования обычный, `decision_mode: recommend_and_ask` | `docs/settings.yaml:4` и `docs/settings.yaml:14` | Существенные инфраструктурные решения нужно подтвердить у пользователя. |
| Проект - API-first backend на PHP 8.5, Spiral, RoadRunner, Cycle ORM | `docs/arch.md:5` | HTTP-сервис должен запускать RoadRunner, а не отдельный nginx/apache по умолчанию. |
| Текущий RoadRunner слушает HTTP на `0.0.0.0:8080` | `.rr.yaml:4` | В compose нужно явно решить наружный порт, например `8080:8080`. |
| Temporal в `.rr.yaml` пока закомментирован | `.rr.yaml:26` | Для полноценного Temporal нужно менять конфиг RoadRunner/worker, не только compose. |
| Текущий `jobs.consume` пустой | `.rr.yaml:30` | Очереди сейчас не включены на уровне RoadRunner runtime. |
| `.env.sample` по умолчанию использует SQLite | `.env.sample:56` | Docker с PostgreSQL потребует отдельный env-профиль или изменение sample/env. |
| `.env.sample` по умолчанию использует `QUEUE_CONNECTION=in-memory` | `.env.sample:17` | Нужно решить: оставлять in-memory для dev/test или делать внешний backend. |
| `.env.sample` по умолчанию использует `CACHE_STORAGE=rr-local` | `.env.sample:20` | Redis есть в запросе, но приложение пока не выбрало Redis как cache backend. |
| S3-конфигурация в `storage.php` есть только как закомментированный пример | `app/config/storage.php:49` | Для S3 нужны env-переменные и включение сервера/bucket-а в конфиге. |
| В примере S3 уже учтён `use_path_style_endpoint` | `app/config/storage.php:111` | Это важно для MinIO/LocalStack и других S3-compatible dev-сервисов. |
| В правилах Centrifugo должен вызываться через собственный HTTP-клиент | `docs/rules.md:66` | Compose должен дать Centrifugo HTTP API endpoint и секреты. |
| Внешние эффекты должны идти через transactional outbox | `docs/rules.md:67` | Нужен worker для outbox/queue, иначе email/Centrifugo могут не отрабатываться локально. |
| Docker-папки в корне сейчас нет | локальная проверка `find . -maxdepth 3 -type d -name docker` | Папку `docker/` нужно будет создать при реализации. |

Проверенные внешние источники на 2026-05-17:

| Тема | Источник | Что важно |
|---|---|---|
| Docker Compose profiles | https://docs.docker.com/compose/how-tos/profiles/ | Тяжёлые сервисы вроде Temporal можно включать профилем, а базовый stack держать быстрым. |
| PostgreSQL Docker image | https://hub.docker.com/_/postgres/tags | Есть tag `postgres:18.3-bookworm`; при выборе PostgreSQL 18 важно закрепить Debian-suite tag, а не голый major/latest. |
| Temporal server | https://github.com/temporalio/temporal/releases | Актуальные server images есть в GitHub releases; найден release с Docker tag `1.29.6`. |
| Temporal UI | https://github.com/temporalio/ui/releases | UI версионируется отдельно от Temporal server. |
| Redis | https://github.com/redis/redis/releases и https://github.com/redis/redis | Последняя стабильная ветка в источнике отмечена как Redis `8.6.3`; pre-release `8.8-M*` не подходит для dev baseline. |
| Centrifugo | https://github.com/centrifugal/centrifugo/releases | Centrifugo активно версионируется отдельно; нужно закрепить tag после выбора. |
| LocalStack S3 | https://docs.localstack.cloud/user-guide/aws/s3/ | У LocalStack есть S3-only Docker image, но документация указывает, что S3-only image не поддерживает persistence. |
| LocalStack releases | https://github.com/localstack/localstack | Найден latest release `v4.14.0`; подходит как кандидат для S3-эмуляции, если нужна AWS-похожая среда. |
| MinIO | https://github.com/minio/minio | Репозиторий MinIO заархивирован 2026-04-25; historical binary releases больше не поддерживаются. |

Рекомендации по развилкам до ответа пользователя:

| Развилка | Рекомендация | Почему | Риск/ограничение |
|---|---|---|---|
| Где хранить compose-файл | `docker-compose.dev.yml` в корне, а `docker/` для Dockerfile, env, init scripts, service configs | Так compose удобно запускать из корня без `-f docker/...`, и одновременно выполняется требование хранить Docker-артефакты в `docker/` | В запросе можно прочитать иначе: сам compose тоже должен лежать в `docker/`. Нужно подтвердить. |
| Dev и test | Один compose-файл с профилями `dev`, `test`, `temporal`, возможно `app` | Пользователь просит "1 настройка"; profiles позволяют одной настройкой включать/выключать тяжёлые части | Если CI должен запускать всё одной командой без profiles, подход нужно упростить. |
| S3 | По умолчанию рассмотреть LocalStack S3 вместо MinIO | MinIO repository archived, LocalStack даёт AWS-похожую S3-эмуляцию | S3-only LocalStack без persistence; если нужны сохраняемые файлы между перезапусками, нужен полный LocalStack с volume или другой S3-compatible сервис. |
| PostgreSQL | Не менять на PostgreSQL 18 автоматически; сначала подтвердить: 15 как в `.env.sample` или 18 как текущий Docker tag | В `.env.sample` уже стоит `POSTGRESQL_VERSION=15`, но Docker Hub показывает 18.x tags | Major upgrade PostgreSQL меняет формат данных и может ломать volume; для чистого dev/test это проще, для долгоживущих локальных данных риск выше. |
| Очереди | Начать с RoadRunner jobs, но решить backend отдельно | В проекте уже есть RoadRunner Bridge и queue config | Redis как очередь не включён в текущий config; AMQP/Beanstalk/SQS примеры есть, Redis-коннектора в текущем config нет. |
| Temporal DB | Не использовать app DB без подтверждения; лучше отдельная БД/schema для Temporal | Temporal - инфраструктурный runtime, смешивать служебные таблицы с app schema неудобно | Увеличит compose-сложность: либо отдельный postgres service, либо отдельные databases в одном Postgres. |

## Ответы на вопросы

Пока ответов пользователя нет. Нужно ответить на вопросы ниже, чтобы финализировать исследование и затем перейти к `eda-plan`.

1. Где должен лежать сам compose-файл: в корне как `docker-compose.dev.yml` или внутри `docker/docker-compose.dev.yml`?
2. Под "всё хранилось в папке docker" вы имеете в виду только Dockerfile/config/init scripts/volumes templates, или ещё и compose-файл?
3. Docker должен поднимать PHP-приложение и RoadRunner тоже, или только внешние сервисы, а приложение запускается на host-машине?
4. Для dev/test нужен один и тот же набор сервисов или test должен быть урезанным и быстрым?
5. Нужны ли Docker Compose profiles, например базовый stack по умолчанию, а Temporal/Centrifugo/S3 через `--profile`?
6. Нужна ли поддержка CI, или только локальная разработка на машине разработчика?
7. Тесты должны сами стартовать compose, или разработчик/CI стартует его заранее?
8. Должны ли тесты очищать базы/бакеты/очереди автоматически между прогонами?
9. Данные dev-окружения должны сохраняться между `docker compose down/up`?
10. Для тестов данные должны быть ephemeral, то есть удаляться после каждого запуска?
11. PostgreSQL брать версии 15, как сейчас указано в `.env.sample`, или актуальную 18.x?
12. Нужны ли отдельные базы `app`, `app_test`, `temporal`, или достаточно одной базы и разных schema?
13. Нужен ли PostGIS/pgvector/uuid-ossp/другие расширения PostgreSQL?
14. Нужно ли пробрасывать PostgreSQL наружу на host, например `5432:5432`, или доступ только внутри Docker network?
15. Redis нужен только как cache, или ещё как session/lock/rate-limit/pubsub?
16. Redis должен быть обязательным для тестов или только для dev?
17. Нужен ли RedisInsight/UI для Redis, или достаточно контейнера Redis?
18. "Очереди" - это RoadRunner jobs, отдельный broker RabbitMQ/Beanstalk/SQS, Redis-backed queue, или пока не принципиально?
19. Почта: достаточно Mailpit/MailHog с web UI, или нужна проверка реального SMTP-поведения?
20. Нужно ли сохранять письма между перезапусками dev stack?
21. S3: нужна максимальная похожесть на AWS S3 или просто S3-compatible bucket для загрузки файлов?
22. S3-данные должны сохраняться между перезапусками?
23. Можно ли использовать LocalStack для S3, учитывая EULA/лицензионные условия, или предпочитаете MinIO/другой совместимый сервис?
24. Если S3 через LocalStack, нужен только S3 или потенциально позже пригодятся SQS/SNS/SES?
25. Нужно ли автоматически создавать bucket при старте compose?
26. Нужны ли public/private buckets отдельно?
27. Temporal нужен всегда при dev-start или только когда разрабатываем workflows/activities?
28. Temporal должен использовать отдельный Postgres service или общую PostgreSQL-инстанцию с отдельной БД?
29. Нужен ли Temporal admin-tools контейнер для `tctl`/`temporal` CLI?
30. Какое имя task queue должно быть каноническим: текущее `default`, `my-task-queue` из примера `Ping`, или новое имя проекта?
31. Centrifugo должен стартовать с включённым web admin UI?
32. Какие secrets/token для Centrifugo использовать в dev: фиксированные небезопасные значения или генерируемые через `.env`?
33. Нужно ли пробрасывать Centrifugo наружу для мобильного приложения/фронта на host-машине?
34. HTTP-сервис должен быть доступен на `localhost:8080`, как в `.rr.yaml`, или нужен другой порт?
35. Нужен ли Swagger/OpenAPI UI в Docker stack, или это отдельная задача?
36. Нужен ли отдельный worker container для queue/outbox, или RoadRunner jobs внутри app container достаточно?
37. Нужен ли отдельный Temporal worker container, или Temporal worker запускается внутри общего RoadRunner/app process?
38. Нужны ли healthchecks для всех сервисов, чтобы app стартовал только после готовности PostgreSQL/Redis/S3/Temporal?
39. Нужны ли init scripts в `docker/`, например создание БД, S3 bucket, Temporal namespace?
40. Нужен ли dev Dockerfile с PHP 8.5 extensions (`pdo_pgsql`, `sockets`, `redis`, `curl`) или PHP окружение уже считается установленным вне Docker?
41. Нужно ли запускать Composer install внутри контейнера или vendor остаётся с host-машины?
42. Нужен ли bind mount всего проекта в app container для live reload?
43. Нужна ли отдельная `.env.docker` / `.env.testing.docker`, или менять существующий `.env.sample`?
44. Можно ли менять `.rr.yaml`, `app/config/*.php` и `.env.sample` в рамках реализации Docker, если без этого сервисы не будут реально использоваться?
45. Нужно ли документировать команды запуска в README или отдельном `docker/README.md`?
46. Нужно ли оставлять поддержку запуска без Docker после внедрения?

## Итог

Исследование заблокировано до ответов пользователя. Предварительно стоит делать единый `docker-compose.dev.yml` с профилями, корневой папкой `docker/` для всех Docker-артефактов, app/http на RoadRunner, PostgreSQL, Redis, Mailpit, S3-эмулятор, Temporal + UI, Centrifugo и отдельный worker-подход для queue/outbox. Самые важные решения для подтверждения: расположение compose-файла, запуск app внутри Docker или на host, S3-эмулятор, версия PostgreSQL, backend очередей и разделение dev/test persistence.
