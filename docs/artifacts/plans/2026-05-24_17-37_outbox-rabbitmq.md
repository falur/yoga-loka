---
title: Outbox через RabbitMQ
date: 2026-05-24 17:37
mode: normal
plan_size: normal
decision_mode: recommend_and_ask
status: draft
reviewer: none
meta_reviewers: []
test_strategy: after_each_phase
logging_strategy: debug_precise
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  research: docs/researches/2026-05-24_17-25_outbox-rabbitmq.md
---

# План реализации

## Задача

Реализовать transactional outbox для внешних действий через PostgreSQL, RabbitMQ и Spiral Queue.

Готовый результат: бизнес-код сможет сохранить outbox-сообщение в той же транзакции, что и свои изменения; отдельный relay переложит это сообщение в default queue connection из общей queue-конфигурации, которое в dev указывает на RabbitMQ; общий queue interceptor будет менять статусы `queued`, `handled` и `failed`; первый технический Job только запишет debug-лог и проверит весь путь без реальных email, push или Centrifugo.

## Контекст

В проекте уже закреплено правило: внешние действия, например Centrifugo, email, push и webhooks, нельзя вызывать напрямую из Handler-ов. Их нужно сохранять в transactional outbox в той же транзакции, что и бизнес-изменение, а выполнять после commit-а отдельным worker-ом.

Текущая очередь настроена как RoadRunner memory pipeline: `app/config/queue.php` использует подключение `in-memory`, а `docker/rr/http-jobs.yaml` содержит pipeline `memory`. Для важных внешних действий это слабое место, потому что memory-очередь живёт только внутри текущего RoadRunner runtime.

В проекте уже установлен `spiral/roadrunner-jobs` версии `v4.7.0` из `composer.lock`. В `app/config/queue.php` уже импортирован `AMQPCreateInfo`, поэтому для RabbitMQ не нужен новый PHP-пакет. Но Dockerfile сейчас собирает RoadRunner только с plugin-ами `http`, `jobs`, `kv`, `temporal`, `metrics`, `lock`; для AMQP нужно добавить RoadRunner plugin `amqp`.

Версия RabbitMQ берётся из research: `rabbitmq:4.3.0-management-alpine`. Management-вариант нужен в dev-окружении для UI. Floating tag `latest` не использовать.

Пользователь подтвердил:

- размер плана: `normal`;
- схема `outbox_events`: базовая;
- первый реальный бизнес-Job не создавать;
- вместо него добавить тестовый Job, который пишет что-то в лог;
- `type` и `payload` не должны быть магическими строками и сырыми массивами.

Стратегия тестов из `docs/settings.yaml`: `after_each_phase`.

Стратегия логирования из `docs/settings.yaml`: `debug_precise`.

Мета-ревью плана показало важные факты из текущего кода:

- стандартный Spiral `JsonSerializer` не умеет восстанавливать объект по классу payload, поэтому для outbox нужен отдельный queue serializer;
- `QueueInterface::push()` в Spiral типизирован как приём array-payload, поэтому объект сообщения нельзя передавать напрямую через этот интерфейс;
- `app/config/queue.php` сейчас задаёт свой раздел `interceptors`, а Spiral объединяет config неглубоко через `array_merge`; значит дефолтные consume interceptor-ы нужно явно вернуть в config;
- `QUEUE_CONNECTION=sync` уже указан в `phpunit.xml`, поэтому relay должен быть защищён от сценария, когда sync-очередь выполнит Job до того, как relay запишет `queued`;
- `FOR UPDATE SKIP LOCKED` был исходным желаемым вариантом для нескольких relay-процессов, но текущие правила запрещают ручной SQL, а Cycle ORM Select не даёт отдельного API для `SKIP LOCKED`. Поэтому целевой контракт на этот этап: один постоянный relay-процесс, короткая claim-транзакция через `FOR UPDATE`, push в RabbitMQ после освобождения блокировок.

