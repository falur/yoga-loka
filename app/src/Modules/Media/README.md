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
| `CompleteMediaUpload(userId, mediaId, MediaConversionPlan, parts?)` | Command `#[Transactional]` | `MediaResult` |
| `ProcessMedia(mediaId, MediaConversionPlan)` | Command (через Job) | `void` |
| `RecordMediaProcessingFailure(mediaId, error, isTransient)` | Command | `void` |
| `DeleteMedia(userId, mediaId)` | Command | `void` |
| `MakeMediaPermanent(userId, mediaId)` | Command | `MediaResult` |
| `RemoveMediaOriginal(userId, mediaId)` | Command | `MediaResult` (удаляет оригинал из целевого бакета, статус → `readyOriginalRemoved`; конверсии сохраняются; требует ≥1 конверсии, иначе 422; идемпотентна на уже удалённом оригинале) |
| `FindMediaUrl(mediaId, presignedTtlSeconds?)` | Query | `MediaUrlsResult` или `null` (полный набор: `original` — оригинал, `null` если он удалён в `readyOriginalRemoved`; `conversions` — все конверсии, каждая со своим типом; вызывающий выбирает нужное по типу, не зная заранее, какие конверсии есть. Медиа нет или не финализировано → `null` (для best-effort показа), но невалидный переданный срок (например, явный `0`) бросает `InvalidDomainValueException`, а не возвращает `null`. Для public — прямые URL без срока (`presignedTtlSeconds` игнорируется и не валидируется), для private — presigned со сроком: по умолчанию из конфига, вызывающий может переопределить `presignedTtlSeconds` (`< 1` → ошибка; верхнюю границу `≤ 604800` на override код не проверяет — её держит только значение по умолчанию из конфига, а слишком большой срок хранилище отклонит при запросе). Потребитель, которому нужен только оригинал (аватар профиля), берёт `original` из набора; лента `Posts` строит оригинал напрямую из уже загруженной сущности через `MediaUrlService::getUrls`) |
| `GetAudioWaveform(mediaId)` | Query | `MediaWaveform` (числа амплитуд аудио-конверсии; не через URL; обслуживается при `ready` и `readyOriginalRemoved`; не финализировано/не аудио/нет конверсии → 404) |
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
- Профили конверсии на тип — **публичные** примитив-дружественные DTO (идут в полезных данных outbox),
  объединены в `MediaConversionPlan{ image: list<MediaImageConversionSpec>, video: list<...>, audio: list<...> }`:
  - `MediaImageConversionSpec{ type, width, height }`
  - `MediaVideoConversionSpec{ type, width, height, videoBitrate, audioBitrate }`
  - `MediaAudioConversionSpec{ type, bitrate, sampleRate, waveformPeaks }`

  Валидируется только список, относящийся к `media.type`; список «не своего» типа должен быть
  пустым (кросс-тип → 422). Для video и audio нужен ровно один профиль; дубли типов в списке image → 422.
- `RequestMediaUploadResult` — `single`: `putUrl`; `multipart`: `uploadId` + коллекция
  presigned-ссылок частей; всегда `mediaId`, `uploadMode`, `expiresAt`.
- `MediaResult{ mediaId, status, visibility }`, `MediaUrlResult{ url, expiresAt? }` (одна ссылка),
  `MediaConversionUrl{ kind, type, url, expiresAt? }` (ссылка конверсии: `kind` — вид
  image/video/audio для рендера на клиенте, `type` — конкретный профиль; постер видео имеет
  `kind = image`), `MediaUrlsResult{ original: MediaUrlResult?, conversions: MediaConversionUrlCollection }`
  (полный набор ссылок медиа) — наружу не отдаётся доменная Entity. URL строит `MediaUrlService`
  за контрактом `MediaUrlServiceContract` (реализация в `Infrastructure/Storage` читает
  `MediaConfig` напрямую; public — прямой URL, private — presigned), один резолвер на запрос.

