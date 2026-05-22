---
title: Сущности, Value Object и миграции медиа-домена
date: 2026-05-21 17:59
mode: normal
plan_size: normal
decision_mode: recommend_and_ask
status: meta-reviewed
reviewer: none
meta_reviewers:
  - gpt-5.4-mini
  - gpt-5.3-codex
  - gpt-5.5
test_strategy: after_each_phase
logging_strategy: debug_precise
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  research: docs/researches/2026-05-20_18-42_media-domain.md
---

# План реализации

## Задача

Создать основу медиа-домена на уровне данных: Value Object, enum, Cycle Entity, репозитории, типизированные коллекции, поддержку typecast для Value Object, миграции базы и конфиг storage aliases для `MediaStorage`.

Готовый результат: приложение знает доменные сущности `Media`, `MediaImageConversion`, `MediaVideoConversion` и `MediaMultipartUpload`, база имеет таблицы под эти сущности, Value Object валидируют доменные значения, Cycle ORM может сохранять и восстанавливать сущности, а проверки `make test` и `make phpstan` проходят.

В этот план не входят HTTP API, выдача подписанных ссылок, storage-клиенты, фоновые обработчики, события, WebSocket, очистка файлов и таблицы связи с внешними доменами. Конфиг storage aliases входит, потому что `MediaStorage` хранится в Entity и должен ссылаться на существующее логическое хранилище.

## Контекст

Проект пока почти не содержит доменной модели: в `app/src/Domain` есть только исключения, а в `app/database/migrations` есть только `.gitignore`. Поэтому вместе с медиа-сущностями нужно добавить небольшую базовую инфраструктуру, которую уже требуют правила проекта и примеры кода: UUID v7 Value Object, timestamps, `Castable`, `ValueObjectCast`, доменные репозитории и типизированные коллекции.

Правила проекта требуют:

- все идентификаторы сущностей делать UUID v7;
- Entity создавать через `create()`, а не через конструктор;
- Entity не должны хранить и принимать доменные `string`, `int`, `float`, `bool`, `array`;
- одиночные доменные значения хранить как Value Object или enum;
- наборы сущностей возвращать через именованные типизированные коллекции;
- доступ к базе выполнять через репозитории;
- тесты приложения запускать через `make test`;
- PHPStan приложения запускать через `make phpstan`.

Исследование медиа-домена уже зафиксировало четыре сущности:

- `Media` - основной медиа-объект;
- `MediaImageConversion` - готовые производные картинки;
- `MediaVideoConversion` - готовые производные видео;
- `MediaMultipartUpload` - временная запись multipart upload.

Пользователь подтвердил, что для этого плана нужно взять схему из research без урезания.

Новые пакеты не нужны. `ramsey/uuid` уже есть в `composer.lock` как транзитивная зависимость Cycle ORM и Spiral, поэтому для UUID v7 используется существующий пакет.

Текущий `app/config/storage.php` уже описывает Spiral Storage, но пока содержит только общие bucket-и `default`, `s3` и `s3-test`. Для `MediaStorage` нужны отдельные стабильные storage aliases: `media-upload`, `media-private`, `media-public`. Alias не должен означать S3: он может указывать на S3, local storage или другой сервер Spiral Storage.

## Принятые решения

