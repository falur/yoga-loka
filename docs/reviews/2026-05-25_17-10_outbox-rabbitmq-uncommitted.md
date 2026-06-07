---
title: Ревью незакоммиченных изменений outbox RabbitMQ
date: 2026-05-25 17:10
target: git diff HEAD + untracked files
mode: normal
score: 58
status: meta-reviewed
meta_reviewers:
  - plan-check:gpt-5.3-codex
  - architecture-check:gpt-5.3-codex
  - rules-check:gpt-5.3-codex
  - quality-check:gpt-5.3-codex
---

# Ревью: незакоммиченные изменения outbox RabbitMQ

## Оценка

**58/100.** Локальные проверки проходят, но в реализации есть несколько рисков именно в тех местах, где outbox должен быть самым надёжным: сбои, повторы и границы модуля.

## Проблемы сверки с планом

### 1. Не реализован лимит попыток из outbox-настройки

Статус: частично

Контекст:
План требовал при ошибке publish увеличивать число попыток, переносить событие на повтор, а при исчерпании `OutboxConfig::maxAttempts` переводить событие в `failed`.

Проблема:
Outbox config не добавлен. Лимит попыток зашит в `OutboxAttempts::MAX = 100`, а сценария, который при достижении лимита переводит событие в `failed`, нет.

Риск:
Постоянная ошибка, например незарегистрированный Job или недоступная очередь, не завершится управляемо. Вместо финального статуса код может упасть на новом исключении из value object.

Где:

- `app/src/Modules/System/Domain/Outbox/ValueObject/OutboxAttempts.php:12` — лимит зашит в value object
- `app/src/Modules/System/Domain/Outbox/Entity/StoredOutboxEvent.php:132` — publish failure всегда пытается увеличить попытки
- `app/src/Modules/System/Infrastructure/Outbox/OutboxRelay.php:111` — relay не проверяет max attempts перед новым retry

### 2. RoadRunner-настройка RabbitMQ не следует env-параметрам из плана

Статус: отклонение

Контекст:
План требовал задавать AMQP-параметры через env: имя очереди, exchange, routing key, durable-флаги, prefetch и requeue.

Проблема:
PHP-конфиг читает эти значения из env, но `docker/rr/http-jobs.yaml` оставляет те же параметры жёстко прописанными. Документация при этом говорит, что RabbitMQ pipeline настраивается через env.

Риск:
Если поменять env, PHP-конфиг и RoadRunner pipeline могут начать описывать разные очереди. Приложение будет выглядеть настроенным через env, но worker продолжит читать жёстко заданную очередь.

Где:

- `docker/rr/http-jobs.yaml:51` — RabbitMQ pipeline содержит жёсткие значения
- `app/config/queue.php:72` — PHP-конфиг берёт AMQP-настройки из env
- `docker/README.md:109` — документация обещает настройку pipeline через env
- `.env.sample:30` — env-переменные объявлены как пользовательский контракт

## Замечания

### 1. Outbox нужно вынести в отдельный модуль

Тип: `architecture`

Рекомендация: `править обязательно`

Контекст:
Outbox стал самостоятельным техническим механизмом со своей таблицей, сущностью, репозиторием, relay, serializer-ом, interceptor-ом, Job и консольной командой. Для такого объёма отдельный модуль понятнее, чем вложенность внутри `System`.

Риск:
Если оставить outbox внутри `System`, модуль `System` начнёт смешивать разные ответственности: health/openapi/temporal и надёжную доставку внешних действий. Это усложнит развитие реальных email, push, Centrifugo и webhook Job. Дополнительно внутри текущей структуры уже появился обратный импорт из `Domain` в `Application`.

Где:

- `app/src/Modules/System/Application/Outbox/` — application-контракты и сообщения outbox лежат внутри `System`
- `app/src/Modules/System/Domain/Outbox/` — доменная модель outbox лежит внутри `System`
- `app/src/Modules/System/Infrastructure/Outbox/` — relay, serializer и interceptor лежат внутри `System`
- `app/src/Modules/System/Repository/OutboxEventRepository.php` — repository outbox лежит внутри `System`
- `app/src/Modules/System/Presentation/Console/OutboxRelayCommand.php` — консольный вход outbox лежит внутри `System`
- `app/src/Modules/System/Presentation/Job/OutboxDebugLogJob.php` — Job outbox лежит внутри `System`
- `app/src/Modules/System/Domain/Outbox/ValueObject/OutboxEventType.php:7` — domain value object импортирует `OutboxMessage` из `Application`

Детали:
Правило из `docs/arch.md` описывает модули как отдельные области приложения. Outbox уже выглядит как отдельная область с полным набором слоёв. При этом `OutboxEventType` лежит в `Domain`, но проверяет реализацию интерфейса из `Application`, что нарушает направление зависимостей.

Как исправить:
Вынести outbox в отдельный модуль и сохранить обычные границы слоёв внутри него.

Конкретные шаги:

- Создать модуль `Modules/Outbox`.
- Перенести outbox-код из `Modules/System/.../Outbox` и `OutboxEventRepository` в соответствующие слои нового модуля.
- Перенести `OutboxMessage` туда, где `Domain` не будет зависеть от `Application`.
- Обновить bootloader-ы, config, namespace-ы, тесты и документацию.
- Добавить проверку, что больше нет зависимости `Outbox/Domain -> Outbox/Application`.

### 2. Запись длинной ошибки может сама сломать обработку сбоя

Тип: `bug`

Рекомендация: `править обязательно`

Контекст:
Outbox должен быть устойчивым именно в момент ошибок: если очередь недоступна или Job падает, система всё равно должна сохранить короткое описание ошибки и новый статус события.

Риск:
Если текст исключения длиннее 2000 символов, создание `OutboxLastError` выбросит новое исключение. Тогда исходный сбой не будет корректно записан, событие может остаться в промежуточном статусе, а relay или worker завершится уже другой ошибкой.

Где:

- `app/src/Modules/System/Domain/Outbox/ValueObject/OutboxLastError.php:22` — длинная ошибка запрещена
- `app/src/Modules/System/Domain/Outbox/ValueObject/OutboxLastError.php:33` — `fromThrowable()` передаёт полное сообщение исключения без обрезки
- `app/src/Modules/System/Infrastructure/Outbox/OutboxRelay.php:111` — это вызывается внутри `catch`
- `app/src/Modules/System/Infrastructure/Outbox/OutboxQueueStatusInterceptor.php:103` — то же происходит при падении Job

Детали:
Код пытается сохранить ошибку уже после того, как основная операция упала. В этот момент нельзя использовать создание value object, которое само может упасть на обычном содержимом exception message.

Как исправить:
Сделать формирование `last_error` безопасным: оно должно всегда возвращать короткую строку и не выбрасывать новое исключение при обработке сбоя.

Конкретные шаги:

- В `OutboxLastError::fromThrowable()` обрезать сообщение до допустимого лимита.
- Добавить тест на publish failure с длинным текстом исключения.
- Добавить тест на Job failure с длинным текстом исключения.

### 3. Лимит попыток не переводит событие в `failed`

Тип: `bug`

Рекомендация: `править обязательно`

Контекст:
Повторы нужны, чтобы временные сбои проходили сами. Но постоянный сбой должен когда-то завершиться понятным финальным статусом, иначе событие будет бесконечно возвращаться в работу или падать в обработчике ошибок.

Риск:
После достижения максимального числа попыток следующий сбой не поставит событие в `failed`, а выбросит доменное исключение из `OutboxAttempts::increment()`. Это ломает relay и может оставить событие в промежуточном состоянии.

Где:

