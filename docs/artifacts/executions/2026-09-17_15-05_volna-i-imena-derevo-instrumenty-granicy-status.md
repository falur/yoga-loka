---
plan: docs/artifacts/plans/2026-09-17_15-00_volna-i-imena-derevo-instrumenty-granicy-status.md
started: 2026-09-17 15:05
finished: 2026-09-17 21:30
status: done
mode: subagents -> main (с фазы 6, вынужденно)
current_phase: 7 (все фазы завершены)
---

# Журнал выполнения: Волна I — имена, дерево, инструменты, границы, статус архитектуры

Режим `subagents` зафиксирован явным указанием оркестратора («субагенты» в аргументах вызова `eda-plan-execute`) — план большой (7 фаз, широкий охват модулей, много самостоятельных решений), в `main` не поместился бы без потери контекста.

Место выполнения: текущая ветка `arch-modular-migration`, без новой ветки и worktree — выбор передан оркестратором, повторно не подтверждается.

## Фазы

| Фаза | Результат | Проверка | Статус |
|---|---|---|---|
| 1. Дерево модулей и самодостаточность | код и `docs/arch.md` соответствуют обновлённому целевому дереву, `PersistsMedia` у фактического владельца | `make test-unit`, `make phpstan`, целевые grep | **завершена** (passed со второй проверки) |
| 2. Имена классов | 3 класса переименованы по конвенции | `make test-unit`, `make phpstan`, grep старых имён | **завершена** (passed с первой проверки) |
| 3. Хвост: домен, сценарии, зависимости | `PostVisibilityPolicy` — инстанс; relay/interceptor пропускают исчезнувшую запись; `spiral-outbox` удалён | `make test-unit`, `make phpstan`, новые тесты | **завершена** (passed с первой проверки) |
| 4. OpenAPI, Bearer-безопасность и тест S3 | спецификация с `securitySchemes`; тест S3 зелёный | `make test-unit`, `make phpstan`, генерация OpenAPI, точечный прогон теста | **завершена** (passed с первой проверки) |
| 5. Автоматическая защита границ (deptrac) | deptrac встроен в `make qa`, падает на нарушениях | `make test-unit`, `make phpstan`, deptrac + эксперимент | **завершена** (passed с первой проверки) |
| 6. Статус архитектуры | `docs/arch.md` содержит все отступления с причиной | `make test-unit`, `make phpstan`, `make deptrac`, сверка фактов | **завершена** (passed со второй проверки) |
| 7. Приёмка волны | `make qa` зелёный, количественные критерии подтверждены | `make qa` | **завершена** (passed с первого прогона) |

