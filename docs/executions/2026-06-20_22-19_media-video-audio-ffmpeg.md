---
plan: docs/plans/2026-06-20_21-48_media-video-audio-ffmpeg.md
started: 2026-06-20 22:19
finished: 2026-06-20 23:44
status: done
---

# Журнал: Media — реальная нарезка видео и аудио на ffmpeg

Место выполнения: текущая ветка `main` (выбор пользователя).

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Фаза 1: среда ffmpeg | composer.json, docker/Dockerfile, app/config/media.php, MediaConfig.php, phpunit.xml, MediaConfigTest.php | MediaConfigTest расширен (4 поля) | done |
| 2 | Фаза 2: аудио-домен + БД + MediaPath | Enum/VO/Entity/Collection/Repository аудио, MediaWaveformTypecast, миграция media_audio_conversions, MediaPath (audios+фабрики+originalReady), MediaProcessingError, MediaDuration/MediaBitrate NAME, Media.audioConversions | MediaEnumTest, MediaValueObjectTest, MediaTypecastTest, MediaEntityTest, MediaRepositoryTest расширены; репозиторий аудио проверен на MinIO/БД | done |
| 3 | Фаза 3: процессоры ffmpeg | downloadToFile/uploadFromFile (контракт+S3), контракты+DTO процессоров, MediaProcessorFailedException, спеки video/audio, AbstractFfmpegMediaProcessor, Ffmpeg video/audio процессоры, NativeAacAudioFormat, биндинги бутлоадера, ProcessMediaJob (ИЛИ-ветка + причина повтора) | FfmpegMediaProcessorTest (unit), FfmpegMediaProcessorIntegrationTest (реальный ffmpeg, 16 зелёных), S3 round-trip + ошибки, ProcessMediaJobTest строки процессора | done |
| 4 | Фаза 4: план конверсий + ветвление | MediaConversionSpec→MediaImageConversionSpec, MediaConversionPlan, conversions→plan (MediaUploaded/ProcessMediaCommand/CompleteMediaUploadCommand/ProcessMediaJob), валидация плана по типу в CompleteMediaUploadHandler, match по типу в ProcessMediaHandler (build image/video/audio), MediaTypeResolver audio, DeleteMediaHandler чистит аудио, locale-ключи | MediaApplicationTestCase (хелперы plan), ProcessMediaHandlerTest, CompleteMediaUploadHandlerTest, MediaProcessingFlowTest (video/audio e2e), MediaTypeResolverTest, DeleteMediaHandlerTest, MediaUploadedSerializationTest (Valinor round-trip) — 88 Media Application+Flow зелёных | done |
| 5 | Фаза 5: выдача URL + волна | GetMediaUrlQuery union трёх enum, GetMediaUrlHandler выбор репозитория match по классу enum (+2 репозитория), GetAudioWaveformQuery/Handler | GetMediaUrlHandlerTest (image/video/audio/оригинал), GetAudioWaveformHandlerTest (успех + 404 ветки) — 14 зелёных | done |

## Заметки

- Стратегия проверок: phpstan гоняю после каждой фазы (быстро, без reset БД); полный сьют + покрытие 100% — финальным гейтом `make qa-build` (нужен `--build`, т.к. ffmpeg-бинарь в образе для интеграционных тестов фазы 3). Это согласовано со скиллом: «не гонять полный сьют на каждом шаге», финальная проверка — полный прогон.
- Фаза 1: `composer.lock` обновлён внутри Docker (php-ffmpeg/php-ffmpeg v1.4.0 + symfony/process 8.1, spatie/temporary-directory, evenement). Образ пересобран — самопроверка `ffmpeg -version`/`ffprobe -version` прошла. `make phpstan` зелёный.
- Фаза 3, решения по реализации (отклонения деталей плана в пользу его же цели «собирается после каждой фазы» и 100% покрытия):
  - Спеки `MediaVideoConversionSpec`/`MediaAudioConversionSpec` созданы в фазе 3 (их требуют контракты процессоров); переименование `MediaConversionSpec`→`MediaImageConversionSpec` и `MediaConversionPlan` — в фазе 4.
  - Постер видео извлекается прямым вызовом ffmpeg из нормализованного файла (размер постера = транскод), без повторного `open()` — убирает недостижимую ветку.
  - AAC: `FFMpeg\Format\Audio\Aac` жёстко требует `libfdk_aac` (нет в сборке Ubuntu) → свой `NativeAacAudioFormat` на нативном кодере `aac`.
  - Тестируемость: реальный вызов ffmpeg вынесен в `protected runEncoding()` (класс процессора не final), тест подменяет его в наследнике для веток ошибок/очистки; реальный ffmpeg покрыт интеграционным тестом в Docker.
  - `@codeCoverageIgnore` только на 3 детерминированно недостижимых защитных throw (нет потока/нет видеопотока/нет аудиопотока после успешного транскода; unpack('v*') не вернёт false). Авто-ориентация — на дефолтном поведении ffmpeg.
  - Образ `test-runner` собирается отдельно от `app-http` — пересобран с ffmpeg перед прогоном фазы 3.

## Финальная проверка

- `make qa` (cs + phpstan + один coverage-run PCOV, ParaTest 4 процесса) — зелёный.
  - php-cs-fixer: без замечаний.
  - PHPStan level max: `[OK] No errors`.
  - Тесты: 1189 тестов, 3909 проверок — все зелёные (Unit/Kernel/Feature, включая реальный ffmpeg
    в Docker и сквозные video/audio потоки).
  - Покрытие: **100.00%** при пороге 100% (`assert-coverage.php`).
- Docker-образ (`app-http` и `test-runner`) пересобран с пакетом `ffmpeg`; самопроверка
  `ffmpeg -version`/`ffprobe -version` при сборке прошла.
- Миграция `media_audio_conversions` применяется и откатывается (проверено в прогонах фаз 2–5;
  применяется ко всем worker-БД в coverage-run).

## Изменения в docs

- Предписаны планом и обновлены: README модуля Media (`app/src/Modules/Media/README.md`) и
  `.env.sample` (ffmpeg-переменные `MEDIA_FFMPEG_*`).
- `docs/rules.md` и `docs/arch.md` изменений не требуют: работа выполнена в рамках существующих
  правил и архитектуры (Application-контракты + Infrastructure-реализации, typecast-слой, outbox).
