---
title: Волна H — тесты модулей внутри модулей
date: 2026-09-17 11:30
mode: normal
plan_size: normal
decision_mode: autonomous
status: draft
reviewer: none
plan_review: none
plan_review_fix: none
test_strategy: end_of_plan
logging_strategy: standard
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  business: []
  references:
    - docs/references/integration-test.md
    - docs/references/unit-test.md
  research: docs/artifacts/researches/2026-09-15_17-25_karta-rashozhdenij-s-celevoj-arhitekturoj.md
---

# План реализации

## Задача

Волна H переезда на целевую архитектуру закрывает задачу roadmap 24: проверки каждого модуля физически переезжают из `tests/{Unit,Kernel,Feature}/Modules/{Module}` в `app/src/Modules/{Module}/Tests`, разложенные по целевому дереву `docs/arch.md` (`Tests/Unit/{Domain,Application}`, `Tests/Integration/{Cycle,Spiral}`, `Tests/Feature/Spiral`). Задачи 25–30 (переименование классов, сверка дерева, автоматизация проверки границ, статус архитектуры) в волну не входят.

Готово, когда: у каждого из 9 модулей есть собственный `Tests/` с тестами, перенесёнными без изменения содержимого (кроме namespace, импортов и минимальных правок, нужных для работы на новом месте); в корневом `tests/` остались только сквозные, межмодульные и не имеющие владельца-модуля проверки и инфраструктура, и для каждого оставшегося файла в журнале записана причина; `Tests/Common` нигде не создана; `phpunit.xml`, `phpstan.neon` и `docker/test/assert-unit-suite-is-light.sh` учитывают новое расположение; наборы `Unit`, `Kernel`, `Feature` и `make test-unit` работают, `make test-unit` не поднимает инфраструктуру; число тестов и утверждений не уменьшилось; `make qa` зелёный кроме заранее известного падения `S3MediaFileServiceTest::testCopyObjectToPublicBucketIsAnonymouslyReadable`; PHPStan level max чист; `route:list` — 33 маршрута; md5 сгенерированной OpenAPI — `fd8d4a16c3994dddcfbf915caa85157b`.

Ни таблицы, ни маршруты, ни ответы приложения не меняются. Содержимое тестов не переписывается — меняются расположение, namespace, импорты и объявление наборов.

## Целевой алгоритм

Единственное наблюдаемое отличие после волны — расположение файлов тестов и путь конфигурации, которая их находит; поведение приложения и результат тестов не меняются.

### Правило раскладки по видам (применяется к каждому файлу механически)

| Текущее расположение (относительно `tests/`) | Что проверяет | Новое расположение (внутри `{M}/Tests`) |
|---|---|---|
| `Unit/Modules/{M}/Domain/**` | чистое доменное правило | `Unit/Domain/**` |
| `Unit/Modules/{M}/Application/**` | сценарий со всеми зависимостями-дублёрами | `Unit/Application/**` |
| `Unit/Modules/{M}/Public/**` (Public-vs-Domain parity, структурные проверки без Spiral/БД) | соответствие Public и Domain | `Unit/Domain/**` — у `Unit` нет подпапки `Public`, а проверка по сути доменная (сверяет проекцию с доменным перечислением) |
| `Unit/Modules/{M}/Infrastructure/Persistence/Cycle/**` (Mapper, Typecast — без реальной БД) | границу хранения Cycle | `Integration/Cycle/**` — тест границы хранения относится к `Integration/Cycle` независимо от того, открывает ли конкретный тест соединение к БД; `Unit` в целевом дереве не имеет подпапки `Infrastructure` |
| `Unit/Modules/{M}/Infrastructure/**` (прочее: Spiral-адаптеры, клиенты, ffmpeg, S3 с фейковым клиентом — без реального рантайма) | границу любой другой технологии Infrastructure | `Integration/Spiral/**` — по той же причине: `Unit` не имеет `Infrastructure`, а технология не Cycle |
| `Kernel/Modules/{M}/**` | реальный Spiral-контейнер/bootloader без HTTP | `Integration/Spiral/**` |
| `Feature/Modules/{M}/Migration/**`, `Feature/Modules/{M}/Repository/**`, `Feature/Modules/{M}/Reader/**` | хранение через реальный Cycle/БД | `Integration/Cycle/**` |
| `Feature/Modules/{M}/Http/**` | маршрут через настоящий Spiral runtime (см. `integration-test.md`) | `Feature/Spiral/**` |
| `Feature/Modules/{M}/{Application,Console,PublicApi,Flow,Infrastructure}/**` | сценарий/адаптер через настоящий Spiral-контейнер и БД, но не через HTTP | `Integration/Spiral/**` |
| Базовый `{Module}{Tier}TestCase.php` (например `PostsHttpTestCase`, `TagsApplicationTestCase`) на верхнем уровне модуля | общий каркас конкретного вида тестов модуля | переезжает в ту же новую папку, что и его наследники |