## Принятые решения

- Размер плана: `normal`. Источник: ответ пользователя `1 - 1`.
- Таблица `outbox_events` создаётся в базовом варианте: `id`, `type`, `payload`, `status`, `attempts`, `available_at`, `queued_at`, `handled_at`, `failed_at`, `last_error`, `created_at`, `updated_at`. Источник: ответ пользователя `2 - базовая`.
- Первый реальный бизнес-Job не создаётся. Вместо него создаётся техническое outbox-сообщение и Job, который пишет debug-лог. Источник: ответ пользователя по третьему вопросу.
- `type` хранит полное имя PHP-класса outbox-сообщения, например `App\Modules\System\Application\Outbox\Message\OutboxDebugLogMessage`. Это не произвольная строка. Value Object `OutboxEventType` проверяет, что класс существует и реализует контракт outbox-сообщения. Источник: уточнение пользователя про отсутствие магических констант.
- `payload` хранится как `jsonb`, но в коде не передаётся как ассоциативный массив. Общий serializer превращает объект outbox-сообщения в JSON и восстанавливает объект по классу из `type`. Источник: уточнение пользователя про JSON, который ходит туда-обратно как объект.
- Контракт outbox-сообщения вводится в `Modules/System/Application/Outbox`, например `OutboxMessage`. Конкретные сообщения являются `final readonly` DTO с типизированными полями и реализуют `JsonSerializable`.
- Для сериализации и восстановления объектов использовать существующую зависимость `cuyz/valinor` из `composer.json`, потому что проект уже использует её для typed config. Новые Composer-пакеты не добавлять.
- Outbox хранится внутри основного проекта, не отдельным Composer-пакетом. Источник: research.
- Технический код outbox размещается в модуле `System`, потому что это общий технический механизм приложения. Другие модули обращаются только к `System/Application` контракту записи outbox-сообщения, а не к таблице или инфраструктуре напрямую.
- Relay кладёт в очередь Job по имени класса Job Handler-а, а не по строковому alias. Это соответствует правилу проекта: queue push по FQCN класса.
- Через `QueueInterface` relay передаёт только внутренний transport array, который создаётся из `OutboxQueueEnvelope`. Это единственное место, где нужен массив, потому что так типизирован Spiral Queue. Job получает не массив, а объект сообщения после восстановления кастомным queue serializer-ом.
- В RabbitMQ-сообщение добавляются headers `outboxId` и `outboxType`. Тело queue-сообщения хранит JSON envelope, из которого consumer-side serializer восстанавливает объект outbox-сообщения.
- Job Handler-ы не меняют статус outbox вручную. Статус меняет общий consume interceptor по `outboxId`.
- Если queue-сообщение пришло повторно, а outbox-событие уже в финальном статусе `handled` или `failed`, interceptor пропускает выполнение Job и пишет debug-лог. Это закрывает часть риска дублей между RabbitMQ и PostgreSQL.
- Relay использует короткую claim-транзакцию: выбирает `pending` и просроченные `publishing` события через `FOR UPDATE`, переводит их в `publishing`, выставляет новый `available_at` как дедлайн claim-а и сразу отпускает блокировки. Push в RabbitMQ выполняется после commit-а claim-транзакции. Эксплуатационный контракт на этот этап — один постоянный relay-процесс.
- Статус `publishing` нужен, чтобы событие не зависло навсегда при падении relay между выборкой и push в RabbitMQ. Просроченный `publishing` снова доступен следующему relay.
- После успешного push relay переводит событие в `queued` только условным update-ом `WHERE status = publishing`. Если в тесте sync-очередь уже успела выполнить Job и interceptor поставил `handled`, relay не перезаписывает статус обратно в `queued`.
- `OutboxEventStore::add()` только создаёт Entity и вызывает `EntityManager::persist()`. Он не делает `EntityManager::run()`, commit или собственную транзакцию, чтобы outbox-событие сохранялось атомарно вместе с бизнес-изменением текущего Handler-а.
- Поля `queued_at`, `handled_at`, `failed_at`, `last_error` nullable только в базе. В Entity они представлены Value Object-ами с состоянием `none`, чтобы доменная модель не отдавала наружу `null`.
- `attempts` считает неуспешные попытки доставки: ошибки publish в RabbitMQ и ошибки выполнения Job. Источник ошибки хранится в коротком `last_error`; отдельная аналитика по типам ошибок не входит в этот план.
- Retry-контракт: `OutboxQueueStatusInterceptor` стоит перед `RetryPolicyInterceptor`, поэтому он видит `RetryException` как retry-сценарий. `RetryException` оставляет статус `queued`, любая другая ошибка после `RetryPolicyInterceptor` переводит событие в `failed`.
- Полный consume-chain в `app/config/queue.php`: `ErrorHandlerInterceptor`, `OutboxQueueStatusInterceptor`, `RetryPolicyInterceptor`. Это нужно явно, потому что проектный config сейчас переопределяет дефолты Spiral.
- FQCN в `type` считается контрактом хранения. Переименовывать классы outbox-сообщений можно только с миграцией старых строк или с временным `class_alias`.
- RabbitMQ и PostgreSQL не имеют общей транзакции. Дубли после сбоя между push в RabbitMQ и записью `queued` считаются нормальным свойством схемы. Все будущие реальные Job должны быть идемпотентными по `outboxId`.
- В dev Docker сразу добавляется RabbitMQ и AMQP-подключение RoadRunner. В тестовом контейнере очередь остаётся `sync`, чтобы `make test` не зависел от RabbitMQ там, где тест не проверяет RabbitMQ явно.
- Relay не выбирает RabbitMQ напрямую и не хранит отдельное имя queue-подключения в `OutboxConfig`. Он использует default queue connection из общей конфигурации `app/config/queue.php` через `QUEUE_CONNECTION`: в dev это `rabbitmq`, в тестах `sync`, в другом окружении — то, что задано там же. `OutboxConfig` хранит только настройки самого outbox, например `maxAttempts`.
- Несколько постоянных relay-процессов не поддерживаются, пока не появится отдельный безопасный контракт для `SKIP LOCKED` без нарушения правила про ручной SQL.
- AMQP-параметры задаются через env с безопасными dev-дефолтами: queue `yoga_loka_jobs`, exchange `yoga_loka_jobs`, routing key `yoga_loka_jobs`, durable queue/exchange, `requeueOnFail=false`. Retry выполняет Spiral `RetryPolicyInterceptor`, а не бесконечный nack-redelivery RabbitMQ.
- Стратегия тестов: после каждой фазы добавлять или обновлять тесты и запускать связанные проверки.
- Стратегия логирования: точные debug-логи на ключевых шагах outbox-алгоритма без секретов, персональных данных и полного содержимого payload.

