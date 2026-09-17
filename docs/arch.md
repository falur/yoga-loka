# Архитектура YogaLoka

API-only сервер социальной сети для практикующих йогу. PHP 8.5, Spiral Framework, RoadRunner, Cycle ORM, PostgreSQL.

Форма — модульный монолит: один репозиторий, один runtime, один выпуск, предметные области изолированы как модули.

Модель — DDD с CQRS на уровне сценариев: Command изменяет состояние, Query читает. Event sourcing и отдельная база чтения не используются.

Статус: целевая архитектура согласована 2026-09-01, переезд кодовой базы на неё завершён 2026-09-17. Документ описывает действующее состояние `app/src`, а не намерение. Направления зависимостей и границы модулей проверяются автоматически при каждом обычном прогоне проверок — чем именно и что остаётся вне проверки, сказано в разделе «Проверка границ». Осознанные отступления перечислены в разделе «Осознанные отступления»; других расхождений с кодом документ не подразумевает. То, что правилом инструмента не выражено, остаётся на авторе: ориентируйся на этот документ, `docs/rules.md` и `docs/references.md`.

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
          Data/                        данные чтения, которые отдаёт Reader
          Result/                      переиспользуемые части ответа
          Exception/
        Infrastructure/
          Spiral/                      адаптеры и ресурсы Spiral Framework
            Bootloader/
            Configuration/
            PublicApi/                 реализации контрактов из Public
            Adapter/                   реализации собственных технических контрактов модуля
            Registry/                  реестры вариантов поверх конфигурации Spiral
            Queue/                     адаптеры очереди поверх Spiral Queue
            Http/
              Access/                  применение публичных атрибутов доступа до Controller
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
          Persistence/
            Cycle/
              Migration/
              Columns/
              Entity/                  модели хранения
              Mapper/                  Domain <-> Cycle Entity
              Repository/              реализации Domain Repository
              Read/                    реализации Reader
              Typecast/
          Cache/
          Client/                      внешние HTTP-клиенты
          Storage/
        Tests/
          Unit/{Domain,Application}/
          Integration/{Cycle,Spiral}/
          Feature/Spiral/
          Support/                     помощники и фикстуры для нескольких видов тестов модуля
    Shared/
      Domain/                          общие доменные примитивы
      Application/                     общие нейтральные формы сценариев
      Infrastructure/
        Persistence/
          Cycle/                       общие примитивы Cycle: базовый Repository, Select, Typecast
        Spiral/                        общая композиция и адаптеры Spiral
