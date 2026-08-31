---
title: Поддержка документов в модуле Media
date: 2026-06-22 17:35
mode: strict
plan_size: normal
decision_mode: recommend_and_ask
status: reviewed
reviewer: codex
meta_reviewers: [haiku, sonnet, opus]
test_strategy: after_each_phase
logging_strategy: debug_precise
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  research: docs/researches/2026-06-22_17-22_media-document-support.md
---

# План реализации

## Задача

Включить в модуле `Media` загрузку и выдачу документов (PDF, DOC, DOCX, DJVU, TXT и другие
ходовые форматы). Сейчас `MediaType::Document` объявлен в enum, но это заглушка: документ
сознательно отклоняется во всех точках пайплайна. Нужно снять заглушку так, чтобы документ
можно было загрузить, переложить в постоянное хранилище и отдать по URL, **не ломая** уже
работающий поток image/video/audio.

Готово, когда: документ проходит весь путь `RequestMediaUpload → CompleteMediaUpload →
ProcessMedia → GetMediaUrl` как «медиа без конверсий»; `make test` и `make phpstan` зелёные;
покрытие тестами 100%.

## Контекст

Документ в выбранной модели — это «image без конверсий»: тот же путь
`waitingUpload → uploaded → перекладка оригинала → ready`, но `MediaConversionPlan` пустой и
обработчик ничего не транскодирует. Доменная и архитектурная модель НЕ меняется — нет новых
Entity, контрактов, репозиториев, зависимостей, миграций, конфигов, очередей, outbox-сообщений
или ключей локали. Меняются 5 точек, где `MediaType::Document` сейчас бросает исключение, плюс
два места сравнения MIME (`MediaMimeType::baseValue()` и
`MediaMimeTypeCollection::containsMimeType()` — нормализация для Markdown/CSV с `;charset=…`),
плюс затрагиваемые тесты.

Точки, проверенные по коду:

```text
1 MediaTypeResolver::resolve()              app/.../Application/Service/MediaTypeResolver.php:18-38
2 MediaPath::assertValid() (regex)          app/.../Domain/ValueObject/MediaPath.php:154
3 MediaPath::originalReady()                app/.../Domain/ValueObject/MediaPath.php:83
4 ProcessMediaHandler::handle() match       app/.../Application/Command/ProcessMedia/ProcessMediaHandler.php:90
5 CompleteMediaUploadHandler::assertPlanValid()  app/.../Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:123
```

Важные факты из кода:

- **У модуля Media нет HTTP-роутов и контроллеров.** `RequestMediaUpload`,
  `CompleteMediaUpload`, `GetMediaUrl` — это CQRS Application-сценарии (Command/Query + Handler),
  которые потребляются другими модулями (например `User`, `Posts`) через `Media/Application`.
  Единственный вход в `Media/Presentation` — технический `ProcessMediaJob`. Поэтому «правок
  роутов/контроллеров/OpenAPI» в этой задаче нет, а проверка идёт feature-тестами Handler'ов и
  end-to-end Flow-тестом.
- Резолвер вызывается ДО проверки `allowedMimeTypes`
  (`RequestMediaUploadHandler.php:40-41`): потребитель спецификации может только сузить набор,
  заданный whitelist'ом резолвера, но не добавить новый MIME. Значит любой принимаемый MIME
  документа обязан быть в whitelist кода.
- Расширение файла берётся из имени файла (`pathinfo($fileMeta->fileName, PATHINFO_EXTENSION)`
  в `RequestMediaUploadHandler::extension()`), а НЕ из MIME. Поэтому резолверу нужен только
  **набор MIME** документов; таблица «MIME → расширение» из research — справочная.
- `MediaPath::originalUpload()` (staging-путь `uploads/...`) от типа не зависит — не трогаем.
- `MediaConversionPlan` (три списка `image/video/audio`) и `MediaUploaded` не трогаем: для
  документа все три списка пусты.
- `MediaTypeResolver` — `final readonly` без зависимостей (чистый сервис); `MediaPath` — доменный
  VO (классового докстринга у него нет). Логгер в них не вводится (см. раздел «Логирование»).
