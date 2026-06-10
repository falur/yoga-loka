---
title: Media upload pipeline — незакоммиченный diff (шестой цикл, после пяти ревью)
date: 2026-06-10 16:10
target: git diff HEAD + untracked (модуль Media)
plan: docs/plans/2026-06-08_22-56_media-upload-pipeline.md
mode: strict
score: 93
status: meta-reviewed
meta_reviewers: [architecture-check (sonnet), rules-check (sonnet), plan-check (sonnet), quality-check (sonnet)]
---

# Ревью: Media upload pipeline — незакоммиченный diff (шестой цикл)

## Оценка

**93/100.** Реализация полностью покрывает план и сошлась после пяти циклов правок: переиспользуемый
Application-API без собственного HTTP, presigned/multipart через `Aws\S3\S3Client`, асинхронная
обработка через outbox, идемпотентный `ProcessMedia`, санитизация и классификация ошибок в
`ProcessMediaJob`. Блокеров и обязательных правок нет. Мета-ревью шестого цикла (architecture/rules/
plan/quality) подтвердило корректность ранее закрытых пунктов, но выявило четыре ранее не поднимавшихся
optional-наблюдения (см. раздел «Замечания» и «Рекомендации»); из-за этого оценка снижена с 95 до 93 —
все находки низкорисковые и «на усмотрение автора», ни одна не блокирует. Оба optional-замечания пятого
цикла применены/закрыты и подтверждаются в рабочем дереве:

- замечание №1 (вводящая в заблуждение документация/тест про размеры конверсий) исправлено:
  комментарий у `MediaImageConversion::create` в `ProcessMediaHandler` (строки 123-127), раздел README
  (строки 98-103) и тест `ProcessMediaHandlerTest::testStoresConversionDimensionsFromProcessorResult`
  (переименован) теперь честно говорят, что `cover()` всегда даёт точные spec-размеры, а чтение из
  результата процессора устойчиво к будущей смене режима ресайза — недостижимый при `cover` сценарий
  больше не выдаётся за реальное поведение;
