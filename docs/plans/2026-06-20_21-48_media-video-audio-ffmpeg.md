---
title: Media — реальная нарезка видео и аудио на ffmpeg (профиль конверсии на тип)
date: 2026-06-20 21:48
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
  research: docs/researches/2026-06-20_11-14_media-video-audio-conversions.md
---

# План реализации

## Задача

Модуль `Media` умеет реально нарезать только картинки. Нужно довести его до единой модели
«профиль конверсии на тип»: на каждый `MediaType` — свой каталог профилей (enum) и свой
процессор; потребитель задаёт списки профилей на тип; видео и аудио режутся реальным ffmpeg;
результаты (включая URL и волну аудио) доступны потребителю. Документы — вне работ (остаются 422).

Готово, когда:
- аудио принимается (`audio/*` → `MediaType::Audio`), есть полный доменный стек аудио + таблица
  `media_audio_conversions`;
- видео реально транскодируется в mp4/H.264+AAC и даёт кадр-постер (как `MediaImageConversion`
  type=Poster); аудио — в m4a/AAC и даёт массив амплитуд (волну);
- `ProcessMediaHandler` ветвится исчерпывающим `match` по `MediaType`;
- ffmpeg работает с локальными файлами; их владелец и уборщик — инфраструктурный процессор;
- ошибки ffmpeg классифицируются контрактным `MediaProcessorFailedException` с `isTransient()`;
- `GetMediaUrl` отдаёт URL image/video/audio-конверсий; волна — отдельным `GetAudioWaveform`;
- `php-ffmpeg/php-ffmpeg ^1.4` в composer, бинарь ffmpeg в Docker, ffmpeg-настройки в `MediaConfig`;
- `make qa` зелёный, покрытие 100%, все новые сценарии покрыты тестами.

## Контекст

Факты из кода (проверено чтением):

- `ProcessMediaHandler` (`app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php`)
  — без `#[Transactional]`: все S3/Imagick-операции вне транзакции, затем один атомарный
  `persist(media + conversions); run()` с `markReadyMovedTo`. Идемпотентно: на `ready` — no-op;
  конверсии создаются только в финальном flush; детерминированные ключи от `storageKey`,
  unique `(media_id, type)`.
- Сейчас обрабатываются только картинки: `buildConversions()` читает оригинал в строку
  (`getObjectContents`), гоняет `MediaImageProcessorContract::resize`, льёт `putObject`, создаёт
  `MediaImageConversion`. Видео сегодня = только `copyObject` оригинала в `videos/.../source.<ext>`.
- Сообщение `MediaUploaded{mediaId, conversions: list<MediaConversionSpec>}` →
  `ProcessMediaCommand{mediaId, conversions}`. `CompleteMediaUploadHandler` кладёт его в outbox в
  одной транзакции с `markUploaded` и валидирует диапазон пикселей через `MediaPixelDimension`.
- Видео-каркас уже есть: enum `MediaVideoConversionType::NormalizedMp4H264`, сущность
  `MediaVideoConversion` (колонки + `duration_ms`, `bitrate`), `MediaVideoConversionRepository::findByMediaId`
  → `MediaVideoConversionCollection`, связь `Media::videoConversions`, таблица `media_video_conversions`.
  Нет: процессора, постера, создания конверсий, выдачи URL видео.
- Аудио нет нигде. `MediaTypeResolver` (`Application/Service/MediaTypeResolver.php`) отклоняет всё
  кроме `image/*` и `video/*` как 422. `MediaPath::assertValid` regex допускает только
  `uploads|images|videos`; `originalReady` для Audio/Document бросает; нет фабрик
  `videoConversion()`/`audioConversion()`.
- `MediaFileServiceContract` (`Application/Contract/MediaFileServiceContract.php`): только
  `getObjectContents(): string` и `putObject(string)` — файловых методов нет.
- `GetMediaUrlQuery.conversionType: MediaImageConversionType|null`; `GetMediaUrlHandler` ходит только
  в `MediaImageConversionRepository`.
- `ProcessMediaJob` (`Presentation/Job/ProcessMediaJob.php`): признак временной ошибки = только
  `MediaFileServiceFailedException::isTransient()`; ошибки процессора станут терминальными.
- VO (`Domain/ValueObject`): `MediaPixelDimension` (1..100000, `supports()`), `MediaDuration`
  (1..604_800_000 мс), `MediaBitrate` (1..1_000_000_000), `MediaFileSize`, `MediaMimeType`,
  `MediaProcessingError` (null-object `none()`). Нет `MediaSampleRate`, `MediaWaveform`,
  `MediaWaveformPeakCount`, `MediaAudioConversionId`, `MediaAudioConversionType`,
  `MediaAudioConversion`, `MediaAudioConversionCollection`, `MediaAudioConversionRepository`.
- `MediaConfig` (`app/src/Shared/Infrastructure/Configuration/Media/MediaConfig.php`):
  `stagingTtlSeconds, multipartThresholdBytes, multipartPartSizeBytes, imageProcessingDriver`;
  `configName()='media'`; `app/config/media.php` через `env()`; маппинг покрыт `MediaConfigTest`.
- Docker (`docker/Dockerfile`, `ubuntu:26.04`) ставит php8.5 + `imagick`/`gd`, бинаря ffmpeg нет, в
  блоке самопроверки `php -m | grep ...`. `composer.json`: `intervention/image: ^4`, php-ffmpeg нет.
- Тесты: suites `Unit/Kernel/Feature` (`phpunit.xml`). Дублёры: `tests/.../Fixture/FakeS3Client`,
  `FakeS3ClientProvider`, `tests/.../Flow/Fixture/RecordingMediaLogger`,
  `ThrowingProcessMediaCommandBus`. База `MediaApplicationTestCase` (`createMedia`, `conversionSpec`,
  доступ к репозиториям). `ProcessMediaHandlerTest` мокает контракты; `ProcessMediaJobTest`
  проверяет классификацию ошибок и логирование уровней.
- `Media` (`Domain/Entity/Media.php`): `recordTemporaryProcessingError`/`recordPermanentProcessingError`,
  `markReadyMovedTo` (Ready→no-op; допускает переход из Uploaded/Processing/ProcessingFailed),
  `markReady`, `isReady`; связи `imageConversions`, `videoConversions` (нужна `audioConversions`).
- `php-ffmpeg/php-ffmpeg` 1.4.0 (релиз 2026-01-19), поддержка PHP 8.5, требует бинарей
  `ffmpeg`/`ffprobe`. Источник — Packagist; версия подтверждена в research и кросс-ревью Codex.