- Смежные точки проверены и правок НЕ требуют: typecast `MediaType` уже поддерживает значение
  `document`; `GetMediaUrl` отдаёт оригинал по `visibility` без зависимости от типа;
  `S3MediaFileService` оперирует абстрактным `MediaPath` и префикс не хардкодит; staging-expiry
  чистит документы как и другие типы.

## Принятые решения

Все существенные решения подтверждены пользователем в research
(`docs/researches/2026-06-22_17-22_media-document-support.md`, раздел «Ответы на вопросы»):

- **Поведение** — документ только хранится: после подтверждения оригинал перекладывается в
  постоянный бакет по `visibility` и отдаётся через `GetMediaUrl`. Конверсий, превью первой
  страницы и извлечения метаданных нет. *(подтверждено пользователем)*
- **Распознавание типа** — явный whitelist MIME в `MediaTypeResolver`. Неизвестный
  `application/*` по-прежнему 422 `unsupported_file_type`. *(подтверждено пользователем)*
- **Набор форматов** — все четыре категории (полный whitelist в research, раздел «Whitelist
  MIME → расширение»). Whitelist зафиксирован пользователем; добавление прочих вариантов MIME
  (например `text/x-rtf`, `image/svg+xml`) в эту задачу не входит. *(подтверждено пользователем)*
- **Порядок проверок в резолвере** — whitelist документов сравнивается ДО префиксов
  `image/`/`video/`/`audio/`, потому что DJVU имеет MIME `image/vnd.djvu`; иначе он стал бы
  Image. *(закрытие риска из research)*
- **Нормализация MIME — общий метод VO, применяется при ВСЕХ сравнениях MIME.** В
  `MediaMimeType` добавляется метод `baseValue()` (нижний регистр + отбрасывание параметров после
  `;` + `trim`: `text/markdown;charset=utf-8` → `text/markdown`); сама строка в VO по-прежнему
  хранится целиком (`value()` не меняется). Резолвер сравнивает с разрешённым списком по
  `baseValue()`; `MediaMimeTypeCollection::containsMimeType()` тоже сравнивает по `baseValue()` с
  обеих сторон. Иначе документ с charset-параметром (Markdown/CSV) опознался бы резолвером как
  документ, но не прошёл бы проверку `allowedMimeTypes` (`text/markdown;charset=utf-8` ≠
  `text/markdown`). Три префиксные проверки image/video/audio остаются на исходном `value()` —
  поведение этих типов не меняется. *(закрытие риска из research + кросс-CLI ревью Codex; решение
  пользователя — вариант «нормализовать и в спецификации»)*
- **Представление whitelist** — приватная типизированная константа `list<string>` в
  `MediaTypeResolver` (нормализованные MIME в нижнем регистре), членство — `\in_array(...,
  strict: true)`. По `decision_mode: recommend_and_ask` это деталь реализации, не новое
  существенное решение (нет новых зависимостей/БД/API): закрытый набор вариантов рядом с
  использованием — по правилу «Не плодить технические константы» это уместная константа.

Ожидаемый объём (plan_size: normal): 7 правок кода в 6 файлах (5 точек-заглушек +
`MediaMimeType::baseValue()` + `MediaMimeTypeCollection::containsMimeType()`) + правки/добавления
в 8 тестовых файлах; миграций и новых файлов кода нет.

## Целевой алгоритм

```text
RequestMediaUpload
  resolve(mime): normalized = lower(strip_params(mime))
                 если normalized в whitelist документов ⇒ Document;
                 иначе по исходному value префикс image/ | video/ | audio/;
                 иначе 422 unsupported_file_type
  allowedMimeTypes сужает набор спецификацией потребителя (сравнение по базовому MIME без
                 параметров; может отклонить документ как mime_not_allowed, но не добавить новый MIME)
  media = waitingUpload, path = uploads/<shard>/<key>/source.<ext>  (staging, тип не влияет)
  клиент PUT-ит байты в staging-бакет

CompleteMediaUpload
  headObject подтверждает размер
  assertPlanValid(type=Document): все три списка плана (image/video/audio) ДОЛЖНЫ быть пусты,
                 иначе 422 conversion_plan_type_mismatch
  media = uploaded, MediaUploaded(plan=пустой) в outbox

outbox:relay → ProcessMediaJob → ProcessMedia
  match(type): Document ⇒ конверсий нет ([])
  persistReady: copyObject(uploads/... → documents/<shard>/<key>/source.<ext>),
                media → ready (storage = public|private по visibility)

GetMediaUrl
  public: прямой URL; private: presignGet  (без изменений)
```

