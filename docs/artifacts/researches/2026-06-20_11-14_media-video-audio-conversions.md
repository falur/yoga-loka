---
title: Media — обработка картинок, видео и аудио (как режем каждый тип)
date: 2026-06-20 11:14
mode: strict
decision_mode: recommend_and_ask
status: reviewed
reviewer: codex
sources:
  rules: docs/rules.md
  arch: docs/arch.md
---

# Media — обработка картинок, видео и аудио (как режем каждый тип)

## Суть

Модуль `Media` должен уметь работать с картинками, видео и аудио (документы — вне работ по
прямому указанию). Картинки уже полностью обрабатываются: проверяем, как именно потребитель
управляет ими сейчас, и распространяем тот же подход на видео и аудио так, чтобы модуль мог
явно «сказать», как он режет каждый тип. Под «режет» понимается набор конверсий: для картинки —
ресайз и кроп; для видео — транскодирование в нормализованный формат и кадр-постер; для аудио —
транскодирование в нормализованный формат и волна амплитуд для показа прогресса воспроизведения
(как в Telegram).

Проблема: текущая модель конверсий заточена под картинку и в исполнении работает только для
неё. Для видео доменный каркас уже есть, но реальной нарезки нет (выполняется только серверное
копирование оригинала); аудио не принимается вообще. Нужна единая модель «профиль конверсии на
тип» плюс реальные процессоры видео и аудио на ffmpeg, а также способ отдать потребителю
результаты этих конверсий.

## Решение

### Как картинки контролируются из других модулей сейчас (проверка)

У потребителя две точки контроля, обе — параметрами вызова, ничего не зашито в общий конфиг:

```text
RequestMediaUpload(userId, MediaUploadSpec, MediaFileMeta)
  MediaUploadSpec{ allowedMimeTypes, maxSize, visibility, presignedTtl }
  -> потребитель решает: какие MIME и размер разрешены под этот случай.

CompleteMediaUpload(userId, mediaId, list<MediaConversionSpec>, parts?)
  MediaConversionSpec{ type: MediaImageConversionType, width:int, height:int }
  -> потребитель решает: какие профили картинки сгенерировать и в каком размере.
```

Источники: `MediaUploadSpec` и проверка — `RequestMediaUploadHandler.php:125-137`; список конверсий
и его проверка диапазона пикселей (1..100000) — `CompleteMediaUploadHandler.php:55,107-121`; список
доезжает до обработки через сообщение `MediaUploaded` (`MediaUploaded.php:21-24`) →
`ProcessMediaCommand.php:14-17`. Модуль «говорит, как режет» картинку через перечисление
`MediaImageConversionType` (thumbnail/preview/large/poster — `MediaImageConversionType.php:9-12`) и
контракт `MediaImageProcessorContract::resize` (cover-crop до width×height, формат = исходный mime —
`MediaImageProcessorContract.php:19-24`, `ProcessMediaHandler.php:104-138`).

Вывод: потребитель полностью управляет нарезкой картинки, а модуль декларирует доступные профили и
семантику ресайза. Эту же двойную поверхность (политика приёма + список профилей) переносим на видео
и аудио, плюс закрываем недостающую часть — получение URL результатов всех типов.

### Что уже есть, а что добавляем

```text
Image  ГОТОВО ПОЛНОСТЬЮ: enum, сущность, репозиторий, коллекция, процессор (Imagick),
       приём (MediaTypeResolver image/*), путь (images/), создание конверсий, выдача URL.

Video  ЕСТЬ КАРКАС, НЕТ НАРЕЗКИ: enum MediaVideoConversionType{NormalizedMp4H264},
       сущность MediaVideoConversion, MediaVideoConversionRepository, коллекция,
       связь Media::videoConversions, таблица media_video_conversions, приём video/*,
       путь videos/ для оригинала. НЕТ: процессора (ffmpeg), создания конверсий в
       ProcessMediaHandler, фабрики пути videoConversion(), постера, выдачи URL видео-конверсий.

Audio  НЕТ НИЧЕГО: ни приёма (MediaTypeResolver отклоняет audio/* как 422 —
       MediaTypeResolver.php:30-34), ни префикса пути audios/ (MediaPath.php:120),
       ни enum/сущности/репозитория/коллекции/процессора/волны.

Document  ВНЕ РАБОТ по указанию пользователя: остаётся 422, MediaPath для Document бросает.
```

