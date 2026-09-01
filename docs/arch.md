# Архитектура YogaLoka

API-only сервер социальной сети для практикующих йогу. PHP 8.5, Spiral Framework, RoadRunner, Cycle ORM, PostgreSQL.

Форма — модульный монолит: один репозиторий, один runtime, один выпуск, предметные области изолированы как модули.

Модель — DDD с CQRS на уровне сценариев: Command изменяет состояние, Query читает. Event sourcing и отдельная база чтения не используются.

Статус: целевая архитектура согласована 2026-09-01, действует для нового и изменяемого кода. Отклонения существующего кода перечислены в конце документа.

## Структура

```text
app/
  config/                              конфигурация общего runtime и состава приложения
  locale/                              общие переводы приложения
  src/
    Modules/
      {Module}/
        Public/                        опубликованный язык модуля
          Contract/                    синхронные интерфейсы для других модулей
          Dto/                         данные межмодульного ответа
          Event/                       интеграционные события
          Enum/                        варианты, нужные другим модулям
          Attribute/                   декларации доступа для HTTP-слоя соседей
        Domain/
          Entity/                      сущности и корни агрегатов
          ValueObject/
          Enum/
          Collection/
          Service/                     доменные операции без естественного владельца
          Event/                       внутренние доменные события
          Repository/                  интерфейсы Repository агрегатов
          Exception/
        Application/
          Command/{Action}/            команды и обработчики изменения
          Query/{Action}/              запросы и обработчики чтения
          Contract/                    порты технических зависимостей
          View/                        read-model представления
          Exception/
        Infrastructure/
          Spiral/                      адаптеры и ресурсы Spiral Framework
            Bootloader/
            Configuration/
            Http/
              Controller/
              Filter/
              Middleware/
              Resource/
              Response/
            Console/
            Job/
            Temporal/
            Auth/
            Mail/
            Resources/
              locale/
              views/
          PublicApi/                   реализации контрактов из Public
          Persistence/
            Cycle/
              Migration/
              Columns/
              Entity/                  модели хранения
              Mapper/                  Domain <-> Cycle Entity
              Repository/              реализации Domain Repository
              Typecast/
          Cache/
          Client/                      внешние HTTP-клиенты
          Storage/
        Tests/
          Unit/{Domain,Application}/
          Integration/{Cycle,Spiral}/
          Feature/Spiral/
    Shared/
      Domain/                          общие доменные примитивы
      Application/                     общие нейтральные формы сценариев
      Infrastructure/
        Spiral/                        общая композиция и адаптеры Spiral
packages/                              внутренние независимые Composer-пакеты
tests/                                 сквозные и межмодульные проверки
```

Папки внутри `Infrastructure/Spiral` создаются только при наличии соответствующего адаптера. Отдельного верхнеуровневого `Presentation` и папки `Infrastructure/Spiral/Presentation` нет. Папка `Tests/Common` не используется.

## Самодостаточность модуля

Модуль содержит весь свой код, конфигурацию, миграции, переводы, шаблоны и тесты. Подключение модуля к приложению — регистрация его bootloader. Удаление модуля не оставляет его файлов в других папках.

Bootloader модуля регистрирует его конфигурацию, путь к миграциям и ресурсам, обработчики и технические реализации. Композиционный корень знает состав модулей и не повторяет их внутреннюю настройку.

Глобальными остаются только части без владельца среди модулей: `app/config`, `app/locale`, `tests`, `Shared/Infrastructure/Spiral/Kernel`.

## Границы слоёв

### Public

Единственная видимая соседям часть модуля. Содержит скаляры, публичные enum, публичные DTO, их типизированные списки, интеграционные события и публичные атрибуты доступа.

Не содержит: доменные Entity и ValueObject, Command, Query, Handler, Repository, Cycle-модели, HTTP Resource, классы фреймворка, реализации контрактов.

Публичный контракт описывает возможность модуля, не его внутренний сценарий. Изменяется как API: совместимо либо с согласованным переходом потребителей.

`Public/Event` — стабильные интеграционные события. Внутреннее доменное событие остаётся в `Domain/Event`. Application решает, какое доменное действие становится интеграционным событием.

### Domain

Бизнес-модель одного ограниченного контекста. Зависит только от PHP, собственного домена и `Shared/Domain`.

Сущность не содержит атрибуты Cycle, имя таблицы, typecast, HTTP-код, конфигурацию, logger и контейнер. Состояние меняется доменными методами. ValueObject проверяет и нормализует одно понятие. Корень агрегата защищает согласованность агрегата.

