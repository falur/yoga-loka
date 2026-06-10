# Модуль Media

`Media` — переиспользуемый Application-API загрузки и обработки файлов. У модуля **нет
собственного HTTP**: точку входа с настройками держит модуль-потребитель (например, будущий
`User` для аватара), а `Media` предоставляет только свои сценарии через
`Application`-слой. Ограничения каждой загрузки потребитель передаёт спецификацией прямо в
вызов — они не зашиты в общий конфиг и не отдаются клиенту.

> `Media` не знает про аватар или конкретное применение. Он умеет только: запросить загрузку,
> подтвердить её, асинхронно обработать, отдать URL, проверить и удалить.

## Публичный Application-API

Другие модули обращаются только к `Application` (Command/Query), не к `Repository`,
`Infrastructure` или таблицам.

| Сценарий | Тип | Результат |
|---|---|---|
| `RequestMediaUpload(userId, MediaUploadSpec, MediaFileMeta)` | Command | `RequestMediaUploadResult` |
| `CompleteMediaUpload(userId, mediaId, list<MediaConversionSpec>, parts?)` | Command `#[Transactional]` | `MediaResult` |
| `ProcessMedia(mediaId, list<MediaConversionSpec>)` | Command (через Job) | `void` |
| `RecordMediaProcessingFailure(mediaId, error, isTransient)` | Command | `void` |
| `DeleteMedia(userId, mediaId)` | Command | `void` |
| `MakeMediaPermanent(userId, mediaId)` | Command | `MediaResult` |
| `GetMediaUrl(mediaId, presignedTtlSeconds, conversionType?)` | Query | `MediaUrlResult` (для public-медиа `presignedTtlSeconds` игнорируется и не валидируется — отдаётся прямой URL без срока; срок применяется и проверяется только для private) |
| `CheckMediaIsImage(mediaId)` | Query | `bool` |
| `CheckMediaExists(mediaId)` | Query | `bool` |

`userId` потребитель передаёт параметром (строкой). Проверка владельца —
в Handler-е (`CompleteMediaUpload`/`DeleteMedia`/`MakeMediaPermanent`): несовпадение →
`ForbiddenException` (403). Ошибки — типизированные доменные исключения
(`ValidationException` 422, `NotFoundException` 404, `ForbiddenException` 403), которые на
границе потребителя превращает в ответ `ApiExceptionInterceptor`.

### Спецификация и DTO (`Application/Dto`)

- `MediaUploadSpec{ allowedMimeTypes: MediaMimeTypeCollection, maxSize: MediaFileSize, visibility, presignedTtl: MediaPresignedTtl }`
  — политика загрузки от потребителя. Конверсий здесь нет. `presignedTtl` — срок жизни
  presigned-ссылок загрузки (PUT/части), задаёт потребитель под контекст, а не общий конфиг.
- `MediaFileMeta{ fileName, mimeType, size }` — `fileName` используется только для извлечения
  расширения (не хранится).
- `MediaConversionSpec{ type, width, height }` — профиль конверсии, **публичный** DTO с
  примитивами (идёт и в outbox-payload).
- `RequestMediaUploadResult` — `single`: `putUrl`; `multipart`: `uploadId` + коллекция
  presigned-ссылок частей; всегда `mediaId`, `uploadMode`, `expiresAt`.
- `MediaResult{ mediaId, status, visibility }`, `MediaUrlResult{ url, expiresAt? }` — наружу не
  отдаётся доменная Entity.

## Поток загрузки