### Целевая модель: конверсия на тип (как режем каждый тип)

Единый принцип: на каждый `MediaType` — каталог профилей (enum) и свой процессор (контракт),
который делает реальную нарезку; потребитель управляет списком профилей на тип.

```text
Тип     Профили (enum)                 Процессор (контракт)           Инструмент
----    --------------                 --------------------           ----------
Image   MediaImageConversionType       MediaImageProcessorContract    Imagick (Intervention v4)
        thumbnail/preview/large/poster resize: cover-crop W×H

Video   MediaVideoConversionType       MediaVideoProcessorContract    ffmpeg (php-ffmpeg) [новый]
        normalizedMp4H264              transcode + posterFrame
        (+ постер -> image-конверсия)

Audio   MediaAudioConversionType       MediaAudioProcessorContract    ffmpeg (php-ffmpeg) [новый]
        normalizedAacM4a               transcode + extractWaveform
        (+ волна амплитуд)
```

Профили (перечисления) на тип со своими параметрами (решение развилки 2 — отдельные на тип):
объединять в один `MediaConversionSpec` нельзя, потому что у аудио нет width/height, а у видео есть
битрейт и кодек; общий объект потянул бы nullable-примитивы и слишком широкие типы — прямое
нарушение `rules.md` («Явные типы вместо null», «Entity без примитивов», «Не заменять типизацию
ручными проверками»).

### Профили-объекты (примитив-дружественные, для сериализации сообщения)

Все профили — публичные `readonly`-DTO из примитивов и enum, чтобы `ValinorOutboxMessageSerializer`
восстанавливал их без приватных фабрик VO (так же, как нынешний `MediaConversionSpec` —
`MediaConversionSpec.php:9-22`):

```text
MediaImageConversionSpec{ type: MediaImageConversionType, width:int, height:int }   // = нынешний, переименовать
MediaVideoConversionSpec{ type: MediaVideoConversionType, width:int, height:int, videoBitrate:int, audioBitrate:int }
MediaAudioConversionSpec{ type: MediaAudioConversionType, bitrate:int, sampleRate:int, waveformPeaks:int }
```

Контейнер плана вместо голого `list<MediaConversionSpec>`:

```text
MediaConversionPlan{ image: list<...ImageSpec>, video: list<...VideoSpec>, audio: list<...AudioSpec> }
```

`MediaUploaded` и `ProcessMediaCommand` несут `MediaConversionPlan` (три примитивных списка) вместо
одного списка картинок. Это и есть точка, где модель «как режем» становится общей для трёх типов;
содержимое сообщения остаётся пригодным для Valinor (тот же контракт, что сегодня). В
`CompleteMediaUpload` проверяется только список, относящийся к типу медиа: видео-файл со списком
картинок → 422.

### Профили: точные форматы, расширения и режимы нарезки

Нормализация меняет контейнер и кодек, поэтому путь конверсии берёт расширение из профиля, а не из
расширения оригинала (в отличие от картинок, где формат сохраняется). Каждый профиль декларирует
своё расширение и MIME:

```text
normalizedMp4H264  -> контейнер mp4, видео H.264 + звук AAC, MIME video/mp4, расширение .mp4
normalizedAacM4a   -> контейнер mp4 (m4a), звук AAC,          MIME audio/mp4, расширение .m4a
постер видео       -> кадр JPEG/WebP, хранится как MediaImageConversion type=Poster
волна аудио        -> массив амплитуд (не файл), см. ниже
```

