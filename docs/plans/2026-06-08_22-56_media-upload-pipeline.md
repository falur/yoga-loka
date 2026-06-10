---
title: Модуль Media — переиспользуемый Application-API загрузки (presigned + конверсии через outbox)
date: 2026-06-08 22:56
mode: normal
plan_size: normal
decision_mode: recommend_and_ask
status: meta-reviewed
reviewer: none
meta_reviewers: [claude-haiku, claude-sonnet, claude-opus]
test_strategy: after_each_phase
logging_strategy: debug_precise
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  research: docs/researches/2026-06-08_22-56_media-upload-pipeline.md
---

# План реализации: модуль Media — переиспользуемый Application-API загрузки

## Задача

Сделать у модуля `Media` переиспользуемый Application-API загрузки файла: прямая
загрузка клиентом по временной ссылке (presigned, для крупных файлов — multipart) в
MinIO, подтверждение, асинхронная обработка через transactional outbox (перекладка
оригинала в целевой бакет + конверсии изображений на Imagick). Ограничения каждой
загрузки задаёт модуль-потребитель спецификацией в вызов; собственного HTTP у Media нет.

Готово, когда: модуль `User` (и другие) могут цепочкой Application-вызовов запросить
загрузку с нужными настройками, подтвердить её и получить готовое медиа (`ready` с
конверсиями); публичные сценарии Media работают и покрыты тестами на 100%; всё проходит
`make phpstan` (level max).

## Контекст

- Зачем именно так: `docs/arch.md` фиксирует, что `Media` даёт только свои сценарии и не
  знает про конкретные применения: «Media должен давать только свои сценарии…»
  (`arch.md:158-166`); «Другие модули могут обращаться только к Application»
  (`arch.md:131`); «Media не должен знать про аватар. Аватар - это часть User»
  (`arch.md:156`); пример `User/Application/SetAvatar -> Media/Application/...`
  (`arch.md:148-155`). Поэтому общий HTTP-эндпоинт в Media не делаем — точка входа с
  настройками живёт в потребителе и вызывает Media/Application.
- Настройки — в вызов: ограничения нельзя зашивать в общий конфиг и нельзя отдавать
  клиенту. Потребитель (серверный модуль) держит политику и передаёт её в сценарии Media.
- `Media` собран только «внутри» (Domain, Repository, Infrastructure/Cycle, миграции);
  публичного входа и реализации работы с файлами нет.
- Доменная модель готова: статусы `Media`, сущности `Media`, `MediaImageConversion`,
  `MediaMultipartUpload`, VO, enum, коллекции (`MediaMultipartPartCollection`,
  `MediaImageConversionCollection`). Таблицы созданы миграцией
  `app/database/migrations/20260521.184100_0_create_media_domain_tables.php` (unique
  `media_image_conversions(media_id, type)`). **Схема БД не меняется.**
- Доменные дополнения (без изменения схемы):
  1. `Media::markReadyMovedTo(MediaStorage, MediaPath)` (поля `storage`/`path` —
     `private(set)`, переназначаются вторично после `create`) — guard: допустим только из
     `uploaded`/`processing`, на `ready` → no-op (повтор); predicate `Media::isReady(): bool`.
  2. Фабрики `MediaPath::imageConversion(MediaStorageKey, MediaImageConversionType, ext)`
     (префикс `images/`) и `MediaPath::originalReady(MediaStorageKey, MediaType, ext)` —
     `assertValid` строго требует префикс `uploads|images|videos` и shard = первые 2 символа
     `storageKey` (`MediaPath.php:61-79`); `originalReady` выбирает префикс по `MediaType`
     (`images/`|`videos/`), не хардкодит `images/`.
  3. Резолвер mime → `MediaType` (поддержаны `Image`/`Video`; `Audio`/`Document` вне scope
     этого плана → `ValidationException` 422, т.к. `MediaPath::assertValid` допускает только
     префиксы `uploads|images|videos`) и проверка допустимости по `MediaUploadSpec`.
- `MediaStorageKey` использует UUID v4 (`MediaStorageKey.php:18`) — это существующий код
  (формально расходится с правилом «UUID v7 для всех идентификаторов»; shard завязан на
  hex-формат v4). В рамках этого плана не трогаем; вынесено как отдельный вопрос.
- Multipart-completion в этом плане **синхронный** (внутри `CompleteMediaUpload`), поэтому
  существующие доменные методы/статусы `startCompletingMultipartUpload`,
  `MultipartCompletionFailedCanRetry`, `MultipartCompletionFailedNeedReupload`
  (`Media.php:130-148`) не используются — оставляем зарезервированными (не удаляем: затронуло бы
  enum/домен вне scope), отдельный вопрос как UUID v4.
- Хранилище: бакеты `media-upload`/`media-private`/`media-public` настроены
  (`app/config/storage.php:84-101`), автосоздаются в dev
  (`docker/docker-compose.dev.yml:148`). `StorageConfig` (typed) отдаёт сервер `s3` и
  бакеты (`StorageConfig.php`, `StorageServerConfig.php`, `StorageBucketConfig`). enum
  `MediaStorage` хранит **алиасы** бакетов Spiral; реальное имя бакета и `prefix` берутся
  из `StorageConfig->buckets[alias]` (`StorageBucketConfig.bucket`/`prefix`). `media-public`
  по умолчанию создаётся без anonymous-read policy → прямой `publicUrl` вернёт 403; политику
  публичного чтения выставляем в `ensure-buckets.sh` (решение пользователя).
- S3: `league/flysystem-aws-s3-v3` 3.34 явно, `aws/aws-sdk-php` 3.381.2 транзитивно
  (`composer.lock:64`) — добавляем `aws/aws-sdk-php` явной зависимостью. presigned/
  multipart flysystem не даёт; используем `Aws\S3\S3Client`.
- read-методы репозиториев уже существуют (`MediaRepository::findById`,
  `MediaImageConversionRepository::findByMediaId`,
  `MediaMultipartUploadRepository::findByMediaId`) — заново не создавать.
