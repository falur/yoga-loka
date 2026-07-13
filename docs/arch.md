# Архитектура: модульный монолит на Spiral + CQRS + тактический DDD

## Обзор

YogaLoka - API-first backend для мобильного приложения на PHP 8.5, Spiral
Framework, RoadRunner и Cycle ORM.

Приложение работает как модульный монолит: один runtime, один deploy и один код
приложения. Код группируется в модули по предметной области.

## Модули

Проект устроен как модульный монолит с прагматичным DDD-подходом.

Бизнес-логика живёт в `Domain`. Важные значения оформляются как
`ValueObject`, сущности содержат поведение, а технические детали вынесены в
`Infrastructure`.

DDD-паттерны не вводятся автоматически. Aggregate, Domain Service, Domain Event,
отдельная read model или anti-corruption layer добавляются только когда они
решают реальную проблему в коде.

Модуль - это отдельная область приложения: `Media`, `User`, `Feed`, `Auth`.

Структура нового модуля:

```text
Modules/
  Media/
    Domain/
    Application/
      Contract/
    Repository/
    Infrastructure/
      Cycle/
      FileService/
    Presentation/
```

Назначение слоёв:

```text
Domain         - бизнес-логика модуля.
Application    - сценарии модуля и контракты для технических зависимостей.
Repository     - доступ к БД своего модуля через доменные методы.
Infrastructure - технические реализации контрактов, Cycle typecast, S3, внешние сервисы.
Presentation   - HTTP-контроллеры, фильтры запросов и другие входы.
```

Исключения каждого слоя лежат в папке `Exception/` своего слоя:
`Application/Exception`, `Infrastructure/Exception`, `Presentation/Exception`, а
общие доменные — в `Shared/Domain/Exception`. Слой исключения определяется
контрактом, к которому оно относится, а не местом выброса: например
`OutboxMessageLoadingException` лежит в `Application/Exception`, хотя бросается в
инфраструктурной реализации загрузчика сообщений.

Правила связей:

```text
Domain не зависит от других слоёв.

Application может использовать:
- свой Domain;
- свой Repository;
- свои Contract;
- Application других модулей.

Infrastructure реализует Contract своего модуля.

Presentation вызывает Application своего модуля.
```

Контракты лежат в:

```text
Modules/{Module}/Application/Contract
```

Имена контрактов заканчиваются на `Contract`.

Примеры:

```text
MediaFileServiceContract
```

Репозитории лежат в:

```text
Modules/{Module}/Repository
```

Пример:

```text
MediaRepository
```

Репозитории не являются публичным API модуля. `Application` своего модуля может
использовать свои репозитории напрямую, но другие модули не обращаются к ним.

Технические реализации контрактов лежат в `Infrastructure`:

```text
Infrastructure/
  Cycle/        # typecast и другие классы для Cycle ORM
  FileService/  # техническая работа с файлами
```

Примеры:

```text
S3MediaFileService
```

Общий доменный код, который не принадлежит одному модулю, лежит в `Shared`.

Пример:

```text
Shared/
  Domain/
    Exception/
    Trait/
    ValueObject/
  Application/
    View/
  Presentation/
    Http/
      Resource/
```

Другие модули могут обращаться только к `Application`.

Хорошо:

```text
User/Application -> Media/Application
```

Плохо:

```text
User/Application -> Media/Infrastructure
User/Infrastructure -> Media/Infrastructure
User -> media_files table
User -> MediaRepository
```

Пример:

```text
User/Application/SetAvatar
  -> Media/Application/CheckMediaIsImage
  -> User/Domain/User::setAvatar
```

`Media` не должен знать про аватар. Аватар - это часть `User`.

### Исключение: `Media` — foundational-модуль

`Media` — универсальный (foundational) модуль: хранение и раздача файлов нужны почти любому
модулю. Поэтому для него действует осознанное исключение из правила «модули общаются только через
`Application`»: другим модулям разрешено **держать ORM-relation на сущности `Media` (на чтение)** и
**передавать загруженную сущность `Media` в Application-сервисы `Media`**.