## Целевой алгоритм

1. Application Handler будущего бизнес-сценария создаёт объект outbox-сообщения.
2. Handler вызывает `OutboxEventStore::add()` внутри своей обычной транзакции.
3. Store сериализует объект в JSON, сохраняет строку класса в `type`, JSON в `payload`, статус `pending`, `attempts = 0`, `available_at = now`, timestamps.
4. Store вызывает только `persist()` и не делает `run()`, commit или собственную транзакцию.
5. После commit-а relay запускается console-командой через Command Handler.
6. Relay открывает короткую транзакцию claim-а.
7. Repository берёт пачку `pending` и просроченных `publishing`-событий, у которых `available_at <= now`, в порядке `id ASC` с блокировкой `FOR UPDATE`.
8. В той же claim-транзакции repository переводит выбранные события в `publishing` и ставит `available_at = now + claimTimeoutSeconds`.
9. Claim-транзакция завершается до push в RabbitMQ, чтобы не держать блокировки БД во время сетевого вызова.
10. Для каждой claimed-строки relay восстанавливает объект outbox-сообщения по `type` и `payload`.
11. Relay находит Job Handler для класса сообщения через registry без строковых alias.
12. Relay создаёт `OutboxQueueEnvelope`, превращает его во внутренний transport array и отправляет Job через default queue connection из общей Spiral Queue-конфигурации, без отдельного outbox connection.
13. Queue options содержат headers `outboxId` и `outboxType`.
14. Для каждого outbox Job зарегистрирован `OutboxQueueSerializer`: на producer-side он сериализует transport array в JSON envelope, на consumer-side восстанавливает объект сообщения по типу payload параметра Job Handler-а.
15. После успешного push relay условно переводит outbox-событие в `queued` и пишет `queued_at`, только если текущий статус всё ещё `publishing`.
16. Если sync-очередь уже выполнила Job и статус стал `handled`, условный перевод в `queued` ничего не меняет, а relay пишет debug-лог.
17. Если push в настроенную очередь упал, relay увеличивает `attempts`, записывает `last_error`, ставит `pending` с backoff через `available_at` или `failed` при исчерпании `OutboxConfig::maxAttempts`.
18. RoadRunner jobs consumer получает задачу из настроенной queue pipeline.
19. Общий consume interceptor читает `outboxId` из headers.
20. Если headers нет, interceptor пропускает задачу как обычную не-outbox задачу.
21. Если outbox уже `handled` или `failed`, interceptor не запускает Job повторно и пишет debug-лог.
22. Если статус outbox не финальный, interceptor запускает Job Handler.
23. Технический Job для первой проверки получает объект тестового сообщения и пишет debug-лог с `outboxId`, `outboxType` и коротким текстом без полного payload.
24. После успешного Job interceptor ставит статус `handled`, пишет `handled_at`; если `queued_at` ещё пустой из-за sync-очереди, он заполняет и его.
25. Если Job падает и `RetryPolicyInterceptor` превращает ошибку в `RetryException`, outbox interceptor увеличивает `attempts`, пишет `last_error`, оставляет статус `queued`, пишет debug-лог и пробрасывает `RetryException` дальше.
26. Если Job падает финально, outbox interceptor увеличивает `attempts`, пишет `last_error`, ставит `failed`, пишет `failed_at`, пишет debug-лог и пробрасывает ошибку дальше.
27. RoadRunner подтверждает задачу после успешного завершения handler-а. При `RetryException` RoadRunner requeue-ит задачу, при финальной ошибке делает nack без бесконечного redelivery.

