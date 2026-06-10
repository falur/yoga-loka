---
review: docs/reviews/2026-06-10_16-10_media-upload-pipeline-uncommitted-draft.md
date: 2026-06-10 16:40
status: done
mode: apply-optional
---

# Фиксы по ревью: Media upload pipeline — незакоммиченный diff (шестой цикл)

Режим `apply-optional`. В ревью обязательных правок нет, четыре optional-замечания
(«на усмотрение автора»). Все четыре конкретны, применимы и улучшают качество — применены.

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Асимметрия валидации размеров конверсии: синхронно проверялась только нижняя граница, верхняя ловилась лишь асинхронно в Job → терминальный `ProcessingFailed` | `app/src/Shared/Domain/ValueObject/AbstractIntegerValue.php` (новый `supports()`), `app/src/Modules/Media/Application/Command/Media/CompleteMediaUpload/CompleteMediaUploadHandler.php` | `CompleteMediaUploadHandlerTest::testRejectsConversionDimensionsOutOfDomainRange` (4 кейса: нижняя/верхняя × ширина/высота), `MediaValueObjectTest::testIntegerValueObjectsValidateBorders` (+ `supports`/`jsonSerialize`/`__toString`) | ✓ применено |
| 2 | Не покрыты обе rethrow-ветки `S3MediaFileService::completeMultipartUpload` | `tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php` | `testCompleteMultipartUploadRethrowsNoSuchUploadWhenObjectIsMissing`, `testCompleteMultipartUploadRethrowsNonNoSuchUploadError` | ✓ применено |
| 3 | Уровень лога транзиентной ошибки в `ProcessMediaJob` — ERROR вместо WARN | `app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php` | `ProcessMediaJobTest::testLogsTransientFailureAsWarningAndPermanentAsError` (2 кейса) + fixture `RecordingMediaLogger` | ✓ применено |
| 4 | Утечка слоя: `ProcessMediaJob` (Presentation) импортировал `Aws\Exception\AwsException` и классифицировал транзиентность по внутренностям AWS | `ProcessMediaJob.php`, `S3MediaFileService.php`, новый `app/src/Modules/Media/Application/Exception/MediaFileServiceFailedException.php`, удалён `Infrastructure/Exception/MediaFileServiceException.php`, `README.md` | `S3MediaFileServiceErrorTest` (классификация транзиентности + wrap каждой ветки), `ProcessMediaJobTest`, `S3MediaFileServiceTest` | ✓ применено (чистый рефактор) |

## Решения по optional

- **Принято №1.** Симметричный guard верхней границы на той же Application-границе
  `CompleteMediaUpload`. Без магических чисел: добавлен публичный `AbstractIntegerValue::supports(int): bool`,
  выражающий контракт диапазона `[MIN; MAX]` (для `MediaPixelDimension` — `1..100_000`).
  `assertConversionsValid` теперь отвергает и `0`/негатив, и `> MAX` через `MediaPixelDimension::supports()`
  с `ValidationException` (422). Раньше `width=200000` проходил подтверждение и падал асинхронно в Job
  (`InvalidDomainValueException` → `isTransient=false` → терминальный `ProcessingFailed`). Тест
  `testRejectsNonPositiveConversionDimensions` переименован в `testRejectsConversionDimensionsOutOfDomainRange`
  и расширен data-provider'ом (нижняя и верхняя границы по ширине и высоте).

- **Принято №2.** Добавлены две rethrow-ветки `completeMultipartUpload`: `NoSuchUpload` + объекта нет
  (`headObject` отвечает `NoSuchKey` → `null` → идемпотентность не подтверждена → rethrow) и иной код
  ошибки (`AccessDenied` → rethrow). По образцу соседних покрытых rethrow-веток.

- **Принято №3.** Уровень лога выбирается по уже посчитанному `isTransient`: транзиентный
  (ретраябельный) сбой → `WARNING` (по `rules.md:84` — реальная, но временная проблема инфраструктуры),
  постоянный → `ERROR`. Добавлен спай-логгер `RecordingMediaLogger` и тест, проверяющий уровень для
  обеих веток. Существующий `failureProvider` обновлён под контрактный сигнал (см. №4) — `NullLogger`
  по-прежнему используется там, где уровень не ассертится.