Зачем: чтение списков с вложениями (лента `Posts`) должно грузить медиа и их конверсии вместе с
основной выборкой (`->load('media.imageConversions'...)`), а не разрешать URL поэлементно (N+1).
Для этого `PostMedia` объявляет `#[BelongsTo(target: Media::class, ..., cascade: false, fkCreate:
false)]` и eager-грузит её в репозитории, после чего URL строится из уже загруженной сущности без
обращений в БД: лента получает URL через `MediaUrlService::getUrls(Media $media)` и берёт из набора
только оригинал (eager-загруженные конверсии держатся под планируемый показ превью); тот же метод
отдаёт полный набор «оригинал + конверсии» (путь `FindMediaUrl`).

Что по-прежнему **запрещено** даже для `Media`: использовать `MediaRepository` или `Media/Infrastructure`
из другого модуля; писать/менять данные `Media` через relation (поэтому `cascade: false`); заводить
кросс-модульный FK ради такой связи (`fkCreate: false` — FK либо уже есть в миграции, либо его нет).
Запись и изменение медиа идут только через Command-сценарии `Media/Application`.

`Media` должен давать только свои сценарии:

```text
CreateMedia
DeleteMedia
RemoveMediaOriginal
CheckMediaExists
CheckMediaIsImage
FindMediaUrl
FindMediaUrls
```

## Локальный Docker-runtime

Локальная разработка и проверки выполняются через Docker Compose из
`docker/docker-compose.dev.yml`. Приложение запускается в двух RoadRunner runtime:

- `app-http`: RoadRunner слушает `0.0.0.0:8080` только внутри контейнера.
  Наружу compose публикует сервис как `127.0.0.1:60080 -> 8080`. RoadRunner
  jobs consumer для RabbitMQ работает в том же процессе.
- `temporal-worker`: отдельный Temporal worker на task queue `default`.

Dev runtime использует RabbitMQ как queue connection по умолчанию. Memory
pipeline остаётся запасным вариантом для локальных экспериментов и обратной
совместимости, но не является основной очередью dev-стенда. Redis в локальном
стенде используется для cache/session и RoadRunner KV, но не является брокером
очереди.

Локальная инфраструктура: PostgreSQL, Redis, RabbitMQ, MinIO, Mailpit, Temporal,
Temporal UI и Centrifugo. Dev storage по умолчанию использует MinIO bucket
`yoga-loka`, тесты используют отдельные `yoga_loka_test` и `yoga-loka-test`.

## Структура каталогов

```
app/
  config/                         # Spiral config-файлы; env() допустим только здесь
  database/
    migrations/                   # Cycle ORM миграции
  locale/                         # Переводы
  src/
    Modules/
      Media/
        Domain/                   # Entity, ValueObject, Enum, доменные коллекции
        Application/              # Command/Query сценарии модуля
          Contract/               # Контракты технических сервисов, *Contract
          Service/                # Внутримодульные stateless-помощники Application (резолверы/чекеры): инкапсулируют репозитории своего модуля под один use-case-вопрос
          View/                   # Read-model: {Name}View и {Name}ViewAssembler
        Repository/               # Cycle repositories с доменными методами
        Infrastructure/
          Cycle/                  # Typecast и другие классы Cycle ORM
          FileService/            # Реализации файловых сервисов
        Presentation/             # HTTP, console, queue, Temporal входы модуля

      Outbox/
        Domain/                   # Outbox-события, статусы, value object
        Application/
          Command/                # Сценарии relay и обработки outbox-сообщений
          Contract/               # OutboxEventStoreContract и сериализация сообщений
          Exception/              # Исключения слоя Application (загрузка, сериализация сообщений)
          Message/                # DTO сообщений outbox
        Repository/               # Доступ к outbox_events
        Infrastructure/
          Bootloader/             # OutboxBootloader, OutboxConsoleBootloader
          Exception/              # Исключения слоя Infrastructure (registry, relay)
          Relay/                  # Relay, worker, loop control, sleeper
          Queue/                  # Publisher, serializer, headers, status interceptor
          Message/                # Event store, message loader, message serializer
          Registry/               # Реестр пары message -> Job
          Cycle/                  # Typecast outbox-полей
        Presentation/
          Console/                # outbox:relay
          Job/                    # Технические Job outbox

      System/
        Infrastructure/
          Bootloader/             # SystemBootloader: регистрация namespace `system` для view
        Presentation/
          Http/                   # Health, Swagger UI, OpenAPI YAML route
          Console/                # openapi:* команды
          Exception/              # Исключения слоя Presentation (публикация ассетов OpenAPI)
          Temporal/               # технические workflow
          views/                  # twig-шаблоны модуля (swagger/index), namespace `system`

    Shared/
      Domain/
        Collection/               # Базовая типизированная коллекция TypedCollection
        Enum/                     # Общие доменные enum (например Locale)
        Exception/                # Общие доменные исключения
        Locale/                   # Доменный сервис разбора локали (LocaleResolver)
        Pagination/               # Примитивы cursor-пагинации (CursorSlice)
        Trait/                    # Общие доменные трейты
        ValueObject/              # Общие базовые VO и общие идентификаторы
      Application/
        View/                     # Общие View нескольких модулей (MediaView)
      Presentation/
        Http/
          Resource/               # Общие базовые и общие конкретные API-ресурсы
      Infrastructure/
        Cache/
        Configuration/            # Типизированные config DTO и ConfigMapper
        Cycle/                    # Общие typecast-классы
        Exception/                # Общие инфраструктурные исключения (config mapping)
        Framework/                # Spiral Kernel, bootloaders, routes
```