Режим нарезки видео (закрытие замечания о crop/contain/padding/повороте): вписываем кадр в рамку
W×H **с сохранением пропорций, без обрезки**, выравниваем стороны до чётных (H.264 требует чётные
размеры), применяем поворот по метаданным и очищаем флаг поворота. Кроп не используем, чтобы не
терять содержимое кадра; рамку-паддинг не добавляем, чтобы не зашивать цвет фона.

### Процессоры: сигнатуры и результаты

Контракты в `Application/Contract`, реализации на php-ffmpeg в `Infrastructure/FileService`. Вход —
локальный путь к файлу (не строка в памяти, см. ниже). Результат несёт фактические метаданные,
которые требуют конструкторы доменных сущностей конверсий.

```text
interface MediaVideoProcessorContract {
    transcode(string $sourceFile, MediaVideoConversionSpec $spec): MediaVideoConversionResult
    extractPoster(string $sourceFile, MediaPixelDimension $width, MediaPixelDimension $height): MediaConversionResult
    probe(string $sourceFile): MediaVideoProbe   // ffprobe: width,height,duration,bitrate
}
interface MediaAudioProcessorContract {
    transcode(string $sourceFile, MediaAudioConversionSpec $spec): MediaAudioConversionResult
    extractWaveform(string $sourceFile, int $peaks): MediaWaveform
    probe(string $sourceFile): MediaAudioProbe   // ffprobe: duration,bitrate,sampleRate
}

MediaVideoConversionResult{ localFile, mimeType, size, width, height, duration, bitrate }
MediaAudioConversionResult{ localFile, mimeType, size, duration, bitrate, sampleRate }
```

`extractPoster` возвращает тот же `MediaConversionResult`, что и картиночный процессор, поэтому кадр
кладётся как `MediaImageConversion` без отдельной сущности. Размер постера берётся от транскода видео
(решение развилки 4а): потребитель не передаёт картиночный профиль для видео, правило «картиночные
профили для видео → 422» остаётся в силе; постер — фиксированный побочный выход видео-профиля.

### Волна аудио «как в Telegram»

Решение развилки 4б: аудио в этой итерации — обязательный нормализованный транскод плюс **волна
амплитуд для показа прогресса воспроизведения**. «Как в Telegram» — это не картинка, а компактный
массив амплитуд (например, ~100 значений), по которому клиент сам рисует полоски и заполняет их по
ходу проигрывания. Статичный PNG этого не даёт, поэтому именно массив, а не `showwavespic`-картинка.

```text
MediaWaveform — value object: список малых целых (амплитуды), JsonSerializable.
Построение: ffmpeg декодирует оригинал в моно-PCM низкой частоты -> делим на N интервалов ->
            пик амплитуды каждого -> нормализуем в 0..максимум. N = waveformPeaks из профиля.
Хранение:   JSON-колонка waveform на строке нормализованной аудио-конверсии (MediaAudioConversion),
            через JSON-typecast (образец MediaMultipartPartCollectionTypecast).
Выдача:     встроенно числами (клиенту нужны сами значения для отрисовки), отдельным запросом
            GetAudioWaveform(mediaId) -> MediaWaveform; presigned-URL тут не подходит.
```

Компромисс: волна логически — характеристика исходного аудио, но привязка к строке нормализованной
конверсии приемлема, потому что та всегда создаётся для аудио; отдельная сущность под один список
была бы лишней.

### Получение URL результатов всех типов (закрытие пробела)

Сейчас выдача URL умеет только картинки: `GetMediaUrlQuery.conversionType` имеет тип
`MediaImageConversionType|null` (`GetMediaUrlQuery.php:14`), а `GetMediaUrlHandler` ходит только в
`MediaImageConversionRepository` (`GetMediaUrlHandler.php:24,57-62`). Значит результаты видео- и
аудио-конверсий потребителю недостижимы. Расширяем:

```text
GetMediaUrlQuery.conversionType: MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType|null
GetMediaUrlHandler: по классу enum выбирает нужный репозиторий конверсий (исчерпывающий match),
                    дальше та же логика public/presignGet, что и сейчас.
Волна отдаётся не через URL, а запросом GetAudioWaveform (числа встроенно).
```