- Outbox готов: `OutboxEventStoreContract::add(OutboxMessage)`, поток
  `сообщение → Job → Command`, загрузка payload через `OutboxMessageLoaderContract`
  (по `OutboxEventId` VO из `OutboxQueueEnvelope`), реестр `OutboxJobRegistryContract`
  (singleton), статусы через `OutboxQueueStatusInterceptor`. Рецепт — `Outbox/README.md`.
  Сериализация payload — `ValinorOutboxMessageSerializer`: **нормализатор/маппер не
  регистрирует кастомные конструкторы**, поэтому payload не должен содержать доменных VO с
  приватными фабриками (иначе `MappingError` при десериализации в Job). Допустимы
  `string`/`int`/`bool`/`BackedEnum`/`DateTimeImmutable` **и публичные readonly-DTO с публичным
  конструктором** (Valinor штатно маппит `list<MediaConversionSpec>`, где `MediaConversionSpec`
  — enum+int+int с публичным конструктором). Consume-interceptors стоят в
  `app/config/queue.php:158-162` (порядок не менять).
- Картинки: Intervention Image 4.1.3 (PHP `^8.3`, packagist) + Imagick; в
  `docker/Dockerfile:22-34` нет графических расширений.
- Тестовая среда: presigned endpoint `minio:9000` доступен только внутри docker; S3-тесты —
  внутри контейнера (`make test`). Изоляции тестовых media-бакетов сейчас нет, а `reset-test`
  намеренно чистит только `yoga-loka-test`. Поэтому тесты используют **общие** media-бакеты с
  выделенным тестовым key-префиксом и сами чистят этот префикс в `tearDown` (решение
  пользователя); `docker-compose.dev.yml`, `ensure-buckets.sh` и guard `reset-test` для этого
  не трогаем.

## Принятые решения

Существенные решения подтверждены пользователем или зафиксированы в research:

1. Обработка — Intervention Image v4 + Imagick; в Docker imagick; драйвер из
   `MediaConfig`; фолбэк GD; фикстуры процессора JPEG/PNG. *(research + пользователь)*
2. presigned/multipart и серверные S3-операции — через `Aws\S3\S3Client` из
   `StorageConfig`. *(research)*
3. Обычный путь — одиночный presigned PUT; multipart — для крупных файлов, порог по
   размеру (≥ 5 MB на часть) из `MediaConfig`. *(research)*
4. Асинхронная обработка — через transactional outbox: `CompleteUpload`
   (`#[Transactional]`) помечает `uploaded` и в той же транзакции кладёт `MediaUploaded`;
   relay → `ProcessMediaJob` → `ProcessMediaCommand`. *(research + пользователь)*
5. Расхождение с документами снимается в фазе 6 (правка формулировки outbox в
   `arch.md`/`rules.md`). *(пользователь)*
6. **Media без собственного HTTP — переиспользуемый Application-API.** Точки входа с
   настройками — в потребителях. *(пользователь, по arch.md)*
7. **Ограничения загрузки потребитель передаёт спецификацией в вызов.** *(пользователь)*
8. Размер плана — normal. *(пользователь)*

Локальные решения (`decision_mode: recommend_and_ask`):

- Application-DTO в `Media/Application/Dto`:
  - `MediaUploadSpec` (in-process, VO/enum): `allowedMimeTypes: MediaMimeTypeCollection`,
    `maxSize: MediaFileSize`, `visibility: MediaVisibility`. Конверсий здесь нет.
  - `MediaFileMeta`: `fileName: string` (вспомогательное, для расширения, не хранится),
    `mimeType: MediaMimeType`, `size: MediaFileSize`.
  - `MediaConversionSpec` (примитив-дружественный, переиспользуется и в outbox-payload):
    `type: MediaImageConversionType` (enum), `width: int`, `height: int`. Это **Application-DTO**
    (не Domain-VO) с **публичным** конструктором (требование `ValinorOutboxMessageSerializer`);
    Handler сам строит из него `MediaImageConversionType`/`MediaPixelDimension`.
  - Result-DTO: `RequestMediaUploadResult` (`mediaId: string`, `uploadMode: MediaUploadMode`
    enum, типизированные ссылки, `expiresAt`), `MediaResult`, `MediaUrlResult`.
- `UserId` загружающего потребитель передаёт параметром; проверка владельца
  (`CompleteUpload`/`Delete`/`MakePermanent`) — в Handler по переданному `UserId`
  (несовпадение → `ForbiddenException`).
- **Профиль конверсий — единый источник: передаётся только в `CompleteMediaUpload`**
  (не в `MediaUploadSpec`/`RequestUpload`), кладётся в outbox-сообщение
  `MediaUploaded(string mediaId, list<MediaConversionSpec> conversions)` и так доезжает до
  `ProcessMedia`. Дублирования нет.
- Части multipart — типизированная `MediaMultipartPartCollection` (готовая) в Command,
  без array-shape `list<{...}>`.
- Media-сценарии возвращают Result-DTO, **не доменную Entity** наружу.
- `app/config/media.php` + `MediaConfig` — только инфра-дефолты (TTL presigned, порог
  multipart, размер части, драйвер обработки), с дефолтами; маппинг покрыт `ConfigMapper`.
- `CheckMediaIsImage`/`CheckMediaExists` возвращают `bool` (predicate-Query).
- `app/config/media.php` + `MediaConfig` размещается в
  `Shared/Infrastructure/Configuration/Media` — по правилу `arch.md` «Configuration → app/config
  → Shared/Infrastructure/Configuration» (`arch.md:598`); это не нарушение `Shared`, а конвенция
  размещения всех `TypedConfig` (рядом с `Outbox`, `Storage`, `Queue`).

### Транзакционная дисциплина и идемпотентность