- Размер плана: `normal`. Источник: ответ пользователя `1 - 1`.
- Схема базы берётся из research: `Media`, `MediaImageConversion`, `MediaVideoConversion`, `MediaMultipartUpload`. Источник: ответ пользователя `2 - 1`.
- Таблицы связи с внешними доменами, например `PostMedia` и `ProfileMedia`, не входят в этот план. Источник: пользователь попросил спланировать только сущности, Value Object, миграции и связанное с этим; вариант с расширением связями не выбран.
- HTTP API, storage-операции, очереди, workers, события и WebSocket не входят в этот план. Источник: research прямо относит эти детали к следующим этапам.
- `uploaded_by_id` хранится как UUID без внешнего ключа, а в Entity представлен Value Object `UserId`. Причина: это именно идентификатор пользователя, но в текущем коде нет `User` entity и таблицы `users`; добавлять пользовательский домен и внешний ключ в план медиа-сущностей нельзя.
- `Media.storageKey` хранится как UUID v4 в колонке `storage_key` и имеет уникальный индекс. Источник: research; это значение скрывает время создания файла в storage path.
- Значения `storage` ограничиваются enum `MediaStorage` со значениями `media-upload`, `media-private`, `media-public`. Значение enum одновременно является логическим storage alias в Spiral Storage, но не задаёт конкретный backend. Источник: research зафиксировал ровно эти зоны хранения.
- `Media.status`, `Media.type`, `Media.visibility`, типы конверсий и статусы конверсий хранятся как string-backed enum. Это закрытые наборы значений, поэтому правила проекта требуют enum вместо строк.
- `expires_at` и `processing_error` остаются nullable в базе, но в Entity представлены не nullable Value Object: `MediaExpiration` и `MediaProcessingError`. Так база сохраняет схему из research, а доменная модель не отдаёт наружу `null`.
- Multipart `parts` хранится в JSON-колонке, но Entity работает с `MediaMultipartPartCollection`, а не с массивом. Это сохраняет решение research и правило проекта про типизированные коллекции.
- Для видео поле `duration` из research хранится как `duration_ms` в миллисекундах. Причина: базе нужна точная единица измерения, а integer в миллисекундах не создаёт ошибок округления.
- Для конверсий вводится уникальность `(media_id, type)`. Один медиа-объект имеет не больше одной готовой конверсии каждого типа.
- `MediaMultipartUpload.media_id` уникален. Один медиа-объект имеет не больше одной активной multipart-загрузки.
- Общий `HasUuid` с одним типом `id` не создаётся. У каждой Entity свой id Value Object: `MediaId`, `MediaImageConversionId`, `MediaVideoConversionId`, `MediaMultipartUploadId`. Каждый id-класс сам генерирует UUID v7 через `::generate()`, а Entity явно объявляет своё id-свойство.
- Для повторяющейся логики UUID v7 создаётся общий абстрактный базовый класс `AbstractUuidV7Id` внутри Value Object-слоя, но он не заменяет конкретные id-классы в Entity и Repository.
- `ValueObjectCast` отвечает не только за Value Object, но и за запись string-backed enum обратно в базу. Встроенный Cycle `Typecast` читает `BackedEnum`, но не добавляет обратное преобразование enum в строку при записи.
- Для JSON-колонки `parts` используется отдельный JSON-cast внутри `ValueObjectCast`: база хранит JSON, Entity получает `MediaMultipartPartCollection`.
- Колонка `parts` остаётся типа `json`, а не `jsonb`. Причина: встроенный Cycle `Typecast` прямо поддерживает правило `json`; переход на `jsonb` не нужен для текущих запросов и добавит лишний риск typecast.
- В `app/config/storage.php` добавляются storage aliases `media-upload`, `media-private`, `media-public`; backend для каждого alias задаётся через env `MEDIA_UPLOAD_STORAGE_SERVER`, `MEDIA_PRIVATE_STORAGE_SERVER`, `MEDIA_PUBLIC_STORAGE_SERVER`.
- Физические bucket names для S3-compatible backend задаются через env `MEDIA_UPLOAD_STORAGE_BUCKET`, `MEDIA_PRIVATE_STORAGE_BUCKET`, `MEDIA_PUBLIC_STORAGE_BUCKET`. Для local backend эти значения не являются доменным контрактом и могут не использоваться storage adapter-ом.
- Отдельный `MediaStorageConfig` не создаётся. Причина: typed config для `storage` уже есть, а `MediaStorage` должен оставаться доменным enum без зависимости от инфраструктурного config-класса.
- Стратегия тестов: `after_each_phase`. Источник: `docs/settings.yaml`.
- Стратегия логирования: `debug_precise`. В рамках этого плана runtime-логов нет, поэтому точная диагностика реализуется через сообщения исключений Value Object и тесты без вывода секретов и персональных данных.

## Целевой алгоритм

1. Код приложения получает примитивные значения на внешней границе будущего сценария загрузки.
2. Handler будущего сценария создаёт Value Object и enum: тип файла, видимость, storage, path, MIME type, размер, срок жизни, идентификатор пользователя.
3. `Media::create()` создаёт новый медиа-объект, генерирует UUID v7 для `id`, принимает UUID v4 `storageKey`, выставляет статус `waitingUpload`, сохраняет путь к оригиналу и timestamps.
4. Для multipart-загрузки `MediaMultipartUpload::create()` создаёт временную запись с `uploadId`, количеством частей, размером части, общим размером файла и пустой типизированной коллекцией частей.
5. Когда клиент будущего сценария передаст список частей, доменный метод `MediaMultipartUpload` заменит пустую коллекцию на коллекцию `MediaMultipartPartCollection`, а `Media` перейдёт в `completingMultipartUpload`.
6. Когда обработка будущего сценария создаст производные файлы, домен создаст `MediaImageConversion` или `MediaVideoConversion` с типом, статусом, storage, path и техническими параметрами файла.
7. Repository сохраняет и читает сущности через Cycle ORM.
8. `ValueObjectCast` преобразует значения из базы в Value Object, enum и `MediaMultipartPartCollection` при гидрации и обратно в значения базы при записи.
9. Миграции создают таблицы в порядке зависимостей: сначала `media`, затем таблицы конверсий и multipart-загрузок.
10. Индексы обеспечивают быстрый поиск по статусу, сроку временной загрузки, владельцу, связи с `media` и уникальности технических ключей.
11. Ошибки доменных значений Value Object выбрасывают `InvalidDomainValueException` с коротким русским сообщением без имени файла пользователя, без storage path целиком и без ETag.
12. Конфигурация Storage содержит storage alias для каждого значения `MediaStorage`, чтобы будущий storage-сценарий мог открыть нужное хранилище по значению enum без ручного соответствия строк. Конкретный backend берётся из config, поэтому `media-upload` можно переключить с S3 на local без изменения доменной модели и базы.

