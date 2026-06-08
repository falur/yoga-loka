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

`Media` должен давать только свои сценарии:

```text
CreateMedia
DeleteMedia
CheckMediaExists
CheckMediaIsImage
GetMediaUrl
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
        Presentation/
          Http/                   # Health, Swagger UI, OpenAPI YAML route
          Console/                # openapi:* команды
          Exception/              # Исключения слоя Presentation (публикация ассетов OpenAPI)
          Temporal/               # технические workflow

    Shared/
      Domain/
        Exception/                # Общие доменные исключения
        Trait/                    # Общие доменные трейты
        ValueObject/              # Общие базовые VO и общие идентификаторы
      Presentation/
        Http/
          Resource/               # Общие базовые API-ресурсы
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

Общие доменные исключения остаются в приложении в `App\Shared\Domain\Exception` и явно
расширяют `\DomainException`. Ожидаемые клиентские ошибки с кодами 4xx
`GianTiaga\SpiralApiErrors\Interceptor\ApiExceptionInterceptor` превращает в JSON
`{"message":"...","code":...}` без логирования. Доменные исключения без
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
translator. Выбор языка пользователя по HTTP-заголовкам, профилю или другому
признаку остаётся задачей request-слоя приложения.

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
данные. Каждый use-case живёт в отдельной папке действия внутри
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
  -> Entity / typed collection / Result DTO / PaginatedResult<T>
```

Command Handler создаёт VO, enum и доменные коллекции до передачи данных в
Entity. Примитивы не передаются в `Entity::create()` и доменные методы Entity.

Command Handler возвращает Entity, Result DTO или `void`. Query Handler
возвращает Entity, типизированную коллекцию, Result DTO или `PaginatedResult<T>`.
Query не изменяет состояние и не оборачивается в транзакцию. Если Query
Handler помечен `#[Transactional]`, это ошибка контракта.

Data Grid не используется как бизнесовый Query-слой. Фильтрация и сортировка в
Query Handler должны быть явными.

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
Configuration -> app/config -> Shared/Infrastructure/Configuration
Persistence   -> Modules/{Module}/Repository -> Cycle ORM
Typecast      -> Shared/Infrastructure/Cycle + Modules/{Module}/Infrastructure/Cycle
Events        -> Modules/{Module}/Application Message DTO -> Modules/Outbox/Infrastructure -> publisher
Errors        -> Shared/Domain/Exception / router 404 -> packages/spiral-api-errors -> packages/spiral-openapi ErrorResponse
Logging       -> CQRS attributes / infrastructure adapters
Quality       -> PHPStan level max, 100% coverage, all HTTP routes integration-tested
```

Framework, config, persistence, logging и error rendering не попадают в `Domain`.
