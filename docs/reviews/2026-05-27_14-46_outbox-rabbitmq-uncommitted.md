---
title: Ревью незакоммиченных изменений outbox RabbitMQ
date: 2026-05-27 14:46
target: git diff HEAD + untracked files
mode: normal
score: 72
status: meta-reviewed
meta_reviewers:
  - plan-check:gpt-5.3-codex
  - architecture-check:gpt-5.3-codex
  - rules-check:gpt-5.3-codex
  - quality-check:gpt-5.3-codex
---

# Ревью: незакоммиченные изменения outbox RabbitMQ

## Оценка

**72/100.** Остался риск в `sync`-сценарии ошибок Job, есть нарушение правил вызова методов, а архитектурная документация по runtime противоречит новой RabbitMQ-схеме. Полные проверки в этом запуске не подтвердились из-за недоступного Docker daemon.

## Проблемы сверки с планом

### 1. Документация Docker runtime противоречит новой схеме очередей

Статус: частично

Контекст:
План требовал заменить dev default queue с memory на RabbitMQ и обновить документацию по Docker runtime и очередям.

Проблема:
`README.md` и `docker/README.md` уже описывают RabbitMQ, но `docs/arch.md` в разделе локального Docker-runtime всё ещё говорит, что `app-http` запускает RoadRunner jobs memory consumer и что memory-очередь остаётся причиной не выносить worker отдельно. В этом же блоке `app-http` описан дважды, а RabbitMQ не указан в списке локальной инфраструктуры.

Риск:
`docs/arch.md` — основной архитектурный документ проекта. Если он оставляет старую memory-схему, следующий разработчик может настроить runtime или эксплуатацию не по фактической RabbitMQ-схеме.

Где:

- `docs/plans/2026-05-24_17-37_outbox-rabbitmq.md:244` — цель заменить memory на RabbitMQ
- `docs/plans/2026-05-24_17-37_outbox-rabbitmq.md:385` — требование обновить документацию runtime
- `docs/arch.md:167` — первый пункт `app-http` всё ещё описывает jobs memory consumer
- `docs/arch.md:168` — второй пункт повторно описывает тот же `app-http`
- `docs/arch.md:173` — всё ещё объясняется, почему memory-очередь не выносится отдельно
- `docs/arch.md:178` — в локальной инфраструктуре RabbitMQ не указан
- `docker/README.md:49` — уже описывает RabbitMQ как очередь по умолчанию

### 2. Финальные проверки не удалось подтвердить в текущем ревью

Статус: не проверялось

Контекст:
План требовал финально выполнить `docker compose ... config`, `make test` и `make phpstan`.

Проблема:
`docker compose -f docker/docker-compose.dev.yml --env-file .env config` выполняется, но `make test` и `make phpstan` сейчас не стартуют, потому что Docker daemon недоступен по сокету.

Риск:
Без свежего прогона тестов и PHPStan нельзя подтвердить, что текущий полный незакоммиченный diff проходит обязательные проверки проекта.

Где:

- `docs/plans/2026-05-24_17-37_outbox-rabbitmq.md:425` — список финальных проверок
- `docs/plans/2026-05-24_17-37_outbox-rabbitmq.md:429` — обязательный `make test`
- `docs/plans/2026-05-24_17-37_outbox-rabbitmq.md:430` — обязательный `make phpstan`

## Замечания

### 1. Ошибка Job в `sync`-очереди может быть перезаписана как ошибка publish

Тип: `bug`

Рекомендация: `править обязательно`

Контекст:
В тестовой среде и в любом окружении с `QUEUE_CONNECTION=sync` постановка задачи сразу запускает Job в том же процессе. Если Job падает, это уже ошибка выполнения Job, а не ошибка отправки сообщения в очередь.

Риск:
Interceptor успеет записать `failed` или retry-состояние, но затем relay поймает то же исключение как ошибку `push()` и вызовет `recordPublishFailure()`. Для не последней попытки это переводит событие обратно в `pending`, увеличивает попытки ещё раз и может оставить старый `failed_at`. В итоге outbox хранит неверный статус и может повторно запускать уже упавший Job как будто сообщение вообще не было опубликовано.

Где:

- `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelay.php:116` — `push()` в sync-режиме выполняет Job сразу
- `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelay.php:149` — relay ловит любой `Throwable` вокруг `push()`
- `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelay.php:150` — ошибка записывается как publish failure
- `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueStatusInterceptor.php:66` — interceptor отдельно ловит ошибку выполнения Job
- `app/src/Modules/Outbox/Domain/Outbox/Entity/StoredOutboxEvent.php:147` — publish failure переводит событие обратно в `pending`
- `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php:39` — sync-сценарий покрыт только для успешного Job
- `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php:130` — ошибка покрыта только fake queue, без выполнения Job