## Принятые решения

1. **Профиль конверсии на тип**: на каждый `MediaType` — свой enum профилей и свой процессор-контракт;
   потребитель задаёт списки профилей на тип в едином `MediaConversionPlan`. Объединять в один спек
   нельзя — у аудио нет width/height, общий объект потянул бы nullable-примитивы (нарушение rules.md
   «Явные типы вместо null»). *Источник: ответ пользователя (research, развилки 1 и 2).*
2. **Инструмент и среда**: `php-ffmpeg/php-ffmpeg ^1.4` в текущем пути outbox→RabbitMQ-Job (без
   Temporal). *Источник: ответ пользователя (research, развилка 3); версия подтверждена Packagist.*
3. **Видео**: транскод mp4/H.264+AAC + кадр-постер как `MediaImageConversion` type=Poster; размер
   постера = размер транскода; отдельный картиночный профиль для видео потребитель не передаёт
   (картиночные профили для видео → 422). *Источник: ответ пользователя (research, развилка 4а).*
4. **Аудио**: транскод m4a/AAC + волна амплитуд (массив малых целых для прогресса воспроизведения «как
   в Telegram», не картинка). *Источник: ответ пользователя (research, развилка 4б).*
5. **БД**: отдельная таблица на тип конверсии (как существующие `media_image_conversions`,
   `media_video_conversions`); новая `media_audio_conversions` со своими полями; волна — JSON-колонка
   `waveform` на строке аудио-конверсии. Общая таблица отклонена: дала бы массу пустых NULL-колонок
   (нарушение «Явные типы вместо null»). *Источник: ответ пользователя в текущем сообщении.*
6. **Размер плана**: normal. *Источник: ответ пользователя.*
7. **Контракт процессора — крупный**: `MediaVideoProcessorContract::process(...)` /
   `MediaAudioProcessorContract::process(...)` возвращают только метаданные результата. Скачивание
   оригинала, ffmpeg (probe/транскод/постер/волна), загрузка результатов и удаление временных файлов в
   `finally` — внутри Infrastructure-реализации; Application-Handler локальных путей не видит. Это
   закрывает внутреннее противоречие research (granular-сигнатуры с локальными путями vs «процессор
   владеет временными файлами и чистит их в finally») в пользу формулировки research про владельца
   очистки. *Источник: autonomous (decision_mode recommend_and_ask, внутренний технический контракт;
   обоснование — rules.md «handler не управляет путями», единый владелец и очистка).*
8. **Смена содержимого `MediaUploaded`** (`conversions` → `plan: MediaConversionPlan`) — несовместимое
   изменение. Сквозного потребителя в рабочей среде нет: HTTP-входа Media не существует, `Posts`
   привязывает уже загруженные медиа по id (`PostContentComposer.php`). Предусловие выката: слить
   очередь RabbitMQ и outbox. *Источник: research, подтверждено чтением кода (у модуля Media нет
   HTTP-контроллеров).*

## Целевой алгоритм

**Подтверждение загрузки (`CompleteMediaUpload`).** Принимает `MediaConversionPlan` (три списка:
image/video/audio). Валидирует **только** список, относящийся к `media.type`; список «не своего» типа
непуст → `ValidationException` 422 (видео-файл со списком картинок → 422, и наоборот). Для video и audio
требуется ровно один профиль (`NormalizedMp4H264` / `NormalizedAacM4a`): пустой список или больше одного
→ 422. Дубли типов в любом списке → 422 (иначе падение по unique `(media_id, type)` уже после S3-записей).
Диапазоны: image
и video — `MediaPixelDimension::supports(width/height)`; video — `MediaBitrate::supports(videoBitrate,
audioBitrate)`; audio — `MediaBitrate::supports(bitrate)`, `MediaSampleRate::supports(sampleRate)`,
`MediaWaveformPeakCount::supports(waveformPeaks)`. Кладёт `MediaUploaded{mediaId, plan}` в outbox в одной
транзакции с `markUploaded`.

**Обработка (`ProcessMediaJob` → `ProcessMediaCommand{mediaId, plan}` → `ProcessMediaHandler`).**
Идемпотентность: `media.isReady()` → no-op. Затем исчерпывающий `match (media.type)`:
- `Image` — как сейчас: `getObjectContents` → `imageProcessor.resize` → `putObject`; конверсии из
  `plan.image`; оригинал `copyObject` → `images/.../source.<ext>`.
- `Video` — `videoProcessor.process(sourceStorage, sourcePath, spec, targetStorage, normalizedPath,
  posterPath)` → метаданные (нормализованное видео + постер). Создаёт `MediaVideoConversion`
  (`NormalizedMp4H264`) и `MediaImageConversion` (`Poster`, размер = размер транскода). Оригинал
  `copyObject` → `videos/.../source.<ext>`.
- `Audio` — `audioProcessor.process(sourceStorage, sourcePath, spec, targetStorage, normalizedPath)` →
  метаданные (нормализованное аудио + `MediaWaveform`). Создаёт `MediaAudioConversion`
  (`NormalizedAacM4a`) с волной. Оригинал `copyObject` → `audios/.../source.<ext>`.
- `Document` — недостижимо: `MediaTypeResolver` отклоняет 422 на приёме (ветка `match` бросает
  `InvalidDomainValueException` как защита инварианта).

Финал — атомарный `persist(media + все конверсии); run()` + `markReadyMovedTo`. Внутри процессора:
`downloadToFile(оригинал)` → ffmpeg → `uploadFromFile(результаты)` → `finally` удаляет вход и все
выходы при любом исходе (успех, ошибка ffmpeg, сбой загрузки).

**Выдача URL (`GetMediaUrl`).** `conversionType:
MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType|null`. По классу enum —
исчерпывающий `match`, выбор нужного репозитория конверсий, дальше та же логика `publicUrl`/`presignGet`.
Без `conversionType` — оригинал. Волна — не через URL.

**Волна (`GetAudioWaveform`).** `GetAudioWaveformQuery{mediaId}` → handler читает нормализованную
`MediaAudioConversion` через `MediaAudioConversionRepository`, возвращает её `MediaWaveform` (числа
встроенно). Медиа не ready / не аудио / нет конверсии → `NotFoundException`.