- `app/src/Modules/System/Domain/Outbox/ValueObject/OutboxAttempts.php:21` — превышение лимита выбрасывает исключение
- `app/src/Modules/System/Domain/Outbox/Entity/StoredOutboxEvent.php:123` — `markFailed()` тоже увеличивает попытки без проверки лимита
- `app/src/Modules/System/Domain/Outbox/Entity/StoredOutboxEvent.php:132` — publish failure всегда возвращает событие в `pending`
- `app/src/Modules/System/Infrastructure/Outbox/OutboxRelay.php:111` — relay не проверяет max attempts перед новым retry

Детали:
В плане был сценарий `failed` при исчерпании попыток, но в реализации есть только числовой предел внутри value object. Этот предел не управляет статусом события.

Как исправить:
Решение о финальном статусе должно приниматься до увеличения попыток сверх лимита. Лимит лучше держать в явной настройке outbox или рядом с доменной логикой, а не полагаться на исключение из value object.

Конкретные шаги:

- Добавить метод, который проверяет, можно ли делать новую попытку.
- При исчерпании лимита переводить событие в `failed`, записывать `failed_at` и короткий `last_error`.
- Добавить тесты для publish failure и Job failure на последней допустимой попытке.

### 4. Sync-подключение не использует serializer и получает неверный payload

Тип: `bug`

Рекомендация: `править обязательно`

Контекст:
В тестовой среде очередь оставлена `sync`, чтобы проверки не зависели от RabbitMQ. Если relay запускается на таком подключении, Job выполняется сразу в том же процессе.

Риск:
Sync-драйвер передаст в `OutboxDebugLogJob` массив envelope вместо объекта сообщения. Job не сможет выполниться, а relay воспримет это как ошибку публикации. Это делает sync-сценарий из плана недостоверным.

Где:

- `vendor/spiral/framework/src/Queue/src/Driver/SyncDriver.php:25` — sync-драйвер передаёт payload как есть
- `app/src/Modules/System/Infrastructure/Outbox/OutboxRelay.php:76` — в очередь уходит массив
- `app/src/Modules/System/Presentation/Job/OutboxDebugLogJob.php:17` — обработчик принимает объект `OutboxDebugLogMessage`
- `tests/Feature/Modules/System/Outbox/OutboxRelayTest.php:23` — тест покрывает fake queue, а не реальный sync-драйвер

Детали:
RoadRunner consumer умеет восстановить объект через `OutboxQueueSerializer`, но sync-драйвер Spiral вызывает handler напрямую. Текущие тесты не ловят это, потому что fake queue только запоминает push.

Как исправить:
Нужно сохранить универсальность выбора очереди, но сделать поведение одинаковым для подключений, которые выполняют Job через разные механизмы.

Конкретные шаги:

- Добавить отдельный тест с реальным `QUEUE_CONNECTION=sync` и добиться передачи в Job объекта сообщения.
- Не прибивать relay к `rabbitmq`, если цель — универсальное подключение очереди через общий `QUEUE_CONNECTION`.
- Добавить тест на ветку, где после `push()` событие уже не `publishing`, чтобы защита от sync-гонки была доказана.

### 5. RabbitMQ pipeline в RoadRunner расходится с env-контрактом

Тип: `bug`

Рекомендация: `править обязательно`

Контекст:
Имя очереди, exchange и routing key должны быть единым контрактом между приложением, RoadRunner и RabbitMQ. Иначе часть системы пишет в одно место, а worker читает из другого.

Риск:
При изменении env-переменных приложение может создавать или использовать одну AMQP-конфигурацию, а RoadRunner consumer останется на жёстко заданных `yoga_loka_jobs`. Это приведёт к задачам, которые не обрабатываются, или к неожиданному чтению из старой очереди.

Где:

- `docker/rr/http-jobs.yaml:55` — `prefetch` задан числом, а не env
- `docker/rr/http-jobs.yaml:56` — `queue` задан жёстко
- `docker/rr/http-jobs.yaml:58` — `exchange` задан жёстко
- `docker/rr/http-jobs.yaml:62` — `routing_key` задан жёстко
- `app/config/queue.php:75` — PHP-конфиг берёт эти значения из env

