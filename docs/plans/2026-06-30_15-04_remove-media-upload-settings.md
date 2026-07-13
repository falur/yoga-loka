---
title: Убрать MediaUploadSettings — конфиг-зависимое поведение в Infrastructure за Application-контрактом
date: 2026-06-30 15:04
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
  research: —
---

# План реализации

## Задача

Убрать промежуточный DTO `App\Modules\Media\Application\Dto\MediaUploadSettings`, который бутлоадер
собирает из `MediaConfig` и инжектит в Application-Handler. Это «конфиг от конфига»: набор полей
конфига, переупакованный в Application-объект. Вместо него конфиг-зависимое поведение пайплайна
загрузки выносится в Infrastructure-сервис за `Application/Contract` (по образцу уже существующего
`MediaUrlServiceContract`/`MediaUrlService`). Правила (`docs/rules.md`) и архитектура (`docs/arch.md`)
обновляются: убирается разрешение на «settings-объекты», фиксируется новый паттерн.

Готово, когда: `MediaUploadSettings` удалён, `RequestMediaUploadHandler` получает конфиг-решения
через `MediaUploadPlannerContract`, `make test` и `make phpstan` зелёные, покрытие 100%, а
`docs/rules.md`/`docs/arch.md` описывают новый паттерн без упоминания разрешённых settings-объектов.

## Контекст

- `MediaUploadSettings` (`app/src/Modules/Media/Application/Dto/MediaUploadSettings.php`) — `final
  readonly` DTO с тремя полями `stagingTtlSeconds`, `multipartThresholdBytes`,
  `multipartPartSizeBytes`. Собирается фабрикой `MediaBootloader::mediaUploadSettings(MediaConfig)`
  (`defineSingletons()`), инжектится в `RequestMediaUploadHandler` (поле `$uploadSettings`).
- В `RequestMediaUploadHandler` эти три значения используются в четырёх местах:
  - `stagingTtlSeconds` → `MediaExpiration::temporaryUntil($this->expiresIn(...))` в `Media::create`;
  - `multipartThresholdBytes` → приватный `isMultipart(MediaFileSize): bool`;
  - `multipartPartSizeBytes` → `MediaMultipartPartSize::fromInt(...)` в `prepareMultipartUpload`;
  - тот же `multipartPartSizeBytes` → приватный `partsCount(MediaFileSize, MediaMultipartPartSize): int`
    (`ceil(size / partSize)`).
- Прецедент чистого решения уже в коде: `App\Modules\Media\Infrastructure\FileService\MediaUrlService`
  лежит в Infrastructure, получает `MediaConfig` прямо в конструктор (DI авто-вайрит, потому что класс
  биндится через `const BINDINGS` контрактом `MediaUrlServiceContract`), читает
  `$this->mediaConfig->presignedTtlSeconds` сам и считает срок per-call внутри метода (stateless).
  Это и есть санкционированный способ «Infrastructure читает конфиг сама».
- `MediaUploadSettings` — единственный в `app/src` промежуточный «конфиг от конфига». Остальные
  передачи конфига иные и в охват не входят: `UserPublicProfileAssembler` получает единичный скаляр
  `$defaultAvatarUrl` через фабрику `UserBootloader`; `LocaleResolver` — доменный сервис, собранный
  из `LocaleConfig`. Классы `Notifications/*Setting*` — доменные данные пользователя, не конфиг.
- Правила и архитектура сейчас ЯВНО разрешают settings-объекты — это и предстоит развернуть:
  - `docs/rules.md` строка 62 («`env()` только в конфигах»): «…получают готовые значения
    (VO/скаляры/доменные сервисы/settings) через DI».
  - `docs/arch.md` строки 285–295 (строгое правило про конфиг): «…отдаёт в Application уже готовые
    значения (VO, скаляры, доменные сервисы, маленькие settings-объекты) через DI-биндинги».
- Память агента `application-config-independence.md` фиксировала settings-DTO как согласованный
  паттерн от 29.06.2026. Текущая задача (30.06.2026) разворачивает это решение; память будет
  обновлена при исполнении (см. «Документация и эксплуатация»).
- Тесты: `tests/Feature/.../RequestMediaUploadHandlerTest.php` собирает Handler вручную с
  `new MediaUploadSettings(...)`; `tests/Kernel/.../MediaBootloaderTest.php` проверяет, что фабрика
  строит `MediaUploadSettings` из конфига. Оба обновляются. Unit-тесты чистых сервисов лежат в
  `tests/Unit` на голом `PHPUnit\Framework\TestCase` (образец `MediaTypeResolverTest`).

