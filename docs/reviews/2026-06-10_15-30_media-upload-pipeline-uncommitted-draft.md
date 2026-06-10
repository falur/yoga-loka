---
title: Media upload pipeline — незакоммиченный diff (пятый цикл, после четырёх ревью)
date: 2026-06-10 15:30
target: git diff HEAD + untracked (модуль Media)
plan: docs/plans/2026-06-08_22-56_media-upload-pipeline.md
mode: draft
score: 93
status: meta-reviewed
meta_reviewers: [architecture-check (sonnet), rules-check (sonnet), plan-check (sonnet), quality-check (sonnet)]
---

# Ревью: Media upload pipeline — незакоммиченный diff (пятый цикл)

## Оценка

**93/100.** Реализация полностью покрывает план: переиспользуемый Application-API без собственного
HTTP, presigned/multipart через `Aws\S3\S3Client`, асинхронная обработка через outbox,
идемпотентный `ProcessMedia`, санитизация и классификация ошибок в `ProcessMediaJob`. Оба
optional-замечания прошлого (четвёртого) цикла применены и подтверждаются в рабочем дереве:

- инвариант «`ready` без ошибки» теперь защищён в самих доменных методах
  `recordTemporaryProcessingError` и `recordPermanentProcessingError` (ранний no-op на `ready`,
  симметрично `markReadyMovedTo`), а не только структурным guard'ом в другом Handler'е; поведение
  покрыто и domain-тестом (`MediaEntityTest`), и Handler-тестом
  (`RecordMediaProcessingFailureHandlerTest::testKeepsReadyMediaIntact`);
- выбор «хранить фактические размеры выхода процессора, а не запрошенный `spec.width/spec.height`»
  зафиксирован комментарием у `MediaImageConversion::create` в `ProcessMediaHandler`, записью в
  README и тестом `ProcessMediaHandlerTest::testStoresActualProcessorDimensionsNotSpec` (aspect-fit).

Блокеров нет. Новый media-код покрыт тестами; красный глобальный coverage-gate `make qa` —
пред-существующее осознанное отклонение из-за долга НЕ-media классов, в прошлых циклах разобрано,
повторно не поднимаю.

Блокеров и обязательных правок нет. Мета-ревью пятого цикла нашло два узких неблокирующих
`quality`-замечания «на усмотрение автора», ранее не поднимавшихся (см. «Замечания»): обоснование
«храним фактические размеры, а не spec» в README/комментарии описывает поведение `contain`, а реальный
процессор использует `cover()` (всегда выдаёт точные spec-размеры) — обоснование и закрепляющий его тест
вводят в заблуждение; и два Application-Handler'а инжектят `MediaConfig` из `Shared\Infrastructure\Configuration`
напрямую — единственный такой случай в проекте, формально вне списка зависимостей Application в `arch.md`.

Ранее поднятые и осознанно отклонённые/задокументированные в циклах 1–4 нитки (размещение
`MediaTypeResolver` в `Application/Service`; чтение `getObjectContents` целиком в память; «мёртвые»
пред-существующие `recordPermanentProcessingError`/`markReady`; FQCN в PHPDoc у `assertConversionsValid`;
S3-I/O внутри `#[Transactional]` в `CompleteMediaUpload`) повторно не выношу — новых аргументов против
этих решений нет.

## Проблемы сверки с планом

Явных проблем по плану не найдено. Все шесть фаз отмечены выполненными и подтверждаются кодом:
зависимости и Docker (Imagick + GD-фолбэк + aws-sdk), конфиг `media.php` + `MediaConfig`,
Application-DTO и контракты, `S3MediaFileService`, `ImagickMediaImageProcessor`, доменные методы
`markReadyMovedTo`/`isReady` и фабрики `MediaPath::imageConversion`/`originalReady`,
CQRS-сценарии, проводка outbox (`MediaBootloader`, `queue.php`, `Kernel`), документация.

Единственное расхождение с буквой плана — размеры `MediaImageConversion` берутся из фактического
результата процессора, а не из `spec.width/spec.height` (план, строка 241) — в этом цикле закрыто как
расхождение с планом (выбор задокументирован комментарием + README и закреплён тестом). Однако
мета-ревью этого цикла показало, что **обоснование** этого выбора некорректно: оно описывает
поведение `contain`/aspect-fit, тогда как процессор использует `cover()` и фактический размер всегда
равен запрошенному — см. замечание №1. Это не меняет сверку с планом (запись в БД корректна), но
делает документацию и закрепляющий тест вводящими в заблуждение.