## Контракты реализации

### Данные и БД

Создать миграцию `app/database/migrations/YYYYMMDD_NNNNNN_create_outbox_events_table.php`.

Таблица `outbox_events`:

```text
id             uuid, not null, primary key
type           string(255), not null
payload        jsonb, not null
status         string(32), not null
attempts       integer, not null, default 0
available_at   datetime, not null
queued_at      datetime, nullable
handled_at     datetime, nullable
failed_at      datetime, nullable
last_error     text, nullable
created_at     datetime, not null
updated_at     datetime, not null
```

Индексы и ограничения:

- primary key: `id`;
- index: `status, available_at, id` для выбора pending-событий relay-ем;
- index: `type` для диагностики и будущих выборок по типу сообщения;
- index: `queued_at`;
- index: `failed_at`.

Старых данных нет, backfill не нужен.

Rollback удаляет таблицу `outbox_events`.

Доменные и инфраструктурные типы:

- `OutboxEventId` наследует общий UUID v7 Value Object;
- `OutboxEventType` хранит `class-string<OutboxMessage>`;
- `OutboxEventPayload` хранит JSON-строку и отдаёт её только serializer-у;
- `OutboxEventStatus` enum: `pending`, `queued`, `handled`, `failed`;
- `OutboxAttempts` Value Object для количества попыток;
- `OutboxLastError` Value Object для отсутствующей или короткой ошибки;
- `StoredOutboxEvent` Cycle Entity для строки таблицы;
- `OutboxEventCollection` типизированная коллекция для пачек relay-а;
- `OutboxEventRepository` с доменными методами, без прямого доступа к БД вне repository.