- `RequestMediaUpload` и `CompleteMediaUpload` — короткие DB-операции;
  `CompleteMediaUpload` — `#[Transactional]` (uploaded + outbox в одной транзакции).
- `ProcessMediaHandler` — **без `#[Transactional]`**: сначала все S3/Imagick-операции
  (вне транзакции, чтобы не держать БД и блокировку строки на время обработки), затем
  один `entityManager->persist(media + conversions); run()` (атомарный flush) с
  `markReadyMovedTo`. `copyObject` и заливка конверсий идемпотентны (детерминированные
  ключи от `storageKey`). Статус `MediaStatus::Processing` в этом потоке намеренно не
  используется (атомарный переход `uploaded`→`ready` одним flush, без лишней записи в БД);
  `startProcessing()` зарезервирован.
- Идемпотентность без delete+create: конверсии создаются только в финальном атомарном
  flush вместе с переходом в `ready`. Поэтому частичного состояния «есть конверсии, но не
  ready» не бывает: при повторе до коммита конверсий нет → создаём заново без конфликта по
  unique `(media_id, type)`; если `isReady()` — no-op. Перезагрузка из репозитория не
  нужна.
- `deleteObject` оригинала из `media-upload` синхронно **не делаем** — staging чистится по
  expiry (отдельная задача; `findExpired` + `ReadyOriginalRemoved` зарезервированы).
- Обработка ошибок — **в `ProcessMediaJob` (инфраструктурный Job, try-catch разрешён
  правилами), не в Handler**: Job ловит исключение S3/Imagick, диспатчит
  `RecordMediaProcessingFailure(mediaId, safeMessage, isTransient)`, затем для транзиентной
  бросает `RetryException` (его читает `OutboxQueueStatusInterceptor`), для постоянной —
  пробрасывает терминально (outbox → failed). `recordTemporaryProcessingError` и
  `recordPermanentProcessingError` сейчас семантически идентичны (`Media.php:163-177`):
  `isTransient` влияет **только** на ретрай Job (`RetryException` vs терминальный проброс), на
  Entity пишется один и тот же `ProcessingFailed` + инкремент. Чтобы не плодить мёртвую ветку —
  вызывать один метод записи (различие зафиксировать как семантическое-без-эффекта). Текст для
  `MediaProcessingError` — из **предопределённого набора безопасных сообщений** по классу
  ошибки, сырой текст AWS не прокидывать (VO отклоняет пути и слово `etag`,
  `MediaProcessingError.php:30-38`; иначе `fromString` бросит 500 внутри обработчика ошибки).

## Целевой алгоритм

```text
Потребитель (например, будущий User-аватар) держит политику и вызывает Media/Application:

1. RequestMediaUpload(UserId, MediaUploadSpec, MediaFileMeta) -> RequestMediaUploadResult
   - резолв mime → MediaType (поддержаны Image/Video; иное → ValidationException 422);
     mime вне spec.allowedMimeTypes → ValidationException(422)
   - size > spec.maxSize → ValidationException; выбор single|multipart по порогу MediaConfig
   - Media::create(waitingUpload, storage=Upload, path=MediaPath::originalUpload,
     visibility=spec.visibility, uploadedById=UserId, mimeType=meta.mimeType,
     size=meta.size, expiration=MediaExpiration из staging-TTL MediaConfig)
   - single: presignPut(media-upload, key, ttl) → один PUT-URL
     multipart: partsCount/partSize вычисляются из MediaFileMeta.size и порога/размера части
     MediaConfig; createMultipartUpload → uploadId; presignUploadPart для каждой части (1..N);
     MediaMultipartUpload::create(media, uploadId, partsCount, partSize, fileSize)
   - persist(media [+ multipartUpload]); run()
   - return RequestMediaUploadResult{mediaId, uploadMode, expiresAt; single: putUrl |
     multipart: uploadId + MediaPresignedPartCollection}

2. Клиент PUT-ит байты прямо в MinIO (media-upload).

3. CompleteMediaUpload(UserId, mediaId, list<MediaConversionSpec> conversions,
   MediaMultipartPartCollection|null parts)        [#[Transactional]] -> MediaResult
   - грузит Media (нет → NotFoundException); uploadedById == UserId (иначе ForbiddenException)
   - waitingUpload (иначе ValidationException 422)
   - валидация conversions: каждый width/height > 0 (иначе ValidationException 422 на границе,
     чтобы 0/негатив не дошёл до MediaPixelDimension::fromInt → 500 в ProcessMedia)
   - multipart: multipartUpload->replaceParts(parts) (для идемпотентного повтора complete);
     completeMultipartUpload(uploadId, parts) — на повторе после частичного сбоя S3 вернёт
     NoSuchUpload: трактуем как успех, если headObject подтверждает собранный объект
   - headObject(media-upload, key): объект есть и ContentLength == Media->size->value()
     (для multipart — после completeMultipartUpload; MinIO даёт read-after-write
     consistency, при необходимости один повтор headObject)
   - Media->markUploaded(); outboxEventStore->add(new MediaUploaded(mediaId, conversions))
   - persist(media); run()       ← одна транзакция

4. outbox:relay → ProcessMediaJob в RabbitMQ.

5. ProcessMediaJob.invoke (образец OutboxDebugLogJob):
   - msg = OutboxMessageLoader->load($payload->outboxEventId, MediaUploaded::class)
   - try: commandBus->dispatch(new ProcessMediaCommand(msg.mediaId, msg.conversions), ...)
     catch: dispatch(RecordMediaProcessingFailure(...)); RetryException|terminal

6. ProcessMediaHandler(mediaId, conversions)        [без #[Transactional], идемпотентен]
   - грузит Media; isReady() → no-op
   - targetStorage = Public|Private по visibility медиа (оригинал И конверсии живут в одном
     бакете по visibility — иначе GetMediaUrl резолвит конверсию не в том бакете / утечка)
   - S3/Imagick ВНЕ транзакции: для каждого MediaConversionSpec ресайз из оригинала →
     процессор возвращает результат (байты/поток + mimeType + size + фактические width/height);
     заливка в targetStorage по MediaPath::imageConversion; copyObject оригинала
     upload→targetStorage по MediaPath::originalReady
   - построить MediaImageConversion::create(media, spec.type, MediaConversionStatus::Ready,
     targetStorage, MediaPath::imageConversion, mimeType+size ИЗ результата процессора,
     MediaPixelDimension из spec.width/spec.height) — mimeType/size конверсии в spec
     отсутствуют, их даёт процессор
   - Media->markReadyMovedTo(targetStorage, targetPath)
   - persist(media + conversions); run()    ← единый атомарный flush; оригинал не удаляем

7. Потребитель: GetMediaUrl, CheckMediaIsImage, CheckMediaExists (Query); DeleteMedia,
   MakeMediaPermanent (Command, проверка владельца).
   - DeleteMedia: статуса/метода `deleted` в домене нет → `entityManager->delete($media)` +
     DB-cascade на `MediaMultipartUpload`/`MediaImageConversion`; допустим из любого статуса.
     В `waitingUpload` с активным multipart — сначала abortMultipartUpload(uploadId из
     MediaMultipartUpload), затем deleteObject (игнорируя 404).
   - MakeMediaPermanent: Handler проверяет статус (`uploaded`/`ready`) до вызова и бросает
     `ValidationException` 422 на границе; доменный `makePermanent()` остаётся без guard
     (домен бросил бы 500, валидация статуса — на Application-границе).
```