## Принятые решения

- **Заменяем `MediaUploadSettings` на `MediaUploadPlannerContract` (`Media/Application/Contract`) +
  реализацию `MediaUploadPlanner` (`Media/Infrastructure/FileService`).** Источник подтверждения:
  ответ пользователя (вариант «Плоский планнер»).
- **Контракт плоский, четыре метода-решения, без новых DTO, без nullable, без `instanceof`:**
  - `stagingExpiration(): MediaExpiration` — `now + stagingTtlSeconds` из конфига;
  - `isMultipart(MediaFileSize $size): bool` — `size >= multipartThresholdBytes`;
  - `partSize(): MediaMultipartPartSize` — `MediaMultipartPartSize::fromInt(multipartPartSizeBytes)`;
  - `partsCount(MediaFileSize $size): MediaMultipartPartsCount` — `ceil(size / multipartPartSizeBytes)`.
- **Реализация читает `MediaConfig` напрямую через конструктор** (как `MediaUrlService`), `final
  readonly`, биндится простым `const BINDINGS` в `MediaBootloader`. Фабрика `defineSingletons()` для
  настроек удаляется целиком (она держала только `MediaUploadSettings`). Источник подтверждения:
  ответ пользователя + прецедент `MediaUrlService`.
- **Расположение реализации — `Infrastructure/FileService`**, рядом с `MediaUrlService` (config-зависимый
  сервис того же модуля, считающий значения per-call). Новых подпапок не вводим. Источник: `decision_mode:
  autonomous` по непринципиальной детали — следуем фактическому размещению `MediaUrlService`.
- **Срок staging считается per-call внутри `stagingExpiration()`** (`new \DateTimeImmutable()` в методе,
  не в конструкторе), иначе все загрузки получили бы один замороженный момент — та же причина, по которой
  `MediaUrlService` считает `expiresAt` внутри вызова.
- **Охват — только `MediaUploadSettings` + правила/архитектура.** `UserPublicProfileAssembler`
  (единичный скаляр) и `LocaleResolver` (доменный сервис) не трогаем. Источник подтверждения: ответ
  пользователя (вариант «Только MediaUploadSettings»).
- **`docs/rules.md` и `docs/arch.md` обновляются** так, чтобы убрать разрешение settings-объектов и
  зафиксировать: конфиг-зависимое поведение → Infrastructure-сервис за `Application/Contract`;
  промежуточный settings-DTO, проецирующий поля `*Config` в Application, запрещён. Это прямой запрос
  пользователя «обновить правила и архитектуру», поэтому изменение правил — часть задачи, а не конфликт.
- **Ожидаемый объём (normal):** 3 новых файла, 4 изменённых файла кода/тестов, 3 файла документации
  (`docs/rules.md`, `docs/arch.md`, `app/src/Modules/Media/README.md`), 1 удалённый файл; ~170–220
  строк diff.

## Целевой алгоритм

```text
HTTP/Job -> RequestMediaUploadCommand
  -> RequestMediaUploadHandler::handle()
       1. mediaType = MediaTypeResolver.resolve(mimeType)
       2. assertUploadAllowed(spec, fileMeta)              // mime + maxSize, без изменений
       3. storageKey = MediaStorageKey::generate()
       4. expiration = uploadPlanner.stagingExpiration()   // <- было: settings.stagingTtlSeconds
       5. media = Media::create(..., expiration)
       6. presignedExpiresAt = expiresIn(spec.presignedTtl) // как раньше (TTL из спеки потребителя)
       7. multipart? = uploadPlanner.isMultipart(fileMeta.size)  // <- было: приватный isMultipart()
            true  -> prepareMultipartUpload(media, presignedExpiresAt)
                       partSize   = uploadPlanner.partSize()              // <- было: fromInt(settings…)
                       partsCount = uploadPlanner.partsCount(media.size)  // <- было: приватный partsCount()
                       createMultipartUpload + presignUploadParts + persist(media, MediaMultipartUpload)
            false -> prepareSingleUpload(media, presignedExpiresAt)
                       presignPut + persist(media)
       8. entityManager.run()
       9. logger.debug('Запрошена загрузка медиа.', {...})  // существующий лог, без изменений
       -> RequestMediaUploadResult (single | multipart)
```

