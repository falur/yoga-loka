---
title: Второй черновой обзор Outbox-среза после исправлений
date: 2026-06-07 12:36
target: Outbox-slice of git diff HEAD after docs/review-fixes/2026-06-07_12-32_outbox-module-draft.md
plan: none
mode: draft
score: 84
status: meta-reviewed
meta_reviewers: [architecture-check (gpt-5.5), rules-check (gpt-5.5), quality-check (gpt-5.5)]
---

# Ревью: Второй черновой обзор Outbox-среза после исправлений

## Оценка

**84/100.** Обязательные замечания из прошлого ревью по статусам Job и состоянию рабочего дерева выглядят закрытыми. По публичному контракту Outbox-сообщений исправлена основная часть, но транспортный envelope всё ещё отдаёт наружу массивный контракт. Также остались архитектурный риск с логированием в `Application` и риск в настройке RabbitMQ vhost: дефолтное значение `/` работает, но нестандартный vhost может сломать запуск RoadRunner jobs.

Проверено:

- `make phpstan` — успешно.
- `make qa` — не запускался по прямому запрету пользователя.
- Coverage / проверки покрытия — не запускались по прямому запрету пользователя.
- `make test` в этом втором обзоре не запускался; в `docs/review-fixes/2026-06-07_12-32_outbox-module-draft.md` указан успешный запуск после фиксов.

## Проблемы сверки с планом

Проверка плана пропущена: пользователь явно указал ревью без плана.

## Замечания

### 1. `OutboxQueueEnvelope` оставляет массивный транспортный контракт в Application

`OutboxQueueEnvelope` лежит в `Application\Message`, но публично принимает и возвращает ассоциативные массивы через `fromTransport(array)`, `toTransport(): array` и `jsonSerialize(): array`. Это всё ещё спорит с правилом проекта про запрет ассоциативных массивов в публичных контрактах.

Главный контракт `OutboxMessage` после фиксов больше не заставляет каждое сообщение реализовывать `jsonSerialize()`, но envelope для очереди остался на границе Application и транспорта. Из-за этого формат payload очереди становится частью Application API, хотя сериализация и разбор transport payload должны быть технической деталью инфраструктуры.

Технические детали:

- **Тип:** `rules`
- **Рекомендация:** `править обязательно`
- **Где:** `app/src/Modules/Outbox/Application/Message/OutboxQueueEnvelope.php:31` — `fromTransport(array)`; `app/src/Modules/Outbox/Application/Message/OutboxQueueEnvelope.php:47` — `toTransport(): array`; `app/src/Modules/Outbox/Application/Message/OutboxQueueEnvelope.php:56` — `jsonSerialize(): array`; `app/src/Modules/Outbox/Infrastructure/OutboxQueueSerializer.php:16` и `app/src/Modules/Outbox/Infrastructure/OutboxQueueSerializer.php:35` — инфраструктура использует эти массивные методы.
- **Что подтверждает проблему:** публичный класс Application-слоя описывает transport payload как `array<string, string>`.
- **Как исправить:** оставить в Application только типизированный envelope/DTO с `OutboxEventId` и `OutboxEventType`, а преобразование в массив и обратно перенести в инфраструктурный сериализатор.

### 2. Application-сценарий debug-сообщения напрямую зависит от PSR-логгера

`ProcessOutboxDebugLogMessageHandler` находится в `Application`, но напрямую принимает `Psr\Log\LoggerInterface`. По архитектуре технические зависимости Application-слоя должны идти через контракт своего модуля или оставаться в инфраструктурном слое.

Сейчас debug-сообщение Outbox фактически превращает логирование в Application-сценарий без собственного контракта. Это не ломает runtime сразу, но закрепляет внешнюю техническую зависимость в слое сценариев.

Технические детали:

- **Тип:** `architecture`
- **Рекомендация:** `править обязательно`
- **Где:** `app/src/Modules/Outbox/Application/Command/ProcessDebugLogMessage/ProcessOutboxDebugLogMessageHandler.php:7` и `app/src/Modules/Outbox/Application/Command/ProcessDebugLogMessage/ProcessOutboxDebugLogMessageHandler.php:12`.
- **Что подтверждает проблему:** `Application` импортирует внешний `Psr\Log\LoggerInterface`, тогда как остальные Outbox-классы с прямым логгером находятся в `Infrastructure`.
- **Как исправить:** либо ввести контракт модуля для обработки debug-сообщения и реализацию в Infrastructure, либо оставить debug-логирование на технической границе Job/Infrastructure без отдельного Application Handler-а.

### 3. Нестандартный RabbitMQ vhost может создать один vhost, а подключаться к другому