## Контракты реализации

### Данные и БД

`Не затрагивается`. Таблицы и индексы (включая unique `media_image_conversions
(media_id, type)`) уже созданы миграцией `20260521.184100`. Доменные методы и фабрики
путей схему не меняют.

### Публичный Application-API модуля Media (контракт для потребителей)

```text
Command RequestMediaUpload(UserId, MediaUploadSpec, MediaFileMeta)        -> RequestMediaUploadResult
Command CompleteMediaUpload(UserId, mediaId, list<MediaConversionSpec>,
                            MediaMultipartPartCollection|null parts)        -> MediaResult
Command ProcessMedia(mediaId: string, list<MediaConversionSpec>)           -> void (через Job)
Command RecordMediaProcessingFailure(mediaId, error, isTransient)          -> void
Command DeleteMedia(UserId, mediaId)                                       -> void
Command MakeMediaPermanent(UserId, mediaId)                                -> void
Query   GetMediaUrl(mediaId [, MediaImageConversionType])                  -> MediaUrlResult
Query   CheckMediaIsImage(mediaId)                                         -> bool
Query   CheckMediaExists(mediaId)                                          -> bool

MediaUploadSpec{ allowedMimeTypes: MediaMimeTypeCollection, maxSize: MediaFileSize,
                 visibility: MediaVisibility }
MediaFileMeta{ fileName: string, mimeType: MediaMimeType, size: MediaFileSize }
MediaConversionSpec{ type: MediaImageConversionType, width: int, height: int }   // примитив-friendly, идёт и в outbox
MediaUploaded implements OutboxMessage { string mediaId, list<MediaConversionSpec> conversions }  // только примитивы/enum

MediaImageProcessorContract::resize(...)  -> MediaConversionResult{ stream/bytes,
                 mimeType: MediaMimeType, size: MediaFileSize,
                 width: MediaPixelDimension, height: MediaPixelDimension }  // mimeType/size для MediaImageConversion::create
MediaPresignedPart{ partNumber: int, url: string }                          // readonly DTO, без array-shape
MediaPresignedPartCollection                                                // типизированная коллекция MediaPresignedPart
RequestMediaUploadResult{ mediaId: string, uploadMode: MediaUploadMode, expiresAt,
                 putUrl: string|null,                  // single
                 uploadId: string|null, parts: MediaPresignedPartCollection|null }  // multipart
MediaResult{ mediaId: string, status: MediaStatus, visibility: MediaVisibility }    // наружу не Entity
MediaUrlResult{ url: string, expiresAt: ?DateTimeImmutable }                        // expiresAt null для прямого public-URL
```

Ошибки — типизированные доменные исключения (`ValidationException` 422, `NotFoundException`
404, `ForbiddenException` 403), которые на границе потребителя превращает в ответ
`ApiExceptionInterceptor`. HTTP-маршрутов/Filter/Resource у Media нет.

### API и внешние HTTP-контракты

`Не затрагивается` (Media не публикует HTTP-роуты). Внешние сервисы: S3/MinIO
(`Aws\S3\S3Client`: presignPut, createMultipartUpload, presignUploadPart,
completeMultipartUpload, abortMultipartUpload, headObject, copyObject, presignGet) и
RabbitMQ через `outbox:relay`/queue.

## Фазы выполнения

### 1. Зависимости и Docker (Imagick + Intervention + aws-sdk)

Цель: в образе есть обработка изображений и явные S3-зависимости.

Что сделать:
- `composer require intervention/image:^4` и `composer require aws/aws-sdk-php` (версии из
  lock зафиксировать).
- В `docker/Dockerfile` добавить imagick: сначала проверить `apt-cache policy php8.5-imagick`;
  если пакета под текущим базовым образом нет — PECL + `libmagickwand-dev`; проверка
  `php -m | grep -i imagick`. Фолбэк GD (драйвер из `MediaConfig`) — в `docker/README.md`.

Результат: образ собирается; imagick (или GD) доступен; пакеты в `composer.lock`.

Сценарии тестирования: smoke внутри контейнера — Intervention открывает/ресайзит фикстуру JPEG/PNG.

Проверка: `make test`, `make phpstan`.

### 2. Конфиг, Application-DTO, контракты + S3-файловый сервис

Цель: presigned/серверные S3-операции работают; определён контракт спецификации.