**Ошибки ffmpeg.** Процессоры Infrastructure оборачивают исключения php-ffmpeg в
`MediaProcessorFailedException` (`Application/Exception`) с `isTransient()`: битый/неподдерживаемый
вход, нештатный код выхода ffmpeg на валидном вызове → постоянная (terminal, ERROR); таймаут
транскодирования, нехватка ресурсов процесса → временная (`RetryException`, WARN). `ProcessMediaJob`
дополняет признак временной ошибки веткой `|| (MediaProcessorFailedException && isTransient())`;
`safeMessage()` не меняется (уже покрывает новый тип), а текст `RetryException.reason` выбирается по типу
ошибки (для процессора — сообщение о повторе обработки, не «ошибка хранилища»). Текст — из безопасного
набора, сырой вывод ffmpeg наружу не уходит (`MediaProcessingError` отклоняет пути/`etag`).

## Контракты реализации

### Данные и БД

Меняется: добавляется таблица `media_audio_conversions`. Существующие `media`,
`media_image_conversions`, `media_video_conversions` не меняются.

Новая миграция `app/database/migrations/{timestamp}_0_create_media_audio_conversions_table.php` в стиле
`20260521.184100_0_create_media_domain_tables.php`:

```text
media_audio_conversions
  id           uuid          NOT NULL  PK
  media_id     uuid          NOT NULL  FK -> media(id) ON DELETE CASCADE ON UPDATE CASCADE (indexCreate=false)
  type         string(64)    NOT NULL
  status       string(32)    NOT NULL
  storage      string(64)    NOT NULL
  path         string(1024)  NOT NULL
  mime_type    string(255)   NOT NULL
  size         bigInteger    NOT NULL
  duration_ms  bigInteger    NOT NULL
  bitrate      integer       NOT NULL
  sample_rate  integer       NOT NULL
  waveform     json          NOT NULL
  created_at   datetime      NOT NULL
  updated_at   datetime      NOT NULL
  unique (media_id, type); index (media_id); index (status)
down(): drop media_audio_conversions
```

Совместимость: таблица новая, аудио-строк ещё нет, backfill не нужен; rollback = `drop`.

### API и внешние контракты

Публичных HTTP-роутов/webhooks у модуля Media нет — меняются **внутренние Application-контракты**
(CQRS DTO, outbox-сообщение, контракты процессоров и файлового сервиса). Внешних API/очередей третьих
сторон задача не затрагивает.

Спеки профилей (`Application/Dto`, публичные `readonly`, примитив-дружественные для Valinor):
```text
MediaImageConversionSpec{ MediaImageConversionType type, int width, int height }   // = переименование MediaConversionSpec
MediaVideoConversionSpec{ MediaVideoConversionType type, int width, int height, int videoBitrate, int audioBitrate }
MediaAudioConversionSpec{ MediaAudioConversionType type, int bitrate, int sampleRate, int waveformPeaks }
MediaConversionPlan{ list<MediaImageConversionSpec> image, list<MediaVideoConversionSpec> video, list<MediaAudioConversionSpec> audio }
```

Все три списка `MediaConversionPlan` несут точные PHPDoc-типы `@param/@var list<...Spec>` — это условие
восстановления вложенного DTO через Valinor (`ValinorOutboxMessageSerializer`); покрыть round-trip тестом.

Сообщение и команды: `MediaUploaded.conversions` → `plan: MediaConversionPlan`;
`ProcessMediaCommand.conversions` → `plan`; `CompleteMediaUploadCommand.conversions` → `plan`.

Контракты процессоров (`Application/Contract`):
```text
MediaVideoProcessorContract::process(
  MediaStorage sourceStorage, MediaPath sourcePath, MediaVideoConversionSpec spec,
  MediaStorage targetStorage, MediaPath normalizedPath, MediaPath posterPath
): MediaVideoProcessingResult
MediaAudioProcessorContract::process(
  MediaStorage sourceStorage, MediaPath sourcePath, MediaAudioConversionSpec spec,
  MediaStorage targetStorage, MediaPath normalizedPath
): MediaAudioProcessingResult
```

Целевые `MediaPath` (`normalizedPath`, `posterPath`) строит **Handler (Application)** через доменные
фабрики `MediaPath::videoConversion`/`audioConversion`/`imageConversion(Poster)` ДО вызова `process()`
и передаёт готовыми; Infrastructure-процессор доменные пути не строит (граница arch.md: Infrastructure
→ только Contract + runtime). Процессоры зависят от `MediaFileServiceContract` (download/upload) и
`MediaConfig` (бинарь/таймаут/threads) — autowire в `MediaBootloader`.

Result-DTO (`Application/Dto`, несут доменные VO — потребляются handler-ом сразу, не сериализуются;
как существующий `MediaConversionResult`):
```text
MediaVideoProcessingResult{
  MediaMimeType normalizedMimeType, MediaFileSize normalizedSize,
  MediaPixelDimension width, MediaPixelDimension height, MediaDuration duration, MediaBitrate bitrate,
  MediaMimeType posterMimeType, MediaFileSize posterSize   // пиксельный размер постера = width/height транскода (решение 3), отдельных полей нет
}
MediaAudioProcessingResult{
  MediaMimeType normalizedMimeType, MediaFileSize normalizedSize,
  MediaDuration duration, MediaBitrate bitrate, MediaSampleRate sampleRate, MediaWaveform waveform
}
```

Файловый сервис (`MediaFileServiceContract` + `S3MediaFileService`): добавить файловые методы (стиль
контракта уже строковый — `getObjectContents`/`putObject` работают со строками, локальный путь —
такой же технический скаляр):
```text
downloadToFile(MediaStorage storage, MediaPath path): string            // локальный временный путь, владелец-вызыватель (процессор) чистит
uploadFromFile(MediaStorage storage, MediaPath path, string localFile, MediaMimeType mimeType): void
```

Выдача: `GetMediaUrlQuery.conversionType` — union трёх enum | null; `GetMediaUrlHandler` инжектит
`MediaVideoConversionRepository` и `MediaAudioConversionRepository`, выбор репозитория `match` по классу
enum. Новый запрос `GetAudioWaveformQuery{string mediaId}` / `GetAudioWaveformHandler` → `MediaWaveform`.

Профили: фиксированные форматы (путь конверсии берёт расширение из профиля, а не из оригинала):
```text
NormalizedMp4H264  -> mp4 (H.264+AAC),  MIME video/mp4, .mp4
NormalizedAacM4a   -> mp4/m4a (AAC),     MIME audio/mp4, .m4a
постер видео       -> JPEG,              MIME image/jpeg, .jpg  (MediaImageConversion type=Poster)
```