Ранее зафиксированные осознанные отклонения (уровень лога «media готова» DEBUG вместо INFO по
`rules.md:84`, решение принято в фиксе цикла 1 №10; guard `markReadyMovedTo` расширен до
`ProcessingFailed`; красный coverage-gate) остаются в силе и не ухудшены — повторно не поднимаю.

## Замечания

### 1. Обоснование «храним фактические размеры, а не spec» описывает `contain`, а процессор делает `cover()` — документация и тест вводят в заблуждение

Optional-фикс №2 прошлого цикла зафиксировал выбор «храним фактический выход процессора, а не
`spec.width/spec.height`» комментарием у `MediaImageConversion::create`, записью в README и тестом.
Обоснование везде одно: «при сохранении пропорций (aspect-fit) реальный размер может отличаться от
запрошенного (запросили `100×100`, при letterbox/aspect-fit выдано `100×56`)». Но реальный процессор
(`ImagickMediaImageProcessor::resize`, `ImagickMediaImageProcessor.php:40`) использует
`$image->cover(width, height)` — это aspect-**fill** с обрезкой до **точных** запрошенных размеров
(Intervention v4: «Cropping and resizing... to fit exactly into given dimensions»). После `cover()`
`$image->width()`/`height()` **всегда** равны `spec.width`/`spec.height`. Сценарий расхождения, которым
обосновано хранение фактических размеров, с `cover()` недостижим.

Это подтверждает и собственный журнал исполнения: `docs/executions/2026-06-09_12-46_media-upload-pipeline.md:37`
прямо пишет «Дименсии конверсии в БД = фактические из результата процессора (**== spec при cover**)».
То есть исполнитель знал о равенстве, но в README/комментарии осталась формулировка про «aspect-fit, может
отличаться». Тест `ProcessMediaHandlerTest::testStoresActualProcessorDimensionsNotSpec` имитирует
`100×100 → 100×56` через mock-процессор — но реальный `cover()` так никогда не вернёт; тест проходит лишь
потому, что процессор замокан, и закрепляет недостижимый на практике сценарий.

Поведение корректно (брать `result->width/height` безопасно и при `cover`, и при возможной будущей смене
на `contain`), это не баг. Проблема — в **корректности документации и теста**: обоснование противоречит
фактическому процессору и собственному журналу.

- **Тип:** `quality`
- **Рекомендация:** на усмотрение автора
- **Где:** обоснование — `app/src/Modules/Media/Application/Command/Media/ProcessMedia/ProcessMediaHandler.php:123-125`
  (комментарий про aspect-fit) и `app/src/Modules/Media/README.md:97-100`; процессор —
  `app/src/Modules/Media/Infrastructure/FileService/ImagickMediaImageProcessor.php:40` (`$image->cover(...)`);
  тест — `tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php`
  (`testStoresActualProcessorDimensionsNotSpec`, `100×100 → 100×56`).
- **Что подтверждает проблему:** `cover()` всегда даёт точные запрошенные размеры (журнал исполнения,
  строка 37: «== spec при cover»), поэтому «aspect-fit, может отличаться» неверно для текущей реализации.
- **Как исправить:** один из вариантов — переписать комментарий/README на честную формулировку («берём
  `result->width/height`, потому что это устойчиво к будущей смене режима ресайза; при текущем `cover()`
  они равны spec») и убрать/переименовать тест так, чтобы он не утверждал недостижимый при `cover` сценарий
  (например, проверять, что в БД попадают именно значения, которые вернул процессор, без обещания их
  отличия от spec); либо, если действительно нужен aspect-fit, заменить `cover()` на `contain()`/`scale()`
  и тогда сохранить текущее обоснование.

### 2. Application-Handler'ы инжектят `MediaConfig` из `Shared\Infrastructure\Configuration` напрямую — единственный такой случай в проекте, формально вне списка зависимостей Application

`RequestMediaUploadHandler` и `GetMediaUrlHandler` (оба — новые файлы в `Application/`) конструктором
получают `App\Shared\Infrastructure\Configuration\Media\MediaConfig`. `arch.md` («Правила зависимостей»)
ограничивает Application-слой: «Application → Domain, Repository своего модуля, Contract своего модуля,
Application других модулей»; `Shared\Infrastructure\Configuration` в этот список не входит. Проверка по
всему проекту: типизированные конфиги используются только в Infrastructure/Presentation
(`OutboxConfig` — `Outbox/Infrastructure`; `CacheConfig`/`OpenApiConfig` — `System/Presentation`;
`StorageConfig`/`StorageServerConfig`/`StorageBucketConfig` — `Media/Infrastructure`). Эти два Media
Application-Handler'а — **единственные** в кодовой базе Application-потребители config-объекта из
`Shared/Infrastructure`.