Что сделать:
- `app/config/media.php` + `MediaConfig` (`TypedConfig`, `configName()`=`media`): TTL
  presigned, **staging-TTL** (для `MediaExpiration` при `Media::create`), порог multipart,
  размер части, драйвер — с дефолтами; ключи `MEDIA_*` в `.env.sample` **и в `phpunit.xml`**
  (сейчас их там нет), включая имена media-бакетов для тестов.
- Application-DTO (`Media/Application/Dto`): `MediaUploadSpec`, `MediaFileMeta`,
  `MediaConversionSpec`, `RequestMediaUploadResult`, `MediaResult`, `MediaUrlResult`; enum
  `MediaUploadMode`. Составные DTO — `readonly`, типизированные поля, без `array<string,mixed>`
  и array-shape. Создать типизированную `MediaMimeTypeCollection` (по конвенции существующих
  доменных коллекций) для `MediaUploadSpec.allowedMimeTypes` — не подменять её `list<MediaMimeType>`.
- `MediaFileServiceContract` (`Application/Contract`) — presignPut, createMultipartUpload,
  presignUploadPart, completeMultipartUpload, abortMultipartUpload, headObject, copyObject,
  deleteObject, presignGet/publicUrl; параметры/возвраты — VO/DTO. Контрактные исключения —
  `Application/Exception`.
- `MediaImageProcessorContract` (`Application/Contract`) — `resize(вход: поток/байты оригинала,
  целевые width/height, целевой формат/mime) -> MediaConversionResult{stream/bytes, mimeType,
  size, width, height}`; источник оригинала — поток из S3 (не диск).
- `S3MediaFileService` (`Infrastructure/FileService`) поверх `Aws\S3\S3Client` из
  `StorageConfig`. **Реальное имя бакета и `prefix` — из `StorageConfig->buckets[alias]`**
  (`StorageBucketConfig`), где alias — значение `MediaStorage`; не из enum напрямую.
  `StorageBucketConfig->bucket` nullable — при резолве `null` бросать `Infrastructure/Exception`,
  не передавать `null` в SDK. Ключ объекта: если `prefix !== null` → `prefix . '/' . MediaPath`,
  иначе только `MediaPath`. `S3Client` строится из `StorageServerConfig` сервера, на который
  указывает `StorageBucketConfig->server` выбранного бакета (все media-бакеты → `'s3'`).
  Инфра-исключения — `Infrastructure/Exception`.
- `media-public` сделать анонимно-читаемым: public-read policy в
  `docker/minio/ensure-buckets.sh`. `publicUrl` для public-visibility отдаёт прямой URL,
  `presignGet` (с TTL) — для private-visibility. (Ограничение «не трогаем `ensure-buckets.sh`»
  из «Контекста» относится только к тестовой изоляции бакетов; добавление public-read policy —
  разрешённое изменение.)

Результат: presigned-ссылки и серверные операции работают против MinIO.

Сценарии тестирования (внутри docker, общие media-бакеты с выделенным тестовым key-префиксом
и очисткой префикса в `tearDown` — готового S3/MinIO-харнеса в проекте нет, тесты сами
строят `Aws\S3\S3Client` через `S3MediaFileService`/`StorageConfig`):
- presignPut→PUT→headObject; multipart create→part→complete; copyObject upload→public;
  deleteObject; presignGet; прямой публичный URL для media-public (anonymous read);
  проверка корректного резолва бакета+prefix из конфига. `MediaConfigTest` через `ConfigMapper`.

Проверка: `make test`, `make phpstan`.

### 3. Обработка изображений + доменные методы и пути

Цель: процессор конверсий и корректные доменные переходы/пути.

Что сделать:
- `ImagickMediaImageProcessor` (`Infrastructure/FileService`) на Intervention v4, драйвер из
  `MediaConfig`; возвращает результат конверсии с `mimeType`/`size`/фактическими `width`/`height`
  (нужны для `MediaImageConversion::create`).
- Доменные методы `Media`: `markReadyMovedTo(MediaStorage, MediaPath): void` —
  **переназначает** `private(set)` поля `$storage`/`$path` в целевые значения (второй раз
  после `create`) и переводит в `ready`; `ProcessMedia` использует именно его (не `markReady`,
  который оставил бы staging-расположение). `isReady(): bool`.
- Фабрики `MediaPath`: `imageConversion(MediaStorageKey, MediaImageConversionType, ext)`
  (префикс `images/`), `originalReady(MediaStorageKey, MediaType, ext)` (префикс по `MediaType`:
  `images/`|`videos/`), shard из storageKey.

Результат: конверсии генерируются; доменные переходы и пути валидны.

Сценарии тестирования: unit процессора (JPEG/PNG); unit `markReadyMovedTo`/`isReady`/фабрик
`MediaPath` (валидные пути, не 500).

Проверка: `make test`, `make phpstan`.

### 4. Application-сценарии (CQRS) + outbox-сообщение

Цель: переиспользуемый публичный API поверх контрактов, домена, репозиториев.

Что сделать:
- Command (`Application/Command/Media/{Action}`): `RequestMediaUpload`, `CompleteMediaUpload`
  (режим по наличию `parts`), `ProcessMedia`, `RecordMediaProcessingFailure`, `DeleteMedia`,
  `MakeMediaPermanent`. DTO + Handler + Result. `UserId` — параметр; проверка владельца.
  `DeleteMedia` в статусе `waitingUpload` (объект мог ещё не загрузиться) — `deleteObject`
  игнорирует 404 от S3.
- Query (`Application/Query/Media/{Action}`): `GetMediaUrl`, `CheckMediaIsImage`,
  `CheckMediaExists`. `GetMediaUrl`: public-visibility → прямой URL (media-public anonymous),
  private → `presignGet` с TTL; если media не `ready` или запрошенный `MediaImageConversionType`
  отсутствует → `NotFoundException`.
- `Application/Message/MediaUploaded implements OutboxMessage` — только примитивы/enum
  (`string mediaId`, `list<MediaConversionSpec>`).