## Контракты реализации

### Данные и БД

Создать миграцию `app/database/migrations/YYYYMMDD_NNNNNN_create_media_domain_tables.php`.

Таблица `media`:

```text
id                       uuid, not null, primary key
storage_key              uuid, not null
type                     string(32), not null
status                   string(64), not null
visibility               string(16), not null
storage                  string(64), not null
path                     string(1024), not null
mime_type                string(255), not null
size                     bigInteger, not null
uploaded_by_id           uuid, not null
expires_at               datetime, nullable
processing_attempts      integer, not null, default 0
processing_error         text, nullable
created_at               datetime, not null
updated_at               datetime, not null
```

Индексы и ограничения `media`:

- primary key: `id`;
- unique: `storage_key`;
- index: `uploaded_by_id`;
- index: `status`;
- index: `expires_at`.

Таблица `media_image_conversions`:

```text
id                       uuid, not null, primary key
media_id                 uuid, not null
type                     string(64), not null
status                   string(32), not null
storage                  string(64), not null
path                     string(1024), not null
mime_type                string(255), not null
size                     bigInteger, not null
width                    integer, not null
height                   integer, not null
created_at               datetime, not null
updated_at               datetime, not null
```

Индексы и ограничения `media_image_conversions`:

- primary key: `id`;
- foreign key: `media_id -> media.id`, `ON DELETE CASCADE`, `ON UPDATE CASCADE`;
- unique: `media_id, type`;
- index: `media_id`;
- index: `status`.

Таблица `media_video_conversions`:

```text
id                       uuid, not null, primary key
media_id                 uuid, not null
type                     string(64), not null
status                   string(32), not null
storage                  string(64), not null
path                     string(1024), not null
mime_type                string(255), not null
size                     bigInteger, not null
width                    integer, not null
height                   integer, not null
duration_ms              bigInteger, not null
bitrate                  integer, not null
created_at               datetime, not null
updated_at               datetime, not null
```

Индексы и ограничения `media_video_conversions`:

- primary key: `id`;
- foreign key: `media_id -> media.id`, `ON DELETE CASCADE`, `ON UPDATE CASCADE`;
- unique: `media_id, type`;
- index: `media_id`;
- index: `status`.

Таблица `media_multipart_uploads`:

```text
id                       uuid, not null, primary key
media_id                 uuid, not null
upload_id                string(1024), not null
parts_count              integer, not null
part_size                bigInteger, not null
file_size                bigInteger, not null
parts                    json, not null
created_at               datetime, not null
updated_at               datetime, not null
```

Индексы и ограничения `media_multipart_uploads`:

- primary key: `id`;
- foreign key: `media_id -> media.id`, `ON DELETE CASCADE`, `ON UPDATE CASCADE`;
- unique: `media_id`;
- index: `upload_id`.

Совместимость со старыми данными:

- старых таблиц медиа нет;
- backfill не нужен;
- rollback удаляет таблицы в обратном порядке: `media_multipart_uploads`, `media_video_conversions`, `media_image_conversions`, `media`.

Доменные enum:

- `MediaType`: `image`, `video`, `audio`, `document`;
- `MediaStatus`: `waitingUpload`, `completingMultipartUpload`, `multipartCompletionFailedCanRetry`, `multipartCompletionFailedNeedReupload`, `uploaded`, `processing`, `processingFailed`, `ready`, `readyOriginalRemoved`;
- `MediaVisibility`: `private`, `public`;
- `MediaStorage`: `media-upload`, `media-private`, `media-public`;
- `MediaImageConversionType`: `thumbnail`, `preview`, `large`, `poster`;
- `MediaVideoConversionType`: `normalizedMp4H264`;
- `MediaConversionStatus`: `processing`, `ready`, `processingFailed`.

Value Object:

- `MediaId`, `MediaImageConversionId`, `MediaVideoConversionId`, `MediaMultipartUploadId` - UUID v7 идентификаторы сущностей;
- `MediaStorageKey` - UUID v4 для storage path;
- `UserId` - UUID пользователя без связи с `users`;
- `MediaPath` - backend-controlled path формата `{kind}/{shard}/{storageKey}/{file}`;
- `MediaMimeType` - MIME type;
- `MediaFileSize` - размер файла в байтах;
- `MediaPixelDimension` - ширина или высота в пикселях;
- `MediaDuration` - длительность видео в миллисекундах;
- `MediaBitrate` - bitrate видео в битах в секунду;
- `MediaProcessingAttempts` - количество попыток обработки;
- `MediaProcessingError` - отсутствие ошибки или короткое сообщение последней ошибки;
- `MediaExpiration` - временная загрузка с датой удаления или постоянная загрузка без даты удаления;
- `MediaMultipartUploadIdValue` - `uploadId` из S3/Yandex;
- `MediaMultipartPartsCount` - ожидаемое количество частей;
- `MediaMultipartPartSize` - размер одной части;
- `MediaMultipartPartNumber` - номер части multipart upload;
- `MediaMultipartPartETag` - ETag части multipart upload;
- `MediaMultipartPart` - пара `partNumber + ETag`;
- `MediaMultipartPartCollection` - типизированная коллекция `MediaMultipartPart`.

Ограничения Value Object:

- `MediaPath`: строка до 1024 символов, формат `{kind}/{shard}/{storageKey}/{file}`, `kind` равен `uploads`, `images` или `videos`, `shard` совпадает с первыми двумя символами `storageKey`.
- `MediaMimeType`: строка от 1 до 255 символов.
- `MediaFileSize`: от 1 байта до 5 TB.
- `MediaPixelDimension`: от 1 до 100000 пикселей.
- `MediaDuration`: от 1 до 604800000 миллисекунд.
- `MediaBitrate`: от 1 до 1000000000 бит в секунду.
- `MediaProcessingAttempts`: от 0 до 100.
- `MediaProcessingError`: пустое состояние или строка от 1 до 2000 символов без полного storage path и без ETag.
- `MediaMultipartUploadIdValue`: строка от 1 до 1024 символов.
- `MediaMultipartPartsCount`: от 1 до 10000.
- `MediaMultipartPartSize`: от 5242880 байт до 5 TB.
- `MediaMultipartPartNumber`: от 1 до 10000.
- `MediaMultipartPartETag`: строка от 1 до 512 символов.

JSON-формат `parts`:

```json
[
  {
    "partNumber": 1,
    "eTag": "etag-value"
  }
]
```

В публичных PHP-контрактах этот JSON не представляется как array shape. Для одной части используется `MediaMultipartPart`, для списка используется `MediaMultipartPartCollection`.

Сущности:

- `App\Domain\Entity\Media`;
- `App\Domain\Entity\MediaImageConversion`;
- `App\Domain\Entity\MediaVideoConversion`;
- `App\Domain\Entity\MediaMultipartUpload`.

Репозитории:

- `App\Repository\MediaRepository`;
- `App\Repository\MediaImageConversionRepository`;
- `App\Repository\MediaVideoConversionRepository`;
- `App\Repository\MediaMultipartUploadRepository`.

Типизированные коллекции:

- `App\Domain\Collection\MediaCollection`;
- `App\Domain\Collection\MediaImageConversionCollection`;
- `App\Domain\Collection\MediaVideoConversionCollection`;
- `App\Domain\Collection\MediaMultipartPartCollection`.

Инфраструктура Cycle:

- `App\Infrastructure\Cycle\Castable`;
- `App\Infrastructure\Cycle\JsonCastable`;
- `App\Infrastructure\Cycle\ValueObjectCast`;
- `App\Domain\Trait\HasTimestamps`;

Контракт cast:

- `Castable` задаёт восстановление Value Object из значения базы через `fromDatabase()` и получение значения для записи через `toDatabase()`.
- `JsonCastable` задаёт восстановление объекта из JSON базы и получение JSON для записи.
- `ValueObjectCast` поддерживает `Castable`, `JsonCastable` и `BackedEnum`.
- Для `BackedEnum` при чтении используется `Enum::from()`, при записи используется `Enum->value`.
- Для `MediaExpiration` значение `null` из базы превращается в постоянное состояние, а постоянное состояние при записи превращается в `null`.
- Для `MediaProcessingError` значение `null` из базы превращается в пустое состояние, а пустое состояние при записи превращается в `null`.

### API и внешние контракты

Не затрагивается.

HTTP routes, request/response DTO, OpenAPI, очереди, events, WebSocket, публичные ссылки и private content endpoints не создаются.