Детали:
Сейчас env-переменные есть в `.env.sample` и используются в PHP-конфиге, но RoadRunner YAML не использует их для самой pipeline-конфигурации.

Как исправить:
Сделать один источник правды для RabbitMQ-параметров. Для Docker runtime проще всего использовать env-подстановки в `docker/rr/http-jobs.yaml` для всех параметров, которые объявлены в `.env.sample`.

Конкретные шаги:

- Заменить hard-coded значения в `docker/rr/http-jobs.yaml` на `${RABBITMQ_*:-...}`.
- Добавить проверку или тест, который подтверждает, что compose/RoadRunner config содержит значения из env.
- Синхронизировать `docker/README.md` с фактическим способом настройки.

### 6. Для outbox-задачи без записи в БД нет безопасной политики

Тип: `architecture`

Рекомендация: `править обязательно`

Контекст:
Если задача пришла с `outboxId`, это уже не обычная задача. Её выполнение должно быть связано с записью outbox, потому что именно эта запись хранит статус, попытки и защиту от дублей.

Риск:
Если запись не найдена, текущий код всё равно запускает Job. Внешнее действие может выполниться без возможности поставить `handled` или `failed`, а повторная доставка не будет контролироваться outbox-статусами.

Где:

- `app/src/Modules/System/Infrastructure/Outbox/OutboxQueueStatusInterceptor.php:41` — событие ищется по `outboxId`
- `app/src/Modules/System/Infrastructure/Outbox/OutboxQueueStatusInterceptor.php:43` — при отсутствии записи Job всё равно выполняется

Детали:
По плану явная политика для такого случая не описана, но архитектурно outbox-задача без outbox-записи теряет главный механизм контроля доставки.

Как исправить:
Зафиксировать явную политику для outbox-задачи без записи: не выполнять Job и логировать проблему, либо выбрасывать ошибку для повторной доставки и последующей ручной диагностики.

Конкретные шаги:

- Добавить тест на задачу с `outboxId`, которого нет в БД.
- Зафиксировать выбранное поведение в `OutboxQueueStatusInterceptor`.
- Если Job всё же должен выполняться, отдельно описать, как тогда отслеживается статус и идемпотентность.

## Рекомендации

- **Править обязательно:** 1, 2, 3, 4, 5, 6
- **На усмотрение автора:** нет

## Изменения после мета-ревью

### После plan-check / architecture-check / rules-check / quality-check

- **+ Добавлено:** необходимость вынести outbox в отдельный модуль; зависимость `Domain -> Application`; отсутствие `OutboxConfig::maxAttempts`; тестовый пробел для ветки после sync-like `push()`; уточнена политика для outbox-задачи без записи.
- **~ Изменено:** убрано недоказанное утверждение про пустой текст исключения; sync-проблема привязана к `SyncDriver`; пункт про отсутствующую запись в БД переформулирован как архитектурно неопределённая политика.
- **− Убрано:** отдельное утверждение, что пустой `getMessage()` сам по себе ломает `OutboxLastError`.
- **Отклонено:** отдельное замечание про сырые transport-массивы в `OutboxQueueEnvelope`, потому что план явно допускает внутренний transport array для `QueueInterface`; предложение убрать пункты 1–5 отклонено, основные риски подтверждены кодом.

### После уточнения пользователя

- **+ Добавлено:** outbox должен быть вынесен в отдельный модуль.
- **~ Изменено:** sync-пункт оставлен как проблема несовместимого payload, но исправление больше не предлагает прибивать relay к `rabbitmq`.
- **− Убрано:** пункт сверки с планом «Relay не использует отдельную outbox-настройку очереди», потому что универсальное подключение через общую очередь является ожидаемым решением.
- **Отклонено:** нет.

## Применённые фиксы

Отчёт: `docs/review-fixes/2026-05-25_21-01_outbox-rabbitmq-uncommitted.md`
