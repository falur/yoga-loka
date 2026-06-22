---
review: docs/reviews/2026-06-22_01-01_media-video-audio-ffmpeg.md
date: 2026-06-22 01:40
status: done
---

# Фиксы по ревью: Media — реальная нарезка видео и аудио на ffmpeg

Режим: `apply-optional`. В ревью нет пунктов «Править обязательно» — все 10 пунктов
(план 1, замечания 1–9) помечены «на усмотрение автора». По каждому принято решение
самостоятельно: полезные и дешёвые правки применены, расширяющие scope или конфликтующие
с принятым планом/паттерном проекта — отклонены с причиной.

## Применённые правки
| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| Баг 1 | Утечка временного файла в `downloadToFile` при сбое скачивания | `app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php` | `tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php` (1 ✓ новый) | ✓ применено |
| Баг 2 | Плоские нули в волне при `waveformPeaks > sampleCount` | `app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php` | `tests/Unit/Modules/Media/Infrastructure/FfmpegWaveformTest.php` (3 ✓ новый) + фикстура `WaveformExposingAudioProcessor.php` | ✓ применено |
| План 1 | Докблок `MediaProcessorFailedException::transient` обещал «нехватку ресурсов» как временную, но классификатор ловит только таймаут | `app/src/Modules/Media/Application/Exception/MediaProcessorFailedException.php` | покрыто существующими | ✓ применено (текст докблока приведён к фактическому поведению) |
| 4 | Голый `\RuntimeException` в достижимых guard-clause процессоров | `FfmpegMediaAudioProcessor.php`, `FfmpegMediaVideoProcessor.php`, `AbstractFfmpegMediaProcessor.php` | `FfmpegMediaProcessorIntegrationTest.php` (уже ждёт `MediaProcessorFailedException`) | ✓ применено |
| 5 | Англицизмы-кальки в русских докблоках/комментариях/README | `MediaFileServiceFailedException.php`, `MediaProcessorFailedException.php`, `AbstractFfmpegMediaProcessor.php`, `S3MediaFileService.php`, `RecordMediaProcessingFailureHandler.php`, `Media.php`, `ProcessMediaJob.php`, `MediaTypeResolver.php`, `README.md`, `ProcessMediaJobTest.php`, `MediaProcessingFlowTest.php` | покрыто существующими | ✓ применено |
| 6 | `ProcessMediaHandler::handle()` длиннее лимита ~40 строк | `app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php` | покрыто существующими | ✓ применено (вынес `persistReady()`) |
| 9 (часть) | Итоговый DEBUG-счётчик не по типам | `app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php` | покрыто существующими | ✓ применено (разбивка `imageConversions`/`videoConversions`/`audioConversions`) |
| 8 (часть) | PCM-константы без пояснения происхождения | `FfmpegMediaAudioProcessor.php` | — | ✓ применено (однострочные комментарии) |
| 3 | `\InvalidArgumentException` в typecast волны вместо доменного | — | — | ✗ отклонено (паттерн всего слоя, см. ниже) |
| 7 | Query Handler возвращает доменный VO мимо CQRS-конвенции | — | — | ✗ отклонено (санкционировано планом, см. ниже) |
| 8 (часть) | Дедупликация обёртки `encode()`/конвертации длительности в Abstract | — | — | ✗ отклонено (расширение scope/риск, см. ниже) |
| 9 (часть) | Инъекция `LoggerInterface` в процессоры и оба Query-обработчика | — | — | ✗ отклонено (большой scope, отклонение от плана, см. ниже) |

## Что конкретно сделано по применённым пунктам

- **Баг 1.** В `downloadToFile` обёрнут `catch (AwsException)`: если `SaveAs` успел записать частичный
  файл, он удаляется перед перебросом. Это законная очистка ресурса на границе Infrastructure
  (rules.md разрешает try-catch, который реально освобождает ресурс), без выноса нового типа исключения.
  Тест воспроизводит сценарий из ревью: handler пишет в `SaveAs`-путь, затем `getObject` бросает —
  проверяется отсутствие temp-файла после.
- **Баг 2.** В `buildWaveform` добавлен guard для пустого интервала (`start === end` при
  `sampleCount > 0`): берётся хотя бы один ближайший сэмпл (`end = min(start+1, sampleCount)`), а не
  молчаливый 0. При `sampleCount >= peaks` поведение не меняется. Метод сделан `protected` и покрыт
  быстрым юнит-тестом на детерминированных PCM-буферах (нормализация, граничные сэмплы −32768/32767,
  короткий буфер, максимум по интервалу).