## Правила зависимостей

Внешние слои могут зависеть от внутренних, внутренние не зависят от внешних.

```text
Presentation   -> Application своего модуля, Response/Resource, framework attributes
Application    -> Domain, Repository своего модуля, Contract своего модуля
Repository     -> Domain, Cycle ORM
Infrastructure -> Contract своего модуля, framework/runtime libraries, external libraries
Domain         -> PHP standard library, свой Domain, Shared/Domain
Shared         -> общий доменный и инфраструктурный код без привязки к одному модулю
```

Строгое правило: **Domain и Application не зависят от `Shared/Infrastructure/Configuration` и
не импортируют `*Config`.** Конфиг читается в Infrastructure (бутлоадеры, инфра-сервисы,
middleware), которая отдаёт в Application уже готовые значения через DI. Допустимы две формы
передачи самого значения:

- **Единичное готовое значение или VO через фабрику бутлоадера.** Бутлоадер модуля читает нужный
  `*Config` в фабрике `defineSingletons()` — контейнер подставляет config параметром фабрики, это
  законное чтение конфига в Infrastructure — собирает из него одно готовое значение и биндит его в
  Application-класс, куда оно авто-вайрится. Живой образец: `UserBootloader` отдаёт
  `UserPublicProfileAssembler` готовый `defaultAvatarUrl`.
- **Доменный сервис над значениями.** Значения конфига собираются в доменный сервис, который
  Application получает как зависимость. Живой образец: `LocaleResolver` из `LocaleConfig`.

Так слой сценариев не знает ни про источник значения, ни про `Shared/Infrastructure`.

Конфиг-зависимое **поведение** (а не одно значение) выносится в Infrastructure-сервис за
`Application/Contract`: реализация читает `*Config` сама через конструктор и отдаёт Application готовые
решения. Образцы — `MediaUrlServiceContract`/`MediaUrlService` (срок presigned-ссылки скачивания по
умолчанию) и `MediaUploadPlannerContract`/`MediaUploadPlanner` (срок staging-хранения, нужен ли
multipart, размер и число частей), реализации биндятся `const BINDINGS`. Общее правило —
«Infrastructure-сервис за контрактом», папка — просто `Infrastructure`; конкретное размещение
`MediaUploadPlanner` в `Infrastructure/FileService` рядом с `MediaUrlService` — частная деталь модуля
Media, а не предписание для всех будущих config-зависимых сервисов.

Промежуточный settings-объект, который лишь проецирует набор полей `*Config` в Application-объект,
запрещён как «конфиг от конфига»: вместо него — одна из двух форм передачи значения выше или
Infrastructure-сервис за контрактом для конфиг-зависимого поведения.

Если Application нужен именно технический сервис с поведением (S3, процессор, внешний клиент) — он
по-прежнему идёт через `Application/Contract` + реализацию в `Infrastructure`, без исключений.