Сейчас одна переменная `RABBITMQ_VHOST` используется сразу в двух разных местах: как имя vhost, который создаёт RabbitMQ, и как path-часть AMQP-адреса для RoadRunner. Для дефолтного значения `/` это выглядит рабочим, но для нестандартного vhost появляется риск рассинхронизации.

Если поставить `RABBITMQ_VHOST=/staging`, RabbitMQ получит имя vhost со слэшем, а в AMQP URL значение после порта станет path-частью адреса. Это другой формат, чем имя vhost в `RABBITMQ_DEFAULT_VHOST`. Если поставить `RABBITMQ_VHOST=staging`, RabbitMQ создаст ожидаемый vhost, но RoadRunner соберёт битый адрес `...:5672staging`. В обоих вариантах dev/runtime может не поднять jobs consumer, и outbox-события будут копиться без обработки.

Технические детали:

- **Тип:** `bug`
- **Рекомендация:** `править обязательно`
- **Где:** `.env.sample:29` — комментарий требует ведущий слэш для `RABBITMQ_VHOST`; `docker/rr/http-jobs.yaml:40` — переменная подставляется напрямую в AMQP URL; `docker/docker-compose.dev.yml:173` — та же переменная используется как `RABBITMQ_DEFAULT_VHOST`; `app/src/Modules/Outbox/README.md:134` — документация показывает значение `/`; `tests/Unit/Shared/Infrastructure/Configuration/RoadRunnerRabbitMqConfigTest.php:11` — тест проверяет параметры pipeline, но не проверяет `amqp.addr` и формат vhost.
- **Что подтверждает проблему:** одна переменная одновременно описывает имя vhost для брокера и URL path для клиента. Эти два представления совпадают только для дефолтного `/`, но расходятся для пользовательских vhost.
- **Как исправить:** разделить имя vhost и путь в AMQP URL, например `RABBITMQ_VHOST=staging` для RabbitMQ и отдельный `RABBITMQ_URL_VHOST=/staging` или собирать URL так, чтобы слэш добавлялся только в RoadRunner-конфиге. Для дефолтного vhost оставить корректный `/`. Добавить короткую проверку или документацию для случая `staging`, чтобы конфиг нельзя было снова сломать.

## Рекомендации

- **Править обязательно:** 1, 2, 3

## Изменения после мета-ревью

Режим: `normal` (strict: false). Запущены роли `architecture-check`, `rules-check`, `quality-check` на модели `gpt-5.5` одним batch. plan-check не запускался: план не указан в ревью (`plan: none`). Кросс-CLI не запускался (обычный режим).

### После architecture-check / rules-check / quality-check

- **+ Добавлено:**
  - Замечание 1 (`rules`, править обязательно): `OutboxQueueEnvelope` в `Application\Message` всё ещё публикует transport payload как ассоциативный массив.
  - Замечание 2 (`architecture`, править обязательно): `ProcessOutboxDebugLogMessageHandler` в `Application` напрямую зависит от `Psr\Log\LoggerInterface`.
  - Уточнение к замечанию 3: `RoadRunnerRabbitMqConfigTest` не проверяет `amqp.addr` и формат `RABBITMQ_VHOST`.
- **~ Изменено:**
  - Оценка снижена с 92 до 84, потому что после мета-проверки осталось три обязательных замечания вместо одного.
  - Замечание про RabbitMQ vhost переформулировано: проблема описана как конфликт имени vhost RabbitMQ и path-части AMQP URL, без утверждения про конкретный результат парсинга `/staging`.
  - Блок проверок уточнён: `make test` не запускался во втором обзоре, но успешный запуск после фиксов указан в `docs/review-fixes/2026-06-07_12-32_outbox-module-draft.md`.
- **− Убрано:**
  - Утверждение, что публичный контракт Outbox-сообщений полностью закрыт.
- **Отклонено:**
  - Предложение считать зависимость `OutboxBootloader` / `OutboxConsoleBootloader` от `Presentation` отдельным дефектом отклонено: для bootloader-ов в проекте уже есть похожий composition-root паттерн, поэтому нарушение недостаточно доказано.
  - Предложение считать незапущенный `make qa` или coverage дефектом отклонено по прямому запрету пользователя в текущей сессии.
  - Предложение требовать повторного подтверждения `make phpstan` отклонено: ревью фиксирует результат как уже проверенный, а текущая задача была мета-проверкой ревью, не повторным запуском всех команд.

## Применённые фиксы

Отчёт: `docs/review-fixes/2026-06-07_13-09_outbox-module-draft-iter2.md`
