---
review: docs/reviews/2026-06-10_15-30_media-upload-pipeline-uncommitted-draft.md
date: 2026-06-10 15:30
status: done
mode: apply-optional
---

# Фиксы по ревью: Media upload pipeline — незакоммиченный diff (пятый цикл)

Режим `apply-optional`. Обязательных пунктов («править обязательно») в ревью нет, оба замечания —
«на усмотрение автора». №1 (приоритетный — дефект, внесённый фиксом прошлого цикла: документация и
тест описывают недостижимый при `cover()` сценарий) **применён**. №2 (Application-Handler'ы инжектят
`MediaConfig` из `Shared/Infrastructure/Configuration` напрямую) — **рефакторинг отклонён, исключение
осознанно задокументировано** в `arch.md` и README по образцу принятого trade-off про S3-I/O в
`#[Transactional]`. Режим ресайза `cover()` не менялся (как и просил пользователь).

## Применённые правки
| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Обоснование «храним фактические размеры, а не spec» описывает `contain`/aspect-fit, а процессор делает `cover()` (всегда даёт точные spec-размеры) — комментарий, README и тест вводят в заблуждение | `app/src/Modules/Media/Application/Command/Media/ProcessMedia/ProcessMediaHandler.php` (переписан комментарий у `MediaImageConversion::create`); `app/src/Modules/Media/README.md` (раздел «Транзакционная дисциплина S3») | `tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php` (`testStoresActualProcessorDimensionsNotSpec` → `testStoresConversionDimensionsFromProcessorResult`, переписан комментарий, контракт сохранён) | ✓ применено |
| 2 | Application-Handler'ы инжектят `MediaConfig` из `Shared/Infrastructure` напрямую — единственный такой случай, формально вне списка зависимостей Application в `arch.md` | `docs/arch.md` («Правила зависимостей» — осознанное исключение); `app/src/Modules/Media/README.md` («Инфраструктура и конфиг») | — (поведение не меняется) | ✗ рефакторинг отклонён, исключение задокументировано |

## Решения по optional

### Принято

- **№1 — исправлена вводящая в заблуждение документация/тест (приоритет).** Дефект внесён фиксом
  цикла 4: комментарий у `MediaImageConversion::create`, запись в README и тест
  `testStoresActualProcessorDimensionsNotSpec` объясняли хранение фактических размеров через
  «aspect-fit/letterbox: запросили `100×100`, получили `100×56`». Но реальный
  `ImagickMediaImageProcessor::resize` использует `$image->cover(width, height)` — это
  aspect-fill с обрезкой до **точных** запрошенных размеров (Intervention v4), после которого
  `$image->width()/height()` **всегда** равны spec. Сценарий расхождения с `cover()` недостижим, что
  подтверждает и собственный журнал исполнения (`docs/executions/2026-06-09_12-46_...:37` — «== spec
  при cover»). Тест проходил лишь из-за мока-процессора.

  Что сделано (без смены режима ресайза — `cover()` оставлен, как просил пользователь):
  - **Комментарий** у `MediaImageConversion::create` в `ProcessMediaHandler::buildConversions`
    переписан на честную формулировку: берём `conversionResult->width/height`, потому что в БД должен
    лежать размер реально записанного объекта; текущий `cover()` всегда выдаёт точные spec-размеры, так
    что значения совпадают, а чтение из результата устойчиво к будущей смене режима на `contain`/`scale`.
  - **README** (раздел «Транзакционная дисциплина S3», запись про размеры) переписан так же: добавлено,
    что с `cover()` фактические значения совпадают со spec, и убрана ложная посылка «letterbox даёт
    `100×56`».
  - **Тест** переименован в `testStoresConversionDimensionsFromProcessorResult` и переформулирован: он
    проверяет корректный контракт Handler'а — в БД попадают значения, которые вернул процессор, а не
    spec. Стаб по-прежнему возвращает размеры, отличные от spec (`100×56` против `100×100`), но комментарий
    теста теперь явно говорит, что это **артефакт стаба** для проверки источника данных, а не поведение
    продакшен-процессора (реальный `cover()` вернул бы `100×100`). Тест остаётся дискриминирующим (если
    кто-то начнёт читать из spec, он упадёт) и больше не утверждает недостижимый при `cover` сценарий как
    реальное поведение.

