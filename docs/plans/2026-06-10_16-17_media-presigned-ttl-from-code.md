---
title: Управляемый из кода TTL presigned-ссылок Media (upload и download)
date: 2026-06-10 16:17
mode: normal
plan_size: normal
decision_mode: recommend_and_ask
status: meta-reviewed
reviewer: pending
meta_reviewers: [haiku, sonnet, opus]
test_strategy: after_each_phase
logging_strategy: debug_precise
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  research: —
---

# План реализации

## Задача
Сделать TTL presigned-ссылок модуля `Media` управляемым из кода потребителя, а не одним
глобальным значением `presignedTtlSeconds` из конфига. Потребитель задаёт срок под конкретный
контекст (аватар = 5 мин, видео = 30 мин и т.д.).

Готово, когда:
- TTL **загрузки** (presigned PUT и части multipart) берётся из `MediaUploadSpec`, а не из `MediaConfig`.
- TTL **скачивания** (presigned GET в `GetMediaUrlHandler`) берётся из `GetMediaUrlQuery`, а не из `MediaConfig`.
- Поле `presignedTtlSeconds` полностью удалено из `app/config/media.php`, `MediaConfig`,
  `.env.sample`, `phpunit.xml` (и из всех его использований в тестах).
- Срок завёрнут в Value Object.
- `make test` и `make phpstan` зелёные, покрытие сохранено.

## Контекст
- Сейчас один `presignedTtlSeconds` (дефолт 900с) обслуживает оба сценария:
  - `app/config/media.php:14` (env `MEDIA_PRESIGNED_TTL_SECONDS`), `MediaConfig.php:18`.
  - Загрузка: `RequestMediaUploadHandler.php:55` (`$this->mediaConfig->presignedTtlSeconds`).
  - Скачивание: `GetMediaUrlHandler.php:69` (`$this->mediaConfig->presignedTtlSeconds`).
- `MediaUploadSpec` (`Application/Dto`) — in-process policy-DTO потребителя, уже держит **только**
  богатые типы (`MediaMimeTypeCollection`, `MediaFileSize` VO, `MediaVisibility` enum), без примитивов.
- `GetMediaUrlQuery` — тонкий Query, держит примитив `string $mediaId` + enum
  `MediaImageConversionType|null`; VO строит Handler (`MediaId::fromString`).
- У модуля нет HTTP. Публичный API — Application Command/Query; кроме тестов **нет реальных
  потребителей** `RequestMediaUpload` и `GetMediaUrl`, поэтому ломающее изменение контракта безопасно.
- Целочисленные VO наследуют `App\Shared\Domain\ValueObject\AbstractIntegerValue` (`fromInt`,
  `value()`, `equals`, `supports`, `__toString`, `jsonSerialize`; границы `MIN`/`MAX`, имя `NAME`;
  выход за диапазон → `InvalidDomainValueException` 500). Примеры: `MediaFileSize`, `MediaDuration`.
- Все целочисленные VO тестируются единым data-provider'ом `integerValueObjectProvider` в
  `tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php` (проверка границ).
- `MediaConfig` инжектится в: `RequestMediaUploadHandler`, `GetMediaUrlHandler`,
  `ImagickMediaImageProcessor` (последний использует только `imageProcessingDriver` — не затрагивается).
- `MediaConfig` конструируется руками в 4 тестах: `MediaConfigTest`, `ImagickMediaImageProcessorTest`,
  `RequestMediaUploadHandlerTest`, `GetMediaUrlHandlerTest`.
- TTL presigned-ссылки **нигде не персистится** — это только срок жизни URL. Персистируемый
  staging-TTL (`MediaExpiration`, `stagingTtlSeconds`) — отдельная сущность, не трогаем.

