# Архитектура: модульный монолит на Spiral + CQRS + тактический DDD

## Обзор

YogaLoka - API-first backend для мобильного приложения на PHP 8.5, Spiral
Framework, RoadRunner и Cycle ORM.

Приложение работает как единый монолит: один runtime, один deploy и один код
приложения. Физические bounded contexts не выделены. Домены группируются внутри
текущих слоёв по предметной области и действию.

## Локальный Docker-runtime

Локальная разработка и проверки выполняются через Docker Compose из
`docker/docker-compose.dev.yml`. Приложение запускается в двух RoadRunner
runtime:

- `app-http`: HTTP на `0.0.0.0:8080` внутри контейнера и RoadRunner jobs memory consumer в том же
- `app-http`: RoadRunner слушает `0.0.0.0:8080` только внутри контейнера.
  Наружу compose публикует сервис как `127.0.0.1:60080 -> 8080`. RoadRunner
  jobs memory consumer работает в том же процессе.
- `temporal-worker`: отдельный Temporal worker на task queue `default`.

Memory-очередь RoadRunner не выносится в отдельный queue worker, потому что
задачи доступны только внутри runtime, который владеет memory pipeline. Redis в
локальном стенде используется для cache/session и RoadRunner KV, но не является
брокером очереди.

Локальная инфраструктура: PostgreSQL, Redis, MinIO, Mailpit, Temporal, Temporal
UI и Centrifugo. Dev storage по умолчанию использует MinIO bucket `yoga-loka`,
тесты используют отдельные `yoga_loka_test` и `yoga-loka-test`.

## Структура каталогов

```
app/
  config/                         # Spiral config-файлы; env() допустим только здесь
  database/
    migrations/                   # Cycle ORM миграции
  locale/                         # Переводы
  src/
    Endpoint/                     # Внешние входы в систему
      Api/
        V1/                       # Версия REST API /api/v1
          Controller/             # HTTP controllers
          Filter/                 # Spiral Filter DTO для HTTP request
            {Area}/
          Resource/               # API resources: Entity/DTO -> JSON representation
          Response/               # Типизированные API responses
          Middleware/             # HTTP middleware версии API
          Interceptor/            # API interceptors версии API
          Attribute/              # PHP attributes для API metadata
      Console/                    # Console commands
      Job/                        # Queue job handlers
        Payload/                  # Payload DTO для jobs
      Temporal/                   # Temporal workflows и activities

    Application/                  # Use-case слой приложения
      Command/                    # CQRS Commands - операции записи
        {Area}/                   # Auth, User, Post, Follow и т.д.
          {Action}/               # Login, RegisterUser, UpdateUserProfile
            {Action}Command.php
            {Action}Handler.php
            {Action}Result.php
      Query/                      # CQRS Queries - операции чтения
        {Area}/
          {Action}/
            {Action}Query.php
            {Action}Handler.php
      Event/                      # DTO интеграционных событий для outbox

    Domain/                       # Доменная модель
      Entity/                     # Cycle ORM entities с доменным поведением
      ValueObject/                # Email, PasswordHash, Username, Slug
      Enum/                       # Статусы, роли, типы
      Exception/                  # ValidationException, NotFoundException и т.д.
      Service/                    # Доменная логика вне одной Entity
      Trait/                      # Общие entity traits

    Repository/                   # Cycle ORM repositories с доменными методами

    Infrastructure/               # Технические адаптеры и интеграции
      Bus/                        # CommandBus/QueryBus и middleware pipeline
        Middleware/
      Configuration/              # Типизированные config DTO и ConfigMapper
      Cycle/                      # Typecast, Select helpers, ORM utilities
      Framework/                  # Spiral Kernel, bootloaders, routes
        Bootloader/
      Http/                       # HTTP clients для внешних сервисов
      Logging/                    # Логирование и handlers
      Outbox/                     # Хранилище, сериализация и публикация outbox-событий
      Queue/                      # Queue infrastructure
      Storage/                    # Storage adapters
```

