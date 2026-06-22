---
review: docs/reviews/2026-06-22_03-30_media-video-audio-ffmpeg.md
date: 2026-06-22 03:45
status: done
---

# Фиксы по ревью: Media — реальная нарезка видео и аудио на ffmpeg (третий круг)

Режим: `apply-optional`. Обязательных пунктов в ревью нет — по каждому из замечаний 1–7
решение принято самостоятельно с обоснованием. Покрытие держится 100%, гейт `make qa` зелёный.

## Применённые правки
| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 4 | `countByClass` считает через `foreach`-счётчик вместо пайплайна | `app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php` | покрыто существующими тестами handler-а (счётчики лога), поведение не изменилось | ✓ применено |
| 5 | Запуск ffmpeg для волны и постера дублируется и обходит `ffmpegThreads` | `.../FileService/AbstractFfmpegMediaProcessor.php`, `.../FfmpegMediaAudioProcessor.php`, `.../FfmpegMediaVideoProcessor.php` | `tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php` (+2 ✓), `.../Fixture/FfmpegCommandRecordingProcessor.php` (новый) | ✓ применено |
| 6 | Нет 422-сценария на несколько аудио-профилей | — | `tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php` (+1 ✓) | ✓ применено |
| 7 | Интеграционный видео-тест не закрепляет кодеки H.264/AAC | — | `tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php` (+ассерт codec_name) | ✓ применено |
| 1 | Пустой PCM-буфер молча даёт плоскую тишину без явного решения | `.../FfmpegMediaAudioProcessor.php` (комментарий в `buildWaveform`) | не требуется (комментарий, поведение не меняется) | ✓ применено |
| 2 | Асимметрия проверки дубликатов типов в валидации плана | `app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php` (комментарии в `assertVideoPlan`/`assertAudioPlan`) | не требуется | ✓ применено |
| 3 | Ассерт пропорций видео завязан на поведение масштабатора/версии ffmpeg | — | — | ✗ отклонено (см. ниже) |

## Решения по optional

- **Принято: 4** — чистый подсчёт переведён на collection-пайплайн `Collection::make(...)->filter(...)->count()`.
  Это прямо требует rules.md («Collection-пайплайны … `foreach` — только при побочных эффектах»). Изначальный
  вариант ревью `\array_filter($conversions, $fn)` отвергается кастомным PHPStan-правилом
  `gianTiaga.phpstanStrictRules.namedArgumentsRequired` (позиционный вызов с 2+ аргументами), поэтому
  выбран `Collection`-вариант из того же замечания, согласный со стилем соседнего `CompleteMediaUploadHandler`.
  Поведение не изменилось, тесты handler-а уже проверяют счётчики лога.

- **Принято: 5** — запуск внешнего ffmpeg для волны и постера вынесен в защищённый хелпер
  `AbstractFfmpegMediaProcessor::runFfmpeg(array $arguments)`. Он ставит таймаут из `MediaConfig` и при
  `ffmpegThreads > 0` добавляет `-threads <n>` сразу после бинаря, распространяя лимит потоков на
  оба прямых вызова (раньше его учитывал только путь через `FFMpeg::create`). Дублирование «бинарь +
  таймаут + запуск» убрано. Сам запуск процесса вынесен в `runFfmpegProcess(array $command)` —
  тестовый наследник подменяет его и проверяет состав команды без реального бинаря. Добавлены два
  юнит-теста: `-threads 2` попадает в команду при `ffmpegThreads > 0` и отсутствует при `0`. Это
  закрыло новую ветку и удержало покрытие 100%.

- **Принято: 6** — добавлен `testRejectsMultipleAudioProfiles` (два аудио-профиля → 422) по образцу
  `testRejectsMultipleVideoProfiles`. Закрывает плановый пробел: сценарий «несколько профилей → 422»
  теперь закреплён симметрично для видео и аудио.

- **Принято: 7** — в интеграционный `testVideoProcessorTranscodesToMp4WithPoster` добавлена проверка
  `codec_name` потоков выхода через ffprobe: видео `h264`, аудио `aac` (нормализованный файл
  скачивается во временный файл, проверяется, удаляется). Контракт плана «mp4/H.264+AAC» теперь
  зафиксирован тестом, смена кодека не пройдёт незамеченной.

- **Принято: 1** — выбор сделан явным коротким комментарием в `buildWaveform`: волна строится из того
  же файла, который выше успешно открыт как аудио, поэтому PCM непуст; полностью пустой буфер сюда не
  доходит, а плоская тишина из нулей — валидный результат, а не сбой. Guard с исключением не вводился:
  он создал бы недостижимую строку под `@codeCoverageIgnore` (как `audioStream === null` рядом),
  комментарий проще и не плодит мёртвый код. Поведение не изменилось.

- **Принято: 2** — в `assertVideoPlan`/`assertAudioPlan` добавлен однострочный комментарий, что
  проверка `count === 1` уже исключает дубликаты типа (поэтому отдельный блок уникальности, как в
  `assertImagePlan`, не нужен). Саму проверку не дублировали — она стала бы недостижимой.

- **Отклонено: 3** — допуск (`assertEqualsWithDelta`) для соотношения сторон не добавлен. Фикстура
  фиксирована (320x240 → рамка 640x480), inset-масштаб даёт точное 4:3, тест зелёный; само замечание
  про устойчивость к будущей смене фикстуры, а не текущий дефект. Вводить допуск ради гипотетического
  изменения фикстуры — расширение scope без пользы сейчас; при реальном расширении набора входов это
  будет уместно. Текущий точный ассерт сохранён.

Отклонённые в прошлых кругах optional повторно не открывались (typecast `\InvalidArgumentException`,
возврат `MediaWaveform` из Query, дедупликация `encode()`/`probeDurationMs()` в Abstract, инъекция
`LoggerInterface`/DEBUG плана в процессоры/Query, `first()` без фильтра + `match (true)` без `default`,
rotation-фикстура видео) — новых аргументов нет.

## Финальная проверка
- **Тесты + покрытие:** `make qa` (cs + PHPStan + coverage-run на PCOV) — ✓ зелёный.
  1199 тестов, 3933 ассерта, покрытие 100.00% при пороге 100.00%.
- **Линтер (cs):** php-cs-fixer — ✓ без замечаний.
- **PHPStan:** уровень max — ✓ No errors.
- **Заметки:** новый юнит-тест команды ffmpeg покрыл ветку `-threads`; реальный путь
  `runFfmpegProcess` (с `mustRun()`) покрывается интеграционными тестами волны/постера в Docker.