Интерфейс Repository находится в `Domain/Repository`, существует у корня агрегата, а не у таблицы, и работает с агрегатом. Внутренняя сущность агрегата сохраняется вместе с корнем.

Доменное исключение описывает нарушение бизнес-смысла; преобразование в HTTP-ответ выполняется на внешней границе.

### Application

Сценарии. Handler создаёт доменные типы из проверенных значений, загружает агрегаты через интерфейсы Repository, вызывает доменное поведение и сохраняет результат.

Command владеет границей транзакции. Query не меняет состояние и не открывает транзакцию записи; для сложного чтения использует read-контракт своего сценария и возвращает Result или View.

Технические зависимости — хеширование, часы, токены, файлы, почта, внешние клиенты, read-проекции — описаны интерфейсами в `Application/Contract`, реализованы в Infrastructure.

К соседу Application обращается только через `{OtherModule}/Public`.

### Infrastructure

Реализует интерфейсы Domain, Application и собственного Public. Каждая технология собрана в явно названной границе.

Cycle Entity повторяет форму хранения, mapper преобразует её в доменную сущность и обратно, Cycle Repository скрывает ORM и `EntityManager`. Класс, реализующий одновременно контракт Cycle и Spiral, разделяется на два адаптера.

`Infrastructure/PublicApi` реализует собственный `Public/Contract`, вызывает внутренний handler через шину и возвращает публичный DTO.

`Infrastructure/Spiral` содержит все прямые зависимости модуля от Spiral. Входные адаптеры `Http`, `Console`, `Job`, `Temporal` преобразуют внешний ввод в Command или Query и результат обратно, бизнес-правил не содержат. Filter отвечает за чтение и первичную проверку запроса, Resource — за JSON и OpenAPI. Импорт `Spiral\...` в `Public`, `Domain` и `Application` запрещён.

### Shared

Стабильные примитивы без владельца среди модулей: базовые идентификаторы, типизированные коллекции, общая техническая конфигурация, общие HTTP-ответы, композиционный корень.

`Shared` не импортирует бизнес-модули. Исключение — Kernel, собирающий bootloader-ы приложения.

## Направления зависимостей

```text
Domain         -> PHP, свой Domain, Shared/Domain
Public         -> PHP, свой Public, нейтральные контракты внутренних packages
Application    -> свой Domain, свои Application/Contract, Public других модулей
Infrastructure -> свой Domain, свой Application, свой Public, Shared/Infrastructure,
                  библиотека своей явно названной границы
Spiral         -> свой Application, свой Public, Public других модулей,
                  Shared/Infrastructure/Spiral, Spiral Framework
Kernel         -> bootloader-ы модулей и Spiral runtime
Другой модуль  -> только {TargetModule}/Public
```

`Domain` и `Public` не зависят друг от друга. Циклические межмодульные зависимости отсутствуют: общий смысл переносится к владельцу либо взаимодействие разделяется событием.

## Владение данными

Каждая таблица имеет одного владельца-модуль; только его Infrastructure читает и меняет её. Общая схема PostgreSQL владение не меняет.

Модуль не создаёт ORM relation на Entity другого модуля, не использует его Repository и не читает его таблицы. Межмодульный идентификатор хранится как собственный объект-значение ссылки без навигации ORM. Для `Media` это правило действует без исключений: другие модули хранят идентификаторы медиа, а проверку, пакетное чтение URL и изменение состояния выполняют через `Media/Public`; публичный контракт для списков принимает набор идентификаторов.

Межмодульные внешние ключи не используются как основа согласованности. Согласованность обеспечивает синхронный публичный контракт в общей локальной транзакции либо интеграционное событие с идемпотентным потребителем.

Миграция находится в `Infrastructure/Persistence/Cycle/Migration` модуля-владельца и меняет только его таблицы. Bootloader регистрирует путь в общем механизме миграций. Имена миграций сохраняют единый порядок в рамках приложения.

## Транзакции

Command handler задаёт границу бизнес-транзакции через `#[Transactional]`. Все изменения агрегатов и запись outbox-событий завершаются в этой транзакции.

Одна транзакция может включать синхронный публичный контракт соседнего модуля: один процесс и одна PostgreSQL. Каждый модуль меняет только свои таблицы через свою реализацию.

Прямая массовая запись выполняется отдельным инфраструктурным портом — только для операции без поэлементных доменных инвариантов и с неограниченным размером набора — и не маскируется под Repository агрегата.

## Взаимодействие модулей

Синхронный вызов — когда результат нужен текущему ответу или изменения должны завершиться одной транзакцией.