Уточнение подпапок внутри нового вида (добавлено при исполнении фазы 3 как разрешение обнаруженной неоднозначности, действует для всех фаз): `Unit/Domain` и `Unit/Application` СОХРАНЯЮТ исходные подпапки старого пути (`Entity/`, `ValueObject/`, `Enum/`, `Collection/`, `Service/`, `Event/`, `Command/{Action}/`, `Query/{Action}/` и т.п.), потому что перенос из `Domain/**`/`Application/**` в `Unit/Domain/**`/`Unit/Application/**` — это перенос 1:1 без консолидации: подпапка старого пути становится подпапкой нового пути без изменений. `Integration/Cycle` и `Integration/Spiral`, напротив, физически размещаются плоско (без подпапок), потому что это узлы КОНСОЛИДАЦИИ: в один вид `Integration/Spiral` сливаются файлы из многих разных старых расположений (`Kernel/Modules/{M}`, `Feature/Modules/{M}/Application`, `.../Console`, `.../PublicApi`, `.../Flow`, `.../Infrastructure`, `Unit/Modules/{M}/Infrastructure/Spiral`), а единой исходной подпапочной структуры, которую можно перенести без выдумывания новой иерархии, не существует — плоское размещение здесь единственный вариант, не требующий произвольного решения о новой иерархии. `docs/arch.md` не запрещает подпапки ни в одном из пяти листьев дерева `Tests/*` — он их просто не детализирует (как не детализирует и остальные короткие ветки дерева), поэтому решение определяется практическим критерием «перенос 1:1 сохраняет структуру, консолидация — нет», а не текстом документа буквально.

Файлов, требующих разделения на несколько (смешение видов внутри одного класса), правило не находит: каждый текущий файл целиком относится к одному старому набору (`Unit`/`Kernel`/`Feature`) и по таблице выше однозначно попадает в один новый вид. Если при переносе конкретного файла обнаружится смешение (часть тестов класса не поднимает инфраструктуру, часть — поднимает), класс делится по этому же критерию, и разделение фиксируется в журнале с причиной.

### Namespace и импорты

