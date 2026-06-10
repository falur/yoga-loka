---
review: docs/reviews/2026-06-10_02-14_media-upload-pipeline-uncommitted-draft.md
date: 2026-06-10 03:10
status: done
mode: apply-optional
---

# Фиксы по ревью: Media upload pipeline — незакоммиченный diff (после правок предыдущего ревью)

Режим `apply-optional`. Обязательных пунктов («править обязательно») в ревью нет. Два пункта
сверки с планом (№1 глобальный coverage-gate, №2 девиация INFO→DEBUG) — осознанные
пред-существующие отклонения, в коде править нечего. Все 8 замечаний — «на усмотрение автора»;
по каждому принято решение ниже. Приоритетные №6 и №7 (прямые нарушения правил) применены.

## Применённые правки
| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Инвариант `multipartThresholdBytes >= multipartPartSizeBytes` не проверяется | `app/src/Shared/Infrastructure/Configuration/Media/MediaConfig.php` | `tests/Unit/Shared/Infrastructure/Configuration/MediaConfigTest.php` (+1 ✓); `tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php` (подгонка под инвариант) | ✓ применено |
| 2 | `GetMediaUrl` ветвит публичность по visibility, путь — по conversion.storage | — | — | ✗ отклонено (контракт держит инвариант; future-proofing вне scope) |
| 3 | `MediaPath::extension()` может вернуть `''` | — | — | ✗ отклонено (перенос идентичной 500 без выгоды контракта, ломает осознанный тест) |
| 4 | `getObjectContents` материализует тело в память | — | — | ✗ отклонено (ревью: вне scope; смена контракта на `StreamInterface`) |
| 5 | `composer.json`: перестановка dev-пакетов + попутный bump | — | — | ✗ отклонено (не код; lock зафиксирован; вопрос чистоты коммита) |
| 6 | Абстрактное имя `$result` (`rules.md:33`) | `app/src/Modules/Media/Application/Command/Media/RequestMediaUpload/RequestMediaUploadHandler.php`; `app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php` | существующие media-тесты (рефакторинг имени, поведение неизменно) | ✓ применено |
| 7 | `foreach` для чистой трансформации (`rules.md:19`) | `app/src/Modules/Media/Domain/Collection/MediaMultipartPartCollection.php`; `app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php` | существующие media-тесты (поведение неизменно) | ✓ применено |
| 8 | Исключения контрактов в `Infrastructure/Exception` vs `Application/Exception` (`rules.md:42`) | — | — | ✗ отклонено (внутренние инфра-сбои, не контрактная поверхность; текущее размещение обосновано) |

## Решения по optional

### Принято

- **№6 (`$result` → конкретное имя / инлайн):**
  - `RequestMediaUploadHandler::handle` — переменная читалась 3 раза (присваивание, чтение
    `uploadMode`, return), инлайн неприменим → переименована в `$preparedUpload`.
  - `S3MediaFileService` — три одноразовых обращения к ключу `Aws\Result` (`createMultipartUpload`,
    `headObject`, `getObjectContents`) инлайнены прямо в извлечение нужного ключа
    (`...['UploadId'] ?? null`, `...['ContentLength'] ?? null`, `...['Body'] ?? null`) по
    `rules.md:26`. Абстрактное имя `$result` устранено во всех вхождениях. Поведение и проверки
    (`is_string`, `instanceof StreamInterface`, missing-content-length) сохранены.

- **№7 (`foreach` → `->map()`):**
  - `MediaMultipartPartCollection::jsonSerialize` и `S3MediaFileService::completeMultipartUpload`
    переведены на collection-пайплайн. Важная деталь: оба места строят массив **другого** типа
    (не `MediaMultipartPart`), поэтому прямой `$this->map(...)` на типизированной Illuminate-коллекции
    падал бы — `map()` возвращает `new static`, заново прогоняя конструктор `MediaMultipartPartCollection`
    с не-`MediaMultipartPart` элементами. Использован `->toBase()->map(...)->values()->all()`:
    `toBase()` даёт плоскую `Illuminate\Support\Collection`, поэтому реконструкции типизированной
    коллекции не происходит. Это подтверждено падением тестов на промежуточном шаге и зелёным
    прогоном после `toBase()`.

- **№1 (инвариант конфига multipart):**
  Добавлен guard в конструктор `MediaConfig`: при `multipartThresholdBytes < multipartPartSizeBytes`
  бросается `InvalidConfigValueException` (по образцу `CycleCollectionsConfig` — единственный другой
  config-DTO с конструктор-валидацией инварианта). Это реальная защита контракта: misconfig через
  env больше не сможет молча отправить файл «чуть больше порога, но меньше одной части» в multipart
  с единственной частью. Покрыт unit-кейсом `testRejectsMultipartThresholdSmallerThanPartSize`
  (100% новой ветки; класс `InvalidConfigValueException` поднят до 100%).
  Побочный эффект: feature-тест `testRequestsMultipartUploadWhenSizeReachesThreshold` намеренно
  использовал `threshold: 2048` с part size 8 MiB (ровно тот misconfig, что теперь запрещён) ради
  крошечной тестовой фикстуры. Тест переведён на валидную конфигурацию `threshold == partSize ==
  5_242_880` (минимум VO `MediaMultipartPartSize`), файл `5_242_880` → ветка multipart, `partsCount = 1`
  сохранён; helper `handler()` получил параметр `partSize`.