Новые доменные типы:
```text
enum MediaAudioConversionType: string { NormalizedAacM4a = 'normalizedAacM4a' }
VO  MediaSampleRate        (extends AbstractIntegerValue: MIN 8000, MAX 192000, supports())
VO  MediaWaveformPeakCount (extends AbstractIntegerValue: MIN 1, MAX 4096, supports())
VO  MediaWaveform          (readonly, JsonSerializable, НЕ Stringable; список int 0..255; fromPeaks(list<int>) валидирует
                            количество через MediaWaveformPeakCount и диапазон значений; peaks()/equals())
VO  MediaAudioConversionId (extends AbstractUuidV7Id: generate(), fromString())
Entity MediaAudioConversion (role media_audio_conversion, table media_audio_conversions; колонки как у видео
                            минус width/height плюс sample_rate, waveform; create(media, type, status, storage, path,
                            mimeType, size, duration, bitrate, sampleRate, waveform))
Collection MediaAudioConversionCollection (extends Collection<int, MediaAudioConversion>)
Repository MediaAudioConversionRepository (extends AbstractRepository<MediaAudioConversion>; findByMediaId -> коллекция)
Typecast   MediaWaveformTypecast (Infrastructure/Cycle, ColumnValueTypecast со статическими
                            castDatabaseValue()/uncastValue(): JSON <-> MediaWaveform; без них общий
                            ValueObjectCast его не вызовет; образец MediaMultipartPartCollectionTypecast)
```

`MediaWaveform::fromPeaks(list<int>)` валидирует И количество (через `MediaWaveformPeakCount`), И диапазон
каждого значения (0..255) — две разные проверки. Это валидация при **построении** (500-путь, в процессоре);
422-проверка запрошенного `waveformPeaks` из спека на приёме — отдельно, через
`MediaWaveformPeakCount::supports()`. `MediaSampleRate`/`MediaWaveformPeakCount` получают `supports()` от
`AbstractIntegerValue`; у `MediaWaveform` `supports()` нет (составной VO).

Правки переиспользуемых типов:
- `MediaDuration`/`MediaBitrate` переиспользуются для аудио — нейтрализовать их сообщения `NAME`
  («Длительность видео» → «Длительность», «Bitrate видео» → «Битрейт»), иначе аудио-ошибка скажет «видео».
- `MediaProcessingError`: добавить `audios` в regex фильтра путей (консистентно с `MediaPath`), чтобы путь
  аудио-конверсии тоже отсеивался из текста ошибки.

`Media` Entity: добавить `MediaAudioConversionCollection $audioConversions` + `HasMany`-связь и
инициализацию в `create()`.

`MediaPath`: в `assertValid` regex добавить `audios`; добавить фабрики
`videoConversion(storageKey, MediaVideoConversionType, ext)` → `videos/{shard}/{key}/{type}.{ext}` и
`audioConversion(storageKey, MediaAudioConversionType, ext)` → `audios/{shard}/{key}/{type}.{ext}`;
в `originalReady` добавить `MediaType::Audio → 'audios'` (Document по-прежнему бросает). Постер видео
кладётся существующей `imageConversion(storageKey, Poster, 'jpg')` → `images/{shard}/{key}/poster.jpg`.

Конфиг (`app/config/media.php` + `MediaConfig`): добавить `ffmpegBinaryPath` (env
`MEDIA_FFMPEG_BINARY`, default `/usr/bin/ffmpeg`), `ffprobeBinaryPath` (env `MEDIA_FFPROBE_BINARY`,
default `/usr/bin/ffprobe`), `ffmpegTimeoutSeconds` (env `MEDIA_FFMPEG_TIMEOUT_SECONDS`, default 1800),
`ffmpegThreads` (env `MEDIA_FFMPEG_THREADS`, default 0 = авто). Процессоры получают их через
типизированный `MediaConfig` (rules.md «env() только в конфигах»). Дефолтные пути подтверждаются при
сборке образа через `command -v ffmpeg`/`command -v ffprobe`.

## Фазы выполнения

Порядок выбран так, чтобы код собирался и тесты были зелёными после каждой фазы (фазы 1–3 — аддитивные;
несовместимая смена контракта и ветвление сведены в одну фазу 4).

### 1. Среда выполнения ffmpeg: зависимость, Docker, конфиг

Цель: подготовить фундамент — пакет, бинарь и настройки ffmpeg доступны, без изменения поведения.

Что сделать:
- `composer.json`: добавить `"php-ffmpeg/php-ffmpeg": "^1.4"` в `require` (тянет `symfony/process`).
- `docker/Dockerfile`: в `apt-get install` добавить пакет `ffmpeg`; в блок самопроверки — `ffmpeg
  -version >/dev/null` и `ffprobe -version >/dev/null` (по образцу проверок imagick/gd).
- `app/config/media.php` + `MediaConfig`: добавить четыре ffmpeg-поля (binary/probe/timeout/threads),
  значения через `env()` с дефолтами.
- `phpunit.xml`: добавить env `MEDIA_FFMPEG_BINARY`, `MEDIA_FFPROBE_BINARY`,
  `MEDIA_FFMPEG_TIMEOUT_SECONDS`, `MEDIA_FFMPEG_THREADS`. Значения `*_BINARY`/`*_PROBE` = фактическим
  путям в образе (`/usr/bin/ffmpeg`, `/usr/bin/ffprobe` из apt), чтобы интеграционные тесты фазы 3
  нашли бинари (иначе процессор упадёт «ffmpeg not found»).

Результат: пакет установлен, образ имеет ffmpeg/ffprobe, типизированный конфиг несёт ffmpeg-настройки.

Сценарии тестирования (after_each_phase):
- `MediaConfigTest` расширен: `ConfigMapper` маппит новые поля в `MediaConfig`.
- Сборка Docker-образа проходит самопроверку `ffmpeg -version`/`ffprobe -version`.

Проверка: `make phpstan`; `make test` (Unit/Kernel) зелёный; `composer.lock` обновлён; сборка образа
успешна.

### 2. Аудио-доменный стек + БД + расширение MediaPath

Цель: завести все доменные типы аудио и таблицу; расширить пути; всё аддитивно, существующий поток
картинок/видео не меняется.

Что сделать:
- `Domain/Enum/MediaAudioConversionType` (`NormalizedAacM4a`).
- `Domain/ValueObject`: `MediaSampleRate`, `MediaWaveformPeakCount`, `MediaWaveform`,
  `MediaAudioConversionId` (формы и границы — см. «Контракты реализации»).
