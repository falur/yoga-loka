---
title: Модуль уведомлений (Notifications) — незакоммиченный diff (шестой круг, финальная верификация)
date: 2026-06-15 13:10
target: git diff HEAD + untracked
plan: docs/plans/2026-06-13_13-55_notifications_module.md
mode: meta-reviewed
score: 84
status: meta-reviewed
meta_reviewers: [architecture-check (sonnet), rules-check (sonnet), plan-check (sonnet), quality-check (sonnet)]
---

# Ревью: Модуль уведомлений (Notifications) — незакоммиченный diff (шестой круг, финальная верификация)

## Оценка

**84/100.** Финальная верификация после пяти кругов доводки. Подавляющая часть кода консистентна и
подтверждается прямо в реализации: невалидный path-`id` и битый `cursor` отбиваются на границе как
422 (`#[Assert\Uuid]`), удаление токена скоупится по владельцу (нет IDOR), Centrifugo ходит с
`Authorization: apikey`, разбирает тело при 2xx и считает `error` сбоем, push-токены в логах не
светятся, лимит размера списка настроек применён (`#[Assert\Count(max: 300)]` с тестом на 422).
Рассылка идемпотентна по `outbox_id`, классификация временных/терминальных сбоев в доставочных Job
корректна, рефактор `OutboxEventDate` под уточнённое `rules.md:21` консистентен.

Шестой круг впервые вскрыл **реальное нарушение границ слоёв, не замеченное в первых пяти кругах**:
оба доменных исключения доставки (`CentrifugoPublishException`, `FcmPushFailedException`) лежат в
`Infrastructure/Exception`, хотя являются контрактными сигналами `CentrifugoServiceContract` /
`FcmPushSenderContract` (оба — `Application/Contract`), и импортируются классами `Presentation/Job`.
Это пробивает `Presentation → Infrastructure` (запрещено `arch.md` «Правила зависимостей») и
противоречит `rules.md:46` («слой исключения определяется контрактом, к которому оно относится»).
В самом проекте есть прямой образец-антитеза: `MediaFileServiceFailedException` лежит в
`Media/Application/Exception` и потребляется `ProcessMediaJob` именно из `Application/Exception`.
Это пункт «править обязательно» — он и снижает балл с 95 до 84.

Остальные находки — мелкие optional уровня «на усмотрение автора»: одиночный `->each()` вместо
`foreach` для итерации с побочным эффектом, три одноразовых `private const` сообщения в Job-классах,
и неотслеживаемый stray-артефакт `tools/openapi/runtime/openapi-fixture.yml` (гигиена дерева, не код,
вне диапазона изменений модуля).

## Проблемы сверки с планом

Расхождений реализации с планом по существу нет. Все 7 фаз выполнены и совпадают с разделами «Данные
и БД» и «API и внешние контракты»: 3 таблицы с нужными индексами и unique (включая
`notifications.outbox_id unique`), 8 роутов, 3 пары message→Job зарегистрированы и в
`NotificationsBootloader.boot()`, и в `app/config/queue.php`, OpenApi-конфиг расширен на список
`sourcePaths` + `apiNamespace: App\Modules`, `openapi.yml` перегенерирован (8 роутов). `findAllForUser`
вместо `findActiveForUser` — осознанно отклонённый ранее пункт (у `notification_device_tokens` нет
колонки статуса, все хранимые токены активны по определению), переименование не воскрешается.

Замечание №1 ниже (размещение исключений) — это нарушение архитектурных правил проекта, а не самого
плана: план в фазе 6 кладёт оба исключения в `Infrastructure/Exception`, реализация плану следует, но
сам план в этой точке противоречит `arch.md`/`rules.md:46`. Поэтому фиксируется как обязательная
архитектурная правка, не как отклонение от плана.

## Замечания

### 1. Доменные исключения доставки лежат в `Infrastructure/Exception`, но импортируются из `Presentation`