Так модуль «говорит, как нарезал», и одновременно даёт ссылку на каждый нарезанный объект.

### Ветвление обработки по типу

`ProcessMediaHandler` (`ProcessMediaHandler.php:41-84`) получает исчерпывающий `match` по
`MediaType` (домен уже знает все 4 значения — `MediaType.php:9-12`):

```text
match (media.type):
  Image    -> картиночный процессор (Imagick); оригинал copyObject -> images/.../source.<ext>
  Video    -> probe(ffprobe) -> транскоды (ffmpeg) + кадр-постер; оригинал -> videos/.../source.<ext>
  Audio    -> probe -> транскоды (ffmpeg) + волна; оригинал -> audios/.../source.<ext>
  Document -> вне работ (MediaTypeResolver отклоняет 422)
```

Идемпотентность сохраняется по образцу картинок: детерминированные ключи от `storageKey`, конверсии
создаются только в финальном `persist+run()`, на статусе `ready` — повторный вызов ничего не делает
(`ProcessMediaHandler.php:46-52`, README модуля «Транзакционная дисциплина S3»).

### Файловый сервис: ffmpeg работает с файлами, не со строкой в памяти

Текущий контракт читает оригинал целиком в строку (`getObjectContents(): string`) и пишет строку
(`putObject(string $contents)` — `MediaFileServiceContract.php:85,90-95`). Для ffmpeg/ffprobe нужен
локальный путь к файлу, а видео может быть большим — держать его строкой в памяти неприемлемо.
Добавляем файловые методы и используем их только для видео и аудио (картинка остаётся на чтении в
память):

```text
downloadToFile(storage, path): string                       // вернуть локальный временный путь
uploadFromFile(storage, path, localFile, mimeType): void
```

Ответственность за временные файлы (закрытие замечания о владельце и очистке): владелец временных
путей — инфраструктурный ffmpeg-процессор. Он сам через `downloadToFile` получает входной файл,
создаёт выходные временные файлы и в блоке `finally` удаляет и входной, и выходные при любом исходе
(успех, ошибка ffmpeg, сбой загрузки результата). Application-Handler локальных путей не видит и за
очистку не отвечает.

### Классификация ошибок ffmpeg

Сейчас `ProcessMediaJob` считает повторяемой только ошибку `MediaFileServiceFailedException` и читает
её признак `isTransient()` (`ProcessMediaJob.php:57`). Ошибка ffmpeg-процессора в эту ветку не
попадёт и всегда станет терминальной. Вводим контрактное исключение процессора:

```text
MediaProcessorFailedException (App\Modules\Media\Application\Exception) с isTransient():
  битый/неподдерживаемый вход, нештатный код выхода ffmpeg на валидном вызове -> постоянная (terminal, ERROR)
  таймаут транскодирования, нехватка ресурсов процесса                        -> временная (RetryException, WARN)
Реализации процессоров в Infrastructure оборачивают исключения php-ffmpeg в этот тип.
ProcessMediaJob расширяет проверку: признак временной ошибки = MediaFileServiceFailedException
  ИЛИ MediaProcessorFailedException с isTransient().
```

Текст ошибки — из безопасного набора `MediaProcessingError`, сырой вывод ffmpeg наружу не уходит
(README модуля «Статусы и обработка ошибок»).

### Инструмент, среда выполнения и Docker (развилка 3)