## Правила зависимостей

Внешние слои могут зависеть от внутренних, внутренние не зависят от внешних.

```text
Endpoint       -> Application, Infrastructure/Bus, Resource, Response
Application    -> Domain, Repository, infrastructure ports
Repository     -> Domain, Infrastructure/Cycle, Cycle ORM
Infrastructure -> framework/runtime libraries, external libraries, adapter DTO
Domain         -> PHP standard library, Domain classes
```

## Взаимодействие слоёв

### Поток HTTP-команды

```text
HTTP Request
  -> RoadRunner
    -> Spiral HTTP middleware
      -> Endpoint\Api\V1\Controller
        -> Endpoint\Api\V1\Filter
        -> Application\Command DTO
        -> CommandBus::dispatch(callable)
          -> LoggingMiddleware
          -> TransactionalMiddleware
          -> Handler::handle(Command)
            -> Domain ValueObject
            -> Domain Entity
            -> Repository
            -> EntityManager::run()
        -> Endpoint\Api\V1\Resource
        -> Endpoint\Api\V1\Response
      -> JSON Response
```

### Поток HTTP-запроса

```text
HTTP Request
  -> RoadRunner
    -> Spiral HTTP middleware
      -> Endpoint\Api\V1\Controller
        -> Endpoint\Api\V1\Filter
        -> Application\Query DTO
        -> QueryBus::dispatch(callable)
          -> LoggingMiddleware
          -> Handler::handle(Query)
            -> Repository read method
        -> Endpoint\Api\V1\Resource
        -> Endpoint\Api\V1\Response
      -> JSON Response
```

## API-документация

API-документация генерируется автоматически из типизированного HTTP-слоя:
контроллеров, Filter DTO, Response DTO, Resource-классов, enum-ов и API
attributes в `Endpoint\Api\V1`. OpenAPI-спецификация строится из кода, а
Swagger используется как UI для её просмотра.

Генератор OpenAPI живёт в переносимом Composer-пакете `tools/openapi` с
namespace `Tools\OpenApi`. Приложение не содержит логики статического разбора:
оно только собирает типизированный `OpenApiConfig`, задаёт mapping базовых
response wrappers и вызывает пакет через команду `openapi:generate`. YAML
записывается в `public/openapi/openapi.yml`, а Swagger UI по `/api/docs` читает
тот же файл через route `/api/docs/openapi.yml`.

### Поток консольной команды

```text
Console command
  -> input arguments/options
  -> Application\Command DTO
  -> CommandBus::dispatch(callable)
  -> Handler
  -> console output
```

Console command не содержит бизнес-логику. Он только читает CLI-ввод,
создаёт Command/Query и выводит результат.

### Поток задачи очереди

```text
Queue payload
  -> Endpoint\Job handler
  -> Payload DTO
  -> Application\Command DTO
  -> CommandBus::dispatch(callable)
  -> Handler
```

Job handler - технический адаптер очереди. Бизнес-сценарий находится в
Application Handler.

### Поток Temporal

```text
Temporal Workflow / Activity
  -> Endpoint\Temporal adapter
  -> Application\Command или Query
  -> Bus
  -> Handler
```

Temporal workflow/activity не становится доменным слоем. Это внешний runtime
adapter, как HTTP controller или queue job.

## Правила CQRS

CQRS используется на уровне use-case-ов: Command изменяет состояние, Query читает
данные. Каждый use-case живёт в отдельной папке действия внутри
`Application/Command/{Area}/{Action}` или `Application/Query/{Area}/{Action}`.

Command flow:

```text
Command DTO
  -> CommandBus::dispatch(callable)
  -> Handler::handle(Command)
  -> Domain types
  -> Entity
  -> Repository
  -> EntityManager::run()
```

Query flow:

```text
Query DTO
  -> QueryBus::dispatch(callable)
  -> Handler::handle(Query)
  -> Repository read method
  -> Entity / typed collection / Result DTO / PaginatedResult<T>
```