`CentrifugoPublishException` и `FcmPushFailedException` находятся в
`Modules/Notifications/Infrastructure/Exception`, но являются контрактными сигналами публичных
контрактов модуля `CentrifugoServiceContract` и `FcmPushSenderContract` (оба — `Application/Contract`):
именно по их `isTransient()` доставочные Job решают, переводить ли сбой в `RetryException` (временный сбой)
или пробрасывать терминально. Оба исключения импортируются классами `Presentation/Job`
(`PublishRealtimeNotificationJob`, `SendPushNotificationJob`).

Это даёт сразу два нарушения:

- **Граница слоёв** — `Presentation` начинает зависеть от `Infrastructure` своего модуля, хотя
  `arch.md` («Правила зависимостей») допускает для `Presentation` только зависимость от `Application`
  своего модуля, Response/Resource и framework attributes. Presentation/Job не должен знать про
  инфраструктурную реализацию доставки.
- **Размещение исключения** — `rules.md:46`: «Слой исключения определяется контрактом, к которому оно
  относится, а не местом выброса». Контракт обоих исключений — `Application/Contract`, значит их место —
  `Application/Exception`, даже если бросаются они из `Infrastructure`.

В самом проекте есть канонический образец правильного размещения: `MediaFileServiceFailedException` —
контрактный сбой `MediaFileServiceContract` — лежит в `Media/Application/Exception`, а `ProcessMediaJob`
(`Presentation/Job`) импортирует его именно из `Application/Exception`. Модуль Notifications отступил от
собственного шаблона.

Технические детали:

- **Тип:** `architecture` / нарушение `rules.md:46` (двойное)
- **Рекомендация:** `править обязательно`
- **Где:**
  - `app/src/Modules/Notifications/Infrastructure/Exception/CentrifugoPublishException.php`
  - `app/src/Modules/Notifications/Infrastructure/Exception/FcmPushFailedException.php`
  - импорты из `Presentation`:
    `app/src/Modules/Notifications/Presentation/Job/PublishRealtimeNotificationJob.php:10`,
    `app/src/Modules/Notifications/Presentation/Job/SendPushNotificationJob.php:10`
- **Что подтверждает проблему:** оба класса используются и в `Infrastructure` (`CentrifugoClient`,
  `KreaitFcmPushSender` бросают), и в `Presentation/Job` (ловят/классифицируют). Образец-антитеза:
  `app/src/Modules/Media/Application/Exception/MediaFileServiceFailedException.php` +
  `app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:11` (`use ...Application\Exception\...`).
- **Как исправить:** перенести оба класса в
  `app/src/Modules/Notifications/Application/Exception/` (сменить namespace на
  `App\Modules\Notifications\Application\Exception`); обновить `use`-импорты в `CentrifugoClient.php`,
  `KreaitFcmPushSender.php`, `PublishRealtimeNotificationJob.php`, `SendPushNotificationJob.php` и в
  затронутых тестах (`tests/Unit/.../CentrifugoClientTest.php`, `tests/Unit/.../KreaitFcmPushSenderTest.php`,
  `tests/Feature/.../DeliveryJobTest.php`).
- **Тесты:** существующие unit/feature-тесты доставки переедут на новый namespace; новых сценариев не
  требуется — поведение исключений не меняется, только их слой.

### 2. В рабочем дереве остался неотслеживаемый артефакт OpenAPI-генератора

В рабочем дереве лежит неотслеживаемый файл `tools/openapi/runtime/openapi-fixture.yml`, на который не
ссылается ни тест, ни код приложения. Это не влияет на поведение и не попадёт в коммит, если его не
добавить вручную, но засоряет вывод `git status` и может случайно уехать в коммит «оптом» (`git add -A`)
или скрыть от человека лишний файл в дереве.

Происхождение: `tools/openapi/` — это **прежнее** расположение пакета `spiral-openapi` до рефакторинга
(`8bf83ff refactor(packages): перенести локальные пакеты в packages`). Файл — остаток тестового прогона
пакета с его старой позиции (mtime датирован задолго до начала работ над модулем). После переезда пакета
в `packages/spiral-openapi/` корневой `.gitignore` получил правило `/packages/*/runtime/`, которое
покрывает `packages/spiral-openapi/runtime/`, но не покрывает `tools/openapi/runtime/`: правило
`*/runtime/` одноуровневое и до двухуровневой вложенности не достаёт. Поэтому артефакт всплывает в
`git status` как `?? tools/`.