## Принятые решения
- **Один VO `MediaPresignedTtl`** для обоих сценариев — источник подтверждения: ответ пользователя
  (Q «Тип TTL» → Value Object) + `decision_mode: autonomous` на унификацию (значение и доменное
  ограничение идентичны для PUT/части/GET: «сколько живёт presigned-S3-URL», лимит S3 SigV4 = 7 дней).
  Размещение: `app/src/Modules/Media/Domain/ValueObject/MediaPresignedTtl.php`, extends
  `AbstractIntegerValue`, `MIN=1`, `MAX=604_800` (7 дней — жёсткий предел срока presigned-URL в AWS
  Signature V4; MinIO совместим), `NAME='TTL presigned-ссылки'`. Отдельные VO под upload/download не
  плодим (DRY, `rules.md`).
- **Upload-TTL в `MediaUploadSpec`** новым обязательным полем-VO `MediaPresignedTtl $presignedTtl` —
  источник: ответ пользователя. Обязательное (без дефолта/nullable): спека уже состоит только из
  богатых обязательных типов, и потребитель всегда задаёт срок осознанно.
- **Download-TTL выносится в `GetMediaUrlQuery`** — источник: ответ пользователя (Q «TTL скачивания»
  → «Тоже выносить»). Новое **обязательное** поле-примитив `int $presignedTtlSeconds`; VO строит
  Handler (`MediaPresignedTtl::fromInt(...)`) — это сохраняет существующий стиль Query (примитив
  `mediaId` + построение VO в Handler) и правило CQRS «Command/Query принимает примитивы на границе,
  Handler создаёт VO». В `MediaUploadSpec` поле — VO (спека богатая), в `GetMediaUrlQuery` —
  примитив (Query тонкий): каждый DTO следует своему уже сложившемуся стилю.
- **`presignedTtlSeconds` удаляется полностью** из `media.php`, `MediaConfig`, `.env.sample`,
  `phpunit.xml` — источник: ответ пользователя (Q «Конфиг-поле» → «Удалить полностью»). После выноса
  обоих сценариев поле никто не читает; оставлять его — мёртвый конфиг (`rules.md` «Нет мёртвого кода»).
  Никакого upload/download-фолбэка не заводим: оба поля обязательны у потребителя.
- `GetMediaUrlHandler` после выноса больше не зависит от `MediaConfig` — зависимость удаляется из
  конструктора (`decision_mode: autonomous`: иначе остаётся неиспользуемая зависимость).
- **Query-хендлер остаётся без логгера** (правка по мета-ревью): во всём проекте логируют только
  Command-хендлеры; ни один Query-хендлер (`CheckMediaExists`, `CheckMediaIsImage`) не имеет
  `LoggerInterface`. Поэтому в `GetMediaUrlHandler` логгер НЕ добавляем — это сохраняет единый стиль
  Query=без-лога и не плодит scope creep. `debug_precise` применяется к изменённому write-пути
  загрузки (обогащение существующего лога), download-Query логом осознанно не покрывается.
- **VO скачивания `MediaPresignedTtl` строится только в private-ветке** (правка по мета-ревью): для
  public-медиа presigned GET не применяется, поэтому TTL для него не валидируется и не используется.
  `MediaPresignedTtl::fromInt($query->presignedTtlSeconds)` вызывается внутри private-ветки
  `buildUrl`, а не в начале `handle()`. Невалидный TTL (0 или >604800) для private даёт
  `InvalidDomainValueException` (500); для public TTL игнорируется.
- Значения TTL контролируются кодом потребителя, не пользовательским вводом, поэтому выход за
  диапазон — программная ошибка (`InvalidDomainValueException` 500 из VO). 422/`supports()` на границе
  не вводим — нет пользовательского ввода для перевода в 422.
- Ожидаемый объём (`normal`): 1 новый VO + 1 строка в data-provider VO-теста; правки 2 хендлеров,
  2 DTO, 1 typed-config, 1 config-файла, `.env.sample`, `phpunit.xml`, 4 тест-файлов, README. ~3 фазы.

## Целевой алгоритм
**Загрузка (`RequestMediaUpload`):**
1. Потребитель строит `MediaUploadSpec(allowedMimeTypes, maxSize, visibility, presignedTtl)` с
   нужным сроком и передаёт в `RequestMediaUploadCommand`.