Namespace модульного теста — `App\Modules\{Module}\Tests\{Вид}\...`, повторяет путь файла (`docs/references/integration-test.md`, `docs/references/unit-test.md`). Класс `App\` уже покрывает `app/src` в `composer.json` (`"App\\": "app/src"`), отдельная запись автозагрузки не нужна. Импорты внутри перенесённых файлов, ссылавшиеся на старый namespace теста (`Tests\{Unit|Kernel|Feature}\Modules\{M}\...`), заменяются на новый; импорты классов приложения (`App\Modules\...`) не меняются, если только тест не перемещается в другой модуль (случаи ниже).

### Корневой `tests/`: что остаётся и почему

Правило: файл остаётся в `tests/`, если он сквозной (используется несколькими модулями), межмодульный (проверяет взаимодействие через шину или контейнер сразу нескольких модулей) или относится к `Shared`/каркасу тестового окружения — частям без владельца среди модулей (`docs/arch.md`, «Самодостаточность модуля», и прецедент базового `TestCase` в `docs/references/integration-test.md`).

| Остаётся | Причина |
|---|---|
| `tests/TestCase.php`, `tests/DatabaseTestCase.php`, `tests/NonTransactionalDatabaseTestCase.php`, `tests/RealStorageTestCase.php`, `tests/TestRuntime.php`, `tests/bootstrap.php`, `tests/warmup.php` | базовый каркас PHPUnit/Spiral-тестов для всех модулей сразу — прямой прецедент карточки `integration-test.md` |
| `tests/App/TestKernel.php` | тестовый Kernel собирает bootloader-ы всех модулей разом — по конструкции не принадлежит одному модулю |
| `tests/App/Bootloader/ApiErrorTestRoutesBootloader.php`, `tests/App/Modules/System/Http/{ApiErrorTestController,ApiErrorTestFilter}.php` | тестовые маршруты сквозной проверки формата ошибок API (общий `spiral-api-errors`-контракт), не бизнес-функциональность модуля System |
| `tests/Storage/FakeStorage.php` | фейковая реализация `StorageInterface`, используемая общим `DatabaseTestCase` |
| `tests/Support/Media/PersistsMedia.php` | сидинг Media используется тестами трёх разных модулей (Notifications, User, Posts) — нет единственного модуля-потребителя |
| `tests/Support/Migration/ReplaysMigration.php` | используется тестами миграций семи модулей |
| `tests/Support/Notifications/RecordingOutboxEventStore.php` | используется тестами Notifications и Posts |
| `tests/Support/Outbox/CleansOutboxEvents.php` (новое расположение, см. фазу Outbox) | используется тестами Outbox и Media |
| `tests/Feature/CqrsContainerTest.php` | сквозная проверка регистрации CQRS-шины для обработчиков всех модулей |
| `tests/Kernel/DemoTest.php` | демонстрационный smoke загрузки kernel, не привязан к модулю |
| `tests/Feature/Shared/Infrastructure/DockerRuntimeSmokeTest.php` | сквозной smoke реальной инфраструктуры окружения |
| весь `tests/Unit/Shared/**`, `tests/Kernel/Shared/**` | тестируют примитивы `Shared` (`TypedCollection`, `CursorSlice`, `LazyGhost*`, `ValueObjectCast`, `ConfigMapper`, `RouteAccess*`, `LocaleMiddleware`, `RateLimitMiddleware`, конфигурация) — `Shared` сам является частью без владельца среди модулей (`docs/arch.md`) |

Файл `tests/Support/Notifications/FixtureNotificationTypeDefinition.php` в этот список не входит: все его потребители (проверено grep) — тесты самого модуля Notifications во всех трёх видах (Unit, Integration/Spiral, Feature/Spiral). Он переезжает внутрь `Notifications/Tests` в фазе Notifications, в подпапку вида, где определён (`Unit/Application/Fixture`, как класс без зависимости от Spiral/БД), а тесты других видов того же модуля импортируют его оттуда по FQCN — это не нарушает запрет `Tests/Common`, потому что не создаёт отдельную папку-вид, а переиспользует существующий вид внутри одного модуля.

### Наборы PHPUnit

Имена наборов `Unit`, `Kernel`, `Feature` не меняются, меняется список включённых каталогов: каждый набор дополняется соответствующими каталогами всех 9 модулей.

| Набор PHPUnit | Каталоги |
|---|---|
| `Unit` | `tests/Unit` (только `Shared`) + `app/src/Modules/{M}/Tests/Unit` для каждого модуля с Unit-тестами |
| `Kernel` | `tests/Kernel` (только `Shared` + `DemoTest.php`) + `app/src/Modules/{M}/Tests/Integration` для каждого модуля с Integration-тестами (папка `Integration` рекурсивно содержит и `Cycle`, и `Spiral`) |
| `Feature` | `tests/Feature` (остаток) + `app/src/Modules/{M}/Tests/Feature` для каждого модуля с Feature-тестами |

Обоснование, что это не меняет наблюдаемое поведение прогонов: `make test-kernel` и `make test-feature` уже сегодня выполняют `migrate-test-databases.sh` перед своим набором (`Makefile`), то есть оба набора уже имеют доступ к БД в CI — перенос текущих `Feature/Modules/{M}/Migration|Repository|Reader` (нужны БД) в набор `Kernel` не лишает их инфраструктуры. `make test` и `make qa` прогоняют все три набора одним проходом после одного `migrate-test-databases.sh`, поэтому для них деление между `Kernel` и `Feature` не наблюдаемо вовсе.

### Покрытие и статический анализ

`phpstan.neon` анализирует `app/src` целиком и сегодня не видит `tests/` — тесты никогда не проверялись PHPStan. Чтобы физический перенос тестов внутрь `app/src` не включил их в анализ впервые в этой волне (это было бы новым требованием к содержимому тестов, которое волна прямо запрещает переписывать), `phpstan.neon` получает `excludePaths` с шаблоном `app/src/Modules/*/Tests/*` (PHPStan поддерживает `fnmatch`-шаблоны в `excludePaths`) — поведение анализа не меняется, только явно фиксируется прежняя граница.

`phpunit.xml`, секция `<source>`: `<include>` остаётся `app/src`, `<exclude>` получает явный список `app/src/Modules/{M}/Tests` для всех 9 модулей — тесты продолжают выполняться, но не попадают в отчёт покрытия и не портят порог 100% по коду приложения (сегодня `tests/` тоже не входит в отчёт, потому что не лежит под `app/src`).

`docker/test/assert-unit-suite-is-light.sh` сегодня жёстко сканирует только `tests/Unit`. Скрипт меняется на перебор каталогов `tests/Unit` и `app/src/Modules/*/Tests/Unit` (bash-глоб, без перечисления модулей поимённо — новый модуль не потребует правки скрипта) с той же проверкой запрещённых паттернов (`Tests\TestCase`, `getContainer(`, `Tests\App\TestKernel`, `Spiral\Testing\TestCase`) на каждом.

`composer.json` не меняется: `"App\\": "app/src"` уже покрывает новые namespace тестов, `"Tests\\": "tests"` остаётся верным для того, что остаётся в корне, скрипт `test-coverage` ссылается на наборы по имени, а не по пути. `.php-cs-fixer.php` не меняется: `Finder` уже сканирует весь репозиторий (`__DIR__`) за вычетом `runtime`, значит новое расположение тестов уже под проверкой стиля.

## Контракты реализации

### Данные и БД

Не затрагивается: схема, таблицы и миграции не меняются, конфигурация подключений не меняется.

### API и внешние контракты

Не затрагивается: маршруты, тела запросов/ответов, коды ошибок не меняются. Единственная проверяемая величина — неизменный md5 сгенерированной OpenAPI и прежние 33 маршрута (раздел «Приёмка»).

## Фазы выполнения

### 1. Модуль Access и общие однократные правки инструментов

Цель: перенести самый маленький модуль (4 файла: 1 доменный unit-тест, 3 Cycle-интеграционных теста) и один раз внести те правки инструментов, которые покрывают все последующие модули без повторной правки.

Что сделать: `tests/Unit/Modules/Access/Domain/AccessDomainTest.php` переезжает в `Access/Tests/Unit/Domain`; `tests/Feature/Modules/Access/{Migration/CreateAccessDomainTablesMigrationTest.php,Migration/DropUserRolesUserForeignKeyMigrationTest.php,Repository/AccessRepositoryTest.php}` переезжают в `Access/Tests/Integration/Cycle` — namespace и импорты обновляются по правилу из «Целевого алгоритма». `phpstan.neon` получает `excludePaths` с шаблоном `app/src/Modules/*/Tests/*` — эта правка покрывает все 9 модулей сразу и больше не повторяется. `docker/test/assert-unit-suite-is-light.sh` переписывается на перебор `tests/Unit` и `app/src/Modules/*/Tests/Unit` — тоже покрывает все модули сразу. `phpunit.xml` получает для Access: одну директорию в `Unit`, одну в `Kernel` (для `Integration`), запись в `<source><exclude>`.

Результат: `app/src/Modules/Access/Tests` содержит 4 файла по целевому дереву; `tests/Unit/Modules/Access` и `tests/Feature/Modules/Access` не существуют; `phpstan.neon` и `assert-unit-suite-is-light.sh` готовы к появлению тестов в любом из оставшихся 8 модулей без повторной правки.

Проверка:
- чтением кода: 4 файла лежат по таблице раскладки, namespace каждого повторяет путь;
- чтением кода: `phpstan.neon` содержит `excludePaths` с шаблоном для `*/Tests/*`;
- чтением кода: `assert-unit-suite-is-light.sh` итерирует оба каталога через глоб, а не перечисляет модули;
- чтением кода: `phpunit.xml` содержит новые записи для Access и не потерял записи `tests/Unit`, `tests/Kernel`, `tests/Feature`.

### 2. Модуль System

Цель: перенести 6 файлов System без Domain-слоя (у модуля нет своих таблиц).

Что сделать: `tests/Unit/Modules/System/Infrastructure/Spiral/Temporal/PingTest.php` переезжает в `System/Tests/Integration/Spiral` (тест адаптера Infrastructure/Spiral, у `Unit` нет подпапки `Infrastructure`). `tests/Feature/Modules/System/Console/{OpenApiGenerateCommandTest,OpenApiPublishAssetsCommandTest}.php` переезжают в `System/Tests/Integration/Spiral`. `tests/Feature/Modules/System/Http/{ApiErrorHttpTest,LocaleHttpTest,OpenApiHttpTest}.php` переезжают в `System/Tests/Feature/Spiral`; `ApiErrorHttpTest` продолжает импортировать тестовые маршруты из `Tests\App\Modules\System\Http\*`, которые остаются в корне (раздел «Корневой tests/»). `phpunit.xml` получает записи для System (`Kernel`-каталог из `Integration`, `Feature`-каталог, `<source><exclude>`); Unit-запись для System не добавляется — своих тестов у модуля в этом виде не остаётся.

Результат: `app/src/Modules/System/Tests` содержит 6 файлов (3 в `Integration/Spiral`, 3 в `Feature/Spiral`); `tests/Unit/Modules/System` и `tests/Feature/Modules/System` не существуют, `tests/App/Modules/System/Http/*` остаются в корне.

Проверка:
- чтением кода: 6 файлов лежат по таблице раскладки, namespace повторяет путь;
- чтением кода: `ApiErrorHttpTest` по-прежнему импортирует тестовые маршруты из корневого `Tests\App\Modules\System\Http`;
- чтением кода: `phpunit.xml` содержит записи для System.

### 3. Модуль Tags

Цель: перенести 10 файлов Tags (3 доменных, 3 Cycle-интеграционных с базовым классом `TagsRepositoryTestCase`, 4 Spiral-интеграционных с базовым классом `TagsApplicationTestCase`).

Что сделать: `tests/Unit/Modules/Tags/Domain/**` переезжает в `Tags/Tests/Unit/Domain`. `tests/Feature/Modules/Tags/{Migration/DropTagsCreatedByForeignKeyMigrationTest.php,Repository/TagRepositoryTest.php,TagsRepositoryTestCase.php}` переезжают в `Tags/Tests/Integration/Cycle` — базовый класс переезжает в ту же папку, что и его единственный наследник. `tests/Feature/Modules/Tags/{Application/GetTagsHandlerTest.php,Application/ResolveTagsHandlerTest.php,TagsApplicationTestCase.php,PublicApi/TagsProviderTest.php}` переезжают в `Tags/Tests/Integration/Spiral`. Докблок в `TagsRepositoryTestCase`, упоминающий дублирование кода с `Tests\Feature\Modules\Posts\PostsRepositoryTestCase` (комментарий, не импорт), обновляется на новый FQCN этого класса. `phpunit.xml` получает записи для Tags (`Unit`, `Kernel`, `<source><exclude>`; отдельного `Feature`-каталога у Tags нет — HTTP-тестов у модуля нет).

Результат: `app/src/Modules/Tags/Tests` содержит 10 файлов; `tests/Unit/Modules/Tags` и `tests/Feature/Modules/Tags` не существуют.

Проверка:
- чтением кода: 10 файлов лежат по таблице раскладки, базовые классы — рядом со своими наследниками;
- чтением кода: докблок с упоминанием Posts ссылается на новый FQCN;
- чтением кода: `phpunit.xml` содержит записи для Tags.

### 4. Модуль User

Цель: перенести 15 файлов User (2 доменных, 3 Cycle-mapper + 2 миграции + 1 репозиторий = 6 Cycle-интеграционных, 6 Spiral-интеграционных с базовым классом `UserApplicationTestCase` + 1 PublicApi).

Что сделать: `tests/Unit/Modules/User/Domain/**` переезжает в `User/Tests/Unit/Domain`. `tests/Unit/Modules/User/Infrastructure/Persistence/Cycle/Mapper/**` и `tests/Feature/Modules/User/{Migration/**,Repository/UserRepositoryTest.php}` переезжают в `User/Tests/Integration/Cycle`. `tests/Feature/Modules/User/{Application/**,PublicApi/UserProviderTest.php}` переезжают в `User/Tests/Integration/Spiral`, включая базовый класс `UserApplicationTestCase` рядом с наследниками. `UserApplicationTestCase` использует `Tests\Support\Media\PersistsMedia` из корня — импорт не меняется (файл остаётся в корне, раздел «Корневой tests/»). `phpunit.xml` получает записи для User (`Unit`, `Kernel`, `<source><exclude>`; `Feature`-каталога нет — своего HTTP у User нет).

Результат: `app/src/Modules/User/Tests` содержит 15 файлов; `tests/Unit/Modules/User` и `tests/Feature/Modules/User` не существуют.

Проверка:
- чтением кода: 15 файлов лежат по таблице раскладки;
- чтением кода: `UserApplicationTestCase` продолжает импортировать `Tests\Support\Media\PersistsMedia` из корня без изменений;
- чтением кода: `phpunit.xml` содержит записи для User.

### 5. Модуль Notifications

Цель: перенести 29 файлов Notifications, включая единственный переезд файла из корневого `tests/Support` внутрь модуля.

Что сделать: `tests/Unit/Modules/Notifications/{Domain/**,Public/**}` переезжают в `Notifications/Tests/Unit/Domain` (Public-parity тесты — по правилу «Public в Unit/Domain» из «Целевого алгоритма»). `tests/Unit/Modules/Notifications/Application/**` переезжает в `Notifications/Tests/Unit/Application`. `tests/Unit/Modules/Notifications/Infrastructure/Persistence/Cycle/**` переезжает в `Notifications/Tests/Integration/Cycle`. `tests/Unit/Modules/Notifications/Infrastructure/Spiral/**` и `tests/Feature/Modules/Notifications/{Application/**,PublicApi/**,Infrastructure/**}` переезжают в `Notifications/Tests/Integration/Spiral`. `tests/Feature/Modules/Notifications/{Migration/**,Repository/**}` переезжают в `Notifications/Tests/Integration/Cycle`. `tests/Feature/Modules/Notifications/Http/**` переезжает в `Notifications/Tests/Feature/Spiral`. `tests/Support/Notifications/FixtureNotificationTypeDefinition.php` переезжает в `Notifications/Tests/Unit/Application/Fixture` (namespace `App\Modules\Notifications\Tests\Unit\Application\Fixture`) — единственный класс волны, перемещаемый из корня внутрь модуля, потому что все его потребители (проверено grep) принадлежат Notifications во всех трёх видах; тесты видов Integration/Spiral и Feature/Spiral импортируют его по новому FQCN из `Unit`, что не создаёт папку `Common` — это переиспользование существующей папки вида, а не новый вид. `tests/Support/Notifications/RecordingOutboxEventStore.php` остаётся в корне без изменений (используется и Posts) — только обновляются импорты в перенесённых файлах Notifications, если ссылались на относительный путь. `phpunit.xml` получает записи для Notifications (`Unit`, `Kernel`, `Feature`, `<source><exclude>`).

Результат: `app/src/Modules/Notifications/Tests` содержит 29 файлов, включая перенесённую фикстуру; `tests/Unit/Modules/Notifications`, `tests/Feature/Modules/Notifications`, `tests/Support/Notifications/FixtureNotificationTypeDefinition.php` не существуют; `tests/Support/Notifications/RecordingOutboxEventStore.php` остаётся.

Проверка:
- чтением кода: 29 файлов лежат по таблице раскладки;
- чтением кода: `FixtureNotificationTypeDefinition` лежит в `Notifications/Tests/Unit/Application/Fixture`, и все её потребители в Notifications (включая Integration/Spiral и Feature/Spiral) импортируют её оттуда;
- чтением кода: `RecordingOutboxEventStore` осталась в `tests/Support/Notifications` без изменений;
- чтением кода: `phpunit.xml` содержит записи для Notifications.

### 6. Модуль Auth

Цель: перенести 36 файлов Auth, включая фикстуры уровня Application.

Что сделать: `tests/Unit/Modules/Auth/{Domain/**}` переезжает в `Auth/Tests/Unit/Domain`; `tests/Unit/Modules/Auth/Application/**` — в `Auth/Tests/Unit/Application`. `tests/Unit/Modules/Auth/Infrastructure/Persistence/Cycle/Mapper/**` — в `Auth/Tests/Integration/Cycle`. `tests/Unit/Modules/Auth/Infrastructure/Spiral/**`, `tests/Kernel/Modules/Auth/**` и `tests/Feature/Modules/Auth/{Application/**,Console/**,Infrastructure/**}` (включая фикстуры `Application/Fixture/**` и базовый класс `AuthApplicationTestCase`) — в `Auth/Tests/Integration/Spiral`. `tests/Feature/Modules/Auth/Migration/**` — в `Auth/Tests/Integration/Cycle`. `tests/Feature/Modules/Auth/Http/AuthHttpTest.php` — в `Auth/Tests/Feature/Spiral`. `phpunit.xml` получает записи для Auth (`Unit`, `Kernel`, `Feature`, `<source><exclude>`).

Результат: `app/src/Modules/Auth/Tests` содержит 36 файлов; `tests/Unit/Modules/Auth`, `tests/Kernel/Modules/Auth`, `tests/Feature/Modules/Auth` не существуют.

Проверка:
- чтением кода: 36 файлов лежат по таблице раскладки, включая фикстуры рядом с использующими их тестами;
- чтением кода: `phpunit.xml` содержит записи для Auth.

### 7. Модуль Outbox и вынос `CleansOutboxEvents`

Цель: перенести 37 файлов Outbox, включая большое число фикстур в `Infrastructure/Fixture`, и вынести общий трейт очистки outbox-событий в корень, потому что его использует ещё и Media.

Что сделать: `tests/Unit/Modules/Outbox/{Domain/**,Application/**}` переезжают в `Outbox/Tests/Unit/{Domain,Application}` по правилу. `tests/Unit/Modules/Outbox/Infrastructure/Persistence/Cycle/Mapper/**` — в `Outbox/Tests/Integration/Cycle`. `tests/Unit/Modules/Outbox/Infrastructure/**` (прочее), `tests/Kernel/Modules/Outbox/**` и `tests/Feature/Modules/Outbox/{Application/**,Console/**,Infrastructure/**}` (включая все файлы `Infrastructure/Fixture/**`) — в `Outbox/Tests/Integration/Spiral`. `tests/Feature/Modules/Outbox/Migration/**` и `tests/Feature/Modules/Outbox/Repository/**` — в `Outbox/Tests/Integration/Cycle`. Трейт `tests/Feature/Modules/Outbox/CleansOutboxEvents.php` переезжает не в модуль, а в `tests/Support/Outbox/CleansOutboxEvents.php` (namespace `Tests\Support\Outbox`) — используется тестами Outbox и Media (`MediaProcessingFlowTest`, ещё не перенесённым на этот момент). Семь потребителей внутри Outbox, переезжающих в этой же фазе, получают новый импорт `Tests\Support\Outbox\CleansOutboxEvents`; восьмой потребитель — `tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php`, физически ещё лежащий по старому пути до фазы Media, — тоже получает обновлённый импорт в этой фазе, чтобы не оставлять межмодульную ссылку разорванной между фазами. `phpunit.xml` получает записи для Outbox (`Unit`, `Kernel`, `Feature` — `Feature/Modules/Outbox` не пуст только за счёт `Console`, который относится к `Integration/Spiral`, поэтому отдельной `Feature`-записи для Outbox нет, `<source><exclude>` добавляется).

Результат: `app/src/Modules/Outbox/Tests` содержит 37 файлов; `tests/Support/Outbox/CleansOutboxEvents.php` существует и используется Outbox и (пока) старым путём Media; `tests/Unit/Modules/Outbox`, `tests/Kernel/Modules/Outbox`, `tests/Feature/Modules/Outbox` не существуют.

Проверка:
- чтением кода: 37 файлов лежат по таблице раскладки;
- чтением кода: `tests/Support/Outbox/CleansOutboxEvents.php` существует, все 7 файлов Outbox и `MediaProcessingFlowTest.php` импортируют его оттуда;
- чтением кода: `phpunit.xml` содержит записи для Outbox.

### 8. Модуль Posts

Цель: перенести 42 файла Posts, самого зависимого от чужих фикстур модуля (использует корневые `PersistsMedia` и `RecordingOutboxEventStore`).

Что сделать: `tests/Unit/Modules/Posts/{Domain/**,Application/**}` переезжают в `Posts/Tests/Unit/{Domain,Application}`. `tests/Unit/Modules/Posts/Infrastructure/Persistence/Cycle/Mapper/**` — в `Posts/Tests/Integration/Cycle`. `tests/Kernel/Modules/Posts/**` — в `Posts/Tests/Integration/Spiral`. `tests/Feature/Modules/Posts/{Migration/**,Reader/**,Repository/**,PostsRepositoryTestCase.php}` — в `Posts/Tests/Integration/Cycle` (базовый класс рядом с наследниками). `tests/Feature/Modules/Posts/Application/**` — в `Posts/Tests/Integration/Spiral`. `tests/Feature/Modules/Posts/Http/**` (включая базовый класс `PostsHttpTestCase`) — в `Posts/Tests/Feature/Spiral`. `PostsRepositoryTestCase` продолжает импортировать `Tests\Support\Media\PersistsMedia`, `PostsHttpTestCase` — `Tests\Support\Notifications\RecordingOutboxEventStore`; оба импорта из корня не меняются. `phpunit.xml` получает записи для Posts (`Unit`, `Kernel`, `Feature`, `<source><exclude>`).

Результат: `app/src/Modules/Posts/Tests` содержит 42 файла; `tests/Unit/Modules/Posts`, `tests/Kernel/Modules/Posts`, `tests/Feature/Modules/Posts` не существуют.

Проверка:
- чтением кода: 42 файла лежат по таблице раскладки, базовые классы — рядом с наследниками;
- чтением кода: `PostsRepositoryTestCase` и `PostsHttpTestCase` продолжают импортировать корневые фикстуры без изменений;
- чтением кода: `phpunit.xml` содержит записи для Posts.

### 9. Модуль Media

Цель: перенести 44 файла Media, последний и самый крупный по числу файлов модуль, включая закрытие ссылки на `CleansOutboxEvents`, вынесенной в фазе Outbox.

Что сделать: `tests/Unit/Modules/Media/{Domain/**,Application/**,Public/**}` переезжают в `Media/Tests/Unit/{Domain,Application}` (`Public/Enum/MediaConversionTypeEnumParityTest.php` — в `Unit/Domain` по правилу parity-тестов). `tests/Unit/Modules/Media/Infrastructure/Persistence/Cycle/**` — в `Media/Tests/Integration/Cycle`. `tests/Unit/Modules/Media/Infrastructure/**` (прочее: ffmpeg, imagick, S3 с фейковым клиентом, фикстуры) и `tests/Kernel/Modules/Media/**` — в `Media/Tests/Integration/Spiral`. `tests/Feature/Modules/Media/{Migration/**,Repository/**}` — в `Media/Tests/Integration/Cycle`. `tests/Feature/Modules/Media/{Application/**,Flow/**,Infrastructure/**,PublicApi/**}` — в `Media/Tests/Integration/Spiral`; `MediaProcessingFlowTest.php` из `Flow/` при переносе уже импортирует `Tests\Support\Outbox\CleansOutboxEvents` (обновлено фазой Outbox) — правка не повторяется. У Media нет собственного HTTP, поэтому `Feature/Spiral` для модуля не создаётся. `phpunit.xml` получает записи для Media (`Unit`, `Kernel`, `<source><exclude>`).

Результат: `app/src/Modules/Media/Tests` содержит 44 файла; `tests/Unit/Modules/Media`, `tests/Kernel/Modules/Media`, `tests/Feature/Modules/Media` не существуют; в `tests/` не осталось файлов, принадлежащих ровно одному модулю.

Проверка:
- чтением кода: 44 файла лежат по таблице раскладки;
- чтением кода: `MediaProcessingFlowTest.php` импортирует `Tests\Support\Outbox\CleansOutboxEvents`;
- чтением кода: `phpunit.xml` содержит записи для Media;
- чтением кода: под `tests/Unit/Modules`, `tests/Kernel/Modules`, `tests/Feature/Modules` не осталось ни одного каталога — все 9 модулей перенесены.

### 10. Приёмка волны H

Цель: подтвердить зелёный `make qa`, отдельно подтвердить, что `make test-unit` не поднимает инфраструктуру, и сверить числовые критерии приёмки.

Что сделать: прогони `make qa` в Docker; при красных находках исправляй точечно (только расположение/namespace/импорты/объявления наборов — не логику тестов) и повторяй `make qa`, закладывая несколько циклов. Если прогон падает на миграциях с `ReflectionException`, очисти `runtime/cache/listeners` и `runtime/cache/cycle.php` и повтори. Отдельно прогони `make test-unit` и убедись, что он проходит и не поднимает Spiral, БД, Redis, MinIO, очередь и сеть (это уже гарантирует `docker/test/assert-unit-suite-is-light.sh`, но факт зелёного прогона фиксируется отдельно от `make qa`). Сверь итоговое число тестов и утверждений в выводе `make qa` с базовым замером (не меньше 1549 тестов и 5294 утверждений) — числа не должны уменьшиться, поскольку тесты только переносились. Сверь `route:list` (33 маршрута) и md5 сгенерированной OpenAPI (`fd8d4a16c3994dddcfbf915caa85157b`) — они не должны были измениться, так как HTTP-слой не редактировался. Собери итоговый журнал: таблица «модуль → число перенесённых файлов» по всем 9 модулям, полный список того, что осталось в `tests/`, с причиной для каждого файла (или однородной группы файлов) из раздела «Целевой алгоритм» плюс любые найденные при переносе исключения (расщеплённые/переименованные файлы, если такие возникли).

Результат: `make qa` зелёный, кроме заранее известного падения `S3MediaFileServiceTest::testCopyObjectToPublicBucketIsAnonymouslyReadable`; `make test-unit` зелёный и не поднимает инфраструктуру; числа тестов/утверждений не меньше базовых; маршруты и OpenAPI не изменились; журнал волны фиксирует итоговую раскладку и причины всего, что осталось в корне.

Проверка:
- `make qa` — 0 ошибок PHPStan, 0 замечаний php-cs-fixer, 100% покрытие app/src (без `Modules/*/Tests`), все тесты зелёные кроме известного падения;
- отдельный `make test-unit` зелёный;
- зафиксированные числа тестов/утверждений ≥ 1549/5294;
- `route:list` — 33 маршрута, md5 OpenAPI совпадает;
- журнал содержит таблицу переноса по модулям и полный список остатка `tests/` с причинами.

## Тесты

`test_strategy: end_of_plan` — волна не добавляет нового поведения и не пишет новых тестов; работа фаз 1–9 — это сама по себе миграция тестов (перенос, namespace, импорты, регистрация в наборах), проверяемая чтением кода. Прогон тестов и покрытия выполняется один раз, в фазе 10, где и оценивается фактический результат переноса (зелёный `make qa`, зелёный отдельный `make test-unit`, сохранённые числа тестов/утверждений). Ни одна из фаз 1–9 не запускает `make qa`/`make test`/`make phpstan`/`make test-unit`.

## Логирование

Не применимо: волна не меняет поведение приложения и не добавляет новых точек логирования — она перемещает тестовые файлы и правит конфигурацию инструментов.

## Документация и эксплуатация

- `docs/arch.md` уже фиксирует целевое расположение тестов — правок документа не требуется.
- Журнал волны (фаза 10) — единственное место, где по правилу задачи фиксируется причина каждого оставшегося в `tests/` файла; отдельного обновления `docs/arch.md` или `docs/rules.md` это не требует.
- `.env`/`.env.example`, Dockerfile и CI не меняются — сборка образов не различает `app/src/Modules/*/Tests` и остальной `app/src`, а самодостаточность модуля (включая тесты внутри него) — уже описанное целевое состояние `docs/arch.md`.

## Принятые решения

1. Тесты Infrastructure-слоя (Mapper, Typecast, Spiral-адаптеры, клиенты, ffmpeg/S3 с фейковыми зависимостями), сегодня лежащие в наборе `Unit` и не поднимающие реальную инфраструктуру, физически переезжают в `Integration/{Cycle,Spiral}`, а не остаются в `Unit`, потому что целевое дерево `docs/arch.md` ограничивает `Tests/Unit` подпапками `{Domain,Application}` и не содержит `Infrastructure`; раскладка при этом остаётся смысловой, а не механической, так как в phpunit-наборе `Unit` физически остаются ровно те файлы, что и раньше не поднимали инфраструктуру — только под новым видом `Integration`. Autonomous, обоснование — прямое прочтение дерева `docs/arch.md` и карточек `integration-test.md`/`unit-test.md`, которые описывают только `Domain` и `Application` для `Unit`.
2. `tests/Support/Notifications/FixtureNotificationTypeDefinition.php` переезжает внутрь модуля Notifications (единственный переезд файла из корня внутрь модуля в этой волне), потому что фактическая проверка потребителей (grep по всему `tests/` и `app/src/`) показала: все потребители — тесты Notifications. `tests/Support/Media/PersistsMedia.php`, `tests/Support/Notifications/RecordingOutboxEventStore.php`, `tests/Support/Migration/ReplaysMigration.php` остаются в корне — их фактические потребители (тоже проверено grep, а не взято из устаревшей карты расхождений) принадлежат нескольким разным модулям. Autonomous.
3. `tests/Feature/Modules/Outbox/CleansOutboxEvents.php` переезжает в `tests/Support/Outbox/CleansOutboxEvents.php` (а не остаётся внутри Outbox и не дублируется), потому что его использует и Media. Карта расхождений утверждала более широкий список потребителей (Auth, Media, Notifications) — фактическая проверка grep подтвердила только Media; решение принято по факту, а не по карте. Autonomous.
4. `phpstan.neon` получает `excludePaths` на `app/src/Modules/*/Tests/*` вместо включения тестов в анализ level max, потому что тесты никогда не входили в `paths` PHPStan (только `app/src` без тестов) — физический перенос внутрь `app/src` не должен де-факто расширять область анализа новым требованием к содержимому тестов, а волна прямо запрещает переписывать содержимое тестов за пределами необходимого для переноса. Autonomous.
5. `composer.json` и `.php-cs-fixer.php` не меняются: автозагрузка `App\\ -> app/src` уже покрывает новые namespace тестов, а `Finder` php-cs-fixer уже сканирует весь репозиторий. Это расходится с предположением карты расхождений о необходимости правки `composer.json` — предположение сделано до фактической проверки текущей конфигурации. Autonomous.
6. Порядок фаз 1–9 идёт по фактическому числу файлов на модуль (посчитано `find` по актуальному дереву на момент начала волны: Access 4, System 6, Tags 10, User 15, Notifications 29, Auth 36, Outbox 37, Posts 42, Media 44), а не по числам из устаревшей карты расхождений, которая писалась до волн A–G и по собственному предупреждению карты могла устареть в деталях. Autonomous.
7. `test_strategy: end_of_plan` переопределяет дефолт `after_each_phase` из `docs/settings.yaml` по прямому указанию заказчика волны: фазы 1–9 не запускают проверки, единственный прогон `make qa` и отдельный `make test-unit` — в фазе 10. Источник — явное указание в задаче волны, а не autonomous-выбор.

## Прогресс выполнения

Журнал: `docs/artifacts/executions/2026-09-17_11-30_volna-h-testy-vnutri-modulej.md`

| Фаза | Статус |
|---|---|
| 1. Модуль Access и общие однократные правки инструментов | done |
| 2. Модуль System | done |
| 3. Модуль Tags | done |
| 4. Модуль User | done |
| 5. Модуль Notifications | done |
| 6. Модуль Auth | done |
| 7. Модуль Outbox и вынос CleansOutboxEvents | done |
| 8. Модуль Posts | done |
| 9. Модуль Media | done |
| 10. Приёмка волны H | pending |