`MediaUploadPlanner` (Infrastructure) на каждый вызов:

```text
stagingExpiration() -> MediaExpiration::temporaryUntil(now + PT{stagingTtlSeconds}S)
isMultipart(size)   -> size.value() >= mediaConfig.multipartThresholdBytes
partSize()          -> MediaMultipartPartSize::fromInt(mediaConfig.multipartPartSizeBytes)
partsCount(size)    -> MediaMultipartPartsCount::fromInt( ceil(size.value() / this.partSize().value()) )
```

Поведение полностью сохраняется: те же значения, та же логика multipart, тот же staging-TTL. Меняется
только местоположение чтения конфига и вычислений — из Application-Handler в Infrastructure-сервис за
контрактом.

## Контракты реализации

### Данные и БД

Не затрагивается. Схема `media_files`, `media_multipart_uploads` и связанные таблицы не меняются.
`Media::create()`, `MediaMultipartUpload::create()` и доменные VO не меняются.

### API и внешние контракты

Внешний HTTP/Queue-контракт не затрагивается. `RequestMediaUploadCommand`, `RequestMediaUploadResult`,
`MediaUploadSpec` и presigned-флоу S3 не меняются.

Внутренний (in-process) контракт модуля Media — новый:

```text
interface App\Modules\Media\Application\Contract\MediaUploadPlannerContract
  stagingExpiration(): MediaExpiration
  isMultipart(MediaFileSize $size): bool
  partSize(): MediaMultipartPartSize
  partsCount(MediaFileSize $size): MediaMultipartPartsCount

реализация: App\Modules\Media\Infrastructure\FileService\MediaUploadPlanner
  __construct(private MediaConfig $mediaConfig)
  биндинг: MediaUploadPlannerContract::class => MediaUploadPlanner::class (const BINDINGS, MediaBootloader)
```

Контракт внутренний (не публичный кросс-модульный сценарий Media), поэтому в список сценариев Media в
`arch.md` (`CreateMedia`, `DeleteMedia`, …) не добавляется.

## Фазы выполнения

### 1. Контракт планнера + реализация + unit-тест

Цель: появляется конфиг-зависимый сервис планирования загрузки в Infrastructure за Application-контрактом,
покрытый тестом, ещё не подключённый к Handler.

Что сделать:
- Создать `app/src/Modules/Media/Application/Contract/MediaUploadPlannerContract.php` с четырьмя
  методами (`stagingExpiration`, `isMultipart`, `partSize`, `partsCount`), типы аргументов/возврата —
  доменные VO модуля Media (`MediaExpiration`, `MediaFileSize`, `MediaMultipartPartSize`,
  `MediaMultipartPartsCount`). `bool` у `isMultipart` допустим как return type чистого predicate.
- Создать `app/src/Modules/Media/Infrastructure/FileService/MediaUploadPlanner.php` — `final readonly
  class ... implements MediaUploadPlannerContract`, конструктор `private MediaConfig $mediaConfig`.
  Реализовать четыре метода по «Целевому алгоритму». `stagingExpiration()` считает `now` внутри метода
  через `new \DateTimeImmutable()->add(new \DateInterval(\sprintf('PT%dS', $this->mediaConfig->stagingTtlSeconds)))`.
  `partsCount()` делит на `$this->partSize()->value()` (а не на сырой `$this->mediaConfig->multipartPartSizeBytes`),
  чтобы делитель прошёл валидацию VO `MediaMultipartPartSize` (минимум 5 MiB) — это исключает деление на
  ноль/некорректный размер части и держит `partSize()`/`partsCount()` согласованными; `\ceil(...)`
  приводится к `int` перед `MediaMultipartPartsCount::fromInt(...)`.
- Создать `tests/Unit/Modules/Media/Infrastructure/MediaUploadPlannerTest.php` на голом
  `PHPUnit\Framework\TestCase` (как `MediaTypeResolverTest`): конструировать
  `new MediaUploadPlanner(new MediaConfig(...))`. Все `new MediaConfig(...)` — **именованными
  аргументами** (9 обязательных полей, правило именованных аргументов проверяется PHPStan strict). Во
  всех сценариях соблюдать инвариант конструктора `MediaConfig`: `multipartThresholdBytes >=
  multipartPartSizeBytes` (иначе он бросит `InvalidConfigValueException` и замаскирует реальный смысл
  теста).