Оговорка по охвату: правило адресовано Domain и Application. Некоторые Presentation-адаптеры
(`HealthController`, `SwaggerController`, `OpenApiGenerateCommand`) пока читают `*Config` напрямую —
это вне охвата текущей задачи и возможная отдельная чистка.

## Взаимодействие слоёв

### Поток HTTP-команды

```text
HTTP Request
  -> RoadRunner
    -> Spiral HTTP middleware
      -> Modules/{Module}/Presentation/Http/Controller
        -> Modules/{Module}/Presentation/Http/Filter
        -> Modules/{Module}/Application/Command DTO
        -> CommandBus::dispatch(command, handler)
          -> Handler::handle(Command)
            -> Modules/{Module}/Domain ValueObject
            -> Modules/{Module}/Domain Entity
            -> Modules/{Module}/Repository
            -> EntityManager::run()
        -> Modules/{Module}/Presentation/Http/Resource
        -> Response
      -> JSON Response
```

### Поток HTTP-запроса

```text
HTTP Request
  -> RoadRunner
    -> Spiral HTTP middleware
      -> Modules/{Module}/Presentation/Http/Controller
        -> Modules/{Module}/Presentation/Http/Filter
        -> Modules/{Module}/Application/Query DTO
        -> QueryBus::dispatch(query, handler)
          -> Handler::handle(Query)
            -> Modules/{Module}/Repository read method
            -> {Name}ViewAssembler -> {Name}View (обогащённые ответы)
        -> Modules/{Module}/Presentation/Http/Resource
        -> Response
      -> JSON Response
```

## API-документация

API-документация генерируется автоматически из типизированного HTTP-слоя:
контроллеров, Filter DTO, Response DTO, Resource-классов, enum-ов и API
attributes в `Modules/{Module}/Presentation/Http`. OpenAPI-спецификация строится из кода, а
Swagger используется как UI для её просмотра.

Генератор OpenAPI живёт в переносимом Composer-пакете `packages/spiral-openapi` с
namespace `GianTiaga\SpiralOpenApi`. Приложение не содержит логики статического разбора:
оно только собирает типизированный `OpenApiConfig`, задаёт mapping базовых
response wrappers и вызывает пакет через команду `openapi:generate`. YAML
записывается в `public/openapi/openapi.yml`, а Swagger UI по `/api/docs` читает
тот же файл через route `/api/docs/openapi.yml`.

Генератор сканирует все модули приложения: `openapi.sourcePath` = `app/src/Modules`,
`openapi.apiNamespace` = `App\Modules`. Он отбирает классы по префиксу namespace и строит
операции только из публичных методов с атрибутом `#[Route]`. Поэтому любой модуль с
`#[Route]`-контроллером автоматически попадает в публичную спецификацию. Если контроллер не
должен публиковаться (внутренний/служебный эндпоинт), его исключают через
`#[OpenApi(ignore: true)]`, а не через сужение области сканирования.

`packages/spiral-openapi` использует Spiral translator только для стандартных описаний
response по ключам `gian_tiaga.spiral_openapi.successful_response` и
`gian_tiaga.spiral_openapi.api_error`. Описания из `#[OpenApi(description: ...)]` и
PHPDoc остаются текстом приложения. YAML статический, поэтому язык выбирается во
время команды генерации.

## API-ошибки

Общая обработка API-ошибок живёт в переносимом Composer-пакете
`packages/spiral-api-errors` с namespace `GianTiaga\SpiralApiErrors`. Пакет зависит от
`packages/spiral-openapi`, потому что возвращает `ErrorResponse` и
`ValidationErrorResponse`.