## Поток загрузки

```text
1. RequestMediaUpload  -> presigned PUT (single) или multipart-ссылки; media = waitingUpload
2. Клиент PUT-ит байты напрямую в MinIO (бакет media-upload)
3. CompleteMediaUpload  -> headObject подтверждает объект и размер; media = uploaded;
                           MediaUploaded кладётся в outbox в той же транзакции   #[Transactional]
4. outbox:relay -> ProcessMediaJob -> ProcessMediaCommand
5. ProcessMedia (без #[Transactional]): S3/ffmpeg/Imagick вне транзакции. Исчерпывающий match по
   media.type: image -> ресайз (Imagick); video -> транскод mp4/H.264+AAC + кадр-постер (ffmpeg);
   audio -> транскод m4a/AAC + волна амплитуд (ffmpeg). Затем перекладка оригинала в целевой бакет
   по visibility и один атомарный persist+run() -> media = ready
6. FindMediaUrl: отдаёт полный набор ссылок (оригинал + все конверсии). public -> прямые URL
   (media-public, anonymous read); private -> presignGet (TTL по умолчанию из конфига,
   переопределяется presignedTtlSeconds запроса). Волна аудио — отдельным GetAudioWaveform (числа, не URL)
```

`uploadMode` (single/multipart) и размер части выбирает `MediaUploadPlanner` (читает ключи
`MediaConfig.multipartThresholdBytes` и `MediaConfig.multipartPartSizeBytes`); S3 требует ≥ 5 MiB
на часть, кроме последней.

## Статусы и обработка ошибок

`MediaStatus`: `waitingUpload → uploaded → ready (→ readyOriginalRemoved опционально)`. Последний
переход не обязателен: `readyOriginalRemoved` — опциональная терминальная ветка, в которую переводит
только явная команда `RemoveMediaOriginal`. Промежуточный `processing`
намеренно не используется (атомарный переход `uploaded → ready` одним flush). Переход
`ready → readyOriginalRemoved` делает `RemoveMediaOriginal`: оригинальный объект физически удаляется
из целевого бакета, а `storage`/`path` остаются исторической ссылкой на уже удалённый оригинал;
конверсии при этом не трогаются и продолжают резолвиться. Команда требует у медиа хотя бы одну
конверсию (иначе 422 `no_conversions_to_keep`), поэтому удалить оригинал у `Document` или у медиа с
пустым планом конверсий нельзя, а после удаления всегда остаётся доступный контент. Семантика запросов
после удаления оригинала: `FindMediaUrl` обслуживает `readyOriginalRemoved` — возвращает
`MediaUrlsResult` с `original = null` и доступными конверсиями (отдельного 404 на удалённый оригинал
нет); потребитель решает по `original`: вложение в `Posts` исчезает, аватар в `User` берёт значение по
умолчанию. `GetAudioWaveform` тоже обслуживает `readyOriginalRemoved`; `CheckMediaAttachable`
осознанно остаётся строгим (только `ready` → иначе 422).
Защита финализированного состояния: дубль/повтор `ProcessMedia` после удаления оригинала — ранний no-op
(`isFinalized()`), а поздняя запись ошибки обработки на `readyOriginalRemoved` — тоже no-op (медиа не
понижается в `processingFailed`). При ошибке обработки
`ProcessMediaJob` фиксирует `ProcessingFailed` через `RecordMediaProcessingFailure` и выбирает
стратегию повтора по **контрактному сигналу** временной ошибки, а не по типу хранилища:
`MediaFileServiceFailedException::isTransient()` → временная (сетевые/5xx/ограничение скорости) даёт
`RetryException` (событие остаётся на повтор) и лог уровня WARN; постоянная (Imagick/битый файл,
нештатный ответ S3) → терминальный проброс (outbox → `failed`) и лог уровня ERROR. Ошибки ffmpeg
классифицируются так же контрактным `MediaProcessorFailedException::isTransient()` (таймаут
транскодирования → временная/`RetryException`; битый/неподдерживаемый вход, нештатный код выхода →
постоянная); причина повтора в `RetryException` выбирается по типу (ошибка процессора ≠ ошибка
хранилища). Классификацию сырого `AwsException`/исключений php-ffmpeg делает Infrastructure
(`S3MediaFileService` / ffmpeg-процессоры → контрактные исключения), поэтому `Infrastructure/Spiral/Job` не
импортирует `Aws\*`/`FFMpeg\*` и не знает про реализации хранилища и обработки. Текст ошибки —
из предопределённого набора безопасных сообщений; сырой текст AWS не прокидывается (VO
`MediaProcessingError` отклоняет пути и слово `etag`). Сама запись ошибки на Media обёрнута
локальным guard: если она падает (медиа конкурентно удалили → `NotFoundException`, короткий сбой
БД), вторичный сбой логируется уровнем ERROR и не подменяет исходную причину — Job всё равно
выбирает повтор/терминальный исход по исходному исключению (временное → `RetryException`).