Command Handler создаёт VO, enum и доменные коллекции до передачи данных в
Entity. Примитивы не передаются в `Entity::create()` и доменные методы Entity.

Command Handler возвращает Entity, Result DTO или `void`. Query Handler
возвращает Entity, типизированную коллекцию, Result DTO или `PaginatedResult<T>`.
Query не изменяет состояние и не оборачивается в транзакцию по умолчанию.

Data Grid не используется как бизнесовый Query-слой. Фильтрация и сортировка в
Query Handler должны быть явными.

## Архитектура шины

Bus принимает `callable`, а не Command/Query объект. Return type берётся из
`Handler::handle()`, поэтому PHPStan и IDE видят точный тип результата без
ручного приведения.

```php
/**
 * @template TResult
 * @param callable(): TResult $operation
 * @return TResult
 */
public function dispatch(callable $operation);
```

```text
CommandBus: LoggingMiddleware -> TransactionalMiddleware -> callable
QueryBus:   LoggingMiddleware -> callable
```

`TransactionalMiddleware` используется только для Command. `EntityManager::run()`
остаётся в Handler-е, чтобы сценарий явно управлял моментом flush.

`LoggingMiddleware` логирует имя операции и время выполнения. Имя операции
извлекается из callable через reflection по захваченным переменным.

## События и outbox

События, которые приводят к внешним побочным эффектам, публикуются через
transactional outbox. К таким эффектам относятся сообщения в Centrifugo, email,
push-уведомления, webhooks и любые интеграции, которые нельзя выполнять до
финального commit-а бизнес-транзакции.

```text
HTTP / Console / Job / Temporal
  -> Application Command Handler
    -> Domain Entity / Repository
    -> EntityManager::run()
    -> OutboxEventStore::add(IntegrationEvent DTO)
  -> commit транзакции CommandBus

Outbox worker
  -> забирает pending outbox-события
  -> выбирает publisher по типу события
  -> Infrastructure adapter, например CentrifugoService
  -> помечает событие delivered / failed
```

Application Handler фиксирует интеграционное событие как факт завершённого
use-case-а. Например, после создания поста Handler сохраняет `Post` и добавляет
`PostCreated` в outbox в той же транзакции. Он не вызывает Centrifugo, email или
другие внешние сервисы напрямую.

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

Outbox worker является техническим входом, как обычный queue job: он забирает
событие, делегирует публикацию в application/infrastructure слой и отвечает за
повторы, статусы и логирование инфраструктурных ошибок.

## Правила доменной модели

`Domain` содержит Entity, ValueObject, Enum, доменные исключения и доменные
сервисы. Он не зависит от HTTP, Application Handler, Repository, framework,
request и config.

Entity создаётся через `create()`, меняется через доменные методы и не содержит
доменные примитивы.

```text
single value -> ValueObject или enum
many values  -> typed domain collection
```

Свойства Entity, `create()` и доменные методы не принимают `string`, `int`,
`float`, `bool`, `array` или голый `Collection` для доменных данных. `bool`
разрешён только как return type чистого predicate-метода.

ValueObject валидирует вход и поддерживает ORM hydration через typecast.
Repository скрывает Cycle API и возвращает доменные типы. Доменные исключения
всплывают до endpoint/interceptor boundary.

## Примеры кода

Каноничные примеры кода вынесены в `docs/code-examples.md`.

## Границы и контроль качества

```text
API           -> Filter DTO, Resource, Response
Configuration -> app/config -> typed config DTO
Persistence   -> Repository -> Infrastructure/Cycle -> Cycle ORM
Events        -> Application Event DTO -> Infrastructure/Outbox -> publisher
Errors        -> Domain exception -> endpoint/interceptor -> ErrorResponse
Logging       -> Bus middleware / infrastructure adapters
Quality       -> PHPStan level max, 100% coverage, all HTTP routes integration-tested
```

Framework, config, persistence, logging и error rendering не попадают в `Domain`.