### Отклонено (рефакторинг), решение задокументировано

- **№2 — рефакторинг «вынести `MediaUploadSettingsContract` в `Application/Contract`» отклонён;
  исключение осознанно задокументировано.** Оценка баланса польза/риск:
  - **Против рефакторинга.** План явно и многократно закрепил прямую инъекцию `MediaConfig`
    (строки 148-154; в plan-polish iter 2 перенос конфига из `Shared` отклонён как противоречащий
    `arch.md`). `rules.md` («Typed config для каждого config-файла») и `arch.md` («Configuration →
    app/config → Shared/Infrastructure/Configuration») предписывают единое размещение всех config-DTO в
    `Shared/Infrastructure/Configuration`. `MediaConfig` — это `TypedConfig`, а не технический сервис с
    поведением. Оборачивание config-DTO в Application-`*Contract` с привязкой в бутлоадере = pass-through-
    обёртка над `TypedConfig` ради формального списка, что прямо противоречит правилу «Без pass-through
    typecast-обёрток» (тот же принцип) и сделало бы Media единственным модулем, прячущим собственный
    typed-config за контрактом — новая несогласованность вместо устранённой. Затрагивает 2 хендлера +
    бутлоадер + стабы тестов без изменения поведения.
  - **За документирование.** Ревью само предлагает альтернативу — осознанно зафиксировать исключение по
    образцу уже принятого trade-off про S3-I/O внутри `#[Transactional]`. Сделано:
    - `docs/arch.md` после блока «Правила зависимостей» добавлен абзац: `TypedConfig` из
      `Shared/Infrastructure/Configuration` может инжектиться напрямую в Application-Handler (пример —
      `MediaConfig` в `RequestMediaUploadHandler`/`GetMediaUrlHandler`); технический сервис с поведением
      по-прежнему идёт через `Application/Contract` + реализацию в `Infrastructure`.
    - `app/src/Modules/Media/README.md` (раздел «Инфраструктура и конфиг») — короткая ссылка на это
      исключение со ссылкой на `arch.md`.

  Итог: пункт не оставлен без решения — конвенция стала явной, прямая инъекция `MediaConfig` легитимизирована
  на уровне `arch.md`/README.

## Финальная проверка

Проверки прогнаны в Docker (демон был запущен; инфра-контейнеры postgres/redis/minio/rabbitmq/temporal/
centrifugo/mailpit подняты, тестовая БД мигрирована).

- **Стиль (cs):** шаг `make qa` `composer cs` (php-cs-fixer dry-run) — ✓ зелёный.
- **PHPStan (level max):** `make phpstan` и шаг `make qa` — ✓ `[OK] No errors`.
- **Тесты:** `make test` / `make qa` — ✓ **318/318**, 1073 assertions. Deprecations (1 + 29 PHPUnit) и
  1 Notice — пред-существующие, не связаны с задачей (фигурировали в прошлых циклах). Переименованный
  тест `testStoresConversionDimensionsFromProcessorResult` зелёный.
- **Покрытие (media-код):** новый/затронутый media-код — **100%** (адресный прогон media-сьюта,
  clover): `ProcessMediaHandler.php` 69/69, `RequestMediaUploadHandler.php` 80/80,
  `GetMediaUrlHandler.php` 29/29, `ImagickMediaImageProcessor.php` 16/16. Регресса нет (правки №1 —
  комментарий + переименование теста на тех же ветках; №2 — только docs/README).
- **Покрытие (глобальный gate):** `make qa` шаг `test-coverage` — ✗ красный: `Покрытие 92.89% ниже
  обязательного порога 100.00%` → `make: *** [qa] Error 1`. Это **пред-существующий долг чужих
  не-media классов** (`LazyGhostMapper` 75.56%, `ValueObjectCast` 78.64%, `LazyGhostEntityFactory`
  87.80%, `ExceptionHandlerBootloader` 75.00%, `ConfigMappingException` 88.46% и т.п.), осознанно
  разобранный в циклах 1–4. Не регресс этой задачи: цифра `92.89%` идентична цифре цикла 4.
- **Заметки:** красным остаётся только глобальный coverage-gate `make qa` — пред-существующее осознанное
  отклонение, не вызвано этими правками. cs/phpstan/test зелёные, media-покрытие 100%.