Методы repository:

- `findPendingForRelay(OutboxRelayBatchSize $batchSize, DateTimeImmutable $now): OutboxEventCollection`;
- `findById(OutboxEventId $id): ?StoredOutboxEvent`;
- `save(StoredOutboxEvent $event): void`.

Метод `findPendingForRelay()` должен использовать ORM Select и блокировку `FOR UPDATE`. `SKIP LOCKED` не входит в этот этап, потому что проектное правило запрещает ручной SQL, а Cycle ORM Select не даёт для него отдельного API. До отдельного решения по `SKIP LOCKED` эксплуатация должна запускать только один relay-процесс.

### API и внешние контракты

HTTP API не меняется.

Добавляется внутренний queue-контракт:

```text
job name: FQCN класса Job Handler-а
payload: JSON объекта, который реализует OutboxMessage
headers:
  outboxId: UUID v7 outbox_events.id
  outboxType: FQCN класса OutboxMessage
```

Payload не передаётся в Job как ассоциативный массив. Job получает типизированный объект сообщения.

Добавляется console-команда:

```text
php app.php outbox:relay {limit=100} {--loop} {--sleep=1}
```

Контракт команды:

- без `--loop` команда обрабатывает одну пачку и завершается;
- с `--loop` команда повторяет обработку пачек с паузой `--sleep`;
- `limit` ограничивает размер одной пачки;
- команда не содержит бизнес-логики, а только создаёт Application Command и передаёт его в Handler.

Добавляются env-переменные dev/runtime:

```text
QUEUE_CONNECTION=rabbitmq
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=yoga_loka
RABBITMQ_PASSWORD=yoga_loka_password
RABBITMQ_VHOST=/
RABBITMQ_QUEUE_NAME=yoga_loka_jobs
RABBITMQ_QUEUE_PREFETCH=100
RABBITMQ_QUEUE_DURABLE=true
RABBITMQ_EXCHANGE_NAME=yoga_loka_jobs
RABBITMQ_EXCHANGE_TYPE=direct
RABBITMQ_EXCHANGE_DURABLE=true
RABBITMQ_ROUTING_KEY=yoga_loka_jobs
RABBITMQ_REQUEUE_ON_FAIL=false
RABBITMQ_HOST_PORT=60672
RABBITMQ_MANAGEMENT_HOST_PORT=61672
```

RoadRunner:

- Dockerfile добавляет plugin `amqp`;
- `docker/rr/http-jobs.yaml` добавляет секцию `amqp.addr`;
- `jobs.pipelines.rabbitmq` использует driver `amqp`;
- `jobs.consume` переключается на `rabbitmq`;
- memory pipeline остаётся как запасной pipeline, но он не является default-подключением dev runtime.

`rabbitmq` здесь — значение default queue connection для dev runtime, а не отдельная настройка outbox. Если в другом окружении `QUEUE_CONNECTION` указывает на другое подключение, relay использует его через общий queue config.

RabbitMQ dev service:

- image: `rabbitmq:4.3.0-management-alpine`;
- AMQP port внутри Docker: `5672`;
- management UI внутри Docker: `15672`;
- host ports через prefix `6`: `60672`, `61672`;
- volume: `rabbitmq-data`;
- healthcheck через `rabbitmq-diagnostics -q ping`.

## Фазы выполнения

### 1. Подключить RabbitMQ и AMQP-очередь

Цель: заменить dev default queue с memory на RabbitMQ, не ломая тестовый запуск.

Что сделать:

- В `docker/Dockerfile` добавить RoadRunner plugin `amqp`.
- В `docker/docker-compose.dev.yml` добавить сервис `rabbitmq`, volume `rabbitmq-data`, healthcheck и host ports.
- Добавить `rabbitmq` в `depends_on` для `app-http`.
- В `test-runner` явно задать `QUEUE_CONNECTION=sync`.
- В `.env.sample` и локальном `.env` добавить переменные RabbitMQ и поменять dev `QUEUE_CONNECTION` на `rabbitmq`.
- В `docker/rr/http-jobs.yaml` добавить AMQP-настройки и pipeline `rabbitmq`.
- В `app/config/queue.php` добавить connection `rabbitmq`, pipeline `rabbitmq`, оставить `in-memory` для локальных экспериментов и обратной совместимости.
- Обновить typed config тесты queue-конфигурации, чтобы они проверяли наличие `rabbitmq` pipeline и AMQP connector.
- Обновить `docker/README.md`: состав сервисов, порты, env, диагностика RabbitMQ и пояснение, что memory больше не основная очередь.

Результат: dev runtime умеет работать с RabbitMQ через RoadRunner jobs, а тесты по умолчанию используют sync queue.

Сценарии тестирования:

- typed config читает `rabbitmq` connection и pipeline;
- default queue в dev-конфигурации берётся из `QUEUE_CONNECTION`;
- test-runner не требует RabbitMQ для обычного `make test`;
- Docker compose config валиден.

Проверка:

- `docker compose -f docker/docker-compose.dev.yml --env-file .env config`;
- `make test` после добавления тестов фазы;
- `make phpstan` после добавления типов фазы.

### 2. Добавить модель хранения outbox и сериализацию сообщений

Цель: сделать таблицу, Entity, Value Object, repository и serializer, которые хранят сообщения без магических строк и сырых массивов.

Что сделать:

- Создать миграцию `outbox_events`.
- Добавить контракт `OutboxMessage` для типизированных outbox-сообщений.
- Добавить `StoredOutboxEvent` Cycle Entity.
- Добавить `OutboxEventId`, `OutboxEventType`, `OutboxEventPayload`, `OutboxAttempts`, `OutboxLastError`.
- Добавить enum `OutboxEventStatus`.
- Добавить `OutboxEventCollection`.
- Добавить typecast для nullable дат, payload JSON и Value Object outbox-сущности.
- Добавить `OutboxMessageSerializer`, который:
  - принимает объект `OutboxMessage`;
  - сохраняет `type` как класс объекта;
  - превращает объект в JSON;
  - восстанавливает объект по `type` и JSON;
  - выбрасывает типизированное исключение на неизвестный класс, невалидный JSON или несовместимый payload.
- Добавить `OutboxEventRepository` с методами `findPendingForRelay()`, `findById()`, `save()`.
- Добавить `OutboxEventStore`, который создаёт `StoredOutboxEvent` из `OutboxMessage`.
- Зарегистрировать контракт store-а и serializer-а через bootloader.

Результат: приложение может сохранить outbox-сообщение в PostgreSQL и восстановить его обратно как объект.

Сценарии тестирования:

- `OutboxEventType` принимает только класс, который реализует `OutboxMessage`;
- serializer сохраняет и восстанавливает тестовое сообщение без ассоциативных массивов в публичном контракте;
- serializer падает на неизвестном классе;
- Entity сохраняется и восстанавливается через repository;
- `findPendingForRelay()` возвращает только `pending` с `available_at <= now`, сортирует по `id ASC` и не возвращает `queued`, `handled`, `failed`.

Проверка:

- `make test`;
- `make phpstan`.

### 3. Реализовать relay и общий queue status interceptor

Цель: переложить pending-события в настроенную очередь и централизованно менять статусы после обработки Job.

Что сделать:

- Добавить `OutboxJobRegistry`, который связывает класс `OutboxMessage` с классом Job Handler-а.
- Добавить `OutboxRelay`, который берёт pending-события, восстанавливает объекты сообщений, находит Job Handler и делает `QueueInterface::push()` в default queue connection с headers `outboxId`, `outboxType`.
- После успешного push переводить событие в `queued` и заполнять `queued_at`.
- При ошибке push оставлять событие `pending`, записывать короткий `last_error`, обновлять `updated_at` и писать debug-лог.
- Добавить `OutboxQueueStatusInterceptor` в consume chain.
- Interceptor должен:
  - пропускать обычные задачи без `outboxId`;
  - пропускать повторные сообщения для `handled` и `failed`;
  - после успешного Job ставить `handled` и `handled_at`;
  - при ошибке увеличивать `attempts`, писать `last_error`, `failed_at`;
  - при retry оставлять `queued`, при финальной ошибке ставить `failed`;
  - всегда пробрасывать ошибку дальше, чтобы RoadRunner сделал retry или nack.
- Добавить `RetryPolicyInterceptor` после outbox interceptor в consume chain, чтобы retry-ошибки проходили через общий статусный слой.
- Добавить точные debug-логи для взятия пачки, успешного push, ошибки push, пропуска дубля, успешной обработки и ошибки Job.

Результат: outbox-события проходят путь `pending -> queued -> handled` или `pending/queued -> failed` без ручного изменения статусов в каждом Job.

Сценарии тестирования:

- relay пушит Job по FQCN класса и добавляет headers `outboxId`, `outboxType`;
- relay использует default queue connection из `app/config/queue.php`, а не отдельное outbox-подключение;
- relay переводит событие в `queued` только после успешного push;
- при ошибке push событие остаётся `pending`;
- interceptor не трогает задачи без `outboxId`;
- interceptor пропускает уже `handled` событие;
- interceptor ставит `handled` после успешного Job;
- interceptor увеличивает `attempts`, пишет `last_error` и пробрасывает ошибку при падении Job.

Проверка:

- `make test`;
- `make phpstan`.

### 4. Добавить технический outbox Job для проверки

Цель: проверить весь путь без отправки реальных писем, push-уведомлений или Centrifugo-сообщений.

Что сделать:

- Создать тестовое сообщение `OutboxDebugLogMessage` с коротким текстом и временем создания.
- Создать Job Handler `OutboxDebugLogJob`.
- Job должен принимать `OutboxDebugLogMessage` как типизированный payload и писать debug-лог.
- Зарегистрировать связь `OutboxDebugLogMessage -> OutboxDebugLogJob` в `OutboxJobRegistry`.
- Для Job зарегистрировать JSON serializer, который умеет восстановить объект сообщения.
- Добавить console-команду `outbox:relay`, которая создаёт Application Command и запускает relay через Handler.
- В тестах создать `OutboxDebugLogMessage`, сохранить через store, запустить relay и проверить, что Job поставлен в очередь.
- Отдельно проверить прямой вызов Job: он получает объект сообщения и пишет лог без полного payload.

Результат: есть безопасный технический сценарий, который доказывает, что outbox, RabbitMQ-постановка, object payload и статусный interceptor совместимы.

Сценарии тестирования:

- тестовое сообщение сохраняется и восстанавливается как объект;
- relay находит для него правильный Job;
- Job получает объект, а не массив;
- Job пишет debug-лог с `outboxId` и типом сообщения;
- команда `outbox:relay` вызывает relay с заданным `limit`.

Проверка:

- `make test`;
- `make phpstan`.

### 5. Закрыть эксплуатацию и полный прогон проверок

Цель: подготовить изменение к запуску и ревью.

Что сделать:

- Обновить документацию по Docker runtime и очередям.
- Описать, как локально открыть RabbitMQ management UI.
- Описать, что реальные email, push, Centrifugo и webhooks в этот план не входят.
- Описать правило для будущих реальных Job: использовать `outboxId` как ключ идемпотентности там, где внешний сервис это позволяет.
- Проверить, что в плане реализации нет прямого вызова внешних интеграций из Handler-ов.
- Проверить, что новые логи не содержат секретов, персональных данных и полного payload.
- Запустить полный набор проверок.