- `Domain/Entity/MediaAudioConversion`, `Domain/Collection/MediaAudioConversionCollection`,
  `Repository/MediaAudioConversionRepository`.
- `Infrastructure/Cycle/MediaWaveformTypecast`; подключить `#[Column(typecast: ...)]` на колонке
  `waveform` сущности (образец `MediaMultipartPartCollectionTypecast`).
- `Domain/Entity/Media`: добавить связь `audioConversions` (`HasMany`) и инициализацию пустой коллекции
  в `create()`.
- `Domain/ValueObject/MediaPath`: `audios` в `assertValid` regex; фабрики `videoConversion()`,
  `audioConversion()`; в `originalReady` ветка `MediaType::Audio → 'audios'` (исчерпывающий `match`:
  Image/Video/Audio, `Document` по-прежнему бросает `InvalidDomainValueException`).
- `Domain/ValueObject/MediaProcessingError`: добавить `audios` в regex фильтра путей (консистентно с
  `MediaPath`).
- `Domain/ValueObject/MediaDuration`, `MediaBitrate`: нейтрализовать `NAME` («…видео» → «Длительность»,
  «Битрейт»), т.к. эти VO переиспользуются аудио-результатом и сообщением об ошибке.
- Миграция отдельным новым файлом в формате `YYYYMMDD.HHMMSS_0_create_media_audio_conversions_table.php`
  (как `20260521.184100_0_create_media_domain_tables.php`), синтаксис через Cycle migration API
  (`->addColumn(...)`, `->addIndex([...], ['unique' => true])`, `->addForeignKey(...)`); существующую
  миграцию не править. Схема — см. «Данные и БД».

Результат: домен и БД аудио готовы; пути для video/audio-конверсий и аудио-оригинала строятся; чтение
аудио-конверсий из БД работает. Приём аудио и обработка пока не включены.

Сценарии тестирования:
- Unit: `MediaWaveform`/`MediaSampleRate`/`MediaWaveformPeakCount` (границы, валидация, equals,
  JSON-сериализация волны), `MediaAudioConversionType`, `MediaPath` (новые фабрики, `assertValid` принимает `audios/...`, `originalReady` для Audio →
  `audios/source`, отказ `originalReady` для Document).
- Infrastructure: `MediaWaveformTypecast` (cast/uncast JSON ↔ VO; отказ на битом JSON, не-массиве и
  значении вне 0..255 — по образцу `MediaTypecastTest`).
- Feature/Repository: `MediaAudioConversionRepository::findByMediaId` возвращает коллекцию по
  сохранённой `MediaAudioConversion` (через миграцию).
- Доменный тест сущности `MediaAudioConversion::create`.

Проверка: `make phpstan`; `make test` зелёный; миграция применяется и откатывается.

### 3. Контракты и ffmpeg-процессоры + файловые методы S3 + классификация ошибок

Цель: добавить реальные процессоры видео и аудио и инфраструктуру вокруг них, не трогая текущий поток
обработки (процессоры пока не вызываются handler-ом).

Что сделать:
- `MediaFileServiceContract` + `S3MediaFileService`: `downloadToFile()`, `uploadFromFile()` (локальный
  временный файл во временном каталоге; стримовое чтение/запись без полного буфера в памяти).
- `Application/Contract`: `MediaVideoProcessorContract`, `MediaAudioProcessorContract`;
  `Application/Dto`: `MediaVideoProcessingResult`, `MediaAudioProcessingResult`.
- `Application/Exception/MediaProcessorFailedException` с `isTransient()` и фабриками
  `transient()`/`permanent()` (образец `MediaFileServiceFailedException`).
- `Infrastructure/FileService/FfmpegMediaVideoProcessor`, `FfmpegMediaAudioProcessor` на php-ffmpeg:
  владеют временными файлами и удаляют их в `finally`; берут бинарь/таймаут/threads из `MediaConfig`;
  оборачивают исключения php-ffmpeg в `MediaProcessorFailedException`; в репозитории не ходят и доменных
  сценариев не строят (только download/upload через `MediaFileServiceContract` и ffmpeg). Видео: probe →
  транскод mp4/H.264+AAC с фильтром масштабирования, сохраняющим пропорции, и округлением сторон до
  чётных (без обрезки и паддинга); опереться на авто-ориентацию ffmpeg (применяет display matrix) и
  обнулить флаг поворота на выходе → кадр-постер JPEG размером транскода. Аудио: probe → транскод m4a/AAC
  → волна — прямым вызовом ffmpeg в сырой PCM (`s16le`, моно, низкая частота), чтение сэмплов, деление на
  `waveformPeaks` интервалов, пик каждого, нормализация в 0..255 (php-ffmpeg готового массива амплитуд не
  даёт — только PNG-волну, поэтому PCM берётся своей обёрткой над процессом ffmpeg). Классификацию
  «временная/постоянная» вынести в приватный статический метод-«решатель» (`private static (\Throwable):
  bool`) для юнит-теста. Процессоры инжектят `MediaFileServiceContract` и `MediaConfig`. Для 100%
  покрытия (порог 100, `composer.json:90`): вызов ffmpeg обернуть в тонкую внутреннюю обёртку
  (метод/интерфейс), подменяемую в тесте, чтобы покрыть ветки скачивания/ffmpeg/загрузки/`finally` без
  реального таймаута; детерминированно нетестируемые строки бинаря (ветка таймаута) — `@codeCoverageIgnore`
  с обоснованием.
- `Infrastructure/Bootloader/MediaBootloader`: биндинги
  `MediaVideoProcessorContract→FfmpegMediaVideoProcessor`,
  `MediaAudioProcessorContract→FfmpegMediaAudioProcessor`.
- `Presentation/Job/ProcessMediaJob`: добавить ТОЛЬКО ИЛИ-ветку в признак временной ошибки (строка
  `$isTransient = ...`): `|| ($exception instanceof MediaProcessorFailedException && $exception->isTransient())`.
  `safeMessage()` НЕ трогать — он уже возвращает `PROCESSING_FAILURE_MESSAGE` для любого
  не-`MediaFileServiceFailedException` (отдельная ветка стала бы мёртвым кодом, rules.md «Нет мёртвого
  кода»). Текст `RetryException.reason` выбирать по типу: для ошибки процессора — сообщение о повторе
  обработки (отдельная константа), не `STORAGE_FAILURE_MESSAGE`.

Результат: процессоры и файловые методы готовы и протестированы изолированно; Job классифицирует ошибки
процессора; основной поток не изменён.