Общие доменные исключения остаются в приложении в `App\Shared\Domain\Exception` и
расширяют `\DomainException`. Переводимые 4xx (`NotFoundException`, `ForbiddenException`,
`ValidationException`, `AuthenticationException`) расширяют его через общий абстрактный
`DomainTranslatableException`, который реализует
`GianTiaga\SpiralApiErrors\Exception\TranslatableException` и несёт ключ перевода с
параметрами вместо готовой строки. `InvalidDomainValueException` (500) переводимым не
является. Ожидаемые клиентские ошибки с кодами 4xx
`GianTiaga\SpiralApiErrors\Interceptor\ApiExceptionInterceptor` превращает в JSON
`{"message":"...","code":...}` без логирования; для `TranslatableException` сообщение
переводится на границе в локали текущего запроса (`translationKey()` в домене
`translationDomain()`), иначе берётся `getMessage()`. Каталоги переводов приложения
разбиты по модулям: `translationDomain()` выводит домен (= файл `app/locale/{lang}/{domain}.php`)
из второго сегмента ключа — `app.media.*` → `media.php`, `app.system.*` → `system.php`,
ключи без модульного сегмента — домен `messages`. Доменные исключения без
поддерживаемого 4xx-кода, включая `InvalidDomainValueException`, считаются
внутренними ошибками: HTTP-ответ получает обычное сообщение 500, а исходное
сообщение исключения пользователю не отдаётся. Ошибки Spiral Filter рендерятся через
`GianTiaga\SpiralApiErrors\Filter\ApiValidationErrorsRenderer` по ключу
`gian_tiaga.spiral_api_errors.validation_error` в JSON
`{"message":"Validation error","code":422,"errors":[...]}` или
`{"message":"Ошибка валидации","code":422,"errors":[...]}` в зависимости от
текущего locale.

`ApiExceptionInterceptor` работает только внутри цепочки controller/action.
Ненайденные маршруты возникают раньше controller/action, поэтому их обрабатывает
`GianTiaga\SpiralApiErrors\Middleware\RouteNotFoundMiddleware` в глобальной HTTP-цепочке.
Он переводит ключ `gian_tiaga.spiral_api_errors.route_not_found` и возвращает JSON
`{"message":"Route not found.","code":404}` или
`{"message":"Маршрут не найден.","code":404}` в зависимости от текущего locale.
Ошибки bootstrap и middleware, не связанные с router 404, остаются в зоне
стандартного Spiral error handler.

`packages/spiral-api-errors` и `packages/spiral-openapi` только читают текущий locale Spiral
translator. Выбор языка пользователя реализован в request-слое приложения:
`App\Shared\Infrastructure\Framework\Middleware\LocaleMiddleware` определяет локаль запроса
из заголовка `Accept-Language`, пересечённого с белым списком `LocaleConfig.supported`
(иначе `LocaleConfig.default`), и выставляет её в Spiral translator до контроллера и до
`RouteNotFoundMiddleware`. Для не-HTTP контекстов (queue/console/Temporal) per-request
локали нет — там перевод на границе не выполняется, исключение несёт ключ.

### Поток консольной команды

```text
Console command
  -> input arguments/options
  -> Modules/{Module}/Application/Command DTO
  -> CommandBus::dispatch(command, handler)
  -> Handler::handle(Command)
  -> console output
```

Console command не содержит бизнес-логику. Он только читает CLI-ввод,
создаёт Command/Query и выводит результат.

### Поток задачи очереди

```text
Queue payload
  -> Modules/{Module}/Presentation/Job handler
  -> Payload DTO
  -> Modules/{Module}/Application/Command DTO
  -> CommandBus::dispatch(command, handler)
  -> Handler::handle(Command)
```

Job handler - технический адаптер очереди. Бизнес-сценарий находится в
Application Handler.

### Поток Temporal

```text
Temporal Workflow / Activity
  -> Modules/{Module}/Presentation/Temporal adapter
  -> Modules/{Module}/Application Command или Query
  -> Bus
  -> Handler
```

Temporal workflow/activity не становится доменным слоем. Это внешний runtime
adapter, как HTTP controller или queue job.

## Правила CQRS

CQRS используется на уровне use-case-ов: Command изменяет состояние, Query читает
данные. Каждый use-case живёт в отдельной папке действия. Пока в модуле одна
область (Area), действие лежит прямо в корне:
`Modules/{Module}/Application/Command/{Action}` или
`Modules/{Module}/Application/Query/{Action}`. Уровень области добавляется только
когда областей становится несколько — тогда действия группируются по областям:
`Modules/{Module}/Application/Command/{Area}/{Action}` или
`Modules/{Module}/Application/Query/{Area}/{Action}`.

Command flow:

```text
Command DTO
  -> CommandBus::dispatch(command, handler)
  -> Handler::handle(Command)
  -> Domain types
  -> Entity
  -> Repository
  -> EntityManager::run()
```

Query flow:

```text
Query DTO
  -> QueryBus::dispatch(query, handler)
  -> Handler::handle(Query)
  -> Repository read method
  -> {Name}ViewAssembler -> {Name}View (обогащённые ответы)
  -> Entity / typed collection / Result DTO / View / PaginatedResult<T>
```

Command Handler создаёт VO, enum и доменные коллекции до передачи данных в
Entity. Примитивы не передаются в `Entity::create()` и доменные методы Entity.

Command Handler возвращает `void` или Result DTO с минимальным результатом
операции (идентификатор, токены); read-model (View) команды не возвращают.
Query Handler возвращает Entity, типизированную коллекцию, Result DTO, View
или `PaginatedResult<T>`. Result DTO — ответ сценария для кода
(межмодульный контракт), View — обогащённая форма для показа клиенту,
`PaginatedResult<T>` — страница элементов (Entity, Result DTO или View).
Query не изменяет состояние и не оборачивается в транзакцию. Если Query
Handler помечен `#[Transactional]`, это ошибка контракта.

Data Grid не используется как бизнесовый Query-слой. Фильтрация и сортировка в
Query Handler должны быть явными.

## Read-model для ответов API: View, Assembler, Resource

```text
Controller
  -> Query Handler (Application)
    -> {Name}ViewAssembler (Application/View) -> {Name}View
  -> Resource::fromView(View) (Presentation)
  -> Response
```

View — read-model DTO в `Modules/{Module}/Application/View/`. Не знает про
HTTP и OpenAPI. Поля View — только скаляры, enum, `DateTimeImmutable` и
другие View (своего модуля или из `Shared/Application/View/`), в том числе
их списки. Entity, VO и чужие Application-DTO (результаты сценариев других
модулей) во View не попадают — Assembler перекладывает их в View-типы.

Assembler (`{Name}ViewAssembler`, рядом со своим View) — единственное место
кросс-модульного обогащения при чтении: Entity и репозитории своего модуля
плюс Application других модулей, пакетно, без N+1.

Resource — форма JSON-ответа и источник OpenAPI: чистый маппинг `from*()` из
своего View, Entity или Result DTO. Даты не форматирует —
`DateTimeImmutable` сериализует `AbstractResource` (ATOM), в OpenAPI-схеме
это `string`/`date-time`.

Без обогащения View не нужен: Resource маппится из Entity или Result DTO
напрямую. Command read-model не возвращает — `void` или Result DTO с
минимальным результатом операции (идентификатор, токены), обогащённый ответ
клиент дочитывает отдельным Query.

Одно понятие API — один Resource; копии одной формы запрещены. Resource,
общий для нескольких модулей, лежит в `Shared/Presentation/Http/Resource`
(`MediaResource` `{id, position, original, conversions}` с вложенными
`MediaOriginalResource` и `MediaConversionResource`). Общей форме
соответствует и общий View: `Shared/Application/View/MediaView`
`{id, position, original, conversions}`, из которого `MediaResource`
маппится одним `fromView()`. Неприменимое в контексте поле — `null`;
фиктивные значения и придуманные сервером заглушки запрещены. `id` всегда
задан (объект существует только за реальной сущностью медиа); отсутствие медиа —
это `MediaView|null` у потребителя: аватар без медиа отдаётся как `null`, а
дефолтный аватар подставляет клиент (фронт), сервер заглушку не выдумывает.

## Архитектура шины

Инфраструктура шины живёт в локальном Composer-пакете `packages/spiral-cqrs` с namespace
`GianTiaga\SpiralCqrs`. Приложение подключает `GianTiaga\SpiralCqrs\CommandBusInterface` и
`GianTiaga\SpiralCqrs\QueryBusInterface` через Spiral DI-контейнер. Прикладные Command,
Query и Handler остаются в модулях приложения.

Bus принимает DTO и first-class callable на `Handler::handle(...)`. Return type
берётся из `Handler::handle()`, поэтому PHPStan и IDE видят точный тип
результата без ручного приведения.

```php
/**
 * @template TCommand of object
 * @template TResult
 * @param TCommand $command
 * @param callable(TCommand): TResult $handler
 * @return TResult
 */
public function dispatch(object $command, callable $handler);
```