```text
Пакет     php-ffmpeg/php-ffmpeg ^1.4 (стабильная 1.4.0 от 19.01.2026; ограничение PHP включает 8.5;
          требует установленных бинарей ffmpeg и ffprobe). Источник версии — Packagist (см. Итог).
Среда     тот же путь outbox -> ProcessMediaJob (RabbitMQ), что и для картинок — без Temporal.
Docker    FROM ubuntu:26.04, установка apt. Добавить пакет `ffmpeg` (даёт ffmpeg и ffprobe) и проверку
          `ffmpeg -version`/`ffprobe -version` в блок самопроверки образа (по образцу imagick/gd —
          docker/Dockerfile:35-36,66-69). Наличие пакета `ffmpeg` в репозитории базового образа и
          фактический путь к бинарям подтверждаются на этапе сборки образа через `command -v ffmpeg`/
          `command -v ffprobe` — это снимает риск неверного абсолютного пути.
Конфиг    app/config/media.php + MediaConfig: путь к бинарям и предел времени транскодирования —
          MEDIA_FFMPEG_BINARY, MEDIA_FFPROBE_BINARY (значения по умолчанию подставляются из найденного
          при сборке пути), MEDIA_FFMPEG_TIMEOUT_SECONDS, MEDIA_FFMPEG_THREADS. Процессор
          (Infrastructure) получает их через типизированный MediaConfig, не через env() (rules.md
          «env() только в конфигах»).
```

Принятый предел среды выполнения: длинные видео могут упереться в предел времени задачи RoadRunner
или предел видимости сообщения RabbitMQ. `ProcessMedia` намеренно без `#[Transactional]`, поэтому БД
и блокировку строки на время транскодирования не держим (`ProcessMediaHandler` без атрибута, README
«Транзакционная дисциплина S3»). Сдерживаем вход через `MediaUploadSpec.maxSize` от потребителя и
`MEDIA_FFMPEG_TIMEOUT_SECONDS`. Если упрёмся — задокументированный путь отхода: вынести
транскодирование в Temporal (отклонённый вариант B развилки 3), Temporal в проекте уже поднят
(`arch.md` «Поток Temporal»). Берём как осознанный компромисс ради единообразия с картинками.

### Совместимость сообщения при смене содержимого

Замена `conversions: list<MediaConversionSpec>` на `plan: MediaConversionPlan` в `MediaUploaded` —
несовместимое изменение содержимого сообщения. В рабочей среде сквозного потребителя ещё нет:
HTTP-входа, вызывающего `RequestMediaUpload`/`CompleteMediaUpload`, не существует, а `Posts` лишь
привязывает уже загруженные медиа по идентификатору
(`app/src/Modules/Posts/Application/Post/PostContentComposer.php:75-95`, README модуля
«Эксплуатация»). Поэтому зависших старых сообщений на момент выката не ожидается; на стенде перед
выкатом — слить очередь и outbox. Зафиксировать как предусловие миграции.

## Ответы на вопросы

| Развилка | Вопрос | Ответ пользователя |
|---|---|---|
| 1. Объём | Что покрыть по видео/аудио? | **Полная реализация сразу**: профили/процессоры/контракты + реальное ffmpeg-транскодирование видео и аудио, бинарь ffmpeg в Docker, конфиг профилей. |
| 2. Модель конверсий | Один общий профиль или на тип? | **Отдельные профили и процессоры на тип** (Image/Video/Audio). |
| 3. Инструмент/среда | Чем и где обрабатывать? | **php-ffmpeg в текущем RabbitMQ-Job** (php-ffmpeg/php-ffmpeg ^1.4, обработка в outbox→Job, ffmpeg/ffprobe в Docker). |
| 4а. Постер видео | Откуда параметры постера? | **Часть видео-профиля, размер от транскодирования**: процессор сам извлекает кадр и кладёт его как MediaImageConversion type=Poster; потребитель отдельный картиночный профиль для видео не передаёт. |
| 4б. Объём аудио | Что входит в аудио? | **Нормализованный транскод + волна как в Telegram**: массив амплитуд для показа прогресса воспроизведения (не PNG). |

Документы — вне работ по прямому указанию («Документы можно не трогать»): `MediaTypeResolver`
продолжает отклонять их 422, `MediaPath`/`originalReady` для Document бросает.

Проверка версии пакета: `php-ffmpeg/php-ffmpeg` последняя стабильная **1.4.0** (релиз 2026-01-19),
ограничение PHP `^8.0 || … || ^8.5` — совместимо с PHP 8.5 проекта; требует установленных бинарей
ffmpeg и ffprobe. Источник: Packagist (ниже). `intervention/image` остаётся `^4` (composer.json),
для аудио и видео не используется.