Результат: `MediaUploadPlanner` и его контракт существуют, unit-тест зелёный, планнер ещё нигде не
используется (нет регрессии в остальном коде).

Сценарии тестирования:
- `stagingExpiration()` возвращает временный `MediaExpiration` с `expiresAt ≈ now + stagingTtlSeconds`.
  `MediaExpiration` не сравнивается по `===` — проверять через `value()?->getTimestamp()` с
  `assertEqualsWithDelta(..., 5)` (дельта 5 c, как в существующих тестах) и отдельно `isTemporary() === true`.
- `isMultipart()` — true при `size === threshold`, true при `size > threshold`, false при `size < threshold`.
  Для всех трёх кейсов брать валидную пару конфига, например `threshold = partSize = 5_242_880`, и
  варьировать только `size` (`5_242_880` / `5_242_881` / `5_242_879`).
- `partSize()` возвращает `MediaMultipartPartSize` со значением `multipartPartSizeBytes`.
- `partsCount()` округляет вверх: при валидной паре `threshold = partSize = 5_242_880` для
  `size = 5_242_881` → 2 части, для `size = 5_242_880` → 1 часть.
- `partSize()`/`partsCount()` пропускают значение через VO `MediaMultipartPartSize`: размер части ниже
  минимума VO (например `multipartPartSizeBytes` валиден для `MediaConfig`, но `< 5_242_880`) бросает
  `InvalidDomainValueException` — расчёт не обходит валидацию VO. (Подбирается пара, где инвариант
  `MediaConfig` соблюдён, но значение ниже минимума `MediaMultipartPartSize`.)

Проверка:
- `make test` (новый unit-тест зелёный), `make phpstan` (level max, strict-rules без ошибок).

### 2. Перевести Handler на контракт и подключить биндинг

Цель: `RequestMediaUploadHandler` получает конфиг-решения только через `MediaUploadPlannerContract`;
DI резолвит планнер; `MediaUploadSettings` больше не используется Handler-ом (но пока ещё существует).

Что сделать:
- В `MediaBootloader::BINDINGS` добавить `MediaUploadPlannerContract::class => MediaUploadPlanner::class`
  и нужные `use`. (Старую фабрику `MediaUploadSettings` пока НЕ трогаем — система остаётся целостной:
  планнер забиндён и резолвится, Handler переключается в этой же фазе.)
- В `RequestMediaUploadHandler`:
  - заменить зависимость `private MediaUploadSettings $uploadSettings` на
    `private MediaUploadPlannerContract $uploadPlanner`; обновить `use`;
  - `expiration: MediaExpiration::temporaryUntil($this->expiresIn($this->uploadSettings->stagingTtlSeconds))`
    → `expiration: $this->uploadPlanner->stagingExpiration()`;
  - вызов `$this->isMultipart($command->fileMeta->size)` → `$this->uploadPlanner->isMultipart($command->fileMeta->size)`;
    удалить приватный метод `isMultipart()`;
  - в `prepareMultipartUpload()`: `$partsCount = $this->uploadPlanner->partsCount($media->size);`
    оставить переменной (используется дважды — в `presignUploadParts(partsCount: ...)` и
    `MediaMultipartUpload::create(partsCount: ...)`); удалить приватный метод `partsCount()`. Локальную
    `$partSize` НЕ заводить: после переноса расчёта частей в планнер она стала одноразовой (только в
    `MediaMultipartUpload::create`), поэтому по правилу «Инлайн одноразовых переменных» вызов
    инлайнится — `partSize: $this->uploadPlanner->partSize()` прямо в аргументах
    `MediaMultipartUpload::create(...)`;
  - удалить ставшие неиспользуемыми `use`: `MediaUploadSettings`, `MediaExpiration`, `MediaFileSize`,
    `MediaMultipartPartSize`, `MediaMultipartPartsCount`. Оставить `expiresIn()` (используется для
    presigned-срока из спеки) и все прочие импорты.