2. `RequestMediaUploadHandler` валидирует спеку, создаёт `Media` (staging-expiration из
   `stagingTtlSeconds` — не меняется), затем вычисляет `presignedExpiresAt` из
   `$command->spec->presignedTtl->value()` (вместо `$this->mediaConfig->presignedTtlSeconds`).
3. Срок применяется к presigned PUT (single) и presigned частям (multipart) — как сейчас.
4. Debug-лог запроса загрузки дополняется полем `presignedTtlSeconds` (применённый срок).

**Скачивание (`GetMediaUrl`):**
1. Потребитель передаёт `GetMediaUrlQuery(mediaId, presignedTtlSeconds, conversionType?)`.
2. `GetMediaUrlHandler` находит готовое медиа; для **public** возвращает прямой URL (TTL не
   применяется, `expiresAt = null`).
3. Для **private** строит `MediaPresignedTtl::fromInt($query->presignedTtlSeconds)` внутри
   private-ветки `buildUrl`, вычисляет `expiresAt = now + ttl` и отдаёт presigned GET. Без
   логирования (Query-хендлеры в проекте логгер не имеют).

## Контракты реализации

### Данные и БД
Не затрагивается. TTL presigned-ссылки не персистится; схемы таблиц и миграции не меняются.

### API и внешние контракты
HTTP API не затрагивается (у модуля нет HTTP). Меняется **in-process Application-контракт** модуля
`Media` (публичный API для будущих модулей-потребителей):
- `MediaUploadSpec.__construct(...)` — добавляется обязательный 4-й параметр
  `MediaPresignedTtl $presignedTtl`.
- `GetMediaUrlQuery.__construct(...)` — добавляется обязательный параметр `int $presignedTtlSeconds`
  (после `mediaId`, перед опциональным `conversionType`).
- `GetMediaUrlHandler.__construct(...)` — удаляется параметр `MediaConfig $mediaConfig`.
- `MediaConfig.__construct(...)` — удаляется параметр `int $presignedTtlSeconds`.
Реальных потребителей нет; обновляются только тесты и README.

## Фазы выполнения

### 1. Value Object `MediaPresignedTtl`
Цель: ввести единый доменный тип для срока presigned-ссылки с валидацией диапазона.

Что сделать:
- Создать `app/src/Modules/Media/Domain/ValueObject/MediaPresignedTtl.php`:
  `final readonly class MediaPresignedTtl extends AbstractIntegerValue` с
  `protected const int MIN = 1; protected const int MAX = 604_800; protected const string NAME = 'TTL presigned-ссылки';`.
  Никаких новых методов — поведение целиком из `AbstractIntegerValue`.
- В `tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php` добавить импорт
  `MediaPresignedTtl` и строку в `integerValueObjectProvider`:
  `yield MediaPresignedTtl::class => [MediaPresignedTtl::class, 604_800, 604_801];`
  (граница «валидно/невалидно» проверяется существующим `testIntegerValueObjectsValidateBorders`).

Результат: VO существует, граничные значения протестированы тем же механизмом, что и прочие
целочисленные VO модуля.

Сценарии тестирования:
- Через общий `testIntegerValueObjectsValidateBorders`: `fromInt(604_800)` валиден,
  `fromInt(604_801)` → `InvalidDomainValueException`; `value()`/`jsonSerialize()`/`(string)` отдают
  число; `supports(604_800)=true`, `supports(604_801)=false`.
- Нижняя граница `MIN=1` обеспечивается общим `AbstractIntegerValue` (его поведение уже покрыто
  сиблингами вроде `MediaFileSize`); отдельный кейс на `fromInt(0)` для этого VO не добавляем — это
  совпадает со стилем остальных целочисленных VO модуля, которые data-provider проверяет только по
  верхней границе.

Проверка:
- `make test` (затрагивая `MediaValueObjectTest`) — зелёный.
- `make phpstan` — без новых ошибок.

### 2. Upload-TTL из `MediaUploadSpec`
Цель: брать срок presigned-загрузки из спеки потребителя.