Практического риска для рантайма нет — это чистая гигиена рабочего дерева, и относится она не к коду
модуля Notifications, а к остаткам прежней структуры пакетов. Стоит удалить артефакт перед коммитом
(`rm -r tools/`) либо, если есть инструмент, который штатно пишет во `tools/openapi/runtime`, добавить
эту папку в `.gitignore`.

Технические детали:

- **Тип:** `quality` (гигиена рабочего дерева / неотслеживаемый артефакт; вне диапазона изменений модуля)
- **Рекомендация:** `на усмотрение автора`
- **Где:** `tools/openapi/runtime/openapi-fixture.yml` — единственный файл в неотслеживаемой папке
  `tools/`; в `git status` числится как `?? tools/`.
- **Что подтверждает проблему:** `grep -rn "tools/openapi" app tests` — пусто (на файл из приложения
  никто не ссылается); `git ls-files tools/` — пусто (не отслеживается). Файл — выход теста
  `packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php` (`openapi-fixture.yml`), оставшийся
  от старого расположения пакета.
- **Как исправить:** удалить файл/папку перед коммитом (`rm -r tools/`), либо добавить `tools/openapi/runtime/`
  (или `**/runtime/`) в `.gitignore`. Перед `eda-commit` убедиться, что в коммит не уходит лишний артефакт.
- **Тесты:** не требуется — это рабочее дерево, не код.

### 3. `->each()` вместо `foreach` для итерации с побочным эффектом (quality)

`MarkAllNotificationsReadHandler::handle()` обходит непрочитанные уведомления через
`$unread->each(fn ...)`, выполняя побочный эффект (`markRead`/изменение состояния каждой Entity).
`rules.md:19` прямо предписывает для итераций с побочными эффектами `foreach`, а collection-пайплайны
(`map`/`filter`/`each`) — для чистых трансформаций. В остальном коде модуля и проекта (`OutboxRelay`,
`UpdateNotificationSettingsHandler`, `KreaitFcmPushSender`) такие итерации идут через `foreach` — это
единственное расхождение со стилем.