Детали:
Для RoadRunner/RabbitMQ `push()` только публикует сообщение, поэтому catch в relay действительно означает ошибку publish. Для `sync` это не так: `SyncDriver` вызывает consume-chain и Job Handler внутри `push()`, поэтому исключение после interceptor-а возвращается обратно в relay. Это также может дважды увеличить `attempts` за одно падение Job и раньше времени довести событие до лимита попыток.

Как исправить:
Нужно разделить ошибку постановки в очередь и ошибку уже выполненного sync Job. После исключения из sync `push()` relay должен проверить текущее состояние события и не перезаписывать финальный или retry-статус, который уже выставил interceptor.

Конкретные шаги:

- Добавить feature-тест для `QUEUE_CONNECTION=sync`, где Job падает, а outbox остаётся `failed` или `queued` при retry.
- В catch после `push()` для sync-сценария перечитать событие из repository или проверить актуальный статус в базе.
- Не вызывать `recordPublishFailure()`, если Job уже прошёл через `OutboxQueueStatusInterceptor` и статус события изменён не как результат ошибки publish.

### 2. Вызовы relay сделаны без именованного аргумента

Тип: `rules`

Рекомендация: `править обязательно`

Контекст:
В проекте при вызове метода с необязательными параметрами используются именованные аргументы. Это снижает риск перепутать значения и делает вызов устойчивее к изменению сигнатуры.

Риск:
`OutboxRelay::relay()` уже имеет второй необязательный параметр `$now`. Позиционный вызов сейчас работает, но нарушает правило проекта и делает код менее устойчивым при дальнейшем расширении метода.

Где:

- `docs/rules.md:13` — правило об именованных аргументах
- `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelay.php:42` — метод имеет необязательный параметр `$now`
- `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelayWorker.php:19` — позиционный вызов `relay($outboxRelayBatchSize)`
- `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelayWorker.php:26` — второй позиционный вызов

Детали:
Правило применяется при любом необязательном параметре, даже если в конкретном вызове передаётся только первый аргумент.

Как исправить:
Сделать вызовы явными по имени параметра.

Конкретные шаги:

- В `OutboxRelayWorker::runOnce()` вызвать `relay(outboxRelayBatchSize: $outboxRelayBatchSize)`.
- В `OutboxRelayWorker::runLoop()` сделать такой же именованный вызов.
- Повторить `make phpstan`, когда Docker daemon будет доступен.

### 3. В diff остались лишние пустые строки в конце файлов

Тип: `quality`

Рекомендация: `на усмотрение автора`

Контекст:
Проектные проверки не требуют отдельно `git diff --check`, но такие ошибки обычно мешают чистому diff и могут быть пойманы хуками или ревью-инструментами.

Риск:
Поведение приложения не ломается, но diff содержит мелкий форматный шум.

Где:

- `app/src/Modules/Outbox/Application/Outbox/Message/OutboxDebugLogMessage.php:28` — лишняя пустая строка в конце файла
- `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxConsoleBootloader.php:18` — лишняя пустая строка в конце файла
- `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxJobRegistryException.php:24` — лишняя пустая строка в конце файла
- `tests/Unit/Modules/Outbox/Infrastructure/Outbox/OutboxQueueSerializerTest.php:44` — лишняя пустая строка в конце файла

Детали:
`git diff --check HEAD` возвращает ошибки `new blank line at EOF` по перечисленным файлам.

Как исправить:
Убрать дополнительные пустые строки в конце файлов, оставив один финальный перевод строки.

Конкретные шаги:

- Удалить лишние пустые строки в перечисленных файлах.
- Повторить `git diff --check HEAD`.

## Рекомендации

- **Править обязательно:** проблемы плана 1, 2; замечания 1, 2
- **На усмотрение автора:** замечание 3

## Изменения после мета-ревью

### После plan-check / architecture-check / rules-check / quality-check

- **+ Добавлено:** замечание про позиционные вызовы `OutboxRelay::relay()`; уточнение, что `docs/arch.md` не указывает RabbitMQ в локальной инфраструктуре и дублирует `app-http`.
- **~ Изменено:** проблема с документацией переформулирована как внутреннее противоречие memory/RabbitMQ; проверка `make test` и `make phpstan` переведена в обязательные пункты; sync-замечание уточнено двойным увеличением попыток.
- **− Убрано:** фраза из оценки про закрытые прошлые ревью как лишняя для текущего diff.
- **Отклонено:** отклонено замечание о переносе outbox из `System` в `Outbox`, потому что это уже зафиксированный архитектурный fix и не является проблемой; отклонено замечание про невалидный `outboxId` header как недостаточно связанное с плановым контрактом и штатным relay-потоком; отклонено дублирование `integerArgument()` / `integerOption()` как мелкое и без достаточного риска для отдельного пункта.

## Применённые фиксы
Отчёт: `docs/review-fixes/2026-05-27_17-43_outbox-rabbitmq-uncommitted.md`