```text
1. RequestMediaUpload  -> presigned PUT (single) или multipart-ссылки; media = waitingUpload
2. Клиент PUT-ит байты напрямую в MinIO (бакет media-upload)
3. CompleteMediaUpload  -> headObject подтверждает объект и размер; media = uploaded;
                           MediaUploaded кладётся в outbox в той же транзакции   #[Transactional]
4. outbox:relay -> ProcessMediaJob -> ProcessMediaCommand
5. ProcessMedia (без #[Transactional]): S3/Imagick вне транзакции — конверсии и перекладка
   оригинала в целевой бакет по visibility, затем один атомарный persist+run() -> media = ready
6. GetMediaUrl: public -> прямой URL (media-public, anonymous read); private -> presignGet
   (TTL из presignedTtlSeconds запроса)
```

`uploadMode` (single/multipart) выбирается по порогу `MediaConfig.multipartThresholdBytes`;
размер части — `MediaConfig.multipartPartSizeBytes` (S3 требует ≥ 5 MiB на часть, кроме последней).

## Статусы и обработка ошибок

`MediaStatus`: `waitingUpload → uploaded → ready`. Промежуточный `processing` намеренно не
используется (атомарный переход `uploaded → ready` одним flush). При ошибке обработки
`ProcessMediaJob` фиксирует `ProcessingFailed` через `RecordMediaProcessingFailure` и выбирает
стратегию повтора по **контрактному сигналу** транзиентности, а не по типу хранилища:
`MediaFileServiceFailedException::isTransient()` → транзиентная (сетевые/5xx/throttling) даёт
`RetryException` (событие остаётся на повтор) и лог уровня WARN; постоянная (Imagick/битый файл,
нештатный ответ S3) → терминальный проброс (outbox → `failed`) и лог уровня ERROR. Классификацию
сырого `AwsException` делает Infrastructure (`S3MediaFileService` → `MediaFileServiceFailedException`),
поэтому `Presentation/Job` не импортирует `Aws\*` и не знает про реализацию хранилища. Текст ошибки —
из предопределённого набора безопасных сообщений; сырой текст AWS не прокидывается (VO
`MediaProcessingError` отклоняет пути и слово `etag`). Сама запись ошибки на Media обёрнута
локальным guard: если она падает (медиа конкурентно удалили → `NotFoundException`, короткий сбой
БД), вторичный сбой логируется уровнем ERROR и не подменяет исходную причину — Job всё равно
выбирает retry/terminal по исходному исключению (транзиентное → `RetryException`).

## Транзакционная дисциплина S3

- `CompleteMediaUpload` — `#[Transactional]`: переход `uploaded` и запись `MediaUploaded` в
  outbox происходят в одной транзакции. Trade-off: для multipart-ветки внешние S3-операции
  (`completeMultipartUpload`, `headObject`) выполняются под открытой транзакцией БД. Это
  осознанный компромисс — повтор `complete` идемпотентен (`NoSuchUpload` → подтверждение через
  `headObject`), а транзакция короткая. Если появятся проблемы с длительными транзакциями (блокировки,
  рассинхрон БД↔S3 при таймауте), сборку multipart и HEAD стоит вынести за пределы транзакции
  по образцу `ProcessMediaHandler`.
- `ProcessMediaHandler` — **без** `#[Transactional]`: сначала все S3/Imagick-операции вне
  транзакции (чтобы не держать БД и блокировку строки), затем один атомарный
  `persist(media + conversions); run()` с `markReadyMovedTo`. Операции идемпотентны
  (детерминированные ключи от `storageKey`): на повторе до коммита конверсий нет → создаём
  заново без конфликта по unique `(media_id, type)`; если медиа уже `ready` — no-op. Оригинал
  читается в память только при непустом наборе конверсий (для видео/без конверсий выполняется
  лишь server-side `copyObject`).
- Набор `conversions` фиксируется один раз в `CompleteMediaUpload` и доезжает до обработки в
  outbox-сообщении, поэтому на практике он стабилен. Повторная обработка перезаписывает объекты
  по детерминированным ключам и не размножает их; но если бы тот же media обработали повторно с
  другим набором конверсий, ранее залитые и больше не запрашиваемые конверсии в S3 не удаляются
  (осиротевшие объекты). В текущем контракте такого повтора не возникает.