- **Пункт 4.** Два достижимых guard теперь бросают `MediaProcessorFailedException::permanent(...)`
  напрямую. Чтобы избежать двойной упаковки в `encode()`, `classifyFailure` пробрасывает уже готовый
  `MediaProcessorFailedException` как есть.
- **Пункт 5.** Заменены только русские прозаические кальки: «транзиентный/ретраябельный» → «временный/
  повторяемый», «throttling» → «ограничение скорости», «scope» → «область», «trade-off» → «компромисс»,
  «retry/terminal» → «повтор/терминальный исход», «payload» → «полезные данные». Технические
  идентификаторы и строковые литералы AWS-кодов (`'Throttling'` и т.п.) не тронуты. В тестах исправлены
  прозаические комментарии; ключи data-provider (имена тест-кейсов) оставлены как технические идентификаторы.
- **Пункт 6.** Финальная часть `handle()` (перекладка оригинала + `markReadyMovedTo` + persist + run +
  итоговый лог) вынесена в `persistReady(...)`; `handle()` стал тонким.
- **Пункт 9 (часть).** Итоговый DEBUG-лог разбит на счётчики по типам (`imageConversions`/
  `videoConversions`/`audioConversions`) через приватный `countByClass(...)` — по образцу
  `CompleteMediaUploadHandler`.
- **Пункт 8 (часть).** К константам `PCM_FULL_SCALE`/`PCM_SIGNED_OFFSET`/`PCM_SIGNED_THRESHOLD`
  добавлены однострочные комментарии о происхождении (s16-максимум, ширина u16, граница знака).

## Решения по optional
- **Принято:** баг 1, баг 2, план 1, 4, 5, 6, 8 (только комментарии к PCM-константам), 9 (только
  разбивка счётчика по типам). Все — дешёвые, снижают риск или прямо требуются rules.md, без расширения
  scope и без конфликта с принятым планом.
- **Отклонено (чтобы следующие ревьюеры не открывали повторно без новых аргументов):**
  - **Замечание 3** (`\InvalidArgumentException` в `MediaWaveformTypecast`): это сложившийся паттерн всего
    typecast-слоя проекта (`MediaMultipartPartCollectionTypecast`, общий `ValueObjectCast`). Менять один
    класс — создать рассогласование; менять весь слой — расширение scope на несвязанный код вне ревью.
    Ревью само смягчило пункт до «не однозначное нарушение для одного класса». Это решение об
    консистентности всего слоя, а не точечная правка.
  - **Замечание 7** (`GetAudioWaveformHandler` возвращает `MediaWaveform`): возврат прямо санкционирован
    планом (разделы «Целевой алгоритм»/«Контракты реализации»: `GetAudioWaveformHandler → MediaWaveform`).
    HTTP-входа у Media нет, сырой VO наружу не течёт. Обёртка в Result DTO противоречила бы утверждённому
    плану и расширила бы scope; осознанное исключение из конвенции уже зафиксировано в плане.
  - **Замечание 8 (дедупликация `encode()`/`probeDurationMs()` в Abstract):** подъём шаблона затрагивает
    оба процессора и абстракцию ради маргинальной выгоды при гипотетическом третьем процессоре —
    расширение scope и риск регрессии на горячем пути обработки. Применена только дешёвая часть
    (комментарии к PCM-константам).
  - **Замечание 9 (инъекция `LoggerInterface` в процессоры и оба Query-обработчика + полный набор
    DEBUG-логов плана):** большой объём изменений (конструкторы, биндинги бутлоадера, перестройка тестов)
    ради DEBUG-логов; отклонение от фактической реализации плана. Применена дешёвая и полезная часть —
    разбивка итогового счётчика по типам. Если детальные DEBUG в процессорах/Query сочтут нужными — это
    отдельная задача с корректировкой стратегии логирования в плане.

## Финальная проверка
- **PHPStan:** `make phpstan` — ✓ (No errors).
- **QA-гейт (cs + phpstan + покрытие):** `make qa` — ✓ (cs OK, phpstan OK, 1193 теста / 3914 проверок
  зелёные, покрытие 100.00% при пороге 100%).
- **Заметки:** все правки прогнаны через Docker, как требует AGENTS.md. Все новые ветки (guard
  пустого интервала, очистка частичного файла, `countByClass`, новые тесты) покрыты — порог 100% не
  просел.
