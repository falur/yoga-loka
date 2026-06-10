---
review: docs/reviews/2026-06-10_13-47_media-upload-pipeline.md
date: 2026-06-10 14:15
status: done
mode: apply-optional
---

# Фиксы по ревью: Media upload pipeline — цикл 7

Режим `apply-optional`. В ревью обязательных правок нет, два optional-замечания
(«на усмотрение автора»). Оба применены — оценены как реальные улучшения, согласованные
с `docs/rules.md` и `docs/arch.md`.

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Вторичный сбой записи ошибки в `ProcessMediaJob::invoke` (`catch`) подменял исходную причину и решение retry/terminal | `app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php`, `app/src/Modules/Media/README.md` | `ProcessMediaJobTest::testTransientFailureStillRetriesWhenRecordingErrorThrows` (1 ✓) | ✓ применено |
| 2 | `assertConversionsValid` обходил `list<...>` через `foreach` ради чистой проверки границ | `app/src/Modules/Media/Application/Command/Media/CompleteMediaUpload/CompleteMediaUploadHandler.php` | существующий `CompleteMediaUploadHandlerTest::testRejectsConversionDimensionsOutOfDomainRange` (4 кейса) + happy-path (валидная спека) — обе ветки предиката покрыты | ✓ применено |

## Решения по optional

- **Принято №1 (реальное улучшение устойчивости).** Вызов `dispatch(RecordMediaProcessingFailureCommand)`
  внутри `catch (\Throwable $exception)` обёрнут локальным `try/catch (\Throwable $recordFailure)`. При
  сбое записи (медиа конкурентно удалили → `NotFoundException` в `RecordMediaProcessingFailureHandler::handle`,
  либо короткий сбой БД) вторичный сбой логируется уровнем **ERROR** (по `rules.md:84` — реальная
  инфра/инвариант-проблема), после чего поток продолжает к решению retry/terminal **по исходному
  `$exception`**. Классификация транзиентности (`$isTransient`) и санитизация сообщений (`safeMessage`,
  предопределённые безопасные строки) не менялись — `$isTransient` считается до guard'а, по исходному
  исключению. Для транзиентного исходного сбоя Job по-прежнему бросает `RetryException`; исходная причина
  не подменяется ошибкой записи. Guard выполняет реальную обработку (логирование + продолжение к
  классификации), поэтому не нарушает запрет `rules.md:47` на «голую перетипизацию `\Throwable`».
  Добавлен тест: `MediaUploaded` с несуществующим `mediaId` → `RecordMediaProcessingFailureHandler`
  бросает `NotFoundException` → для транзиентного исходного сбоя Job всё равно бросает `RetryException`
  (а не `NotFoundException`), и при этом логируются и ERROR (вторичный сбой), и WARNING (транзиентный
  ретрай). README модуля дополнен описанием guard'а.

- **Принято №2 (консистентность со стилем модуля без потери читабельности).** `foreach` заменён на
  `Collection::make($conversions)->contains(fn(MediaConversionSpec $c) => !supports($c->width) || !supports($c->height))`
  + ранний `throw` при `true`. Ревью отметило, что guard-`foreach` допустим (список — `list<...>` от
  Valinor, не доменная коллекция), но предложило выразить через `contains`. Применено, потому что:
  правило `rules.md:19` («Collection-пайплайны для чистых трансформаций, `foreach` — только при побочных
  эффектах») здесь буквально на стороне пайплайна — проверка чистая, без побочных эффектов; тот же стиль
  `foreach→map`/`contains` уже применён в самом diff (`S3MediaFileService:127`,
  `MediaMultipartPartCollection:31` — `->map(...)`). Читабельность guard'а не пострадала: выражение
  «есть ли конверсия вне диапазона?» читается прямо, ранний `throw` сохранён. Поведение не изменилось
  (та же `ValidationException` 422), существующий data-provider-тест границ остаётся валиден и покрывает
  обе ветки предиката (out-of-range → `true`, валидная спека в happy-path → `false`).

- **Отклонено:** ничего из двух optional-пунктов не отклонено.

## Что НЕ менялось (по решению ревью / осознанные trade-off)

- Классификация транзиентности и санитизация сообщений в `ProcessMediaJob` — не трогались (явное
  требование замечания №1).
- Осознанные отклонения плана (размеры конверсии из результата процессора, `markReadyMovedTo` guard,
  S3-I/O внутри `#[Transactional]` для multipart-ветки) — задокументированы ранее, не трогались.
- Глобальный coverage-долг НЕ-media классов (`LazyGhostMapper`, `ValueObjectCast`,
  `ExceptionHandlerBootloader` и т.п.) — пред-существующий, разобран в циклах 1–6, не регресс.

## Финальная проверка (Docker)

Docker-инфра была поднята и здорова (postgres/redis/minio/rabbitmq/temporal/centrifugo/mailpit —
healthy); тестовая БД мигрирована.

- **PHPStan (level max):** `make phpstan` — ✓ `[OK] No errors`.
- **Тесты:** `make test` — ✓ **331/331**, 1116 assertions (включая новый
  `testTransientFailureStillRetriesWhenRecordingErrorThrows`). Deprecations (1 + 29 PHPUnit) и 1 PHPUnit
  Notice — пред-существующие, не связаны с media (фигурировали в прошлых циклах).
- **Стиль (cs):** шаг `make qa` `composer cs` (php-cs-fixer dry-run) — ✓ зелёный (импорты
  `MediaConversionSpec`/`Collection` отсортированы; новый guard прошёл стиль).
- **Покрытие (media-код):** новый/затронутый media-код — **100% statements** (адресный прогон
  media-сьюта, clover): `ProcessMediaJob.php` 42/42 (включая новую ветку вторичного сбоя записи —
  `catch (\Throwable $recordFailure)` + ERROR-лог, 0 непокрытых строк), `CompleteMediaUploadHandler.php`
  43/43 (предикат `Collection::contains` — обе ветки: out-of-range → `true`, валидная спека → `false`),
  `RecordMediaProcessingFailureHandler.php` 11/11.
- **Покрытие (глобальный gate):** `make qa` шаг `test-coverage` — ✗ красный: `Покрытие 93.00% ниже
  обязательного порога 100.00%` → `make: *** [qa] Error 1`. Это **пред-существующий долг чужих
  не-media классов** (`LazyGhostMapper` 75.56%, `ValueObjectCast` 78.64%, `LazyGhostEntityFactory`
  87.80%, `ExceptionHandlerBootloader` 75.00%, `ConfigMappingException` 88.46% — те же файлы, что в
  циклах 1–6). **Не регресс** этих правок: media-код на 100%, мои изменения не задели не-media классы.
- **Заметки:** красным остаётся только глобальный coverage-gate `make qa` — пред-существующее осознанное
  отклонение, не вызванное этими правками. cs / phpstan / test зелёные, весь затронутый media-код — 100%.