### Конфигурация

Изменить `app/config/storage.php`:

```text
buckets.media-upload:
  server: env MEDIA_UPLOAD_STORAGE_SERVER, default s3
  bucket: env MEDIA_UPLOAD_STORAGE_BUCKET, default media-upload
  prefix: env MEDIA_UPLOAD_STORAGE_PREFIX, default null
  visibility: private

buckets.media-private:
  server: env MEDIA_PRIVATE_STORAGE_SERVER, default s3
  bucket: env MEDIA_PRIVATE_STORAGE_BUCKET, default media-private
  prefix: env MEDIA_PRIVATE_STORAGE_PREFIX, default null
  visibility: private

buckets.media-public:
  server: env MEDIA_PUBLIC_STORAGE_SERVER, default s3
  bucket: env MEDIA_PUBLIC_STORAGE_BUCKET, default media-public
  prefix: env MEDIA_PUBLIC_STORAGE_PREFIX, default null
  visibility: public
```

Изменить env-документацию:

```text
MEDIA_UPLOAD_STORAGE_SERVER=s3
MEDIA_UPLOAD_STORAGE_BUCKET=media-upload
MEDIA_UPLOAD_STORAGE_PREFIX=

MEDIA_PRIVATE_STORAGE_SERVER=s3
MEDIA_PRIVATE_STORAGE_BUCKET=media-private
MEDIA_PRIVATE_STORAGE_PREFIX=

MEDIA_PUBLIC_STORAGE_SERVER=s3
MEDIA_PUBLIC_STORAGE_BUCKET=media-public
MEDIA_PUBLIC_STORAGE_PREFIX=
```

Если позже нужно хранить upload на локальном диске, меняется только config:

```text
MEDIA_UPLOAD_STORAGE_SERVER=local
MEDIA_UPLOAD_STORAGE_PREFIX=media-upload
```

`Media.storage = media-upload` и схема БД при этом не меняются.

Изменить Docker bootstrap MinIO:

- добавить `media-upload`, `media-private`, `media-public` в список bucket-ов, которые создаёт `minio-init` для S3/MinIO-конфигурации по умолчанию;
- не менять правило `reset-test`: оно по-прежнему очищает только `yoga-loka-test`, потому что этот план не добавляет запись файлов в media bucket-и.

Typed config:

- существующий `App\Infrastructure\Configuration\Storage\StorageConfig` уже поддерживает `buckets`;
- новый typed config не нужен;
- добавить тест, что `StorageConfig->buckets` содержит ключи для всех значений `MediaStorage`;
- добавить тест, что `media-upload` можно настроить на `server: local` без изменения `MediaStorage`.

## Фазы выполнения

### 1. Базовая поддержка доменных типов для Cycle ORM

Цель: подготовить минимальную инфраструктуру, чтобы Entity могли хранить Value Object без сырых примитивов.

Что сделать:

- Создать `App\Infrastructure\Cycle\Castable` с методами восстановления Value Object из значения базы и получения значения для записи в базу.
- Создать `App\Infrastructure\Cycle\JsonCastable` для объектов, которые сохраняются в JSON-колонки.
- Создать `App\Infrastructure\Cycle\ValueObjectCast`, который используется в `#[Entity(typecast: [Typecast::class, ValueObjectCast::class])]`.
- Поддержать в `ValueObjectCast` значения из string, int, datetime, nullable datetime, json-колонок и string-backed enum, которые нужны медиа-домену.
- Создать `App\Domain\Trait\HasTimestamps`, который хранит `createdAt` и `updatedAt`, инициализирует их при создании и обновляет `updatedAt` через `touch()`.
- Зафиксировать правило: id-свойство объявляется в каждой Entity конкретным id-классом, а не в общем trait.
- Проверить, что новая инфраструктура не зависит от HTTP, Application Handler, storage, queue и config.

Результат: в проекте есть общая поддержка Entity с Value Object, enum, JSON-cast и timestamps.

Сценарии тестирования:

- `ValueObjectCast` восстанавливает тестовый Value Object из значения базы.
- `ValueObjectCast` отдаёт сырое значение для записи в базу.
- `ValueObjectCast` сохраняет string-backed enum в базу как строку и восстанавливает enum из строки.
- `ValueObjectCast` сохраняет JSON-объект в базу как JSON и восстанавливает объект из JSON.
- `HasTimestamps` выставляет `createdAt` и `updatedAt`, а `touch()` меняет только `updatedAt`.

Проверка:

- Добавить unit-тесты для `ValueObjectCast`, `JsonCastable`, enum-cast и `HasTimestamps`.
- Запустить `make test`.
- Запустить `make phpstan`.