Что сделать:
- `app/src/Modules/Media/Application/Dto/MediaUploadSpec.php`: добавить в конструктор обязательное
  свойство `public MediaPresignedTtl $presignedTtl` (после `visibility`); импорт VO.
- `RequestMediaUploadHandler.php:55`: заменить `$this->mediaConfig->presignedTtlSeconds` на
  `$command->spec->presignedTtl->value()` в `$presignedExpiresAt = $this->expiresIn(...)`.
  (`stagingTtlSeconds`, `multipartThresholdBytes`, `multipartPartSizeBytes` через `MediaConfig`
  остаются — `MediaConfig` из хендлера не удаляем.)
- Debug-лог в `handle()` (`'Запрошена загрузка медиа.'`): добавить в `context` ключ
  `'presignedTtlSeconds' => $command->spec->presignedTtl->value()`.
- `tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php`: в хелпере `spec()`
  добавить `presignedTtl: MediaPresignedTtl::fromInt(900)`; импорт VO. (Конструирование `MediaConfig`
  в `handler()` пока не трогаем — поле `presignedTtlSeconds` удаляется в фазе 3.)
- `app/src/Modules/Media/README.md`: в описании `MediaUploadSpec` (раздел «Спецификация и DTO»)
  добавить поле `presignedTtl: MediaPresignedTtl` и пояснить, что срок presigned-загрузки задаёт
  потребитель.

Результат: presigned PUT и части multipart истекают по сроку из спеки; конфиг для загрузки не читается.

Сценарии тестирования:
- Single-upload: через `willReturnCallback` на `presignPut` захватить аргумент `$expiresAt` и
  проверить, что он ≈ `now + spec.presignedTtl` (с допуском в несколько секунд). В `spec()`
  использовать характерный TTL (например, `MediaPresignedTtl::fromInt(300)`), отличный от прежнего
  конфиг-дефолта 900, чтобы тест доказывал именно источник срока, а не совпадение с конфигом.
- Multipart: аналогично захватить `$expiresAt` из `presignUploadParts` и проверить против `spec.presignedTtl`.
- Debug-лог содержит ключ `presignedTtlSeconds` с применённым значением.
- Валидационные тесты спеки (MIME/размер/расширение) не регрессируют.

Проверка:
- `make test` (затрагивая `RequestMediaUploadHandlerTest`) — зелёный.
- `make phpstan` — чисто.

### 3. Download-TTL из `GetMediaUrlQuery` и полное удаление `presignedTtlSeconds`
Цель: брать срок presigned-скачивания из запроса потребителя и убрать осиротевшее конфиг-поле.

Что сделать:
- `app/src/Modules/Media/Application/Query/Media/GetMediaUrl/GetMediaUrlQuery.php`: добавить
  обязательный `public int $presignedTtlSeconds` между `mediaId` и `conversionType`.
- `GetMediaUrlHandler.php`:
  - Удалить из конструктора `private MediaConfig $mediaConfig` и его импорт; **логгер НЕ добавляем**
    (Query-хендлеры в проекте без лога).
  - Пробросить `$query->presignedTtlSeconds` (int) в `buildUrl(...)`; импорт VO `MediaPresignedTtl`.
  - `buildUrl(...)`: в private-ветке построить `MediaPresignedTtl::fromInt($presignedTtlSeconds)` и
    вычислить `expiresAt = now + ttl` секунд (формат `PT%dS` через `\DateInterval`, как сейчас); для
    public — без изменений (`expiresAt = null`, TTL не строится и не используется).
- `app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php`: обновить докблок
  (строки 20–21): убрать «единый источник — MediaConfig в Handler-е», заменить на «срок задаёт
  потребитель — через `MediaUploadSpec` (загрузка) и `GetMediaUrlQuery` (скачивание)».
- `app/src/Shared/Infrastructure/Configuration/Media/MediaConfig.php`: удалить параметр
  `public int $presignedTtlSeconds`.