tests/                                 сквозные и межмодульные проверки
```

Состав модулей: `Access`, `Auth`, `Media`, `Notifications`, `Outbox`, `Posts`, `System`, `Tags`, `User`.

Дерево — целевая номенклатура разделов, а не опись существующих каталогов: раздел заводится вместе с первым своим классом, поэтому часть имён сегодня не занята ни одним модулем — `Domain/Event`, `Infrastructure/Cache` и `Shared/Application`. Папки внутри `Infrastructure/Spiral` создаются только при наличии соответствующего адаптера. Отдельного верхнеуровневого `Presentation` и папки `Infrastructure/Spiral/Presentation` нет. Папка `Tests/Common` не используется. В `Tests/Support` лежат помощники и фикстуры, которые нужны больше чем одному виду тестов модуля; помощник одного вида остаётся внутри него.

Верхнеуровневый список `Infrastructure/{Cache,Client,Storage}` представительный, а не исчерпывающий: каждая технология собирается в явно названной границе модуля-владельца (принцип — в разделе «Границы слоёв → Infrastructure»). Текущие дополнительные названные границы: `Media/Infrastructure/{Ffmpeg,Imagick}` (внешние библиотеки обработки медиа) и `Outbox/Infrastructure/{Relay,Serializer}` (не-Spiral технические границы очереди и сериализации).

## Имена ролей

Имя класса называет его роль и слой, поэтому граница видна без перехода к определению. Конвенции имён методов и констант — в `docs/rules.md`.

```text
Domain Entity           {Name}
Cycle Entity            Cycle{Name}Entity
Domain Repository       {Name}Repository
Cycle Repository        Cycle{Name}Repository
Reader                  {Name}Reader
Cycle Reader            Cycle{Name}Reader
Данные чтения           {Name}Data
Набор данных чтения     {Name}DataCollection
Страница данных чтения  {Name}PageData
Сценарий                {Action}Command, {Action}Query, {Action}Handler, {Action}Result
Публичный контракт      {Name}Contract
Реализация контракта    {Name}Provider
Межмодульный DTO        {Name}Dto
Mapper                  {Name}Mapper
Каталог имён колонок    {Entity}Columns
Typecast                {Value}Typecast
Типизированный конфиг   {Name}Config
```

## Самодостаточность модуля

Модуль содержит весь свой код, конфигурацию, миграции, переводы, шаблоны и тесты. Подключение модуля к приложению — регистрация его bootloader. Удаление модуля не оставляет его файлов в других папках.

Bootloader модуля регистрирует его конфигурацию, путь к миграциям и ресурсам, обработчики и технические реализации. Композиционный корень знает состав модулей и не повторяет их внутреннюю настройку.

Глобальными остаются только части без владельца среди модулей: `app/config`, `app/locale`, `tests`, `Shared/Infrastructure/Spiral/Kernel`.

## Границы слоёв

### Public

Единственная видимая соседям часть модуля. Содержит скаляры, публичные enum, публичные DTO, их типизированные списки, интеграционные события и публичные атрибуты доступа.

Не содержит: доменные Entity и ValueObject, Command, Query, Handler, Repository, Cycle-модели, HTTP Resource, классы фреймворка, реализации контрактов.

Публичный контракт описывает возможность модуля, не его внутренний сценарий. Изменяется как API: совместимо либо с согласованным переходом потребителей.

За публичным контрактом нет отдельной логики: его обслуживают сценарии Application. Реализация контракта живёт в `Infrastructure/Spiral/PublicApi`, вызывает Command или Query своего модуля и преобразует Result в DTO из `Public/Dto`. Новая возможность для соседа появляется как сценарий Application, а не как код за контрактом.

`Public/Event` — стабильные интеграционные события. Внутреннее доменное событие остаётся в `Domain/Event`. Application решает, какое доменное действие становится интеграционным событием.

### Domain

Бизнес-модель одного ограниченного контекста. Зависит только от PHP, собственного домена и `Shared/Domain`.

Сущность не содержит атрибуты Cycle, имя таблицы, typecast, HTTP-код, конфигурацию, logger и контейнер. Состояние меняется доменными методами. ValueObject проверяет и нормализует одно понятие. Корень агрегата защищает согласованность агрегата.

Интерфейс Repository находится в `Domain/Repository`, существует у корня агрегата, а не у таблицы, и работает с агрегатом. Внутренняя сущность агрегата сохраняется вместе с корнем.

Доменное исключение описывает нарушение бизнес-смысла; преобразование в HTTP-ответ выполняется на внешней границе.

### Application

Сценарии. Handler создаёт доменные типы из проверенных значений, загружает агрегаты через интерфейсы Repository, вызывает доменное поведение и сохраняет результат.

Command владеет границей транзакции и возвращает Result своего сценария. Query не меняет состояние и не открывает транзакцию записи. Свою часть handler берёт через Repository, когда ответу хватает полей агрегата, и через Reader, когда ответу нужен признак, которого в агрегате нет: флаг по зрителю, счётчик из соседней таблицы, склейка нескольких таблиц. Чужие части дочитывает через `Public` соседей и собирает Result.

Технические зависимости — хеширование, часы, токены, файлы, почта, внешние клиенты, read-проекции — описаны интерфейсами в `Application/Contract`, реализованы в Infrastructure.

К соседу Application обращается только через `{OtherModule}/Public`.

### Infrastructure

Реализует интерфейсы Domain, Application и собственного Public. Каждая технология собрана в явно названной границе.

Cycle Entity повторяет форму хранения, mapper преобразует её в доменную сущность и обратно, Cycle Repository скрывает ORM и `EntityManager`. Класс, реализующий одновременно контракт Cycle и Spiral, разделяется на два адаптера.

Reader реализует объявленный в `Application/Contract` порт чтения и содержит запрос к своим таблицам. Доменные сущности он не создаёт.

`Infrastructure/Spiral/PublicApi` реализует собственный `Public/Contract`. Это входной адаптер наравне с `Http`, `Console`, `Job` и `Temporal`: вызов приходит от соседнего модуля. Provider преобразует аргументы контракта в Command или Query, вызывает handler через шину и возвращает DTO из `Public/Dto`; бизнес-правил, ветвлений по ним и обращений к Repository, Reader и `Public` соседей он не содержит.

`Infrastructure/Spiral` содержит все прямые зависимости модуля от Spiral. Входные адаптеры `PublicApi`, `Http`, `Console`, `Job`, `Temporal` преобразуют входящий вызов в Command или Query и результат обратно, бизнес-правил не содержат. Filter отвечает за чтение и первичную проверку запроса, Resource — за JSON и OpenAPI. Импорт `Spiral\...` в `Public`, `Domain` и `Application` запрещён.

### Shared

Стабильные примитивы без владельца среди модулей: базовые идентификаторы, типизированные коллекции, срез курсорной страницы, общая техническая конфигурация, общие примитивы Cycle в `Infrastructure/Persistence/Cycle`, общие HTTP-ответы, композиционный корень.

`Shared` не импортирует бизнес-модули. Исключение — Kernel, собирающий bootloader-ы приложения.

## Направления зависимостей

```text
Domain         -> PHP, свой Domain, Shared/Domain
Public         -> PHP, свой Public, Public других модулей, Shared/Domain,
                  нейтральные контракты собственных Composer-пакетов
