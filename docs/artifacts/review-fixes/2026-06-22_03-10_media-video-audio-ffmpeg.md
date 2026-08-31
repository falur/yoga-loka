---
review: docs/reviews/2026-06-22_02-30_media-video-audio-ffmpeg.md
date: 2026-06-22 03:10
status: done
---

# Фиксы по ревью: Media — реальная нарезка видео и аудио на ffmpeg (второй круг)

Режим: `apply-optional`. В ревью нет пунктов «Править обязательно» — все 5 замечаний помечены
«на усмотрение автора». По каждому решение принято самостоятельно: полезные и дешёвые правки
применены, расширяющие scope или спекулятивные — отклонены с причиной. Осознанно отклонённые в
прошлых кругах optional повторно не открывались.

## Применённые правки
| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Остаточный англицизм «Payload» в русском докблоке `MediaUploaded` | `app/src/Modules/Media/Application/Message/MediaUploaded.php` | — (текст докблока) | ✓ применено |
| 2 | `spec.sampleRate` не применяется при транскоде аудио (bug) | `app/src/Modules/Media/Infrastructure/FileService/NativeAacAudioFormat.php`, `app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php` | `tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php` (+1 новый, 1 обновлён), `tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php` (+1 новый: вход 48000 ≠ профиль 44100) | ✓ применено |
| 3 | `first()` без фильтра по `$type` + `match (true)` без `default` в `GetMediaUrlHandler` | — | — | ✗ отклонено (future-proofing, см. ниже) |
| 4 | Недостижимые guard-ветки процессоров на голом `\RuntimeException` | `FfmpegMediaAudioProcessor.php` (2 ветки), `FfmpegMediaVideoProcessor.php` (1 ветка) | покрыто (`@codeCoverageIgnore`) | ✓ применено |
| 5 | Пробелы тестов: нет повторного прогона аудио после `ProcessingFailed`; видео-контракт ffmpeg | `tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php` (+1), `tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php` (усилены ассерты видео) | см. файлы | ◐ частично применено (rotation-фикстура отклонена, см. ниже) |

## Что конкретно сделано по применённым пунктам

- **Замечание 1.** В докблоке `MediaUploaded` «Payload — только примитивы/enum…» → «Полезные
  данные — только примитивы/enum…». Прозаический русский текст, под правило об англицизмах. Имена
  параметров `$payload`/`payload` (технические идентификаторы) не тронуты.

- **Замечание 2 (bug).** Корень: php-ffmpeg `Audio::save()` собирает команду только из `-acodec`,
  `-b:a`, `-ac` и `getExtraParams()` формата; у `DefaultAudio` нет `setAudioSampleRate` и нет хука для
  `-ar`. Единственный чистый штатный хук — `getExtraParams()` (его массив мёрджится в команду через
  `SimpleFilter`). Поэтому `NativeAacAudioFormat` теперь принимает `sampleRate` в конструкторе и отдаёт
  `['-ar', (string) $sampleRate]` из `getExtraParams()`. `FfmpegMediaAudioProcessor::runEncoding()`
  создаёт формат как `new NativeAacAudioFormat(sampleRate: $spec->sampleRate)`. Теперь профильная
  частота реально применяется к выходу, а не остаётся только валидацией на приёме.
  - Юнит-тест: добавлен `testNativeAacAudioFormatPassesProfileSampleRate` (проверяет `['-ar','22050']`),
    обновлён существующий `testNativeAacAudioFormatUsesNativeCodec` под новую сигнатуру.
  - Интеграционный тест: `testAudioProcessorAppliesProfileSampleRate` — фикстура `sine` генерируется на
    48000 Гц, профиль 44100 Гц, ассерт `$result->sampleRate->value() === 44100`. Вход ≠ профиль, как
    требует ревью.

- **Замечание 4.** Три недостижимые guard-ветки в `@codeCoverageIgnore`-блоках переведены с голого
  `\RuntimeException` на `MediaProcessorFailedException::permanent(...)` (тот же permanent-сбой, класс
  уже импортирован в обоих процессорах). Соответствие правилу «типизированные доменные исключения в
  источнике guard», правило не делает оговорки про недостижимость.

- **Замечание 5 (частично).**
  - **Повторный прогон аудио:** добавлен `testRerunsAudioAfterProcessingFailed` в
    `ProcessMediaHandlerTest` — симметрия с видео-аналогом, плюс проверка перезаписи детерминированных
    данных аудио (одна конверсия, `sampleRate` и `waveform` сохранены).
  - **Видео-контракт ffmpeg:** в `testVideoProcessorTranscodesToMp4WithPoster` усилены ассерты вместо
    «width/height > 0»: выход не превышает рамку профиля (≤640/≤480), стороны чётные (H.264), пропорции
    входа 320x240 (4:3) сохранены (сравнение округлённых соотношений сторон). Это фиксирует масштаб
    inset с сохранением пропорций и чётности.

## Решения по optional

- **Принято:** замечания 1, 2, 4, 5 (части «повторный прогон аудио» и «видео: пропорции/чётность/рамка
  профиля»). Все дёшевы, снижают риск или закрывают функциональный пробел; покрытие 100% не просело.
- **Отклонено (чтобы следующие ревьюеры не открывали повторно без новых аргументов):**
  - **Замечание 3** (`first()` без фильтра по `$type` для video/audio + `match (true)` без `default`):
    чисто future-proofing под расширение каталога, которого нет — каталоги
    `MediaVideoConversionType`/`MediaAudioConversionType` содержат ровно по одному профилю, поэтому
    `first()` всегда корректен. Само ревью даёт вариант «оставить как есть (приемлемо для каталога из
    одного профиля на тип)». Добавление фильтра сделало бы условие всегда-истинным (фактически мёртвым),
    а защитная `default`-ветка с типизированным исключением была бы недостижима и потребовала
    `@codeCoverageIgnore` (или просадила бы порог 100%). Сложность и покрытийный долг ради гипотетики —
    нецелесообразно; пункт безопасно закрывать при реальном добавлении второго профиля в enum.
  - **Замечание 5 — часть про rotation-фикстуру видео:** отклонено. Реальное поведение ffmpeg на
    повёрнутом входе на целевом образе не подтверждено (это прямо отмечено в самом ревью и в кросс-CLI
    блоке: «потребность в фикстуре с поворотом… запуск кода в мета-ревью не делаем»). Ассерт на
    rotation был бы спекулятивным и хрупким к версии ffmpeg в образе; авто-ориентация уже документирована
    в докблоке `FfmpegMediaVideoProcessor`. Пропорции/чётность/рамку профиля — главную часть пробела —
    закрыли усиленными ассертами без новой фикстуры.

### Повторно НЕ открыты (нет новых аргументов)
Осознанно отклонённые в прошлых кругах optional: typecast `\InvalidArgumentException`, возврат
`MediaWaveform` из Query, дедупликация `encode()`/`probeDurationMs()` в Abstract, полная инъекция
`LoggerInterface`/DEBUG-логов плана в процессоры и Query.

## Финальная проверка
- **Тесты + линтер + покрытие:** `make qa` (Docker) — ✓. cs OK, PHPStan «No errors»,
  1196 тестов / 3928 проверок зелёные, покрытие 100.00% при пороге 100%.
- **Заметки:** всё прогнано через Docker, как требует AGENTS.md. Новые/изменённые ветки (новый
  `getExtraParams()`, новые тесты) покрыты — порог 100% не просел.