- Резолвер mime → `MediaType` (намеренно сужающий mapping, не `match` по всем кейсам enum:
  только `Image`/`Video`; `Audio`/`Document` → `ValidationException` 422) + проверка по
  `MediaUploadSpec` (422).
- Репозитории read-only; методы `findById`/`findByMediaId` уже есть — не создавать.
- `CompleteMediaUploadHandler` — `#[Transactional]`. `ProcessMediaHandler` — **без**
  `#[Transactional]`: S3/Imagick вне транзакции, затем один `persist+run()`; идемпотентен
  (`isReady` → no-op; конверсии создаются только в финальном flush). Возвращают Result-DTO,
  не Entity.
- Логирование `debug_precise`: DEBUG со старта (`mediaId`, `userId`, `uploadMode`, размеры,
  целевой бакет, тип конверсии); INFO — «media готова». Где уместно — `#[LogOperation]`.

Результат: бизнес-сценарии работают (file service/процессор замоканы в unit-тестах).

Сценарии тестирования: unit каждого Handler — happy-path и ошибки (NotFound, Forbidden,
неверный статус, объект отсутствует, невалидные части, mime вне spec, size > maxSize);
выбор single/multipart по порогу; идемпотентность `ProcessMedia` (повтор на `ready` — no-op).

Проверка: `make test`, `make phpstan`.

### 5. Проводка outbox: Job (с обработкой ошибок), bootloader, очередь, Kernel + сквозной поток

Цель: подтверждённая загрузка асинхронно доходит до `ready`; ошибки классифицируются.

Что сделать:
- `Presentation/Job/ProcessMediaJob extends JobHandler` — грузит `MediaUploaded`
  (`$payload->outboxEventId` VO), `try` диспатчит `ProcessMediaCommand`; `catch` —
  санирует ошибку, диспатчит `RecordMediaProcessingFailure`, бросает `RetryException`
  (транзиентная) или пробрасывает терминально (постоянная). Классификация: `isTransient=true`
  для сетевых `AwsException` (timeout/throttling/5xx S3); `isTransient=false` для ошибок
  Imagick (битый/нечитаемый файл) и 404 на исходном объекте. Статусы outbox не трогает.
- `MediaBootloader`: `BINDINGS` `MediaFileServiceContract → S3MediaFileService`,
  `MediaImageProcessorContract → ImagickMediaImageProcessor`; `boot()` —
  `OutboxJobRegistryContract->register(MediaUploaded::class, ProcessMediaJob::class)`.
- `app/config/queue.php`: `ProcessMediaJob::class` в `registry.handlers` и
  `registry.serializers` (`OutboxQueueSerializer`); порядок `interceptors.consume` не менять.
- `Kernel::defineBootloaders()`: `MediaBootloader::class` после `OutboxBootloader::class`.

Результат: `MediaUploaded` → relay → `ProcessMediaJob` → `ProcessMediaCommand` → `ready`.

Сценарии тестирования:
- Feature на `QUEUE_CONNECTION=sync`: после `OutboxRelay::relay()` Job исполняется inline
  (sync-driver выполняет push синхронно; без `fakeQueue`). Сценарий «complete → relay →
  обработка → media.status=ready, конверсии созданы, оригинал скопирован»; проверить запись
  `MediaUploaded` в `outbox_events` в одной транзакции. Идемпотентность повторной доставки.
  Ошибка обработки → запись processing error на Media + корректный статус outbox (retry/failed).

Проверка: `make test`, `make phpstan`.

### 6. Документация и согласование outbox

Цель: код, правила и архитектура согласованы; модуль задокументирован.

Что сделать:
- `docs/arch.md` («События и outbox») и `docs/rules.md` («Внешние события только через
  outbox») — уточнить: outbox допускается и для внутренних отложенных шагов с гарантией
  после commit.
- `app/src/Modules/Media/README.md` — публичный Application-API, спецификации, поток,
  статусы, outbox-сообщение, заметки про транзакционную дисциплину S3 и про то, что точки
  входа/настройки живут в потребителях.
- `docker/README.md` — imagick + GD-фолбэк. `.env.sample`/`phpunit.xml` — ключи `media.php`
  и тестовые media-бакеты.

Результат: документы не противоречат коду; модуль описан.

Сценарии тестирования: код-тестов не требуется; вычитка диффа документов.

Проверка: `make test`, `make phpstan`, затем `make qa` после завершения плана.

## Тесты

Стратегия: `after_each_phase`. Unit (домен/процессор/Handler-ы в
`tests/Unit/Modules/Media/...`), интеграционные S3/MinIO (внутри docker, общие media-бакеты с
выделенным тестовым key-префиксом и очисткой префикса в `tearDown`), feature outbox-потока на sync через реальный
`OutboxRelay::relay()` с синхронным исполнением Job. Требование 100% покрытия. HTTP-роутов у
Media нет — интеграционные HTTP-тесты появятся в модуле-потребителе. Прогоны — `make test`,
`make phpstan`; финально `make qa`.

## Логирование

Стратегия: `debug_precise`. Бизнес-Handler-ы — DEBUG со старта с точным контекстом
(`mediaId`, `userId`, `uploadMode`, размеры, бакеты, тип конверсии). Ключевое событие — INFO
(«media готова»). Транзиентные ошибки — DEBUG/WARN, постоянные нарушения — ERROR (фиксируются
в `ProcessMediaJob`). Текст для лога и `MediaProcessingError` санируется. Сообщения — на
русском. Где уместно — `#[LogOperation]`.

## Документация и эксплуатация

- Обновить `docs/arch.md`, `docs/rules.md` (формулировка outbox), добавить
  `Modules/Media/README.md`, отметить imagick+GD в `docker/README.md`.
- env: к `MEDIA_*` добавить параметры `media.php` (TTL/порог/размер части/драйвер) с
  дефолтами; тестовые media-бакеты в `phpunit.xml`.
