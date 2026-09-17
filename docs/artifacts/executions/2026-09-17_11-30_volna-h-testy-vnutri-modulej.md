---
plan: docs/artifacts/plans/2026-09-17_11-30_volna-h-testy-vnutri-modulej.md
started: 2026-09-17 07:55
status: done
mode: subagents
current_phase: 10
---

# Журнал выполнения: Волна H — тесты модулей внутри модулей

Режим выполнения — `subagents`: план большой (10 фаз, перенос ~235 файлов по 9 модулям, правка трёх файлов инструментов), затрагивает много разных областей кода и легко переполнил бы контекст одного прохода. Причина зафиксирована по правилу skill `eda-plan-execute` («auto выбирай main, только когда весь план реально помещается в один контекст» — здесь не помещается), режим задан явно оркестратором аргументом «субагенты».

Место выполнения — текущая ветка `arch-modular-migration`, без новой ветки/worktree (решение оркестратора, передано явно, не переспрашивается).

Режим проверок для этого выполнения изменён заказчиком волны: фазы 1–9 НЕ запускают `make qa`/`make test`/`make phpstan`/`make test-unit` — проверяющий каждой фазы проверяет соответствие плану чтением кода. Полный `make qa` — один раз в фазе 10, с циклами точечных исправлений; `make test-unit` там же отдельно.

## Фазы
| Фаза | Результат | Проверка | Статус |
|---|---|---|---|
| 1. Access + общие однократные правки инструментов | 4 файла перенесены, phpstan.neon/assert-unit-suite-is-light.sh/phpunit.xml обновлены | пройдена | done |
| 2. System | 6 файлов перенесены, phpunit.xml дополнен | пройдена | done |
| 3. Tags | 10 файлов перенесены (Unit/Domain с подпапками, Integration плоско), докблок Posts обновлён, phpunit.xml дополнен | вердикт проверяющего переопределён управляющим агентом (см. «Решения и блокеры») | done |
| 4. User | 15 файлов перенесены (Unit/Domain с подпапками, Integration плоско), phpunit.xml дополнен | пройдена | done |
| 5. Notifications | 30 файлов перенесены (29 из старых module-каталогов + FixtureNotificationTypeDefinition из корня), phpunit.xml дополнен | пройдена | done |
| 6. Auth | 36 файлов перенесены (Unit/Domain,Application с подпапками; Integration/Cycle, Integration/Spiral плоско, включая 5 фикстур и базовый класс), phpunit.xml дополнен | пройдена | done |
| 7. Outbox + вынос CleansOutboxEvents | 36 файлов перенесены в модуль, CleansOutboxEvents вынесен в tests/Support/Outbox, phpunit.xml дополнен | пройдена | done |
| 8. Posts | 42 файла перенесены (Unit/Domain,Application с подпапками; Integration/Cycle, Integration/Spiral, Feature/Spiral плоско), phpunit.xml дополнен | пройдена | done |
| 9. Media | 44 файла перенесены (Unit/Domain с подпапками, Unit/Application плоско, Integration/Cycle,Integration/Spiral плоско), phpunit.xml дополнен; под tests/{Unit,Kernel,Feature}/Modules пусто — все 9 модулей перенесены | пройдена | done |
| 10. Приёмка волны H | `make qa` зелёный кроме известного падения S3 (1549 тестов, 5294 утверждения, покрытие 100%), `make test-unit` зелёный, 33 маршрута, md5 OpenAPI совпал; найдена и исправлена одна реальная ошибка переноса (коллизия `RecordingLogger` в Outbox) | пройдена | done |

