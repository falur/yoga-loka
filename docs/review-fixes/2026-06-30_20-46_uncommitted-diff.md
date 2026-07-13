---
review: docs/reviews/2026-06-30_19-42_uncommitted-diff.md
date: 2026-06-30 20:46
status: done
---

# Фиксы по ревью: Незакоммиченный diff — пятый круг (доводка до 95/100)

Режим `apply-optional`. Обязательных замечаний в ревью нет — все 7 «на усмотрение автора».
По заданию: закрываемые optional закрыты конструктивно (реальной правкой), п.2 отклонён по
явному указанию.

## Применённые правки
| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | `kind` шире реальности (`MediaType` допускает `Document`) | `Domain/Enum/MediaConversionKind.php` (new), `Application/Dto/MediaConversionUrl.php`, `Infrastructure/FileService/MediaUrlService.php` | `FindMediaUrlHandlerTest.php` (kind-ассерты на новый enum) | ✓ применено (вариант Б) |
| 2 | Подмешаны `.php-cs-fixer single_quote` и дефолты `docs/settings.yaml` | — | — | ✗ отклонено (по указанию: настройки проекта, зона eda-commit) |
| 3 | 7 зависимостей / 3 репозитория ради `hasConversion` | `Application/Service/MediaConversionsChecker.php` (new), `Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php` | `RemoveMediaOriginalHandlerTest.php` (рев handler-helper) | ✓ применено |
| 4 | Инвариант `partSize()/partsCount()` только в докблоке | `Application/Contract/MediaUploadPlannerContract.php`, `Infrastructure/FileService/MediaUploadPlanner.php`, `Command/RequestMediaUpload/RequestMediaUploadHandler.php` | `MediaUploadPlannerTest.php` | ✓ применено (передача partSize аргументом) |
| 5 | `AppBootloaderTest` слабо проверяет `LocaleResolver` | — | `AppBootloaderTest.php` | ✓ применено (только тест) |
| 6 | Прямое чтение статуса вместо предиката | `Domain/Entity/Media.php` (`isOriginalRemoved()`), `Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php` | `MediaEntityTest.php` (+1 тест предиката) | ✓ применено (`DeleteMediaHandler` не тронут) |
| 7 | Тройное дублирование map в `conversionUrls()` | `Infrastructure/FileService/MediaUrlService.php` (`conversionUrlsOf()`) | покрыт `FindMediaUrlHandlerTest.php` | ✓ применено |

## Решения по optional
- **Принято: 1, 3, 4, 5, 6, 7.**
  - **1 (вариант Б):** заведён узкий доменный enum `MediaConversionKind` (image/video/audio) в
    `Modules/Media/Domain/Enum`, им типизирован `MediaConversionUrl::$kind`. Документа в конверсии
    не бывает — теперь потребитель с исчерпывающим `match` по `kind` не тянет мёртвую ветку
    `Document`. Проставление в `MediaUrlService` и ожидания в `FindMediaUrlHandlerTest` обновлены.
    Не переусложнение: проект и так требует enum для закрытых наборов вариантов (rules.md «Enum
    вместо строк»).
  - **3:** проверка «есть ли хотя бы одна конверсия» вынесена в Application-сервис
    `MediaConversionsChecker` (`Application/Service`, как существующий `MediaTypeResolver`), который
    инкапсулирует три репозитория конверсий. `RemoveMediaOriginalHandler` ужат с 7 до 5 зависимостей
    и больше не знает про каждый тип конверсии. Сервис покрыт через feature-тесты обработчика (ветки
    image-true / audio-only / без конверсий — все три `existsForMediaId` исполняются). Выбрана форма
    Application-сервиса, а не Query+QueryBus: проверка чисто внутренняя для модуля Media, дёргать шину
    из Command-обработчика было бы тяжеловеснее без пользы.
  - **4:** `partsCount()` теперь принимает уже полученный `MediaMultipartPartSize` аргументом; деление
    гарантированно идёт на тот же размер части, что записывается в `MediaMultipartUpload`. Инвариант
    держится сигнатурой, а не докблоком. Отдельный result-DTO (отклонён в круге 2) НЕ вводился —
    выбран более дешёвый путь передачи объекта.
  - **5:** тест сравнивает `resolve('zz') === LocaleConfig.default` (фактический default из
    контейнера) и `resolve($supported) === $supported` по каждому supported. Production-код не менялся.
  - **6:** добавлен предикат `Media::isOriginalRemoved()`, обработчик использует его вместо прямого
    `=== MediaStatus::ReadyOriginalRemoved`. `DeleteMediaHandler` сознательно НЕ тронут (вне changeset,
    расширило бы scope — как и просило задание).
  - **7:** три почти одинаковых `->toBase()->map(...)` блока свёрнуты в приватный `conversionUrlsOf()`
    с union-типом элемента (`MediaImageConversion|MediaVideoConversion|MediaAudioConversion`). Применено,
    т.к. union-тип безопасен под PHPStan (TValue у `Illuminate\Support\Collection` ковариантен) и убирает
    реальную обвязку-копипаст; добавление 4-го вида теперь — одна строка, а не новый блок.
- **Отклонено: 2.** По прямому указанию задания. Причины: (а) разделение/организация коммитов — зона
  `eda-commit`, не `fix-by-review` (здесь не коммитим); (б) `docs/settings.yaml` и правило
  `.php-cs-fixer single_quote` — намеренные настройки проекта, откатывать нельзя. Файлы
  `docs/settings.yaml` и `.php-cs-fixer.dist.php` не редактировались. Чтобы следующие ревьюеры не
  поднимали повторно без новых аргументов.

## Финальная проверка
- **Тесты:** `make test` (Docker) — ✓ OK (1288 tests, 4162 assertions).
- **PHPStan:** `make phpstan` (Docker) — ✓ No errors (level max).
- **Заметки:** обе проверки прогнаны через Docker (minio:9000 доступен только внутри сети). Изменения
  не закоммичены; индекс не сбрасывался. Новый код (`MediaConversionKind`, `MediaConversionsChecker`,
  `isOriginalRemoved()`, `conversionUrlsOf()`, новая сигнатура `partsCount()`) покрыт существующими и
  добавленными тестами.