## Итог

Выбранный вариант: единая модель «профиль конверсии на тип». На каждый тип — своё перечисление
профилей и свой процессор-контракт; потребитель задаёт списки профилей на тип в едином
`MediaConversionPlan`, который доезжает до обработки через outbox. Видео и аудио режутся реальным
ffmpeg через `php-ffmpeg ^1.4` в текущем пути outbox→RabbitMQ-Job; для видео это транскод в mp4/H.264
плюс кадр-постер (хранится как картиночная конверсия), для аудио — транскод в m4a/AAC плюс массив
амплитуд для прогресса воспроизведения (как в Telegram, отдаётся числами, не картинкой). Оригинал и
конверсии лежат в одном хранилище по видимости; обработка ветвится исчерпывающим `match` по
`MediaType`; ffmpeg работает с локальными временными файлами, владельцем и уборщиком которых
выступает инфраструктурный процессор; ошибки ffmpeg классифицируются отдельным контрактным
исключением с признаком повторяемости. Получение URL расширяется на видео- и аудио-конверсии, волна
отдаётся отдельным запросом. Документы — вне работ.

Принятые ограничения: длинные видео ограничиваются размером входа и пределом времени ffmpeg, при
упоре путь отхода — Temporal; смена содержимого сообщения `MediaUploaded` требует слить очередь и
outbox перед выкатом (сквозного потребителя в рабочей среде ещё нет).

Источник версии пакета: https://packagist.org/packages/php-ffmpeg/php-ffmpeg

## Реакция на ревью

Кросс-ревью: `docs/researches/2026-06-20_11-14_media-video-audio-conversions_review.md` (Codex CLI).

**Принято:**
- [факт] Видео названо «новым» — добавлен раздел «Что уже есть, а что добавляем»: каркас видео уже
  существует, нарезки и выдачи URL нет.
- [факт] Недостижимость URL видео/аудио-конверсий — добавлен раздел «Получение URL результатов всех
  типов»: `conversionType` расширяется до объединения трёх перечислений, выбор репозитория по классу
  enum; волна отдаётся отдельным запросом.
- [развилка] Противоречие по постеру — вынесено пользователю (развилка 4а) и закрыто: постер —
  фиксированный побочный выход видео-профиля, размер от транскодирования.
- [развилка] Волна/превью «опционально» — вынесено пользователю (развилка 4б) и закрыто: волна
  амплитуд входит в эту итерацию как массив для прогресса воспроизведения.
- [риск] Классификация ошибок ffmpeg — добавлен `MediaProcessorFailedException` с `isTransient()` и
  расширение проверки в `ProcessMediaJob`.
- [риск] Владелец и очистка временных файлов — закреплено: владелец инфраструктурный процессор,
  удаление в `finally`.
- [конкретика] Сигнатуры процессоров и result-DTO — добавлены методы и объекты результата.
- [конкретика] Режим нарезки видео — выбран: вписывание с сохранением пропорций, чётные стороны,
  автоповорот по метаданным, без обрезки и паддинга.
- [конкретика] MIME и расширения нормализованных файлов — зафиксированы; путь конверсии берёт
  расширение из профиля, а не из оригинала.
- [архитектура] «Итог» переписан как выбранный вариант и принятые ограничения, без пошагового плана.
- [источник] Утверждение про путь к ffmpeg — смягчено: наличие пакета и путь подтверждаются при
  сборке образа через `command -v`, значения по умолчанию подставляются из найденного пути.
- [правила] Англицизмы в тексте заменены русскими словами (объём/рамки, содержимое сообщения, среда
  выполнения, защитная проверка, проверка запуска, повтор, временная ошибка, ветвление по типу);
  технические идентификаторы оставлены.
- [ссылка] Указан полный путь `app/src/Modules/Posts/Application/Post/PostContentComposer.php`.

**Отклонено:** нет.

**Спорное:** нет (по подтверждённой версии php-ffmpeg правок не требовалось — отмечено самим
ревьюером).
</content>