## Запуски
| Время | Исполнитель | Фаза | Результат | Файлы | Проверки |
|---|---|---|---|---|---|
| 2026-09-17 08:0x | субагент-исполнитель a907d0455793fe658 | 1 | completed | 4 файла Access перенесены git mv в app/src/Modules/Access/Tests/{Unit/Domain,Integration/Cycle} (плоско, без подпапок Migration/Repository); phpstan.neon (+excludePaths), docker/test/assert-unit-suite-is-light.sh (перебор tests/Unit + glob app/src/Modules/*/Tests/Unit), phpunit.xml (+3 записи для Access) | все проверки фазы passed |
| 2026-09-17 08:1x | субагент-проверяющий a7a6a79445856cfea | 1 | passed | — | 9/9 критериев passed, отклонений не найдено |
| 2026-09-17 08:2x | субагент-исполнитель a0f06d35003040903 | 2 | completed | 6 файлов System перенесены в app/src/Modules/System/Tests/{Integration/Spiral,Feature/Spiral} (плоско); phpunit.xml +3 записи | все проверки фазы passed/not_run(make qa — не запускается в фазах 1-9) |
| 2026-09-17 08:3x | субагент-проверяющий aca7ee851d446e1dc | 2 | passed | — | 9/9 критериев passed; уточнение: ApiErrorHttpTest не импортирует тестовые маршруты напрямую по FQCN, обращается к ним через HTTP-путь и TestKernel/ApiErrorTestRoutesBootloader — формулировка плана неточна буквально, но по существу верна (корневая инфраструктура не менялась) |
| 2026-09-17 08:4x | субагент-исполнитель ad80bbdcfdd7e1b11 | 3 | completed | 10 файлов Tags перенесены (Unit/Domain — с подпапками Collection/Entity/ValueObject; Integration/Cycle, Integration/Spiral — плоско); докблок TagsRepositoryTestCase обновлён на целевой FQCN Posts; phpunit.xml +3 записи | все проверки фазы passed |
| 2026-09-17 08:5x | субагент-проверяющий a205c518f5f29fe0d | 3 | failed (переопределено управляющим агентом на passed) | — | 6/7 критериев passed; 1 критерий (сохранение подпапок в Unit/Domain) оценён проверяющим как ошибка, управляющий агент не согласился и переопределил решение с обоснованием в «Решения и блокеры» |
| 2026-09-17 09:0x | субагент-исполнитель ae677c973a53896ff | 4 | completed | 15 файлов User перенесены (Unit/Domain — Entity/ValueObject подпапки; Integration/Cycle, Integration/Spiral — плоско); phpunit.xml +3 записи | все проверки фазы passed |
| 2026-09-17 09:1x | субагент-проверяющий a8a0f6e127c75031c | 4 | passed | — | 8/8 критериев passed; отдельно перепроверен подозрительный путь exclude из резюме исполнителя ("Tests/Tests") — фактическое содержимое phpunit.xml корректно (app/src/Modules/User/Tests), неточность была только в тексте резюме исполнителя, не в файле |
| 2026-09-17 09:2x | субагент-исполнитель a5ed81e585c1dc74d | 5 | completed | 30 файлов Notifications перенесены (Unit/Domain 5, Unit/Application 3 включая Fixture, Integration/Cycle 7, Integration/Spiral 14, Feature/Spiral 1); FixtureNotificationTypeDefinition перенесена из tests/Support/Notifications в модуль, RecordingOutboxEventStore осталась в корне; phpunit.xml +4 записи | все проверки фазы passed; деталь: расхождение "29 vs 30" в тексте плана — арифметическая погрешность плана (18+11=29 без учёта отдельного переезда фикстуры), не ошибка исполнения |
| 2026-09-17 09:3x | субагент-проверяющий ab10c8e4a464e4e68 | 5 | passed | — | 11/11 критериев passed, включая построчную сверку всех 30 диффов (37 insertions/37 deletions, арифметика сошлась) и grep всех потребителей FixtureNotificationTypeDefinition |
| 2026-09-17 09:4x | субагент-исполнитель ae4fbce696c959ed2 | 6 | completed | 36 файлов Auth перенесены (Unit/Domain 2, Unit/Application 1 — с подпапками; Integration/Cycle 6, Integration/Spiral 26 включая 5 фикстур+базовый класс — плоско; Feature/Spiral 1); phpunit.xml +4 записи по алфавиту | все проверки фазы passed |
| 2026-09-17 09:5x | субагент-проверяющий ade5152a30d4406f4 | 6 | passed | — | 11/11 критериев passed; отдельно подтверждено: два разных GetUserSessionsHandlerTest (Unit vs Integration/Spiral) — не дубликат, разные namespace/логика; локальная фикстура Auth RecordingOutboxEventStore.php не спутана с корневой tests/Support/Notifications версией (разные классы, разные namespace, оба целы) |
| 2026-09-17 10:0x | субагент-исполнитель af8f6a05b3e455a9d | 7 | completed | 36 файлов Outbox перенесены (Unit/Domain 1, Unit/Application/Message 1 — с подпапками; Integration/Cycle 3, Integration/Spiral 31 включая 18 бывших фикстур — плоско); CleansOutboxEvents вынесен в tests/Support/Outbox, 8 потребителей (7 внутри Outbox + MediaProcessingFlowTest.php, ещё не перенесённый) обновлены; phpunit.xml +3 записи | 3/4 проверок passed, 1 помечена failed самим исполнителем как расхождение текста плана "37" — фактически корректно (36 в модуль + 1 в корень = 37 исходных) |
| 2026-09-17 10:1x | субагент-проверяющий acbff13e9ccadcef7 | 7 | passed | — | 12/12 критериев passed; арифметика 36+1=37 подтверждена подсчётом renamed-записей git status; отдельно проверены отсутствие коллизий имён среди 31 файла плоской Integration/Spiral и корректность единственной внемодульной правки (импорт в ещё не перенесённом MediaProcessingFlowTest.php) |
| 2026-09-17 10:2x | субагент-исполнитель a4cd775e5ee9db188 | 8 | completed | 42 файла Posts перенесены (Unit/Domain 11, Unit/Application 1 — с подпапками; Integration/Cycle 11, Integration/Spiral 5, Feature/Spiral 14 — плоско); phpunit.xml +4 записи по алфавиту | все проверки фазы passed; уточнение: PostsRepositoryTestCase на самом деле не имел реального use-импорта PersistsMedia (только докблок) — текст плана здесь неточен, аналогично прецеденту фазы 4 |
| 2026-09-17 10:3x | субагент-проверяющий a154d39e3a12f3ed4 | 8 | passed | — | 10/10 критериев passed; межфазовая сверка: докблок Tags (фаза 3) дословно совпадает с фактическим FQCN PostsRepositoryTestCase после фазы 8 |
| 2026-09-17 10:4x | субагент-исполнитель a4107cd04476d5b27 | 9 | completed | 44 файла Media перенесены (Unit/Domain 5 с подпапками включая бывший Public/Enum; Unit/Application 2 плоско; Integration/Cycle 6, Integration/Spiral 31 — плоско); phpunit.xml +3 записи (без Feature); tests/Unit,Kernel,Feature/Modules полностью пусты | все проверки фазы passed |
| 2026-09-17 10:5x | субагент-проверяющий ae5d1dea6778bfb47 | 9 | passed | — | 11/11 критериев passed; ИТОГО по волне: 223 файла перенесены в 9 модулей (Access 4, System 6, Tags 10, User 15, Notifications 30, Auth 36, Outbox 36, Posts 42, Media 44); корневой tests/ сверен построчно с таблицей плана — ничего лишнего не осталось (контрольный grep-фильтр пуст); tests/Unit/Modules, tests/Kernel/Modules, tests/Feature/Modules отсутствуют полностью — финальный структурный критерий волны выполнен |

## Решения и блокеры

- Фаза 10: обнаружена и исправлена единственная реальная ошибка переноса всей волны. После того как фаза 7 сплющила `Integration/Spiral` модуля Outbox, два РАЗНЫХ фикстур-класса с одинаковым именем `RecordingLogger` оказались в одном namespace `App\Modules\Outbox\Tests\Integration\Spiral`: один объявлен в конце `OutboxDebugLogJobTest.php` (строка 72, `extends AbstractLogger implements LoggerInterface`, метод `hasDebugContextValue`), другой — в конце `OutboxRelayWorkerTest.php` (строка 212, `extends AbstractLogger`, счётчики `warningCount/errorCount/debugCount`). До волны коллизии не было, потому что файлы лежали в разных namespace (`Tests\Kernel\Modules\Outbox\Infrastructure\Spiral\Job` и `Tests\Unit\Modules\Outbox\Infrastructure`). Симптом: `PHP Fatal error: Cannot redeclare class ...RecordingLogger` при любом сканировании классов Spiral Tokenizer — прогон падал ещё на этапе миграций тестовых баз, до запуска единого теста. Исправление точечное и без изменения логики: класс в `OutboxRelayWorkerTest.php` переименован в `OutboxRelayWorkerRecordingLogger` (объявление + 4 использования `new` внутри того же файла, итого 5 строк). Это тот самый случай «переименованных файлов», который план в фазе 10 разрешает фиксировать как найденное при переносе исключение. Autonomous.
- Фаза 10: выполнена сплошная проверка, что коллизия была единственной. По всем 223 перенесённым файлам собраны пары «namespace + имя объявленного class/interface/trait/enum» (включая дополнительные классы, объявленные в конце тестовых файлов) — дубликатов, кроме `RecordingLogger`, нет. Дополнительно подтверждено: у всех 223 файлов namespace точно соответствует пути PSR-4, и у всех 223 имя основного класса совпадает с именем файла; ссылок на старые namespace `Tests\{Unit,Kernel,Feature}\Modules` в репозитории не осталось ни в PHP, ни в xml/neon/sh/json.
- Фаза 1: файлы Integration/Cycle размещены плоско (без подпапок Migration/Repository) — целевое дерево docs/arch.md не описывает подпапок глубже Integration/{Cycle,Spiral}, и план сам использует такое же плоское размещение в описании фаз 2 и 3. Это не отклонение от плана, а согласованная с планом деталь синтаксиса пути.
- Фаза 3: исполнитель сохранил исходные подпапки `Collection/Entity/ValueObject` внутри `Unit/Domain` (не сплющил их), а проверяющий фазы 3 вернул `failed`, настаивая на полном сплющивании по аналогии с Integration. Управляющий агент рассмотрел оба аргумента и принял решение В ПОЛЬЗУ ИСПОЛНИТЕЛЯ, переопределив вердикт проверяющего: перенос `Domain/** -> Unit/Domain/**` — это перенос 1:1 без консолидации (подпапка старого пути становится подпапкой нового пути без изменений), тогда как `Integration/Cycle`/`Integration/Spiral` — узлы КОНСОЛИДАЦИИ, куда стекаются файлы из многих разных старых расположений без единой исходной иерархии, что и было настоящей причиной решения о плоском размещении в фазе 1 (не буквальный запрет подпапок в `docs/arch.md`, а отсутствие содержательной единой иерархии для узла консолидации). Правило уточнено в плане (раздел «Целевой алгоритм») для единообразного применения во всех оставшихся фазах: `Unit/Domain` и `Unit/Application` сохраняют исходные подпапки, `Integration/Cycle` и `Integration/Spiral` — всегда плоские. Фаза 3 в её текущем виде (как выполнил исполнитель) признана соответствующей уточнённому правилу и закрыта как `passed`, повторное исправление не требуется. Autonomous, решение управляющего агента без участия пользователя (недоступен по условиям волны).

## Изменения в документации

Правок `docs/arch.md` и `docs/rules.md` не потребовалось: целевое дерево `Tests/Unit/{Domain,Application}`, `Tests/Integration/{Cycle,Spiral}`, `Tests/Feature/Spiral` и требование самодостаточности модуля уже зафиксированы в `docs/arch.md` до волны — волна привела код в соответствие документу, а не наоборот. Единственное место, где по правилу задачи фиксируется причина каждого оставшегося в корневом `tests/` файла, — раздел «Финальная проверка» этого журнала.

## Финальная проверка

### Итоговая раскладка: модуль → число перенесённых файлов

Проверено командой `find app/src/Modules/{M}/Tests -name "*.php" | wc -l` на момент приёмки.

| Модуль | Файлов | Раскладка по видам |
|---|---|---|
| Access | 4 | Unit/Domain 1, Integration/Cycle 3 |
| System | 6 | Integration/Spiral 3, Feature/Spiral 3 |
| Tags | 10 | Unit/Domain 3 (подпапки Collection/Entity/ValueObject), Integration/Cycle 3, Integration/Spiral 4 |
| User | 15 | Unit/Domain 2 (подпапки Entity/ValueObject), Integration/Cycle 6, Integration/Spiral 7 |
| Notifications | 30 | Unit/Domain 5, Unit/Application 3 (включая перенесённую из корня фикстуру), Integration/Cycle 7, Integration/Spiral 14, Feature/Spiral 1 |
| Auth | 36 | Unit/Domain 2, Unit/Application 1, Integration/Cycle 6, Integration/Spiral 26 (включая 5 фикстур и базовый класс), Feature/Spiral 1 |
| Outbox | 36 | Unit/Domain 1, Unit/Application/Message 1, Integration/Cycle 3, Integration/Spiral 31 (включая 18 бывших фикстур) |
| Posts | 42 | Unit/Domain 11, Unit/Application 1, Integration/Cycle 11, Integration/Spiral 5, Feature/Spiral 14 |
| Media | 44 | Unit/Domain 5, Unit/Application 2, Integration/Cycle 6, Integration/Spiral 31 |
| **Итого** | **223** | — |

Исключения, найденные при переносе (кроме них перенос везде 1:1 по правилу раскладки):

- `tests/Support/Notifications/FixtureNotificationTypeDefinition.php` — единственный файл, переехавший ИЗ корня ВНУТРЬ модуля (в `Notifications/Tests/Unit/Application/Fixture`), потому что все его фактические потребители — тесты самого Notifications (решение 2 плана, подтверждено grep в фазе 5).
- `tests/Feature/Modules/Outbox/CleansOutboxEvents.php` — единственный файл, переехавший ИЗ модуля В корень (`tests/Support/Outbox/CleansOutboxEvents.php`), потому что его используют и Outbox, и Media (решение 3 плана, фаза 7). Именно поэтому арифметика фазы 7 — 36 файлов в модуль + 1 в корень = 37 исходных.
- `OutboxRelayWorkerTest.php` — единственный файл с переименованным классом: фикстура `RecordingLogger` переименована в `OutboxRelayWorkerRecordingLogger` из-за коллизии имён после сплющивания `Integration/Spiral` (подробности в «Решения и блокеры»).
- Расщеплённых файлов (деления одного класса на несколько по смешению видов) не потребовалось ни в одном из 9 модулей — предсказание плана подтвердилось.

### Что осталось в корневом `tests/` и почему

Всего 62 файла (`find tests -name "*.php" | wc -l`). Каталогов `tests/Unit/Modules`, `tests/Kernel/Modules`, `tests/Feature/Modules` не существует — все модульные тесты переехали.

| Файл или группа | Шт. | Причина |
|---|---|---|
| `tests/TestCase.php`, `tests/DatabaseTestCase.php`, `tests/NonTransactionalDatabaseTestCase.php`, `tests/RealStorageTestCase.php`, `tests/TestRuntime.php`, `tests/bootstrap.php`, `tests/warmup.php` | 7 | Базовый каркас PHPUnit/Spiral-тестов сразу для всех модулей — прямой прецедент карточки `docs/references/integration-test.md`; владельца среди модулей нет. |
| `tests/App/TestKernel.php` | 1 | Тестовый Kernel собирает bootloader-ы всех модулей разом — по конструкции не принадлежит одному модулю. |
| `tests/App/Bootloader/ApiErrorTestRoutesBootloader.php`, `tests/App/Modules/System/Http/ApiErrorTestController.php`, `tests/App/Modules/System/Http/ApiErrorTestFilter.php` | 3 | Тестовые маршруты сквозной проверки формата ошибок API (общий контракт `spiral-api-errors`), а не бизнес-функциональность модуля System; подключаются через `TestKernel`, обращение к ним идёт по HTTP-пути, а не по FQCN. |
| `tests/Storage/FakeStorage.php` | 1 | Фейковая реализация `StorageInterface`, которую использует общий `DatabaseTestCase`; потребитель — корневой каркас, не модуль. |
| `tests/Support/Migration/ReplaysMigration.php` | 1 | Используется тестами миграций семи модулей (проверено grep на приёмке: Access 1, Auth 2, Media 2, Notifications 1, Outbox 1, Posts 1, User 1) — единственного владельца нет. |
| `tests/Support/Notifications/RecordingOutboxEventStore.php` | 1 | Используется тестами двух модулей (проверено grep: Notifications 3, Posts 1) — единственного владельца нет. |
| `tests/Support/Outbox/CleansOutboxEvents.php` | 1 | Используется тестами двух модулей (проверено grep: Outbox 7, Media 1) — новое расположение, заведённое фазой 7 именно из-за двух владельцев. |
| `tests/Support/Media/PersistsMedia.php` | 1 | Сидинг Media для тестов, оставлен в корне по решению плана. Уточнение приёмки: фактические `use`-импорты трейта сейчас есть только у 5 тестов Notifications; User и Posts упоминают файл лишь в докблоках (`UserApplicationTestCase.php:221`, `PostsRepositoryTestCase.php:65`), реального импорта у них нет. Формулировка плана «используется тестами трёх модулей» в этой части неточна; файл в волне не перемещался, потому что фаза 10 разрешает только точечные исправления реальных поломок, а этот файл ничего не ломает. Кандидат на отдельное решение вне волны H. |
| `tests/Feature/CqrsContainerTest.php` | 1 | Сквозная проверка регистрации CQRS-шины для обработчиков всех модулей сразу. |
| `tests/Feature/Shared/Infrastructure/DockerRuntimeSmokeTest.php` | 1 | Сквозной smoke реальной инфраструктуры окружения, не привязан к модулю. |
| `tests/Feature/Shared/Infrastructure/Spiral/Cache/RedisCacheStorageTest.php` | 1 | Тест примитива `Shared` (кэш-хранилище Redis). Поимённо в таблице плана не перечислен, но попадает под общее правило «`Shared` — часть без владельца среди модулей»; файл существует с 2026-09-15 и волной H не затрагивался. |
| `tests/Kernel/DemoTest.php` | 1 | Демонстрационный smoke загрузки kernel, не привязан к модулю. |
| `tests/Kernel/Shared/**` | 15 | Тесты примитивов `Shared`: LazyGhost (2), bootloader-ы `App`/`ExceptionHandler` (2), типизированные конфиги и их привязка (9), `RouteAccessCore` с фикстурой (2). `Shared` сам является частью без владельца среди модулей (`docs/arch.md`). |
| `tests/Unit/Shared/**` | 27 | Тесты примитивов `Shared` без инфраструктуры: Domain (6 — `TypedCollection`, `Locale`, `ApiDomainException`, `LocaleResolver`, `CursorSlice`, `AbstractUuidV7Id`), Persistence/Cycle (5 — каталог колонок, LazyGhost, `ValueObjectCast`), Spiral/Configuration (8, включая 2 фикстуры-массива конфигурации), Spiral/Http/Access (5, включая 4 фикстуры), Spiral/Http/Middleware (3 — Locale, RateLimit, `RecordingCache`). |
| **Итого** | **62** | — |

### Результаты прогонов

`make qa` (Docker, 4 процесса ParaTest, один coverage-run на PCOV):

- php-cs-fixer — `Found 0 of 1206 files that can be fixed`, 0 замечаний;
- PHPStan level max — `[OK] No errors`;
- тесты — `Tests: 1549, Assertions: 5294, Failures: 1`, время 04:15;
- единственное падение — заранее известное, до-волновое и не связанное с переносом: `App\Modules\Media\Tests\Integration\Spiral\S3MediaFileServiceTest::testCopyObjectToPublicBucketIsAnonymouslyReadable` (ожидает `test/` в публичном URL, получает `media-public/...`);
- покрытие — 100.00% при пороге 100%. Замер снят отдельной командой `php docker/test/assert-coverage.php runtime/coverage/clover.xml 100` по clover-отчёту ТОГО ЖЕ прогона, потому что в `composer qa` шаг покрытия стоит за `&&` после ParaTest и из-за известного падения S3 сам не выполняется. Отчёт охватывает 905 файлов приложения, 9851 из 9851 инструкции; вхождений `/Tests/` в clover нет — `<exclude>` в `phpunit.xml` действительно выводит перенесённые тесты из отчёта покрытия.

Сверка с базовым замером до волны: тестов 1549 при базовых 1549, утверждений 5294 при базовых 5294 — числа не уменьшились, что и требовалось, поскольку тесты только переносились.

`make test-unit` отдельным прогоном: зелёный, `OK (531 tests, 1828 assertions)`, время 00:00.217, память 34 MB. Инфраструктура не поднимается: цель идёт с `--no-deps` и без `reset-test`, а `docker/test/assert-unit-suite-is-light.sh` прошёл по всем девяти каталогам `app/src/Modules/*/Tests/Unit` плюс `tests/Unit` и не нашёл запрещённых паттернов (`Tests\TestCase`, `getContainer(`, `Tests\App\TestKernel`, `Spiral\Testing\TestCase`). Время прогона в пятую долю секунды само по себе исключает Spiral, БД, Redis, MinIO, очередь и сеть.

`php app.php route:list` — 33 маршрута, как и до волны.

`php app.php openapi:generate` — 31 операция, 50 schemas; md5 сгенерированного `public/openapi/openapi.yml` равен `fd8d4a16c3994dddcfbf915caa85157b` и совпадает с ожидаемым; `git status` по файлу пуст, то есть спецификация побайтово не изменилась. HTTP-слой волной не редактировался, и это подтверждено.

### Состояние рабочего дерева на момент приёмки

224 переименования (223 файла тестов внутрь модулей + `CleansOutboxEvents` в `tests/Support/Outbox`), 3 изменённых файла инструментов (`phpunit.xml`, `phpstan.neon`, `docker/test/assert-unit-suite-is-light.sh`) и одна точечная правка фазы 10 в `app/src/Modules/Outbox/Tests/Integration/Spiral/OutboxRelayWorkerTest.php` (6 строк: namespace от фазы 7 плюс переименование фикстуры). `composer.json` и `.php-cs-fixer.php` не менялись, как и предсказывало решение 5 плана. Изменения не закоммичены — коммит выполняет отдельный агент.
