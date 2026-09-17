---
title: Волна A переезда на целевую архитектуру — структурный перенос
date: 2026-09-15 17:41
mode: normal
plan_size: normal
decision_mode: autonomous
status: draft
reviewer: none
plan_review: none
plan_review_fix: none
test_strategy: after_each_phase
logging_strategy: debug_precise
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  business: []
  references: [docs/references/bootloader.md, docs/references/api-resource.md, docs/references/http-controller.md, docs/references/http-filter.md, docs/references/http-middleware.md, docs/references/http-response.md, docs/references/console-command.md, docs/references/job-consumer.md, docs/references/typed-config.md, docs/references/typecast.md, docs/references/cycle-repository.md, docs/references/reader.md]
  research: docs/artifacts/researches/2026-09-15_17-25_karta-rashozhdenij-s-celevoj-arhitekturoj.md
---

# План реализации

## Задача

Перевести дерево `app/src` на целевую структуру `docs/arch.md` в части размещения файлов: общая часть, входные адаптеры модулей, инфраструктурные границы модулей и bootloader-ы. Это задачи roadmap 2, 3, 4, 17, 18, 22 и файловая часть 19.

Готово, когда: у Shared нет слоя `Presentation` и папки `Infrastructure/Framework`, Shared не импортирует ни один бизнес-модуль (кроме списка bootloader-ов в Kernel), ни у одного из девяти модулей нет верхнеуровневого `Presentation`, у каждого модуля ровно один bootloader в `Infrastructure/Spiral/Bootloader`, Kernel перечисляет девять bootloader-ов модулей, и `make qa` даёт тот же результат, что до начала работ.

В план не входит: создание слоя `Public`, отделение домена от Cycle, введение Reader и Data, переезд Repository в `Domain`/`Infrastructure`, переезд миграций, переводов, секций конфигурации и тестов внутрь модулей, снятие межмодульных вызовов в обход `Public`, изменение `ConfigBootloader` как механизма и `app/config/migration.php`. Это волны B и далее (задачи roadmap 5–16, 20, 21, 23, 24).

## Целевой алгоритм

Наблюдаемое поведение системы не меняется ни в одной точке. Переезд затрагивает только физическое размещение классов и строки, которые на них ссылаются.

```text
Что меняется                        Что остаётся неизменным
namespace и путь файла              тело класса, сигнатуры, докблоки по смыслу
use-импорты у потребителей          набор и порядок HTTP-маршрутов
строковые ссылки в app/config       форма запроса и ответа, коды ошибок
пути сканирования (openapi, config) схема базы и содержимое миграций
регистрация во view-namespace       содержимое шаблонов и переводов
список bootloader-ов в Kernel       порядок и состав HTTP-middleware
namespace и импорты в tests/        набор тестов и их утверждения
```

Целевое дерево общей части:

```text
Shared/Domain/                                  без изменений, кроме ухода TagId и HasTimestamps
Shared/Infrastructure/Persistence/Cycle/        бывшие Infrastructure/Cycle и Infrastructure/Database
                                                + HasTimestamps
Shared/Infrastructure/Spiral/Kernel.php         бывший Infrastructure/Framework/Kernel.php
Shared/Infrastructure/Spiral/DirectoryAlias.php
Shared/Infrastructure/Spiral/Bootloader/        шесть общих bootloader-ов без OpenApiBootloader
Shared/Infrastructure/Spiral/Configuration/     бывшая Infrastructure/Configuration
Shared/Infrastructure/Spiral/Cache/             бывшая Infrastructure/Cache
Shared/Infrastructure/Spiral/Http/Middleware/   LocaleMiddleware, RateLimitMiddleware
Shared/Infrastructure/Spiral/Http/Resource/     AbstractResource
Shared/Infrastructure/Exception/                без изменений
```

Целевое дерево модуля:

```text
Modules/{M}/Infrastructure/Spiral/Bootloader/
Modules/{M}/Infrastructure/Spiral/Http/{Controller,Filter,Middleware,Resource,Enum,View}/
Modules/{M}/Infrastructure/Spiral/{Job,Console,Temporal,Auth,Hash,Mail,Queue,Registry}/
Modules/{M}/Infrastructure/Spiral/Resources/views/
Modules/{M}/Infrastructure/Persistence/Cycle/Typecast/
Modules/{M}/Infrastructure/{Storage,Client,Ffmpeg,Imagick,Relay,Serializer}/
```

Папка создаётся только там, где для неё есть файл: разделы `Entity`, `Mapper`, `Repository`, `Read`, `Columns`, `Migration` внутри `Persistence/Cycle` в этой волне не появляются, потому что таких файлов ещё нет.

## Контракты реализации

### Данные и БД

Не затрагивается.

### API и внешние контракты

Не затрагивается. Проверка неизменности: сгенерированная спецификация `public/openapi/openapi.yml` после переезда совпадает с текущей — имена схем строятся по коротким именам классов, а они не меняются.

## Фазы выполнения

### 1. Общие примитивы Cycle в Persistence

Цель: убрать из общей части папки `Infrastructure/Cycle` и `Infrastructure/Database`, которые целевое дерево не предусматривает.

Что сделать: девять классов из `Shared/Infrastructure/Cycle` и два из `Shared/Infrastructure/Database` переезжают в единый раздел `Shared/Infrastructure/Persistence/Cycle` — именно этот путь ожидают карточки `cycle-repository.md` и `reader.md`, когда описывают `AbstractRepository` и `WhenSelect`. Вместе с файлами меняются импорты у всех потребителей в `app/src` и `tests`. Маркер санкционированной массовой записи `SetBasedWrite` меняет своё полное имя, поэтому параметр `setBasedWriteMarkerInterface` в `phpstan.neon` и точечный игнор для `LazyGhostEntityFactory` указывают на новые значения. Строковая ссылка на `LazyGhostMapper` в `app/config/cycle.php` обновляется вместе с ними.

Результат: `app/src/Shared/Infrastructure/Cycle` и `app/src/Shared/Infrastructure/Database` не существуют; правило массовой записи продолжает срабатывать на `NotificationBulkWriter`.

Проверка:
- `make qa` завершается с тем же результатом, что до начала работ.

### 2. Общая часть под Spiral

Цель: собрать все зависимости общей части от Spiral Framework в `Shared/Infrastructure/Spiral` и убрать общий слой `Presentation`.

Что сделать: `Kernel` и `DirectoryAlias` поднимаются из `Infrastructure/Framework` в `Infrastructure/Spiral`, шесть оставшихся общих bootloader-ов — в `Infrastructure/Spiral/Bootloader`, `LocaleMiddleware` и `RateLimitMiddleware` — в `Infrastructure/Spiral/Http/Middleware` по карточке `http-middleware.md`, а `AbstractResource` — в `Infrastructure/Spiral/Http/Resource` по карточке `api-resource.md`; после этого папки `Shared/Presentation` и `Shared/Infrastructure/Framework` исчезают. Раздел типизированных конфигов переезжает в `Infrastructure/Spiral/Configuration`, как того требует карточка `typed-config.md`, а `RedisCacheStorage` — в `Infrastructure/Spiral/Cache`. `ConfigBootloader` продолжает сканировать один каталог тем же способом, но его константы каталога и префикса namespace указывают на новое место — иначе типизированные конфиги перестанут связываться. Точки входа `app.php` и `tests/App/TestKernel.php` и строковая ссылка в `app/config/cache.php` обновляются под новые имена.

Результат: общая часть содержит только `Domain`, `Infrastructure/Persistence`, `Infrastructure/Spiral` и `Infrastructure/Exception`; приложение и тестовое ядро стартуют, все типизированные конфиги связываются как прежде.

Проверка:
- `make qa` завершается с тем же результатом, что до начала работ.

### 3. Общая часть без кода модулей

Цель: закрыть требование `docs/arch.md` «Shared не импортирует бизнес-модули».