Это не функциональный баг и не нарушение rules.md (там размещение конфигов в `Shared/Infrastructure/Configuration`
прямо предписано, и план в строках 148-154 закрепил инъекцию `MediaConfig` в Handler'ы). Но это
несогласованность с правилом зависимостей слоёв `arch.md` и с фактической конвенцией проекта, ранее в
циклах не отмеченная.

- **Тип:** `quality`
- **Рекомендация:** на усмотрение автора
- **Где:** `app/src/Modules/Media/Application/Command/Media/RequestMediaUpload/RequestMediaUploadHandler.php:22,32`;
  `app/src/Modules/Media/Application/Query/Media/GetMediaUrl/GetMediaUrlHandler.php:18,26`.
- **Что подтверждает проблему:** grep по `app/src` — Application-потребители
  `Shared\Infrastructure\Configuration` есть только в этих двух файлах; остальные config-объекты читаются из
  Infrastructure/Presentation. `arch.md` («Правила зависимостей») не перечисляет `Shared/Infrastructure`
  среди допустимых зависимостей Application.
- **Как исправить:** один из вариантов — завести контракт в `Media/Application/Contract` (например,
  `MediaUploadSettingsContract` с `presignedTtlSeconds`/`stagingTtlSeconds`/`multipartThresholdBytes`/
  `multipartPartSizeBytes`/`imageProcessingDriver`) и привязать к нему `MediaConfig` в `MediaBootloader`,
  оставив сам config-объект внутри Infrastructure-границы; либо осознанно принять прямую инъекцию и
  зафиксировать исключение в `arch.md`/README (по образцу уже принятого trade-off про S3-I/O внутри
  `#[Transactional]`), чтобы конвенция стала явной.
- **Тесты:** при вынесении контракта — переключить unit/feature-стабы Handler'ов на контракт; поведение не
  меняется, новых сценариев не требуется.

## Рекомендации

- **Править обязательно:** —
- **На усмотрение автора:** 2

## Изменения после мета-ревью

Запущены 4 специализированных субагента (`architecture-check`, `rules-check`, `plan-check`,
`quality-check`) на модели `sonnet`, одним batch (в этом окружении субагентный tool недоступен —
роли исполнены параллельными процессами `claude -p --model sonnet`, по одному на роль, каждому передан
файл ревью, target-diff, план, `rules.md`, `arch.md`). Режим `strict` — далее кросс-CLI (Codex).

### После architecture-check
- **+ Добавлено (замечание №2):** Application-Handler'ы `RequestMediaUploadHandler`/`GetMediaUrlHandler`
  инжектят `MediaConfig` из `Shared\Infrastructure\Configuration` напрямую. Проверил grep'ом: это
  единственные Application-потребители config-объекта из `Shared/Infrastructure` во всём проекте
  (остальные конфиги — только в Infrastructure/Presentation); `arch.md` не перечисляет `Shared/Infrastructure`
  среди допустимых зависимостей Application. Новый, ранее не поднимавшийся пункт; `quality`/на усмотрение.
- **Подтверждено агентом без правок:** границы модуля (Media без HTTP, только Application-API), CQRS-структура,
  outbox-поток в одной транзакции, контракты в `Application/Contract` + реализации в `Infrastructure/FileService`,
  размещение исключений в `Infrastructure/Exception` (уже отклонённый пункт, новых аргументов нет).

### После rules-check
- Реальных нарушений `rules.md` не найдено: `declare(strict_types=1)`, `match` вместо `switch`, нет `assert()`,
  строгие сравнения, именованные аргументы, guard clauses, `foreach` только с side-effect, нет мёртвого кода,
  Entity без примитивов, VO-контракт, read-only репозитории, типизированные исключения, короткие `handle()`,
  trailing commas — всё выдержано. Утверждение ревью об отсутствии новых нарушений правил подтверждено.
  Дополнять/убирать нечего.

### После plan-check
- **Подтверждено:** все шесть фаз выполнены и подтверждаются кодом (sync-feature-тест на `QUEUE_CONNECTION=sync`,
  guard `markReadyMovedTo` до `ProcessingFailed` задокументирован в docblock и покрыт тестом восстановления,
  фактические размеры конверсий закреплены тестом). Пропущенных требований плана нет.
- **~ Учтено (в №1 и в «Проблемы сверки с планом»):** агент указал, что обоснование «фактические размеры»
  описывает поведение, недостижимое при `cover()`. Это усилило замечание №1 (документация/тест), фактическую
  корректность записи в БД не меняет.
- **Отклонено:** «уровень лога `media готова` DEBUG — голословное осознанное отклонение без артефакта».
  Проверил: решение задокументировано в фиксе цикла 1 (№10) с обоснованием по `rules.md:84` (INFO — только
  для ключевых бизнес-событий, рутинное per-media завершение → DEBUG). Артефакт есть (цепочка фиксов +
  правило), поэтому формулировка ревью «осознанное отклонение» корректна; в README дублировать не требуется.

### После quality-check
- **+ Добавлено (замечание №1):** обоснование «храним фактические размеры, а не spec» в README/комментарии
  описывает `contain`/aspect-fit, тогда как `ImagickMediaImageProcessor::resize` использует `cover()`
  (точные spec-размеры всегда). Проверил по vendor (`cover()` = crop+resize до точных размеров) и по
  собственному журналу исполнения (строка 37: «== spec при cover»). Тест `testStoresActualProcessorDimensionsNotSpec`
  закрепляет недостижимый при `cover` сценарий `100×100 → 100×56` (проходит только из-за mock-процессора).
  Новый, ранее не поднимавшийся пункт; `quality`/на усмотрение.
- **Отклонено:** «идентичные тела `recordTemporaryProcessingError`/`recordPermanentProcessingError` —
  дублирование/мёртвый production-путь». По сути это уже отклонённый пункт про dead-code этих методов.
  Новый аргумент «оба модифицированы этим diff'ом (добавлен guard), значит в scope» не перевешивает: guard
  в `recordPermanentProcessingError` — это и есть принятый optional-фикс цикла 4, после которого метод получил
  содержательный доменный guard и **полное покрытие двумя ветками** (фикс-отчёт цикла 4 это прямо разобрал —
  метод больше не мёртвый и не дубликат без отличий). Слияние двух методов в один и пересмотр контракта
  транзиентная/постоянная ошибка — отдельная задача рефакторинга домена, а не дефект этого diff. Возвращать
  без нового веса не следует.

### После соседнего CLI (codex) — НЕ ЗАВЕРШЁН (внешняя причина, как и в цикле 4)
Кросс-CLI в режиме `strict` запускался, но **не завершён по внешним причинам**, не связанным с содержанием
ревью:
- сконфигурированная модель `gpt-5.3-codex` не поддерживается на текущем Codex-аккаунте (ChatGPT): API вернул
  `400 invalid_request_error — 'gpt-5.3-codex' model is not supported when using Codex with a ChatGPT account`;
- fallback на дефолтную модель аккаунта (`gpt-5.5`) упёрся в usage limit:
  `You've hit your usage limit … try again at Jun 12th, 2026 11:51 AM`.

Согласно явной инструкции запуска, на этом не блокируюсь: мета-проверка четырьмя субагентами завершена и
применена выше, итоговый score зафиксирован. Для завершения кросс-CLI повторно запустить
`eda-review-check strict` после сброса лимита Codex (после 2026-06-12 11:51) или с поддерживаемой аккаунтом
моделью. `status` остаётся `meta-reviewed` (до `final` не повышается без кросс-CLI).

### Пересчёт оценки
Добавлены два валидных optional-замечания «на усмотрение автора», ранее не поднимавшихся: №1 (обоснование
фактических размеров противоречит `cover()` — документация и тест вводят в заблуждение) и №2 (Application-Handler'ы
зависят от config из `Shared/Infrastructure` — несогласованность с правилом зависимостей слоёв). Блокеров и
обязательных правок по-прежнему нет; оба замечания минимального веса, но №1 вскрывает неверную документацию +
тест на недостижимый сценарий, что снижает уверенность в «чистоте» сильнее обычного nit. `score` 95 → 93,
`status: draft → meta-reviewed`.

## Применённые фиксы
Отчёт: `docs/review-fixes/2026-06-10_15-30_media-upload-pipeline-uncommitted-draft.md`

Отклонённые optional-решения:
- **№2 (рефакторинг отклонён, исключение задокументировано):** вынесение `MediaUploadSettingsContract`
  в `Media/Application/Contract` не выполнено — это была бы pass-through-обёртка над `TypedConfig` ради
  формального списка зависимостей (противоречит правилу «Без pass-through typecast-обёрток» и плану,
  явно закрепившему прямую инъекцию `MediaConfig`). Вместо рефакторинга прямая инъекция `TypedConfig`
  в Application осознанно легитимизирована в `docs/arch.md` («Правила зависимостей») и README по образцу
  trade-off про S3-I/O в `#[Transactional]`. Повторно как открытое замечание не поднимать без новых
  аргументов.