Application    -> свой Domain, свой Public, свои Application/Contract,
                  Public других модулей, Shared/Domain, Shared/Application,
                  нейтральные контракты собственных Composer-пакетов
Infrastructure -> свой Domain, свой Application, свой Public, своя Infrastructure/Spiral,
                  Public других модулей, Shared, Cycle ORM,
                  библиотека своей явно названной границы
Spiral         -> свой Domain, свой Application, свой Public, своя остальная Infrastructure,
                  Public других модулей, Shared, Spiral Framework, Cycle ORM,
                  нейтральные контракты и runtime собственных Composer-пакетов
Kernel         -> bootloader-ы модулей, общие bootloader-ы Shared/Infrastructure,
                  Spiral runtime
Другой модуль  -> только {TargetModule}/Public
```

`Domain` и `Public` не зависят друг от друга. Циклические межмодульные зависимости отсутствуют: общий смысл переносится к владельцу либо взаимодействие разделяется событием.

Часть строк таблицы шире, чем «слой ниже», и это норма, а не уступка:

- `Application` зависит от собственного `Public`, потому что именно Application решает, какое доменное действие становится интеграционным событием, и собирает публичный DTO своего модуля.
- `Application` зависит от `Shared/Domain`: это общие примитивы без владельца — `TypedCollection`, `CursorSlice`, `Locale`, `UserId`.
- `Infrastructure` модуля и его `Infrastructure/Spiral` — один слой, разделённый только ради изоляции прямой зависимости от Spiral, поэтому зависят друг от друга: типизированный `{Name}Config` лежит в `Infrastructure/Spiral/Configuration`, а читают его в том числе не-Spiral адаптеры (`Storage`, `Client`, `Ffmpeg`, `Imagick`, `Relay`).
- `Infrastructure/Spiral` зависит от собственного `Domain`: bootloader связывает интерфейсы `Domain/Repository` с их реализациями Cycle, а Resource отображает доменный enum.
- `Kernel` подключает не только bootloader-ы модулей: шесть общих bootloader-ов приложения (`AnnotationsBootloader`, `ConfigBootloader`, `ExceptionHandlerBootloader`, `LoggingBootloader`, `RoutesBootloader`, `AppBootloader`) лежат в `Shared/Infrastructure/Spiral/Bootloader`, поэтому `Shared/Infrastructure` для него открыт.

Runtime собственных Composer-пакетов (bootloader-ы, middleware, интерсепторы, генератор OpenAPI) — такая же прямая зависимость от Spiral, поэтому допущен только в `Infrastructure/Spiral`, `Shared/Infrastructure` и Kernel. Нейтральные контракты тех же пакетов — атрибуты `#[Transactional]`, `#[LogOperation]` и интерфейсы шин `GianTiaga\SpiralCqrs` — относятся к сценарию и доступны `Application`, `Public` и Infrastructure; `#[Transactional]` предписан разделом «Транзакции». Базовый переводимый контракт `GianTiaga\SpiralApiErrors\Exception\TranslatableException` доменные исключения модулей получают по наследству через `Shared/Domain` — см. «Осознанные отступления».