Что сделать: три медиа-представления из `Shared/Application/View` переезжают в `Media/Application/View`, три медиа-ресурса из бывшего `Shared/Presentation/Http/Resource` — в `Media/Infrastructure/Spiral/Http/Resource`; их потребители в Posts, User и Notifications продолжают импортировать те же классы по новому адресу, потому что снятие межмодульных импортов — предмет следующей волны. `TagId` переезжает из `Shared/Domain/ValueObject` к владельцу в `Tags/Domain/ValueObject`. Трейт `HasTimestamps` несёт разметку Cycle, поэтому уходит из `Shared/Domain/Trait` в общие примитивы `Shared/Infrastructure/Persistence/Cycle`; сущности продолжают его использовать без изменения тела. `OpenApiBootloader` общей части перестаёт существовать: регистрацию двух консольных команд System принимает на себя `SystemBootloader`, а Kernel теряет упоминание общего bootloader-а.

Результат: в `app/src/Shared` нет ни одного импорта `App\Modules\*`, кроме списка bootloader-ов в `Kernel`; команды `openapi:generate` и `openapi:publish-assets` доступны из CLI как прежде.

Проверка:
- `make qa` завершается с тем же результатом, что до начала работ.

### 4. HTTP-адаптеры модулей внутри инфраструктуры

Цель: перенести HTTP-границу четырёх модулей в `Infrastructure/Spiral/Http` — задача roadmap 17.

Что сделать: контроллеры, фильтры, middleware, ресурсы, а также вспомогательные `Enum` и `View` модулей Auth, Notifications, Posts и System переезжают из `Presentation/Http` в `Infrastructure/Spiral/Http` с сохранением вложенных папок фильтров Notifications. Генератор OpenAPI ищет типы по путям, поэтому `app/config/openapi.php` сканирует `app/src/Modules/*/Infrastructure/Spiral/Http`; отдельная запись про общие ресурсы Shared уходит, так как медиа-ресурсы уже лежат внутри Media и попадают под ту же маску. Точечный игнор PHPStan для `AuthContextAttributeMiddleware` указывает на новый путь файла. Шаблоны генерации в `app/config/scaffolder.php` называют целевые namespace, чтобы новый код создавался сразу в целевом дереве.

Результат: у Auth, Notifications, Posts и System в `Presentation` остаются только не-HTTP части; спецификация OpenAPI генерируется в прежнем виде.

Проверка:
- `make qa` завершается с тем же результатом, что до начала работ.

### 5. Остальные входные адаптеры и ресурсы модулей

Цель: убрать верхнеуровневый `Presentation` у всех модулей — задачи roadmap 18 и 22.

Что сделать: обработчики очереди Auth, Media, Notifications и Outbox переезжают в `Infrastructure/Spiral/Job` по карточке `job-consumer.md`, консольная команда Outbox и две команды System — в `Infrastructure/Spiral/Console` по карточке `console-command.md`, workflow `Ping` — в `Infrastructure/Spiral/Temporal`. Исключение публикации ассетов OpenAPI перестаёт быть частью транспорта и уходит в `System/Application/Exception`, куда его относит целевое дерево. Шаблоны `login-code.twig` и `swagger/index.twig` переезжают в `Infrastructure/Spiral/Resources/views` своих модулей, а bootloader-ы Auth и System регистрируют новый путь под теми же view-namespace `auth` и `system`, поэтому ссылки на шаблоны в коде не меняются. Реестр очередей `app/config/queue.php` называет Job по новым именам, включая закомментированные примеры, чтобы в конфиге не осталось несуществующих классов.

Результат: `find app/src/Modules -maxdepth 2 -name Presentation` не находит ничего; письмо с кодом входа и страница Swagger UI отдаются как прежде; задачи из очереди разбираются теми же обработчиками.

Проверка:
- `make qa` завершается с тем же результатом, что до начала работ.

### 6. Технические границы модулей по целевым папкам

Цель: разложить инфраструктуру модулей по явно названным границам — задачи roadmap 4 в части размещения bootloader-ов и файловая часть 19.