```text
CommandBus: Command DTO -> Handler::handle(Command)
QueryBus:   Query DTO -> Handler::handle(Query)
```

`#[Transactional]` на `Handler::handle()` включает транзакцию для Command. Если
атрибута нет, Command выполняется без транзакции. `#[NonTransactional]` не
используется.

`#[LogOperation]` на `Handler::handle()` включает debug-лог старта и времени
выполнения операции. Если атрибута нет, operation-log не пишется.

CQRS-bus не проверяет конкретные классы атрибутов. Он собирает все атрибуты
`Handler::handle()`, которые наследуются от
`GianTiaga\SpiralCqrs\Attribute\HandlerMiddlewareAttribute`, берёт из них middleware-класс
и создаёт middleware через контейнер. Зависимости вроде `LoggerInterface` или
`DatabaseInterface` получает сам middleware через constructor injection, а не
executor.

Lifecycle hooks и after-commit callbacks не входят в CQRS-bus. Внешние
побочные эффекты идут через transactional outbox.

## События и outbox

События, которые приводят к внешним побочным эффектам, публикуются через
transactional outbox. К таким эффектам относятся сообщения в Centrifugo, email,
push-уведомления, webhooks и любые интеграции, которые нельзя выполнять до
финального commit-а бизнес-транзакции.

Outbox используется не только для внешних интеграций, но и для внутренних
отложенных шагов, которым нужна гарантированная доставка после commit-а. Пример —
асинхронная обработка медиа: `Media` Application Handler в одной транзакции
переводит медиа в `uploaded` и кладёт `MediaUploaded` в outbox, а relay запускает
тяжёлую обработку (перекладка оригинала, конверсии на Imagick) уже после commit-а.
Определяющий признак — требование «надёжно выполнить после commit», а не то,
внешний эффект или внутренний.

```text
HTTP / Console / Job / Temporal
  -> Modules/{Module}/Application Command Handler
    -> Domain Entity / Repository
    -> Modules/Outbox/Application/Contract/OutboxEventStoreContract::add(IntegrationEvent DTO)
    -> EntityManager::run()
  -> commit транзакции, если Handler помечен #[Transactional]

Modules/Outbox relay
  -> забирает pending outbox-события
  -> кладёт задачу в RabbitMQ
  -> RoadRunner jobs consumer запускает Job
  -> общий queue interceptor помечает событие handled / failed
```

Application Handler фиксирует интеграционное событие как факт завершённого
use-case-а. Например, после создания поста Handler сохраняет `Post` и добавляет
`PostCreated` в outbox в той же транзакции. Он не вызывает Centrifugo, email или
другие внешние сервисы напрямую.

`OutboxEventStoreContract::add()` только ставит outbox-событие на сохранение.
Handler вызывает его до финального `EntityManager::run()`, чтобы доменное
изменение и outbox-событие записались одним flush внутри одной транзакции. Если
outbox-событие добавлено после `EntityManager::run()`, Handler обязан явно
вызвать ещё один `EntityManager::run()` до выхода из транзакции.

Публичная граница outbox для других модулей находится в Application-слое:
интеграционное событие реализует `Application\Message\OutboxMessage`, а
`OutboxEventStoreContract::add()` возвращает Application DTO с идентификатором
сохранённого outbox-события. Domain-типы outbox остаются внутренней моделью
модуля.

Outbox-событие - сериализуемый DTO с camelCase-полями. Payload не передаётся как
ассоциативный массив. DTO должен содержать только данные, нужные подписчикам или
publisher-у: идентификаторы, тип события, время создания и минимальный набор
данных для публикации.

`Domain` не знает про outbox, queue, Centrifugo и transport-level события.
Доменная модель может выражать инварианты и состояние, но решение "это надо
опубликовать наружу" принимает Application use-case.

Spiral events можно использовать для локальных in-process расширений, которые не
требуют гарантированной доставки и не зависят от commit-а транзакции. Для
пользовательских уведомлений и интеграций они не являются основным механизмом.

Outbox relay является техническим входом: он забирает событие и кладёт Job в
RabbitMQ. Job выполняет внешнее действие после commit-а. Общий queue interceptor
меняет статусы outbox и отвечает за обработку дублей, retry-сценарии и
логирование инфраструктурных ошибок.