### 2. Enum, Value Object и storage config медиа-домена

Цель: вынести все одиночные доменные значения медиа-домена из примитивов в проверяемые типы и связать `MediaStorage` с реальными storage aliases.

Что сделать:

- Создать enum из раздела `Данные и БД`.
- Создать Value Object из раздела `Данные и БД`.
- Добавить в `app/config/storage.php` storage aliases для всех значений `MediaStorage`.
- Добавить generic env-переменные `MEDIA_UPLOAD_STORAGE_SERVER`, `MEDIA_UPLOAD_STORAGE_BUCKET`, `MEDIA_UPLOAD_STORAGE_PREFIX`, `MEDIA_PRIVATE_STORAGE_SERVER`, `MEDIA_PRIVATE_STORAGE_BUCKET`, `MEDIA_PRIVATE_STORAGE_PREFIX`, `MEDIA_PUBLIC_STORAGE_SERVER`, `MEDIA_PUBLIC_STORAGE_BUCKET`, `MEDIA_PUBLIC_STORAGE_PREFIX` в `.env.sample`.
- Обновить Docker bootstrap MinIO, чтобы dev-стенд создавал media bucket-и.
- В `MediaPath` проверить формат `{kind}/{shard}/{storageKey}/{file}` и совпадение `shard` с первыми двумя символами `storageKey`.
- Для начального оригинала использовать path `uploads/{shard}/{storageKey}/source.{extension}`, где extension берётся из разрешённого backend-ом MIME type в будущих upload-сценариях.
- В `MediaStorageKey` генерировать UUID v4 и проверять UUID v4 при создании из строки.
- В entity id Value Object генерировать UUID v7 и проверять UUID v7 при создании из строки.
- В `MediaMimeType` проверять непустое значение и максимальную длину 255.
- В `MediaFileSize`, `MediaPixelDimension`, `MediaDuration`, `MediaBitrate`, `MediaProcessingAttempts`, `MediaMultipartPartsCount`, `MediaMultipartPartSize`, `MediaMultipartPartNumber` проверять числовые границы из контракта.
- В `MediaProcessingError` ограничить длину сообщения и хранить только короткий текст без ETag, полного path и пользовательского имени файла.
- В `MediaExpiration` дать два явных состояния: временная загрузка с датой удаления и постоянная загрузка без даты удаления.
- В `MediaMultipartPartCollection` запретить дубли `partNumber` и хранить части в порядке возрастания номера.

Результат: все доменные значения, которые попадут в Entity, представлены enum или Value Object и валидируются в одном месте.

Сценарии тестирования:

- Каждый Value Object создаётся из валидного значения.
- Каждый Value Object выбрасывает `InvalidDomainValueException` на невалидном значении.
- `fromDatabase()` восстанавливает Value Object без пользовательской валидации, но сохраняет тип.
- `MediaMultipartPartCollection` отклоняет повторяющийся номер части.
- `MediaMultipartPartCollection` сериализуется в JSON-формат из контракта и восстанавливается из него.
- Enum содержат ровно значения, перечисленные в контракте БД.
- `StorageConfig->buckets` содержит storage alias для каждого `MediaStorage`.
- `media-upload` и `media-private` имеют `visibility: private`, `media-public` имеет `visibility: public`.
- Тестовая конфигурация может поставить `media-upload.server = local`, и `MediaStorage::Upload` остаётся тем же значением.

Проверка:

- Добавить unit-тесты для всех enum и Value Object.
- Добавить unit-тест на маппинг `StorageConfig` и соответствие `MediaStorage` storage aliases.
- Запустить `make test`.
- Запустить `make phpstan`.

### 3. Entity, связи, коллекции и репозитории

Цель: описать доменную модель медиа-домена для Cycle ORM и закрыть доступ к данным через репозитории.

Что сделать:

- Создать `Media` с `#[Entity(role: 'media', table: 'media', repository: MediaRepository::class, typecast: [Typecast::class, ValueObjectCast::class])]`.
- Добавить в `Media` свойства из таблицы `media` через enum и Value Object.
- Добавить в `Media` `MediaImageConversionCollection` и `MediaVideoConversionCollection` как связи `HasMany` с `innerKey: 'id'`, `outerKey: 'media_id'`, `collection: MediaImageConversionCollection::class` и `collection: MediaVideoConversionCollection::class`, с сортировкой по `id`.
- Не добавлять nullable-связь `Media -> MediaMultipartUpload`; multipart-загрузка читается через `MediaMultipartUploadRepository`, чтобы Entity не хранила `null`.
- Создать доменные методы `Media`: переход к `completingMultipartUpload`, переход к `multipartCompletionFailedCanRetry`, переход к `multipartCompletionFailedNeedReupload`, переход к `uploaded`, переход к `processing`, фиксация временной ошибки обработки, фиксация постоянной ошибки обработки, переход к `ready`, переход к `readyOriginalRemoved`, перевод временной загрузки в постоянную.
- Создать `MediaImageConversion` с `BelongsTo` на `Media`, `innerKey: 'media_id'`, `outerKey: 'id'`, `fkOnDelete: CASCADE`.
- Создать `MediaVideoConversion` с `BelongsTo` на `Media`, `innerKey: 'media_id'`, `outerKey: 'id'`, `fkOnDelete: CASCADE`.
- Создать `MediaMultipartUpload` с `BelongsTo` на `Media`, `innerKey: 'media_id'`, `outerKey: 'id'`, `fkOnDelete: CASCADE`.
- Создать typed collection-классы для связей и multipart parts.
- Создать репозитории с доменными методами:
  - `MediaRepository::findById(MediaId $mediaId): ?Media`;
  - `MediaRepository::findByStorageKey(MediaStorageKey $storageKey): ?Media`;
  - `MediaRepository::findExpired(DateTimeImmutable $now): MediaCollection`;
  - `MediaImageConversionRepository::findByMediaId(MediaId $mediaId): MediaImageConversionCollection`;
  - `MediaVideoConversionRepository::findByMediaId(MediaId $mediaId): MediaVideoConversionCollection`;
  - `MediaMultipartUploadRepository::findByMediaId(MediaId $mediaId): ?MediaMultipartUpload`.
- Создать `MediaCollection`, потому что репозиторий `findExpired()` возвращает набор `Media`.
- Проверить компиляцию Cycle schema после добавления Entity командой внутри Docker через `make shell CMD='php app.php cycle'`.
- Не добавлять Handler, Controller, Filter, Resource и Response.

Результат: медиа-домен имеет Cycle Entity, связи, доменные методы и репозитории без доступа к базе из будущих Handler-ов напрямую.

Сценарии тестирования:

- `Media::create()` создаёт объект со статусом `waitingUpload`, UUID v7 `id`, UUID v4 `storageKey`, storage `media-upload`, непустым path и timestamps.
- Доменные методы `Media` переводят статус только в ожидаемые состояния.
- Multipart-ветки переводят статус в `completingMultipartUpload`, `multipartCompletionFailedCanRetry` и `multipartCompletionFailedNeedReupload`.
- Ошибка обработки увеличивает `processingAttempts` и сохраняет короткое сообщение.
- Успешная обработка очищает `processingError`.
- `MediaImageConversion` и `MediaVideoConversion` создаются только для существующей `Media`.
- `MediaMultipartUpload` хранит типизированную коллекцию частей.
- Репозитории возвращают конкретные типы и не отдают сырые массивы.
- Сохранение в PostgreSQL и чтение обратно сохраняют типы Value Object, enum и `MediaMultipartPartCollection`.

Проверка:

- Добавить unit-тесты доменных методов Entity.
- Добавить интеграционные тесты репозиториев на сохранение и чтение сущностей через Cycle ORM.
- Запустить `make shell CMD='php app.php cycle'`.
- Запустить `make test`.
- Запустить `make phpstan`.

### 4. Миграция базы и проверка схемы

Цель: создать таблицы медиа-домена в PostgreSQL и проверить, что они совпадают с Entity.

Что сделать:

- Создать миграцию из раздела `Данные и БД`.
- Создать таблицы в порядке: `media`, `media_image_conversions`, `media_video_conversions`, `media_multipart_uploads`.
- Добавить все primary key, foreign key, unique и обычные индексы из раздела `Данные и БД`.
- В `down()` удалить таблицы в обратном порядке.
- Проверить, что в миграции не используются `primary` и `bigPrimary`; все id-колонки имеют тип `uuid`.
- Проверить, что nullable есть только там, где это зафиксировано контрактом: `expires_at` и `processing_error`.
- Проверить миграции внутри Docker через `make migrate`.
- Проверить, что тестовая база поднимается, очищается и применяет миграции через `make test`. Это важно: `docker/test/run-tests.sh` запускает `php app.php migrate --force` перед PHPUnit.

Результат: база имеет таблицы медиа-домена, Cycle ORM видит Entity, а интеграционные тесты подтверждают сохранение, чтение и ограничения.

Сценарии тестирования:

- Миграция создаёт все четыре таблицы.
- `storage_key` уникален.
- Повторная image conversion с тем же `media_id + type` запрещена базой.
- Повторная video conversion с тем же `media_id + type` запрещена базой.
- Вторая multipart-загрузка для той же `media_id` запрещена базой.
- Удаление `Media` удаляет conversion и multipart rows каскадом.
- `uploaded_by_id` не требует таблицу `users`.
- Полный цикл `Value Object/enum/parts collection -> PostgreSQL -> Entity` сохраняет типы и значения.