Сценарии тестирования:
- Feature/Infrastructure (реальный ffmpeg в Docker): фикстуры генерируются в тесте через ffmpeg `lavfi`
  (`testsrc`/`sine`), без бинарных файлов в репозитории. Обязательные ветки `process()`: успех (видео →
  mp4 + постер с метаданными; аудио → m4a + волна нужной длины), ошибка скачивания, ошибка ffmpeg (битый
  вход → постоянная), ошибка загрузки результата, удаление временных файлов в `finally` при любом исходе,
  классификация временная и постоянная.
- Feature/Infrastructure: `downloadToFile`/`uploadFromFile` против MinIO (round-trip).
- Unit: метод-«решатель» (фабрикуемые исключения → временная/постоянная); постоянная на битом входном
  файле; `MediaProcessorFailedException`.
- Feature/Flow: `ProcessMediaJobTest` дополнен — `MediaProcessorFailedException::transient()` →
  `RetryException` + WARN; `permanent()` → проброс + ERROR.

Проверка: пересобрать Docker-образ (фаза 1 уже добавила ffmpeg) ДО прогона тестов фазы 3, иначе
ffmpeg-интеграция упадёт «ffmpeg not found». Затем `make phpstan`; `make test` зелёный (включая
ffmpeg-интеграцию в Docker).

### 4. Профили на тип, MediaConversionPlan, смена контрактов, ветвление обработки

Цель: переключить конвейер на `MediaConversionPlan` и включить реальную обработку всех трёх типов.

Что сделать:
- Переименовать `MediaConversionSpec` → `MediaImageConversionSpec`; добавить `MediaVideoConversionSpec`,
  `MediaAudioConversionSpec`, `MediaConversionPlan`. Обновить ВСЕ места старого имени и поля
  `conversions` (иначе сьют не соберётся): `ProcessMediaCommand`, `ProcessMediaHandler` (`@param`),
  `MediaUploaded`, `CompleteMediaUploadCommand`, `CompleteMediaUploadHandler::assertConversionsValid`,
  `MediaApplicationTestCase::conversionSpec`, конструкторы в тестах (`ProcessMediaJobTest`,
  `MediaProcessingFlowTest`, `ProcessMediaHandlerTest`, `CompleteMediaUploadHandlerTest`).
- `MediaUploaded`, `ProcessMediaCommand`, `CompleteMediaUploadCommand`: `conversions` → `plan`.
- `CompleteMediaUploadHandler`: валидация по типу выполняется ПОСЛЕ загрузки `media` из репозитория
  (нужен `media.type`); список «не своего» типа непуст → 422; диапазоны по типу — см. «Целевой алгоритм»
  (`waveformPeaks` — через `MediaWaveformPeakCount::supports()`). Обновить лог-контекст (счётчики плана
  по типам).
- `ProcessMediaHandler`: исчерпывающий `match (media.type)`; целевые `MediaPath` (normalized/poster)
  строит сам handler через `MediaPath`-фабрики до вызова процессора. Каждую ветку вынести в приватный
  метод — `buildImageConversions` (переименовать текущий `buildConversions`), `buildVideoConversions`,
  `buildAudioConversions` — чтобы `handle()` остался ≤ ~40 строк (rules.md «Короткие методы»). image — из
  `plan.image`; video — `videoProcessor.process(...)` → `MediaVideoConversion` + `MediaImageConversion(Poster)`;
  audio — `audioProcessor.process(...)` → `MediaAudioConversion` с волной; оригиналы `copyObject` по типу.
  Инжектить `MediaVideoProcessorContract`, `MediaAudioProcessorContract` (конструктор станет 7
  зависимостей — допустимо, фасад не вводим). Инвариант: `processor.process` идемпотентен по перезаписи
  детерминированных путей и не оставляет видимых эффектов до финального flush.
- `Application/Service/MediaTypeResolver`: принимать `audio/*` → `MediaType::Audio` (Document остаётся
  422). Обновить докстринг. Проверить, что приём аудио зависит только от `MediaUploadSpec.allowedMimeTypes`
  потребителя и резолвера: `MediaMimeType` собственного белого списка не имеет (валидирует формат/длину),
  `audio/mp4`/`audio/mpeg` проходят.
- `DeleteMediaHandler`: инжектить `MediaAudioConversionRepository` и добавить третий цикл удаления
  объектов аудио-конверсий из S3 (сейчас обходятся только image+video). Постер видео — это
  `MediaImageConversion`, он уже попадёт в image-цикл, отдельной чистки постера не нужно (не задваивать).

Результат: полный конвейер режет картинки, видео (mp4 + постер) и аудио (m4a + волна); приём аудио
включён; удаление чистит все конверсии.

Сценарии тестирования:
- Feature/Application `ProcessMediaHandlerTest`: image/video/audio (процессоры подменяются `createStub`,
  не `createMock`+`method`) — создаются верные конверсии, постер для видео, волна для аудио;
  идемпотентность на `ready` (no-op) И повторный прогон video/audio после `ProcessingFailed` (перезапись
  детерминированных путей без конфликта); оригинал переложен по верному пути; width/height/duration/
  bitrate берутся из результата процессора.
- Feature/Application `CompleteMediaUploadHandlerTest`: валидный план по типу проходит; кросс-тип → 422;
  выход за диапазон по каждому типу → 422; для video/audio пустой, дублирующий или множественный профиль
  → 422; в outbox кладётся `plan`.
- Unit `MediaTypeResolverTest`: `audio/*` → Audio; документы/прочее → 422.
- Feature/Application `DeleteMediaHandlerTest`: удаляются объекты всех конверсий, включая постер.
- Feature/Flow `MediaProcessingFlowTest`: сквозной поток video и audio.
- Unit (Outbox): round-trip `MediaUploaded{plan}` через `ValinorOutboxMessageSerializer` (образец
  `OutboxMessageSerializerTest`) — вложенный `MediaConversionPlan` из примитивов+enum восстанавливается.

Проверка: `make phpstan`; `make test` зелёный.

### 5. Выдача результатов: URL на три типа + волна

Цель: дать потребителю ссылки на все конверсии и числа волны.

Что сделать:
- `GetMediaUrlQuery.conversionType` → union трёх enum | null; `GetMediaUrlHandler` — приватный
  `findConversion` через `match` по классу enum выбирает `MediaImageConversionRepository`/
  `MediaVideoConversionRepository`/`MediaAudioConversionRepository`, дальше прежняя логика
  `publicUrl`/`presignGet`. Инжектить новые репозитории. HTTP-входа у Media нет, поэтому выбор enum по
  строке в Spiral Filter (autocast в один BackedEnum) сейчас не нужен — Query создаётся внутренними
  вызовами/тестами с конкретным enum; авторазбор union отложен до появления роута.
