Reading additional input from stdin...
OpenAI Codex v0.139.0
--------
workdir: /Users/gian_tiaga/Code/yoga-loka-spiral-2
model: gpt-5.5
provider: openai
approval: never
sandbox: read-only
reasoning effort: high
reasoning summaries: none
session id: 019ecae5-a94a-78c3-9e3f-680aef590ae4
--------
user
Прочитай docs/plans/2026-06-15_13-25_auth-module.md, docs/rules.md, docs/arch.md и связанное исследование docs/researches/2026-06-13_22-36_auth-module.md. Затем глубоко проверь план по релевантному коду: открой файлы, модули, API и тесты, которые следуют из плана (модуль User в app/src/Modules/User, Outbox-контракты и эталон Media, Shared-исключения и ValueObjectCast, каркас Kernel/AppBootloader/RoutesBootloader/LocaleMiddleware, vendor spiral/auth в vendor/spiral/framework/src/Auth и AuthHttp, Cycle forUpdate, EncryptionInterface, примеры тестов в tests/), и оцени фактическую реализуемость. Это план модуля Auth (беспарольный вход по email-коду) для PHP 8.5 / Spiral 3.16 / Cycle ORM / RoadRunner. Дай критическое ревью на русском: пропущенные шаги, неучтённые зависимости, нарушения правил и архитектуры, скрытые риски, риски для смежного кода, недостающие тесты, шаги без критериев готовности, фактическую (не)реализуемость конкретных решений (особо: разделение VerifyLoginCode на оркестратор+ResolveLoginCode ради коммита attempts; RateLimitMiddleware per-route строит 429 сам; CycleTokenStorage реализует и Spiral TokenStorageInterface и свой AuthTokenStorageContract, load без forUpdate, getID()=raw; per-route auth middleware на logout вместо группы api; AuthBootloader alias+defineDependencies; forUpdate в цепочке select; вложенная транзакция CompleteRegistration->CreateUser). Ответ — список в формате '- [тип] описание — что предлагаешь'.
codex
Сначала прочитаю обязательные документы и сам план, затем подниму связанные участки кода и vendor. Правок делать не буду: задача на критическое ревью реализуемости.
exec
/bin/zsh -lc "sed -n '1,260p' docs/rules.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "sed -n '1,280p' docs/arch.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
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

Осознанное исключение: типизированный `TypedConfig` из
`Shared/Infrastructure/Configuration` может инжектиться напрямую в Application-Handler,
когда сценарию нужны инфра-дефолты (staging-TTL, пороги, размеры, драйвер). Пример —
`MediaConfig` в `RequestMediaUploadHandler` модуля `Media`.
Формально `Shared/Infrastructure` не входит в список зависимостей Application выше, но
`TypedConfig` — это не технический сервис с поведением и не зависимость от чужого модуля:
правила (`rules.md` «Typed config для каждого config-файла») и эта же `arch.md`
(«Configuration → app/config → Shared/Infrastructure/Configuration») предписывают единое
размещение всех config-DTO в `Shared/Infrastructure/Configuration`. Оборачивать такой
config-DTO в Application-`*Contract` и привязывать его в бутлоадере означало бы создать
pass-through-обёртку над `TypedConfig` ради формального списка — это запрещённый паттерн
(ср. «Без pass-through typecast-обёрток») и сделало бы модуль единственным, кто прячет
собственный typed-config за контрактом. Поэтому прямая инъекция `TypedConfig` в Application
допускается явно. Если Application нужен именно технический сервис с поведением (S3,
процессор, внешний клиент) — он по-прежнему идёт через `Application/Contract` + реализацию
в `Infrastructure`, без исключений.


 succeeded in 0ms:
# Правила проекта

## Язык

- Отвечай всегда на русском
- Исключения пишутся на русском языке.
- Логи пишутся на русском языке.
- Комментарии к коду пишутся на русском языке.
- Ошибки, которые отдаются пользователю, пишутся на языке пользователя.
- **Коммиты на русском**: сообщения коммитов (subject, body) пишутся на русском языке. Тип и scope остаются на английском по Conventional Commits (`feat`, `fix`, `refactor` и т.д.), но описание — на русском. Пример: `feat(tenant): добавить управление тенантами`.

## Качество кода

- **Ранний возврат**: guard clauses (`throw`/`return`) в начале метода. Инвертировать условие, выбросить исключение первым, happy path без вложенности. Вложенность 2+ уровней `if/else` — красный флаг.
- **Короткие методы**: `handle()` в Handler — не более ~40 строк. Длиннее — выносить в приватные методы.
- **`match` вместо `switch`**: `switch` не используется. Всегда `match`-выражение. Enum — исчерпывающий `match` без `default`.
- **Именованные аргументы**: обязательны при 2+ обычных параметрах и при любом необязательном/булевом параметре. Позиционные — для 1–2 очевидных параметров. Variadic-вызовы и вызовы с unpack (`...$args`) допускают позиционные аргументы, потому что именование ломает читаемость таких API (`sprintf`, `implode` и т.д.). (Проверяется PHPStan)
- **`sprintf()` для строк**: для пользовательских сообщений и строк ошибок. Интерполяция `"{$a}-{$b}"` — только для компактных ключей/идентификаторов. Конкатенация `.` — избегать.
- **Collection-пайплайны**: `->map()`, `->filter()`, `->groupBy()` для чистых трансформаций. `foreach` — только при побочных эффектах.
- **Типизированные Laravel Collections вместо массивов и Doctrine Collections**: в Entity и Repository не использовать массивы для наборов сущностей или value object. Для каждой доменной коллекции создавать именованный класс на базе `Illuminate\Support\Collection` с generic-типом элемента: например, связь `User -> Role` хранится и возвращается как `RoleCollection`, а результат репозитория со списком пользователей — как `UserCollection`. `Doctrine\Common\Collections\ArrayCollection` запрещена. Все связи Cycle ORM и методы репозиториев, возвращающие несколько элементов, должны возвращать конкретную типизированную коллекцию, а не `array` и не голый `Illuminate\Support\Collection`.
- **Явные типы вместо `null`**: `null` в доменной модели избегается. Отсутствие, неизвестность или особое состояние выражать отдельным типом/value object/enum, например `KnownIp` и `UnknownIp` как реализации абстракции `Ip`, а не `?string`/`?Ip`.
- **Строгая типизация**: все PHP-файлы, анализируемые PHPStan, должны начинаться с `declare(strict_types=1)`.
- **Строгие сравнения**: значения сравниваются только через `===` и `!==`; loose comparisons `==`, `!=` и `<>` запрещены.
- **Trailing commas**: обязательны в каждом многострочном списке (аргументы, массивы, `match`, параметры).
- **Нет мёртвого кода**: неиспользуемые переменные, закомментированные блоки, недостижимые ветки — удалять.
- **Инлайн одноразовых переменных**: если переменная используется один раз и получается просто (обращение к свойству, вызов метода, enum-значение), не заводить для неё отдельную переменную — подставлять выражение напрямую. `$slug = $role->slug->value; ... 'slug' => $slug` → `'slug' => $role->slug->value`.
- **Не дублировать конструктор фабриками без смысла**: статическая фабрика (`create()`, `fromParts()`, `fromItems()` и т.д.) добавляется только если она выражает отдельный доменный сценарий, преобразует внешний формат, делает дополнительную валидацию/нормализацию или скрывает сложное создание. Если метод принимает те же данные, что и конструктор, и просто вызывает `new self(...)` без нового смысла — он запрещён; используй конструктор напрямую.
- **Исключения, а не коды ошибок**: при ошибке — типизированное исключение, не `null`/`false`/код.
- **Не плодить технические константы**: одноразовые технические строки, шаблоны и сообщения оставлять рядом с использованием. Константу, enum или value object использовать, когда значение переиспользуется, является публичным контрактом или закрытым набором вариантов.
- **Enum вместо строк**: если значение имеет ограниченный набор вариантов (статус, тип, роль, категория) — использовать `enum`. Строковые литералы `'active'`, `'super_user'`, `'pending'` в бизнес-логике — красный флаг, должен быть enum.
- **UUID v7 для всех идентификаторов**: все первичные ключи и идентификаторы сущностей — UUID версии 7 (`Ramsey\Uuid\Uuid::uuid7()`). UUID v4 и автоинкремент запрещены. UUID v7 обеспечивает хронологическую сортируемость и лучшую производительность индексов в PostgreSQL.
- **Cursor-пагинация по UUID v7 `id`**: для cursor-based пагинации используется `id` (UUID v7) вместо `createdAt`. UUID v7 содержит timestamp и хронологически сортируем, поэтому `ORDER BY id DESC` эквивалентен `ORDER BY createdAt DESC`, но использует PK-индекс напрямую — без дополнительного индекса и без проблем с дубликатами timestamp.
- **Конкретные имена переменных**: запрещены абстрактные имена вроде `$handler`, `$service`, `$manager`, `$data`, `$result`, `$item`. Имя должно отражать суть: `$loginSuperUser` вместо `$handler`, `$tenantRepository` вместо `$repository`, `$activeUsers` вместо `$result`. Это касается и параметров контроллеров: `$updateUserProfileHandler` вместо `$updateHandler`, `$getUserProfileHandler` вместо `$profileHandler`, `$loginFilter` вместо `$filter`. Исключение — лямбды с очевидным контекстом (`fn(Role $role) => $role->slug`).
- **Property hooks вместо геттеров/сеттеров**: в Entity использовать PHP 8.4 property hooks (`get`/`set`) вместо методов `getX()`/`setX()`. Вычисляемые значения — через `get` hook, валидация при записи — через `set` hook.
- **Entity: фабричный метод `create()` вместо конструктора**: Entity не объявляет конструктор, если он пустой. Создание новой сущности — через статический метод `create(...)`, который принимает обязательные бизнес-параметры, инициализирует все поля и возвращает готовый объект. Это чётко разделяет «создание нового» от «восстановления из БД».
- **Entity без примитивов**: Entity не хранит и не принимает `string`, `int`, `float`, `bool`, `array` или голый `Collection` для доменных данных. Свойства Entity, аргументы `create()`, аргументы доменных методов и значения, которые Entity возвращает наружу, должны быть VO, enum, другой доменной моделью или именованной типизированной коллекцией. Исключение: `bool` разрешён только как return type у чистых predicate-методов без побочных эффектов (`isActive()`, `canLogin()`), если метод отвечает на вопрос и не протаскивает состояние наружу.
- **ValueObject для доменных примитивов**: email, пароль, slug, subdomain, имена, идентификаторы, счётчики, флаги состояния и другие одиночные значения внутри Entity — не голые примитивы, а `readonly class` в `Modules/{Module}/Domain/ValueObject` или enum для закрытого набора вариантов. Общие базовые VO и общие идентификаторы, которые не принадлежат одному модулю, лежат в `Shared/Domain/ValueObject`. Простой скалярный VO: `readonly`, `Stringable`, `JsonSerializable`, приватный конструктор, фабричный метод с валидацией, метод для чтения значения (`value()`, `toString()` или другой явный доменный метод) и `equals()`. Составной VO для JSON-значения может не быть `Stringable`, если у него нет естественного безопасного строкового представления; при этом он всё равно должен быть `readonly`, `JsonSerializable`, создаваться через фабрику, валидировать входные значения и иметь `equals()`. Вспомогательные payload/DTO для JSON не кладём в `Domain/ValueObject`, если они сами не являются полноценными VO. VO не реализует инфраструктурные интерфейсы и не зависит от Cycle ORM. Гидрация и запись VO в БД настраиваются в инфраструктурном typecast-слое: для простых VO общий `ValueObjectCast` может использовать публичные методы VO по соглашению, для сложных VO создаётся отдельный typecast-класс в `Infrastructure`. Entity хранит VO-свойства нативно (`public private(set) Email $email`) с `#[Column(typecast: Email::class)]` или с отдельным typecast-классом, без промежуточных raw-полей. CQRS Command принимает примитивы на внешней границе, Handler создаёт VO до вызова `Entity::create()` или доменных методов Entity.
- **Не заменять типизацию ручными проверками**: если ожидаемый тип известен, указывай его в сигнатуре метода. Не принимай `object`, `mixed` или слишком широкий тип с последующей проверкой `instanceof`. Исключение — границы системы и места, где входные данные действительно имеют неизвестный тип.
- **Валидация значений — только в ValueObject**: Entity не содержит методов валидации (`validateX()`, `mb_strlen()`, `trim()` и т.д.) для доменных значений. Вся валидация (длина, формат, пустота, диапазон, нормализация) инкапсулирована в соответствующем VO. Entity принимает готовый VO в `create()` и доменных методах, без голых примитивов и локальной валидации скаляров.
- **Исключения из ValueObject — это внутренние доменные ошибки (500)**: VO и доменные коллекции бросают `InvalidDomainValueException` (код 500), не `ValidationException` и не `\InvalidArgumentException`. `ValidationException` используется только на HTTP/API-границе, когда нужно вернуть пользователю ожидаемую ошибку 422. `GianTiaga\SpiralApiErrors\Interceptor\ApiExceptionInterceptor` возвращает для `InvalidDomainValueException` обычную 500-ошибку без исходного сообщения исключения в ответе.
- **Типизированные доменные исключения вместо `RuntimeException`**: запрещено использовать голый `\RuntimeException` с HTTP-кодом. Для ожидаемых HTTP-сценариев — свой класс: `NotFoundException` (404), `AuthenticationException` (401), `ForbiddenException` (403), `ValidationException` (422). Такие HTTP-исключения не логируются, а только превращаются в ответ пользователю. Для невалидного доменного значения — `InvalidDomainValueException` (500). Код зашит в конструкторе, передаётся только сообщение.
- **Исключения слоя — в папке `Exception/` этого слоя**: классы-исключения не лежат рядом с логикой, которая их бросает, а собираются в папке `Exception/` своего слоя — `App\Modules\{Module}\{Layer}\Exception` (`Application/Exception`, `Infrastructure/Exception`, `Presentation/Exception`) и `App\Shared\{Layer}\Exception`. Слой исключения определяется контрактом, к которому оно относится, а не местом выброса: `OutboxMessageLoadingException` — часть контракта загрузчика сообщений, поэтому лежит в `Application/Exception`, хотя бросается в `Infrastructure`. Это распространяет на все слои и модули паттерн `Shared/Domain/Exception`.
- **Запрет `assert()`**: `\assert()` запрещён во всём коде проекта. Вместо assert — явная проверка условия с выбросом типизированного исключения. Причина: assert может быть отключён в production через `zend.assertions=-1`, что молча пропускает невалидные данные. В ValueObject публичные фабрики валидируют пользовательский ввод, а восстановление значений из БД выполняет инфраструктурный typecast-слой.

## Архитектура

- **Запрет try-catch в контроллерах, Handler-ах и бизнес-логике**: контроллеры и Handler-ы не ловят исключения — ошибки всплывают до `GianTiaga\SpiralApiErrors\Interceptor\ApiExceptionInterceptor`, который конвертирует их в `ErrorResponse` с корректным HTTP-кодом. `try-catch` допустим только в инфраструктурном коде (interceptor-ы, middleware, Job-обработчики) — там, где исключение ловится на границе системы. В Handler-ах вместо try-catch на `ConstrainException` использовать проверку перед записью (check-then-act) — дублирование при конкурентных запросах допустимо как 500, которую `ApiExceptionInterceptor` обработает. Ожидаемые клиентские ошибки бросать типизированными доменными исключениями (`AuthenticationException`, `ForbiddenException` и т.д.) с 4xx-кодом в `$code`; внутренние нарушения доменных значений — через `InvalidDomainValueException`. Даже в инфраструктурном коде запрещён try-catch, который ловит широкий `\Throwable` и лишь перебрасывает его как другой тип исключения, не добавляя реальной обработки (логирование, fallback, retry, изменение состояния, освобождение ресурса) — пусть исходное исключение всплывает к обработчику на границе. Оборачивать чужое исключение в доменный тип оправдано только тогда, когда это добавляет существенный контекст, нужный потребителю, или требуется контрактом границы; перетипизация без пользы — лишний код. Если же ошибку код обнаруживает сам (guard-clause, проверка предусловия), он сразу бросает конкретное типизированное доменное исключение в источнике, а не generic-заглушку вроде `\UnexpectedValueException`/`\RuntimeException`.
- **Консольные команды — тонкие обёртки**: консольная команда только собирает ввод (аргументы, опции) и делегирует в соответствующий CQRS Command+Handler. Вся бизнес-логика — в Handler, консольная команда не содержит логики кроме маппинга ввода в Command DTO и вывода результата. Формат атрибута `#[AsCommand]` см. в примере консольной команды в `docs/code-examples.md`.
- **`env()` только в конфигах**: вызов `env()` допустим исключительно в файлах `app/config/*.php`. Сервисы, Handler-ы и любой другой код получают значения через типизированные конфиг-объекты (Spiral Config) или через DI. Прямое обращение к `env()` в бизнес-коде — нарушение.
- **Typed config для каждого config-файла**: при добавлении нового `app/config/*.php` сразу создаётся корневой DTO в `App\Shared\Infrastructure\Configuration\<Section>\<Section>Config`, который реализует `TypedConfig`. `configName()` должен совпадать с именем файла без `.php`. Вложенные секции описываются отдельными DTO рядом с корневым классом, а маппинг покрывается тестом через `ConfigMapper`.
- EnvironmentInterface - запрещён нужно использовать конфиги
- **Реквесты через Filter**: входящие данные принимаются через Spiral Filter-классы (`Spiral\Filter\Dto\FilterInterface`). Ассоциативные массивы `array $input` в контроллерах — запрещены. Enum-ы (`TenantStatus`, `TenantPlan` и т.д.) типизируются прямо в Filter-е — Spiral автоматически кастит строку в BackedEnum. Контроллер не делает `::from()`/`::tryFrom()` вручную.
- **Запрет `ServerRequestInterface` в контроллерах**: контроллеры не инжектят `Psr\Http\Message\ServerRequestInterface`. Для тела запроса — Spiral Filter (`#[Post]`, `#[Query]`). Для аутентифицированного пользователя — `#[Attribute(key: 'authUserId')]`, читающий request attribute из JWT middleware. Ключ `authUserId` (не `userId`) во избежание коллизий с параметрами роута. `ServerRequestInterface` допустим только в middleware и interceptor-ах.
- **Filter-ы полностью объектно-ориентированные**: свойства Filter-а — только скалярные типы, Enum-ы и вложенные Filter-ы/DTO. Ассоциативные массивы (`array<string, mixed>`) в Filter-е — запрещены. Для вложенных JSON-объектов создавать отдельный вложенный Filter или DTO (`#[NestedFilter]` / отдельный `readonly class`). Spiral поддерживает вложенность фильтров — использовать её вместо сырых массивов. Плоские типизированные списки (`list<string>`, `list<int>`, `list<UuidString>`) — допустимы, в том числе nullable (`?array` с PHPDoc `@var list<string>|null`).
- **Filter: не указывать `key` если совпадает с именем свойства**: `#[Post]` без `key` — Spiral автоматически берёт имя PHP-свойства. `key` указывать только когда имя в JSON отличается от имени свойства. Поскольку API использует camelCase и PHP-свойства тоже camelCase — `key` практически никогда не нужен.
- **Filter: обязательные свойства без значения по умолчанию**: если свойство Filter-а обязательное — оно объявляется без значения по умолчанию и без nullable. `public string $email;` — не `public string $email = '';`. Spiral заполняет свойства из запроса, валидация через `#[Assert\NotBlank]` гарантирует наличие. Дефолт `= ''` маскирует отсутствие значения. Nullable (`?string $name = null`) и дефолты (`int $limit = 20`) — только для реально опциональных полей. Для файлов: `public UploadedFileInterface $file;` — не nullable.
- **Респонс — типизированный класс**: каждый ответ API — объект с типизированными полями, не ассоциативный массив. Запрещено возвращать `['key' => $value]` напрямую.
- **Запрет ассоциативных массивов**: для передачи данных используем ООП-подход — DTO, Filter, Response-классы. `array<string, mixed>` в публичных контрактах (параметры, return type) — нарушение.
- **Базовые Response-классы**: для API-ответов использовать `DataResponse<T>` (одиночный объект), `PaginationResponse<T>` (постраничный список), `CollectionResponse<T>` (коллекция без пагинации). Response-классы параметризованы дженерик-типом `T` возвращаемого ресурса.
- **Ресурсы наследуют `AbstractResource`**: все API-ресурсы — `final readonly class` extends `App\Shared\Presentation\Http\Resource\AbstractResource`. Определяют только свойства и `fromEntity()`. Сериализация автоматическая через рефлексию, `jsonSerialize()` вручную не определяется.
- **camelCase везде кроме БД**: все ключи в любых сериализуемых данных — camelCase. Это касается: HTTP request/response JSON, payload очередей (queue jobs), WebSocket-сообщений (Centrifugo), event payload, логгер-контекста, PSR-7 request attributes. Единственное исключение — имена таблиц и колонок в БД (PostgreSQL convention: snake_case), Cycle ORM аннотации для колонок, переменные окружения и ключи конфигов фреймворка. Если ключ попадает в JSON, очередь, лог или передаётся между слоями — он camelCase.
- **Контроллеры возвращают Response напрямую**: методы контроллеров возвращают базовые Response-классы (`DataResponse`, `CollectionResponse`, `PaginationResponse`, `ErrorResponse`) напрямую, без промежуточных обёрток. Return type метода — конкретный Response-класс.
- **PHPDoc `@return` с дженериком на каждом методе контроллера**: если метод возвращает базовый Response-класс, обязателен `@return DataResponse<SuperUserResource>` (или аналогичный) в PHPDoc. Это даёт PHPStan полную информацию о типе ресурса внутри ответа.
- **OpenAPI metadata не обязательна**: `#[OpenApi(id: ..., description: ...)]` используется только для ручного уточнения `operationId` и описания. Если атрибута нет, генератор строит операцию из route name, controller method, PHPDoc, Filter DTO, Response/Resource и enum-ов. Перед релизом запускать `php app.php openapi:generate` и проверять обновление `public/openapi/openapi.yml`.
- **Контроллеры — тонкие обёртки**: контроллер не содержит бизнес-логики и логики выборки. Контроллер только: принимает Filter, вызывает Command Handler (для записи) или Query Handler (для чтения), маппит результат в Resource и оборачивает в Response-класс. Никаких `WHERE`-условий, `if-else`, подсчётов, ручной пагинации — вся логика выборки в Query Handler.
- **CQRS: Command + Query**: запись через Command, чтение через Query; каждая операция — DTO + Handler. Выборку пишем явно в репозиториях, без Data Grid.
- **Репозитории — отдельный слой модуля**: Repository лежит в `Modules/{Module}/Repository`. `Application` своего модуля может использовать свои Repository напрямую через constructor injection. Репозиторий другого модуля использовать нельзя: для связи между модулями обращаться только к `Application` другого модуля.
- **В папке `Repository` — только репозитории, работающие с сущностями**: `Modules/{Module}/Repository` содержит исключительно `*Repository`-классы. Репозиторий принимает критерии запроса и возвращает доменные сущности (`Entity`) и их коллекции; скаляр или enum допустим только как результат запроса состояния (`count`, `exists`, статус). DTO, read-model, типизированные представления сырой строки БД, value object-ы и прочие вспомогательные классы в папке `Repository` запрещены — им место в `Domain`/`Application`/`Infrastructure`. Если строки нужно прочитать в обход ORM-гидрации (например, обработка повреждённых данных), такой код и его типы живут в `Infrastructure`, а не в репозитории.
- **Репозитории без обязательных контрактов**: `RepositoryContract` не создаётся автоматически. Контракт для репозитория добавляется только при реальной причине: несколько реализаций, сложная подмена в тестах или необходимость полностью отвязать сценарий от конкретной ORM.
- **Контракты технических сервисов**: если `Application` нужен технический сервис, контракт лежит в `Modules/{Module}/Application/Contract` и заканчивается на `Contract`. Реализация лежит в `Infrastructure` своего модуля. Пример: `MediaFileServiceContract` и `S3MediaFileService`.
- **Доменные методы в репозиториях**: запрещены generic-вызовы `findByPK()`, `findOne()`, `select()` в Handler-ах и другом бизнес-коде. Репозиторий предоставляет конкретные доменные методы: `findByEmail()`, `findByTokenHash()`, `findLatestByUserId()`. Базовые методы Repository (`findOne`, `findByPK`) используются только внутри самого Repository для реализации доменных методов.
- **Без лишнего `instanceof` в репозиториях**: если репозиторий наследуется от `Cycle\ORM\Select\Repository` с PHPDoc `@extends Repository<Entity>`, методы Cycle (`findByPK()`, `findOne()`, `findAll()`, `select()->fetchOne()`) уже типизируются как эта Entity. Не писать `return $entity instanceof Entity ? $entity : null;` после таких вызовов — возвращать результат напрямую.
- **Параметры методов Repository только про запрос**: методы Repository принимают только критерии выборки, сортировки, пагинации или блокировки, относящиеся к конкретному запросу. Нельзя передавать в методы Repository технические зависимости вроде `DatabaseInterface`, `Select`, `EntityManagerInterface`, config, logger или service-объекты. Такие зависимости передаются через constructor injection и остаются внутренней деталью Repository.
- **Выборки в Repository через ORM Select**: внутри Repository для чтения сущностей использовать `$this->select()` с доменными методами Repository. Не строить выборку сущностей через `$database->select()->from('table_name')`, если то же можно выразить через ORM Select. Query Builder допустим только для операций, которые ORM Select не умеет выразить, и причина должна быть понятна из кода.
- **Репозиторий — только чтение (read-only)**: Repository только читает данные — через ORM `$this->select()` и базовые `findByPK()`/`findOne()`/`findAll()`. Репозиторий не сохраняет и не изменяет данные: запрещены методы-мутаторы `save()`, `persist()`, `store()`, `create()`, `update()`, `delete()`, а также инъекция `EntityManagerInterface` в репозиторий. Прямые `$database->update()`, `$database->insert()`, `$database->delete()` тоже запрещены — даже атомарный CAS-переход по статусу. Любое изменение состояния выражается доменным методом Entity (`markQueued()`, `markFailed()` и т.д.) и сохраняется вызовом `$this->entityManager->persist($entity)` + `$this->entityManager->run()` в Handler-е или инфраструктурном orchestrator-е, который вызывает репозиторий только для выборки. Правило rules.md «Запрет SQL текстом» отвечает на вопрос *как* писать запрос (query builder, не сырая строка), но не разрешает репозиторию модифицировать данные.
- **Не обходить ORM ради «свежего» чтения**: все изменения проходят через Entity и общий identity map ORM, поэтому Entity в памяти и есть актуальное состояние. Запрещено добавлять сырые `$database->select()` «чтобы перечитать свежий статус мимо identity map» — читать саму Entity или доменный метод репозитория на ORM Select. Если в sync-сценарии другой обработчик меняет состояние в том же процессе, он меняет ту же Entity, и она уже актуальна. Потребность в read мимо гидрации почти всегда сигнал, что изменение должно было пройти через Entity.
- **Запрет транзакций внутри Repository**: Repository не открывает, не коммитит и не откатывает транзакции (`transaction()`, `begin()`, `commit()`, `rollback()`). Граница транзакции находится в Handler-е, Application-сценарии или инфраструктурном orchestrator-е, который вызывает Repository. Repository отвечает только за конкретные запросы (чтение).
- **Доступ к БД только через репозитории**: прямые запросы к базе данных (`$database->query()`, `$database->table()`, `$orm->getRepository()`, инлайн `Select`, raw SQL) запрещены в Handler-ах, контроллерах, сервисах и любом бизнес-коде. Весь доступ к данным — исключительно через методы Repository-классов своего модуля. Это обеспечивает единую точку доступа к данным и предотвращает дублирование запросов. Исключение — миграции и инфраструктурный код (console-команды для обслуживания БД).
- **Запрет SQL текстом**: ручные SQL-строки в `$database->query()` и `$database->execute()` запрещены в коде приложения и тестах. Для выборки, вставки, обновления и удаления всегда использовать Cycle ORM Select или Cycle Database Query Builder (`select()`, `insert()`, `update()`, `delete()`). Исключение — миграции, если это невозможно выразить через schema/query builder и причина явно обоснована рядом с кодом.
- **Формат даты для БД через общий контракт**: если дату нужно явно форматировать для query builder или другого запроса к БД, формат нельзя писать строкой прямо в вызове `format()` и нельзя заводить локальную константу в отдельном репозитории. Используй общий `App\Shared\Infrastructure\Database\DatabaseDateTimeFormat`, чтобы формат был единым контрактом хранения.
- **Typecast VO: конвенция или отдельный класс**: для non-nullable VO, отображаемого на скаляр (id, тип, счётчик, enum), достаточно общего `App\Shared\Infrastructure\Cycle\ValueObjectCast` по соглашению — cast из БД через `fromString(string)`/`fromInt(int)`/`BackedEnum::from()`, запись через `value()` (скаляр или `DateTimeInterface`). Отдельный `ColumnValueTypecast`-класс в `Modules/{Module}/Infrastructure/Cycle` (статические `castDatabaseValue()`/`uncastValue()`) нужен только для случаев, которые конвенция не выражает: nullable-колонка при non-null VO-свойстве (null-object `none()`/`permanent()` — иначе `NULL` превратится в `null` и сломает типизированное свойство), дата/время (нужна фабрика `fromDateTime`), JSON или коллекция (`fromJson`/`json_encode` вместо `fromString`/`value()`).
- **Без pass-through typecast-обёрток**: не создавать per-module typecast-класс, который реализует `Castable`/`UncastableInterface` и только делегирует в `ValueObjectCast` без своей логики. Entity ссылается на общий движок напрямую: `typecast: [Typecast::class, ValueObjectCast::class]`. Отдельный класс заводится только при наличии собственной логики преобразования (см. предыдущее правило).
- **Stateful typecast — не синглтон**: `ValueObjectCast` хранит правила (`$rules`) для конкретной роли Entity, поэтому stateful; Cycle создаёт по одному обработчику на роль через `factory->make()`. Его (и любой stateful typecast) нельзя помечать `#[Singleton]` или биндить общим синглтоном в контейнере — иначе правила разных сущностей смешаются.
- **Логирование: DEBUG по умолчанию**: бизнес-логика логирует на уровне DEBUG. INFO — только для ключевых бизнес-событий (успешная регистрация, успешный сброс пароля). WARN — реальные проблемы инфраструктуры. ERROR — сбои, нарушения инвариантов. Неверный пароль, истёкший код, невалидный токен — это нормальный flow пользователя → DEBUG, не WARNING.
- **Centrifugo через свой HTTP-клиент**: для взаимодействия с Centrifugo используется клиент в `Modules/{Module}/Infrastructure/Centrifugo` или в `Shared/Infrastructure`, если он нужен нескольким модулям (прямые curl-запросы к HTTP API). Пакет `centrifugal/phpcent` удалён (deprecated `curl_close()` на PHP 8.5). Сервис `CentrifugoService` — обёртка над `CentrifugoClient`.
- **Внешние события и отложенные шаги через outbox**: события, которые вызывают внешние побочные эффекты (Centrifugo, email, push, webhooks), сохраняются как DTO в transactional outbox в той же транзакции, что и бизнес-изменение. Handler-ы и Spiral event listener-ы не вызывают такие интеграции напрямую. Публикация выполняется отдельным relay-процессом после commit-а с повторами и статусами доставки. Тот же механизм применяется и к внутренним отложенным шагам, которые должны гарантированно выполниться после commit-а бизнес-транзакции, даже если их побочный эффект остаётся внутри системы (например, асинхронная обработка загруженного медиа: `MediaUploaded` кладётся в outbox в одной транзакции с переходом медиа в `uploaded`, а relay запускает обработку). Критерий — нужна надёжная доставка «после commit», а не природа эффекта (внешний или внутренний).
- **Outbox relay запускается в одном экземпляре**: текущий relay использует `FOR UPDATE` без `SKIP LOCKED`, потому что ручной SQL запрещён, а Cycle ORM Select не даёт отдельного API для `SKIP LOCKED`. До отдельного решения по безопасному `SKIP LOCKED` нельзя запускать несколько постоянных `outbox:relay --loop` одновременно.
- **Запрет yii-error-handler-bridge**: пакет `spiral-packages/yii-error-handler-bridge` удалён — HTML/XML рендеры ошибок не нужны в API-only проекте. Ошибки обрабатываются через `GianTiaga\SpiralApiErrors\Interceptor\ApiExceptionInterceptor`.
- **`entityManager->run()` вызывается в Handler-е**: каждый Handler сам вызывает `$this->entityManager->persist()` / `$this->entityManager->delete()` и затем `$this->entityManager->run()` для flush. Транзакцию вокруг dispatch включает только `#[Transactional]` на `Handler::handle()`. Если атрибута нет, dispatch выполняется без транзакции. `#[NonTransactional]` не используется. `run()` остаётся внутри Handler-а, чтобы сценарий явно управлял моментом flush. Исключение — технический `outbox:relay`: это инфраструктурный orchestrator после commit-а бизнес-транзакции, поэтому он сам фиксирует claim, publish-failure и queue-status переходы.
- **Queue push по FQCN класса**: задачи пушатся в очередь по имени класса Job (`SendEmailVerificationCodeJob::class`). Промежуточные enum-ы для имён задач — лишняя прослойка. Spiral Queue маршрутизирует по имени класса напрямую.
- **Filter-ы живут в модуле**: Filter-классы — часть HTTP-слоя (парсинг HTTP-запросов), не доменной логики. Размещать в `Modules/{Module}/Presentation/Http/Filter/{Area}`. Например: `App\Modules\Auth\Presentation\Http\Filter\LoginFilter`, `App\Modules\User\Presentation\Http\Filter\UpdateUserProfileFilter`. Папка `App\Filter` запрещена.
- **CQRS: группировка по действию**: внутри `Modules/{Module}/Application/Command/{Domain}/` и `Modules/{Module}/Application/Query/{Domain}/` каждое действие выносится в отдельную подпапку. Папка называется по действию (без суффикса Command/Query). Пример: `Modules/Auth/Application/Command/Auth/Login/LoginCommand.php`, `LoginHandler.php`, `LoginResult.php`. Это даёт чёткую изоляцию: все файлы одного use-case лежат рядом. Namespace соответствует: `App\Modules\Auth\Application\Command\Auth\Login`.
- **CQRS: полное имя действия в имени класса**: имя Command/Query/Handler/Filter должно содержать полный контекст действия, включая доменную сущность, даже если namespace уже содержит домен. Пример: `UpdateUserProfileCommand` (не `UpdateProfileCommand`), `GetUserProfileQuery` (не `GetProfileQuery`), `UpdateUserProfileFilter` (не `UpdateProfileFilter`). Папка действия совпадает: `Application/Command/User/UpdateUserProfile/`, `Application/Query/User/GetUserProfile/`. Это делает класс самодокументируемым — по имени сразу понятно, что он делает, без заглядывания в namespace.


## Типовые контракты

- **Без неявного `mixed`**: generic-параметры указываются явно. Нельзя писать `array`, если контракт на самом деле ожидает `list<Foo>` или `array<int, Foo>`.
- **Без сложных массивов в PHPDoc**: вложенные массивы, tuple-типы и array shapes запрещены, например `array<string, array<int, string>>` и `array{foo: string}`.
- **Без явного `mixed`**: `mixed` не используется в проектных контрактах, включая параметры, return type, PHPDoc generic-и и callbacks. Неизвестное значение нужно сузить на границе системы и дальше передавать именованный DTO/VO/enum, конкретный union type или типизированную коллекцию.
- **Именованные типы для сложных данных**: вместо сложных generic-контейнеров используйте DTO/value object, enum или коллекции. Допустимы простые `list<T>` и `array<int|string, T>`, где `T` не является массивом, shape или tuple.

## Тестирование
- 100 процентное покрытие тестами
- Каждый роут должен быть покрыт интеграционным тестом
- **Стаб вместо `expects()` для дублёров без проверки вызова**: если дублёр нужен только чтобы вернуть данные (не для проверки факта/числа вызовов), используй `createStub()` + `willReturn*()`, а не `createMock()` с `->method()` без `expects()` и не `->expects($this->any())`. В PHPUnit 13 `with()`/`method()` без `expects()` деприкейтнут, а `expects($this->any())` тоже деприкейтнут (удаляется в PHPUnit 14). Для подбора возврата по аргументу — `willReturnMap([[ $arg, $value ]])`. Важный нюанс: при несовпадении аргумента `willReturnMap` возвращает дефолт по типу возврата метода (для `: array` это `[]`, не исключение), поэтому стаб сам по себе не проверяет аргумент. Для дублёров конфигуратора (`getConfig(): array`) это допустимо, пока у целевого DTO есть обязательные поля — маппинг пустого `[]` падает и косвенно ловит «не ту секцию». Если добавляешь конфиг, у которого все поля опциональны/с дефолтами, заведи отдельный позитивный тест на «ожидаемая секция запрошена» (например `willReturnCallback`, бросающий при несовпадении), иначе неверная секция пройдёт молча.

## Проверки

- **Тесты приложения**: локальная проверка запускается командой `make test`.
- **PHPStan приложения**: локальная проверка запускается командой `make phpstan`.
- **PHPStan tooling**: живёт отдельным Composer-пакетом в `packages/phpstan-strict-rules`. Его тесты и fixtures должны оставаться внутри этого пакета, а не в корневом `tests/`.
- **Packages-пакеты изолированы**: каждый пакет в `packages/*` является отдельным Composer-пакетом со своим `composer.json`, локальным `vendor/`, package-local `bootstrap.php`, `phpunit.xml` и `phpstan.neon`. Bootstrap и Composer scripts внутри `packages/*` не должны ссылаться на корневой `../../vendor` или `../../vendor/bin`. Для проверки пакета используется `composer -d packages/<package> install`, затем `composer -d packages/<package> test` и `composer -d packages/<package> phpstan`.

exec
/bin/zsh -lc "sed -n '1,260p' docs/plans/2026-06-15_13-25_auth-module.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
---
title: Модуль Auth — беспарольный вход по email-коду
date: 2026-06-15 13:25
mode: strict
plan_size: normal
decision_mode: recommend_and_ask
status: meta-reviewed
reviewer: pending
meta_reviewers: [haiku, sonnet, opus]
test_strategy: after_each_phase
logging_strategy: debug_precise
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  research: docs/researches/2026-06-13_22-36_auth-module.md
---

# План реализации

## Задача

Реализовать модуль `Auth` — беспарольную аутентификацию по email-коду для API
мобильного приложения (один поток = и вход, и регистрация). Плюс минимальный слой
`User/Application` (`CreateUser`, `FindUserForAuth`), без которого `Auth` не собрать.

Готово, когда: 5 маршрутов (`code/request`, `code/verify`, `register`, `refresh`,
`logout`) работают по сценарию из исследования; каждый маршрут покрыт интеграционным
тестом; HMAC-хэширование кодов/талонов и SHA-256 токенов на месте; rate limit активен;
`make test` и `make phpstan` зелёные; покрытие 100%.

## Контекст

- Стек: PHP 8.5, Spiral 3.16.2 (включает `spiral/auth`, `spiral/auth-http`), Cycle ORM,
  RoadRunner, PostgreSQL, Redis, RabbitMQ, Mailpit. Модульный монолит + CQRS + outbox
  (`docs/arch.md`).
- `spiral/auth` **не подключён**: `AuthBootloader`/`HttpAuthBootloader` отсутствуют в
  `Kernel` (`app/src/Shared/Infrastructure/Framework/Kernel.php`). Их надо добавить.
- Модуль `User` реализован как Domain + Repository + Infrastructure, **слоя `Application`
  нет** (подтверждено). `User::create(UserName, Email, UserNickname, Locale)` ставит
  статус `WaitingEmailConfirmation`; `User::confirmEmail()` → `Active`
  (`app/src/Modules/User/Domain/Entity/User.php:77,101`). Predicate-методы `isActive()`,
  `isBanned()`, `isDeleted()`. VO создаются через `fromString()`. `Locale` — enum
  `Shared/Domain/Enum/Locale.php` (Ru|En). Ник: уникальность через
  `UserRepository::existsByNickname()` и резерв через `ReservedNicknameRepository::isReserved()`.
- Outbox: `OutboxEventStoreContract::add(OutboxMessage): StoredOutboxEventId`; сообщение —
  `final readonly class ... implements OutboxMessage` (публичные readonly-поля,
  примитивы/enum/DateTimeImmutable, сериализация Valinor); пара «сообщение → Job»
  регистрируется в бутлоадере модуля через `OutboxJobRegistryContract::register()`; Job
  расширяет `Spiral\Queue\JobHandler`, грузит сообщение через
  `OutboxMessageLoaderContract::load(outboxEventId, expectedMessageClass)` и диспатчит Command
  (эталон: `MediaBootloader`, `ProcessMediaJob`, `CompleteMediaUploadHandler`).
- Shared: `UserId extends AbstractUuidV7Id` (`generate()`, `fromString()`, `value()`);
  trait `HasTimestamps` (`createdAt`/`updatedAt`, `initializeTimestamps()`, `touch()`);
  переводимые 4xx-исключения `AuthenticationException(translationKey, params)` (401) и др.
  через `DomainTranslatableException` (домен перевода = второй сегмент ключа: `app.auth.*`
  → `app/locale/{lang}/auth.php`); `ValueObjectCast` (конвенция `fromString`/`fromInt`/
  `value`) и отдельные `ColumnValueTypecast` для nullable/datetime; `AbstractResource`
  (наследник определяет публичные поля + `fromEntity()`); Response-классы
  `DataResponse/CollectionResponse/PaginationResponse` (`packages/spiral-openapi`).
- Каркас: бутлоадеры — `Kernel::defineBootloaders()`; HTTP-интерсепторы контроллеров —
  `AppBootloader::INTERCEPTORS` (`CycleInterceptor`, `GuardInterceptor`,
  `HttpResponseInterceptor`, `ApiExceptionInterceptor`); группы middleware —
  `RoutesBootloader::middlewareGroups()` (`GROUP_API='api'`); `LocaleMiddleware` (global)
  ставит локаль в Spiral Translator (singleton). `Route` annotation поддерживает `group`
  и per-route `middleware` (массив class-string/Autowire). Бизнес-контроллеров с `#[Route]`
  в коде пока нет — есть только System (Health/Swagger) и тестовый
  `tests/App/.../ApiErrorTestController`; в фазе 7 проверяем, что сканирование
  аннотированных маршрутов включено (иначе добавляем `AnnotatedRoutesBootloader`).
- `spiral/auth` контракты (vendor 3.16.2): `TokenStorageInterface::{load(string):?TokenInterface,
  create(array,?DateTimeInterface):TokenInterface, delete(TokenInterface):void}`;
  `TokenInterface::{getID():string, getPayload():array, getExpiresAt():?DateTimeInterface}`;
  `ActorProviderInterface::getActor(TokenInterface):?object`;
  `HeaderTransport(header:'Authorization', valueFormat:'Bearer %s')`;
  `HttpAuthBootloader::{addTransport(), addTokenStorage()}`, `AuthBootloader::addActorProvider()`;
  `AuthTransportWithStorageMiddleware(transportName, storage)` (`#[Scope('http')]`);
  `AuthContextInterface` биндится Proxy в http-scope, читается из request-атрибута.
  `AuthMiddleware::initContext` зовёт `tokenStorage->load()` на каждом запросе с токеном.
- Подтверждено по vendor: `Cycle\ORM\Select\Repository::forUpdate()` и `Select::forUpdate()`
  (эталон применения — `OutboxEventRepository`: `->select()->...->forUpdate()->fetchOne()`);
  `EncryptionInterface::getKey()`; типизированные коллекции = `extends Illuminate\Support\Collection`;
  `HttpStatus::TooManyRequests = 429` (`packages/spiral-openapi`); `EntityManager::run()`
  безопасно вызывать несколько раз в одной транзакции (не ломает identity map).

## Принятые решения

Все решения по схемам БД, API-контрактам, TTL, типам токенов и потокам подтверждены в
research (`status: reviewed`, раздел «Ответы на вопросы», `decision_mode: ask_each_time`).
Ниже — решения, дополнительно принятые при планировании (источник: `autonomous` с причиной,
либо ответ пользователя в текущем сеансе), чтобы план соответствовал `docs/rules.md` и
`docs/arch.md`:

1. **Пути маршрутов нормализованы к `/api/v1/auth/*`** (research писал `/api/auth/*`).
   Причина: в кодовой базе принята схема `/api/v1/...` (`docs/code-examples.md` UserController).
2. **Каркас auth настраивается в `AuthBootloader`, без `app/config/auth.php`.** Добавляем в
   `Kernel` vendor-бутлоадер `HttpAuthBootloader` (он тянет vendor `AuthBootloader`), а
   транспорт/хранилище/actor-provider регистрируем кодом. Причина: создание нового
   `app/config/*.php` обязывает завести `TypedConfig` (правило rules.md), но конфиг auth
   хранит объекты-транспорты/хранилища, и Valinor-маппинг к нему неприменим — это был бы
   конфликт с правилом. Бутлоадер-настройка конфликт снимает.
3. **`Auth/Domain` владеет собственным VO `EmailAddress`** (а не импортирует
   `User/Domain/ValueObject/Email`). Причина: `arch.md` запрещает `Domain → Domain другого
   модуля` (`Domain` зависит только от своего и `Shared/Domain`). Нормализация ОБЯЗАНА быть
   идентична `User\Email` (`mb_strtolower`+`trim`, `FILTER_VALIDATE_EMAIL`, ≤254), чтобы ключи
   по email в `auth_*` и `users` совпадали побайтово; это покрывается тестом-инвариантом
   эквивалентности нормализации. На границе с `User/Application` email передаётся строкой.
4. **Атомарность одноразовых операций — через блокировку строки `forUpdate()` в
   репозиторном read внутри `#[Transactional]`-хендлера**, а не сырым `UPDATE ... WHERE
   consumed_at IS NULL`. `forUpdate()` применяется прямо в цепочке `$this->select()->where(...)
   ->forUpdate()->fetchOne()` (как в `OutboxEventRepository`), а не через `Repository::forUpdate()`
   (иначе флаг блокировки теряется при clone). Причина: rules.md запрещает мутации и сырой SQL
   в репозитории, но явно разрешает «блокировки» как параметр запроса. **`forUpdate` только в
   путях изменения (consume кода/талона, ротация/отзыв токенов); обычные чтения (в т.ч.
   `load()` access-токена на каждом запросе) — БЕЗ блокировки.**
5. **Один VO `Expiration`** (вместо `CodeExpiration`/`TicketExpiration`/`TokenExpiration`):
   `Expiration::after(now, seconds)`, `value(): DateTimeImmutable`, `isExpired(now): bool`.
   Причина: rules.md «не плодить» — поведение идентично, отличаются лишь TTL при создании.
6. **Токены хранит единый адаптер `CycleTokenStorage`**, реализующий и
   `Spiral\Auth\TokenStorageInterface` (метод `load` — для auth-middleware), и собственный
   `Auth/Application/Contract/AuthTokenStorageContract` (доменные методы `issuePair`,
   `rotate`, `revokeSession` — для хендлеров). Уточнения по итогам мета-ревью: (а) `load()` —
   обычное чтение по хэшу БЕЗ `forUpdate`; (б) адаптер-`TokenInterface` (`AuthTokenView`)
   возвращает `getID()` = исходный raw из аргумента `load(string $id)`, иначе `commitToken`
   фреймворка испортит заголовок ответа; (в) `persist`+`run` адаптер делает сам как
   инфраструктурный orchestrator (rules.md допускает; двойного flush/проблем identity map нет),
   вызовы происходят внутри `#[Transactional]` сценария — это корректно. Причина: хендлеры
   зависят только от своего Application-контракта, middleware — от фреймворк-интерфейса;
   методы `load/create/delete` все задействованы (без мёртвого кода).
7. **`FindUserForAuth` возвращает DTO `UserAuthView{userId, canSignIn}`** (Application-DTO,
   не Entity). `status`/`locale` в DTO не входят: сценарию хватает `canSignIn` (= `isActive()`),
   а `locale` не нужен (см. решение №11) — лишние поля убраны по rules.md «нет мёртвого кода».
   Источник: ответ пользователя в текущем сеансе.
8. **Аутентифицированный пользователь прокидывается в контроллер через request-атрибуты
   `authUserId` и `authSessionId`** (rules.md: контроллеры читают `#[Attribute(key:'authUserId')]`,
   `ServerRequestInterface` запрещён). Их ставит тонкий `AuthContextAttributeMiddleware`,
   который инжектит `AuthContextInterface` через конструктор (Proxy, http-scope) и берёт
   `getToken()->getPayload()['userID']` и `['sessionID']`. **Auth-middleware вешаются per-route
   только на `logout`** (`AuthTransportWithStorageMiddleware` → `AuthContextAttributeMiddleware`
   → `RequireAuthenticatedMiddleware`), а НЕ на всю группу `api`. Причина: только `logout`
   требует Bearer; группа `api` не трогается → нет регрессии System-тестов и лишних `load()`
   на публичных маршрутах. Будущие защищённые маршруты добавляют те же middleware у себя.
9. **Rate limit: общий per-route HTTP-middleware `RateLimitMiddleware` в `Shared`** (а НЕ
   интерцептор + атрибут). Конфигурируется аргументами на маршруте:
   `#[Route(..., middleware: [new Autowire(RateLimitMiddleware::class, ['maxAttempts'=>N,'perSeconds'=>M])])]`.
   Ключ = client IP (`ServerRequestInterface` `REMOTE_ADDR`) + имя маршрута; счётчик
   фиксированного окна в Redis (PSR-16 `CacheInterface`, TTL=perSeconds). Превышение → middleware
   САМ возвращает JSON-ответ 429 `{"message": <перевод app.shared.rate_limit_exceeded>, "code":429}`
   (middleware вне цепочки controller-интерсепторов, поэтому исключение тут не сконвертируется —
   нужен прямой ответ). Причина итогов мета-ревью: интерцептор не получает client IP из
   `CallContext`, имеет проблемы порядка относительно `ApiExceptionInterceptor` и Cycle, и менял
   бы общий `AppBootloader::INTERCEPTORS`. Middleware получает запрос напрямую и срабатывает до
   контроллера и до открытия БД-транзакции. Незначительный перерасчёт при гонке допустим.
10. **Серверный секрет для HMAC — ключ приложения через `Spiral\Encrypter\EncryptionInterface::getKey()`**
    (тот же `ENCRYPTER_KEY`), без нового конфига и без `env()` в коде.
11. **Язык письма с кодом всегда берётся из локали запроса** (`requestLocale`, переданный
    контроллером из Spiral Translator `getLocale()`; при неподдерживаемом значении — fallback
    `LocaleConfig.default`). `RequestLoginCode` не обращается в `User/Application` ради локали,
    `UserAuthView` не несёт `locale`. Источник: ответ пользователя в текущем сеансе. Причина:
    убрать второй источник локали и связность ради краевого случая; для мобильного клиента
    язык запроса корректнее.
12. **`VerifyLoginCode` разделён на оркестратор и транзакционный под-сценарий**
    (`ResolveLoginCode` `#[Transactional]`). Причина (блокер мета-ревью): при `#[Transactional]`
    на всём `VerifyLoginCode` инкремент `attempts` + `throw 401` приводил бы к rollback и счётчик
    попыток никогда не рос бы (защита от перебора неработоспособна). Поэтому `ResolveLoginCode`
    коммитит запись (consume или `attempts++`) и ВОЗВРАЩАЕТ результат, а 401 бросает
    нетранзакционный оркестратор `VerifyLoginCode` уже после commit.

## Целевой алгоритм

```text
ЗАПРОС КОДА  POST /api/v1/auth/code/request {email}        [RequestLoginCode #[Transactional]]
  Filter → EmailAddress. Язык письма = requestLocale (локаль запроса, передал контроллер).
  Если активный код для email создан < 60с назад → ничего не шлём, 200. Иначе: гасим прежний
  активный код, генерим 6-значный код, пишем LoginCode (codeHash = HMAC(code),
  expiresAt = now+10м, attempts=0), кладём LoginCodeRequested {email, code, locale} в outbox,
  run(). Ответ ВСЕГДА 200 (наличие юзера не раскрываем; в User/Application не ходим).

ПРОВЕРКА КОДА POST /api/v1/auth/code/verify {email, code}
  VerifyLoginCode (оркестратор, БЕЗ #[Transactional]):
    диспатчит ResolveLoginCode (#[Transactional]) → LoginCodeResolution {outcome, tokens?, ticket?}.
    Затем по outcome: NoCode/Expired/Exhausted/Wrong/NotAllowed → AuthenticationException (401);
    Verified → 200 {tokens, needsProfile:false}; NeedsProfile → 200 {registrationTicket, needsProfile:true}.
  ResolveLoginCode (#[Transactional]):
    Filter дал EmailAddress + LoginCodeValue(6 цифр). Грузит активный код по email с forUpdate.
    нет кода → NoCode; истёк → Expired; attempts исчерпаны → Exhausted (записей нет, тх пустая).
    Неверный код → registerFailedAttempt()+persist+run → Wrong (инкремент КОММИТИТСЯ).
    Верный → consume(); FindUserForAuth(email):
      • есть и canSignIn → issuePair(userId) (в той же тх) → Verified+tokens;
      • есть и !canSignIn → NotAllowed (код погашен, токенов нет);
      • нет → RegistrationTicket::issue (ticketHash=HMAC(ticket), now+15м) → NeedsProfile+ticket.
    run() финально.

РЕГИСТРАЦИЯ  POST /api/v1/auth/register {ticket, name, nickname}  [CompleteRegistration #[Transactional]]
  Filter → строки; локаль = requestLocale (от контроллера). Грузит активный талон по HMAC(ticket)
  с forUpdate; нет/истёк → AuthenticationException (401). consume() талона. Читает email из талона,
  диспатчит User/Application CreateUser(email, name, nickname, requestLocale) (#[Transactional],
  вложенно через SAVEPOINT; User::create()+confirmEmail() → Active; держит уникальность ника/резерв;
  занятый ник/email → ValidationException 422 → откат SAVEPOINT и внешней тх ⇒ талон НЕ погашен,
  можно повторить). UserId → issuePair(userId), run() → 200 {accessToken, refreshToken, tokenType,
  expiresIn}.

ОБНОВЛЕНИЕ   POST /api/v1/auth/refresh {refreshToken}     [RefreshTokens #[Transactional]]
  AuthTokenStorage.rotate(refreshRaw): hash → find с forUpdate → type=refresh и не истёк
  (иначе 401) → delete пары по sessionId → issuePair нового sessionId → 200 {access, refresh, ...}.

ВЫХОД        POST /api/v1/auth/logout (Bearer access)      [Logout #[Transactional]]
  Per-route middleware: AuthTransportWithStorageMiddleware (header/cycle) аутентифицирует access →
  AuthContext; AuthContextAttributeMiddleware ставит authUserId/authSessionId из payload токена;
  RequireAuthenticatedMiddleware → 401 без actor. Контроллер читает #[Attribute('authSessionId')] →
  AuthTokenStorage.revokeSession(sessionId) (delete обеих строк), run() → 200 {status:'ok'}.

ЗАПРОС К API (любой защищённый, на будущее)
  AuthTransportWithStorageMiddleware → CycleTokenStorage.load(raw) [обычное чтение] → только
  type=access → UserActorProvider.getActor(token) → AuthenticatedUser(UserId из payload.userID).
  refresh-токен, присланный как Bearer, actor НЕ даёт (закрытая дыра).

ПИСЬМО  outbox relay → RabbitMQ → SendLoginCodeJob → SendLoginCode (Mailer, локаль из сообщения) → Mailpit.
```

## Контракты реализации

### Данные и БД

Три новые таблицы (владелец — модуль `Auth`). Миграция Cycle в
`app/database/migrations/{stamp}_0_create_auth_domain_tables.php` по эталону
`20260613.143901_0_create_user_domain_tables.php` (`addColumn`, `setPrimaryKeys`,
`addIndex`, `up()/down()`). Все id — UUID v7. `email` — `string(254)` + нормализация в VO
(не citext), как в `users`.

```text
auth_login_codes
  id          uuid           NOT NULL  PK
  email       string(254)    NOT NULL  index
  code_hash   text           NOT NULL            -- HMAC-SHA256(code)
  expires_at  datetime       NOT NULL            -- now + 10 мин
  attempts    integer        NOT NULL  default 0 -- лимит 5
  consumed_at datetime       NULL                -- одноразовость
  created_at  datetime       NOT NULL
  updated_at  datetime       NOT NULL

auth_registration_tickets
  id          uuid           NOT NULL  PK
  email       string(254)    NOT NULL  index
  ticket_hash text           NOT NULL            -- HMAC-SHA256(ticket)
  expires_at  datetime       NOT NULL            -- now + 15 мин
  consumed_at datetime       NULL
  created_at  datetime       NOT NULL
  updated_at  datetime       NOT NULL

auth_tokens
  id          uuid           NOT NULL  PK
  user_id     uuid           NOT NULL  index      -- UserId (отзыв по пользователю)
  session_id  uuid           NOT NULL  index      -- общий id пары access+refresh
  type        string(16)     NOT NULL             -- access | refresh
  token_hash  text           NOT NULL  UNIQUE     -- SHA-256(token); unique → findByHash однозначен
  expires_at  datetime       NOT NULL             -- access: now+1ч; refresh: now+60д
  created_at  datetime       NOT NULL
  updated_at  datetime       NOT NULL
```

Совместимость: таблицы новые, backfill не нужен; `down()` дропает три таблицы. `users`,

exec
/bin/zsh -lc "sed -n '1,280p' docs/researches/2026-06-13_22-36_auth-module.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
---
title: Модуль Auth — беспарольный вход по email-коду
date: 2026-06-13 22:36
mode: strict
decision_mode: ask_each_time
status: reviewed
reviewer: codex
sources:
  rules: docs/rules.md
  arch: docs/arch.md
---

# Модуль Auth — беспарольный вход по email-коду

## Суть

Проектируем модуль `Auth` — аутентификацию для API мобильного приложения YogaLoka.
Домен и данные `User` и `Access` уже реализованы
(`app/src/Modules/User/Domain/Entity/User.php:77`, модуль `Access`), но
аутентификацию там осознанно не делали (`docs/researches/2026-06-13_12-33_user-domain.md:248`):
у `User` нет поля пароля вообще, есть только статус `WaitingEmailConfirmation` и метод
`confirmEmail()` (`app/src/Modules/User/Domain/Entity/User.php:101`).

Решаем: как пользователь входит и регистрируется, чем выдаём и проверяем токены, где
хранить коды и токены, как защититься от перебора, и как всё это лечь в модульный
монолит на Spiral + Cycle + CQRS (`docs/arch.md:5`), не нарушая границы модулей и не
переписывая готовый домен `User`.

Рамка: модуль `Auth` уже назван в архитектуре отдельной областью (`docs/arch.md:23`).
Идентификаторы — UUID v7, доменные значения — VO/enum, доступ к БД — через репозитории,
внешние эффекты (письма) — через outbox (`docs/arch.md:514`).

## Решение

Беспарольная аутентификация: пользователь вводит email, получает на почту 6-значный код,
вводит код — и входит. Один поток = и вход, и регистрация. Пароля нет, поэтому сброса и
смены пароля тоже нет. Токены — непрозрачные (случайная строка, данные на сервере), пара
access + refresh, хранятся в Cycle, refresh ротируется. Защита от перебора — переиспользуемый
атрибут `#[RateLimited]` + Spiral-интерцептор в `Shared`.

### Два потока входа

```text
СУЩЕСТВУЮЩИЙ email
  POST /api/auth/code/request {email}     -> код на почту (через outbox)
  POST /api/auth/code/verify  {email,code}-> сервер узнал юзера
                                             -> {access, refresh, expiresIn}

НОВЫЙ email (вход = регистрация)
  POST /api/auth/code/request {email}     -> код на почту
  POST /api/auth/code/verify  {email,code}-> юзера ещё нет
                                             -> {registrationTicket, needsProfile:true}
  POST /api/auth/register {ticket,name,nickname}
                                          -> создаём User (сразу Active) + {access, refresh}

ОБНОВЛЕНИЕ И ВЫХОД
  POST /api/auth/refresh {refreshToken}   -> ротация -> новые {access, refresh}
  POST /api/auth/logout  (Bearer access)  -> отзыв токена(ов)
```

«Талон регистрации» — короткоживущая (~10 мин) одноразовая запись, привязанная к email,
а не к пользователю (пользователя ещё нет). Он переносит доказательство «email только что
подтверждён кодом» с шага проверки кода на шаг заполнения профиля. Без него запрос
`register` не имел бы доказательства, что почта подтверждена, и любой мог бы
зарегистрировать чужой email. Талон годится только для завершения регистрации, это не токен
доступа.

Почему так, а не «авто-ник» или «всё сразу»: `User::create()` требует обязательные `UserName`
и уникальный `UserNickname` (`app/src/Modules/User/Domain/Entity/User.php:77-99`), а email-вход
даёт только email. Вариант «код → талон → профиль» не трогает готовый домен `User`, не плодит
пустые аккаунты и не занимает ники на неподтверждённые email. Пользователь рождается `Active`
не напрямую: `User::create()` ставит `WaitingEmailConfirmation`, а `CreateUser` сразу вызывает
`User::confirmEmail()` → `Active` (код уже доказал владение почтой). Поэтому при регистрации
«ожидание подтверждения» не висит, а сам статус остаётся для смены email существующим
пользователем (`changeEmail()` → код → `confirmEmail()`,
`app/src/Modules/User/Domain/Entity/User.php:107`).

### Карта модуля Auth

```text
Modules/Auth/
  Domain/
    Entity/        LoginCode, RegistrationTicket, AuthToken
    ValueObject/   LoginCodeId, RegistrationTicketId, AuthTokenId,
                   EmailFingerprint, SecretHash, CodeAttempts,
                   CodeExpiration, TicketExpiration, TokenExpiration
    Enum/          AuthTokenType (Access | Refresh)
  Application/
    Command/Auth/  RequestLoginCode, VerifyLoginCode, CompleteRegistration,
                   RefreshTokens, Logout
    Contract/      SecretHasherContract, TokenGeneratorContract
    Message/       LoginCodeRequested            # outbox-сообщение (письмо)
    Exception/     # ошибки слоя Application, если нужны сверх общих
  Repository/      LoginCodeRepository, RegistrationTicketRepository, AuthTokenRepository
  Infrastructure/
    Cycle/         # typecast VO
    Auth/          CycleTokenStorage, UserActorProvider
    Hash/          HmacSecretHasher                # реализация SecretHasherContract
    Bootloader/    AuthBootloader                  # биндинги storage/actor provider
  Presentation/
    Http/Controller/ AuthController
    Http/Filter/     RequestCodeFilter, VerifyCodeFilter, RegisterFilter, RefreshFilter
    Http/Resource/   TokenPairResource, VerifyResultResource
    Job/             SendLoginCodeJob              # потребитель outbox-задачи

Shared/                                            # переиспользуемый rate limit
  Presentation/Http/Attribute/ RateLimited
  Infrastructure/Framework/Interceptor/ RateLimitInterceptor
```

Auth ссылается на пользователя через общий `UserId` из `Shared/Domain/ValueObject`
(уже есть), как это делает `Access`. В таблицу `users` и в `UserRepository` Auth напрямую
не лезет — только через `User/Application` (`docs/arch.md:131`, `docs/arch.md:141-146`).

### Таблицы (владелец — модуль Auth)

```text
auth_login_codes
  id              UUID v7 PK
  email           string(254)       -- по нему ищем; индекс. Тип string + lower в Email VO,
                                        как в users (миграция 20260613.143901:21 — НЕ citext)
  code_hash       text              -- HMAC-SHA256(code, app secret); сам код не храним
  expires_at      datetime          -- now + 10 мин
  attempts        int               -- счётчик неверных вводов, лимит 5 -> код сгорает
  consumed_at     datetime null     -- одноразовость
  created_at, updated_at

auth_registration_tickets
  id              UUID v7 PK
  email           string(254)       -- string + lower (как users), не citext
  ticket_hash     text              -- HMAC-SHA256(ticket, app secret)
  expires_at      datetime          -- now + 15 мин
  consumed_at     datetime null
  created_at, updated_at

auth_tokens                          -- наше Cycle-хранилище токенов
  id              UUID v7 PK
  user_id         UUID              -- UserId; индекс (для отзыва по пользователю)
  session_id      UUID              -- общий id пары access+refresh (для logout и мульти-девайса)
  type            enum              -- access | refresh
  token_hash      text              -- SHA-256(token); сам токен у клиента
  expires_at      datetime          -- access: now + 1ч; refresh: now + 60д
  created_at, updated_at
```

`email` — обычный `string`, нормализованный в нижний регистр доменным VO `Email`
(`app/src/Modules/User/Domain/ValueObject/Email.php`), а не `citext`: модуль `User` уже
реализован на `string + lower VO` (миграция `app/database/migrations/20260613.143901_0_create_user_domain_tables.php:21-32`),
и Auth следует той же стратегии, чтобы email-ключи совпадали побайтово.

`session_id` связывает пару access+refresh: при `logout` по access-токену гасим обе строки
одной сессии; «выйти на всех устройствах» — удалить все строки по `user_id` (будущее).

### Токены: своё Cycle-хранилище поверх spiral/auth

`spiral/auth` (входит в `spiral/framework` 3.16.2) даёт `TokenStorageInterface`,
`ActorProviderInterface`, набор `Auth*Middleware` и настраиваемые транспорты — подтверждено по
документации Spiral 3.16. Точные сигнатуры, которые реализуем/используем:

```text
TokenStorageInterface::load(string $id): ?TokenInterface
TokenStorageInterface::create(array $payload, ?\DateTimeInterface $expiresAt = null): TokenInterface
TokenStorageInterface::delete(TokenInterface $token): void
ActorProviderInterface::getActor(TokenInterface $token): ?object   # получает TokenInterface, не строку
TokenInterface::getID(): string        # сырое значение токена, идёт клиенту
TokenInterface::getPayload(): array    # {userID, type: access|refresh, sessionID}
```

Транспорт по умолчанию в Spiral — заголовок `X-Auth-Token`, а не `Authorization: Bearer`.
Нам нужен Bearer, поэтому в `auth.php` явно настраиваем
`HeaderTransport(header: 'Authorization', valueFormat: 'Bearer %s')` и вешаем на API-группу
`AuthTransportMiddleware` (или `AuthTransportWithStorageMiddleware`), а не «просто
`AuthMiddleware`».

`TokenStorageInterface` реализуем **сами** (`Auth/Infrastructure/Auth/CycleTokenStorage`)
поверх своей сущности `AuthToken`, а не берём generic-хранилище `cycle-bridge`
(`AUTH_TOKEN_STORAGE=cycle`). Причина: generic кладёт payload в JSON и не индексирует по
пользователю, а нам нужны ротация refresh, отзыв при выходе, мульти-девайс и отзыв всех
токенов при бане. Своя таблица с `user_id` + `session_id` это решает и ложится в паттерн
проекта (Repository + Cycle). Это по-прежнему «хранилище Cycle», как и выбрано.

**Важно (закрытая дыра):** на HTTP-границе один `load()` вернул бы любой токен из заголовка,
поэтому refresh-токен, присланный как Bearer, мог бы пройти как сессия. Защита: для
HTTP-авторизации `CycleTokenStorage` отдаёт `getActor()` только при `payload.type === access`;
refresh-токены через HTTP-auth не аутентифицируют. Refresh-токен проверяется отдельным
сценарием из тела запроса, а не через `AuthTransportMiddleware`.

```text
выдача (verify/register): sessionId = UUID; create access (TTL 1ч) + create refresh (TTL 60д),
                          оба с одним sessionId -> клиенту отдаём сырые строки обоих токенов
запрос к API:             AuthTransportMiddleware (Authorization: Bearer) -> CycleTokenStorage.load
                          -> только type=access -> UserActorProvider.getActor(TokenInterface)
                          -> actor = AuthenticatedUser(UserId из payload.userID)
refresh:                  refreshToken из ТЕЛА -> load -> type=refresh + срок
                          -> атомарно delete пары по sessionId (ротация) + create новой пары
logout:                   по access-токену берём sessionId -> delete обеих строк сессии
```

`UserActorProvider::getActor(TokenInterface $token)` достаёт `userID` из payload и возвращает
лёгкий объект актора с `UserId`. Тяжёлой проверки в БД на каждом запросе не делаем: бан
отзывает токены (будущий `BanUser` дёргает `Auth/Application` для удаления токенов по
`user_id`), а access короткоживущий. Привязка актора к ролям/правам `Access` (GuardInterceptor)
— вне рамок этого захода.

Токены — 256-бит случайная строка (high-entropy), в БД только `SHA-256`. Коды и талоны —
низкоэнтропийные (6 цифр / короткая строка), поэтому хэшируем `HMAC-SHA256` с серверным
секретом (app key из `EncrypterBootloader`): при утечке БД без ключа перебор offline
невозможен. Сравнение — по хэшу (constant-time). Это `SecretHasherContract` с реализацией в
`Infrastructure/Hash`.

**Гонки.** Проверка кода, гашение талона и ротация refresh должны быть атомарны: два
параллельных `verify` не должны оба «потратить» один код. Используем условный `UPDATE ... SET
consumed_at = now WHERE id = ? AND consumed_at IS NULL` (или `SELECT ... FOR UPDATE` в той же
`#[Transactional]`-транзакции) и считаем операцию успешной только если строка реально
обновилась; то же для `attempts++`, гашения талона и удаления пары при ротации refresh.

### Письмо с кодом — через outbox

Отправка письма — внешний эффект, поэтому идёт через transactional outbox (`docs/arch.md:514`),
а не напрямую из Handler-а:

```text
RequestLoginCode Handler (#[Transactional])
  -> сгенерировать код, сохранить LoginCode (хэш) через LoginCodeRepository
  -> OutboxEventStoreContract::add( LoginCodeRequested{email, code, locale} )
  -> EntityManager::run()                       # код и outbox в одной транзакции
outbox relay -> RabbitMQ -> SendLoginCodeJob -> Mailer (Mailpit в dev) шлёт письмо
```

Код в payload outbox идёт открытым текстом (иначе письмо не отправить) — это короткоживущая
запись в служебной таблице, не видна пользователю, риск принимаем. Локаль письма кладём в
сообщение: для нового email — из `Accept-Language` запроса (`LocaleMiddleware`), для
существующего — из поля `User.locale` (`app/src/Modules/User/Domain/Entity/User.php:71`);
в очереди per-request локали нет (`docs/arch.md:382`), поэтому язык переносим в сообщение.

### Rate limit — атрибут + интерцептор в Shared

```text
Shared/Presentation/Http/Attribute/RateLimited(maxAttempts, perSeconds)
Shared/Infrastructure/Framework/Interceptor/RateLimitInterceptor
  - общий, ничего не знает про email
  - ключ = client IP + действие (контроллер::метод), счётчики в Redis (уже есть)
  - превышение -> 429 (через packages/spiral-api-errors)
```

Вешаем `#[RateLimited]` на действия `AuthController` (request/verify/register/refresh).
Лимит «не чаще одного кода в минуту на email» и «N неверных вводов кода» — это доменное
правило, живёт внутри сценариев Auth по таблице `auth_login_codes` (поля `attempts`,
`expires_at`), а не в общем интерцепторе. Так общий механизм остаётся универсальным для
любого эндпоинта, а email-специфика — там, где есть смысл email.

### Сценарии (CQRS)

```text
RequestLoginCode(email)            Command #[Transactional]
  валидирует email (Filter -> 422), генерит код, гасит/обновляет прошлый код для email,
  пишет LoginCode (hash), кладёт LoginCodeRequested в outbox. Ответ всегда 200
  (не раскрываем, есть ли такой пользователь).

VerifyLoginCode(email, code)       Command #[Transactional]
  грузит активный LoginCode по email, сверяет hash, проверяет срок и attempts.
  Неверно -> attempts++ (атомарно), AuthenticationException (401). Верно -> consumed (атомарно).
    - User по email существует (FindUserForAuth) и canSignIn -> выдать пару токенов.
    - User не существует -> создать RegistrationTicket, вернуть его + needsProfile.
    - User Banned/Deleted (canSignIn=false) -> AuthenticationException (401), общий текст
      (ключ перевода app.auth.sign_in_not_allowed), детали не раскрываем.
  WaitingEmailConfirmation при регистрации не возникает (см. поток); смена email -> confirmEmail()
  относится к User/Application changeEmail, не к этому сценарию.

CompleteRegistration(ticket,name,nickname)  Command #[Transactional]
  валидирует и атомарно гасит ticket, вызывает User/Application
  CreateUser(email,name,nickname,locale) (он внутри User::create()+confirmEmail(), держит
  уникальность ника и ReservedNickname, возвращает UserId), выдаёт пару токенов.

RefreshTokens(refreshToken)        Command #[Transactional]
  load -> type=refresh + срок -> delete (ротация) -> create access+refresh.

Logout(token)                      Command #[Transactional]
  delete токена(ов) текущей сессии.
```

codex
План уже содержит много решений после мета-ревью, поэтому дальше проверяю не текст «в вакууме», а совпадение с текущим каркасом проекта и реальными сигнатурами vendor. Отдельно смотрю места, которые ты перечислил как рискованные.
exec
/bin/zsh -lc "sed -n '261,620p' docs/plans/2026-06-15_13-25_auth-module.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
`user_bans`, `reserved_nicknames` не меняются. Принятый риск (из research): payload
outbox-события `LoginCodeRequested` содержит код открытым текстом в `outbox_events` —
запись короткоживущая, relay обрабатывает её сразу (не накапливать).

### API и внешние контракты

Маршруты регистрируются аннотацией `#[Route(group:'api')]` в `AuthController`. Тела —
Spiral Filter, ответы — типизированные Resource в `DataResponse`. Ошибки — через
`ApiExceptionInterceptor` (401/422); 429 — напрямую из `RateLimitMiddleware`.

```text
POST /api/v1/auth/code/request          middleware: RateLimitMiddleware
  body  {email: string}
  200   DataResponse<RequestCodeResultResource{status:'sent'}>   -- всегда, юзера не раскрываем
  422   ошибка валидации Filter
  429   RateLimitMiddleware

POST /api/v1/auth/code/verify           middleware: RateLimitMiddleware
  body  {email: string, code: string(6 цифр)}
  200   DataResponse<VerifyResultResource{needsProfile:bool, tokens:?TokenPairResource,
                                          registrationTicket:?string}>
  401   AuthenticationException (нет/истёк/неверный код, attempts исчерпаны, вход запрещён)
  422   валидация Filter
  429   RateLimitMiddleware

POST /api/v1/auth/register              middleware: RateLimitMiddleware
  body  {ticket: string, name: string, nickname: string}
  200   DataResponse<TokenPairResource{accessToken, refreshToken, tokenType:'Bearer', expiresIn:int}>
  401   AuthenticationException (нет/истёк талон)
  422   ValidationException (ник занят/зарезервирован, email уже зарегистрирован) / валидация Filter
  429   RateLimitMiddleware

POST /api/v1/auth/refresh               middleware: RateLimitMiddleware
  body  {refreshToken: string}
  200   DataResponse<TokenPairResource>
  401   AuthenticationException (невалидный/истёкший refresh, не type=refresh)
  429   RateLimitMiddleware

POST /api/v1/auth/logout    (Authorization: Bearer <access>)
  middleware: AuthTransportWithStorageMiddleware → AuthContextAttributeMiddleware → RequireAuthenticatedMiddleware
  body  —
  200   DataResponse<LogoutResultResource{status:'ok'}>
  401   нет/невалидный access (RequireAuthenticatedMiddleware)
```

Внешние сервисы: письмо с кодом — через outbox → RabbitMQ → `SendLoginCodeJob` → Spiral
Mailer (Mailpit в dev). Bearer-транспорт `Authorization: Bearer %s`.

## Фазы выполнения

### 1. User/Application: CreateUser и FindUserForAuth

Цель: дать модулю `Auth` единственную внешнюю зависимость — два сценария создания и поиска
пользователя на границе `User/Application`, не трогая Domain `User`.

Что сделать:
- `app/src/Modules/User/Application/Command/User/CreateUser/`: `CreateUserCommand`
  (`email, name, nickname, locale` — примитивы), `CreateUserHandler` `#[Transactional]`,
  `CreateUserResult{userId}`. Handler: строит VO (`Email::fromString`, `UserName::fromString`,
  `UserNickname::fromString`, `Locale::from`), проверяет `existsByEmail` → `ValidationException`
  (`app.user.email_taken`, 422), `existsByNickname`/`ReservedNicknameRepository::isReserved`
  → `ValidationException` (`app.user.nickname_taken`), `User::create(...)` затем
  `User::confirmEmail()` (→ Active), `entityManager->persist`+`run`, возвращает `UserId`.
  Вызывается и автономно, и вложенно из `CompleteRegistration` (тогда работает в SAVEPOINT
  внешней транзакции — при ошибке откат и талон не гасится).
- `app/src/Modules/User/Application/Query/User/FindUserForAuth/`: `FindUserForAuthQuery{email}`,
  `FindUserForAuthHandler`, DTO `Application/Dto/UserAuthView{userId:string, canSignIn:bool}`.
  Handler: `UserRepository::findByEmail`; нет юзера → `null`; есть → `UserAuthView`
  (`canSignIn = isActive()`).
- Хендлеры резолвятся контейнером, отдельный бутлоадер не требуется (как в Media).
- Переводы `app/locale/{ru,en}/user.php`: `app.user.email_taken`, `app.user.nickname_taken`.

Результат: `User/Application` отдаёт `CreateUser`/`FindUserForAuth`; Domain `User` не изменён.

Сценарии тестирования:
- CreateUser: успех (создан Active, вернулся UserId); email занят → 422; ник занят → 422;
  ник зарезервирован → 422; вложенный вызов с ошибкой → внешняя транзакция откатывается.
- FindUserForAuth: активный (canSignIn=true), Banned/Deleted (canSignIn=false), отсутствует (null).

Проверка: `make test` (новые unit/kernel-тесты `User/Application`), `make phpstan`.

### 2. Auth Domain: VO, enum, сущности, репозитории, typecast, миграция

Цель: доменная модель и доступ к данным трёх таблиц.

Что сделать:
- VO `Auth/Domain/ValueObject/`: `LoginCodeId`, `RegistrationTicketId`, `AuthTokenId`,
  `SessionId` (все `extends AbstractUuidV7Id`); `EmailAddress` (норм. идентична `User\Email`);
  `SecretHash` (`fromString`/`value` — HMAC-строка); `TokenHash`
  (`fromRawToken(string)`→sha256, `fromString`, `value`; конвенция typecast возьмёт `fromString`);
  `CodeAttempts` (`initial()`, `fromInt(int)`, `increment()`, `isExhausted()` ≥5, `value():int`
  — `fromInt` обязателен для `ValueObjectCast`); `LoginCodeValue` (валидация 6 цифр, `value()` —
  транзиентный, не хранится); `Expiration` (`after(now,seconds)`, `value():DateTimeImmutable`,
  `isExpired(now)`); `Consumption` (`notConsumed()`/`at(now)`, `isConsumed()`, `value():?DateTimeImmutable`).
- Enum `Auth/Domain/Enum/AuthTokenType` (`Access='access'`, `Refresh='refresh'`).
- Коллекция `Auth/Domain/Collection/AuthTokenCollection extends Illuminate\Support\Collection`
  (`@extends Collection<int, AuthToken>`).
- Сущности `Auth/Domain/Entity/`:
  - `LoginCode` (`issue(LoginCodeId, EmailAddress, SecretHash codeHash, Expiration, now)`;
    методы `registerFailedAttempt()`, `consume(now)`; predicate `isExpired(now)`,
    `attemptsExhausted()`, `isConsumed()`).
  - `RegistrationTicket` (`issue(...)`, `consume(now)`, `isExpired(now)`, `isConsumed()`; доступ
    к email для регистрации — property hook/предикат `email`/`emailAddress`).
  - `AuthToken` (`issue(AuthTokenId, UserId, SessionId, AuthTokenType, TokenHash, Expiration)`,
    `isExpired(now)`, `isAccess()`/`isRefresh()`).
  Все — `typecast: [Typecast::class, ValueObjectCast::class]`, колонки uuid/string/int — по
  конвенции (`CodeAttempts` через `fromInt`/`value`), `expires_at`/`consumed_at` — отдельные
  `ColumnValueTypecast` (`Auth/Infrastructure/Cycle/`: `ExpirationTypecast` datetime,
  `ConsumptionTypecast` nullable↔null-object, uncast `notConsumed()`→NULL), `HasTimestamps`.
- Репозитории `Auth/Repository/` (read-only, доменные методы; `forUpdate` — в цепочке
  `select()`):
  - `LoginCodeRepository::findActiveByEmailForUpdate(EmailAddress): ?LoginCode`
    (consumed_at IS NULL, `->forUpdate()`), `findLatestByEmail(EmailAddress): ?LoginCode`
    (для троттлинга 60с).
  - `RegistrationTicketRepository::findActiveByHashForUpdate(SecretHash): ?RegistrationTicket`.
  - `AuthTokenRepository::findByHash(TokenHash): ?AuthToken` (обычное чтение — для `load`),
    `findByHashForUpdate(TokenHash): ?AuthToken` (для rotate), `findBySessionId(SessionId):
    AuthTokenCollection`, `findByUserId(UserId): AuthTokenCollection`.
- Миграция `create_auth_domain_tables` (три таблицы; индексы email/user_id/session_id;
  `token_hash` UNIQUE).

Результат: сущности гидрируются/сохраняются, миграция применяется и откатывается.

Сценарии тестирования: VO (валидация формата кода/email, `CodeAttempts` increment/isExhausted/
fromInt, `Expiration::isExpired`, `Consumption` null-object); **инвариант: `EmailAddress::fromString(x)
->value()` === нормализация `User\Email` для тех же входов**; сущности (issue/consume/attempt);
репозитории (find* на тестовой БД, forUpdate-чтение); миграция up/down; `token_hash` unique.

Проверка: `make test` (unit VO/Entity + feature-репозитории), `make phpstan`.

### 3. Auth Infrastructure: токены, хэширование, actor provider

Цель: технические сервисы — генерация/хранение токенов, HMAC, провайдер актора.

Что сделать:
- `Auth/Application/Contract/`: `SecretHasherContract` (`hash(string):string`,
  `verify(string $secret, string $hash):bool` constant-time), `TokenGeneratorContract`
  (`generate():string` — 32 случайных байта → base64url), `AuthTokenStorageContract`
  (`issuePair(UserId):IssuedTokenPair`, `rotate(string $refreshRaw):IssuedTokenPair`,
  `revokeSession(SessionId):void`).
- `Auth/Application/Dto/IssuedTokenPair{accessToken, refreshToken, expiresIn:int}`.
- `Auth/Infrastructure/Hash/HmacSecretHasher` (HMAC-SHA256 с ключом
  `EncryptionInterface::getKey()`; `verify` через `hash_equals`).
- `Auth/Infrastructure/Auth/RandomTokenGenerator` (`random_bytes(32)`).
- `Auth/Infrastructure/Auth/AuthTokenView implements TokenInterface` — адаптер: хранит
  исходный raw (`getID()` возвращает его), `getPayload()` = `{userID, type, sessionID}`,
  `getExpiresAt()`.
- `Auth/Infrastructure/Auth/CycleTokenStorage` — реализует `Spiral\Auth\TokenStorageInterface`
  И `AuthTokenStorageContract`:
  - `load(string $id): ?TokenInterface` — `TokenHash::fromRawToken`, `findByHash` (БЕЗ forUpdate),
    не истёк → `AuthTokenView` (getID() = $id); просрочен/нет → null.
  - `create(array $payload, ?expiresAt)` — генерит raw, строит `AuthToken::issue` из payload,
    `persist`; возвращает `AuthTokenView` (getID() = raw).
  - `delete(TokenInterface)` — `findByHashForUpdate` → `entityManager->delete`.
  - `issuePair(UserId)` — `SessionId::generate`, `create` access (TTL 1ч) + refresh (TTL 60д)
    с общим sessionId, `run`, вернуть `IssuedTokenPair` (raw-строки + expiresIn=3600).
  - `rotate(refreshRaw)` — `findByHashForUpdate`, проверка type=refresh и срока (иначе
    `AuthenticationException` 401), `revokeSession(sessionId)`, `issuePair(userId)`.
  - `revokeSession(SessionId)` — `findBySessionId` → delete всех строк → `run`.
- `Auth/Infrastructure/Auth/UserActorProvider implements ActorProviderInterface`:
  `getActor(TokenInterface)` — только `payload.type==='access'` → `AuthenticatedUser(UserId)`
  (`Auth/Infrastructure/Auth/AuthenticatedUser`); иначе null.

Результат: токены выдаются/проверяются/ротируются/отзываются; refresh как Bearer не даёт актора;
`load` не берёт лишних блокировок.

Сценарии тестирования: HMAC hash/verify (constant-time, неверный → false); генератор
(длина/уникальность); CycleTokenStorage `issuePair`/`load`(access ok, истёкший access → null,
refresh→null для актора)/`rotate`(старая пара удалена, новая выдана, старый refresh→null)/
`revokeSession`; `AuthTokenView::getID()` === поданный raw; `UserActorProvider` (access→actor,
refresh→null).

Проверка: `make test` (feature на тестовой БД), `make phpstan`.

### 4. Auth Application: сценарии CQRS + outbox-сообщение

Цель: use-case-ы записи и постановка письма в outbox.

Что сделать (каждый use-case — папка `Auth/Application/Command/Auth/{Action}/` с
`*Command`, `*Handler`, `#[LogOperation]`, при необходимости `*Result`):
- `RequestLoginCode` (`#[Transactional]`) — язык письма = `requestLocale` из команды; в
  `User/Application` НЕ ходит. Троттлинг 60с/email (`findLatestByEmail`), гашение прежнего
  активного кода, генерация 6-значного кода, `LoginCode::issue` (codeHash =
  `SecretHasher::hash(code)`), `OutboxEventStore::add(new LoginCodeRequested{email, code,
  locale})`, `persist`+`run`. Результат — всегда успех (200).
- `ResolveLoginCode` (`#[Transactional]`) — `findActiveByEmailForUpdate`; маппинг в
  `LoginCodeResolution{outcome: LoginCodeOutcome, tokens:?IssuedTokenPair, registrationTicket:?string}`:
  null→NoCode; истёк→Expired; `attemptsExhausted`→Exhausted; `SecretHasher::verify` false →
  `registerFailedAttempt()`+persist+run → Wrong; ok → `consume`; `FindUserForAuth`:
  есть+canSignIn → `AuthTokenStorage::issuePair` → Verified+tokens; есть+!canSignIn → NotAllowed;
  нет → `RegistrationTicket::issue` → NeedsProfile+ticket. `run` финально.
- `VerifyLoginCode` (оркестратор, БЕЗ `#[Transactional]`) — диспатчит `ResolveLoginCode`;
  outcome ∈ {NoCode,Expired,Exhausted,Wrong,NotAllowed} → `AuthenticationException` (401,
  ключи `app.auth.invalid_code` / `app.auth.sign_in_not_allowed`); Verified → `VerifyLoginCodeResult`
  с токенами (needsProfile=false); NeedsProfile → результат с ticket (needsProfile=true).
- `CompleteRegistration` (`#[Transactional]`) — `findActiveByHashForUpdate(HMAC(ticket))`;
  нет/истёк → 401; `consume`; читает email из талона; диспатчит `User/Application CreateUser(
  email, name, nickname, requestLocale)` → `UserId`; `AuthTokenStorage::issuePair`; `run`.
- `RefreshTokens` (`#[Transactional]`) — `AuthTokenStorage::rotate(refreshToken)`.
- `Logout` (`#[Transactional]`) — `AuthTokenStorage::revokeSession(SessionId::fromString(authSessionId))`.
- `Auth/Application/Message/LoginCodeRequested implements OutboxMessage`
  (`public string $email; public string $code; public string $locale;`).

Результат: бизнес-сценарии собраны; `attempts++` на неверном коде коммитится; письмо ставится
в outbox в одной транзакции с кодом.

Сценарии тестирования (хендлеры; дублёры контрактов и User/Application): request (outbox получил
сообщение с локалью запроса; троттлинг 60с; в User/Application не ходим); resolve/verify (успех
существующего → пара; новый → талон; **неверный код → attempts++ сохранён И 401** (проверка, что
инкремент пережил throw); истёк/исчерпан → 401; attempts ровно =5 — граница; Banned → 401);
register (успех Active; талон истёк → 401; ник занят → 422 и талон не погашен); refresh (успех;
невалидный/чужой → 401); logout (отзыв сессии).

Проверка: `make test`, `make phpstan`.

### 5. Доставка письма: Job + Mailer + переводы

Цель: потребитель outbox шлёт письмо с кодом на нужном языке.

Что сделать:
- `Auth/Application/Command/Auth/SendLoginCode/`: `SendLoginCodeCommand{email, code, locale}`,
  `SendLoginCodeHandler` — формирует и отправляет письмо через Spiral Mailer (тема/тело из
  переводов по `locale` с fallback `LocaleConfig.default`; код в теле). Без `#[Transactional]`.
- `Auth/Presentation/Job/SendLoginCodeJob extends JobHandler` — `invoke(OutboxQueueEnvelope,
  ..., OutboxMessageLoaderContract, CommandBusInterface, SendLoginCodeHandler, LoggerInterface)`:
  грузит `LoginCodeRequested`, диспатчит `SendLoginCodeCommand` (эталон `ProcessMediaJob`);
  сбой Mailer → WARN + RetryException.
- Переводы `app/locale/{ru,en}/auth.php`: тема и текст письма с плейсхолдером кода + ключи
  ошибок входа (`app.auth.invalid_code`, `app.auth.sign_in_not_allowed`,
  `app.auth.invalid_ticket`, `app.auth.invalid_refresh`).
- Регистрация пары `LoginCodeRequested → SendLoginCodeJob` — в `AuthBootloader` (фаза 7).

Результат: при relay письмо с кодом уходит в Mailpit на языке запроса.

Сценарии тестирования: Job грузит сообщение и диспатчит команду; `SendLoginCodeHandler` строит
письмо с кодом и локалью (через тестовый mailer/transport); сбой Mailer → WARN+retry; ключи
переводов есть для ru и en.

Проверка: `make test` (feature на Job/Mailer), `make phpstan`.

### 6. Shared: rate limit (per-route middleware + 429)

Цель: переиспользуемое ограничение частоты по IP+маршрут, без правки общих интерсепторов.

Что сделать:
- `Shared/Infrastructure/Framework/Middleware/RateLimitMiddleware implements MiddlewareInterface`
  — конструктор `(int $maxAttempts, int $perSeconds, CacheInterface $cache, TranslatorInterface)`;
  `process()`: ключ = client IP (`$request->getServerParams()['REMOTE_ADDR']`) + имя маршрута/пути;
  читает/инкрементит счётчик фиксированного окна в Redis (PSR-16, TTL=perSeconds на первом
  инкременте). Превышение → возвращает JSON-ответ 429
  `{"message": <trans app.shared.rate_limit_exceeded>, "code":429}`; иначе `$handler->handle()`.
- Переводы `app/locale/{ru,en}/shared.php`: `app.shared.rate_limit_exceeded`.
- Применение — per-route через `#[Route(middleware: [new Autowire(RateLimitMiddleware::class,
  ['maxAttempts'=>N,'perSeconds'=>M])])]` (в фазе 7 на маршрутах Auth).

Результат: маршрут с навешенным `RateLimitMiddleware` ограничивается по IP; превышение → 429 JSON.
Общий `AppBootloader::INTERCEPTORS` не меняется (нет регрессии порядка интерсепторов).

Сценарии тестирования: в пределах лимита проходит; при превышении → 429 с корректным телом;
ключ зависит от IP и маршрута; TTL сбрасывает окно; HTTP-тест 429 на тестовом маршруте.

Проверка: `make test`, `make phpstan`.

### 7. Auth Presentation + каркас + интеграционные тесты маршрутов

Цель: HTTP-вход, подключение auth-каркаса и сквозные тесты всех маршрутов.

Что сделать:
- Filter `Auth/Presentation/Http/Filter/`: `RequestCodeFilter{email}`,
  `VerifyCodeFilter{email, code}`, `RegisterFilter{ticket, name, nickname}`,
  `RefreshFilter{refreshToken}` (обязательные поля без дефолтов, `#[Assert\NotBlank]`,
  `code` — `#[Assert\Regex]` на 6 цифр).
- Resource `Auth/Presentation/Http/Resource/`: `TokenPairResource`, `VerifyResultResource`
  (nullable `tokens`/`registrationTicket`), `RequestCodeResultResource`, `LogoutResultResource`
  (наследуют `AbstractResource`).
- `Auth/Presentation/Http/Controller/AuthController` — 5 тонких методов, `group:'api'`,
  `@return DataResponse<...>` PHPDoc на каждом; `code/request`, `code/verify`, `register`,
  `refresh` несут per-route `middleware: [new Autowire(RateLimitMiddleware::class, [...])]`;
  `code/request` и `register` кладут в команду `requestLocale` = `$translator->getLocale()`
  (fallback `LocaleConfig.default`); `logout` — per-route `middleware:
  [AuthTransportWithStorageMiddleware(header,cycle через Autowire), AuthContextAttributeMiddleware,
  RequireAuthenticatedMiddleware]` и читает `#[Attribute('authUserId')]`, `#[Attribute('authSessionId')]`.
  Методы строят Command/диспатчат через `CommandBusInterface`, маппят в Resource/`DataResponse`.
- Middleware `Auth/Presentation/Http/Middleware/`:
  `AuthContextAttributeMiddleware` (инжектит `AuthContextInterface` конструктором (Proxy,
  http-scope); если actor есть — ставит request-атрибуты `authUserId`=payload.userID,
  `authSessionId`=payload.sessionID), `RequireAuthenticatedMiddleware` (нет actor →
  `AuthenticationException` 401).
- `Auth/Infrastructure/Bootloader/AuthBootloader`:
  - `BINDINGS`: `SecretHasherContract→HmacSecretHasher`, `TokenGeneratorContract→RandomTokenGenerator`,
    `AuthTokenStorageContract→CycleTokenStorage`.
  - `defineDependencies(): [HttpAuthBootloader::class, SpiralAuthBootloader::class]` (порядок init).
  - `init(HttpAuthBootloader $httpAuth, SpiralAuthBootloader $auth)` — alias
    `use Spiral\Bootloader\Auth\AuthBootloader as SpiralAuthBootloader` (иначе конфликт с этим же
    классом): `$httpAuth->addTransport('header', new HeaderTransport('Authorization','Bearer %s'))`,
    `$httpAuth->addTokenStorage('cycle', CycleTokenStorage::class)`,
    `$auth->addActorProvider(UserActorProvider::class)`.
  - `boot(OutboxJobRegistryContract)`: `register(LoginCodeRequested::class, SendLoginCodeJob::class)`.
- `Kernel::defineBootloaders()`: добавить `Spiral\Bootloader\Auth\HttpAuthBootloader::class`
  (тянет vendor `AuthBootloader`) и `App\Modules\Auth\Infrastructure\Bootloader\AuthBootloader::class`.
- Проверить, что аннотированные `#[Route]` сканируются (по System-контроллерам); если нет —
  добавить `Spiral\Router\Bootloader\AnnotatedRoutesBootloader::class`.
- OpenAPI: прогнать `php app.php openapi:generate`, проверить `public/openapi/openapi.yml`
  (в т.ч. корректность схемы `VerifyResultResource` с nullable-полями).

Результат: все 5 маршрутов отвечают по контракту; Bearer-аутентификация (на logout) и rate limit
активны; группа `api` и общие интерсепторы не тронуты.

Сценарии тестирования (интеграционные HTTP по эталону `ApiErrorHttpTest`, `fakeHttp()`):
- `code/request`: 200 на новый и существующий email; письмо ушло в outbox; 422 на пустой email.
- `code/verify`: новый email → `needsProfile:true` + ticket; существующий → пара токенов;
  неверный код → 401 и attempts увеличился; повтор после исчерпания attempts (=5) → 401.
- `register`: валидный ticket → пара токенов и пользователь Active; занятый ник → 422 (талон
  остаётся валидным для повтора); истёкший/неверный ticket → 401.
- `refresh`: валидный refresh → новая пара, старый refresh больше не работает (401);
  access как refresh → 401.
- `logout`: с Bearer access → 200 и токены сессии отозваны (тот же access потом → 401);
  без токена → 401; refresh как Bearer → 401; повторный logout тем же access → 401.
- rate limit: превышение лимита на `code/request` → 429.
- регрессия: существующие System api-тесты (`/test/api/...`) проходят (группа `api` не менялась).
- kernel-тест: биндинги `AuthBootloader` (`SecretHasherContract`/`TokenGeneratorContract`/
  `AuthTokenStorageContract`) и регистрация транспорта/хранилища/actor-provider резолвятся.

Проверка: полный `make test` (все unit/feature, интеграция всех маршрутов, 100% покрытие),
`make phpstan`, `php app.php openapi:generate`.

## Тесты

Стратегия `after_each_phase`: в конце каждой фазы добавляются и прогоняются тесты на её
артефакты (`make test`), фаза не считается готовой без зелёного прогона. Unit — для VO,
enum, сущностей, чистой логики хендлеров (дублёры контрактов: `createStub`+`willReturn*`
для возвратов, `createMock`+`expects` — только где проверяем факт вызова). Feature —
репозитории (включая forUpdate), `CycleTokenStorage`, Job/Mailer, kernel-тесты биндингов.
Интеграционные HTTP (`fakeHttp()`) — каждый маршрут с позитивными и негативными ветками
(обязательно по rules.md), плюс регрессия System api-тестов. Особо покрыть: `attempts++`
переживает 401, граница attempts=5, истёкший access в `load`, повторный logout, отзыв сессии,
эквивалентность нормализации `EmailAddress` и `User\Email`. Итог фазы 7 — 100% покрытие.

## Логирование

Стратегия `debug_precise`: `#[LogOperation]` на Command-хендлерах (debug-лог старта и времени).
Точечные DEBUG-логи ключевых шагов с контекстом (email, sessionId, userId, причина): код
запрошен/выдан, попытка кода неверна, код подтверждён, талон создан/погашен, регистрация
завершена, пара выдана/ротирована/сессия отозвана, rate limit сработал. INFO — успешная
регистрация (ключевое бизнес-событие). WARN — только инфраструктурные сбои (ошибка отправки
письма в `SendLoginCodeJob`). Неверный/истёкший код, невалидный токен, исчерпанные попытки —
нормальный пользовательский flow → DEBUG (rules.md), не WARN. Сырые коды и токены в логи не
пишутся (логируются email и идентификаторы).

## Документация и эксплуатация

- `.env`/`.env.sample`: заданы `ENCRYPTER_KEY` (используется как HMAC-секрет), `MAILER_DSN`
  (Mailpit в dev), очередь RabbitMQ — переменные уже есть, новых не требуется.
- `AUTH_TOKEN_TRANSPORT`/`AUTH_TOKEN_STORAGE` не задаём: транспорт/хранилище привязаны явно в
  per-route middleware `logout` (`header`/`cycle`), дефолты vendor (`cookie`/`session`) не влияют.
- Эксплуатация: `outbox:relay --loop` (один экземпляр) — письма с кодом идут через него; payload
  содержит код открытым текстом (короткоживущая запись, обрабатывать сразу). Проверить Mailpit.
- OpenAPI: `public/openapi/openapi.yml` обновить `openapi:generate`; маршруты Auth появятся
  в Swagger `/api/docs`.
- Переводы: добавлены `app/locale/{ru,en}/auth.php`, ключи в `user.php` и `shared.php`.

exec
/bin/zsh -lc "sed -n '1,260p' docs/code-examples.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
# Примеры кода

## DTO команды

```php
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\Auth\Login;

final readonly class LoginCommand
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}
}
```

## Обработчик команды

```php
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\Auth\Login;

use App\Shared\Domain\Exception\AuthenticationException;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Repository\UserRepository;

final readonly class LoginHandler
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    public function handle(LoginCommand $command): LoginResult
    {
        $user = $this->userRepository->findByEmail(Email::from($command->email))
            ?? throw new AuthenticationException('Неверный email или пароль');

        if (!$user->passwordHash->verify($command->password)) {
            throw new AuthenticationException('Неверный email или пароль');
        }

        return new LoginResult(
            accessToken: $user->issueAccessToken(),
            refreshToken: $user->issueRefreshToken(),
        );
    }
}
```

## DTO запроса

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Query\User\GetUserProfile;

final readonly class GetUserProfileQuery
{
    public function __construct(
        public string $userId,
    ) {}
}
```

## Typed config

```php
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Payment;

use App\Shared\Infrastructure\Configuration\TypedConfig;

final readonly class PaymentConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'payment';
    }

    /**
     * @param array<string, PaymentProviderConfig> $providers
     */
    public function __construct(
        public string $default,
        public array $providers,
    ) {}
}
```

```php
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Payment;

final readonly class PaymentProviderConfig
{
    public function __construct(
        public string $dsn,
        public bool $sandbox,
    ) {}
}
```

Тест на `Tests\TestCase` поднимает Spiral kernel, поэтому живёт в suite `Kernel`
(`tests/Kernel`), а не в лёгком `Unit`:

```php
<?php

declare(strict_types=1);

namespace Tests\Kernel\Shared\Infrastructure\Configuration;

use App\Shared\Infrastructure\Configuration\Mapping\ConfigMapper;
use App\Shared\Infrastructure\Configuration\Payment\PaymentConfig;
use Tests\TestCase;

final class PaymentConfigTest extends TestCase
{
    public function testPaymentConfigMapsFromConfigurator(): void
    {
        $config = $this->getContainer()
            ->get(ConfigMapper::class)
            ->map(
                section: PaymentConfig::configName(),
                targetClass: PaymentConfig::class,
            );

        self::assertSame('stripe', $config->default);
        self::assertArrayHasKey('stripe', $config->providers);
    }
}
```

## Обработчик запроса

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Query\User\GetUserProfile;

use App\Modules\User\Domain\Entity\User;
use App\Shared\Domain\Exception\NotFoundException;
use App\Modules\User\Repository\UserRepository;

final readonly class GetUserProfileHandler
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    public function handle(GetUserProfileQuery $query): User
    {
        return $this->userRepository->findById($query->userId)
            ?? throw new NotFoundException('Пользователь не найден');
    }
}
```

## Контроллер

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Presentation\Http\Controller;

use App\Modules\User\Application\Query\User\GetUserProfile\GetUserProfileHandler;
use App\Modules\User\Application\Query\User\GetUserProfile\GetUserProfileQuery;
use App\Modules\User\Presentation\Http\Resource\UserResource;
use GianTiaga\SpiralOpenApi\Response\DataResponse;
use GianTiaga\SpiralCqrs\QueryBusInterface;
use Spiral\Router\Annotation\Route;

final readonly class UserController
{
    /**
     * @return DataResponse<UserResource>
     */
    #[Route(route: '/api/v1/users/<id>', name: 'api.v1.user.show', methods: ['GET'])]
    public function show(
        string $id,
        GetUserProfileHandler $getUserProfileHandler,
        QueryBusInterface $queryBus,
    ): DataResponse {
        $query = new GetUserProfileQuery(
            userId: $id,
        );

        $user = $queryBus->dispatch(
            query: $query,
            handler: $getUserProfileHandler->handle(...),
        );

        return new DataResponse(UserResource::fromEntity($user));
    }
}
```

## Сущность

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Entity;

use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;
use App\Modules\User\Domain\ValueObject\DisplayName;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\PasswordHash;
use App\Modules\User\Domain\ValueObject\Username;
use App\Modules\User\Infrastructure\Cycle\UserValueObjectTypecast;
use App\Modules\User\Repository\UserRepository;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'user',
    table: 'users',
    repository: UserRepository::class,
    typecast: [Typecast::class, UserValueObjectTypecast::class],
)]
class User
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: UserId::class)]
    public private(set) UserId $id;

    #[Column(type: 'string', typecast: Email::class)]
    public private(set) Email $email;

    #[Column(type: 'string', typecast: PasswordHash::class)]
    public private(set) PasswordHash $passwordHash;

    #[Column(type: 'string', typecast: Username::class)]
    public private(set) Username $username;

    #[Column(type: 'string', typecast: DisplayName::class)]

exec
/bin/zsh -lc 'find app/src/Shared/Infrastructure/Framework -maxdepth 3 -type f | sort' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Shared/Infrastructure/Framework/Bootloader/AppBootloader.php
app/src/Shared/Infrastructure/Framework/Bootloader/ConfigBootloader.php
app/src/Shared/Infrastructure/Framework/Bootloader/ExceptionHandlerBootloader.php
app/src/Shared/Infrastructure/Framework/Bootloader/LoggingBootloader.php
app/src/Shared/Infrastructure/Framework/Bootloader/OpenApiBootloader.php
app/src/Shared/Infrastructure/Framework/Bootloader/RoutesBootloader.php
app/src/Shared/Infrastructure/Framework/DirectoryAlias.php
app/src/Shared/Infrastructure/Framework/Kernel.php
app/src/Shared/Infrastructure/Framework/Middleware/LocaleMiddleware.php

exec
/bin/zsh -lc "rg --files app/src/Modules/User app/src/Modules/Media app/src/Modules/Outbox app/src/Shared app/src/Modules/System tests vendor/spiral/framework/src/Auth vendor/spiral/framework/src/AuthHttp app/database/migrations | sed -n '1,260p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/Outbox/Presentation/Console/OutboxRelayCommand.php
app/src/Modules/Outbox/Presentation/Job/OutboxDebugLogJob.php
app/src/Modules/User/Domain/Enum/UserStatus.php
app/src/Modules/User/Domain/Enum/UserVerification.php
app/src/Shared/Presentation/Http/Resource/AbstractResource.php
app/src/Modules/User/Domain/Entity/ReservedNickname.php
app/src/Modules/User/Domain/Entity/UserBan.php
app/src/Modules/User/Domain/Entity/User.php
app/src/Modules/Outbox/Domain/Enum/OutboxEventStatus.php
app/src/Modules/User/Domain/ValueObject/ReservedNicknameId.php
app/src/Modules/User/Domain/ValueObject/UserNickname.php
app/src/Modules/User/Domain/ValueObject/ReservedNicknameHolder.php
app/src/Modules/User/Domain/ValueObject/UserBio.php
app/src/Modules/User/Domain/ValueObject/UserAvatar.php
app/src/Modules/User/Domain/ValueObject/BanUnbannedAt.php
app/src/Modules/User/Domain/ValueObject/BanUnbannedBy.php
app/src/Modules/User/Domain/ValueObject/BanUnbannedReason.php
app/src/Modules/User/Domain/ValueObject/UserDeletion.php
app/src/Modules/User/Domain/ValueObject/UserName.php
app/src/Modules/User/Domain/ValueObject/UserSpiritualName.php
app/src/Modules/User/Domain/ValueObject/UserLocation.php
app/src/Modules/User/Domain/ValueObject/UserBanId.php
app/src/Modules/User/Domain/ValueObject/BanReason.php
app/src/Modules/User/Domain/ValueObject/BanExpiration.php
app/src/Modules/User/Domain/ValueObject/Email.php
app/src/Shared/Domain/Exception/InvalidDomainValueException.php
app/src/Shared/Domain/Exception/DomainTranslatableException.php
app/src/Shared/Domain/Exception/AuthenticationException.php
app/src/Shared/Domain/Exception/ValidationException.php
app/src/Shared/Domain/Exception/NotFoundException.php
app/src/Shared/Domain/Exception/ForbiddenException.php
tests/Kernel/Modules/Outbox/Presentation/Job/OutboxDebugLogJobTest.php
vendor/spiral/framework/src/Auth/src/Event/Logout.php
vendor/spiral/framework/src/Auth/src/Event/Authenticated.php
app/src/Modules/User/Infrastructure/Cycle/UserAvatarTypecast.php
app/src/Modules/User/Infrastructure/Cycle/UserBioTypecast.php
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php
app/src/Modules/User/Infrastructure/Cycle/UserSpiritualNameTypecast.php
app/src/Modules/User/Infrastructure/Cycle/UserLocationTypecast.php
app/src/Modules/User/Infrastructure/Cycle/ReservedNicknameHolderTypecast.php
app/src/Modules/User/Infrastructure/Cycle/BanUnbannedReasonTypecast.php
app/src/Modules/User/Infrastructure/Cycle/BanUnbannedByTypecast.php
app/src/Modules/User/Infrastructure/Cycle/BanExpirationTypecast.php
app/src/Modules/User/Infrastructure/Cycle/BanUnbannedAtTypecast.php
app/src/Modules/Outbox/Domain/Entity/StoredOutboxEvent.php
app/src/Modules/Outbox/Domain/Collection/OutboxEventCollection.php
app/src/Modules/User/Repository/UserRepository.php
app/src/Modules/User/Repository/UserBanRepository.php
app/src/Modules/User/Repository/ReservedNicknameRepository.php
app/src/Shared/Domain/Trait/HasTimestamps.php
app/src/Shared/Domain/Trait/ComparesDateTimeToMicroseconds.php
tests/Kernel/Shared/Infrastructure/Cycle/LazyGhostMapperExtractTest.php
tests/Kernel/Shared/Infrastructure/Cycle/LazyGhostEntityFactoryTest.php
app/src/Modules/System/Presentation/Console/OpenApiPublishAssetsCommand.php
app/src/Modules/System/Presentation/Console/OpenApiGenerateCommand.php
app/src/Modules/Outbox/Domain/ValueObject/OutboxMaxAttempts.php
app/src/Modules/Outbox/Domain/ValueObject/OutboxRelaySleepSeconds.php
app/src/Modules/Outbox/Domain/ValueObject/OutboxEventType.php
app/src/Modules/Outbox/Domain/ValueObject/OutboxEventId.php
app/src/Modules/Outbox/Domain/ValueObject/OutboxAttempts.php
app/src/Modules/Outbox/Domain/ValueObject/OutboxRelayBatchSize.php
app/src/Modules/Outbox/Domain/ValueObject/KnownOutboxEventDate.php
app/src/Modules/Outbox/Domain/ValueObject/EmptyOutboxEventDate.php
app/src/Modules/Outbox/Domain/ValueObject/OutboxEventDate.php
app/src/Modules/Outbox/Domain/ValueObject/OutboxAvailableAt.php
app/src/Modules/Outbox/Domain/ValueObject/OutboxLastError.php
app/src/Modules/Outbox/Domain/ValueObject/OutboxEventPayload.php
app/src/Shared/Domain/Enum/Locale.php
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php
app/database/migrations/20260613.143902_0_create_access_domain_tables.php
app/database/migrations/20260525.153700_0_create_outbox_events_table.php
app/database/migrations/20260613.143901_0_create_user_domain_tables.php
app/database/migrations/20260521.184100_0_create_media_domain_tables.php
app/src/Shared/Domain/ValueObject/UserId.php
app/src/Shared/Domain/ValueObject/AbstractUuidV7Id.php
app/src/Shared/Domain/ValueObject/AbstractIntegerValue.php
tests/Kernel/Shared/Infrastructure/Framework/Bootloader/ExceptionHandlerBootloaderTest.php
vendor/spiral/framework/src/Auth/src/Exception/TokenStorageException.php
vendor/spiral/framework/src/Auth/src/Exception/AuthException.php
vendor/spiral/framework/src/Auth/src/TokenInterface.php
vendor/spiral/framework/src/Auth/src/TokenStorageInterface.php
vendor/spiral/framework/src/Auth/src/ActorProviderInterface.php
vendor/spiral/framework/src/Auth/src/AuthContext.php
vendor/spiral/framework/src/Auth/src/TokenStorageProvider.php
vendor/spiral/framework/src/Auth/src/AuthContextInterface.php
vendor/spiral/framework/src/Auth/src/TokenStorageProviderInterface.php
vendor/spiral/framework/src/Auth/composer.json
vendor/spiral/framework/src/Auth/README.md
vendor/spiral/framework/src/Auth/LICENSE
tests/warmup.php
app/src/Modules/System/Presentation/Exception/OpenApiAssetsPublicationException.php
tests/Storage/FakeStorage.php
tests/bootstrap.php
tests/TestRuntime.php
app/src/Shared/Infrastructure/Exception/InvalidConfigValueException.php
app/src/Shared/Infrastructure/Exception/ConfigMappingException.php
tests/Kernel/Shared/Infrastructure/Configuration/CacheConfigBindingTest.php
tests/Kernel/Shared/Infrastructure/Configuration/ComplexConfigBindingTest.php
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php
tests/Kernel/Shared/Infrastructure/Configuration/LocaleConfigTest.php
tests/Kernel/Shared/Infrastructure/Configuration/SimpleConfigBindingTest.php
tests/Kernel/Shared/Infrastructure/Configuration/MediaStorageConfigTest.php
tests/Kernel/Shared/Infrastructure/Configuration/ConfigShapeTest.php
tests/Kernel/DemoTest.php
tests/TestCase.php
tests/DatabaseTestCase.php
vendor/spiral/framework/src/AuthHttp/src/Middleware/AuthTransportMiddleware.php
vendor/spiral/framework/src/AuthHttp/src/Middleware/AuthTransportWithStorageMiddleware.php
vendor/spiral/framework/src/AuthHttp/src/Middleware/AuthMiddleware.php
app/src/Modules/Outbox/Infrastructure/Exception/OutboxRelayStoppedException.php
app/src/Modules/Outbox/Infrastructure/Exception/OutboxJobRegistryException.php
app/src/Shared/Infrastructure/Cycle/ValueObjectCast.php
app/src/Shared/Infrastructure/Cycle/LazyGhostMapper.php
app/src/Shared/Infrastructure/Cycle/LazyGhostEntityFactory.php
app/src/Shared/Infrastructure/Cycle/LazyGhostReflectionRegistry.php
app/src/Shared/Infrastructure/Cycle/ColumnValueTypecast.php
app/src/Shared/Infrastructure/Cycle/LazyGhostPendingRelationReference.php
app/src/Shared/Infrastructure/Cycle/LazyGhostPendingRelationReferenceCollection.php
app/src/Modules/Media/Domain/Enum/MediaStatus.php
app/src/Modules/Media/Domain/Enum/MediaConversionStatus.php
app/src/Modules/Media/Domain/Enum/MediaStorage.php
app/src/Modules/Media/Domain/Enum/MediaType.php
app/src/Modules/Media/Domain/Enum/MediaVideoConversionType.php
app/src/Modules/Media/Domain/Enum/MediaVisibility.php
app/src/Modules/Media/Domain/Enum/MediaImageConversionType.php
app/src/Modules/Outbox/Infrastructure/Relay/SystemOutboxRelaySleeper.php
app/src/Modules/Outbox/Infrastructure/Relay/OutboxRelay.php
app/src/Modules/Outbox/Infrastructure/Relay/OutboxRelayWorker.php
app/src/Modules/Outbox/Infrastructure/Relay/InfiniteOutboxRelayLoopControl.php
app/src/Shared/Infrastructure/Framework/Kernel.php
app/src/Shared/Infrastructure/Framework/DirectoryAlias.php
vendor/spiral/framework/src/AuthHttp/src/Middleware/Firewall/OverwriteFirewall.php
vendor/spiral/framework/src/AuthHttp/src/Middleware/Firewall/AbstractFirewall.php
vendor/spiral/framework/src/AuthHttp/src/Middleware/Firewall/RedirectFirewall.php
vendor/spiral/framework/src/AuthHttp/src/Middleware/Firewall/ExceptionFirewall.php
vendor/spiral/framework/src/AuthHttp/composer.json
vendor/spiral/framework/src/AuthHttp/README.md
vendor/spiral/framework/src/AuthHttp/LICENSE
vendor/spiral/framework/src/AuthHttp/src/HttpTransportInterface.php
app/src/Shared/Infrastructure/Framework/Bootloader/RoutesBootloader.php
app/src/Shared/Infrastructure/Framework/Bootloader/ExceptionHandlerBootloader.php
app/src/Shared/Infrastructure/Framework/Bootloader/AppBootloader.php
app/src/Shared/Infrastructure/Framework/Bootloader/ConfigBootloader.php
app/src/Shared/Infrastructure/Framework/Bootloader/LoggingBootloader.php
app/src/Shared/Infrastructure/Framework/Bootloader/OpenApiBootloader.php
app/src/Modules/Outbox/Infrastructure/Registry/OutboxJobRegistry.php
vendor/spiral/framework/src/AuthHttp/src/Exception/TransportException.php
vendor/spiral/framework/src/AuthHttp/src/TransportRegistry.php
app/src/Shared/Infrastructure/Framework/Middleware/LocaleMiddleware.php
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueHeaders.php
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueSerializer.php
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueuePublisher.php
app/src/Modules/System/Presentation/Http/Resource/HealthResource.php
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php
app/src/Modules/Media/Domain/Entity/Media.php
app/src/Modules/Media/Domain/Entity/MediaImageConversion.php
app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php
tests/App/Bootloader/ApiErrorTestRoutesBootloader.php
vendor/spiral/framework/src/AuthHttp/src/Transport/HeaderTransport.php
app/src/Modules/Outbox/Infrastructure/Message/OutboxMessageLoader.php
vendor/spiral/framework/src/AuthHttp/src/Transport/CookieTransport.php
app/src/Modules/Outbox/Infrastructure/Message/ValinorOutboxMessageSerializer.php
app/src/Modules/Outbox/Infrastructure/Message/OutboxEventStore.php
tests/App/TestKernel.php
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php
tests/Feature/Modules/Media/Flow/Fixture/RecordingMediaLogger.php
tests/Feature/Modules/Media/Flow/Fixture/ThrowingProcessMediaCommandBus.php
app/src/Modules/System/Presentation/Http/View/SwaggerView.php
app/src/Modules/Outbox/Infrastructure/Cycle/OutboxAvailableAtTypecast.php
app/src/Modules/Outbox/Infrastructure/Cycle/OutboxLastErrorTypecast.php
app/src/Modules/Outbox/Infrastructure/Cycle/OutboxEventPayloadTypecast.php
app/src/Modules/Outbox/Infrastructure/Cycle/OutboxEventDateTypecast.php
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php
app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxConsoleBootloader.php
app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxBootloader.php
app/src/Modules/Media/Domain/Collection/MediaMimeTypeCollection.php
app/src/Modules/Media/Domain/Collection/MediaCollection.php
app/src/Modules/Media/Domain/Collection/MediaMultipartPartCollection.php
app/src/Modules/Media/Domain/Collection/MediaImageConversionCollection.php
app/src/Modules/Media/Domain/Collection/MediaVideoConversionCollection.php
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php
app/src/Modules/System/Presentation/Http/Controller/HealthController.php
app/src/Modules/System/Presentation/Http/Controller/SwaggerController.php
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php
tests/Feature/Modules/Outbox/Console/OutboxRelayCommandTest.php
tests/Unit/Modules/Media/Domain/Enum/MediaEnumTest.php
app/src/Modules/Outbox/Application/Exception/OutboxMessageLoadingException.php
app/src/Modules/Outbox/Application/Exception/OutboxMessageSerializationException.php
app/src/Modules/System/Presentation/Http/Enum/HealthStatus.php
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php
tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php
tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorFailureTest.php
tests/Feature/Modules/Outbox/Infrastructure/OutboxMessageLoaderTest.php
tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartUploadId.php
app/src/Modules/Media/Domain/ValueObject/MediaProcessingError.php
app/src/Modules/Media/Domain/ValueObject/MediaDuration.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPart.php
app/src/Modules/Media/Domain/ValueObject/MediaFileSize.php
app/src/Modules/Media/Domain/ValueObject/MediaStorageKey.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartETag.php
app/src/Modules/Media/Domain/ValueObject/MediaProcessingAttempts.php
app/src/Modules/Media/Domain/ValueObject/MediaVideoConversionId.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartUploadIdValue.php
app/src/Modules/Media/Domain/ValueObject/MediaBitrate.php
app/src/Modules/Media/Domain/ValueObject/MediaPixelDimension.php
app/src/Modules/Media/Domain/ValueObject/MediaExpiration.php
app/src/Modules/Media/Domain/ValueObject/MediaId.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartSize.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartsCount.php
app/src/Modules/Media/Domain/ValueObject/MediaPresignedTtl.php
app/src/Modules/Media/Domain/ValueObject/MediaPath.php
app/src/Modules/Media/Domain/ValueObject/MediaImageConversionId.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartNumber.php
app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php
tests/Feature/Modules/System/Console/OpenApiPublishAssetsCommandTest.php
tests/Feature/Modules/System/Console/OpenApiGenerateCommandTest.php
app/src/Modules/Outbox/Application/Command/RelayOutbox/RelayOutboxHandler.php
app/src/Modules/Outbox/Application/Command/RelayOutbox/RelayOutboxCommand.php
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/UnregisteredOutboxRelayMessage.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/RetryingOutboxRelayJob.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/FailingOutboxRelayMessage.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/QueueStatusDebugLogJobCore.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/RecordingOutboxLogger.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/MarkFinalThenThrowQueueStatusCore.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/OutboxQueueStatusInterceptorTestHelpers.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/QueueStatusTestCore.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/ThrowingQueue.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/ThrowingQueueConnectionProvider.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/MarkHandledDuringPushQueueConnectionProvider.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/FailingOutboxRelayJob.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/OutboxRelayTestHelpers.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/RetryingOutboxRelayMessage.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/MarkHandledDuringPushQueue.php
tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorTest.php
tests/Feature/Modules/System/Http/ApiErrorHttpTest.php
tests/Feature/Modules/System/Http/OpenApiHttpTest.php
tests/Feature/Modules/System/Http/LocaleHttpTest.php
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php
tests/Feature/Modules/Outbox/Application/OutboxEventStoreTransactionTest.php
tests/Feature/Modules/Outbox/CleansOutboxEvents.php
app/src/Shared/Infrastructure/Cache/RedisCacheStorage.php
app/src/Modules/Outbox/Application/Command/ProcessDebugLogMessage/ProcessOutboxDebugLogMessageHandler.php
app/src/Modules/Outbox/Application/Command/ProcessDebugLogMessage/ProcessOutboxDebugLogMessageCommand.php
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php
app/src/Modules/System/Presentation/Temporal/Ping.php
tests/Feature/Modules/User/Repository/UserRepositoryTest.php

codex
Нашёл важное отличие от исходного исследования: финальный план уже отказался от атрибута rate limit и от группы auth на весь `api`, то есть часть старых рисков снята. Теперь проверяю, не появились ли новые: порядок middleware, вложенные транзакции, реальные методы Cycle и Spiral Auth.
exec
/bin/zsh -lc "nl -ba app/src/Modules/User/Domain/Entity/User.php | sed -n '1,230p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\User\Domain\Entity;
     6	
     7	use App\Modules\User\Domain\Enum\UserStatus;
     8	use App\Modules\User\Domain\Enum\UserVerification;
     9	use App\Modules\User\Domain\ValueObject\Email;
    10	use App\Modules\User\Domain\ValueObject\UserAvatar;
    11	use App\Modules\User\Domain\ValueObject\UserBio;
    12	use App\Modules\User\Domain\ValueObject\UserDeletion;
    13	use App\Modules\User\Domain\ValueObject\UserLocation;
    14	use App\Modules\User\Domain\ValueObject\UserName;
    15	use App\Modules\User\Domain\ValueObject\UserNickname;
    16	use App\Modules\User\Domain\ValueObject\UserSpiritualName;
    17	use App\Modules\User\Infrastructure\Cycle\UserAvatarTypecast;
    18	use App\Modules\User\Infrastructure\Cycle\UserBioTypecast;
    19	use App\Modules\User\Infrastructure\Cycle\UserDeletionTypecast;
    20	use App\Modules\User\Infrastructure\Cycle\UserLocationTypecast;
    21	use App\Modules\User\Infrastructure\Cycle\UserSpiritualNameTypecast;
    22	use App\Modules\User\Repository\UserRepository;
    23	use App\Shared\Domain\Enum\Locale;
    24	use App\Shared\Domain\Trait\HasTimestamps;
    25	use App\Shared\Domain\ValueObject\UserId;
    26	use App\Shared\Infrastructure\Cycle\ValueObjectCast;
    27	use Cycle\Annotated\Annotation\Column;
    28	use Cycle\Annotated\Annotation\Entity;
    29	use Cycle\ORM\Parser\Typecast;
    30	
    31	#[Entity(
    32	    role: 'user',
    33	    table: 'users',
    34	    repository: UserRepository::class,
    35	    typecast: [Typecast::class, ValueObjectCast::class],
    36	)]
    37	final class User
    38	{
    39	    use HasTimestamps;
    40	
    41	    #[Column(type: 'uuid', primary: true, typecast: UserId::class)]
    42	    public private(set) UserId $id;
    43	
    44	    #[Column(type: 'string(100)', typecast: UserName::class)]
    45	    public private(set) UserName $name;
    46	
    47	    #[Column(type: 'string(100)', name: 'spiritual_name', nullable: true, typecast: UserSpiritualNameTypecast::class)]
    48	    public private(set) UserSpiritualName $spiritualName;
    49	
    50	    #[Column(type: 'text', nullable: true, typecast: UserBioTypecast::class)]
    51	    public private(set) UserBio $bio;
    52	
    53	    #[Column(type: 'string(100)', nullable: true, typecast: UserLocationTypecast::class)]
    54	    public private(set) UserLocation $location;
    55	
    56	    #[Column(type: 'string(254)', typecast: Email::class)]
    57	    public private(set) Email $email;
    58	
    59	    #[Column(type: 'string(30)', typecast: UserNickname::class)]
    60	    public private(set) UserNickname $nickname;
    61	
    62	    #[Column(type: 'uuid', name: 'avatar_media_id', nullable: true, typecast: UserAvatarTypecast::class)]
    63	    public private(set) UserAvatar $avatar;
    64	
    65	    #[Column(type: 'string(32)', typecast: UserVerification::class)]
    66	    public private(set) UserVerification $verification;
    67	
    68	    #[Column(type: 'string(32)', typecast: UserStatus::class)]
    69	    public private(set) UserStatus $status;
    70	
    71	    #[Column(type: 'string(8)', typecast: Locale::class)]
    72	    public private(set) Locale $locale;
    73	
    74	    #[Column(type: 'datetime', name: 'deleted_at', nullable: true, typecast: UserDeletionTypecast::class)]
    75	    public private(set) UserDeletion $deletion;
    76	
    77	    public static function create(
    78	        UserName $name,
    79	        Email $email,
    80	        UserNickname $nickname,
    81	        Locale $locale,
    82	    ): self {
    83	        $user = new self();
    84	        $user->id = UserId::generate();
    85	        $user->name = $name;
    86	        $user->spiritualName = UserSpiritualName::none();
    87	        $user->bio = UserBio::none();
    88	        $user->location = UserLocation::none();
    89	        $user->email = $email;
    90	        $user->nickname = $nickname;
    91	        $user->avatar = UserAvatar::none();
    92	        $user->verification = UserVerification::Unverified;
    93	        $user->status = UserStatus::WaitingEmailConfirmation;
    94	        $user->locale = $locale;
    95	        $user->deletion = UserDeletion::active();
    96	        $user->initializeTimestamps();
    97	
    98	        return $user;
    99	    }
   100	
   101	    public function confirmEmail(): void
   102	    {
   103	        $this->status = UserStatus::Active;
   104	        $this->touch();
   105	    }
   106	
   107	    public function changeEmail(Email $email): void
   108	    {
   109	        $this->email = $email;
   110	        $this->status = UserStatus::WaitingEmailConfirmation;
   111	        $this->touch();
   112	    }
   113	
   114	    public function changeNickname(UserNickname $nickname): void
   115	    {
   116	        $this->nickname = $nickname;
   117	        $this->touch();
   118	    }
   119	
   120	    public function rename(UserName $name): void
   121	    {
   122	        $this->name = $name;
   123	        $this->touch();
   124	    }
   125	
   126	    public function changeSpiritualName(UserSpiritualName $spiritualName): void
   127	    {
   128	        $this->spiritualName = $spiritualName;
   129	        $this->touch();
   130	    }
   131	
   132	    public function changeBio(UserBio $bio): void
   133	    {
   134	        $this->bio = $bio;
   135	        $this->touch();
   136	    }
   137	
   138	    public function changeLocation(UserLocation $location): void
   139	    {
   140	        $this->location = $location;
   141	        $this->touch();
   142	    }
   143	
   144	    public function changeLocale(Locale $locale): void
   145	    {
   146	        $this->locale = $locale;
   147	        $this->touch();
   148	    }
   149	
   150	    public function setAvatar(UserAvatar $avatar): void
   151	    {
   152	        $this->avatar = $avatar;
   153	        $this->touch();
   154	    }
   155	
   156	    public function removeAvatar(): void
   157	    {
   158	        $this->avatar = UserAvatar::none();
   159	        $this->touch();
   160	    }
   161	
   162	    public function verify(): void
   163	    {
   164	        $this->verification = UserVerification::Verified;
   165	        $this->touch();
   166	    }
   167	
   168	    public function unverify(): void
   169	    {
   170	        $this->verification = UserVerification::Unverified;
   171	        $this->touch();
   172	    }
   173	
   174	    public function ban(): void
   175	    {
   176	        $this->status = UserStatus::Banned;
   177	        $this->touch();
   178	    }
   179	
   180	    public function unban(): void
   181	    {
   182	        $this->status = UserStatus::Active;
   183	        $this->touch();
   184	    }
   185	
   186	    public function markDeleted(\DateTimeImmutable $now): void
   187	    {
   188	        $this->deletion = UserDeletion::at($now);
   189	        $this->status = UserStatus::Deleted;
   190	        $this->touch(now: $now);
   191	    }
   192	
   193	    public function isActive(): bool
   194	    {
   195	        return $this->status === UserStatus::Active;
   196	    }
   197	
   198	    public function isVerified(): bool
   199	    {
   200	        return $this->verification === UserVerification::Verified;
   201	    }
   202	
   203	    public function isBanned(): bool
   204	    {
   205	        return $this->status === UserStatus::Banned;
   206	    }
   207	
   208	    public function isDeleted(): bool
   209	    {
   210	        return $this->status === UserStatus::Deleted || $this->deletion->isDeleted();
   211	    }
   212	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/User/Repository/UserRepository.php app/src/Modules/User/Repository/ReservedNicknameRepository.php | sed -n '1,260p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

exec
/bin/zsh -lc "nl -ba app/database/migrations/20260613.143901_0_create_user_domain_tables.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba app/src/Modules/User/Domain/ValueObject/Email.php app/src/Modules/User/Domain/ValueObject/UserName.php app/src/Modules/User/Domain/ValueObject/UserNickname.php | sed -n '1,320p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Migration;
     6	
     7	use Cycle\Migrations\Migration;
     8	
     9	class CreateUserDomainTables extends Migration
    10	{
    11	    protected const DATABASE = null;
    12	
    13	    public function up(): void
    14	    {
    15	        $this->table('users')
    16	            ->addColumn('id', 'uuid', ['nullable' => false])
    17	            ->addColumn('name', 'string', ['length' => 100, 'nullable' => false])
    18	            ->addColumn('spiritual_name', 'string', ['length' => 100, 'nullable' => true])
    19	            ->addColumn('bio', 'text', ['nullable' => true])
    20	            ->addColumn('location', 'string', ['length' => 100, 'nullable' => true])
    21	            ->addColumn('email', 'string', ['length' => 254, 'nullable' => false])
    22	            ->addColumn('nickname', 'string', ['length' => 30, 'nullable' => false])
    23	            ->addColumn('avatar_media_id', 'uuid', ['nullable' => true])
    24	            ->addColumn('verification', 'string', ['length' => 32, 'nullable' => false])
    25	            ->addColumn('status', 'string', ['length' => 32, 'nullable' => false])
    26	            ->addColumn('locale', 'string', ['length' => 8, 'nullable' => false])
    27	            ->addColumn('deleted_at', 'datetime', ['nullable' => true])
    28	            ->addColumn('created_at', 'datetime', ['nullable' => false])
    29	            ->addColumn('updated_at', 'datetime', ['nullable' => false])
    30	            ->setPrimaryKeys(['id'])
    31	            ->addIndex(['email'], ['unique' => true])
    32	            ->addIndex(['nickname'], ['unique' => true])
    33	            ->addIndex(['status'])
    34	            ->addForeignKey(
    35	                ['avatar_media_id'],
    36	                'media',
    37	                ['id'],
    38	                ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'indexCreate' => false],
    39	            )
    40	            ->create();
    41	
    42	        $this->table('user_bans')
    43	            ->addColumn('id', 'uuid', ['nullable' => false])
    44	            ->addColumn('user_id', 'uuid', ['nullable' => false])
    45	            ->addColumn('banned_by_id', 'uuid', ['nullable' => false])
    46	            ->addColumn('reason', 'string', ['length' => 500, 'nullable' => false])
    47	            ->addColumn('expires_at', 'datetime', ['nullable' => true])
    48	            ->addColumn('unbanned_at', 'datetime', ['nullable' => true])
    49	            ->addColumn('unbanned_by_id', 'uuid', ['nullable' => true])
    50	            ->addColumn('unbanned_reason', 'string', ['length' => 500, 'nullable' => true])
    51	            ->addColumn('created_at', 'datetime', ['nullable' => false])
    52	            ->addColumn('updated_at', 'datetime', ['nullable' => false])
    53	            ->setPrimaryKeys(['id'])
    54	            ->addForeignKey(
    55	                ['user_id'],
    56	                'users',
    57	                ['id'],
    58	                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
    59	            )
    60	            ->addIndex(['user_id'])
    61	            ->create();
    62	
    63	        $this->table('reserved_nicknames')
    64	            ->addColumn('id', 'uuid', ['nullable' => false])
    65	            ->addColumn('nickname', 'string', ['length' => 30, 'nullable' => false])
    66	            ->addColumn('assigned_user_id', 'uuid', ['nullable' => true])
    67	            ->addColumn('created_at', 'datetime', ['nullable' => false])
    68	            ->addColumn('updated_at', 'datetime', ['nullable' => false])
    69	            ->setPrimaryKeys(['id'])
    70	            ->addIndex(['nickname'], ['unique' => true])
    71	            ->addForeignKey(
    72	                ['assigned_user_id'],
    73	                'users',
    74	                ['id'],
    75	                ['delete' => 'SET NULL', 'update' => 'CASCADE', 'indexCreate' => false],
    76	            )
    77	            ->addIndex(['assigned_user_id'])
    78	            ->create();
    79	    }
    80	
    81	    public function down(): void
    82	    {
    83	        $this->table('reserved_nicknames')->drop();
    84	        $this->table('user_bans')->drop();
    85	        $this->table('users')->drop();
    86	    }
    87	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/User/Repository/ReservedNicknameRepository.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\User\Repository;
     6	
     7	use App\Modules\User\Domain\Entity\ReservedNickname;
     8	use App\Modules\User\Domain\ValueObject\UserNickname;
     9	use Cycle\ORM\Select\Repository;
    10	
    11	/**
    12	 * @extends Repository<ReservedNickname>
    13	 */
    14	final class ReservedNicknameRepository extends Repository
    15	{
    16	    public function findByNickname(UserNickname $nickname): ReservedNickname|null
    17	    {
    18	        return $this->findOne(['nickname' => $nickname->value()]);
    19	    }
    20	
    21	    public function isReserved(UserNickname $nickname): bool
    22	    {
    23	        return $this->findByNickname($nickname) !== null;
    24	    }
    25	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/User/Repository/UserRepository.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\User\Repository;
     6	
     7	use App\Modules\User\Domain\Entity\User;
     8	use App\Modules\User\Domain\ValueObject\Email;
     9	use App\Modules\User\Domain\ValueObject\UserNickname;
    10	use App\Shared\Domain\ValueObject\UserId;
    11	use Cycle\ORM\Select\Repository;
    12	
    13	/**
    14	 * @extends Repository<User>
    15	 */
    16	final class UserRepository extends Repository
    17	{
    18	    public function findById(UserId $userId): User|null
    19	    {
    20	        return $this->findByPK($userId->value());
    21	    }
    22	
    23	    public function findByEmail(Email $email): User|null
    24	    {
    25	        return $this->findOne(['email' => $email->value()]);
    26	    }
    27	
    28	    public function findByNickname(UserNickname $nickname): User|null
    29	    {
    30	        return $this->findOne(['nickname' => $nickname->value()]);
    31	    }
    32	
    33	    public function existsByEmail(Email $email): bool
    34	    {
    35	        return $this->findByEmail($email) !== null;
    36	    }
    37	
    38	    public function existsByNickname(UserNickname $nickname): bool
    39	    {
    40	        return $this->findByNickname($nickname) !== null;
    41	    }
    42	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/User/Domain/ValueObject/Email.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\User\Domain\ValueObject;
     6	
     7	use App\Shared\Domain\Exception\InvalidDomainValueException;
     8	
     9	final readonly class Email implements \Stringable, \JsonSerializable
    10	{
    11	    private const int MAX_LENGTH = 254;
    12	
    13	    private function __construct(
    14	        private string $value,
    15	    ) {}
    16	
    17	    public static function fromString(string $value): self
    18	    {
    19	        $email = \mb_strtolower(\trim($value));
    20	
    21	        if (
    22	            $email === ''
    23	            || \mb_strlen($email) > self::MAX_LENGTH
    24	            || !\filter_var(value: $email, filter: FILTER_VALIDATE_EMAIL)
    25	        ) {
    26	            throw new InvalidDomainValueException('Email имеет неверный формат.');
    27	        }
    28	
    29	        return new self(value: $email);
    30	    }
    31	
    32	    public function value(): string
    33	    {
    34	        return $this->value;
    35	    }
    36	
    37	    public function equals(self $email): bool
    38	    {
    39	        return $this->value === $email->value;
    40	    }
    41	
    42	    #[\Override]
    43	    public function __toString(): string
    44	    {
    45	        return $this->value;
    46	    }
    47	
    48	    #[\Override]
    49	    public function jsonSerialize(): string
    50	    {
    51	        return $this->value;
    52	    }
    53	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/User/Domain/ValueObject/UserName.php app/src/Modules/User/Domain/ValueObject/UserNickname.php | sed -n '1,260p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

exec
/bin/zsh -lc "nl -ba app/src/Modules/User/Domain/ValueObject/UserName.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\User\Domain\ValueObject;
     6	
     7	use App\Shared\Domain\Exception\InvalidDomainValueException;
     8	
     9	final readonly class UserName implements \Stringable, \JsonSerializable
    10	{
    11	    private const int MAX_LENGTH = 100;
    12	
    13	    private function __construct(
    14	        private string $value,
    15	    ) {}
    16	
    17	    public static function fromString(string $value): self
    18	    {
    19	        $name = self::normalize($value);
    20	
    21	        if ($name === '' || \mb_strlen($name) > self::MAX_LENGTH) {
    22	            throw new InvalidDomainValueException('Имя имеет неверную длину.');
    23	        }
    24	
    25	        if (\preg_match(pattern: "/^[\\p{L}\\p{M}' -]+$/u", subject: $name) !== 1) {
    26	            throw new InvalidDomainValueException('Имя содержит недопустимые символы.');
    27	        }
    28	
    29	        return new self(value: $name);
    30	    }
    31	
    32	    public function value(): string
    33	    {
    34	        return $this->value;
    35	    }
    36	
    37	    public function equals(self $name): bool
    38	    {
    39	        return $this->value === $name->value;
    40	    }
    41	
    42	    #[\Override]
    43	    public function __toString(): string
    44	    {
    45	        return $this->value;
    46	    }
    47	
    48	    #[\Override]
    49	    public function jsonSerialize(): string
    50	    {
    51	        return $this->value;
    52	    }
    53	
    54	    private static function normalize(string $value): string
    55	    {
    56	        // Форма нормализации фиксируется явно (FORM_C). Имена параметров расходятся
    57	        // между нативным ext-intl ($string/$form) и стабом
    58	        // symfony/polyfill-intl-normalizer ($s/$form), поэтому аргументы позиционные,
    59	        // а named-arguments-правило для этой строки погашено в phpstan.neon.
    60	        $normalized = \Normalizer::normalize(\trim($value), \Normalizer::FORM_C);
    61	
    62	        if (!\is_string($normalized)) {
    63	            throw new InvalidDomainValueException('Имя не удалось нормализовать.');
    64	        }
    65	
    66	        return \preg_replace(pattern: '/\s+/u', replacement: ' ', subject: $normalized) ?? '';
    67	    }
    68	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/User/Domain/ValueObject/UserNickname.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\User\Domain\ValueObject;
     6	
     7	use App\Shared\Domain\Exception\InvalidDomainValueException;
     8	
     9	final readonly class UserNickname implements \Stringable, \JsonSerializable
    10	{
    11	    private function __construct(
    12	        private string $value,
    13	    ) {}
    14	
    15	    public static function fromString(string $value): self
    16	    {
    17	        $nickname = \mb_strtolower(\trim($value));
    18	
    19	        if (\preg_match(pattern: '/^[a-z0-9](?:[a-z0-9._-]{1,28})[a-z0-9]$/', subject: $nickname) !== 1) {
    20	            throw new InvalidDomainValueException('Никнейм имеет неверный формат.');
    21	        }
    22	
    23	        if (\str_contains(haystack: $nickname, needle: '..')) {
    24	            throw new InvalidDomainValueException('Никнейм не должен содержать две точки подряд.');
    25	        }
    26	
    27	        return new self(value: $nickname);
    28	    }
    29	
    30	    public function value(): string
    31	    {
    32	        return $this->value;
    33	    }
    34	
    35	    public function equals(self $nickname): bool
    36	    {
    37	        return $this->value === $nickname->value;
    38	    }
    39	
    40	    #[\Override]
    41	    public function __toString(): string
    42	    {
    43	        return $this->value;
    44	    }
    45	
    46	    #[\Override]
    47	    public function jsonSerialize(): string
    48	    {
    49	        return $this->value;
    50	    }
    51	}

exec
/bin/zsh -lc "nl -ba app/src/Shared/Domain/Enum/Locale.php app/src/Shared/Domain/Exception/AuthenticationException.php app/src/Shared/Domain/Exception/DomainTranslatableException.php app/src/Shared/Domain/Exception/ValidationException.php | sed -n '1,260p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

exec
/bin/zsh -lc "nl -ba app/src/Shared/Infrastructure/Cycle/ValueObjectCast.php app/src/Shared/Infrastructure/Cycle/ColumnValueTypecast.php | sed -n '1,300p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

exec
/bin/zsh -lc "nl -ba app/src/Shared/Domain/Exception/AuthenticationException.php app/src/Shared/Domain/Exception/DomainTranslatableException.php app/src/Shared/Domain/Exception/ValidationException.php | sed -n '1,260p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

exec
/bin/zsh -lc "nl -ba app/src/Shared/Domain/Enum/Locale.php | sed -n '1,160p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Shared\Domain\Enum;
     6	
     7	enum Locale: string
     8	{
     9	    case Ru = 'ru';
    10	    case En = 'en';
    11	}

exec
/bin/zsh -lc "nl -ba app/src/Shared/Infrastructure/Cycle/ValueObjectCast.php | sed -n '1,240p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Shared\Infrastructure\Cycle;
     6	
     7	use BackedEnum;
     8	use Cycle\ORM\Parser\CastableInterface;
     9	use Cycle\ORM\Parser\UncastableInterface;
    10	
    11	final class ValueObjectCast implements CastableInterface, UncastableInterface
    12	{
    13	    /**
    14	     * Правила привязаны к конкретной роли Entity, поэтому класс stateful. Cycle
    15	     * создаёт по одному typecast-обработчику на роль через factory->make().
    16	     * Не биндить как #[Singleton] / общий синглтон — иначе правила разных ролей смешаются.
    17	     *
    18	     * @var array<non-empty-string, class-string>
    19	     */
    20	    private array $rules = [];
    21	
    22	    /**
    23	     * @param array<non-empty-string, mixed> $rules
    24	     * @return array<non-empty-string, mixed>
    25	     */
    26	    #[\Override]
    27	    public function setRules(array $rules): array
    28	    {
    29	        foreach ($rules as $field => $rule) {
    30	            if (!\is_string($rule) || !\class_exists($rule)) {
    31	                continue;
    32	            }
    33	
    34	            if (!$this->supportsRule($rule)) {
    35	                continue;
    36	            }
    37	
    38	            /** @var class-string $rule */
    39	            $this->rules[$field] = $rule;
    40	            unset($rules[$field]);
    41	        }
    42	
    43	        return $rules;
    44	    }
    45	
    46	    /**
    47	     * @param array<int|string, null|bool|int|float|string|\DateTimeInterface|object> $data
    48	     * @return array<int|string, null|bool|int|float|string|\DateTimeInterface|object>
    49	     */
    50	    #[\Override]
    51	    public function cast(array $data): array
    52	    {
    53	        foreach ($this->rules as $field => $rule) {
    54	            if (!\array_key_exists(key: $field, array: $data)) {
    55	                continue;
    56	            }
    57	
    58	            $data[$field] = $this->castField(rule: $rule, value: $data[$field]);
    59	        }
    60	
    61	        return $data;
    62	    }
    63	
    64	    /**
    65	     * @param array<int|string, null|bool|int|float|string|\DateTimeInterface|object> $data
    66	     * @return array<int|string, bool|int|float|string|\DateTimeInterface|null>
    67	     */
    68	    #[\Override]
    69	    public function uncast(array $data): array
    70	    {
    71	        foreach ($data as $field => $value) {
    72	            $data[$field] = $this->uncastField(
    73	                field: (string) $field,
    74	                value: $value,
    75	            );
    76	        }
    77	
    78	        return $data;
    79	    }
    80	
    81	    /**
    82	     * @param class-string $rule
    83	     */
    84	    private function supportsRule(string $rule): bool
    85	    {
    86	        return (
    87	            \is_subclass_of(object_or_class: $rule, class: ColumnValueTypecast::class)
    88	            && \method_exists(object_or_class: $rule, method: 'castDatabaseValue')
    89	            && \method_exists(object_or_class: $rule, method: 'uncastValue')
    90	        )
    91	            || \is_subclass_of(object_or_class: $rule, class: BackedEnum::class)
    92	            || \method_exists(object_or_class: $rule, method: 'fromString')
    93	            || \method_exists(object_or_class: $rule, method: 'fromInt');
    94	    }
    95	
    96	    /**
    97	     * @param class-string $rule
    98	     */
    99	    private function castField(
   100	        string $rule,
   101	        bool|int|float|string|object|null $value,
   102	    ): object|null {
   103	        if (\is_subclass_of(object_or_class: $rule, class: ColumnValueTypecast::class)) {
   104	            return $this->invokeColumnCast(
   105	                rule: $rule,
   106	                value: $this->databaseValueOrFail($value),
   107	            );
   108	        }
   109	
   110	        if ($value === null) {
   111	            return null;
   112	        }
   113	
   114	        if (\is_subclass_of(object_or_class: $rule, class: BackedEnum::class)) {
   115	            if (!\is_string($value) && !\is_int($value)) {
   116	                throw new \InvalidArgumentException('Enum-значение базы должно быть строкой или числом.');
   117	            }
   118	
   119	            return $rule::from($value);
   120	        }
   121	
   122	        if (\method_exists(object_or_class: $rule, method: 'fromString')) {
   123	            if (!\is_string($value)) {
   124	                throw new \InvalidArgumentException('Строковый value object должен восстанавливаться из строки.');
   125	            }
   126	
   127	            return $this->invokeObjectFactory(rule: $rule, method: 'fromString', value: $value);
   128	        }
   129	
   130	        // Инвариант: supportsRule() пропускает ровно четыре вида правил
   131	        // (ColumnValueTypecast, BackedEnum, fromString, fromInt); первые три отсечены выше,
   132	        // поэтому единственное оставшееся правило здесь — фабрика fromInt, и финальный throw
   133	        // означает «не-число для fromInt». Если в supportsRule() добавят новый вид правила,
   134	        // эту хвостовую ветку нужно расширить синхронно, иначе сообщение про «числовой
   135	        // value object» станет вводить в заблуждение.
   136	        if (\is_string($value) && \preg_match(pattern: '/^-?\d+$/', subject: $value) === 1) {
   137	            return $this->invokeObjectFactory(rule: $rule, method: 'fromInt', value: (int) $value);
   138	        }
   139	
   140	        if (\is_int($value)) {
   141	            return $this->invokeObjectFactory(rule: $rule, method: 'fromInt', value: $value);
   142	        }
   143	
   144	        throw new \InvalidArgumentException('Числовой value object должен восстанавливаться из числа.');
   145	    }
   146	
   147	    private function uncastField(
   148	        string $field,
   149	        bool|int|float|string|object|null $value,
   150	    ): bool|int|float|string|\DateTimeInterface|null {
   151	        $rule = $this->rules[$field] ?? null;
   152	
   153	        if ($rule !== null) {
   154	            return $this->uncastFieldByRule(rule: $rule, value: $value);
   155	        }
   156	
   157	        if ($value instanceof BackedEnum) {
   158	            return $value->value;
   159	        }
   160	
   161	        if ($value instanceof \DateTimeInterface) {
   162	            return $value;
   163	        }
   164	
   165	        if (\is_object($value)) {
   166	            throw new \InvalidArgumentException('Объект не поддерживает запись в базу.');
   167	        }
   168	
   169	        return $value;
   170	    }
   171	
   172	    /**
   173	     * @param class-string $rule
   174	     */
   175	    private function uncastFieldByRule(
   176	        string $rule,
   177	        bool|int|float|string|object|null $value,
   178	    ): bool|int|float|string|\DateTimeInterface|null {
   179	        if (\is_subclass_of(object_or_class: $rule, class: ColumnValueTypecast::class)) {
   180	            if ($value !== null && !\is_object($value)) {
   181	                return $value;
   182	            }
   183	
   184	            return $this->invokeColumnUncast(rule: $rule, value: $value);
   185	        }
   186	
   187	        if ($value instanceof BackedEnum) {
   188	            return $value->value;
   189	        }
   190	
   191	        if ($value instanceof \DateTimeInterface) {
   192	            return $value;
   193	        }
   194	
   195	        if (!\is_object($value)) {
   196	            return $value;
   197	        }
   198	
   199	        if (!\method_exists(object_or_class: $value, method: 'value')) {
   200	            throw new \InvalidArgumentException('Value object должен иметь метод value().');
   201	        }
   202	
   203	        return $this->valueObjectDatabaseValue($value);
   204	    }
   205	
   206	    /**
   207	     * @param class-string $rule
   208	     */
   209	    private function invokeObjectFactory(string $rule, string $method, string|int $value): object
   210	    {
   211	        $createdValue = (new \ReflectionMethod(objectOrMethod: $rule, method: $method))->invoke(null, $value);
   212	
   213	        if (!\is_object($createdValue)) {
   214	            throw new \InvalidArgumentException('Фабрика value object должна вернуть объект.');
   215	        }
   216	
   217	        return $createdValue;
   218	    }
   219	
   220	    /**
   221	     * @param class-string $rule
   222	     */
   223	    private function invokeColumnCast(
   224	        string $rule,
   225	        bool|int|float|string|\DateTimeInterface|null $value,
   226	    ): object {
   227	        $castValue = (new \ReflectionMethod(objectOrMethod: $rule, method: 'castDatabaseValue'))->invoke(null, $value);
   228	
   229	        if (!\is_object($castValue)) {
   230	            throw new \InvalidArgumentException('Typecast базы должен вернуть объект.');
   231	        }
   232	
   233	        return $castValue;
   234	    }
   235	
   236	    /**
   237	     * @param class-string $rule
   238	     */
   239	    private function invokeColumnUncast(
   240	        string $rule,

exec
/bin/zsh -lc "nl -ba app/src/Shared/Infrastructure/Cycle/ColumnValueTypecast.php | sed -n '1,200p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Shared\Infrastructure\Cycle;
     6	
     7	interface ColumnValueTypecast {}

exec
/bin/zsh -lc "nl -ba app/src/Shared/Domain/Exception/AuthenticationException.php | sed -n '1,120p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Shared\Domain\Exception;
     6	
     7	final class AuthenticationException extends DomainTranslatableException
     8	{
     9	    #[\Override]
    10	    protected function statusCode(): int
    11	    {
    12	        return 401;
    13	    }
    14	}

exec
/bin/zsh -lc "nl -ba app/src/Shared/Domain/Exception/DomainTranslatableException.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Shared\Domain\Exception;
     6	
     7	use GianTiaga\SpiralApiErrors\Exception\TranslatableException;
     8	
     9	abstract class DomainTranslatableException extends \DomainException implements TranslatableException
    10	{
    11	    /**
    12	     * @param array<string, string> $translationParameters
    13	     */
    14	    public function __construct(private readonly string $translationKey, private readonly array $translationParameters = [])
    15	    {
    16	        parent::__construct(message: $translationKey, code: $this->statusCode());
    17	    }
    18	
    19	    abstract protected function statusCode(): int;
    20	
    21	    #[\Override]
    22	    public function translationKey(): string
    23	    {
    24	        return $this->translationKey;
    25	    }
    26	
    27	    /**
    28	     * Домен перевода (= файл каталога) выводится из второго сегмента ключа:
    29	     * `app.media.not_found` -> `media`, `app.system.swagger_ui_disabled` -> `system`.
    30	     * Ключи без модульного сегмента переводятся в домене по умолчанию `messages`.
    31	     */
    32	    #[\Override]
    33	    public function translationDomain(): string
    34	    {
    35	        return \explode(separator: '.', string: $this->translationKey)[1] ?? 'messages';
    36	    }
    37	
    38	    /**
    39	     * @return array<string, string>
    40	     */
    41	    #[\Override]
    42	    public function translationParameters(): array
    43	    {
    44	        return $this->translationParameters;
    45	    }
    46	}

exec
/bin/zsh -lc "nl -ba app/src/Shared/Domain/Exception/ValidationException.php | sed -n '1,120p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Shared\Domain\Exception;
     6	
     7	final class ValidationException extends DomainTranslatableException
     8	{
     9	    #[\Override]
    10	    protected function statusCode(): int
    11	    {
    12	        return 422;
    13	    }
    14	}

exec
/bin/zsh -lc "nl -ba app/src/Shared/Domain/ValueObject/AbstractUuidV7Id.php app/src/Shared/Domain/Trait/HasTimestamps.php | sed -n '1,260p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

exec
/bin/zsh -lc "nl -ba app/src/Shared/Infrastructure/Framework/Bootloader/AppBootloader.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba app/src/Shared/Infrastructure/Framework/Kernel.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba app/src/Shared/Infrastructure/Framework/Bootloader/RoutesBootloader.php | sed -n '1,240p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Shared\Infrastructure\Framework\Bootloader;
     6	
     7	use Spiral\Bootloader\DomainBootloader;
     8	use Spiral\Cycle\Interceptor\CycleInterceptor;
     9	use Spiral\DataGrid\Interceptor\GridInterceptor;
    10	use Spiral\Domain\GuardInterceptor;
    11	use Spiral\Interceptors\HandlerInterface;
    12	use GianTiaga\SpiralApiErrors\Interceptor\ApiExceptionInterceptor;
    13	use GianTiaga\SpiralOpenApi\Response\Interceptor\HttpResponseInterceptor;
    14	
    15	/**
    16	 * @link https://spiral.dev/docs/http-interceptors
    17	 */
    18	final class AppBootloader extends DomainBootloader
    19	{
    20	    protected const array INTERCEPTORS = [
    21	        CycleInterceptor::class,
    22	        GridInterceptor::class,
    23	        GuardInterceptor::class,
    24	        HttpResponseInterceptor::class,
    25	        ApiExceptionInterceptor::class,
    26	    ];
    27	
    28	    #[\Override]
    29	    public function defineSingletons(): array
    30	    {
    31	        return [
    32	            ...parent::defineSingletons(),
    33	            HandlerInterface::class => [self::class, 'domainCore'],
    34	        ];
    35	    }
    36	}

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Shared\Infrastructure\Framework\Bootloader;
     6	
     7	use App\Shared\Infrastructure\Framework\Middleware\LocaleMiddleware;
     8	use Spiral\Bootloader\Http\RoutesBootloader as BaseRoutesBootloader;
     9	use Spiral\Cookies\Middleware\CookiesMiddleware;
    10	use Spiral\Csrf\Middleware\CsrfMiddleware;
    11	use Spiral\Debug\Middleware\DumperMiddleware;
    12	use Spiral\Debug\StateCollector\HttpCollector;
    13	use Spiral\Filter\ValidationHandlerMiddleware;
    14	use Spiral\Http\Middleware\ErrorHandlerMiddleware;
    15	use Spiral\Http\Middleware\JsonPayloadMiddleware;
    16	use Spiral\Router\Bootloader\AnnotatedRoutesBootloader;
    17	use Spiral\Session\Middleware\SessionMiddleware;
    18	use GianTiaga\SpiralApiErrors\Middleware\RouteNotFoundMiddleware;
    19	
    20	/**
    21	 * Настраивает маршруты и middleware приложения.
    22	 *
    23	 * @link https://spiral.dev/docs/http-routing
    24	 */
    25	final class RoutesBootloader extends BaseRoutesBootloader
    26	{
    27	    public const string GROUP_API = 'api';
    28	    public const string GROUP_WEB = 'web';
    29	    protected const array DEPENDENCIES = [AnnotatedRoutesBootloader::class];
    30	
    31	    #[\Override]
    32	    protected function globalMiddleware(): array
    33	    {
    34	        return [
    35	            ErrorHandlerMiddleware::class,
    36	            LocaleMiddleware::class,
    37	            RouteNotFoundMiddleware::class,
    38	            DumperMiddleware::class,
    39	            JsonPayloadMiddleware::class,
    40	            HttpCollector::class,
    41	        ];
    42	    }
    43	
    44	    #[\Override]
    45	    protected function middlewareGroups(): array
    46	    {
    47	        return [
    48	            self::GROUP_API => [
    49	                ValidationHandlerMiddleware::class,
    50	            ],
    51	            self::GROUP_WEB => [
    52	                CookiesMiddleware::class,
    53	                SessionMiddleware::class,
    54	                CsrfMiddleware::class,
    55	                ValidationHandlerMiddleware::class,
    56	            ],
    57	        ];
    58	    }
    59	}

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Shared\Infrastructure\Framework;
     6	
     7	use Spiral\Boot\Bootloader\CoreBootloader;
     8	use Spiral\Bootloader as Framework;
     9	use Spiral\Bootloader\Http\HttpBootloader;
    10	use Spiral\Bootloader\I18nBootloader;
    11	use Spiral\Bootloader\Views\TranslatedCacheBootloader;
    12	use Spiral\Cache\Bootloader\CacheBootloader;
    13	use Spiral\Cycle\Bootloader as CycleBridge;
    14	use Spiral\DataGrid\Bootloader\GridBootloader;
    15	use Spiral\Debug\Bootloader\DumperBootloader;
    16	use Spiral\Distribution\Bootloader\DistributionBootloader;
    17	use Spiral\DotEnv\Bootloader\DotenvBootloader;
    18	use Spiral\Events\Bootloader\EventsBootloader;
    19	use Spiral\League\Event\Bootloader\EventBootloader;
    20	use Spiral\Monolog\Bootloader\MonologBootloader;
    21	use Spiral\Nyholm\Bootloader\NyholmBootloader;
    22	use Spiral\Prototype\Bootloader\PrototypeBootloader;
    23	use Spiral\Queue\Bootloader\QueueBootloader;
    24	use Spiral\RoadRunnerBridge\Bootloader as RoadRunnerBridge;
    25	use Spiral\Scaffolder\Bootloader\ScaffolderBootloader;
    26	use Spiral\Scheduler\Bootloader\SchedulerBootloader;
    27	use Spiral\SendIt\Bootloader\MailerBootloader;
    28	use Spiral\Sentry\Bootloader\SentryReporterBootloader;
    29	use Spiral\Storage\Bootloader\StorageBootloader;
    30	use Spiral\TemporalBridge\Bootloader as TemporalBridge;
    31	use Spiral\Tokenizer\Bootloader\TokenizerListenerBootloader;
    32	use Spiral\Twig\Bootloader\TwigBootloader;
    33	use Spiral\Validation\Bootloader\ValidationBootloader;
    34	use Spiral\Validation\Symfony\Bootloader\ValidatorBootloader;
    35	use Spiral\Views\Bootloader\ViewsBootloader;
    36	use App\Modules\Media\Infrastructure\Bootloader\MediaBootloader;
    37	use App\Modules\Outbox\Infrastructure\Bootloader\OutboxBootloader;
    38	use App\Modules\Outbox\Infrastructure\Bootloader\OutboxConsoleBootloader;
    39	use GianTiaga\SpiralApiErrors\Bootloader\ApiErrorBootloader;
    40	use GianTiaga\SpiralCqrs\Bootloader\CqrsBootloader;
    41	use GianTiaga\SpiralOpenApi\Bootloader\OpenApiToolsBootloader;
    42	
    43	class Kernel extends \Spiral\Framework\Kernel
    44	{
    45	    #[\Override]
    46	    public function defineSystemBootloaders(): array
    47	    {
    48	        return [
    49	            CoreBootloader::class,
    50	            DotenvBootloader::class,
    51	            TokenizerListenerBootloader::class,
    52	
    53	            DumperBootloader::class,
    54	        ];
    55	    }
    56	
    57	    #[\Override]
    58	    public function defineBootloaders(): array
    59	    {
    60	        return [
    61	            // Логирование и обработка исключений
    62	            MonologBootloader::class,
    63	            Bootloader\ExceptionHandlerBootloader::class,
    64	
    65	            // Логи приложения
    66	            Bootloader\ConfigBootloader::class,
    67	            Bootloader\LoggingBootloader::class,
    68	
    69	            // RoadRunner
    70	            RoadRunnerBridge\LoggerBootloader::class,
    71	            RoadRunnerBridge\QueueBootloader::class,
    72	            RoadRunnerBridge\HttpBootloader::class,
    73	            RoadRunnerBridge\CacheBootloader::class,
    74	            RoadRunnerBridge\LockBootloader::class,
    75	
    76	            // Базовые сервисы
    77	            Framework\SnapshotsBootloader::class,
    78	
    79	            // Безопасность и валидация
    80	            Framework\Security\EncrypterBootloader::class,
    81	            Framework\Security\FiltersBootloader::class,
    82	            Framework\Security\GuardBootloader::class,
    83	
    84	            // HTTP-расширения
    85	            HttpBootloader::class,
    86	            Framework\Http\ErrorHandlerBootloader::class,
    87	            Framework\Http\RouterBootloader::class,
    88	            Framework\Http\JsonPayloadsBootloader::class,
    89	            Framework\Http\CookiesBootloader::class,
    90	            Framework\Http\SessionBootloader::class,
    91	            Framework\Http\CsrfBootloader::class,
    92	            Framework\Http\PaginationBootloader::class,
    93	
    94	            // Базы данных
    95	            CycleBridge\DatabaseBootloader::class,
    96	            CycleBridge\MigrationsBootloader::class,
    97	
    98	            // ORM
    99	            CycleBridge\SchemaBootloader::class,
   100	            CycleBridge\CycleOrmBootloader::class,
   101	            CycleBridge\AnnotatedBootloader::class,
   102	
   103	            // Диспетчер событий
   104	            EventsBootloader::class,
   105	            EventBootloader::class,
   106	
   107	            // Планировщик
   108	            SchedulerBootloader::class,
   109	
   110	            // Sentry и сборщики данных
   111	            SentryReporterBootloader::class,
   112	            Framework\DebugBootloader::class,
   113	            Framework\Debug\LogCollectorBootloader::class,
   114	            Framework\Debug\HttpCollectorBootloader::class,
   115	
   116	            // Представления
   117	            ViewsBootloader::class,
   118	            TwigBootloader::class,
   119	
   120	            // Очереди
   121	            QueueBootloader::class,
   122	
   123	            // Кэш
   124	            CacheBootloader::class,
   125	
   126	            // Хранилище файлов
   127	            StorageBootloader::class,
   128	            DistributionBootloader::class,
   129	
   130	            // Интернационализация
   131	            I18nBootloader::class,
   132	            TranslatedCacheBootloader::class,
   133	            OpenApiToolsBootloader::class,
   134	            CqrsBootloader::class,
   135	            OutboxBootloader::class,
   136	            MediaBootloader::class,
   137	
   138	            // Почта
   139	            MailerBootloader::class,
   140	
   141	            // Data Grid
   142	            GridBootloader::class,
   143	
   144	            // Temporal
   145	            TemporalBridge\PrototypeBootloader::class,
   146	            TemporalBridge\TemporalBridgeBootloader::class,
   147	
   148	            NyholmBootloader::class,
   149	
   150	            CycleBridge\DataGridBootloader::class,
   151	
   152	            ValidationBootloader::class,
   153	            ValidatorBootloader::class,
   154	
   155	            RoadRunnerBridge\MetricsBootloader::class,
   156	
   157	            // Консольные команды
   158	            Framework\CommandBootloader::class,
   159	            Bootloader\OpenApiBootloader::class,
   160	            OutboxConsoleBootloader::class,
   161	            RoadRunnerBridge\CommandBootloader::class,
   162	            CycleBridge\CommandBootloader::class,
   163	            ScaffolderBootloader::class,
   164	            RoadRunnerBridge\ScaffolderBootloader::class,
   165	            CycleBridge\ScaffolderBootloader::class,
   166	
   167	            // Быстрое прототипирование кода
   168	            PrototypeBootloader::class,
   169	
   170	            // Группы маршрутов и middleware
   171	            ApiErrorBootloader::class,
   172	            Bootloader\RoutesBootloader::class,
   173	        ];
   174	    }
   175	
   176	    #[\Override]
   177	    public function defineAppBootloaders(): array
   178	    {
   179	        return [
   180	            // Доменный обработчик приложения
   181	            Bootloader\AppBootloader::class,
   182	        ];
   183	    }
   184	}

exec
/bin/zsh -lc "nl -ba app/src/Shared/Infrastructure/Framework/Middleware/LocaleMiddleware.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Shared\Infrastructure\Framework\Middleware;
     6	
     7	use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
     8	use Illuminate\Support\Collection;
     9	use Psr\Http\Message\ResponseInterface;
    10	use Psr\Http\Message\ServerRequestInterface;
    11	use Psr\Http\Server\MiddlewareInterface;
    12	use Psr\Http\Server\RequestHandlerInterface;
    13	use Psr\Log\LoggerInterface;
    14	use Spiral\Http\Header\AcceptHeader;
    15	use Spiral\Http\Header\AcceptHeaderItem;
    16	use Spiral\Translator\Translator;
    17	
    18	/**
    19	 * Определяет локаль запроса из заголовка Accept-Language, пересечённого с белым списком
    20	 * поддерживаемых локалей, и выставляет её в translator на время обработки запроса.
    21	 *
    22	 * Зависит от конкретного Spiral\Translator\Translator (не TranslatorInterface): метод
    23	 * setLocale() объявлен в LocaleAwareInterface и реализован конкретным Translator, а не
    24	 * в TranslatorInterface. Translator — singleton, поэтому выставленная локаль видна и
    25	 * интерсептору ошибок, который читает тот же инстанс через TranslatorInterface.
    26	 */
    27	final readonly class LocaleMiddleware implements MiddlewareInterface
    28	{
    29	    public function __construct(
    30	        private Translator $translator,
    31	        private LocaleConfig $localeConfig,
    32	        private LoggerInterface $logger,
    33	    ) {}
    34	
    35	    #[\Override]
    36	    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    37	    {
    38	        $acceptLanguage = $request->getHeaderLine('Accept-Language');
    39	        $matchedLocale = $this->matchSupportedLocale($acceptLanguage);
    40	        $resolvedLocale = $matchedLocale ?? $this->localeConfig->default;
    41	
    42	        $this->translator->setLocale($resolvedLocale);
    43	        $this->logger->debug(message: 'Локаль запроса определена.', context: [
    44	            'acceptLanguage' => $acceptLanguage,
    45	            'resolvedLocale' => $resolvedLocale,
    46	            'usedFallback' => $matchedLocale === null,
    47	        ]);
    48	
    49	        return $handler->handle($request);
    50	    }
    51	
    52	    private function matchSupportedLocale(string $acceptLanguage): string|null
    53	    {
    54	        // getAll() уже отсортирован по quality по убыванию, поэтому first() даёт самый приоритетный язык.
    55	        return Collection::make(AcceptHeader::fromString($acceptLanguage)->getAll())
    56	            ->filter(static fn(AcceptHeaderItem $item): bool => $item->getQuality() > 0.0)
    57	            ->map(fn(AcceptHeaderItem $item): string => $this->primarySubtag((string) $item->getValue()))
    58	            ->first(fn(string $primarySubtag): bool => \in_array(
    59	                needle: $primarySubtag,
    60	                haystack: $this->localeConfig->supported,
    61	                strict: true,
    62	            ));
    63	    }
    64	
    65	    private function primarySubtag(string $value): string
    66	    {
    67	        $lower = \strtolower($value);
    68	        $separatorPosition = \strpos(haystack: $lower, needle: '-');
    69	
    70	        return $separatorPosition === false ? $lower : \substr(string: $lower, offset: 0, length: $separatorPosition);
    71	    }
    72	}

codex
Каркас маршрутов уже подтягивает `AnnotatedRoutesBootloader`, значит пункт плана «если нет — добавить» фактически не нужен. Это не блокер, но в плане лучше заменить на явную проверку, чтобы исполнитель не полез искать несуществующую проблему.
exec
/bin/zsh -lc "rg -n \"class .*AuthBootloader|HttpAuthBootloader|addTokenStorage|addTransport|addActorProvider|defineDependencies|DEPENDENCIES\" vendor/spiral/framework/src/Auth vendor/spiral/framework/src/AuthHttp vendor/spiral/framework/src/Bootloader -g '*.php'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 exited 2 in 0ms:
rg: vendor/spiral/framework/src/Bootloader: No such file or directory (os error 2)

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/AuthHttp/src/Transport/HeaderTransport.php vendor/spiral/framework/src/AuthHttp/src/TransportRegistry.php | sed -n '1,260p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Auth/src/TokenStorageInterface.php vendor/spiral/framework/src/Auth/src/TokenInterface.php vendor/spiral/framework/src/Auth/src/ActorProviderInterface.php vendor/spiral/framework/src/Auth/src/AuthContextInterface.php | sed -n '1,260p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/AuthHttp/src/Middleware/AuthTransportWithStorageMiddleware.php vendor/spiral/framework/src/AuthHttp/src/Middleware/AuthTransportMiddleware.php vendor/spiral/framework/src/AuthHttp/src/Middleware/AuthMiddleware.php | sed -n '1,360p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Auth/src/TokenStorageInterface.php | sed -n '1,120p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Auth;
     6	
     7	use Spiral\Auth\Exception\TokenStorageException;
     8	
     9	/**
    10	 * Provides the ability to store tokens in persistent storage.
    11	 */
    12	interface TokenStorageInterface
    13	{
    14	    /**
    15	     * Load token by id, must return null if token not found.
    16	     *
    17	     * @throws TokenStorageException
    18	     */
    19	    public function load(string $id): ?TokenInterface;
    20	
    21	    /**
    22	     * Create token based on the payload provided by actor provider.
    23	     *
    24	     * @throws TokenStorageException
    25	     */
    26	    public function create(array $payload, ?\DateTimeInterface $expiresAt = null): TokenInterface;
    27	
    28	    /**
    29	     * Delete token from the persistent storage.
    30	     *
    31	     * @throws TokenStorageException
    32	     */
    33	    public function delete(TokenInterface $token): void;
    34	}

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Auth/src/TokenInterface.php | sed -n '1,120p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Auth;
     6	
     7	/**
     8	 * Carries information about current authentication token, it's expiration time and actor provider specific payload.
     9	 */
    10	interface TokenInterface
    11	{
    12	    public function getID(): string;
    13	
    14	    public function getExpiresAt(): ?\DateTimeInterface;
    15	
    16	    /**
    17	     * Actor provider specific payload.
    18	     */
    19	    public function getPayload(): array;
    20	}

exec
/bin/zsh -lc "rg -n \"class .*AuthBootloader|HttpAuthBootloader|addTokenStorage|addTransport|addActorProvider|defineDependencies|DEPENDENCIES\" vendor/spiral/framework/src -g '*.php'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
vendor/spiral/framework/src/Scaffolder/src/Declaration/BootloaderDeclaration.php:44:        $this->class->addConstant('DEPENDENCIES', [])->setProtected();
vendor/spiral/framework/src/Boot/src/BootloadManager/Initializer.php:280:            $deps = [...$deps, ...$bootloader->defineDependencies()];
vendor/spiral/framework/src/Boot/src/Bootloader/Bootloader.php:33:    protected const DEPENDENCIES = [];
vendor/spiral/framework/src/Boot/src/Bootloader/Bootloader.php:45:    public function defineDependencies(): array
vendor/spiral/framework/src/Boot/src/Bootloader/Bootloader.php:47:        return static::DEPENDENCIES;
vendor/spiral/framework/src/Boot/src/Bootloader/DependedInterface.php:19:    public function defineDependencies(): array;
vendor/spiral/framework/src/Boot/src/Bootloader/CoreBootloader.php:19:    protected const DEPENDENCIES = [
vendor/spiral/framework/src/Queue/src/Bootloader/QueueBootloader.php:41:    protected const DEPENDENCIES = [
vendor/spiral/framework/src/Tokenizer/src/Bootloader/TokenizerListenerBootloader.php:36:    protected const DEPENDENCIES = [
vendor/spiral/framework/src/Events/src/Bootloader/EventsBootloader.php:39:    protected const DEPENDENCIES = [
vendor/spiral/framework/src/AnnotatedRoutes/src/Bootloader/AnnotatedRoutesBootloader.php:21:    protected const DEPENDENCIES = [
vendor/spiral/framework/src/Bridge/Monolog/src/Bootloader/MonologBootloader.php:36:    protected const DEPENDENCIES = [
vendor/spiral/framework/src/SendIt/src/Bootloader/MailerBootloader.php:34:    protected const DEPENDENCIES = [
vendor/spiral/framework/src/Console/src/Bootloader/ConsoleBootloader.php:33:    protected const DEPENDENCIES = [
vendor/spiral/framework/src/SendIt/src/Bootloader/BuilderBootloader.php:18:    protected const DEPENDENCIES = [
vendor/spiral/framework/src/Prototype/src/Bootloader/PrototypeBootloader.php:29:    protected const DEPENDENCIES = [
vendor/spiral/framework/src/Broadcasting/src/Bootloader/WebsocketsBootloader.php:20:    protected const DEPENDENCIES = [
vendor/spiral/framework/src/Framework/Bootloader/Views/TranslatedCacheBootloader.php:18:    protected const DEPENDENCIES = [
vendor/spiral/framework/src/Framework/Bootloader/Security/GuardBootloader.php:22:    protected const DEPENDENCIES = [
vendor/spiral/framework/src/Framework/Bootloader/StorageSnapshotsBootloader.php:23:    protected const DEPENDENCIES = [
vendor/spiral/framework/src/Framework/Bootloader/Auth/AuthBootloader.php:20:final class AuthBootloader extends Bootloader implements ActorProviderInterface
vendor/spiral/framework/src/Framework/Bootloader/Auth/AuthBootloader.php:65:    public function addActorProvider(ActorProviderInterface|Autowire|string $actorProvider): void
vendor/spiral/framework/src/Framework/Bootloader/Auth/HttpAuthBootloader.php:40:final class HttpAuthBootloader extends Bootloader
vendor/spiral/framework/src/Framework/Bootloader/Auth/HttpAuthBootloader.php:46:    public function defineDependencies(): array
vendor/spiral/framework/src/Framework/Bootloader/Auth/HttpAuthBootloader.php:113:    public function addTransport(string $name, Autowire|HttpTransportInterface|string $transport): void
vendor/spiral/framework/src/Framework/Bootloader/Auth/HttpAuthBootloader.php:124:    public function addTokenStorage(string $name, Autowire|TokenStorageInterface|string $storage): void
vendor/spiral/framework/src/Framework/Bootloader/Auth/SecurityActorBootloader.php:18:    protected const DEPENDENCIES = [
vendor/spiral/framework/src/Framework/Bootloader/Auth/TokenStorage/SessionTokensBootloader.php:11:use Spiral\Bootloader\Auth\HttpAuthBootloader;
vendor/spiral/framework/src/Framework/Bootloader/Auth/TokenStorage/SessionTokensBootloader.php:20:    protected const DEPENDENCIES = [
vendor/spiral/framework/src/Framework/Bootloader/Auth/TokenStorage/SessionTokensBootloader.php:21:        HttpAuthBootloader::class,
vendor/spiral/framework/src/Framework/Bootloader/Http/PaginationBootloader.php:20:    public function defineDependencies(): array
vendor/spiral/framework/src/Framework/Bootloader/Http/HttpBootloader.php:42:    public function defineDependencies(): array
vendor/spiral/framework/src/Framework/Bootloader/Http/RouterBootloader.php:46:    public function defineDependencies(): array

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Auth/src/ActorProviderInterface.php vendor/spiral/framework/src/Auth/src/AuthContextInterface.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

codex
По vendor видно, что путь к бутлоадерам — `Spiral\Bootloader\Auth\...`, а не внутри `vendor/spiral/framework/src/Auth`. Сами интерфейсы токенов совпадают с планом.
exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Framework/Bootloader/Auth/HttpAuthBootloader.php | sed -n '1,210p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Framework/Bootloader/Auth/AuthBootloader.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Bootloader\Auth;
     6	
     7	use Spiral\Auth\ActorProviderInterface;
     8	use Spiral\Auth\AuthScope;
     9	use Spiral\Auth\Exception\AuthException;
    10	use Spiral\Auth\TokenInterface;
    11	use Spiral\Boot\Bootloader\Bootloader;
    12	use Spiral\Core\Attribute\Singleton;
    13	use Spiral\Core\Container\Autowire;
    14	use Spiral\Core\FactoryInterface;
    15	
    16	/**
    17	 * Manages the set of actor providers.
    18	 */
    19	#[Singleton]
    20	final class AuthBootloader extends Bootloader implements ActorProviderInterface
    21	{
    22	    protected const SINGLETONS = [
    23	        AuthScope::class => AuthScope::class,
    24	        ActorProviderInterface::class => self::class,
    25	    ];
    26	
    27	    /** @var array<int, ActorProviderInterface|Autowire|string> */
    28	    private array $actorProvider = [];
    29	
    30	    public function __construct(
    31	        private readonly FactoryInterface $factory,
    32	    ) {}
    33	
    34	    /**
    35	     * Find actor by first matching actor provider.
    36	     */
    37	    public function getActor(TokenInterface $token): ?object
    38	    {
    39	        foreach ($this->getProviders() as $provider) {
    40	            if (!$provider instanceof ActorProviderInterface) {
    41	                throw new AuthException(
    42	                    \sprintf(
    43	                        'Expected `ActorProviderInterface`, got `%s`',
    44	                        $provider::class,
    45	                    ),
    46	                );
    47	            }
    48	
    49	            $actor = $provider->getActor($token);
    50	            if ($actor !== null) {
    51	                return $actor;
    52	            }
    53	        }
    54	
    55	        if ($this->actorProvider === []) {
    56	            throw new AuthException('No actor provider');
    57	        }
    58	
    59	        return null;
    60	    }
    61	
    62	    /**
    63	     * Register new actor provider.
    64	     */
    65	    public function addActorProvider(ActorProviderInterface|Autowire|string $actorProvider): void
    66	    {
    67	        $this->actorProvider[] = $actorProvider;
    68	    }
    69	
    70	    /**
    71	     * @return \Generator<array-key, ActorProviderInterface>
    72	     */
    73	    private function getProviders(): \Generator
    74	    {
    75	        foreach ($this->actorProvider as $provider) {
    76	            if ($provider instanceof Autowire) {
    77	                yield $provider->resolve($this->factory);
    78	                continue;
    79	            }
    80	
    81	            if (\is_object($provider)) {
    82	                yield $provider;
    83	                continue;
    84	            }
    85	
    86	            yield $this->factory->make($provider);
    87	        }
    88	    }
    89	}

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Bootloader\Auth;
     6	
     7	use Psr\Http\Message\ServerRequestInterface;
     8	use Spiral\Auth\AuthContextInterface;
     9	use Spiral\Auth\Config\AuthConfig;
    10	use Spiral\Auth\HttpTransportInterface;
    11	use Spiral\Auth\Middleware\AuthMiddleware;
    12	use Spiral\Auth\Middleware\AuthTransportMiddleware;
    13	use Spiral\Auth\Middleware\AuthTransportWithStorageMiddleware;
    14	use Spiral\Auth\Session\TokenStorage as SessionTokenStorage;
    15	use Spiral\Auth\TokenStorageInterface;
    16	use Spiral\Auth\TokenStorageProvider;
    17	use Spiral\Auth\TokenStorageProviderInterface;
    18	use Spiral\Auth\Transport\CookieTransport;
    19	use Spiral\Auth\Transport\HeaderTransport;
    20	use Spiral\Auth\TransportRegistry;
    21	use Spiral\Boot\Bootloader\Bootloader;
    22	use Spiral\Boot\EnvironmentInterface;
    23	use Spiral\Bootloader\Http\Exception\ContextualObjectNotFoundException;
    24	use Spiral\Bootloader\Http\Exception\InvalidRequestScopeException;
    25	use Spiral\Bootloader\Http\HttpBootloader;
    26	use Spiral\Config\ConfiguratorInterface;
    27	use Spiral\Config\Patch\Append;
    28	use Spiral\Core\Attribute\Singleton;
    29	use Spiral\Core\BinderInterface;
    30	use Spiral\Core\Config\Proxy;
    31	use Spiral\Core\Container\Autowire;
    32	use Spiral\Core\FactoryInterface;
    33	use Spiral\Framework\Spiral;
    34	use Spiral\Http\Config\HttpConfig;
    35	
    36	/**
    37	 * Enables Auth middleware and http transports to read and write tokens in PSR-7 request/response.
    38	 */
    39	#[Singleton]
    40	final class HttpAuthBootloader extends Bootloader
    41	{
    42	    public function __construct(
    43	        private readonly ConfiguratorInterface $config,
    44	    ) {}
    45	
    46	    public function defineDependencies(): array
    47	    {
    48	        return [
    49	            AuthBootloader::class,
    50	            HttpBootloader::class,
    51	        ];
    52	    }
    53	
    54	    public function defineBindings(): array
    55	    {
    56	        return [
    57	            AuthContextInterface::class => new Proxy(AuthContextInterface::class, false),
    58	        ];
    59	    }
    60	
    61	    public function defineSingletons(): array
    62	    {
    63	        return [
    64	            TransportRegistry::class => [self::class, 'transportRegistry'],
    65	            TokenStorageProviderInterface::class => TokenStorageProvider::class,
    66	            // Default token storage outside of HTTP scope
    67	            TokenStorageInterface::class => static fn(TokenStorageProviderInterface $provider): TokenStorageInterface
    68	                => $provider->getStorage(),
    69	        ];
    70	    }
    71	
    72	    public function init(EnvironmentInterface $env, BinderInterface $binder): void
    73	    {
    74	        $httpBinder = $binder->getBinder(Spiral::Http);
    75	        // Token storage from request attribute in HTTP scope
    76	        $httpBinder->bindSingleton(TokenStorageInterface::class, [self::class, 'getTokenStorage']);
    77	        $httpBinder->bind(
    78	            AuthContextInterface::class,
    79	            static fn(?ServerRequestInterface $request): AuthContextInterface =>
    80	                ($request ?? throw new InvalidRequestScopeException(AuthContextInterface::class))
    81	                    ->getAttribute(AuthMiddleware::ATTRIBUTE) ?? throw new ContextualObjectNotFoundException(
    82	                        AuthContextInterface::class,
    83	                        AuthMiddleware::ATTRIBUTE,
    84	                    ),
    85	        );
    86	        // Bind middleware to `http` scope
    87	        $httpBinder->bind(AuthMiddleware::class, AuthMiddleware::class);
    88	        $httpBinder->bind(AuthTransportMiddleware::class, AuthTransportMiddleware::class);
    89	        $httpBinder->bind(AuthTransportWithStorageMiddleware::class, AuthTransportWithStorageMiddleware::class);
    90	
    91	        $this->config->setDefaults(
    92	            AuthConfig::CONFIG,
    93	            [
    94	                'defaultTransport' => $env->get('AUTH_TOKEN_TRANSPORT', 'cookie'),
    95	                'defaultStorage' => $env->get('AUTH_TOKEN_STORAGE', 'session'),
    96	                'transports' => [
    97	                    'cookie' => $this->createDefaultCookieTransport(),
    98	                    'header' => new HeaderTransport(header: 'X-Auth-Token'),
    99	                ],
   100	                'storages' => [
   101	                    'session' => SessionTokenStorage::class,
   102	                ],
   103	            ],
   104	        );
   105	    }
   106	
   107	    /**
   108	     * Add new Http token transport.
   109	     *
   110	     * @param non-empty-string $name
   111	     * @param Autowire|HttpTransportInterface|class-string<HttpTransportInterface> $transport
   112	     */
   113	    public function addTransport(string $name, Autowire|HttpTransportInterface|string $transport): void
   114	    {
   115	        $this->config->modify(AuthConfig::CONFIG, new Append('transports', $name, $transport));
   116	    }
   117	
   118	    /**
   119	     * Add new Http token storage.
   120	     *
   121	     * @param non-empty-string $name
   122	     * @param Autowire|TokenStorageInterface|class-string<TokenStorageInterface> $storage
   123	     */
   124	    public function addTokenStorage(string $name, Autowire|TokenStorageInterface|string $storage): void
   125	    {
   126	        $this->config->modify(AuthConfig::CONFIG, new Append('storages', $name, $storage));
   127	    }
   128	
   129	    /**
   130	     * Creates default cookie transport when "transports" section is empty.
   131	     */
   132	    private function createDefaultCookieTransport(): CookieTransport
   133	    {
   134	        $config = $this->config->getConfig(HttpConfig::CONFIG);
   135	
   136	        return new CookieTransport('token', $config['basePath'] ?? '/');
   137	    }
   138	
   139	    /**
   140	     * @noRector RemoveUnusedPrivateMethodRector
   141	     */
   142	    private function transportRegistry(AuthConfig $config, FactoryInterface $factory): TransportRegistry
   143	    {
   144	        $registry = new TransportRegistry();
   145	        $registry->setDefaultTransport($config->getDefaultTransport());
   146	
   147	        foreach ($config->getTransports() as $name => $transport) {
   148	            if ($transport instanceof Autowire) {
   149	                $transport = $transport->resolve($factory);
   150	            }
   151	
   152	            $registry->setTransport($name, $transport);
   153	        }
   154	
   155	        return $registry;
   156	    }
   157	
   158	    /**
   159	     * Get default token storage from provider
   160	     */
   161	    private function getTokenStorage(
   162	        TokenStorageProviderInterface $provider,
   163	        ServerRequestInterface $request,
   164	    ): TokenStorageInterface {
   165	        return $request->getAttribute(AuthMiddleware::TOKEN_STORAGE_ATTRIBUTE) ?? $provider->getStorage();
   166	    }
   167	}

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/AuthHttp/src/Middleware/AuthMiddleware.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/AuthHttp/src/Middleware/AuthTransportWithStorageMiddleware.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Auth\Middleware;
     6	
     7	use Psr\EventDispatcher\EventDispatcherInterface;
     8	use Psr\Http\Message\ResponseInterface as Response;
     9	use Psr\Http\Message\ServerRequestInterface as Request;
    10	use Psr\Http\Server\MiddlewareInterface;
    11	use Psr\Http\Server\RequestHandlerInterface;
    12	use Spiral\Auth\ActorProviderInterface;
    13	use Spiral\Auth\AuthContext;
    14	use Spiral\Auth\AuthContextInterface;
    15	use Spiral\Auth\TokenStorageInterface;
    16	use Spiral\Auth\TransportRegistry;
    17	use Spiral\Core\Attribute\Scope;
    18	use Spiral\Core\ScopeInterface;
    19	
    20	/**
    21	 * Manages auth context scope.
    22	 */
    23	#[Scope('http')]
    24	final class AuthMiddleware implements MiddlewareInterface
    25	{
    26	    public const ATTRIBUTE = 'authContext';
    27	    public const TOKEN_STORAGE_ATTRIBUTE = 'tokenStorage';
    28	
    29	    /**
    30	     * @param ScopeInterface $scope Deprecated, will be removed in v4.0.
    31	     */
    32	    public function __construct(
    33	        private readonly ScopeInterface $scope,
    34	        private readonly ActorProviderInterface $actorProvider,
    35	        private readonly TokenStorageInterface $tokenStorage,
    36	        private readonly TransportRegistry $transportRegistry,
    37	        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    38	    ) {}
    39	
    40	    /**
    41	     * @throws \Throwable
    42	     */
    43	    public function process(Request $request, RequestHandlerInterface $handler): Response
    44	    {
    45	        $authContext = $this->initContext($request, new AuthContext($this->actorProvider, $this->eventDispatcher));
    46	
    47	        $response = $handler->handle(
    48	            $request
    49	                ->withAttribute(self::ATTRIBUTE, $authContext)
    50	                ->withAttribute(self::TOKEN_STORAGE_ATTRIBUTE, $this->tokenStorage),
    51	        );
    52	
    53	        return $this->closeContext($request, $response, $authContext);
    54	    }
    55	
    56	    private function initContext(Request $request, AuthContextInterface $authContext): AuthContextInterface
    57	    {
    58	        foreach ($this->transportRegistry->getTransports() as $name => $transport) {
    59	            $tokenID = $transport->fetchToken($request);
    60	            if ($tokenID === null) {
    61	                continue;
    62	            }
    63	
    64	            $token = $this->tokenStorage->load($tokenID);
    65	            if ($token === null) {
    66	                continue;
    67	            }
    68	
    69	            // found valid token
    70	            $authContext->start($token, $name);
    71	            return $authContext;
    72	        }
    73	
    74	        return $authContext;
    75	    }
    76	
    77	    private function closeContext(Request $request, Response $response, AuthContextInterface $authContext): Response
    78	    {
    79	        if ($authContext->getToken() === null) {
    80	            return $response;
    81	        }
    82	
    83	        $transport = $this->transportRegistry->getTransport($authContext->getTransport());
    84	
    85	        if ($authContext->isClosed()) {
    86	            $this->tokenStorage->delete($authContext->getToken());
    87	
    88	            return $transport->removeToken(
    89	                $request,
    90	                $response,
    91	                $authContext->getToken()->getID(),
    92	            );
    93	        }
    94	
    95	        return $transport->commitToken(
    96	            $request,
    97	            $response,
    98	            $authContext->getToken()->getID(),
    99	            $authContext->getToken()->getExpiresAt(),
   100	        );
   101	    }
   102	}

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Auth\Middleware;
     6	
     7	use Psr\EventDispatcher\EventDispatcherInterface;
     8	use Psr\Http\Message\ResponseInterface;
     9	use Psr\Http\Message\ServerRequestInterface;
    10	use Psr\Http\Server\MiddlewareInterface;
    11	use Psr\Http\Server\RequestHandlerInterface;
    12	use Spiral\Auth\ActorProviderInterface;
    13	use Spiral\Auth\TokenStorageProviderInterface;
    14	use Spiral\Auth\TransportRegistry;
    15	use Spiral\Core\Attribute\Scope;
    16	use Spiral\Core\ScopeInterface;
    17	
    18	/**
    19	 * Auth by specific transport.
    20	 */
    21	#[Scope('http')]
    22	final class AuthTransportWithStorageMiddleware implements MiddlewareInterface
    23	{
    24	    private readonly AuthTransportMiddleware $authMiddleware;
    25	
    26	    /**
    27	     * @param ScopeInterface $scope Deprecated, will be removed in v4.0.
    28	     */
    29	    public function __construct(
    30	        string $transportName,
    31	        ScopeInterface $scope,
    32	        ActorProviderInterface $actorProvider,
    33	        TokenStorageProviderInterface $tokenStorageProvider,
    34	        TransportRegistry $transportRegistry,
    35	        ?EventDispatcherInterface $eventDispatcher = null,
    36	        ?string $storage = null,
    37	    ) {
    38	        $this->authMiddleware = new AuthTransportMiddleware(
    39	            $transportName,
    40	            $scope,
    41	            $actorProvider,
    42	            $tokenStorageProvider->getStorage($storage),
    43	            $transportRegistry,
    44	            $eventDispatcher,
    45	        );
    46	    }
    47	
    48	    /**
    49	     * @throws \Throwable
    50	     */
    51	    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    52	    {
    53	        return $this->authMiddleware->process($request, $handler);
    54	    }
    55	}

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/AuthHttp/src/Middleware/AuthTransportMiddleware.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Auth\Middleware;
     6	
     7	use Psr\EventDispatcher\EventDispatcherInterface;
     8	use Psr\Http\Message\ResponseInterface;
     9	use Psr\Http\Message\ServerRequestInterface;
    10	use Psr\Http\Server\MiddlewareInterface;
    11	use Psr\Http\Server\RequestHandlerInterface;
    12	use Spiral\Auth\ActorProviderInterface;
    13	use Spiral\Auth\TokenStorageInterface;
    14	use Spiral\Auth\TransportRegistry;
    15	use Spiral\Core\Attribute\Scope;
    16	use Spiral\Core\ScopeInterface;
    17	
    18	/**
    19	 * Auth by specific transport.
    20	 */
    21	#[Scope('http')]
    22	final class AuthTransportMiddleware implements MiddlewareInterface
    23	{
    24	    private readonly AuthMiddleware $authMiddleware;
    25	
    26	    /**
    27	     * @param ScopeInterface $scope Deprecated, will be removed in v4.0.
    28	     */
    29	    public function __construct(
    30	        string $transportName,
    31	        ScopeInterface $scope,
    32	        ActorProviderInterface $actorProvider,
    33	        TokenStorageInterface $tokenStorage,
    34	        TransportRegistry $transportRegistry,
    35	        ?EventDispatcherInterface $eventDispatcher = null,
    36	    ) {
    37	        $this->authMiddleware = new AuthMiddleware(
    38	            $scope,
    39	            $actorProvider,
    40	            $tokenStorage,
    41	            $this->getTransportRegistry($transportRegistry, $transportName),
    42	            $eventDispatcher,
    43	        );
    44	    }
    45	
    46	    /**
    47	     * @throws \Throwable
    48	     */
    49	    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    50	    {
    51	        return $this->authMiddleware->process($request, $handler);
    52	    }
    53	
    54	    private function getTransportRegistry(TransportRegistry $registry, string $transportName): TransportRegistry
    55	    {
    56	        $transports = new TransportRegistry();
    57	        $transports->setDefaultTransport($transportName);
    58	        $transports->setTransport($transportName, $registry->getTransport($transportName));
    59	
    60	        return $transports;
    61	    }
    62	}

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/AuthHttp/src/Transport/HeaderTransport.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Auth\Transport;
     6	
     7	use Psr\Http\Message\ResponseInterface as Response;
     8	use Psr\Http\Message\ServerRequestInterface as Request;
     9	use Spiral\Auth\HttpTransportInterface;
    10	
    11	/**
    12	 * Reads and writes auth tokens via headers.
    13	 */
    14	final class HeaderTransport implements HttpTransportInterface
    15	{
    16	    public function __construct(
    17	        private readonly string $header = 'X-Auth-Token',
    18	        private readonly string $valueFormat = '%s',
    19	    ) {}
    20	
    21	    public function fetchToken(Request $request): ?string
    22	    {
    23	        if ($request->hasHeader($this->header)) {
    24	            return $this->extractToken($request);
    25	        }
    26	
    27	        return null;
    28	    }
    29	
    30	    public function commitToken(
    31	        Request $request,
    32	        Response $response,
    33	        string $tokenID,
    34	        ?\DateTimeInterface $expiresAt = null,
    35	    ): Response {
    36	        if ($request->hasHeader($this->header) && $this->extractToken($request) === $tokenID) {
    37	            return $response;
    38	        }
    39	
    40	        return $response->withAddedHeader($this->header, \sprintf($this->valueFormat, $tokenID));
    41	    }
    42	
    43	    public function removeToken(Request $request, Response $response, string $tokenID): Response
    44	    {
    45	        return $response;
    46	    }
    47	
    48	    private function extractToken(Request $request): ?string
    49	    {
    50	        $headerLine = $request->getHeaderLine($this->header);
    51	
    52	        if ($this->valueFormat !== '%s') {
    53	            [$token] = \sscanf($headerLine, $this->valueFormat);
    54	
    55	            return $token !== null ? (string) $token : null;
    56	        }
    57	
    58	        return $headerLine;
    59	    }
    60	}

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/AuthHttp/src/TransportRegistry.php | sed -n '1,200p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Auth/src/AuthContext.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Auth;
     6	
     7	use Spiral\Auth\Exception\TransportException;
     8	
     9	/**
    10	 * Manages list of transports by their names, manages token storage association.
    11	 */
    12	final class TransportRegistry
    13	{
    14	    /** @var HttpTransportInterface[] */
    15	    private array $transports = [];
    16	
    17	    private ?string $default = null;
    18	
    19	    public function setDefaultTransport(string $name): void
    20	    {
    21	        $this->default = $name;
    22	    }
    23	
    24	    public function setTransport(string $name, HttpTransportInterface $transport): void
    25	    {
    26	        $this->transports[$name] = $transport;
    27	    }
    28	
    29	    public function getTransport(?string $name = null): HttpTransportInterface
    30	    {
    31	        $name ??= $this->default;
    32	
    33	        if (!isset($this->transports[$name])) {
    34	            throw new TransportException(\sprintf('Undefined auth transport %s', $name));
    35	        }
    36	
    37	        return $this->transports[$name];
    38	    }
    39	
    40	    /**
    41	     * @return HttpTransportInterface[]
    42	     */
    43	    public function getTransports(): array
    44	    {
    45	        return $this->transports;
    46	    }
    47	}

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Auth;
     6	
     7	use Psr\EventDispatcher\EventDispatcherInterface;
     8	use Spiral\Auth\Event\Authenticated;
     9	use Spiral\Auth\Event\Logout;
    10	
    11	final class AuthContext implements AuthContextInterface
    12	{
    13	    private ?TokenInterface $token = null;
    14	    private ?object $actor = null;
    15	    private ?string $transport = null;
    16	    private bool $closed = false;
    17	
    18	    public function __construct(
    19	        private readonly ActorProviderInterface $actorProvider,
    20	        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    21	    ) {}
    22	
    23	    public function start(TokenInterface $token, ?string $transport = null): void
    24	    {
    25	        $this->closed = false;
    26	        $this->actor = null;
    27	        $this->token = $token;
    28	        $this->transport = $transport;
    29	
    30	        $this->eventDispatcher?->dispatch(new Authenticated($token, $transport));
    31	    }
    32	
    33	    public function getToken(): ?TokenInterface
    34	    {
    35	        return $this->token;
    36	    }
    37	
    38	    public function getTransport(): ?string
    39	    {
    40	        return $this->transport;
    41	    }
    42	
    43	    public function getActor(): ?object
    44	    {
    45	        if ($this->closed) {
    46	            return null;
    47	        }
    48	
    49	        if ($this->actor === null && $this->token !== null) {
    50	            $this->actor = $this->actorProvider->getActor($this->token);
    51	        }
    52	
    53	        return $this->actor;
    54	    }
    55	
    56	    public function close(): void
    57	    {
    58	        // Store for Event Dispatcher
    59	        $actor = $this->actor;
    60	
    61	        $this->closed = true;
    62	        $this->actor = null;
    63	
    64	        /** The {@see Logout} event should be processed after state reset. */
    65	        $this->eventDispatcher?->dispatch(new Logout($actor, $this->transport));
    66	    }
    67	
    68	    public function isClosed(): bool
    69	    {
    70	        return $this->closed;
    71	    }
    72	}

exec
/bin/zsh -lc "nl -ba app/src/Shared/Infrastructure/Cache/RedisCacheStorage.php | sed -n '1,260p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Shared\Infrastructure\Cache;
     6	
     7	use Psr\SimpleCache\CacheInterface;
     8	use Symfony\Component\Cache\Adapter\RedisAdapter;
     9	use Symfony\Component\Cache\Psr16Cache;
    10	
    11	final class RedisCacheStorage implements CacheInterface
    12	{
    13	    private readonly CacheInterface $cache;
    14	
    15	    public function __construct(
    16	        string $dsn = 'redis://redis:6379/0',
    17	        string $namespace = 'yoga_loka_cache',
    18	        int $defaultLifetime = 0,
    19	    ) {
    20	        $this->cache = new Psr16Cache(
    21	            new RedisAdapter(
    22	                redis: RedisAdapter::createConnection(dsn: $dsn),
    23	                namespace: $namespace,
    24	                defaultLifetime: $defaultLifetime,
    25	            ),
    26	        );
    27	    }
    28	
    29	    #[\Override]
    30	    public function get(string $key, mixed $default = null): mixed
    31	    {
    32	        return $this->cache->get(key: $key, default: $default);
    33	    }
    34	
    35	    #[\Override]
    36	    public function set(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
    37	    {
    38	        return $this->cache->set(key: $key, value: $value, ttl: $ttl);
    39	    }
    40	
    41	    #[\Override]
    42	    public function delete(string $key): bool
    43	    {
    44	        return $this->cache->delete($key);
    45	    }
    46	
    47	    #[\Override]
    48	    public function clear(): bool
    49	    {
    50	        return $this->cache->clear();
    51	    }
    52	
    53	    /**
    54	     * @param iterable<string> $keys
    55	     */
    56	    #[\Override]
    57	    public function getMultiple(iterable $keys, mixed $default = null): iterable
    58	    {
    59	        return $this->cache->getMultiple(keys: $keys, default: $default);
    60	    }
    61	
    62	    /**
    63	     * @param iterable<string, mixed> $values
    64	     */
    65	    #[\Override]
    66	    public function setMultiple(iterable $values, int|\DateInterval|null $ttl = null): bool
    67	    {
    68	        return $this->cache->setMultiple(values: $values, ttl: $ttl);
    69	    }
    70	
    71	    #[\Override]
    72	    public function deleteMultiple(iterable $keys): bool
    73	    {
    74	        return $this->cache->deleteMultiple($keys);
    75	    }
    76	
    77	    #[\Override]
    78	    public function has(string $key): bool
    79	    {
    80	        return $this->cache->has($key);
    81	    }
    82	}

exec
/bin/zsh -lc "rg -n \"CacheInterface|cache\" app/config app/src/Shared/Infrastructure/Configuration tests -g '*.php'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
tests/warmup.php:43:// Принудительно получаем схему, чтобы Cycle скомпилировал и записал её в cache.
tests/warmup.php:46:$cacheFile = $runtime . '/cache/cycle.php';
tests/warmup.php:48:if (!\is_file($cacheFile)) {
tests/warmup.php:49:    \fwrite(\STDERR, \sprintf("[warmup] Cycle schema cache не создан: %s\n", $cacheFile));
tests/warmup.php:55:    "[warmup] Cycle schema cache готов: %s (%d байт)\n",
tests/warmup.php:56:    $cacheFile,
tests/warmup.php:57:    (int) \filesize($cacheFile),
tests/TestRuntime.php:14: * `tests/warmup.php`, чтобы прогретый Cycle schema cache совпадал с тем, что
tests/TestRuntime.php:30:     * Директории для тестового kernel: один runtime и cache внутри него,
tests/TestRuntime.php:31:     * чтобы `cache/cycle.php` лежал в каталоге текущего worker-а.
tests/TestRuntime.php:42:            DirectoryAlias::Cache->value => $runtime . '/cache',
app/config/cycle.php:19:        'cache' => \env('CYCLE_SCHEMA_CACHE', true),
app/src/Shared/Infrastructure/Configuration/Cycle/CycleSchemaConfig.php:14:        public bool $cache,
tests/Feature/Shared/Infrastructure/Cache/RedisCacheStorageTest.php:22:        $cache = new RedisCacheStorage(
tests/Feature/Shared/Infrastructure/Cache/RedisCacheStorageTest.php:24:            namespace: 'yoga_loka_test_cache',
tests/Feature/Shared/Infrastructure/Cache/RedisCacheStorageTest.php:26:        $cache->clear();
tests/Feature/Shared/Infrastructure/Cache/RedisCacheStorageTest.php:28:        self::assertTrue($cache->set(key: 'single', value: 'value'));
tests/Feature/Shared/Infrastructure/Cache/RedisCacheStorageTest.php:29:        self::assertSame('value', $cache->get(key: 'single'));
tests/Feature/Shared/Infrastructure/Cache/RedisCacheStorageTest.php:30:        self::assertTrue($cache->has(key: 'single'));
tests/Feature/Shared/Infrastructure/Cache/RedisCacheStorageTest.php:32:        self::assertTrue($cache->delete(key: 'single'));
tests/Feature/Shared/Infrastructure/Cache/RedisCacheStorageTest.php:33:        self::assertFalse($cache->has(key: 'single'));
tests/Feature/Shared/Infrastructure/Cache/RedisCacheStorageTest.php:34:        self::assertSame('fallback', $cache->get(key: 'single', default: 'fallback'));
tests/Feature/Shared/Infrastructure/Cache/RedisCacheStorageTest.php:36:        self::assertTrue($cache->setMultiple(values: ['alpha' => 1, 'beta' => 2]));
tests/Feature/Shared/Infrastructure/Cache/RedisCacheStorageTest.php:39:            \iterator_to_array($cache->getMultiple(keys: ['alpha', 'beta'])),
tests/Feature/Shared/Infrastructure/Cache/RedisCacheStorageTest.php:41:        self::assertTrue($cache->deleteMultiple(keys: ['alpha', 'beta']));
tests/Feature/Shared/Infrastructure/Cache/RedisCacheStorageTest.php:42:        self::assertFalse($cache->has(key: 'alpha'));
tests/Feature/Shared/Infrastructure/Cache/RedisCacheStorageTest.php:44:        self::assertTrue($cache->clear());
tests/Kernel/Shared/Infrastructure/Configuration/ComplexConfigBindingTest.php:66:        self::assertIsBool($cycleConfig->schema->cache);
tests/Kernel/Shared/Infrastructure/Configuration/ComplexConfigBindingTest.php:67:        self::assertSame($container->get(SpiralCycleConfig::class)->cacheSchema(), $cycleConfig->schema->cache);
tests/Kernel/Shared/Infrastructure/Configuration/ComplexConfigBindingTest.php:125:        self::assertIsBool($container->get(SpiralCycleConfig::class)->cacheSchema());
tests/Kernel/Shared/Infrastructure/Configuration/ConfigShapeTest.php:21:                'cache',
tests/Kernel/Shared/Infrastructure/Configuration/ConfigShapeTest.php:61:        $cacheConfig = $configMapper->map(section: CacheConfig::configName(), targetClass: CacheConfig::class);
tests/Kernel/Shared/Infrastructure/Configuration/ConfigShapeTest.php:64:        self::assertSame('local', $cacheConfig->default);
app/config/cache.php:12: * @link https://spiral.dev/docs/basics-cache
app/config/cache.php:47:            'path' => \directory('runtime') . 'cache',
app/config/cache.php:53:            'namespace' => \env('REDIS_CACHE_NAMESPACE', 'yoga_loka_cache'),
app/src/Shared/Infrastructure/Configuration/Cache/CacheConfig.php:13:        return 'cache';
tests/Unit/Shared/Infrastructure/Configuration/ConfigMapperTest.php:41:                    'path' => '/tmp/cache',
tests/Unit/Shared/Infrastructure/Configuration/ConfigMapperTest.php:57:        self::assertSame('/tmp/cache', $config->storages['file']->path);
tests/Unit/Shared/Infrastructure/Configuration/ConfigMapperTest.php:71:        $this->expectExceptionMessage('Не удалось преобразовать раздел конфигурации `cache`');
tests/Unit/Shared/Infrastructure/Configuration/ComplexConfigMapperTest.php:72:                'cache' => true,
tests/Unit/Shared/Infrastructure/Configuration/ComplexConfigMapperTest.php:96:        self::assertTrue($config->schema->cache);
tests/Unit/Shared/Infrastructure/Configuration/ComplexConfigMapperTest.php:111:                'cache' => true,
tests/Unit/Shared/Infrastructure/Configuration/ComplexConfigMapperTest.php:141:                    'cache' => true,
tests/Unit/Shared/Infrastructure/Configuration/ComplexConfigMapperTest.php:351:                    'cache' => ['secret-password'],
tests/Unit/Shared/Infrastructure/Configuration/ComplexConfigMapperTest.php:363:            'schema.cache',

exec
/bin/zsh -lc "rg -n \"ResponseFactoryInterface|JsonResponse|withStatus|write\\(|DataResponse|HttpStatus|CacheInterface|Psr\\\\SimpleCache\" app/src tests packages -g '*.php'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "rg -n \"interface CacheInterface|class .*Cache\" vendor/spiral/framework/src/Cache vendor/psr/simple-cache -g '*.php' | sed -n '1,200p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
vendor/spiral/framework/src/Cache/src/Event/KeyDeleted.php:10:final class KeyDeleted extends CacheEvent {}
vendor/spiral/framework/src/Cache/src/Event/KeyDeleteFailed.php:10:final class KeyDeleteFailed extends CacheEvent {}
vendor/spiral/framework/src/Cache/src/Event/KeyWritten.php:10:final class KeyWritten extends CacheEvent
vendor/spiral/framework/src/Cache/src/Event/CacheMissed.php:10:final class CacheMissed extends CacheEvent {}
vendor/spiral/framework/src/Cache/src/Config/CacheConfig.php:10:final class CacheConfig extends InjectableConfig
vendor/spiral/framework/src/Cache/src/CacheRepository.php:22:class CacheRepository implements CacheInterface
vendor/spiral/framework/src/Cache/src/Event/CacheRetrieving.php:10:final class CacheRetrieving extends CacheEvent {}
vendor/spiral/framework/src/Cache/src/Event/CacheHit.php:10:final class CacheHit extends CacheEvent
vendor/psr/simple-cache/src/CacheInterface.php:5:interface CacheInterface
vendor/spiral/framework/src/Cache/src/Exception/CacheException.php:7:class CacheException extends \Exception {}
vendor/spiral/framework/src/Cache/src/Event/KeyWriting.php:10:final class KeyWriting extends CacheEvent
vendor/spiral/framework/src/Cache/src/Event/KeyDeleting.php:10:final class KeyDeleting extends CacheEvent {}
vendor/spiral/framework/src/Cache/src/Core/CacheInjector.php:17:final class CacheInjector implements InjectorInterface
vendor/spiral/framework/src/Cache/src/Core/CacheInjector.php:53:        if ($connection::class === CacheRepository::class) {
vendor/spiral/framework/src/Cache/src/Event/CacheEvent.php:10:abstract class CacheEvent
vendor/spiral/framework/src/Cache/src/Storage/FileStorage.php:11:final class FileStorage implements CacheInterface
vendor/spiral/framework/src/Cache/src/Exception/InvalidArgumentException.php:7:class InvalidArgumentException extends CacheException {}
vendor/spiral/framework/src/Cache/src/CacheManager.php:14:class CacheManager implements CacheStorageProviderInterface, CacheStorageRegistryInterface
vendor/spiral/framework/src/Cache/src/Event/KeyWriteFailed.php:10:final class KeyWriteFailed extends CacheEvent
vendor/spiral/framework/src/Cache/src/Storage/ArrayStorage.php:9:class ArrayStorage implements CacheInterface
vendor/spiral/framework/src/Cache/src/Bootloader/CacheBootloader.php:24:final class CacheBootloader extends Bootloader
vendor/spiral/framework/src/Cache/src/Bootloader/CacheBootloader.php:27:        CacheStorageRegistryInterface::class => CacheManager::class,
vendor/spiral/framework/src/Cache/src/Bootloader/CacheBootloader.php:28:        CacheStorageProviderInterface::class => CacheManager::class,
vendor/spiral/framework/src/Cache/src/Bootloader/CacheBootloader.php:29:        CacheManager::class => [self::class, 'initCacheManager'],

 succeeded in 503ms:
packages/spiral-openapi/vendor/symfony/console/Output/ConsoleSectionOutput.php:85:    public function overwrite(string|iterable $message): void
packages/spiral-openapi/vendor/symfony/console/Output/Output.php:105:        $this->write($messages, true, $options);
packages/spiral-openapi/vendor/symfony/console/Output/Output.php:108:    public function write(string|iterable $messages, bool $newline = false, int $options = self::OUTPUT_NORMAL): void
packages/spiral-openapi/vendor/symfony/console/Output/OutputInterface.php:41:    public function write(string|iterable $messages, bool $newline = false, int $options = 0): void;
packages/spiral-cqrs/vendor/symfony/mime/Crypto/SMime.php:36:            fwrite($stream, $chunk);
packages/spiral-cqrs/vendor/symfony/mime/CharacterStream.php:95:                $this->write($read);
packages/spiral-cqrs/vendor/symfony/mime/CharacterStream.php:98:            $this->write($input);
packages/spiral-cqrs/vendor/symfony/mime/CharacterStream.php:152:    public function write(string $chars): void
packages/spiral-openapi/vendor/symfony/console/Cursor.php:39:        $this->output->write(\sprintf("\x1b[%dA", $lines));
packages/spiral-openapi/vendor/symfony/console/Cursor.php:49:        $this->output->write(\sprintf("\x1b[%dB", $lines));
packages/spiral-openapi/vendor/symfony/console/Cursor.php:59:        $this->output->write(\sprintf("\x1b[%dC", $columns));
packages/spiral-openapi/vendor/symfony/console/Cursor.php:69:        $this->output->write(\sprintf("\x1b[%dD", $columns));
packages/spiral-openapi/vendor/symfony/console/Cursor.php:79:        $this->output->write(\sprintf("\x1b[%dG", $column));
packages/spiral-openapi/vendor/symfony/console/Cursor.php:89:        $this->output->write(\sprintf("\x1b[%d;%dH", $row + 1, $column));
packages/spiral-openapi/vendor/symfony/console/Cursor.php:99:        $this->output->write("\x1b7");
packages/spiral-openapi/vendor/symfony/console/Cursor.php:109:        $this->output->write("\x1b8");
packages/spiral-openapi/vendor/symfony/console/Cursor.php:119:        $this->output->write("\x1b[?25l");
packages/spiral-openapi/vendor/symfony/console/Cursor.php:129:        $this->output->write("\x1b[?25h\x1b[?0c");
packages/spiral-openapi/vendor/symfony/console/Cursor.php:141:        $this->output->write("\x1b[2K");
packages/spiral-openapi/vendor/symfony/console/Cursor.php:151:        $this->output->write("\x1b[K");
packages/spiral-openapi/vendor/symfony/console/Cursor.php:163:        $this->output->write("\x1b[0J");
packages/spiral-openapi/vendor/symfony/console/Cursor.php:175:        $this->output->write("\x1b[2J");
packages/spiral-openapi/vendor/symfony/console/Cursor.php:194:        @fwrite($this->input, "\033[6n");
packages/spiral-api-errors/vendor/spiral/framework/src/Http/src/ResponseWrapper.php:7:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-api-errors/vendor/spiral/framework/src/Http/src/ResponseWrapper.php:25:        private readonly ResponseFactoryInterface $responseFactory,
packages/spiral-api-errors/vendor/spiral/framework/src/Http/src/ResponseWrapper.php:99:        $response->getBody()->write($html);
packages/spiral-api-errors/vendor/spiral/framework/src/Http/src/CallableHandler.php:7:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-api-errors/vendor/spiral/framework/src/Http/src/CallableHandler.php:25:        private readonly ResponseFactoryInterface $responseFactory,
packages/spiral-api-errors/vendor/spiral/framework/src/Http/src/CallableHandler.php:70:                $result->getBody()->write($output);
packages/spiral-api-errors/vendor/spiral/framework/src/Http/src/CallableHandler.php:79:            $response->getBody()->write((string) $result);
packages/spiral-api-errors/vendor/spiral/framework/src/Http/src/CallableHandler.php:83:        $response->getBody()->write($output);
packages/spiral-api-errors/vendor/spiral/framework/src/Http/src/ErrorHandler/PlainRenderer.php:7:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-api-errors/vendor/spiral/framework/src/Http/src/ErrorHandler/PlainRenderer.php:18:        private readonly ResponseFactoryInterface $responseFactory,
packages/spiral-api-errors/vendor/spiral/framework/src/Http/src/ErrorHandler/PlainRenderer.php:28:            $response->getBody()->write(\json_encode(['status' => $code]));
packages/spiral-api-errors/vendor/spiral/framework/src/Http/src/ErrorHandler/PlainRenderer.php:30:            $response->getBody()->write("Error code: {$code}");
packages/spiral-api-errors/vendor/symfony/mime/RawMessage.php:80:                fwrite($message, $chunk);
packages/spiral-api-errors/vendor/spiral/framework/src/Http/src/Http.php:9:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-api-errors/vendor/spiral/framework/src/Http/src/Http.php:33:        private readonly ResponseFactoryInterface $responseFactory,
packages/spiral-api-errors/vendor/spiral/framework/src/Http/src/Stream/GeneratorStream.php:81:    public function write($string): int
packages/spiral-api-errors/vendor/spiral/framework/src/Http/src/Traits/JsonTrait.php:27:        $response->getBody()->write(\json_encode($payload));
packages/spiral-api-errors/vendor/spiral/framework/src/Http/src/Traits/JsonTrait.php:29:        return $response->withStatus($code)->withHeader('Content-Type', 'application/json');
packages/spiral-api-errors/vendor/spiral/framework/src/Broadcasting/src/Bootloader/WebsocketsBootloader.php:8:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-api-errors/vendor/spiral/framework/src/Broadcasting/src/Bootloader/WebsocketsBootloader.php:29:            ResponseFactoryInterface $responseFactory,
packages/spiral-api-errors/vendor/spiral/framework/src/Http/src/Middleware/ErrorHandlerMiddleware.php:7:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-api-errors/vendor/spiral/framework/src/Http/src/Middleware/ErrorHandlerMiddleware.php:32:        private readonly ResponseFactoryInterface $responseFactory,
packages/spiral-api-errors/vendor/spiral/framework/src/Http/src/Middleware/ErrorHandlerMiddleware.php:75:        $response->getBody()->write(
packages/spiral-api-errors/vendor/spiral/framework/src/Csrf/src/Middleware/StrictCsrfFirewall.php:7:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-api-errors/vendor/spiral/framework/src/Csrf/src/Middleware/StrictCsrfFirewall.php:20:    public function __construct(ResponseFactoryInterface $responseFactory)
packages/spiral-api-errors/vendor/spiral/framework/src/Broadcasting/src/Middleware/AuthorizationMiddleware.php:8:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-api-errors/vendor/spiral/framework/src/Broadcasting/src/Middleware/AuthorizationMiddleware.php:22:        private readonly ResponseFactoryInterface $responseFactory,
packages/spiral-api-errors/vendor/spiral/framework/src/Csrf/src/Middleware/CsrfFirewall.php:7:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-api-errors/vendor/spiral/framework/src/Csrf/src/Middleware/CsrfFirewall.php:35:        private readonly ResponseFactoryInterface $responseFactory,
packages/spiral-api-errors/vendor/symfony/mime/Crypto/SMime.php:36:            fwrite($stream, $chunk);
packages/spiral-api-errors/vendor/symfony/mime/CharacterStream.php:95:                $this->write($read);
packages/spiral-api-errors/vendor/symfony/mime/CharacterStream.php:98:            $this->write($input);
packages/spiral-api-errors/vendor/symfony/mime/CharacterStream.php:152:    public function write(string $chars): void
packages/spiral-api-errors/vendor/spiral/framework/src/Storage/src/Bucket/WritableTrait.php:23:            return $this->write($pathname, '', $config);
packages/spiral-api-errors/vendor/spiral/framework/src/Storage/src/Bucket/WritableTrait.php:29:    public function write(string $pathname, mixed $content, array $config = []): FileInterface
packages/spiral-api-errors/vendor/spiral/framework/src/Storage/src/Bucket/WritableTrait.php:39:                    $fs->write($pathname, (string) $content, $config);
packages/spiral-api-errors/vendor/spiral/framework/src/Storage/src/Bucket/WritableTrait.php:91:        return $storage->write($destination, $this->getStream($source), $config);
packages/spiral-api-errors/vendor/spiral/framework/src/Storage/src/Bucket/WritableTrait.php:112:        $result = $storage->write($destination, $this->getStream($source), $config);
packages/spiral-api-errors/vendor/spiral/framework/src/Storage/src/Bucket/WritableInterface.php:33:    public function write(string $pathname, mixed $content, array $config = []): FileInterface;
packages/spiral-api-errors/vendor/spiral/framework/src/Storage/src/Storage/WritableTrait.php:29:    public function write(string|\Stringable $id, mixed $content, array $config = []): FileInterface
packages/spiral-api-errors/vendor/spiral/framework/src/Storage/src/Storage/WritableTrait.php:33:        return $this->bucket($name)->write($pathname, $content, $config);
packages/spiral-api-errors/vendor/spiral/framework/src/Storage/src/Storage/WritableInterface.php:31:     * {@see BucketInterface::write()}
packages/spiral-api-errors/vendor/spiral/framework/src/Storage/src/Storage/WritableInterface.php:37:    public function write(string|\Stringable $id, mixed $content, array $config = []): FileInterface;
packages/spiral-openapi/vendor/symfony/mime/RawMessage.php:80:                fwrite($message, $chunk);
packages/spiral-api-errors/vendor/spiral/framework/src/Scaffolder/src/Declaration/ConfigDeclaration.php:109:        $this->files->write(
packages/spiral-api-errors/vendor/spiral/framework/src/Storage/src/File/WritableTrait.php:32:    public function write(mixed $content, array $config = []): FileInterface
packages/spiral-api-errors/vendor/spiral/framework/src/Storage/src/File/WritableTrait.php:34:        return $this->getBucket()->write($this->getPathname(), $content, $config);
packages/spiral-api-errors/vendor/spiral/framework/src/Storage/src/File/WritableInterface.php:26:     * {@see BucketInterface::write()}
packages/spiral-api-errors/vendor/spiral/framework/src/Storage/src/File/WritableInterface.php:31:    public function write(mixed $content, array $config = []): FileInterface;
packages/spiral-api-errors/vendor/spiral/framework/src/Scaffolder/src/Command/AbstractCommand.php:76:        (new Writer($this->files))->write($filename, $declaration->getFile());
packages/phpstan-strict-rules/vendor/phpunit/phpunit/src/Runner/Baseline/Writer.php:31:    public function write(string $baselineFile, Baseline $baseline): void
packages/spiral-api-errors/vendor/spiral/framework/src/Exceptions/src/ExceptionHandler.php:115:            \fwrite($this->output, $this->render($e, verbosity: $this->verbosity, format: $format));
packages/spiral-cqrs/vendor/symfony/mailer/Transport/Smtp/SmtpTransport.php:185:        $this->stream->write($command);
packages/spiral-cqrs/vendor/symfony/mailer/Transport/Smtp/SmtpTransport.php:212:                    $this->stream->write($chunk, false);
packages/spiral-cqrs/vendor/symfony/mailer/Transport/Smtp/Stream/AbstractStream.php:37:    public function write(string $bytes, bool $debug = true): void
packages/spiral-cqrs/vendor/symfony/mailer/Transport/Smtp/Stream/AbstractStream.php:49:            $bytesWritten = @fwrite($this->in, substr($bytes, $totalBytesWritten));
packages/spiral-openapi/vendor/symfony/mime/Crypto/SMime.php:36:            fwrite($stream, $chunk);
packages/spiral-cqrs/vendor/symfony/mailer/Transport/SendmailTransport.php:120:            $this->stream->write($chunk, false);
packages/spiral-openapi/vendor/symfony/mime/CharacterStream.php:95:                $this->write($read);
packages/spiral-openapi/vendor/symfony/mime/CharacterStream.php:98:            $this->write($input);
packages/spiral-openapi/vendor/symfony/mime/CharacterStream.php:152:    public function write(string $chars): void
packages/spiral-api-errors/vendor/spiral/framework/src/Translator/src/Catalogue/CatalogueManager.php:19:    private readonly CacheInterface $cache;
packages/spiral-api-errors/vendor/spiral/framework/src/Translator/src/Catalogue/CatalogueManager.php:26:        ?CacheInterface $cache = null,
packages/spiral-api-errors/vendor/spiral/framework/src/Translator/src/Catalogue/CacheInterface.php:7:interface CacheInterface
packages/spiral-api-errors/vendor/spiral/framework/src/Translator/src/Catalogue/NullCache.php:7:final class NullCache implements CacheInterface
packages/spiral-api-errors/vendor/spiral/framework/src/Prototype/src/Command/DumpCommand.php:32:        $this->write('Updating <fg=yellow>PrototypeTrait</fg=yellow> DOCComment... ');
packages/spiral-api-errors/vendor/spiral/framework/src/Prototype/src/Command/DumpCommand.php:41:            $writer->write($ref->getFileName(), $file);
packages/spiral-api-errors/vendor/spiral/framework/src/Prototype/src/Command/DumpCommand.php:43:            $this->write('<fg=red>' . $e->getMessage() . "</fg=red>\n");
packages/spiral-api-errors/vendor/spiral/framework/src/Prototype/src/Command/DumpCommand.php:48:        $this->write("<fg=green>complete</fg=green>\n");
packages/spiral-api-errors/vendor/spiral/framework/src/Prototype/src/Bootloader/PrototypeBootloader.php:76:        'cache' => \Psr\SimpleCache\CacheInterface::class,
packages/spiral-cqrs/vendor/symfony/translation/Command/TranslationPushCommand.php:135:            $provider->write($localTranslations);
packages/spiral-cqrs/vendor/symfony/translation/Command/TranslationPushCommand.php:160:        $provider->write($translationsToWrite);
packages/spiral-cqrs/vendor/symfony/translation/Command/TranslationPullCommand.php:156:                $this->writer->write($operation->getResult(), $format, $writeOptions);
packages/spiral-cqrs/vendor/symfony/translation/Command/TranslationPullCommand.php:170:            $this->writer->write($catalogue, $format, $writeOptions);
packages/spiral-api-errors/vendor/symfony/mailer/Transport/Smtp/SmtpTransport.php:185:        $this->stream->write($command);
packages/spiral-api-errors/vendor/symfony/mailer/Transport/Smtp/SmtpTransport.php:212:                    $this->stream->write($chunk, false);
packages/spiral-api-errors/vendor/symfony/mailer/Transport/Smtp/Stream/AbstractStream.php:37:    public function write(string $bytes, bool $debug = true): void
packages/spiral-api-errors/vendor/symfony/mailer/Transport/Smtp/Stream/AbstractStream.php:49:            $bytesWritten = @fwrite($this->in, substr($bytes, $totalBytesWritten));
packages/spiral-cqrs/vendor/symfony/translation/Writer/TranslationWriterInterface.php:32:    public function write(MessageCatalogue $catalogue, string $format, array $options = []): void;
packages/spiral-cqrs/vendor/symfony/translation/Writer/TranslationWriter.php:55:    public function write(MessageCatalogue $catalogue, string $format, array $options = []): void
packages/spiral-api-errors/vendor/symfony/mailer/Transport/SendmailTransport.php:120:            $this->stream->write($chunk, false);
packages/spiral-cqrs/vendor/symfony/translation/Provider/ProviderInterface.php:25:    public function write(TranslatorBagInterface $translatorBag): void;
packages/spiral-cqrs/vendor/symfony/translation/Provider/NullProvider.php:27:    public function write(TranslatorBagInterface $translatorBag, bool $override = false): void
packages/spiral-cqrs/vendor/symfony/translation/Provider/FilteringProvider.php:36:    public function write(TranslatorBagInterface $translatorBag): void
packages/spiral-cqrs/vendor/symfony/translation/Provider/FilteringProvider.php:38:        $this->provider->write($translatorBag);
packages/spiral-api-errors/vendor/phpunit/phpunit/src/Runner/Baseline/Writer.php:31:    public function write(string $baselineFile, Baseline $baseline): void
packages/spiral-cqrs/vendor/symfony/translation/Translator.php:16:use Symfony\Component\Config\ConfigCacheInterface;
packages/spiral-cqrs/vendor/symfony/translation/Translator.php:290:            function (ConfigCacheInterface $cache) use ($locale) {
packages/spiral-cqrs/vendor/symfony/translation/Translator.php:304:    private function dumpCatalogue(string $locale, ConfigCacheInterface $cache): void
packages/spiral-cqrs/vendor/symfony/translation/Translator.php:325:        $cache->write($content, $this->catalogues[$locale]->getResources());
packages/spiral-api-errors/vendor/symfony/translation/Provider/ProviderInterface.php:25:    public function write(TranslatorBagInterface $translatorBag): void;
packages/spiral-api-errors/vendor/symfony/translation/Provider/NullProvider.php:27:    public function write(TranslatorBagInterface $translatorBag, bool $override = false): void
packages/spiral-api-errors/vendor/symfony/translation/Provider/FilteringProvider.php:36:    public function write(TranslatorBagInterface $translatorBag): void
packages/spiral-api-errors/vendor/symfony/translation/Provider/FilteringProvider.php:38:        $this->provider->write($translatorBag);
packages/spiral-api-errors/vendor/symfony/translation/Translator.php:16:use Symfony\Component\Config\ConfigCacheInterface;
packages/spiral-api-errors/vendor/symfony/translation/Translator.php:290:            function (ConfigCacheInterface $cache) use ($locale) {
packages/spiral-api-errors/vendor/symfony/translation/Translator.php:304:    private function dumpCatalogue(string $locale, ConfigCacheInterface $cache): void
packages/spiral-api-errors/vendor/symfony/translation/Translator.php:325:        $cache->write($content, $this->catalogues[$locale]->getResources());
packages/spiral-openapi/vendor/symfony/mailer/Transport/Smtp/SmtpTransport.php:185:        $this->stream->write($command);
packages/spiral-openapi/vendor/symfony/mailer/Transport/Smtp/SmtpTransport.php:212:                    $this->stream->write($chunk, false);
packages/spiral-openapi/vendor/symfony/mailer/Transport/Smtp/Stream/AbstractStream.php:37:    public function write(string $bytes, bool $debug = true): void
packages/spiral-openapi/vendor/symfony/mailer/Transport/Smtp/Stream/AbstractStream.php:49:            $bytesWritten = @fwrite($this->in, substr($bytes, $totalBytesWritten));
packages/spiral-openapi/vendor/symfony/mailer/Transport/SendmailTransport.php:120:            $this->stream->write($chunk, false);
packages/spiral-api-errors/vendor/spiral/framework/src/Streams/src/StreamWrapper.php:209:    public function stream_write(string $data): int
packages/spiral-api-errors/vendor/spiral/framework/src/Streams/src/StreamWrapper.php:215:        return $this->stream->write($data);
packages/spiral-api-errors/vendor/symfony/translation/Command/TranslationPushCommand.php:135:            $provider->write($localTranslations);
packages/spiral-api-errors/vendor/symfony/translation/Command/TranslationPushCommand.php:160:        $provider->write($translationsToWrite);
packages/spiral-api-errors/vendor/symfony/translation/Command/TranslationPullCommand.php:156:                $this->writer->write($operation->getResult(), $format, $writeOptions);
packages/spiral-api-errors/vendor/symfony/translation/Command/TranslationPullCommand.php:170:            $this->writer->write($catalogue, $format, $writeOptions);
packages/spiral-api-errors/vendor/spiral/framework/src/Bridge/Stempler/src/StemplerCache.php:20:    public function write(string $key, string $content, array $paths = []): void
packages/spiral-api-errors/vendor/spiral/framework/src/Bridge/Stempler/src/StemplerCache.php:23:        $this->files->write(
packages/spiral-api-errors/vendor/spiral/framework/src/Bridge/Stempler/src/StemplerCache.php:31:        $this->files->write(
packages/spiral-api-errors/vendor/spiral/framework/src/Bridge/Stempler/src/StemplerEngine.php:115:                $this->cache->write(
packages/spiral-api-errors/vendor/symfony/translation/Writer/TranslationWriterInterface.php:32:    public function write(MessageCatalogue $catalogue, string $format, array $options = []): void;
packages/spiral-api-errors/vendor/symfony/translation/Writer/TranslationWriter.php:55:    public function write(MessageCatalogue $catalogue, string $format, array $options = []): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/SyslogUdp/UdpSocket.php:31:    public function write(string $line, string $header = ""): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/CubeHandler.php:112:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/RedisHandler.php:56:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/CouchDBHandler.php:67:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/AbstractProcessingHandler.php:19: * Classes extending it should (in most cases) only implement write($record)
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/AbstractProcessingHandler.php:44:        $this->write($record);
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/AbstractProcessingHandler.php:52:    abstract protected function write(LogRecord $record): void;
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/TelegramBotHandler.php:233:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/AmqpHandler.php:75:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/FleepHookHandler.php:91:    public function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/FleepHookHandler.php:93:        parent::write($record);
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/SlackHandler.php:156:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/SlackHandler.php:158:        parent::write($record);
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/NewRelicHandler.php:60:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/SyslogUdp/UdpSocket.php:31:    public function write(string $line, string $header = ""): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/CubeHandler.php:112:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/RedisHandler.php:56:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/ChromePHPHandler.php:111:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/CouchDBHandler.php:67:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/AbstractProcessingHandler.php:19: * Classes extending it should (in most cases) only implement write($record)
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/AbstractProcessingHandler.php:44:        $this->write($record);
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/AbstractProcessingHandler.php:52:    abstract protected function write(LogRecord $record): void;
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/ElasticaHandler.php:82:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/SlackWebhookHandler.php:96:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/TelegramBotHandler.php:233:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/symfony/translation/Command/TranslationPushCommand.php:135:            $provider->write($localTranslations);
packages/spiral-openapi/vendor/symfony/translation/Command/TranslationPushCommand.php:160:        $provider->write($translationsToWrite);
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/LogglyHandler.php:118:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/AmqpHandler.php:75:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/StreamHandler.php:135:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/StreamHandler.php:139:            $this->write($record);
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/StreamHandler.php:190:                $this->write($record);
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/StreamHandler.php:210:        fwrite($stream, (string) $record->formatted);
packages/spiral-openapi/vendor/symfony/translation/Command/TranslationPullCommand.php:156:                $this->writer->write($operation->getResult(), $format, $writeOptions);
packages/spiral-openapi/vendor/symfony/translation/Command/TranslationPullCommand.php:170:            $this->writer->write($catalogue, $format, $writeOptions);
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/MailHandler.php:59:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/FleepHookHandler.php:91:    public function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/FleepHookHandler.php:93:        parent::write($record);
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/RedisPubSubHandler.php:53:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/SlackHandler.php:156:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/SlackHandler.php:158:        parent::write($record);
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/DynamoDbHandler.php:51:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/DoctrineCouchDBHandler.php:38:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/phpunit/phpunit/src/Util/PHP/JobRunner.php:179:        fwrite($pipes[0], $job->code());
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/NewRelicHandler.php:60:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/FirePHPHandler.php:137:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/DeduplicationHandler.php:168:            fwrite($handle, $log);
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/ChromePHPHandler.php:111:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/ElasticaHandler.php:82:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/ErrorLogHandler.php:76:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/SlackWebhookHandler.php:96:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/PHPConsoleHandler.php:232:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/LogglyHandler.php:118:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/ZendMonitorHandler.php:61:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/StreamHandler.php:135:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/StreamHandler.php:139:            $this->write($record);
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/StreamHandler.php:190:                $this->write($record);
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/StreamHandler.php:210:        fwrite($stream, (string) $record->formatted);
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/GelfHandler.php:46:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/MailHandler.php:59:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/symfony/translation/Writer/TranslationWriterInterface.php:32:    public function write(MessageCatalogue $catalogue, string $format, array $options = []): void;
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/RedisPubSubHandler.php:53:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/symfony/translation/Writer/TranslationWriter.php:55:    public function write(MessageCatalogue $catalogue, string $format, array $options = []): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/ProcessHandler.php:85:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/ProcessHandler.php:175:        fwrite($this->pipes[0], $string);
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/DynamoDbHandler.php:51:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/MongoDBHandler.php:63:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/DoctrineCouchDBHandler.php:38:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/RotatingFileHandler.php:100:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/RotatingFileHandler.php:113:        parent::write($record);
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/FirePHPHandler.php:137:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/SqsHandler.php:45:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/DeduplicationHandler.php:168:            fwrite($handle, $log);
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/BrowserConsoleHandler.php:56:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/SyslogUdpHandler.php:69:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/SyslogUdpHandler.php:76:            $this->socket->write($line, $header);
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/ErrorLogHandler.php:76:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/autoload.php:12:            fwrite(STDERR, $err);
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/PHPConsoleHandler.php:232:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/ZendMonitorHandler.php:61:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/PushoverHandler.php:197:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/PushoverHandler.php:202:            parent::write($record);
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/GelfHandler.php:46:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/FlowdockHandler.php:89:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/FlowdockHandler.php:91:        parent::write($record);
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/ProcessHandler.php:85:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/ProcessHandler.php:175:        fwrite($this->pipes[0], $string);
packages/spiral-openapi/vendor/symfony/translation/Provider/ProviderInterface.php:25:    public function write(TranslatorBagInterface $translatorBag): void;
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/MongoDBHandler.php:63:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/ElasticsearchHandler.php:107:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/symfony/translation/Provider/NullProvider.php:27:    public function write(TranslatorBagInterface $translatorBag, bool $override = false): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/RotatingFileHandler.php:100:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/RotatingFileHandler.php:113:        parent::write($record);
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/SyslogUdp/UdpSocket.php:31:    public function write(string $line, string $header = ""): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/SqsHandler.php:45:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/symfony/translation/Provider/FilteringProvider.php:36:    public function write(TranslatorBagInterface $translatorBag): void
packages/spiral-openapi/vendor/symfony/translation/Provider/FilteringProvider.php:38:        $this->provider->write($translatorBag);
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/CubeHandler.php:112:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/RedisHandler.php:56:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/BrowserConsoleHandler.php:56:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/CouchDBHandler.php:67:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/SyslogUdpHandler.php:69:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/SyslogUdpHandler.php:76:            $this->socket->write($line, $header);
packages/spiral-openapi/vendor/symfony/translation/Translator.php:16:use Symfony\Component\Config\ConfigCacheInterface;
packages/spiral-openapi/vendor/symfony/translation/Translator.php:290:            function (ConfigCacheInterface $cache) use ($locale) {
packages/spiral-openapi/vendor/symfony/translation/Translator.php:304:    private function dumpCatalogue(string $locale, ConfigCacheInterface $cache): void
packages/spiral-openapi/vendor/symfony/translation/Translator.php:325:        $cache->write($content, $this->catalogues[$locale]->getResources());
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/AbstractProcessingHandler.php:19: * Classes extending it should (in most cases) only implement write($record)
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/AbstractProcessingHandler.php:44:        $this->write($record);
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/AbstractProcessingHandler.php:52:    abstract protected function write(LogRecord $record): void;
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/RollbarHandler.php:78:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/TelegramBotHandler.php:233:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/PushoverHandler.php:197:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/PushoverHandler.php:202:            parent::write($record);
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/SyslogHandler.php:58:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/FlowdockHandler.php:89:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/FlowdockHandler.php:91:        parent::write($record);
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/AmqpHandler.php:75:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/SocketHandler.php:83:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/SocketHandler.php:298:    protected function fwrite(string $data): int|bool
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/SocketHandler.php:304:        return @fwrite($this->resource, $data);
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/SocketHandler.php:390:                $chunk = $this->fwrite($data);
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/SocketHandler.php:392:                $chunk = $this->fwrite(substr($data, $sent));
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/FleepHookHandler.php:91:    public function write(LogRecord $record): void
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/FleepHookHandler.php:93:        parent::write($record);
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/ElasticsearchHandler.php:107:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/SlackHandler.php:156:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/SlackHandler.php:158:        parent::write($record);
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/IFTTTHandler.php:55:    public function write(LogRecord $record): void
packages/spiral-cqrs/vendor/monolog/monolog/src/Monolog/Handler/TestHandler.php:178:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/NewRelicHandler.php:60:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/ChromePHPHandler.php:111:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/RollbarHandler.php:78:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/SyslogHandler.php:58:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/ElasticaHandler.php:82:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/SlackWebhookHandler.php:96:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/psr/http-factory/src/ResponseFactoryInterface.php:5:interface ResponseFactoryInterface
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/SocketHandler.php:83:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/SocketHandler.php:298:    protected function fwrite(string $data): int|bool
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/SocketHandler.php:304:        return @fwrite($this->resource, $data);
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/SocketHandler.php:390:                $chunk = $this->fwrite($data);
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/SocketHandler.php:392:                $chunk = $this->fwrite(substr($data, $sent));
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/LogglyHandler.php:118:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/StreamHandler.php:135:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/StreamHandler.php:139:            $this->write($record);
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/StreamHandler.php:190:                $this->write($record);
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/StreamHandler.php:210:        fwrite($stream, (string) $record->formatted);
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/IFTTTHandler.php:55:    public function write(LogRecord $record): void
packages/spiral-cqrs/vendor/psr/simple-cache/src/CacheInterface.php:5:interface CacheInterface
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/MailHandler.php:59:    protected function write(LogRecord $record): void
packages/spiral-api-errors/tests/Interceptor/ApiExceptionInterceptorTest.php:19:use GianTiaga\SpiralOpenApi\Response\Enum\HttpStatus;
packages/spiral-api-errors/tests/Interceptor/ApiExceptionInterceptorTest.php:26:        $this->assertExceptionMapsToResponse(exception: new \DomainException(message: 'Некорректное значение', code: HttpStatus::UnprocessableEntity->value), expectedStatus: HttpStatus::UnprocessableEntity, expectedBody: '{"message":"Некорректное значение","code":422}', translator: self::englishTranslator());
packages/spiral-api-errors/tests/Interceptor/ApiExceptionInterceptorTest.php:30:        $this->assertExceptionMapsToResponse(exception: new \DomainException(message: 'Нужна аутентификация', code: HttpStatus::Unauthorized->value), expectedStatus: HttpStatus::Unauthorized, expectedBody: '{"message":"Нужна аутентификация","code":401}', translator: self::englishTranslator());
packages/spiral-api-errors/tests/Interceptor/ApiExceptionInterceptorTest.php:34:        $this->assertExceptionMapsToResponse(exception: new \DomainException(message: 'Доступ запрещён', code: HttpStatus::Forbidden->value), expectedStatus: HttpStatus::Forbidden, expectedBody: '{"message":"Доступ запрещён","code":403}', translator: self::englishTranslator());
packages/spiral-api-errors/tests/Interceptor/ApiExceptionInterceptorTest.php:38:        $this->assertExceptionMapsToResponse(exception: new \DomainException(message: 'Не найдено', code: HttpStatus::NotFound->value), expectedStatus: HttpStatus::NotFound, expectedBody: '{"message":"Не найдено","code":404}', translator: self::englishTranslator());
packages/spiral-api-errors/tests/Interceptor/ApiExceptionInterceptorTest.php:42:        $this->assertExceptionMapsToResponse(exception: new \DomainException(message: 'Конфликт', code: HttpStatus::Conflict->value), expectedStatus: HttpStatus::Conflict, expectedBody: '{"message":"Конфликт","code":409}', translator: self::englishTranslator());
packages/spiral-api-errors/tests/Interceptor/ApiExceptionInterceptorTest.php:47:        $this->assertExceptionMapsToResponse(exception: new \DomainException(message: 'Некорректное значение', code: HttpStatus::UnprocessableEntity->value), expectedStatus: HttpStatus::UnprocessableEntity, expectedBody: '{"message":"Некорректное значение","code":422}', translator: self::englishTranslator(), logger: $logger);
packages/spiral-api-errors/tests/Interceptor/ApiExceptionInterceptorTest.php:52:        $this->assertExceptionMapsToResponse(exception: new \DomainException(message: 'Доменная ошибка'), expectedStatus: HttpStatus::InternalServerError, expectedBody: '{"message":"Internal server error","code":500}', translator: self::englishTranslator());
packages/spiral-api-errors/tests/Interceptor/ApiExceptionInterceptorTest.php:56:        $this->assertExceptionMapsToResponse(exception: new \DomainException(message: 'Неподдерживаемая ошибка', code: 499), expectedStatus: HttpStatus::InternalServerError, expectedBody: '{"message":"Internal server error","code":500}', translator: self::englishTranslator());
packages/spiral-api-errors/tests/Interceptor/ApiExceptionInterceptorTest.php:60:        $this->assertExceptionMapsToResponse(exception: new \RuntimeException(message: 'SQL connection failed'), expectedStatus: HttpStatus::InternalServerError, expectedBody: '{"message":"Internal server error","code":500}', translator: self::englishTranslator());
packages/spiral-api-errors/tests/Interceptor/ApiExceptionInterceptorTest.php:64:        $this->assertExceptionMapsToResponse(exception: new \RuntimeException(message: 'SQL connection failed'), expectedStatus: HttpStatus::InternalServerError, expectedBody: '{"message":"Внутренняя ошибка сервера","code":500}', translator: new FakeTranslator(locale: 'ru', messages: ['gian_tiaga.spiral_api_errors.internal_server_error' => 'Внутренняя ошибка сервера']));
packages/spiral-api-errors/tests/Interceptor/ApiExceptionInterceptorTest.php:68:        $this->assertExceptionMapsToResponse(exception: new ApiExceptionInterceptorTranslatableFixtureException(translationKey: 'app.media.unsupported_file_type', translationDomain: 'media', translationParameters: ['type' => 'png'], code: HttpStatus::UnprocessableEntity->value), expectedStatus: HttpStatus::UnprocessableEntity, expectedBody: '{"message":"Unsupported file type: png.","code":422}', translator: new FakeTranslator(locale: 'en', messages: ['app.media.unsupported_file_type' => 'Unsupported file type: {type}.']));
packages/spiral-api-errors/tests/Interceptor/ApiExceptionInterceptorTest.php:72:        $this->assertExceptionMapsToResponse(exception: new ApiExceptionInterceptorTranslatableFixtureException(translationKey: 'app.media.not_found', translationDomain: 'media', translationParameters: [], code: HttpStatus::NotFound->value), expectedStatus: HttpStatus::NotFound, expectedBody: '{"message":"app.media.not_found","code":404}', translator: self::englishTranslator());
packages/spiral-api-errors/tests/Interceptor/ApiExceptionInterceptorTest.php:78:        $interceptor->intercept(context: self::createStub(CallContextInterface::class), handler: new ApiExceptionInterceptorFixtureHandler(exception: new ApiExceptionInterceptorTranslatableFixtureException(translationKey: 'app.media.not_found', translationDomain: 'media', translationParameters: [], code: HttpStatus::NotFound->value)));
packages/spiral-api-errors/tests/Interceptor/ApiExceptionInterceptorTest.php:87:    private function assertExceptionMapsToResponse(\Throwable $exception, HttpStatus $expectedStatus, string $expectedBody, FakeTranslator $translator, LoggerInterface|null $logger = null): void
packages/spiral-api-errors/vendor/monolog/monolog/src/Monolog/Handler/TestHandler.php:178:    protected function write(LogRecord $record): void
packages/spiral-api-errors/tests/Filter/ApiValidationErrorsRendererTest.php:13:use GianTiaga\SpiralOpenApi\Response\Enum\HttpStatus;
packages/spiral-api-errors/tests/Filter/ApiValidationErrorsRendererTest.php:28:        self::assertSame(HttpStatus::UnprocessableEntity->value, $response->getStatusCode());
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/RedisPubSubHandler.php:53:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/phpunit/phpunit/src/TextUI/Output/Printer/DefaultPrinter.php:115:        fwrite($this->stream, $buffer);
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/DynamoDbHandler.php:51:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/DoctrineCouchDBHandler.php:38:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/psr/http-factory/src/ResponseFactoryInterface.php:5:interface ResponseFactoryInterface
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/SocketHandler.php:83:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/SocketHandler.php:298:    protected function fwrite(string $data): int|bool
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/SocketHandler.php:304:        return @fwrite($this->resource, $data);
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/SocketHandler.php:390:                $chunk = $this->fwrite($data);
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/SocketHandler.php:392:                $chunk = $this->fwrite(substr($data, $sent));
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/FirePHPHandler.php:137:    protected function write(LogRecord $record): void
packages/spiral-api-errors/tests/Middleware/RouteNotFoundMiddlewareTest.php:21:use GianTiaga\SpiralOpenApi\Response\Enum\HttpStatus;
packages/spiral-api-errors/tests/Middleware/RouteNotFoundMiddlewareTest.php:37:        self::assertSame(HttpStatus::NotFound->value, $response->getStatusCode());
packages/spiral-api-errors/tests/Middleware/RouteNotFoundMiddlewareTest.php:43:        $expectedResponse = new Response(status: HttpStatus::Accepted->value, body: 'ok');
packages/spiral-api-errors/tests/Middleware/RouteNotFoundMiddlewareTest.php:63:        self::assertSame(['method' => 'POST', 'path' => '/hidden', 'status' => HttpStatus::NotFound->value, 'exceptionClass' => RouteNotFoundException::class], $record->context);
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/DeduplicationHandler.php:168:            fwrite($handle, $log);
packages/spiral-api-errors/vendor/psr/simple-cache/src/CacheInterface.php:5:interface CacheInterface
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/IFTTTHandler.php:55:    public function write(LogRecord $record): void
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/ErrorLogHandler.php:76:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/TestHandler.php:178:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/PHPConsoleHandler.php:232:    protected function write(LogRecord $record): void
packages/spiral-api-errors/runtime/phpstan/resultCache.php:2187:              'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/resultCache.php:3140:              'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/resultCache.php:3286:              'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/resultCache.php:3578:              'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/resultCache.php:3662:              'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/resultCache.php:3730:              'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/ZendMonitorHandler.php:61:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/GelfHandler.php:46:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/psr/http-message/src/ResponseInterface.php:52:    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface;
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/ProcessHandler.php:85:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/ProcessHandler.php:175:        fwrite($this->pipes[0], $string);
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/MongoDBHandler.php:63:    protected function write(LogRecord $record): void
packages/spiral-cqrs/vendor/psr/http-message/src/StreamInterface.php:115:    public function write(string $string): int;
packages/spiral-openapi/vendor/psr/http-factory/src/ResponseFactoryInterface.php:5:interface ResponseFactoryInterface
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/RotatingFileHandler.php:100:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/RotatingFileHandler.php:113:        parent::write($record);
packages/phpstan-strict-rules/vendor/phpunit/phpunit/src/Util/PHP/JobRunner.php:179:        fwrite($pipes[0], $job->code());
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/SqsHandler.php:45:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/psr/simple-cache/src/CacheInterface.php:5:interface CacheInterface
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/BrowserConsoleHandler.php:56:    protected function write(LogRecord $record): void
packages/spiral-api-errors/vendor/psr/http-message/src/ResponseInterface.php:52:    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface;
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/SyslogUdpHandler.php:69:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/SyslogUdpHandler.php:76:            $this->socket->write($line, $header);
packages/spiral-api-errors/vendor/psr/http-message/src/StreamInterface.php:115:    public function write(string $string): int;
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/PushoverHandler.php:197:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/PushoverHandler.php:202:            parent::write($record);
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/FlowdockHandler.php:89:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/FlowdockHandler.php:91:        parent::write($record);
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/ElasticsearchHandler.php:107:    protected function write(LogRecord $record): void
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/25/96/2596476aca28fdc4c2d2c9eab4e465e76d00675d5a7a3c5d32a77f0df00276e3.php:19:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/25/96/2596476aca28fdc4c2d2c9eab4e465e76d00675d5a7a3c5d32a77f0df00276e3.php:48:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/25/96/2596476aca28fdc4c2d2c9eab4e465e76d00675d5a7a3c5d32a77f0df00276e3.php:77:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/25/96/2596476aca28fdc4c2d2c9eab4e465e76d00675d5a7a3c5d32a77f0df00276e3.php:106:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/RollbarHandler.php:78:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/monolog/monolog/src/Monolog/Handler/SyslogHandler.php:58:    protected function write(LogRecord $record): void
packages/spiral-openapi/vendor/psr/http-message/src/ResponseInterface.php:52:    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface;
packages/spiral-cqrs/vendor/defuse/php-encryption/src/File.php:780:            $written = \fwrite($stream, $buf, $remaining);
packages/spiral-openapi/vendor/psr/http-message/src/StreamInterface.php:115:    public function write(string $string): int;
packages/spiral-api-errors/vendor/defuse/php-encryption/src/File.php:780:            $written = \fwrite($stream, $buf, $remaining);
packages/spiral-cqrs/vendor/php-http/discovery/src/Psr18Client.php:8:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-cqrs/vendor/php-http/discovery/src/Psr18Client.php:30:        ?ResponseFactoryInterface $responseFactory = null,
packages/spiral-cqrs/vendor/php-http/discovery/src/Psr18Client.php:37:        $responseFactory ?? $responseFactory = $client instanceof ResponseFactoryInterface ? $client : null;
packages/spiral-cqrs/vendor/php-http/discovery/src/Strategy/CommonPsr17ClassesStrategy.php:6:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-cqrs/vendor/php-http/discovery/src/Strategy/CommonPsr17ClassesStrategy.php:36:        ResponseFactoryInterface::class => [
packages/spiral-cqrs/vendor/nette/utils/src/Utils/Process.php:341:	 * Writes the whole string to STDIN, handling partial writes. Stops (and lets fwrite() warn) on a broken pipe,
packages/spiral-cqrs/vendor/nette/utils/src/Utils/Process.php:348:			$bytes = fwrite($this->inputPipe, substr($string, $written));
packages/spiral-cqrs/vendor/nette/utils/src/Utils/FileInfo.php:62:	public function write(string $content): void
packages/spiral-cqrs/vendor/nette/utils/src/Utils/FileInfo.php:64:		FileSystem::write($this->getPathname(), $content);
packages/spiral-cqrs/vendor/php-http/discovery/src/Psr17Factory.php:7:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-cqrs/vendor/php-http/discovery/src/Psr17Factory.php:42:class Psr17Factory implements RequestFactoryInterface, ResponseFactoryInterface, ServerRequestFactoryInterface, StreamFactoryInterface, UploadedFileFactoryInterface, UriFactoryInterface
packages/spiral-cqrs/vendor/php-http/discovery/src/Psr17Factory.php:53:        ?ResponseFactoryInterface $responseFactory = null,
packages/spiral-cqrs/vendor/php-http/discovery/src/Psr17Factory.php:161:        if (!$this->responseFactory && $factory instanceof ResponseFactoryInterface) {
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/14/10/1410a2e2b02b26a98e30d6dccf081ec9b2d335ef035eeeb0e665c58a50997fb9.php:29:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/14/10/1410a2e2b02b26a98e30d6dccf081ec9b2d335ef035eeeb0e665c58a50997fb9.php:66:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/14/10/1410a2e2b02b26a98e30d6dccf081ec9b2d335ef035eeeb0e665c58a50997fb9.php:103:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/14/10/1410a2e2b02b26a98e30d6dccf081ec9b2d335ef035eeeb0e665c58a50997fb9.php:140:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/14/10/1410a2e2b02b26a98e30d6dccf081ec9b2d335ef035eeeb0e665c58a50997fb9.php:177:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/14/10/1410a2e2b02b26a98e30d6dccf081ec9b2d335ef035eeeb0e665c58a50997fb9.php:214:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/14/10/1410a2e2b02b26a98e30d6dccf081ec9b2d335ef035eeeb0e665c58a50997fb9.php:251:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/14/10/1410a2e2b02b26a98e30d6dccf081ec9b2d335ef035eeeb0e665c58a50997fb9.php:288:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/14/10/1410a2e2b02b26a98e30d6dccf081ec9b2d335ef035eeeb0e665c58a50997fb9.php:325:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/14/10/1410a2e2b02b26a98e30d6dccf081ec9b2d335ef035eeeb0e665c58a50997fb9.php:362:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/14/10/1410a2e2b02b26a98e30d6dccf081ec9b2d335ef035eeeb0e665c58a50997fb9.php:399:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/14/10/1410a2e2b02b26a98e30d6dccf081ec9b2d335ef035eeeb0e665c58a50997fb9.php:436:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/14/10/1410a2e2b02b26a98e30d6dccf081ec9b2d335ef035eeeb0e665c58a50997fb9.php:473:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/14/10/1410a2e2b02b26a98e30d6dccf081ec9b2d335ef035eeeb0e665c58a50997fb9.php:510:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/14/10/1410a2e2b02b26a98e30d6dccf081ec9b2d335ef035eeeb0e665c58a50997fb9.php:547:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/14/10/1410a2e2b02b26a98e30d6dccf081ec9b2d335ef035eeeb0e665c58a50997fb9.php:584:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/14/10/1410a2e2b02b26a98e30d6dccf081ec9b2d335ef035eeeb0e665c58a50997fb9.php:621:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-cqrs/vendor/php-http/discovery/src/Composer/Plugin.php:117:            'Psr\Http\Message\ResponseFactoryInterface',
packages/spiral-cqrs/vendor/php-http/discovery/src/Composer/Plugin.php:472:        $lockFile->write($lockData);
packages/spiral-cqrs/vendor/nette/utils/src/Utils/FileSystem.php:214:	public static function write(string $file, string $content, ?int $mode = 0o666): void
packages/spiral-cqrs/vendor/php-http/discovery/src/Psr17FactoryDiscovery.php:8:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-cqrs/vendor/php-http/discovery/src/Psr17FactoryDiscovery.php:47:     * @return ResponseFactoryInterface
packages/spiral-cqrs/vendor/php-http/discovery/src/Psr17FactoryDiscovery.php:54:            $messageFactory = static::findOneByType(ResponseFactoryInterface::class);
packages/spiral-openapi/vendor/defuse/php-encryption/src/File.php:780:            $written = \fwrite($stream, $buf, $remaining);
packages/phpstan-strict-rules/vendor/phpunit/phpunit/src/TextUI/Output/Printer/DefaultPrinter.php:115:        fwrite($this->stream, $buffer);
packages/spiral-openapi/vendor/league/flysystem/src/DecoratedAdapter.php:23:    public function write(string $path, string $contents, Config $config): void
packages/spiral-openapi/vendor/league/flysystem/src/DecoratedAdapter.php:25:        $this->adapter->write($path, $contents, $config);
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/7f/2a/7f2a0751238f9a83b917e102734554abf570a7b70e905631384aa7e863841a1c.php:29:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/7f/2a/7f2a0751238f9a83b917e102734554abf570a7b70e905631384aa7e863841a1c.php:66:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/7f/2a/7f2a0751238f9a83b917e102734554abf570a7b70e905631384aa7e863841a1c.php:103:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/7f/2a/7f2a0751238f9a83b917e102734554abf570a7b70e905631384aa7e863841a1c.php:140:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/7f/2a/7f2a0751238f9a83b917e102734554abf570a7b70e905631384aa7e863841a1c.php:177:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/7f/2a/7f2a0751238f9a83b917e102734554abf570a7b70e905631384aa7e863841a1c.php:214:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/7f/2a/7f2a0751238f9a83b917e102734554abf570a7b70e905631384aa7e863841a1c.php:251:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/7f/2a/7f2a0751238f9a83b917e102734554abf570a7b70e905631384aa7e863841a1c.php:288:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/7f/2a/7f2a0751238f9a83b917e102734554abf570a7b70e905631384aa7e863841a1c.php:325:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/7f/2a/7f2a0751238f9a83b917e102734554abf570a7b70e905631384aa7e863841a1c.php:362:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/7f/2a/7f2a0751238f9a83b917e102734554abf570a7b70e905631384aa7e863841a1c.php:399:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/7f/2a/7f2a0751238f9a83b917e102734554abf570a7b70e905631384aa7e863841a1c.php:436:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/7f/2a/7f2a0751238f9a83b917e102734554abf570a7b70e905631384aa7e863841a1c.php:473:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/7f/2a/7f2a0751238f9a83b917e102734554abf570a7b70e905631384aa7e863841a1c.php:510:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/7f/2a/7f2a0751238f9a83b917e102734554abf570a7b70e905631384aa7e863841a1c.php:547:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/7f/2a/7f2a0751238f9a83b917e102734554abf570a7b70e905631384aa7e863841a1c.php:584:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/7f/2a/7f2a0751238f9a83b917e102734554abf570a7b70e905631384aa7e863841a1c.php:621:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-openapi/vendor/league/flysystem/src/FilesystemAdapter.php:25:    public function write(string $path, string $contents, Config $config): void;
packages/spiral-openapi/vendor/league/flysystem/src/MountManager.php:181:    public function write(string $location, string $contents, array $config = []): void
packages/spiral-openapi/vendor/league/flysystem/src/MountManager.php:187:            $filesystem->write($path, $contents, $this->config->extend($config)->toArray());
packages/spiral-openapi/vendor/nyholm/psr7/src/Factory/Psr17Factory.php:8:use Psr\Http\Message\{RequestFactoryInterface, RequestInterface, ResponseFactoryInterface, ResponseInterface, ServerRequestFactoryInterface, ServerRequestInterface, StreamFactoryInterface, StreamInterface, UploadedFileFactoryInterface, UploadedFileInterface, UriFactoryInterface, UriInterface};
packages/spiral-openapi/vendor/nyholm/psr7/src/Factory/Psr17Factory.php:16:class Psr17Factory implements RequestFactoryInterface, ResponseFactoryInterface, ServerRequestFactoryInterface, StreamFactoryInterface, UploadedFileFactoryInterface, UriFactoryInterface
packages/spiral-openapi/vendor/nyholm/psr7/src/UploadedFile.php:151:                if (!$dest->write($stream->read(1048576))) {
packages/spiral-openapi/vendor/nyholm/psr7/src/Stream.php:88:                \fwrite($resource, $body);
packages/spiral-openapi/vendor/nyholm/psr7/src/Stream.php:215:    public function write($string): int
packages/spiral-openapi/vendor/nyholm/psr7/src/Stream.php:228:        if (false === $result = @\fwrite($this->stream, $string)) {
packages/spiral-openapi/vendor/nyholm/psr7/src/Stream.php:326:            public function stream_write(string $data): int
packages/spiral-openapi/vendor/nyholm/psr7/src/Response.php:73:    public function withStatus($code, $reasonPhrase = ''): ResponseInterface
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/47/62/4762aabe4c9aea1d040857e9c550158c7a1e1d830a848fb25173f5dc6679b254.php:19:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/47/62/4762aabe4c9aea1d040857e9c550158c7a1e1d830a848fb25173f5dc6679b254.php:48:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/47/62/4762aabe4c9aea1d040857e9c550158c7a1e1d830a848fb25173f5dc6679b254.php:77:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/47/62/4762aabe4c9aea1d040857e9c550158c7a1e1d830a848fb25173f5dc6679b254.php:106:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-openapi/vendor/league/flysystem/src/Filesystem.php:53:    public function write(string $location, string $contents, array $config = []): void
packages/spiral-openapi/vendor/league/flysystem/src/Filesystem.php:55:        $this->adapter->write(
packages/spiral-cqrs/vendor/league/flysystem/src/DecoratedAdapter.php:23:    public function write(string $path, string $contents, Config $config): void
packages/spiral-cqrs/vendor/league/flysystem/src/DecoratedAdapter.php:25:        $this->adapter->write($path, $contents, $config);
packages/spiral-openapi/vendor/league/flysystem/src/FilesystemWriter.php:13:    public function write(string $location, string $contents, array $config = []): void;
packages/spiral-cqrs/vendor/league/flysystem/src/FilesystemAdapter.php:25:    public function write(string $path, string $contents, Config $config): void;
packages/spiral-openapi/vendor/league/flysystem-local/LocalFilesystemAdapter.php:105:    public function write(string $path, string $contents, Config $config): void
packages/spiral-api-errors/vendor/phpunit/phpunit/src/TextUI/Application.php:297:                (new Writer)->write(
packages/spiral-cqrs/vendor/league/flysystem/src/MountManager.php:181:    public function write(string $location, string $contents, array $config = []): void
packages/spiral-cqrs/vendor/league/flysystem/src/MountManager.php:187:            $filesystem->write($path, $contents, $this->config->extend($config)->toArray());
packages/spiral-cqrs/vendor/league/flysystem/src/Filesystem.php:53:    public function write(string $location, string $contents, array $config = []): void
packages/spiral-cqrs/vendor/league/flysystem/src/Filesystem.php:55:        $this->adapter->write(
packages/spiral-openapi/vendor/spiral/attributes/src/Psr16CachedReader.php:7:use Psr\SimpleCache\CacheInterface;
packages/spiral-openapi/vendor/spiral/attributes/src/Psr16CachedReader.php:16:        private readonly CacheInterface $cache,
packages/spiral-cqrs/vendor/league/flysystem/src/FilesystemWriter.php:13:    public function write(string $location, string $contents, array $config = []): void;
packages/spiral-cqrs/vendor/league/flysystem-local/LocalFilesystemAdapter.php:105:    public function write(string $path, string $contents, Config $config): void
packages/spiral-openapi/vendor/spiral/attributes/src/Factory.php:10:use Psr\SimpleCache\CacheInterface;
packages/spiral-openapi/vendor/spiral/attributes/src/Factory.php:15:    private CacheInterface|CacheItemPoolInterface|null $cache = null;
packages/spiral-openapi/vendor/spiral/attributes/src/Factory.php:17:    public function withCache(CacheInterface|CacheItemPoolInterface|null $cache): self
packages/spiral-openapi/vendor/spiral/attributes/src/Factory.php:19:        \assert($cache instanceof CacheItemPoolInterface || $cache instanceof CacheInterface || $cache === null);
packages/spiral-openapi/vendor/spiral/attributes/src/Factory.php:50:            $this->cache instanceof CacheInterface => new Psr16CachedReader($reader, $this->cache),
packages/spiral-cqrs/vendor/spiral/attributes/src/Psr16CachedReader.php:7:use Psr\SimpleCache\CacheInterface;
packages/spiral-cqrs/vendor/spiral/attributes/src/Psr16CachedReader.php:16:        private readonly CacheInterface $cache,
packages/spiral-openapi/vendor/spiral/framework/src/Router/src/Route.php:9:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-openapi/vendor/spiral/framework/src/Router/src/Route.php:161:                $this->container->get(ResponseFactoryInterface::class),
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/8c/11/8c115793159fd160ab233371cfe0703324de1b76db3cc6f7c3f662e875b51e49.php:56:            'name' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/8c/11/8c115793159fd160ab233371cfe0703324de1b76db3cc6f7c3f662e875b51e49.php:62:          'code' => '\\Tools\\OpenApi\\Response\\Enum\\HttpStatus::Ok',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/8c/11/8c115793159fd160ab233371cfe0703324de1b76db3cc6f7c3f662e875b51e49.php:155:      'withStatus' => 
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/8c/11/8c115793159fd160ab233371cfe0703324de1b76db3cc6f7c3f662e875b51e49.php:157:        'name' => 'withStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/8c/11/8c115793159fd160ab233371cfe0703324de1b76db3cc6f7c3f662e875b51e49.php:169:                'name' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/8c/11/8c115793159fd160ab233371cfe0703324de1b76db3cc6f7c3f662e875b51e49.php:411:            'name' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-cqrs/vendor/spiral/attributes/src/Factory.php:10:use Psr\SimpleCache\CacheInterface;
packages/spiral-cqrs/vendor/spiral/attributes/src/Factory.php:15:    private CacheInterface|CacheItemPoolInterface|null $cache = null;
packages/spiral-cqrs/vendor/spiral/attributes/src/Factory.php:17:    public function withCache(CacheInterface|CacheItemPoolInterface|null $cache): self
packages/spiral-cqrs/vendor/spiral/attributes/src/Factory.php:19:        \assert($cache instanceof CacheItemPoolInterface || $cache instanceof CacheInterface || $cache === null);
packages/spiral-cqrs/vendor/spiral/attributes/src/Factory.php:50:            $this->cache instanceof CacheInterface => new Psr16CachedReader($reader, $this->cache),
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/8d/70/8d70287ed9b1b4bc1682b547fee1085da6ad4dbbe64d1fdc4bd1cf1872b4e887.php:473:                'name' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/phpstan-strict-rules/vendor/phpunit/phpunit/src/TextUI/Application.php:297:                (new Writer)->write(
packages/spiral-openapi/vendor/spiral/framework/src/Router/src/CoreHandler.php:7:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-openapi/vendor/spiral/framework/src/Router/src/CoreHandler.php:54:        private readonly ResponseFactoryInterface $responseFactory,
packages/spiral-openapi/vendor/spiral/framework/src/Router/src/CoreHandler.php:173:                $result->getBody()->write($output);
packages/spiral-openapi/vendor/spiral/framework/src/Router/src/CoreHandler.php:186:            $response->getBody()->write((string) $result);
packages/spiral-openapi/vendor/spiral/framework/src/Router/src/CoreHandler.php:190:        $response->getBody()->write($output);
packages/spiral-openapi/vendor/spiral/framework/src/Router/src/Target/AbstractTarget.php:9:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-openapi/vendor/spiral/framework/src/Router/src/Target/AbstractTarget.php:104:                $container->get(ResponseFactoryInterface::class),
packages/spiral-api-errors/vendor/nette/utils/src/Utils/Process.php:341:	 * Writes the whole string to STDIN, handling partial writes. Stops (and lets fwrite() warn) on a broken pipe,
packages/spiral-api-errors/vendor/nette/utils/src/Utils/Process.php:348:			$bytes = fwrite($this->inputPipe, substr($string, $written));
packages/spiral-api-errors/vendor/nette/utils/src/Utils/FileInfo.php:62:	public function write(string $content): void
packages/spiral-api-errors/vendor/nette/utils/src/Utils/FileInfo.php:64:		FileSystem::write($this->getPathname(), $content);
packages/spiral-cqrs/vendor/phpunit/php-code-coverage/src/Report/Facade.php:175:            Filesystem::write(
packages/spiral-cqrs/vendor/phpunit/php-code-coverage/src/Report/Crap4j.php:129:            Filesystem::write($target, $buffer);
packages/spiral-cqrs/vendor/spiral/framework/src/Router/src/Route.php:9:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Router/src/Route.php:161:                $this->container->get(ResponseFactoryInterface::class),
packages/phpstan-strict-rules/vendor/composer/platform_check.php:17:            fwrite(STDERR, 'Composer detected issues in your platform:' . PHP_EOL.PHP_EOL . implode(PHP_EOL, $issues) . PHP_EOL.PHP_EOL);
packages/spiral-openapi/vendor/spiral/framework/src/Session/src/Handler/CacheHandler.php:7:use Psr\SimpleCache\CacheInterface;
packages/spiral-openapi/vendor/spiral/framework/src/Session/src/Handler/CacheHandler.php:13:    private readonly CacheInterface $cache;
packages/spiral-openapi/vendor/spiral/framework/src/Session/src/Handler/CacheHandler.php:62:    public function write(string $id, string $data): bool
packages/spiral-openapi/vendor/spiral/framework/src/Session/src/Handler/NullHandler.php:43:    public function write(string $id, string $data): bool
packages/spiral-api-errors/vendor/nette/utils/src/Utils/FileSystem.php:214:	public static function write(string $file, string $content, ?int $mode = 0o666): void
packages/spiral-openapi/vendor/spiral/framework/src/Session/src/Handler/FileHandler.php:60:    public function write(string $id, string $data): bool
packages/spiral-openapi/vendor/spiral/framework/src/Session/src/Handler/FileHandler.php:62:        return $this->files->write($this->getFilename($id), $data, FilesInterface::RUNTIME, true);
packages/spiral-cqrs/vendor/spiral/framework/src/Router/src/CoreHandler.php:7:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Router/src/CoreHandler.php:54:        private readonly ResponseFactoryInterface $responseFactory,
packages/spiral-cqrs/vendor/spiral/framework/src/Router/src/CoreHandler.php:173:                $result->getBody()->write($output);
packages/spiral-cqrs/vendor/spiral/framework/src/Router/src/CoreHandler.php:186:            $response->getBody()->write((string) $result);
packages/spiral-cqrs/vendor/spiral/framework/src/Router/src/CoreHandler.php:190:        $response->getBody()->write($output);
packages/spiral-cqrs/vendor/spiral/framework/src/Router/src/Target/AbstractTarget.php:9:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Router/src/Target/AbstractTarget.php:104:                $container->get(ResponseFactoryInterface::class),
packages/spiral-openapi/vendor/phpunit/php-code-coverage/src/Report/Facade.php:175:            Filesystem::write(
packages/spiral-openapi/vendor/phpunit/php-code-coverage/src/Report/Crap4j.php:129:            Filesystem::write($target, $buffer);
packages/spiral-cqrs/vendor/phpunit/php-code-coverage/src/Report/OpenClover.php:251:            Filesystem::write($target, $buffer);
packages/spiral-cqrs/vendor/phpunit/php-code-coverage/src/Report/Clover.php:229:            Filesystem::write($target, $buffer);
packages/spiral-cqrs/vendor/phpunit/php-code-coverage/src/Report/Cobertura.php:290:            Filesystem::write($target, $buffer);
packages/spiral-cqrs/vendor/phpunit/php-code-coverage/src/StaticAnalysis/CachingSourceAnalyser.php:82:        $this->write($cacheFile, $analysisResult);
packages/spiral-cqrs/vendor/phpunit/php-code-coverage/src/StaticAnalysis/CachingSourceAnalyser.php:137:    private function write(string $cacheFile, AnalysisResult $result): void
packages/spiral-cqrs/vendor/spiral/framework/src/Session/src/Handler/CacheHandler.php:7:use Psr\SimpleCache\CacheInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Session/src/Handler/CacheHandler.php:13:    private readonly CacheInterface $cache;
packages/spiral-cqrs/vendor/spiral/framework/src/Session/src/Handler/CacheHandler.php:62:    public function write(string $id, string $data): bool
packages/spiral-cqrs/vendor/spiral/framework/src/Session/src/Handler/NullHandler.php:43:    public function write(string $id, string $data): bool
packages/spiral-cqrs/vendor/spiral/framework/src/Session/src/Handler/FileHandler.php:60:    public function write(string $id, string $data): bool
packages/spiral-cqrs/vendor/spiral/framework/src/Session/src/Handler/FileHandler.php:62:        return $this->files->write($this->getFilename($id), $data, FilesInterface::RUNTIME, true);
packages/spiral-cqrs/vendor/phpunit/php-code-coverage/src/Serialization/Serializer.php:116:        Filesystem::write(
packages/spiral-openapi/vendor/spiral/framework/src/Reactor/src/Writer.php:15:    public function write(string $filename, FileDeclaration $file): bool
packages/spiral-openapi/vendor/spiral/framework/src/Reactor/src/Writer.php:17:        return $this->files->write(
packages/spiral-openapi/vendor/phpunit/php-code-coverage/src/Report/OpenClover.php:251:            Filesystem::write($target, $buffer);
packages/spiral-api-errors/vendor/league/flysystem/src/DecoratedAdapter.php:23:    public function write(string $path, string $contents, Config $config): void
packages/spiral-api-errors/vendor/league/flysystem/src/DecoratedAdapter.php:25:        $this->adapter->write($path, $contents, $config);
packages/spiral-openapi/vendor/phpunit/php-code-coverage/src/Report/Clover.php:229:            Filesystem::write($target, $buffer);
packages/spiral-openapi/vendor/phpunit/php-code-coverage/src/Report/Cobertura.php:290:            Filesystem::write($target, $buffer);
packages/spiral-cqrs/vendor/phpunit/php-code-coverage/src/Util/Filesystem.php:49:    public static function write(string $target, string $buffer): void
packages/spiral-openapi/vendor/phpunit/php-code-coverage/src/StaticAnalysis/CachingSourceAnalyser.php:82:        $this->write($cacheFile, $analysisResult);
packages/spiral-openapi/vendor/phpunit/php-code-coverage/src/StaticAnalysis/CachingSourceAnalyser.php:137:    private function write(string $cacheFile, AnalysisResult $result): void
packages/spiral-api-errors/vendor/league/flysystem/src/FilesystemAdapter.php:25:    public function write(string $path, string $contents, Config $config): void;
packages/spiral-api-errors/vendor/league/flysystem/src/MountManager.php:181:    public function write(string $location, string $contents, array $config = []): void
packages/spiral-api-errors/vendor/league/flysystem/src/MountManager.php:187:            $filesystem->write($path, $contents, $this->config->extend($config)->toArray());
packages/spiral-openapi/vendor/phpunit/php-code-coverage/src/Serialization/Serializer.php:116:        Filesystem::write(
packages/spiral-openapi/vendor/phpunit/php-code-coverage/src/Util/Filesystem.php:49:    public static function write(string $target, string $buffer): void
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/41/46/4146b4adc712ac03d4de6b494587ca1d183963c084221cd1d7bc9c2c01692d32.php:105:      'withStatus' => 
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/41/46/4146b4adc712ac03d4de6b494587ca1d183963c084221cd1d7bc9c2c01692d32.php:107:        'name' => 'withStatus',
packages/spiral-api-errors/vendor/league/flysystem/src/Filesystem.php:53:    public function write(string $location, string $contents, array $config = []): void
packages/spiral-api-errors/vendor/league/flysystem/src/Filesystem.php:55:        $this->adapter->write(
packages/spiral-cqrs/vendor/spiral/framework/src/Reactor/src/Writer.php:15:    public function write(string $filename, FileDeclaration $file): bool
packages/spiral-cqrs/vendor/spiral/framework/src/Reactor/src/Writer.php:17:        return $this->files->write(
packages/spiral-openapi/vendor/spiral/framework/src/Console/src/Traits/HelpersTrait.php:205:        $this->write(
packages/spiral-openapi/vendor/spiral/framework/src/Console/src/Traits/HelpersTrait.php:219:    protected function write(string|iterable $messages, bool $newline = false): void
packages/spiral-openapi/vendor/spiral/framework/src/Console/src/Traits/HelpersTrait.php:221:        $this->output->write(messages: $messages, newline: $newline);
packages/spiral-api-errors/vendor/league/flysystem/src/FilesystemWriter.php:13:    public function write(string $location, string $contents, array $config = []): void;
packages/spiral-api-errors/vendor/league/flysystem-local/LocalFilesystemAdapter.php:105:    public function write(string $path, string $contents, Config $config): void
packages/spiral-cqrs/vendor/spiral/framework/src/Console/src/Traits/HelpersTrait.php:205:        $this->write(
packages/spiral-cqrs/vendor/spiral/framework/src/Console/src/Traits/HelpersTrait.php:219:    protected function write(string|iterable $messages, bool $newline = false): void
packages/spiral-cqrs/vendor/spiral/framework/src/Console/src/Traits/HelpersTrait.php:221:        $this->output->write(messages: $messages, newline: $newline);
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/cb/db/cbdbfd7432d40d555e390f353887e1d424c95fbb4aa61bc13fc4cb1b34b8e8e2.php:105:      'withStatus' => 
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/cb/db/cbdbfd7432d40d555e390f353887e1d424c95fbb4aa61bc13fc4cb1b34b8e8e2.php:107:        'name' => 'withStatus',
packages/spiral-openapi/vendor/spiral/framework/src/Snapshots/src/FileSnapshot.php:38:        $this->files->write(
packages/spiral-openapi/vendor/spiral/framework/src/Snapshots/src/StorageSnapshot.php:37:            ->write($this->renderer->render($snapshot->getException(), $this->verbosity));
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/f1/97/f1973aedc6347adcee1c8296f6d6de32c826407681ecfbc05d1f202ce3a38fd2.php:917:         'functionName' => 'withStatus',
packages/spiral-openapi/vendor/spiral/framework/src/Framework/Console/Sequence/RuntimeDirectory.php:23:        $output->write('Verifying runtime directory... ');
packages/spiral-cqrs/vendor/autoload.php:12:            fwrite(STDERR, $err);
packages/spiral-openapi/vendor/spiral/framework/src/Framework/Console/ConsoleDispatcher.php:67:        $output->write(
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/e9/39/e939570320c9e9657105055ddf26ac9316fed92b2340d4711d03b8c007a245ad.php:22:          'cacheinterface' => 'Spiral\\Translator\\Catalogue\\CacheInterface',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/e9/39/e939570320c9e9657105055ddf26ac9316fed92b2340d4711d03b8c007a245ad.php:63:          'cacheinterface' => 'Spiral\\Translator\\Catalogue\\CacheInterface',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/e9/39/e939570320c9e9657105055ddf26ac9316fed92b2340d4711d03b8c007a245ad.php:93:            'cacheinterface' => 'Spiral\\Translator\\Catalogue\\CacheInterface',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/e9/39/e939570320c9e9657105055ddf26ac9316fed92b2340d4711d03b8c007a245ad.php:144:          'cacheinterface' => 'Spiral\\Translator\\Catalogue\\CacheInterface',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/e9/39/e939570320c9e9657105055ddf26ac9316fed92b2340d4711d03b8c007a245ad.php:174:            'cacheinterface' => 'Spiral\\Translator\\Catalogue\\CacheInterface',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/e9/39/e939570320c9e9657105055ddf26ac9316fed92b2340d4711d03b8c007a245ad.php:225:          'cacheinterface' => 'Spiral\\Translator\\Catalogue\\CacheInterface',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/e9/39/e939570320c9e9657105055ddf26ac9316fed92b2340d4711d03b8c007a245ad.php:255:            'cacheinterface' => 'Spiral\\Translator\\Catalogue\\CacheInterface',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/e9/39/e939570320c9e9657105055ddf26ac9316fed92b2340d4711d03b8c007a245ad.php:306:          'cacheinterface' => 'Spiral\\Translator\\Catalogue\\CacheInterface',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/e9/39/e939570320c9e9657105055ddf26ac9316fed92b2340d4711d03b8c007a245ad.php:336:            'cacheinterface' => 'Spiral\\Translator\\Catalogue\\CacheInterface',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/c5/72/c572d35716cb748b02f5703b817298346b3640c22152e06f102dae286aff823c.php:3:// osfsl-/app/packages/spiral-api-errors/vendor/composer/../gian-tiaga/spiral-openapi/src/Response/Enum/HttpStatus.php-presentSymbols
packages/spiral-openapi/vendor/spiral/framework/src/Framework/Filter/JsonErrorsRenderer.php:22:            ->withStatus(422, 'The given data was invalid.');
packages/spiral-openapi/vendor/spiral/framework/src/Framework/Command/Views/CompileCommand.php:124:        $this->write("\n");
packages/spiral-openapi/vendor/spiral/framework/src/Framework/Command/Encrypter/KeyCommand.php:66:        $files->write($file, $content);
packages/spiral-openapi/vendor/spiral/framework/src/Framework/Translator/MemoryCache.php:8:use Spiral\Translator\Catalogue\CacheInterface;
packages/spiral-openapi/vendor/spiral/framework/src/Framework/Translator/MemoryCache.php:10:final class MemoryCache implements CacheInterface
packages/spiral-cqrs/vendor/spiral/framework/src/Prototype/src/Command/DumpCommand.php:32:        $this->write('Updating <fg=yellow>PrototypeTrait</fg=yellow> DOCComment... ');
packages/spiral-cqrs/vendor/spiral/framework/src/Prototype/src/Command/DumpCommand.php:41:            $writer->write($ref->getFileName(), $file);
packages/spiral-cqrs/vendor/spiral/framework/src/Prototype/src/Command/DumpCommand.php:43:            $this->write('<fg=red>' . $e->getMessage() . "</fg=red>\n");
packages/spiral-cqrs/vendor/spiral/framework/src/Prototype/src/Command/DumpCommand.php:48:        $this->write("<fg=green>complete</fg=green>\n");
packages/spiral-openapi/vendor/spiral/framework/src/Framework/Bootloader/Http/HttpBootloader.php:8:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-openapi/vendor/spiral/framework/src/Framework/Bootloader/Http/HttpBootloader.php:142:        ResponseFactoryInterface $responseFactory,
packages/spiral-openapi/vendor/spiral/framework/src/Framework/Bootloader/I18nBootloader.php:14:use Spiral\Translator\Catalogue\CacheInterface;
packages/spiral-openapi/vendor/spiral/framework/src/Framework/Bootloader/I18nBootloader.php:39:        CacheInterface::class => MemoryCache::class,
packages/spiral-cqrs/vendor/spiral/framework/src/Prototype/src/Bootloader/PrototypeBootloader.php:76:        'cache' => \Psr\SimpleCache\CacheInterface::class,
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/e1/59/e15906beda061af84da4a4ed034a5de118a787db86764e59d593fa424e0fc226.php:62:          'code' => '[\\Symfony\\Contracts\\Translation\\TranslatorInterface::class => \\Spiral\\Translator\\TranslatorInterface::class, \\Spiral\\Translator\\TranslatorInterface::class => \\Spiral\\Translator\\Translator::class, \\Spiral\\Translator\\CatalogueManagerInterface::class => \\Spiral\\Translator\\Catalogue\\CatalogueManager::class, \\Spiral\\Translator\\Catalogue\\LoaderInterface::class => \\Spiral\\Translator\\Catalogue\\CatalogueLoader::class, \\Spiral\\Translator\\Catalogue\\CacheInterface::class => \\Spiral\\Translator\\MemoryCache::class, \\Symfony\\Component\\Translation\\IdentityTranslator::class => [self::class, \'identityTranslator\']]',
packages/spiral-openapi/vendor/spiral/framework/src/AuthHttp/src/Middleware/Firewall/OverwriteFirewall.php:24:        return $handler->handle($request->withUri($this->uri))->withStatus($this->status);
packages/phpstan-strict-rules/vendor/autoload.php:12:            fwrite(STDERR, $err);
packages/spiral-openapi/vendor/spiral/framework/src/AuthHttp/src/Middleware/Firewall/RedirectFirewall.php:7:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-openapi/vendor/spiral/framework/src/AuthHttp/src/Middleware/Firewall/RedirectFirewall.php:17:        protected readonly ResponseFactoryInterface $responseFactory,
packages/spiral-openapi/vendor/spiral/framework/src/Boot/src/Memory.php:63:        $this->files->write(
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a6/d1/a6d1d3f6336f1146537a0957b9a42ddba9a418a7c4bea73ed601967c699f202f.php:3:// osfsl-/Users/gian_tiaga/Code/yoga-loka-spiral-2/packages/spiral-api-errors/vendor/composer/../gian-tiaga/spiral-openapi/src/Response/Enum/HttpStatus.php-PHPStan\BetterReflection\Reflection\ReflectionClass-GianTiaga\SpiralOpenApi\Response\Enum\HttpStatus
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a6/d1/a6d1d3f6336f1146537a0957b9a42ddba9a418a7c4bea73ed601967c699f202f.php:13:        'name' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a6/d1/a6d1d3f6336f1146537a0957b9a42ddba9a418a7c4bea73ed601967c699f202f.php:14:        'filename' => '/Users/gian_tiaga/Code/yoga-loka-spiral-2/packages/spiral-api-errors/vendor/composer/../gian-tiaga/spiral-openapi/src/Response/Enum/HttpStatus.php',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a6/d1/a6d1d3f6336f1146537a0957b9a42ddba9a418a7c4bea73ed601967c699f202f.php:18:    'name' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a6/d1/a6d1d3f6336f1146537a0957b9a42ddba9a418a7c4bea73ed601967c699f202f.php:19:    'shortName' => 'HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a6/d1/a6d1d3f6336f1146537a0957b9a42ddba9a418a7c4bea73ed601967c699f202f.php:47:        'declaringClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a6/d1/a6d1d3f6336f1146537a0957b9a42ddba9a418a7c4bea73ed601967c699f202f.php:48:        'implementingClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a6/d1/a6d1d3f6336f1146537a0957b9a42ddba9a418a7c4bea73ed601967c699f202f.php:78:        'declaringClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a6/d1/a6d1d3f6336f1146537a0957b9a42ddba9a418a7c4bea73ed601967c699f202f.php:79:        'implementingClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a6/d1/a6d1d3f6336f1146537a0957b9a42ddba9a418a7c4bea73ed601967c699f202f.php:140:        'declaringClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a6/d1/a6d1d3f6336f1146537a0957b9a42ddba9a418a7c4bea73ed601967c699f202f.php:141:        'implementingClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a6/d1/a6d1d3f6336f1146537a0957b9a42ddba9a418a7c4bea73ed601967c699f202f.php:142:        'currentClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a6/d1/a6d1d3f6336f1146537a0957b9a42ddba9a418a7c4bea73ed601967c699f202f.php:220:        'declaringClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a6/d1/a6d1d3f6336f1146537a0957b9a42ddba9a418a7c4bea73ed601967c699f202f.php:221:        'implementingClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a6/d1/a6d1d3f6336f1146537a0957b9a42ddba9a418a7c4bea73ed601967c699f202f.php:222:        'currentClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a6/d1/a6d1d3f6336f1146537a0957b9a42ddba9a418a7c4bea73ed601967c699f202f.php:319:        'declaringClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a6/d1/a6d1d3f6336f1146537a0957b9a42ddba9a418a7c4bea73ed601967c699f202f.php:320:        'implementingClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a6/d1/a6d1d3f6336f1146537a0957b9a42ddba9a418a7c4bea73ed601967c699f202f.php:321:        'currentClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/db/bf/dbbf520483e56382d40ae238d789ada06eeefb70ce3febc5b755fc68e02a1c62.php:3:// osfsl-/Users/gian_tiaga/Code/yoga-loka-spiral-2/tools/api-error/vendor/composer/../yoga-loka/openapi-tools/src/Response/AbstractJsonResponse.php-presentSymbols
packages/spiral-cqrs/vendor/spiral/framework/src/AuthHttp/src/Middleware/Firewall/OverwriteFirewall.php:24:        return $handler->handle($request->withUri($this->uri))->withStatus($this->status);
packages/spiral-cqrs/vendor/spiral/framework/src/AuthHttp/src/Middleware/Firewall/RedirectFirewall.php:7:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/AuthHttp/src/Middleware/Firewall/RedirectFirewall.php:17:        protected readonly ResponseFactoryInterface $responseFactory,
packages/spiral-cqrs/vendor/spiral/framework/src/Streams/src/StreamWrapper.php:209:    public function stream_write(string $data): int
packages/spiral-cqrs/vendor/spiral/framework/src/Streams/src/StreamWrapper.php:215:        return $this->stream->write($data);
packages/spiral-openapi/vendor/spiral/framework/src/Cache/src/CacheStorageRegistryInterface.php:7:use Psr\SimpleCache\CacheInterface;
packages/spiral-openapi/vendor/spiral/framework/src/Cache/src/CacheStorageRegistryInterface.php:17:    public function register(string $name, CacheInterface $cache): void;
packages/spiral-openapi/vendor/spiral/framework/src/Cache/src/CacheStorageRegistryInterface.php:20:     * @return array<non-empty-string, CacheInterface>
packages/spiral-openapi/vendor/spiral/framework/src/Cache/src/Storage/FileStorage.php:7:use Psr\SimpleCache\CacheInterface;
packages/spiral-openapi/vendor/spiral/framework/src/Cache/src/Storage/FileStorage.php:11:final class FileStorage implements CacheInterface
packages/spiral-openapi/vendor/spiral/framework/src/Cache/src/Storage/FileStorage.php:28:        return $this->files->write(
packages/spiral-openapi/vendor/spiral/framework/src/Cache/src/Storage/ArrayStorage.php:7:use Psr\SimpleCache\CacheInterface;
packages/spiral-openapi/vendor/spiral/framework/src/Cache/src/Storage/ArrayStorage.php:9:class ArrayStorage implements CacheInterface
packages/spiral-openapi/vendor/spiral/framework/src/Cache/src/CacheManager.php:8:use Psr\SimpleCache\CacheInterface;
packages/spiral-openapi/vendor/spiral/framework/src/Cache/src/CacheManager.php:16:    /** @var array<non-empty-string, CacheInterface> */
packages/spiral-openapi/vendor/spiral/framework/src/Cache/src/CacheManager.php:25:    public function storage(?string $name = null): CacheInterface
packages/spiral-openapi/vendor/spiral/framework/src/Cache/src/CacheManager.php:45:    public function register(string $name, CacheInterface $cache): void
packages/spiral-openapi/vendor/spiral/framework/src/Cache/src/CacheManager.php:55:    private function resolve(?string $name): CacheInterface
packages/spiral-cqrs/vendor/spiral/framework/src/Bridge/Stempler/src/StemplerCache.php:20:    public function write(string $key, string $content, array $paths = []): void
packages/spiral-cqrs/vendor/spiral/framework/src/Bridge/Stempler/src/StemplerCache.php:23:        $this->files->write(
packages/spiral-cqrs/vendor/spiral/framework/src/Bridge/Stempler/src/StemplerCache.php:31:        $this->files->write(
packages/spiral-openapi/vendor/spiral/framework/src/Cache/src/Bootloader/CacheBootloader.php:8:use Psr\SimpleCache\CacheInterface;
packages/spiral-openapi/vendor/spiral/framework/src/Cache/src/Bootloader/CacheBootloader.php:48:        $binder->bindInjector(CacheInterface::class, CacheInjector::class);
packages/spiral-openapi/vendor/spiral/framework/src/Cache/src/Bootloader/CacheBootloader.php:62:                static fn(CacheManager $manager): CacheInterface => $manager->storage($storageName),
packages/spiral-openapi/vendor/spiral/framework/src/Cache/src/Core/CacheInjector.php:12:use Psr\SimpleCache\CacheInterface;
packages/spiral-openapi/vendor/spiral/framework/src/Cache/src/Core/CacheInjector.php:15: * @implements InjectorInterface<CacheInterface>
packages/spiral-openapi/vendor/spiral/framework/src/Cache/src/Core/CacheInjector.php:23:    public function createInjection(\ReflectionClass $class, ?string $context = null): CacheInterface
packages/spiral-openapi/vendor/spiral/framework/src/Cache/src/Core/CacheInjector.php:51:    private function matchType(\ReflectionClass $class, ?string $context, CacheInterface $connection): void
packages/spiral-openapi/vendor/spiral/framework/src/Cache/src/Core/CacheInjector.php:57:        if ($className !== CacheInterface::class && !$connection instanceof $className) {
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/aa/3a/aa3a24b00d2deb07d9901a4d52bc011f29daf034baeed8c4d98fb2e055198605.php:19:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/aa/3a/aa3a24b00d2deb07d9901a4d52bc011f29daf034baeed8c4d98fb2e055198605.php:48:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/aa/3a/aa3a24b00d2deb07d9901a4d52bc011f29daf034baeed8c4d98fb2e055198605.php:77:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/aa/3a/aa3a24b00d2deb07d9901a4d52bc011f29daf034baeed8c4d98fb2e055198605.php:106:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-cqrs/vendor/spiral/framework/src/Bridge/Stempler/src/StemplerEngine.php:115:                $this->cache->write(
packages/spiral-openapi/vendor/spiral/framework/src/Cache/src/CacheStorageProviderInterface.php:7:use Psr\SimpleCache\CacheInterface;
packages/spiral-openapi/vendor/spiral/framework/src/Cache/src/CacheStorageProviderInterface.php:17:    public function storage(?string $name = null): CacheInterface;
packages/spiral-openapi/vendor/spiral/framework/src/Cache/src/CacheRepository.php:8:use Psr\SimpleCache\CacheInterface;
packages/spiral-openapi/vendor/spiral/framework/src/Cache/src/CacheRepository.php:22:class CacheRepository implements CacheInterface
packages/spiral-openapi/vendor/spiral/framework/src/Cache/src/CacheRepository.php:25:        protected CacheInterface $storage,
packages/spiral-openapi/vendor/spiral/framework/src/Cache/src/CacheRepository.php:175:    public function getStorage(): CacheInterface
packages/spiral-openapi/vendor/spiral/framework/src/Files/src/FilesInterface.php:61:    public function write(
packages/spiral-openapi/vendor/spiral/framework/src/Files/src/FilesInterface.php:71:     * @see write()
packages/spiral-openapi/vendor/spiral/framework/src/Files/src/Files.php:90:    public function write(
packages/spiral-openapi/vendor/spiral/framework/src/Files/src/Files.php:132:        return $this->write($filename, $data, $mode, $ensureDirectory, true);
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a9/d4/a9d43d6a36d5600ef334042930feb98e12856394d5152e369aedd9a193e3370e.php:33:    'parentClassName' => 'Tools\\OpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/b1/54/b1544380da36e41d4ffeaf708eefdebcbe9bb3f6dafa1d07e64262561ad1594c.php:62:          'code' => '[\\Symfony\\Contracts\\Translation\\TranslatorInterface::class => \\Spiral\\Translator\\TranslatorInterface::class, \\Spiral\\Translator\\TranslatorInterface::class => \\Spiral\\Translator\\Translator::class, \\Spiral\\Translator\\CatalogueManagerInterface::class => \\Spiral\\Translator\\Catalogue\\CatalogueManager::class, \\Spiral\\Translator\\Catalogue\\LoaderInterface::class => \\Spiral\\Translator\\Catalogue\\CatalogueLoader::class, \\Spiral\\Translator\\Catalogue\\CacheInterface::class => \\Spiral\\Translator\\MemoryCache::class, \\Symfony\\Component\\Translation\\IdentityTranslator::class => [self::class, \'identityTranslator\']]',
packages/spiral-cqrs/vendor/spiral/framework/src/Files/src/FilesInterface.php:61:    public function write(
packages/spiral-cqrs/vendor/spiral/framework/src/Files/src/FilesInterface.php:71:     * @see write()
packages/spiral-cqrs/vendor/spiral/framework/src/Files/src/Files.php:90:    public function write(
packages/spiral-cqrs/vendor/spiral/framework/src/Files/src/Files.php:132:        return $this->write($filename, $data, $mode, $ensureDirectory, true);
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a0/0e/a00ee047a327ca009cba5691df419764f8d6788e350fb166362481a4d3490f62.php:27:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a0/0e/a00ee047a327ca009cba5691df419764f8d6788e350fb166362481a4d3490f62.php:63:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a0/0e/a00ee047a327ca009cba5691df419764f8d6788e350fb166362481a4d3490f62.php:99:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a0/0e/a00ee047a327ca009cba5691df419764f8d6788e350fb166362481a4d3490f62.php:135:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a0/0e/a00ee047a327ca009cba5691df419764f8d6788e350fb166362481a4d3490f62.php:171:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a0/0e/a00ee047a327ca009cba5691df419764f8d6788e350fb166362481a4d3490f62.php:207:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a0/0e/a00ee047a327ca009cba5691df419764f8d6788e350fb166362481a4d3490f62.php:243:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a0/0e/a00ee047a327ca009cba5691df419764f8d6788e350fb166362481a4d3490f62.php:279:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a0/0e/a00ee047a327ca009cba5691df419764f8d6788e350fb166362481a4d3490f62.php:315:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a0/0e/a00ee047a327ca009cba5691df419764f8d6788e350fb166362481a4d3490f62.php:351:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a0/0e/a00ee047a327ca009cba5691df419764f8d6788e350fb166362481a4d3490f62.php:387:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a0/0e/a00ee047a327ca009cba5691df419764f8d6788e350fb166362481a4d3490f62.php:423:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a0/0e/a00ee047a327ca009cba5691df419764f8d6788e350fb166362481a4d3490f62.php:459:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a0/0e/a00ee047a327ca009cba5691df419764f8d6788e350fb166362481a4d3490f62.php:495:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a0/0e/a00ee047a327ca009cba5691df419764f8d6788e350fb166362481a4d3490f62.php:531:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a0/0e/a00ee047a327ca009cba5691df419764f8d6788e350fb166362481a4d3490f62.php:567:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a0/0e/a00ee047a327ca009cba5691df419764f8d6788e350fb166362481a4d3490f62.php:603:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a0/0e/a00ee047a327ca009cba5691df419764f8d6788e350fb166362481a4d3490f62.php:639:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a0/0e/a00ee047a327ca009cba5691df419764f8d6788e350fb166362481a4d3490f62.php:675:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a0/0e/a00ee047a327ca009cba5691df419764f8d6788e350fb166362481a4d3490f62.php:711:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a0/0e/a00ee047a327ca009cba5691df419764f8d6788e350fb166362481a4d3490f62.php:747:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a0/0e/a00ee047a327ca009cba5691df419764f8d6788e350fb166362481a4d3490f62.php:1262:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a0/0e/a00ee047a327ca009cba5691df419764f8d6788e350fb166362481a4d3490f62.php:1298:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a0/0e/a00ee047a327ca009cba5691df419764f8d6788e350fb166362481a4d3490f62.php:1334:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a0/0e/a00ee047a327ca009cba5691df419764f8d6788e350fb166362481a4d3490f62.php:1370:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a0/0e/a00ee047a327ca009cba5691df419764f8d6788e350fb166362481a4d3490f62.php:1406:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a0/0e/a00ee047a327ca009cba5691df419764f8d6788e350fb166362481a4d3490f62.php:1442:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/d3/79/d37913a5e606d00465f54af086d1dd95ce36fa508c12e6b922099a352c50b431.php:3:// osfsl-/app/packages/spiral-api-errors/vendor/composer/../gian-tiaga/spiral-openapi/src/Response/AbstractJsonResponse.php-PHPStan\BetterReflection\Reflection\ReflectionClass-GianTiaga\SpiralOpenApi\Response\AbstractJsonResponse
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/d3/79/d37913a5e606d00465f54af086d1dd95ce36fa508c12e6b922099a352c50b431.php:13:        'name' => 'GianTiaga\\SpiralOpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/d3/79/d37913a5e606d00465f54af086d1dd95ce36fa508c12e6b922099a352c50b431.php:14:        'filename' => '/app/packages/spiral-api-errors/vendor/composer/../gian-tiaga/spiral-openapi/src/Response/AbstractJsonResponse.php',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/d3/79/d37913a5e606d00465f54af086d1dd95ce36fa508c12e6b922099a352c50b431.php:18:    'name' => 'GianTiaga\\SpiralOpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/d3/79/d37913a5e606d00465f54af086d1dd95ce36fa508c12e6b922099a352c50b431.php:19:    'shortName' => 'AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/d3/79/d37913a5e606d00465f54af086d1dd95ce36fa508c12e6b922099a352c50b431.php:81:        'declaringClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/d3/79/d37913a5e606d00465f54af086d1dd95ce36fa508c12e6b922099a352c50b431.php:82:        'implementingClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/d3/79/d37913a5e606d00465f54af086d1dd95ce36fa508c12e6b922099a352c50b431.php:83:        'currentClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/d3/79/d37913a5e606d00465f54af086d1dd95ce36fa508c12e6b922099a352c50b431.php:116:        'declaringClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/d3/79/d37913a5e606d00465f54af086d1dd95ce36fa508c12e6b922099a352c50b431.php:117:        'implementingClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/d3/79/d37913a5e606d00465f54af086d1dd95ce36fa508c12e6b922099a352c50b431.php:118:        'currentClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/d3/79/d37913a5e606d00465f54af086d1dd95ce36fa508c12e6b922099a352c50b431.php:153:        'declaringClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/d3/79/d37913a5e606d00465f54af086d1dd95ce36fa508c12e6b922099a352c50b431.php:154:        'implementingClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/d3/79/d37913a5e606d00465f54af086d1dd95ce36fa508c12e6b922099a352c50b431.php:155:        'currentClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\AbstractJsonResponse',
packages/spiral-cqrs/vendor/spiral/framework/src/Snapshots/src/FileSnapshot.php:38:        $this->files->write(
packages/spiral-cqrs/vendor/spiral/framework/src/Snapshots/src/StorageSnapshot.php:37:            ->write($this->renderer->render($snapshot->getException(), $this->verbosity));
packages/spiral-cqrs/vendor/spiral/framework/src/Framework/Console/Sequence/RuntimeDirectory.php:23:        $output->write('Verifying runtime directory... ');
packages/spiral-cqrs/vendor/spiral/framework/src/Framework/Console/ConsoleDispatcher.php:67:        $output->write(
packages/spiral-openapi/vendor/spiral/framework/src/Http/src/ResponseWrapper.php:7:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-openapi/vendor/spiral/framework/src/Http/src/ResponseWrapper.php:25:        private readonly ResponseFactoryInterface $responseFactory,
packages/spiral-openapi/vendor/spiral/framework/src/Http/src/ResponseWrapper.php:99:        $response->getBody()->write($html);
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/0f/45/0f45adbb5cbe207dc3ea5e95094f5f3de5d12a212bb3ca43e171ebc6fcf5bca7.php:22:          'cacheinterface' => 'Spiral\\Translator\\Catalogue\\CacheInterface',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/0f/45/0f45adbb5cbe207dc3ea5e95094f5f3de5d12a212bb3ca43e171ebc6fcf5bca7.php:63:          'cacheinterface' => 'Spiral\\Translator\\Catalogue\\CacheInterface',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/0f/45/0f45adbb5cbe207dc3ea5e95094f5f3de5d12a212bb3ca43e171ebc6fcf5bca7.php:93:            'cacheinterface' => 'Spiral\\Translator\\Catalogue\\CacheInterface',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/0f/45/0f45adbb5cbe207dc3ea5e95094f5f3de5d12a212bb3ca43e171ebc6fcf5bca7.php:144:          'cacheinterface' => 'Spiral\\Translator\\Catalogue\\CacheInterface',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/0f/45/0f45adbb5cbe207dc3ea5e95094f5f3de5d12a212bb3ca43e171ebc6fcf5bca7.php:174:            'cacheinterface' => 'Spiral\\Translator\\Catalogue\\CacheInterface',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/0f/45/0f45adbb5cbe207dc3ea5e95094f5f3de5d12a212bb3ca43e171ebc6fcf5bca7.php:225:          'cacheinterface' => 'Spiral\\Translator\\Catalogue\\CacheInterface',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/0f/45/0f45adbb5cbe207dc3ea5e95094f5f3de5d12a212bb3ca43e171ebc6fcf5bca7.php:255:            'cacheinterface' => 'Spiral\\Translator\\Catalogue\\CacheInterface',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/0f/45/0f45adbb5cbe207dc3ea5e95094f5f3de5d12a212bb3ca43e171ebc6fcf5bca7.php:306:          'cacheinterface' => 'Spiral\\Translator\\Catalogue\\CacheInterface',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/0f/45/0f45adbb5cbe207dc3ea5e95094f5f3de5d12a212bb3ca43e171ebc6fcf5bca7.php:336:            'cacheinterface' => 'Spiral\\Translator\\Catalogue\\CacheInterface',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/97/a0/97a01dc63605c9d7f2128b8960ac03fef31c46ea20e243063817d668e3323a37.php:518:                  'name' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/97/a0/97a01dc63605c9d7f2128b8960ac03fef31c46ea20e243063817d668e3323a37.php:593:                'name' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-cqrs/vendor/spiral/framework/src/Framework/Filter/JsonErrorsRenderer.php:22:            ->withStatus(422, 'The given data was invalid.');
packages/spiral-openapi/vendor/spiral/framework/src/Http/src/CallableHandler.php:7:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-openapi/vendor/spiral/framework/src/Http/src/CallableHandler.php:25:        private readonly ResponseFactoryInterface $responseFactory,
packages/spiral-openapi/vendor/spiral/framework/src/Http/src/CallableHandler.php:70:                $result->getBody()->write($output);
packages/spiral-openapi/vendor/spiral/framework/src/Http/src/CallableHandler.php:79:            $response->getBody()->write((string) $result);
packages/spiral-openapi/vendor/spiral/framework/src/Http/src/CallableHandler.php:83:        $response->getBody()->write($output);
packages/spiral-openapi/vendor/spiral/framework/src/Http/src/ErrorHandler/PlainRenderer.php:7:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-openapi/vendor/spiral/framework/src/Http/src/ErrorHandler/PlainRenderer.php:18:        private readonly ResponseFactoryInterface $responseFactory,
packages/spiral-openapi/vendor/spiral/framework/src/Http/src/ErrorHandler/PlainRenderer.php:28:            $response->getBody()->write(\json_encode(['status' => $code]));
packages/spiral-openapi/vendor/spiral/framework/src/Http/src/ErrorHandler/PlainRenderer.php:30:            $response->getBody()->write("Error code: {$code}");
packages/spiral-cqrs/vendor/spiral/framework/src/Framework/Command/Views/CompileCommand.php:124:        $this->write("\n");
packages/spiral-openapi/vendor/spiral/framework/src/Http/src/Http.php:9:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-openapi/vendor/spiral/framework/src/Http/src/Http.php:33:        private readonly ResponseFactoryInterface $responseFactory,
packages/spiral-openapi/vendor/spiral/framework/src/Http/src/Stream/GeneratorStream.php:81:    public function write($string): int
packages/spiral-openapi/vendor/spiral/framework/src/Http/src/Traits/JsonTrait.php:27:        $response->getBody()->write(\json_encode($payload));
packages/spiral-openapi/vendor/spiral/framework/src/Http/src/Traits/JsonTrait.php:29:        return $response->withStatus($code)->withHeader('Content-Type', 'application/json');
packages/spiral-cqrs/vendor/spiral/framework/src/Framework/Command/Encrypter/KeyCommand.php:66:        $files->write($file, $content);
packages/spiral-openapi/vendor/spiral/framework/src/Broadcasting/src/Bootloader/WebsocketsBootloader.php:8:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-openapi/vendor/spiral/framework/src/Broadcasting/src/Bootloader/WebsocketsBootloader.php:29:            ResponseFactoryInterface $responseFactory,
packages/spiral-openapi/vendor/spiral/framework/src/Http/src/Middleware/ErrorHandlerMiddleware.php:7:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-openapi/vendor/spiral/framework/src/Http/src/Middleware/ErrorHandlerMiddleware.php:32:        private readonly ResponseFactoryInterface $responseFactory,
packages/spiral-openapi/vendor/spiral/framework/src/Http/src/Middleware/ErrorHandlerMiddleware.php:75:        $response->getBody()->write(
packages/spiral-cqrs/vendor/spiral/framework/src/Http/src/ResponseWrapper.php:7:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Http/src/ResponseWrapper.php:25:        private readonly ResponseFactoryInterface $responseFactory,
packages/spiral-cqrs/vendor/spiral/framework/src/Http/src/ResponseWrapper.php:99:        $response->getBody()->write($html);
packages/spiral-cqrs/vendor/spiral/framework/src/Framework/Translator/MemoryCache.php:8:use Spiral\Translator\Catalogue\CacheInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Framework/Translator/MemoryCache.php:10:final class MemoryCache implements CacheInterface
packages/spiral-openapi/vendor/spiral/framework/src/Csrf/src/Middleware/StrictCsrfFirewall.php:7:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-openapi/vendor/spiral/framework/src/Csrf/src/Middleware/StrictCsrfFirewall.php:20:    public function __construct(ResponseFactoryInterface $responseFactory)
packages/spiral-openapi/vendor/spiral/framework/src/Csrf/src/Middleware/CsrfFirewall.php:7:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-openapi/vendor/spiral/framework/src/Csrf/src/Middleware/CsrfFirewall.php:35:        private readonly ResponseFactoryInterface $responseFactory,
packages/spiral-openapi/vendor/spiral/framework/src/Broadcasting/src/Middleware/AuthorizationMiddleware.php:8:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-openapi/vendor/spiral/framework/src/Broadcasting/src/Middleware/AuthorizationMiddleware.php:22:        private readonly ResponseFactoryInterface $responseFactory,
packages/spiral-cqrs/vendor/spiral/framework/src/Http/src/CallableHandler.php:7:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Http/src/CallableHandler.php:25:        private readonly ResponseFactoryInterface $responseFactory,
packages/spiral-cqrs/vendor/spiral/framework/src/Http/src/CallableHandler.php:70:                $result->getBody()->write($output);
packages/spiral-cqrs/vendor/spiral/framework/src/Http/src/CallableHandler.php:79:            $response->getBody()->write((string) $result);
packages/spiral-cqrs/vendor/spiral/framework/src/Http/src/CallableHandler.php:83:        $response->getBody()->write($output);
packages/spiral-cqrs/vendor/spiral/framework/src/Http/src/ErrorHandler/PlainRenderer.php:7:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Http/src/ErrorHandler/PlainRenderer.php:18:        private readonly ResponseFactoryInterface $responseFactory,
packages/spiral-cqrs/vendor/spiral/framework/src/Http/src/ErrorHandler/PlainRenderer.php:28:            $response->getBody()->write(\json_encode(['status' => $code]));
packages/spiral-cqrs/vendor/spiral/framework/src/Http/src/ErrorHandler/PlainRenderer.php:30:            $response->getBody()->write("Error code: {$code}");
packages/spiral-cqrs/vendor/spiral/framework/src/Http/src/Http.php:9:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Http/src/Http.php:33:        private readonly ResponseFactoryInterface $responseFactory,
packages/spiral-openapi/vendor/spiral/framework/src/Storage/src/Bucket/WritableTrait.php:23:            return $this->write($pathname, '', $config);
packages/spiral-openapi/vendor/spiral/framework/src/Storage/src/Bucket/WritableTrait.php:29:    public function write(string $pathname, mixed $content, array $config = []): FileInterface
packages/spiral-openapi/vendor/spiral/framework/src/Storage/src/Bucket/WritableTrait.php:39:                    $fs->write($pathname, (string) $content, $config);
packages/spiral-openapi/vendor/spiral/framework/src/Storage/src/Bucket/WritableTrait.php:91:        return $storage->write($destination, $this->getStream($source), $config);
packages/spiral-openapi/vendor/spiral/framework/src/Storage/src/Bucket/WritableTrait.php:112:        $result = $storage->write($destination, $this->getStream($source), $config);
packages/spiral-openapi/vendor/spiral/framework/src/Storage/src/Bucket/WritableInterface.php:33:    public function write(string $pathname, mixed $content, array $config = []): FileInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Http/src/Stream/GeneratorStream.php:81:    public function write($string): int
packages/spiral-cqrs/vendor/spiral/framework/src/Framework/Bootloader/Http/HttpBootloader.php:8:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Framework/Bootloader/Http/HttpBootloader.php:142:        ResponseFactoryInterface $responseFactory,
packages/spiral-cqrs/vendor/spiral/framework/src/Http/src/Traits/JsonTrait.php:27:        $response->getBody()->write(\json_encode($payload));
packages/spiral-cqrs/vendor/spiral/framework/src/Http/src/Traits/JsonTrait.php:29:        return $response->withStatus($code)->withHeader('Content-Type', 'application/json');
packages/spiral-openapi/vendor/spiral/framework/src/Storage/src/Storage/WritableTrait.php:29:    public function write(string|\Stringable $id, mixed $content, array $config = []): FileInterface
packages/spiral-openapi/vendor/spiral/framework/src/Storage/src/Storage/WritableTrait.php:33:        return $this->bucket($name)->write($pathname, $content, $config);
packages/spiral-openapi/vendor/spiral/framework/src/Storage/src/Storage/WritableInterface.php:31:     * {@see BucketInterface::write()}
packages/spiral-openapi/vendor/spiral/framework/src/Storage/src/Storage/WritableInterface.php:37:    public function write(string|\Stringable $id, mixed $content, array $config = []): FileInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Http/src/Middleware/ErrorHandlerMiddleware.php:7:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Http/src/Middleware/ErrorHandlerMiddleware.php:32:        private readonly ResponseFactoryInterface $responseFactory,
packages/spiral-cqrs/vendor/spiral/framework/src/Http/src/Middleware/ErrorHandlerMiddleware.php:75:        $response->getBody()->write(
packages/spiral-cqrs/vendor/spiral/framework/src/Framework/Bootloader/I18nBootloader.php:14:use Spiral\Translator\Catalogue\CacheInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Framework/Bootloader/I18nBootloader.php:39:        CacheInterface::class => MemoryCache::class,
packages/spiral-cqrs/vendor/spiral/framework/src/Csrf/src/Middleware/StrictCsrfFirewall.php:7:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Csrf/src/Middleware/StrictCsrfFirewall.php:20:    public function __construct(ResponseFactoryInterface $responseFactory)
packages/spiral-cqrs/vendor/spiral/framework/src/Csrf/src/Middleware/CsrfFirewall.php:7:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Csrf/src/Middleware/CsrfFirewall.php:35:        private readonly ResponseFactoryInterface $responseFactory,
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/01/53/01536ff7d25f27031aa42bcf630d22d5316d5bfe2e1a2986091886828784711f.php:3:// osfsl-/Users/gian_tiaga/Code/yoga-loka-spiral-2/packages/spiral-api-errors/vendor/composer/../gian-tiaga/spiral-openapi/src/Response/AbstractJsonResponse.php-PHPStan\BetterReflection\Reflection\ReflectionClass-GianTiaga\SpiralOpenApi\Response\AbstractJsonResponse
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/01/53/01536ff7d25f27031aa42bcf630d22d5316d5bfe2e1a2986091886828784711f.php:13:        'name' => 'GianTiaga\\SpiralOpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/01/53/01536ff7d25f27031aa42bcf630d22d5316d5bfe2e1a2986091886828784711f.php:14:        'filename' => '/Users/gian_tiaga/Code/yoga-loka-spiral-2/packages/spiral-api-errors/vendor/composer/../gian-tiaga/spiral-openapi/src/Response/AbstractJsonResponse.php',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/01/53/01536ff7d25f27031aa42bcf630d22d5316d5bfe2e1a2986091886828784711f.php:18:    'name' => 'GianTiaga\\SpiralOpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/01/53/01536ff7d25f27031aa42bcf630d22d5316d5bfe2e1a2986091886828784711f.php:19:    'shortName' => 'AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/01/53/01536ff7d25f27031aa42bcf630d22d5316d5bfe2e1a2986091886828784711f.php:81:        'declaringClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/01/53/01536ff7d25f27031aa42bcf630d22d5316d5bfe2e1a2986091886828784711f.php:82:        'implementingClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/01/53/01536ff7d25f27031aa42bcf630d22d5316d5bfe2e1a2986091886828784711f.php:83:        'currentClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/01/53/01536ff7d25f27031aa42bcf630d22d5316d5bfe2e1a2986091886828784711f.php:116:        'declaringClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/01/53/01536ff7d25f27031aa42bcf630d22d5316d5bfe2e1a2986091886828784711f.php:117:        'implementingClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/01/53/01536ff7d25f27031aa42bcf630d22d5316d5bfe2e1a2986091886828784711f.php:118:        'currentClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/01/53/01536ff7d25f27031aa42bcf630d22d5316d5bfe2e1a2986091886828784711f.php:153:        'declaringClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/01/53/01536ff7d25f27031aa42bcf630d22d5316d5bfe2e1a2986091886828784711f.php:154:        'implementingClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/01/53/01536ff7d25f27031aa42bcf630d22d5316d5bfe2e1a2986091886828784711f.php:155:        'currentClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\AbstractJsonResponse',
packages/spiral-openapi/vendor/spiral/framework/src/Storage/src/File/WritableTrait.php:32:    public function write(mixed $content, array $config = []): FileInterface
packages/spiral-openapi/vendor/spiral/framework/src/Storage/src/File/WritableTrait.php:34:        return $this->getBucket()->write($this->getPathname(), $content, $config);
packages/spiral-openapi/vendor/spiral/framework/src/Storage/src/File/WritableInterface.php:26:     * {@see BucketInterface::write()}
packages/spiral-openapi/vendor/spiral/framework/src/Storage/src/File/WritableInterface.php:31:    public function write(mixed $content, array $config = []): FileInterface;
packages/spiral-openapi/vendor/spiral/framework/src/Scaffolder/src/Declaration/ConfigDeclaration.php:109:        $this->files->write(
packages/spiral-cqrs/vendor/spiral/framework/src/Storage/src/Bucket/WritableTrait.php:23:            return $this->write($pathname, '', $config);
packages/spiral-cqrs/vendor/spiral/framework/src/Storage/src/Bucket/WritableTrait.php:29:    public function write(string $pathname, mixed $content, array $config = []): FileInterface
packages/spiral-cqrs/vendor/spiral/framework/src/Storage/src/Bucket/WritableTrait.php:39:                    $fs->write($pathname, (string) $content, $config);
packages/spiral-cqrs/vendor/spiral/framework/src/Storage/src/Bucket/WritableTrait.php:91:        return $storage->write($destination, $this->getStream($source), $config);
packages/spiral-cqrs/vendor/spiral/framework/src/Storage/src/Bucket/WritableTrait.php:112:        $result = $storage->write($destination, $this->getStream($source), $config);
packages/spiral-openapi/vendor/spiral/framework/src/Exceptions/src/ExceptionHandler.php:115:            \fwrite($this->output, $this->render($e, verbosity: $this->verbosity, format: $format));
packages/spiral-cqrs/vendor/spiral/framework/src/Storage/src/Bucket/WritableInterface.php:33:    public function write(string $pathname, mixed $content, array $config = []): FileInterface;
packages/spiral-openapi/vendor/spiral/framework/src/Scaffolder/src/Command/AbstractCommand.php:76:        (new Writer($this->files))->write($filename, $declaration->getFile());
packages/spiral-cqrs/vendor/spiral/framework/src/Storage/src/Storage/WritableTrait.php:29:    public function write(string|\Stringable $id, mixed $content, array $config = []): FileInterface
packages/spiral-cqrs/vendor/spiral/framework/src/Storage/src/Storage/WritableTrait.php:33:        return $this->bucket($name)->write($pathname, $content, $config);
packages/spiral-cqrs/vendor/spiral/framework/src/Storage/src/Storage/WritableInterface.php:31:     * {@see BucketInterface::write()}
packages/spiral-cqrs/vendor/spiral/framework/src/Storage/src/Storage/WritableInterface.php:37:    public function write(string|\Stringable $id, mixed $content, array $config = []): FileInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Storage/src/File/WritableTrait.php:32:    public function write(mixed $content, array $config = []): FileInterface
packages/spiral-cqrs/vendor/spiral/framework/src/Storage/src/File/WritableTrait.php:34:        return $this->getBucket()->write($this->getPathname(), $content, $config);
packages/spiral-cqrs/vendor/spiral/framework/src/Storage/src/File/WritableInterface.php:26:     * {@see BucketInterface::write()}
packages/spiral-cqrs/vendor/spiral/framework/src/Storage/src/File/WritableInterface.php:31:    public function write(mixed $content, array $config = []): FileInterface;
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/5b/e1/5be1cb6d02f8ab481f34cd562def678407b7abf7cc9a66a4ea9f6e0287960c41.php:33:    'parentClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\AbstractJsonResponse',
packages/spiral-openapi/vendor/spiral/framework/src/Translator/src/Catalogue/CatalogueManager.php:19:    private readonly CacheInterface $cache;
packages/spiral-openapi/vendor/spiral/framework/src/Translator/src/Catalogue/CatalogueManager.php:26:        ?CacheInterface $cache = null,
packages/spiral-openapi/vendor/spiral/framework/src/Translator/src/Catalogue/CacheInterface.php:7:interface CacheInterface
packages/spiral-openapi/vendor/spiral/framework/src/Translator/src/Catalogue/NullCache.php:7:final class NullCache implements CacheInterface
packages/spiral-cqrs/vendor/spiral/framework/src/Exceptions/src/ExceptionHandler.php:115:            \fwrite($this->output, $this->render($e, verbosity: $this->verbosity, format: $format));
packages/spiral-openapi/vendor/spiral/framework/src/Prototype/src/Command/DumpCommand.php:32:        $this->write('Updating <fg=yellow>PrototypeTrait</fg=yellow> DOCComment... ');
packages/spiral-openapi/vendor/spiral/framework/src/Prototype/src/Command/DumpCommand.php:41:            $writer->write($ref->getFileName(), $file);
packages/spiral-openapi/vendor/spiral/framework/src/Prototype/src/Command/DumpCommand.php:43:            $this->write('<fg=red>' . $e->getMessage() . "</fg=red>\n");
packages/spiral-openapi/vendor/spiral/framework/src/Prototype/src/Command/DumpCommand.php:48:        $this->write("<fg=green>complete</fg=green>\n");
packages/spiral-cqrs/vendor/spiral/framework/src/Translator/src/Catalogue/CatalogueManager.php:19:    private readonly CacheInterface $cache;
packages/spiral-cqrs/vendor/spiral/framework/src/Translator/src/Catalogue/CatalogueManager.php:26:        ?CacheInterface $cache = null,
packages/spiral-cqrs/vendor/spiral/framework/src/Boot/src/Memory.php:63:        $this->files->write(
packages/spiral-cqrs/vendor/spiral/framework/src/Translator/src/Catalogue/CacheInterface.php:7:interface CacheInterface
packages/spiral-cqrs/vendor/spiral/framework/src/Translator/src/Catalogue/NullCache.php:7:final class NullCache implements CacheInterface
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/65/58/6558146ffbc3b7bbd0cd24dfe0d0f5765fb2be19656b076dbbc59769fde164fc.php:518:                  'name' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/65/58/6558146ffbc3b7bbd0cd24dfe0d0f5765fb2be19656b076dbbc59769fde164fc.php:593:                'name' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-openapi/vendor/spiral/framework/src/Prototype/src/Bootloader/PrototypeBootloader.php:76:        'cache' => \Psr\SimpleCache\CacheInterface::class,
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/91/fa/91fa72af12ddbf37a490805f8630a705e9f3840815bef7967f15499e5b027ce9.php:82:         'functionName' => 'withStatus',
packages/spiral-cqrs/vendor/phpunit/phpunit/src/Runner/Baseline/Writer.php:31:    public function write(string $baselineFile, Baseline $baseline): void
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/31/4e/314e1e648128d568863dc11fd3c51442792376c9506450dc287a9bc9b2ec9f22.php:3:// osfsl-/Users/gian_tiaga/Code/yoga-loka-spiral-2/tools/api-error/vendor/composer/../yoga-loka/openapi-tools/src/Response/Enum/HttpStatus.php-presentSymbols
packages/spiral-openapi/vendor/spiral/composer-publish-plugin/src/PublishPlugin.php:145:                $this->io->write(
packages/spiral-openapi/vendor/spiral/composer-publish-plugin/src/PublishPlugin.php:155:                    $this->io->write('<info>OK</info>');
packages/spiral-openapi/vendor/spiral/composer-publish-plugin/src/PublishPlugin.php:157:                    $this->io->write(sprintf('<error>%s</error>', $e->getMessage()));
packages/spiral-openapi/vendor/spiral/composer-publish-plugin/src/PublishPlugin.php:188:            $this->io->write($p->getOutput() . $p->getErrorOutput());
packages/spiral-cqrs/vendor/spiral/framework/src/Cache/src/CacheStorageRegistryInterface.php:7:use Psr\SimpleCache\CacheInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Cache/src/CacheStorageRegistryInterface.php:17:    public function register(string $name, CacheInterface $cache): void;
packages/spiral-cqrs/vendor/spiral/framework/src/Cache/src/CacheStorageRegistryInterface.php:20:     * @return array<non-empty-string, CacheInterface>
packages/spiral-cqrs/vendor/spiral/framework/src/Cache/src/Storage/FileStorage.php:7:use Psr\SimpleCache\CacheInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Cache/src/Storage/FileStorage.php:11:final class FileStorage implements CacheInterface
packages/spiral-cqrs/vendor/spiral/framework/src/Cache/src/Storage/FileStorage.php:28:        return $this->files->write(
packages/spiral-openapi/vendor/phpunit/phpunit/src/Runner/Baseline/Writer.php:31:    public function write(string $baselineFile, Baseline $baseline): void
packages/spiral-cqrs/vendor/spiral/framework/src/Cache/src/Storage/ArrayStorage.php:7:use Psr\SimpleCache\CacheInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Cache/src/Storage/ArrayStorage.php:9:class ArrayStorage implements CacheInterface
packages/spiral-cqrs/vendor/spiral/framework/src/Cache/src/CacheManager.php:8:use Psr\SimpleCache\CacheInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Cache/src/CacheManager.php:16:    /** @var array<non-empty-string, CacheInterface> */
packages/spiral-cqrs/vendor/spiral/framework/src/Cache/src/CacheManager.php:25:    public function storage(?string $name = null): CacheInterface
packages/spiral-cqrs/vendor/spiral/framework/src/Cache/src/CacheManager.php:45:    public function register(string $name, CacheInterface $cache): void
packages/spiral-cqrs/vendor/spiral/framework/src/Cache/src/CacheManager.php:55:    private function resolve(?string $name): CacheInterface
packages/spiral-cqrs/vendor/spiral/framework/src/Cache/src/Bootloader/CacheBootloader.php:8:use Psr\SimpleCache\CacheInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Cache/src/Bootloader/CacheBootloader.php:48:        $binder->bindInjector(CacheInterface::class, CacheInjector::class);
packages/spiral-cqrs/vendor/spiral/framework/src/Cache/src/Bootloader/CacheBootloader.php:62:                static fn(CacheManager $manager): CacheInterface => $manager->storage($storageName),
packages/spiral-cqrs/vendor/spiral/framework/src/Cache/src/Core/CacheInjector.php:12:use Psr\SimpleCache\CacheInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Cache/src/Core/CacheInjector.php:15: * @implements InjectorInterface<CacheInterface>
packages/spiral-cqrs/vendor/spiral/framework/src/Cache/src/Core/CacheInjector.php:23:    public function createInjection(\ReflectionClass $class, ?string $context = null): CacheInterface
packages/spiral-cqrs/vendor/spiral/framework/src/Cache/src/Core/CacheInjector.php:51:    private function matchType(\ReflectionClass $class, ?string $context, CacheInterface $connection): void
packages/spiral-cqrs/vendor/spiral/framework/src/Cache/src/Core/CacheInjector.php:57:        if ($className !== CacheInterface::class && !$connection instanceof $className) {
packages/spiral-cqrs/vendor/spiral/framework/src/Cache/src/CacheStorageProviderInterface.php:7:use Psr\SimpleCache\CacheInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Cache/src/CacheStorageProviderInterface.php:17:    public function storage(?string $name = null): CacheInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Cache/src/CacheRepository.php:8:use Psr\SimpleCache\CacheInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Cache/src/CacheRepository.php:22:class CacheRepository implements CacheInterface
packages/spiral-cqrs/vendor/spiral/framework/src/Cache/src/CacheRepository.php:25:        protected CacheInterface $storage,
packages/spiral-cqrs/vendor/spiral/framework/src/Cache/src/CacheRepository.php:175:    public function getStorage(): CacheInterface
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/36/ba/36bab137f2d4a408dc2d9472cf2141c1927b85698b997bd181f139200b42e43d.php:33:    'parentClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\AbstractJsonResponse',
packages/spiral-openapi/vendor/composer/platform_check.php:17:            fwrite(STDERR, 'Composer detected issues in your platform:' . PHP_EOL.PHP_EOL . implode(PHP_EOL, $issues) . PHP_EOL.PHP_EOL);
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/6e/7e/6e7e08db7ae5cda20c1772e16a52e4c2d18bca18616e26e213ed14488fb989f9.php:22:          'cacheinterface' => 'Spiral\\Translator\\Catalogue\\CacheInterface',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/6e/7e/6e7e08db7ae5cda20c1772e16a52e4c2d18bca18616e26e213ed14488fb989f9.php:63:          'cacheinterface' => 'Spiral\\Translator\\Catalogue\\CacheInterface',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/6e/7e/6e7e08db7ae5cda20c1772e16a52e4c2d18bca18616e26e213ed14488fb989f9.php:93:            'cacheinterface' => 'Spiral\\Translator\\Catalogue\\CacheInterface',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/6e/7e/6e7e08db7ae5cda20c1772e16a52e4c2d18bca18616e26e213ed14488fb989f9.php:144:          'cacheinterface' => 'Spiral\\Translator\\Catalogue\\CacheInterface',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/6e/7e/6e7e08db7ae5cda20c1772e16a52e4c2d18bca18616e26e213ed14488fb989f9.php:174:            'cacheinterface' => 'Spiral\\Translator\\Catalogue\\CacheInterface',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/6e/7e/6e7e08db7ae5cda20c1772e16a52e4c2d18bca18616e26e213ed14488fb989f9.php:225:          'cacheinterface' => 'Spiral\\Translator\\Catalogue\\CacheInterface',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/6e/7e/6e7e08db7ae5cda20c1772e16a52e4c2d18bca18616e26e213ed14488fb989f9.php:255:            'cacheinterface' => 'Spiral\\Translator\\Catalogue\\CacheInterface',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/6e/7e/6e7e08db7ae5cda20c1772e16a52e4c2d18bca18616e26e213ed14488fb989f9.php:306:          'cacheinterface' => 'Spiral\\Translator\\Catalogue\\CacheInterface',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/6e/7e/6e7e08db7ae5cda20c1772e16a52e4c2d18bca18616e26e213ed14488fb989f9.php:336:            'cacheinterface' => 'Spiral\\Translator\\Catalogue\\CacheInterface',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/38/76/38762ff11a114eca3b05ef836dd86350145f7c01532442f30fd863023f89535c.php:62:          'code' => '[\\Symfony\\Contracts\\Translation\\TranslatorInterface::class => \\Spiral\\Translator\\TranslatorInterface::class, \\Spiral\\Translator\\TranslatorInterface::class => \\Spiral\\Translator\\Translator::class, \\Spiral\\Translator\\CatalogueManagerInterface::class => \\Spiral\\Translator\\Catalogue\\CatalogueManager::class, \\Spiral\\Translator\\Catalogue\\LoaderInterface::class => \\Spiral\\Translator\\Catalogue\\CatalogueLoader::class, \\Spiral\\Translator\\Catalogue\\CacheInterface::class => \\Spiral\\Translator\\MemoryCache::class, \\Symfony\\Component\\Translation\\IdentityTranslator::class => [self::class, \'identityTranslator\']]',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/38/19/3819f647893394421f7c7b3b18bc9fa6d8e13034cdb7174b7b0d1c85200706e5.php:917:         'functionName' => 'withStatus',
packages/spiral-openapi/vendor/spiral/framework/src/Streams/src/StreamWrapper.php:209:    public function stream_write(string $data): int
packages/spiral-openapi/vendor/spiral/framework/src/Streams/src/StreamWrapper.php:215:        return $this->stream->write($data);
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/43/5f/435f5c4a45d552d7ed8f79593820d90600c16c86f78a1b7533b4a8bb69fd9a0a.php:447:      'withStatus' => 
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/43/5f/435f5c4a45d552d7ed8f79593820d90600c16c86f78a1b7533b4a8bb69fd9a0a.php:449:        'name' => 'withStatus',
packages/spiral-openapi/vendor/spiral/framework/src/Bridge/Stempler/src/StemplerCache.php:20:    public function write(string $key, string $content, array $paths = []): void
packages/spiral-openapi/vendor/spiral/framework/src/Bridge/Stempler/src/StemplerCache.php:23:        $this->files->write(
packages/spiral-openapi/vendor/spiral/framework/src/Bridge/Stempler/src/StemplerCache.php:31:        $this->files->write(
packages/spiral-openapi/vendor/spiral/framework/src/Bridge/Stempler/src/StemplerEngine.php:115:                $this->cache->write(
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/4c/b0/4cb019a105ada669237d1adad0c42f9788424762bdf0844d2b279cc85b13c46e.php:473:                'name' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-cqrs/vendor/spiral/framework/src/Broadcasting/src/Bootloader/WebsocketsBootloader.php:8:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Broadcasting/src/Bootloader/WebsocketsBootloader.php:29:            ResponseFactoryInterface $responseFactory,
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/10/18/10188c3318ca1c116da6893e682ebe1ddbc20f6d30327ea616cac538b1bd3508.php:518:                  'name' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/10/18/10188c3318ca1c116da6893e682ebe1ddbc20f6d30327ea616cac538b1bd3508.php:593:                'name' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-cqrs/vendor/spiral/framework/src/Broadcasting/src/Middleware/AuthorizationMiddleware.php:8:use Psr\Http\Message\ResponseFactoryInterface;
packages/spiral-cqrs/vendor/spiral/framework/src/Broadcasting/src/Middleware/AuthorizationMiddleware.php:22:        private readonly ResponseFactoryInterface $responseFactory,
packages/spiral-cqrs/vendor/spiral/framework/src/Scaffolder/src/Declaration/ConfigDeclaration.php:109:        $this->files->write(
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/74/98/7498d61b7f8e93564f35ce382c1ab680611a9bb95ef00d08dfa6244592a2f271.php:3:// osfsl-/Users/gian_tiaga/Code/yoga-loka-spiral-2/packages/spiral-api-errors/vendor/composer/../gian-tiaga/spiral-openapi/src/Response/AbstractJsonResponse.php-presentSymbols
packages/spiral-cqrs/vendor/spiral/framework/src/Scaffolder/src/Command/AbstractCommand.php:76:        (new Writer($this->files))->write($filename, $declaration->getFile());
packages/spiral-cqrs/vendor/spiral/composer-publish-plugin/src/PublishPlugin.php:145:                $this->io->write(
packages/spiral-cqrs/vendor/spiral/composer-publish-plugin/src/PublishPlugin.php:155:                    $this->io->write('<info>OK</info>');
packages/spiral-cqrs/vendor/spiral/composer-publish-plugin/src/PublishPlugin.php:157:                    $this->io->write(sprintf('<error>%s</error>', $e->getMessage()));
packages/spiral-cqrs/vendor/spiral/composer-publish-plugin/src/PublishPlugin.php:188:            $this->io->write($p->getOutput() . $p->getErrorOutput());
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/1f/16/1f16b552718bdaba3665422bf458e522c80f6964a2312ffe0925d1801b36b99d.php:3:// osfsl-/Users/gian_tiaga/Code/yoga-loka-spiral-2/tools/api-error/vendor/composer/../yoga-loka/openapi-tools/src/Response/AbstractJsonResponse.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Tools\OpenApi\Response\AbstractJsonResponse
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/1f/16/1f16b552718bdaba3665422bf458e522c80f6964a2312ffe0925d1801b36b99d.php:13:        'name' => 'Tools\\OpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/1f/16/1f16b552718bdaba3665422bf458e522c80f6964a2312ffe0925d1801b36b99d.php:14:        'filename' => '/Users/gian_tiaga/Code/yoga-loka-spiral-2/tools/api-error/vendor/composer/../yoga-loka/openapi-tools/src/Response/AbstractJsonResponse.php',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/1f/16/1f16b552718bdaba3665422bf458e522c80f6964a2312ffe0925d1801b36b99d.php:18:    'name' => 'Tools\\OpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/1f/16/1f16b552718bdaba3665422bf458e522c80f6964a2312ffe0925d1801b36b99d.php:19:    'shortName' => 'AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/1f/16/1f16b552718bdaba3665422bf458e522c80f6964a2312ffe0925d1801b36b99d.php:81:        'declaringClassName' => 'Tools\\OpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/1f/16/1f16b552718bdaba3665422bf458e522c80f6964a2312ffe0925d1801b36b99d.php:82:        'implementingClassName' => 'Tools\\OpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/1f/16/1f16b552718bdaba3665422bf458e522c80f6964a2312ffe0925d1801b36b99d.php:83:        'currentClassName' => 'Tools\\OpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/1f/16/1f16b552718bdaba3665422bf458e522c80f6964a2312ffe0925d1801b36b99d.php:116:        'declaringClassName' => 'Tools\\OpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/1f/16/1f16b552718bdaba3665422bf458e522c80f6964a2312ffe0925d1801b36b99d.php:117:        'implementingClassName' => 'Tools\\OpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/1f/16/1f16b552718bdaba3665422bf458e522c80f6964a2312ffe0925d1801b36b99d.php:118:        'currentClassName' => 'Tools\\OpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/1f/16/1f16b552718bdaba3665422bf458e522c80f6964a2312ffe0925d1801b36b99d.php:153:        'declaringClassName' => 'Tools\\OpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/1f/16/1f16b552718bdaba3665422bf458e522c80f6964a2312ffe0925d1801b36b99d.php:154:        'implementingClassName' => 'Tools\\OpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/1f/16/1f16b552718bdaba3665422bf458e522c80f6964a2312ffe0925d1801b36b99d.php:155:        'currentClassName' => 'Tools\\OpenApi\\Response\\AbstractJsonResponse',
packages/spiral-openapi/vendor/phpunit/phpunit/src/Util/PHP/JobRunner.php:179:        fwrite($pipes[0], $job->code());
packages/spiral-cqrs/vendor/phpunit/phpunit/src/Util/PHP/JobRunner.php:179:        fwrite($pipes[0], $job->code());
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/27/95/2795d527128c094354642fe49357cc39783c139cbba98bafcbc7cc8e170721b9.php:3:// osfsl-/app/packages/spiral-api-errors/vendor/composer/../gian-tiaga/spiral-openapi/src/Response/Enum/HttpStatus.php-PHPStan\BetterReflection\Reflection\ReflectionClass-GianTiaga\SpiralOpenApi\Response\Enum\HttpStatus
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/27/95/2795d527128c094354642fe49357cc39783c139cbba98bafcbc7cc8e170721b9.php:13:        'name' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/27/95/2795d527128c094354642fe49357cc39783c139cbba98bafcbc7cc8e170721b9.php:14:        'filename' => '/app/packages/spiral-api-errors/vendor/composer/../gian-tiaga/spiral-openapi/src/Response/Enum/HttpStatus.php',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/27/95/2795d527128c094354642fe49357cc39783c139cbba98bafcbc7cc8e170721b9.php:18:    'name' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/27/95/2795d527128c094354642fe49357cc39783c139cbba98bafcbc7cc8e170721b9.php:19:    'shortName' => 'HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/27/95/2795d527128c094354642fe49357cc39783c139cbba98bafcbc7cc8e170721b9.php:47:        'declaringClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/27/95/2795d527128c094354642fe49357cc39783c139cbba98bafcbc7cc8e170721b9.php:48:        'implementingClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/27/95/2795d527128c094354642fe49357cc39783c139cbba98bafcbc7cc8e170721b9.php:78:        'declaringClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/27/95/2795d527128c094354642fe49357cc39783c139cbba98bafcbc7cc8e170721b9.php:79:        'implementingClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/27/95/2795d527128c094354642fe49357cc39783c139cbba98bafcbc7cc8e170721b9.php:140:        'declaringClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/27/95/2795d527128c094354642fe49357cc39783c139cbba98bafcbc7cc8e170721b9.php:141:        'implementingClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/27/95/2795d527128c094354642fe49357cc39783c139cbba98bafcbc7cc8e170721b9.php:142:        'currentClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/27/95/2795d527128c094354642fe49357cc39783c139cbba98bafcbc7cc8e170721b9.php:220:        'declaringClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/27/95/2795d527128c094354642fe49357cc39783c139cbba98bafcbc7cc8e170721b9.php:221:        'implementingClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/27/95/2795d527128c094354642fe49357cc39783c139cbba98bafcbc7cc8e170721b9.php:222:        'currentClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/27/95/2795d527128c094354642fe49357cc39783c139cbba98bafcbc7cc8e170721b9.php:319:        'declaringClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/27/95/2795d527128c094354642fe49357cc39783c139cbba98bafcbc7cc8e170721b9.php:320:        'implementingClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/27/95/2795d527128c094354642fe49357cc39783c139cbba98bafcbc7cc8e170721b9.php:321:        'currentClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-openapi/vendor/phpunit/phpunit/src/TextUI/Output/Printer/DefaultPrinter.php:115:        fwrite($this->stream, $buffer);
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/c6/b5/c6b5a7098f78d8e8b35a7b0e774a50433f5f068c94e4e2c4a3637ee2061f3dc8.php:56:            'name' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/c6/b5/c6b5a7098f78d8e8b35a7b0e774a50433f5f068c94e4e2c4a3637ee2061f3dc8.php:62:          'code' => '\\GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus::Ok',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/c6/b5/c6b5a7098f78d8e8b35a7b0e774a50433f5f068c94e4e2c4a3637ee2061f3dc8.php:155:      'withStatus' => 
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/c6/b5/c6b5a7098f78d8e8b35a7b0e774a50433f5f068c94e4e2c4a3637ee2061f3dc8.php:157:        'name' => 'withStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/c6/b5/c6b5a7098f78d8e8b35a7b0e774a50433f5f068c94e4e2c4a3637ee2061f3dc8.php:169:                'name' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/c6/b5/c6b5a7098f78d8e8b35a7b0e774a50433f5f068c94e4e2c4a3637ee2061f3dc8.php:411:            'name' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-cqrs/vendor/phpunit/phpunit/src/TextUI/Output/Printer/DefaultPrinter.php:115:        fwrite($this->stream, $buffer);
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/0e/33/0e33685a42ff4b3b677293b3a494bbc2b69accd6a9c516d48b22489748c49f73.php:105:      'withStatus' => 
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/0e/33/0e33685a42ff4b3b677293b3a494bbc2b69accd6a9c516d48b22489748c49f73.php:107:        'name' => 'withStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/0e/6a/0e6ab826066b4f8436ab4b3f8bc2add486bc5c93b8b27a2b0c3848b6c4976881.php:578:                'name' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/94/07/9407c7b2c33a45379a57396568f7ab29b38d2d25cd9ebace2d83a7e4548f5c57.php:56:            'name' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/94/07/9407c7b2c33a45379a57396568f7ab29b38d2d25cd9ebace2d83a7e4548f5c57.php:62:          'code' => '\\GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus::Ok',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/94/07/9407c7b2c33a45379a57396568f7ab29b38d2d25cd9ebace2d83a7e4548f5c57.php:155:      'withStatus' => 
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/94/07/9407c7b2c33a45379a57396568f7ab29b38d2d25cd9ebace2d83a7e4548f5c57.php:157:        'name' => 'withStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/94/07/9407c7b2c33a45379a57396568f7ab29b38d2d25cd9ebace2d83a7e4548f5c57.php:169:                'name' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/94/07/9407c7b2c33a45379a57396568f7ab29b38d2d25cd9ebace2d83a7e4548f5c57.php:411:            'name' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/9e/1e/9e1e24fa0dfd30cd3dc459ec3575d7f03b936b52774688b6725c5ee5ff59448f.php:3:// osfsl-/Users/gian_tiaga/Code/yoga-loka-spiral-2/packages/spiral-api-errors/vendor/composer/../gian-tiaga/spiral-openapi/src/Response/Enum/HttpStatus.php-presentSymbols
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/fe/8b/fe8b4c101088161fd5531ac24c079271f23cc516ebf54b1001c0d57d8ef461dd.php:26:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/fe/8b/fe8b4c101088161fd5531ac24c079271f23cc516ebf54b1001c0d57d8ef461dd.php:61:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/fe/8b/fe8b4c101088161fd5531ac24c079271f23cc516ebf54b1001c0d57d8ef461dd.php:96:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/fe/8b/fe8b4c101088161fd5531ac24c079271f23cc516ebf54b1001c0d57d8ef461dd.php:131:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/fe/8b/fe8b4c101088161fd5531ac24c079271f23cc516ebf54b1001c0d57d8ef461dd.php:166:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/fe/8b/fe8b4c101088161fd5531ac24c079271f23cc516ebf54b1001c0d57d8ef461dd.php:201:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/fe/8b/fe8b4c101088161fd5531ac24c079271f23cc516ebf54b1001c0d57d8ef461dd.php:236:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/fe/8b/fe8b4c101088161fd5531ac24c079271f23cc516ebf54b1001c0d57d8ef461dd.php:271:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/fe/8b/fe8b4c101088161fd5531ac24c079271f23cc516ebf54b1001c0d57d8ef461dd.php:306:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/fe/8b/fe8b4c101088161fd5531ac24c079271f23cc516ebf54b1001c0d57d8ef461dd.php:341:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/fe/8b/fe8b4c101088161fd5531ac24c079271f23cc516ebf54b1001c0d57d8ef461dd.php:376:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/fe/8b/fe8b4c101088161fd5531ac24c079271f23cc516ebf54b1001c0d57d8ef461dd.php:411:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/fe/8b/fe8b4c101088161fd5531ac24c079271f23cc516ebf54b1001c0d57d8ef461dd.php:446:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/fe/8b/fe8b4c101088161fd5531ac24c079271f23cc516ebf54b1001c0d57d8ef461dd.php:481:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/fe/8b/fe8b4c101088161fd5531ac24c079271f23cc516ebf54b1001c0d57d8ef461dd.php:516:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/fe/8b/fe8b4c101088161fd5531ac24c079271f23cc516ebf54b1001c0d57d8ef461dd.php:551:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/fe/8b/fe8b4c101088161fd5531ac24c079271f23cc516ebf54b1001c0d57d8ef461dd.php:586:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/fe/8b/fe8b4c101088161fd5531ac24c079271f23cc516ebf54b1001c0d57d8ef461dd.php:621:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/fe/8b/fe8b4c101088161fd5531ac24c079271f23cc516ebf54b1001c0d57d8ef461dd.php:1135:          'httpstatus' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/9e/c7/9ec7c826d9ad2ea8416b25b0064c0562df3acc41e4d53a8c69fd13134188b3c1.php:82:         'functionName' => 'withStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/9e/ae/9eaeb0a4809fb4872955598bfca252f2978f9bcd692295fd2e77d68bb606d367.php:33:    'parentClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/cf/6c/cf6c4977a1aff6f9e55349d7090cfee639921089e1f80dd7b104d2fd45c97ddb.php:33:    'parentClassName' => 'GianTiaga\\SpiralOpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/3d/20/3d20cf756e79fa3e71b82d4c0a839277a9debdeed183b3edf800bd6b8883672d.php:3:// osfsl-/Users/gian_tiaga/Code/yoga-loka-spiral-2/tools/api-error/vendor/composer/../yoga-loka/openapi-tools/src/Response/Enum/HttpStatus.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Tools\OpenApi\Response\Enum\HttpStatus
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/3d/20/3d20cf756e79fa3e71b82d4c0a839277a9debdeed183b3edf800bd6b8883672d.php:13:        'name' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/3d/20/3d20cf756e79fa3e71b82d4c0a839277a9debdeed183b3edf800bd6b8883672d.php:14:        'filename' => '/Users/gian_tiaga/Code/yoga-loka-spiral-2/tools/api-error/vendor/composer/../yoga-loka/openapi-tools/src/Response/Enum/HttpStatus.php',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/3d/20/3d20cf756e79fa3e71b82d4c0a839277a9debdeed183b3edf800bd6b8883672d.php:18:    'name' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/3d/20/3d20cf756e79fa3e71b82d4c0a839277a9debdeed183b3edf800bd6b8883672d.php:19:    'shortName' => 'HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/3d/20/3d20cf756e79fa3e71b82d4c0a839277a9debdeed183b3edf800bd6b8883672d.php:47:        'declaringClassName' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/3d/20/3d20cf756e79fa3e71b82d4c0a839277a9debdeed183b3edf800bd6b8883672d.php:48:        'implementingClassName' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/3d/20/3d20cf756e79fa3e71b82d4c0a839277a9debdeed183b3edf800bd6b8883672d.php:78:        'declaringClassName' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/3d/20/3d20cf756e79fa3e71b82d4c0a839277a9debdeed183b3edf800bd6b8883672d.php:79:        'implementingClassName' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/3d/20/3d20cf756e79fa3e71b82d4c0a839277a9debdeed183b3edf800bd6b8883672d.php:140:        'declaringClassName' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/3d/20/3d20cf756e79fa3e71b82d4c0a839277a9debdeed183b3edf800bd6b8883672d.php:141:        'implementingClassName' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/3d/20/3d20cf756e79fa3e71b82d4c0a839277a9debdeed183b3edf800bd6b8883672d.php:142:        'currentClassName' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/3d/20/3d20cf756e79fa3e71b82d4c0a839277a9debdeed183b3edf800bd6b8883672d.php:220:        'declaringClassName' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/3d/20/3d20cf756e79fa3e71b82d4c0a839277a9debdeed183b3edf800bd6b8883672d.php:221:        'implementingClassName' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/3d/20/3d20cf756e79fa3e71b82d4c0a839277a9debdeed183b3edf800bd6b8883672d.php:222:        'currentClassName' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/3d/20/3d20cf756e79fa3e71b82d4c0a839277a9debdeed183b3edf800bd6b8883672d.php:319:        'declaringClassName' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/3d/20/3d20cf756e79fa3e71b82d4c0a839277a9debdeed183b3edf800bd6b8883672d.php:320:        'implementingClassName' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/3d/20/3d20cf756e79fa3e71b82d4c0a839277a9debdeed183b3edf800bd6b8883672d.php:321:        'currentClassName' => 'Tools\\OpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/35/69/35694cf006b59871f66d284a42ac65ff16dea6de0ef5e0921daa63eb05606f82.php:917:         'functionName' => 'withStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/33/4a/334a91c14e843daa33578c4ff31925e8826d7f5384bae9db7811b20b59abec7f.php:29:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/33/4a/334a91c14e843daa33578c4ff31925e8826d7f5384bae9db7811b20b59abec7f.php:66:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/33/4a/334a91c14e843daa33578c4ff31925e8826d7f5384bae9db7811b20b59abec7f.php:103:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/33/4a/334a91c14e843daa33578c4ff31925e8826d7f5384bae9db7811b20b59abec7f.php:140:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/33/4a/334a91c14e843daa33578c4ff31925e8826d7f5384bae9db7811b20b59abec7f.php:177:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/33/4a/334a91c14e843daa33578c4ff31925e8826d7f5384bae9db7811b20b59abec7f.php:214:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/33/4a/334a91c14e843daa33578c4ff31925e8826d7f5384bae9db7811b20b59abec7f.php:251:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/33/4a/334a91c14e843daa33578c4ff31925e8826d7f5384bae9db7811b20b59abec7f.php:288:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/33/4a/334a91c14e843daa33578c4ff31925e8826d7f5384bae9db7811b20b59abec7f.php:325:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/33/4a/334a91c14e843daa33578c4ff31925e8826d7f5384bae9db7811b20b59abec7f.php:362:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/33/4a/334a91c14e843daa33578c4ff31925e8826d7f5384bae9db7811b20b59abec7f.php:399:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/33/4a/334a91c14e843daa33578c4ff31925e8826d7f5384bae9db7811b20b59abec7f.php:436:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/33/4a/334a91c14e843daa33578c4ff31925e8826d7f5384bae9db7811b20b59abec7f.php:473:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/33/4a/334a91c14e843daa33578c4ff31925e8826d7f5384bae9db7811b20b59abec7f.php:510:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/33/4a/334a91c14e843daa33578c4ff31925e8826d7f5384bae9db7811b20b59abec7f.php:547:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/33/4a/334a91c14e843daa33578c4ff31925e8826d7f5384bae9db7811b20b59abec7f.php:584:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/33/4a/334a91c14e843daa33578c4ff31925e8826d7f5384bae9db7811b20b59abec7f.php:621:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/33/e9/33e92e48f935689492832cf583850e621c8163d163331c234c7989959ce94d45.php:82:         'functionName' => 'withStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/b2/d3/b2d3ca2e25ff28db80daaa989cfd201749e8754969c67418e69f40e8726bb789.php:447:      'withStatus' => 
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/b2/d3/b2d3ca2e25ff28db80daaa989cfd201749e8754969c67418e69f40e8726bb789.php:449:        'name' => 'withStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/a3/70/a370737922ec91363bb854ea32c8271357d527e01d6e26180ab19762c1e911e2.php:33:    'parentClassName' => 'Tools\\OpenApi\\Response\\AbstractJsonResponse',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/b4/d5/b4d5259a4948b7c6f5095b5363d914666fc20ebdca980f55e653fa97b2fdbb6c.php:447:      'withStatus' => 
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/b4/d5/b4d5259a4948b7c6f5095b5363d914666fc20ebdca980f55e653fa97b2fdbb6c.php:449:        'name' => 'withStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/d8/44/d8440df14e21599158e1cc338772bdd9ec640f6903965b2bf9af959066e07417.php:3:// osfsl-/app/packages/spiral-api-errors/vendor/composer/../gian-tiaga/spiral-openapi/src/Response/AbstractJsonResponse.php-presentSymbols
packages/spiral-openapi/vendor/autoload.php:12:            fwrite(STDERR, $err);
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/b3/0a/b30a24f5cea2c32846a1088dd27855aba9485efb837936084ece0c65810b8753.php:26:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/b3/0a/b30a24f5cea2c32846a1088dd27855aba9485efb837936084ece0c65810b8753.php:61:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/b3/0a/b30a24f5cea2c32846a1088dd27855aba9485efb837936084ece0c65810b8753.php:96:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/b3/0a/b30a24f5cea2c32846a1088dd27855aba9485efb837936084ece0c65810b8753.php:131:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/b3/0a/b30a24f5cea2c32846a1088dd27855aba9485efb837936084ece0c65810b8753.php:166:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/b3/0a/b30a24f5cea2c32846a1088dd27855aba9485efb837936084ece0c65810b8753.php:201:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/b3/0a/b30a24f5cea2c32846a1088dd27855aba9485efb837936084ece0c65810b8753.php:236:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/b3/0a/b30a24f5cea2c32846a1088dd27855aba9485efb837936084ece0c65810b8753.php:271:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/b3/0a/b30a24f5cea2c32846a1088dd27855aba9485efb837936084ece0c65810b8753.php:306:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/b3/0a/b30a24f5cea2c32846a1088dd27855aba9485efb837936084ece0c65810b8753.php:341:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/b3/0a/b30a24f5cea2c32846a1088dd27855aba9485efb837936084ece0c65810b8753.php:376:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/b3/0a/b30a24f5cea2c32846a1088dd27855aba9485efb837936084ece0c65810b8753.php:411:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/b3/0a/b30a24f5cea2c32846a1088dd27855aba9485efb837936084ece0c65810b8753.php:446:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/b3/0a/b30a24f5cea2c32846a1088dd27855aba9485efb837936084ece0c65810b8753.php:481:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/b3/0a/b30a24f5cea2c32846a1088dd27855aba9485efb837936084ece0c65810b8753.php:516:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/b3/0a/b30a24f5cea2c32846a1088dd27855aba9485efb837936084ece0c65810b8753.php:551:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/b3/0a/b30a24f5cea2c32846a1088dd27855aba9485efb837936084ece0c65810b8753.php:586:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/b3/0a/b30a24f5cea2c32846a1088dd27855aba9485efb837936084ece0c65810b8753.php:621:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-api-errors/runtime/phpstan/cache/PHPStan/b3/0a/b30a24f5cea2c32846a1088dd27855aba9485efb837936084ece0c65810b8753.php:1135:          'httpstatus' => 'GianTiaga\\SpiralOpenApi\\Response\\Enum\\HttpStatus',
packages/spiral-cqrs/vendor/composer/platform_check.php:17:            fwrite(STDERR, 'Composer detected issues in your platform:' . PHP_EOL.PHP_EOL . implode(PHP_EOL, $issues) . PHP_EOL.PHP_EOL);
packages/spiral-openapi/vendor/phpunit/phpunit/src/TextUI/Application.php:297:                (new Writer)->write(
packages/spiral-cqrs/vendor/phpunit/phpunit/src/TextUI/Application.php:297:                (new Writer)->write(
packages/spiral-openapi/vendor/nette/utils/src/Utils/Process.php:341:	 * Writes the whole string to STDIN, handling partial writes. Stops (and lets fwrite() warn) on a broken pipe,
packages/spiral-openapi/vendor/nette/utils/src/Utils/Process.php:348:			$bytes = fwrite($this->inputPipe, substr($string, $written));
packages/spiral-openapi/vendor/nette/utils/src/Utils/FileInfo.php:62:	public function write(string $content): void
packages/spiral-openapi/vendor/nette/utils/src/Utils/FileInfo.php:64:		FileSystem::write($this->getPathname(), $content);
packages/spiral-openapi/vendor/nette/utils/src/Utils/FileSystem.php:214:	public static function write(string $file, string $content, ?int $mode = 0o666): void

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Cache/src/Bootloader/CacheBootloader.php | sed -n '1,160p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba app/config/cache.php | sed -n '1,140p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Cache\Bootloader;
     6	
     7	use Psr\EventDispatcher\EventDispatcherInterface;
     8	use Psr\SimpleCache\CacheInterface;
     9	use Spiral\Boot\Bootloader\Bootloader;
    10	use Spiral\Boot\DirectoriesInterface;
    11	use Spiral\Boot\EnvironmentInterface;
    12	use Spiral\Cache\CacheManager;
    13	use Spiral\Cache\CacheStorageProviderInterface;
    14	use Spiral\Cache\CacheStorageRegistryInterface;
    15	use Spiral\Cache\Config\CacheConfig;
    16	use Spiral\Cache\Core\CacheInjector;
    17	use Spiral\Cache\Storage\ArrayStorage;
    18	use Spiral\Cache\Storage\FileStorage;
    19	use Spiral\Config\ConfiguratorInterface;
    20	use Spiral\Config\Patch\Append;
    21	use Spiral\Core\BinderInterface;
    22	use Spiral\Core\FactoryInterface;
    23	
    24	final class CacheBootloader extends Bootloader
    25	{
    26	    protected const SINGLETONS = [
    27	        CacheStorageRegistryInterface::class => CacheManager::class,
    28	        CacheStorageProviderInterface::class => CacheManager::class,
    29	        CacheManager::class => [self::class, 'initCacheManager'],
    30	    ];
    31	
    32	    public function __construct(
    33	        private readonly ConfiguratorInterface $config,
    34	    ) {}
    35	
    36	    public function registerTypeAlias(string $storageClass, string $alias): void
    37	    {
    38	        $this->config->modify(
    39	            CacheConfig::CONFIG,
    40	            new Append('typeAliases', $alias, $storageClass),
    41	        );
    42	    }
    43	
    44	    public function init(BinderInterface $binder, EnvironmentInterface $env, DirectoriesInterface $dirs): void
    45	    {
    46	        $this->initConfig($env, $dirs);
    47	
    48	        $binder->bindInjector(CacheInterface::class, CacheInjector::class);
    49	    }
    50	
    51	    private function initCacheManager(
    52	        BinderInterface $binder,
    53	        FactoryInterface $factory,
    54	        CacheConfig $config,
    55	        ?EventDispatcherInterface $dispatcher = null,
    56	    ): CacheManager {
    57	        $manager = new CacheManager($config, $factory, $dispatcher);
    58	
    59	        foreach ($config->getAliases() as $alias => $storageName) {
    60	            $binder->bind(
    61	                $alias,
    62	                static fn(CacheManager $manager): CacheInterface => $manager->storage($storageName),
    63	            );
    64	        }
    65	
    66	        return $manager;
    67	    }
    68	
    69	    private function initConfig(EnvironmentInterface $env, DirectoriesInterface $dirs): void
    70	    {
    71	        $this->config->setDefaults(
    72	            CacheConfig::CONFIG,
    73	            [
    74	                'default' => $env->get('CACHE_STORAGE', 'array'),
    75	                'aliases' => [],
    76	                'storages' => [
    77	                    'array' => [
    78	                        'type' => 'array',
    79	                    ],
    80	                    'file' => [
    81	                        'type' => 'file',
    82	                        'path' => $dirs->get('runtime') . 'cache',
    83	                    ],
    84	                ],
    85	                'typeAliases' => [
    86	                    'array' => ArrayStorage::class,
    87	                    'file' => FileStorage::class,
    88	                ],
    89	            ],
    90	        );
    91	    }
    92	}

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	use App\Shared\Infrastructure\Cache\RedisCacheStorage;
     6	use Spiral\Cache\Storage\ArrayStorage;
     7	use Spiral\Cache\Storage\FileStorage;
     8	
     9	/**
    10	 * Конфигурация компонента кэша.
    11	 *
    12	 * @link https://spiral.dev/docs/basics-cache
    13	 */
    14	return [
    15	    /**
    16	     * Хранилище кэша по умолчанию.
    17	     */
    18	    'default' => \env('CACHE_STORAGE', 'rr-local'),
    19	
    20	    /**
    21	     * Алиасы для предметных хранилищ.
    22	     */
    23	    'aliases' => [
    24	        // 'user-data' => [
    25	        //     'storage' => 'rr-local',
    26	        //     'prefix' => 'user_'
    27	        // ],
    28	        // 'blog-data' => 'rr-local',
    29	    ],
    30	
    31	    /**
    32	     * Хранилища кэша и их типы.
    33	     */
    34	    'storages' => [
    35	
    36	        'rr-local' => [
    37	            'type' => 'roadrunner',
    38	            'driver' => 'local',
    39	        ],
    40	
    41	        'local' => [
    42	            'type' => ArrayStorage::class,
    43	        ],
    44	
    45	        'file' => [
    46	            'type' => FileStorage::class,
    47	            'path' => \directory('runtime') . 'cache',
    48	        ],
    49	
    50	        'redis' => [
    51	            'type' => RedisCacheStorage::class,
    52	            'dsn' => \env('REDIS_DSN', 'redis://redis:6379/0'),
    53	            'namespace' => \env('REDIS_CACHE_NAMESPACE', 'yoga_loka_cache'),
    54	            'defaultLifetime' => (int) \env('REDIS_CACHE_DEFAULT_LIFETIME', 0),
    55	        ],
    56	    ],
    57	
    58	    /**
    59	     * Алиасы типов хранилищ.
    60	     */
    61	    'typeAliases' => [],
    62	];

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Cache/src/Core/CacheInjector.php | sed -n '1,130p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba packages/spiral-openapi/src/Response/ErrorResponse.php packages/spiral-openapi/src/Response/AbstractJsonResponse.php packages/spiral-openapi/src/Response/ConvertsToHttpResponse.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Cache\Core;
     6	
     7	use Spiral\Cache\CacheRepository;
     8	use Spiral\Core\Container\InjectorInterface;
     9	use Spiral\Core\Exception\Container\ContainerException;
    10	use Spiral\Cache\Exception\InvalidArgumentException;
    11	use Spiral\Cache\CacheStorageProviderInterface;
    12	use Psr\SimpleCache\CacheInterface;
    13	
    14	/**
    15	 * @implements InjectorInterface<CacheInterface>
    16	 */
    17	final class CacheInjector implements InjectorInterface
    18	{
    19	    public function __construct(
    20	        private readonly CacheStorageProviderInterface $provider,
    21	    ) {}
    22	
    23	    public function createInjection(\ReflectionClass $class, ?string $context = null): CacheInterface
    24	    {
    25	        try {
    26	            if ($context === null) {
    27	                $connection = $this->provider->storage();
    28	            } else {
    29	                // Get Cache by context
    30	                try {
    31	                    $connection = $this->provider->storage($context);
    32	                } catch (InvalidArgumentException) {
    33	                    // Case when context doesn't match to configured connections
    34	                    return $this->provider->storage();
    35	                }
    36	            }
    37	
    38	            $this->matchType($class, $context, $connection);
    39	        } catch (\Throwable $e) {
    40	            throw new ContainerException(\sprintf("Can't inject the required cache. %s", $e->getMessage()), 0, $e);
    41	        }
    42	
    43	        return $connection;
    44	    }
    45	
    46	    /**
    47	     * Check the resolved connection implements required type
    48	     *
    49	     * @throws \RuntimeException
    50	     */
    51	    private function matchType(\ReflectionClass $class, ?string $context, CacheInterface $connection): void
    52	    {
    53	        if ($connection::class === CacheRepository::class) {
    54	            $connection = $connection->getStorage();
    55	        }
    56	        $className = $class->getName();
    57	        if ($className !== CacheInterface::class && !$connection instanceof $className) {
    58	            throw new \RuntimeException(
    59	                \sprintf(
    60	                    "The cache obtained by the context `%s` doesn't match the type `%s`.",
    61	                    $context ?? 'NULL',
    62	                    $className,
    63	                ),
    64	            );
    65	        }
    66	    }
    67	}

 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

exec
/bin/zsh -lc "nl -ba packages/spiral-openapi/src/Response/ErrorResponse.php | sed -n '1,120p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare (strict_types=1);
     4	
     5	namespace GianTiaga\SpiralOpenApi\Response;
     6	
     7	final class ErrorResponse extends AbstractJsonResponse
     8	{
     9	    public function __construct(public readonly string $message, public readonly int|null $code = null) {}
    10	}

exec
/bin/zsh -lc "rg -n \"CACHE_STORAGE|REDIS_DSN|ENCRYPTER_KEY|MAILER_DSN|QUEUE\" .env .env.sample docker -g '*'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
.env.sample:4:ENCRYPTER_KEY={encrypt-key}
.env.sample:24:QUEUE_CONNECTION=rabbitmq
.env.sample:33:RABBITMQ_QUEUE_NAME=yoga_loka_jobs
.env.sample:34:RABBITMQ_QUEUE_PREFETCH=100
.env.sample:35:RABBITMQ_QUEUE_DURABLE=true
.env.sample:40:RABBITMQ_REQUEUE_ON_FAIL=false
.env.sample:49:CACHE_STORAGE=redis
.env.sample:50:REDIS_DSN=redis://redis:6379/0
.env.sample:56:SESSION_CACHE_STORAGE=redis
.env.sample:99:MAILER_DSN=smtp://mailpit:1025
.env.sample:100:MAILER_QUEUE=local
.env.sample:101:MAILER_QUEUE_CONNECTION=null
.env.sample:123:TEMPORAL_TASK_QUEUE=default
.env:4:ENCRYPTER_KEY=def0000041f5187e8d25b67c645b03f081377a67c90d2916f0fe065622ddccfd3c463b5e9ec224c956aa91ab8289f8f6b7ce9f204cabdae15e981fc7116cefa446abaa16
.env:22:QUEUE_CONNECTION=in-memory
.env:23:CACHE_STORAGE=redis
.env:24:REDIS_DSN=redis://redis:6379/0
.env:30:SESSION_CACHE_STORAGE=redis
.env:57:MAILER_DSN=smtp://mailpit:1025
.env:58:MAILER_QUEUE=local
.env:59:MAILER_QUEUE_CONNECTION=null
.env:81:TEMPORAL_TASK_QUEUE=default
docker/README.md:114:`CACHE_STORAGE=local`, потому что существующие unit-тесты проверяют этот режим.
docker/README.md:115:Test queue остаётся `QUEUE_CONNECTION=sync`, чтобы `make test` не требовал
docker/README.md:120:- `RABBITMQ_QUEUE_NAME`
docker/README.md:121:- `RABBITMQ_QUEUE_PREFETCH`
docker/README.md:122:- `RABBITMQ_QUEUE_DURABLE`
docker/README.md:127:- `RABBITMQ_REQUEUE_ON_FAIL`
docker/docker-compose.dev.yml:64:      CACHE_STORAGE: local
docker/docker-compose.dev.yml:65:      QUEUE_CONNECTION: sync
docker/rr/http-jobs.yaml:55:        prefetch: ${RABBITMQ_QUEUE_PREFETCH:-100}
docker/rr/http-jobs.yaml:56:        queue: ${RABBITMQ_QUEUE_NAME:-yoga_loka_jobs}
docker/rr/http-jobs.yaml:65:        requeue_on_fail: ${RABBITMQ_REQUEUE_ON_FAIL:-false}
docker/rr/http-jobs.yaml:66:        durable: ${RABBITMQ_QUEUE_DURABLE:-true}

exec
/bin/zsh -lc "nl -ba packages/spiral-openapi/src/Response/AbstractJsonResponse.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba packages/spiral-openapi/src/Response/ConvertsToHttpResponse.php | sed -n '1,80p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare (strict_types=1);
     4	
     5	namespace GianTiaga\SpiralOpenApi\Response;
     6	
     7	use Psr\Http\Message\ResponseInterface;
     8	
     9	interface ConvertsToHttpResponse
    10	{
    11	    public function toResponse(): ResponseInterface;
    12	}

 succeeded in 0ms:
     1	<?php
     2	
     3	declare (strict_types=1);
     4	
     5	namespace GianTiaga\SpiralOpenApi\Response;
     6	
     7	use Nyholm\Psr7\Response;
     8	use Psr\Http\Message\ResponseInterface;
     9	use GianTiaga\SpiralOpenApi\Response\Enum\ContentType;
    10	use GianTiaga\SpiralOpenApi\Response\Enum\HttpHeader;
    11	
    12	abstract class AbstractJsonResponse implements \JsonSerializable, ConvertsToHttpResponse
    13	{
    14	    use HasHttpResponseMetadata;
    15	    final public function jsonSerialize(): mixed
    16	    {
    17	        $payload = [];
    18	        foreach ((new \ReflectionObject($this))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
    19	            $payload[$property->getName()] = $property->getValue(object: $this);
    20	        }
    21	        return $payload;
    22	    }
    23	    public function toResponse(): ResponseInterface
    24	    {
    25	        return new Response(status: $this->responseStatus()->value, headers: $this->responseHeaders(), body: \json_encode(value: $this, flags: JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    26	    }
    27	    /**
    28	     * @return array<string, mixed>
    29	     */
    30	    protected function defaultHeaders(): array
    31	    {
    32	        return [HttpHeader::ContentType->value => [ContentType::Json->value]];
    33	    }
    34	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Outbox/Repository/OutboxEventRepository.php | sed -n '1,240p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Outbox\Repository;
     6	
     7	use App\Modules\Outbox\Domain\Collection\OutboxEventCollection;
     8	use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
     9	use App\Modules\Outbox\Domain\Enum\OutboxEventStatus;
    10	use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
    11	use App\Modules\Outbox\Domain\ValueObject\OutboxRelayBatchSize;
    12	use Cycle\Database\Injection\Parameter;
    13	use Cycle\ORM\Select\Repository;
    14	
    15	/**
    16	 * @extends Repository<StoredOutboxEvent>
    17	 */
    18	final class OutboxEventRepository extends Repository
    19	{
    20	    public function findById(OutboxEventId $outboxEventId): StoredOutboxEvent|null
    21	    {
    22	        return $this->findByPK($outboxEventId->value());
    23	    }
    24	
    25	    public function findPendingForRelay(
    26	        OutboxRelayBatchSize $outboxRelayBatchSize,
    27	        \DateTimeImmutable $now,
    28	    ): OutboxEventCollection {
    29	        return new OutboxEventCollection(
    30	            $this->select()
    31	                ->where('status', 'in', new Parameter([
    32	                    OutboxEventStatus::Pending->value,
    33	                    OutboxEventStatus::Publishing->value,
    34	                ]))
    35	                ->where('available_at', '<=', $now)
    36	                // Порядок выборки согласован с составным индексом (status, available_at, id):
    37	                // сначала по времени доступности (естественный порядок relay), затем id
    38	                // (UUID v7, хронологический) как стабильный tie-breaker.
    39	                ->orderBy([
    40	                    'available_at' => 'ASC',
    41	                    'id' => 'ASC',
    42	                ])
    43	                ->forUpdate()
    44	                ->limit($outboxRelayBatchSize->value())
    45	                ->fetchAll(),
    46	        );
    47	    }
    48	}

exec
/bin/zsh -lc "rg -n \"class Repository|function select\\(|function forUpdate\" vendor/cycle/orm/src -g '*.php'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
vendor/cycle/orm/src/Select.php:363:    public function forUpdate(): static
vendor/cycle/orm/src/Select/Repository.php:17:class Repository implements RepositoryInterface
vendor/cycle/orm/src/Select/Repository.php:49:    public function select(): Select
vendor/cycle/orm/src/Select/Repository.php:54:    public function forUpdate(): static
vendor/cycle/orm/src/Service/Implementation/RepositoryProvider.php:19:final class RepositoryProvider implements RepositoryProviderInterface

exec
/bin/zsh -lc "rg -n \"function forUpdate|forUpdate\\(\" vendor/cycle app/src tests -g '*.php'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
vendor/cycle/orm/src/Select/Repository.php:54:    public function forUpdate(): static
vendor/cycle/orm/src/Select/Repository.php:57:        $repository->select->forUpdate();
vendor/cycle/orm/src/Select/QueryBuilder.php:25: * @method QueryBuilder forUpdate()
vendor/cycle/orm/src/Select.php:358:     *     $user = $select->where('id', 1)->forUpdate()->fetchOne();
vendor/cycle/orm/src/Select.php:363:    public function forUpdate(): static
vendor/cycle/orm/src/Select.php:365:        $this->builder->forUpdate();
vendor/cycle/database/src/Query/SelectQuery.php:130:    public function forUpdate(): self
app/src/Modules/Outbox/Repository/OutboxEventRepository.php:43:                ->forUpdate()

exec
/bin/zsh -lc "rg -n \"function forUpdate\" vendor/cycle/database vendor/cycle/orm -g '*.php'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
vendor/cycle/database/src/Query/SelectQuery.php:130:    public function forUpdate(): self
vendor/cycle/orm/src/Select/Repository.php:54:    public function forUpdate(): static
vendor/cycle/orm/src/Select.php:363:    public function forUpdate(): static

exec
/bin/zsh -lc "nl -ba vendor/cycle/orm/src/Select/Repository.php | sed -n '1,100p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba vendor/cycle/orm/src/Select.php | sed -n '340,375p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Cycle\ORM\Select;
     6	
     7	use Cycle\ORM\RepositoryInterface;
     8	use Cycle\ORM\Select;
     9	
    10	/**
    11	 * Repository provides ability to load entities and construct queries.
    12	 *
    13	 * @template TEntity of object
    14	 *
    15	 * @implements RepositoryInterface<TEntity>
    16	 */
    17	class Repository implements RepositoryInterface
    18	{
    19	    /**
    20	     * Create repository linked to one specific selector.
    21	     *
    22	     * @param Select<TEntity> $select
    23	     */
    24	    public function __construct(
    25	        /** @readonly */
    26	        protected Select $select,
    27	    ) {}
    28	
    29	    public function findByPK($id): ?object
    30	    {
    31	        return $this->select()->wherePK($id)->fetchOne();
    32	    }
    33	
    34	    public function findOne(array $scope = []): ?object
    35	    {
    36	        return $this->select()->fetchOne($scope);
    37	    }
    38	
    39	    public function findAll(array $scope = [], array $orderBy = []): iterable
    40	    {
    41	        return $this->select()->where($scope)->orderBy($orderBy)->fetchAll();
    42	    }
    43	
    44	    /**
    45	     * Get selector associated with the repository.
    46	     *
    47	     * @return Select<TEntity>
    48	     */
    49	    public function select(): Select
    50	    {
    51	        return clone $this->select;
    52	    }
    53	
    54	    public function forUpdate(): static
    55	    {
    56	        $repository = clone $this;
    57	        $repository->select->forUpdate();
    58	
    59	        return $repository;
    60	    }
    61	
    62	    /**
    63	     * Repositories are always immutable by default.
    64	     */
    65	    public function __clone()
    66	    {
    67	        $this->select = clone $this->select;
    68	    }
    69	}

 succeeded in 0ms:
   340	     *     $select->orderBy(new \Cycle\Database\Injection\Fragment('RAND()'));
   341	     *
   342	     * @param non-empty-string|FragmentInterface|array<non-empty-string, non-empty-string> $expression
   343	     * @param 'ASC'|'DESC'|null $direction Sorting direction, default ASC.
   344	     *
   345	     * @return static<TEntity>
   346	     */
   347	    public function orderBy(string|FragmentInterface|array $expression, ?string $direction = 'ASC'): static
   348	    {
   349	        $this->builder->orderBy($expression, $direction);
   350	        return $this;
   351	    }
   352	
   353	    /**
   354	     * Add a FOR UPDATE lock to the query. Selected rows will be locked for the duration
   355	     * of the current transaction, preventing other transactions from modifying them.
   356	     *
   357	     *     // Inside a transaction
   358	     *     $user = $select->where('id', 1)->forUpdate()->fetchOne();
   359	     *     $user->balance -= 100;
   360	     *
   361	     * @return static<TEntity>
   362	     */
   363	    public function forUpdate(): static
   364	    {
   365	        $this->builder->forUpdate();
   366	        return $this;
   367	    }
   368	
   369	    /**
   370	     * Filter by JSON field value using exact match.
   371	     *
   372	     *     $select->whereJson('settings->theme', 'dark');
   373	     *     $select->whereJson('meta->score', 10);
   374	     *
   375	     * @return static<TEntity>

exec
/bin/zsh -lc "nl -ba vendor/cycle/database/src/Query/SelectQuery.php | sed -n '120,140p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
   120	    }
   121	
   122	    public function getColumns(): array
   123	    {
   124	        return $this->columns;
   125	    }
   126	
   127	    /**
   128	     * Select entities for the following update.
   129	     */
   130	    public function forUpdate(): self
   131	    {
   132	        $this->forUpdate = true;
   133	
   134	        return $this;
   135	    }
   136	
   137	    /**
   138	     * Sort result by column/expression. You can apply multiple sortings to query via calling method
   139	     * few times or by specifying values using array of sort parameters.
   140	     *

exec
/bin/zsh -lc "rg -n \"Transactional|class .*Interceptor|SAVEPOINT|nested|transaction\" vendor/spiral vendor/cycle app/src packages/spiral-cqrs -g '*.php'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
packages/spiral-cqrs/src/Attribute/Transactional.php:7:use GianTiaga\SpiralCqrs\Middleware\TransactionalMiddleware;
packages/spiral-cqrs/src/Attribute/Transactional.php:10:final readonly class Transactional extends HandlerMiddlewareAttribute
packages/spiral-cqrs/src/Attribute/Transactional.php:15:        return TransactionalMiddleware::class;
packages/spiral-cqrs/src/Middleware/TransactionalMiddleware.php:9:use GianTiaga\SpiralCqrs\Attribute\Transactional;
packages/spiral-cqrs/src/Middleware/TransactionalMiddleware.php:14:final readonly class TransactionalMiddleware implements HandlerMiddlewareInterface
packages/spiral-cqrs/src/Middleware/TransactionalMiddleware.php:34:        if (!$attribute instanceof Transactional) {
packages/spiral-cqrs/src/Middleware/TransactionalMiddleware.php:39:            throw new \LogicException(message: 'Атрибут #[Transactional] поддерживается только для обработчика команды.');
packages/spiral-cqrs/src/Middleware/TransactionalMiddleware.php:42:        return $this->database->transaction(
packages/spiral-cqrs/src/PHPStan/Rules/RequireCqrsHandlerCallableRule.php:16:use GianTiaga\SpiralCqrs\Attribute\Transactional;
packages/spiral-cqrs/src/PHPStan/Rules/RequireCqrsHandlerCallableRule.php:66:        if (!$this->hasTransactionalAttribute(handlerCall: $handlerArgument->value, scope: $scope)) {
packages/spiral-cqrs/src/PHPStan/Rules/RequireCqrsHandlerCallableRule.php:71:            RuleErrorBuilder::message('Query handlers must not be marked with #[Transactional].')
packages/spiral-cqrs/src/PHPStan/Rules/RequireCqrsHandlerCallableRule.php:72:                ->identifier('gianTiaga.spiralCqrs.transactionalQueryHandler')
packages/spiral-cqrs/src/PHPStan/Rules/RequireCqrsHandlerCallableRule.php:124:    private function hasTransactionalAttribute(MethodCall $handlerCall, Scope $scope): bool
packages/spiral-cqrs/src/PHPStan/Rules/RequireCqrsHandlerCallableRule.php:142:            ->getAttributes(Transactional::class) !== [];
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:13:use GianTiaga\SpiralCqrs\Attribute\Transactional;
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:21:use GianTiaga\SpiralCqrs\Middleware\TransactionalMiddleware;
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:28:    public function testTransactionalHandlerRunsInsideTransaction(): void
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:34:            command: new TransactionalCommand(value: 'ok'),
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:35:            handler: (new TransactionalCommandHandler(executionLog: $executionLog))->handle(...),
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:40:            'transaction:begin',
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:42:            'transaction:commit',
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:46:    public function testHandlerWithoutTransactionalAttributeRunsWithoutTransaction(): void
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:49:        $database->expects(self::never())->method('transaction');
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:61:    public function testFailedTransactionalHandlerRethrowsOriginalException(): void
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:70:                handler: (new FailingTransactionalHandler(
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:82:            'transaction:begin',
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:84:            'transaction:rollback',
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:226:            callback: fn() => new TransactionalMiddleware(
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:236:            expectedMessage: 'Атрибут #[Transactional] поддерживается только для обработчика команды.',
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:237:            callback: fn() => new TransactionalMiddleware(
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:241:                attribute: new Transactional(),
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:285:        $database->expects(self::never())->method('transaction');
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:303:                        TransactionalMiddleware::class => new TransactionalMiddleware(
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:331:            ->method('transaction')
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:333:                $executionLog->add('transaction:begin');
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:338:                    $executionLog->add('transaction:rollback');
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:343:                $executionLog->add('transaction:commit');
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:352:final readonly class TransactionalCommand
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:383:final readonly class TransactionalCommandHandler
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:389:    #[Transactional]
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:390:    public function handle(TransactionalCommand $command): string
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:406:final readonly class FailingTransactionalHandler
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:413:    #[Transactional]
packages/spiral-cqrs/tests/PHPStan/RequireCqrsHandlerCallableRuleTest.php:25:    public function testRejectsInvalidHandlerCallableFormsAndTransactionalQueryHandlers(): void
packages/spiral-cqrs/tests/PHPStan/RequireCqrsHandlerCallableRuleTest.php:37:            'gianTiaga.spiralCqrs.transactionalQueryHandler',
app/src/Modules/Media/Application/Command/Media/CompleteMediaUpload/CompleteMediaUploadHandler.php:25:use GianTiaga\SpiralCqrs\Attribute\Transactional;
app/src/Modules/Media/Application/Command/Media/CompleteMediaUpload/CompleteMediaUploadHandler.php:40:    #[Transactional]
app/src/Modules/Media/Application/Command/Media/ProcessMedia/ProcessMediaHandler.php:25: * Без #[Transactional]: все S3/Imagick-операции выполняются вне транзакции, затем один
vendor/spiral/sentry-bridge/src/Config/SentryConfig.php:67:     * A number between 0 and 1, controlling the percentage chance a given transaction will be sent to Sentry.
vendor/spiral/sentry-bridge/src/Config/SentryConfig.php:68:     * (0 represents 0% while 1 represents 100%.) Applies equally to all transactions created in the app. Either this
vendor/cycle/orm/src/TransactionInterface.php:29:     * Execute all nested commands in transaction, if failed - transaction MUST automatically
vendor/cycle/orm/src/TransactionInterface.php:32:     * Attention, Transaction is clean after this invocation, you must assemble new transaction to retry.
vendor/cycle/orm/src/EntityManagerInterface.php:17:     * Note: The entity will be updated or inserted into the database at transaction
vendor/cycle/orm/src/EntityManagerInterface.php:28:     * Note: The entity will be updated or inserted into the database at transaction
vendor/cycle/orm/src/EntityManagerInterface.php:36:     * Note: A deleted entity will be removed from the database at transaction
vendor/cycle/orm/src/Select/AbstractLoader.php:313:     * Ensure state of every nested loader.
vendor/cycle/orm/src/Select/JoinableLoader.php:137:            // load data for all nested relations
vendor/cycle/orm/src/Select/JoinableLoader.php:150:        // Ensure all nested relations
vendor/cycle/orm/src/Select/JoinableLoader.php:163:        // load data for all nested relations
vendor/cycle/orm/src/Select/Loader/EmbeddedLoader.php:107:     * Ensure state of every nested loader.
vendor/spiral/data-grid/src/Specification/Filter/Any.php:55:                // all nested filters must be configured
vendor/cycle/orm/src/Select/RootLoader.php:19: * and etc based on nested loaders.
vendor/cycle/orm/src/Select/LoaderInterface.php:11: * Loaders provide the ability to create data tree based on set of nested queries or parse resulted
vendor/cycle/orm/src/Select/Traits/ChainTrait.php:45:        // chain of relations provided (relation.nestedRelation)
vendor/cycle/orm/src/Select/Traits/ChainTrait.php:54:        // load nested relation through chain (chainOptions prior to user options)
vendor/cycle/orm/src/Command/CommandInterface.php:10: * Represent one or multiple operations in transaction.
vendor/cycle/orm/src/Command/CommandInterface.php:13: * Traversable interface to let transaction to flatten command.
vendor/cycle/orm/src/Command/CompleteMethodInterface.php:11:     * transaction is closed.
vendor/spiral/data-grid/src/Specification/Filter/Gt.php:30: * // High-value transaction filtering
vendor/spiral/data-grid/src/Specification/Filter/Gt.php:31: * $transactionFilter = new Gt('amount', new NumericValue());
vendor/spiral/data-grid/src/Specification/Filter/Gt.php:32: * $result = $transactionFilter->withValue(1000); // Amount > $1000
vendor/spiral/cycle-bridge/src/Interceptor/CycleInterceptor.php:15:class CycleInterceptor implements CoreInterceptorInterface
app/src/Modules/Outbox/Infrastructure/Relay/OutboxRelay.php:75:        return $this->database->transaction(function () use (
vendor/cycle/orm/src/Command/Database/Update.php:42:     * Avoid opening transaction when no changes are expected.
vendor/cycle/orm/src/Select.php:197:     * Closure for nested or complex conditions:
vendor/cycle/orm/src/Select.php:355:     * of the current transaction, preventing other transactions from modifying them.
vendor/cycle/orm/src/Select.php:357:     *     // Inside a transaction
vendor/cycle/orm/src/Select.php:639:     * Loaded data is populated into entity relations. Use "." to specify nested relations.
vendor/cycle/orm/src/Select.php:925:     * Remove nested loaders and clean ORM link.
vendor/spiral/data-grid/src/Specification/Filter/All.php:45:                // all nested filters must be configured
vendor/cycle/orm/src/Heap/Node.php:182:     * The intial (post-load) node date. Does not change during the transaction.
vendor/cycle/orm/src/Heap/State.php:20:    private array $transactionData;
vendor/cycle/orm/src/Heap/State.php:30:     * @param array<string, mixed> $transactionRaw
vendor/cycle/orm/src/Heap/State.php:36:        private array $transactionRaw = [],
vendor/cycle/orm/src/Heap/State.php:38:        $this->transactionData = $state === Node::NEW ? [] : $data;
vendor/cycle/orm/src/Heap/State.php:106:        return $this->transactionData;
vendor/cycle/orm/src/Heap/State.php:113:                $this->transactionData[$field] = $value;
vendor/cycle/orm/src/Heap/State.php:114:                if (isset($this->transactionRaw[$field])) {
vendor/cycle/orm/src/Heap/State.php:115:                    $this->transactionRaw[$field] = Node::convertToSolid($this->data[$field]);
vendor/cycle/orm/src/Heap/State.php:124:                $this->transactionData[$field] = $this->data[$field];
vendor/cycle/orm/src/Heap/State.php:125:                if (\array_key_exists($field, $this->transactionRaw)) {
vendor/cycle/orm/src/Heap/State.php:126:                    $this->transactionRaw[$field] = Node::convertToSolid($this->data[$field]);
vendor/cycle/orm/src/Heap/State.php:130:            $changes = $changes || Node::compare($value, $this->transactionRaw[$field] ?? $this->transactionData[$field] ?? null) !== 0;
vendor/cycle/orm/src/Heap/State.php:149:            if (!\array_key_exists($field, $this->transactionData)) {
vendor/cycle/orm/src/Heap/State.php:155:                \array_key_exists($field, $this->transactionRaw) ? $this->transactionRaw[$field] : $this->transactionData[$field],
vendor/cycle/orm/src/Heap/State.php:166:        return \array_key_exists($key, $this->data) ? $this->data[$key] : ($this->transactionData[$key] ?? null);
vendor/cycle/orm/src/Heap/State.php:172:            return isset($this->data[$key]) || isset($this->transactionData[$key]);
vendor/cycle/orm/src/Heap/State.php:174:        return \array_key_exists($key, $this->data) || \array_key_exists($key, $this->transactionData);
vendor/cycle/orm/src/Heap/State.php:204:        unset($this->relations, $this->storage, $this->data, $this->transactionData);
vendor/cycle/orm/src/Transaction.php:11: * Transaction provides ability to define set of entities to be stored or deleted within one transaction. Transaction
vendor/cycle/orm/src/Transaction.php:12: * can operate as UnitOfWork. Multiple transactions can co-exists in one application.
vendor/cycle/orm/src/Transaction.php:14: * Internally, upon "run", transaction will request mappers to generate graph of linked commands to create, update or
vendor/cycle/orm/src/Schema/GeneratedField.php:13:     * Field value is generated in the user space before transaction running.
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:20:final readonly class OutboxQueueStatusInterceptor implements CoreInterceptorInterface
vendor/cycle/orm/src/Parser/OutputNode.php:15:     * Array used to aggregate all nested node results in a form of tree.
vendor/cycle/orm/src/Transaction/Runner.php:33:     * Create Runner in the 'inner transaction' mode.
vendor/cycle/orm/src/Transaction/Runner.php:34:     * In this case the Runner will open new transaction for each used driver connection
vendor/cycle/orm/src/Transaction/Runner.php:43:     * Create Runner in the 'outer transaction' mode.
vendor/cycle/orm/src/Transaction/Runner.php:44:     * In this case the Runner won't begin transactions, you should do it previously manually.
vendor/cycle/orm/src/Transaction/Runner.php:45:     * This mode also means the Runner WON'T commit or rollback opened transactions on success or fail.
vendor/cycle/orm/src/Transaction/Runner.php:48:     * @param bool $strict Check transaction statuses before commands running.
vendor/cycle/orm/src/Transaction/Runner.php:49:     *        When strict mode is {@see true} and a transaction of any used driver didn't be opened
vendor/cycle/orm/src/Transaction/Runner.php:51:     *        When strict mode is {@see false} the Runner won't begin/commit/rollback transactions
vendor/cycle/orm/src/Transaction/Runner.php:52:     *        and will ignore any transaction statuses.
vendor/cycle/orm/src/Transaction/Runner.php:102:            // Commit all of the open and normalized database transactions
vendor/cycle/orm/src/Transaction/Runner.php:109:        // Other type of transaction to close
vendor/cycle/orm/src/Transaction/Runner.php:121:            // Close all open and normalized database transactions
vendor/cycle/orm/src/Transaction/Runner.php:128:        // Close all of external types of transactions (revert changes)
vendor/cycle/orm/src/Transaction/Runner.php:147:                    'The `%s` driver connection has no opened transaction.',
vendor/cycle/orm/src/Transaction/StateInterface.php:13:     * Check if transaction has been run successful.
vendor/cycle/orm/src/Transaction/StateInterface.php:22:     * The reason of failed transaction.
vendor/cycle/orm/src/Transaction/StateInterface.php:27:     * Try to rerun transaction if previous run has been failed.
vendor/cycle/orm/src/Parser/AbstractNode.php:144:             * This means offset has to be calculated using all nested nodes
vendor/cycle/orm/src/Parser/AbstractNode.php:151:            //Counting nested tree offset
vendor/cycle/orm/src/Transaction/CommandGenerator.php:41:        // currently we rely on db to delete all nested records (or soft deletes)
vendor/cycle/orm/src/Transaction/UnitOfWork.php:89:                'A successful transaction cannot be re-run.',
vendor/cycle/orm/src/Transaction/UnitOfWork.php:91:            self::STAGE_PROCESS => throw new TransactionException('Can\'t run started transaction.'),
vendor/cycle/orm/src/Transaction/UnitOfWork.php:113:            // this will keep entity data as it was before transaction run
vendor/cycle/orm/src/Transaction/UnitOfWork.php:143:     *         In case the transaction is finished, it will always return false.
vendor/cycle/orm/src/Transaction/UnitOfWork.php:144:     *         In case the transaction is in process, it will always return true.
vendor/cycle/orm/src/Transaction/UnitOfWork.php:227:                // currently we rely on db to delete all nested records (or soft deletes)
vendor/spiral/data-grid/src/Specification/Value/PositiveValue.php:67: * // Financial transaction amount
vendor/spiral/data-grid/src/Specification/Value/PositiveValue.php:69: * $transactionFilter = new Gte('amount', $amountValue);
vendor/spiral/data-grid/src/Specification/Value/PositiveValue.php:70: * $result = $transactionFilter->withValue(100.50); // Valid - positive amount
vendor/spiral/data-grid/src/Specification/Value/PositiveValue.php:71: * $result = $transactionFilter->withValue(0);      // Invalid - zero transaction
vendor/spiral/data-grid/src/Specification/Value/PositiveValue.php:72: * $result = $transactionFilter->withValue(-25);    // Invalid - negative amount
vendor/cycle/orm/src/Relation/RelationInterface.php:17:    // Relation statuses in an unfinished transaction
vendor/spiral/data-grid/src/Specification/Value/UuidValue.php:65: * $transactionUuidValue = UuidValue::v4();
vendor/spiral/data-grid/src/Specification/Value/UuidValue.php:66: * $transactionFilter = new Equals('transaction_id', $transactionUuidValue);
vendor/spiral/data-grid/src/Specification/Value/UuidValue.php:67: * $result = $transactionFilter->withValue('f47ac10b-58cc-4372-a567-0e02b2c3d479');
vendor/spiral/data-grid/src/Specification/Value/ArrayValue.php:67: * // Complex nested validation
vendor/spiral/data-grid/src/Specification/Value/NumericValue.php:50: * $transactionFilter = new Gte('amount', $amountValue);
vendor/spiral/data-grid/src/Specification/Value/NumericValue.php:51: * $result = $transactionFilter->withValue(100);     // $100.00 (int)
vendor/spiral/data-grid/src/Specification/Value/NumericValue.php:52: * $result = $transactionFilter->withValue(99.99);   // $99.99 (float)
vendor/spiral/data-grid/src/Specification/Value/NumericValue.php:53: * $result = $transactionFilter->withValue('50.5');  // $50.50 (float)
vendor/spiral/data-grid/src/Specification/Value/Accessor/Split.php:203: * // Complex nested processing
vendor/spiral/grpc-client/src/Interceptor/SetTimeoutInterceptor.php:17:final class SetTimeoutInterceptor implements InterceptorInterface
vendor/spiral/grpc-client/src/Interceptor/ExecuteServiceInterceptors.php:19:final class ExecuteServiceInterceptors implements Interceptor
vendor/spiral/grpc-client/src/Interceptor/RetryInterceptor.php:25:final class RetryInterceptor implements InterceptorInterface
vendor/spiral/data-grid/src/Specification/Sorter/DirectionalSorter.php:51: * $transactionSort = new DirectionalSorter(
vendor/spiral/grpc-client/src/Interceptor/ConnectionsRotationInterceptor.php:17:final class ConnectionsRotationInterceptor implements InterceptorInterface
vendor/spiral/roadrunner-grpc/src/StatusCode.php:132:     * issue like sequencer check failures, transaction aborts, etc.
vendor/spiral/grpc-client/src/Internal/StatusCode.php:130:     * issue like sequencer check failures, transaction aborts, etc.
app/src/Shared/Infrastructure/Configuration/Queue/QueueInterceptorsConfig.php:11:final readonly class QueueInterceptorsConfig
vendor/spiral/data-grid-bridge/src/Interceptor/GridInterceptor.php:21:final class GridInterceptor implements CoreInterceptorInterface
vendor/cycle/migrations/src/Migrator.php:121:                $capsule->getDatabase()->transaction(
vendor/cycle/migrations/src/Migrator.php:174:            $capsule->getDatabase()->transaction(
vendor/spiral/framework/src/Console/src/Interceptor/AttributeInterceptor.php:13:final class AttributeInterceptor implements CoreInterceptorInterface
vendor/spiral/framework/src/Boot/src/Bootloader/DependedInterface.php:14:     * Related bootloaders will be initiated automatically with nested
vendor/spiral/framework/src/Interceptors/src/Event/InterceptorCalling.php:10:final class InterceptorCalling
vendor/spiral/framework/src/Interceptors/src/Exception/InterceptorException.php:7:class InterceptorException extends \RuntimeException {}
vendor/spiral/framework/src/Interceptors/src/Handler/InterceptorPipeline.php:19:final class InterceptorPipeline implements HandlerInterface
vendor/cycle/database/src/Query/SelectQuery.php:164:        foreach ($expression as $nested => $dir) {
vendor/cycle/database/src/Query/SelectQuery.php:166:            if (\is_int($nested)) {
vendor/cycle/database/src/Query/SelectQuery.php:167:                $nested = $dir;
vendor/cycle/database/src/Query/SelectQuery.php:171:            $this->addOrder($nested, $dir);
vendor/cycle/database/src/Query/Traits/TokenTrait.php:179:                foreach ($value as $nested) {
vendor/cycle/database/src/Query/Traits/TokenTrait.php:180:                    if (\count($nested) === 1) {
vendor/cycle/database/src/Query/Traits/TokenTrait.php:181:                        $this->flattenWhere($token, $nested, $tokens, $wrapper);
vendor/cycle/database/src/Query/Traits/TokenTrait.php:186:                    $this->flattenWhere(CompilerInterface::TOKEN_AND, $nested, $tokens, $wrapper);
vendor/cycle/database/src/Query/Traits/TokenTrait.php:240:                // AND|OR [name] [OPERATION] [nestedValue]
vendor/cycle/database/src/DatabaseInterface.php:30: *         consistency for the duration of the enclosing transaction. Requires the read driver to
vendor/cycle/database/src/DatabaseInterface.php:32: *         and an active transaction. Driver-specific knobs (FETCH FORWARD batch, WITH HOLD,
vendor/cycle/database/src/DatabaseInterface.php:138:     * Execute multiple commands defined by Closure function inside one transaction. Closure or
vendor/cycle/database/src/DatabaseInterface.php:141:     * @link http://en.wikipedia.org/wiki/Database_transaction
vendor/cycle/database/src/DatabaseInterface.php:151:    public function transaction(callable $callback, ?string $isolationLevel = null): mixed;
vendor/cycle/database/src/DatabaseInterface.php:154:     * Start database transaction.
vendor/cycle/database/src/DatabaseInterface.php:156:     * @link http://en.wikipedia.org/wiki/Database_transaction
vendor/cycle/database/src/DatabaseInterface.php:161:     * Commit the active database transaction.
vendor/cycle/database/src/DatabaseInterface.php:166:     * Rollback the active database transaction.
vendor/cycle/database/src/Schema/Reflector.php:20: * Attention, not every DBMS support transactional schema manipulations!
vendor/cycle/database/src/Schema/Reflector.php:165:     * Begin mass transaction.
vendor/cycle/database/src/Schema/Reflector.php:171:                // do not cache statements for this transaction
vendor/cycle/database/src/Schema/Reflector.php:180:     * Commit mass transaction.
vendor/cycle/database/src/Schema/Reflector.php:190:     * Roll back mass transaction.
vendor/spiral/framework/src/Filters/src/Model/Interceptor/ValidateFilterInterceptor.php:22:final class ValidateFilterInterceptor implements CoreInterceptorInterface
vendor/cycle/database/src/Database.php:31:    // Isolation levels for transactions
vendor/cycle/database/src/Database.php:144:     * including snapshot consistency within the transaction — are preserved:
vendor/cycle/database/src/Database.php:206:    public function transaction(
vendor/spiral/framework/src/Filters/src/Model/Interceptor/PopulateDataFromEntityInterceptor.php:16:final class PopulateDataFromEntityInterceptor implements CoreInterceptorInterface
vendor/cycle/database/src/Driver/Compiler.php:83:        bool $nestedQuery = true,
vendor/cycle/database/src/Driver/Compiler.php:113:                if ($nestedQuery) {
vendor/cycle/database/src/Driver/Compiler.php:577:            // possibly support between nested queries
vendor/cycle/database/src/Driver/DriverInterface.php:32:     * be released at the end of the transaction. Also range-locks must be acquired when a SELECT
vendor/cycle/database/src/Driver/DriverInterface.php:37:     * detects a write collision among several concurrent transactions, only one of them is allowed
vendor/cycle/database/src/Driver/DriverInterface.php:48:     * write locks (acquired on selected data) until the end of the transaction. However,
vendor/cycle/database/src/Driver/DriverInterface.php:60:     * (acquired on selected data) until the end of the transaction, but read locks are released as
vendor/cycle/database/src/Driver/DriverInterface.php:68:     * transaction re-issues the read, it will find the same data; data is free to change after it
vendor/cycle/database/src/Driver/DriverInterface.php:79:     * transaction may see not-yet-committed changes made by other transactions.
vendor/cycle/database/src/Driver/DriverInterface.php:82:     * allows an action forbidden by a lower one, the standard permits a DBMS to run a transaction
vendor/cycle/database/src/Driver/DriverInterface.php:83:     * at an isolation level stronger than that requested (e.g., a "Read committed" transaction may
vendor/cycle/database/src/Driver/DriverInterface.php:182:     * Start SQL transaction with specified isolation level (not all DBMS support it). Nested
vendor/cycle/database/src/Driver/DriverInterface.php:183:     * transactions are processed using savepoints.
vendor/cycle/database/src/Driver/DriverInterface.php:185:     * @link   http://en.wikipedia.org/wiki/Database_transaction
vendor/cycle/database/src/Driver/DriverInterface.php:193:     * Commit the active database transaction.
vendor/cycle/database/src/Driver/DriverInterface.php:200:     * Rollback the active database transaction.
vendor/cycle/database/src/Driver/DriverInterface.php:207:     * Get current opened transaction level.
vendor/spiral/framework/src/Filters/src/Model/Schema/Builder.php:23:    // Used to define multiple nested models.
vendor/spiral/framework/src/Filters/src/Model/Schema/Builder.php:65:                // singular nested model
vendor/cycle/database/src/Driver/SQLServer/SQLServerDriver.php:101:                'SQLServer cursor requires an active transaction. '
vendor/cycle/database/src/Driver/SQLServer/SQLServerDriver.php:102:                . 'Wrap the cursor iteration in Database::transaction() or call beginTransaction() before cursor().',
vendor/cycle/database/src/Driver/SQLServer/SQLServerDriver.php:134:                // Cursor may already be gone (e.g. transaction was rolled back) — swallow.
vendor/cycle/database/src/Driver/SQLServer/SQLServerDriver.php:209:     * Create nested transaction save point.
vendor/cycle/database/src/Driver/SQLServer/SQLServerDriver.php:234:        // SQLServer automatically commits nested transactions with parent transaction
vendor/spiral/framework/src/Filters/src/Model/Schema/InputMapper.php:43:            $nested = $map[Builder::SCHEMA_FILTER];
vendor/spiral/framework/src/Filters/src/Model/Schema/InputMapper.php:47:                    $result[$field] = $this->provider->createFilter($nested, $input->withPrefix($map[Builder::SCHEMA_ORIGIN]));
vendor/spiral/framework/src/Filters/src/Model/Schema/InputMapper.php:63:                    $values[$index] = $this->provider->createFilter($nested, $input->withPrefix($origin));
vendor/spiral/framework/src/Filters/src/Model/Schema/InputMapper.php:76:     * Create set of origins and prefixed for a nested array of models.
vendor/cycle/database/src/Driver/SQLServer/CursorType.php:35:     * updates, and deletes by other transactions are all visible. No snapshot.
vendor/cycle/database/src/Driver/MySQL/MySQLDriver.php:58:            $this->transactionLevel = 0;
vendor/cycle/database/src/Driver/MySQL/MySQLDriver.php:63:        return $this->transactionLevel;
vendor/spiral/framework/src/Tokenizer/src/Reflection/ReflectionFile.php:462:        //Multiple "(" and ")" statements nested.
vendor/cycle/database/src/Driver/Postgres/PostgresDriver.php:152:     * Start SQL transaction with specified isolation level (not all DBMS support it). Nested
vendor/cycle/database/src/Driver/Postgres/PostgresDriver.php:153:     * transactions are processed using savepoints.
vendor/cycle/database/src/Driver/Postgres/PostgresDriver.php:155:     * @link http://en.wikipedia.org/wiki/Database_transaction
vendor/cycle/database/src/Driver/Postgres/PostgresDriver.php:162:        ++$this->transactionLevel;
vendor/cycle/database/src/Driver/Postgres/PostgresDriver.php:164:        if ($this->transactionLevel === 1) {
vendor/cycle/database/src/Driver/Postgres/PostgresDriver.php:165:            $this->logger?->info('Begin transaction');
vendor/cycle/database/src/Driver/Postgres/PostgresDriver.php:184:                        $this->transactionLevel = 1;
vendor/cycle/database/src/Driver/Postgres/PostgresDriver.php:187:                        $this->transactionLevel = 0;
vendor/cycle/database/src/Driver/Postgres/PostgresDriver.php:191:                    $this->transactionLevel = 0;
vendor/cycle/database/src/Driver/Postgres/PostgresDriver.php:197:        $this->createSavepoint($this->transactionLevel);
vendor/cycle/database/src/Driver/Postgres/PostgresDriver.php:204:     * lazily. Provides snapshot consistency within the enclosing transaction.
vendor/cycle/database/src/Driver/Postgres/PostgresDriver.php:206:     * Requires an active transaction (cursor lifetime is bound to it unless
vendor/cycle/database/src/Driver/Postgres/PostgresDriver.php:232:                'Postgres server-side cursor requires an active transaction. '
vendor/cycle/database/src/Driver/Postgres/PostgresDriver.php:233:                . 'Wrap the cursor iteration in Database::transaction() or call beginTransaction() before cursor().',
vendor/cycle/database/src/Driver/Postgres/PostgresDriver.php:259:                // Cursor may already be gone (e.g. transaction was rolled back) — swallow.
vendor/spiral/framework/src/Filters/src/Attribute/NestedFilter.php:12: * The attribute provides the ability to create nested filters. To demonstrate the composition, we will use a sample
vendor/spiral/framework/src/Filters/src/Attribute/NestedFilter.php:21: * After creating nested filter it will be validated.
vendor/spiral/framework/src/Filters/src/Attribute/NestedArray.php:14: * The attribute provides the ability to create nested array of filters. To demonstrate the composition, we will use
vendor/spiral/framework/src/Filters/src/Attribute/NestedArray.php:26: * After creating nested filters they will be validated.
vendor/cycle/database/src/Driver/Postgres/PostgresCursorOptions.php:22:     *        `FETCH` calls outside the transaction. Use for long-running exports that should
vendor/cycle/database/src/Driver/Postgres/PostgresCursorOptions.php:23:     *        not keep a write-blocking transaction alive.
vendor/cycle/database/src/Driver/Driver.php:47:    protected int $transactionLevel = 0;
vendor/cycle/database/src/Driver/Driver.php:187:        $this->transactionLevel = 0;
vendor/cycle/database/src/Driver/Driver.php:254:        return $this->transactionLevel;
vendor/cycle/database/src/Driver/Driver.php:258:     * Start SQL transaction with specified isolation level (not all DBMS support it). Nested
vendor/cycle/database/src/Driver/Driver.php:259:     * transactions are processed using savepoints.
vendor/cycle/database/src/Driver/Driver.php:261:     * @link http://en.wikipedia.org/wiki/Database_transaction
vendor/cycle/database/src/Driver/Driver.php:267:        ++$this->transactionLevel;
vendor/cycle/database/src/Driver/Driver.php:269:        if ($this->transactionLevel === 1) {
vendor/cycle/database/src/Driver/Driver.php:274:            $this->logger?->info('Begin transaction');
vendor/cycle/database/src/Driver/Driver.php:288:                        $this->transactionLevel = 1;
vendor/cycle/database/src/Driver/Driver.php:291:                        $this->transactionLevel = 0;
vendor/cycle/database/src/Driver/Driver.php:295:                    $this->transactionLevel = 0;
vendor/cycle/database/src/Driver/Driver.php:301:        $this->createSavepoint($this->transactionLevel);
vendor/cycle/database/src/Driver/Driver.php:307:     * Commit the active database transaction.
vendor/cycle/database/src/Driver/Driver.php:313:        // Check active transaction
vendor/cycle/database/src/Driver/Driver.php:317:                    'Attempt to commit a transaction that has not yet begun. Transaction level: %d',
vendor/cycle/database/src/Driver/Driver.php:318:                    $this->transactionLevel,
vendor/cycle/database/src/Driver/Driver.php:322:            if ($this->transactionLevel === 0) {
vendor/cycle/database/src/Driver/Driver.php:326:            $this->transactionLevel = 0;
vendor/cycle/database/src/Driver/Driver.php:330:        --$this->transactionLevel;
vendor/cycle/database/src/Driver/Driver.php:332:        if ($this->transactionLevel === 0) {
vendor/cycle/database/src/Driver/Driver.php:333:            $this->logger?->info('Commit transaction');
vendor/cycle/database/src/Driver/Driver.php:342:        $this->releaseSavepoint($this->transactionLevel + 1);
vendor/cycle/database/src/Driver/Driver.php:348:     * Rollback the active database transaction.
vendor/cycle/database/src/Driver/Driver.php:354:        // Check active transaction
vendor/cycle/database/src/Driver/Driver.php:358:                    'Attempt to rollback a transaction that has not yet begun. Transaction level: %d',
vendor/cycle/database/src/Driver/Driver.php:359:                    $this->transactionLevel,
vendor/cycle/database/src/Driver/Driver.php:363:            $this->transactionLevel = 0;
vendor/cycle/database/src/Driver/Driver.php:367:        --$this->transactionLevel;
vendor/cycle/database/src/Driver/Driver.php:369:        if ($this->transactionLevel === 0) {
vendor/cycle/database/src/Driver/Driver.php:370:            $this->logger?->info('Rollback transaction');
vendor/cycle/database/src/Driver/Driver.php:379:        $this->rollbackSavepoint($this->transactionLevel + 1);
vendor/cycle/database/src/Driver/Driver.php:471:                && $this->transactionLevel === 0
vendor/cycle/database/src/Driver/Driver.php:597:     * Set transaction isolation level, this feature may not be supported by specific database
vendor/cycle/database/src/Driver/Driver.php:609:     * Create nested transaction save point.
vendor/cycle/database/src/Driver/Driver.php:619:        $this->execute('SAVEPOINT ' . $this->identifier("SVP{$level}"));
vendor/cycle/database/src/Driver/Driver.php:633:        $this->execute('RELEASE SAVEPOINT ' . $this->identifier("SVP{$level}"));
vendor/cycle/database/src/Driver/Driver.php:647:        $this->execute('ROLLBACK TO SAVEPOINT ' . $this->identifier("SVP{$level}"));
vendor/cycle/database/src/Driver/SQLite/SQLiteDriver.php:61:     * transaction) is therefore satisfied by the engine + an active
vendor/cycle/database/src/Driver/SQLite/SQLiteDriver.php:62:     * transaction:
vendor/cycle/database/src/Driver/SQLite/SQLiteDriver.php:67:     *    until the transaction completes.
vendor/cycle/database/src/Driver/SQLite/SQLiteDriver.php:74:     * @throws DriverException When no transaction is active.
vendor/cycle/database/src/Driver/SQLite/SQLiteDriver.php:84:                'SQLite cursor requires an active transaction to guarantee snapshot consistency. '
vendor/cycle/database/src/Driver/SQLite/SQLiteDriver.php:85:                . 'Wrap the cursor iteration in Database::transaction() or call beginTransaction() before cursor().',
vendor/cycle/database/src/Driver/CursorInterface.php:16: * **snapshot consistency** for the duration of its enclosing transaction:
vendor/cycle/database/src/Driver/CursorInterface.php:32:     * Implementations require an active transaction on the connection for the
vendor/cycle/database/src/Config/SQLServer/TcpConnectionConfig.php:47:     * @param IsolationLevelType|null $isolation Specifies the transaction isolation level.
vendor/spiral/roadrunner-jobs/src/Queue/Kafka/ProducerOptions.php:30:     * @param DateInterval|null $transactionTimeout sets the allowed for a transaction, overriding the default 40s. It is
vendor/spiral/roadrunner-jobs/src/Queue/Kafka/ProducerOptions.php:42:        public readonly ?DateInterval $transactionTimeout = null,
vendor/spiral/roadrunner-jobs/src/Queue/Kafka/ProducerOptions.php:62:        if ($this->transactionTimeout !== null) {
vendor/spiral/roadrunner-jobs/src/Queue/Kafka/ProducerOptions.php:63:            $data['transaction_timeout'] = $this->convertDateIntervalToString($this->transactionTimeout);
vendor/spiral/framework/src/Stempler/src/Lexer/Buffer.php:27:     * Delegate generation to the nested generator and collect
vendor/spiral/framework/src/Stempler/src/Lexer/Grammar/HTMLGrammar.php:115:                    // language inclusions allow nested strings
vendor/spiral/framework/src/Stempler/src/Lexer/Grammar/HTMLGrammar.php:140:                        // language inclusions allow nested strings
vendor/spiral/framework/src/Framework/Domain/GuardInterceptor.php:16:final class GuardInterceptor implements CoreInterceptorInterface
vendor/spiral/framework/src/Framework/Domain/PipelineInterceptor.php:19:class PipelineInterceptor implements CoreInterceptorInterface
vendor/spiral/framework/src/Stempler/src/Transform/Context/ImportContext.php:15: * Manages currently open scope of imports (via nested tags).
vendor/spiral/framework/src/Views/src/Engine/Native/NativeView.php:38:            //Closing all nested buffers
vendor/spiral/roadrunner-bridge/src/Centrifugo/Internal/InterceptorRegistry.php:21:final class InterceptorRegistry implements RegistryInterface
vendor/spiral/roadrunner-bridge/src/Tcp/Internal/InterceptorRegistry.php:18:final class InterceptorRegistry implements RegistryInterface
vendor/spiral/framework/src/Hmvc/src/InterceptorPipeline.php:21:final class InterceptorPipeline implements CoreInterface, HandlerInterface
vendor/spiral/framework/src/Hmvc/src/Event/InterceptorCalling.php:13:final class InterceptorCalling
vendor/spiral/framework/src/Hmvc/src/Exception/InterceptorException.php:13:    class InterceptorException extends \RuntimeException {}
vendor/spiral/framework/src/Queue/src/Interceptor/Consume/RetryPolicyInterceptor.php:18:final class RetryPolicyInterceptor implements CoreInterceptorInterface
vendor/spiral/framework/src/Queue/src/Interceptor/Consume/ErrorHandlerInterceptor.php:15:final class ErrorHandlerInterceptor implements LegacyInterceptor, InterceptorInterface
vendor/spiral/roadrunner-bridge/src/Bootloader/CentrifugoBootloader.php:37:            Interceptor\RegistryInterface::class => [self::class, 'initInterceptorRegistry'],
vendor/spiral/roadrunner-bridge/src/Bootloader/TcpBootloader.php:37:            Interceptor\RegistryInterface::class => [self::class, 'initInterceptorRegistry'],

exec
/bin/zsh -lc "nl -ba packages/spiral-cqrs/src/Middleware/TransactionalMiddleware.php | sed -n '1,100p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace GianTiaga\SpiralCqrs\Middleware;
     6	
     7	use Cycle\Database\DatabaseInterface;
     8	use GianTiaga\SpiralCqrs\Attribute\HandlerMiddlewareAttribute;
     9	use GianTiaga\SpiralCqrs\Attribute\Transactional;
    10	use GianTiaga\SpiralCqrs\CommandHandlerContext;
    11	use GianTiaga\SpiralCqrs\HandlerContext;
    12	use GianTiaga\SpiralCqrs\HandlerMiddlewareInterface;
    13	
    14	final readonly class TransactionalMiddleware implements HandlerMiddlewareInterface
    15	{
    16	    public function __construct(
    17	        private DatabaseInterface $database,
    18	    ) {}
    19	
    20	    /**
    21	     * @template TInput of object
    22	     * @template TResult
    23	     * @param TInput $input
    24	     * @param callable(TInput): TResult $next
    25	     * @return TResult
    26	     */
    27	    #[\Override]
    28	    public function handle(
    29	        object $input,
    30	        HandlerMiddlewareAttribute $attribute,
    31	        callable $next,
    32	        HandlerContext $context,
    33	    ) {
    34	        if (!$attribute instanceof Transactional) {
    35	            throw new \LogicException(message: 'Некорректный атрибут для транзакционного промежуточного обработчика.');
    36	        }
    37	
    38	        if (!$context instanceof CommandHandlerContext) {
    39	            throw new \LogicException(message: 'Атрибут #[Transactional] поддерживается только для обработчика команды.');
    40	        }
    41	
    42	        return $this->database->transaction(
    43	            static fn(DatabaseInterface $database) => $next($input),
    44	        );
    45	    }
    46	}

exec
/bin/zsh -lc "nl -ba vendor/cycle/database/src/Database.php | sed -n '200,232p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
   200	
   201	        return $this->getDriver(self::READ)
   202	            ->getQueryBuilder()
   203	            ->selectQuery($this->prefix, [], $arguments);
   204	    }
   205	
   206	    public function transaction(
   207	        callable $callback,
   208	        ?string $isolationLevel = null,
   209	    ): mixed {
   210	        $this->begin($isolationLevel);
   211	
   212	        try {
   213	            $result = $callback($this);
   214	            $this->commit();
   215	
   216	            return $result;
   217	        } catch (\Throwable $e) {
   218	            $this->rollback();
   219	            throw $e;
   220	        }
   221	    }
   222	
   223	    public function begin(?string $isolationLevel = null): bool
   224	    {
   225	        return $this->getDriver(self::WRITE)->beginTransaction($isolationLevel);
   226	    }
   227	
   228	    public function commit(): bool
   229	    {
   230	        return $this->getDriver(self::WRITE)->commitTransaction();
   231	    }
   232	

exec
/bin/zsh -lc "nl -ba vendor/cycle/database/src/Driver/Driver.php | sed -n '258,348p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba vendor/cycle/database/src/Driver/Postgres/PostgresDriver.php | sed -n '152,208p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
   258	     * Start SQL transaction with specified isolation level (not all DBMS support it). Nested
   259	     * transactions are processed using savepoints.
   260	     *
   261	     * @link http://en.wikipedia.org/wiki/Database_transaction
   262	     * @link http://en.wikipedia.org/wiki/Isolation_(database_systems)
   263	     *
   264	     */
   265	    public function beginTransaction(?string $isolationLevel = null): bool
   266	    {
   267	        ++$this->transactionLevel;
   268	
   269	        if ($this->transactionLevel === 1) {
   270	            if ($isolationLevel !== null) {
   271	                $this->setIsolationLevel($isolationLevel);
   272	            }
   273	
   274	            $this->logger?->info('Begin transaction');
   275	
   276	            try {
   277	                return $this->getPDO()->beginTransaction();
   278	            } catch (\Throwable  $e) {
   279	                $e = $this->mapException($e, 'BEGIN TRANSACTION');
   280	
   281	                if (
   282	                    $e instanceof StatementException\ConnectionException
   283	                    && $this->config->reconnect
   284	                ) {
   285	                    $this->disconnect();
   286	
   287	                    try {
   288	                        $this->transactionLevel = 1;
   289	                        return $this->getPDO()->beginTransaction();
   290	                    } catch (\Throwable $e) {
   291	                        $this->transactionLevel = 0;
   292	                        throw $this->mapException($e, 'BEGIN TRANSACTION');
   293	                    }
   294	                } else {
   295	                    $this->transactionLevel = 0;
   296	                    throw $e;
   297	                }
   298	            }
   299	        }
   300	
   301	        $this->createSavepoint($this->transactionLevel);
   302	
   303	        return true;
   304	    }
   305	
   306	    /**
   307	     * Commit the active database transaction.
   308	     *
   309	     * @throws StatementException
   310	     */
   311	    public function commitTransaction(): bool
   312	    {
   313	        // Check active transaction
   314	        if (!$this->getPDO()->inTransaction()) {
   315	            $this->logger?->warning(
   316	                \sprintf(
   317	                    'Attempt to commit a transaction that has not yet begun. Transaction level: %d',
   318	                    $this->transactionLevel,
   319	                ),
   320	            );
   321	
   322	            if ($this->transactionLevel === 0) {
   323	                return false;
   324	            }
   325	
   326	            $this->transactionLevel = 0;
   327	            return true;
   328	        }
   329	
   330	        --$this->transactionLevel;
   331	
   332	        if ($this->transactionLevel === 0) {
   333	            $this->logger?->info('Commit transaction');
   334	
   335	            try {
   336	                return $this->getPDO()->commit();
   337	            } catch (\Throwable $e) {
   338	                throw $this->mapException($e, 'COMMIT TRANSACTION');
   339	            }
   340	        }
   341	
   342	        $this->releaseSavepoint($this->transactionLevel + 1);
   343	
   344	        return true;
   345	    }
   346	
   347	    /**
   348	     * Rollback the active database transaction.

 succeeded in 0ms:
   152	     * Start SQL transaction with specified isolation level (not all DBMS support it). Nested
   153	     * transactions are processed using savepoints.
   154	     *
   155	     * @link http://en.wikipedia.org/wiki/Database_transaction
   156	     * @link http://en.wikipedia.org/wiki/Isolation_(database_systems)
   157	     *
   158	     *
   159	     */
   160	    public function beginTransaction(?string $isolationLevel = null): bool
   161	    {
   162	        ++$this->transactionLevel;
   163	
   164	        if ($this->transactionLevel === 1) {
   165	            $this->logger?->info('Begin transaction');
   166	
   167	            try {
   168	                $ok = $this->getPDO()->beginTransaction();
   169	                if ($isolationLevel !== null) {
   170	                    $this->setIsolationLevel($isolationLevel);
   171	                }
   172	
   173	                return $ok;
   174	            } catch (\Throwable $e) {
   175	                $e = $this->mapException($e, 'BEGIN TRANSACTION');
   176	
   177	                if (
   178	                    $e instanceof StatementException\ConnectionException
   179	                    && $this->config->reconnect
   180	                ) {
   181	                    $this->disconnect();
   182	
   183	                    try {
   184	                        $this->transactionLevel = 1;
   185	                        return $this->getPDO()->beginTransaction();
   186	                    } catch (\Throwable $e) {
   187	                        $this->transactionLevel = 0;
   188	                        throw $this->mapException($e, 'BEGIN TRANSACTION');
   189	                    }
   190	                } else {
   191	                    $this->transactionLevel = 0;
   192	                    throw $e;
   193	                }
   194	            }
   195	        }
   196	
   197	        $this->createSavepoint($this->transactionLevel);
   198	
   199	        return true;
   200	    }
   201	
   202	    /**
   203	     * Open a Postgres server-side cursor for the given SELECT and yield rows
   204	     * lazily. Provides snapshot consistency within the enclosing transaction.
   205	     *
   206	     * Requires an active transaction (cursor lifetime is bound to it unless
   207	     * {@see PostgresCursorOptions::$withHold} is set, in which case the cursor
   208	     * survives `COMMIT` and the result is materialized on the server). The

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php | sed -n '1,260p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

exec
/bin/zsh -lc "rg -n \"interface OutboxMessage|OutboxEventStoreContract|OutboxJobRegistryContract|OutboxMessageLoaderContract|class .*EventStore|class .*MessageLoader|implements OutboxMessage|register\\(\" app/src/Modules/Outbox app/src/Modules/Media -g '*.php'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:13:use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:35:        OutboxMessageLoaderContract $outboxMessageLoader,
app/src/Modules/Outbox/Presentation/Job/OutboxDebugLogJob.php:9:use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
app/src/Modules/Outbox/Presentation/Job/OutboxDebugLogJob.php:20:        OutboxMessageLoaderContract $outboxMessageLoader,
app/src/Modules/Outbox/Infrastructure/Registry/OutboxJobRegistry.php:7:use App\Modules\Outbox\Application\Contract\OutboxJobRegistryContract;
app/src/Modules/Outbox/Infrastructure/Registry/OutboxJobRegistry.php:12:final class OutboxJobRegistry implements OutboxJobRegistryContract
app/src/Modules/Outbox/Infrastructure/Registry/OutboxJobRegistry.php:24:    public function register(string $outboxMessageClass, string $outboxJobClass): void
app/src/Modules/Media/Application/Command/Media/CompleteMediaUpload/CompleteMediaUploadHandler.php:18:use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
app/src/Modules/Media/Application/Command/Media/CompleteMediaUpload/CompleteMediaUploadHandler.php:35:        private OutboxEventStoreContract $outboxEventStore,
app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php:15:use App\Modules\Outbox\Application\Contract\OutboxJobRegistryContract;
app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php:26:    public function boot(OutboxJobRegistryContract $outboxJobRegistry): void
app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php:28:        $outboxJobRegistry->register(
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueuePublisher.php:7:use App\Modules\Outbox\Application\Contract\OutboxJobRegistryContract;
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueuePublisher.php:21:        private OutboxJobRegistryContract $outboxJobRegistry,
app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxBootloader.php:7:use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxBootloader.php:8:use App\Modules\Outbox\Application\Contract\OutboxJobRegistryContract;
app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxBootloader.php:9:use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxBootloader.php:30:        OutboxEventStoreContract::class => OutboxEventStore::class,
app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxBootloader.php:31:        OutboxMessageLoaderContract::class => OutboxMessageLoader::class,
app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxBootloader.php:40:        OutboxJobRegistryContract::class => OutboxJobRegistry::class,
app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxBootloader.php:43:    public function boot(OutboxJobRegistryContract $outboxJobRegistry): void
app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxBootloader.php:45:        $outboxJobRegistry->register(
app/src/Modules/Outbox/Infrastructure/Message/OutboxMessageLoader.php:7:use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
app/src/Modules/Outbox/Infrastructure/Message/OutboxMessageLoader.php:15:final readonly class OutboxMessageLoader implements OutboxMessageLoaderContract
app/src/Modules/Outbox/Infrastructure/Message/ValinorOutboxMessageSerializer.php:18:final readonly class ValinorOutboxMessageSerializer implements OutboxMessageSerializerContract
app/src/Modules/Outbox/Infrastructure/Message/OutboxEventStore.php:7:use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
app/src/Modules/Outbox/Infrastructure/Message/OutboxEventStore.php:16:final readonly class OutboxEventStore implements OutboxEventStoreContract
app/src/Modules/Media/Application/Message/MediaUploaded.php:16:final readonly class MediaUploaded implements OutboxMessage
app/src/Modules/Outbox/Application/Message/OutboxMessage.php:7:interface OutboxMessage {}
app/src/Modules/Outbox/Application/Message/OutboxDebugLogMessage.php:7:final readonly class OutboxDebugLogMessage implements OutboxMessage
app/src/Modules/Outbox/Application/Contract/OutboxMessageLoaderContract.php:10:interface OutboxMessageLoaderContract
app/src/Modules/Outbox/Application/Contract/OutboxMessageSerializerContract.php:10:interface OutboxMessageSerializerContract
app/src/Modules/Outbox/Application/Contract/OutboxEventStoreContract.php:10:interface OutboxEventStoreContract
app/src/Modules/Outbox/Application/Contract/OutboxJobRegistryContract.php:9:interface OutboxJobRegistryContract
app/src/Modules/Outbox/Application/Contract/OutboxJobRegistryContract.php:15:    public function register(string $outboxMessageClass, string $outboxJobClass): void;

exec
/bin/zsh -lc "nl -ba app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxBootloader.php app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxConsoleBootloader.php | sed -n '1,260p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Application/Command/Media/CompleteMediaUpload/CompleteMediaUploadHandler.php | sed -n '1,200p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Application\Command\Media\CompleteMediaUpload;
     6	
     7	use App\Modules\Media\Application\Contract\MediaFileServiceContract;
     8	use App\Modules\Media\Application\Dto\MediaConversionSpec;
     9	use App\Modules\Media\Application\Dto\MediaResult;
    10	use App\Modules\Media\Application\Message\MediaUploaded;
    11	use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
    12	use App\Modules\Media\Domain\Entity\Media;
    13	use App\Modules\Media\Domain\Enum\MediaStatus;
    14	use App\Modules\Media\Domain\ValueObject\MediaId;
    15	use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
    16	use App\Modules\Media\Repository\MediaMultipartUploadRepository;
    17	use App\Modules\Media\Repository\MediaRepository;
    18	use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
    19	use App\Shared\Domain\Exception\ForbiddenException;
    20	use App\Shared\Domain\Exception\NotFoundException;
    21	use App\Shared\Domain\Exception\ValidationException;
    22	use App\Shared\Domain\ValueObject\UserId;
    23	use Cycle\ORM\EntityManagerInterface;
    24	use GianTiaga\SpiralCqrs\Attribute\LogOperation;
    25	use GianTiaga\SpiralCqrs\Attribute\Transactional;
    26	use Illuminate\Support\Collection;
    27	use Psr\Log\LoggerInterface;
    28	
    29	final readonly class CompleteMediaUploadHandler
    30	{
    31	    public function __construct(
    32	        private MediaRepository $mediaRepository,
    33	        private MediaMultipartUploadRepository $mediaMultipartUploadRepository,
    34	        private MediaFileServiceContract $mediaFileService,
    35	        private OutboxEventStoreContract $outboxEventStore,
    36	        private EntityManagerInterface $entityManager,
    37	        private LoggerInterface $logger,
    38	    ) {}
    39	
    40	    #[Transactional]
    41	    #[LogOperation]
    42	    public function handle(CompleteMediaUploadCommand $command): MediaResult
    43	    {
    44	        $media = $this->mediaRepository->findById(MediaId::fromString($command->mediaId))
    45	            ?? throw new NotFoundException('app.media.not_found');
    46	
    47	        if (!$media->uploadedById->equals(UserId::fromString($command->userId))) {
    48	            throw new ForbiddenException('app.media.access_denied');
    49	        }
    50	
    51	        if ($media->status !== MediaStatus::WaitingUpload) {
    52	            throw new ValidationException('app.media.upload_not_pending');
    53	        }
    54	
    55	        $this->assertConversionsValid($command->conversions);
    56	
    57	        if ($command->parts !== null) {
    58	            $this->completeMultipartUpload(media: $media, parts: $command->parts);
    59	        }
    60	
    61	        $this->assertObjectUploaded($media);
    62	
    63	        $media->markUploaded();
    64	        $this->outboxEventStore->add(new MediaUploaded(
    65	            mediaId: $media->id->value(),
    66	            conversions: $command->conversions,
    67	        ));
    68	        $this->entityManager->persist($media);
    69	        $this->entityManager->run();
    70	
    71	        $this->logger->debug(message: 'Загрузка медиа подтверждена.', context: [
    72	            'mediaId' => $media->id->value(),
    73	            'userId' => $command->userId,
    74	            'conversions' => \count($command->conversions),
    75	        ]);
    76	
    77	        return MediaResult::fromEntity($media);
    78	    }
    79	
    80	    private function completeMultipartUpload(Media $media, MediaMultipartPartCollection $parts): void
    81	    {
    82	        $multipartUpload = $this->mediaMultipartUploadRepository->findByMediaId($media->id)
    83	            ?? throw new ValidationException('app.media.multipart_upload_not_found');
    84	
    85	        $multipartUpload->replaceParts($parts);
    86	        $this->entityManager->persist($multipartUpload);
    87	        $this->mediaFileService->completeMultipartUpload(
    88	            storage: $media->storage,
    89	            path: $media->path,
    90	            uploadId: $multipartUpload->uploadId,
    91	            parts: $parts,
    92	        );
    93	    }
    94	
    95	    private function assertObjectUploaded(Media $media): void
    96	    {
    97	        $objectHead = $this->mediaFileService->headObject(storage: $media->storage, path: $media->path);
    98	
    99	        if ($objectHead === null || $objectHead->contentLength->value() !== $media->size->value()) {
   100	            throw new ValidationException('app.media.uploaded_object_mismatch');
   101	        }
   102	    }
   103	
   104	    /**
   105	     * @param list<MediaConversionSpec> $conversions
   106	     */
   107	    private function assertConversionsValid(array $conversions): void
   108	    {
   109	        // Симметричный guard на той же Application-границе: проверяем обе границы домена
   110	        // (MediaPixelDimension MIN=1, MAX=100_000) через контракт VO, а не магическими числами.
   111	        // Иначе невалидная спека (например, 0 или 200000) проходит подтверждение, кладётся в outbox
   112	        // и падает асинхронно в ProcessMedia -> терминальный ProcessingFailed.
   113	        $hasOutOfRangeConversion = Collection::make($conversions)->contains(
   114	            static fn(MediaConversionSpec $conversion): bool => !MediaPixelDimension::supports($conversion->width)
   115	                || !MediaPixelDimension::supports($conversion->height),
   116	        );
   117	
   118	        if ($hasOutOfRangeConversion) {
   119	            throw new ValidationException('app.media.conversion_dimensions_out_of_range');
   120	        }
   121	    }
   122	}

 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

codex
Outbox-паттерн в плане в целом совпадает с Media: сообщение сохраняется в транзакции, Job грузит его через loader и диспатчит команду. Но есть нюанс по месту Job: существующий эталон кладёт Job в `Presentation/Job`, а не в `Presentation/Http`. План это соблюдает.
exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php | sed -n '1,120p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Infrastructure\Bootloader;
     6	
     7	use App\Modules\Media\Application\Contract\MediaFileServiceContract;
     8	use App\Modules\Media\Application\Contract\MediaImageProcessorContract;
     9	use App\Modules\Media\Application\Message\MediaUploaded;
    10	use App\Modules\Media\Infrastructure\FileService\ConfiguredS3ClientProvider;
    11	use App\Modules\Media\Infrastructure\FileService\ImagickMediaImageProcessor;
    12	use App\Modules\Media\Infrastructure\FileService\S3ClientProvider;
    13	use App\Modules\Media\Infrastructure\FileService\S3MediaFileService;
    14	use App\Modules\Media\Presentation\Job\ProcessMediaJob;
    15	use App\Modules\Outbox\Application\Contract\OutboxJobRegistryContract;
    16	use Spiral\Boot\Bootloader\Bootloader;
    17	
    18	final class MediaBootloader extends Bootloader
    19	{
    20	    protected const BINDINGS = [
    21	        MediaFileServiceContract::class => S3MediaFileService::class,
    22	        MediaImageProcessorContract::class => ImagickMediaImageProcessor::class,
    23	        S3ClientProvider::class => ConfiguredS3ClientProvider::class,
    24	    ];
    25	
    26	    public function boot(OutboxJobRegistryContract $outboxJobRegistry): void
    27	    {
    28	        $outboxJobRegistry->register(
    29	            outboxMessageClass: MediaUploaded::class,
    30	            outboxJobClass: ProcessMediaJob::class,
    31	        );
    32	    }
    33	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Outbox/Application/Contract/OutboxEventStoreContract.php app/src/Modules/Outbox/Application/Contract/OutboxMessageLoaderContract.php app/src/Modules/Outbox/Application/Contract/OutboxJobRegistryContract.php app/src/Modules/Outbox/Application/Message/OutboxMessage.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php | sed -n '1,150p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Presentation\Job;
     6	
     7	use App\Modules\Media\Application\Command\Media\ProcessMedia\ProcessMediaCommand;
     8	use App\Modules\Media\Application\Command\Media\ProcessMedia\ProcessMediaHandler;
     9	use App\Modules\Media\Application\Command\Media\RecordMediaProcessingFailure\RecordMediaProcessingFailureCommand;
    10	use App\Modules\Media\Application\Command\Media\RecordMediaProcessingFailure\RecordMediaProcessingFailureHandler;
    11	use App\Modules\Media\Application\Exception\MediaFileServiceFailedException;
    12	use App\Modules\Media\Application\Message\MediaUploaded;
    13	use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
    14	use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
    15	use GianTiaga\SpiralCqrs\CommandBusInterface;
    16	use Psr\Log\LoggerInterface;
    17	use Spiral\Queue\Exception\RetryException;
    18	use Spiral\Queue\JobHandler;
    19	
    20	/**
    21	 * Инфраструктурный Job обработки медиа. Грузит MediaUploaded из outbox и запускает
    22	 * ProcessMediaCommand. Ошибки обработки ловятся здесь (Job — граница системы, try-catch
    23	 * разрешён): фиксируем безопасную ошибку на Media и классифицируем — транзиентную просим
    24	 * повторить (RetryException, его читает OutboxQueueStatusInterceptor), постоянную пробрасываем
    25	 * терминально (outbox -> failed). Статусы outbox Job сам не трогает.
    26	 */
    27	final class ProcessMediaJob extends JobHandler
    28	{
    29	    private const string STORAGE_FAILURE_MESSAGE = 'Ошибка хранилища при обработке медиа.';
    30	    private const string PROCESSING_FAILURE_MESSAGE = 'Не удалось обработать медиа.';
    31	
    32	    public function invoke(
    33	        OutboxQueueEnvelope $payload,
    34	        string $id,
    35	        OutboxMessageLoaderContract $outboxMessageLoader,
    36	        CommandBusInterface $commandBus,
    37	        ProcessMediaHandler $processMediaHandler,
    38	        RecordMediaProcessingFailureHandler $recordMediaProcessingFailureHandler,
    39	        LoggerInterface $logger,
    40	    ): void {
    41	        $mediaUploaded = $outboxMessageLoader->load(
    42	            outboxEventId: $payload->outboxEventId,
    43	            expectedMessageClass: MediaUploaded::class,
    44	        );
    45	
    46	        try {
    47	            $commandBus->dispatch(
    48	                command: new ProcessMediaCommand(
    49	                    mediaId: $mediaUploaded->mediaId,
    50	                    conversions: $mediaUploaded->conversions,
    51	                ),
    52	                handler: $processMediaHandler->handle(...),
    53	            );
    54	        } catch (\Throwable $exception) {
    55	            // Транзиентность приходит контрактным сигналом MediaFileServiceFailedException::isTransient():
    56	            // классификацию AWS делает Infrastructure, Presentation не знает про реализацию хранилища.
    57	            $isTransient = $exception instanceof MediaFileServiceFailedException && $exception->isTransient();
    58	
    59	            // Запись ошибки на Media — отдельный сбойный путь: медиа могли конкурентно удалить
    60	            // (NotFoundException) или короткий сбой БД. Защищаем только этот вызов локальным guard,
    61	            // чтобы вторичный сбой записи не подменил исходную причину и решение retry/terminal:
    62	            // логируем его как вторичный сбой (ERROR — реальная инфра/инвариант-проблема, rules.md:84)
    63	            // и продолжаем классифицировать по исходному $exception.
    64	            try {
    65	                $commandBus->dispatch(
    66	                    command: new RecordMediaProcessingFailureCommand(
    67	                        mediaId: $mediaUploaded->mediaId,
    68	                        error: $this->safeMessage($exception),
    69	                        isTransient: $isTransient,
    70	                    ),
    71	                    handler: $recordMediaProcessingFailureHandler->handle(...),
    72	                );
    73	            } catch (\Throwable $recordFailure) {
    74	                $logger->error(message: 'Не удалось записать ошибку обработки на медиа.', context: [
    75	                    'mediaId' => $mediaUploaded->mediaId,
    76	                    'jobId' => $id,
    77	                    'recordErrorClass' => $recordFailure::class,
    78	                    'originalErrorClass' => $exception::class,
    79	                    'errorMessage' => $recordFailure->getMessage(),
    80	                ]);
    81	            }
    82	
    83	            $logContext = [
    84	                'mediaId' => $mediaUploaded->mediaId,
    85	                'jobId' => $id,
    86	                'isTransient' => $isTransient,
    87	                'errorClass' => $exception::class,
    88	            ];
    89	
    90	            if ($isTransient) {
    91	                // Транзиентный ретраябельный сбой инфраструктуры -> WARN (rules.md:84), повтор ожидаем.
    92	                $logger->warning(
    93	                    message: 'Транзиентная ошибка обработки медиа, запланирован повтор.',
    94	                    context: $logContext,
    95	                );
    96	
    97	                throw new RetryException(reason: self::STORAGE_FAILURE_MESSAGE);
    98	            }
    99	
   100	            // Постоянный сбой (битый файл/нарушение инварианта) -> ERROR, повтора не будет.
   101	            $logger->error(message: 'Ошибка обработки медиа.', context: $logContext);
   102	
   103	            throw $exception;
   104	        }
   105	    }
   106	
   107	    private function safeMessage(\Throwable $exception): string
   108	    {
   109	        return $exception instanceof MediaFileServiceFailedException
   110	            ? self::STORAGE_FAILURE_MESSAGE
   111	            : self::PROCESSING_FAILURE_MESSAGE;
   112	    }
   113	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxBootloader.php | sed -n '1,120p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Outbox\Infrastructure\Bootloader;
     6	
     7	use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
     8	use App\Modules\Outbox\Application\Contract\OutboxJobRegistryContract;
     9	use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
    10	use App\Modules\Outbox\Application\Contract\OutboxMessageSerializerContract;
    11	use App\Modules\Outbox\Application\Contract\OutboxRelayContract;
    12	use App\Modules\Outbox\Application\Contract\OutboxRelayLoopControlContract;
    13	use App\Modules\Outbox\Application\Contract\OutboxRelaySleeperContract;
    14	use App\Modules\Outbox\Application\Contract\OutboxRelayWorkerContract;
    15	use App\Modules\Outbox\Application\Message\OutboxDebugLogMessage;
    16	use App\Modules\Outbox\Infrastructure\Message\OutboxEventStore;
    17	use App\Modules\Outbox\Infrastructure\Message\OutboxMessageLoader;
    18	use App\Modules\Outbox\Infrastructure\Message\ValinorOutboxMessageSerializer;
    19	use App\Modules\Outbox\Infrastructure\Registry\OutboxJobRegistry;
    20	use App\Modules\Outbox\Infrastructure\Relay\InfiniteOutboxRelayLoopControl;
    21	use App\Modules\Outbox\Infrastructure\Relay\OutboxRelay;
    22	use App\Modules\Outbox\Infrastructure\Relay\OutboxRelayWorker;
    23	use App\Modules\Outbox\Infrastructure\Relay\SystemOutboxRelaySleeper;
    24	use App\Modules\Outbox\Presentation\Job\OutboxDebugLogJob;
    25	use Spiral\Boot\Bootloader\Bootloader;
    26	
    27	final class OutboxBootloader extends Bootloader
    28	{
    29	    protected const BINDINGS = [
    30	        OutboxEventStoreContract::class => OutboxEventStore::class,
    31	        OutboxMessageLoaderContract::class => OutboxMessageLoader::class,
    32	        OutboxRelayContract::class => OutboxRelay::class,
    33	        OutboxRelayLoopControlContract::class => InfiniteOutboxRelayLoopControl::class,
    34	        OutboxRelaySleeperContract::class => SystemOutboxRelaySleeper::class,
    35	        OutboxMessageSerializerContract::class => ValinorOutboxMessageSerializer::class,
    36	        OutboxRelayWorkerContract::class => OutboxRelayWorker::class,
    37	    ];
    38	
    39	    protected const SINGLETONS = [
    40	        OutboxJobRegistryContract::class => OutboxJobRegistry::class,
    41	    ];
    42	
    43	    public function boot(OutboxJobRegistryContract $outboxJobRegistry): void
    44	    {
    45	        $outboxJobRegistry->register(
    46	            outboxMessageClass: OutboxDebugLogMessage::class,
    47	            outboxJobClass: OutboxDebugLogJob::class,
    48	        );
    49	    }
    50	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Outbox/Application/Contract/OutboxMessageLoaderContract.php | sed -n '1,100p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba app/src/Modules/Outbox/Application/Contract/OutboxEventStoreContract.php | sed -n '1,80p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Outbox\Application\Contract;
     6	
     7	use App\Modules\Outbox\Application\Message\OutboxMessage;
     8	use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
     9	
    10	interface OutboxMessageLoaderContract
    11	{
    12	    /**
    13	     * @template TOutboxMessage of OutboxMessage
    14	     * @param class-string<TOutboxMessage> $expectedMessageClass
    15	     * @return TOutboxMessage
    16	     */
    17	    public function load(OutboxEventId $outboxEventId, string $expectedMessageClass): OutboxMessage;
    18	}

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Outbox\Application\Contract;
     6	
     7	use App\Modules\Outbox\Application\Message\OutboxMessage;
     8	use App\Modules\Outbox\Application\Message\StoredOutboxEventId;
     9	
    10	interface OutboxEventStoreContract
    11	{
    12	    public function add(OutboxMessage $outboxMessage): StoredOutboxEventId;
    13	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Outbox/Application/Contract/OutboxJobRegistryContract.php | sed -n '1,80p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Outbox\Application\Contract;
     6	
     7	use App\Modules\Outbox\Application\Message\OutboxMessage;
     8	
     9	interface OutboxJobRegistryContract
    10	{
    11	    /**
    12	     * @param class-string<OutboxMessage> $outboxMessageClass
    13	     * @param class-string $outboxJobClass
    14	     */
    15	    public function register(string $outboxMessageClass, string $outboxJobClass): void;
    16	
    17	    /**
    18	     * @return class-string
    19	     */
    20	    public function jobFor(OutboxMessage $outboxMessage): string;
    21	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Application/Message/MediaUploaded.php app/src/Modules/Outbox/Application/Message/OutboxDebugLogMessage.php app/src/Modules/Outbox/Application/Message/OutboxQueueEnvelope.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

exec
/bin/zsh -lc "nl -ba app/src/Modules/Outbox/Application/Message/OutboxDebugLogMessage.php | sed -n '1,80p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Application/Message/MediaUploaded.php | sed -n '1,100p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Outbox\Application\Message;
     6	
     7	final readonly class OutboxDebugLogMessage implements OutboxMessage
     8	{
     9	    public function __construct(
    10	        public string $text,
    11	        public \DateTimeImmutable $createdAt,
    12	    ) {}
    13	}

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Application\Message;
     6	
     7	use App\Modules\Media\Application\Dto\MediaConversionSpec;
     8	use App\Modules\Outbox\Application\Message\OutboxMessage;
     9	
    10	/**
    11	 * Outbox-сообщение: загрузка подтверждена, нужна асинхронная обработка. Payload — только
    12	 * примитивы/enum (mediaId — строка, conversions — список публичных readonly-DTO
    13	 * MediaConversionSpec), чтобы ValinorOutboxMessageSerializer восстановил его без кастомных
    14	 * конструкторов VO.
    15	 */
    16	final readonly class MediaUploaded implements OutboxMessage
    17	{
    18	    /**
    19	     * @param list<MediaConversionSpec> $conversions
    20	     */
    21	    public function __construct(
    22	        public string $mediaId,
    23	        public array $conversions,
    24	    ) {}
    25	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Outbox/Application/Message/OutboxQueueEnvelope.php | sed -n '1,100p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba app/src/Modules/Outbox/Infrastructure/Message/ValinorOutboxMessageSerializer.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Outbox\Application\Message;
     6	
     7	use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
     8	use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
     9	use App\Modules\Outbox\Domain\ValueObject\OutboxEventType;
    10	
    11	final readonly class OutboxQueueEnvelope
    12	{
    13	    public function __construct(
    14	        public OutboxEventId $outboxEventId,
    15	        public OutboxEventType $outboxEventType,
    16	    ) {}
    17	
    18	    public static function fromStoredEvent(StoredOutboxEvent $storedOutboxEvent): self
    19	    {
    20	        return new self(
    21	            outboxEventId: $storedOutboxEvent->id,
    22	            outboxEventType: $storedOutboxEvent->type,
    23	        );
    24	    }
    25	}

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Outbox\Infrastructure\Message;
     6	
     7	use App\Modules\Outbox\Application\Contract\OutboxMessageSerializerContract;
     8	use App\Modules\Outbox\Application\Exception\OutboxMessageSerializationException;
     9	use App\Modules\Outbox\Application\Message\SerializedOutboxMessage;
    10	use App\Modules\Outbox\Application\Message\OutboxMessage;
    11	use CuyZ\Valinor\Mapper\Source\Source;
    12	use CuyZ\Valinor\Mapper\TreeMapper;
    13	use CuyZ\Valinor\MapperBuilder;
    14	use CuyZ\Valinor\Normalizer\Format;
    15	use CuyZ\Valinor\Normalizer\Normalizer;
    16	use CuyZ\Valinor\NormalizerBuilder;
    17	
    18	final readonly class ValinorOutboxMessageSerializer implements OutboxMessageSerializerContract
    19	{
    20	    private TreeMapper $mapper;
    21	
    22	    /**
    23	     * @var Normalizer<string>
    24	     */
    25	    private Normalizer $normalizer;
    26	
    27	    public function __construct()
    28	    {
    29	        $this->mapper = (new MapperBuilder())
    30	            ->allowPermissiveTypes()
    31	            ->allowScalarValueCasting()
    32	            ->mapper();
    33	        $this->normalizer = (new NormalizerBuilder())->normalizer(Format::json());
    34	    }
    35	
    36	    #[\Override]
    37	    public function serialize(OutboxMessage $outboxMessage): SerializedOutboxMessage
    38	    {
    39	        return new SerializedOutboxMessage(
    40	            type: $outboxMessage::class,
    41	            payload: $this->normalizer->normalize($outboxMessage),
    42	        );
    43	    }
    44	
    45	    #[\Override]
    46	    public function deserialize(SerializedOutboxMessage $serializedOutboxMessage): OutboxMessage
    47	    {
    48	        if (!\is_subclass_of(object_or_class: $serializedOutboxMessage->type, class: OutboxMessage::class)) {
    49	            throw OutboxMessageSerializationException::unsupportedMessageType($serializedOutboxMessage->type);
    50	        }
    51	
    52	        return $this->mapper->map(
    53	            signature: $serializedOutboxMessage->type,
    54	            source: Source::json($serializedOutboxMessage->payload),
    55	        );
    56	    }
    57	}

exec
/bin/zsh -lc "nl -ba tests/TestCase.php tests/DatabaseTestCase.php tests/Feature/Modules/System/Http/ApiErrorHttpTest.php | sed -n '1,300p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

exec
/bin/zsh -lc "nl -ba tests/Feature/Modules/User/Repository/UserRepositoryTest.php tests/Feature/Modules/Outbox/Application/OutboxEventStoreTransactionTest.php | sed -n '1,320p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

exec
/bin/zsh -lc "nl -ba tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php | sed -n '1,260p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba tests/App/TestKernel.php tests/App/Bootloader/ApiErrorTestRoutesBootloader.php tests/App/Modules/System/Http/ApiErrorTestController.php | sed -n '1,320p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Tests\Feature\Modules\Media\Application;
     6	
     7	use App\Modules\Media\Application\Command\Media\CompleteMediaUpload\CompleteMediaUploadCommand;
     8	use App\Modules\Media\Application\Command\Media\CompleteMediaUpload\CompleteMediaUploadHandler;
     9	use App\Modules\Media\Application\Contract\MediaFileServiceContract;
    10	use App\Modules\Media\Application\Dto\MediaObjectHead;
    11	use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
    12	use App\Modules\Media\Domain\Entity\Media;
    13	use App\Modules\Media\Domain\Entity\MediaMultipartUpload;
    14	use App\Modules\Media\Domain\Enum\MediaStatus;
    15	use App\Modules\Media\Domain\ValueObject\MediaFileSize;
    16	use App\Modules\Media\Domain\ValueObject\MediaMultipartPart;
    17	use App\Modules\Media\Domain\ValueObject\MediaMultipartPartETag;
    18	use App\Modules\Media\Domain\ValueObject\MediaMultipartPartNumber;
    19	use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
    20	use App\Modules\Media\Domain\ValueObject\MediaMultipartPartSize;
    21	use App\Modules\Media\Domain\ValueObject\MediaMultipartUploadIdValue;
    22	use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
    23	use App\Modules\Outbox\Application\Message\StoredOutboxEventId;
    24	use App\Shared\Domain\Exception\ForbiddenException;
    25	use App\Shared\Domain\Exception\NotFoundException;
    26	use App\Shared\Domain\Exception\ValidationException;
    27	use App\Shared\Domain\ValueObject\UserId;
    28	use PHPUnit\Framework\Attributes\DataProvider;
    29	use Psr\Log\NullLogger;
    30	
    31	final class CompleteMediaUploadHandlerTest extends MediaApplicationTestCase
    32	{
    33	    public function testCompletesSingleUploadAndQueuesProcessing(): void
    34	    {
    35	        $userId = UserId::generate();
    36	        $media = $this->createMedia(userId: $userId, size: MediaFileSize::fromInt(2048));
    37	        $this->persist($media);
    38	
    39	        $outboxStore = $this->createMock(OutboxEventStoreContract::class);
    40	        $outboxStore->expects(self::once())->method('add')->willReturn(StoredOutboxEventId::fromString('outbox-1'));
    41	
    42	        $result = $this->handler($this->fileServiceWithHead(2048), $outboxStore)->handle(
    43	            new CompleteMediaUploadCommand(
    44	                userId: $userId->value(),
    45	                mediaId: $media->id->value(),
    46	                conversions: [$this->conversionSpec()],
    47	                parts: null,
    48	            ),
    49	        );
    50	
    51	        self::assertSame(MediaStatus::Uploaded, $result->status);
    52	        self::assertSame(MediaStatus::Uploaded, $media->status);
    53	    }
    54	
    55	    public function testCompletesMultipartUpload(): void
    56	    {
    57	        $userId = UserId::generate();
    58	        $media = $this->createMedia(userId: $userId, size: MediaFileSize::fromInt(2048));
    59	        $multipartUpload = $this->multipartUploadFor($media);
    60	        $this->persist($media, $multipartUpload);
    61	
    62	        $fileService = $this->createMock(MediaFileServiceContract::class);
    63	        $fileService->method('headObject')->willReturn(new MediaObjectHead(contentLength: MediaFileSize::fromInt(2048)));
    64	        $fileService->expects(self::once())->method('completeMultipartUpload');
    65	
    66	        $result = $this->handler($fileService, $this->outboxStore())->handle(new CompleteMediaUploadCommand(
    67	            userId: $userId->value(),
    68	            mediaId: $media->id->value(),
    69	            conversions: [],
    70	            parts: $this->parts(),
    71	        ));
    72	
    73	        self::assertSame(MediaStatus::Uploaded, $result->status);
    74	    }
    75	
    76	    public function testRejectsMissingMedia(): void
    77	    {
    78	        $this->expectException(NotFoundException::class);
    79	
    80	        $this->handler($this->fileServiceWithHead(2048), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
    81	            userId: UserId::generate()->value(),
    82	            mediaId: UserId::generate()->value(),
    83	            conversions: [],
    84	            parts: null,
    85	        ));
    86	    }
    87	
    88	    public function testRejectsForeignOwner(): void
    89	    {
    90	        $media = $this->createMedia(userId: UserId::generate());
    91	        $this->persist($media);
    92	
    93	        $this->expectException(ForbiddenException::class);
    94	
    95	        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
    96	            userId: UserId::generate()->value(),
    97	            mediaId: $media->id->value(),
    98	            conversions: [],
    99	            parts: null,
   100	        ));
   101	    }
   102	
   103	    public function testRejectsMediaNotWaitingUpload(): void
   104	    {
   105	        $userId = UserId::generate();
   106	        $media = $this->createMedia(userId: $userId);
   107	        $media->markUploaded();
   108	        $this->persist($media);
   109	
   110	        $this->expectException(ValidationException::class);
   111	
   112	        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   113	            userId: $userId->value(),
   114	            mediaId: $media->id->value(),
   115	            conversions: [],
   116	            parts: null,
   117	        ));
   118	    }
   119	
   120	    #[DataProvider('outOfRangeConversionDimensionProvider')]
   121	    public function testRejectsConversionDimensionsOutOfDomainRange(int $width, int $height): void
   122	    {
   123	        $userId = UserId::generate();
   124	        $media = $this->createMedia(userId: $userId);
   125	        $this->persist($media);
   126	
   127	        $this->expectException(ValidationException::class);
   128	
   129	        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   130	            userId: $userId->value(),
   131	            mediaId: $media->id->value(),
   132	            conversions: [$this->conversionSpec(width: $width, height: $height)],
   133	            parts: null,
   134	        ));
   135	    }
   136	
   137	    /**
   138	     * @return array<string, array{int, int}>
   139	     */
   140	    public static function outOfRangeConversionDimensionProvider(): array
   141	    {
   142	        // Симметрия с MediaPixelDimension (MIN=1, MAX=100_000): ноль/негатив снизу, 200000 сверху.
   143	        return [
   144	            'нулевая ширина (нижняя граница)' => [0, 100],
   145	            'нулевая высота (нижняя граница)' => [100, 0],
   146	            'ширина выше максимума (верхняя граница)' => [200_000, 100],
   147	            'высота выше максимума (верхняя граница)' => [100, 200_000],
   148	        ];
   149	    }
   150	
   151	    public function testRejectsMultipartWithoutUploadRecord(): void
   152	    {
   153	        $userId = UserId::generate();
   154	        $media = $this->createMedia(userId: $userId);
   155	        $this->persist($media);
   156	
   157	        $this->expectException(ValidationException::class);
   158	
   159	        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   160	            userId: $userId->value(),
   161	            mediaId: $media->id->value(),
   162	            conversions: [],
   163	            parts: $this->parts(),
   164	        ));
   165	    }
   166	
   167	    public function testRejectsWhenUploadedObjectMissing(): void
   168	    {
   169	        $userId = UserId::generate();
   170	        $media = $this->createMedia(userId: $userId);
   171	        $this->persist($media);
   172	
   173	        $fileService = $this->createStub(MediaFileServiceContract::class);
   174	        $fileService->method('headObject')->willReturn(null);
   175	
   176	        $this->expectException(ValidationException::class);
   177	
   178	        $this->handler($fileService, $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   179	            userId: $userId->value(),
   180	            mediaId: $media->id->value(),
   181	            conversions: [],
   182	            parts: null,
   183	        ));
   184	    }
   185	
   186	    public function testRejectsWhenUploadedObjectSizeMismatches(): void
   187	    {
   188	        $userId = UserId::generate();
   189	        $media = $this->createMedia(userId: $userId, size: MediaFileSize::fromInt(2048));
   190	        $this->persist($media);
   191	
   192	        $this->expectException(ValidationException::class);
   193	
   194	        $this->handler($this->fileServiceWithHead(999), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   195	            userId: $userId->value(),
   196	            mediaId: $media->id->value(),
   197	            conversions: [],
   198	            parts: null,
   199	        ));
   200	    }
   201	
   202	    private function handler(
   203	        MediaFileServiceContract $fileService,
   204	        OutboxEventStoreContract $outboxStore,
   205	    ): CompleteMediaUploadHandler {
   206	        return new CompleteMediaUploadHandler(
   207	            mediaRepository: $this->mediaRepository(),
   208	            mediaMultipartUploadRepository: $this->multipartUploadRepository(),
   209	            mediaFileService: $fileService,
   210	            outboxEventStore: $outboxStore,
   211	            entityManager: $this->entityManager(),
   212	            logger: new NullLogger(),
   213	        );
   214	    }
   215	
   216	    private function fileServiceWithHead(int $contentLength): MediaFileServiceContract
   217	    {
   218	        $fileService = $this->createStub(MediaFileServiceContract::class);
   219	        $fileService->method('headObject')->willReturn(
   220	            new MediaObjectHead(contentLength: MediaFileSize::fromInt($contentLength)),
   221	        );
   222	
   223	        return $fileService;
   224	    }
   225	
   226	    private function outboxStore(): OutboxEventStoreContract
   227	    {
   228	        $outboxStore = $this->createStub(OutboxEventStoreContract::class);
   229	        $outboxStore->method('add')->willReturn(StoredOutboxEventId::fromString('outbox-1'));
   230	
   231	        return $outboxStore;
   232	    }
   233	
   234	    private function multipartUploadFor(Media $media): MediaMultipartUpload
   235	    {
   236	        return MediaMultipartUpload::create(
   237	            media: $media,
   238	            uploadId: MediaMultipartUploadIdValue::fromString('upload-1'),
   239	            partsCount: MediaMultipartPartsCount::fromInt(1),
   240	            partSize: MediaMultipartPartSize::fromInt(5_242_880),
   241	            fileSize: $media->size,
   242	        );
   243	    }
   244	
   245	    private function parts(): MediaMultipartPartCollection
   246	    {
   247	        return new MediaMultipartPartCollection([
   248	            MediaMultipartPart::create(
   249	                partNumber: MediaMultipartPartNumber::fromInt(1),
   250	                eTag: MediaMultipartPartETag::fromString('etag-1'),
   251	            ),
   252	        ]);
   253	    }
   254	}

codex
Тестовая база уже даёт хорошие шаблоны для feature-тестов Application и HTTP. Плану не хватает только некоторых конкретных проверок гонок и DI, а не полного пересмотра тестовой стратегии.
exec
/bin/zsh -lc "nl -ba tests/TestCase.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Tests;
     6	
     7	use Cycle\Database\DatabaseInterface;
     8	use Spiral\Config\ConfiguratorInterface;
     9	use Spiral\Config\Patch\Set;
    10	use Spiral\Core\Container;
    11	use Spiral\Testing\TestableKernelInterface;
    12	use Spiral\Testing\TestCase as BaseTestCase;
    13	use Spiral\Translator\TranslatorInterface;
    14	use Tests\App\TestKernel;
    15	
    16	class TestCase extends BaseTestCase
    17	{
    18	    public function createAppInstance(Container $container = new Container()): TestableKernelInterface
    19	    {
    20	        return TestKernel::create(
    21	            directories: $this->defineDirectories(
    22	                $this->rootDirectory(),
    23	            ),
    24	            container: $container,
    25	        );
    26	    }
    27	
    28	    public function rootDirectory(): string
    29	    {
    30	        return __DIR__ . '/..';
    31	    }
    32	
    33	    public function defineDirectories(string $root): array
    34	    {
    35	        return TestRuntime::directories($root);
    36	    }
    37	
    38	    protected function setUp(): void
    39	    {
    40	        $this->beforeBooting(static function (ConfiguratorInterface $config): void {
    41	            if (!$config->exists('session')) {
    42	                return;
    43	            }
    44	
    45	            $config->modify('session', new Set('handler', null));
    46	        });
    47	
    48	        parent::setUp();
    49	
    50	        $container = $this->getContainer();
    51	
    52	        if ($container->has(TranslatorInterface::class)) {
    53	            $container->get(TranslatorInterface::class)->setLocale('en');
    54	        }
    55	    }
    56	
    57	    protected function tearDown(): void
    58	    {
    59	        try {
    60	            \restore_error_handler();
    61	            \restore_exception_handler();
    62	            $this->disconnectDatabase();
    63	        } finally {
    64	            parent::tearDown();
    65	        }
    66	
    67	        // Раскомментируйте строку ниже, если нужно очищать runtime-директорию после тестов.
    68	        // $this->cleanUpRuntimeDirectory();
    69	    }
    70	
    71	    private function disconnectDatabase(): void
    72	    {
    73	        $container = $this->getContainer();
    74	
    75	        if (!$container->has(DatabaseInterface::class)) {
    76	            return;
    77	        }
    78	
    79	        $database = $container->get(DatabaseInterface::class);
    80	        $database->getDriver(DatabaseInterface::WRITE)->disconnect();
    81	        $database->getDriver(DatabaseInterface::READ)->disconnect();
    82	    }
    83	}

exec
/bin/zsh -lc "nl -ba tests/Feature/Modules/User/Repository/UserRepositoryTest.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Tests\Feature\Modules\User\Repository;
     6	
     7	use App\Modules\Media\Domain\Entity\Media;
     8	use App\Modules\Media\Domain\Enum\MediaType;
     9	use App\Modules\Media\Domain\Enum\MediaVisibility;
    10	use App\Modules\Media\Domain\ValueObject\MediaExpiration;
    11	use App\Modules\Media\Domain\ValueObject\MediaFileSize;
    12	use App\Modules\Media\Domain\ValueObject\MediaMimeType;
    13	use App\Modules\Media\Domain\ValueObject\MediaPath;
    14	use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
    15	use App\Modules\User\Domain\Entity\ReservedNickname;
    16	use App\Modules\User\Domain\Entity\User;
    17	use App\Modules\User\Domain\Entity\UserBan;
    18	use App\Modules\User\Domain\Enum\UserStatus;
    19	use App\Modules\User\Domain\ValueObject\BanExpiration;
    20	use App\Modules\User\Domain\ValueObject\BanReason;
    21	use App\Modules\User\Domain\ValueObject\BanUnbannedAt;
    22	use App\Modules\User\Domain\ValueObject\BanUnbannedBy;
    23	use App\Modules\User\Domain\ValueObject\BanUnbannedReason;
    24	use App\Modules\User\Domain\ValueObject\Email;
    25	use App\Modules\User\Domain\ValueObject\UserAvatar;
    26	use App\Modules\User\Domain\ValueObject\UserBio;
    27	use App\Modules\User\Domain\ValueObject\UserLocation;
    28	use App\Modules\User\Domain\ValueObject\UserName;
    29	use App\Modules\User\Domain\ValueObject\UserNickname;
    30	use App\Modules\User\Domain\ValueObject\UserSpiritualName;
    31	use App\Modules\User\Repository\ReservedNicknameRepository;
    32	use App\Modules\User\Repository\UserBanRepository;
    33	use App\Modules\User\Repository\UserRepository;
    34	use App\Shared\Domain\Enum\Locale;
    35	use App\Shared\Domain\ValueObject\UserId;
    36	use Cycle\ORM\EntityManagerInterface;
    37	use Tests\DatabaseTestCase;
    38	
    39	final class UserRepositoryTest extends DatabaseTestCase
    40	{
    41	    public function testStoresAndRestoresUserWithValueObjects(): void
    42	    {
    43	        $media = $this->createMedia();
    44	        $user = $this->createUser();
    45	        $user->changeSpiritualName(UserSpiritualName::fromString('Шанти'));
    46	        $user->changeBio(UserBio::fromString('Описание'));
    47	        $user->changeLocation(UserLocation::fromString('Москва'));
    48	        $user->setAvatar(UserAvatar::pointingTo($media->id->value()));
    49	        $user->confirmEmail();
    50	        $user->markDeleted(new \DateTimeImmutable('2026-06-13 12:00:00'));
    51	
    52	        $this->entityManager()->persist($media);
    53	        $this->entityManager()->persist($user);
    54	        $this->entityManager()->run();
    55	        $this->cleanOrmHeap();
    56	
    57	        $restoredUser = $this->userRepository()->findById($user->id);
    58	
    59	        self::assertInstanceOf(User::class, $restoredUser);
    60	        self::assertTrue($user->id->equals($restoredUser->id));
    61	        self::assertSame('test@example.com', $restoredUser->email->value());
    62	        self::assertSame('yoga.test', $restoredUser->nickname->value());
    63	        self::assertSame('Шанти', $restoredUser->spiritualName->value());
    64	        self::assertSame('Описание', $restoredUser->bio->value());
    65	        self::assertSame('Москва', $restoredUser->location->value());
    66	        self::assertSame($media->id->value(), $restoredUser->avatar->value());
    67	        self::assertTrue($restoredUser->isDeleted());
    68	        self::assertSame(UserStatus::Deleted, $restoredUser->status);
    69	        self::assertSame(Locale::Ru, $restoredUser->locale);
    70	        self::assertInstanceOf(User::class, $this->userRepository()->findByEmail(Email::fromString('TEST@example.com')));
    71	        self::assertInstanceOf(User::class, $this->userRepository()->findByNickname(UserNickname::fromString('YOGA.TEST')));
    72	        self::assertTrue($this->userRepository()->existsByEmail(Email::fromString('test@example.com')));
    73	        self::assertTrue($this->userRepository()->existsByNickname(UserNickname::fromString('yoga.test')));
    74	    }
    75	
    76	    public function testStoresAndRestoresUserWithEmptyOptionalValues(): void
    77	    {
    78	        $user = $this->createUser(email: 'empty@example.com', nickname: 'empty.user');
    79	
    80	        $this->entityManager()->persist($user);
    81	        $this->entityManager()->run();
    82	        $this->cleanOrmHeap();
    83	
    84	        $restoredUser = $this->userRepository()->findById($user->id);
    85	
    86	        self::assertInstanceOf(User::class, $restoredUser);
    87	        self::assertTrue($restoredUser->spiritualName->isEmpty());
    88	        self::assertTrue($restoredUser->bio->isEmpty());
    89	        self::assertTrue($restoredUser->location->isEmpty());
    90	        self::assertTrue($restoredUser->avatar->isEmpty());
    91	        self::assertFalse($restoredUser->deletion->isDeleted());
    92	    }
    93	
    94	    public function testStoresAndFindsActiveUserBans(): void
    95	    {
    96	        $user = $this->createUser();
    97	        $permanentBan = UserBan::create(
    98	            userId: $user->id,
    99	            bannedById: UserId::generate(),
   100	            reason: BanReason::fromString('Навсегда'),
   101	            expiration: BanExpiration::permanent(),
   102	        );
   103	
   104	        $this->entityManager()->persist($user);
   105	        $this->entityManager()->persist($permanentBan);
   106	        $this->entityManager()->run();
   107	        $this->cleanOrmHeap();
   108	
   109	        $restoredBan = $this->userBanRepository()->findById($permanentBan->id);
   110	        $activeBan = $this->userBanRepository()->findActiveByUserId($user->id, new \DateTimeImmutable());
   111	
   112	        self::assertInstanceOf(UserBan::class, $restoredBan);
   113	        self::assertInstanceOf(UserBan::class, $activeBan);
   114	        self::assertTrue($permanentBan->id->equals($activeBan->id));
   115	    }
   116	
   117	    public function testFindActiveUserBanIgnoresExpiredAndUnbannedRows(): void
   118	    {
   119	        $expiredUser = $this->createUser(email: 'expired@example.com', nickname: 'expired.user');
   120	        $unbannedUser = $this->createUser(email: 'unbanned@example.com', nickname: 'unbanned.user');
   121	        $activeTemporaryUser = $this->createUser(email: 'active@example.com', nickname: 'active.user');
   122	        $expiredBan = UserBan::create(
   123	            userId: $expiredUser->id,
   124	            bannedById: UserId::generate(),
   125	            reason: BanReason::fromString('Истёк'),
   126	            expiration: BanExpiration::until(new \DateTimeImmutable('-1 hour')),
   127	        );
   128	        $unbannedBan = UserBan::create(
   129	            userId: $unbannedUser->id,
   130	            bannedById: UserId::generate(),
   131	            reason: BanReason::fromString('Снят'),
   132	            expiration: BanExpiration::permanent(),
   133	        );
   134	        $activeTemporaryBan = UserBan::create(
   135	            userId: $activeTemporaryUser->id,
   136	            bannedById: UserId::generate(),
   137	            reason: BanReason::fromString('Активен'),
   138	            expiration: BanExpiration::until(new \DateTimeImmutable('+1 hour')),
   139	        );
   140	        $unbannedBan->markUnbanned(
   141	            unbannedBy: BanUnbannedBy::by(UserId::generate()),
   142	            unbannedAt: BanUnbannedAt::at(new \DateTimeImmutable()),
   143	            unbannedReason: BanUnbannedReason::of('Снят'),
   144	        );
   145	
   146	        $this->entityManager()->persist($expiredUser);
   147	        $this->entityManager()->persist($unbannedUser);
   148	        $this->entityManager()->persist($activeTemporaryUser);
   149	        $this->entityManager()->persist($expiredBan);
   150	        $this->entityManager()->persist($unbannedBan);
   151	        $this->entityManager()->persist($activeTemporaryBan);
   152	        $this->entityManager()->run();
   153	        $this->cleanOrmHeap();
   154	
   155	        self::assertNull($this->userBanRepository()->findActiveByUserId($expiredUser->id, new \DateTimeImmutable()));
   156	        self::assertNull($this->userBanRepository()->findActiveByUserId($unbannedUser->id, new \DateTimeImmutable()));
   157	        self::assertInstanceOf(
   158	            UserBan::class,
   159	            $this->userBanRepository()->findActiveByUserId($activeTemporaryUser->id, new \DateTimeImmutable()),
   160	        );
   161	    }
   162	
   163	    public function testStoresReservedNicknameWithHolder(): void
   164	    {
   165	        $user = $this->createUser();
   166	        $reservedNickname = ReservedNickname::create(UserNickname::fromString('reserved'));
   167	        $reservedNickname->assignTo($user->id);
   168	
   169	        $this->entityManager()->persist($user);
   170	        $this->entityManager()->persist($reservedNickname);
   171	        $this->entityManager()->run();
   172	        $this->cleanOrmHeap();
   173	
   174	        $restoredReservedNickname = $this->reservedNicknameRepository()
   175	            ->findByNickname(UserNickname::fromString('reserved'));
   176	
   177	        self::assertInstanceOf(ReservedNickname::class, $restoredReservedNickname);
   178	        self::assertTrue($restoredReservedNickname->isAssignedTo($user->id));
   179	        self::assertTrue($this->reservedNicknameRepository()->isReserved(UserNickname::fromString('reserved')));
   180	    }
   181	
   182	    public function testDuplicateEmailFails(): void
   183	    {
   184	        $this->entityManager()->persist($this->createUser());
   185	        $this->entityManager()->persist($this->createUser(email: 'TEST@example.com', nickname: 'other.nick'));
   186	
   187	        $this->expectException(\Throwable::class);
   188	
   189	        $this->entityManager()->run();
   190	    }
   191	
   192	    public function testDuplicateNicknameFails(): void
   193	    {
   194	        $this->entityManager()->persist($this->createUser());
   195	        $this->entityManager()->persist($this->createUser(email: 'other@example.com', nickname: 'YOGA.TEST'));
   196	
   197	        $this->expectException(\Throwable::class);
   198	
   199	        $this->entityManager()->run();
   200	    }
   201	
   202	    public function testDuplicateReservedNicknameFails(): void
   203	    {
   204	        $this->entityManager()->persist(ReservedNickname::create(UserNickname::fromString('reserved')));
   205	        $this->entityManager()->persist(ReservedNickname::create(UserNickname::fromString('RESERVED')));
   206	
   207	        $this->expectException(\Throwable::class);
   208	
   209	        $this->entityManager()->run();
   210	    }
   211	
   212	    private function createUser(
   213	        string $email = 'test@example.com',
   214	        string $nickname = 'yoga.test',
   215	    ): User {
   216	        return User::create(
   217	            name: UserName::fromString('Йога Тест'),
   218	            email: Email::fromString($email),
   219	            nickname: UserNickname::fromString($nickname),
   220	            locale: Locale::Ru,

exec
/bin/zsh -lc "nl -ba tests/Feature/Modules/System/Http/ApiErrorHttpTest.php | sed -n '1,240p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Tests\Feature\Modules\System\Http;
     6	
     7	use App\Shared\Infrastructure\Framework\Bootloader\AppBootloader;
     8	use App\Shared\Infrastructure\Framework\Bootloader\RoutesBootloader;
     9	use App\Shared\Infrastructure\Framework\Middleware\LocaleMiddleware;
    10	use Spiral\Filters\ErrorsRendererInterface;
    11	use Spiral\Http\Middleware\ErrorHandlerMiddleware;
    12	use Tests\TestCase;
    13	use GianTiaga\SpiralApiErrors\Filter\ApiValidationErrorsRenderer;
    14	use GianTiaga\SpiralApiErrors\Interceptor\ApiExceptionInterceptor;
    15	use GianTiaga\SpiralApiErrors\Middleware\RouteNotFoundMiddleware;
    16	use GianTiaga\SpiralOpenApi\Response\Enum\ContentType;
    17	use GianTiaga\SpiralOpenApi\Response\Enum\HttpHeader;
    18	use GianTiaga\SpiralOpenApi\Response\Interceptor\HttpResponseInterceptor;
    19	
    20	final class ApiErrorHttpTest extends TestCase
    21	{
    22	    public function testApiRouteWithDomainExceptionReturnsJsonError(): void
    23	    {
    24	        $response = $this->fakeHttp()
    25	            ->withHeader('Accept-Language', 'ru')
    26	            ->getJson('/test/api/errors/domain');
    27	
    28	        $response->assertNotFound();
    29	        $response->assertHasHeader(HttpHeader::ContentType->value, ContentType::Json->value);
    30	        $response->assertBodySame('{"message":"Тестовый ресурс не найден.","code":404}');
    31	    }
    32	
    33	    public function testApiRouteWithInvalidDomainValueExceptionReturnsInternalServerError(): void
    34	    {
    35	        $response = $this->fakeHttp()
    36	            ->withHeader('Accept-Language', 'ru')
    37	            ->getJson('/test/api/errors/invalid-domain-value');
    38	
    39	        $response->assertStatus(500);
    40	        $response->assertHasHeader(HttpHeader::ContentType->value, ContentType::Json->value);
    41	        $response->assertBodySame('{"message":"Внутренняя ошибка сервера","code":500}');
    42	    }
    43	
    44	    public function testApiFilterValidationReturnsEnglishJsonErrorWithErrors(): void
    45	    {
    46	        $response = $this->fakeHttp()
    47	            ->withHeader('Accept-Language', 'en')
    48	            ->postJson('/test/api/errors/filter', [
    49	                'age' => 'abc',
    50	            ]);
    51	
    52	        $response->assertUnprocessable();
    53	        $response->assertHasHeader(HttpHeader::ContentType->value, ContentType::Json->value);
    54	        $response->assertBodySame(
    55	            '{"message":"Validation error","code":422,"errors":[{"field":"age","message":"Возраст должен быть числом"}]}',
    56	        );
    57	    }
    58	
    59	    public function testApiFilterValidationReturnsRussianJsonErrorWithErrors(): void
    60	    {
    61	        $response = $this->fakeHttp()
    62	            ->withHeader('Accept-Language', 'ru')
    63	            ->postJson('/test/api/errors/filter', [
    64	                'age' => 'abc',
    65	            ]);
    66	
    67	        $response->assertUnprocessable();
    68	        $response->assertHasHeader(HttpHeader::ContentType->value, ContentType::Json->value);
    69	        $response->assertBodySame(
    70	            '{"message":"Ошибка валидации","code":422,"errors":[{"field":"age","message":"Возраст должен быть числом"}]}',
    71	        );
    72	    }
    73	
    74	    public function testValidationHandlerMiddlewareUsesApiValidationErrorsRenderer(): void
    75	    {
    76	        self::assertInstanceOf(
    77	            ApiValidationErrorsRenderer::class,
    78	            $this->getContainer()->get(ErrorsRendererInterface::class),
    79	        );
    80	    }
    81	
    82	    public function testApiExceptionInterceptorIsRegisteredAfterHttpResponseInterceptor(): void
    83	    {
    84	        $interceptors = (new \ReflectionClass(AppBootloader::class))
    85	            ->getReflectionConstant('INTERCEPTORS')
    86	            ?->getValue();
    87	
    88	        self::assertIsArray($interceptors);
    89	
    90	        $httpResponseInterceptorPosition = \array_search(HttpResponseInterceptor::class, $interceptors, true);
    91	        $apiExceptionInterceptorPosition = \array_search(ApiExceptionInterceptor::class, $interceptors, true);
    92	
    93	        self::assertIsInt($httpResponseInterceptorPosition);
    94	        self::assertIsInt($apiExceptionInterceptorPosition);
    95	        self::assertGreaterThan($httpResponseInterceptorPosition, $apiExceptionInterceptorPosition);
    96	    }
    97	
    98	    public function testUnknownRouteReturnsEnglishJsonRouteNotFoundError(): void
    99	    {
   100	        $response = $this->fakeHttp()
   101	            ->withHeader('Accept-Language', 'en')
   102	            ->getJson('/test/api/errors/missing');
   103	
   104	        $response->assertNotFound();
   105	        $response->assertHasHeader(HttpHeader::ContentType->value, ContentType::Json->value);
   106	        $response->assertBodySame('{"message":"Route not found.","code":404}');
   107	    }
   108	
   109	    public function testUnknownRouteReturnsRussianJsonRouteNotFoundError(): void
   110	    {
   111	        $response = $this->fakeHttp()
   112	            ->withHeader('Accept-Language', 'ru')
   113	            ->getJson('/test/api/errors/missing');
   114	
   115	        $response->assertNotFound();
   116	        $response->assertHasHeader(HttpHeader::ContentType->value, ContentType::Json->value);
   117	        $response->assertBodySame('{"message":"Маршрут не найден.","code":404}');
   118	    }
   119	
   120	    public function testWrongMethodReturnsEnglishJsonRouteNotFoundError(): void
   121	    {
   122	        $response = $this->fakeHttp()
   123	            ->withHeader('Accept-Language', 'en')
   124	            ->postJson('/test/api/errors/domain');
   125	
   126	        $response->assertNotFound();
   127	        $response->assertHasHeader(HttpHeader::ContentType->value, ContentType::Json->value);
   128	        $response->assertBodySame('{"message":"Route not found.","code":404}');
   129	    }
   130	
   131	    public function testLocaleMiddlewareIsRegisteredBetweenErrorHandlerAndRouteNotFoundMiddleware(): void
   132	    {
   133	        $middleware = (new \ReflectionClass(RoutesBootloader::class))
   134	            ->getMethod('globalMiddleware')
   135	            ->invoke(new RoutesBootloader());
   136	
   137	        self::assertIsArray($middleware);
   138	        self::assertSame(ErrorHandlerMiddleware::class, $middleware[0] ?? null);
   139	        self::assertSame(LocaleMiddleware::class, $middleware[1] ?? null);
   140	        self::assertSame(RouteNotFoundMiddleware::class, $middleware[2] ?? null);
   141	    }
   142	}

exec
/bin/zsh -lc "nl -ba tests/DatabaseTestCase.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Tests;
     6	
     7	use Cycle\Database\DatabaseInterface;
     8	use Cycle\ORM\EntityManagerInterface;
     9	use Cycle\ORM\ORMInterface;
    10	use Spiral\Storage\StorageInterface;
    11	use Tests\Storage\FakeStorage;
    12	
    13	/**
    14	 * Базовый класс для обычных DB-тестов.
    15	 *
    16	 * В `setUp()` открывает транзакцию на `DatabaseInterface`, в `tearDown()`
    17	 * откатывает её, поэтому данные не утекают между тестами без ручной очистки
    18	 * таблиц. ORM heap чистится до и после теста, а storage по умолчанию заменён
    19	 * fake-реализацией, чтобы DB-тест не ходил в MinIO.
    20	 *
    21	 * Тесты, которым нужен реальный commit, relay, queue status, console flow или
    22	 * проверка транзакционного поведения, наследуются от
    23	 * `Tests\NonTransactionalDatabaseTestCase`.
    24	 */
    25	abstract class DatabaseTestCase extends TestCase
    26	{
    27	    private DatabaseInterface|null $transactionalDatabase = null;
    28	
    29	    #[\Override]
    30	    protected function setUp(): void
    31	    {
    32	        parent::setUp();
    33	
    34	        $this->cleanOrmHeap();
    35	
    36	        if ($this->useFakeStorage()) {
    37	            $this->getContainer()->bindSingleton(
    38	                StorageInterface::class,
    39	                new FakeStorage(TestRuntime::storageDirectory($this->rootDirectory())),
    40	            );
    41	        }
    42	
    43	        if ($this->useDatabaseTransaction()) {
    44	            $database = $this->getContainer()->get(DatabaseInterface::class);
    45	            $database->begin();
    46	            $this->transactionalDatabase = $database;
    47	        }
    48	    }
    49	
    50	    #[\Override]
    51	    protected function tearDown(): void
    52	    {
    53	        try {
    54	            if ($this->transactionalDatabase !== null) {
    55	                $this->transactionalDatabase->rollback();
    56	                $this->transactionalDatabase = null;
    57	            }
    58	
    59	            $this->cleanOrmHeap();
    60	        } finally {
    61	            parent::tearDown();
    62	        }
    63	    }
    64	
    65	    /**
    66	     * Обычный DB-тест оборачивается в транзакцию с rollback.
    67	     */
    68	    protected function useDatabaseTransaction(): bool
    69	    {
    70	        return true;
    71	    }
    72	
    73	    /**
    74	     * Обычный DB-тест получает fake storage вместо MinIO.
    75	     */
    76	    protected function useFakeStorage(): bool
    77	    {
    78	        return true;
    79	    }
    80	
    81	    protected function cleanOrmHeap(): void
    82	    {
    83	        $container = $this->getContainer();
    84	
    85	        if ($container->has(EntityManagerInterface::class)) {
    86	            $container->get(EntityManagerInterface::class)->clean();
    87	        }
    88	
    89	        if ($container->has(ORMInterface::class)) {
    90	            $container->get(ORMInterface::class)->getHeap()->clean();
    91	        }
    92	    }
    93	}

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Router/src/Annotation/Route.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
nl: vendor/spiral/framework/src/Router/src/Annotation/Route.php: No such file or directory

exec
/bin/zsh -lc "rg -n \"class Route|readonly class Route|middleware\" vendor/spiral/framework/src/Router vendor/spiral/framework/src/AnnotatedRoutes app/src tests -g '*.php'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
vendor/spiral/framework/src/AnnotatedRoutes/src/RouteLocatorListener.php:14:final class RouteLocatorListener implements TokenizationListenerInterface
vendor/spiral/framework/src/AnnotatedRoutes/src/RouteLocatorListener.php:53:                'middleware' => $route->middleware,
vendor/spiral/framework/src/AnnotatedRoutes/src/RouteLocatorListener.php:74:                ->addRoute($name, $route->withVerbs(...$schema['verbs'])->withMiddleware(...$schema['middleware']));
vendor/spiral/framework/src/Router/src/Event/RouteMatched.php:10:final class RouteMatched
vendor/spiral/framework/src/AnnotatedRoutes/src/Annotation/Route.php:22: *     @Attribute("middleware", type="array"),
vendor/spiral/framework/src/AnnotatedRoutes/src/Annotation/Route.php:27:final class Route
vendor/spiral/framework/src/AnnotatedRoutes/src/Annotation/Route.php:35:     * @param array $middleware Route specific middleware set, if any
vendor/spiral/framework/src/AnnotatedRoutes/src/Annotation/Route.php:47:        public readonly array $middleware = [],
vendor/spiral/framework/src/Router/src/Event/RouteNotFound.php:9:final class RouteNotFound
tests/Feature/Modules/System/Http/ApiErrorHttpTest.php:133:        $middleware = (new \ReflectionClass(RoutesBootloader::class))
tests/Feature/Modules/System/Http/ApiErrorHttpTest.php:137:        self::assertIsArray($middleware);
tests/Feature/Modules/System/Http/ApiErrorHttpTest.php:138:        self::assertSame(ErrorHandlerMiddleware::class, $middleware[0] ?? null);
tests/Feature/Modules/System/Http/ApiErrorHttpTest.php:139:        self::assertSame(LocaleMiddleware::class, $middleware[1] ?? null);
tests/Feature/Modules/System/Http/ApiErrorHttpTest.php:140:        self::assertSame(RouteNotFoundMiddleware::class, $middleware[2] ?? null);
vendor/spiral/framework/src/Router/src/Exception/RouteNotFoundException.php:9:class RouteNotFoundException extends UndefinedRouteException
vendor/spiral/framework/src/Router/src/Loader/Configurator/ImportConfigurator.php:74:    public function middleware(MiddlewareInterface|string|array $middleware): self
vendor/spiral/framework/src/Router/src/Loader/Configurator/ImportConfigurator.php:77:            $configurator->middleware($middleware);
vendor/spiral/framework/src/Router/src/Exception/RouterException.php:7:class RouterException extends \RuntimeException {}
vendor/spiral/framework/src/Router/src/Loader/Configurator/RouteConfigurator.php:24: * @property-read array<MiddlewareInterface|non-empty-string> $middleware
vendor/spiral/framework/src/Router/src/Loader/Configurator/RouteConfigurator.php:29:final class RouteConfigurator
vendor/spiral/framework/src/Router/src/Loader/Configurator/RouteConfigurator.php:36:    private ?array $middleware = null;
vendor/spiral/framework/src/Router/src/Loader/Configurator/RouteConfigurator.php:121:    public function middleware(MiddlewareInterface|string|array $middleware): self
vendor/spiral/framework/src/Router/src/Loader/Configurator/RouteConfigurator.php:123:        if (!\is_array($middleware)) {
vendor/spiral/framework/src/Router/src/Loader/Configurator/RouteConfigurator.php:124:            $middleware = [$middleware];
vendor/spiral/framework/src/Router/src/Loader/Configurator/RouteConfigurator.php:127:        $this->middleware = $middleware;
vendor/spiral/framework/src/Router/src/Loader/Configurator/RouteConfigurator.php:154:            'middleware' => $this->middleware,
vendor/spiral/framework/src/Router/src/Exception/RouteException.php:7:class RouteException extends RouterException {}
vendor/spiral/framework/src/Router/src/Router.php:30:final class Router implements RouterInterface
vendor/spiral/framework/src/Router/src/Router.php:155:            if ($configurator->middleware !== null) {
vendor/spiral/framework/src/Router/src/Router.php:156:                $route = $route->withMiddleware(...$configurator->middleware);
vendor/spiral/framework/src/Router/src/RouteGroup.php:20:final class RouteGroup
vendor/spiral/framework/src/Router/src/RouteGroup.php:29:    private array $middleware = [];
vendor/spiral/framework/src/Router/src/RouteGroup.php:78:     * @param MiddlewareType $middleware
vendor/spiral/framework/src/Router/src/RouteGroup.php:80:    public function addMiddleware(MiddlewareInterface|Autowire|string $middleware): self
vendor/spiral/framework/src/Router/src/RouteGroup.php:82:        $this->middleware[] = $middleware;
vendor/spiral/framework/src/Router/src/RouteGroup.php:116:                    ->withMiddleware(...$this->middleware),
vendor/spiral/framework/src/Router/src/RouteCollection.php:12:class RouteCollection implements \IteratorAggregate, \Countable
vendor/spiral/framework/src/Router/src/Route.php:36:final class Route extends AbstractRoute implements ContainerizedInterface
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:26:    protected array $middleware = [];
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:29:     * Associated middleware with route. New instance of route will be returned.
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:37:     * @param MiddlewareType|array{0:MiddlewareType[]} ...$middleware
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:42:    public function withMiddleware(...$middleware): RouteInterface
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:47:        if (\count($middleware) === 1 && \is_array($middleware[0])) {
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:48:            $middleware = $middleware[0];
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:51:        /** @var MiddlewareType[] $middleware */
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:52:        foreach ($middleware as $item) {
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:53:            $route->middleware[] = $item;
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:67:        $route->middleware = [$pipeline];
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:86:                ->createWithMiddleware($this->middleware);
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:103:            return $pipeline->withMiddleware(...$this->middleware);
vendor/spiral/framework/src/Router/src/PipelineFactory.php:26:    public function createWithMiddleware(array $middleware): Pipeline
vendor/spiral/framework/src/Router/src/PipelineFactory.php:28:        if (\count($middleware) === 1 && $middleware[0] instanceof Pipeline) {
vendor/spiral/framework/src/Router/src/PipelineFactory.php:29:            return $middleware[0];
vendor/spiral/framework/src/Router/src/PipelineFactory.php:35:        foreach ($middleware as $item) {
vendor/spiral/framework/src/Router/src/PipelineFactory.php:50:                throw new RouteException(\sprintf('Invalid middleware `%s`', $name));
tests/Unit/Shared/Infrastructure/Framework/Middleware/LocaleMiddlewareTest.php:25:        $middleware = new LocaleMiddleware(
tests/Unit/Shared/Infrastructure/Framework/Middleware/LocaleMiddlewareTest.php:31:        $middleware->process(
app/src/Shared/Infrastructure/Framework/Kernel.php:170:            // Группы маршрутов и middleware
app/src/Shared/Infrastructure/Framework/Bootloader/RoutesBootloader.php:21: * Настраивает маршруты и middleware приложения.
app/src/Shared/Infrastructure/Framework/Bootloader/RoutesBootloader.php:25:final class RoutesBootloader extends BaseRoutesBootloader
app/src/Shared/Infrastructure/Framework/Bootloader/RoutesBootloader.php:45:    protected function middlewareGroups(): array

exec
/bin/zsh -lc "rg -n \"class AnnotatedRoutes|middleware\" vendor/spiral/framework/src/AnnotatedRoutes vendor/spiral/framework/src/Router -g '*.php' | sed -n '1,240p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
vendor/spiral/framework/src/AnnotatedRoutes/src/RouteLocatorListener.php:53:                'middleware' => $route->middleware,
vendor/spiral/framework/src/AnnotatedRoutes/src/RouteLocatorListener.php:74:                ->addRoute($name, $route->withVerbs(...$schema['verbs'])->withMiddleware(...$schema['middleware']));
vendor/spiral/framework/src/AnnotatedRoutes/src/Annotation/Route.php:22: *     @Attribute("middleware", type="array"),
vendor/spiral/framework/src/AnnotatedRoutes/src/Annotation/Route.php:35:     * @param array $middleware Route specific middleware set, if any
vendor/spiral/framework/src/AnnotatedRoutes/src/Annotation/Route.php:47:        public readonly array $middleware = [],
vendor/spiral/framework/src/AnnotatedRoutes/src/Bootloader/AnnotatedRoutesBootloader.php:19:final class AnnotatedRoutesBootloader extends Bootloader
vendor/spiral/framework/src/Router/src/RouteGroup.php:29:    private array $middleware = [];
vendor/spiral/framework/src/Router/src/RouteGroup.php:78:     * @param MiddlewareType $middleware
vendor/spiral/framework/src/Router/src/RouteGroup.php:80:    public function addMiddleware(MiddlewareInterface|Autowire|string $middleware): self
vendor/spiral/framework/src/Router/src/RouteGroup.php:82:        $this->middleware[] = $middleware;
vendor/spiral/framework/src/Router/src/RouteGroup.php:116:                    ->withMiddleware(...$this->middleware),
vendor/spiral/framework/src/Router/src/Router.php:155:            if ($configurator->middleware !== null) {
vendor/spiral/framework/src/Router/src/Router.php:156:                $route = $route->withMiddleware(...$configurator->middleware);
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:26:    protected array $middleware = [];
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:29:     * Associated middleware with route. New instance of route will be returned.
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:37:     * @param MiddlewareType|array{0:MiddlewareType[]} ...$middleware
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:42:    public function withMiddleware(...$middleware): RouteInterface
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:47:        if (\count($middleware) === 1 && \is_array($middleware[0])) {
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:48:            $middleware = $middleware[0];
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:51:        /** @var MiddlewareType[] $middleware */
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:52:        foreach ($middleware as $item) {
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:53:            $route->middleware[] = $item;
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:67:        $route->middleware = [$pipeline];
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:86:                ->createWithMiddleware($this->middleware);
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:103:            return $pipeline->withMiddleware(...$this->middleware);
vendor/spiral/framework/src/Router/src/Loader/Configurator/ImportConfigurator.php:74:    public function middleware(MiddlewareInterface|string|array $middleware): self
vendor/spiral/framework/src/Router/src/Loader/Configurator/ImportConfigurator.php:77:            $configurator->middleware($middleware);
vendor/spiral/framework/src/Router/src/PipelineFactory.php:26:    public function createWithMiddleware(array $middleware): Pipeline
vendor/spiral/framework/src/Router/src/PipelineFactory.php:28:        if (\count($middleware) === 1 && $middleware[0] instanceof Pipeline) {
vendor/spiral/framework/src/Router/src/PipelineFactory.php:29:            return $middleware[0];
vendor/spiral/framework/src/Router/src/PipelineFactory.php:35:        foreach ($middleware as $item) {
vendor/spiral/framework/src/Router/src/PipelineFactory.php:50:                throw new RouteException(\sprintf('Invalid middleware `%s`', $name));
vendor/spiral/framework/src/Router/src/Loader/Configurator/RouteConfigurator.php:24: * @property-read array<MiddlewareInterface|non-empty-string> $middleware
vendor/spiral/framework/src/Router/src/Loader/Configurator/RouteConfigurator.php:36:    private ?array $middleware = null;
vendor/spiral/framework/src/Router/src/Loader/Configurator/RouteConfigurator.php:121:    public function middleware(MiddlewareInterface|string|array $middleware): self
vendor/spiral/framework/src/Router/src/Loader/Configurator/RouteConfigurator.php:123:        if (!\is_array($middleware)) {
vendor/spiral/framework/src/Router/src/Loader/Configurator/RouteConfigurator.php:124:            $middleware = [$middleware];
vendor/spiral/framework/src/Router/src/Loader/Configurator/RouteConfigurator.php:127:        $this->middleware = $middleware;
vendor/spiral/framework/src/Router/src/Loader/Configurator/RouteConfigurator.php:154:            'middleware' => $this->middleware,

exec
/bin/zsh -lc "rg -n \"new Autowire|Autowire\\(\" app/src tests vendor/spiral/framework/src -g '*.php' | sed -n '1,200p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
tests/Kernel/Shared/Infrastructure/Configuration/SimpleConfigBindingTest.php:101:            handler: new Autowire(CacheHandler::class),
vendor/spiral/framework/src/Router/src/Target/AbstractTarget.php:100:                    $container->has(HandlerInterface::class) => new AutowireHandler($container),
tests/Unit/Shared/Infrastructure/Configuration/SimpleConfigMapperTest.php:94:            'handler' => new Autowire(CacheHandler::class),
vendor/spiral/framework/src/Session/src/Config/SessionConfig.php:55:            return new Autowire($this->config['handler']);
vendor/spiral/framework/src/Session/src/Config/SessionConfig.php:60:        return new Autowire($handler['class'], $handler['options']);
vendor/spiral/framework/src/Bridge/Stempler/src/Config/StemplerConfig.php:82:            return new Autowire($item);
vendor/spiral/framework/src/Bridge/Monolog/src/Config/MonologConfig.php:88:            return new Autowire($definition);
vendor/spiral/framework/src/Bridge/Monolog/src/Config/MonologConfig.php:92:            return new Autowire($definition['class'], $definition['options'] ?? []);
vendor/spiral/framework/src/Core/src/Container/Autowire.php:59:        throw new AutowireException('Invalid autowire definition.');
vendor/spiral/framework/src/Core/src/Internal/Actor.php:155:            Config\Autowire::class => $this->resolveAutowire($binding, $alias, $context, $arguments, $tracer),
vendor/spiral/framework/src/Core/src/Internal/Actor.php:341:    private function resolveAutowire(
vendor/spiral/framework/src/Core/src/Internal/Config/StateBinder.php:155:            $resolver instanceof Autowire => new \Spiral\Core\Config\Autowire($resolver, $singleton),
vendor/spiral/framework/src/Framework/helpers.php:20:        return new Autowire($alias, $parameters);
vendor/spiral/framework/src/Views/src/Config/ViewsConfig.php:86:        return new Autowire($item);
vendor/spiral/framework/src/Framework/Bootloader/Http/SessionBootloader.php:70:                'handler' => new Autowire(

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/AnnotatedRoutes/src/Annotation/Route.php | sed -n '1,100p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Router/src/PipelineFactory.php | sed -n '1,90p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Router;
     6	
     7	use Psr\Container\ContainerExceptionInterface;
     8	use Psr\Container\ContainerInterface;
     9	use Psr\Http\Server\MiddlewareInterface;
    10	use Spiral\Core\Container\Autowire;
    11	use Spiral\Core\FactoryInterface;
    12	use Spiral\Http\Pipeline;
    13	use Spiral\Router\Exception\RouteException;
    14	
    15	final class PipelineFactory
    16	{
    17	    public function __construct(
    18	        private readonly ContainerInterface $container,
    19	        private readonly FactoryInterface $factory,
    20	    ) {}
    21	
    22	    /**
    23	     * @throws RouteException
    24	     * @throws ContainerExceptionInterface
    25	     */
    26	    public function createWithMiddleware(array $middleware): Pipeline
    27	    {
    28	        if (\count($middleware) === 1 && $middleware[0] instanceof Pipeline) {
    29	            return $middleware[0];
    30	        }
    31	
    32	        $pipeline = $this->factory->make(Pipeline::class);
    33	        \assert($pipeline instanceof Pipeline);
    34	
    35	        foreach ($middleware as $item) {
    36	            if ($item instanceof MiddlewareInterface) {
    37	                $pipeline->pushMiddleware($item);
    38	            } elseif (\is_string($item)) {
    39	                $item = $this->container->get($item);
    40	                \assert($item instanceof MiddlewareInterface);
    41	
    42	                $pipeline->pushMiddleware($item);
    43	            } elseif ($item instanceof Autowire) {
    44	                $item = $item->resolve($this->factory);
    45	                \assert($item instanceof MiddlewareInterface);
    46	
    47	                $pipeline->pushMiddleware($item);
    48	            } else {
    49	                $name = \get_debug_type($item);
    50	                throw new RouteException(\sprintf('Invalid middleware `%s`', $name));
    51	            }
    52	        }
    53	
    54	        return $pipeline;
    55	    }
    56	}

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Router\Annotation;
     6	
     7	use Doctrine\Common\Annotations\Annotation\Attribute;
     8	use Doctrine\Common\Annotations\Annotation\Attributes;
     9	use Doctrine\Common\Annotations\Annotation\Target;
    10	use Spiral\Attributes\NamedArgumentConstructor;
    11	
    12	/**
    13	 * @Annotation
    14	 * @NamedArgumentConstructor
    15	 * @Target({"METHOD"})
    16	 * @Attributes({
    17	 *     @Attribute("route", required=true, type="string"),
    18	 *     @Attribute("name", type="string"),
    19	 *     @Attribute("verbs", required=true, type="mixed"),
    20	 *     @Attribute("defaults", type="array"),
    21	 *     @Attribute("group", type="string"),
    22	 *     @Attribute("middleware", type="array"),
    23	 *     @Attribute("priority", type="int")
    24	 * })
    25	 */
    26	#[\Attribute(\Attribute::TARGET_METHOD), NamedArgumentConstructor]
    27	final class Route
    28	{
    29	    /**
    30	     * @deprecated Deprecated since v3.3.0.
    31	     */
    32	    public const DEFAULT_GROUP = 'web';
    33	
    34	    /**
    35	     * @param array $middleware Route specific middleware set, if any
    36	     * @psalm-param non-empty-string $route
    37	     * @psalm-param non-empty-string|null $name
    38	     * @psalm-param non-empty-string|array<string> $methods
    39	     * @psalm-param non-empty-string $group Route group, groups can be configured using MiddlewareRegistry
    40	     */
    41	    public function __construct(
    42	        public readonly string $route,
    43	        public readonly ?string $name = null,
    44	        public readonly array|string $methods = \Spiral\Router\Route::VERBS,
    45	        public readonly array $defaults = [],
    46	        public readonly ?string $group = null,
    47	        public readonly array $middleware = [],
    48	        public readonly int $priority = 0,
    49	    ) {}
    50	}

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Core/src/Container/Autowire.php | sed -n '1,120p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Core\Container;
     6	
     7	use Spiral\Core\Exception\Container\AutowireException;
     8	use Spiral\Core\Exception\Container\ContainerException;
     9	use Spiral\Core\FactoryInterface;
    10	
    11	/**
    12	 * Provides ability to delegate option to container.
    13	 *
    14	 * @template TObject of object
    15	 */
    16	final class Autowire
    17	{
    18	    /** @var null|TObject */
    19	    private ?object $target = null;
    20	
    21	    /**
    22	     * Autowire constructor.
    23	     *
    24	     * @param non-empty-string|class-string<TObject> $alias
    25	     */
    26	    public function __construct(
    27	        public readonly string $alias,
    28	        public readonly array $parameters = [],
    29	    ) {}
    30	
    31	    /**
    32	     * Init the autowire based on string or array definition.
    33	     *
    34	     * @throws AutowireException
    35	     */
    36	    public static function wire(mixed $definition): Autowire
    37	    {
    38	        if ($definition instanceof self) {
    39	            return $definition;
    40	        }
    41	
    42	        if (\is_string($definition)) {
    43	            return new self($definition);
    44	        }
    45	
    46	        if (\is_array($definition) && isset($definition['class'])) {
    47	            return new self(
    48	                $definition['class'],
    49	                $definition['options'] ?? $definition['params'] ?? [],
    50	            );
    51	        }
    52	
    53	        if (\is_object($definition)) {
    54	            $autowire = new self($definition::class, []);
    55	            $autowire->target = $definition;
    56	            return $autowire;
    57	        }
    58	
    59	        throw new AutowireException('Invalid autowire definition.');
    60	    }
    61	
    62	    /**
    63	     * @param array $parameters Context specific parameters (always prior to declared ones).
    64	     * @return TObject
    65	     *
    66	     * @throws AutowireException  No entry was found for this identifier.
    67	     * @throws ContainerException Error while retrieving the entry.
    68	     */
    69	    public function resolve(FactoryInterface $factory, array $parameters = []): object
    70	    {
    71	        return $this->target ?? $factory->make($this->alias, \array_merge($this->parameters, $parameters));
    72	    }
    73	
    74	    public static function __set_state(array $anArray): static
    75	    {
    76	        return new self($anArray['alias'], $anArray['parameters']);
    77	    }
    78	}

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/AnnotatedRoutes/src/RouteLocatorListener.php | sed -n '1,100p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Router;
     6	
     7	use Spiral\Attributes\ReaderInterface;
     8	use Spiral\Router\Annotation\Route;
     9	use Spiral\Router\Target\Action;
    10	use Spiral\Tokenizer\Attribute\TargetAttribute;
    11	use Spiral\Tokenizer\TokenizationListenerInterface;
    12	
    13	#[TargetAttribute(Route::class, useAnnotations: true)]
    14	final class RouteLocatorListener implements TokenizationListenerInterface
    15	{
    16	    /** @var array<array-key, array{\ReflectionMethod, Route}> */
    17	    private array $attributes = [];
    18	
    19	    public function __construct(
    20	        private readonly ReaderInterface $reader,
    21	        private readonly GroupRegistry $groups,
    22	    ) {}
    23	
    24	    public function listen(\ReflectionClass $class): void
    25	    {
    26	        foreach ($class->getMethods() as $method) {
    27	            $route = $this->reader->firstFunctionMetadata($method, Route::class);
    28	
    29	            if ($route === null) {
    30	                continue;
    31	            }
    32	
    33	            $this->attributes[] = [$method, $route];
    34	        }
    35	    }
    36	
    37	    public function finalize(): void
    38	    {
    39	        $defaultGroup = $this->groups->getDefaultGroup();
    40	
    41	        $routes = [];
    42	        foreach ($this->attributes as $classes) {
    43	            [$method, $route] = $classes;
    44	            $class = $method->getDeclaringClass();
    45	
    46	            $routes[$route->name ?? $this->generateName($route)] = [
    47	                'pattern' => $route->route,
    48	                'controller' => $class->getName(),
    49	                'action' => $method->getName(),
    50	                'group' => $route->group ?? $defaultGroup,
    51	                'verbs' => (array) $route->methods,
    52	                'defaults' => $route->defaults,
    53	                'middleware' => $route->middleware,
    54	                'priority' => $route->priority,
    55	            ];
    56	        }
    57	
    58	        \uasort($routes, static fn(array $route1, array $route2): int => $route1['priority'] <=> $route2['priority']);
    59	
    60	        $this->configureRoutes($routes);
    61	    }
    62	
    63	    private function configureRoutes(array $routes): void
    64	    {
    65	        foreach ($routes as $name => $schema) {
    66	            $route = new \Spiral\Router\Route(
    67	                $schema['pattern'],
    68	                new Action($schema['controller'], $schema['action']),
    69	                $schema['defaults'],
    70	            );
    71	
    72	            $this->groups
    73	                ->getGroup($schema['group'])
    74	                ->addRoute($name, $route->withVerbs(...$schema['verbs'])->withMiddleware(...$schema['middleware']));
    75	        }
    76	    }
    77	
    78	    /**
    79	     * Generates route name based on declared methods and route.
    80	     */
    81	    private function generateName(Route $route): string
    82	    {
    83	        $methods = \is_array($route->methods)
    84	            ? \implode(',', $route->methods)
    85	            : $route->methods;
    86	
    87	        return \mb_strtolower(\sprintf('%s:%s', $methods, $route->route));
    88	    }
    89	}

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Router/src/RouteGroup.php | sed -n '70,130p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
    70	    public function setCore(Autowire|CoreInterface|HandlerInterface|string $core): self
    71	    {
    72	        $this->core = $core;
    73	
    74	        return $this;
    75	    }
    76	
    77	    /**
    78	     * @param MiddlewareType $middleware
    79	     */
    80	    public function addMiddleware(MiddlewareInterface|Autowire|string $middleware): self
    81	    {
    82	        $this->middleware[] = $middleware;
    83	
    84	        return $this;
    85	    }
    86	
    87	    /**
    88	     * Push routes to router.
    89	     *
    90	     * @internal
    91	     */
    92	    public function register(RouterInterface $router, FactoryInterface $factory): void
    93	    {
    94	        foreach ($this->routes as $name => $route) {
    95	            if ($this->core !== null) {
    96	                if (!$this->core instanceof CoreInterface && !$this->core instanceof HandlerInterface) {
    97	                    $this->core = $factory->make($this->core);
    98	                }
    99	
   100	                $target = $route->getTarget();
   101	                if ($target instanceof AbstractTarget) {
   102	                    $route = $route->withTarget($target->withCore($this->core));
   103	                }
   104	            }
   105	
   106	            try {
   107	                $uriHandler = $route->getUriHandler();
   108	            } catch (\Throwable) {
   109	                $uriHandler = $factory->make(UriHandler::class);
   110	            }
   111	
   112	            $router->setRoute(
   113	                $name,
   114	                $route
   115	                    ->withUriHandler($uriHandler->withPrefix($this->prefix))
   116	                    ->withMiddleware(...$this->middleware),
   117	            );
   118	        }
   119	    }
   120	
   121	    /**
   122	     * Add a route to a route group.
   123	     *
   124	     * @param non-empty-string $name
   125	     *
   126	     * @psalm-assert Route $route
   127	     */
   128	    public function addRoute(string $name, RouteInterface $route): self
   129	    {
   130	        \assert($route instanceof Route);

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php | sed -n '35,110p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
    35	     * $route->withMiddleware([ProxyMiddleware::class, OtherMiddleware::class]);
    36	     *
    37	     * @param MiddlewareType|array{0:MiddlewareType[]} ...$middleware
    38	     * @return RouteInterface|$this
    39	     *
    40	     * @throws RouteException
    41	     */
    42	    public function withMiddleware(...$middleware): RouteInterface
    43	    {
    44	        $route = clone $this;
    45	
    46	        // array fallback
    47	        if (\count($middleware) === 1 && \is_array($middleware[0])) {
    48	            $middleware = $middleware[0];
    49	        }
    50	
    51	        /** @var MiddlewareType[] $middleware */
    52	        foreach ($middleware as $item) {
    53	            $route->middleware[] = $item;
    54	        }
    55	
    56	        if ($route->pipeline !== null) {
    57	            $route->pipeline = $route->makeLazyPipeline();
    58	        }
    59	
    60	        return $route;
    61	    }
    62	
    63	    public function withPipeline(Pipeline $pipeline): static
    64	    {
    65	        $route = clone $this;
    66	
    67	        $route->middleware = [$pipeline];
    68	        $route->pipeline = $pipeline;
    69	
    70	        return $route;
    71	    }
    72	
    73	    /**
    74	     * Get associated route pipeline.
    75	     *
    76	     * @throws RouteException
    77	     *
    78	     * @deprecated Will be removed in Spiral v4.0. Use {@see makeLazyPipeline()} instead.
    79	     */
    80	    protected function makePipeline(): Pipeline
    81	    {
    82	        \assert($this->container !== null);
    83	        try {
    84	            return $this->container
    85	                ->get(PipelineFactory::class)
    86	                ->createWithMiddleware($this->middleware);
    87	        } catch (ContainerExceptionInterface $e) {
    88	            throw new RouteException($e->getMessage(), $e->getCode(), $e);
    89	        }
    90	    }
    91	
    92	    /**
    93	     * Get associated route pipeline.
    94	     *
    95	     * @throws RouteException
    96	     */
    97	    protected function makeLazyPipeline(): LazyPipeline
    98	    {
    99	        \assert($this->container !== null);
   100	        try {
   101	            /** @var LazyPipeline $pipeline */
   102	            $pipeline = $this->container->get(LazyPipeline::class);
   103	            return $pipeline->withMiddleware(...$this->middleware);
   104	        } catch (ContainerExceptionInterface $e) {
   105	            throw new RouteException($e->getMessage(), $e->getCode(), $e);
   106	        }
   107	    }
   108	}

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Http/src/Pipeline.php | sed -n '1,160p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Http;
     6	
     7	use Psr\EventDispatcher\EventDispatcherInterface;
     8	use Psr\Http\Message\ResponseInterface as Response;
     9	use Psr\Http\Message\ServerRequestInterface as Request;
    10	use Psr\Http\Server\MiddlewareInterface;
    11	use Psr\Http\Server\RequestHandlerInterface;
    12	use Spiral\Core\Attribute\Proxy;
    13	use Spiral\Core\ContainerScope;
    14	use Spiral\Core\ScopeInterface;
    15	use Spiral\Http\Event\MiddlewareProcessing;
    16	use Spiral\Http\Exception\PipelineException;
    17	use Spiral\Http\Traits\MiddlewareTrait;
    18	use Spiral\Telemetry\NullTracer;
    19	use Spiral\Telemetry\SpanInterface;
    20	use Spiral\Telemetry\TracerInterface;
    21	
    22	/**
    23	 * Pipeline used to pass request and response thought the chain of middleware.
    24	 * @deprecated Will be removed in v4.0. Use {@see LazyPipeline} instead.
    25	 */
    26	final class Pipeline implements RequestHandlerInterface, MiddlewareInterface
    27	{
    28	    use MiddlewareTrait;
    29	
    30	    private int $position = 0;
    31	    private readonly TracerInterface $tracer;
    32	    private ?RequestHandlerInterface $handler = null;
    33	
    34	    public function __construct(
    35	        #[Proxy] ScopeInterface $scope,
    36	        private readonly ?EventDispatcherInterface $dispatcher = null,
    37	        ?TracerInterface $tracer = null,
    38	    ) {
    39	        $this->tracer = $tracer ?? new NullTracer($scope);
    40	    }
    41	
    42	    /**
    43	     * Configures pipeline with target endpoint.
    44	     *
    45	     * @throws PipelineException
    46	     */
    47	    public function withHandler(RequestHandlerInterface $handler): self
    48	    {
    49	        $pipeline = clone $this;
    50	        $pipeline->handler = $handler;
    51	        $pipeline->position = 0;
    52	
    53	        return $pipeline;
    54	    }
    55	
    56	    public function process(Request $request, RequestHandlerInterface $handler): Response
    57	    {
    58	        return $this->withHandler($handler)->handle($request);
    59	    }
    60	
    61	    public function handle(Request $request): Response
    62	    {
    63	        if ($this->handler === null) {
    64	            throw new PipelineException('Unable to run pipeline, no handler given.');
    65	        }
    66	
    67	        // todo: find a better solution in the Spiral v4.0
    68	        /** @var CurrentRequest|null $currentRequest */
    69	        $currentRequest = ContainerScope::getContainer()?->get(CurrentRequest::class);
    70	
    71	        $previousRequest = $currentRequest?->get();
    72	        $currentRequest?->set($request);
    73	        try {
    74	            $position = $this->position++;
    75	            if (!isset($this->middleware[$position])) {
    76	                return $this->handler->handle($request);
    77	            }
    78	
    79	            $middleware = $this->middleware[$position];
    80	            $this->dispatcher?->dispatch(new MiddlewareProcessing($request, $middleware));
    81	
    82	            $callback = function (SpanInterface $span) use ($request, $middleware): Response {
    83	                $response = $middleware->process($request, $this);
    84	
    85	                $span
    86	                    ->setAttribute(
    87	                        'http.status_code',
    88	                        $response->getStatusCode(),
    89	                    )
    90	                    ->setAttribute(
    91	                        'http.response_content_length',
    92	                        $response->getHeaderLine('Content-Length') ?: $response->getBody()->getSize(),
    93	                    )
    94	                    ->setStatus($response->getStatusCode() < 500 ? 'OK' : 'ERROR');
    95	
    96	                return $response;
    97	            };
    98	
    99	            return $this->tracer->trace(
   100	                name: \sprintf('Middleware processing [%s]', $middleware::class),
   101	                callback: $callback,
   102	                attributes: [
   103	                    'http.middleware' => $middleware::class,
   104	                ],
   105	                scoped: true,
   106	            );
   107	        } finally {
   108	            if ($previousRequest !== null) {
   109	                $currentRequest?->set($previousRequest);
   110	            }
   111	        }
   112	    }
   113	}

exec
/bin/zsh -lc "rg -n \"execute|process\\(ServerRequestInterface|withMiddleware|pipeline\" vendor/spiral/framework/src/Router/src vendor/spiral/framework/src/Http/src -g '*.php' | sed -n '1,200p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
vendor/spiral/framework/src/Router/src/RouteGroup.php:116:                    ->withMiddleware(...$this->middleware),
vendor/spiral/framework/src/Router/src/CoreHandler.php:165:     * @param Response $response Initial pipeline response.
vendor/spiral/framework/src/Http/src/Http.php:28:    private readonly Pipeline|LazyPipeline $pipeline;
vendor/spiral/framework/src/Http/src/Http.php:32:        Pipeline|LazyPipeline $pipeline,
vendor/spiral/framework/src/Http/src/Http.php:38:        if ($pipeline instanceof Pipeline) {
vendor/spiral/framework/src/Http/src/Http.php:40:                $pipeline->pushMiddleware($this->container->get($middleware));
vendor/spiral/framework/src/Http/src/Http.php:43:            $pipeline = $pipeline->withAddedMiddleware(
vendor/spiral/framework/src/Http/src/Http.php:48:        $this->pipeline = $pipeline;
vendor/spiral/framework/src/Http/src/Http.php:58:        return $this->pipeline;
vendor/spiral/framework/src/Http/src/Http.php:84:            $response = $this->pipeline->withHandler($this->handler)->handle($request);
vendor/spiral/framework/src/Router/src/Route.php:94:        $route->pipeline = $route->makeLazyPipeline();
vendor/spiral/framework/src/Router/src/Route.php:121:        \assert($this->pipeline !== null);
vendor/spiral/framework/src/Router/src/Route.php:122:        return $this->pipeline->process(
vendor/spiral/framework/src/Router/src/Route.php:134:            'Unable to configure route pipeline without associated container.',
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:23:    protected Pipeline|LazyPipeline|null $pipeline = null;
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:32:     * $route->withMiddleware(new CacheMiddleware(100));
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:33:     * $route->withMiddleware(ProxyMiddleware::class);
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:34:     * $route->withMiddleware(ProxyMiddleware::class, OtherMiddleware::class);
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:35:     * $route->withMiddleware([ProxyMiddleware::class, OtherMiddleware::class]);
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:42:    public function withMiddleware(...$middleware): RouteInterface
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:56:        if ($route->pipeline !== null) {
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:57:            $route->pipeline = $route->makeLazyPipeline();
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:63:    public function withPipeline(Pipeline $pipeline): static
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:67:        $route->middleware = [$pipeline];
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:68:        $route->pipeline = $pipeline;
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:74:     * Get associated route pipeline.
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:93:     * Get associated route pipeline.
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:101:            /** @var LazyPipeline $pipeline */
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:102:            $pipeline = $this->container->get(LazyPipeline::class);
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:103:            return $pipeline->withMiddleware(...$this->middleware);
vendor/spiral/framework/src/Router/src/Target/AbstractTarget.php:30:    private HandlerInterface|CoreInterface|null $pipeline = null;
vendor/spiral/framework/src/Router/src/Target/AbstractTarget.php:60:        $target->pipeline = $core;
vendor/spiral/framework/src/Router/src/Target/AbstractTarget.php:72:        $target->pipeline = $handler;
vendor/spiral/framework/src/Router/src/Target/AbstractTarget.php:99:                    $this->pipeline === null => $this->pipeline,
vendor/spiral/framework/src/Router/src/PipelineFactory.php:32:        $pipeline = $this->factory->make(Pipeline::class);
vendor/spiral/framework/src/Router/src/PipelineFactory.php:33:        \assert($pipeline instanceof Pipeline);
vendor/spiral/framework/src/Router/src/PipelineFactory.php:37:                $pipeline->pushMiddleware($item);
vendor/spiral/framework/src/Router/src/PipelineFactory.php:42:                $pipeline->pushMiddleware($item);
vendor/spiral/framework/src/Router/src/PipelineFactory.php:47:                $pipeline->pushMiddleware($item);
vendor/spiral/framework/src/Router/src/PipelineFactory.php:54:        return $pipeline;
vendor/spiral/framework/src/Http/src/LazyPipeline.php:22: * This kind of pipeline creates middleware on the fly.
vendor/spiral/framework/src/Http/src/LazyPipeline.php:37:     * Trace span for the current pipeline run.
vendor/spiral/framework/src/Http/src/LazyPipeline.php:47:     * Add middleware to the pipeline.
vendor/spiral/framework/src/Http/src/LazyPipeline.php:53:        $pipeline = clone $this;
vendor/spiral/framework/src/Http/src/LazyPipeline.php:54:        $pipeline->middleware = \array_merge($pipeline->middleware, $middleware);
vendor/spiral/framework/src/Http/src/LazyPipeline.php:55:        return $pipeline;
vendor/spiral/framework/src/Http/src/LazyPipeline.php:59:     * Replace middleware in the pipeline.
vendor/spiral/framework/src/Http/src/LazyPipeline.php:63:    public function withMiddleware(MiddlewareInterface|Autowire|string ...$middleware): self
vendor/spiral/framework/src/Http/src/LazyPipeline.php:65:        $pipeline = clone $this;
vendor/spiral/framework/src/Http/src/LazyPipeline.php:66:        $pipeline->middleware = $middleware;
vendor/spiral/framework/src/Http/src/LazyPipeline.php:67:        return $pipeline;
vendor/spiral/framework/src/Http/src/LazyPipeline.php:71:     * Configures pipeline with target endpoint.
vendor/spiral/framework/src/Http/src/LazyPipeline.php:77:        $pipeline = clone $this;
vendor/spiral/framework/src/Http/src/LazyPipeline.php:78:        $pipeline->handler = $handler;
vendor/spiral/framework/src/Http/src/LazyPipeline.php:79:        return $pipeline;
vendor/spiral/framework/src/Http/src/LazyPipeline.php:89:        $this->handler === null and throw new PipelineException('Unable to run pipeline, no handler given.');
vendor/spiral/framework/src/Http/src/LazyPipeline.php:111:            // Init a tracing span when the pipeline starts
vendor/spiral/framework/src/Http/src/LazyPipeline.php:137:        $pipeline = clone $this;
vendor/spiral/framework/src/Http/src/LazyPipeline.php:138:        ++$pipeline->position;
vendor/spiral/framework/src/Http/src/LazyPipeline.php:139:        $pipeline->span = $span;
vendor/spiral/framework/src/Http/src/LazyPipeline.php:140:        return $pipeline;
vendor/spiral/framework/src/Router/src/Router.php:156:                $route = $route->withMiddleware(...$configurator->middleware);
vendor/spiral/framework/src/Http/src/Pipeline.php:43:     * Configures pipeline with target endpoint.
vendor/spiral/framework/src/Http/src/Pipeline.php:49:        $pipeline = clone $this;
vendor/spiral/framework/src/Http/src/Pipeline.php:50:        $pipeline->handler = $handler;
vendor/spiral/framework/src/Http/src/Pipeline.php:51:        $pipeline->position = 0;
vendor/spiral/framework/src/Http/src/Pipeline.php:53:        return $pipeline;
vendor/spiral/framework/src/Http/src/Pipeline.php:64:            throw new PipelineException('Unable to run pipeline, no handler given.');
vendor/spiral/framework/src/Http/src/Middleware/JsonPayloadMiddleware.php:26:    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
vendor/spiral/framework/src/Http/src/CallableHandler.php:62:     * @param Response $response Initial pipeline response.

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Encrypter/src/EncryptionInterface.php vendor/spiral/framework/src/Encrypter/src/Encrypter.php vendor/spiral/framework/src/Framework/Bootloader/Security/EncrypterBootloader.php | sed -n '1,260p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

exec
/bin/zsh -lc 'rg -n "openapi:generate|sourcePaths|apiNamespace|Modules" app/config app/src/Shared/Infrastructure/Framework/Bootloader/OpenApiBootloader.php app/src/Modules/System/Presentation/Console/OpenApiGenerateCommand.php' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/System/Presentation/Console/OpenApiGenerateCommand.php:5:namespace App\Modules\System\Presentation\Console;
app/src/Modules/System/Presentation/Console/OpenApiGenerateCommand.php:17:    name: 'openapi:generate',
app/src/Shared/Infrastructure/Framework/Bootloader/OpenApiBootloader.php:7:use App\Modules\System\Presentation\Console\OpenApiGenerateCommand;
app/src/Shared/Infrastructure/Framework/Bootloader/OpenApiBootloader.php:8:use App\Modules\System\Presentation\Console\OpenApiPublishAssetsCommand;
app/config/scaffolder.php:24:            'namespace' => 'Modules\\System\\Presentation\\Http\\Controller',
app/config/scaffolder.php:27:            'namespace' => 'Modules\\System\\Presentation\\Http\\Filter',
app/config/scaffolder.php:30:            'namespace' => 'Modules\\System\\Presentation\\Http\\Middleware',
app/config/scaffolder.php:33:            'namespace' => 'Modules\\System\\Presentation\\Console',
app/config/scaffolder.php:36:            'namespace' => 'Modules\\System\\Presentation\\Job',
app/config/openapi.php:8:    'sourcePath' => 'app/src/Modules/System/Presentation/Http',
app/config/openapi.php:9:    'apiNamespace' => 'App\\Modules\\System\\Presentation\\Http',
app/config/queue.php:5:use App\Modules\Media\Presentation\Job\ProcessMediaJob;
app/config/queue.php:6:use App\Modules\Outbox\Infrastructure\Queue\OutboxQueueSerializer;
app/config/queue.php:7:use App\Modules\Outbox\Infrastructure\Queue\OutboxQueueStatusInterceptor;
app/config/queue.php:8:use App\Modules\Outbox\Presentation\Job\OutboxDebugLogJob;
app/config/queue.php:130:            // 'ping' => \App\Modules\System\Presentation\Job\Ping::class
app/config/queue.php:143:            // \App\Modules\System\Presentation\Job\Ping::class => 'json',

exec
/bin/zsh -lc "rg -n \"interface EncryptionInterface|function getKey|class .*Encrypter\" vendor/spiral/framework/src app/src -g '*.php'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
vendor/spiral/framework/src/Session/src/Handler/CacheHandler.php:67:    private function getKey(string $id): string
vendor/spiral/framework/src/Encrypter/src/Exception/DecryptException.php:10:class DecryptException extends EncrypterException {}
vendor/spiral/framework/src/Stempler/src/Lexer/Grammar/Dynamic/DirectiveGrammar.php:114:    public function getKeyword(): string
vendor/spiral/framework/src/Encrypter/src/Exception/EncrypterException.php:7:class EncrypterException extends \RuntimeException {}
vendor/spiral/framework/src/Encrypter/src/Exception/EncryptException.php:10:class EncryptException extends EncrypterException {}
vendor/spiral/framework/src/Encrypter/src/EncryptionInterface.php:9:interface EncryptionInterface
vendor/spiral/framework/src/Encrypter/src/EncryptionInterface.php:21:    public function getKey(): string;
vendor/spiral/framework/src/Encrypter/src/Encrypter.php:21:final class Encrypter implements EncrypterInterface, InjectableInterface
vendor/spiral/framework/src/Encrypter/src/Encrypter.php:51:    public function getKey(): string
vendor/spiral/framework/src/Encrypter/src/EncrypterInterface.php:26:    public function getKey(): string;
vendor/spiral/framework/src/Encrypter/src/EncrypterFactory.php:20:final class EncrypterFactory implements InjectorInterface, EncryptionInterface
vendor/spiral/framework/src/Encrypter/src/EncrypterFactory.php:38:    public function getKey(): string
vendor/spiral/framework/src/Encrypter/src/Config/EncrypterConfig.php:12:final class EncrypterConfig extends InjectableConfig
vendor/spiral/framework/src/Encrypter/src/Config/EncrypterConfig.php:26:    public function getKey(): string
vendor/spiral/framework/src/Framework/Bootloader/Security/EncrypterBootloader.php:16:final class EncrypterBootloader extends Bootloader
vendor/spiral/framework/src/Framework/Bootloader/Security/EncrypterBootloader.php:19:        EncryptionInterface::class => EncrypterFactory::class,
vendor/spiral/framework/src/Framework/Bootloader/Security/EncrypterBootloader.php:22:        EncrypterInterface::class => Encrypter::class,
vendor/spiral/framework/src/Models/src/AbstractEntity.php:231:    protected function getKeys(): array
vendor/spiral/framework/src/Filters/src/Attribute/Input/AbstractInput.php:15:    protected function getKey(\ReflectionProperty $property): string

exec
/bin/zsh -lc "nl -ba app/config/openapi.php app/src/Shared/Infrastructure/Framework/Bootloader/OpenApiBootloader.php app/src/Modules/System/Presentation/Console/OpenApiGenerateCommand.php | sed -n '1,280p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

exec
/bin/zsh -lc "nl -ba app/src/Modules/System/Presentation/Console/OpenApiGenerateCommand.php | sed -n '1,140p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba app/config/openapi.php | sed -n '1,120p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	return [
     6	    'enabled' => (bool) \env('OPENAPI_ENABLED', true),
     7	    'swaggerEnabled' => (bool) \env('OPENAPI_SWAGGER_ENABLED', \env('APP_ENV') !== 'production'),
     8	    'sourcePath' => 'app/src/Modules/System/Presentation/Http',
     9	    'apiNamespace' => 'App\\Modules\\System\\Presentation\\Http',
    10	    'routePrefix' => '/api/v1',
    11	    'outputFile' => 'public/openapi/openapi.yml',
    12	    'title' => 'YogaLoka API',
    13	    'version' => '1.0.0',
    14	    'debug' => (bool) \env('OPENAPI_DEBUG', true),
    15	];

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\System\Presentation\Console;
     6	
     7	use App\Shared\Infrastructure\Configuration\OpenApi\OpenApiConfig;
     8	use App\Shared\Infrastructure\Framework\DirectoryAlias;
     9	use Spiral\Boot\DirectoriesInterface;
    10	use Spiral\Console\Attribute\AsCommand;
    11	use Spiral\Console\Command;
    12	use Symfony\Component\Console\Command\Command as SymfonyCommand;
    13	use GianTiaga\SpiralOpenApi\Exception\OpenApiException;
    14	use GianTiaga\SpiralOpenApi\OpenApiGenerator;
    15	
    16	#[AsCommand(
    17	    name: 'openapi:generate',
    18	    description: 'Сгенерировать OpenAPI YAML из типизированного HTTP-слоя',
    19	)]
    20	final class OpenApiGenerateCommand extends Command
    21	{
    22	    public function perform(
    23	        OpenApiConfig $openApiConfig,
    24	        DirectoriesInterface $directories,
    25	        OpenApiGenerator $openApiGenerator,
    26	    ): int {
    27	        if (!$openApiConfig->enabled) {
    28	            $this->warning('Генерация OpenAPI выключена в конфигурации.');
    29	
    30	            return SymfonyCommand::SUCCESS;
    31	        }
    32	
    33	        try {
    34	            $openApiGenerationResult = $openApiGenerator->generate(
    35	                config: $openApiConfig->toGeneratorConfig(projectRoot: $directories->get(name: DirectoryAlias::Root->value)),
    36	                debugWriter: fn(string $message): null => $this->writeDebug(message: $message),
    37	            );
    38	        } catch (OpenApiException $exception) {
    39	            $this->error('Ошибка генерации OpenAPI: ' . $exception->getMessage());
    40	
    41	            return SymfonyCommand::FAILURE;
    42	        }
    43	
    44	        $this->info(\strtr(
    45	            string: 'OpenAPI YAML записан: {file}. Операций: {operations}, schemas: {schemas}.',
    46	            from: [
    47	                '{file}' => $openApiGenerationResult->outputFile,
    48	                '{operations}' => (string) $openApiGenerationResult->operationCount,
    49	                '{schemas}' => (string) $openApiGenerationResult->schemaCount,
    50	            ],
    51	        ));
    52	
    53	        return SymfonyCommand::SUCCESS;
    54	    }
    55	
    56	    private function writeDebug(string $message): null
    57	    {
    58	        $this->comment('[openapi] ' . $message);
    59	
    60	        return null;
    61	    }
    62	}

exec
/bin/zsh -lc "nl -ba app/src/Shared/Infrastructure/Framework/Bootloader/OpenApiBootloader.php | sed -n '1,120p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Encrypter/src/EncryptionInterface.php vendor/spiral/framework/src/Encrypter/src/EncrypterFactory.php vendor/spiral/framework/src/Framework/Bootloader/Security/EncrypterBootloader.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Shared\Infrastructure\Framework\Bootloader;
     6	
     7	use App\Modules\System\Presentation\Console\OpenApiGenerateCommand;
     8	use App\Modules\System\Presentation\Console\OpenApiPublishAssetsCommand;
     9	use Spiral\Boot\Bootloader\Bootloader;
    10	use Spiral\Console\Bootloader\ConsoleBootloader;
    11	
    12	final class OpenApiBootloader extends Bootloader
    13	{
    14	    public function init(ConsoleBootloader $console): void
    15	    {
    16	        $console->addCommand(OpenApiGenerateCommand::class);
    17	        $console->addCommand(OpenApiPublishAssetsCommand::class);
    18	    }
    19	}

 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

codex
Нашёл явный пропуск: OpenAPI сейчас смотрит только в `System/Presentation/Http`, поэтому новые Auth-контроллеры не попадут в YAML простым запуском генератора. Это надо добавить в ревью как отдельный блокер плана.
exec
/bin/zsh -lc "nl -ba app/src/Shared/Infrastructure/Configuration/OpenApi/OpenApiConfig.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Shared\Infrastructure\Configuration\OpenApi;
     6	
     7	use App\Shared\Infrastructure\Configuration\TypedConfig;
     8	use GianTiaga\SpiralOpenApi\Config\OpenApiGeneratorConfig;
     9	use GianTiaga\SpiralOpenApi\Config\ResponseWrapperMapping;
    10	use GianTiaga\SpiralOpenApi\Response\CollectionResponse;
    11	use GianTiaga\SpiralOpenApi\Response\DataResponse;
    12	use GianTiaga\SpiralOpenApi\Response\ErrorResponse;
    13	use GianTiaga\SpiralOpenApi\Response\PaginationResponse;
    14	
    15	final readonly class OpenApiConfig implements TypedConfig
    16	{
    17	    public static function configName(): string
    18	    {
    19	        return 'openapi';
    20	    }
    21	
    22	    public function __construct(
    23	        public bool $enabled,
    24	        public bool $swaggerEnabled,
    25	        public string $sourcePath,
    26	        public string $apiNamespace,
    27	        public string $routePrefix,
    28	        public string $outputFile,
    29	        public string $title,
    30	        public string $version,
    31	        public bool $debug,
    32	    ) {}
    33	
    34	    public function sourcePath(string $projectRoot): string
    35	    {
    36	        return \sprintf(
    37	            '%s/%s',
    38	            \rtrim(string: $projectRoot, characters: \DIRECTORY_SEPARATOR),
    39	            \ltrim(string: $this->sourcePath, characters: \DIRECTORY_SEPARATOR),
    40	        );
    41	    }
    42	
    43	    public function outputFile(string $projectRoot): string
    44	    {
    45	        return \sprintf(
    46	            '%s/%s',
    47	            \rtrim(string: $projectRoot, characters: \DIRECTORY_SEPARATOR),
    48	            \ltrim(string: $this->outputFile, characters: \DIRECTORY_SEPARATOR),
    49	        );
    50	    }
    51	
    52	    public function toGeneratorConfig(string $projectRoot): OpenApiGeneratorConfig
    53	    {
    54	        return new OpenApiGeneratorConfig(
    55	            projectRoot: $projectRoot,
    56	            sourcePaths: [$this->sourcePath(projectRoot: $projectRoot)],
    57	            apiNamespace: $this->apiNamespace,
    58	            routePrefix: $this->routePrefix,
    59	            outputFile: $this->outputFile(projectRoot: $projectRoot),
    60	            title: $this->title,
    61	            version: $this->version,
    62	            responseWrapperMapping: new ResponseWrapperMapping(
    63	                dataResponseClass: DataResponse::class,
    64	                collectionResponseClass: CollectionResponse::class,
    65	                paginationResponseClass: PaginationResponse::class,
    66	                errorResponseClass: ErrorResponse::class,
    67	            ),
    68	            debug: $this->debug,
    69	        );
    70	    }
    71	}

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Encrypter/src/EncrypterFactory.php | sed -n '1,100p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Encrypter;
     6	
     7	use Defuse\Crypto\Exception\CryptoException;
     8	use Defuse\Crypto\Key;
     9	use Spiral\Core\Attribute\Singleton;
    10	use Spiral\Core\Container\InjectorInterface;
    11	use Spiral\Encrypter\Config\EncrypterConfig;
    12	use Spiral\Encrypter\Exception\EncrypterException;
    13	
    14	/**
    15	 * Only manages encrypter injections (factory).
    16	 *
    17	 * @implements InjectorInterface<EncrypterInterface>
    18	 */
    19	#[Singleton]
    20	final class EncrypterFactory implements InjectorInterface, EncryptionInterface
    21	{
    22	    public function __construct(
    23	        private readonly EncrypterConfig $config,
    24	    ) {}
    25	
    26	    /**
    27	     * @codeCoverageIgnore
    28	     */
    29	    public function generateKey(): string
    30	    {
    31	        try {
    32	            return Key::createNewRandomKey()->saveToAsciiSafeString();
    33	        } catch (CryptoException $e) {
    34	            throw new EncrypterException($e->getMessage(), $e->getCode(), $e);
    35	        }
    36	    }
    37	
    38	    public function getKey(): string
    39	    {
    40	        try {
    41	            Key::loadFromAsciiSafeString($this->config->getKey());
    42	        } catch (CryptoException $e) {
    43	            throw new EncrypterException($e->getMessage(), $e->getCode(), $e);
    44	        }
    45	
    46	        return $this->config->getKey();
    47	    }
    48	
    49	    public function getEncrypter(): EncrypterInterface
    50	    {
    51	        return new Encrypter($this->getKey());
    52	    }
    53	
    54	    public function createInjection(\ReflectionClass $class, ?string $context = null): EncrypterInterface
    55	    {
    56	        return $this->getEncrypter();
    57	    }
    58	}

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Encrypter/src/EncryptionInterface.php | sed -n '1,80p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Encrypter;
     6	
     7	use Spiral\Encrypter\Exception\EncrypterException;
     8	
     9	interface EncryptionInterface
    10	{
    11	    /**
    12	     * Generate new random encryption key (binary format).
    13	     *
    14	     * @throws EncrypterException
    15	     */
    16	    public function generateKey(): string;
    17	
    18	    /**
    19	     * @throws EncrypterException
    20	     */
    21	    public function getKey(): string;
    22	
    23	    public function getEncrypter(): EncrypterInterface;
    24	}

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Framework/Bootloader/Security/EncrypterBootloader.php | sed -n '1,80p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Bootloader\Security;
     6	
     7	use Spiral\Boot\Bootloader\Bootloader;
     8	use Spiral\Boot\EnvironmentInterface;
     9	use Spiral\Config\ConfiguratorInterface;
    10	use Spiral\Encrypter\Config\EncrypterConfig;
    11	use Spiral\Encrypter\Encrypter;
    12	use Spiral\Encrypter\EncrypterFactory;
    13	use Spiral\Encrypter\EncrypterInterface;
    14	use Spiral\Encrypter\EncryptionInterface;
    15	
    16	final class EncrypterBootloader extends Bootloader
    17	{
    18	    protected const SINGLETONS = [
    19	        EncryptionInterface::class => EncrypterFactory::class,
    20	    ];
    21	    protected const BINDINGS = [
    22	        EncrypterInterface::class => Encrypter::class,
    23	    ];
    24	
    25	    public function init(ConfiguratorInterface $config, EnvironmentInterface $env): void
    26	    {
    27	        $config->setDefaults(EncrypterConfig::CONFIG, ['key' => $env->get('ENCRYPTER_KEY')]);
    28	    }
    29	}

exec
/bin/zsh -lc "rg -n \"function addIndex|where|Index\" vendor/cycle/database/src/Schema vendor/cycle/migrations -g '*.php' | sed -n '1,200p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
vendor/cycle/migrations/src/Migrator.php:75:            if ($schema->hasIndex(['migration'])) {
vendor/cycle/migrations/src/Migrator.php:76:                $schema->dropIndex(['migration']);
vendor/cycle/migrations/src/Migrator.php:232:        return !(!$table->hasIndex(['migration', 'created_at']));
vendor/cycle/migrations/src/Migrator.php:242:            ->where(
vendor/cycle/migrations/src/Migrator.php:259:                ->where(
vendor/cycle/migrations/src/Migrator.php:289:                    ->where(['created_at' => null])
vendor/cycle/database/src/Schema/ComparatorInterface.php:33:     * Returns array where each value contain current and initial element state.
vendor/cycle/database/src/Schema/ComparatorInterface.php:39:     * @return AbstractIndex[]
vendor/cycle/database/src/Schema/ComparatorInterface.php:41:    public function addedIndexes(): array;
vendor/cycle/database/src/Schema/ComparatorInterface.php:44:     * @return AbstractIndex[]
vendor/cycle/database/src/Schema/ComparatorInterface.php:46:    public function droppedIndexes(): array;
vendor/cycle/database/src/Schema/ComparatorInterface.php:49:     * Returns array where each value contain current and initial element state.
vendor/cycle/database/src/Schema/ComparatorInterface.php:52:    public function alteredIndexes(): array;
vendor/cycle/database/src/Schema/ComparatorInterface.php:65:     * Returns array where each value contain current and initial element state.
vendor/cycle/migrations/src/TableBlueprint.php:78:     * $table->addIndex(['email'], ['unique' => true]);
vendor/cycle/migrations/src/TableBlueprint.php:80:    public function addIndex(array $columns, array $options = []): self
vendor/cycle/migrations/src/TableBlueprint.php:83:            new Operation\Index\Add($this->table, $columns, $options),
vendor/cycle/migrations/src/TableBlueprint.php:89:     * $table->alterIndex(['email'], ['unique' => false]);
vendor/cycle/migrations/src/TableBlueprint.php:91:    public function alterIndex(array $columns, array $options): self
vendor/cycle/migrations/src/TableBlueprint.php:94:            new Operation\Index\Alter($this->table, $columns, $options),
vendor/cycle/migrations/src/TableBlueprint.php:100:     * $table->dropIndex(['email']);
vendor/cycle/migrations/src/TableBlueprint.php:102:    public function dropIndex(array $columns): self
vendor/cycle/migrations/src/TableBlueprint.php:105:            new Operation\Index\Drop($this->table, $columns),
vendor/cycle/database/src/Schema/State.php:24:    /** @var AbstractIndex[] */
vendor/cycle/database/src/Schema/State.php:70:     * @return AbstractIndex[]
vendor/cycle/database/src/Schema/State.php:72:    public function getIndexes(): array
vendor/cycle/database/src/Schema/State.php:119:    public function hasIndex(array $columns = []): bool
vendor/cycle/database/src/Schema/State.php:121:        return $this->findIndex($columns) !== null;
vendor/cycle/database/src/Schema/State.php:134:    public function registerIndex(AbstractIndex $index): void
vendor/cycle/database/src/Schema/State.php:163:    public function forgetIndex(AbstractIndex $index): void
vendor/cycle/database/src/Schema/State.php:217:    public function findIndex(array $columns): ?AbstractIndex
vendor/cycle/database/src/Schema/AbstractTable.php:249:    public function hasIndex(array $columns = []): bool
vendor/cycle/database/src/Schema/AbstractTable.php:251:        return $this->current->hasIndex($columns);
vendor/cycle/database/src/Schema/AbstractTable.php:255:     * @return AbstractIndex[]
vendor/cycle/database/src/Schema/AbstractTable.php:257:    public function getIndexes(): array
vendor/cycle/database/src/Schema/AbstractTable.php:259:        return $this->current->getIndexes();
vendor/cycle/database/src/Schema/AbstractTable.php:316:     * Get/create instance of AbstractIndex associated with current table based on list of forming
vendor/cycle/database/src/Schema/AbstractTable.php:328:    public function index(array $columns): AbstractIndex
vendor/cycle/database/src/Schema/AbstractTable.php:335:            [$column, $order] = AbstractIndex::parseColumn($expression);
vendor/cycle/database/src/Schema/AbstractTable.php:339:                $this->isIndexColumnSortingSupported() or throw new DriverException(\sprintf(
vendor/cycle/database/src/Schema/AbstractTable.php:358:        if ($this->hasIndex($original)) {
vendor/cycle/database/src/Schema/AbstractTable.php:359:            return $this->current->findIndex($original);
vendor/cycle/database/src/Schema/AbstractTable.php:362:        if ($this->initial->hasIndex($original)) {
vendor/cycle/database/src/Schema/AbstractTable.php:364:            $name = $this->initial->findIndex($original)->getName();
vendor/cycle/database/src/Schema/AbstractTable.php:369:        $index = $this->createIndex($name)->columns($columns)->sort($sort);
vendor/cycle/database/src/Schema/AbstractTable.php:372:        $this->current->registerIndex($index);
vendor/cycle/database/src/Schema/AbstractTable.php:408:        $indexCreate ? $this->index($columns) : $foreign->setIndex(false);
vendor/cycle/database/src/Schema/AbstractTable.php:436:     * @param array $columns Index forming columns.
vendor/cycle/database/src/Schema/AbstractTable.php:442:    public function renameIndex(array $columns, string $name): self
vendor/cycle/database/src/Schema/AbstractTable.php:444:        $this->hasIndex($columns) or throw new SchemaException(
vendor/cycle/database/src/Schema/AbstractTable.php:477:    public function dropIndex(array $columns): self
vendor/cycle/database/src/Schema/AbstractTable.php:479:        $schema = $this->current->findIndex($columns);
vendor/cycle/database/src/Schema/AbstractTable.php:485:        $this->current->forgetIndex($schema);
vendor/cycle/database/src/Schema/AbstractTable.php:647:            'indexes'     => \array_values($this->getIndexes()),
vendor/cycle/database/src/Schema/AbstractTable.php:689:         * In cases where columns are removed we have to automatically remove related indexes and
vendor/cycle/database/src/Schema/AbstractTable.php:693:            foreach ($target->getIndexes() as $index) {
vendor/cycle/database/src/Schema/AbstractTable.php:695:                    $target->current->forgetIndex($index);
vendor/cycle/database/src/Schema/AbstractTable.php:714:            foreach ($target->getIndexes() as $index) {
vendor/cycle/database/src/Schema/AbstractTable.php:728:                    $targetIndex = $target->initial->findIndex($index->getColumns());
vendor/cycle/database/src/Schema/AbstractTable.php:729:                    if ($targetIndex !== null) {
vendor/cycle/database/src/Schema/AbstractTable.php:731:                        $targetIndex->columns($columns);
vendor/cycle/database/src/Schema/AbstractTable.php:767:        foreach ($this->fetchIndexes() as $index) {
vendor/cycle/database/src/Schema/AbstractTable.php:768:            $state->registerIndex($index);
vendor/cycle/database/src/Schema/AbstractTable.php:779:    protected function isIndexColumnSortingSupported(): bool
vendor/cycle/database/src/Schema/AbstractTable.php:794:     * @return AbstractIndex[]
vendor/cycle/database/src/Schema/AbstractTable.php:796:    abstract protected function fetchIndexes(): array;
vendor/cycle/database/src/Schema/AbstractTable.php:823:    abstract protected function createIndex(string $name): AbstractIndex;
vendor/cycle/migrations/src/Exception/Operation/IndexException.php:9:class IndexException extends OperationException {}
vendor/cycle/migrations/src/Atomizer/Renderer.php:9:use Cycle\Database\Schema\AbstractIndex;
vendor/cycle/migrations/src/Atomizer/Renderer.php:30:        $this->declareIndexes($method, $comparator);
vendor/cycle/migrations/src/Atomizer/Renderer.php:51:        $this->declareIndexes($method, $comparator);
vendor/cycle/migrations/src/Atomizer/Renderer.php:65:        $this->revertIndexes($method, $comparator);
vendor/cycle/migrations/src/Atomizer/Renderer.php:125:    private function declareIndexes(Method $method, Comparator $comparator): void
vendor/cycle/migrations/src/Atomizer/Renderer.php:127:        foreach ($comparator->addedIndexes() as $index) {
vendor/cycle/migrations/src/Atomizer/Renderer.php:128:            $method->addBody('->addIndex(?, ?)', [$index->getColumns(), $this->indexOptions($index)]);
vendor/cycle/migrations/src/Atomizer/Renderer.php:131:        foreach ($comparator->alteredIndexes() as $pair) {
vendor/cycle/migrations/src/Atomizer/Renderer.php:132:            /** @var AbstractIndex $index */
vendor/cycle/migrations/src/Atomizer/Renderer.php:134:            $method->addBody('->alterIndex(?, ?)', [$index->getColumns(), $this->indexOptions($index)]);
vendor/cycle/migrations/src/Atomizer/Renderer.php:137:        foreach ($comparator->droppedIndexes() as $index) {
vendor/cycle/migrations/src/Atomizer/Renderer.php:138:            $method->addBody('->dropIndex(?)', [$index->getColumns()]);
vendor/cycle/migrations/src/Atomizer/Renderer.php:195:    private function revertIndexes(Method $method, Comparator $comparator): void
vendor/cycle/migrations/src/Atomizer/Renderer.php:197:        foreach ($comparator->droppedIndexes() as $index) {
vendor/cycle/migrations/src/Atomizer/Renderer.php:198:            $method->addBody('->addIndex(?, ?)', [$index->getColumns(), $this->indexOptions($index)]);
vendor/cycle/migrations/src/Atomizer/Renderer.php:201:        foreach ($comparator->alteredIndexes() as $pair) {
vendor/cycle/migrations/src/Atomizer/Renderer.php:202:            /** @var AbstractIndex $index */
vendor/cycle/migrations/src/Atomizer/Renderer.php:204:            $method->addBody('->alterIndex(?, ?)', [$index->getColumns(), $this->indexOptions($index)]);
vendor/cycle/migrations/src/Atomizer/Renderer.php:207:        foreach ($comparator->addedIndexes() as $index) {
vendor/cycle/migrations/src/Atomizer/Renderer.php:208:            $method->addBody('->dropIndex(?)', [$index->getColumns()]);
vendor/cycle/migrations/src/Atomizer/Renderer.php:276:    private function indexOptions(AbstractIndex $index): array
vendor/cycle/migrations/src/Atomizer/Renderer.php:290:            'indexCreate' => $reference->hasIndex(),
vendor/cycle/database/src/Schema/AbstractForeignKey.php:154:    public function setIndex(bool $index = true): static
vendor/cycle/database/src/Schema/AbstractForeignKey.php:161:    public function hasIndex(): bool
vendor/cycle/database/src/Schema/Comparator.php:38:            \count($this->addedIndexes()),
vendor/cycle/database/src/Schema/Comparator.php:39:            \count($this->droppedIndexes()),
vendor/cycle/database/src/Schema/Comparator.php:40:            \count($this->alteredIndexes()),
vendor/cycle/database/src/Schema/Comparator.php:94:     * Returns array where each value contain current and initial element state.
vendor/cycle/database/src/Schema/Comparator.php:116:     * @return AbstractIndex[]
vendor/cycle/database/src/Schema/Comparator.php:118:    public function addedIndexes(): array
vendor/cycle/database/src/Schema/Comparator.php:121:        foreach ($this->current->getIndexes() as $_ => $index) {
vendor/cycle/database/src/Schema/Comparator.php:122:            if (!$this->initial->hasIndex($index->getColumnsWithSort())) {
vendor/cycle/database/src/Schema/Comparator.php:131:     * @return AbstractIndex[]
vendor/cycle/database/src/Schema/Comparator.php:133:    public function droppedIndexes(): array
vendor/cycle/database/src/Schema/Comparator.php:136:        foreach ($this->initial->getIndexes() as $_ => $index) {
vendor/cycle/database/src/Schema/Comparator.php:137:            if (!$this->current->hasIndex($index->getColumnsWithSort())) {
vendor/cycle/database/src/Schema/Comparator.php:146:     * Returns array where each value contain current and initial element state.
vendor/cycle/database/src/Schema/Comparator.php:148:    public function alteredIndexes(): array
vendor/cycle/database/src/Schema/Comparator.php:152:        foreach ($this->current->getIndexes() as $_ => $index) {
vendor/cycle/database/src/Schema/Comparator.php:153:            if (!$this->initial->hasIndex($index->getColumnsWithSort())) {
vendor/cycle/database/src/Schema/Comparator.php:158:            $initial = $this->initial->findIndex($index->getColumnsWithSort());
vendor/cycle/database/src/Schema/Comparator.php:198:     * Returns array where each value contain current and initial element state.
vendor/cycle/migrations/src/Operation/Index/Drop.php:5:namespace Cycle\Migrations\Operation\Index;
vendor/cycle/migrations/src/Operation/Index/Drop.php:8:use Cycle\Migrations\Exception\Operation\IndexException;
vendor/cycle/migrations/src/Operation/Index/Drop.php:10:final class Drop extends Index
vendor/cycle/migrations/src/Operation/Index/Drop.php:16:        if (!$schema->hasIndex($this->columns)) {
vendor/cycle/migrations/src/Operation/Index/Drop.php:18:            throw new IndexException(
vendor/cycle/migrations/src/Operation/Index/Drop.php:23:        $schema->dropIndex($this->columns);
vendor/cycle/database/src/Schema/Reflector.php:102:            $this->dropIndexes();
vendor/cycle/database/src/Schema/Reflector.php:131:    protected function dropIndexes(): void
vendor/cycle/database/src/Schema/AbstractIndex.php:15:use Cycle\Database\IndexInterface;
vendor/cycle/database/src/Schema/AbstractIndex.php:19: * Abstract index schema with read (see IndexInterface) and write abilities. Must be implemented
vendor/cycle/database/src/Schema/AbstractIndex.php:22:abstract class AbstractIndex implements IndexInterface, ElementInterface
vendor/cycle/database/src/Schema/AbstractIndex.php:27:     * Index types.
vendor/cycle/database/src/Schema/AbstractIndex.php:34:     * Index type, by default NORMAL and UNIQUE indexes supported, additional types can be
vendor/cycle/database/src/Schema/AbstractIndex.php:164:     * Index sql creation syntax.
vendor/cycle/migrations/src/Operation/Index/Index.php:5:namespace Cycle\Migrations\Operation\Index;
vendor/cycle/migrations/src/Operation/Index/Index.php:9:abstract class Index extends AbstractOperation
vendor/cycle/migrations/src/Operation/Index/Alter.php:5:namespace Cycle\Migrations\Operation\Index;
vendor/cycle/migrations/src/Operation/Index/Alter.php:8:use Cycle\Migrations\Exception\Operation\IndexException;
vendor/cycle/migrations/src/Operation/Index/Alter.php:11:final class Alter extends Index
vendor/cycle/migrations/src/Operation/Index/Alter.php:25:        if (!$schema->hasIndex($this->columns)) {
vendor/cycle/migrations/src/Operation/Index/Alter.php:27:            throw new IndexException(
vendor/cycle/migrations/src/Operation/Index/Add.php:5:namespace Cycle\Migrations\Operation\Index;
vendor/cycle/migrations/src/Operation/Index/Add.php:8:use Cycle\Migrations\Exception\Operation\IndexException;
vendor/cycle/migrations/src/Operation/Index/Add.php:11:final class Add extends Index
vendor/cycle/migrations/src/Operation/Index/Add.php:25:        if ($schema->hasIndex($this->columns)) {
vendor/cycle/migrations/src/Operation/Index/Add.php:27:            throw new IndexException(

exec
/bin/zsh -lc "nl -ba vendor/cycle/database/src/Schema/AbstractTable.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	/**
     4	 * This file is part of Cycle ORM package.
     5	 *
     6	 * For the full copyright and license information, please view the LICENSE
     7	 * file that was distributed with this source code.
     8	 */
     9	
    10	declare(strict_types=1);
    11	
    12	namespace Cycle\Database\Schema;
    13	
    14	use Cycle\Database\Driver\DriverInterface;
    15	use Cycle\Database\Driver\HandlerInterface;
    16	use Cycle\Database\Exception\DriverException;
    17	use Cycle\Database\Exception\HandlerException;
    18	use Cycle\Database\Exception\SchemaException;
    19	use Cycle\Database\TableInterface;
    20	
    21	/**
    22	 * AbstractTable class used to describe and manage state of specified table. It provides ability to
    23	 * get table introspection, update table schema and automatically generate set of diff operations.
    24	 *
    25	 * Most of table operation like column, index or foreign key creation/altering will be applied when
    26	 * save() method will be called.
    27	 *
    28	 * Column configuration shortcuts:
    29	 *
    30	 * @method AbstractColumn primary($column)
    31	 * @method AbstractColumn bigPrimary($column)
    32	 * @method AbstractColumn enum($column, array $values)
    33	 * @method AbstractColumn string($column, $length = 255)
    34	 * @method AbstractColumn decimal($column, $precision, $scale)
    35	 * @method AbstractColumn boolean($column)
    36	 * @method AbstractColumn integer($column)
    37	 * @method AbstractColumn tinyInteger($column)
    38	 * @method AbstractColumn smallInteger($column)
    39	 * @method AbstractColumn bigInteger($column)
    40	 * @method AbstractColumn text($column)
    41	 * @method AbstractColumn tinyText($column)
    42	 * @method AbstractColumn mediumText($column)
    43	 * @method AbstractColumn longText($column)
    44	 * @method AbstractColumn json($column)
    45	 * @method AbstractColumn double($column)
    46	 * @method AbstractColumn float($column)
    47	 * @method AbstractColumn datetime($column, $size = 0)
    48	 * @method AbstractColumn date($column)
    49	 * @method AbstractColumn time($column)
    50	 * @method AbstractColumn timestamp($column)
    51	 * @method AbstractColumn binary($column)
    52	 * @method AbstractColumn tinyBinary($column)
    53	 * @method AbstractColumn longBinary($column)
    54	 * @method AbstractColumn snowflake($column)
    55	 * @method AbstractColumn ulid($column)
    56	 * @method AbstractColumn uuid($column)
    57	 */
    58	abstract class AbstractTable implements TableInterface, ElementInterface
    59	{
    60	    /**
    61	     * Table states.
    62	     */
    63	    public const STATUS_NEW = 0;
    64	
    65	    public const STATUS_EXISTS = 1;
    66	    public const STATUS_DECLARED_DROPPED = 2;
    67	
    68	    /**
    69	     * Initial table state.
    70	     *
    71	     * @internal
    72	     */
    73	    protected State $initial;
    74	
    75	    /**
    76	     * Currently defined table state.
    77	     *
    78	     * @internal
    79	     */
    80	    protected State $current;
    81	
    82	    /**
    83	     * Indication that table is exists and current schema is fetched from database.
    84	     */
    85	    private int $status = self::STATUS_NEW;
    86	
    87	    /**
    88	     * @param DriverInterface $driver Parent driver.
    89	     *
    90	     * @param string $prefix Database specific table prefix. Required for table renames.
    91	     * @psalm-param non-empty-string $name Table name, must include table prefix.
    92	     */
    93	    public function __construct(
    94	        protected DriverInterface $driver,
    95	        string $name,
    96	        private string $prefix,
    97	    ) {
    98	        //Initializing states
    99	        $prefixedName = $this->prefixTableName($name);
   100	        $this->initial = new State($prefixedName);
   101	        $this->current = new State($prefixedName);
   102	
   103	        if ($this->driver->getSchemaHandler()->hasTable($this->getFullName())) {
   104	            $this->status = self::STATUS_EXISTS;
   105	        }
   106	
   107	        if ($this->exists()) {
   108	            //Initiating table schema
   109	            $this->initSchema($this->initial);
   110	        }
   111	
   112	        $this->setState($this->initial);
   113	    }
   114	
   115	    /**
   116	     * Sanitize column expression for index name
   117	     *
   118	     * @psalm-param non-empty-string $column
   119	     *
   120	     * @psalm-return non-empty-string
   121	     */
   122	    public static function sanitizeColumnExpression(string $column): string
   123	    {
   124	        return \preg_replace(['/\(/', '/\)/', '/ /'], '__', \strtolower($column));
   125	    }
   126	
   127	    /**
   128	     * Get instance of associated driver.
   129	     */
   130	    public function getDriver(): DriverInterface
   131	    {
   132	        return $this->driver;
   133	    }
   134	
   135	    /**
   136	     * Return database specific table prefix.
   137	     */
   138	    public function getPrefix(): string
   139	    {
   140	        return $this->prefix;
   141	    }
   142	
   143	    public function getComparator(): ComparatorInterface
   144	    {
   145	        return new Comparator($this->initial, $this->current);
   146	    }
   147	
   148	    public function exists(): bool
   149	    {
   150	        // Declared as dropped != actually dropped
   151	        return $this->status === self::STATUS_EXISTS || $this->status === self::STATUS_DECLARED_DROPPED;
   152	    }
   153	
   154	    /**
   155	     * Table status (see codes above).
   156	     */
   157	    public function getStatus(): int
   158	    {
   159	        return $this->status;
   160	    }
   161	
   162	    /**
   163	     * Sets table name. Use this function in combination with save to rename table.
   164	     *
   165	     * @psalm-param non-empty-string $name
   166	     *
   167	     * @psalm-return non-empty-string Prefixed table name.
   168	     */
   169	    public function setName(string $name): string
   170	    {
   171	        $this->current->setName($this->prefixTableName($name));
   172	
   173	        return $this->getFullName();
   174	    }
   175	
   176	    /**
   177	     * @psalm-return non-empty-string
   178	     */
   179	    public function getName(): string
   180	    {
   181	        return $this->getFullName();
   182	    }
   183	
   184	    /**
   185	     * @psalm-return non-empty-string
   186	     */
   187	    public function getFullName(): string
   188	    {
   189	        return $this->current->getName();
   190	    }
   191	
   192	    /**
   193	     * Table name before rename.
   194	     *
   195	     * @psalm-return non-empty-string
   196	     */
   197	    public function getInitialName(): string
   198	    {
   199	        return $this->initial->getName();
   200	    }
   201	
   202	    /**
   203	     * Declare table as dropped, you have to sync table using "save" method in order to apply this
   204	     * change.
   205	     *
   206	     * Attention, method will flush declared FKs to ensure that table express no dependecies.
   207	     */
   208	    public function declareDropped(): void
   209	    {
   210	        $this->status === self::STATUS_NEW and throw new SchemaException('Unable to drop non existed table');
   211	
   212	        //Declaring as dropped
   213	        $this->status = self::STATUS_DECLARED_DROPPED;
   214	    }
   215	
   216	    /**
   217	     * Set table primary keys. Operation can only be applied for newly created tables. Now every
   218	     * database might support compound indexes.
   219	     */
   220	    public function setPrimaryKeys(array $columns): self

exec
/bin/zsh -lc "rg -n \"addIndex\\(\" app/database/migrations -g '*.php'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/database/migrations/20260613.143902_0_create_access_domain_tables.php:21:            ->addIndex(['slug'], ['unique' => true])
app/database/migrations/20260613.143902_0_create_access_domain_tables.php:30:            ->addIndex(['slug'], ['unique' => true])
app/database/migrations/20260613.143902_0_create_access_domain_tables.php:50:            ->addIndex(['role_id', 'permission_id'], ['unique' => true])
app/database/migrations/20260613.143902_0_create_access_domain_tables.php:70:            ->addIndex(['user_id', 'role_id'], ['unique' => true])
app/database/migrations/20260525.153700_0_create_outbox_events_table.php:29:            ->addIndex(['status', 'available_at', 'id'])
app/database/migrations/20260525.153700_0_create_outbox_events_table.php:30:            ->addIndex(['type'])
app/database/migrations/20260525.153700_0_create_outbox_events_table.php:31:            ->addIndex(['queued_at'])
app/database/migrations/20260525.153700_0_create_outbox_events_table.php:32:            ->addIndex(['failed_at'])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php:31:            ->addIndex(['email'], ['unique' => true])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php:32:            ->addIndex(['nickname'], ['unique' => true])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php:33:            ->addIndex(['status'])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php:60:            ->addIndex(['user_id'])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php:70:            ->addIndex(['nickname'], ['unique' => true])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php:77:            ->addIndex(['assigned_user_id'])
app/database/migrations/20260521.184100_0_create_media_domain_tables.php:32:            ->addIndex(['storage_key'], ['unique' => true])
app/database/migrations/20260521.184100_0_create_media_domain_tables.php:33:            ->addIndex(['uploaded_by_id'])
app/database/migrations/20260521.184100_0_create_media_domain_tables.php:34:            ->addIndex(['status'])
app/database/migrations/20260521.184100_0_create_media_domain_tables.php:35:            ->addIndex(['expires_at'])
app/database/migrations/20260521.184100_0_create_media_domain_tables.php:58:            ->addIndex(['media_id', 'type'], ['unique' => true])
app/database/migrations/20260521.184100_0_create_media_domain_tables.php:59:            ->addIndex(['media_id'])
app/database/migrations/20260521.184100_0_create_media_domain_tables.php:60:            ->addIndex(['status'])
app/database/migrations/20260521.184100_0_create_media_domain_tables.php:85:            ->addIndex(['media_id', 'type'], ['unique' => true])
app/database/migrations/20260521.184100_0_create_media_domain_tables.php:86:            ->addIndex(['media_id'])
app/database/migrations/20260521.184100_0_create_media_domain_tables.php:87:            ->addIndex(['status'])
app/database/migrations/20260521.184100_0_create_media_domain_tables.php:107:            ->addIndex(['media_id'], ['unique' => true])
app/database/migrations/20260521.184100_0_create_media_domain_tables.php:108:            ->addIndex(['upload_id'])

exec
/bin/zsh -lc "rg -n \"partial|where.*index|CREATE INDEX|execute\\(\" vendor/cycle/database vendor/cycle/migrations app/database/migrations -g '*.php' | sed -n '1,200p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
vendor/cycle/migrations/src/TableBlueprint.php:183:        $this->execute();
vendor/cycle/migrations/src/TableBlueprint.php:195:        $this->execute();
vendor/cycle/migrations/src/TableBlueprint.php:207:        $this->execute();
vendor/cycle/migrations/src/TableBlueprint.php:219:        $this->execute();
vendor/cycle/migrations/src/TableBlueprint.php:235:    private function execute(): void
vendor/cycle/migrations/src/TableBlueprint.php:241:        $this->capsule->execute($this->operations);
vendor/cycle/migrations/src/Capsule.php:47:    public function execute(array $operations): void
vendor/cycle/migrations/src/Capsule.php:59:            $operation->execute($this);
vendor/cycle/migrations/src/CapsuleInterface.php:35:    public function execute(array $operations): void;
vendor/cycle/migrations/src/Operation/ForeignKey/Drop.php:12:    public function execute(CapsuleInterface $capsule): void
vendor/cycle/migrations/src/Operation/ForeignKey/Alter.php:27:    public function execute(CapsuleInterface $capsule): void
vendor/cycle/migrations/src/OperationInterface.php:25:    public function execute(CapsuleInterface $capsule): void;
vendor/cycle/migrations/src/Operation/ForeignKey/Add.php:27:    public function execute(CapsuleInterface $capsule): void
vendor/cycle/migrations/src/Operation/Index/Drop.php:12:    public function execute(CapsuleInterface $capsule): void
vendor/cycle/migrations/src/Operation/Index/Alter.php:21:    public function execute(CapsuleInterface $capsule): void
vendor/cycle/migrations/src/Operation/Index/Add.php:21:    public function execute(CapsuleInterface $capsule): void
vendor/cycle/migrations/src/Operation/Table/PrimaryKeys.php:18:    public function execute(CapsuleInterface $capsule): void
vendor/cycle/database/src/DatabaseInterface.php:92:    public function execute(string $query, array $parameters = []): int;
vendor/cycle/migrations/src/Operation/Column/Drop.php:18:    public function execute(CapsuleInterface $capsule): void
vendor/cycle/migrations/src/Operation/Table/Drop.php:14:    public function execute(CapsuleInterface $capsule): void
vendor/cycle/database/src/Database.php:123:    public function execute(string $query, array $parameters = []): int
vendor/cycle/database/src/Database.php:126:            ->execute($query, $parameters);
vendor/cycle/migrations/src/Operation/Column/Alter.php:12:    public function execute(CapsuleInterface $capsule): void
vendor/cycle/database/src/Schema/AbstractTable.php:689:         * In cases where columns are removed we have to automatically remove related indexes and
vendor/cycle/migrations/src/Operation/Table/Create.php:14:    public function execute(CapsuleInterface $capsule): void
vendor/cycle/migrations/src/Operation/Column/Rename.php:18:    public function execute(CapsuleInterface $capsule): void
vendor/cycle/migrations/src/Operation/Table/Rename.php:19:    public function execute(CapsuleInterface $capsule): void
vendor/cycle/migrations/src/Operation/Column/Add.php:12:    public function execute(CapsuleInterface $capsule): void
vendor/cycle/migrations/src/Operation/Table/Update.php:14:    public function execute(CapsuleInterface $capsule): void
vendor/cycle/database/src/Driver/Handler.php:220:            return $this->driver->execute($statement, $parameters);
vendor/cycle/database/src/Query/UpdateQuery.php:85:        return $this->driver->execute($queryString, $params->getParameters());
vendor/cycle/database/src/Driver/DriverInterface.php:168:    public function execute(string $query, array $parameters = []): int;
vendor/cycle/database/src/Query/DeleteQuery.php:63:        return $this->driver->execute($queryString, $params->getParameters());
vendor/cycle/database/src/Driver/SQLite/SQLiteHandler.php:66:        $this->driver->execute(
vendor/cycle/database/src/Driver/PDOStatementInterface.php:25:    public function execute(array|null $params = null): bool;
vendor/cycle/database/src/Query/InsertQuery.php:124:        $this->driver->execute(
vendor/cycle/database/src/Driver/SQLServer/SQLServerDriver.php:220:        $this->execute('SAVE TRANSACTION ' . $this->identifier("SVP{$level}"));
vendor/cycle/database/src/Driver/SQLServer/SQLServerDriver.php:248:        $this->execute('ROLLBACK TRANSACTION ' . $this->identifier("SVP{$level}"));
vendor/cycle/database/src/Driver/SQLServer/SQLServerHandler.php:61:        $this->driver->execute(
vendor/cycle/database/src/Driver/Postgres/PostgresHandler.php:83:        $this->driver->execute($query);
vendor/cycle/database/src/Driver/Driver.php:227:    public function execute(string $query, array $parameters = []): int
vendor/cycle/database/src/Driver/Driver.php:463:            $statement->execute();
vendor/cycle/database/src/Driver/Driver.php:605:        $this->execute("SET TRANSACTION ISOLATION LEVEL {$level}");
vendor/cycle/database/src/Driver/Driver.php:619:        $this->execute('SAVEPOINT ' . $this->identifier("SVP{$level}"));
vendor/cycle/database/src/Driver/Driver.php:633:        $this->execute('RELEASE SAVEPOINT ' . $this->identifier("SVP{$level}"));
vendor/cycle/database/src/Driver/Driver.php:647:        $this->execute('ROLLBACK TO SAVEPOINT ' . $this->identifier("SVP{$level}"));
vendor/cycle/database/src/Driver/MySQL/MySQLHandler.php:62:        $this->driver->execute(

codex
Нашёл ещё один важный риск по logout: `RequireAuthenticatedMiddleware` в плане бросает 401 из HTTP middleware, а не из контроллера. Такие исключения не проходят через `ApiExceptionInterceptor`, потому что он стоит в доменном пайплайне контроллера. Значит для middleware-недопуска нужен прямой JSON-ответ, как у rate limit, или отдельный HTTP-level обработчик.
exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Http/src/Middleware/ErrorHandlerMiddleware.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba app/src/Shared/Infrastructure/Framework/Bootloader/ExceptionHandlerBootloader.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Http\Middleware;
     6	
     7	use Psr\Http\Message\ResponseFactoryInterface;
     8	use Psr\Http\Message\ResponseInterface as Response;
     9	use Psr\Http\Message\ServerRequestInterface as Request;
    10	use Psr\Http\Server\RequestHandlerInterface as Handler;
    11	use Psr\Http\Server\MiddlewareInterface;
    12	use Spiral\Exceptions\ExceptionHandlerInterface;
    13	use Spiral\Exceptions\ExceptionRendererInterface;
    14	use Spiral\Exceptions\Verbosity;
    15	use Spiral\Http\ErrorHandler\RendererInterface;
    16	use Spiral\Http\Exception\ClientException;
    17	use Spiral\Http\Header\AcceptHeader;
    18	use Spiral\Http\Middleware\ErrorHandlerMiddleware\SuppressErrorsInterface;
    19	use Spiral\Logger\Traits\LoggerTrait;
    20	use Spiral\Router\Exception\RouterException;
    21	
    22	/**
    23	 * Wraps Client and Routing exceptions into proper response.
    24	 */
    25	final class ErrorHandlerMiddleware implements MiddlewareInterface
    26	{
    27	    use LoggerTrait;
    28	
    29	    public function __construct(
    30	        private readonly SuppressErrorsInterface $suppressErrors,
    31	        private readonly RendererInterface $renderer,
    32	        private readonly ResponseFactoryInterface $responseFactory,
    33	        private readonly ExceptionHandlerInterface $errorHandler,
    34	        private readonly Verbosity $verbosity = Verbosity::VERBOSE,
    35	    ) {}
    36	
    37	    /**
    38	     * @psalm-suppress UnusedVariable
    39	     * @throws \Throwable
    40	     */
    41	    public function process(Request $request, Handler $handler): Response
    42	    {
    43	        try {
    44	            return $handler->handle($request);
    45	        } catch (ClientException|RouterException $e) {
    46	            $code = $e instanceof ClientException ? $e->getCode() : 404;
    47	        } catch (\Throwable $e) {
    48	            $code = 500;
    49	        }
    50	
    51	        $this->errorHandler->report($e);
    52	
    53	        if (!$this->suppressErrors->suppressed()) {
    54	            return $this->renderError($request, $e, $code);
    55	        }
    56	
    57	        $this->logError($request, $code, $e->getMessage());
    58	
    59	        return $this->renderer->renderException($request, $code, $e);
    60	    }
    61	
    62	    /**
    63	     * @throws \Throwable
    64	     */
    65	    private function renderError(Request $request, \Throwable $e, int $code): Response
    66	    {
    67	        $response = $this->responseFactory->createResponse($code);
    68	
    69	        [$format, $renderer] = $this->getRenderer($this->errorHandler, $request);
    70	
    71	        if ($format !== null) {
    72	            $response = $response->withHeader('Content-Type', $format . '; charset=UTF-8');
    73	        }
    74	
    75	        $response->getBody()->write(
    76	            (string) $renderer?->render(
    77	                exception: $e,
    78	                verbosity: $this->verbosity,
    79	                format: $format,
    80	            ),
    81	        );
    82	
    83	        return $response;
    84	    }
    85	
    86	    /**
    87	     * @return array{string|null, ExceptionRendererInterface|null}
    88	     */
    89	    private function getRenderer(ExceptionHandlerInterface $handler, Request $request): array
    90	    {
    91	        if ($request->hasHeader('Accept')) {
    92	            $acceptItems = AcceptHeader::fromString($request->getHeaderLine('Accept'))->getAll();
    93	            foreach ($acceptItems as $item) {
    94	                $format = $item->getValue();
    95	                $renderer = $handler->getRenderer($format);
    96	                if ($renderer !== null) {
    97	                    return [$format, $renderer];
    98	                }
    99	            }
   100	        }
   101	        return [null, $handler->getRenderer()];
   102	    }
   103	
   104	    private function logError(Request $request, int $code, string $message): void
   105	    {
   106	        $this->getLogger()->error(
   107	            \sprintf(
   108	                '%s://%s%s caused the error %s (%s) by client %s.',
   109	                $request->getUri()->getScheme(),
   110	                $request->getUri()->getHost(),
   111	                $request->getUri()->getPath(),
   112	                $code,
   113	                $message ?: '-not specified-',
   114	                $request->getServerParams()['REMOTE_ADDR'] ?? '127.0.0.1',
   115	            ),
   116	        );
   117	    }
   118	}

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Shared\Infrastructure\Framework\Bootloader;
     6	
     7	use Spiral\Boot\AbstractKernel;
     8	use Spiral\Boot\Bootloader\Bootloader;
     9	use Spiral\Boot\Environment\AppEnvironment;
    10	use Spiral\Exceptions\ExceptionHandler;
    11	use Spiral\Exceptions\Renderer\ConsoleRenderer;
    12	use Spiral\Exceptions\Renderer\JsonRenderer;
    13	use Spiral\Exceptions\Reporter\FileReporter;
    14	use Spiral\Exceptions\Reporter\LoggerReporter;
    15	use Spiral\Http\Middleware\ErrorHandlerMiddleware\EnvSuppressErrors;
    16	use Spiral\Http\Middleware\ErrorHandlerMiddleware\SuppressErrorsInterface;
    17	
    18	/**
    19	 * Регистрирует рендереры и репортёры исключений.
    20	 *
    21	 * @link https://spiral.dev/docs/basics-errors
    22	 */
    23	final class ExceptionHandlerBootloader extends Bootloader
    24	{
    25	    protected const array BINDINGS = [
    26	        SuppressErrorsInterface::class => EnvSuppressErrors::class,
    27	    ];
    28	
    29	    public function __construct(
    30	        private readonly ExceptionHandler $handler,
    31	    ) {}
    32	
    33	    public function init(AbstractKernel $kernel): void
    34	    {
    35	        // Регистрируем рендерер для консольного режима.
    36	        $this->handler->addRenderer(new ConsoleRenderer());
    37	
    38	        $kernel->running(function (): void {
    39	            // Регистрируем JSON-рендерер для HTTP-запросов, ожидающих JSON.
    40	            $this->handler->addRenderer(new JsonRenderer());
    41	        });
    42	    }
    43	
    44	    public function boot(LoggerReporter $logger, FileReporter $files, AppEnvironment $appEnv): void
    45	    {
    46	        // Регистрируем репортёр, который пишет исключения в лог.
    47	        $this->handler->addReporter($logger);
    48	
    49	        // В локальном окружении сохраняем подробный snapshot исключения в файл.
    50	        if ($appEnv->isLocal()) {
    51	            $this->handler->addReporter($files);
    52	        }
    53	    }
    54	}

exec
/bin/zsh -lc "rg -n \"ExceptionRenderer|Renderer|exception\" app/src/Shared/Infrastructure/Framework vendor/spiral/framework/src/Http/src packages/spiral-api-errors/src -g '*.php' | sed -n '1,240p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
vendor/spiral/framework/src/Http/src/ErrorHandler/PlainRenderer.php:15:final class PlainRenderer implements RendererInterface
vendor/spiral/framework/src/Http/src/ErrorHandler/PlainRenderer.php:21:    public function renderException(Request $request, int $code, \Throwable $exception): Response
packages/spiral-api-errors/src/Interceptor/ApiExceptionInterceptor.php:26:        } catch (\DomainException $exception) {
packages/spiral-api-errors/src/Interceptor/ApiExceptionInterceptor.php:27:            return $this->domainExceptionResponse($exception);
packages/spiral-api-errors/src/Interceptor/ApiExceptionInterceptor.php:28:        } catch (FilterValidationException $exception) {
packages/spiral-api-errors/src/Interceptor/ApiExceptionInterceptor.php:29:            throw $exception;
packages/spiral-api-errors/src/Interceptor/ApiExceptionInterceptor.php:30:        } catch (\Throwable $exception) {
packages/spiral-api-errors/src/Interceptor/ApiExceptionInterceptor.php:31:            return $this->unexpectedExceptionResponse($exception);
packages/spiral-api-errors/src/Interceptor/ApiExceptionInterceptor.php:34:    private function domainExceptionResponse(\DomainException $exception): ErrorResponse
packages/spiral-api-errors/src/Interceptor/ApiExceptionInterceptor.php:36:        $status = $this->supportedClientStatus($exception);
packages/spiral-api-errors/src/Interceptor/ApiExceptionInterceptor.php:38:            return $this->unexpectedExceptionResponse($exception);
packages/spiral-api-errors/src/Interceptor/ApiExceptionInterceptor.php:40:        if ($exception instanceof TranslatableException) {
packages/spiral-api-errors/src/Interceptor/ApiExceptionInterceptor.php:41:            return $this->errorResponse(message: $this->translator->trans(id: $exception->translationKey(), parameters: $exception->translationParameters(), domain: $exception->translationDomain()), status: $status);
packages/spiral-api-errors/src/Interceptor/ApiExceptionInterceptor.php:43:        return $this->errorResponse(message: $exception->getMessage(), status: $status);
packages/spiral-api-errors/src/Interceptor/ApiExceptionInterceptor.php:45:    private function unexpectedExceptionResponse(\Throwable $exception): ErrorResponse
packages/spiral-api-errors/src/Interceptor/ApiExceptionInterceptor.php:47:        $this->logger->error(message: 'Непредвиденная ошибка API.', context: ['exceptionClass' => $exception::class, 'message' => $exception->getMessage(), 'file' => $exception->getFile(), 'line' => $exception->getLine()]);
packages/spiral-api-errors/src/Interceptor/ApiExceptionInterceptor.php:50:    private function supportedClientStatus(\DomainException $exception): HttpStatus|null
packages/spiral-api-errors/src/Interceptor/ApiExceptionInterceptor.php:52:        $status = HttpStatus::tryFrom($exception->getCode());
packages/spiral-api-errors/src/Filter/ApiValidationErrorsRenderer.php:9:use Spiral\Filters\ErrorsRendererInterface;
packages/spiral-api-errors/src/Filter/ApiValidationErrorsRenderer.php:15:final readonly class ApiValidationErrorsRenderer implements ErrorsRendererInterface
packages/spiral-api-errors/src/Bootloader/ApiErrorBootloader.php:10:use Spiral\Filters\ErrorsRendererInterface;
packages/spiral-api-errors/src/Bootloader/ApiErrorBootloader.php:12:use GianTiaga\SpiralApiErrors\Filter\ApiValidationErrorsRenderer;
packages/spiral-api-errors/src/Bootloader/ApiErrorBootloader.php:26:        return [...parent::defineSingletons(), ErrorsRendererInterface::class => [self::class, 'apiValidationErrorsRenderer'], ApiValidationErrorsRenderer::class => [self::class, 'apiValidationErrorsRenderer'], ApiExceptionInterceptor::class => [self::class, 'apiExceptionInterceptor'], RouteNotFoundMiddleware::class => [self::class, 'routeNotFoundMiddleware']];
packages/spiral-api-errors/src/Bootloader/ApiErrorBootloader.php:28:    public function apiValidationErrorsRenderer(LoggerInterface $logger, TranslatorInterface $translator): ApiValidationErrorsRenderer
packages/spiral-api-errors/src/Bootloader/ApiErrorBootloader.php:30:        return new ApiValidationErrorsRenderer(logger: $logger, translator: $translator);
vendor/spiral/framework/src/Http/src/ErrorHandler/RendererInterface.php:11: * Render exception content into response.
vendor/spiral/framework/src/Http/src/ErrorHandler/RendererInterface.php:13:interface RendererInterface
vendor/spiral/framework/src/Http/src/ErrorHandler/RendererInterface.php:15:    public function renderException(Request $request, int $code, \Throwable $exception): Response;
app/src/Shared/Infrastructure/Framework/Bootloader/ExceptionHandlerBootloader.php:11:use Spiral\Exceptions\Renderer\ConsoleRenderer;
app/src/Shared/Infrastructure/Framework/Bootloader/ExceptionHandlerBootloader.php:12:use Spiral\Exceptions\Renderer\JsonRenderer;
app/src/Shared/Infrastructure/Framework/Bootloader/ExceptionHandlerBootloader.php:36:        $this->handler->addRenderer(new ConsoleRenderer());
app/src/Shared/Infrastructure/Framework/Bootloader/ExceptionHandlerBootloader.php:40:            $this->handler->addRenderer(new JsonRenderer());
packages/spiral-api-errors/src/Middleware/RouteNotFoundMiddleware.php:24:        } catch (RouteNotFoundException $exception) {
packages/spiral-api-errors/src/Middleware/RouteNotFoundMiddleware.php:25:            $this->logger->debug(message: 'HTTP-маршрут не найден.', context: ['method' => $request->getMethod(), 'path' => $request->getUri()->getPath(), 'status' => HttpStatus::NotFound->value, 'exceptionClass' => $exception::class]);
vendor/spiral/framework/src/Http/src/Exception/ClientException/UnauthorizedException.php:10: * HTTP 401 exception.
vendor/spiral/framework/src/Http/src/Exception/ClientException/BadRequestException.php:10: * HTTP 400 exception.
vendor/spiral/framework/src/Http/src/Exception/ClientException/ServerErrorException.php:10: * HTTP 500 exception.
vendor/spiral/framework/src/Http/src/Exception/ResponseException.php:8: * Responder exceptions such as unability to generate response.
vendor/spiral/framework/src/Http/src/Exception/ClientException/NotFoundException.php:10: * HTTP 404 exception.
vendor/spiral/framework/src/Http/src/Exception/ClientException/ForbiddenException.php:10: * HTTP 403 exception.
vendor/spiral/framework/src/Http/src/Middleware/ErrorHandlerMiddleware.php:13:use Spiral\Exceptions\ExceptionRendererInterface;
vendor/spiral/framework/src/Http/src/Middleware/ErrorHandlerMiddleware.php:15:use Spiral\Http\ErrorHandler\RendererInterface;
vendor/spiral/framework/src/Http/src/Middleware/ErrorHandlerMiddleware.php:23: * Wraps Client and Routing exceptions into proper response.
vendor/spiral/framework/src/Http/src/Middleware/ErrorHandlerMiddleware.php:31:        private readonly RendererInterface $renderer,
vendor/spiral/framework/src/Http/src/Middleware/ErrorHandlerMiddleware.php:69:        [$format, $renderer] = $this->getRenderer($this->errorHandler, $request);
vendor/spiral/framework/src/Http/src/Middleware/ErrorHandlerMiddleware.php:77:                exception: $e,
vendor/spiral/framework/src/Http/src/Middleware/ErrorHandlerMiddleware.php:87:     * @return array{string|null, ExceptionRendererInterface|null}
vendor/spiral/framework/src/Http/src/Middleware/ErrorHandlerMiddleware.php:89:    private function getRenderer(ExceptionHandlerInterface $handler, Request $request): array
vendor/spiral/framework/src/Http/src/Middleware/ErrorHandlerMiddleware.php:95:                $renderer = $handler->getRenderer($format);
vendor/spiral/framework/src/Http/src/Middleware/ErrorHandlerMiddleware.php:101:        return [null, $handler->getRenderer()];
vendor/spiral/framework/src/Http/src/Exception/ClientException.php:8: * Generic client driven http exception.

exec
/bin/zsh -lc "nl -ba packages/spiral-api-errors/src/Interceptor/ApiExceptionInterceptor.php packages/spiral-api-errors/src/Middleware/RouteNotFoundMiddleware.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

exec
/bin/zsh -lc "nl -ba packages/spiral-api-errors/src/Interceptor/ApiExceptionInterceptor.php | sed -n '1,100p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba packages/spiral-api-errors/src/Middleware/RouteNotFoundMiddleware.php | sed -n '1,80p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare (strict_types=1);
     4	
     5	namespace GianTiaga\SpiralApiErrors\Middleware;
     6	
     7	use Psr\Http\Message\ResponseInterface;
     8	use Psr\Http\Message\ServerRequestInterface;
     9	use Psr\Http\Server\MiddlewareInterface;
    10	use Psr\Http\Server\RequestHandlerInterface;
    11	use Psr\Log\LoggerInterface;
    12	use Spiral\Router\Exception\RouteNotFoundException;
    13	use Spiral\Translator\TranslatorInterface;
    14	use GianTiaga\SpiralOpenApi\Response\Enum\HttpStatus;
    15	use GianTiaga\SpiralOpenApi\Response\ErrorResponse;
    16	
    17	final readonly class RouteNotFoundMiddleware implements MiddlewareInterface
    18	{
    19	    public function __construct(private LoggerInterface $logger, private TranslatorInterface $translator) {}
    20	    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    21	    {
    22	        try {
    23	            return $handler->handle($request);
    24	        } catch (RouteNotFoundException $exception) {
    25	            $this->logger->debug(message: 'HTTP-маршрут не найден.', context: ['method' => $request->getMethod(), 'path' => $request->getUri()->getPath(), 'status' => HttpStatus::NotFound->value, 'exceptionClass' => $exception::class]);
    26	            return (new ErrorResponse(message: $this->translator->trans(id: 'gian_tiaga.spiral_api_errors.route_not_found'), code: HttpStatus::NotFound->value))->withStatus(HttpStatus::NotFound)->toResponse();
    27	        }
    28	    }
    29	}

 succeeded in 0ms:
     1	<?php
     2	
     3	declare (strict_types=1);
     4	
     5	namespace GianTiaga\SpiralApiErrors\Interceptor;
     6	
     7	use Psr\Log\LoggerInterface;
     8	use Spiral\Filters\Exception\ValidationException as FilterValidationException;
     9	use Spiral\Interceptors\Context\CallContextInterface;
    10	use Spiral\Interceptors\HandlerInterface;
    11	use Spiral\Interceptors\InterceptorInterface;
    12	use Spiral\Translator\TranslatorInterface;
    13	use GianTiaga\SpiralApiErrors\Exception\TranslatableException;
    14	use GianTiaga\SpiralOpenApi\Response\Enum\HttpStatus;
    15	use GianTiaga\SpiralOpenApi\Response\ErrorResponse;
    16	
    17	final readonly class ApiExceptionInterceptor implements InterceptorInterface
    18	{
    19	    private const int CLIENT_ERROR_MIN = 400;
    20	    private const int CLIENT_ERROR_MAX = 499;
    21	    public function __construct(private LoggerInterface $logger, private TranslatorInterface $translator) {}
    22	    public function intercept(CallContextInterface $context, HandlerInterface $handler): mixed
    23	    {
    24	        try {
    25	            return $handler->handle($context);
    26	        } catch (\DomainException $exception) {
    27	            return $this->domainExceptionResponse($exception);
    28	        } catch (FilterValidationException $exception) {
    29	            throw $exception;
    30	        } catch (\Throwable $exception) {
    31	            return $this->unexpectedExceptionResponse($exception);
    32	        }
    33	    }
    34	    private function domainExceptionResponse(\DomainException $exception): ErrorResponse
    35	    {
    36	        $status = $this->supportedClientStatus($exception);
    37	        if ($status === null) {
    38	            return $this->unexpectedExceptionResponse($exception);
    39	        }
    40	        if ($exception instanceof TranslatableException) {
    41	            return $this->errorResponse(message: $this->translator->trans(id: $exception->translationKey(), parameters: $exception->translationParameters(), domain: $exception->translationDomain()), status: $status);
    42	        }
    43	        return $this->errorResponse(message: $exception->getMessage(), status: $status);
    44	    }
    45	    private function unexpectedExceptionResponse(\Throwable $exception): ErrorResponse
    46	    {
    47	        $this->logger->error(message: 'Непредвиденная ошибка API.', context: ['exceptionClass' => $exception::class, 'message' => $exception->getMessage(), 'file' => $exception->getFile(), 'line' => $exception->getLine()]);
    48	        return $this->errorResponse(message: $this->translator->trans(id: 'gian_tiaga.spiral_api_errors.internal_server_error'), status: HttpStatus::InternalServerError);
    49	    }
    50	    private function supportedClientStatus(\DomainException $exception): HttpStatus|null
    51	    {
    52	        $status = HttpStatus::tryFrom($exception->getCode());
    53	        if ($status === null) {
    54	            return null;
    55	        }
    56	        if ($status->value < self::CLIENT_ERROR_MIN || $status->value > self::CLIENT_ERROR_MAX) {
    57	            return null;
    58	        }
    59	        return $status;
    60	    }
    61	    private function errorResponse(string $message, HttpStatus $status): ErrorResponse
    62	    {
    63	        return (new ErrorResponse(message: $message, code: $status->value))->withStatus($status);
    64	    }
    65	}

exec
/bin/zsh -lc "rg -n \"final class JsonRenderer|class JsonRenderer\" vendor/spiral/framework/src/Exceptions -g '*.php' && nl -ba vendor/spiral/framework/src/Exceptions/src/Renderer/JsonRenderer.php | sed -n '1,120p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
vendor/spiral/framework/src/Exceptions/src/Renderer/JsonRenderer.php:9:final class JsonRenderer extends AbstractRenderer
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Exceptions\Renderer;
     6	
     7	use Spiral\Exceptions\Verbosity;
     8	
     9	final class JsonRenderer extends AbstractRenderer
    10	{
    11	    protected const FORMATS = ['application/json', 'json'];
    12	
    13	    public function render(
    14	        \Throwable $exception,
    15	        ?Verbosity $verbosity = Verbosity::BASIC,
    16	        ?string $format = null,
    17	    ): string {
    18	        $verbosity ??= $this->defaultVerbosity;
    19	        $result = \json_encode([
    20	            'error' => \sprintf(
    21	                '[%s] %s as %s:%s',
    22	                $exception::class,
    23	                $exception->getMessage(),
    24	                $exception->getFile(),
    25	                $exception->getLine(),
    26	            ),
    27	            'stacktrace' => \iterator_to_array($this->renderTrace($exception->getTrace(), $verbosity)),
    28	        ]);
    29	
    30	        return $result === false ? 'false' : $result;
    31	    }
    32	
    33	    private function renderTrace(array $trace, Verbosity $verbosity): \Generator
    34	    {
    35	        foreach ($trace as $item) {
    36	            $result = [];
    37	
    38	            if (isset($item['class'])) {
    39	                $result['function'] = \sprintf(
    40	                    '%s%s%s()',
    41	                    $item['class'],
    42	                    $item['type'],
    43	                    $item['function'],
    44	                );
    45	            } else {
    46	                $result['function'] = \sprintf(
    47	                    '%s()',
    48	                    $item['function'],
    49	                );
    50	            }
    51	
    52	            if ($verbosity->value >= Verbosity::VERBOSE->value && isset($item['file'])) {
    53	                $result['at'] = [
    54	                    'file' => $item['file'] ?? null,
    55	                    'line' => $item['line'] ?? null,
    56	                ];
    57	            }
    58	
    59	            yield $result;
    60	        }
    61	    }
    62	}

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Http/src/Exception/ClientException/UnauthorizedException.php vendor/spiral/framework/src/Http/src/Exception/ClientException.php | sed -n '1,140p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

codex
Ещё одна тонкость по auth middleware: инжектить `AuthContextInterface` в конструктор следующего middleware должно сработать только если оно создаётся после того, как `AuthTransportWithStorageMiddleware` уже положил контекст в request. Spiral LazyPipeline как раз лениво создаёт следующий middleware по ходу цепочки, но это стоит закрепить тестом.
exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Http/src/CallableHandler.php | sed -n '1,120p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Http/src/LazyPipeline.php | sed -n '85,160p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
    85	    }
    86	
    87	    public function handle(Request $request): Response
    88	    {
    89	        $this->handler === null and throw new PipelineException('Unable to run pipeline, no handler given.');
    90	
    91	        /** @var CurrentRequest $currentRequest */
    92	        $currentRequest = $this->container->get(CurrentRequest::class);
    93	
    94	        $previousRequest = $currentRequest->get();
    95	        $currentRequest->set($request);
    96	        try {
    97	            // There is no middleware to process, let's pass the request to the handler
    98	            if (!\array_key_exists($this->position, $this->middleware)) {
    99	                return $this->handler->handle($request);
   100	            }
   101	
   102	            $middleware = $this->resolveMiddleware($this->position);
   103	            $this->dispatcher?->dispatch(new MiddlewareProcessing($request, $middleware));
   104	
   105	            $span = $this->span;
   106	
   107	            $middlewareTitle = \is_string($this->middleware[$this->position])
   108	            && $this->middleware[$this->position] !== $middleware::class
   109	                ? \sprintf('%s=%s', $this->middleware[$this->position], $middleware::class)
   110	                : $middleware::class;
   111	            // Init a tracing span when the pipeline starts
   112	            if ($span === null) {
   113	                /** @var TracerInterface $tracer */
   114	                $tracer = $this->container->get(TracerInterface::class);
   115	                return $tracer->trace(
   116	                    name: 'HTTP Pipeline',
   117	                    callback: function (SpanInterface $span) use ($request, $middleware, $middlewareTitle): Response {
   118	                        $span->setAttribute('http.middleware', [$middlewareTitle]);
   119	                        return $middleware->process($request, $this->next($span));
   120	                    },
   121	                    scoped: true,
   122	                );
   123	            }
   124	
   125	            $middlewares = $span->getAttribute('http.middleware') ?? [];
   126	            $middlewares[] = $middlewareTitle;
   127	            $span->setAttribute('http.middleware', $middlewares);
   128	
   129	            return $middleware->process($request, $this->next($span));
   130	        } finally {
   131	            $currentRequest->set($previousRequest);
   132	        }
   133	    }
   134	
   135	    private function next(SpanInterface $span): self
   136	    {
   137	        $pipeline = clone $this;
   138	        ++$pipeline->position;
   139	        $pipeline->span = $span;
   140	        return $pipeline;
   141	    }
   142	
   143	    private function resolveMiddleware(int $position): MiddlewareInterface
   144	    {
   145	        $middleware = $this->middleware[$position];
   146	        return $middleware instanceof MiddlewareInterface
   147	            ? $middleware
   148	            : $this->container->get($middleware);
   149	    }
   150	}

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Http;
     6	
     7	use Psr\Http\Message\ResponseFactoryInterface;
     8	use Psr\Http\Message\ResponseInterface as Response;
     9	use Psr\Http\Message\ServerRequestInterface as Request;
    10	use Psr\Http\Server\RequestHandlerInterface;
    11	use Spiral\Http\Traits\JsonTrait;
    12	
    13	/**
    14	 * Provides ability to invoke any handler and write it's response into ResponseInterface.
    15	 */
    16	final class CallableHandler implements RequestHandlerInterface
    17	{
    18	    use JsonTrait;
    19	
    20	    /** @var callable */
    21	    private mixed $callable;
    22	
    23	    public function __construct(
    24	        callable $callable,
    25	        private readonly ResponseFactoryInterface $responseFactory,
    26	    ) {
    27	        $this->callable = $callable;
    28	    }
    29	
    30	    /**
    31	     * @psalm-suppress UnusedVariable
    32	     */
    33	    public function handle(Request $request): Response
    34	    {
    35	        $outputLevel = \ob_get_level();
    36	        \ob_start();
    37	
    38	        $output = $result = null;
    39	
    40	        $response = $this->responseFactory->createResponse(200);
    41	        try {
    42	            $result = \call_user_func($this->callable, $request, $response);
    43	        } catch (\Throwable $e) {
    44	            \ob_get_clean();
    45	            throw $e;
    46	        } finally {
    47	            while (\ob_get_level() > $outputLevel + 1) {
    48	                $output = \ob_get_clean() . $output;
    49	            }
    50	        }
    51	
    52	        return $this->wrapResponse(
    53	            $response,
    54	            $result,
    55	            \ob_get_clean() . $output,
    56	        );
    57	    }
    58	
    59	    /**
    60	     * Convert endpoint result into valid response.
    61	     *
    62	     * @param Response $response Initial pipeline response.
    63	     * @param mixed    $result   Generated endpoint output.
    64	     * @param string   $output   Buffer output.
    65	     */
    66	    private function wrapResponse(Response $response, mixed $result = null, string $output = ''): Response
    67	    {
    68	        if ($result instanceof Response) {
    69	            if (!empty($output) && $result->getBody()->isWritable()) {
    70	                $result->getBody()->write($output);
    71	            }
    72	
    73	            return $result;
    74	        }
    75	
    76	        if (\is_array($result) || $result instanceof \JsonSerializable) {
    77	            $response = $this->writeJson($response, $result);
    78	        } else {
    79	            $response->getBody()->write((string) $result);
    80	        }
    81	
    82	        //Always glue buffered output
    83	        $response->getBody()->write($output);
    84	
    85	        return $response;
    86	    }
    87	}

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Router/src/Route.php | sed -n '80,135p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
    80	    }
    81	
    82	    /**
    83	     * Associated route with given container.
    84	     */
    85	    public function withContainer(ContainerInterface $container): ContainerizedInterface
    86	    {
    87	        $route = clone $this;
    88	        $route->container = $container;
    89	
    90	        if ($route->target instanceof TargetInterface) {
    91	            $route->target = clone $route->target;
    92	        }
    93	
    94	        $route->pipeline = $route->makeLazyPipeline();
    95	
    96	        return $route;
    97	    }
    98	
    99	    public function withTarget(mixed $target): static
   100	    {
   101	        $route = clone $this;
   102	        $route->target = $target;
   103	
   104	        return $route;
   105	    }
   106	
   107	    public function getTarget(): mixed
   108	    {
   109	        return $this->target;
   110	    }
   111	
   112	    /**
   113	     * @throws RouteException
   114	     */
   115	    public function handle(ServerRequestInterface $request): ResponseInterface
   116	    {
   117	        if (empty($this->requestHandler)) {
   118	            $this->requestHandler = $this->requestHandler();
   119	        }
   120	
   121	        \assert($this->pipeline !== null);
   122	        return $this->pipeline->process(
   123	            $request->withAttribute(self::ROUTE_ATTRIBUTE, $this),
   124	            $this->requestHandler,
   125	        );
   126	    }
   127	
   128	    /**
   129	     * @throws RouteException
   130	     */
   131	    protected function requestHandler(): RequestHandlerInterface
   132	    {
   133	        $this->hasContainer() or throw new RouteException(
   134	            'Unable to configure route pipeline without associated container.',
   135	        );

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Bootloader/Http/HttpBootloader.php vendor/spiral/framework/src/Http/src/Http.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

codex
Проверяю сейчас самый практический вопрос: сработает ли `new Autowire(...)` прямо в `#[Route(middleware: ...)]`. В текущем Spiral маршрут использует `LazyPipeline`, а в нём путь резолва отличается от старого `PipelineFactory`; это может быть фактическим блокером для per-route middleware с параметрами.
exec
/bin/zsh -lc "rg -n \"function get\\(|class Container|interface ContainerInterface|Autowire\" vendor/spiral/framework/src/Core/src -g '*.php' | sed -n '1,240p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
vendor/spiral/framework/src/Core/src/Container/Autowire.php:7:use Spiral\Core\Exception\Container\AutowireException;
vendor/spiral/framework/src/Core/src/Container/Autowire.php:16:final class Autowire
vendor/spiral/framework/src/Core/src/Container/Autowire.php:22:     * Autowire constructor.
vendor/spiral/framework/src/Core/src/Container/Autowire.php:34:     * @throws AutowireException
vendor/spiral/framework/src/Core/src/Container/Autowire.php:36:    public static function wire(mixed $definition): Autowire
vendor/spiral/framework/src/Core/src/Container/Autowire.php:59:        throw new AutowireException('Invalid autowire definition.');
vendor/spiral/framework/src/Core/src/Container/Autowire.php:66:     * @throws AutowireException  No entry was found for this identifier.
vendor/spiral/framework/src/Core/src/ContainerScope.php:17:final class ContainerScope
vendor/spiral/framework/src/Core/src/FactoryInterface.php:7:use Spiral\Core\Exception\Container\AutowireException;
vendor/spiral/framework/src/Core/src/FactoryInterface.php:29:     * @throws AutowireException
vendor/spiral/framework/src/Core/src/Internal/Container.php:8:use Spiral\Core\Container\Autowire;
vendor/spiral/framework/src/Core/src/Internal/Container.php:17:final class Container implements ContainerInterface
vendor/spiral/framework/src/Core/src/Internal/Container.php:46:     * @param class-string<T>|string|Autowire $id
vendor/spiral/framework/src/Core/src/Internal/Container.php:54:    public function get(string|Autowire $id, \Stringable|string|null $context = null): mixed
vendor/spiral/framework/src/Core/src/Internal/Container.php:56:        if ($id instanceof Autowire) {
vendor/spiral/framework/src/Core/src/Internal/Resolver.php:12:use Spiral\Core\Container\Autowire;
vendor/spiral/framework/src/Core/src/Internal/Resolver.php:282:     * Arguments processing. {@see Autowire} object will be resolved.
vendor/spiral/framework/src/Core/src/Internal/Resolver.php:295:        // Resolve Autowire objects
vendor/spiral/framework/src/Core/src/Internal/Resolver.php:296:        if ($value instanceof Autowire) {
vendor/spiral/framework/src/Core/src/Exception/Container/AutowireException.php:10:class AutowireException extends TracedContainerException {}
vendor/spiral/framework/src/Core/src/Container.php:11:use Spiral\Core\Container\Autowire;
vendor/spiral/framework/src/Core/src/Container.php:38:final class Container implements
vendor/spiral/framework/src/Core/src/Container.php:126:     * @param class-string<T>|string|Autowire $id
vendor/spiral/framework/src/Core/src/Container.php:135:    public function get(string|Autowire $id, \Stringable|string|null $context = null): mixed
vendor/spiral/framework/src/Core/src/Internal/Config/StateBinder.php:16:use Spiral\Core\Container\Autowire;
vendor/spiral/framework/src/Core/src/Internal/Config/StateBinder.php:155:            $resolver instanceof Autowire => new \Spiral\Core\Config\Autowire($resolver, $singleton),
vendor/spiral/framework/src/Core/src/Internal/Common/Registry.php:36:    public function get(string $name, string $interface): object
vendor/spiral/framework/src/Core/src/Internal/State.php:9:use Spiral\Core\Container\Autowire;
vendor/spiral/framework/src/Core/src/Internal/State.php:13: * @psalm-type TResolver = class-string|non-empty-string|callable|array{class-string, non-empty-string}|Autowire
vendor/spiral/framework/src/Core/src/Exception/Container/ContainerException.php:13:class ContainerException extends RuntimeException implements ContainerExceptionInterface {}
vendor/spiral/framework/src/Core/src/Internal/Actor.php:16:use Spiral\Core\Exception\Container\AutowireException;
vendor/spiral/framework/src/Core/src/Internal/Actor.php:155:            Config\Autowire::class => $this->resolveAutowire($binding, $alias, $context, $arguments, $tracer),
vendor/spiral/framework/src/Core/src/Internal/Actor.php:178:     * @throws AutowireException
vendor/spiral/framework/src/Core/src/Internal/Actor.php:341:    private function resolveAutowire(
vendor/spiral/framework/src/Core/src/Internal/Actor.php:342:        Config\Autowire $binding,
vendor/spiral/framework/src/Core/src/Exception/Container/ArgumentException.php:10:class ArgumentException extends AutowireException
vendor/spiral/framework/src/Core/src/Config/Autowire.php:7:use Spiral\Core\Container\Autowire as AutowireAlias;
vendor/spiral/framework/src/Core/src/Config/Autowire.php:10: * Wraps {@see AutowireAlias}.
vendor/spiral/framework/src/Core/src/Config/Autowire.php:12:final class Autowire extends Binding
vendor/spiral/framework/src/Core/src/Config/Autowire.php:15:        public readonly AutowireAlias $autowire,
vendor/spiral/framework/src/Core/src/Config/Autowire.php:21:        return 'Autowire object';
vendor/spiral/framework/src/Core/src/Exception/Container/NotFoundException.php:12:class NotFoundException extends AutowireException implements NotFoundExceptionInterface {}

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Http/src/LazyPipeline.php | sed -n '1,90p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "rg -n \"withMiddleware\\(|Autowire\" vendor/spiral/framework/src/Http/src/LazyPipeline.php vendor/spiral/framework/src/Router/src -g '*.php'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Http;
     6	
     7	use Psr\Container\ContainerInterface;
     8	use Psr\EventDispatcher\EventDispatcherInterface;
     9	use Psr\Http\Message\ResponseInterface as Response;
    10	use Psr\Http\Message\ServerRequestInterface as Request;
    11	use Psr\Http\Server\MiddlewareInterface;
    12	use Psr\Http\Server\RequestHandlerInterface;
    13	use Spiral\Core\Attribute\Proxy;
    14	use Spiral\Core\Container\Autowire;
    15	use Spiral\Http\Event\MiddlewareProcessing;
    16	use Spiral\Http\Exception\PipelineException;
    17	use Spiral\Telemetry\SpanInterface;
    18	use Spiral\Telemetry\TracerInterface;
    19	
    20	/**
    21	 * Pipeline used to pass request and response thought the chain of middleware.
    22	 * This kind of pipeline creates middleware on the fly.
    23	 */
    24	final class LazyPipeline implements RequestHandlerInterface, MiddlewareInterface
    25	{
    26	    /**
    27	     * Set of middleware to be applied for every request.
    28	     *
    29	     * @var list<MiddlewareInterface|Autowire|string>
    30	     */
    31	    protected array $middleware = [];
    32	
    33	    private ?RequestHandlerInterface $handler = null;
    34	    private int $position = 0;
    35	
    36	    /**
    37	     * Trace span for the current pipeline run.
    38	     */
    39	    private ?SpanInterface $span = null;
    40	
    41	    public function __construct(
    42	        #[Proxy] private readonly ContainerInterface $container,
    43	        private readonly ?EventDispatcherInterface $dispatcher = null,
    44	    ) {}
    45	
    46	    /**
    47	     * Add middleware to the pipeline.
    48	     *
    49	     * @param MiddlewareInterface|Autowire|non-empty-string ...$middleware List of middleware or its definition.
    50	     */
    51	    public function withAddedMiddleware(MiddlewareInterface|Autowire|string ...$middleware): self
    52	    {
    53	        $pipeline = clone $this;
    54	        $pipeline->middleware = \array_merge($pipeline->middleware, $middleware);
    55	        return $pipeline;
    56	    }
    57	
    58	    /**
    59	     * Replace middleware in the pipeline.
    60	     *
    61	     * @param MiddlewareInterface ...$middleware List of middleware or its definition.
    62	     */
    63	    public function withMiddleware(MiddlewareInterface|Autowire|string ...$middleware): self
    64	    {
    65	        $pipeline = clone $this;
    66	        $pipeline->middleware = $middleware;
    67	        return $pipeline;
    68	    }
    69	
    70	    /**
    71	     * Configures pipeline with target endpoint.
    72	     *
    73	     * @throws PipelineException
    74	     */
    75	    public function withHandler(RequestHandlerInterface $handler): self
    76	    {
    77	        $pipeline = clone $this;
    78	        $pipeline->handler = $handler;
    79	        return $pipeline;
    80	    }
    81	
    82	    public function process(Request $request, RequestHandlerInterface $handler): Response
    83	    {
    84	        return $this->withHandler($handler)->handle($request);
    85	    }
    86	
    87	    public function handle(Request $request): Response
    88	    {
    89	        $this->handler === null and throw new PipelineException('Unable to run pipeline, no handler given.');
    90	

 succeeded in 0ms:
vendor/spiral/framework/src/Http/src/LazyPipeline.php:14:use Spiral\Core\Container\Autowire;
vendor/spiral/framework/src/Http/src/LazyPipeline.php:29:     * @var list<MiddlewareInterface|Autowire|string>
vendor/spiral/framework/src/Http/src/LazyPipeline.php:49:     * @param MiddlewareInterface|Autowire|non-empty-string ...$middleware List of middleware or its definition.
vendor/spiral/framework/src/Http/src/LazyPipeline.php:51:    public function withAddedMiddleware(MiddlewareInterface|Autowire|string ...$middleware): self
vendor/spiral/framework/src/Http/src/LazyPipeline.php:63:    public function withMiddleware(MiddlewareInterface|Autowire|string ...$middleware): self
vendor/spiral/framework/src/Router/src/Router.php:156:                $route = $route->withMiddleware(...$configurator->middleware);
vendor/spiral/framework/src/Router/src/RouteGroup.php:9:use Spiral\Core\Container\Autowire;
vendor/spiral/framework/src/Router/src/RouteGroup.php:18: * @psalm-type MiddlewareType = MiddlewareInterface|class-string<MiddlewareInterface>|non-empty-string|Autowire
vendor/spiral/framework/src/Router/src/RouteGroup.php:31:    private Autowire|HandlerInterface|CoreInterface|string|null $core = null;
vendor/spiral/framework/src/Router/src/RouteGroup.php:70:    public function setCore(Autowire|CoreInterface|HandlerInterface|string $core): self
vendor/spiral/framework/src/Router/src/RouteGroup.php:80:    public function addMiddleware(MiddlewareInterface|Autowire|string $middleware): self
vendor/spiral/framework/src/Router/src/RouteGroup.php:116:                    ->withMiddleware(...$this->middleware),
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:9:use Spiral\Core\Container\Autowire;
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:17: * @psalm-type MiddlewareType = MiddlewareInterface|class-string<MiddlewareInterface>|non-empty-string|Autowire
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:32:     * $route->withMiddleware(new CacheMiddleware(100));
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:33:     * $route->withMiddleware(ProxyMiddleware::class);
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:34:     * $route->withMiddleware(ProxyMiddleware::class, OtherMiddleware::class);
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:35:     * $route->withMiddleware([ProxyMiddleware::class, OtherMiddleware::class]);
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:42:    public function withMiddleware(...$middleware): RouteInterface
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:103:            return $pipeline->withMiddleware(...$this->middleware);
vendor/spiral/framework/src/Router/src/Target/AbstractTarget.php:14:use Spiral\Interceptors\Handler\AutowireHandler;
vendor/spiral/framework/src/Router/src/Target/AbstractTarget.php:100:                    $container->has(HandlerInterface::class) => new AutowireHandler($container),
vendor/spiral/framework/src/Router/src/PipelineFactory.php:10:use Spiral\Core\Container\Autowire;
vendor/spiral/framework/src/Router/src/PipelineFactory.php:43:            } elseif ($item instanceof Autowire) {

exec
/bin/zsh -lc "rg -n \"type MiddlewareType\" vendor/spiral/framework/src/Router/src -g '*.php' && rg -n \"MiddlewareType\" vendor/spiral/framework/src/Router/src -g '*.php'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
vendor/spiral/framework/src/Router/src/RouteGroup.php:18: * @psalm-type MiddlewareType = MiddlewareInterface|class-string<MiddlewareInterface>|non-empty-string|Autowire
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:17: * @psalm-type MiddlewareType = MiddlewareInterface|class-string<MiddlewareInterface>|non-empty-string|Autowire
vendor/spiral/framework/src/Router/src/RouteGroup.php:18: * @psalm-type MiddlewareType = MiddlewareInterface|class-string<MiddlewareInterface>|non-empty-string|Autowire
vendor/spiral/framework/src/Router/src/RouteGroup.php:28:    /** @var array<MiddlewareType> */
vendor/spiral/framework/src/Router/src/RouteGroup.php:78:     * @param MiddlewareType $middleware
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:17: * @psalm-type MiddlewareType = MiddlewareInterface|class-string<MiddlewareInterface>|non-empty-string|Autowire
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:25:    /** @psalm-var array<array-key, MiddlewareType> */
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:37:     * @param MiddlewareType|array{0:MiddlewareType[]} ...$middleware
vendor/spiral/framework/src/Router/src/Traits/PipelineTrait.php:51:        /** @var MiddlewareType[] $middleware */

codex
Почти закончил: собираю итог как список проблем и решений. Общая картина такая: план реализуем, но есть несколько настоящих блокеров в HTTP-level ошибках, OpenAPI-конфиге, конкурентном создании кодов и точности token storage.
exec
/bin/zsh -lc "nl -ba app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueuePublisher.php app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueSerializer.php | sed -n '1,260p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba app/config/queue.php | sed -n '1,190p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	use App\Modules\Media\Presentation\Job\ProcessMediaJob;
     6	use App\Modules\Outbox\Infrastructure\Queue\OutboxQueueSerializer;
     7	use App\Modules\Outbox\Infrastructure\Queue\OutboxQueueStatusInterceptor;
     8	use App\Modules\Outbox\Presentation\Job\OutboxDebugLogJob;
     9	use Spiral\Queue\Driver\SyncDriver;
    10	use Spiral\Queue\Interceptor\Consume\ErrorHandlerInterceptor;
    11	use Spiral\Queue\Interceptor\Consume\RetryPolicyInterceptor;
    12	use Spiral\RoadRunner\Jobs\Queue\AMQP\ExchangeType;
    13	use Spiral\RoadRunner\Jobs\Queue\AMQPCreateInfo;
    14	use Spiral\RoadRunner\Jobs\Queue\BeanstalkCreateInfo;
    15	use Spiral\RoadRunner\Jobs\Queue\MemoryCreateInfo;
    16	use Spiral\RoadRunner\Jobs\Queue\SQSCreateInfo;
    17	use Spiral\RoadRunnerBridge\Queue\Queue;
    18	
    19	/**
    20	 * Конфигурация очередей.
    21	 *
    22	 * @link https://spiral.dev/docs/queue-configuration and https://spiral.dev/docs/queue-roadrunner
    23	 */
    24	return [
    25	    /**
    26	     * Подключение очереди по умолчанию.
    27	     */
    28	    'default' => \env('QUEUE_CONNECTION', 'in-memory'),
    29	
    30	    /**
    31	     * Алиасы подключений для предметных очередей.
    32	     */
    33	    'aliases' => [
    34	        // 'mail-queue' => 'in-memory',
    35	        // 'rating-queue' => 'sync',
    36	    ],
    37	
    38	    /**
    39	     * Подключения очередей.
    40	     * Драйверы: "sync", "roadrunner".
    41	     *
    42	     * @link https://spiral.dev/docs/queue-configuration
    43	     */
    44	    'connections' => [
    45	        'sync' => [
    46	            // Задача будет выполнена сразу, без постановки в очередь.
    47	            'driver' => 'sync',
    48	        ],
    49	        'in-memory' => [
    50	            'driver' => 'roadrunner',
    51	            'pipeline' => 'memory',
    52	        ],
    53	        'rabbitmq' => [
    54	            'driver' => 'roadrunner',
    55	            'pipeline' => 'rabbitmq',
    56	        ],
    57	    ],
    58	
    59	    /**
    60	     * Динамические конвейеры для RoadRunner.
    61	     *
    62	     * @link https://spiral.dev/docs/queue-roadrunner#declaring-pipelines-in-configuration-file
    63	     * Список доступных очередей: {@link https://roadrunner.dev/docs/queues-overview#creating-a-new-queue}
    64	     */
    65	    'pipelines' => [
    66	        'memory' => [
    67	            'connector' => new MemoryCreateInfo('local'),
    68	            // Запускаем обработчик этого конвейера при старте.
    69	            // Обработчик можно поставить на паузу консольной командой.
    70	            // php app.php queue:pause local
    71	            'consume' => true,
    72	        ],
    73	        'rabbitmq' => [
    74	            'connector' => new AMQPCreateInfo(
    75	                name: 'rabbitmq',
    76	                prefetch: \max(1, (int) \env('RABBITMQ_QUEUE_PREFETCH', 100)),
    77	                queue: (string) \env('RABBITMQ_QUEUE_NAME', 'yoga_loka_jobs'),
    78	                exchange: (string) \env('RABBITMQ_EXCHANGE_NAME', 'yoga_loka_jobs'),
    79	                exchangeType: ExchangeType::from((string) \env('RABBITMQ_EXCHANGE_TYPE', ExchangeType::Direct->value)),
    80	                routingKey: (string) \env('RABBITMQ_ROUTING_KEY', 'yoga_loka_jobs'),
    81	                requeueOnFail: (bool) \filter_var(
    82	                    value: \env('RABBITMQ_REQUEUE_ON_FAIL', 'false'),
    83	                    filter: FILTER_VALIDATE_BOOL,
    84	                ),
    85	                durable: (bool) \filter_var(
    86	                    value: \env('RABBITMQ_QUEUE_DURABLE', 'true'),
    87	                    filter: FILTER_VALIDATE_BOOL,
    88	                ),
    89	                exchangeDurable: (bool) \filter_var(
    90	                    value: \env('RABBITMQ_EXCHANGE_DURABLE', 'true'),
    91	                    filter: FILTER_VALIDATE_BOOL,
    92	                ),
    93	            ),
    94	            'consume' => true,
    95	        ],
    96	        // 'amqp' => [
    97	        //     'connector' => new AMQPCreateInfo('bus', ...),
    98	        //     // Не запускаем обработчик этого конвейера при старте.
    99	        //     // Обработчик можно запустить консольной командой.
   100	        //     // php app.php queue:resume local
   101	        //     'consume' => false
   102	        // ],
   103	        //
   104	        // 'beanstalk' => [
   105	        //     'connector' => new BeanstalkCreateInfo('bus', ...),
   106	        // ],
   107	        //
   108	        // 'sqs' => [
   109	        //     'connector' => new SQSCreateInfo('amazon', ...),
   110	        // ],
   111	    ],
   112	
   113	    /**
   114	     * Сериализатор для преобразования payload задачи в строку и обратно.
   115	     *
   116	     * @link https://spiral.dev/docs/queue-jobs/#job-payload-serialization
   117	     */
   118	    'defaultSerializer' => 'json',
   119	
   120	    'registry' => [
   121	        /**
   122	         * Соответствие имён задач и обработчиков.
   123	         * Когда обработчик очереди получает задачу, он ищет обработчик задачи здесь.
   124	         *
   125	         * (QueueInterface)->push('ping', ["url" => "http://site.com"]);
   126	         *
   127	         * @link https://spiral.dev/docs/queue-jobs#job-handler-registry
   128	         */
   129	        'handlers' => [
   130	            // 'ping' => \App\Modules\System\Presentation\Job\Ping::class
   131	            OutboxDebugLogJob::class => OutboxDebugLogJob::class,
   132	            ProcessMediaJob::class => ProcessMediaJob::class,
   133	        ],
   134	
   135	        /**
   136	         * Соответствие имён задач и сериализаторов.
   137	         * При постановке задачи используется указанный сериализатор, при обработке он же используется для десериализации.
   138	         *
   139	         * @link https://spiral.dev/docs/queue-jobs#changing-serializer
   140	         */
   141	        'serializers' => [
   142	            // 'ping' => 'json',
   143	            // \App\Modules\System\Presentation\Job\Ping::class => 'json',
   144	            OutboxDebugLogJob::class => OutboxQueueSerializer::class,
   145	            ProcessMediaJob::class => OutboxQueueSerializer::class,
   146	        ],
   147	    ],
   148	
   149	    /**
   150	     * Interceptor позволяет подключиться к обработке задач до или после постановки и выполнения.
   151	     *
   152	     * @link https://spiral.dev/docs/queue-interceptors
   153	     */
   154	    'interceptors' => [
   155	        // 'push' => [],
   156	        // Порядок критичен: RetryPolicyInterceptor обязан стоять ниже (внутри)
   157	        // OutboxQueueStatusInterceptor, иначе исключение Job ещё не преобразовано в
   158	        // RetryException и статус-interceptor спутает «оставить на повтор» с
   159	        // «окончательно failed». Перестановка interceptor-ов местами или удаление
   160	        // политики ретраев молча инвертирует классификацию ошибок outbox.
   161	        'consume' => [
   162	            ErrorHandlerInterceptor::class,
   163	            OutboxQueueStatusInterceptor::class,
   164	            RetryPolicyInterceptor::class,
   165	        ],
   166	    ],
   167	
   168	    'driverAliases' => [
   169	        'sync' => SyncDriver::class,
   170	    ],
   171	];

exec
/bin/zsh -lc "nl -ba app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Outbox\Infrastructure\Queue;
     6	
     7	use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
     8	use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
     9	use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
    10	use App\Modules\Outbox\Domain\ValueObject\OutboxLastError;
    11	use App\Modules\Outbox\Domain\ValueObject\OutboxMaxAttempts;
    12	use App\Modules\Outbox\Repository\OutboxEventRepository;
    13	use App\Shared\Infrastructure\Configuration\Outbox\OutboxConfig;
    14	use Cycle\ORM\EntityManagerInterface;
    15	use Psr\Log\LoggerInterface;
    16	use Spiral\Core\CoreInterceptorInterface;
    17	use Spiral\Core\CoreInterface;
    18	use Spiral\Queue\Exception\RetryException;
    19	
    20	final readonly class OutboxQueueStatusInterceptor implements CoreInterceptorInterface
    21	{
    22	    public function __construct(
    23	        private OutboxEventRepository $outboxEventRepository,
    24	        private EntityManagerInterface $entityManager,
    25	        private OutboxConfig $outboxConfig,
    26	        private LoggerInterface $logger,
    27	        private OutboxQueueSerializer $outboxQueueSerializer,
    28	    ) {}
    29	
    30	    /**
    31	     * @param array<int|string, mixed> $parameters
    32	     */
    33	    #[\Override]
    34	    public function process(string $controller, string $action, array $parameters, CoreInterface $core): mixed
    35	    {
    36	        $outboxEventId = $this->outboxEventIdFromParameters($parameters);
    37	
    38	        if ($outboxEventId === null) {
    39	            $this->logger->debug(message: 'Outbox interceptor пропустил обычную задачу без outboxId.', context: [
    40	                'jobClass' => $controller,
    41	            ]);
    42	
    43	            return $core->callAction(controller: $controller, action: $action, parameters: $parameters);
    44	        }
    45	
    46	        $storedOutboxEvent = $this->outboxEventRepository->findById($outboxEventId);
    47	
    48	        if ($storedOutboxEvent === null) {
    49	            $this->logger->warning(message: 'Outbox interceptor не нашёл событие для задачи и не запустил Job.', context: [
    50	                'outboxId' => $outboxEventId->value(),
    51	                'jobClass' => $controller,
    52	            ]);
    53	
    54	            return null;
    55	        }
    56	
    57	        if ($storedOutboxEvent->isFinal()) {
    58	            $this->logger->debug(message: 'Outbox interceptor пропустил дубль уже финального события.', context: [
    59	                'outboxId' => $storedOutboxEvent->id->value(),
    60	                'outboxType' => $storedOutboxEvent->type->value(),
    61	                'status' => $storedOutboxEvent->status->value,
    62	            ]);
    63	
    64	            return null;
    65	        }
    66	
    67	        try {
    68	            $result = $core->callAction(controller: $controller, action: $action, parameters: $parameters);
    69	        } catch (\Throwable $exception) {
    70	            $this->recordJobFailure(storedOutboxEvent: $storedOutboxEvent, exception: $exception);
    71	
    72	            throw $exception;
    73	        }
    74	
    75	        if ($this->eventBecameFinalDuringJob($storedOutboxEvent)) {
    76	            return $result;
    77	        }
    78	
    79	        $now = new \DateTimeImmutable();
    80	        $storedOutboxEvent->markHandled($now);
    81	        $this->entityManager->persist($storedOutboxEvent);
    82	        $this->entityManager->run();
    83	
    84	        $this->logger->debug(message: 'Outbox interceptor поставил handled.', context: [
    85	            'outboxId' => $storedOutboxEvent->id->value(),
    86	            'outboxType' => $storedOutboxEvent->type->value(),
    87	        ]);
    88	
    89	        return $result;
    90	    }
    91	
    92	    /**
    93	     * @param array<int|string, mixed> $parameters
    94	     */
    95	    private function outboxEventIdFromParameters(array $parameters): OutboxEventId|null
    96	    {
    97	        $outboxQueueHeaders = $this->outboxQueueHeadersFromParameters($parameters);
    98	        $outboxQueueEnvelope = $this->outboxQueueEnvelopeFromParameters($parameters);
    99	
   100	        if (
   101	            $outboxQueueHeaders->outboxId !== null
   102	            && $outboxQueueEnvelope !== null
   103	            && (
   104	                $outboxQueueHeaders->outboxId !== $outboxQueueEnvelope->outboxEventId->value()
   105	                || (
   106	                    $outboxQueueHeaders->outboxType !== null
   107	                    && $outboxQueueHeaders->outboxType !== $outboxQueueEnvelope->outboxEventType->value()
   108	                )
   109	            )
   110	        ) {
   111	            $this->logger->warning(message: 'Outbox interceptor обнаружил несовпадение outboxId в headers и payload.', context: [
   112	                'headerOutboxId' => $outboxQueueHeaders->outboxId,
   113	                'payloadOutboxId' => $outboxQueueEnvelope->outboxEventId->value(),
   114	                'headerOutboxType' => $outboxQueueHeaders->outboxType,
   115	                'payloadOutboxType' => $outboxQueueEnvelope->outboxEventType->value(),
   116	            ]);
   117	
   118	            throw new \UnexpectedValueException('Outbox interceptor получил разные outbox-данные в headers и payload.');
   119	        }
   120	
   121	        if ($outboxQueueHeaders->outboxId !== null) {
   122	            return OutboxEventId::fromString($outboxQueueHeaders->outboxId);
   123	        }
   124	
   125	        return $outboxQueueEnvelope?->outboxEventId;
   126	    }
   127	
   128	    /**
   129	     * @param array<int|string, mixed> $parameters
   130	     */
   131	    private function outboxQueueHeadersFromParameters(array $parameters): OutboxQueueHeaders
   132	    {
   133	        if (!\array_key_exists(key: 'headers', array: $parameters) || !\is_array($parameters['headers'])) {
   134	            return new OutboxQueueHeaders(
   135	                outboxId: null,
   136	                outboxType: null,
   137	            );
   138	        }
   139	
   140	        return OutboxQueueHeaders::fromHeaders($parameters['headers']);
   141	    }
   142	
   143	    /**
   144	     * @param array<int|string, mixed> $parameters
   145	     */
   146	    private function outboxQueueEnvelopeFromParameters(array $parameters): OutboxQueueEnvelope|null
   147	    {
   148	        if (!\array_key_exists(key: 'payload', array: $parameters)) {
   149	            return null;
   150	        }
   151	
   152	        $payload = $parameters['payload'];
   153	
   154	        if ($payload instanceof OutboxQueueEnvelope) {
   155	            return $payload;
   156	        }
   157	
   158	        if (!\is_array($payload)) {
   159	            return null;
   160	        }
   161	
   162	        if (!\array_key_exists(key: OutboxQueueHeaders::OUTBOX_ID, array: $payload)
   163	            || !\array_key_exists(key: OutboxQueueHeaders::OUTBOX_TYPE, array: $payload)) {
   164	            $this->logger->warning(message: 'Outbox interceptor получил payload-массив без обоих outbox-ключей и не считает его outbox-задачей.', context: [
   165	                'hasOutboxId' => \array_key_exists(key: OutboxQueueHeaders::OUTBOX_ID, array: $payload),
   166	                'hasOutboxType' => \array_key_exists(key: OutboxQueueHeaders::OUTBOX_TYPE, array: $payload),
   167	            ]);
   168	
   169	            return null;
   170	        }
   171	
   172	        if (!\is_string($payload[OutboxQueueHeaders::OUTBOX_ID]) || !\is_string($payload[OutboxQueueHeaders::OUTBOX_TYPE])) {
   173	            $this->logger->warning(message: 'Outbox interceptor получил payload-массив с нестроковыми outbox-ключами и не считает его outbox-задачей.', context: [
   174	                'outboxIdType' => \get_debug_type($payload[OutboxQueueHeaders::OUTBOX_ID]),
   175	                'outboxTypeType' => \get_debug_type($payload[OutboxQueueHeaders::OUTBOX_TYPE]),
   176	            ]);
   177	
   178	            return null;
   179	        }
   180	
   181	        return $this->outboxQueueSerializer->envelopeFromTransportPayload([
   182	            OutboxQueueHeaders::OUTBOX_ID => $payload[OutboxQueueHeaders::OUTBOX_ID],
   183	            OutboxQueueHeaders::OUTBOX_TYPE => $payload[OutboxQueueHeaders::OUTBOX_TYPE],
   184	        ]);
   185	    }
   186	
   187	    private function recordJobFailure(StoredOutboxEvent $storedOutboxEvent, \Throwable $exception): void
   188	    {
   189	        if ($this->eventBecameFinalDuringJob($storedOutboxEvent)) {
   190	            return;
   191	        }
   192	
   193	        $now = new \DateTimeImmutable();
   194	        $lastError = OutboxLastError::fromThrowable($exception);
   195	
   196	        if ($exception instanceof RetryException) {
   197	            $storedOutboxEvent->recordJobRetry(
   198	                lastError: $lastError,
   199	                outboxMaxAttempts: $this->outboxMaxAttempts(),
   200	                now: $now,
   201	            );
   202	        } else {
   203	            $storedOutboxEvent->markFailed(
   204	                lastError: $lastError,
   205	                outboxMaxAttempts: $this->outboxMaxAttempts(),
   206	                now: $now,
   207	            );
   208	        }
   209	
   210	        $this->entityManager->persist($storedOutboxEvent);
   211	        $this->entityManager->run();
   212	
   213	        $jobFailureContext = [
   214	            'outboxId' => $storedOutboxEvent->id->value(),
   215	            'outboxType' => $storedOutboxEvent->type->value(),
   216	            'status' => $storedOutboxEvent->status->value,
   217	            'attempts' => $storedOutboxEvent->attempts->value(),
   218	            'errorClass' => $exception::class,
   219	        ];
   220	

exec
/bin/zsh -lc "rg -n \"MailerInterface|MailMessage|SendIt|send\\(\" app/src tests vendor/spiral/framework/src/SendIt -g '*.php' | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
vendor/spiral/framework/src/SendIt/src/MessageSerializer.php:5:namespace Spiral\SendIt;
vendor/spiral/framework/src/SendIt/src/Renderer/ViewRenderer.php:5:namespace Spiral\SendIt\Renderer;
vendor/spiral/framework/src/SendIt/src/Renderer/ViewRenderer.php:10:use Spiral\SendIt\Event\PostRender;
vendor/spiral/framework/src/SendIt/src/Renderer/ViewRenderer.php:11:use Spiral\SendIt\Event\PreRender;
vendor/spiral/framework/src/SendIt/src/Renderer/ViewRenderer.php:12:use Spiral\SendIt\RendererInterface;
vendor/spiral/framework/src/SendIt/src/Event/PreRender.php:5:namespace Spiral\SendIt\Event;
vendor/spiral/framework/src/SendIt/src/Config/MailerConfig.php:5:namespace Spiral\SendIt\Config;
vendor/spiral/framework/src/SendIt/src/TransportResolverInterface.php:5:namespace Spiral\SendIt;
vendor/spiral/framework/src/SendIt/src/RendererInterface.php:5:namespace Spiral\SendIt;
vendor/spiral/framework/src/SendIt/src/Event/MessageNotSent.php:5:namespace Spiral\SendIt\Event;
vendor/spiral/framework/src/SendIt/src/MailJob.php:5:namespace Spiral\SendIt;
vendor/spiral/framework/src/SendIt/src/MailJob.php:10:use Spiral\SendIt\Config\MailerConfig;
vendor/spiral/framework/src/SendIt/src/MailJob.php:11:use Spiral\SendIt\Event\MessageNotSent;
vendor/spiral/framework/src/SendIt/src/MailJob.php:12:use Spiral\SendIt\Event\MessageSent;
vendor/spiral/framework/src/SendIt/src/MailJob.php:14:use Symfony\Component\Mailer\MailerInterface as SymfonyMailer;
vendor/spiral/framework/src/SendIt/src/MailJob.php:49:            $this->mailer->send($email);
vendor/spiral/framework/src/SendIt/src/TransportResolver.php:5:namespace Spiral\SendIt;
vendor/spiral/framework/src/SendIt/src/MailQueue.php:5:namespace Spiral\SendIt;
vendor/spiral/framework/src/SendIt/src/MailQueue.php:7:use Spiral\Mailer\MailerInterface;
vendor/spiral/framework/src/SendIt/src/MailQueue.php:11:use Spiral\SendIt\Config\MailerConfig;
vendor/spiral/framework/src/SendIt/src/MailQueue.php:13:final class MailQueue implements MailerInterface
vendor/spiral/framework/src/SendIt/src/MailQueue.php:22:    public function send(MessageInterface ...$message): void
vendor/spiral/framework/src/SendIt/src/Listener/LoggerListener.php:5:namespace Spiral\SendIt\Listener;
vendor/spiral/framework/src/SendIt/src/Listener/LoggerListener.php:8:use Spiral\SendIt\Event\MessageNotSent;
vendor/spiral/framework/src/SendIt/src/Listener/LoggerListener.php:9:use Spiral\SendIt\Event\MessageSent;
vendor/spiral/framework/src/SendIt/src/Event/MessageSent.php:5:namespace Spiral\SendIt\Event;
vendor/spiral/framework/src/SendIt/src/Bootloader/MailerBootloader.php:5:namespace Spiral\SendIt\Bootloader;
vendor/spiral/framework/src/SendIt/src/Bootloader/MailerBootloader.php:14:use Spiral\Mailer\MailerInterface;
vendor/spiral/framework/src/SendIt/src/Bootloader/MailerBootloader.php:18:use Spiral\SendIt\Config\MailerConfig;
vendor/spiral/framework/src/SendIt/src/Bootloader/MailerBootloader.php:19:use Spiral\SendIt\MailJob;
vendor/spiral/framework/src/SendIt/src/Bootloader/MailerBootloader.php:20:use Spiral\SendIt\MailQueue;
vendor/spiral/framework/src/SendIt/src/Bootloader/MailerBootloader.php:21:use Spiral\SendIt\TransportRegistryInterface;
vendor/spiral/framework/src/SendIt/src/Bootloader/MailerBootloader.php:22:use Spiral\SendIt\TransportResolver;
vendor/spiral/framework/src/SendIt/src/Bootloader/MailerBootloader.php:23:use Spiral\SendIt\TransportResolverInterface;
vendor/spiral/framework/src/SendIt/src/Bootloader/MailerBootloader.php:25:use Symfony\Component\Mailer\MailerInterface as SymfonyMailer;
vendor/spiral/framework/src/SendIt/src/Bootloader/MailerBootloader.php:64:            MailerInterface::class,
vendor/spiral/framework/src/SendIt/src/Event/PostRender.php:5:namespace Spiral\SendIt\Event;
vendor/spiral/framework/src/SendIt/src/Bootloader/BuilderBootloader.php:5:namespace Spiral\SendIt\Bootloader;
vendor/spiral/framework/src/SendIt/src/Bootloader/BuilderBootloader.php:8:use Spiral\SendIt\Renderer\ViewRenderer;
vendor/spiral/framework/src/SendIt/src/Bootloader/BuilderBootloader.php:9:use Spiral\SendIt\RendererInterface;
vendor/spiral/framework/src/SendIt/views/builder.dark.php:8:$_msg_->subject(\Spiral\SendIt\Renderer\ViewRenderer::escapeSubject(inject('subject')));
vendor/spiral/framework/src/SendIt/src/TransportRegistryInterface.php:5:namespace Spiral\SendIt;
tests/Kernel/Shared/Infrastructure/Configuration/SimpleConfigBindingTest.php:23:use Spiral\SendIt\Config\MailerConfig as SpiralMailerConfig;
app/src/Shared/Infrastructure/Framework/Kernel.php:27:use Spiral\SendIt\Bootloader\MailerBootloader;

codex
Ещё один фактический пропуск: одной регистрации пары `LoginCodeRequested → SendLoginCodeJob` в outbox registry недостаточно. В `app/config/queue.php` текущие outbox Job явно прописаны и как handlers, и как serializers; новый Job иначе может не обработаться или уйти с неправильной сериализацией.
exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Config/src/Patch/Append.php | sed -n '1,120p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Config\Patch;
     6	
     7	use Spiral\Config\Exception\DotNotFoundException;
     8	use Spiral\Config\Exception\PatchException;
     9	use Spiral\Config\Patch\Traits\DotTrait;
    10	use Spiral\Config\PatchInterface;
    11	
    12	final class Append implements PatchInterface
    13	{
    14	    use DotTrait;
    15	
    16	    private string $position;
    17	
    18	    public function __construct(
    19	        string $position,
    20	        private ?string $key,
    21	        private mixed $value,
    22	    ) {
    23	        $this->position = $position === '.' ? '' : $position;
    24	    }
    25	
    26	    public function patch(array $config): array
    27	    {
    28	        try {
    29	            $target = &$this->dotGet($config, $this->position);
    30	
    31	            if ($this->key !== null) {
    32	                $target[$this->key] = $this->value;
    33	            } else {
    34	                $target[] = $this->value;
    35	            }
    36	        } catch (DotNotFoundException $e) {
    37	            throw new PatchException($e->getMessage(), $e->getCode(), $e);
    38	        }
    39	
    40	        return $config;
    41	    }
    42	}

exec
/bin/zsh -lc "rg -n \"class Append\" vendor/spiral/framework/src/Config -g '*.php'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
vendor/spiral/framework/src/Config/src/Patch/Append.php:12:final class Append implements PatchInterface

codex
- [итог] План в целом реализуем, но не готов к исполнению без правок — сначала закрыть блокеры по HTTP-ошибкам, очереди, OpenAPI, гонкам кодов и token storage.

- [блокер] `RequireAuthenticatedMiddleware` не должен бросать `AuthenticationException` — это HTTP middleware, оно не проходит через `ApiExceptionInterceptor` и может стать 500/debug JSON; вернуть 401 JSON напрямую, как `RateLimitMiddleware`.

- [блокер] `SendLoginCodeJob` не добавлен в `app/config/queue.php` — outbox registry выберет Job, но очередь ещё должна знать handler и `OutboxQueueSerializer`; добавить Job в `handlers` и `serializers`.

- [блокер] OpenAPI сейчас сканирует только `System/Presentation/Http` — Auth-контроллеры не попадут в YAML; расширить `OpenApiConfig` до нескольких путей/namespace или сканировать `app/src/Modules`.

- [блокер] `code/request` может создать два активных кода при параллельных запросах, если активной строки ещё нет — `FOR UPDATE` не блокирует отсутствие строки; добавить частичный уникальный индекс на активный код по email или отдельную таблицу/строку блокировки email.

- [риск] `findLatestByEmail` для троттлинга 60 секунд опасен, если он найдёт уже consumed-код — ответ будет 200 без письма и без активного кода; явно искать только активный код или явно описать другое поведение и покрыть тестом.

- [риск] `RateLimitMiddleware` строит ключ из IP + маршрут/путь, но PSR-16 ключи плохо совместимы с `/`, `:`, IPv6 и сырыми путями — хэшировать нормализованную строку `sha256(ip|routeName)`.

- [риск] План говорит «Redis», но Docker dev переопределяет `CACHE_STORAGE=local`; rate limit будет локальным на процесс, не общим — либо принять как dev-режим, либо явно брать named storage `redis`.

- [риск] В `RateLimitMiddleware` надо явно указать способ ответа — проще вернуть `ErrorResponse(...)->withStatus(HttpStatus::TooManyRequests)->toResponse()`, а не вручную собирать PSR-7 тело.

- [блокер] В фазе `CycleTokenStorage` публичные `create()` и `delete()` описаны без обязательного `run()` — vendor `AuthMiddleware::closeContext()` вызывает `delete()` и ожидает реальное удаление; сделать публичные методы self-flushing или разделить на приватные no-flush helpers.

- [риск] `revokeSession()` мутирует токены через `findBySessionId` без `forUpdate`, хотя решение плана требует блокировки при отзыве — добавить `findBySessionIdForUpdate()` и использовать его в logout/rotate.

- [нарушение] `AuthTokenRepository::findByUserId()` добавлен «на будущее», но сценария revoke-all нет — это мёртвый код по правилам; убрать из плана или добавить реальный use-case.

- [реализуемо] `CycleTokenStorage::load()` без `forUpdate` — правильное решение для каждого Bearer-запроса; оставить обычное чтение и тест на отсутствие лишней блокировки не нужен, достаточно тестов поведения.

- [реализуемо] `AuthTokenView::getID() = raw` — фактически обязательно: иначе `HeaderTransport::commitToken()` может вернуть клиенту хэш вместо исходного токена; оставить и покрыть тестом.

- [реализуемо] Разделение `VerifyLoginCode` на оркестратор и транзакционный `ResolveLoginCode` оправдано — иначе `attempts++` откатится вместе с 401; зафиксировать тестом «неверный код увеличил attempts, хотя HTTP получил 401».

- [реализуемо] `forUpdate()` в цепочке `$this->select()->...->forUpdate()->fetchOne()` корректен и совпадает с эталоном Outbox; оставить именно так.

- [реализуемо] Вложенная транзакция `CompleteRegistration -> CreateUser` фактически поддерживается Cycle через savepoint; тест нужен на 422 по нику: талон после ошибки должен остаться активным.

- [риск] `CreateUser` обещает 422 при занятом email/nickname, но конкурентная уникальность всё равно может дать 500 от базы — это допустимо по правилам проекта, но план должен прямо назвать этот принятый риск.

- [риск] `AuthBootloader` не нужно дублировать vendor `HttpAuthBootloader` и в `Kernel`, и в `defineDependencies()` — лучше добавить в `Kernel` только модульный `AuthBootloader`, а зависимости пусть подтянут vendor-bootloader; затем kernel-тест.

- [риск] Per-route auth на logout вместо всей группы `api` — правильное решение, но нужен HTTP-тест порядка middleware: Bearer access даёт `authSessionId`, refresh Bearer даёт 401, публичные маршруты не вызывают token `load()`.

- [риск] `AuthContextAttributeMiddleware` должен аккуратно проверять payload токена: `userID/sessionID/type` могут отсутствовать из-за чужого/будущего storage; при плохом payload возвращать 401 JSON, не notice/500.

- [тесты] Не хватает явных тестов на гонки: два параллельных `code/request`, два параллельных `verify`, два параллельных `refresh`, `logout` во время `refresh` — без них одноразовость и ротация проверены только линейно.

- [тесты] Добавить тесты конфигурации: Auth bootloader зарегистрировал transport/storage/provider, queue registry знает `SendLoginCodeJob`, `app/config/queue.php` использует `OutboxQueueSerializer`, OpenAPI содержит 5 Auth routes.
tokens used
202 062
- [итог] План в целом реализуем, но не готов к исполнению без правок — сначала закрыть блокеры по HTTP-ошибкам, очереди, OpenAPI, гонкам кодов и token storage.

- [блокер] `RequireAuthenticatedMiddleware` не должен бросать `AuthenticationException` — это HTTP middleware, оно не проходит через `ApiExceptionInterceptor` и может стать 500/debug JSON; вернуть 401 JSON напрямую, как `RateLimitMiddleware`.

- [блокер] `SendLoginCodeJob` не добавлен в `app/config/queue.php` — outbox registry выберет Job, но очередь ещё должна знать handler и `OutboxQueueSerializer`; добавить Job в `handlers` и `serializers`.

- [блокер] OpenAPI сейчас сканирует только `System/Presentation/Http` — Auth-контроллеры не попадут в YAML; расширить `OpenApiConfig` до нескольких путей/namespace или сканировать `app/src/Modules`.

- [блокер] `code/request` может создать два активных кода при параллельных запросах, если активной строки ещё нет — `FOR UPDATE` не блокирует отсутствие строки; добавить частичный уникальный индекс на активный код по email или отдельную таблицу/строку блокировки email.

- [риск] `findLatestByEmail` для троттлинга 60 секунд опасен, если он найдёт уже consumed-код — ответ будет 200 без письма и без активного кода; явно искать только активный код или явно описать другое поведение и покрыть тестом.

- [риск] `RateLimitMiddleware` строит ключ из IP + маршрут/путь, но PSR-16 ключи плохо совместимы с `/`, `:`, IPv6 и сырыми путями — хэшировать нормализованную строку `sha256(ip|routeName)`.

- [риск] План говорит «Redis», но Docker dev переопределяет `CACHE_STORAGE=local`; rate limit будет локальным на процесс, не общим — либо принять как dev-режим, либо явно брать named storage `redis`.

- [риск] В `RateLimitMiddleware` надо явно указать способ ответа — проще вернуть `ErrorResponse(...)->withStatus(HttpStatus::TooManyRequests)->toResponse()`, а не вручную собирать PSR-7 тело.

- [блокер] В фазе `CycleTokenStorage` публичные `create()` и `delete()` описаны без обязательного `run()` — vendor `AuthMiddleware::closeContext()` вызывает `delete()` и ожидает реальное удаление; сделать публичные методы self-flushing или разделить на приватные no-flush helpers.

- [риск] `revokeSession()` мутирует токены через `findBySessionId` без `forUpdate`, хотя решение плана требует блокировки при отзыве — добавить `findBySessionIdForUpdate()` и использовать его в logout/rotate.

- [нарушение] `AuthTokenRepository::findByUserId()` добавлен «на будущее», но сценария revoke-all нет — это мёртвый код по правилам; убрать из плана или добавить реальный use-case.

- [реализуемо] `CycleTokenStorage::load()` без `forUpdate` — правильное решение для каждого Bearer-запроса; оставить обычное чтение и тест на отсутствие лишней блокировки не нужен, достаточно тестов поведения.

- [реализуемо] `AuthTokenView::getID() = raw` — фактически обязательно: иначе `HeaderTransport::commitToken()` может вернуть клиенту хэш вместо исходного токена; оставить и покрыть тестом.

- [реализуемо] Разделение `VerifyLoginCode` на оркестратор и транзакционный `ResolveLoginCode` оправдано — иначе `attempts++` откатится вместе с 401; зафиксировать тестом «неверный код увеличил attempts, хотя HTTP получил 401».

- [реализуемо] `forUpdate()` в цепочке `$this->select()->...->forUpdate()->fetchOne()` корректен и совпадает с эталоном Outbox; оставить именно так.

- [реализуемо] Вложенная транзакция `CompleteRegistration -> CreateUser` фактически поддерживается Cycle через savepoint; тест нужен на 422 по нику: талон после ошибки должен остаться активным.

- [риск] `CreateUser` обещает 422 при занятом email/nickname, но конкурентная уникальность всё равно может дать 500 от базы — это допустимо по правилам проекта, но план должен прямо назвать этот принятый риск.

- [риск] `AuthBootloader` не нужно дублировать vendor `HttpAuthBootloader` и в `Kernel`, и в `defineDependencies()` — лучше добавить в `Kernel` только модульный `AuthBootloader`, а зависимости пусть подтянут vendor-bootloader; затем kernel-тест.

- [риск] Per-route auth на logout вместо всей группы `api` — правильное решение, но нужен HTTP-тест порядка middleware: Bearer access даёт `authSessionId`, refresh Bearer даёт 401, публичные маршруты не вызывают token `load()`.

- [риск] `AuthContextAttributeMiddleware` должен аккуратно проверять payload токена: `userID/sessionID/type` могут отсутствовать из-за чужого/будущего storage; при плохом payload возвращать 401 JSON, не notice/500.

- [тесты] Не хватает явных тестов на гонки: два параллельных `code/request`, два параллельных `verify`, два параллельных `refresh`, `logout` во время `refresh` — без них одноразовость и ротация проверены только линейно.

- [тесты] Добавить тесты конфигурации: Auth bootloader зарегистрировал transport/storage/provider, queue registry знает `SendLoginCodeJob`, `app/config/queue.php` использует `OutboxQueueSerializer`, OpenAPI содержит 5 Auth routes.