- замечание №2 (Application-Handler'ы инжектят `MediaConfig` из `Shared/Infrastructure/Configuration`)
  закрыто как осознанное исключение: оно задокументировано в `docs/arch.md` («Правила зависимостей»,
  строки 264-279) и в README, рефакторинг в pass-through-обёртку обоснованно отклонён.

Проверено в этом цикле: `make phpstan` — `[OK] No errors`; media-сьют (`tests/Unit/Modules/Media` +
`tests/Feature/Modules/Media`) — 133 теста, 329 assertions, зелёный; 1 deprecation и 1 PHPUnit-notice
пред-существующие, не связаны с media. Красный глобальный coverage-gate `make qa` — пред-существующий
осознанный долг НЕ-media классов, разобран в циклах 1–5; повторно не поднимаю.

Все ранее открытые пункты пяти циклов либо применены, либо осознанно отклонены с фиксацией причины.
Реализация в сошедшемся состоянии; новые находки шестого цикла — четыре низкорисковых optional-пункта,
перечислены в «Замечаниях».

## Проблемы сверки с планом

Явных проблем по плану не найдено. Все шесть фаз отмечены выполненными и подтверждаются кодом:
зависимости и Docker (Imagick + GD-фолбэк + `aws/aws-sdk-php` явной зависимостью), `app/config/media.php`
+ `MediaConfig` (`TypedConfig`, guard `multipartThresholdBytes >= multipartPartSizeBytes`), Application-DTO
и контракты, `S3MediaFileService`, `ImagickMediaImageProcessor`, доменные методы
`markReadyMovedTo`/`isReady` и фабрики `MediaPath::imageConversion`/`originalReady`, CQRS-сценарии
(`RequestMediaUpload`/`CompleteMediaUpload`/`ProcessMedia`/`RecordMediaProcessingFailure`/`DeleteMedia`/
`MakeMediaPermanent` + `GetMediaUrl`/`CheckMediaIsImage`/`CheckMediaExists`), проводка outbox
(`MediaBootloader`, `queue.php` handlers/serializers, `Kernel` после `OutboxBootloader`), документация.

Единственное расхождение с буквой плана (строка 241: «`MediaPixelDimension из spec.width/spec.height`»)
— размеры `MediaImageConversion` берутся из фактического результата процессора, а не из spec. В пятом
цикле это закрыто как осознанное отклонение: при `cover()` значения совпадают, чтение из результата
устойчиво к смене режима ресайза; теперь корректно описано в комментарии/README/тесте. Запись в БД
корректна, сверка с планом не страдает.

Ранее зафиксированные осознанные отклонения остаются в силе и не ухудшены: уровень лога «Медиа готово»
DEBUG вместо INFO (по `rules.md:84` рутинное per-media завершение → DEBUG, решение фикса цикла 1 №10);
guard `markReadyMovedTo` расширен до `ProcessingFailed` (ретрай после транзиентной ошибки,
задокументировано в docblock `Media::markReadyMovedTo`, покрыто тестом восстановления); красный
глобальный coverage-gate. Повторно не поднимаю.

## Замечания

Блокирующих и обязательных правок нет. Мета-ревью шестого цикла выявило четыре ранее не поднимавшихся
optional-наблюдения (все «на усмотрение автора», низкий риск):

1. **Асимметрия валидации размеров конверсии: проверяется только нижняя граница, верхняя — нет.**
   `CompleteMediaUploadHandler::assertConversionsValid` (`CompleteMediaUploadHandler.php:104-111`)
   валидирует у `MediaConversionSpec` только `width <= 0 || height <= 0` → `ValidationException` (422).
   Верхнюю границу задаёт `MediaPixelDimension` (`MIN=1`, `MAX=100_000`), но она применяется только
   позже и асинхронно — в `ProcessMediaHandler::buildConversions` через `MediaPixelDimension::fromInt`
   (`ProcessMediaHandler.php:107-108`). Поэтому, например, `width=200000` проходит подтверждение загрузки
   (клиент получает успешный `MediaResult`), кладётся в outbox `MediaUploaded`, а затем падает в Job'е
   `InvalidDomainValueException` (не `AwsException` → `isTransient=false` → терминально) — медиа навсегда
   оседает в `ProcessingFailed`. План (строка 214) обосновывает синхронный guard ровно целью «чтобы
   0/негатив не дошёл до `MediaPixelDimension::fromInt` → 500 в `ProcessMedia`», но guard закрывает лишь
   нижнюю границу, а не верхнюю, на которую ссылается то же обоснование. Риск смягчён тем, что спеки
   конверсий задаёт серверный потребитель (своего HTTP-входа у Media нет), поэтому это не пользовательская
   атака, а несогласованность контракта; тем не менее симметричный guard верхней границы (или общая
   фабрика-валидатор) на той же Application-границе устранил бы «boundary слабее домена, который он питает».
   Тест `testRejectsNonPositiveConversionDimensions` покрывает только `width: 0`; верхняя граница на
   синхронной границе не проверяется и не тестируется.
2. **Тестовый пробел: rethrow-ветки `completeMultipartUpload` не покрыты.**
   В `S3MediaFileService::completeMultipartUpload` (`S3MediaFileService.php:123-131`) три исхода:
   `NoSuchUpload` + объект существует → успех (покрыт `testCompleteMultipartUploadTreatsNoSuchUploadAs...`);
   `NoSuchUpload` + объекта нет → rethrow; иной код ошибки → rethrow. Обе rethrow-ветки тестами не
   покрыты, хотя для соседних методов (`deleteObject`, `abortMultipartUpload`, `headObject`)
   rethrow-ветки покрыты явно (`S3MediaFileServiceErrorTest.php:131-166`). Риск низкий, но при заявленном
   100%-покрытии это реальный пробел.
3. **Уровень лога транзиентной ошибки в `ProcessMediaJob` — ERROR вместо WARN.**
   `ProcessMediaJob::invoke` (`ProcessMediaJob.php:66-77`) логирует ЛЮБОЙ сбой обработки на уровне
   `error`, включая транзиентную ветку (`isTransient === true`: connection/5xx/throttling AWS), которая
   тут же уходит в `RetryException` и при повторе обычно успешна. По `rules.md:84` «WARN — реальные
   проблемы инфраструктуры; ERROR — сбои, нарушения инвариантов» транзиентный ретраябельный сбой
   хранилища ближе к WARN, ERROR корректен для постоянной ветки (битый Imagick-файл/доменное нарушение).
   Флаг `isTransient` уже посчитан строкой выше — уровень можно выбрать по нему. Это отдельный от фикса
   цикла 1 №10 случай (там — уровень SUCCESS-лога «Медиа готово»; здесь — уровень FAILURE-лога).
4. **`MediaFileServiceContract` не объявляет сигнала транзиентности — классификация ошибок хранилища
   живёт в Presentation на сыром `Aws\Exception\AwsException`.**
   `ProcessMediaJob` (`ProcessMediaJob.php:14,81-102`) импортирует `Aws\Exception\AwsException` и в
   `isTransient()` разбирает внутренности AWS-исключения (`isConnectionError()`, `getStatusCode() >= 500`,
   AWS error codes). Это единственная не-framework внешняя зависимость во всём `Presentation/` модуля;
   AWS-SDK во всех остальных местах заперт в `Infrastructure/FileService`. `MediaFileServiceContract`
   спроектирован, чтобы прятать S3/AWS, но не объявляет контракта исключений: `S3MediaFileService`
   пробрасывает сырой `S3Exception`/`AwsException`, он проходит сквозь `ProcessMediaHandler` и доходит до
   Presentation, который тем самым «знает», что хранилище реализовано на AWS. При смене реализации
   контракта (другой SDK/локальный драйвер) классификация ретраев молча сломётся. Чистая альтернатива —
   типизированный сигнал транзиентности на границе контракта (например `isTransient()` у инфра-исключения,
   которое и так знает AWS), а Job реагирует на контрактный сигнал. Job легитимно адаптирует очередь
   (`RetryException`), но классификация storage-ошибки — знание реализации хранилища, а не очереди. На
   усмотрение автора: либо рефакторинг сигнала транзиентности в контракт, либо осознанная фиксация этой
   связности как принятого trade-off (по образцу уже задокументированных исключений).

Кратко по проверенному в этом цикле и не дающему нового замечания:

- `DeleteMediaHandler` удаляет объекты не только image-, но и video-конверсий
  (`MediaVideoConversionRepository`). Video-конверсии этим пайплайном не создаются, но репозиторий и
  сущность `MediaVideoConversion` пред-существуют в HEAD как часть доменной модели; удаление их объектов
  до `delete()` — корректная защита от осиротевших файлов, а не дефект (404 игнорируется сервисом).
- `S3MediaFileService::completeMultipartUpload` корректно трактует идемпотентный повтор (`NoSuchUpload`
  + подтверждённый `headObject` → успех); `isNotFound` покрывает 404 и коды `NoSuchKey/NotFound/NoSuchUpload`.
- `ProcessMediaJob::isTransient` классифицирует только `AwsException` (connection/5xx/throttling) как
  транзиентную, Imagick и доменные нарушения — постоянные; санитизация сообщений предопределёнными
  безопасными строками сохранена (сырой AWS-текст в `MediaProcessingError` не прокидывается).
- Прямая инъекция `MediaConfig` в `RequestMediaUploadHandler`/`GetMediaUrlHandler` — теперь явно
  легитимизированное исключение в `arch.md`; в пятом цикле рассмотрено и закрыто, новых аргументов нет.

## Рекомендации

- **Править обязательно:** —
- **На усмотрение автора:**
  1. Симметричный guard верхней границы размеров конверсии (или общая фабрика-валидатор) на
     Application-границе `CompleteMediaUpload` + тест на верхнюю границу — устранить «boundary слабее
     домена» и не давать невалидной спеке доезжать до терминального `ProcessingFailed`.
  2. Покрыть тестами обе rethrow-ветки `S3MediaFileService::completeMultipartUpload`.
  3. Логировать транзиентную (ретраябельную) ошибку в `ProcessMediaJob` на WARN, а не ERROR (выбор
     уровня по уже посчитанному `isTransient`).
  4. Либо вынести сигнал транзиентности в `MediaFileServiceContract`/инфра-исключение, чтобы Presentation
     не зависел от `Aws\Exception\AwsException`, либо осознанно задокументировать эту связность как
     принятый trade-off.

## Изменения после мета-ревью

### После architecture-check / rules-check / plan-check / quality-check (sonnet)
- **+ Добавлено:**
  - Замечание №1: асимметрия валидации размеров конверсии (нижняя граница — синхронно 422, верхняя —
    только асинхронно в домене → терминальный `ProcessingFailed`); подтверждено кодом
    (`CompleteMediaUploadHandler.php:104-111`, `MediaPixelDimension` `MAX=100_000`, `ProcessMediaHandler.php:107-108`).
  - Замечание №2: rethrow-ветки `completeMultipartUpload` не покрыты тестами (соседние методы покрыты) —
    `S3MediaFileService.php:123-131`, `S3MediaFileServiceErrorTest.php`.
  - Замечание №3: уровень ERROR на транзиентной (ретраябельной) ветке `ProcessMediaJob` против `rules.md:84`
    (WARN для временной инфра-проблемы).
  - Замечание №4: `MediaFileServiceContract` не объявляет сигнал транзиентности → классификация storage-ошибок
    в Presentation на сыром `AwsException` (единственная внешняя зависимость в `Presentation/` модуля).
- **~ Изменено:**
  - Вступление и вывод раздела «Замечания» переписаны: вместо абсолютного «новых проблем нет» — список
    четырёх optional-находок; раздел «Рекомендации» «На усмотрение автора» заполнен; `score` 95 → 93.
- **− Убрано:** ничего (ложных/недоказанных утверждений в ревью мета-ревьюеры не нашли; положительные
  claim'ы про `isNotFound`, `isTransient`, идемпотентный `completeMultipartUpload`, чистку конверсий в
  `DeleteMediaHandler` сверены с кодом и верны).
- **Отклонено:**
  - Дублирование выражения `expiresIn` в `RequestMediaUploadHandler`/`GetMediaUrlHandler` и
    непоследовательность `#[LogOperation]` (нет на `MakeMediaPermanent`/`RecordMediaProcessingFailure`) —
    слишком мелкие стилевые наблюдения без bug/test-риска после шести циклов сходимости; не добавляю.
  - «Мёртвый/тест-онли кластер доменных методов `Media`» (`startProcessing`, `markReady`,
    `recordPermanentProcessingError`, multipart-completion и т.п.) — `git diff HEAD` подтверждает, что в
    этом diff новые только `markReadyMovedTo`/`isReady`; остальные пред-существуют в HEAD как доменный
    каркас и уже покрыты отклонёнными в циклах 1–5 пунктами (слияние `recordPermanent`/`markReady`).
  - `MediaTypeResolver` в `Application/Service` — `arch.md` не запрещает прикладные сервисы; чистая
    stateless-политика пайплайна, однозначного нарушения нет (агент сам пометил `?`).
  - Повторная постановка красного глобального coverage-gate `make qa` — пред-существующий осознанный долг
    не-media классов, разобран в циклах 1–5; per-class 100% media-кода зафиксирован в фикс-отчётах
    (`docs/review-fixes/2026-06-10_15-30_...`).

### После соседнего CLI (codex) — НЕ ЗАВЕРШЕНО (внешняя причина)
Кросс-CLI мета-ревью соседним агентом (Codex) в strict-режиме запущено, но не завершилось по внешним
причинам, не связанным с кодом или ревью:
- запрошенная модель `gpt-5.3-codex` недоступна на текущем ChatGPT-аккаунте Codex
  (`400 invalid_request_error: 'gpt-5.3-codex' model is not supported when using Codex with a ChatGPT account`);
- fallback на доступную по умолчанию модель `gpt-5.5` упёрся в usage limit аккаунта Codex
  (`You've hit your usage limit … try again at Jun 12th, 2026 11:51 AM`).

Согласно указанию не блокироваться на недоступности кросс-CLI: мета-проверка завершена четырьмя
специализированными субагентами (architecture/rules/plan/quality на sonnet), их подтверждённые находки
применены выше, итоговая оценка зафиксирована. Кросс-CLI остаётся незавершённым; повторить можно после
сброса лимита Codex (или с поддерживаемой моделью): `eda-review-check strict <review>`. Статус ревью —
`meta-reviewed` (до `final` не повышен, т.к. кросс-CLI не завершён).

## Применённые фиксы
Отчёт: `docs/review-fixes/2026-06-10_16-40_media-upload-pipeline-uncommitted-draft.md`

Все четыре optional-замечания применены (apply-optional), ни одно не отклонено. Замечание №4
сделано предпочтительным чистым рефактором: типизированный сигнал транзиентности вынесен в
контрактное `Application\Exception\MediaFileServiceFailedException`, `Presentation/Job` больше
не зависит от `Aws\*`. Проверки: `make phpstan` ✓, `make test` ✓; media-код 100% покрыт,
красный глобальный coverage-gate — пред-существующий долг не-media классов (не регресс, 92.66% → 92.93%).