- **Тип:** `quality`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/src/Modules/Notifications/Application/Command/Notification/MarkAllNotificationsRead/MarkAllNotificationsReadHandler.php:35`
- **Как исправить:** заменить `->each(function (Notification $notification) use ($now) {...})` на `foreach`.

### 4. Одноразовые `private const` сообщения в Job-классах (quality)

Три Job-класса заводят `private const string *_FAILURE_MESSAGE`, каждая используется ровно один раз —
в единственном `new RetryException(reason: ...)` внутри того же класса. `rules.md:33`: одноразовые
технические строки и сообщения оставлять рядом с использованием; константа здесь не даёт ни
переиспользования, ни публичного контракта, ни закрытого набора вариантов.

- **Тип:** `quality`
- **Рекомендация:** `на усмотрение автора`
- **Где:**
  `app/src/Modules/Notifications/Presentation/Job/DispatchNotificationJob.php:26` (использование :72),
  `app/src/Modules/Notifications/Presentation/Job/SendPushNotificationJob.php:24` (использование :60),
  `app/src/Modules/Notifications/Presentation/Job/PublishRealtimeNotificationJob.php:25` (использование :63).
- **Как исправить:** подставить строку прямо в `RetryException(reason: '...')`, убрав константу.

## Рекомендации

- **Править обязательно:** 1 (замечание №1 — размещение исключений)
- **На усмотрение автора:** 3 (замечания №2, №3, №4)

## Заметки верификации (проблемами не являются — зафиксированы, чтобы не поднимать повторно)

- **Centrifugo-заголовок `Authorization: apikey` вместо плановского `X-API-Key`.** План фазы 6
  (`docs/plans/2026-06-13_13-55_notifications_module.md`, строки 618, 649) указывает `X-API-Key`,
  реализация (`CentrifugoClient.php`) использует `Authorization: apikey <key>` — актуальный протокол
  Centrifugo v6; `X-API-Key` — формат устаревших версий. Отклонение осознанное, поднималось в 3-м
  круге, закрыто в 4-м — не воскрешать.
- **CentrifugoClient — `JSON_THROW_ON_ERROR` на теле 2xx.** При битом JSON в 2xx-теле
  `ensureNoApiError()` бросит `\JsonException`, не `CentrifugoPublishException`. В
  `PublishRealtimeNotificationJob` это уходит в терминальную ветку → outbox `failed`. Это корректное
  поведение (битый ответ Centrifugo терминален), не баг; ветка недостижима без поломки самого
  Centrifugo и не влияет на покрытие доменного кода.
- **`findAllForUser` (push-доставка) против `findActiveForUser`** — ранее отклонённое переименование;
  у `notification_device_tokens` нет колонки статуса, все хранимые токены активны по определению,
  невалидные удаляются при отправке. Скрытого бага нет. Новых аргументов нет.
- **`resolveType()` через `all()->first()` вместо `get()`** — ранее отклонено (сохраняет контракт
  «неизвестный вид → 422», `get()` дал бы 500); новых аргументов нет.
- **N→1 выборка настроек в `UpdateNotificationSettingsHandler`** — ранее зафиксировано как
  оптимизация, не граница; новых аргументов нет.

## Изменения после мета-ревью

### После architecture-check
- **+ Добавлено:** замечание №1 — `CentrifugoPublishException` и `FcmPushFailedException` лежат в
  `Infrastructure/Exception`, но являются контрактными сигналами `Application/Contract` и импортируются
  из `Presentation/Job`; нарушены `arch.md` «Правила зависимостей» (`Presentation → Infrastructure`) и
  `rules.md:46`. Проверено в коде; образец-антитеза `MediaFileServiceFailedException` подтверждает.
  Уровень — «править обязательно». Это и снизило балл с 95 до 84.
- **~ Изменено:** разделы «Оценка» и «Проблемы сверки с планом» переписаны под новый обязательный пункт.
- **− Убрано:** прежний тезис «новых применимых замечаний уровня "править обязательно" нет».

### После rules-check
- **Отклонено:** rules-check заключил, что размещение исключений «по правилу слоёв» корректно — это
  расходится с фактом (исключения импортируются из `Presentation` и являются контрактом
  `Application/Contract`); опираюсь на собственную проверку и находку architecture-check. Новых
  применимых нарушений правил rules-check не нашёл.

### После plan-check
- **+ Добавлено:** в «Заметки верификации» внесена явная запись про осознанное отклонение
  Centrifugo-заголовка (`X-API-Key` → `Authorization: apikey`), чтобы не воскрешалось в следующих кругах.
- **~ Изменено:** уточнено, что замечание №1 — нарушение архитектурных правил, а не отклонение от
  плана (план сам в этой точке противоречит `arch.md`).

### После quality-check
- **+ Добавлено:** замечание №3 (`->each()` вместо `foreach`, `rules.md:19`) и №4 (одноразовые
  `private const` сообщения в трёх Job, `rules.md:33`) — оба «на усмотрение автора».
- **~ Изменено:** замечание №2 (stray-артефакт) переформулировано — уточнено происхождение (остаток
  старого расположения пакета `tools/openapi/`, не вывод генератора приложения; правило `.gitignore`
  `*/runtime/` не достаёт до двухуровневой вложенности) и явно отмечено, что находка вне диапазона
  изменений модуля.
- **− Убрано:** прежняя формулировка «Файл выглядит как рантайм-вывод генератора `packages/spiral-openapi`».

### Запуски мета-ревьюеров
- Запущены: `architecture-check`, `rules-check`, `plan-check` (план указан в front matter),
  `quality-check` (`review.include_code_quality: true`). Модель — `sonnet`. Режим `normal`: кросс-CLI
  не запускался.

## Применённые фиксы
Отчёт: `docs/review-fixes/2026-06-15_12-52_notifications-module-uncommitted-draft.md`

Все четыре замечания применены (режим `apply-optional`): №1 (обязательное) — исключения перенесены в
`Application/Exception`, зависимость Presentation → Infrastructure устранена; №2, №3, №4 (optional) —
приняты и применены. Отклонённых optional-решений нет. Финальный `make qa` зелёный (стиль,
PHPStan level max, 567 тестов, покрытие 100%).