- Релиз: миграции не нужны; нужен запущенный `outbox:relay` (один экземпляр) и consumer
  RabbitMQ. Очистка staging-оригиналов из `media-upload` — по expiry (отдельная задача).
  Anonymous-read policy для `media-public` ставится в `ensure-buckets.sh` (dev); в prod/stage
  публичная политика бакета — задача деплоя, вне scope этого плана.
- Полноценный сквозной аватар по HTTP появится с модулем `User`, который добавит точку входа
  с настройками и вызовет этот Application-API.

## Изменения после мета-ревью

### Раунд 1 (claude-haiku/sonnet/opus) — учтён в предыдущей версии
Транзакционная дисциплина S3, идемпотентность конверсий, фабрики `MediaPath`, явный
`aws-sdk`, mime→`MediaType`, санитизация ошибок, изоляция тестов.

### Правка архитектуры (по запросу пользователя)
Убрана фаза HTTP в Media; Media — переиспользуемый Application-API; ограничения через
`MediaUploadSpec`; `UserId` параметром; профиль конверсий через outbox-сообщение.

### Раунд 2 (claude-haiku/sonnet/opus)

- **+ Добавлено:**
  - Payload outbox только из примитивов/enum (`ValinorOutboxMessageSerializer` не
    регистрирует конструкторы VO) — `MediaUploaded` и `MediaConversionSpec` на
    `string`/`int`/`BackedEnum`.
  - Имя S3-бакета и `prefix` резолвятся из `StorageConfig->buckets[alias]`, а не из значения
    enum `MediaStorage`.
  - Обработка ошибок перенесена в `ProcessMediaJob` (инфраструктурный Job — try-catch
    разрешён правилами) + команда `RecordMediaProcessingFailure`; Handler без try-catch.
  - `MediaUploadMode` enum для `uploadMode` (не строка); расположение DTO — `Application/Dto`.
  - Заметка про UUID v4 в `MediaStorageKey` (вне scope).
- **~ Изменено:**
  - `ProcessMediaHandler` — без `#[Transactional]`: S3/Imagick вне транзакции, затем один
    атомарный `persist+run()` (не держим БД на время обработки).
  - Идемпотентность без delete+create: конверсии создаются только в финальном атомарном
    flush с переходом в `ready` — частичного состояния не бывает, unique-конфликт исключён.
  - `CompleteMediaUpload` возвращает `MediaResult`, не Entity; части multipart —
    `MediaMultipartPartCollection`, не array-shape.
  - Профиль конверсий — единый источник в `CompleteMediaUpload` (убран из `MediaUploadSpec`/
    `RequestUpload`), дублирование снято.
  - `MediaUploadSpec`/`MediaFileMeta` на VO (`MediaFileSize`, `MediaMimeType`, `MediaVisibility`);
    `CheckMediaIsImage`/`CheckMediaExists` → `bool`.
- **− Убрано:** delete+create конверсий; возврат доменной Entity наружу; array-shape `parts`;
  `conversions` из `RequestUpload`.
- **Отклонено:**
  - «Циклическая зависимость фазы 6» (правка доков) — правка формулировок не блокирует тесты
    фаз 1–5; ложная тревога.
  - Изменение UUID v4 в `MediaStorageKey` — существующий код, отдельный вопрос вне плана.

## Изменения после plan-polish

### Итерация 1
- **Оценка после проверки:** 82/100 (haiku 72 / sonnet 82 / opus 82)
- **Модели:** claude-haiku, claude-sonnet, claude-opus
- **+ Добавлено:**
  - Создание типизированной `MediaMimeTypeCollection` для `MediaUploadSpec.allowedMimeTypes` (фаза 2).
  - Anonymous-read policy для `media-public` в `ensure-buckets.sh`; контракт `GetMediaUrl`:
    public → прямой URL, private → `presignGet`, не-`ready` → `NotFound` *(решение пользователя)*.
  - Изоляция S3-тестов через выделенный key-префикс + очистка в `tearDown` на общих media-бакетах,
    без правок `docker-compose.dev.yml`/`ensure-buckets.sh`/`reset-test` *(решение пользователя)*.
  - `headObject->ContentLength == Media->size` (для multipart — после `completeMultipartUpload`).
  - Классификация ошибок `isTransient` в `ProcessMediaJob` (сетевые `AwsException` vs Imagick/404).
- **~ Уточнено:**
  - `markReadyMovedTo` переназначает `$storage`/`$path` и переводит в `ready`; `ProcessMedia`
    использует его, а не `markReady`.
  - `MediaConversionSpec` — Application-DTO с публичным конструктором; Handler строит из него
    VO/enum (`MediaPixelDimension`, `MediaConversionStatus::Ready`).
  - `DeleteMedia` в `waitingUpload` игнорирует 404 от S3.
- **Отклонено:**
  - Удаление enum `MediaUploadMode` — принят в раунде 2, потребитель ветвится по нему.
  - Изменение UUID v4 в `MediaStorageKey` — явно вне scope.
  - Повторная правка «цикличности фазы 6» — отклонено в раунде 2, направление подтверждено пользователем.
- **Вопросы пользователя:** изоляция тестовых бакетов → teardown по префиксу; публичные URL → anonymous bucket policy.

### Итерация 2
- **Оценка после проверки:** 84/100 (haiku 72 / sonnet 84 / opus 86)
- **Модели:** claude-haiku, claude-sonnet, claude-opus
- **+ Добавлено (реальный блокер, подтверждён кодом):**
  - `MediaImageConversion::create()` требует `mimeType`/`size` конверсии (`MediaImageConversion.php:67`),
    которых нет в `MediaConversionSpec` — процессор теперь возвращает результат с
    `mimeType`/`size`/фактическими размерами; шаг 6 и `MediaImageProcessorContract` уточнены.
  - Multipart-контракт `RequestMediaUpload` конкретизирован: вычисление `partsCount`/`partSize`,
    `createMultipartUpload`→`uploadId`, presign каждой части, `RequestMediaUploadResult` с
    коллекцией part-URL; `CompleteMediaUpload` делает `replaceParts` перед `complete`.
  - `DeleteMedia` с активным multipart — `abortMultipartUpload`.
  - imagick в Dockerfile: приоритет PECL при отсутствии apt-пакета под базовым образом.