- Оригинал И конверсии живут в одном бакете по `visibility` (`media-public`/`media-private`),
  чтобы `GetMediaUrl` резолвил их единообразно и не было утечки private-медиа.
- `MediaImageConversion` хранит `width`/`height` **из результата процессора**
  (`conversionResult->width`/`height`), а не запрошенные `spec.width`/`spec.height`: в БД должен
  лежать размер реально записанного объекта. Текущий `ImagickMediaImageProcessor` использует
  `cover()` и всегда выдаёт точные spec-размеры, поэтому фактически значения совпадают со spec; чтение
  из результата выбрано потому, что оно устойчиво к будущей смене режима ресайза (`contain`/`scale`),
  где фактический размер мог бы отличаться от запрошенного. Это осознанное отклонение от буквы плана
  (шаг 6 предписывал `MediaPixelDimension из spec.width/spec.height`).
- `DeleteMedia` удаляет из S3 не только оригинал, но и объекты конверсий: до `delete($media)`
  Handler грузит конверсии (`MediaImageConversionRepository`/`MediaVideoConversionRepository`) и
  удаляет каждый объект по `storage`/`path` (404 идемпотентно игнорируется). Без этого FK
  `ON DELETE CASCADE` убрал бы строки конверсий, оставив файлы осиротевшими в постоянном бакете.
- Staging-оригинал из `media-upload` синхронно не удаляется — чистится по expiry (отдельная
  задача).

## Outbox-сообщение

`Application/Message/MediaUploaded implements OutboxMessage` — только примитивы/enum
(`string mediaId`, `list<MediaConversionSpec>`), потому что `ValinorOutboxMessageSerializer` не
регистрирует кастомные конструкторы доменных VO. Пара `MediaUploaded → ProcessMediaJob`
регистрируется в `MediaBootloader`; Job — в `app/config/queue.php`
(`registry.handlers` + `registry.serializers = OutboxQueueSerializer`).

## Инфраструктура и конфиг

- `app/config/media.php` + `MediaConfig` (`Shared/Infrastructure/Configuration/Media`): staging-TTL,
  порог и размер части multipart, драйвер обработки. TTL presigned-ссылок в конфиге **нет** — его
  задаёт потребитель под контекст: `MediaUploadSpec.presignedTtl` (загрузка) и
  `GetMediaUrlQuery.presignedTtlSeconds` (скачивание). Ключи `MEDIA_*` — в `.env.sample` и
  `phpunit.xml`. `MediaConfig` инжектится напрямую в `RequestMediaUploadHandler` — осознанное
  исключение из правила зависимостей слоёв: `TypedConfig` живёт в `Shared/Infrastructure/Configuration`
  по конвенции размещения всех config-DTO, и оборачивать его в Application-`*Contract` было бы
  запрещённой pass-through-обёрткой (см. `docs/arch.md`, «Правила зависимостей»).
- presigned/multipart и серверные S3-операции — `S3MediaFileService` поверх `Aws\S3\S3Client`
  (построение клиента вынесено в `S3ClientProvider` для тестируемости). Имя бакета и `prefix`
  резолвятся из `StorageConfig->buckets[alias]`, где alias — значение enum `MediaStorage`.
- Обработка изображений — `ImagickMediaImageProcessor` на Intervention Image v4 (драйвер
  imagick по умолчанию, gd как фолбэк; см. `docker/README.md`).

## Эксплуатация

- Миграции для модуля не нужны (таблицы созданы ранее).
- Нужен запущенный `outbox:relay` (один экземпляр) и consumer RabbitMQ.
- `media-public` должен иметь anonymous-read policy (в dev ставится `ensure-buckets.sh`; в
  prod/stage — задача деплоя).
- Полноценный сквозной сценарий по HTTP появится с модулем-потребителем, который добавит точку
  входа с настройками и вызовет этот Application-API.