### Отклонено

- **№2** — `GetMediaUrl` ветвит публичность по `media.visibility`, путь берёт из `conversion.storage`.
  По текущему контракту `ProcessMedia` кладёт оригинал и конверсии в один `targetStorage` по той же
  visibility, поэтому рассинхрона нет. Альтернатива (ветвить по `storage` объекта) — future-proofing
  без текущего бага, расширяет scope. Ревью прямо допускает «оставить как есть (контракт держит
  инвариант)». README уже фиксирует «оригинал и конверсии в одном бакете по visibility».

- **№3** — guard пустого расширения в `MediaPath::extension()`. Отклонено: путь всегда строится
  фабриками (`originalUpload` гарантирует `source.<ext>`), поэтому пустая строка недостижима в
  проде. Главное — бросок исключения в `extension()` не улучшил бы исход для потребителя: текущий
  `sanitizeExtension('')` уже отвергает пустоту тем же `InvalidDomainValueException` (500). Правка
  лишь перенесла бы идентичную 500 на шаг раньше, при этом сломав осознанно написанный unit-кейс
  `assertSame('', ...)`, который документирует терпимое поведение accessor-а. Реальной защиты
  контракта нет — есть переусложнение. Мета-ревью уже сняло рекомендацию «добавить тест» (тест есть).

- **№4** — потоковое чтение `getObjectContents`. Ревью прямо помечает вне scope: контракт отдаёт
  `string`, стримить нельзя без смены сигнатуры на `StreamInterface`; для текущего scope (изображения,
  Imagick требует полного буфера) приемлемо. Связанный фикс «не читать оригинал без конверсий»
  применён в прошлом цикле (№4). Преждевременная оптимизация.

- **№5** — перестановка трёх `gian-tiaga/*` в `composer.json` + попутный bump в `composer.lock`.
  Не код, вопрос чистоты коммита; lock уже зафиксирован, baseline зелёный. Ревью: «ничего в коде».

- **№8** — перенос `MediaFileServiceException`/`MediaImageProcessorException`/
  `MediaStorageNotConfiguredException` из `Infrastructure/Exception` в `Application/Exception`.
  Осознанное решение оставить как есть. По `rules.md:42` слой определяется контрактом, к которому
  относится исключение. В отличие от прецедента `OutboxMessageLoadingException` (часть
  поверхности загрузчика — он его и порождает как осмысленный исход), эти media-исключения:
  не объявлены в контрактах (`@throws` отсутствует), не ловятся ни одним Application-потребителем,
  и представляют чисто инфраструктурные сбои («SDK вернул мусор», «процессор упал», «бакет/драйвер
  не сконфигурирован»). Это внутренние guard'ы инфраструктуры, а не контрактная поверхность,
  поэтому размещение в `Infrastructure/Exception` — корректное прочтение `rules.md:42`, а не
  нарушение. Ревью само назвало пункт амбивалентным и рекомендовало «принять осознанное решение».
  Решение зафиксировано здесь, чтобы следующие ревьюеры не возвращали его без новых аргументов
  (например, если эти исключения станут объявленной частью контракта).

## Финальная проверка
- **Стиль (cs):** `make qa` шаг `composer cs` (php-cs-fixer) — ✓ OK (прошёл; запуск дошёл до
  стадии coverage, что при `set -euo pipefail` означает зелёные cs/phpstan/test).
- **PHPStan (level max):** `make phpstan` — ✓ OK, No errors.
- **Тесты:** `make test` — ✓ 311/311, 1043 assertions (deprecations: 1 + 29 PHPUnit —
  пред-существующие, не связаны с задачей).
- **Покрытие:** `make qa` шаг `test-coverage` — ✗ глобальный gate 92.72% < 100%
  (пред-существующий долг по `app/src`, сверка с планом №1). Сабжем гейта остаются только
  не-media классы: `LazyGhostEntityFactory`, `LazyGhostMapper`, `LazyGhostReflectionRegistry`,
  `ValueObjectCast`, `ConfigMappingException`, `ExceptionHandlerBootloader`. Ни один media-класс
  не ниже 100%. Новый код покрыт: `InvalidConfigValueException` поднят до 100% новым тестом;
  покрытие выросло 92.52% → 92.72%.
- **Заметки:** красным остаётся только глобальный coverage-gate — пред-существующее осознанное
  отклонение плана, не регресс этой задачи. cs/phpstan/test зелёные.
</content>
</invoke>