```text
Infrastructure/Spiral/{Transport} модуля A
  -> Application handler A
    -> Public/Contract модуля B
      -> Infrastructure/PublicApi модуля B
        -> Application handler B
          -> Domain B и Repository B
      <- Public/Dto модуля B
    -> Domain A и Repository A
```

Асинхронный вызов — действия после commit, повторы и независимые подписчики: почта, push, Centrifugo, обработка медиа.

```text
Command handler
  -> меняет агрегат
  -> создаёт Public/Event
  -> сохраняет событие в outbox в той же транзакции
  -> commit

Outbox relay -> RabbitMQ -> Job модуля-потребителя -> Application handler потребителя
```

Доставка имеет семантику at-least-once, потребитель идемпотентен по идентификатору outbox-события. Событие содержит только минимальные стабильные данные, без Entity, приватных полей и сырых ответов внешних сервисов.

## Потоки

```text
HTTP-команда:
HTTP -> middleware -> Filter -> Controller -> CommandBus -> Command handler
     -> Domain -> Domain Repository -> Cycle Repository -> PostgreSQL
     -> Resource/Response -> JSON

HTTP-запрос:
HTTP -> middleware -> Filter -> Controller -> QueryBus -> Query handler
     -> Domain Repository или read-контракт -> Result/View
     -> Resource/Response -> JSON

Очередь, консоль, Temporal:
Transport adapter -> Command или Query -> Bus -> Application handler
```

## HTTP API, ошибки и доступ

OpenAPI генерируется из типизированных Controller, Filter, Response, Resource и enum. Публичная HTTP-форма, публичный DTO, Application Result/View и доменная Entity — разные формы и не подменяют друг друга.

Ожидаемые ошибки представлены типизированными исключениями; пакет `spiral-api-errors` преобразует их и ошибки Filter в безопасные ответы. Неожиданная ошибка становится ответом 500 без исходного сообщения. Локаль определяется на HTTP-границе.

`Auth` владеет сессиями, токенами и установлением личности. `Access` владеет ролями и правами. Бизнес-модуль не импортирует их middleware и внутренние обработчики.

Маршрут объявляет требуемый доступ публичным атрибутом модуля-владельца: публичный маршрут, маршрут с действующей сессией или маршрут с конкретным правом. Общий HTTP-адаптер применяет атрибут до Controller. Доменная проверка владения ресурсом остаётся в сценарии целевого модуля.

## Runtime

Локальное окружение — `docker/docker-compose.dev.yml`.

Процессы:

- `app-http` — RoadRunner HTTP и consumer задач RabbitMQ;
- `temporal-worker` — worker очереди Temporal `default`;
- один постоянный `outbox:relay --loop`.

Инфраструктура: PostgreSQL, Redis, RabbitMQ, MinIO, Mailpit, Temporal, Temporal UI, Centrifugo. Redis — кэш, сессии и RoadRunner KV; основной брокер очередей — RabbitMQ.

Внешние границы принадлежат Infrastructure модуля-владельца:

- файлы и S3/MinIO — `Media`;
- почта входа — `Auth`;
- push и Centrifugo — `Notifications`;
- очередь и гарантированная доставка — `Outbox`;
- HTTP, OpenAPI и диагностика — `System` и `Infrastructure/Spiral` модулей.

## Модули и отклонения текущего кода

Модули: `Access`, `Auth`, `Media`, `Notifications`, `Outbox`, `Posts`, `System`, `Tags`, `User`.

Существующий код разделён на `Domain`, `Application`, `Infrastructure`, `Presentation` и верхнеуровневый `Repository`. Отклонения от целевой структуры на 2026-09-01:

- доменные сущности содержат атрибуты Cycle ORM и знают конкретные Repository;
- Repository являются конкретными Cycle-классами и находятся вне `Domain` и `Infrastructure`;
- папок `Public` в модулях нет, модули импортируют `Application`, `Domain` и `Presentation` соседей напрямую;
- `Posts` держит ORM-связь с доменной сущностью `Media` и использует middleware из `Auth`;
- сообщения и контракты `Outbox` раскрываются другим модулям через его внутренний `Application`, целевая граница — `Outbox/Public`;
- модульных папок `Tests` нет, тесты находятся в глобальном `tests`;
- автоматической блокирующей проверки границ по namespace и импортам нет.

Текущие межмодульные связи:

```text
Auth          -> User, Outbox
Media         -> Outbox
Notifications -> Media, Outbox
Posts         -> Auth, Media, Notifications, Tags, User
User          -> Media
```

Перечисленные отклонения не разрешают новые такие связи: новый и изменяемый код следует целевой структуре, существующие связи убираются отдельной миграцией.