Что сделать: bootloader каждого модуля переезжает в `Infrastructure/Spiral/Bootloader` по карточке `bootloader.md`, typecast-классы всех модулей — в `Infrastructure/Persistence/Cycle/Typecast` по карточке `typecast.md`, туда же уходит `NotificationBulkWriter` и хранилище событий Outbox, работающее с Cycle. Остальные границы получают собственные имена: у Auth это `Spiral/Auth`, `Spiral/Hash` и `Spiral/Mail`, у Media — `Storage` для S3, URL и планировщика загрузки, `Ffmpeg` и `Imagick` для процессоров вместе с их исключениями, у Notifications — `Client` для Centrifugo и FCM и `Spiral/Registry` для реестра видов уведомлений, у Outbox — `Spiral/Queue`, `Spiral/Registry`, `Relay` и `Serializer`. Bootloader-ы, которые связывают контракты с этими реализациями, обновляют свои импорты, состав привязок при этом не меняется.

Результат: в `Infrastructure` каждого модуля нет папок вне целевого перечня; все контракты Application связаны с теми же реализациями, что и до переезда.

Проверка:
- `make qa` завершается с тем же результатом, что до начала работ.

### 7. Один bootloader на модуль и чистый композиционный корень

Цель: завершить задачу roadmap 4 — девять модулей, девять bootloader-ов, Kernel без внутренней настройки модулей.

Что сделать: Access, Tags и User получают собственные bootloader-ы; сегодня им нечего связывать, поэтому каждый объявляет только себя и служит точкой подключения модуля, к которой следующие волны добавят конфигурацию, миграции и реализации контрактов. Два bootloader-а Outbox сводятся к одному: регистрация консольной команды переходит в `OutboxBootloader`, который объявляет зависимость от консольного bootloader-а Spiral, а `OutboxConsoleBootloader` удаляется. Kernel перечисляет девять bootloader-ов модулей одним блоком в порядке, где Outbox предшествует Notifications, а Notifications — Posts: этот порядок задан тем, что Notifications получает реестр Job из Outbox, а Posts — реестр видов уведомлений из Notifications.

Результат: `Modules/*/Infrastructure/Spiral/Bootloader` содержит ровно девять классов; Kernel не содержит регистрации команд, шаблонов и привязок отдельных модулей.

Проверка:
- `make qa` завершается с тем же результатом, что до начала работ;
- список команд `php app.php list` содержит `outbox:relay`, `openapi:generate` и `openapi:publish-assets`.

## Тесты

Стратегия — проверка после каждой фазы. Новых тестов волна не добавляет и существующих не удаляет: переезд не создаёт ни одной новой ветви поведения, а значит и нового кода, который требовал бы покрытия. Три новых bootloader-а объявляются без методов, поэтому не добавляют исполняемых строк и не снижают покрытие.

В тестах правятся только `namespace`, `use` и пути каталогов: наборы `Unit`, `Kernel`, `Feature` остаются в корневом `tests/`, состав и утверждения тестов не меняются. Каталоги вида `tests/**/Modules/{M}/Presentation` переименовываются вслед за продуктивным деревом, чтобы имя набора продолжало называть проверяемую границу.

Критерий приёмки каждой фазы и всей волны — `make qa`: php-cs-fixer без замечаний, PHPStan level max без ошибок, покрытие не ниже 100%, из тестов падает только известный до начала работ `Tests\Feature\Modules\Media\Infrastructure\S3MediaFileServiceTest::testCopyObjectToPublicBucketIsAnonymouslyReadable`. Любое другое падение чинится в причине, а не подавлением: добавление baseline, игнора PHPStan или пропуска теста запрещено правилами проекта.

## Логирование

Стратегия `debug_precise` применяется к новому коду, а новый код в волне — только три bootloader-а без тела, поэтому новых записей журнала не появляется. Существующие вызовы журнала переносятся дословно: сообщения, уровни и контекстные поля сохраняются, в том числе в `OutboxRelayWorker` и `LoggingBootloader`. Запрещено при переезде добавлять в контекст журнала новые поля и печатать в него пути и namespace классов.

## Документация и эксплуатация

`docs/arch.md`, `docs/references.md` и карточки уже описывают целевое дерево и правок не требуют. Отметка о завершённых задачах roadmap не ставится: roadmap фиксирует состояние, а его актуализация — предмет задачи 30.