## Запуски
| Время | Исполнитель | Фаза | Результат | Файлы | Проверки |
|---|---|---|---|---|---|
| 2026-09-17 15:2x | субагент-исполнитель acd65e0c | 1 | completed | 29 (7 переносов разделов дерева + PersistsMedia в Notifications + `docs/arch.md`) | `make test-unit` OK (531 tests, 1828 assertions); `make phpstan` No errors (level max); оба grep пусты |
| 2026-09-17 15:3x | субагент-проверяющий ab14dfc3 | 1 | **failed** | — | переносы, PSR-4, отсутствие битых ссылок и коллизий, scope, `make test-unit`, `make phpstan` — passed; провал критерия результата: создан раздел `Tests/Support`, которого нет в целевом дереве |
| 2026-09-17 15:4x | субагент-исполнитель acd65e0c (продолжен) | 1 | completed | 2 (`docs/arch.md` + `HealthResource.php`) | `Support/` добавлен в дерево `Tests/` документа; удалён избыточный self-namespace `use`; `make test-unit` OK (531/1828); `make phpstan` No errors |
| 2026-09-17 15:5x | субагент-проверяющий a262500a (новый) | 1 | **passed** | — | 11/11 критериев passed: дерево всех 9 модулей укладывается в обновлённое дерево документа; 1210 символов проверено на коллизии namespace+имя — дублей 0; живых ссылок на 7 старых FQCN нет; scope чист; `make test-unit` OK (531 tests, 1828 assertions); `make phpstan` [OK] No errors |
| 2026-09-17 16:0x | субагент-исполнитель a6ab3591 | 2 | completed | 9 (3 `git mv` + 4 потребителя + `Media/README.md` + карточка `domain-collection.md`) | `make test-unit` OK (531/1828); `make phpstan` [OK] No errors; grep старых имён пуст |
| 2026-09-17 16:1x | субагент-проверяющий af0d5e61 | 2 | **passed** | — | 15/15 критериев passed: 1210 объявлений без дублей namespace+имя и без расхождений PSR-4; старых имён нет нигде в живом коде (grep с якорями границ слова — ловушка подстрок учтена); `git status` показывает RM (история переименований сохранена); отклонение с карточкой `domain-collection.md` признано обоснованным (3 строки, только имя класса); scope чист; `make test-unit` OK (531/1828); `make phpstan` [OK] No errors |
| 2026-09-17 16:3x | субагент-исполнитель af6eba96 | 3 | completed | 23 (политика + 10 handler-ов + 2 класса Outbox + 4 теста + докблок + composer.json/lock) | `make test-unit` OK (540 tests, 1837 assertions); `make phpstan` [OK] No errors; три grep-проверки пусты; точечные прогоны Kernel-тестов Outbox и Posts зелёные; `composer cs` 0 из 1207 |
| 2026-09-17 16:5x | субагент-проверяющий acf5411b | 3 | **passed** | — | 14/14 критериев passed. Главное: переписанные 4 теста не ослаблены, а усилены (2→6, 3→7, 2→5, 2+expectException→7 ассертов; `expectException(RuntimeException::class)` заменён на `assertSame($jobException, $caught)` — проверка тождества строго сильнее проверки класса); каждый новый тест упал бы на старой реализации по двум независимым причинам. Все 4 новые ветки реально исполняются (у каждой уникальный warning, ассертируемый через RecordingOutboxLogger; исчезновение строки воспроизводится настоящим DELETE, не заглушкой). Регрессии в relay/interceptor нет — изменены ровно 4 места, остальной код побайтово прежний. `composer validate` без претензий к lock, других пакетов lock не потерял. Точечно: Outbox 100 tests/373 assertions, Posts Integration 128 tests/512 assertions |
| 2026-09-17 17:2x | субагент-исполнитель a77ab384 | 4 | completed | 5 (`OpenApiConfig` + 2 теста + `docker-compose.dev.yml` + `openapi.yml`) | схема `bearerAuth`; 26 защищённых + 5 публичных = 31 операция, schemas 50; целевой тест S3 OK (1 test, 4 assertions); Media Integration OK (239/684); `make test-unit` OK (540/1837); `make phpstan` [OK] |
| 2026-09-17 17:4x | субагент-проверяющий afd40ba0 | 4 | **passed** | — | 22/22 критериев passed. Ключевое доказательство причинности: контрольный прогон целевого теста с пустым `MEDIA_PUBLIC_STORAGE_PREFIX` воспроизводит исходное падение («Failed asserting that '...media-public/images/...' contains "test/"»), то есть зелёность достигнута правкой окружения, а не теста — `S3MediaFileServiceTest.php` и `S3MediaFileService.php` не изменены ВОВСЕ (пустой diff), `phpunit.xml` и `.env` не тронуты. Сквозная (не выборочная) сверка всех 31 операции спецификации с атрибутами 8 контроллеров — 0 расхождений; операций без ключа `security` нет. Генерация идемпотентна (md5 `a5d4c4e8e3952486166dc182f8647f5d` до и после). Новый тест bearer-безопасности упал бы без подключения `bearerSecurity` (три `assertSame` против `?? null`) |
| 2026-09-17 18:3x | субагент-исполнитель a0211662 | 5 | completed | 6 (`deptrac.yaml` 1202 строки + `composer.json`/`lock` + `Makefile` + `run-qa.sh` + восстановленный `GetPostHandler`) | deptrac 4.7.2; 54 слоя, 49 ruleset-ов; чистый прогон Violations 0; собственный эксперимент по 5 классам границ — все падают; `make test-unit` OK (540/1837); `make phpstan` [OK]; `composer cs` 0 из 1207 |
| 2026-09-17 19:1x | субагент-проверяющий a284fd93 | 5 | **passed** | — | Проверено программным разбором YAML, что правила не вакуумны: ни у одного `Domain`/`Application`/`Public` нет `SpiralFramework`/`CycleOrm`; ни один из них не видит не-`Public` слой соседа; `Shared*` не видят ни одного бизнес-модуля. Область анализа честная: 905 продуктивных .php из 1130 под проверкой, `debug:unassigned` пуст, все 734 непокрытые зависимости ведут только на нейтральный вендор (ни одного `App\`, `Spiral\`, `Cycle\`, `GianTiaga\`). Конфигурация СТРОЖЕ дефолта (`analyser.types` включает `use`, дефолт — только `class`+`function`). Проведено 7 СОБСТВЕННЫХ экспериментов на других файлах и парах модулей (Media/Domain, User/Application, User→Posts, Notifications→Media, Shared/Infrastructure, Tags/Public, Notifications→User ORM, Posts не-Spiral Infrastructure) — все дали EXIT=1 с точным текстом нарушения, все откачены обратной правкой (sha256 до=после), `git checkout` не применялся. Падение deptrac доказанно роняет `composer qa` ДО тяжёлого `@test-coverage` |
| 2026-09-17 19:4x | субагент-исполнитель a87b953d | 6 | completed | 2 (`docs/arch.md` + `deptrac.yaml`) | новый статус, раздел «Проверка границ» с тремя честными оговорками, уточнённая таблица направлений, раздел «Осознанные отступления» из семи пунктов; ужесточение: `OwnPackageRuntime` снят с девяти `{Module}Infrastructure`; число файлов с `UserId` уточнено с «~97» плана до проверенных 111 |
| 2026-09-17 20:0x | субагент-проверяющий aec45e6e | 6 | **failed** | — | 14 критериев passed, один провал: клауза «каждый модуль завёл собственное доменное исключение поверх `DomainTranslatableException`» верна лишь для 6 модулей из 9 (у Access и Tags своих исключений нет, у Outbox четыре наследуют `\DomainException`). Плюс три незначительных замечания |
| 2026-09-17 20:2x | субагент-исполнитель a87b953d (продолжен) | 6 | completed | 1 (`docs/arch.md`) | клауза переписана по факту; добавлена оговорка про архитектурность перечня отступлений и три прочих подавления поимённо; формулировка про дерево как целевую номенклатуру. Замечание про `Kernel → SharedInfrastructure` ОПРОВЕРГНУТО экспериментом: правило не бездействующее (удаление даёт Violations 6), правка внесена в документ, а не в конфигурацию |
| 2026-09-17 20:4x | основной агент (режим `main`, см. решение 0) | 6 | **passed** | — | Перепроверено по коду: 6 модулей с `DomainTranslatableException` (Auth 6, Media 23, Notifications 5, Posts 5, System 2, User 3), у Access и Tags файлов исключений нет, четыре исключения Outbox наследуют `\DomainException`, `OpenApiAssetsPublicationException` — `\Exception`; формулировка документа совпадает с фактом. `UserId` — 111 файлов в семи модулях; `@phpstan-ignore` в `app/src` ровно 6 и все названы; `Domain/Event` и `Infrastructure/Cache` не заняты ни одним модулем, `Shared/Application` отсутствует. Спорный `Kernel → SharedInfrastructure` подтверждён: `use Spiral\Bootloader as Framework` (с алиасом), поэтому шесть ссылок `Bootloader\*Bootloader::class` (строки 59, 73, 76, 77, 190, 199) разрешаются в `App\Shared\Infrastructure\Spiral\Bootloader\*`, все шесть файлов существуют. Прогоны: `make test-unit` OK (540 tests, 1837 assertions), `make phpstan` [OK] No errors, `make deptrac` Violations 0 |
| 2026-09-17 21:2x | основной агент (режим `main`) | 7 | **passed** | — | Полный `make qa` с первого прогона зелёный целиком, без единого исключения (подробности в разделе «Финальная проверка») |

## Решения и блокеры

**Решение 0 (смена режима выполнения, вынужденная).** Фазы 1-5 и первая проверка фазы 6 выполнены в режиме `subagents` (отдельный изолированный исполнитель и отдельный проверяющий на каждую фазу). На этапе повторной проверки фазы 6 инструмент запуска субагентов стал недоступен для сессии («Agent is disabled for this session, in subagents as well as here»). Останавливать волну на этом месте нельзя: изменения не закоммичены, а приёмка (полный `make qa`) — обязательный критерий. Поэтому оставшаяся работа (повторная проверка фазы 6 и фаза 7) выполняется основным агентом в режиме `main`, как это прямо предусмотрено скилом для случая, когда план не помещается в выбранный режим. Независимость проверки при этом снижается: фазу 6 повторно проверяет тот же агент, который координировал волну, а не свежий изолированный проверяющий. Это записано здесь как осознанное ограничение приёмки, а не как соблюдённое требование.

**Решение 1 (фаза 1, принято координатором после провала первой проверки).** Перенос `PersistsMedia` внутрь модуля Notifications создал раздел `Tests/Support/`, которого нет в целевом дереве. Выбрано дополнить дерево `docs/arch.md` строкой `Support/` внутри `Tests/`, а не перекладывать трейт в один из существующих видов. Причина: трейт используют и `Integration/Spiral`, и `Feature/Spiral` тесты Notifications, поэтому он не принадлежит ни одному виду; прецедент волны H (`Unit/Application/Fixture`) здесь не подходит — та фикстура была нужна только unit-тестам. Это согласованное дополнение дерева по правилу задачи 26 («дополни дерево с объяснением — но не молча»).

**Решение 2 (область анализа deptrac, принято координатором заранее для фазы 5).** Проверяющий фазы 1 обнаружил, что `PersistsMedia` импортирует ~20 внутренних классов модуля Media (Domain, Application, Infrastructure) в обход `Media/Public`. Пока файл лежал в корневом `tests/`, он был в глобальной зоне без владельца; после переезда внутрь Notifications формально стал файлом модуля, обращающимся к соседу не через его `Public`.

Решение: deptrac анализирует `app/src`, исключая `app/src/Modules/*/Tests/*`. Причины: (а) таблица «Направления зависимостей» `docs/arch.md` описывает слои `Domain`, `Public`, `Application`, `Infrastructure` — `Tests` в ней не участвует и слоем не является, поэтому исключение не снимает ни одного объявленного правила; (б) это ровно та область анализа, которую проект уже осознанно зафиксировал для PHPStan в волне H (`excludePaths: app/src/Modules/*/Tests/*`), и расхождение областей двух статических анализаторов создавало бы больше путаницы, чем пользы; (в) фикстуре интеграционного теста нужно готовить состояния медиа, которых публичный контракт не выражает (незавершённая загрузка, конкретные конверсии), — принуждение тестов ходить через `Public` сузило бы проверяемые состояния и ослабило тесты.

Это решение об области анализа, а не baseline: ни одна строка продуктивного кода из-под проверки не выводится. Записывается здесь и в `docs/arch.md` на фазе 6, чтобы не выглядело молчаливым послаблением.

**Наблюдения проверяющего фазы 1 вне её scope (решения координатора).**

1. `Notifications/Infrastructure/Client/CentrifugoPresenceException.php:14` — докблок утверждает «поэтому лежит в Infrastructure/Exception», хотя файл лежит в `Infrastructure/Client`. Это ровно тот класс дефекта, который волна закрывает пунктом хвоста «докблоки ссылаются на удалённое расположение/имена», просто найден не grep-ом по именам классов. Передаётся в фазу 3 к остальному хвосту.
2. Два теста волны H лежат в разделе, не совпадающем со слоем проверяемого класса (`Notifications/Tests/Unit/Domain/Dto/NotificationChannelCollectionTest.php` проверяет класс из `Public/Dto`; `Outbox/Tests/Unit/Application/Message/OutboxMessageSerializerTest.php` при контракте в `Application/Contract`). Действий не требуется: волна H приняла это осознанно — её правило раскладки прямо гласит «`Unit/Modules/{M}/Public/**` → `Unit/Domain/**` — у `Unit` нет подпапки `Public`, а проверка по сути доменная». Формально оба каталога укладываются в целевое дерево (оно не детализирует уровни ниже `Unit/{Domain,Application}`).
3. Предсуществующие избыточные `use` из собственного namespace в 8 файлах (`Media/Infrastructure/Storage/S3MediaFileService.php`, `Media/Infrastructure/Imagick/ImagickMediaImageProcessor.php`, `Notifications/Infrastructure/Client/{CentrifugoOnlinePresence,CentrifugoClient}.php` и тесты Outbox). Не правятся: волной не затронуты, в список задач волны не входят, `docs/rules.md` прямо запрещает исправлять соседний код без отдельного запроса. Зафиксировано как наблюдение.

## Изменения в документации

- Фаза 1: `docs/arch.md`, раздел «Структура» — в дерево `Infrastructure/Spiral` добавлены `Adapter/`, `Registry/`, `Queue/`; добавлен абзац о представительности списка `Infrastructure/{Cache,Client,Storage}` с перечислением `Media/Infrastructure/{Ffmpeg,Imagick}` и `Outbox/Infrastructure/{Relay,Serializer}`; в дерево `Tests/` добавлен `Support/` (решение 1).

## Финальная проверка

`make qa` (Docker, полный прогон, 2026-09-17) — зелёный целиком с первого раза, циклов исправления не потребовалось:

| Шаг | Результат |
|---|---|
| php-cs-fixer (`@cs`) | `Found 0 of 1207 files that can be fixed` |
| PHPStan level max (`@phpstan`) | `[OK] No errors` |
| deptrac (`@deptrac`, новый шаг волны) | `Violations 0, Skipped violations 0, Uncovered 734, Allowed 7454, Warnings 0, Errors 0` |
| Тесты (`@test-coverage`, ParaTest) | `OK (1559 tests, 5327 assertions)`, время 04:19, **0 failures, 0 errors** |
| Покрытие | `Покрытие 100.00% соответствует порогу 100.00%` |
| Итог | `Composer QA завершён успешно` |

Количественные критерии волны:

| Критерий | Требование | Факт |
|---|---|---|
| Падений в `make qa` | ноль, без исключений | **0 failures, 0 errors** — впервые за весь переезд прогон зелёный целиком: до этой волны падал `S3MediaFileServiceTest::testCopyObjectToPublicBucketIsAnonymouslyReadable` |
| Число тестов | не меньше 1549 | **1559** (+10 к базовым 1549: `PostVisibilityPolicyTest` и тест bearer-безопасности OpenAPI) |
| Утверждений | не уменьшилось (база 5294) | **5327** |
| Покрытие | 100% | **100.00%** |
| PHPStan | level max без ошибок | **[OK] No errors**, без baseline и без новых игноров |
| php-cs-fixer | чисто | **0 из 1207** |
| `make test-unit` | зелёный, не поднимает инфраструктуру | **OK (540 tests, 1837 assertions)**, 0.27 с, `assert-unit-suite-is-light` пройден |
| `route:list` | 33 маршрута | **33** |
| OpenAPI | пересобрана, закоммиченный файл ей соответствует | md5 `a5d4c4e8e3952486166dc182f8647f5d` до и после перегенерации; 31 операция, 50 schemas; diff к HEAD — 87 строк, все добавления |
| Осиротевшие контейнеры | убрать | `docker ps -a` по `*-run-*` пуст |
| Состояние git | не коммитить | 71 файл изменён, ничего не закоммичено — коммит делает отдельный агент |

### Доказательство работоспособности защиты границ

Проверка реально падает при нарушении — подтверждено двумя независимыми сериями экспериментов (исполнителем фазы 5 и её проверяющим, который брал другие файлы и другие пары модулей). Каждое нарушение вносилось временно, фиксировался вывод deptrac, изменение немедленно откатывалось, и повторный прогон снова давал `Violations 0`.

| Класс границы | Файлы эксперимента | Результат |
|---|---|---|
| Фреймворк в `Domain` | `Tags/Domain/Entity/Tag.php`, `Media/Domain/Entity/Media.php` | `must not depend on Spiral\Core\Container (SpiralFramework)`, EXIT=1 |
| Cycle в `Application` | `Tags/.../GetTagsHandler.php`, `User/.../CreateUserHandler.php` | `must not depend on Cycle\ORM\EntityManagerInterface (CycleOrm)`, EXIT=1 |
| Сосед мимо `Public` | `Posts/.../GetPostHandler.php`, `User/.../GetUserPublicProfileHandler.php`, `Notifications/.../MarkNotificationReadHandler.php` | `must not depend on App\Modules\{X}\Domain\Entity\... ({X}Domain)`, EXIT=1 |
| ORM-связь через границу модуля | `Posts/.../CyclePostMediaEntity.php`, `Notifications/.../CycleNotificationEntity.php` | `must not depend on ...Cycle\Entity\Cycle{X}Entity ({X}Infrastructure)`, EXIT=1 (3 записи: импорт, атрибут связи, свойство) |
| Бизнес-модуль в `Shared` | `Shared/Domain/ValueObject/UserId.php`, `Shared/Infrastructure/.../AbstractRepository.php` | `must not depend on App\Modules\{X}\... ({X}Domain)`, EXIT=1 + каскад по всем наследникам |
| Фреймворк в `Public` | `Tags/Public/Dto/TagDto.php` | `must not depend on Spiral\Queue\QueueInterface (SpiralFramework)`, EXIT=1 |
| Spiral вне `Infrastructure/Spiral` | `Posts/.../CyclePostRepository.php` | `must not depend on Spiral\Queue\QueueInterface (SpiralFramework)`, EXIT=1 |

Встроенность в обычный прогон подтверждена и по коду (`Makefile` → `docker/test/run-qa.sh` под `set -euo pipefail` → `composer qa` → массив `["@cs","@phpstan","@deptrac","@test-coverage"]`), и фактически: с временным нарушением `composer qa` упал на шаге `@deptrac` с кодом 1, а тяжёлый шаг `@test-coverage` не запускался вовсе.