- `app/config/media.php`: удалить ключ `'presignedTtlSeconds' => ...` (строка 14) и убрать упоминание
  «TTL presigned-ссылок (PUT/части/GET)» из file-level докблока (строки 5–10) и inline-комментария.
- `.env.sample`: удалить строку `MEDIA_PRESIGNED_TTL_SECONDS=900`.
- `phpunit.xml`: удалить `<env name="MEDIA_PRESIGNED_TTL_SECONDS" value="900" />`.
- Тесты (убрать `presignedTtlSeconds` из конструирования `MediaConfig` и добавить TTL в запросы):
  - `tests/Unit/Shared/Infrastructure/Configuration/MediaConfigTest.php`: убрать из ассерта
    real-config, из входа+ассерта mapper-теста и из конструктора reject-теста.
  - `tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php`: убрать из
    конструктора `MediaConfig`.
  - `tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php`: убрать
    `presignedTtlSeconds` из конструктора `MediaConfig` в `handler()`.
  - `tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php`: убрать аргумент
    `mediaConfig:` из `handler()` и неиспользуемый импорт `MediaConfig` (логгер НЕ добавляем,
    `NullLogger` не нужен); во **все 6** `new GetMediaUrlQuery(...)` (строки 36, 50, 72, 88, 102, 110)
    добавить `presignedTtlSeconds`.
- `app/src/Modules/Media/README.md`: в разделах «Поток загрузки» (шаг 6 GetMediaUrl) и
  «Инфраструктура и конфиг» убрать `presignedTtlSeconds`/`MEDIA_PRESIGNED_TTL_SECONDS` из описания
  `MediaConfig`; отметить, что TTL и загрузки, и скачивания задаёт потребитель (спека/запрос);
  в таблице публичного API обновить сигнатуру `GetMediaUrl(mediaId, presignedTtlSeconds, conversionType?)`.

Результат: оба сценария берут TTL из кода потребителя; конфиг/env/phpunit не содержат
`presignedTtlSeconds`; `MediaConfig` хранит только staging/multipart/driver.

Сценарии тестирования:
- Private-медиа: через `willReturnCallback` на `presignGet` захватить `$expiresAt` и проверить, что он
  ≈ `now + query.presignedTtlSeconds` (характерный TTL, не 900); `result.expiresAt` не null.
- Public-медиа: прямой URL, `presignGet` не вызывается, `expiresAt = null` (TTL не строится).
- Конверсия (thumbnail) для private отдаёт presigned URL с тем же сроком (тот же захват `$expiresAt`).
- `MediaConfigTest` маппит конфиг без `presignedTtlSeconds`; `make phpstan` не находит обращений к
  удалённому свойству (ключевой сигнал отсутствия осиротевших ссылок).

Проверка:
- Полный `make test` — зелёный, покрытие сохранено.
- `make phpstan` — чисто (ключевой сигнал, что удалённое свойство больше нигде не используется).

## Тесты
Стратегия: `after_each_phase`. После каждой фазы прогоняются релевантные тесты + `make phpstan`:
- Фаза 1 — `MediaValueObjectTest` (границы нового VO через общий data-provider).
- Фаза 2 — `RequestMediaUploadHandlerTest` (срок загрузки из спеки, без регрессий валидации).
- Фаза 3 — полный `make test` (GetMediaUrl public/private/конверсия, MediaConfig-маппинг) + `make phpstan`.
Новых тест-классов не вводим: целочисленный VO покрывается существующим параметризованным тестом,
сценарии хендлеров — существующими feature-тестами с обновлёнными фикстурами. Покрытие 100% сохраняется.

## Логирование
Стратегия: `debug_precise`. Точечные debug-логи на уровне бизнес-логики (как требует `rules.md`
«Логирование: DEBUG по умолчанию»):
- `RequestMediaUploadHandler`: в существующий debug-лог запроса загрузки добавляется применённый
  `presignedTtlSeconds` — видно, какой именно срок ушёл в presigned-ссылки.