## Контракты реализации

### Данные и БД

`Не затрагивается`. Колонка `media.type` уже хранит значение `document` (enum `MediaType`
объявлен с этим case-ом, Entity и typecast его уже поддерживают). Миграций, новых таблиц,
полей, индексов нет. Старые данные совместимы: документов в БД ещё не было, потому что загрузка
отклонялась раньше записи.

### API и внешние контракты

`Не затрагивается` в смысле внешних HTTP-контрактов: у модуля Media нет собственных
HTTP-роутов, контроллеров, Filter и Response-классов (см. «Контекст»). Затрагиваемые
`RequestMediaUpload`/`CompleteMediaUpload`/`GetMediaUrl` — внутренние Application-сценарии
(Command/Query + Handler) для других модулей; их сигнатуры и DTO не меняются. Новых ошибок,
status code, webhook'ов, очередей, outbox-сообщений нет.

Поведенческое расширение (в рамках существующих Application-сценариев):

```text
RequestMediaUpload  — теперь резолвит документный MIME из разрешённого списка в Document вместо
                      422. Конкретный потребитель примет документ, только если его MediaUploadSpec
                      .allowedMimeTypes включает нужный MIME (потребитель сужает набор; правка
                      потребителя — вне рамок модуля Media). Сравнение allowedMimeTypes теперь по
                      базовому MIME без параметров (containsMimeType): для image/video/audio без
                      параметров поведение не меняется, для Markdown/CSV с ;charset=… документ
                      проходит спецификацию, заданную базовым MIME.
CompleteMediaUpload — для документа ожидает пустой план конверсий; непустой план ⇒ 422
                      app.media.conversion_plan_type_mismatch (ключ уже есть в locale).
ProcessMedia        — для документа перекладывает оригинал в documents/... без конверсий.
unsupported_file_type остаётся для реально неизвестных MIME (например font/woff2).
```

Новых ключей локали не требуется (`conversion_plan_type_mismatch`, `unsupported_file_type` уже
есть в `app/locale/ru/media.php` и `app/locale/en/media.php`).

## Фазы выполнения

### 1. Нормализация MIME и классификация документа

Цель: резолвер распознаёт документ по разрешённому списку MIME раньше префиксных правил и
устойчив к регистру и параметрам MIME; проверка `allowedMimeTypes` тоже устойчива к параметрам,
чтобы Markdown/CSV с `;charset=…` проходили спецификацию.

Что сделать:
- Добавить в `MediaTypeResolver` приватную константу со списком разрешённых MIME-типов
  документов — точно `/** @var list<string> */ private const array DOCUMENT_MIME_TYPES = [...]`
  (нативный тип константы — `array`, элемент `list<string>` задаётся только в PHPDoc); MIME из
  research, все в нижнем регистре:
  `application/pdf`, `application/msword`,
  `application/vnd.openxmlformats-officedocument.wordprocessingml.document`, `text/plain`,
  `application/rtf`, `text/rtf`, `application/vnd.oasis.opendocument.text`,
  `application/vnd.oasis.opendocument.spreadsheet`,
  `application/vnd.oasis.opendocument.presentation`, `application/vnd.ms-excel`,
  `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`,
  `application/vnd.ms-powerpoint`,
  `application/vnd.openxmlformats-officedocument.presentationml.presentation`,
  `application/epub+zip`, `application/x-fictionbook+xml`, `image/vnd.djvu`, `text/csv`,
  `text/markdown`. Рядом с константой — короткий комментарий на русском без англицизмов: что
  форматы Office с макросами (MIME с суффиксом `…macroEnabled.12`) намеренно не включены из-за
  активного содержимого.
- Добавить в `MediaMimeType` метод `baseValue(): string`: нижний регистр + отбросить часть после
  `;` + `trim` (нормализованный базовый MIME). Хранимая строка (`value()`) не меняется. Это общий
  источник нормализации и для резолвера, и для сравнения `allowedMimeTypes`.