- Новый запрос `Application/Query/GetAudioWaveform`: `GetAudioWaveformQuery{mediaId}`,
  `GetAudioWaveformHandler` → `MediaWaveform` (нормализованная аудио-конверсия; не ready/не аудио/нет
  конверсии → `NotFoundException`).

Результат: URL доступен для image/video/audio-конверсий и оригинала; волна отдаётся отдельным запросом
числами.

Сценарии тестирования:
- Feature/Application `GetMediaUrlHandlerTest`: URL для image-, video-, audio-конверсии и оригинала;
  public → `publicUrl`, private → `presignGet` с TTL; несуществующая конверсия → 404.
- Feature/Application `GetAudioWaveformHandlerTest`: успех (числа волны); не-аудио/не-ready/нет
  конверсии → 404.
- Покрытие выдачи — Feature/Application-тестами (роут-интеграционных тестов нет, т.к. у Media нет
  HTTP-роутов; правило «каждый роут покрыт» к этим Query не применяется).

Проверка: `make phpstan`; `make test` зелёный.

## Тесты

Стратегия: **after_each_phase** — каждая фаза 1–5 завершается своими unit/feature/integration-тестами
(перечислены в фазах). Дублёры берём из существующих: моки контрактов (`MediaFileServiceContract`,
процессоры), `FakeS3Client`/`FakeS3ClientProvider` для S3-интеграции, `RecordingMediaLogger` для
проверки уровней логов, `ThrowingProcessMediaCommandBus` для классификации в Job; база
`MediaApplicationTestCase`. Реальные ffmpeg-интеграционные тесты (фаза 3) гоняются в Docker, где есть
бинарь; нужны маленькие медиа-фикстуры (короткий mp4 и короткий аудио). Новые дублёры контрактов
процессоров — через `createStub()` + `willReturn*()`, не `createMock()`+`method()` (rules.md «Стаб вместо
expects()»; gate `failOnPhpunitDeprecation`). Классификацию ошибок ffmpeg проверяем юнит-тестом приватного
статического метода-«решателя» (временная/постоянная) и интеграционно постоянная на битом входе;
детерминированно нетестируемые строки вызова бинаря закрыты тонкой обёрткой или `@codeCoverageIgnore`.
Раунд-трип нового `MediaUploaded{plan}` через `ValinorOutboxMessageSerializer` — отдельным unit-тестом.
Финальный гейт — `make qa` (`cs` + `phpstan` + `test-coverage` с порогом 100%): покрыть все новые ветки,
включая `match`-ветки по типу, валидацию по типу в `CompleteMediaUpload`, выбор репозитория в
`GetMediaUrl` и оба исхода классификации ошибок.

## Логирование

Стратегия: **debug_precise**. Точные DEBUG-логи на каждом значимом шаге с camelCase-контекстом:
- `ProcessMediaHandler`: DEBUG в начале каждой ветки `match` (`mediaId`, `type`), DEBUG на каждую
  созданную конверсию (`type`, `path`, `size`), итоговый DEBUG «медиа готово» со счётчиками конверсий
  по типам.
- Процессоры (Infrastructure): DEBUG «оригинал скачан», DEBUG результата probe (`durationMs`,
  `bitrate`, для видео `width`/`height`), DEBUG «транскод готов» (`outputSize`), DEBUG
  «постер/волна извлечены», DEBUG «временные файлы удалены». Технический ffmpeg-контекст допустим во
  внутреннем DEBUG, но не уходит пользователю.
- `CompleteMediaUploadHandler`: существующий DEBUG + счётчики плана по типам.
- `GetMediaUrlHandler`/`GetAudioWaveformHandler`: DEBUG на резолв (`mediaId`, выбранный тип).
- Ошибки ffmpeg не логируются внутри процессора (он бросает типизированное исключение): WARN
  (временная ошибка, повтор ожидаем) / ERROR (terminal) пишет `ProcessMediaJob` на границе — как сейчас.
  INFO не используется (это не ключевые бизнес-события). Пользователю — только безопасный текст из
  набора `MediaProcessingError`.

## Документация и эксплуатация

- README модуля Media (`app/src/Modules/Media/README.md`): добавить video/audio в таблицу профилей и
  процессоров, выдачу URL для трёх типов, `GetAudioWaveform`, ffmpeg-обработку, классификацию ошибок
  процессора, новые ffmpeg-настройки конфига.
- `.env`-пример/документация окружения: `MEDIA_FFMPEG_BINARY`, `MEDIA_FFPROBE_BINARY`,
  `MEDIA_FFMPEG_TIMEOUT_SECONDS`, `MEDIA_FFMPEG_THREADS`.
- Runbook выката (предусловие миграции/релиза): несовместимая смена содержимого `MediaUploaded`
  (`conversions` → `plan`). Перед выкатом **слить очередь RabbitMQ и outbox** — зависших старых
  сообщений быть не должно (сквозного потребителя в рабочей среде нет). Зафиксировать как предусловие.
- Принятое ограничение среды: длинные видео ограничиваются `MediaUploadSpec.maxSize` (потребитель) и
  `MEDIA_FFMPEG_TIMEOUT_SECONDS`. Путь отхода при упоре — вынести транскодирование в Temporal
  (отклонённый вариант research, Temporal в проекте уже поднят). Берётся как осознанный компромисс ради
  единообразия с картинками.
- Осиротевшие объекты при терминальной ошибке: video/audio-процессор грузит результаты в целевой бакет
  ДО финального flush (как и текущий поток картинок). При постоянной ошибке после загрузки строк
  конверсий в БД нет, поэтому `DeleteMedia` их не видит. Осознанное ограничение, совпадающее с поведением
  картинок: пути детерминированы (повтор перезаписывает), реклейм терминальных сирот — будущий
  storage-sweep (сверка префикса бакета с БД); try-catch в Handler не вводим (rules.md).
- Вне этой задачи (решение пользователя): переключение ленты `Posts` на нормализованные video/audio
  (mp4 + постер, m4a + волна). Сейчас лента через `FindMediaUrl` показывает оригинал; Media отдаёт
  результаты своими запросами (`GetMediaUrl`/`GetAudioWaveform`), а подключение ленты — отдельная задача
  через границу Media→Application. `FindMediaUrl`/`PostViewAssembler` в этой задаче не трогаем.