Переменные окружения, `.env.sample`, `.rr.yaml` и состав docker-сервисов не меняются. Для эксплуатации значимо одно: команда `outbox:relay --loop`, которую запускает постоянный процесс, сохраняет имя и поведение.

## Принятые решения

Все решения приняты автономно: пользователь недоступен, режим запуска — без вопросов. Основание каждого — `docs/arch.md`, `docs/rules.md` или карточка `docs/references/`.

`AbstractResource` размещается в `Shared/Infrastructure/Spiral/Http/Resource`, а не в `Http/Response`, как предполагала карта расхождений: карточка `api-resource.md` прямо показывает импорт `App\Shared\Infrastructure\Spiral\Http\Resource\AbstractResource`, и карточка как эталон формы важнее предположения карты.

Медиа-представления переезжают в `Media/Application/View`, а медиа-ресурсы — в `Media/Infrastructure/Spiral/Http/Resource` целиком, без копий у потребителей. Копии в каждом потребителе, которые предлагает карта, появляются вместе с `Public/Dto` в задаче 7; в этой волне они создали бы новые классы и дублирование при том, что межмодульные импорты всё равно остаются.

`HasTimestamps` переезжает в `Shared/Infrastructure/Persistence/Cycle` целиком, вместе с атрибутами `#[Column]`. Разделение трейта на доменную и Cycle-часть возможно только после появления Cycle Entity, то есть в задачах 13 и 14; до тех пор перенос в примитивы Cycle — единственный способ убрать импорт `Cycle\Annotated` из `Shared/Domain`.

`CycleTokenStorage` Auth не разделяется на два адаптера и переезжает как есть в `Spiral/Auth`: разделение меняет содержимое классов, что волна прямо запрещает, и относится к задаче 11.

Исключение публикации ассетов OpenAPI переезжает в `System/Application/Exception`, хотя слоя Application у модуля пока нет. Место указано картой расхождений и целевым деревом; альтернатива — оставить его в `Infrastructure/Spiral/Console` — потребовала бы второго переезда в задаче 16.

Внешние клиенты Notifications — Centrifugo и FCM — кладутся в общую границу `Infrastructure/Client` без подпапок по технологиям: `docs/arch.md` называет `Client` границей внешних HTTP-клиентов, и оба класса являются именно ими.

Процессоры медиа разделяются на `Infrastructure/Ffmpeg` и `Infrastructure/Imagick`, а не складываются в одну папку: это две разные технологии, а `docs/arch.md` требует собирать каждую в явно названной границе. Исключения процессоров переезжают к своей границе, а не в общий раздел исключений.

Новые bootloader-ы Access, Tags и User объявляются без методов. Пустой метод-заглушка был бы созданным этим изменением мёртвым кодом, что запрещает `docs/rules.md`, и уменьшил бы покрытие.

Константы каталога и namespace в `ConfigBootloader` обновляются, хотя волна не трогает сам механизм сканирования: без этого типизированные конфиги не найдутся после переезда. Замена жёсткого каталога реестром путей остаётся задачей 23.

Дублирование регистрации Job — в `OutboxJobRegistry` и в `app/config/queue.php` — сохраняется, в конфиге обновляются только имена классов. Снятие дублирования меняет механизм доставки и относится к задаче 5.

Общая папка `app/views` содержит только `.gitkeep` и модульных шаблонов не содержит, поэтому в этой волне переносится только то, что уже лежит внутри модулей; требование задачи 22 закрывается сменой пути на `Infrastructure/Spiral/Resources/views`.

## Прогресс выполнения

Журнал: `docs/artifacts/executions/2026-09-15_17-45_volna-a-strukturnyj-pereezd.md`

- [x] 1. Общие примитивы Cycle в Persistence
- [x] 2. Общая часть под Spiral
- [x] 3. Общая часть без кода модулей
- [x] 4. HTTP-адаптеры модулей внутри инфраструктуры
- [x] 5. Остальные входные адаптеры и ресурсы модулей
- [x] 6. Технические границы модулей по целевым папкам
- [x] 7. Один bootloader на модуль и чистый композиционный корень