- **Принято №4 (чистый рефактор, приоритетный вариант).** Сигнал транзиентности вынесен на границу
  контракта. Создан `App\Modules\Media\Application\Exception\MediaFileServiceFailedException`
  (`Application/Exception`, потому что слой исключения определяется контрактом `MediaFileServiceContract`,
  по образцу `OutboxMessageLoadingException`) с типизированным `isTransient()` и фабриками
  `transient()`/`permanent()`. `S3MediaFileService` классифицирует сырой `AwsException` (connection/5xx/
  throttling) у себя в Infrastructure и оборачивает его в это контрактное исключение; знание об AWS
  осталось в Infrastructure. `ProcessMediaJob` больше не импортирует `Aws\*` — реагирует только на
  `MediaFileServiceFailedException::isTransient()`. Удалён инфраструктурный `MediaFileServiceException`
  (его внутренние-shape ошибки `missingUploadId`/`missingContentLength`/`unreadableBody` переведены в
  `MediaFileServiceFailedException::permanent(...)`). Санитизация сообщений (предопределённые безопасные
  строки, сырой AWS-текст не прокидывается) и поведение ретраев (`RetryException`) сохранены. README
  модуля обновлён. Оборачивание AWS-исключения здесь оправдано правилом `rules.md` («добавляет
  существенный контекст, нужный потребителю, или требуется контрактом границы»): несёт типизированный
  сигнал, нужный Job, и прячет AWS от верхних слоёв.

- **Отклонено:** ничего из четырёх optional-пунктов не отклонено.

## Что НЕ менялось (по решению ревью)

- Красный глобальный coverage-gate `make qa` — пред-существующий осознанный долг НЕ-media классов
  (`LazyGhostMapper`, `ValueObjectCast`, `ExceptionHandlerBootloader` и т.п.), разобран в циклах 1–5.
  Не регресс: общий статемент-coverage даже подрос (92.66% → 92.93%) за счёт новых media-тестов.
- Прочие осознанные trade-off (DEBUG-лог «Медиа готово», `markReadyMovedTo` guard, размеры конверсии
  из результата процессора) — не трогались.

## Финальная проверка (Docker)

- **PHPStan:** `make phpstan` — ✓ `[OK] No errors`.
- **Тесты:** `make test` — ✓ зелёный (327 → итог после правок зелёный; 1 deprecation, 29 PHPUnit
  deprecations, 1 PHPUnit notice — все пред-существующие, не связаны с media).
- **Стиль (внутри `make qa`):** PHP CS Fixer — ✓ (поправлен nullable-стиль `\Throwable|null` в новом
  исключении под `ordered_types`/`nullable_type_declaration`).
- **Покрытие:** `make qa` — глобальный gate красный (92.93% < 100%) — это пред-существующий долг
  НЕ-media классов (см. выше), а НЕ регресс. Все затронутые/новые media-файлы — **100% statements**:
  `S3MediaFileService.php`, `CompleteMediaUploadHandler.php`, `ProcessMediaJob.php`,
  `MediaFileServiceFailedException.php` — проверено по `runtime/coverage/clover.xml` (none uncovered).
  `AbstractIntegerValue.php` (Shared) тоже доведён до 100% добавленными ассертами.

## Заметки

- Поведение ретраев сохранено: транзиентный storage-сбой → `RetryException` (WARN), постоянный
  (storage permanent / Imagick / домен) → терминальный проброс (ERROR). В проде сырой `AwsException`
  больше не доходит до Presentation — его перехватывает и классифицирует `S3MediaFileService`.
- `ProcessMediaJobTest::failureProvider` и `S3MediaFileServiceTest::testCompleteMultipartUploadRethrowsOnInvalidPart`
  обновлены под новый контракт (ожидают `MediaFileServiceFailedException` вместо сырого AWS-исключения).