- `GetMediaUrlHandler`: логгер НЕ добавляется. Во всём проекте логируют только Command-хендлеры; ни
  один Query-хендлер (`CheckMediaExists`, `CheckMediaIsImage`) не имеет `LoggerInterface`. Внедрять
  логгер в Query-хендлер ради одного debug-лога — отступление от стиля и scope creep (правка по
  мета-ревью). `debug_precise` для этого изменения реализован на стороне загрузки, где write-путь уже
  логируется.
Ключи контекста — camelCase (`rules.md`). Уровни WARN/ERROR не вводятся — сценарий нормального flow.

## Документация и эксплуатация
- `.env.sample` и `phpunit.xml`: удалить `MEDIA_PRESIGNED_TTL_SECONDS` (фаза 3).
- `app/src/Modules/Media/README.md`: обновить описание `MediaUploadSpec`, сигнатуру `GetMediaUrl`,
  раздел «Инфраструктура и конфиг» (TTL presigned больше не в `MediaConfig`).
- Эксплуатация/деплой: переменная окружения `MEDIA_PRESIGNED_TTL_SECONDS` становится неиспользуемой —
  её можно убрать из prod/stage конфигов окружения (необязательно для работы, но чисто). Миграций нет.
- Будущим модулям-потребителям (`User`/аватар и т.д.): при вызове `RequestMediaUpload` обязательно
  задавать `MediaUploadSpec.presignedTtl`, при вызове `GetMediaUrl` — `presignedTtlSeconds`.

## Изменения после мета-ревью

### После моделей
- **+ Добавлено:** Ассерты фактического `expiresAt` в фазах 2 и 3 (захват аргумента presign-методов
  через `willReturnCallback`, проверка `≈ now + TTL` с характерным TTL ≠ 900) — без них существующие
  тесты зелёные при любом TTL и не доказывают, что срок берётся из спеки/запроса (sonnet, opus).
- **+ Добавлено:** Обновление докблока `MediaFileServiceContract` (строки 20–21) — после выноса фраза
  «единый источник — MediaConfig в Handler-е» становится ложной (sonnet, opus).
- **+ Добавлено:** Снятие неиспользуемого импорта `MediaConfig` в `GetMediaUrlHandlerTest`; явные
  строки 36/50/72/88/102/110 (6 вызовов `GetMediaUrlQuery`) и строки docblock `media.php` 5–10 (opus).
- **+ Добавлено:** Ссылка на AWS Signature V4 как источник предела `MAX=604_800` (opus).
- **~ Изменено:** Логгер в `GetMediaUrlHandler` **убран** — Query-хендлеры в проекте логгер не имеют;
  внедрять его ради одного debug-лога — отступление от стиля и scope creep (opus). `debug_precise`
  применён только к write-пути загрузки.
- **~ Изменено:** VO скачивания строится **только в private-ветке** `buildUrl`, а не в `handle()` —
  для public-медиа TTL не валидируется и не используется; снимает двусмысленность и риск 500 на
  public-запросе (sonnet, opus).
- **~ Изменено:** Формулировка тест-сценария VO в фазе 1 — честно отражает, что общий data-provider
  проверяет только верхнюю границу (`MAX`/`MAX+1`), а `MIN=1` обеспечивается базовым
  `AbstractIntegerValue` (sonnet).
- **Отклонено:** Общие пункты быстрого ревьюера («проверить, что VO импортирован/скомпилирован
  везде», «debug-лог не перегружен», «явный тест, что конфиг грузится без поля») — уже покрыты
  `make phpstan` и существующими критериями готовности фаз; отдельные шаги избыточны.

## Прогресс выполнения
Журнал: `docs/executions/2026-06-10_16-29_media-presigned-ttl-from-code.md`

- [x] Фаза 1: Value Object `MediaPresignedTtl`
- [x] Фаза 2: Upload-TTL из `MediaUploadSpec`
- [x] Фаза 3: Download-TTL из `GetMediaUrlQuery` и полное удаление `presignedTtlSeconds`

Финальная проверка: `make test` — OK (332 теста, 1129 ассертов); `make phpstan` — No errors.