- Обновить `tests/Feature/.../RequestMediaUploadHandlerTest.php`:
  - в приватном `handler(...)` заменить `uploadSettings: new MediaUploadSettings(...)` на
    `uploadPlanner: new MediaUploadPlanner($this->mediaConfig(threshold: $threshold, partSize: $partSize))`,
    где приватный помощник `mediaConfig(int $threshold, int $partSize): MediaConfig` строит полный
    `MediaConfig` с `stagingTtlSeconds: 86_400`, переданными `multipartThresholdBytes`/`multipartPartSizeBytes`
    и валидными значениями-заглушками остальных полей (`imageProcessingDriver: 'imagick'`,
    `ffmpegBinaryPath`/`ffprobeBinaryPath` — любые непустые пути, `ffmpegTimeoutSeconds: 1800`,
    `ffmpegThreads: 0`, `presignedTtlSeconds: 3600`). Все `new MediaConfig(...)` — именованными
    аргументами; helper обязан держать инвариант `multipartThresholdBytes >= multipartPartSizeBytes`
    (существующий кейс `testRequestsMultipartUploadWhenSizeReachesThreshold` зовёт `threshold =
    partSize = 5_242_880` — равенство допустимо, исключения нет);
  - заменить импорт `MediaUploadSettings` на `MediaUploadPlanner` и `MediaConfig`;
  - проверки и сценарии тестов не меняются по смыслу (single/multipart/threshold).

Результат: Handler конфиг не знает, берёт решения у планнера; фича-тесты загрузки зелёные.
`MediaUploadSettings` всё ещё существует и биндится фабрикой (используется только `MediaBootloaderTest`),
система целостна.

Сценарии тестирования:
- single-загрузка: `MediaUploadMode::Single`, `parts === null`, presigned-срок из спеки;
- multipart при `size === threshold`: `MediaUploadMode::Multipart`, `uploadId`/`parts` заполнены,
  запись `MediaMultipartUpload`, presigned-срок частей из спеки;
- **staging-срок медиа берётся у планнера**: у сохранённого `Media` (через `mediaRepository()->findById`)
  `expiration` временный и `expiration.value()?->getTimestamp() ≈ now + stagingTtlSeconds` теста (86_400),
  `assertEqualsWithDelta(..., 5)` — это доказывает сквозную проводку `stagingExpiration()` через Handler
  (unit-тест планнера сам по себе её не подтверждает);
- отклонения mime/размер/расширение/документы — без регресса (значения берутся из спеки, не из конфига).

Проверка:
- `make test` (фича-тесты Media зелёные), `make phpstan`.

### 3. Удалить MediaUploadSettings и фабрику бутлоадера

Цель: «конфиг от конфига» физически удалён, бутлоадер не содержит фабрики настроек, kernel-тест
проверяет резолв нового контракта, документация модуля (`README.md`) синхронизирована с кодом.

Что сделать:
- В `MediaBootloader` удалить метод `defineSingletons()` и фабрику `mediaUploadSettings()`,
  атрибут `#[\Override]`, а также неиспользуемые `use MediaUploadSettings` и `use MediaConfig`
  (конфиг больше не читается в самом бутлоадере). `const BINDINGS` (с планнером из фазы 2) и
  `boot(OutboxJobRegistryContract)` остаются.
- Удалить файл `app/src/Modules/Media/Application/Dto/MediaUploadSettings.php`.
- В `tests/Kernel/.../MediaBootloaderTest.php` заменить `testMediaUploadSettingsAreBuiltFromConfig`
  на `testMediaUploadPlannerContractResolvesToInfrastructureImplementation` по образцу соседнего
  `testMediaUrlServiceContractResolvesToInfrastructureImplementation`: получить
  `MediaUploadPlannerContract` из контейнера и `assertInstanceOf(MediaUploadPlanner::class, ...)`.
  Обновить `use`: убрать `MediaUploadSettings`, добавить контракт и реализацию планнера.
- **Обновить `app/src/Modules/Media/README.md`, раздел «Инфраструктура и конфиг» (строки 180–185).**
  Сейчас там дословно описан удаляемый паттерн: «`MediaBootloader` читает `MediaConfig` в фабрике
  `defineSingletons()` и отдаёт в `RequestMediaUploadHandler` готовый `MediaUploadSettings`».
  Переписать на: «`RequestMediaUploadHandler` получает решения пайплайна загрузки (срок
  staging-хранения, нужен ли multipart, размер и число частей) через `MediaUploadPlannerContract`;
  реализация `MediaUploadPlanner` (`Infrastructure/FileService`) читает `MediaConfig` через
  конструктор и биндится `const BINDINGS` — как `MediaUrlService`». Это обязательно сделать **до**
  grep-проверки ниже, иначе README (лежит под `app/`) даст совпадение и гейт станет красным; заодно
  снимается стале-документация.