## Транзакционная дисциплина S3

- `CompleteMediaUpload` — `#[Transactional]`: переход `uploaded` и запись `MediaUploaded` в
  outbox происходят в одной транзакции. Компромисс: для multipart-ветки внешние S3-операции
  (`completeMultipartUpload`, `headObject`) выполняются под открытой транзакцией БД. Это
  осознанный компромисс — повтор `complete` идемпотентен (`NoSuchUpload` → подтверждение через
  `headObject`), а транзакция короткая. Если появятся проблемы с длительными транзакциями (блокировки,
  рассинхрон БД↔S3 при таймауте), сборку multipart и HEAD стоит вынести за пределы транзакции
  по образцу `ProcessMediaHandler`.
- `ProcessMediaHandler` — **без** `#[Transactional]`: сначала все S3/Imagick-операции вне
  транзакции (чтобы не держать БД и блокировку строки), затем один атомарный
  `persist(media + conversions); run()` с `markReadyMovedTo`. Операции идемпотентны
  (детерминированные ключи от `storageKey`): на повторе до коммита конверсий нет → создаём
  заново без конфликта по unique `(media_id, type)`; если медиа уже `ready` — no-op. Для image
  оригинал читается в память только при непустом наборе конверсий; video/audio скачиваются
  процессором в локальный файл (потоково, не в память); перекладка оригинала в целевой бакет —
  server-side `copyObject` для всех типов.
- Набор `conversions` фиксируется один раз в `CompleteMediaUpload` и доезжает до обработки в
  outbox-сообщении, поэтому на практике он стабилен. Повторная обработка перезаписывает объекты
  по детерминированным ключам и не размножает их; но если бы тот же media обработали повторно с
  другим набором конверсий, ранее залитые и больше не запрашиваемые конверсии в S3 не удаляются
  (осиротевшие объекты). В текущем контракте такого повтора не возникает.
- Оригинал И конверсии живут в одном бакете по `visibility` (`media-public`/`media-private`),
  чтобы `FindMediaUrl` резолвил их единообразно и не было утечки private-медиа.
- `MediaImageConversion` хранит `width`/`height` **из результата процессора**
  (`conversionResult->width`/`height`), а не запрошенные `spec.width`/`spec.height`: в БД должен
  лежать размер реально записанного объекта. Текущий `ImagickMediaImageProcessor` использует
  `cover()` и всегда выдаёт точные spec-размеры, поэтому фактически значения совпадают со spec; чтение
  из результата выбрано потому, что оно устойчиво к будущей смене режима ресайза (`contain`/`scale`),
  где фактический размер мог бы отличаться от запрошенного. Это осознанное отклонение от буквы плана
  (шаг 6 предписывал `MediaPixelDimension из spec.width/spec.height`).