Проверка:

- Запустить `make migrate`.
- Запустить `make test`.
- Запустить `make phpstan`.

## Тесты

Стратегия: `after_each_phase`.

После каждой фазы исполнитель добавляет или обновляет тесты для реализованной части и запускает проверки через Docker.

Основные уровни тестов:

- unit-тесты Value Object, enum и доменных методов Entity;
- unit-тесты storage config на наличие storage alias для каждого `MediaStorage` и на возможность local backend для upload;
- unit-тесты `ValueObjectCast`, `JsonCastable`, enum-cast и `HasTimestamps`;
- интеграционные тесты Cycle ORM на сохранение и чтение сущностей;
- интеграционные тесты миграций и ограничений базы через полный `make test`.

Команды:

- `make test`;
- `make phpstan`;
- `make migrate` в фазе миграции.

## Логирование

Стратегия: `debug_precise`.

В этом плане runtime-алгоритма с логированием ещё нет: API, upload flow, workers и storage-операции не реализуются. Поэтому новые application-логи не добавляются.

Точная диагностика для этой части реализуется так:

- Value Object выбрасывают `InvalidDomainValueException` с короткими русскими сообщениями;
- сообщения не содержат секреты, ETag, полный storage path и пользовательские имена файлов;
- тесты проверяют не только факт ошибки, но и безопасность сообщения;
- будущие Handler-ы и workers будут добавлять debug-логи в отдельных планах, где появятся runtime-сценарии.

## Документация и эксплуатация

- Обновить `docs/code-examples.md` коротким примером Entity с конкретным id Value Object, `ValueObjectCast` и `HasTimestamps`, чтобы пример не ссылался на общий `HasUuid`.
- Не менять `docs/arch.md` в этой задаче: архитектура уже описывает Domain, Repository, Cycle ORM и миграции.
- Обновить `.env.sample` generic media storage переменными без привязки к S3 в имени.
- Обновить Docker bootstrap MinIO, чтобы локальный стенд создавал media bucket-и.
- После реализации выполнить `make migrate`, `make test`, `make phpstan`.
- В релизе учесть, что миграция создаёт новые таблицы без backfill и не меняет существующие данные.

## Изменения после мета-ревью

### После моделей

- **+ Добавлено:** явное требование cast/uncast для string-backed enum, потому что Cycle `Typecast` читает enum, но не записывает enum обратно в строку.
- **+ Добавлено:** отдельный `JsonCastable`, JSON-формат `parts` и тест полного цикла JSON `parts -> MediaMultipartPartCollection -> JSON`.
- **+ Добавлено:** `MediaCollection` в список типизированных коллекций и тесты репозиториев на возврат конкретных коллекций.
- **+ Добавлено:** явные `collection`, `innerKey` и `outerKey` для Cycle-связей.
- **+ Добавлено:** проверка `make shell CMD='php app.php cycle'` после добавления Entity и пояснение, что `make test` применяет миграции тестовой базы.
- **+ Добавлено:** конфиг storage aliases для `MediaStorage`: `media-upload`, `media-private`, `media-public`, generic env-переменные и тест `StorageConfig`.
- **~ Изменено:** `MediaStorage` больше не привязан к S3; backend выбирается через `MEDIA_*_STORAGE_SERVER`, поэтому upload можно переключить на local storage без изменения домена и БД.
- **~ Изменено:** общий `HasUuid` убран из плана; каждая Entity объявляет свой id Value Object и генерирует UUID v7 через конкретный id-класс.
- **~ Изменено:** метод очистки `expiresAt` переименован в нейтральный перевод временной загрузки в постоянную, без привязки к будущему внешнему домену.
- **~ Изменено:** ограничения Value Object стали точными: длины строк, числовые границы, JSON-формат и безопасность сообщения `MediaProcessingError`.
- **Отклонено:** переход `parts` с `json` на `jsonb`; оставлен `json`, потому что текущий Cycle `Typecast` имеет встроенное правило `json`, а запросов по JSON в этом плане нет.

## Прогресс выполнения

Журнал: `docs/executions/2026-05-21_18-41_media-domain-entities-vo-migrations.md`

- [x] Шаг 1: Базовая поддержка доменных типов для Cycle ORM
- [x] Шаг 2: Enum, Value Object и storage config медиа-домена
- [x] Шаг 3: Entity, связи, коллекции и репозитории
- [x] Шаг 4: Миграция базы и проверка схемы
- [x] Финальная проверка