- **Там же поправить описание потока загрузки (строки 79–80 README):** сейчас «`uploadMode`
  (single/multipart) выбирается по порогу `MediaConfig.multipartThresholdBytes`; размер части —
  `MediaConfig.multipartPartSizeBytes`». После рефакторинга это решение принимает `MediaUploadPlanner`
  (он читает те же ключи `MediaConfig`), поэтому строки переписать так, чтобы атрибутировать выбор
  режима и размера части `MediaUploadPlanner`, а не «прямому» чтению конфига сценарием. Описание ключей
  конфига в разделе «Инфраструктура и конфиг» (порог/размер части как поля `app/config/media.php`)
  оставить — это легитимное описание конфиг-файла, а не чтения его в Application.
- Проверить отсутствие любых ссылок в коде, тестах и README:
  `grep -rn "MediaUploadSettings\|mediaUploadSettings" app tests` → пусто. Историю в `docs/plans/`
  (старый план/логи про `MediaUploadSettings`) НЕ редактируем — это записи о прошлой работе, и grep
  по `app tests` их не захватывает.

Результат: `MediaUploadSettings` удалён из кода, тестов и README; kernel-тест подтверждает биндинг
планнера, полный прогон зелёный.

Сценарии тестирования:
- контейнер резолвит `MediaUploadPlannerContract` в `MediaUploadPlanner`;
- grep по `MediaUploadSettings`/`mediaUploadSettings` в `app`+`tests` ничего не находит;
- полный сьют без регрессий.

Проверка:
- `grep -rn "MediaUploadSettings\|mediaUploadSettings" app tests` — нет совпадений;
- `make test` (весь сьют), `make phpstan`.

### 4. Обновить docs/rules.md и docs/arch.md

Цель: правила и архитектура запрещают settings-DTO «конфиг от конфига» и фиксируют паттерн
«конфиг-зависимое поведение → Infrastructure-сервис за Application/Contract».

Что сделать:
- `docs/rules.md`, правило «`env()` только в конфигах» (строка 62):
  - убрать `settings` из перечня готовых значений: «получают готовые значения (VO, скаляры, доменные
    сервисы) через DI»;
  - добавить: поведение, зависящее от конфига, живёт в Infrastructure-сервисе за `Application/Contract`
    (образцы `MediaUrlService`, `MediaUploadPlanner`); промежуточный settings-DTO, проецирующий поля
    `*Config` в Application, запрещён как «конфиг от конфига»; допустимые формы передачи значений —
    единичное готовое значение/VO через фабрику бутлоадера либо доменный сервис над значениями.
    Формулировку держать короткой и не дублировать развёрнутый текст arch.md, чтобы два документа не
    разошлись.
- `docs/arch.md`, раздел «Правила зависимостей», строгое правило про конфиг (строки 285–295):
  - убрать «маленькие settings-объекты» из списка готовых значений;
  - переписать на две допустимые формы передачи (единичное значение/VO через фабрику `defineSingletons()`
    — живые образцы после рефакторинга: `UserBootloader` с avatar URL и `LocaleResolver` из
    `LocaleConfig`; доменный сервис над значениями) плюс отдельный абзац: конфиг-зависимое поведение
    выносится в Infrastructure-сервис за `Application/Contract`, образцы
    `MediaUrlServiceContract`/`MediaUrlService` и `MediaUploadPlannerContract`/`MediaUploadPlanner`
    (читает `MediaConfig` через конструктор, биндится `const BINDINGS`); явный запрет settings-DTO
    «конфиг от конфига». Общее правило формулировать как «Infrastructure-сервис за контрактом» (папка —
    просто `Infrastructure`); конкретное размещение `MediaUploadPlanner` в `Infrastructure/FileService` —
    частная деталь модуля Media (рядом с `MediaUrlService`), а не предписание для всех будущих
    config-зависимых сервисов;
  - абзац «Оговорка по охвату» про Presentation-адаптеры оставить как есть (вне охвата).
- Проверить, что обновлённые формулировки не противоречат соседним правилам (`Typed config для каждого
  config-файла`, раздел `TypedConfig`, «Границы и контроль качества») и описывают реально внедрённый
  паттерн.

Результат: документация описывает новый строгий паттерн; нет упоминаний разрешённых settings-объектов;
тексты ссылаются на `MediaUploadPlanner` как на образец. Задача считается завершённой только после этой
фазы: до неё код может быть зелёным, но правила/архитектура ещё разрешают удаляемый паттерн.