## Изменения после мета-ревью

### После моделей (haiku, sonnet, opus)

- **+ Добавлено:**
  - Стратегия 100% покрытия ffmpeg-процессоров: приватный статический «решатель» transient/permanent +
    тонкая обёртка над php-ffmpeg для подмены в тесте + `@codeCoverageIgnore` на детерминированно
    нетестируемой ветке таймаута бинаря (иначе `make qa` с порогом 100 — красный, блокер).
  - Round-trip unit-тест нового `MediaUploaded{plan}` через `ValinorOutboxMessageSerializer`.
  - `DeleteMediaHandler` инжектит `MediaAudioConversionRepository` и чистит аудио-объекты из S3 (постер
    уже покрыт image-циклом, не задваивать).
  - Предусловие: пересобрать Docker-образ с ffmpeg ДО тестов фазы 3; `MEDIA_FFMPEG_*` в `phpunit.xml` =
    фактическим путям бинарей в образе.
  - Тест повторного прогона video/audio после `ProcessingFailed` (идемпотентность по перезаписи) и
    проверка, что `MediaMimeType` не имеет собственного аудио-whitelist.
- **~ Изменено:**
  - `ProcessMediaJob`: меняется ТОЛЬКО ИЛИ-ветка признака временной ошибки; `safeMessage()` не трогаем (иначе
    мёртвый код).
  - `ProcessMediaHandler.handle()` дробится на `buildImageConversions`/`buildVideoConversions`/
    `buildAudioConversions` (≤ ~40 строк); целевые `MediaPath` строит handler, не Infrastructure-процессор.
  - Перечислены ВСЕ точки переименования `MediaConversionSpec` и смены `conversions → plan` (команды,
    сообщение, handler, конкретные тесты).
  - `MediaDuration`/`MediaBitrate`: нейтрализовать `NAME` при переиспользовании аудио; `MediaProcessingError`
    regex дополнить `audios`. Формат имени миграции — `YYYYMMDD.HHMMSS_0_`.
  - Новые дублёры контрактов — через `createStub()` (gate `failOnPhpunitDeprecation`).
- **− Убрано:**
  - Дублирующие поля `posterWidth`/`posterHeight` из `MediaVideoProcessingResult` (= `width`/`height`
    транскода по решению 3).
- **Отклонено:**
  - Ужесточение Docker-самопроверки до `command -v ... || exit 1` — не нужно: блок под `set -eux`,
    падение `ffmpeg -version`/`ffprobe -version` и так прерывает сборку (консистентно с `php -m | grep`).
  - Отдельный per-type тест unique `(media_id, type)` для idempotency insert — избыточно: ограничение
    задаёт уникальный индекс схемы, повтор уже покрыт тестом повторного прогона.

## Реакция на ревью

Кросс-ревью: `docs/plans/2026-06-20_21-48_media-video-audio-ffmpeg_review.md` (Codex CLI 0.141.0).

**Внесено (бесспорное):**
- Для video/audio требуется ровно один профиль; дубли типов в списке → 422 (защита до S3-записей).
- Конкретизирована волна аудио (сырой PCM `s16le` моно прямым вызовом ffmpeg, не PNG) и видеоповорот
  (авто-ориентация ffmpeg + обнуление флага поворота).
- `RetryException.reason` выбирается по типу ошибки; `safeMessage()` не трогаем (иначе мёртвый код).
- Точные PHPDoc `list<...>` в `MediaConversionPlan` + round-trip тест Valinor.
- `MediaWaveformTypecast` — критерий готовности: статические `castDatabaseValue()`/`uncastValue()`.
- Перечислены обязательные ветки тестов ffmpeg-процессоров (успех/скачивание/ffmpeg/загрузка/`finally`/
  классификация); фикстуры генерируются через ffmpeg `lavfi`.
- Тесты `MediaPath`: `assertValid` для `audios/...`, `originalReady` Audio и отказ Document.
- Процессор явно не ходит в репозитории и не строит доменные сценарии (только download/upload + ffmpeg).
- Ограничение про осиротевшие объекты при терминальной ошибке (как у картинок) задокументировано.
- Заменены англицизмы в русском тексте плана («транзиентная» → «временная», «seam» → «обёртка»);
  `MediaBitrate`/`MediaDuration` `NAME` → нейтральные («Битрейт», «Длительность»).

**Спорное (вынесено пользователю и решено):**
- Переключение ленты `Posts` на нормализованные video/audio (через `FindMediaUrl`/`PostViewAssembler`)
  — **вне этой задачи**. Media только производит конверсии и отдаёт их своими запросами; подключение
  ленты — отдельная задача. Зафиксировано в «Документация и эксплуатация».

**Отклонено:**
- Ужесточение Docker-самопроверки до `command -v ... || exit 1` — не нужно: блок под `set -eux`, падение
  `ffmpeg -version`/`ffprobe -version` и так прерывает сборку.
- Отдельный per-type тест unique `(media_id, type)` на конкурентный insert — избыточно: ограничение даёт
  уникальный индекс, повтор покрыт тестом повторного прогона.

## Проверка (end-to-end)

1. Собрать Docker-образ — самопроверка `ffmpeg -version`/`ffprobe -version` проходит.
2. `make phpstan` — без ошибок.
3. `make test` — Unit/Kernel/Feature зелёные, включая ffmpeg-интеграцию в Docker.
4. `make qa` — `cs` + `phpstan` + покрытие 100%.
5. Ручной сквозной (опционально, eda-manual-test): загрузить короткое видео → дождаться обработки →
   `GetMediaUrl` отдаёт ссылку на нормализованный mp4 и постер; загрузить аудио → `GetMediaUrl` на m4a,
   `GetAudioWaveform` отдаёт массив чисел.

## Прогресс выполнения
Журнал: `docs/executions/2026-06-20_22-19_media-video-audio-ffmpeg.md`

- [x] Фаза 1: среда ffmpeg (composer, Docker, конфиг, phpunit env, MediaConfigTest)
- [x] Фаза 2: аудио-доменный стек + БД + расширение MediaPath
- [x] Фаза 3: контракты и ffmpeg-процессоры + файловые методы S3 + классификация ошибок
- [x] Фаза 4: профили на тип, MediaConversionPlan, смена контрактов, ветвление обработки
- [x] Фаза 5: выдача URL на три типа + волна (GetAudioWaveform)
- [x] Обновить docs (README, .env) + финальная проверка (make qa) — покрытие 100%, гейт зелёный