## Проверка границ

Направления зависимостей проверяет deptrac по конфигурации `deptrac.yaml` в корне проекта. Каждый слой каждого модуля описан там как набор классов, а разрешённые направления перечислены явно; направление, которого в списке нет, считается нарушением и роняет проверку. Ни baseline, ни `skip_violations` в конфигурации нет, и ни одна строка продуктивного кода из-под проверки не выведена.

Проверяется: направления между слоями одного модуля и между модулями; отсутствие `Spiral\` и `Cycle\` в `Domain`, `Application` и `Public`; обращение к соседу только через его `Public` — `Domain`, `Application` и `Infrastructure` соседа недоступны ни одному слою, чем закрыта и ORM-связь через границу модуля (цель связи Cycle импортируется обычным `use`); отсутствие импорта бизнес-модулей в `Shared`, кроме Kernel.

Запуск: `make deptrac` точечно и `make qa` в обычном прогоне проверок — там шаг `@deptrac` идёт в составном composer-скрипте `qa` рядом с `@cs`, `@phpstan` и `@test-coverage`.

Чего проверка не покрывает:

- Область анализа — `app/src` без `app/src/Modules/*/Tests/*`. `Tests` не является слоем таблицы направлений; это та же область, которую проект уже зафиксировал для PHPStan; фикстуре интеграционного теста нужно готовить состояния, которых публичный контракт соседа не выражает.
- Правило «каждая технология собрана в явно названной границе» инструментом не проверяется: `FFMpeg`, `Intervention\Image`, `Aws`, `CuyZ\Valinor` и прочий нейтральный вендор в слои не вынесены и остаются непокрытыми зависимостями — формально любой модуль может их импортировать без нарушения.
- Слой `Kernel` получает доступ к `{Module}Infrastructure/Spiral` целиком, а не к одним bootloader-ам модулей: слои deptrac обязаны быть взаимоисключающими, а bootloader лежит внутри `Infrastructure/Spiral`. Фактически `Kernel.php` импортирует ровно девять классов `{Module}Bootloader`, но импорт другого Spiral-класса модуля инструмент не запретит.

## Владение данными

Каждая таблица имеет одного владельца-модуль; только его Infrastructure читает и меняет её. Общая схема PostgreSQL владение не меняет.

Модуль не создаёт ORM relation на Entity другого модуля, не использует его Repository и не читает его таблицы. Межмодульный идентификатор хранится как собственный объект-значение ссылки без навигации ORM. Для `Media` это правило действует без исключений: другие модули хранят идентификаторы медиа, а проверку, пакетное чтение URL и изменение состояния выполняют через `Media/Public`; публичный контракт для списков принимает набор идентификаторов.

Межмодульные внешние ключи не используются как основа согласованности. Согласованность обеспечивает синхронный публичный контракт в общей локальной транзакции либо интеграционное событие с идемпотентным потребителем.

Миграция находится в `Infrastructure/Persistence/Cycle/Migration` модуля-владельца и меняет только его таблицы. Bootloader регистрирует путь в общем механизме миграций. Имена миграций сохраняют единый порядок в рамках приложения.

## Транзакции

Command handler задаёт границу бизнес-транзакции через `#[Transactional]`. Все изменения агрегатов и запись outbox-событий завершаются в этой транзакции.

Одна транзакция может включать синхронный публичный контракт соседнего модуля: один процесс и одна PostgreSQL. Каждый модуль меняет только свои таблицы через свою реализацию.

Прямая массовая запись выполняется отдельным инфраструктурным портом — только для операции без поэлементных доменных инвариантов и с неограниченным размером набора — и не маскируется под Repository агрегата.

## Чтение и сборка ответа

К базе обращаются два вида классов, и их роли не пересекаются.

Repository работает с агрегатом: читает и пишет таблицы своего агрегата, сколько бы их ни было, и возвращает Entity, коллекцию Entity, `null`, `bool`, счётчик или `void`. Repository ничего не компонует и не имеет зависимостей, кроме собственной выборки, поэтому не может вызвать другой Repository, Reader, шину или соседний модуль.

Reader работает с данными: читает любые таблицы своего модуля и возвращает Data — типизированные значения чтения без доменного поведения. Reader не создаёт доменные сущности, не применяет бизнес-правила и не обращается к соседям. Ряд выборки превращает в объект фабрика самого Data, поэтому ключи ряда читаются в одном месте.

Handler собирает ответ: Command handler изменяет состояние через Repository и Domain; Query handler берёт Entity от Repository или Data от Reader — по форме ответа — и дочитывает недостающие части через `Public` соседей. Оба возвращают Result.

```text
Repository -> Entity
Reader     -> Data
Handler    -> Result
```

Возвращаемый тип определяет слой, поэтому граница видна в сигнатуре.

Данные соседнего модуля читаются только через его `Public`, набором идентификаторов и одним вызовом на ответ. Join через границу модуля запрещён: чужая таблица принадлежит чужому модулю. Межмодульное обращение остаётся в Application — ни Repository, ни Reader за границу модуля не выходят.

Command handler не обращается к Reader. Если ответу нужна полная форма изменённого объекта, её читает отдельный Query.

Разные условия показа — разные сценарии. Своя лента и чужая лента это два Query, два метода Reader и два Result, а не один сценарий с вычислением роли зрителя.

Промежуточной read-модели между Entity или Data и Result нет. Форма ответа сценария лежит рядом со своим Command или Query, переиспользуемые части ответа — в `Application/Result`.

## Взаимодействие модулей

Синхронный вызов — когда результат нужен текущему ответу или изменения должны завершиться одной транзакцией.

```text
Infrastructure/Spiral/{Transport} модуля A
  -> Application handler A
    -> Public/Contract модуля B
      -> Infrastructure/Spiral/PublicApi модуля B
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
     -> Repository или Reader и Public соседей -> Result
     -> Resource/Response -> JSON

Очередь, консоль, Temporal:
Transport adapter -> Command или Query -> Bus -> Application handler
```

## HTTP API, ошибки и доступ

OpenAPI генерируется из типизированных Controller, Filter, Response, Resource и enum. Публичная HTTP-форма, публичный DTO, Application Result и доменная Entity — разные формы и не подменяют друг друга.

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

## Осознанные отступления

Отступления приняты сознательно, каждое проверяемо по коду и ни одно не выведено из-под автоматической проверки границ. Другие расхождения с текстом документа расхождениями не считаются — они ошибки и чинятся.

Перечень архитектурный: он называет расхождения с правилами этого документа, а не все точечные послабления инструментов. Подавления статического анализа живут с причиной рядом — в комментарии у строки и в `ignoreErrors` файла `phpstan.neon`; сюда попало только то из них, что говорит о форме кода, а не об особенности анализатора.

### `UserId` живёт в `Shared/Domain/ValueObject`, а не в модуле User

`App\Shared\Domain\ValueObject\UserId` — общий примитив без поведения: идентификатор пользователя, который принято передавать между модулями. Его импортируют 111 файлов вне тестов в семи модулях (`Access`, `Auth`, `Media`, `Notifications`, `Posts`, `Tags`, `User`). Перенос к владельцу потребовал бы в каждом соседе собственного объекта-ссылки ради формальной чистоты; общий примитив без владельца — легитимное содержимое `Shared/Domain`, поэтому он там и остаётся.

### Модуль `Access` не подключён ни к одному маршруту

Доменная модель ролей и прав готова (`app/src/Modules/Access/Domain/{Entity,ValueObject,Enum,Collection,Repository}`), `AccessBootloader` зарегистрирован в `Shared/Infrastructure/Spiral/Kernel`. Разделов `Application` и `Public` у модуля нет, публичного атрибута доступа он не предоставляет: в продукте сегодня нет маршрутов служебных действий, которым нужны его права. Разделы не создаются без кода и появятся вместе с первым таким маршрутом.

### Пара `add()`/`save()` в пяти доменных Repository

`RegistrationTicketRepository` и `LoginCodeRepository` (Auth), `StoredOutboxEventRepository` (Outbox), `PostRepository` и `CommentRepository` (Posts) объявляют оба метода: `add()` ставит агрегат в текущую запись без прогона, `save()` сохраняет своим прогоном вместе со всем, что уже поставлено. Это делает единицу работы видимой в доменном интерфейсе — цена за сценарии с несколькими корнями агрегатов, которым нужен ровно один прогон `EntityManager`. Семантика одинакова во всех пяти и описана докблоком в каждом интерфейсе.

### `DomainTranslatableException` импортирует `GianTiaga\SpiralApiErrors`

`app/src/Shared/Domain/Exception/DomainTranslatableException` наследует `GianTiaga\SpiralApiErrors\Exception\TranslatableException` — нейтральный контракт собственного Composer-пакета, не фреймворк. Свой интерфейс на ту же роль дублировал бы принятый механизм перевода ошибок. Ни один класс `Domain` модулей не импортирует `GianTiaga\` напрямую: доменные исключения получают контракт по наследству через `Shared/Domain`, и deptrac переносит зависимость предка на наследника — поэтому слой контракта разрешён и для `{Module}Domain`.

### Четыре общих исключения `Shared/Domain/Exception` без потребителя в `app/src`

`NotFoundException`, `ForbiddenException`, `ValidationException` и `AuthenticationException` не выбрасывает ни один класс `app/src`: модули, которым нужны собственные исключения, завели их сами — переводимые доменные поверх `DomainTranslatableException` (Auth, Media, Notifications, Posts, System, User) и технические поверх `\DomainException` или `\Exception` (Media, Notifications, Outbox, System); у `Access` и `Tags` собственных исключений нет вовсе. Четыре общих остаются как переиспользуемые примитивы без владельца-модуля; их поведение закреплено `tests/Unit/Shared/Domain/Exception/ApiDomainExceptionTest.php` и тестовым контроллером `tests/App/Modules/System/Http/ApiErrorTestController.php`, который проверяет общий механизм `spiral-api-errors` независимо от бизнес-модуля. Удаление стоило бы либо этой сквозной проверки, либо её дублирования на бизнес-исключении.

### Три подавления `@phpstan-ignore varTag.nativeType` в `MediaMapper`

`app/src/Modules/Media/Infrastructure/Persistence/Cycle/Mapper/MediaMapper.php`, строки 139, 156 и 173: `HasMany`-свойства `CycleMediaEntity` типизированы доменными коллекциями, а Cycle на момент eager-load кладёт в них свои Entity. Подавление точечное, с причиной в комментарии рядом и минимальным путём — как требует `docs/rules.md`.

Прочие подавления в `app/src` архитектурными отступлениями не являются и здесь не разбираются: `Access/Infrastructure/Persistence/Cycle/Repository/CyclePermissionRepository.php:90` и `CycleRoleRepository.php:95` (`method.childReturnType`), `Shared/Infrastructure/Persistence/Cycle/AbstractRepository.php:42` (`property.readOnlyByPhpDocAssignOutOfClass`) — у каждого причина в комментарии рядом.