- В `MediaTypeResolver::resolve()`: **первым** проверить членство `$mimeType->baseValue()` в
  `DOCUMENT_MIME_TYPES` через `\in_array(needle: $mimeType->baseValue(), haystack:
  self::DOCUMENT_MIME_TYPES, strict: true)` → `MediaType::Document`; затем существующие три
  префиксные проверки `str_starts_with` **на исходном `$mimeType->value()`** (поведение
  image/video/audio не меняется); затем существующий `throw 422 unsupported_file_type`.
- В `MediaMimeTypeCollection::containsMimeType()` сравнивать по базовому MIME с обеих сторон:
  `$allowed->baseValue() === $mimeType->baseValue()` вместо точного `$allowed->equals($mimeType)`.
  Иначе документ с charset-параметром (Markdown/CSV) опознается резолвером, но не пройдёт
  `allowedMimeTypes`. Для image/video/audio без параметров поведение не меняется (база совпадает
  со строкой).
- Обновить классовый докстринг резолвера (строки 11-15): сейчас он утверждает, что документы
  отклоняются и что `MediaPath` допускает только `uploads|images|videos|audios` — после правки
  оба утверждения становятся ложными. Привести формулировку в соответствие (на русском, без
  англицизмов вроде «whitelist»: «поддержан список разрешённых MIME-типов документов»;
  `MediaPath` допускает и префикс `documents`).