- `DeleteMedia` удаляет из S3 не только оригинал, но и объекты конверсий: до `delete($media)`
  Handler грузит конверсии (`MediaImageConversionRepository`/`MediaVideoConversionRepository`/
  `MediaAudioConversionRepository`) и удаляет каждый объект по `storage`/`path` (404 идемпотентно
  игнорируется). Постер видео — это `MediaImageConversion` (Poster), он уже в image-цикле, отдельно
  не чистится. Без этого FK `ON DELETE CASCADE` убрал бы строки конверсий, оставив файлы
  осиротевшими в постоянном бакете. На `readyOriginalRemoved`-медиа `DeleteMedia` работает без
  изменений: `storage`/`path` указывают на уже удалённый оригинал, повторный `deleteObject` отдаёт
  404 (идемпотентный no-op), а конверсии чистятся как обычно.
- Staging-оригинал из `media-upload` синхронно не удаляется — чистится по expiry (отдельная
  задача).

## Outbox-сообщение

`Application/Message/MediaUploaded implements OutboxMessage` — только примитивы/enum
(`string mediaId`, вложенный `MediaConversionPlan` из `list<...Spec>`), потому что
`ValinorOutboxMessageSerializer` не регистрирует кастомные конструкторы доменных VO; точные
PHPDoc-типы `list<...Spec>` обязательны для восстановления вложенного DTO Valinor-ом. Пара `MediaUploaded → ProcessMediaJob`
регистрируется в `MediaBootloader`; Job — в `app/config/queue.php`
(`registry.handlers` + `registry.serializers = OutboxQueueSerializer`).

## Инфраструктура и конфиг

- `app/config/media.php` + `MediaConfig` (`Shared/Infrastructure/Spiral/Configuration/Media`): staging-TTL,
  порог и размер части multipart, драйвер обработки изображений, срок presigned-ссылки скачивания по
  умолчанию (`presignedTtlSeconds` — env `MEDIA_PRESIGNED_TTL_SECONDS`; нижнюю границу `≥ 1` держит
  `MediaPresignedTtl`, верхнюю `≤ 604800` — лимит подписи S3 — проверяет `MediaConfig` при старте только
  для значения по умолчанию, не для override), ffmpeg-настройки (`ffmpegBinaryPath`, `ffprobeBinaryPath`,
  `ffmpegTimeoutSeconds`, `ffmpegThreads` —
  env `MEDIA_FFMPEG_BINARY`/`MEDIA_FFPROBE_BINARY`/`MEDIA_FFMPEG_TIMEOUT_SECONDS`/`MEDIA_FFMPEG_THREADS`).
  Срок presigned-ссылок загрузки задаёт потребитель под контекст (`MediaUploadSpec.presignedTtl`); срок
  скачивания берётся из конфига по умолчанию, но вызывающий может переопределить его
  (`FindMediaUrlQuery.presignedTtlSeconds`). Ключи `MEDIA_*` — в `.env.sample` и `phpunit.xml`.
  Application модуля `*Config` не читает: `RequestMediaUploadHandler` получает решения пайплайна
  загрузки (срок staging-хранения, нужен ли multipart, размер и число частей) через
  `MediaUploadPlannerContract`; реализация `MediaUploadPlanner` (`Infrastructure/Storage`) читает
  `MediaConfig` через конструктор и биндится `const BINDINGS` — как `MediaUrlService`. Срок
  presigned-ссылки скачивания по умолчанию читает сама реализация `MediaUrlService` из
  `Infrastructure/Storage` (прямая инъекция `MediaConfig`), а Application зависит от контракта
  `MediaUrlServiceContract` — это технический сервис с поведением, поэтому он живёт в `Infrastructure`
  (см. `docs/arch.md`, «Правила зависимостей»).
- presigned/multipart и серверные S3-операции — `S3MediaFileService` поверх `Aws\S3\S3Client`
  (построение клиента вынесено в `S3ClientProvider` для тестируемости). Имя бакета и `prefix`
  резолвятся из `StorageConfig->buckets[alias]`, где alias — значение enum `MediaStorage`.
