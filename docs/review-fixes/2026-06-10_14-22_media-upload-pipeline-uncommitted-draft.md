---
review: docs/reviews/2026-06-10_14-22_media-upload-pipeline-uncommitted-draft.md
date: 2026-06-10 14:22
status: done
mode: apply-optional
---

# Фиксы по ревью: Media upload pipeline — незакоммиченный diff (четвёртый цикл)

Режим `apply-optional`. Обязательных пунктов («править обязательно») в ревью нет. Оба замечания —
«на усмотрение автора». По указанию пользователя и по существу оба полезны и применены: №1 — недорогая
защита доменного инварианта на будущее; №2 — закрепление осознанного выбора (фактические размеры) в коде,
README и тесте от регрессии. Оба **применены**.

## Применённые правки
| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | `RecordMediaProcessingFailure` пишет сбой без проверки статуса — теоретически откатывает `ready`-медиа в `ProcessingFailed` | `app/src/Modules/Media/Domain/Entity/Media.php` (guard в `recordTemporaryProcessingError` и `recordPermanentProcessingError`) | `tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php` (+1 `testKeepsReadyMediaIntact`); `tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php` (+2 `testRecordProcessingErrorIsNoOpOnReadyMedia`, `testRecordPermanentProcessingErrorMovesUploadedToProcessingFailed`) | ✓ применено |
| 2 | `MediaImageConversion` хранит фактические размеры процессора вместо `spec.width/spec.height` — недокументированное расхождение с планом | `app/src/Modules/Media/Application/Command/Media/ProcessMedia/ProcessMediaHandler.php` (комментарий у `create`); `app/src/Modules/Media/README.md` (запись про фактические размеры) | `tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php` (+1 `testStoresActualProcessorDimensionsNotSpec`, усилен helper `handler()`) | ✓ применено |

## Решения по optional

### Принято (оба)

- **№1 — защита инварианта в доменном методе (выбор уровня).** Пользователь просил выбрать уровень
  (Handler или доменный метод) по образцу guard'а в `markReadyMovedTo`. Выбран **доменный метод**, а не
  Handler, по двум причинам строго в рамках `arch.md`/`rules.md`:
  - Образец `markReadyMovedTo` держит guard «на `ready` → no-op» именно в доменном методе (`Media.php`),
    а не в Handler'е. Обратный переход в `ProcessingFailed` — симметричный, поэтому его guard логично
    положить туда же. Это ровно тот паттерн, который просил пользователь.
  - `arch.md`: «Доменная модель может выражать инварианты и состояние». Guard в домене защищает инвариант
    «`ready` без ошибки» **независимо от вызывающего**. Это и есть суть замечания «защита на будущее»:
    если фиксацию сбоя задиспатчат в обход `isReady`-guard'а в `ProcessMediaHandler` (другой relay,
    ручной перезапуск Job, дубликат в очереди), готовое медиа всё равно не «сломается». Guard только в
    Handler'е такой защиты не дал бы — именно тот сценарий, от которого предостерегает ревью.

  Реализация: в начале `recordTemporaryProcessingError` и `recordPermanentProcessingError` добавлен
  `if ($this->status === MediaStatus::Ready) { return; }` (ранний no-op, симметрично `markReadyMovedTo`).
  Guard добавлен **в оба** симметричных метода фиксации сбоя (транзиентный и постоянный), чтобы инвариант
  держался для любого пути. Handler `RecordMediaProcessingFailureHandler` намеренно не трогался — инвариант
  теперь обеспечивает домен (check-then-act в Handler'е был бы дублированием доменного guard'а).

  Тесты: Handler-кейс `testKeepsReadyMediaIntact` (точно по формулировке ревью: `RecordMediaProcessingFailure`
  для уже `ready`-медиа → статус остаётся `Ready`, `processingError` пуст, попытки = 0). Доменные unit-кейсы:
  `testRecordProcessingErrorIsNoOpOnReadyMedia` (no-op-ветка обоих методов на `ready`) и
  `testRecordPermanentProcessingErrorMovesUploadedToProcessingFailed` (happy-path постоянной ошибки из
  `uploaded`, чтобы новая ветка `recordPermanentProcessingError` была покрыта целиком — раньше у метода не
  было вызовов в `app/`, теперь обе его ветки под тестом).

  Замечание про dead-code `recordPermanentProcessingError` из прошлых циклов **не воскрешается**: метод не
  удалён и не сохранён «как есть» — он получил содержательный доменный guard и полное покрытие, поэтому
  больше не является ни мёртвым, ни дубликатом без отличий от `recordTemporaryProcessingError`.