Результат: `resolve('application/pdf') === Document`, `resolve('image/vnd.djvu') === Document`
(не Image), `resolve('text/markdown;charset=utf-8') === Document`, `resolve('image/jpeg') ===
Image`, `resolve('image/png') === Image` (прочий image/* не перехвачен списком документов),
`resolve('font/woff2')` → `ValidationException`. Дополнительно:
`MediaMimeType::fromString('TEXT/CSV;charset=utf-8')->baseValue() === 'text/csv'` при неизменном
`value()`; `MediaMimeTypeCollection([text/markdown])->containsMimeType('text/markdown;charset=utf-8')
=== true`.

Критерий готовности докстринга: в докблоке резолвера нет фразы про «документы отклоняются» и про
«только uploads|images|videos|audios»; есть упоминание списка разрешённых MIME-типов документов и
префикса `documents`; текст докблока на русском без англицизмов.

Сценарии тестирования (после фазы) — файл `tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php`:
- Позитивные кейсы документов: `application/pdf`, `application/msword`, `text/csv`,
  `text/markdown` → `Document`.
- DJVU `image/vnd.djvu` → `Document`, а НЕ `Image` (порядок проверок).
- Нормализация: `text/markdown;charset=utf-8` → `Document`; `APPLICATION/PDF` и `TEXT/CSV`
  (верхний регистр) → `Document`.
- `image/png` → `Image` (whitelist не перехватывает прочие image/* — кроме djvu).
- `unsupportedMimeTypeProvider` (строки 37-40): убрать ставшие поддержанными `application/pdf` и
  `text/plain`; оставить/добавить реально неподдержанные `font/woff2`, `application/zip`,
  `application/octet-stream` и macro-enabled
  `application/vnd.ms-word.document.macroEnabled.12` (подтверждает исключение макросов).

Сценарии для нормализации (после фазы):
- `MediaValueObjectTest`: `MediaMimeType::baseValue()` — `text/markdown;charset=utf-8` →
  `text/markdown`, `APPLICATION/PDF` → `application/pdf`, `image/jpeg` → `image/jpeg`; `value()`
  возвращает исходную строку без изменений.
- Новый юнит-тест `tests/Unit/Modules/Media/Domain/Collection/MediaMimeTypeCollectionTest.php`:
  `containsMimeType()` матчит spec `text/markdown` против значения `text/markdown;charset=utf-8`
  (`true`) и не матчит spec `image/jpeg` против `image/png` (`false`).

Проверка: `make test` (новые кейсы зелёные), `make phpstan`.

### 2. Префикс `documents` в `MediaPath`

Цель: путь готового оригинала документа валиден и строится с префиксом `documents`.

Что сделать:
- В `MediaPath::assertValid()` (строка 154) расширить регулярку префиксов:
  `(uploads|images|videos|audios)` → `(uploads|images|videos|audios|documents)`. Проверка
  shard↔uuid (строки 162-164) общая для всех префиксов и не меняется.
- В `MediaPath::originalReady()` (строки 79-86) в `match($type)` заменить ветку
  `MediaType::Document => throw ...` на `MediaType::Document => 'documents'`. `match` остаётся
  исчерпывающим без `default`.
- `originalUpload()` не трогаем.

Результат: `MediaPath::originalReady(type: Document, extension: 'pdf')` →
`documents/<shard>/<key>/source.pdf`; `MediaPath::fromString('documents/aa/<uuid>/source.pdf')`
валиден; пути image/video/audio и uploads — без изменений.

Сценарии тестирования (после фазы) — файл
`tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php`:
- В `testMediaPathFactoriesBuildValidPaths` (≈строка 78) добавить
  `originalReady(Document, 'pdf')` → ожидаемая строка с префиксом `documents/`.
- Перевернуть существующий негатив на строках 132-141 (где `originalReady(Document)` ожидает
  `InvalidDomainValueException`) в позитивный кейс построения пути `documents/...`.
- Добавить валидный `MediaPath::fromString('documents/<shard>/<uuid>/source.pdf')` с корректным
  shard и один негатив: `documents/<bad-shard>/<uuid>/source.pdf` (shard не равен первым двум hex
  uuid) → `InvalidDomainValueException` (покрывает новую ветку regex на позитив и негатив).

Проверка: `make test`, `make phpstan`.

### 3. Пустой набор конверсий для документа в `ProcessMediaHandler`

Цель: документ перекладывается из staging в постоянное хранилище без конверсий и переходит в
ready.

Что сделать:
- В `ProcessMediaHandler::handle()` `match($media->type)` (строка 90) заменить
  `MediaType::Document => throw new InvalidDomainValueException(...)` на
  `MediaType::Document => []` (пустой `list` конверсий).
- Дальнейший код не меняется: существующий `persistReady()` вызовет
  `MediaPath::originalReady(type: Document, ...)` (теперь возвращает `documents/...`),
  сделает `copyObject` оригинала и `markReadyMovedTo`. Для пустого набора `getObjectContents`/
  `putObject` не вызываются (как в существующем кейсе пустого плана у image).

Результат: документ-медиа после `handle()` → `isReady() === true`, `storage` = public|private
по visibility, конверсий 0, ready-путь начинается с `documents/`.

Сценарии тестирования (после фазы) — файл
`tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php`:
- Перевернуть существующий `testRejectsDocumentType` (строки 253-270) и **переименовать** в
  `testProcessesDocumentByMovingOriginalWithoutConversions` (имя теста должно описывать
  позитивный сценарий). Мок `MediaFileServiceContract`: `copyObject` ровно один раз
  (`expects(self::once())`), `getObjectContents`/`putObject` — `never`. После `handle()`:
  `media->isReady()`, конверсий 0, и ready-путь медиа начинается с `documents/` (проверка через
  состояние media — отличает документ от перекладки image). Этот тест НЕ дублирует существующий
  `testProcessesMediaWithoutConversionsMovesOriginalOnly` (там image+пустой план): новый кейс
  проверяет именно тип Document и префикс `documents/`.

Проверка: `make test`, `make phpstan`.

### 4. Валидация пустого плана документа в `CompleteMediaUploadHandler` + загрузка документа end-to-end

Цель: подтверждение загрузки документа проходит только с пустым планом конверсий; непустой план
отклоняется как кросс-тип 422; документ проходит весь путь до ready.

Что сделать:
- В `CompleteMediaUploadHandler::assertPlanValid()` `match($type)` (строка 123) заменить
  `MediaType::Document => throw new ValidationException('unsupported_file_type'...)` на
  `MediaType::Document => $this->assertDocumentPlan($plan)`.
- Добавить приватный метод `assertDocumentPlan(MediaConversionPlan $plan): void`: если
  `$plan->image !== [] || $plan->video !== [] || $plan->audio !== []` → бросить
  `ValidationException('app.media.conversion_plan_type_mismatch')`. (Стиль как у существующих
  `assertImagePlan`/`assertVideoPlan`/`assertAudioPlan`; пустой план для документа корректен,
  отдельный guard на «ноль элементов» не нужен.)
- `match` остаётся исчерпывающим без `default`.

Результат: документ с пустым планом → `MediaStatus::Uploaded`, `MediaUploaded` уходит в outbox;
документ с любым непустым списком конверсий → 422 `conversion_plan_type_mismatch`.

Сценарии тестирования (после фазы):

`tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php`:
- Перевернуть существующий `testRejectsDocumentMedia` (строки 345-359) и **переименовать** в
  `testCompletesDocumentUploadWithEmptyPlan`: документ + `emptyPlan()` + `fileServiceWithHead(...)`
  (мок `headObject` обязателен, иначе `assertObjectUploaded` упадёт) → `status === Uploaded`,
  `MediaUploaded` добавлено в outbox.
- Добавить новый негатив `testRejectsNonEmptyPlanForDocument` с data-provider по всем трём
  спискам (непустой `imagePlan`, непустой `videoPlan`, непустой `audioPlan`) — каждый бросает
  `ValidationException` с `expectExceptionMessage('app.media.conversion_plan_type_mismatch')`
  (сообщение исключения равно ключу перевода — см. `DomainTranslatableException`), чтобы тест не
  прошёл по чужой валидации. Покрывает то, что `assertDocumentPlan` отвергает любой из трёх
  списков, а не только image.

`tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php`:
- Позитив `testRequestsDocumentUpload`: `spec` с `allowedMimeTypes`, включающим
  `application/pdf`, `fileMeta` (`document.pdf`, `application/pdf`) → создаётся media с типом
  `Document`, статус `WaitingUpload`, staging-путь начинается с `uploads/` (тест читает
  `media->type` и `media->path`).
- Позитив `testRequestsDocumentUploadWithCharsetMime`: `fileMeta` (`note.md`,
  `text/markdown;charset=utf-8`), `spec.allowedMimeTypes` со `text/markdown` (без параметра) →
  загрузка принимается, media тип `Document`. Проверяет, что нормализация в `containsMimeType`
  пропускает Markdown/CSV с charset-параметром.
- Негатив `testRejectsDocumentMimeOutsideSpec`: документный MIME, который резолвер поддерживает,
  но которого нет в `spec.allowedMimeTypes` → `ValidationException` с
  `expectExceptionMessage('app.media.mime_not_allowed')` (а не просто класс — иначе тест может
  пройти по другой 422). Показывает ключевой тезис плана: потребитель сужает набор, а не расширяет.

`tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php` (end-to-end через MinIO):
- Добавить документный кейс: `RequestMediaUpload(application/pdf)` → PUT байтов в staging →
  `CompleteMediaUpload(emptyPlan)` → `outbox:relay`/`ProcessMedia` → media `ready`. Проверить, что
  реальный объект лежит по ready-пути `documents/<shard>/<key>/source.pdf` (через `headObject` по
  этому пути) и конверсий нет. Это единственный тест, реально исполняющий `persistReady`→
  `copyObject`→`MediaPath::assertValid('documents/...')` без моков файлового сервиса.

`tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php`:
- Добавить кейс: готовый `Document` отдаётся через `GetMediaUrlHandler` — public прямым URL,
  private через presignGet. `GetMediaUrl` не ветвится по типу, кода не меняем; тест фиксирует, что
  выдача URL документа работает (цель плана упоминает `GetMediaUrl`).

`tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php`:
- Добавить кейс: `CheckMediaIsImage` возвращает `false` для `MediaType::Document`. Важно из-за
  DJVU: его MIME `image/vnd.djvu` теперь классифицируется как Document, и тест пиннит, что документ
  (в т.ч. DJVU) нельзя подсунуть в image-only сценарий (аватар). Кода не меняем —
  `CheckMediaIsImageHandler` уже сравнивает `type === MediaType::Image`.

Проверка: `make test`, `make phpstan`.

## Тесты

Стратегия: **after_each_phase**. После каждой фазы добавляются/обновляются тесты именно для
правки этой фазы и прогоняется `make test` + `make phpstan`, прежде чем переходить к следующей
фазе. Затрагиваемые тестовые файлы:

- `tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php` (фаза 1)
- `tests/Unit/Modules/Media/Domain/Collection/MediaMimeTypeCollectionTest.php` (фаза 1, новый файл)
- `tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php` (фаза 1: `baseValue`; фаза 2: путь)
- `tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php` (фаза 3)
- `tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php` (фаза 4)
- `tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php` (фаза 4)
- `tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php` (фаза 4, end-to-end)
- `tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php` (фаза 4)
- `tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php` (фаза 4)

Требование проекта — 100% покрытие. У модуля Media нет HTTP-роутов, поэтому покрытие
обеспечивают feature-тесты Handler'ов плюс end-to-end Flow-тест, который реально гоняет
документ через MinIO. Все перевёрнутые негативы перестают проверять отклонение и начинают
проверять успешный документный путь; имена таких тестов переименовываются под позитивный
сценарий.

Тесты запускаются только через Docker: `make test` (часть тестов ходит в `minio:9000`, доступный
лишь внутри Docker-сети). PHPStan — `make phpstan`.

## Логирование

Стратегия: **debug_precise**. Документная ветка должна быть прослеживаема на каждом переходе на
уровне DEBUG, без новых лог-строк там, где этого не требует дизайн.

- `MediaTypeResolver` и `MediaPath` остаются без логгера: это чистый сервис и доменный VO,
  ввод логгера в них нарушил бы их природу (правило «Логирование: DEBUG по умолчанию» относится
  к бизнес-логике в Handler-ах, а не к чистым VO/резолверам).
- Прослеживаемость документа обеспечивают уже существующие DEBUG-логи на границе Handler-ов, и
  они уже включают нужные поля:
  - `RequestMediaUploadHandler` — «Запрошена загрузка медиа.» с `mimeType` (видно, что принят
    документный MIME).
  - `ProcessMediaHandler` — «Начата обработка медиа.» с `type` (= `document`) и «Медиа готово.» со
    счётчиками конверсий (для документа все три = 0).
  - `CompleteMediaUploadHandler` — «Загрузка медиа подтверждена.» со счётчиками плана (для
    документа все 0).
- Новые DEBUG-строки не добавляются: каждый переход документного пути уже логируется
  существующими сообщениями, и они корректно отражают `type=document`/нулевые конверсии.

## Документация и эксплуатация

- Миграций, env-переменных, новых конфигов и зависимостей нет — изменений в `.env`/runbook не
  требуется.
- OpenAPI-генерация не нужна: у модуля Media нет контроллеров/Filter/Response, whitelist MIME
  живёт только в коде резолвера и в сгенерированное описание не попадает.
- Эксплуатационное замечание для потребителей модуля: чтобы конкретная фича (например вложения
  к постам) реально принимала документы, её `MediaUploadSpec.allowedMimeTypes` должен включать
  нужные документные MIME — это правка на стороне потребителя, вне модуля Media. Если у такого
  потребителя есть HTTP-контракт/OpenAPI, прогон `php app.php openapi:generate` выполняется в его
  задаче, а не здесь.
- Осиротевшие staging-объекты документа чистятся существующей staging-expiry задачей, как и
  другие типы (без изменений).

## Изменения после мета-ревью

### После моделей

- **+ Добавлено:**
  - End-to-end документный кейс в `MediaProcessingFlowTest` (фаза 4): реальная перекладка в
    `documents/...` через MinIO, а не только моки `copyObject`.
  - Негатив `mime_not_allowed` в `RequestMediaUploadHandlerTest`: документный MIME вне
    `spec.allowedMimeTypes` отклоняется — доказывает тезис «потребитель сужает набор».
  - Позитивный кейс `image/png → Image` и регистровые кейсы (`APPLICATION/PDF`, `TEXT/CSV`) в
    `MediaTypeResolverTest`; явное исключение macro-enabled через
    `application/vnd.ms-word.document.macroEnabled.12`.
  - Конкретный негатив по shard для пути `documents/...` в `MediaValueObjectTest`.
  - Критерий готовности для правки докстринга резолвера.
- **~ Изменено:**
  - Раздел «API и внешние контракты» и «Тесты»: исправлена фактическая ошибка — у модуля Media
    нет HTTP-роутов/контроллеров; формулировки «маршруты», «интеграционные тесты роутов»
    заменены на «Application-сценарии (CQRS Handler'ы)» и «feature-тесты Handler'ов».
  - Перевёрнутые негативы переименованы под позитивный сценарий
    (`testProcessesDocumentByMovingOriginalWithoutConversions`,
    `testCompletesDocumentUploadWithEmptyPlan`).
  - Явно зафиксировано: нормализация MIME применяется только к сравнению с whitelist, префиксные
    проверки остаются на исходном `$value` (поведение image/video/audio не меняется).
  - Уточнены обязательные детали тестов: мок `headObject` в позитивном Complete-кейсе; проверка
    префикса `documents/` через состояние media в Process-кейсе.
- **− Убрано:**
  - Шаг `php app.php openapi:generate` как обязательный для этой задачи — неприменим к Media
    (нет HTTP-поверхности); оставлено лишь как заметка для задачи потребителя.
- **Отклонено:**
  - Добавление `text/x-rtf` (sonnet) и `image/svg+xml` (opus) в whitelist — набор форматов
    зафиксирован пользователем в research; прочие MIME-варианты считаются неподдержанными до
    явного добавления отдельной задачей.
  - Нормализация префиксных проверок image/video/audio (opus, «для консистентности») — это
    изменило бы существующее поведение этих типов вне рамок задачи; research требует нормализацию
    только перед сравнением с разрешённым списком.

## Реакция на ревью

Strict-этап: кросс-CLI ревью плана соседним CLI — Codex (`codex-cli 0.141.0`). Полный лог —
`docs/plans/2026-06-22_17-35_media-document-support_review.md`. Codex дал 7 содержательных
замечаний, все приняты.

- **Принято [пропущенный шаг] — нормализация на стороне спецификации.** Резолвер нормализует MIME,
  но `RequestMediaUploadHandler::assertUploadAllowed()` через `MediaMimeTypeCollection::contains
  MimeType()` сравнивает MIME точно, поэтому `text/markdown;charset=utf-8` опознавался бы как
  документ, но не проходил бы `allowedMimeTypes` со `text/markdown`. По решению пользователя
  (вариант «нормализовать и в спецификации») добавлены `MediaMimeType::baseValue()` и сравнение по
  `baseValue()` в `containsMimeType()`; добавлены юнит-тест коллекции и интеграционный позитив с
  charset-MIME (фазы 1 и 4).
- **Принято [недостающий тест] — GetMediaUrl для документа.** Добавлен тест выдачи URL готового
  документа (public/private) в `GetMediaUrlHandlerTest` (фаза 4).
- **Принято [недостающий тест] — точная причина отказа в негативах.** В
  `testRejectsDocumentMimeOutsideSpec` и негативе непустого плана документа проверяется конкретный
  ключ (`mime_not_allowed` / `conversion_plan_type_mismatch`) через `expectExceptionMessage`
  (сообщение исключения равно ключу перевода).
- **Принято [недостающий тест] — все три списка в плане документа.** Негатив непустого плана
  документа покрывает image/video/audio через data-provider (фаза 4).
- **Принято [нарушение правил] — англицизмы в комментариях.** Инструкции по докблоку и комментарию
  переписаны на русский без «whitelist»/«macro-enabled» (требование docs/rules.md «без
  англицизмов»); технические идентификаторы (`DOCUMENT_MIME_TYPES`) остаются на английском.
- **Принято [скрытый риск] — CheckMediaIsImage для документа.** Добавлен тест, что
  `CheckMediaIsImage` возвращает `false` для `MediaType::Document` (важно из-за DJVU, который
  теперь Document, а не Image) — защита image-only сценариев вроде аватара (фаза 4).
- **Принято [реализуемость] — синтаксис типизированной константы.** Уточнено: нативный тип
  константы `array`, `list<string>` задаётся PHPDoc — точная запись
  `/** @var list<string> */ private const array DOCUMENT_MIME_TYPES = [...]` (фаза 1).

## Прогресс выполнения
Журнал: `docs/executions/2026-06-22_18-09_media-document-support.md`

- [x] Фаза 1: Нормализация MIME и классификация документа (`MediaMimeType::baseValue()`, `MediaTypeResolver`, `MediaMimeTypeCollection::containsMimeType()`)
- [x] Фаза 2: Префикс `documents` в `MediaPath` (`assertValid()` regex + `originalReady()` match)
- [x] Фаза 3: Пустой набор конверсий для документа в `ProcessMediaHandler`
- [x] Фаза 4: Валидация пустого плана документа в `CompleteMediaUploadHandler` + загрузка end-to-end
- [x] Финальная проверка: `make test` + `make phpstan`