Результат: плановая реализация готова к ревью и следующему шагу `eda-execute`.

Сценарии тестирования:

- документация совпадает с фактическими env-переменными и портами;
- Docker compose config валиден;
- приложение проходит полный тестовый набор;
- PHPStan проходит без новых нарушений.

Проверка:

- `docker compose -f docker/docker-compose.dev.yml --env-file .env config`;
- `make test`;
- `make phpstan`.

## Тесты

Стратегия: `after_each_phase`.

После каждой фазы нужно добавлять или обновлять тесты для изменённого поведения и запускать `make test`. После фаз с новыми типами, config DTO или сериализацией дополнительно запускать `make phpstan`.

Ключевые группы тестов:

- unit-тесты Value Object и enum outbox;
- unit-тесты `OutboxMessageSerializer`;
- feature-тесты repository и миграции через PostgreSQL;
- unit-тесты relay с fake queue или моками queue-соединения;
- unit-тесты `OutboxQueueStatusInterceptor`;
- feature-тест console-команды `outbox:relay`;
- config-тесты для RabbitMQ queue pipeline;
- smoke-проверка Docker compose config.

Финально выполнить:

```bash
docker compose -f docker/docker-compose.dev.yml --env-file .env config
make test
make phpstan
```

## Логирование

Стратегия: `debug_precise`.

Нужны debug-логи:

- relay начал обработку пачки: размер пачки и время;
- relay не нашёл pending-события;
- relay поставил событие в очередь: `outboxId`, `outboxType`, Job class;
- relay не смог поставить событие в очередь: `outboxId`, `outboxType`, короткий класс ошибки;
- interceptor пропустил обычную задачу без `outboxId`;
- interceptor пропустил дубль уже финального события;
- interceptor поставил `handled`;
- interceptor зафиксировал ошибку Job и новую попытку;
- тестовый Job получил сообщение и записал короткий debug-текст.

В логи нельзя писать:

- полный `payload`;
- секреты RabbitMQ;
- персональные данные;
- stack trace в debug-контексте. Ошибка пробрасывается дальше, а общий error handler уже решает, как её репортить.

## Документация и эксплуатация

Обновить:

- `.env.sample`;
- локальный `.env`, потому что dev runtime должен сразу подняться с RabbitMQ;
- `docker/README.md`;
- `README.md` со списком dev-сервисов и портов RabbitMQ.

Для релиза важно:

- RabbitMQ должен быть доступен приложению до старта `app-http`;
- RoadRunner binary должен быть собран с plugin `amqp`;
- в production нужно запустить ровно один relay-процесс через `php app.php outbox:relay --loop`;
- реальные внешние Job добавлять отдельными задачами после этого плана;
- каждый реальный внешний Job должен быть идемпотентным по `outboxId`;
- если внешний сервис поддерживает idempotency key, использовать `outboxId`.

## Прогресс выполнения

Журнал: `docs/executions/2026-05-25_15-36_outbox-rabbitmq.md`

- [x] Шаг 1: Подключить RabbitMQ и AMQP-очередь
- [x] Шаг 2: Добавить модель хранения outbox и сериализацию сообщений
- [x] Шаг 3: Реализовать relay и общий queue status interceptor
- [x] Шаг 4: Добавить технический outbox Job для проверки
- [x] Шаг 5: Закрыть эксплуатацию и полный прогон проверок

## Дополнительное выполнение 2026-06-06

Журнал: `docs/executions/2026-06-06_15-34_outbox-message-loader-flow.md`

- [x] Добавлен loader, который загружает бизнес-сообщение по `outboxId`.
- [x] Debug Job и бизнес-handler разделены: Job знает технический `outboxId`, handler получает только бизнес-данные.
- [x] Добавлен README модуля Outbox с кодовым flow подключения.
- [x] Покрытие `app/src/Modules/Outbox` закрыто полностью по строкам.