- Обработка изображений — `ImagickMediaImageProcessor` на Intervention Image v4 (драйвер
  imagick по умолчанию, gd как фолбэк; см. `docker/README.md`).
- Обработка видео/аудио — `FfmpegMediaVideoProcessor`/`FfmpegMediaAudioProcessor` на
  `php-ffmpeg/php-ffmpeg` (бинарь ffmpeg в образе). Процессор владеет временными файлами:
  `downloadToFile` оригинала → ffmpeg (probe/транскод/постер/волна) → `uploadFromFile` результатов
  → удаление всех временных файлов в `finally` при любом исходе. Application-handler строит целевые
  пути доменными фабриками и передаёт готовыми; Infrastructure доменные пути не строит. AAC берётся
  нативным кодером ffmpeg (`NativeAacAudioFormat`), т.к. `libfdk_aac` в сборку не входит. Волну
  амплитуд снимаем сырым PCM (s16le, моно) прямым вызовом ffmpeg и нормализуем в 0..255.
- Контракт выхода видео — всегда mp4/**H.264+AAC**. php-ffmpeg добавляет AAC только при наличии
  аудио на входе, поэтому немому видео (`video/*` без звука) процессор подмешивает тихую AAC-дорожку
  (`anullsrc`, обрезается по длине видео через `-shortest`) — выход остаётся H.264+AAC.
- Аудио с обложкой альбома (`audio/*` со встроенным потоком attached_pic — mjpeg/png) — штатный вход:
  «есть видеопоток» не равно «не аудио». Процессор различает реальное видео и аудио-с-обложкой по
  факту настоящего аудиопотока и отсутствия не-обложечной видеодорожки (ffprobe), а при транскоде
  выкидывает обложку (`-vn`), отдавая чистый m4a/AAC.

## Эксплуатация

- Миграция `*_create_media_audio_conversions_table` добавляет таблицу `media_audio_conversions`
  (новая, backfill не нужен; rollback = drop). Прочие таблицы media не меняются.
- В образе нужен бинарь `ffmpeg`/`ffprobe` (ставится в `docker/Dockerfile`, самопроверка
  `ffmpeg -version`/`ffprobe -version` при сборке).
- **Предусловие выката**: смена содержимого `MediaUploaded` (`conversions` → `plan`) несовместима.
  Сквозного потребителя в рабочей среде нет, но перед выкатом нужно **слить очередь RabbitMQ и
  outbox**, чтобы не осталось старых сообщений со старым форматом.
- Осиротевшие объекты при терминальной ошибке: video/audio-процессор грузит результаты в целевой
  бакет ДО финального flush (как и поток картинок). При постоянной ошибке после загрузки строк
  конверсий в БД нет, поэтому `DeleteMedia` их не видит — реклейм терминальных сирот оставлен на
  будущий storage-sweep (try-catch в handler не вводим). Пути детерминированы, повтор перезаписывает.
- Длинные видео ограничены `MediaUploadSpec.maxSize` (потребитель) и `MEDIA_FFMPEG_TIMEOUT_SECONDS`;
  путь отхода при упоре — вынос транскодирования в Temporal (осознанный компромисс ради
  единообразия с картинками).
- Нужен запущенный `outbox:relay` (один экземпляр) и consumer RabbitMQ.
- `media-public` должен иметь anonymous-read policy (в dev ставится `ensure-buckets.sh`; в
  prod/stage — задача деплоя).
- Переключение ленты `Posts` на нормализованные video/audio (mp4 + постер, m4a + волна) — **вне
  этой задачи**: Media производит конверсии и отдаёт их своими запросами, подключение ленты —
  отдельная задача.
- Полноценный сквозной сценарий по HTTP появится с модулем-потребителем, который добавит точку
  входа с настройками и вызовет этот Application-API.