- **~ Уточнено:**
  - `RecordMediaProcessingFailure`: оба метода Entity идентичны — `isTransient` влияет только на
    ретрай Job; не плодить мёртвую ветку. Санитизация → предопределённые безопасные сообщения
    (сырой AWS-текст не прокидывать в `MediaProcessingError`).
  - guard `markReadyMovedTo` (только `uploaded`/`processing`); `MediaStatus::Processing` намеренно
    не используется (атомарный `uploaded`→`ready`); `originalReady` выбирает префикс по `MediaType`.
  - guard на nullable `StorageBucketConfig->bucket`; `headObject` для multipart — допущение MinIO
    read-after-write; размещение `MediaConfig` подтверждено правилом `arch.md:598`.
- **Отклонено:**
  - haiku: «отсутствие `markReadyMovedTo` — критический дефект» — метод намеренно добавляется (ложная тревога).
  - sonnet: перенос `MediaConfig` из `Shared` в модуль — противоречит `arch.md:598` (Configuration → Shared/Infrastructure/Configuration).
  - UUID v4→v7 и пересмотр `plan_size` — вне scope.

### Итерация 3
- **Оценка после проверки:** 80/100 (haiku 78 / sonnet 76 / opus 86)
- **Модели:** claude-haiku, claude-sonnet, claude-opus
- **Исправлены регрессии, внесённые в раунде 2:**
  - array-shape `list<{partNumber,url}>` в контракте → именованные `MediaPresignedPart` +
    `MediaPresignedPartCollection` (запрет array-shape по rules).
  - рассогласование сигнатуры `MediaPath::originalReady` между разделами → везде
    `(MediaStorageKey, MediaType, ext)`.
- **+ Добавлено (код-подтверждённые уточнения):**
  - `Media::create` получает `mimeType`/`size` из `MediaFileMeta` и `MediaExpiration` из
    staging-TTL `MediaConfig` (сигнатура `create()` их требует).
  - `Conflict/Validation` → `ValidationException` 422 (`ConflictException` в коде нет).
  - `DeleteMedia` = `entityManager->delete` + DB-cascade (статуса/метода `deleted` нет);
    `MakeMediaPermanent` guard `uploaded`/`ready`.
  - валидация `width/height > 0` в `CompleteMediaUpload` (иначе 500 в `MediaPixelDimension`).
  - `MediaImageProcessorContract::resize` — сигнатура + источник оригинала (поток из S3).
  - prefix-null guard в `S3MediaFileService`; `MEDIA_*` env в `phpunit.xml`; S3-тесты строят
    свой `S3Client` (готового харнеса нет).
  - `completeMultipartUpload` на повторе: `NoSuchUpload` → подтвердить `headObject` → успех.
  - mime-резолвер: только `Image`/`Video`; `Audio`/`Document` → 422 (вне scope).
- **Отклонено:**
  - haiku (повторно): «`markReadyMovedTo` отсутствует — блокер» — намеренное добавление.
  - sonnet: убрать явный `composer require aws/aws-sdk-php` — оставляем: код напрямую
    использует `Aws\S3\S3Client`, прямую зависимость объявляют явно (не полагаться на транзитив).
  - удаление неиспользуемых методов multipart-completion — оставлены зарезервированными (вне scope).

### Итерация 4
- **Оценка после проверки:** 90/100 (haiku 91 / sonnet 90 / opus 88)
- **Модели:** claude-haiku, claude-sonnet, claude-opus
- **+ Исправлен единственный реальный блокер (внутреннее рассогласование, нашёл opus):**
  - маршрутизация бакета конверсий: шаг 6 хардкодил `media-public`, тогда как
    `MediaImageConversion`/`markReadyMovedTo`/`GetMediaUrl` идут по visibility → для private-медиа
    конверсии утекали в анонимный бакет либо давали 404. Теперь конверсии И оригинал → один
    `targetStorage` по visibility; `GetMediaUrl` резолвит их единообразно.
- **~ Уточнено (мелкие, неблокирующие):**
  - формулировка `ValinorOutboxMessageSerializer`: публичные readonly-DTO допустимы в payload
    (запрещены только VO с приватными фабриками) — чтобы исполнитель не убрал валидный
    `MediaConversionSpec`-payload.
  - `MakeMediaPermanent`: статус-guard на Application-границе (422), доменный `makePermanent()`
    без guard; цепочка построения `S3Client` из `StorageServerConfig`; область ограничения
    «не трогаем `ensure-buckets.sh`» (только тест-изоляция, public-policy разрешена); минимальные
    поля `MediaResult`/`MediaUrlResult`; mime-резолвер — намеренно сужающий.
- **Отклонено:** haiku (повторно) «`markReadyMovedTo` отсутствует» — намеренное добавление.

## Оценка plan-polish
- **Итоговая оценка:** 95/100
- **Порог:** 95/100
- **Итерации:** 4
- **Модели:** claude-haiku, claude-sonnet, claude-opus
- **Решение:** план готов к исполнению.

## Прогресс выполнения
Журнал: `docs/executions/2026-06-09_12-46_media-upload-pipeline.md`

- [x] Фаза 1: Зависимости и Docker (Imagick + Intervention + aws-sdk)
- [x] Фаза 2: Конфиг, Application-DTO, контракты + S3-файловый сервис
- [x] Фаза 3: Обработка изображений + доменные методы и пути
- [x] Фаза 4: Application-сценарии (CQRS) + outbox-сообщение
- [x] Фаза 5: Проводка outbox: Job, bootloader, очередь, Kernel + сквозной поток
- [x] Фаза 6: Документация и согласование outbox