Сценарии тестирования: не применимо (документация). Проверка — согласованность формулировок с кодом и
соседними правилами.

Проверка:
- ручная вычитка `docs/rules.md` и `docs/arch.md`: нет фразы про разрешённые «settings-объекты»,
  есть описание паттерна с контрактом;
- мягкий гейт по живым докам: `grep -rn "settings-объект" docs/rules.md docs/arch.md app/src/Modules/Media/README.md`
  не находит разрешающих формулировок (только запрещающие, если упомянуты); исторические `docs/plans/`,
  `docs/reviews/` не трогаем;
- закрывающий гейт качества: `make test-coverage` (покрытие 100% — отдельная цель, `make test` покрытие
  не считает) и `make phpstan` зелёные (либо общий `make qa`).

## Тесты

Стратегия: `after_each_phase`. После каждой фазы с кодом — прогон `make test` и `make phpstan` (быстрый
прогон без покрытия); закрывающий гейт покрытия — `make test-coverage` (или `make qa`) в конце:
- Фаза 1 — новый `MediaUploadPlannerTest` (unit) покрывает все четыре метода планнера, включая обе ветви
  `isMultipart`, округление `partsCount`, дельту `stagingExpiration` и проброс размера части через VO.
- Фаза 2 — обновлённый `RequestMediaUploadHandlerTest` подтверждает сохранение поведения (single,
  multipart по порогу, staging-срок из планнера, отклонения).
- Фаза 3 — обновлённый `MediaBootloaderTest` подтверждает резолв `MediaUploadPlannerContract`;
  полный сьют без регрессий.
- Фаза 4 — кода нет; закрывающий полный прогон `make test`/`make phpstan` остаётся зелёным.

Покрытие 100% сохраняется: планнер полностью покрыт unit-тестом (а его ветви дополнительно проходят
через фича-тест Handler-а), удаляемый `MediaUploadSettings` уносит свой kernel-тест, заменяемый тестом
резолва контракта.

## Логирование

Стратегия: `debug_precise`. Существующий точечный debug-лог остаётся на границе операции в
`RequestMediaUploadHandler` («Запрошена загрузка медиа.» с `mediaId`, `userId`, `uploadMode`, `size`,
`mimeType`, `presignedTtlSeconds`) — он и есть точная отладочная точка сценария и не меняется.
`MediaUploadPlanner` — чистая функция над `MediaConfig` и размером файла (детерминированный расчёт без
побочных эффектов и без обращений к БД/сети), поэтому собственного логирования не добавляет: лог на
каждый вызов планнера был бы шумом и нарушил бы правило «DEBUG по умолчанию, без шума». Новых уровней,
сообщений или контекста рефакторинг не вводит.

## Документация и эксплуатация

- `docs/rules.md` и `docs/arch.md` — обновляются в фазе 4 (часть задачи); `app/src/Modules/Media/README.md`
  (раздел «Инфраструктура и конфиг») — в фазе 3 (синхрон с кодом + снятие стале-документации).
- ENV/конфиг не меняются: переменные `MEDIA_STAGING_TTL_SECONDS`, `MEDIA_MULTIPART_THRESHOLD_BYTES`,
  `MEDIA_MULTIPART_PART_SIZE_BYTES` и `app/config/media.php` остаются как есть — меняется только то, кто
  и где читает эти значения.
- Память агента `application-config-independence.md` при исполнении обновляется: settings-DTO больше не
  согласованный паттерн; добавить, что конфиг-зависимое поведение идёт через Infrastructure-сервис за
  `Application/Contract` (образец `MediaUploadPlanner`). Это правка личной памяти, не репозитория.
- Релизных рисков нет: поведение пайплайна загрузки сохраняется байт-в-байт, миграции и внешние
  контракты не затронуты.

## Изменения после мета-ревью

### После моделей

- **+ Добавлено:** Правка `app/src/Modules/Media/README.md` (раздел «Инфраструктура и конфиг», строки
  180–185) в фазу 3 — там дословно описан удаляемый паттерн `defineSingletons()` →
  `MediaUploadSettings`; без правки grep-гейт фазы 3 (`grep ... app tests`) находил бы README и падал, и
  оставалась бы стале-документация. Блокер нашли независимо два ревьюера (opus, sonnet).
