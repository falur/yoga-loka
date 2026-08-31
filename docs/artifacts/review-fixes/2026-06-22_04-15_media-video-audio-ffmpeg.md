---
review: docs/reviews/2026-06-22_04-15_media-video-audio-ffmpeg.md
date: 2026-06-22 04:15
status: done
---

# Фиксы по ревью: Media — реальная нарезка видео и аудио на ffmpeg (четвёртый круг)

Режим: `apply-optional`. Обязательных пунктов в ревью нет. Из четырёх замечаний два (3, 4)
применены как поведенческие правки кода + тесты, два (1, 2) отклонены с обоснованием. Гейт
`make qa` зелёный, покрытие держится 100%.

## Применённые правки
| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 3 | Аудио со встроенной обложкой (attached_pic) отклонялось как «не аудио» | `app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php`, `.../NativeAacAudioFormat.php` (`-vn`) | `tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php` (`testAudioProcessorTranscodesAudioWithEmbeddedCover` + `generateAudioWithCoverBytes`; `testAudioProcessorRejectsSourceWithoutAudioStream`), `tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php` (`testIsAttachedPictureTreatsNonArrayDispositionAsNotCover`) | ✓ применено |
| 4 | Контракт «mp4/H.264+AAC» не гарантирован для немого видео | `app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php` (тихая дорожка `anullsrc` вторым входом + `-shortest`) | `tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php` (`testVideoProcessorAddsSilentAacTrackForSilentInput` + `generateSilentVideoBytes`) | ✓ применено |
| 1 | Ассерт пропорций видео завязан на поведение масштабатора/версии ffmpeg | — | — | ✗ отклонено (см. ниже) |
| 2 | Внеплановое изменение файла `todo`, не относящееся к Media | — | — | ✗ отклонено (см. ниже) |

## Решения по замечаниям

- **Применено: 3** (bug, фактически рекомендовано к правке) — `FfmpegMediaAudioProcessor` больше не
  считает «есть видеопоток» = «не аудио». Введён `assertSourceIsAudio()`: вход — аудио, только если у
  него есть настоящий аудиопоток (`audios()->first() !== null`) и нет настоящей (не-обложечной)
  видеодорожки. Обложку альбома отличаем от реального видео по `disposition.attached_pic = 1`
  (`isAttachedPicture()`). При транскоде обложка выкидывается через `-vn` в `NativeAacAudioFormat`.
  Штатный mp3/m4a с обложкой теперь корректно обрабатывается в m4a + волну, а реальное видео
  по-прежнему отклоняется. Покрытие закрыто тремя тестами: интеграционным на аудио-с-обложкой
  (`attached_pic` → выход без `mjpeg`, с `aac`), интеграционным на немое видео без аудиопотока
  (терминальный отказ «не аудио» на проверке входа) и юнит-тестом на guard `!is_array(disposition)`
  через reflection (по образцу `LazyGhostMapperDefensiveTest` — реальный ffprobe всегда отдаёт массив,
  поэтому ветка недостижима через интеграцию, но покрыта прямым вызовом без `@codeCoverageIgnore`).

- **Применено: 4** (контракт H.264+AAC) — для немого входа (нет аудиопотока) `FfmpegMediaVideoProcessor`
  подмешивает тихую стереодорожку `anullsrc` вторым входом (`setInitialParameters(['-f','lavfi','-i',
  SILENT_AUDIO_SOURCE])`) и обрезает её по длине видео `-shortest`, чтобы выход всегда был
  H.264+AAC. Контракт «mp4/H.264+AAC» теперь соблюдается и для видео без звука. Зафиксировано
  интеграционным `testVideoProcessorAddsSilentAacTrackForSilentInput` (немой вход → в выходе есть
  и `h264`, и `aac`).

- **Отклонено: 1** — допуск (`assertEqualsWithDelta`) для соотношения сторон не добавлен. Это тот же
  пункт, что и в прошлых кругах: фикстура фиксирована (320x240 → рамка 640x480), inset-масштаб даёт
  точное 4:3, тест зелёный; замечание про устойчивость к гипотетической будущей смене фикстуры, а не
  текущий дефект. Само ревью помечает его как «правка не нужна сейчас, ранее отклонён»; новых
  аргументов нет. Вводить допуск ради гипотетического изменения — расширение scope без пользы.

- **Отклонено: 2** — файл `todo` не относится к коду модуля Media (две строки личных заметок). Это
  решается на этапе формирования коммита (`eda-commit`): `todo` не включается в коммит задачи Media.
  Код и тесты не трогаем — на модуль Media это не влияет.

Отклонённые в прошлых кругах optional повторно не открывались (typecast `\InvalidArgumentException`,
возврат `MediaWaveform` из Query, дедупликация `encode()`/`probeDurationMs()` в Abstract, инъекция
`LoggerInterface`/DEBUG плана в процессоры/Query, `first()` без фильтра + `match (true)` без `default`,
rotation-фикстура видео, допуск `assertEqualsWithDelta` для соотношения сторон) — новых аргументов нет.

## Финальная проверка
- **Гейт:** `make qa` через Docker (cs + PHPStan + один coverage-run на PCOV) — ✓ зелёный.
  1203 теста, 3944 ассерта, покрытие 100.00% при пороге 100.00%.
- **Линтер (cs):** php-cs-fixer — ✓ без замечаний.
- **PHPStan:** уровень max — ✓ No errors.
- **Заметки:** первый прогон `make qa` показал 99.97% — две защитные ветки фикса 3
  (`assertSourceIsAudio` без аудиопотока и guard `!is_array(disposition)`) не были покрыты тестами.
  Добавлены два теста (один интеграционный, один юнит через reflection), после чего покрытие вернулось
  к 100%. Сами правки кода фиксов 3 и 4 были применены в прошлом (прерванном) прогоне и не менялись.
</content>
</invoke>