Relay сам управляет сохранением технических переходов outbox-события:
`publishing`, `queued`, publish-failure и ошибки queue Job фиксируются после
commit-а бизнес-сценария. Поэтому `outbox:relay` считается инфраструктурным
orchestrator-ом, а не обычным Application Handler-ом с бизнес-записью.

Текущий relay рассчитан на один постоянный процесс `outbox:relay --loop`.
Параллельные relay-процессы не запускаются, пока для выборки событий не будет
добавлен отдельный безопасный контракт `SKIP LOCKED`.

Реальные Job для email, push, Centrifugo и webhooks должны быть идемпотентными
по `outboxId`. Если внешний сервис поддерживает idempotency key, использовать
`outboxId`.

## Правила доменной модели

`Domain` модуля содержит Entity, ValueObject, Enum, доменные коллекции и
доменные сервисы. Общие исключения и трейты лежат в `Shared\Domain`.
Domain не зависит от HTTP, Application Handler, Repository, framework, request
и config.

Entity создаётся через `create()`, меняется через доменные методы и не содержит
доменные примитивы.

```text
single value -> ValueObject или enum
many values  -> typed domain collection
```

Свойства Entity, `create()` и доменные методы не принимают `string`, `int`,
`float`, `bool`, `array` или голый `Collection` для доменных данных. `bool`
разрешён только как return type чистого predicate-метода.

ValueObject валидирует вход и не зависит от ORM. Восстановление из БД и запись
в БД выполняются инфраструктурным typecast-слоем.
Repository скрывает Cycle API и возвращает доменные типы. Доменные исключения
всплывают до presentation/interceptor boundary.

## Typecast-слой Cycle ORM

Два уровня:

- `Shared/Infrastructure/Cycle/ValueObjectCast` — общий движок по соглашению для
  простых non-nullable VO на скаляр (cast: `fromString`/`fromInt`/`BackedEnum::from`;
  uncast: `value()`). Stateful (правила по роли Entity), поэтому не биндится синглтоном.
- `Modules/{Module}/Infrastructure/Cycle/*Typecast` — отдельные `ColumnValueTypecast`
  для случаев, которые конвенция не выражает: nullable↔null-object, дата/время,
  JSON и коллекции.

Entity подключает оба: `typecast: [Typecast::class, ValueObjectCast::class]` на
`#[Entity]` плюс `#[Column(typecast: SpecificTypecast::class)]` на сложных колонках.
Pass-through обёртки над `ValueObjectCast` не создаются.

## Примеры кода

Каноничные примеры кода вынесены в `docs/code-examples.md`.

## Границы и контроль качества

```text
API           -> Modules/{Module}/Presentation/Http -> Filter DTO, Resource, Response
Views         -> Modules/{Module}/Presentation/views -> namespace `{module}:<view>` (bootloader модуля)
Configuration -> app/config -> Shared/Infrastructure/Configuration
Persistence   -> Modules/{Module}/Repository -> Cycle ORM
Typecast      -> Shared/Infrastructure/Cycle + Modules/{Module}/Infrastructure/Cycle
Events        -> Modules/{Module}/Application Message DTO -> Modules/Outbox/Infrastructure -> publisher
Errors        -> Shared/Domain/Exception / router 404 -> packages/spiral-api-errors -> packages/spiral-openapi ErrorResponse
Logging       -> CQRS attributes / infrastructure adapters
Quality       -> PHPStan level max, 100% coverage, all HTTP routes integration-tested
```

Framework, config, persistence, logging и error rendering не попадают в `Domain`.

Что колокейтим в модуль, а что осознанно глобально: view-шаблоны (twig) — живой ассет
Presentation-слоя и лежат в `Modules/{Module}/Presentation/views`, регистрируясь под namespace
модуля его bootloader-ом (правило `rules.md` «View-шаблоны живут в модуле»). А config-файлы и
миграции остаются глобальными намеренно: config-DTO предписано единое размещение в
`Shared/Infrastructure/Configuration` (см. раздел про `TypedConfig` выше и `app/config`), а
миграции (`app/database/migrations`) образуют единую линейную историю схемы, которая часто
кросс-модульная и проигрывается мигратором по общему порядку — дробить её по модулям нельзя.