- **+ Добавлено:** В фазе 2 явный шаг — НЕ заводить локальную `$partSize`, а инлайнить
  `partSize: $this->uploadPlanner->partSize()` в `MediaMultipartUpload::create(...)`: после переноса
  расчёта частей в планнер переменная стала одноразовой, и правило «Инлайн одноразовых переменных»
  (ловится strict-rules PHPStan) требует инлайна (sonnet).
- **+ Добавлено:** Требование именованных аргументов для всех `new MediaConfig(...)` в тестах (9 полей,
  PHPStan strict) и явный инвариант `multipartThresholdBytes >= multipartPartSizeBytes` во всех тестовых
  сценариях, чтобы валидация конфига не маскировала смысл теста (sonnet).
- **+ Добавлено:** В фазе 4 названы живые образцы формы «единичное значение/VO через фабрику» после
  рефакторинга — `UserBootloader` (avatar URL) и `LocaleResolver` — иначе arch.md ссылалась бы на форму
  без наглядного примера в коде (opus).
- **~ Изменено:** Сценарий теста `stagingExpiration()` уточнён — `MediaExpiration` не сравним по `===`,
  проверять через `value()?->getTimestamp()` + `assertEqualsWithDelta(…, 5)` и `isTemporary()` (opus).
- **~ Изменено:** Grep-гейт фазы 3 расширен на имя фабрики `mediaUploadSettings` и зафиксировано, что
  историю в `docs/plans/` не редактируем (haiku, opus).
- **Отклонено:** Тест «два вызова `stagingExpiration()` дают разные моменты» и увеличение дельты до 10 c
  (haiku) — план уже фиксирует расчёт `now` per-call внутри метода (подтвердили opus и sonnet), а дельта
  5 c консистентна с существующими тестами; отдельный тест на «свежесть времени» был бы flaky и избыточен.

## Реакция на ревью

Кросс-CLI ревью (strict): `codex exec`, файл `docs/plans/2026-06-30_15-04_remove-media-upload-settings_review.md`.
Все замечания приняты и внесены в план (спорных/вынесенных пользователю — нет).

- **Принято (блокер):** `partsCount()` делит на `$this->partSize()->value()`, а не на сырой
  `multipartPartSizeBytes` — делитель проходит валидацию VO `MediaMultipartPartSize` (минимум 5 MiB),
  исключая деление на ноль/некорректный размер части и держа `partSize()`/`partsCount()` согласованными.
- **Принято:** Закрывающий гейт качества — `make test-coverage` (или `make qa`), а не `make test`:
  100% покрытие считает отдельная цель, `make test` его не меряет.
- **Принято:** В фазе 2 добавлен сценарий — у сохранённого `Media` `expiration ≈ now + stagingTtlSeconds`,
  чтобы доказать сквозную проводку `stagingExpiration()` через Handler (не только unit-тест планнера).
- **Принято:** В фазе 1 добавлен сценарий — размер части ниже минимума VO бросает
  `InvalidDomainValueException` (расчёт не обходит валидацию `MediaMultipartPartSize`).
- **Принято:** Правка README расширена на строки 79–80 (выбор `uploadMode`/размера части
  атрибутируется `MediaUploadPlanner`, а не «прямому» чтению `MediaConfig` сценарием).
- **Принято:** В фазе 4 — мягкий гейт `grep "settings-объект"` по живым докам (`rules.md`, `arch.md`,
  `Media/README.md`), исторические `docs/plans`/`docs/reviews` не трогаем; формулировка arch.md
  оставляет живыми разрешённые формы (`UserBootloader`-скаляр, `LocaleResolver`).
- **Принято:** `Infrastructure/FileService` для планнера в arch.md подаётся как частная деталь модуля
  Media (рядом с `MediaUrlService`), а общее правило — «Infrastructure-сервис за контрактом», без
  предписания конкретной подпапки.
- **Принято:** Зафиксировано, что задача завершена только после фазы 4 (до неё код зелёный, но
  правила/архитектура ещё разрешают удаляемый паттерн).

## Прогресс выполнения
Журнал: `docs/executions/2026-06-30_15-47_remove-media-upload-settings.md`

- [x] Фаза 1: Контракт планнера + реализация + unit-тест
- [x] Фаза 2: Перевести Handler на контракт и подключить биндинг
- [x] Фаза 3: Удалить MediaUploadSettings и фабрику бутлоадера
- [x] Фаза 4: Обновить docs/rules.md и docs/arch.md