- **№2 — осознанно оставлены фактические размеры процессора + фиксация выбора.** По контракту хранимых
  данных в БД должен лежать настоящий размер залитого объекта, а не запрошенный (при aspect-fit/letterbox
  Imagick реальный выход может отличаться от spec). Возврат к `spec.width/spec.height` записал бы в БД
  размер, не соответствующий файлу, — это менее корректно. Выбор зафиксирован:
  - комментарий у вызова `MediaImageConversion::create` в `ProcessMediaHandler::buildConversions` —
    «храним фактический выход процессора, а не запрошенный spec; см. README»;
  - запись в `README.md` (раздел «Транзакционная дисциплина S3») с явной пометкой, что это осознанное
    отклонение от буквы плана (шаг 6, `MediaPixelDimension из spec.width/spec.height`).

  Тест: `testStoresActualProcessorDimensionsNotSpec` — процессор-стаб настроен на размеры, отличные от spec
  (запросили `100×100`, вернул `100×56`), проверяется, что в `MediaImageConversion` сохранены именно
  фактические `100×56`. Это закрепляет выбранный контракт от регрессии (если кто-то вернёт spec-размеры,
  тест упадёт). Helper `handler()` расширен параметрами `resultWidth`/`resultHeight` с дефолтами `100×100`,
  существующие кейсы не затронуты.

### Отклонено

Нет. Оба optional-пункта применены.

## Финальная проверка

Проверки прогнаны в Docker (Docker Desktop был не запущен — поднял сам, дождался готовности демона,
прогнал полностью).

- **Стиль (cs):** `make qa` шаг `composer cs` (php-cs-fixer dry-run) — ✓ зелёный.
- **PHPStan (level max):** `make phpstan` и шаг `make qa` — ✓ `[OK] No errors`.
- **Тесты:** `make test` — ✓ 318/318 (было 315, +3 новых кейса), 1073 assertions. В `make qa` тот же
  набор прошёл повторно зелёным. Deprecations (1 + 29 PHPUnit) и 1 Notice — пред-существующие, не
  связаны с задачей (фигурировали и в прошлых циклах).
- **Покрытие (media-код):** новый/изменённый media-код покрыт на **100%** (clover full-suite):
  `Media.php` 65/65 строк, 13/13 методов (обе новые guard-ветви в `recordTemporaryProcessingError`/
  `recordPermanentProcessingError` исполнены); `ProcessMediaHandler.php` 69/69, 4/4;
  `RecordMediaProcessingFailureHandler.php` 11/11, 2/2; `MediaImageConversion.php` 14/14, 1/1;
  `MediaConversionResult.php` 1/1, 1/1. Регресса покрытия media-кода нет.
- **Покрытие (глобальный gate):** `make qa` шаг `test-coverage` — ✗ красный: `Покрытие 92.89% ниже
  обязательного порога 100.00%` → `make: *** [qa] Error 1`. Это **пред-существующий долг чужих
  не-media классов** (`LazyGhostEntityFactory`/`LazyGhostMapper`/`LazyGhostReflectionRegistry`,
  `ValueObjectCast`, `ConfigMappingException`, `ExceptionHandlerBootloader`), осознанно
  разобранный в прошлых циклах (сверка с планом №1). Не регресс этой задачи: затронутый media-код
  на 100%.
- **Заметки:** красным остаётся только глобальный coverage-gate `make qa` — пред-существующее
  осознанное отклонение, не вызвано этими правками. cs/phpstan/test зелёные, media-покрытие 100%.
