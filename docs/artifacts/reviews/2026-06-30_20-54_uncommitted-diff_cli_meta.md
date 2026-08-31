Reading additional input from stdin...
OpenAI Codex v0.141.0
--------
workdir: /Users/gian_tiaga/Code/yoga-loka-spiral-2
model: gpt-5.5
provider: openai
approval: never
sandbox: workspace-write [workdir, /tmp, $TMPDIR]
reasoning effort: high
reasoning summaries: none
session id: 019f19ae-3899-7e93-a4aa-719cd09d47b6
--------
user
Ты делаешь глубокое мета-ревью уже готового файла ревью в PHP/Spiral/Cycle проекте (модульный монолит, CQRS, DDD). Прочитай эти файлы по абсолютным путям: /Users/gian_tiaga/Code/yoga-loka-spiral-2/docs/reviews/2026-06-30_20-54_uncommitted-diff.md (само ревью), /Users/gian_tiaga/Code/yoga-loka-spiral-2/docs/rules.md, /Users/gian_tiaga/Code/yoga-loka-spiral-2/docs/arch.md. Target ревью — незакоммиченный git diff HEAD; полный diff лежит в /private/tmp/claude-501/-Users-gian-tiaga-Code-yoga-loka-spiral-2/9e35cc30-c943-40a1-bd4d-5f4a8ab1cf45/scratchpad/full_diff.txt, читай его и сами файлы в /Users/gian_tiaga/Code/yoga-loka-spiral-2/app/src. ВАЖНО: план не указан (plan: none) — НЕ проверяй выполнение плана и не угадывай файл плана; проверяй только правила (rules.md), архитектуру (arch.md) и фактическую корректность кода. Ревью — шестой круг доводки одного и того же changeset, сейчас содержит лишь ОДНО замечание про каталог Application/Service, не описанный в arch.md (тип docs, на усмотрение), оценка 94/100, обязательных замечаний нет. Глубоко проверь, корректно ли это единственное замечание, и — главное — не пропустило ли ревью реальные проблемы: открой ключевые новые/изменённые файлы Media (MediaConversionKind, Application/Service/MediaConversionsChecker, новая сигнатура partsCount, предикат isOriginalRemoved, свёрнутый conversionUrlsOf, контракты MediaUrlServiceContract и MediaUploadPlannerContract и реализации, резолверы URL, FindMediaUrl, DTO MediaConversionUrl/MediaConversionUrlCollection/MediaUrlsResult, Entity Media), а также изменения в User (UserPublicProfileAssembler, UserBootloader), Posts (PostMedia BelongsTo Media), Shared (LocaleResolver), конфиги, и оцени баги, риски для смежного кода, нарушения правил/архитектуры и недостающие тесты. Не предлагай перечислять выполненную работу и не хвали код. Ответ строго на русском, по пунктам, каждый помечен '+' (добавить замечание), '−' (убрать как неверное), '~' (переформулировать) или '?' (нельзя проверить), с указанием файла и причины.
codex
Использую `eda-review-check`, потому что задача прямо про мета-проверку готового ревью. Сначала прочитаю инструкции скилла и обязательные документы, затем сверю ревью с diff и ключевыми файлами.
exec
/bin/zsh -lc "sed -n '1,260p' docs/rules.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "sed -n '1,260p' docs/arch.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
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

### Исключение: `Media` — foundational-модуль

`Media` — универсальный (foundational) модуль: хранение и раздача файлов нужны почти любому
модулю. Поэтому для него действует осознанное исключение из правила «модули общаются только через
`Application`»: другим модулям разрешено **держать ORM-relation на сущности `Media` (на чтение)** и
**передавать загруженную сущность `Media` в Application-сервисы `Media`**.

Зачем: чтение списков с вложениями (лента `Posts`) должно грузить медиа и их конверсии вместе с
основной выборкой (`->load('media.imageConversions'...)`), а не разрешать URL поэлементно (N+1).
Для этого `PostMedia` объявляет `#[BelongsTo(target: Media::class, ..., cascade: false, fkCreate:
false)]` и eager-грузит её в репозитории, после чего URL строится из уже загруженной сущности без
обращений в БД: лента берёт только оригинал через `MediaUrlService::getOriginalUrl(Media $media)`
(eager-загруженные конверсии держатся под планируемый показ превью), а полный набор «оригинал +
конверсии» отдаёт `MediaUrlService::getUrls(Media $media)` (путь `FindMediaUrl`).

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
FindMediaOriginalUrl
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
        Infrastructure/
          Bootloader/             # SystemBootloader: регистрация namespace `system` для view
        Presentation/
          Http/                   # Health, Swagger UI, OpenAPI YAML route
          Console/                # openapi:* команды
          Exception/              # Исключения слоя Presentation (публикация ассетов OpenAPI)
          Temporal/               # технические workflow
          views/                  # twig-шаблоны модуля (swagger/index), namespace `system`

    Shared/

 succeeded in 0ms:
# Правила проекта

## Язык

- Отвечай всегда на русском
- Исключения пишутся на русском языке.
- Логи пишутся на русском языке.
- Комментарии к коду пишутся на русском языке.
- Ошибки, которые отдаются пользователю, пишутся на языке пользователя.
- **Без англицизмов**: в русском тексте — комментариях, докстрингах, сообщениях логов и исключений, документации — не использовать кальки с английского, если есть обычное русское слово. Например: «временный» вместо «транзиентный», «рассылка»/«отправка» вместо «диспатч», «по умолчанию» вместо «дефолтный», «полезные данные» вместо «payload». Имена классов, методов, переменных, типов и прочие технические идентификаторы остаются на английском (`DispatchNotificationJob`, `isTransient()`, `outbox`) — переводу подлежит только человекочитаемый русский текст.
- **Коммиты на русском**: сообщения коммитов (subject, body) пишутся на русском языке. Тип и scope остаются на английском по Conventional Commits (`feat`, `fix`, `refactor` и т.д.), но описание — на русском. Пример: `feat(tenant): добавить управление тенантами`.

## Качество кода

- **Ранний возврат**: guard clauses (`throw`/`return`) в начале метода. Инвертировать условие, выбросить исключение первым, happy path без вложенности. Вложенность 2+ уровней `if/else` — красный флаг.
- **Короткие методы**: `handle()` в Handler — не более ~40 строк. Длиннее — выносить в приватные методы.
- **`match` вместо `switch`**: `switch` не используется. Всегда `match`-выражение. Enum — исчерпывающий `match` без `default`.
- **Именованные аргументы**: обязательны при 2+ обычных параметрах и при любом необязательном/булевом параметре. Позиционные — для 1–2 очевидных параметров. Variadic-вызовы и вызовы с unpack (`...$args`) допускают позиционные аргументы, потому что именование ломает читаемость таких API (`sprintf`, `implode` и т.д.). (Проверяется PHPStan)
- **`sprintf()` для строк**: для пользовательских сообщений и строк ошибок. Интерполяция `"{$a}-{$b}"` — только для компактных ключей/идентификаторов. Конкатенация `.` — избегать.
- **Collection-пайплайны**: `->map()`, `->filter()`, `->groupBy()` для чистых трансформаций. `foreach` — только при побочных эффектах.
- **Не подменять методы коллекции array-функциями**: если значение уже `Illuminate\Support\Collection` (в т.ч. типизированная доменная коллекция), для выборок и трансформаций вызывай её методы (`->filter()`, `->map()`, `->first()`, `->last()`, `->take()`, `->contains()`, `->reject()`, `->keyBy()`), а не разворачивай в массив через `->all()` ради `array_filter`/`array_map`/`array_slice`/`\count()`. Антипаттерн: `array_filter($collection->all(), $fn)` вместо `$collection->filter($fn)`. `->all()` уместен только когда наружу действительно нужен `array`/`list<T>` (метод объявляет такой тип возврата, передаём в чужой API), а не как промежуточный шаг между двумя коллекциями. Подробности и идиомы — в скилле [`laravel-collections`](../.claude/skills/laravel-collections/SKILL.md).
- **Без round-trip «коллекция → массив → коллекция»**: запрещено брать `->all()`, обрабатывать массив (`\count()`, `array_slice()`, индексирование `$page[\count($page) - 1]`) и заворачивать результат обратно в `new SomeCollection($array)`. Если на входе и на выходе коллекция, весь конвейер остаётся на коллекции. Чтобы преобразовать типизированную коллекцию в другой тип элемента (ресурсы, DTO), используй `$collection->toBase()->map($fn)` — базовый `Collection` не ломает дженерик `final`-коллекции, — а не `new Collection($collection->all())`; в конструктор другой типизированной коллекции передавай `Collection`/`Arrayable` напрямую, без `->all()`.
- **`mapToList()` вместо `\array_values(...->all())`**: чтобы получить `list<T>` из коллекции с преобразованием элемента, вызывай метод базовой коллекции `App\Shared\Domain\Collection\TypedCollection::mapToList($fn)`, а не пиши вручную `\array_values($coll->toBase()->map($fn)->all())` или `\array_values(\array_map($fn, $coll->all()))`. Метод коллекции `->all()` PHPStan видит как `array<int, T>`, а не `list<T>`, поэтому ручная связка тянет за собой `array_values`; `mapToList()` прячет это в одном месте и сразу даёт `list<TNew>`. Для переиндексации коллекции, остающейся коллекцией, — её метод `->values()`. `mapToList()` сбрасывает ключи — **не применять на коллекциях-картах** (`TagTextCollection` и подобных `<string, …>`), где ключ несёт смысл: для них конвейер `->toBase()->keyBy($fn)->map($fn)` и оборачивание в нужную коллекцию. `\array_values(...)` допустим только вне темы коллекций: спред id-списка в variadic-метод, `\array_values(\array_unique($list))` над плоским `list<string>`, и конвейер с промежуточной переиндексирующей операцией между `map` и `all` (`->unique()`) как граница `list<T>`, которую `mapToList()` не выражает.
- **Cursor-пагинация — через общие примитивы, не дублированием**: схему «взять `limit + 1`, понять, есть ли следующая страница, отдать первые `limit` и курсор последней отданной» не дублировать в каждом репозитории и обработчике. Используются два общих примитива:
  - **Репозиторий**: `App\Shared\Infrastructure\Cycle\WhenSelect::cursorById($cursor?->value(), $limit)` — последнее звено цепочки `select()`, инкапсулирует `id DESC` + `where('id', '<', $cursor)` при курсоре + `limit`. Не писать вручную блок `->when(...cursor...)->orderBy('id','DESC')->limit($limit)` в каждом методе.
  - **Обработчик (Query)**: `App\Shared\Domain\Pagination\CursorSlice::fromOverfetched(overfetched: $repoResult, limit: $query->limit, cursorOf: static fn(Entity $e): string => $e->id->value())` — возвращает `$slice->items` (та же типизированная коллекция, первые `limit`) и `$slice->nextCursor`. Не писать вручную `$page->take($limit)` + `$page->count() > $limit` + `$visible->last()?->id->value()` в каждом обработчике.

  Антипаттерн (исправлен в `GetUserFeedHandler`, `GetPostCommentsHandler`, `GetCommentRepliesHandler`, `ListNotificationsHandler` и cursor-методах `PostRepository`/`CommentRepository`/`NotificationRepository`): `->all()` → `\count()` → `array_slice()` → `new XCollection($visible)` → `$visible[\count($visible) - 1]`, а также копия `when(cursor)+orderBy+limit` в каждом запросе.
- **Типизированные Laravel Collections вместо массивов и Doctrine Collections**: в Entity и Repository не использовать массивы для наборов сущностей или value object. Для каждой доменной коллекции создавать именованный класс на базе `Illuminate\Support\Collection` с generic-типом элемента: например, связь `User -> Role` хранится и возвращается как `RoleCollection`, а результат репозитория со списком пользователей — как `UserCollection`. `Doctrine\Common\Collections\ArrayCollection` запрещена. Все связи Cycle ORM и методы репозиториев, возвращающие несколько элементов, должны возвращать конкретную типизированную коллекцию, а не `array` и не голый `Illuminate\Support\Collection`.
- **Явные типы вместо `null`**: `null` в доменной модели не протаскивается через границы — тип свойства Entity, параметры `create()`, аргументы и возвраты доменных методов не должны быть `?T` для доменных данных. Отсутствие, неизвестность или особое состояние выражается отдельным value object, enum или доменным типом, а не `?string`/`?Ip`. Форма null-object VO выбирается по содержанию состояний:
  - **Одно опциональное значение, поведение сводится к «есть/нет»** — один `readonly`-VO с приватным nullable внутри и фабриками присутствия/отсутствия (образец `MediaExpiration` — `permanent()`/`temporaryUntil()`; `MediaProcessingError` — `none()`). Такой инкапсулированный nullable правилу не противоречит: наружу `?T` не виден.
  - **Несколько состояний с разными данными или разным поведением**, где полиморфизм убирает `if`/`instanceof` у вызывающих — абстракция + реализации (образец `Ip` → `KnownIp`/`UnknownIp`).

  По умолчанию для одного опционального значения берётся форма «один VO»; абстракция с наследниками заводится только когда варианты реально несут разные данные или поведение.
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
- **`env()` только в конфигах**: вызов `env()` допустим исключительно в файлах `app/config/*.php`. Типизированные конфиги (`*Config`) читаются только в Infrastructure-коде (бутлоадеры/инфра-сервисы/middleware); **Domain и Application `*Config` не импортируют** и получают готовые значения (VO, скаляры, доменные сервисы) через DI. Прямое обращение к `env()` в бизнес-коде — нарушение. Конфиг-зависимое поведение живёт в Infrastructure-сервисе за `Application/Contract` (образцы `MediaUrlService`, `MediaUploadPlanner`): реализация читает `*Config` сама и отдаёт Application готовые решения. Промежуточный settings-объект (settings-DTO), который лишь проецирует поля `*Config` в Application, запрещён как «конфиг от конфига»; допустимые формы передачи значений — единичное готовое значение/VO через фабрику бутлоадера либо доменный сервис над значениями (подробнее — `docs/arch.md`, «Правила зависимостей»).
- **Typed config для каждого config-файла**: при добавлении нового `app/config/*.php` сразу создаётся корневой DTO в `App\Shared\Infrastructure\Configuration\<Section>\<Section>Config`, который реализует `TypedConfig`. `configName()` должен совпадать с именем файла без `.php`. Вложенные секции описываются отдельными DTO рядом с корневым классом, а маппинг покрывается тестом через `ConfigMapper`.
- EnvironmentInterface - запрещён нужно использовать конфиги
- **Реквесты через Filter**: входящие данные принимаются через Spiral Filter-классы (`Spiral\Filter\Dto\FilterInterface`). Ассоциативные массивы `array $input` в контроллерах — запрещены. Enum-ы (`TenantStatus`, `TenantPlan` и т.д.) типизируются прямо в Filter-е — Spiral автоматически кастит строку в BackedEnum. Контроллер не делает `::from()`/`::tryFrom()` вручную.
- **Запрет `ServerRequestInterface` в контроллерах**: контроллеры не инжектят `Psr\Http\Message\ServerRequestInterface`. Для тела запроса — Spiral Filter (`#[Post]`, `#[Query]`). Для аутентифицированного пользователя — `#[Attribute(key: 'authUserId')]`, читающий request attribute из JWT middleware. Ключ `authUserId` (не `userId`) во избежание коллизий с параметрами роута. `ServerRequestInterface` допустим только в middleware и interceptor-ах.
- **Filter-ы полностью объектно-ориентированные**: свойства Filter-а — только скалярные типы, Enum-ы и вложенные Filter-ы/DTO. Ассоциативные массивы (`array<string, mixed>`) в Filter-е — запрещены. Для вложенных JSON-объектов создавать отдельный вложенный Filter или DTO (`#[NestedFilter]` / отдельный `readonly class`). Spiral поддерживает вложенность фильтров — использовать её вместо сырых массивов. Плоские типизированные списки (`list<string>`, `list<int>`, `list<UuidString>`) — допустимы, в том числе nullable (`?array` с PHPDoc `@var list<string>|null`).
- **Filter: не указывать `key` если совпадает с именем свойства**: `#[Post]` без `key` — Spiral автоматически берёт имя PHP-свойства. `key` указывать только когда имя в JSON отличается от имени свойства. Поскольку API использует camelCase и PHP-свойства тоже camelCase — `key` практически никогда не нужен.
- **Filter: обязательные свойства без значения по умолчанию**: если свойство Filter-а обязательное — оно объявляется без значения по умолчанию и без nullable. `public string $email;` — не `public string $email = '';`. Spiral заполняет свойства из запроса, валидация через `#[Assert\NotBlank]` гарантирует наличие. Дефолт `= ''` маскирует отсутствие значения. Nullable (`?string $name = null`) и дефолты (`int $limit = 20`) — только для реально опциональных полей. Для файлов: `public UploadedFileInterface $file;` — не nullable.
- **Респонс — типизированный класс**: каждый ответ API — объект с типизированными полями, не ассоциативный массив. Запрещено возвращать `['key' => $value]` напрямую.
- **Запрет ассоциативного массива как структуры (record)**: ассоциативный массив с фиксированным, заранее известным набором именованных ключей, где значения по ключам имеют разный смысл (`['userId' => .., 'type' => .., 'sessionId' => ..]`), — это скрытый DTO и запрещён везде в коде проекта. Заменять на DTO/VO/Response-класс или enum. Признак нарушения (проверяется на ревью): ключи массива записаны строковыми литералами в коде, либо к массиву обращаются по константному ключу (`$x['userId']`). PHPDoc-тип `array<string, string>` (как и любой `array<string, …>`) такой массив НЕ легализует — он лишь маскирует структуру под «карту», поэтому «значения одного типа» сами по себе массив не оправдывают. Разрешён ассоциативный массив только как однородная карта (map): ключ — это данные времени выполнения (идентификатор, ключ кеша, код локали), набор ключей заранее не известен и не ограничен, а все значения имеют один тип и один смысл; типизируется `array<string, T>`, где `T` — не массив, shape или tuple (см. «Именованные типы для сложных данных»). Когда карта становится самостоятельным доменным понятием, которое передаётся между слоями или несёт поведение/инварианты, — оборачивать в именованную типизированную коллекцию; для разовой инфраструктурной карты raw `array<string, T>` допустим. Исключения, где ассоциативный массив неизбежен и нарушением не считается: контекст логгера (`['userId' => $id]`), `jsonSerialize()` и другая сериализация в JSON, конфиги фреймворка (`app/config/*.php` и их config-DTO), а также payload/headers/параметры на самой границе с чужим контрактом — vendor-интерфейсом, Cycle ORM, Spiral Queue, translator, PSR-интерфейсами. На такой границе массив навязан библиотекой; внутрь нашего кода он должен сразу превращаться в VO/DTO/enum.
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
- **Репозиторий — только чтение (read-only)**: Repository только читает данные — через ORM `$this->select()` и базовые `findByPK()`/`findOne()`/`findAll()`. Репозиторий не сохраняет и не изменяет данные: запрещены методы-мутаторы `save()`, `persist()`, `store()`, `create()`, `update()`, `delete()`, а также инъекция `EntityManagerInterface` в репозиторий. Прямые `$database->update()`, `$database->insert()`, `$database->delete()` тоже запрещены — даже атомарный CAS-переход по статусу. Любое изменение состояния выражается доменным методом Entity (`markQueued()`, `markFailed()` и т.д.) и сохраняется вызовом `$this->entityManager->persist($entity)` + `$this->entityManager->run()` в Handler-е или инфраструктурном orchestrator-е, который вызывает репозиторий только для выборки. Правило rules.md «Запрет SQL текстом» отвечает на вопрос *как* писать запрос (query builder, не сырая строка), но не разрешает репозиторию модифицировать данные. Санкционированное исключение для массовой записи без доменных инвариантов — отдельный класс с маркером `App\Shared\Infrastructure\Database\SetBasedWrite` (см. правило «Set-based запись — только через маркер `SetBasedWrite`»); сам репозиторий остаётся read-only в любом случае.
- **Set-based запись — только через маркер `SetBasedWrite` и только при доказанной необходимости**: прямая массовая запись (`update()`/`insert()`/`delete()` на `Cycle\Database\DatabaseInterface`) по умолчанию запрещена и механически блокируется правилом `DisallowSetBasedWriteRule` — обычное изменение состояния идёт через доменный метод Entity + `EntityManager`. Исключение разрешается, только когда выполнены **оба** блока условий.
  - **Необходимость (хотя бы один пункт):** (1) число затрагиваемых строк не ограничено доменом сверху и растёт с данными или активностью пользователя (mark-all-read, удаление/архивация старых записей, сброс по типу) — загрузка всего набора как Entity даёт неограниченную память и время; (2) операция на горячем пути и вызывается так часто, что стоимость гидрации сущностей значима на масштабе; (3) это чистый «штамп» одной колонки/статуса по набору (флаг, таймстемп, счётчик), где загруженные сущности всё равно не используются. Числовой порог намеренно не задаётся: критерий — «набор не ограничен сверху», а не «больше N».
  - **Безопасность (все пункты обязательны):** у строки нет доменного инварианта, валидации или ветвления, которые должны отработать при записи; переход идемпотентный и тривиальный; затронутые строки не читаются и не используются как живые Entity в этом же запросе.
  - **Запрещено, даже если соблазнительно:** одиночная сущность; набор, заведомо ограниченный доменом небольшим числом; наличие per-row инварианта/проверки/вычисляемых полей (тогда — доменный путь, при необходимости порциями); чтение затронутых строк обратно в том же запросе; мотив «так меньше кода / быстрее писать».
  - Каждый метод set-based writer-а — именованная доменная операция (`markAllReadForRecipient`), а не generic `update(table, set, where)`; writer-классы держатся маленькими и узкими, каждый метод покрыт тестом. Маркер реализуется на классе-реализации в `Infrastructure`, а Application обращается к нему через `*Contract`. Блок «безопасность» PHPStan не проверяет — это зона ревью и докблока.
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
- **View-шаблоны живут в модуле**: шаблоны представления модуля (twig: письма, Swagger UI, любые рендеримые view) — часть Presentation-слоя и лежат в `Modules/{Module}/Presentation/views/`. Каждый модуль регистрирует свою папку шаблонов как namespace = имя модуля в нижнем регистре через `ViewsBootloader::addDirectory(...)` в bootloader-е модуля (путь к папке строится от `__DIR__`), а ссылка на шаблон использует namespace-форму `{module}:<имя>` — например `auth:login-code`, `system:swagger/index`. Общая папка `app/views` (default namespace) для шаблонов модулей не используется. Это контраст с двумя сущностями, которые осознанно остаются глобальными: config-файлы (`app/config/*.php` + typed DTO в `Shared/Infrastructure/Configuration`, см. «Typed config для каждого config-файла» и `arch.md`) и миграции (`app/database/migrations` — единая линейная история схемы, часто кросс-модульная). Шаблон — живой ассет модуля, рендерится в рантайме и переезжает вместе с модулем; конфиг и миграция — нет.
- **CQRS: группировка по действию**: внутри `Modules/{Module}/Application/Command/` и `Modules/{Module}/Application/Query/` каждое действие выносится в отдельную подпапку. Папка называется по действию (без суффикса Command/Query). Пока в модуле одна область (Area), подпапка действия лежит прямо в корне `Command/`/`Query/`; уровень области `{Area}` добавляется только когда областей становится несколько. Пример: `Modules/Auth/Application/Command/Login/LoginCommand.php`, `LoginHandler.php`, `LoginResult.php`. Это даёт чёткую изоляцию: все файлы одного use-case лежат рядом. Namespace соответствует: `App\Modules\Auth\Application\Command\Login`.
- **CQRS: полное имя действия в имени класса**: имя Command/Query/Handler/Filter должно содержать полный контекст действия, включая доменную сущность, даже если namespace уже содержит домен. Пример: `UpdateUserProfileCommand` (не `UpdateProfileCommand`), `GetUserProfileQuery` (не `GetProfileQuery`), `UpdateUserProfileFilter` (не `UpdateProfileFilter`). Папка действия совпадает: `Application/Command/UpdateUserProfile/`, `Application/Query/GetUserProfile/`. Это делает класс самодокументируемым — по имени сразу понятно, что он делает, без заглядывания в namespace.


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
/bin/zsh -lc "sed -n '1,240p' /Users/gian_tiaga/Code/yoga-loka-spiral-2/.codex/skills/eda-review-check/SKILL.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
---
name: eda-review-check
description: 'Проверяет готовое ревью из `docs/reviews/...` специализированными субагентами и, в strict-режиме, соседним CLI. Не делает первичное ревью и не правит код. Читает ревью, target/plan из front matter, `docs/rules.md`, `docs/arch.md`, `docs/settings.yaml` и связанный research при наличии. Запускает architecture-check, rules-check, plan-check только при указанном plan-файле и опциональный quality-check, затем сам применяет подтверждённые замечания к файлу ревью: добавляет, убирает или переформулирует пункты, обновляет score/status/meta_reviewers и раздел изменений после мета-ревью.'
---

# Скил: Проверка ревью (eda-review-check)

Берёшь уже сохранённое ревью из `docs/reviews/`, проверяешь его специализированными субагентами, затем сам правишь файл ревью по подтверждённым замечаниям. Этот скил переиспользуется обычным `eda-review` после draft-этапа и может запускаться отдельно.

## Режим запуска

Прочитай `docs/settings.yaml`, если файл есть. Прямое указание пользователя в текущем сообщении важнее настроек.

Дефолты: `defaults.strict: false`, `review.include_code_quality: true`.

| Настройка | Значения | Аргументы в сообщении |
|---|---|---|
| `defaults.strict` | `true`, `false` | `strict`; выключение: `normal`, `без strict`, `без строгого режима` |
| `review.include_code_quality` | `true`, `false` | «проверь качество кода»; выключение: «без quality», «без проверки качества» |

`strict` добавляет кросс-CLI соседним агентом. `review.include_code_quality: true` добавляет `quality-check` в обычное мета-ревью.

## Вход из сообщения пользователя

Текст рядом с вызовом скилла в текущем сообщении пользователя — главный вход. Сначала разбери именно его: путь к ревью, название или часть названия, «последнее ревью», режим, ограничения и прямые указания.

Если этого входа достаточно, продолжай без вопроса. `AskUserQuestion` задавай только если входа нет, он противоречивый, найдено несколько равных вариантов или есть риск проверить не то ревью. Старое обсуждение в истории не считай входом, если пользователь не связал его с текущим вызовом явно.

## Главные правила

1. **Не делаешь первичное ревью.** Входом всегда является готовый файл `docs/reviews/...`.
2. **Не правишь код.** Можно менять только выбранный файл ревью и вспомогательный файл `${REVIEW_FILE%.md}_cli_meta.md` в strict-режиме.
3. **Мета-ревью специализированными агентами обязательно.** Базовые роли: `architecture-check`, `rules-check`. `plan-check` запускай только если в front matter ревью `plan` указывает на конкретный файл. Если включена проверка качества кода — добавь `quality-check` в тот же batch.
4. **Главный редактор ревью — ты.** Субагенты дают предложения; решение добавить, убрать или переформулировать пункт принимаешь сам.
5. **Ревью остаётся ревью только по проблемам.** Не добавляй выполненные пункты, похвалу, нейтральный отчёт о diff или общие рассуждения.
6. **Каждое принятое изменение фиксируй в `## Изменения после мета-ревью`.** Отклонённые предложения тоже запиши коротко с причиной.
7. **Оценку пересчитывай после правок.** Если изменился набор или тяжесть замечаний, обнови `score` в front matter и текст в разделе `## Оценка`.

## Интерактивные вопросы

Когда инструкция говорит `AskUserQuestion`, это означает блокирующий интерактивный вопрос.
- Claude Code: используй `AskUserQuestion`.
- Codex interactive: если доступен `request_user_input`, используй его. Если tool недоступен, задай один короткий вопрос в чат, дай варианты 1–3, напиши «Ответь номером или своим вариантом. Я продолжу только после ответа.» и остановись.
- Codex exec / неинтерактивный запуск: не задавай вопросы и не пытайся читать stdin. Если без ответа нельзя безопасно продолжать, заверши работу со статусом `blocked: нужен ответ пользователя`, перечисли вопросы и варианты, не выполняй рискованные действия.
- Не запускай команды, которые ждут интерактивного ввода в терминале; перед такими командами спроси в хост-интерфейсе или остановись с `blocked`.

## Этапы

### 1. Выбрать ревью и прочитать контекст

Определи `$REVIEW_FILE`:
- если пользователь указал путь к ревью — используй его;
- если указал название или часть названия — найди совпадение в `docs/reviews/`;
- если написал «последнее ревью» — возьми самый новый файл из `docs/reviews/`;
- если входа нет — `AskUserQuestion` со списком последних файлов из `docs/reviews/`;
- если `docs/reviews/` отсутствует или пуста — остановись со статусом `blocked: нужно ревью`.

Прочитай `$REVIEW_FILE` целиком. Из front matter и текста извлеки `$TARGET`, `$PLAN_FILE`, текущие `mode`, `score`, `status`, `meta_reviewers`. Если `plan: none` или план явно помечен как пропущенный, установи `$PLAN_FILE=none`. Если target указывает на diff-команду, получи актуальный diff. Если target указывает на файл, папку или PR — прочитай достаточно кода, чтобы проверить замечания. Прочитай `docs/rules.md`, `docs/arch.md`, `docs/settings.yaml`, если они есть. Если ревью ссылается на `docs/researches/...`, прочитай связанный research.

Если `$PLAN_FILE=none` или отсутствует, не угадывай план и не запускай `plan-check`. В разделе изменений после мета-ревью коротко зафиксируй: «plan-check не запускался: план не указан в ревью».

### 2. Запустить специализированных субагентов

Запусти **в одном сообщении и одним batch** агентов с выбранными ролями. В интерактивном Codex это означает субагентов, а не отдельные процессы `codex exec`. Не запускай сначала часть агентов, не жди их ответы и не запускай остальных позже: все выбранные проверки должны стартовать до чтения любого результата.

Роли агентов:
- `plan-check`: запускай только если `$PLAN_FILE` указывает на существующий файл. Проверяет, что реализация действительно выполнила `$PLAN_FILE`, включая частичные пункты, пропущенные требования, лишние отклонения и недостающие проверки.
- `architecture-check`: проверяет следование `docs/arch.md`, границы модулей, контракты, зависимости и соответствие существующим архитектурным решениям.
- `rules-check`: проверяет следование `docs/rules.md`, локальным запретам, обязательным командам, требованиям к тестам, миграциям, документации и процессу.
- `quality-check`: запускай только если `review.include_code_quality: true` или пользователь явно попросил качество кода. Проверяет качество реализации: читаемость, сложность, дублирование, имена, размер и ответственность функций/классов, сцепление модулей, поддерживаемость и соответствие локальному стилю. Quality-замечания помечай типом `quality`; по умолчанию рекомендация `на усмотрение автора`, кроме случаев, где сложность, дублирование или неясная структура реально создают риск багов, тестовых пробелов или дорогого сопровождения.

Модели:
- Claude Code: запускай все роли через `Agent` tool на `model: "sonnet"`.
- Codex interactive: если доступен инструмент субагентов (`spawn_agent` или аналог), запускай все роли через него на `gpt-5.3-codex`; не используй отдельные `codex exec` для обычного мета-ревью, когда субагенты доступны.
- Codex exec / неинтерактивный fallback: если инструмента субагентов нет, запускай роли через параллельные `codex exec --model gpt-5.3-codex` одной Bash-командой с `&` и общим `wait`.
- Если нужный идентификатор недоступен, возьми ближайшую code-capable модель того же уровня. Не используй быструю слабую модель для этих проверок: они должны читать diff, план при наличии, правила, архитектуру и релевантный код.

Каждому агенту дай путь к `$REVIEW_FILE`, `$TARGET`, `docs/rules.md`, `docs/arch.md`, `docs/settings.yaml`, связанный research при наличии. `plan-check` дополнительно получает `$PLAN_FILE`, если он есть. Каждый агент должен читать не только ревью, но и фактический diff/код, достаточный для проверки своей роли.

Формат ответа агентов:
- `+` какую проблему добавить в ревью;
- `−` что убрать как неверное или недоказанное;
- `~` что переформулировать;
- `?` что невозможно проверить и почему.

Если `$PLAN_FILE` не передан, равен `none` или файл не найден, `plan-check` не запускай.

### 3. Применить результаты обычного мета-ревью

Когда все агенты ответили: прочитай ответы, **сам реши**, что применять. Добавляй только подтверждённые проблемы; предложения вида «добавить, что пункт выполнен» отклоняй как неформат ревью. Внеси правки в файл ревью.

В разделе `## Изменения после мета-ревью` запиши:

```markdown
### После plan-check / architecture-check / rules-check / quality-check
- **+ Добавлено:** <короткий список>
- **~ Изменено:** <короткий список>
- **− Убрано:** <короткий список>
- **Отклонено:** <короткий список с причиной>
```

Поменяй `status: draft` → `meta-reviewed`, если статус был draft. В `meta_reviewers` добавь только фактически запущенные роли: `architecture-check`, `rules-check`, `plan-check` при наличии `$PLAN_FILE`, а если запускался — `quality-check`, и фактические модели, например `gpt-5.3-codex` или `sonnet`. Если оценка после корректировок изменилась — обнови `score`.

### 4. Кросс-CLI: отдать соседнему агенту — только в `strict`

Обычный режим — пропусти этап 4, иди на финал. `status: meta-reviewed` оставь как есть.

В `strict`: отдай файл соседнему агенту через `Bash`. Если ты в Claude Code — запускай Codex CLI:

```bash
codex exec "Прочитай $REVIEW_FILE, $TARGET, $PLAN_FILE, docs/rules.md, docs/arch.md, связанный research при наличии. Затем глубоко проверь ревью по плану, правилам, архитектуре и релевантному коду: открой файлы, модули, API и тесты, которые следуют из diff и замечаний, и оцени фактическую корректность. Мета-ревью на русском: какие проблемы добавить, убрать, переформулировать; какие баги, риски для смежного кода и недостающие тесты пропущены. Не предлагай перечислять выполненную работу. Формат: '+', '−', '~', '?'." > "${REVIEW_FILE%.md}_cli_meta.md"
```

Если ты в Codex — запускай Claude CLI с теми же требованиями глубины:

```bash
claude -p "Прочитай $REVIEW_FILE, $TARGET, $PLAN_FILE, docs/rules.md, docs/arch.md, связанный research при наличии. Затем глубоко проверь ревью по плану, правилам, архитектуре и релевантному коду: открой файлы, модули, API и тесты, которые следуют из diff и замечаний, и оцени фактическую корректность. Мета-ревью на русском: какие проблемы добавить, убрать, переформулировать; какие баги, риски для смежного кода и недостающие тесты пропущены. Не предлагай перечислять выполненную работу. Формат: '+', '−', '~', '?'." > "${REVIEW_FILE%.md}_cli_meta.md"
```

Если `$PLAN_FILE=none`, в промпте соседнему CLI явно напиши: «План не указан; не проверяй выполнение плана и не угадывай файл плана, проверь только правила, архитектуру и код».

Соседней CLI нет — `AskUserQuestion`: завершить как есть или прервать.

Прочитай ответ, **сам реши**, что применять. Допиши в `## Изменения после мета-ревью`:

```markdown
### После соседнего CLI (codex|claude)
- **+ Добавлено:** ...
- **− Убрано:** ...
- **Отклонено:** ...
```

Добавь в `meta_reviewers` имя CLI. `status: meta-reviewed` → `final`.

### 5. Финал

Короткое сообщение: режим (`normal` или `strict`), путь к файлу ревью, итоговая оценка, сколько пунктов «править обязательно» / «на усмотрение», какие meta-reviewers запускались. Не пересказывай выполненную работу. Если режим был обычный — добавь строку «кросс-CLI не запускался; для проверки соседним агентом — `eda-review-check strict <review>`».

## Чего НЕ делать

- Делать первичное ревью. Это `eda-review`.
- Править код. Это `eda-fix-by-review`.
- Пропускать мета-ревью специализированными агентами.
- Запускать мета-ревьюеров частями: сначала 1–2 агента, потом остальных. Все выбранные обязательные проверки стартуют сразу.
- Пропускать кросс-CLI молча в `strict` (если соседней CLI нет — спроси через `AskUserQuestion`).
- Вставлять чужие предложения механически — каждое решение принимай ты.
- Задавать вопросы без блокировки и продолжать работу до ответа.
- Сохранять или редактировать ревью вне `docs/reviews/`.
- Перечислять выполненные пункты плана или давать общий отчёт о проделанной работе.

exec
/bin/zsh -lc "sed -n '1,260p' docs/code-examples.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
# Примеры кода

## DTO команды

```php
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\Login;

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

namespace App\Modules\Auth\Application\Command\Login;

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

namespace App\Modules\User\Application\Query\GetUserProfile;

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

namespace App\Modules\User\Application\Query\GetUserProfile;

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

use App\Modules\User\Application\Query\GetUserProfile\GetUserProfileHandler;
use App\Modules\User\Application\Query\GetUserProfile\GetUserProfileQuery;
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

codex
Контекст правил и архитектуры уже поднят. Важное ограничение из самого запроса: план не проверяю вообще, даже если рядом есть похожие документы; смотрю только ревью, фактический diff и код.
exec
/bin/zsh -lc "sed -n '1,260p' /Users/gian_tiaga/Code/yoga-loka-spiral-2/docs/reviews/2026-06-30_20-54_uncommitted-diff.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "sed -n '1,260p' /private/tmp/claude-501/-Users-gian-tiaga-Code-yoga-loka-spiral-2/9e35cc30-c943-40a1-bd4d-5f4a8ab1cf45/scratchpad/full_diff.txt" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
---
title: Незакоммиченный diff — шестой круг (доводка до 95/100)
date: 2026-06-30 20:54
target: git diff HEAD
plan: none
mode: draft
score: 94
status: draft
meta_reviewers: []
---

# Ревью: Незакоммиченный diff — шестой круг (доводка до 95/100)

## Оценка

**94/100.** Шестой независимый проход по тому же changeset. Новый код последнего круга
(`MediaConversionKind`, `MediaConversionsChecker`, новая сигнатура `partsCount`, предикат
`isOriginalRemoved`, свёрнутый `conversionUrlsOf`) проверен прицельно — он корректен, типобезопасен,
покрыт тестами на все ветки и согласован с правилами и архитектурой. Обязательных замечаний нет.
Остаётся один мелкий пункт «на усмотрение» по документации, не затрагивающий поведение.

## Проблемы сверки с планом

Проверка плана пропущена: план не указан и не найден.

## Замечания

### 1. Папка `Application/Service` не описана в архитектуре, хотя ею пользуются

Новый сервис-помощник `MediaConversionsChecker` положили в каталог `Application/Service`. Решение
само по себе разумное и повторяет уже существующий `MediaTypeResolver`, то есть в коде это устоявшийся
приём. Проблема не в коде, а в рассинхроне с документацией: описание структуры модуля в архитектуре
перечисляет слои `Domain`, `Application` (с под-каталогом `Contract`), `Repository`,
`Infrastructure`, `Presentation`, но про каталог `Application/Service` не упоминает вовсе. Человек,
который сверяет новый код с архитектурой (а это явно предписанный в проекте шаг перед правками),
не найдёт там санкции на такой каталог и будет вынужден догадываться, легитимен ли он.

Риск практический и проявится при росте команды/числа модулей: каждый следующий автор будет заново
решать, куда класть подобный сервис-помощник (в `Application` корнем, в `Application/Service`, в
`Domain`), и каталоги расползутся по-разному в разных модулях. Это вопрос предсказуемости структуры,
а не работоспособности — поэтому «на усмотрение».

Технические детали:

- **Тип:** `docs`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/src/Modules/Media/Application/Service/MediaConversionsChecker.php` (и существующий
  `app/src/Modules/Media/Application/Service/MediaTypeResolver.php`); раздел «Модули» / «Структура
  каталогов» в `docs/arch.md`.
- **Что подтверждает проблему:** в `docs/arch.md` структура модуля и дерево каталогов
  (`Modules/Media/...`) не содержат `Application/Service`; при этом в коде уже два таких сервиса.
- **Как исправить:** одной строкой зафиксировать в `docs/arch.md` конвенцию: внутримодульные
  Application-сервисы-помощники без состояния (резолверы/чекеры, которые инкапсулируют несколько
  репозиториев своего модуля для одного use-case-вопроса) живут в `Application/Service`. Код не
  трогать — менять только документацию. Альтернатива (если каталог решат не узаконивать) дороже:
  переносить оба сервиса, поэтому предпочтительна правка доков.
- **Тесты:** не требуются — изменение только документации.

## Рекомендации

- **Править обязательно:** —
- **На усмотрение автора:** 1

## Изменения после мета-ревью

Мета-ревью не запускалось (режим `draft`).

 succeeded in 0ms:
diff --git a/.env.sample b/.env.sample
index db0c65d..741b011 100644
--- a/.env.sample
+++ b/.env.sample
@@ -78,6 +78,8 @@ MEDIA_PUBLIC_STORAGE_PREFIX=
 
 # Инфраструктурные параметры пайплайна загрузки Media (app/config/media.php).
 MEDIA_STAGING_TTL_SECONDS=86400
+# Срок presigned-ссылки скачивания по умолчанию, секунды (диапазон 1..604800).
+MEDIA_PRESIGNED_TTL_SECONDS=3600
 MEDIA_MULTIPART_THRESHOLD_BYTES=16777216
 MEDIA_MULTIPART_PART_SIZE_BYTES=8388608
 MEDIA_IMAGE_PROCESSING_DRIVER=imagick
diff --git a/.php-cs-fixer.dist.php b/.php-cs-fixer.dist.php
index 20da8bf..2cd8a7d 100644
--- a/.php-cs-fixer.dist.php
+++ b/.php-cs-fixer.dist.php
@@ -9,6 +9,7 @@ return new Config()
     ->setRiskyAllowed(true)
     ->setRules([
         '@PER-CS' => true,
+        'single_quote' => true,
         'nullable_type_declaration' => ['syntax' => 'union'],
         'native_function_invocation' => [
             'include' => ['@all'],
diff --git a/app/config/media.php b/app/config/media.php
index 03c8004..f060c6e 100644
--- a/app/config/media.php
+++ b/app/config/media.php
@@ -5,15 +5,21 @@ declare(strict_types=1);
 /**
  * Инфраструктурные дефолты модуля Media.
  *
- * Только технические параметры пайплайна загрузки: staging-TTL для MediaExpiration при
- * создании, порог и размер части multipart, драйвер обработки изображений. Бизнес-ограничения
- * загрузки и срок presigned-ссылок сюда не зашиваются — их задаёт потребитель (срок presigned —
- * через MediaUploadSpec для загрузки и GetMediaUrlQuery для скачивания).
+ * Технические параметры пайплайна загрузки (staging-TTL для MediaExpiration при создании, порог и
+ * размер части multipart, драйвер обработки изображений) и срок presigned-ссылки скачивания по
+ * умолчанию. Срок presigned-ссылок загрузки задаёт потребитель через MediaUploadSpec; срок скачивания
+ * по умолчанию берётся отсюда, но вызывающий может переопределить его в FindMediaUrlQuery или
+ * FindMediaOriginalUrlQuery (оба принимают presignedTtlSeconds).
  */
 return [
     // Срок жизни оригинала в staging-бакете до подтверждения (MediaExpiration при create), секунды.
     'stagingTtlSeconds' => \max(1, (int) \env('MEDIA_STAGING_TTL_SECONDS', 86_400)),
 
+    // Срок presigned-ссылки скачивания по умолчанию, секунды. Диапазон 1..604800 (его держит
+    // MediaPresignedTtl); MediaConfig проверяет диапазон при старте, чтобы неверная настройка падала
+    // на запуске, а не на первом построении ссылки для приватного медиа.
+    'presignedTtlSeconds' => \max(1, (int) \env('MEDIA_PRESIGNED_TTL_SECONDS', 3600)),
+
     // Порог: файл размером >= порога загружается через multipart, иначе одиночным PUT.
     'multipartThresholdBytes' => \max(5_242_880, (int) \env('MEDIA_MULTIPART_THRESHOLD_BYTES', 16_777_216)),
 
diff --git a/app/locale/en/media.php b/app/locale/en/media.php
index fd28c1a..c1661e6 100644
--- a/app/locale/en/media.php
+++ b/app/locale/en/media.php
@@ -8,6 +8,8 @@ return [
     'app.media.not_ready' => 'Media is not ready yet.',
     'app.media.conversion_not_found' => 'The requested conversion is missing.',
     'app.media.cannot_make_permanent' => 'Only uploaded or ready media can be made permanent.',
+    'app.media.original_not_removable' => 'The original can only be removed from ready media.',
+    'app.media.no_conversions_to_keep' => 'Cannot remove the original: the media has no conversions.',
     'app.media.upload_not_pending' => 'Media upload is not awaiting confirmation.',
     'app.media.multipart_upload_not_found' => 'No multipart upload was found for the media.',
     'app.media.uploaded_object_mismatch' => 'The uploaded object is missing or its size does not match the declared one.',
diff --git a/app/locale/ru/media.php b/app/locale/ru/media.php
index 7ab4209..5be9ccc 100644
--- a/app/locale/ru/media.php
+++ b/app/locale/ru/media.php
@@ -8,6 +8,8 @@ return [
     'app.media.not_ready' => 'Медиа ещё не готово.',
     'app.media.conversion_not_found' => 'Запрошенное преобразование отсутствует.',
     'app.media.cannot_make_permanent' => 'Постоянным можно сделать только загруженное или готовое медиа.',
+    'app.media.original_not_removable' => 'Удалить оригинал можно только у готового медиа.',
+    'app.media.no_conversions_to_keep' => 'Нельзя удалить оригинал: у медиа нет ни одного преобразования.',
     'app.media.upload_not_pending' => 'Загрузка медиа не ожидает подтверждения.',
     'app.media.multipart_upload_not_found' => 'Для медиа не найдена multipart-загрузка.',
     'app.media.uploaded_object_mismatch' => 'Загруженный объект отсутствует или его размер не совпадает с заявленным.',
diff --git a/app/src/Modules/Auth/Application/Command/SendLoginCode/SendLoginCodeHandler.php b/app/src/Modules/Auth/Application/Command/SendLoginCode/SendLoginCodeHandler.php
index fe66a49..43ae0c7 100644
--- a/app/src/Modules/Auth/Application/Command/SendLoginCode/SendLoginCodeHandler.php
+++ b/app/src/Modules/Auth/Application/Command/SendLoginCode/SendLoginCodeHandler.php
@@ -5,28 +5,28 @@ declare(strict_types=1);
 namespace App\Modules\Auth\Application\Command\SendLoginCode;
 
 use App\Modules\Auth\Application\Contract\LoginCodeMailerContract;
-use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
+use App\Shared\Domain\Locale\LocaleResolver;
 use GianTiaga\SpiralCqrs\Attribute\LogOperation;
 use Spiral\Translator\TranslatorInterface;
 
 /**
  * Отправка письма с кодом входа. Выполняется в очереди (per-request локали нет), поэтому язык
- * берётся из сообщения с явной передачей в translator и fallback на LocaleConfig.default при
- * неподдерживаемом значении. Тема и тело письма — из переводов домена auth, а сама отправка
- * через framework-mailer инкапсулирована за LoginCodeMailerContract.
+ * берётся из сообщения с явной передачей в translator и сведением к значению по умолчанию при
+ * неподдерживаемом значении через LocaleResolver. Тема и тело письма — из переводов домена auth, а
+ * сама отправка через framework-mailer инкапсулирована за LoginCodeMailerContract.
  */
 final readonly class SendLoginCodeHandler
 {
     public function __construct(
         private LoginCodeMailerContract $loginCodeMailer,
         private TranslatorInterface $translator,
-        private LocaleConfig $localeConfig,
+        private LocaleResolver $localeResolver,
     ) {}
 
     #[LogOperation]
     public function handle(SendLoginCodeCommand $command): void
     {
-        $locale = $this->resolveLocale($command->locale);
+        $locale = $this->localeResolver->resolve($command->locale);
 
         $this->loginCodeMailer->send(
             email: $command->email,
@@ -44,11 +44,4 @@ final readonly class SendLoginCodeHandler
             ),
         );
     }
-
-    private function resolveLocale(string $locale): string
-    {
-        return \in_array(needle: $locale, haystack: $this->localeConfig->supported, strict: true)
-            ? $locale
-            : $this->localeConfig->default;
-    }
 }
diff --git a/app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php b/app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php
index c63085f..796dfc7 100644
--- a/app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php
+++ b/app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php
@@ -53,8 +53,8 @@ final readonly class ProcessMediaHandler
         $media = $this->mediaRepository->findById(MediaId::fromString($command->mediaId))
             ?? throw new NotFoundException('app.media.not_found');
 
-        if ($media->isReady()) {
-            $this->logger->debug(message: 'Обработка медиа пропущена: уже ready.', context: [
+        if ($media->isFinalized()) {
+            $this->logger->debug(message: 'Обработка медиа пропущена: медиа уже финализировано.', context: [
                 'mediaId' => $media->id->value(),
             ]);
 
diff --git a/app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalCommand.php b/app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalCommand.php
new file mode 100644
index 0000000..88c1d45
--- /dev/null
+++ b/app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalCommand.php
@@ -0,0 +1,13 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Modules\Media\Application\Command\RemoveMediaOriginal;
+
+final readonly class RemoveMediaOriginalCommand
+{
+    public function __construct(
+        public string $userId,
+        public string $mediaId,
+    ) {}
+}
diff --git a/app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php b/app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php
new file mode 100644
index 0000000..cfe987a
--- /dev/null
+++ b/app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php
@@ -0,0 +1,86 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Modules\Media\Application\Command\RemoveMediaOriginal;
+
+use App\Modules\Media\Application\Contract\MediaFileServiceContract;
+use App\Modules\Media\Application\Dto\MediaResult;
+use App\Modules\Media\Application\Service\MediaConversionsChecker;
+use App\Modules\Media\Domain\ValueObject\MediaId;
+use App\Modules\Media\Repository\MediaRepository;
+use App\Shared\Domain\Exception\ForbiddenException;
+use App\Shared\Domain\Exception\NotFoundException;
+use App\Shared\Domain\Exception\ValidationException;
+use App\Shared\Domain\ValueObject\UserId;
+use Cycle\ORM\EntityManagerInterface;
+use GianTiaga\SpiralCqrs\Attribute\LogOperation;
+use Psr\Log\LoggerInterface;
+
+/**
+ * Удаляет оригинальный объект медиа из целевого бакета, сохраняя конверсии. Без #[Transactional]:
+ * сначала идемпотентный deleteObject оригинала (404 → no-op), затем один атомарный persist+run() с
+ * переходом в readyOriginalRemoved. Идемпотентен: на уже removed-original — ранний no-op без обращения
+ * к S3. На сбое после deleteObject до flush статус остаётся ready, повтор команды довыполнит переход.
+ *
+ * Осознанный компромисс порядка «удалить в S3 → зафиксировать статус»: пока переход не довыполнен,
+ * статус остаётся ready, и любой запрос ссылки на оригинал (FindMediaUrl/FindMediaOriginalUrl/лента/
+ * аватар) вернёт ссылку на уже удалённый объект — короткое окно битой ссылки. Команда предполагает
+ * повторный вызов при сбое (автоматического реиспуска, как у тяжёлой обработки через outbox, тут нет),
+ * поэтому пока не подключена к прямому запуску пользователем; перед подключением к реальному триггеру компромисс
+ * пересмотреть (вариант: вынести deleteObject в outbox-шаг после commit-а перехода).
+ */
+final readonly class RemoveMediaOriginalHandler
+{
+    public function __construct(
+        private MediaRepository $mediaRepository,
+        private MediaConversionsChecker $mediaConversionsChecker,
+        private MediaFileServiceContract $mediaFileService,
+        private EntityManagerInterface $entityManager,
+        private LoggerInterface $logger,
+    ) {}
+
+    #[LogOperation]
+    public function handle(RemoveMediaOriginalCommand $command): MediaResult
+    {
+        $media = $this->mediaRepository->findById(MediaId::fromString($command->mediaId))
+            ?? throw new NotFoundException('app.media.not_found');
+
+        if (!$media->uploadedById->equals(UserId::fromString($command->userId))) {
+            throw new ForbiddenException('app.media.access_denied');
+        }
+
+        if ($media->isOriginalRemoved()) {
+            $this->logger->debug(message: 'Удаление оригинала медиа пропущено: оригинал уже удалён.', context: [
+                'mediaId' => $media->id->value(),
+            ]);
+
+            return MediaResult::fromEntity($media);
+        }
+
+        if (!$media->isReady()) {
+            throw new ValidationException('app.media.original_not_removable');
+        }
+
+        if (!$this->mediaConversionsChecker->hasAnyConversion($media->id)) {
+            throw new ValidationException('app.media.no_conversions_to_keep');
+        }
+
+        // Удаляем текущий оригинал в целевом бакете строго до доменного перехода. 404 идемпотентно
+        // игнорируется сервисом, поэтому повтор команды после частичного сбоя безопасен.
+        $this->mediaFileService->deleteObject(storage: $media->storage, path: $media->path);
+
+        $media->markReadyOriginalRemoved();
+        $this->entityManager->persist($media);
+        $this->entityManager->run();
+
+        $this->logger->debug(message: 'Оригинал медиа удалён.', context: [
+            'mediaId' => $media->id->value(),
+            'userId' => $command->userId,
+            'storage' => $media->storage->value,
+            'path' => $media->path->value(),
+        ]);
+
+        return MediaResult::fromEntity($media);
+    }
+}
diff --git a/app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php b/app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php
index 9cf6b0c..fb42940 100644

exec
/bin/zsh -lc "test -f docs/settings.yaml && sed -n '1,220p' docs/settings.yaml || true" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
version: 1

defaults:
  # Включает strict-режим по умолчанию для eda-explore, eda-plan и eda-review.
  # true | false
  strict: true
  # Задаёт размер плана по умолчанию для eda-plan.
  # normal | short | ask_each_time
  plan_size: normal
  # Определяет, как eda-explore и eda-plan принимают существенные решения.
  # autonomous | recommend_and_ask | ask_each_time
  decision_mode: ask_each_time
  # Задаёт стратегию тестов по умолчанию для eda-plan.
  # after_each_phase | tdd_each_phase | end_of_plan | ask_each_time
  test_strategy: after_each_phase
  # Задаёт стратегию логирования по умолчанию для eda-plan.
  # debug_precise | standard | ask_each_time
  logging_strategy: debug_precise

automate:
  # Добавляет docs/plans/ в обычный запуск eda-automate.
  # true | false
  include_plans: true

review:
  # Добавляет в eda-review проверку качества кода и meta-reviewer quality-check.
  # true | false
  include_code_quality: true

exec
/bin/zsh -lc 'git status --short && git diff --name-only HEAD' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 102ms:
.env.sample
.php-cs-fixer.dist.php
app/config/media.php
app/locale/en/media.php
app/locale/ru/media.php
app/src/Modules/Auth/Application/Command/SendLoginCode/SendLoginCodeHandler.php
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php
app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalCommand.php
app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php
app/src/Modules/Media/Application/Contract/MediaUploadPlannerContract.php
app/src/Modules/Media/Application/Contract/MediaUrlServiceContract.php
app/src/Modules/Media/Application/Dto/MediaConversionUrl.php
app/src/Modules/Media/Application/Dto/MediaConversionUrlCollection.php
app/src/Modules/Media/Application/Dto/MediaUrlsResult.php
app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/FindMediaOriginalUrlHandler.php
app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/FindMediaOriginalUrlQuery.php
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlQuery.php
app/src/Modules/Media/Application/Query/GetAudioWaveform/GetAudioWaveformHandler.php
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlQuery.php
app/src/Modules/Media/Domain/Entity/Media.php
app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php
app/src/Modules/Media/Infrastructure/FileService/MediaUploadPlanner.php
app/src/Modules/Media/Infrastructure/FileService/MediaUrlResolver.php
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php
app/src/Modules/Media/Infrastructure/FileService/PresignedMediaUrlResolver.php
app/src/Modules/Media/Infrastructure/FileService/PublicMediaUrlResolver.php
app/src/Modules/Media/README.md
app/src/Modules/Media/Repository/MediaAudioConversionRepository.php
app/src/Modules/Media/Repository/MediaImageConversionRepository.php
app/src/Modules/Media/Repository/MediaRepository.php
app/src/Modules/Media/Repository/MediaVideoConversionRepository.php
app/src/Modules/Posts/Application/Post/PostContentComposer.php
app/src/Modules/Posts/Application/View/PostViewAssembler.php
app/src/Modules/Posts/Domain/Entity/PostMedia.php
app/src/Modules/Posts/Repository/PostMediaRepository.php
app/src/Modules/User/Application/Command/CreateUser/CreateUserHandler.php
app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php
app/src/Modules/User/Infrastructure/Bootloader/UserBootloader.php
app/src/Shared/Domain/Locale/LocaleResolver.php
app/src/Shared/Infrastructure/Configuration/Media/MediaConfig.php
app/src/Shared/Infrastructure/Framework/Bootloader/AppBootloader.php
app/src/Shared/Infrastructure/Framework/Kernel.php
docs/arch.md
docs/executions/2026-06-25_11-18_media-remove-original.md
docs/executions/2026-06-29_17-50_application-config-independence.md
docs/executions/2026-06-29_21-42_media-url-service-to-infrastructure.md
docs/executions/2026-06-30_15-47_remove-media-upload-settings.md
docs/fixes/2026-06-25_15-30_media-merge-find-get-url.md
docs/plans/2026-06-24_23-26_media-remove-original.md
docs/plans/2026-06-24_23-26_media-remove-original_review.md
docs/plans/2026-06-29_16-38_application-config-independence.md
docs/plans/2026-06-29_16-38_application-config-independence_review.md
docs/plans/2026-06-29_20-08_media-url-service-to-infrastructure.md
docs/plans/2026-06-30_15-04_remove-media-upload-settings.md
docs/plans/2026-06-30_15-04_remove-media-upload-settings_review.md
docs/review-fixes/2026-06-25_13-42_media-remove-original.md
docs/reviews/2026-06-25_11-44_media-remove-original.md
docs/reviews/2026-06-25_14-58_media-remove-original.md
docs/rules.md
docs/settings.yaml
phpunit.xml
tests/Feature/Modules/Auth/Application/AuthApplicationTestCase.php
tests/Feature/Modules/Auth/Application/SendLoginCodeHandlerTest.php
tests/Feature/Modules/Auth/Presentation/SendLoginCodeJobTest.php
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php
tests/Feature/Modules/Posts/Repository/PostRepositoryTest.php
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php
tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php
tests/Feature/Modules/User/Application/UserApplicationTestCase.php
tests/Kernel/Modules/Media/MediaBootloaderTest.php
tests/Kernel/Modules/User/UserBootloaderTest.php
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php
tests/Kernel/Shared/Infrastructure/Framework/Bootloader/AppBootloaderTest.php
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php
tests/Unit/Modules/Media/Infrastructure/FfmpegWaveformTest.php
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php
tests/Unit/Modules/Media/Infrastructure/MediaUploadPlannerTest.php
tests/Unit/Modules/Posts/Domain/Entity/JoinEntityTest.php
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php
tests/Unit/Shared/Domain/Locale/LocaleResolverTest.php

codex
Вижу, что ревью действительно с `plan: none`, а в текущем дереве есть незакоммиченные правки и в коде, и в документации. Сейчас открываю ключевые классы Media/User/Posts/Shared и точечные участки diff, чтобы проверить не только заявленное замечание, но и пропуски по поведению.
exec
/bin/zsh -lc "sed -n '1,240p' app/src/Modules/Media/Domain/Enum/MediaConversionKind.php && sed -n '1,240p' app/src/Modules/Media/Application/Service/MediaConversionsChecker.php && sed -n '1,320p' app/src/Modules/Media/Domain/Entity/Media.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Enum;

/**
 * Вид конверсии для рендера на клиенте: только image/video/audio. Документ не конвертируется, поэтому
 * варианта document здесь нет (в отличие от MediaType): потребитель с исчерпывающим match по kind не
 * тянет заведомо мёртвую ветку document. Постер видео имеет kind = image, т.к. это кадр-картинка.
 */
enum MediaConversionKind: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Service;

use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Repository\MediaAudioConversionRepository;
use App\Modules\Media\Repository\MediaImageConversionRepository;
use App\Modules\Media\Repository\MediaVideoConversionRepository;

/**
 * Отвечает на вопрос «есть ли у медиа хотя бы одна конверсия любого вида (image/video/audio)».
 * Собирает знание обо всех видах конверсий в одном месте, чтобы вызывающий сценарий не зависел от
 * каждого репозитория конверсий по отдельности: при добавлении нового вида конверсии правка остаётся
 * здесь, а не в каждом обработчике.
 *
 * Ленивая проверка с ранним выходом на первой найденной конверсии: existsForMediaId считает строки без
 * гидрации сущностей — до трёх count-запросов на вызов.
 */
final readonly class MediaConversionsChecker
{
    public function __construct(
        private MediaImageConversionRepository $mediaImageConversionRepository,
        private MediaVideoConversionRepository $mediaVideoConversionRepository,
        private MediaAudioConversionRepository $mediaAudioConversionRepository,
    ) {}

    public function hasAnyConversion(MediaId $mediaId): bool
    {
        return $this->mediaImageConversionRepository->existsForMediaId($mediaId)
            || $this->mediaVideoConversionRepository->existsForMediaId($mediaId)
            || $this->mediaAudioConversionRepository->existsForMediaId($mediaId);
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Entity;

use App\Modules\Media\Domain\Collection\MediaAudioConversionCollection;
use App\Modules\Media\Domain\Collection\MediaImageConversionCollection;
use App\Modules\Media\Domain\Collection\MediaVideoConversionCollection;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaProcessingAttempts;
use App\Modules\Media\Domain\ValueObject\MediaProcessingError;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Shared\Domain\ValueObject\UserId;
use App\Modules\Media\Infrastructure\Cycle\MediaExpirationTypecast;
use App\Modules\Media\Infrastructure\Cycle\MediaProcessingErrorTypecast;
use App\Shared\Infrastructure\Cycle\ValueObjectCast;
use App\Modules\Media\Repository\MediaRepository;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\HasMany;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'media',
    table: 'media',
    repository: MediaRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class Media
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: MediaId::class)]
    public private(set) MediaId $id;

    #[Column(type: 'uuid', name: 'storage_key', typecast: MediaStorageKey::class)]
    public private(set) MediaStorageKey $storageKey;

    #[Column(type: 'string(32)', typecast: MediaType::class)]
    public private(set) MediaType $type;

    #[Column(type: 'string(64)', typecast: MediaStatus::class)]
    public private(set) MediaStatus $status;

    #[Column(type: 'string(16)', typecast: MediaVisibility::class)]
    public private(set) MediaVisibility $visibility;

    #[Column(type: 'string(64)', typecast: MediaStorage::class)]
    public private(set) MediaStorage $storage;

    #[Column(type: 'string(1024)', typecast: MediaPath::class)]
    public private(set) MediaPath $path;

    #[Column(type: 'string(255)', name: 'mime_type', typecast: MediaMimeType::class)]
    public private(set) MediaMimeType $mimeType;

    #[Column(type: 'bigInteger', typecast: MediaFileSize::class)]
    public private(set) MediaFileSize $size;

    #[Column(type: 'uuid', name: 'uploaded_by_id', typecast: UserId::class)]
    public private(set) UserId $uploadedById;

    #[Column(type: 'datetime', name: 'expires_at', nullable: true, typecast: MediaExpirationTypecast::class)]
    public private(set) MediaExpiration $expiration;

    #[Column(type: 'integer', name: 'processing_attempts', typecast: MediaProcessingAttempts::class)]
    public private(set) MediaProcessingAttempts $processingAttempts;

    #[Column(type: 'text', name: 'processing_error', nullable: true, typecast: MediaProcessingErrorTypecast::class)]
    public private(set) MediaProcessingError $processingError;

    #[HasMany(
        target: MediaImageConversion::class,
        innerKey: 'id',
        outerKey: 'media_id',
        orderBy: ['id' => 'ASC'],
        collection: MediaImageConversionCollection::class,
    )]
    public private(set) MediaImageConversionCollection $imageConversions;

    #[HasMany(
        target: MediaVideoConversion::class,
        innerKey: 'id',
        outerKey: 'media_id',
        orderBy: ['id' => 'ASC'],
        collection: MediaVideoConversionCollection::class,
    )]
    public private(set) MediaVideoConversionCollection $videoConversions;

    #[HasMany(
        target: MediaAudioConversion::class,
        innerKey: 'id',
        outerKey: 'media_id',
        orderBy: ['id' => 'ASC'],
        collection: MediaAudioConversionCollection::class,
    )]
    public private(set) MediaAudioConversionCollection $audioConversions;

    public static function create(
        MediaStorageKey $storageKey,
        MediaType $type,
        MediaVisibility $visibility,
        MediaPath $path,
        MediaMimeType $mimeType,
        MediaFileSize $size,
        UserId $uploadedById,
        MediaExpiration $expiration,
    ): self {
        $media = new self();
        $media->id = MediaId::generate();
        $media->storageKey = $storageKey;
        $media->type = $type;
        $media->status = MediaStatus::WaitingUpload;
        $media->visibility = $visibility;
        $media->storage = MediaStorage::Upload;
        $media->path = $path;
        $media->mimeType = $mimeType;
        $media->size = $size;
        $media->uploadedById = $uploadedById;
        $media->expiration = $expiration;
        $media->processingAttempts = MediaProcessingAttempts::zero();
        $media->processingError = MediaProcessingError::none();
        $media->imageConversions = new MediaImageConversionCollection();
        $media->videoConversions = new MediaVideoConversionCollection();
        $media->audioConversions = new MediaAudioConversionCollection();
        $media->initializeTimestamps();

        return $media;
    }

    public function startCompletingMultipartUpload(): void
    {
        $this->status = MediaStatus::CompletingMultipartUpload;
        $this->touch();
    }

    public function markMultipartCompletionFailedCanRetry(MediaProcessingError $processingError): void
    {
        $this->status = MediaStatus::MultipartCompletionFailedCanRetry;
        $this->processingError = $processingError;
        $this->touch();
    }

    public function markMultipartCompletionFailedNeedReupload(MediaProcessingError $processingError): void
    {
        $this->status = MediaStatus::MultipartCompletionFailedNeedReupload;
        $this->processingError = $processingError;
        $this->touch();
    }

    public function markUploaded(): void
    {
        $this->status = MediaStatus::Uploaded;
        $this->processingError = MediaProcessingError::none();
        $this->touch();
    }

    public function startProcessing(): void
    {
        $this->status = MediaStatus::Processing;
        $this->touch();
    }

    /**
     * Финализированное медиа не «ломается» задним числом: повторная/запоздалая фиксация ошибки на
     * уже готовом медиа (ready или readyOriginalRemoved) — no-op (симметрично guard'у в
     * markReadyMovedTo). Защищает инвариант «готовое без ошибки» независимо от вызывающего, даже
     * если фиксацию сбоя запустят в обход isFinalized-guard'а в ProcessMediaHandler (другой relay,
     * ручной перезапуск Job, дубликат в очереди после удаления оригинала).
     */
    public function recordTemporaryProcessingError(MediaProcessingError $processingError): void
    {
        $this->recordProcessingError($processingError);
    }

    /**
     * См. recordTemporaryProcessingError: тот же инвариант «готовое без ошибки» — на уже
     * финализированном медиа (ready или readyOriginalRemoved) фиксация постоянной ошибки также no-op.
     */
    public function recordPermanentProcessingError(MediaProcessingError $processingError): void
    {
        $this->recordProcessingError($processingError);
    }

    /**
     * Общее тело фиксации ошибки обработки для временной и постоянной ошибки: оба перехода
     * идентичны (статус processingFailed, инкремент попыток, запись ошибки) и одинаково защищены
     * guard'ом isFinalized. Остаётся приватным, а recordTemporary/PermanentProcessingError —
     * публичные точки доменного контракта.
     */
    private function recordProcessingError(MediaProcessingError $processingError): void
    {
        if ($this->isFinalized()) {
            return;
        }

        $this->status = MediaStatus::ProcessingFailed;
        $this->processingAttempts = $this->processingAttempts->increment();
        $this->processingError = $processingError;
        $this->touch();
    }

    public function markReady(): void
    {
        $this->status = MediaStatus::Ready;
        $this->processingError = MediaProcessingError::none();
        $this->touch();
    }

    /**
     * Перевод в ready с переназначением целевого хранилища и пути (после перекладки оригинала
     * из staging). Идемпотентен: повторная доставка на ready — no-op. Допустим из uploaded,
     * processing или processingFailed (повтор обработки после временной ошибки).
     */
    public function markReadyMovedTo(MediaStorage $storage, MediaPath $path): void
    {
        if ($this->status === MediaStatus::Ready) {
            return;
        }

        if (
            $this->status !== MediaStatus::Uploaded
            && $this->status !== MediaStatus::Processing
            && $this->status !== MediaStatus::ProcessingFailed
        ) {
            throw new InvalidDomainValueException(
                'Перевод медиа в ready допустим только из uploaded, processing или processingFailed.',
            );
        }

        $this->storage = $storage;
        $this->path = $path;
        $this->status = MediaStatus::Ready;
        $this->processingError = MediaProcessingError::none();
        $this->touch();
    }

    public function isReady(): bool
    {
        return $this->status === MediaStatus::Ready;
    }

    /**
     * Оригинал удалён, но конверсии обслуживаются (readyOriginalRemoved). Прячет сравнение с конкретным
     * статусом от вызывающих (как isReady()/isFinalized()), чтобы Application не знал конкретный вариант
     * enum.
     */
    public function isOriginalRemoved(): bool
    {
        return $this->status === MediaStatus::ReadyOriginalRemoved;
    }

    /**
     * Терминальное «готовое» состояние: обработка завершена и конверсии обслуживаются — как при ready,
     * так и после удаления оригинала (readyOriginalRemoved). Используется там, где важна готовность
     * конверсий, а не наличие оригинала: конверсионные запросы и защита от поздней переобработки.
     */
    public function isFinalized(): bool
    {
        return $this->status === MediaStatus::Ready || $this->status === MediaStatus::ReadyOriginalRemoved;
    }

    /**
     * Перевод в readyOriginalRemoved после физического удаления оригинала из целевого бакета.
     * Домен сам отстаивает инвариант источника независимо от вызывающего (симметрично markReadyMovedTo
     * и guard'у isFinalized в recordProcessingError): допустим только из ready. Идемпотентен: повторный
     * вызов на уже readyOriginalRemoved — no-op. Из любого другого статуса — InvalidDomainValueException,
     * чтобы оригинал не оказался «удалён» у медиа, которое никогда не было готовым.
     */
    public function markReadyOriginalRemoved(): void
    {
        if ($this->status === MediaStatus::ReadyOriginalRemoved) {
            return;
        }

        if ($this->status !== MediaStatus::Ready) {
            throw new InvalidDomainValueException(
                'Удаление оригинала медиа допустимо только из ready.',
            );
        }

        $this->status = MediaStatus::ReadyOriginalRemoved;
        $this->processingError = MediaProcessingError::none();
        $this->touch();
    }

    public function makePermanent(): void
    {
        $this->expiration = MediaExpiration::permanent();
        $this->touch();
    }
}

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Media/Application/Contract/MediaUrlServiceContract.php && sed -n '1,220p' app/src/Modules/Media/Application/Contract/MediaUploadPlannerContract.php && sed -n '1,260p' app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php && sed -n '1,220p' app/src/Modules/Media/Infrastructure/FileService/MediaUploadPlanner.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Contract;

use App\Modules\Media\Application\Dto\MediaUrlResult;
use App\Modules\Media\Application\Dto\MediaUrlsResult;
use App\Modules\Media\Domain\Entity\Media;

/**
 * Контракт построения URL из УЖЕ загруженной сущности Media. Принимает доменную сущность Media и
 * возвращает Application DTO.
 *
 * Срок presigned-ссылки скачивания: значение по умолчанию читает реализация из конфига, а вызывающий
 * может переопределить его на конкретный вызов через $presignedTtlSeconds.
 *
 * getUrls — полный набор (оригинал, если не удалён, и все конверсии). getOriginalUrl — только ссылка
 * на оригинал, без обращения к связям-конверсиям: для потребителей, которым конверсии не нужны (аватар
 * профиля, лента) — чтобы не подгружать и не подписывать конверсии впустую.
 *
 * Реализация — App\Modules\Media\Infrastructure\FileService\MediaUrlService.
 */
interface MediaUrlServiceContract
{
    public function getUrls(Media $media, int|null $presignedTtlSeconds = null): MediaUrlsResult|null;

    public function getOriginalUrl(Media $media, int|null $presignedTtlSeconds = null): MediaUrlResult|null;
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Contract;

use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;

/**
 * Контракт решений пайплайна загрузки, зависящих от конфига Media: срок staging-хранения нового медиа,
 * нужен ли multipart для данного размера, размер и число частей multipart. Application-сценарий
 * (RequestMediaUploadHandler) получает эти решения через контракт и не знает про *Config.
 *
 * Реализация — App\Modules\Media\Infrastructure\FileService\MediaUploadPlanner (читает MediaConfig
 * напрямую через конструктор, как MediaUrlService).
 */
interface MediaUploadPlannerContract
{
    public function stagingExpiration(): MediaExpiration;

    public function isMultipart(MediaFileSize $size): bool;

    /**
     * Размер одной части multipart-загрузки. Этот же объект вызывающий передаёт в partsCount(), поэтому
     * число частей всегда считается делением на тот размер части, который записывается в
     * MediaMultipartUpload.
     */
    public function partSize(): MediaMultipartPartSize;

    /**
     * Число частей для размера size при заданном размере части partSize. Согласованность пары
     * (partsCount, partSize) держится сигнатурой, а не докблоком: partSize приходит готовым объектом
     * (от partSize()), поэтому реализация не может разделить на другое значение и собрать
     * MediaMultipartUpload с несогласованной парой.
     */
    public function partsCount(MediaFileSize $size, MediaMultipartPartSize $partSize): MediaMultipartPartsCount;
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\FileService;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Contract\MediaUrlServiceContract;
use App\Modules\Media\Application\Dto\MediaConversionUrl;
use App\Modules\Media\Application\Dto\MediaConversionUrlCollection;
use App\Modules\Media\Application\Dto\MediaUrlResult;
use App\Modules\Media\Application\Dto\MediaUrlsResult;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaAudioConversion;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\Entity\MediaVideoConversion;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaConversionKind;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPresignedTtl;
use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
use Illuminate\Support\Collection;

/**
 * Строит URL из УЖЕ загруженного медиа. getUrls — полный набор: оригинал (если не удалён) и все
 * конверсии. Конверсии берутся из связей сущности (imageConversions/videoConversions/audioConversions),
 * поэтому метод не делает запросов в БД — при условии, что связи загружены eager заранее
 * (репозиторий-метод с ->load(...) или ->load('media.imageConversions') у вызывающего модуля).
 * Если связи не загружены, Cycle подгрузит их лениво — это вернёт N+1, поэтому вызывающий обязан
 * передавать медиа с eager-загруженными конверсиями. getOriginalUrl — только оригинал, к связям
 * не обращается вовсе: для потребителей, которым конверсии не нужны (аватар, лента).
 *
 * Срок presigned-ссылки скачивания: значение по умолчанию реализация читает из MediaConfig напрямую
 * (прямая инъекция конфига — класс лежит в Infrastructure), а вызывающий может переопределить его на
 * конкретный вызов. Сервис stateless, поэтому expiresAt всегда считается внутри вызова (не в
 * конструкторе), иначе все наборы URL получили бы один замороженный срок.
 *
 * Не бросает: не финализированное медиа (ещё не ready и не readyOriginalRemoved) -> null. После
 * удаления оригинала original = null, конверсии резолвятся.
 */
final readonly class MediaUrlService implements MediaUrlServiceContract
{
    public function __construct(
        private MediaFileServiceContract $mediaFileService,
        private MediaConfig $mediaConfig,
    ) {}

    public function getUrls(Media $media, int|null $presignedTtlSeconds = null): MediaUrlsResult|null
    {
        if (!$media->isFinalized()) {
            return null;
        }

        $resolver = $this->resolverFor(
            visibility: $media->visibility,
            presignedTtlSeconds: $presignedTtlSeconds,
        );

        return new MediaUrlsResult(
            original: $media->isReady() ? $resolver->resolve(storage: $media->storage, path: $media->path) : null,
            conversions: $this->conversionUrls(media: $media, resolver: $resolver),
        );
    }

    /**
     * Только ссылка на оригинал, без обращения к связям-конверсиям. Возвращает null, если оригинала
     * нет (медиа не финализировано или оригинал удалён в readyOriginalRemoved) — вызывающий подставит
     * значение по умолчанию. Не трогает imageConversions/videoConversions/audioConversions, поэтому
     * не подгружает их из БД и не подписывает presigned-ссылки для конверсий, которые потребителю
     * (аватар, лента) не нужны.
     */
    public function getOriginalUrl(Media $media, int|null $presignedTtlSeconds = null): MediaUrlResult|null
    {
        if (!$media->isReady()) {
            return null;
        }

        $resolver = $this->resolverFor(
            visibility: $media->visibility,
            presignedTtlSeconds: $presignedTtlSeconds,
        );

        return $resolver->resolve(storage: $media->storage, path: $media->path);
    }

    /**
     * Резолвер URL по контексту медиа: public — прямые URL без срока (TTL не нужен и не валидируется),
     * private — presigned с единым сроком на весь набор URL одного вызова. Переопределение TTL строго
     * по `?? `: null -> значение по умолчанию из конфига; явный 0 или значение вне диапазона ->
     * исключение MediaPresignedTtl (нельзя писать `?:`, иначе явный 0 тихо ушёл бы в значение по
     * умолчанию).
     */
    private function resolverFor(MediaVisibility $visibility, int|null $presignedTtlSeconds): MediaUrlResolver
    {
        if ($visibility === MediaVisibility::Public) {
            return new PublicMediaUrlResolver($this->mediaFileService);
        }

        $ttl = MediaPresignedTtl::fromInt($presignedTtlSeconds ?? $this->mediaConfig->presignedTtlSeconds);
        $expiresAt = new \DateTimeImmutable()->add(new \DateInterval(\sprintf('PT%dS', $ttl->value())));

        return new PresignedMediaUrlResolver(mediaFileService: $this->mediaFileService, expiresAt: $expiresAt);
    }

    private function conversionUrls(Media $media, MediaUrlResolver $resolver): MediaConversionUrlCollection
    {
        $imageUrls = $this->conversionUrlsOf(
            conversions: $media->imageConversions->toBase(),
            kind: MediaConversionKind::Image,
            resolver: $resolver,
        );
        $videoUrls = $this->conversionUrlsOf(
            conversions: $media->videoConversions->toBase(),
            kind: MediaConversionKind::Video,
            resolver: $resolver,
        );
        $audioUrls = $this->conversionUrlsOf(
            conversions: $media->audioConversions->toBase(),
            kind: MediaConversionKind::Audio,
            resolver: $resolver,
        );

        return new MediaConversionUrlCollection($imageUrls->concat($videoUrls)->concat($audioUrls));
    }

    /**
     * Строит ссылки конверсий одного вида. Обвязка map одинакова для image/video/audio и различается
     * только видом и типом элемента, поэтому вынесена сюда (раньше — три почти одинаковых map-блока).
     *
     * @param Collection<int, MediaImageConversion|MediaVideoConversion|MediaAudioConversion> $conversions
     * @return Collection<int, MediaConversionUrl>
     */
    private function conversionUrlsOf(
        Collection $conversions,
        MediaConversionKind $kind,
        MediaUrlResolver $resolver,
    ): Collection {
        return $conversions->map(
            fn(MediaImageConversion|MediaVideoConversion|MediaAudioConversion $conversion): MediaConversionUrl
                => $this->conversionUrl(
                    kind: $kind,
                    type: $conversion->type,
                    storage: $conversion->storage,
                    path: $conversion->path,
                    resolver: $resolver,
                ),
        );
    }

    private function conversionUrl(
        MediaConversionKind $kind,
        MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType $type,
        MediaStorage $storage,
        MediaPath $path,
        MediaUrlResolver $resolver,
    ): MediaConversionUrl {
        $resolved = $resolver->resolve(storage: $storage, path: $path);

        return new MediaConversionUrl(kind: $kind, type: $type, url: $resolved->url, expiresAt: $resolved->expiresAt);
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\FileService;

use App\Modules\Media\Application\Contract\MediaUploadPlannerContract;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
use App\Shared\Infrastructure\Configuration\Media\MediaConfig;

/**
 * Считает решения пайплайна загрузки из MediaConfig: срок staging-хранения, порог multipart, размер и
 * число частей. Лежит в Infrastructure и читает MediaConfig напрямую через конструктор (как
 * MediaUrlService), биндится const BINDINGS в MediaBootloader. Срок staging считается на момент вызова
 * внутри stagingExpiration(), иначе все загрузки получили бы один замороженный момент.
 *
 * partsCount() делит размер на переданный partSize->value(): вызывающий получает partSize() и отдаёт
 * его же в partsCount(), поэтому деление всегда идёт на тот размер части, который записывается в
 * MediaMultipartUpload (валидированный VO MediaMultipartPartSize, минимум 5 MiB). Согласованность пары
 * partSize/partsCount держится сигнатурой, а не только докблоком.
 */
final readonly class MediaUploadPlanner implements MediaUploadPlannerContract
{
    public function __construct(
        private MediaConfig $mediaConfig,
    ) {}

    public function stagingExpiration(): MediaExpiration
    {
        $expiresAt = new \DateTimeImmutable()->add(
            new \DateInterval(\sprintf('PT%dS', $this->mediaConfig->stagingTtlSeconds)),
        );

        return MediaExpiration::temporaryUntil($expiresAt);
    }

    public function isMultipart(MediaFileSize $size): bool
    {
        return $size->value() >= $this->mediaConfig->multipartThresholdBytes;
    }

    public function partSize(): MediaMultipartPartSize
    {
        return MediaMultipartPartSize::fromInt($this->mediaConfig->multipartPartSizeBytes);
    }

    public function partsCount(MediaFileSize $size, MediaMultipartPartSize $partSize): MediaMultipartPartsCount
    {
        return MediaMultipartPartsCount::fromInt((int) \ceil($size->value() / $partSize->value()));
    }
}

exec
/bin/zsh -lc "sed -n '1,260p' app/src/Modules/Media/Infrastructure/FileService/MediaUrlResolver.php && sed -n '1,260p' app/src/Modules/Media/Infrastructure/FileService/PresignedMediaUrlResolver.php && sed -n '1,260p' app/src/Modules/Media/Infrastructure/FileService/PublicMediaUrlResolver.php && sed -n '1,260p' app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php && sed -n '1,240p' app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/FindMediaOriginalUrlHandler.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\FileService;

use App\Modules\Media\Application\Dto\MediaUrlResult;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\ValueObject\MediaPath;

/**
 * Строит URL объекта медиа в зафиксированном контексте одного запроса. Способ (прямой публичный
 * URL или presigned со сроком) и единый срок истечения определяются один раз при создании резолвера
 * в MediaUrlService::resolverFor(), поэтому вызывающему остаётся передать только пару (storage, path) —
 * одинаково для оригинала и каждой конверсии.
 */
interface MediaUrlResolver
{
    public function resolve(MediaStorage $storage, MediaPath $path): MediaUrlResult;
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\FileService;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaUrlResult;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\ValueObject\MediaPath;

/**
 * Резолвер private-медиа: presigned GET-ссылка с единым сроком на весь набор URL одного запроса.
 */
final readonly class PresignedMediaUrlResolver implements MediaUrlResolver
{
    public function __construct(
        private MediaFileServiceContract $mediaFileService,
        private \DateTimeImmutable $expiresAt,
    ) {}

    public function resolve(MediaStorage $storage, MediaPath $path): MediaUrlResult
    {
        return new MediaUrlResult(
            url: $this->mediaFileService->presignGet(storage: $storage, path: $path, expiresAt: $this->expiresAt),
            expiresAt: $this->expiresAt,
        );
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\FileService;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaUrlResult;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\ValueObject\MediaPath;

/**
 * Резолвер public-медиа: прямой URL из бакета с anonymous-read policy, без срока.
 */
final readonly class PublicMediaUrlResolver implements MediaUrlResolver
{
    public function __construct(private MediaFileServiceContract $mediaFileService) {}

    public function resolve(MediaStorage $storage, MediaPath $path): MediaUrlResult
    {
        return new MediaUrlResult(
            url: $this->mediaFileService->publicUrl(storage: $storage, path: $path),
            expiresAt: null,
        );
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\FindMediaUrl;

use App\Modules\Media\Application\Contract\MediaUrlServiceContract;
use App\Modules\Media\Application\Dto\MediaUrlsResult;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Repository\MediaRepository;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Best-effort разрешение всех URL медиа по его id: оригинал (если не удалён) и все конверсии.
 * Грузит медиа вместе с конверсиями одним набором запросов и делегирует построение URL в
 * MediaUrlService. Возвращает null, если медиа нет или оно не финализировано, чтобы вызывающий
 * подставил значение по умолчанию (аватар в профиле) без try-catch.
 *
 * Для модулей, которые уже держат сущность Media загруженной (например, лента Posts через relation),
 * есть прямой путь MediaUrlService::getUrls(Media) — без повторной загрузки.
 */
final readonly class FindMediaUrlHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaUrlServiceContract $mediaUrlService,
    ) {}

    #[LogOperation]
    public function handle(FindMediaUrlQuery $query): MediaUrlsResult|null
    {
        $media = $this->mediaRepository->findByIdWithConversions(MediaId::fromString($query->mediaId));

        if ($media === null) {
            return null;
        }

        return $this->mediaUrlService->getUrls(media: $media, presignedTtlSeconds: $query->presignedTtlSeconds);
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\FindMediaOriginalUrl;

use App\Modules\Media\Application\Contract\MediaUrlServiceContract;
use App\Modules\Media\Application\Dto\MediaUrlResult;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Repository\MediaRepository;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Best-effort разрешение ссылки ТОЛЬКО на оригинал медиа по его id, для потребителей, которым
 * конверсии не нужны (аватар профиля). В отличие от FindMediaUrl, грузит медиа без конверсий
 * (findById, без ->load(...)) и строит только оригинал, не обращаясь к связям-конверсиям — это
 * убирает три лишних загрузки связей и подписание ссылок конверсий, результат которых тут же
 * отбрасывался бы.
 *
 * Возвращает null, если медиа нет, оно не финализировано или оригинал удалён (readyOriginalRemoved),
 * чтобы вызывающий подставил значение по умолчанию без try-catch.
 */
final readonly class FindMediaOriginalUrlHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaUrlServiceContract $mediaUrlService,
    ) {}

    #[LogOperation]
    public function handle(FindMediaOriginalUrlQuery $query): MediaUrlResult|null
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($query->mediaId));

        if ($media === null) {
            return null;
        }

        return $this->mediaUrlService->getOriginalUrl(
            media: $media,
            presignedTtlSeconds: $query->presignedTtlSeconds,
        );
    }
}

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Media/Application/Dto/MediaConversionUrl.php && sed -n '1,220p' app/src/Modules/Media/Application/Dto/MediaConversionUrlCollection.php && sed -n '1,220p' app/src/Modules/Media/Application/Dto/MediaUrlsResult.php && sed -n '1,260p' app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Dto;

use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaConversionKind;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;

/**
 * URL одной конверсии медиа. `kind` — вид (image/video/audio): чем рендерить на клиенте; постер видео
 * имеет kind = image, т.к. это кадр-картинка. `type` — конкретный профиль конверсии. Вызывающий
 * выбирает нужную по виду/типу, не зная заранее, какие конверсии есть. expiresAt = null для прямого
 * публичного URL, заполнен для presigned-ссылки private-медиа.
 */
final readonly class MediaConversionUrl
{
    public function __construct(
        public MediaConversionKind $kind,
        public MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType $type,
        public string $url,
        public \DateTimeImmutable|null $expiresAt,
    ) {}
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Dto;

use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, MediaConversionUrl>
 */
final class MediaConversionUrlCollection extends TypedCollection
{
    /**
     * Конверсия запрошенного типа или null, если её нет. Позволяет вызывающему выбрать нужный
     * профиль по типу, не зная заранее, какие конверсии присутствуют, и без отдельного запроса.
     */
    public function ofType(
        MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType $type,
    ): MediaConversionUrl|null {
        return $this->first(static fn(MediaConversionUrl $conversion): bool => $conversion->type === $type);
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Dto;

/**
 * Полный набор URL медиа: оригинал и все его конверсии. original = null, если оригинал удалён
 * (readyOriginalRemoved) — при этом конверсии продолжают резолвиться. Вызывающий получает всё
 * сразу и сам выбирает нужное по типу, не запрашивая конверсии по отдельности.
 */
final readonly class MediaUrlsResult
{
    public function __construct(
        public MediaUrlResult|null $original,
        public MediaConversionUrlCollection $conversions,
    ) {}
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\RequestMediaUpload;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Contract\MediaUploadPlannerContract;
use App\Modules\Media\Application\Dto\MediaFileMeta;
use App\Modules\Media\Application\Dto\RequestMediaUploadResult;
use App\Modules\Media\Application\Dto\MediaUploadSpec;
use App\Modules\Media\Application\Service\MediaTypeResolver;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaMultipartUpload;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use Psr\Log\LoggerInterface;

final readonly class RequestMediaUploadHandler
{
    public function __construct(
        private MediaFileServiceContract $mediaFileService,
        private MediaTypeResolver $mediaTypeResolver,
        private MediaUploadPlannerContract $uploadPlanner,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[LogOperation]
    public function handle(RequestMediaUploadCommand $command): RequestMediaUploadResult
    {
        $mediaType = $this->mediaTypeResolver->resolve($command->fileMeta->mimeType);
        $this->assertUploadAllowed(spec: $command->spec, fileMeta: $command->fileMeta);

        $storageKey = MediaStorageKey::generate();
        $media = Media::create(
            storageKey: $storageKey,
            type: $mediaType,
            visibility: $command->spec->visibility,
            path: MediaPath::originalUpload(storageKey: $storageKey, extension: $this->extension($command->fileMeta)),
            mimeType: $command->fileMeta->mimeType,
            size: $command->fileMeta->size,
            uploadedById: UserId::fromString($command->userId),
            expiration: $this->uploadPlanner->stagingExpiration(),
        );

        $presignedExpiresAt = $this->expiresIn($command->spec->presignedTtl->value());
        $preparedUpload = $this->uploadPlanner->isMultipart($command->fileMeta->size)
            ? $this->prepareMultipartUpload(media: $media, presignedExpiresAt: $presignedExpiresAt)
            : $this->prepareSingleUpload(media: $media, presignedExpiresAt: $presignedExpiresAt);

        $this->entityManager->run();

        $this->logger->debug(message: 'Запрошена загрузка медиа.', context: [
            'mediaId' => $media->id->value(),
            'userId' => $command->userId,
            'uploadMode' => $preparedUpload->uploadMode->value,
            'size' => $command->fileMeta->size->value(),
            'mimeType' => $command->fileMeta->mimeType->value(),
            'presignedTtlSeconds' => $command->spec->presignedTtl->value(),
        ]);

        return $preparedUpload;
    }

    private function prepareSingleUpload(Media $media, \DateTimeImmutable $presignedExpiresAt): RequestMediaUploadResult
    {
        $putUrl = $this->mediaFileService->presignPut(
            storage: $media->storage,
            path: $media->path,
            mimeType: $media->mimeType,
            expiresAt: $presignedExpiresAt,
        );
        $this->entityManager->persist($media);

        return RequestMediaUploadResult::single(
            mediaId: $media->id->value(),
            putUrl: $putUrl,
            expiresAt: $presignedExpiresAt,
        );
    }

    private function prepareMultipartUpload(Media $media, \DateTimeImmutable $presignedExpiresAt): RequestMediaUploadResult
    {
        $partSize = $this->uploadPlanner->partSize();
        $partsCount = $this->uploadPlanner->partsCount(size: $media->size, partSize: $partSize);
        $uploadId = $this->mediaFileService->createMultipartUpload(
            storage: $media->storage,
            path: $media->path,
            mimeType: $media->mimeType,
        );
        $parts = $this->mediaFileService->presignUploadParts(
            storage: $media->storage,
            path: $media->path,
            uploadId: $uploadId,
            partsCount: $partsCount,
            expiresAt: $presignedExpiresAt,
        );

        $this->entityManager->persist($media);
        $this->entityManager->persist(MediaMultipartUpload::create(
            media: $media,
            uploadId: $uploadId,
            partsCount: $partsCount,
            partSize: $partSize,
            fileSize: $media->size,
        ));

        return RequestMediaUploadResult::multipart(
            mediaId: $media->id->value(),
            uploadId: $uploadId->value(),
            parts: $parts,
            expiresAt: $presignedExpiresAt,
        );
    }

    private function assertUploadAllowed(MediaUploadSpec $spec, MediaFileMeta $fileMeta): void
    {
        if (!$spec->allowedMimeTypes->containsMimeType($fileMeta->mimeType)) {
            throw new ValidationException(
                translationKey: 'app.media.mime_not_allowed',
                translationParameters: ['mimeType' => $fileMeta->mimeType->value()],
            );
        }

        if ($fileMeta->size->value() > $spec->maxSize->value()) {
            throw new ValidationException('app.media.file_size_exceeded');
        }
    }

    private function extension(MediaFileMeta $fileMeta): string
    {
        $extension = \pathinfo(path: $fileMeta->fileName, flags: \PATHINFO_EXTENSION);

        if ($extension === '') {
            throw new ValidationException('app.media.file_name_without_extension');
        }

        return $extension;
    }

    private function expiresIn(int $seconds): \DateTimeImmutable
    {
        return new \DateTimeImmutable()->add(new \DateInterval(\sprintf('PT%dS', $seconds)));
    }
}

codex
По первым файлам вижу, что текущий пункт про `Application/Service` не выглядит ложным: каталог реально используется, а архитектурный документ в основном дереве его явно не называет. Дальше проверяю места, где новое поведение может сломать соседние модули: сборку публичного профиля, ленту постов, связи Cycle и регистрацию контрактов.
exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Media/Domain/Enum/MediaStatus.php && sed -n '1,260p' app/src/Shared/Infrastructure/Configuration/Media/MediaConfig.php && sed -n '1,260p' app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php && sed -n '1,220p' app/src/Modules/User/Infrastructure/Bootloader/UserBootloader.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "sed -n '1,320p' app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php && sed -n '1,260p' app/src/Modules/User/Application/Command/CreateUser/CreateUserHandler.php && sed -n '1,260p' app/src/Shared/Domain/Locale/LocaleResolver.php && sed -n '1,220p' app/src/Shared/Infrastructure/Framework/Bootloader/AppBootloader.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Enum;

enum MediaStatus: string
{
    case WaitingUpload = 'waitingUpload';
    case CompletingMultipartUpload = 'completingMultipartUpload';
    case MultipartCompletionFailedCanRetry = 'multipartCompletionFailedCanRetry';
    case MultipartCompletionFailedNeedReupload = 'multipartCompletionFailedNeedReupload';
    case Uploaded = 'uploaded';
    case Processing = 'processing';
    case ProcessingFailed = 'processingFailed';
    case Ready = 'ready';
    case ReadyOriginalRemoved = 'readyOriginalRemoved';
}
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Media;

use App\Shared\Infrastructure\Configuration\TypedConfig;
use App\Shared\Infrastructure\Exception\InvalidConfigValueException;

final readonly class MediaConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'media';
    }

    public function __construct(
        public int $stagingTtlSeconds,
        public int $multipartThresholdBytes,
        public int $multipartPartSizeBytes,
        public string $imageProcessingDriver,
        public string $ffmpegBinaryPath,
        public string $ffprobeBinaryPath,
        public int $ffmpegTimeoutSeconds,
        public int $ffmpegThreads,
        public int $presignedTtlSeconds,
    ) {
        // Порог multipart должен быть не меньше размера части: иначе файл чуть больше порога,
        // но меньше одной части ушёл бы в multipart с единственной частью — это бессмысленно
        // относительно одиночного PUT и потенциально нарушает лимит S3 ≥ 5 MiB на часть.
        if ($multipartThresholdBytes < $multipartPartSizeBytes) {
            throw new InvalidConfigValueException(
                path: 'media.multipartThresholdBytes',
                expected: \sprintf('>= multipartPartSizeBytes (%d)', $multipartPartSizeBytes),
                actual: (string) $multipartThresholdBytes,
            );
        }

        // Верхняя граница срока presigned-ссылки скачивания: значение больше 7 суток (604800)
        // принимает конфиг (\max(1, ...) обрезает только снизу), но MediaPresignedTtl::fromInt его
        // отвергнет — и приложение, стартовав, падало бы с 500 на каждом построении ссылки для
        // приватного медиа. Поэтому отказываем сразу при старте. Граница 604800 продублирована из
        // MediaPresignedTtl::MAX — держать синхронно с ним: при изменении максимума в VO обновить и
        // это значение. Прямой импорт VO ради ::MAX недопустим (константа protected и завёл бы
        // зависимость Shared/Infrastructure → Modules/Media/Domain), поэтому связь держим комментарием.
        if ($presignedTtlSeconds > 604_800) {
            throw new InvalidConfigValueException(
                path: 'media.presignedTtlSeconds',
                expected: '<= 604800',
                actual: (string) $presignedTtlSeconds,
            );
        }
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Bootloader;

use App\Modules\Media\Application\Contract\MediaAudioProcessorContract;
use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Contract\MediaImageProcessorContract;
use App\Modules\Media\Application\Contract\MediaUploadPlannerContract;
use App\Modules\Media\Application\Contract\MediaVideoProcessorContract;
use App\Modules\Media\Application\Contract\MediaUrlServiceContract;
use App\Modules\Media\Application\Message\MediaUploaded;
use App\Modules\Media\Infrastructure\FileService\ConfiguredS3ClientProvider;
use App\Modules\Media\Infrastructure\FileService\FfmpegMediaAudioProcessor;
use App\Modules\Media\Infrastructure\FileService\FfmpegMediaVideoProcessor;
use App\Modules\Media\Infrastructure\FileService\ImagickMediaImageProcessor;
use App\Modules\Media\Infrastructure\FileService\MediaUploadPlanner;
use App\Modules\Media\Infrastructure\FileService\MediaUrlService;
use App\Modules\Media\Infrastructure\FileService\S3ClientProvider;
use App\Modules\Media\Infrastructure\FileService\S3MediaFileService;
use App\Modules\Media\Presentation\Job\ProcessMediaJob;
use App\Modules\Outbox\Application\Contract\OutboxJobRegistryContract;
use Spiral\Boot\Bootloader\Bootloader;

final class MediaBootloader extends Bootloader
{
    protected const BINDINGS = [
        MediaFileServiceContract::class => S3MediaFileService::class,
        MediaUrlServiceContract::class => MediaUrlService::class,
        MediaUploadPlannerContract::class => MediaUploadPlanner::class,
        MediaImageProcessorContract::class => ImagickMediaImageProcessor::class,
        MediaVideoProcessorContract::class => FfmpegMediaVideoProcessor::class,
        MediaAudioProcessorContract::class => FfmpegMediaAudioProcessor::class,
        S3ClientProvider::class => ConfiguredS3ClientProvider::class,
    ];

    public function boot(OutboxJobRegistryContract $outboxJobRegistry): void
    {
        $outboxJobRegistry->register(
            outboxMessageClass: MediaUploaded::class,
            outboxJobClass: ProcessMediaJob::class,
        );
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Bootloader;

use App\Modules\Media\Application\Query\FindMediaOriginalUrl\FindMediaOriginalUrlHandler;
use App\Modules\User\Application\Profile\UserPublicProfileAssembler;
use App\Shared\Infrastructure\Configuration\User\UserConfig;
use GianTiaga\SpiralCqrs\QueryBusInterface;
use Spiral\Boot\Bootloader\Bootloader;

/**
 * Бутлоадер модуля User. Собирает UserPublicProfileAssembler, передавая ему готовую ссылку на аватар
 * по умолчанию из UserConfig: конфиг читается здесь, в Infrastructure, а Application получает строку
 * без знания про *Config.
 */
final class UserBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            UserPublicProfileAssembler::class => [self::class, 'userPublicProfileAssembler'],
        ];
    }

    protected static function userPublicProfileAssembler(
        QueryBusInterface $queryBus,
        FindMediaOriginalUrlHandler $findMediaOriginalUrlHandler,
        UserConfig $userConfig,
    ): UserPublicProfileAssembler {
        return new UserPublicProfileAssembler(
            queryBus: $queryBus,
            findMediaOriginalUrlHandler: $findMediaOriginalUrlHandler,
            defaultAvatarUrl: $userConfig->defaultAvatarUrl,
        );
    }
}

 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Profile;

use App\Modules\Media\Application\Query\FindMediaOriginalUrl\FindMediaOriginalUrlHandler;
use App\Modules\Media\Application\Query\FindMediaOriginalUrl\FindMediaOriginalUrlQuery;
use App\Modules\User\Application\Dto\UserPublicProfileView;
use App\Modules\User\Domain\Entity\User;
use GianTiaga\SpiralCqrs\QueryBusInterface;

/**
 * Собирает публичный профиль из доменной сущности User, разрешая ссылку на аватар через модуль
 * Media. Аватар всегда непустой: если у пользователя нет аватара или его медиа недоступно
 * (удалено/не готово) — подставляется готовая ссылка на аватар по умолчанию (её передаёт
 * Infrastructure при сборке ассемблера, источник Application не знает).
 *
 * Используется FindMediaOriginalUrl (только оригинал, без подгрузки и подписания конверсий): для
 * аватара нужна одна ссылка, а в листингах профилей сборка вызывается на каждого пользователя, поэтому
 * лишние загрузки связей конверсий множились бы на число пользователей.
 *
 * Межмодульный Query идёт через QueryBus (как в PostViewAssembler): шина возвращает ровно тип
 * Handler::handle() (MediaUrlResult|null), поэтому контракт «медиа недоступно -> null -> значение по
 * умолчанию» сохраняется без try-catch, а middleware обработчика (в том числе #[LogOperation]) работает.
 */
final readonly class UserPublicProfileAssembler
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private FindMediaOriginalUrlHandler $findMediaOriginalUrlHandler,
        private string $defaultAvatarUrl,
    ) {}

    public function fromUser(User $user): UserPublicProfileView
    {
        return new UserPublicProfileView(
            userId: $user->id->value(),
            name: $user->name->value(),
            avatarUrl: $this->resolveAvatarUrl($user),
            locale: $user->locale->value,
        );
    }

    private function resolveAvatarUrl(User $user): string
    {
        $mediaId = $user->avatar->value();

        if ($mediaId === null) {
            return $this->defaultAvatarUrl;
        }

        $originalUrl = $this->queryBus->dispatch(
            query: new FindMediaOriginalUrlQuery(mediaId: $mediaId),
            handler: $this->findMediaOriginalUrlHandler->handle(...),
        );

        // Если медиа недоступно или оригинал удалён (readyOriginalRemoved) — null, подставляем
        // значение по умолчанию.
        if ($originalUrl === null) {
            return $this->defaultAvatarUrl;
        }

        return $originalUrl->url;
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Command\CreateUser;

use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Repository\ReservedNicknameRepository;
use App\Modules\User\Repository\UserRepository;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Locale\LocaleResolver;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

final readonly class CreateUserHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private ReservedNicknameRepository $reservedNicknameRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private LocaleResolver $localeResolver,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(CreateUserCommand $command): CreateUserResult
    {
        $email = Email::fromString($command->email);
        $nickname = UserNickname::fromString($command->nickname);

        if ($this->userRepository->existsByEmail($email)) {
            throw new ValidationException('app.user.email_taken');
        }

        if (
            $this->userRepository->existsByNickname($nickname)
            || $this->reservedNicknameRepository->isReserved($nickname)
        ) {
            throw new ValidationException('app.user.nickname_taken');
        }

        $user = User::create(
            name: UserName::fromString($command->name),
            email: $email,
            nickname: $nickname,
            locale: $this->resolveLocale($command->locale),
        );
        $user->confirmEmail();

        $this->entityManager->persist($user);
        $this->entityManager->run();

        $this->logger->debug(message: 'Пользователь создан.', context: [
            'userId' => $user->id->value(),
            'email' => $email->value(),
        ]);

        return new CreateUserResult(userId: $user->id->value());
    }

    /**
     * Нормализует локаль запроса: неподдерживаемое значение сводится к значению по умолчанию через
     * LocaleResolver, чтобы вход вне HTTP-потока (консоль, очередь) не приводил к 500 из-за Locale::from().
     */
    private function resolveLocale(string $locale): Locale
    {
        return Locale::from($this->localeResolver->resolve($locale));
    }
}
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Locale;

/**
 * Доменный сервис разбора локали: сводит запрошенный код к поддерживаемому или к значению по
 * умолчанию. Поддерживаемые локали и значение по умолчанию приходят готовыми из Infrastructure
 * (бутлоадер собирает сервис из LocaleConfig), поэтому Application не знает про конфиг.
 *
 * Возвращает строку (код локали), а не enum Locale: результат идёт прямо в translator->setLocale,
 * а потребителю, которому нужен enum, проще обернуть результат в Locale::from() у себя.
 */
final readonly class LocaleResolver
{
    /**
     * @param list<string> $supported
     */
    public function __construct(
        private array $supported,
        private string $default,
    ) {}

    public function resolve(string $locale): string
    {
        return \in_array(needle: $locale, haystack: $this->supported, strict: true)
            ? $locale
            : $this->default;
    }
}
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Framework\Bootloader;

use App\Shared\Domain\Locale\LocaleResolver;
use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
use Spiral\Bootloader\DomainBootloader;
use Spiral\Cycle\Interceptor\CycleInterceptor;
use Spiral\DataGrid\Interceptor\GridInterceptor;
use Spiral\Domain\GuardInterceptor;
use Psr\Clock\ClockInterface;
use Spiral\Interceptors\HandlerInterface;
use Symfony\Component\Clock\NativeClock;
use GianTiaga\SpiralApiErrors\Interceptor\ApiExceptionInterceptor;
use GianTiaga\SpiralOpenApi\Response\Interceptor\HttpResponseInterceptor;

/**
 * @link https://spiral.dev/docs/http-interceptors
 */
final class AppBootloader extends DomainBootloader
{
    protected const array INTERCEPTORS = [
        CycleInterceptor::class,
        GridInterceptor::class,
        GuardInterceptor::class,
        HttpResponseInterceptor::class,
        ApiExceptionInterceptor::class,
    ];

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            ...parent::defineSingletons(),
            HandlerInterface::class => [self::class, 'domainCore'],
            ClockInterface::class => NativeClock::class,
            LocaleResolver::class => [self::class, 'localeResolver'],
        ];
    }

    /**
     * Собирает доменный LocaleResolver из конфига локали. Конфиг читается здесь, в Infrastructure,
     * чтобы Application получал готовый сервис без знания про *Config.
     */
    protected static function localeResolver(LocaleConfig $localeConfig): LocaleResolver
    {
        return new LocaleResolver(supported: $localeConfig->supported, default: $localeConfig->default);
    }
}

exec
/bin/zsh -lc "sed -n '1,300p' app/src/Modules/Posts/Domain/Entity/PostMedia.php && sed -n '1,320p' app/src/Modules/Posts/Application/View/PostViewAssembler.php && sed -n '1,300p' app/src/Modules/Posts/Application/Post/PostContentComposer.php && sed -n '1,320p' app/src/Modules/Posts/Repository/PostMediaRepository.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Entity;

use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Posts\Domain\ValueObject\MediaPosition;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\PostMediaId;
use App\Modules\Posts\Domain\ValueObject\PostMediaReference;
use App\Modules\Posts\Repository\PostMediaRepository;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Infrastructure\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\BelongsTo;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'post_media',
    table: 'post_media',
    repository: PostMediaRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class PostMedia
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: PostMediaId::class)]
    public private(set) PostMediaId $id;

    #[Column(type: 'uuid', name: 'post_id', typecast: PostId::class)]
    public private(set) PostId $postId;

    #[Column(type: 'uuid', name: 'media_id', typecast: PostMediaReference::class)]
    public private(set) PostMediaReference $mediaId;

    #[Column(type: 'integer', typecast: MediaPosition::class)]
    public private(set) MediaPosition $position;

    #[BelongsTo(target: Post::class, innerKey: 'post_id', outerKey: 'id', fkOnDelete: 'CASCADE')]
    public private(set) Post $post;

    /**
     * Ссылка на медиа модуля Media. Media — универсальный (foundational) модуль, на сущности которого
     * другим модулям разрешено держать relation на чтение (см. docs/arch.md). cascade: false — Posts
     * не сохраняет и не меняет Media; fkCreate/indexCreate: false — FK media_id уже создан миграцией
     * post_media, повторно его не заводим. Запись идёт по колонке mediaId, связь — для eager-load при
     * сборке URL: лента строит оригинал через MediaUrlService::getOriginalUrl, а getUrls — общий путь
     * полного набора (оригинал + конверсии, например для FindMediaUrl). Доступ без eager-load вызовет
     * ленивую подгрузку.
     */
    #[BelongsTo(
        target: Media::class,
        innerKey: 'media_id',
        outerKey: 'id',
        cascade: false,
        fkCreate: false,
        indexCreate: false,
    )]
    public private(set) Media $media;

    public static function create(Post $post, PostMediaReference $mediaId, MediaPosition $position): self
    {
        $postMedia = new self();
        $postMedia->id = PostMediaId::generate();
        $postMedia->post = $post;
        $postMedia->postId = $post->id;
        $postMedia->mediaId = $mediaId;
        $postMedia->position = $position;
        $postMedia->initializeTimestamps();

        return $postMedia;
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\View;

use App\Modules\Media\Application\Contract\MediaUrlServiceContract;
use App\Modules\Posts\Application\Post\PostVisibilityPolicy;
use App\Modules\Posts\Domain\Collection\PostCollection;
use App\Modules\Posts\Domain\Collection\PostMediaCollection;
use App\Modules\Posts\Domain\Collection\PostTagCollection;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Entity\PostMedia;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Repository\PostLikeRepository;
use App\Modules\Posts\Repository\PostMediaRepository;
use App\Modules\Posts\Repository\PostRepository;
use App\Modules\Posts\Repository\PostTagRepository;
use App\Modules\Tags\Application\Dto\TagTextCollection;
use App\Modules\Tags\Application\Query\GetTags\GetTagsHandler;
use App\Modules\Tags\Application\Query\GetTags\GetTagsQuery;
use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileHandler;
use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileQuery;
use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesHandler;
use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesQuery;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\QueryBusInterface;

/**
 * Собирает read-model PostView из доменной записи, обогащая её данными смежных модулей: автор —
 * через User, URL медиа — через Media, тексты тегов — через Tags; флаг likedByMe и счётчики — из
 * своего модуля. Для репоста доглубляет исходную запись на один уровень (без её собственного
 * оригинала), если она видна зрителю.
 *
 * В листингах пакетно собираются выборки из БД: авторы, флаги likedByMe, медиа и теги берутся одним
 * запросом на страницу (без N+1 на уровне БД). Вложения и их сущности Media грузятся пакетно в
 * PostMediaRepository (eager media.*), поэтому URL оригинала строится в памяти из уже загруженной
 * сущности через MediaUrlService::getOriginalUrl — отдельного запроса в базу на вложение нет (для
 * приватного медиа остаётся только подпись presigned-ссылки на оригинал, не запрос в БД).
 */
final readonly class PostViewAssembler
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private GetUserPublicProfileHandler $getUserPublicProfileHandler,
        private GetUserPublicProfilesHandler $getUserPublicProfilesHandler,
        private MediaUrlServiceContract $mediaUrlService,
        private GetTagsHandler $getTagsHandler,
        private PostRepository $postRepository,
        private PostMediaRepository $postMediaRepository,
        private PostTagRepository $postTagRepository,
        private PostLikeRepository $postLikeRepository,
    ) {}

    public function fromPost(Post $post, UserId $viewer): PostView
    {
        $tags = $this->postTagRepository->findByPostId($post->id);

        return $this->build(
            post: $post,
            author: $this->authorView($post->userId),
            likedByMe: $this->postLikeRepository->existsByPostAndUser(postId: $post->id, userId: $viewer),
            media: $this->mediaItems($this->postMediaRepository->findByPostId($post->id)),
            tags: $this->tagViews(tags: $tags, texts: $this->tagTexts($tags)),
            original: $this->originalView(post: $post, viewer: $viewer),
        );
    }

    public function fromPosts(PostCollection $posts, UserId $viewer): PostViewCollection
    {
        if ($posts->isEmpty()) {
            return new PostViewCollection();
        }

        $postIds = $this->postIds($posts);
        $authors = $this->authorViews($posts);
        $likedPostIds = $this->likedPostIds(posts: $posts, viewer: $viewer);
        $mediaByPost = $this->mediaCollectionsByPost($postIds);
        $tagsByPost = $this->tagCollectionsByPost($postIds);
        $tagTexts = $this->tagTexts(...\array_values($tagsByPost));
        $originals = $this->originalViewsByPost(posts: $posts, viewer: $viewer);

        return new PostViewCollection(
            $posts->toBase()->map(function (Post $post) use ($authors, $likedPostIds, $mediaByPost, $tagsByPost, $tagTexts, $originals): PostView {
                $postId = $post->id->value();
                $originalId = $post->original->value();

                return $this->build(
                    post: $post,
                    author: $this->requireAuthor(authors: $authors, userId: $post->userId->value()),
                    likedByMe: isset($likedPostIds[$postId]),
                    media: $this->mediaItems($mediaByPost[$postId] ?? new PostMediaCollection()),
                    tags: $this->tagViews(tags: $tagsByPost[$postId] ?? new PostTagCollection(), texts: $tagTexts),
                    original: $originalId !== null ? ($originals[$originalId] ?? null) : null,
                );
            }),
        );
    }

    /**
     * @param list<PostMediaItemView> $media
     * @param list<TagView> $tags
     */
    private function build(Post $post, AuthorView $author, bool $likedByMe, array $media, array $tags, PostView|null $original): PostView
    {
        return new PostView(
            id: $post->id->value(),
            text: $post->text->value(),
            status: $post->status->value,
            attachmentType: $this->attachmentType(post: $post, media: $media)->value,
            likesCount: $post->likesCount->value(),
            repostsCount: $post->repostsCount->value(),
            commentsCount: $post->commentsCount->value(),
            likedByMe: $likedByMe,
            createdAt: $post->createdAt->format(\DateTimeInterface::ATOM),
            author: $author,
            media: $media,
            tags: $tags,
            original: $original,
        );
    }

    /**
     * Тип вложения для ответа. Если запись помечена как media, но после мягкой деградации
     * недоступного вложения видимых медиа не осталось, отдаём none — иначе клиент получил бы
     * attachmentType "media" с пустым media и рассогласованный контракт.
     *
     * @param list<PostMediaItemView> $media
     */
    private function attachmentType(Post $post, array $media): AttachmentType
    {
        if ($post->attachmentType === AttachmentType::Media && $media === []) {
            return AttachmentType::None;
        }

        return $post->attachmentType;
    }

    private function authorView(UserId $userId): AuthorView
    {
        $profile = $this->queryBus->dispatch(
            query: new GetUserPublicProfileQuery($userId->value()),
            handler: $this->getUserPublicProfileHandler->handle(...),
        );

        return new AuthorView(userId: $profile->userId, name: $profile->name, avatarUrl: $profile->avatarUrl);
    }

    /**
     * @return array<string, AuthorView>
     */
    private function authorViews(PostCollection $posts): array
    {
        $userIds = \array_values($posts
            ->toBase()
            ->map(static fn(Post $post): string => $post->userId->value())
            ->unique()
            ->all());

        $profiles = $this->queryBus->dispatch(
            query: new GetUserPublicProfilesQuery($userIds),
            handler: $this->getUserPublicProfilesHandler->handle(...),
        );

        $authors = [];

        foreach ($profiles as $profile) {
            $authors[$profile->userId] = new AuthorView(
                userId: $profile->userId,
                name: $profile->name,
                avatarUrl: $profile->avatarUrl,
            );
        }

        return $authors;
    }

    /**
     * Берёт автора из пакетной карты профилей. GetUserPublicProfiles молча опускает отсутствующих,
     * поэтому отсутствие ключа обрабатываем явно — той же 404, что и одиночный путь
     * (fromPost -> GetUserPublicProfile), а не неконтролируемым undefined array key -> 500.
     *
     * @param array<string, AuthorView> $authors
     */
    private function requireAuthor(array $authors, string $userId): AuthorView
    {
        return $authors[$userId] ?? throw new NotFoundException('app.user.not_found');
    }

    /**
     * @return array<string, true>
     */
    private function likedPostIds(PostCollection $posts, UserId $viewer): array
    {
        $postIds = $posts->mapToList(static fn(Post $post): PostId => $post->id);
        $liked = [];

        foreach ($this->postLikeRepository->findByUserAndPostIds($viewer, ...$postIds) as $like) {
            $liked[$like->postId->value()] = true;
        }

        return $liked;
    }

    /**
     * Медиа набора записей одним запросом, сгруппированные по записи (без N+1 в листинге).
     * Значение — типизированная коллекция вложений, поэтому контракт не содержит вложенных массивов.
     *
     * @param list<PostId> $postIds
     *
     * @return array<string, PostMediaCollection>
     */
    private function mediaCollectionsByPost(array $postIds): array
    {
        $byPost = [];

        foreach ($this->postMediaRepository->findByPostIds(...$postIds) as $postMedia) {
            $byPost[$postMedia->postId->value()] ??= new PostMediaCollection();
            $byPost[$postMedia->postId->value()]->push($postMedia);
        }

        return $byPost;
    }

    /**
     * Теги набора записей одним запросом, сгруппированные по записи (без N+1 в листинге).
     *
     * @param list<PostId> $postIds
     *
     * @return array<string, PostTagCollection>
     */
    private function tagCollectionsByPost(array $postIds): array
    {
        $byPost = [];

        foreach ($this->postTagRepository->findByPostIds(...$postIds) as $postTag) {
            $byPost[$postTag->postId->value()] ??= new PostTagCollection();
            $byPost[$postTag->postId->value()]->push($postTag);
        }

        return $byPost;
    }

    /**
     * @return list<PostMediaItemView>
     */
    private function mediaItems(PostMediaCollection $media): array
    {
        $items = [];

        foreach ($media as $postMedia) {
            $item = $this->mediaItem($postMedia);

            if ($item === null) {
                continue;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Разрешает ссылку на медиа записи из УЖЕ загруженной сущности Media (relation post_media.media
     * грузится eager в PostMediaRepository вместе с конверсиями — без N+1 на вложение). Построение URL
     * делегируется MediaUrlService без обращения в БД. Если медиа не готово или его оригинал удалён,
     * недоступное вложение исключается из ответа, а не роняет чтение ленты в 500. Берётся только
     * оригинал (getOriginalUrl): конверсии (постер видео, превью) сейчас в ленте не используются, а
     * подписывать presigned-ссылки на каждую из них впустую для private-медиа не нужно — это отдельная
     * задача.
     */
    private function mediaItem(PostMedia $postMedia): PostMediaItemView|null
    {
        $originalUrl = $this->mediaUrlService->getOriginalUrl(media: $postMedia->media);

        if ($originalUrl === null) {
            return null;
        }

        return new PostMediaItemView(
            mediaId: $postMedia->mediaId->value(),
            url: $originalUrl->url,
            position: $postMedia->position->value(),
        );
    }

    /**
     * Тексты тегов набора связей одним запросом к Tags. Несколько коллекций (по записи в листинге)
     * собираются в один батч, чтобы не ходить в Tags на каждую запись.
     */
    private function tagTexts(PostTagCollection ...$tagCollections): TagTextCollection
    {
        $tagIds = [];

        foreach ($tagCollections as $tags) {
            foreach ($tags as $postTag) {
                $tagIds[] = $postTag->tagId->value();
            }
        }

        if ($tagIds === []) {
            return new TagTextCollection();
        }

        return $this->queryBus->dispatch(
            query: new GetTagsQuery(\array_values(\array_unique($tagIds))),
            handler: $this->getTagsHandler->handle(...),
        );
    }

    /**
     * Строит представления тегов записи из общего батча текстов. В листинге батч содержит теги всех
     * записей страницы, поэтому теги, не относящиеся к этой записи, отсеиваются по принадлежности.
     * Принадлежность проверяется по заранее построенному множеству id связей записи за O(1) (вместо
     * линейного поиска по связям на каждый элемент батча) — это убирает квадратичность на странице с
     * тег-тяжёлыми записями. Порядок тегов в ответе определяется выборкой текстов из Tags и не
     * является частью контракта (в post_tags нет колонки позиции — порядок добавления не хранится).
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Post;

use App\Modules\Media\Application\Command\MakeMediaPermanent\MakeMediaPermanentCommand;
use App\Modules\Media\Application\Command\MakeMediaPermanent\MakeMediaPermanentHandler;
use App\Modules\Media\Application\Query\CheckMediaAttachable\CheckMediaAttachableHandler;
use App\Modules\Media\Application\Query\CheckMediaAttachable\CheckMediaAttachableQuery;
use App\Modules\Posts\Application\Notification\PostNotificationType;
use App\Modules\Posts\Application\Notification\PostNotifier;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Entity\PostMedia;
use App\Modules\Posts\Domain\Entity\PostMention;
use App\Modules\Posts\Domain\Entity\PostTag;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\MediaPosition;
use App\Modules\Posts\Domain\ValueObject\PostMediaReference;
use App\Modules\Posts\Repository\PostMentionRepository;
use App\Modules\Tags\Application\Command\ResolveTags\ResolveTagsCommand;
use App\Modules\Tags\Application\Command\ResolveTags\ResolveTagsHandler;
use App\Modules\User\Application\Dto\UserPublicProfileCollection;
use App\Shared\Domain\ValueObject\TagId;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralCqrs\QueryBusInterface;

/**
 * Общая сборка содержимого записи для сценариев создания и репоста: разрешение тегов, вложение
 * медиа (проверка + перевод в permanent), теги и упоминания, а также стейджинг уведомлений
 * post_mention/post_repost. Все вызовы смежных модулей идут внутри той же транзакции Handler-а
 * (вложенный #[Transactional]-dispatch -> SAVEPOINT), persist выполняет этот сервис, а финальный
 * run() — вызывающий Handler.
 */
final readonly class PostContentComposer
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private ResolveTagsHandler $resolveTagsHandler,
        private CheckMediaAttachableHandler $checkMediaAttachableHandler,
        private MakeMediaPermanentHandler $makeMediaPermanentHandler,
        private MentionRecipientResolver $mentionRecipientResolver,
        private PostNotifier $postNotifier,
        private PostMentionRepository $postMentionRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @param list<string> $texts
     *
     * @return list<string>
     */
    public function resolveTags(array $texts, string $creatorUserId): array
    {
        if ($texts === []) {
            return [];
        }

        return $this->commandBus->dispatch(
            command: new ResolveTagsCommand(texts: $texts, creatorUserId: $creatorUserId),
            handler: $this->resolveTagsHandler->handle(...),
        )->tagIds;
    }

    /**
     * Проверяет каждое медиа (существование, владелец, готовность), переводит в permanent и
     * сохраняет строки вложения. Дубликаты в списке схлопываются (уникальный индекс
     * (post_id, media_id)). Порядок вложений — позиция по порядку в списке.
     *
     * @param list<string> $mediaIds
     */
    public function attachMedia(Post $post, array $mediaIds, string $ownerUserId): void
    {
        $position = 0;

        foreach (\array_values(\array_unique($mediaIds)) as $mediaId) {
            $this->queryBus->dispatch(
                query: new CheckMediaAttachableQuery(mediaId: $mediaId, ownerUserId: $ownerUserId),
                handler: $this->checkMediaAttachableHandler->handle(...),
            );
            $this->commandBus->dispatch(
                command: new MakeMediaPermanentCommand(userId: $ownerUserId, mediaId: $mediaId),
                handler: $this->makeMediaPermanentHandler->handle(...),
            );
            $this->entityManager->persist(PostMedia::create(
                post: $post,
                mediaId: PostMediaReference::fromString($mediaId),
                position: MediaPosition::fromInt($position),
            ));
            $position++;
        }
    }

    /**
     * @param list<string> $tagIds
     */
    public function attachTags(Post $post, array $tagIds): void
    {
        foreach ($tagIds as $tagId) {
            $this->entityManager->persist(PostTag::create(postId: $post->id, tagId: TagId::fromString($tagId)));
        }
    }

    /**
     * Сохраняет упоминания записи и стейджит post_mention каждому упомянутому (кроме автора).
     * Дубликаты в списке схлопываются; несуществующий пользователь -> 422. Для черновика
     * упоминания только сохраняются, без рассылки уведомлений: чужой черновик невидим и deep-link
     * вёл бы в 404. Уведомления по сохранённым упоминаниям рассылает publish через notifyPostMentions.
     *
     * @param list<string> $mentionIds
     */
    public function attachPostMentions(Post $post, array $mentionIds, string $actorUserId): void
    {
        $uniqueIds = \array_values(\array_unique($mentionIds));

        if ($uniqueIds === []) {
            return;
        }

        // Для черновика рассылки нет, поэтому достаточно дешёвой проверки существования без сборки
        // профилей с разрешением ссылки на аватар. Уведомления по сохранённым упоминаниям рассылает
        // publish через notifyPostMentions, когда запись станет видимой.
        if ($post->status === PostStatus::Draft) {
            $this->mentionRecipientResolver->requireAllExist($uniqueIds);
            $this->persistMentions(post: $post, mentionIds: $uniqueIds);

            return;
        }

        // Опубликованная запись: строгое разрешение профилей (проверка полноты -> 422) и сразу
        // рассылка post_mention существующим получателям.
        $recipients = $this->mentionRecipientResolver->resolveRequired($uniqueIds);
        $this->persistMentions(post: $post, mentionIds: $uniqueIds);
        $this->notifyMentions(post: $post, recipients: $recipients, actorUserId: $actorUserId);
    }

    /**
     * @param list<string> $mentionIds
     */
    private function persistMentions(Post $post, array $mentionIds): void
    {
        foreach ($mentionIds as $mentionId) {
            $this->entityManager->persist(PostMention::create(postId: $post->id, userId: UserId::fromString($mentionId)));
        }
    }

    /**
     * Рассылает post_mention по уже сохранённым упоминаниям записи. Вызывается при публикации
     * черновика, чтобы упомянутые получили уведомление с рабочей ссылкой только после того, как
     * запись стала видимой.
     */
    public function notifyPostMentions(Post $post, string $actorUserId): void
    {
        $recipientIds = $this->postMentionRepository->findByPostId($post->id)
            ->mapToList(static fn(PostMention $postMention): string => $postMention->userId->value());

        if ($recipientIds === []) {
            return;
        }

        // Мягкий путь: упоминания уже отвалидированы при создании черновика, к моменту публикации
        // кого-то могло не стать -> недоступные тихо пропускаются, без строгой проверки полноты.
        $this->notifyMentions(
            post: $post,
            recipients: $this->mentionRecipientResolver->resolveExisting($recipientIds),
            actorUserId: $actorUserId,
        );
    }

    private function notifyMentions(Post $post, UserPublicProfileCollection $recipients, string $actorUserId): void
    {
        $actor = $this->mentionRecipientResolver->profile($actorUserId);

        foreach ($recipients as $recipient) {
            $this->postNotifier->notify(
                type: PostNotificationType::PostMention,
                actor: $actor,
                recipient: $recipient,
                actionType: 'post',
                actionId: $post->id->value(),
            );
        }
    }

    /**
     * Стейджит уведомление автору записи о действии над ней (репост, лайк). Самодействие
     * (автор == инициатор) не уведомляет.
     */
    public function notifyPostAuthor(
        PostNotificationType $type,
        UserId $postAuthor,
        string $actorUserId,
        string $postId,
    ): void {
        if ($postAuthor->value() === $actorUserId) {
            return;
        }

        $this->postNotifier->notify(
            type: $type,
            actor: $this->mentionRecipientResolver->profile($actorUserId),
            recipient: $this->mentionRecipientResolver->profile($postAuthor->value()),
            actionType: 'post',
            actionId: $postId,
        );
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Repository;

use App\Modules\Posts\Domain\Collection\PostMediaCollection;
use App\Modules\Posts\Domain\Entity\PostMedia;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Shared\Infrastructure\Cycle\AbstractRepository;
use Cycle\Database\Injection\Parameter;

/**
 * Доступ к вложениям записей. Медиа и его конверсии грузятся eager (media.*Conversions), как
 * предписывает docs/arch.md для ленты Posts. Сейчас лента строит только оригинал
 * (PostViewAssembler -> MediaUrlService::getOriginalUrl) и конверсии не читает, но eager-load держится
 * осознанно под планируемый показ превью (постер видео, миниатюры) в ленте — без него доступ к
 * конверсиям обернулся бы ленивой подгрузкой N+1. Убирать его можно только синхронно с правкой
 * arch.md, отдельной задачей, а не как обычную оптимизацию.
 *
 * @extends AbstractRepository<PostMedia>
 */
final class PostMediaRepository extends AbstractRepository
{
    public function findByPostId(PostId $postId): PostMediaCollection
    {
        return new PostMediaCollection(
            $this->select()
                ->where('post_id', $postId->value())
                ->load('media.imageConversions')
                ->load('media.videoConversions')
                ->load('media.audioConversions')
                ->orderBy(expression: 'position', direction: 'ASC')
                ->fetchAll(),
        );
    }

    /**
     * Медиа набора записей — для сборки листинга ленты без N+1. Сортировка по post_id и позиции,
     * чтобы вызывающий мог сгруппировать вложения по записи.
     */
    public function findByPostIds(PostId ...$postIds): PostMediaCollection
    {
        if ($postIds === []) {
            return new PostMediaCollection();
        }

        return new PostMediaCollection(
            $this->select()
                ->where('post_id', 'in', new Parameter(\array_map(
                    static fn(PostId $postId): string => $postId->value(),
                    $postIds,
                )))
                ->load('media.imageConversions')
                ->load('media.videoConversions')
                ->load('media.audioConversions')
                ->orderBy(expression: 'post_id', direction: 'ASC')
                ->orderBy(expression: 'position', direction: 'ASC')
                ->fetchAll(),
        );
    }
}

exec
/bin/zsh -lc "sed -n '1,320p' app/src/Modules/Media/Repository/MediaRepository.php && sed -n '1,220p' app/src/Modules/Media/Repository/MediaImageConversionRepository.php && sed -n '1,220p' app/src/Modules/Media/Repository/MediaVideoConversionRepository.php && sed -n '1,220p' app/src/Modules/Media/Repository/MediaAudioConversionRepository.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Repository;

use App\Modules\Media\Domain\Collection\MediaCollection;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Shared\Infrastructure\Cycle\AbstractRepository;

/**
 * @extends AbstractRepository<Media>
 */
final class MediaRepository extends AbstractRepository
{
    public function findById(MediaId $mediaId): Media|null
    {
        return $this->findByPK($mediaId->value());
    }

    /**
     * Медиа вместе со всеми конверсиями (image/video/audio), загруженными одним набором запросов,
     * чтобы построение URL не дёргало репозитории конверсий по одному. Используется там, где нужен
     * полный набор ссылок (MediaUrlService::getUrls).
     */
    public function findByIdWithConversions(MediaId $mediaId): Media|null
    {
        return $this->select()
            ->wherePK($mediaId->value())
            ->load('imageConversions')
            ->load('videoConversions')
            ->load('audioConversions')
            ->fetchOne();
    }

    public function findByStorageKey(MediaStorageKey $storageKey): Media|null
    {
        return $this->findOne(['storage_key' => $storageKey->value()]);
    }

    public function findExpired(\DateTimeImmutable $now): MediaCollection
    {
        return new MediaCollection(
            $this->select()
                ->where('expires_at', '<=', $now)
                ->fetchAll(),
        );
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Repository;

use App\Modules\Media\Domain\Collection\MediaImageConversionCollection;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Shared\Infrastructure\Cycle\AbstractRepository;

/**
 * @extends AbstractRepository<MediaImageConversion>
 */
final class MediaImageConversionRepository extends AbstractRepository
{
    public function findByMediaId(MediaId $mediaId): MediaImageConversionCollection
    {
        return new MediaImageConversionCollection(
            $this->select()
                ->where('media_id', $mediaId->value())
                ->orderBy(expression: 'id', direction: 'ASC')
                ->fetchAll(),
        );
    }

    /**
     * Есть ли у медиа хоть одна image-конверсия. Считает строки без гидрации сущностей — для
     * булевой проверки не нужно поднимать конверсии целиком.
     */
    public function existsForMediaId(MediaId $mediaId): bool
    {
        return $this->select()
            ->where('media_id', $mediaId->value())
            ->count() > 0;
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Repository;

use App\Modules\Media\Domain\Collection\MediaVideoConversionCollection;
use App\Modules\Media\Domain\Entity\MediaVideoConversion;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Shared\Infrastructure\Cycle\AbstractRepository;

/**
 * @extends AbstractRepository<MediaVideoConversion>
 */
final class MediaVideoConversionRepository extends AbstractRepository
{
    public function findByMediaId(MediaId $mediaId): MediaVideoConversionCollection
    {
        return new MediaVideoConversionCollection(
            $this->select()
                ->where('media_id', $mediaId->value())
                ->orderBy(expression: 'id', direction: 'ASC')
                ->fetchAll(),
        );
    }

    /**
     * Есть ли у медиа хоть одна video-конверсия. Считает строки без гидрации сущностей — для
     * булевой проверки не нужно поднимать конверсии целиком.
     */
    public function existsForMediaId(MediaId $mediaId): bool
    {
        return $this->select()
            ->where('media_id', $mediaId->value())
            ->count() > 0;
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Repository;

use App\Modules\Media\Domain\Collection\MediaAudioConversionCollection;
use App\Modules\Media\Domain\Entity\MediaAudioConversion;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Shared\Infrastructure\Cycle\AbstractRepository;

/**
 * @extends AbstractRepository<MediaAudioConversion>
 */
final class MediaAudioConversionRepository extends AbstractRepository
{
    public function findByMediaId(MediaId $mediaId): MediaAudioConversionCollection
    {
        return new MediaAudioConversionCollection(
            $this->select()
                ->where('media_id', $mediaId->value())
                ->orderBy(expression: 'id', direction: 'ASC')
                ->fetchAll(),
        );
    }

    /**
     * Есть ли у медиа хоть одна audio-конверсия. Считает строки без гидрации сущностей — для
     * булевой проверки не нужно поднимать конверсии целиком.
     */
    public function existsForMediaId(MediaId $mediaId): bool
    {
        return $this->select()
            ->where('media_id', $mediaId->value())
            ->count() > 0;
    }
}

exec
/bin/zsh -lc "sed -n '220,520p' app/src/Modules/Posts/Application/View/PostViewAssembler.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
            $byPost[$postMedia->postId->value()] ??= new PostMediaCollection();
            $byPost[$postMedia->postId->value()]->push($postMedia);
        }

        return $byPost;
    }

    /**
     * Теги набора записей одним запросом, сгруппированные по записи (без N+1 в листинге).
     *
     * @param list<PostId> $postIds
     *
     * @return array<string, PostTagCollection>
     */
    private function tagCollectionsByPost(array $postIds): array
    {
        $byPost = [];

        foreach ($this->postTagRepository->findByPostIds(...$postIds) as $postTag) {
            $byPost[$postTag->postId->value()] ??= new PostTagCollection();
            $byPost[$postTag->postId->value()]->push($postTag);
        }

        return $byPost;
    }

    /**
     * @return list<PostMediaItemView>
     */
    private function mediaItems(PostMediaCollection $media): array
    {
        $items = [];

        foreach ($media as $postMedia) {
            $item = $this->mediaItem($postMedia);

            if ($item === null) {
                continue;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Разрешает ссылку на медиа записи из УЖЕ загруженной сущности Media (relation post_media.media
     * грузится eager в PostMediaRepository вместе с конверсиями — без N+1 на вложение). Построение URL
     * делегируется MediaUrlService без обращения в БД. Если медиа не готово или его оригинал удалён,
     * недоступное вложение исключается из ответа, а не роняет чтение ленты в 500. Берётся только
     * оригинал (getOriginalUrl): конверсии (постер видео, превью) сейчас в ленте не используются, а
     * подписывать presigned-ссылки на каждую из них впустую для private-медиа не нужно — это отдельная
     * задача.
     */
    private function mediaItem(PostMedia $postMedia): PostMediaItemView|null
    {
        $originalUrl = $this->mediaUrlService->getOriginalUrl(media: $postMedia->media);

        if ($originalUrl === null) {
            return null;
        }

        return new PostMediaItemView(
            mediaId: $postMedia->mediaId->value(),
            url: $originalUrl->url,
            position: $postMedia->position->value(),
        );
    }

    /**
     * Тексты тегов набора связей одним запросом к Tags. Несколько коллекций (по записи в листинге)
     * собираются в один батч, чтобы не ходить в Tags на каждую запись.
     */
    private function tagTexts(PostTagCollection ...$tagCollections): TagTextCollection
    {
        $tagIds = [];

        foreach ($tagCollections as $tags) {
            foreach ($tags as $postTag) {
                $tagIds[] = $postTag->tagId->value();
            }
        }

        if ($tagIds === []) {
            return new TagTextCollection();
        }

        return $this->queryBus->dispatch(
            query: new GetTagsQuery(\array_values(\array_unique($tagIds))),
            handler: $this->getTagsHandler->handle(...),
        );
    }

    /**
     * Строит представления тегов записи из общего батча текстов. В листинге батч содержит теги всех
     * записей страницы, поэтому теги, не относящиеся к этой записи, отсеиваются по принадлежности.
     * Принадлежность проверяется по заранее построенному множеству id связей записи за O(1) (вместо
     * линейного поиска по связям на каждый элемент батча) — это убирает квадратичность на странице с
     * тег-тяжёлыми записями. Порядок тегов в ответе определяется выборкой текстов из Tags и не
     * является частью контракта (в post_tags нет колонки позиции — порядок добавления не хранится).
     *
     * @return list<TagView>
     */
    private function tagViews(PostTagCollection $tags, TagTextCollection $texts): array
    {
        $postTagIds = [];

        foreach ($tags as $postTag) {
            $postTagIds[$postTag->tagId->value()] = true;
        }

        $views = [];

        foreach ($texts as $tagId => $text) {
            if (!isset($postTagIds[$tagId])) {
                continue;
            }

            $views[] = new TagView(id: $tagId, text: $text);
        }

        return $views;
    }

    /**
     * @return list<PostId>
     */
    private function postIds(PostCollection $posts): array
    {
        return $posts->mapToList(static fn(Post $post): PostId => $post->id);
    }

    private function originalView(Post $post, UserId $viewer): PostView|null
    {
        $originalId = $post->original->value();

        if ($originalId === null) {
            return null;
        }

        $original = $this->postRepository->findById(PostId::fromString($originalId));

        if ($original === null || !PostVisibilityPolicy::isVisibleTo(post: $original, viewer: $viewer)) {
            return null;
        }

        $tags = $this->postTagRepository->findByPostId($original->id);

        return $this->build(
            post: $original,
            author: $this->authorView($original->userId),
            likedByMe: $this->postLikeRepository->existsByPostAndUser(postId: $original->id, userId: $viewer),
            media: $this->mediaItems($this->postMediaRepository->findByPostId($original->id)),
            tags: $this->tagViews(tags: $tags, texts: $this->tagTexts($tags)),
            original: null,
        );
    }

    /**
     * Обогащает оригиналы репостов страницы одним пакетом (как и сами записи): один запрос за
     * оригиналами, их авторами, медиа, тегами и флагами likedByMe. Без этого каждый репост дочитывал
     * бы свой оригинал по отдельности (N+1 на уровень глубже). Невидимый зрителю оригинал в карту не
     * попадает — у репоста original будет null.
     *
     * @return array<string, PostView>
     */
    private function originalViewsByPost(PostCollection $posts, UserId $viewer): array
    {
        $originalIds = [];

        foreach ($posts as $post) {
            $originalId = $post->original->value();

            if ($originalId !== null) {
                $originalIds[$originalId] = PostId::fromString($originalId);
            }
        }

        if ($originalIds === []) {
            return [];
        }

        $originals = $this->postRepository->findByIds(...\array_values($originalIds))->filter(
            static fn(Post $original): bool => PostVisibilityPolicy::isVisibleTo(post: $original, viewer: $viewer),
        )->values();

        if ($originals->isEmpty()) {
            return [];
        }

        $originalPostIds = $this->postIds($originals);
        $authors = $this->authorViews($originals);
        $likedPostIds = $this->likedPostIds(posts: $originals, viewer: $viewer);
        $mediaByPost = $this->mediaCollectionsByPost($originalPostIds);
        $tagsByPost = $this->tagCollectionsByPost($originalPostIds);
        $tagTexts = $this->tagTexts(...\array_values($tagsByPost));

        return $originals->toBase()
            ->map(function (Post $original) use ($authors, $likedPostIds, $mediaByPost, $tagsByPost, $tagTexts): PostView {
                $originalId = $original->id->value();

                return $this->build(
                    post: $original,
                    author: $this->requireAuthor(authors: $authors, userId: $original->userId->value()),
                    likedByMe: isset($likedPostIds[$originalId]),
                    media: $this->mediaItems($mediaByPost[$originalId] ?? new PostMediaCollection()),
                    tags: $this->tagViews(tags: $tagsByPost[$originalId] ?? new PostTagCollection(), texts: $tagTexts),
                    original: null,
                );
            })
            ->keyBy(static fn(PostView $view): string => $view->id)
            ->all();
    }
}

exec
/bin/zsh -lc 'rg -n "class MediaUrlResult|MediaUrlResult|presignGet|publicUrl|deleteObject|function deleteObject|function presignGet|function publicUrl" app/src/Modules/Media -S' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "rg -n \"Application/Service|Application\\\\\\\\Service|Application/Contract|Application    -|Media.*foundational|MediaUrlService|FindMediaOriginalUrl|FindMediaUrl\" docs/arch.md app/src/Modules/Media/README.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:300:    public function deleteObject(MediaStorage $storage, MediaPath $path): void
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:303:            $this->client($storage)->deleteObject([
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:317:    public function presignGet(MediaStorage $storage, MediaPath $path, \DateTimeImmutable $expiresAt): string
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:329:    public function publicUrl(MediaStorage $storage, MediaPath $path): string
app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/FindMediaOriginalUrlHandler.php:8:use App\Modules\Media\Application\Dto\MediaUrlResult;
app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/FindMediaOriginalUrlHandler.php:31:    public function handle(FindMediaOriginalUrlQuery $query): MediaUrlResult|null
app/src/Modules/Media/Infrastructure/FileService/MediaUrlResolver.php:7:use App\Modules\Media\Application\Dto\MediaUrlResult;
app/src/Modules/Media/Infrastructure/FileService/MediaUrlResolver.php:19:    public function resolve(MediaStorage $storage, MediaPath $path): MediaUrlResult;
app/src/Modules/Media/Application/Contract/MediaUrlServiceContract.php:7:use App\Modules\Media\Application\Dto\MediaUrlResult;
app/src/Modules/Media/Application/Contract/MediaUrlServiceContract.php:28:    public function getOriginalUrl(Media $media, int|null $presignedTtlSeconds = null): MediaUrlResult|null;
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:130:    public function deleteObject(MediaStorage $storage, MediaPath $path): void;
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:135:    public function presignGet(MediaStorage $storage, MediaPath $path, \DateTimeImmutable $expiresAt): string;
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:140:    public function publicUrl(MediaStorage $storage, MediaPath $path): string;
app/src/Modules/Media/Infrastructure/FileService/PresignedMediaUrlResolver.php:8:use App\Modules\Media\Application\Dto\MediaUrlResult;
app/src/Modules/Media/Infrastructure/FileService/PresignedMediaUrlResolver.php:22:    public function resolve(MediaStorage $storage, MediaPath $path): MediaUrlResult
app/src/Modules/Media/Infrastructure/FileService/PresignedMediaUrlResolver.php:24:        return new MediaUrlResult(
app/src/Modules/Media/Infrastructure/FileService/PresignedMediaUrlResolver.php:25:            url: $this->mediaFileService->presignGet(storage: $storage, path: $path, expiresAt: $this->expiresAt),
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:11:use App\Modules\Media\Application\Dto\MediaUrlResult;
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:76:    public function getOriginalUrl(Media $media, int|null $presignedTtlSeconds = null): MediaUrlResult|null
app/src/Modules/Media/Infrastructure/FileService/PublicMediaUrlResolver.php:8:use App\Modules\Media\Application\Dto\MediaUrlResult;
app/src/Modules/Media/Infrastructure/FileService/PublicMediaUrlResolver.php:19:    public function resolve(MediaStorage $storage, MediaPath $path): MediaUrlResult
app/src/Modules/Media/Infrastructure/FileService/PublicMediaUrlResolver.php:21:        return new MediaUrlResult(
app/src/Modules/Media/Infrastructure/FileService/PublicMediaUrlResolver.php:22:            url: $this->mediaFileService->publicUrl(storage: $storage, path: $path),
app/src/Modules/Media/Application/Dto/MediaUrlResult.php:11:final readonly class MediaUrlResult
app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php:22: * сначала идемпотентный deleteObject оригинала (404 → no-op), затем один атомарный persist+run() с
app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php:24: * к S3. На сбое после deleteObject до flush статус остаётся ready, повтор команды довыполнит переход.
app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php:31: * пересмотреть (вариант: вынести deleteObject в outbox-шаг после commit-а перехода).
app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php:71:        $this->mediaFileService->deleteObject(storage: $media->storage, path: $media->path);
app/src/Modules/Media/Application/Dto/MediaUrlsResult.php:15:        public MediaUrlResult|null $original,
app/src/Modules/Media/README.md:27:| `FindMediaOriginalUrl(mediaId, presignedTtlSeconds?)` | Query | `MediaUrlResult` или `null` (только оригинал, без конверсий: грузит медиа без `->load(...)` конверсий и не обращается к связям-конверсиям — для потребителей, которым нужна одна ссылка (аватар профиля). `null`, если медиа нет, не финализировано или оригинал удалён. Семантика срока — как у `FindMediaUrl`) |
app/src/Modules/Media/README.md:55:- `MediaResult{ mediaId, status, visibility }`, `MediaUrlResult{ url, expiresAt? }` (одна ссылка),
app/src/Modules/Media/README.md:58:  `kind = image`), `MediaUrlsResult{ original: MediaUrlResult?, conversions: MediaConversionUrlCollection }`
app/src/Modules/Media/README.md:76:   (media-public, anonymous read); private -> presignGet (TTL по умолчанию из конфига,
app/src/Modules/Media/README.md:157:  изменений: `storage`/`path` указывают на уже удалённый оригинал, повторный `deleteObject` отдаёт
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:55:        $this->mediaFileService->deleteObject(storage: $media->storage, path: $media->path);
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:69:            $this->mediaFileService->deleteObject(storage: $imageConversion->storage, path: $imageConversion->path);
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:73:            $this->mediaFileService->deleteObject(storage: $videoConversion->storage, path: $videoConversion->path);
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:79:            $this->mediaFileService->deleteObject(storage: $audioConversion->storage, path: $audioConversion->path);

 succeeded in 0ms:
app/src/Modules/Media/README.md:26:| `FindMediaUrl(mediaId, presignedTtlSeconds?)` | Query | `MediaUrlsResult` или `null` (полный набор: `original` — оригинал, `null` если он удалён в `readyOriginalRemoved`; `conversions` — все конверсии, каждая со своим типом; вызывающий выбирает нужное по типу, не зная заранее, какие конверсии есть. Не бросает: медиа нет или не финализировано → `null` для best-effort показа. Для public — прямые URL без срока (`presignedTtlSeconds` игнорируется и не валидируется), для private — presigned со сроком: по умолчанию из конфига, вызывающий может переопределить `presignedTtlSeconds` (явный `0`/вне диапазона 1..604800 → ошибка)) |
app/src/Modules/Media/README.md:27:| `FindMediaOriginalUrl(mediaId, presignedTtlSeconds?)` | Query | `MediaUrlResult` или `null` (только оригинал, без конверсий: грузит медиа без `->load(...)` конверсий и не обращается к связям-конверсиям — для потребителей, которым нужна одна ссылка (аватар профиля). `null`, если медиа нет, не финализировано или оригинал удалён. Семантика срока — как у `FindMediaUrl`) |
app/src/Modules/Media/README.md:59:  (полный набор ссылок медиа) — наружу не отдаётся доменная Entity. URL строит `MediaUrlService`
app/src/Modules/Media/README.md:60:  за контрактом `MediaUrlServiceContract` (реализация в `Infrastructure/FileService` читает
app/src/Modules/Media/README.md:75:6. FindMediaUrl: отдаёт полный набор ссылок (оригинал + все конверсии). public -> прямые URL
app/src/Modules/Media/README.md:95:после удаления оригинала: `FindMediaUrl` обслуживает `readyOriginalRemoved` — возвращает
app/src/Modules/Media/README.md:143:  чтобы `FindMediaUrl` резолвил их единообразно и не было утечки private-медиа.
app/src/Modules/Media/README.md:181:  (`FindMediaUrlQuery.presignedTtlSeconds`). Ключи `MEDIA_*` — в `.env.sample` и `phpunit.xml`.
app/src/Modules/Media/README.md:185:  `MediaConfig` через конструктор и биндится `const BINDINGS` — как `MediaUrlService`. Срок
app/src/Modules/Media/README.md:186:  presigned-ссылки скачивания по умолчанию читает сама реализация `MediaUrlService` из
app/src/Modules/Media/README.md:188:  `MediaUrlServiceContract` — это технический сервис с поведением, поэтому он живёт в `Infrastructure`
docs/arch.md:44:Application    - сценарии модуля и контракты для технических зависимостей.
docs/arch.md:76:Modules/{Module}/Application/Contract
docs/arch.md:158:### Исключение: `Media` — foundational-модуль
docs/arch.md:160:`Media` — универсальный (foundational) модуль: хранение и раздача файлов нужны почти любому
docs/arch.md:169:обращений в БД: лента берёт только оригинал через `MediaUrlService::getOriginalUrl(Media $media)`
docs/arch.md:171:конверсии» отдаёт `MediaUrlService::getUrls(Media $media)` (путь `FindMediaUrl`).
docs/arch.md:186:FindMediaUrl
docs/arch.md:187:FindMediaOriginalUrl
docs/arch.md:282:Application    -> Domain, Repository своего модуля, Contract своего модуля
docs/arch.md:305:`Application/Contract`: реализация читает `*Config` сама через конструктор и отдаёт Application готовые
docs/arch.md:306:решения. Образцы — `MediaUrlServiceContract`/`MediaUrlService` (срок presigned-ссылки скачивания по
docs/arch.md:310:`MediaUploadPlanner` в `Infrastructure/FileService` рядом с `MediaUrlService` — частная деталь модуля
docs/arch.md:318:по-прежнему идёт через `Application/Contract` + реализацию в `Infrastructure`, без исключений.
docs/arch.md:585:    -> Modules/Outbox/Application/Contract/OutboxEventStoreContract::add(IntegrationEvent DTO)

exec
/bin/zsh -lc "rg -n \"GetMediaUrl|FindMediaUrl|FindMediaOriginalUrl|MediaUrlService|getOriginalUrl|getUrls|readyOriginalRemoved|ReadyOriginalRemoved|isFinalized|isOriginalRemoved|MediaConversionKind|MediaConversionsChecker|partsCount\\(\" app/src tests -S" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
tests/Kernel/Modules/Media/MediaBootloaderTest.php:8:use App\Modules\Media\Application\Contract\MediaUrlServiceContract;
tests/Kernel/Modules/Media/MediaBootloaderTest.php:10:use App\Modules\Media\Infrastructure\FileService\MediaUrlService;
tests/Kernel/Modules/Media/MediaBootloaderTest.php:18:        // читает MediaConfig через конструктор (как MediaUrlService), без фабрики и settings-объекта.
tests/Kernel/Modules/Media/MediaBootloaderTest.php:24:    public function testMediaUrlServiceContractResolvesToInfrastructureImplementation(): void
tests/Kernel/Modules/Media/MediaBootloaderTest.php:28:        $mediaUrlService = $this->getContainer()->get(MediaUrlServiceContract::class);
tests/Kernel/Modules/Media/MediaBootloaderTest.php:30:        self::assertInstanceOf(MediaUrlService::class, $mediaUrlService);
app/src/Modules/Media/Domain/Enum/MediaConversionKind.php:12:enum MediaConversionKind: string
app/src/Modules/Media/Domain/Enum/MediaStatus.php:17:    case ReadyOriginalRemoved = 'readyOriginalRemoved';
tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php:51:        // Смысловой центр changeset на границе аватара: оригинал удалён -> getOriginalUrl = null ->
tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php:53:        // отдавать (устаревшую) ссылку для readyOriginalRemoved.
tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php:54:        $media = $this->persistReadyOriginalRemovedMedia();
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:8:use App\Modules\Media\Application\Query\FindMediaOriginalUrl\FindMediaOriginalUrlHandler;
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:18:use App\Modules\Media\Infrastructure\FileService\MediaUrlService;
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:86:    protected function persistReadyOriginalRemovedMedia(): Media
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:94:        $media->markReadyOriginalRemoved();
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:107:            findMediaOriginalUrlHandler: new FindMediaOriginalUrlHandler(
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:109:                mediaUrlService: new MediaUrlService(
app/src/Modules/Media/Domain/Entity/Media.php:177:     * уже готовом медиа (ready или readyOriginalRemoved) — no-op (симметрично guard'у в
app/src/Modules/Media/Domain/Entity/Media.php:179:     * если фиксацию сбоя запустят в обход isFinalized-guard'а в ProcessMediaHandler (другой relay,
app/src/Modules/Media/Domain/Entity/Media.php:189:     * финализированном медиа (ready или readyOriginalRemoved) фиксация постоянной ошибки также no-op.
app/src/Modules/Media/Domain/Entity/Media.php:199:     * guard'ом isFinalized. Остаётся приватным, а recordTemporary/PermanentProcessingError —
app/src/Modules/Media/Domain/Entity/Media.php:204:        if ($this->isFinalized()) {
app/src/Modules/Media/Domain/Entity/Media.php:255:     * Оригинал удалён, но конверсии обслуживаются (readyOriginalRemoved). Прячет сравнение с конкретным
app/src/Modules/Media/Domain/Entity/Media.php:256:     * статусом от вызывающих (как isReady()/isFinalized()), чтобы Application не знал конкретный вариант
app/src/Modules/Media/Domain/Entity/Media.php:259:    public function isOriginalRemoved(): bool
app/src/Modules/Media/Domain/Entity/Media.php:261:        return $this->status === MediaStatus::ReadyOriginalRemoved;
app/src/Modules/Media/Domain/Entity/Media.php:266:     * так и после удаления оригинала (readyOriginalRemoved). Используется там, где важна готовность
app/src/Modules/Media/Domain/Entity/Media.php:269:    public function isFinalized(): bool
app/src/Modules/Media/Domain/Entity/Media.php:271:        return $this->status === MediaStatus::Ready || $this->status === MediaStatus::ReadyOriginalRemoved;
app/src/Modules/Media/Domain/Entity/Media.php:275:     * Перевод в readyOriginalRemoved после физического удаления оригинала из целевого бакета.
app/src/Modules/Media/Domain/Entity/Media.php:277:     * и guard'у isFinalized в recordProcessingError): допустим только из ready. Идемпотентен: повторный
app/src/Modules/Media/Domain/Entity/Media.php:278:     * вызов на уже readyOriginalRemoved — no-op. Из любого другого статуса — InvalidDomainValueException,
app/src/Modules/Media/Domain/Entity/Media.php:281:    public function markReadyOriginalRemoved(): void
app/src/Modules/Media/Domain/Entity/Media.php:283:        if ($this->status === MediaStatus::ReadyOriginalRemoved) {
app/src/Modules/Media/Domain/Entity/Media.php:293:        $this->status = MediaStatus::ReadyOriginalRemoved;
tests/Unit/Modules/Media/Domain/Enum/MediaEnumTest.php:36:                'readyOriginalRemoved',
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:79:        $media->markReadyOriginalRemoved();
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:80:        self::assertSame(MediaStatus::ReadyOriginalRemoved, $media->status);
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:104:        self::assertTrue($this->readyMedia()->isFinalized());
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:105:        self::assertTrue($this->readyOriginalRemovedMedia()->isFinalized());
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:146:            self::assertFalse($media->isFinalized(), $statusValue);
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:152:        self::assertFalse($this->createMedia()->isOriginalRemoved());
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:153:        self::assertFalse($this->readyMedia()->isOriginalRemoved());
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:154:        self::assertTrue($this->readyOriginalRemovedMedia()->isOriginalRemoved());
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:157:    public function testRecordProcessingErrorIsNoOpOnReadyOriginalRemovedMedia(): void
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:161:        $media = $this->readyOriginalRemovedMedia();
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:164:        self::assertSame(MediaStatus::ReadyOriginalRemoved, $media->status);
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:169:        self::assertSame(MediaStatus::ReadyOriginalRemoved, $media->status);
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:238:    public function testMarkReadyOriginalRemovedRejectsTransitionFromNonReady(): void
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:245:        $media->markReadyOriginalRemoved();
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:248:    public function testMarkReadyOriginalRemovedIsIdempotent(): void
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:250:        $media = $this->readyOriginalRemovedMedia();
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:253:        $media->markReadyOriginalRemoved();
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:255:        self::assertSame(MediaStatus::ReadyOriginalRemoved, $media->status);
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:298:    private function readyOriginalRemovedMedia(): Media
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:301:        $media->markReadyOriginalRemoved();
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php:38:    public function testReturnsWaveformForReadyOriginalRemovedAudioMedia(): void
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php:42:        $media->markReadyOriginalRemoved();
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:10:use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlHandler;
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:11:use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlQuery;
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:16:use App\Modules\Media\Domain\Enum\MediaConversionKind;
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:30:use App\Modules\Media\Infrastructure\FileService\MediaUrlService;
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:35:final class FindMediaUrlHandlerTest extends MediaApplicationTestCase
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:40:            new FindMediaUrlQuery(mediaId: UserId::generate()->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:48:        // Ещё не ready и не readyOriginalRemoved -> best-effort null, чтобы вызывающий подставил дефолт.
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:54:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:76:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:90:        self::assertSame(MediaConversionKind::Image, $thumbnailUrl->kind);
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:95:        self::assertSame(MediaConversionKind::Video, $videoUrl->kind);
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:99:        self::assertSame(MediaConversionKind::Audio, $audioUrl->kind);
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:118:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:140:    public function testOmitsOriginalButKeepsConversionsForReadyOriginalRemovedMedia(): void
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:146:        $media->markReadyOriginalRemoved();
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:156:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:178:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:201:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 0),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:225:            new FindMediaUrlQuery(mediaId: $media->id->value()),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:249:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 0),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:253:    private function handler(MediaFileServiceContract $fileService): FindMediaUrlHandler
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:255:        return new FindMediaUrlHandler(
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:257:            mediaUrlService: new MediaUrlService(
app/src/Modules/User/Infrastructure/Bootloader/UserBootloader.php:7:use App\Modules\Media\Application\Query\FindMediaOriginalUrl\FindMediaOriginalUrlHandler;
app/src/Modules/User/Infrastructure/Bootloader/UserBootloader.php:30:        FindMediaOriginalUrlHandler $findMediaOriginalUrlHandler,
tests/Unit/Modules/Media/Infrastructure/MediaUploadPlannerTest.php:69:        self::assertSame(2, $planner->partsCount(size: MediaFileSize::fromInt(5_242_881), partSize: $partSize)->value());
tests/Unit/Modules/Media/Infrastructure/MediaUploadPlannerTest.php:70:        self::assertSame(1, $planner->partsCount(size: MediaFileSize::fromInt(5_242_880), partSize: $partSize)->value());
app/src/Modules/Media/Infrastructure/FileService/MediaUploadPlanner.php:17: * MediaUrlService), биндится const BINDINGS в MediaBootloader. Срок staging считается на момент вызова
app/src/Modules/Media/Infrastructure/FileService/MediaUploadPlanner.php:20: * partsCount() делит размер на переданный partSize->value(): вызывающий получает partSize() и отдаёт
app/src/Modules/Media/Infrastructure/FileService/MediaUploadPlanner.php:21: * его же в partsCount(), поэтому деление всегда идёт на тот размер части, который записывается в
app/src/Modules/Media/Infrastructure/FileService/MediaUploadPlanner.php:50:    public function partsCount(MediaFileSize $size, MediaMultipartPartSize $partSize): MediaMultipartPartsCount
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:8:use App\Modules\Media\Application\Contract\MediaUrlServiceContract;
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:18:use App\Modules\Media\Domain\Enum\MediaConversionKind;
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:29: * Строит URL из УЖЕ загруженного медиа. getUrls — полный набор: оригинал (если не удалён) и все
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:34: * передавать медиа с eager-загруженными конверсиями. getOriginalUrl — только оригинал, к связям
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:42: * Не бросает: не финализированное медиа (ещё не ready и не readyOriginalRemoved) -> null. После
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:45:final readonly class MediaUrlService implements MediaUrlServiceContract
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:52:    public function getUrls(Media $media, int|null $presignedTtlSeconds = null): MediaUrlsResult|null
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:54:        if (!$media->isFinalized()) {
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:71:     * нет (медиа не финализировано или оригинал удалён в readyOriginalRemoved) — вызывающий подставит
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:76:    public function getOriginalUrl(Media $media, int|null $presignedTtlSeconds = null): MediaUrlResult|null
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:113:            kind: MediaConversionKind::Image,
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:118:            kind: MediaConversionKind::Video,
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:123:            kind: MediaConversionKind::Audio,
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:139:        MediaConversionKind $kind,
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:155:        MediaConversionKind $kind,
app/src/Modules/Media/Infrastructure/FileService/MediaUrlResolver.php:14: * в MediaUrlService::resolverFor(), поэтому вызывающему остаётся передать только пару (storage, path) —
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:8:use App\Modules\Media\Application\Query\FindMediaOriginalUrl\FindMediaOriginalUrlHandler;
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:9:use App\Modules\Media\Application\Query\FindMediaOriginalUrl\FindMediaOriginalUrlQuery;
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:13:use App\Modules\Media\Infrastructure\FileService\MediaUrlService;
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:17:final class FindMediaOriginalUrlHandlerTest extends MediaApplicationTestCase
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:22:            new FindMediaOriginalUrlQuery(mediaId: UserId::generate()->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:35:            new FindMediaOriginalUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:43:        // readyOriginalRemoved -> оригинала нет -> null. Конверсия в БД есть, но её не резолвят:
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:47:        $media->markReadyOriginalRemoved();
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:56:            new FindMediaOriginalUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:78:            new FindMediaOriginalUrlQuery(mediaId: $media->id->value()),
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:103:            new FindMediaOriginalUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:129:            new FindMediaOriginalUrlQuery(mediaId: $media->id->value()),
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:141:    private function handler(MediaFileServiceContract $fileService): FindMediaOriginalUrlHandler
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:143:        return new FindMediaOriginalUrlHandler(
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:145:            mediaUrlService: new MediaUrlService(
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:254:    public function testIsNoOpWhenMediaAlreadyOriginalRemoved(): void
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:263:        $media->markReadyOriginalRemoved();
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:275:        // Ради этого расширен guard до isFinalized: поздняя запись ошибки на readyOriginalRemoved
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:277:        self::assertSame(MediaStatus::ReadyOriginalRemoved, $media->status);
app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php:12:use App\Modules\Media\Application\Contract\MediaUrlServiceContract;
app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php:19:use App\Modules\Media\Infrastructure\FileService\MediaUrlService;
app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php:30:        MediaUrlServiceContract::class => MediaUrlService::class,
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:10:use App\Modules\Media\Application\Service\MediaConversionsChecker;
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:62:        self::assertSame(MediaStatus::ReadyOriginalRemoved, $media->status);
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:63:        self::assertSame(MediaStatus::ReadyOriginalRemoved, $result->status);
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:90:        self::assertSame(MediaStatus::ReadyOriginalRemoved, $media->status);
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:118:        self::assertSame(MediaStatus::ReadyOriginalRemoved, $media->status);
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:204:        $media->markReadyOriginalRemoved();
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:217:        self::assertSame(MediaStatus::ReadyOriginalRemoved, $result->status);
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:258:            mediaConversionsChecker: new MediaConversionsChecker(
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:154:    public function testDeletesReadyOriginalRemovedMediaConversionsAndIdempotentOriginal(): void
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:160:        $media->markReadyOriginalRemoved();
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:72:    public function testRejectsReadyOriginalRemovedMedia(): void
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:78:        $media->markReadyOriginalRemoved();
app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php:7:use App\Modules\Media\Application\Query\FindMediaOriginalUrl\FindMediaOriginalUrlHandler;
app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php:8:use App\Modules\Media\Application\Query\FindMediaOriginalUrl\FindMediaOriginalUrlQuery;
app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php:19: * Используется FindMediaOriginalUrl (только оригинал, без подгрузки и подписания конверсий): для
app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php:31:        private FindMediaOriginalUrlHandler $findMediaOriginalUrlHandler,
app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php:54:            query: new FindMediaOriginalUrlQuery(mediaId: $mediaId),
app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php:58:        // Если медиа недоступно или оригинал удалён (readyOriginalRemoved) — null, подставляем
app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/FindMediaOriginalUrlHandler.php:5:namespace App\Modules\Media\Application\Query\FindMediaOriginalUrl;
app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/FindMediaOriginalUrlHandler.php:7:use App\Modules\Media\Application\Contract\MediaUrlServiceContract;
app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/FindMediaOriginalUrlHandler.php:15: * конверсии не нужны (аватар профиля). В отличие от FindMediaUrl, грузит медиа без конверсий
app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/FindMediaOriginalUrlHandler.php:20: * Возвращает null, если медиа нет, оно не финализировано или оригинал удалён (readyOriginalRemoved),
app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/FindMediaOriginalUrlHandler.php:23:final readonly class FindMediaOriginalUrlHandler
app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/FindMediaOriginalUrlHandler.php:27:        private MediaUrlServiceContract $mediaUrlService,
app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/FindMediaOriginalUrlHandler.php:31:    public function handle(FindMediaOriginalUrlQuery $query): MediaUrlResult|null
app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/FindMediaOriginalUrlHandler.php:39:        return $this->mediaUrlService->getOriginalUrl(
app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/FindMediaOriginalUrlQuery.php:5:namespace App\Modules\Media\Application\Query\FindMediaOriginalUrl;
app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/FindMediaOriginalUrlQuery.php:7:final readonly class FindMediaOriginalUrlQuery
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:90:        $partsCount = $this->uploadPlanner->partsCount(size: $media->size, partSize: $partSize);
app/src/Modules/Media/Application/Query/GetAudioWaveform/GetAudioWaveformHandler.php:17: * readyOriginalRemoved) / не аудио / без конверсии — 404.
app/src/Modules/Media/Application/Query/GetAudioWaveform/GetAudioWaveformHandler.php:31:        // Волна — это конверсия, поэтому переживает удаление оригинала (ready и readyOriginalRemoved).
app/src/Modules/Media/Application/Query/GetAudioWaveform/GetAudioWaveformHandler.php:32:        if (!$media->isFinalized()) {
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:69:        $media->markReadyOriginalRemoved();
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:56:        if ($media->isFinalized()) {
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:5:namespace App\Modules\Media\Application\Query\FindMediaUrl;
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:7:use App\Modules\Media\Application\Contract\MediaUrlServiceContract;
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:16: * MediaUrlService. Возвращает null, если медиа нет или оно не финализировано, чтобы вызывающий
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:20: * есть прямой путь MediaUrlService::getUrls(Media) — без повторной загрузки.
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:22:final readonly class FindMediaUrlHandler
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:26:        private MediaUrlServiceContract $mediaUrlService,
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:30:    public function handle(FindMediaUrlQuery $query): MediaUrlsResult|null
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:38:        return $this->mediaUrlService->getUrls(media: $media, presignedTtlSeconds: $query->presignedTtlSeconds);
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlQuery.php:5:namespace App\Modules\Media\Application\Query\FindMediaUrl;
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlQuery.php:7:final readonly class FindMediaUrlQuery
app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php:9:use App\Modules\Media\Application\Service\MediaConversionsChecker;
app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php:23: * переходом в readyOriginalRemoved. Идемпотентен: на уже removed-original — ранний no-op без обращения
app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php:27: * статус остаётся ready, и любой запрос ссылки на оригинал (FindMediaUrl/FindMediaOriginalUrl/лента/
app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php:37:        private MediaConversionsChecker $mediaConversionsChecker,
app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php:53:        if ($media->isOriginalRemoved()) {
app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php:73:        $media->markReadyOriginalRemoved();
app/src/Modules/Media/Application/Service/MediaConversionsChecker.php:21:final readonly class MediaConversionsChecker
app/src/Modules/Media/Application/Contract/MediaUrlServiceContract.php:18: * getUrls — полный набор (оригинал, если не удалён, и все конверсии). getOriginalUrl — только ссылка
app/src/Modules/Media/Application/Contract/MediaUrlServiceContract.php:22: * Реализация — App\Modules\Media\Infrastructure\FileService\MediaUrlService.
app/src/Modules/Media/Application/Contract/MediaUrlServiceContract.php:24:interface MediaUrlServiceContract
app/src/Modules/Media/Application/Contract/MediaUrlServiceContract.php:26:    public function getUrls(Media $media, int|null $presignedTtlSeconds = null): MediaUrlsResult|null;
app/src/Modules/Media/Application/Contract/MediaUrlServiceContract.php:28:    public function getOriginalUrl(Media $media, int|null $presignedTtlSeconds = null): MediaUrlResult|null;
app/src/Modules/Media/Application/Contract/MediaUploadPlannerContract.php:18: * напрямую через конструктор, как MediaUrlService).
app/src/Modules/Media/Application/Contract/MediaUploadPlannerContract.php:27:     * Размер одной части multipart-загрузки. Этот же объект вызывающий передаёт в partsCount(), поэтому
app/src/Modules/Media/Application/Contract/MediaUploadPlannerContract.php:39:    public function partsCount(MediaFileSize $size, MediaMultipartPartSize $partSize): MediaMultipartPartsCount;
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:22: * скачивания — MediaUrlService (значение по умолчанию реализация читает из MediaConfig, вызывающий
app/src/Modules/Media/Application/Dto/MediaUrlsResult.php:9: * (readyOriginalRemoved) — при этом конверсии продолжают резолвиться. Вызывающий получает всё
app/src/Modules/Media/Application/Dto/MediaConversionUrl.php:8:use App\Modules\Media\Domain\Enum\MediaConversionKind;
app/src/Modules/Media/Application/Dto/MediaConversionUrl.php:21:        public MediaConversionKind $kind,
app/src/Modules/Media/README.md:25:| `RemoveMediaOriginal(userId, mediaId)` | Command | `MediaResult` (удаляет оригинал из целевого бакета, статус → `readyOriginalRemoved`; конверсии сохраняются; требует ≥1 конверсии, иначе 422; идемпотентна на уже удалённом оригинале) |
app/src/Modules/Media/README.md:26:| `FindMediaUrl(mediaId, presignedTtlSeconds?)` | Query | `MediaUrlsResult` или `null` (полный набор: `original` — оригинал, `null` если он удалён в `readyOriginalRemoved`; `conversions` — все конверсии, каждая со своим типом; вызывающий выбирает нужное по типу, не зная заранее, какие конверсии есть. Не бросает: медиа нет или не финализировано → `null` для best-effort показа. Для public — прямые URL без срока (`presignedTtlSeconds` игнорируется и не валидируется), для private — presigned со сроком: по умолчанию из конфига, вызывающий может переопределить `presignedTtlSeconds` (явный `0`/вне диапазона 1..604800 → ошибка)) |
app/src/Modules/Media/README.md:27:| `FindMediaOriginalUrl(mediaId, presignedTtlSeconds?)` | Query | `MediaUrlResult` или `null` (только оригинал, без конверсий: грузит медиа без `->load(...)` конверсий и не обращается к связям-конверсиям — для потребителей, которым нужна одна ссылка (аватар профиля). `null`, если медиа нет, не финализировано или оригинал удалён. Семантика срока — как у `FindMediaUrl`) |
app/src/Modules/Media/README.md:28:| `GetAudioWaveform(mediaId)` | Query | `MediaWaveform` (числа амплитуд аудио-конверсии; не через URL; обслуживается при `ready` и `readyOriginalRemoved`; не финализировано/не аудио/нет конверсии → 404) |
app/src/Modules/Media/README.md:59:  (полный набор ссылок медиа) — наружу не отдаётся доменная Entity. URL строит `MediaUrlService`
app/src/Modules/Media/README.md:60:  за контрактом `MediaUrlServiceContract` (реализация в `Infrastructure/FileService` читает
app/src/Modules/Media/README.md:75:6. FindMediaUrl: отдаёт полный набор ссылок (оригинал + все конверсии). public -> прямые URL
app/src/Modules/Media/README.md:86:`MediaStatus`: `waitingUpload → uploaded → ready (→ readyOriginalRemoved опционально)`. Последний
app/src/Modules/Media/README.md:87:переход не обязателен: `readyOriginalRemoved` — опциональная терминальная ветка, в которую переводит
app/src/Modules/Media/README.md:90:`ready → readyOriginalRemoved` делает `RemoveMediaOriginal`: оригинальный объект физически удаляется
app/src/Modules/Media/README.md:95:после удаления оригинала: `FindMediaUrl` обслуживает `readyOriginalRemoved` — возвращает
app/src/Modules/Media/README.md:98:умолчанию. `GetAudioWaveform` тоже обслуживает `readyOriginalRemoved`; `CheckMediaAttachable`
app/src/Modules/Media/README.md:101:(`isFinalized()`), а поздняя запись ошибки обработки на `readyOriginalRemoved` — тоже no-op (медиа не
app/src/Modules/Media/README.md:143:  чтобы `FindMediaUrl` резолвил их единообразно и не было утечки private-медиа.
app/src/Modules/Media/README.md:156:  осиротевшими в постоянном бакете. На `readyOriginalRemoved`-медиа `DeleteMedia` работает без
app/src/Modules/Media/README.md:181:  (`FindMediaUrlQuery.presignedTtlSeconds`). Ключи `MEDIA_*` — в `.env.sample` и `phpunit.xml`.
app/src/Modules/Media/README.md:185:  `MediaConfig` через конструктор и биндится `const BINDINGS` — как `MediaUrlService`. Срок
app/src/Modules/Media/README.md:186:  presigned-ссылки скачивания по умолчанию читает сама реализация `MediaUrlService` из
app/src/Modules/Media/README.md:188:  `MediaUrlServiceContract` — это технический сервис с поведением, поэтому он живёт в `Infrastructure`
app/src/Modules/Media/Repository/MediaRepository.php:26:     * полный набор ссылок (MediaUrlService::getUrls).
app/src/Modules/Posts/Domain/Entity/PostMedia.php:50:     * сборке URL: лента строит оригинал через MediaUrlService::getOriginalUrl, а getUrls — общий путь
app/src/Modules/Posts/Domain/Entity/PostMedia.php:51:     * полного набора (оригинал + конверсии, например для FindMediaUrl). Доступ без eager-load вызовет
app/src/Modules/Posts/Application/View/PostViewAssembler.php:7:use App\Modules\Media\Application\Contract\MediaUrlServiceContract;
app/src/Modules/Posts/Application/View/PostViewAssembler.php:40: * сущности через MediaUrlService::getOriginalUrl — отдельного запроса в базу на вложение нет (для
app/src/Modules/Posts/Application/View/PostViewAssembler.php:49:        private MediaUrlServiceContract $mediaUrlService,
app/src/Modules/Posts/Application/View/PostViewAssembler.php:269:     * делегируется MediaUrlService без обращения в БД. Если медиа не готово или его оригинал удалён,
app/src/Modules/Posts/Application/View/PostViewAssembler.php:271:     * оригинал (getOriginalUrl): конверсии (постер видео, превью) сейчас в ленте не используются, а
app/src/Modules/Posts/Application/View/PostViewAssembler.php:277:        $originalUrl = $this->mediaUrlService->getOriginalUrl(media: $postMedia->media);
app/src/Modules/Posts/Repository/PostMediaRepository.php:16: * (PostViewAssembler -> MediaUrlService::getOriginalUrl) и конверсии не читает, но eager-load держится

exec
/bin/zsh -lc "sed -n '260,330p' docs/arch.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
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

exec
/bin/zsh -lc "sed -n '1,180p' tests/Feature/Modules/Posts/Http/GetPostHttpTest.php && sed -n '1,220p' tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "sed -n '1,180p' app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php && sed -n '1,80p' app/src/Modules/Media/Application/Dto/MediaUrlResult.php && sed -n '280,350p' app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Contract;

use App\Modules\Media\Application\Dto\MediaObjectHead;
use App\Modules\Media\Application\Dto\MediaPresignedPartCollection;
use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
use App\Modules\Media\Domain\ValueObject\MediaMultipartUploadIdValue;
use App\Modules\Media\Domain\ValueObject\MediaPath;

/**
 * Контракт серверных файловых операций модуля Media поверх S3-совместимого хранилища.
 *
 * Реальное имя бакета и prefix реализация резолвит из StorageConfig по алиасу MediaStorage,
 * а не из значения enum напрямую. Срок presigned-ссылок этот контракт не выбирает — presign-методы
 * принимают готовый expiresAt: для загрузки его задаёт потребитель через MediaUploadSpec, для
 * скачивания — MediaUrlService (значение по умолчанию реализация читает из MediaConfig, вызывающий
 * может переопределить).
 */
interface MediaFileServiceContract
{
    /**
     * Presigned PUT-ссылка для прямой одиночной загрузки клиентом.
     */
    public function presignPut(
        MediaStorage $storage,
        MediaPath $path,
        MediaMimeType $mimeType,
        \DateTimeImmutable $expiresAt,
    ): string;

    /**
     * Инициирует multipart-загрузку и возвращает её uploadId.
     */
    public function createMultipartUpload(
        MediaStorage $storage,
        MediaPath $path,
        MediaMimeType $mimeType,
    ): MediaMultipartUploadIdValue;

    /**
     * Presigned-ссылки на загрузку каждой части (1..partsCount).
     */
    public function presignUploadParts(
        MediaStorage $storage,
        MediaPath $path,
        MediaMultipartUploadIdValue $uploadId,
        MediaMultipartPartsCount $partsCount,
        \DateTimeImmutable $expiresAt,
    ): MediaPresignedPartCollection;

    /**
     * Собирает multipart-объект из загруженных частей.
     *
     * На повторе после частичного сбоя S3 может вернуть NoSuchUpload — реализация трактует
     * это как успех, если headObject подтверждает собранный объект.
     */
    public function completeMultipartUpload(
        MediaStorage $storage,
        MediaPath $path,
        MediaMultipartUploadIdValue $uploadId,
        MediaMultipartPartCollection $parts,
    ): void;

    /**
     * Отменяет незавершённую multipart-загрузку. 404/NoSuchUpload игнорируется.
     */
    public function abortMultipartUpload(
        MediaStorage $storage,
        MediaPath $path,
        MediaMultipartUploadIdValue $uploadId,
    ): void;

    /**
     * Метаданные объекта. null — объекта нет.
     */
    public function headObject(MediaStorage $storage, MediaPath $path): MediaObjectHead|null;

    /**
     * Содержимое объекта (для чтения оригинала перед конверсией).
     */
    public function getObjectContents(MediaStorage $storage, MediaPath $path): string;

    /**
     * Скачивает объект в локальный временный файл и возвращает его путь. Стримовое чтение без
     * полного буфера в памяти (для ffmpeg-обработки видео/аудио). Владелец файла — вызыватель:
     * он обязан удалить его после использования (процессор делает это в finally).
     */
    public function downloadToFile(MediaStorage $storage, MediaPath $path): string;

    /**
     * Заливает локальный файл (результат ffmpeg-обработки) в хранилище стримом, без полного
     * буфера в памяти. Локальный файл не удаляется — его владелец вызыватель.
     */
    public function uploadFromFile(
        MediaStorage $storage,
        MediaPath $path,
        string $localFile,
        MediaMimeType $mimeType,
    ): void;

    /**
     * Заливает объект (результат конверсии) в хранилище.
     */
    public function putObject(
        MediaStorage $storage,
        MediaPath $path,
        string $contents,
        MediaMimeType $mimeType,
    ): void;

    /**
     * Копирует объект между хранилищами/путями (перекладка оригинала upload -> target).
     */
    public function copyObject(
        MediaStorage $fromStorage,
        MediaPath $fromPath,
        MediaStorage $toStorage,
        MediaPath $toPath,
    ): void;

    /**
     * Удаляет объект. 404 игнорируется (идемпотентное удаление).
     */
    public function deleteObject(MediaStorage $storage, MediaPath $path): void;

    /**
     * Presigned GET-ссылка с TTL (для private-медиа).
     */
    public function presignGet(MediaStorage $storage, MediaPath $path, \DateTimeImmutable $expiresAt): string;

    /**
     * Прямой публичный URL (для public-медиа, бакет с anonymous-read policy).
     */
    public function publicUrl(MediaStorage $storage, MediaPath $path): string;
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Dto;

/**
 * URL к медиа или его конверсии. expiresAt = null для прямого публичного URL (media-public),
 * заполнен для presigned-ссылки private-медиа.
 */
final readonly class MediaUrlResult
{
    public function __construct(
        public string $url,
        public \DateTimeImmutable|null $expiresAt,
    ) {}
}
    public function copyObject(
        MediaStorage $fromStorage,
        MediaPath $fromPath,
        MediaStorage $toStorage,
        MediaPath $toPath,
    ): void {
        $copySource = \sprintf('%s/%s', $this->bucketName($fromStorage), $this->objectKey(storage: $fromStorage, path: $fromPath));

        try {
            $this->client($toStorage)->copyObject([
                'Bucket' => $this->bucketName($toStorage),
                'Key' => $this->objectKey(storage: $toStorage, path: $toPath),
                'CopySource' => $copySource,
            ]);
        } catch (AwsException $exception) {
            throw $this->awsFailure($exception);
        }
    }

    #[\Override]
    public function deleteObject(MediaStorage $storage, MediaPath $path): void
    {
        try {
            $this->client($storage)->deleteObject([
                'Bucket' => $this->bucketName($storage),
                'Key' => $this->objectKey(storage: $storage, path: $path),
            ]);
        } catch (S3Exception $exception) {
            if ($this->isNotFound($exception)) {
                return;
            }

            throw $this->awsFailure($exception);
        }
    }

    #[\Override]
    public function presignGet(MediaStorage $storage, MediaPath $path, \DateTimeImmutable $expiresAt): string
    {
        $client = $this->client($storage);
        $command = $client->getCommand(name: 'GetObject', args: [
            'Bucket' => $this->bucketName($storage),
            'Key' => $this->objectKey(storage: $storage, path: $path),
        ]);

        return (string) $client->createPresignedRequest(command: $command, expires: $expiresAt)->getUri();
    }

    #[\Override]
    public function publicUrl(MediaStorage $storage, MediaPath $path): string
    {
        return $this->client($storage)->getObjectUrl(
            bucket: $this->bucketName($storage),
            key: $this->objectKey(storage: $storage, path: $path),
        );
    }

    private function bucketConfig(MediaStorage $storage): StorageBucketConfig
    {
        return $this->storageConfig->buckets[$storage->value]
            ?? throw MediaStorageNotConfiguredException::bucketAliasMissing($storage);
    }

    private function bucketName(MediaStorage $storage): string
    {
        return $this->bucketConfig($storage)->bucket
            ?? throw MediaStorageNotConfiguredException::bucketNameMissing($storage);
    }

    private function objectKey(MediaStorage $storage, MediaPath $path): string
    {

 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Http;

use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Repository\MediaRepository;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Shared\Domain\ValueObject\UserId;

final class GetPostHttpTest extends PostsHttpTestCase
{
    public function testReturnsEnrichedPost(): void
    {
        $user = $this->createUser();
        $media = $this->createReadyMedia($user->id, MediaVisibility::Public);

        $createResponse = $this->authedJson('POST', '/api/v1/posts', $user->id, [
            'text' => 'Запись с вложениями',
            'mediaIds' => [$media->id->value()],
            'tags' => ['yoga'],
        ]);
        $postId = $this->json($createResponse)['data']['id'];

        // Лайкнем, чтобы проверить флаг likedByMe в чтении.
        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/like', $postId), $user->id)->assertNoContent();

        $response = $this->authedGet(\sprintf('/api/v1/posts/%s', $postId), $user->id);

        $response->assertOk();
        $data = $this->json($response)['data'];
        self::assertSame('Запись с вложениями', $data['text']);
        self::assertSame($user->id->value(), $data['author']['userId']);
        self::assertCount(1, $data['media']);
        self::assertSame('yoga', $data['tags'][0]['text']);
        self::assertTrue($data['likedByMe']);
        self::assertSame(1, $data['likesCount']);
    }

    public function testUnavailableAttachedMediaIsExcludedWithoutError(): void
    {
        $user = $this->createUser();
        $media = $this->createReadyMedia($user->id, MediaVisibility::Public);

        $postId = $this->json($this->authedJson('POST', '/api/v1/posts', $user->id, [
            'text' => 'Запись с медиа',
            'mediaIds' => [$media->id->value()],
        ]))['data']['id'];

        // Медиа перестаёт быть Ready независимо от записи (перемещение/обработка в проде). Чтение
        // записи не должно падать в 500 — недоступное вложение просто исключается из ответа.
        $this->makeMediaUnavailable($media->id);

        $response = $this->authedGet(\sprintf('/api/v1/posts/%s', $postId), $user->id);

        $response->assertOk();
        $data = $this->json($response)['data'];
        self::assertSame([], $data['media']);
        // Контракт: после деградации media пуст — attachmentType не остаётся "media", а становится "none".
        self::assertSame('none', $data['attachmentType']);
    }

    private function makeMediaUnavailable(MediaId $mediaId): void
    {
        $media = $this->getContainer()->get(MediaRepository::class)->findById($mediaId);
        self::assertNotNull($media);
        $media->markReadyOriginalRemoved();
        $this->entityManager()->persist($media);
        $this->entityManager()->run();
        $this->cleanOrmHeap();
    }

    public function testReturnsOriginalForRepost(): void
    {
        $author = $this->createUser();
        $reposter = $this->createUser();
        $original = $this->persistPost($author->id, PostStatus::Published);

        $repostResponse = $this->authedJson(
            'POST',
            \sprintf('/api/v1/posts/%s/repost', $original->id->value()),
            $reposter->id,
        );
        $repostId = $this->json($repostResponse)['data']['id'];

        $response = $this->authedGet(\sprintf('/api/v1/posts/%s', $repostId), $reposter->id);

        $response->assertOk();
        $data = $this->json($response)['data'];
        self::assertNotNull($data['original']);
        self::assertSame($original->id->value(), $data['original']['id']);
    }

    public function testReturnsNullOriginalWhenOriginalDeleted(): void
    {
        $author = $this->createUser();
        $reposter = $this->createUser();
        $original = $this->persistPost($author->id, PostStatus::Published);

        $repostId = $this->json($this->authedJson(
            'POST',
            \sprintf('/api/v1/posts/%s/repost', $original->id->value()),
            $reposter->id,
        ))['data']['id'];

        $this->authedJson('DELETE', \sprintf('/api/v1/posts/%s', $original->id->value()), $author->id)
            ->assertNoContent();

        $response = $this->authedGet(\sprintf('/api/v1/posts/%s', $repostId), $reposter->id);

        $response->assertOk();
        self::assertNull($this->json($response)['data']['original']);
    }

    public function testForeignDraftReturns404(): void
    {
        $author = $this->createUser();
        $viewer = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Draft);

        $this->authedGet(\sprintf('/api/v1/posts/%s', $post->id->value()), $viewer->id)->assertNotFound();
    }

    public function testOwnerSeesOwnDraft(): void
    {
        $author = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Draft);

        $this->authedGet(\sprintf('/api/v1/posts/%s', $post->id->value()), $author->id)->assertOk();
    }

    public function testBlockedReturns404(): void
    {
        $author = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Blocked);

        $this->authedGet(\sprintf('/api/v1/posts/%s', $post->id->value()), $author->id)->assertNotFound();
    }

    public function testDeletedReturns404(): void
    {
        $author = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published, deleted: true);

        $this->authedGet(\sprintf('/api/v1/posts/%s', $post->id->value()), $author->id)->assertNotFound();
    }

    public function testMissingReturns404(): void
    {
        $user = $this->createUser();

        $this->authedGet(\sprintf('/api/v1/posts/%s', UserId::generate()->value()), $user->id)->assertNotFound();
    }

    public function testInvalidIdReturns422(): void
    {
        $user = $this->createUser();

        $this->authedGet('/api/v1/posts/not-a-uuid', $user->id)->assertUnprocessable();
    }

    public function testRequiresAuthentication(): void
    {
        $author = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);

        $this->fakeHttp()->getJson(\sprintf('/api/v1/posts/%s', $post->id->value()))->assertUnauthorized();
    }
}
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\User\Application;

use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileHandler;
use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileQuery;
use App\Modules\User\Domain\ValueObject\UserAvatar;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;

final class GetUserPublicProfileHandlerTest extends UserApplicationTestCase
{
    public function testReturnsDefaultAvatarAndLocaleWhenUserHasNoAvatar(): void
    {
        $user = $this->persistUser(locale: Locale::En);

        $view = $this->handler()->handle(new GetUserPublicProfileQuery($user->id->value()));

        self::assertSame($user->id->value(), $view->userId);
        self::assertSame('Йога Тест', $view->name);
        self::assertSame($this->defaultAvatarUrl(), $view->avatarUrl);
        self::assertSame('en', $view->locale);
    }

    public function testReturnsRealAvatarUrlWhenMediaReady(): void
    {
        $media = $this->persistReadyPublicMedia();
        $user = $this->persistUser(avatar: UserAvatar::pointingTo($media->id->value()));

        $view = $this->handler()->handle(new GetUserPublicProfileQuery($user->id->value()));

        self::assertSame(self::STUBBED_AVATAR_URL, $view->avatarUrl);
        self::assertSame('ru', $view->locale);
    }

    public function testFallsBackToDefaultWhenAvatarMediaNotReady(): void
    {
        $media = $this->persistNotReadyMedia();
        $user = $this->persistUser(avatar: UserAvatar::pointingTo($media->id->value()));

        $view = $this->handler()->handle(new GetUserPublicProfileQuery($user->id->value()));

        self::assertSame($this->defaultAvatarUrl(), $view->avatarUrl);
    }

    public function testFallsBackToDefaultWhenAvatarOriginalRemoved(): void
    {
        // Смысловой центр changeset на границе аватара: оригинал удалён -> getOriginalUrl = null ->
        // ассемблер отдаёт аватар по умолчанию. Регресс-гард на случай, если оригинал снова начнёт
        // отдавать (устаревшую) ссылку для readyOriginalRemoved.
        $media = $this->persistReadyOriginalRemovedMedia();
        $user = $this->persistUser(avatar: UserAvatar::pointingTo($media->id->value()));

        $view = $this->handler()->handle(new GetUserPublicProfileQuery($user->id->value()));

        self::assertSame($this->defaultAvatarUrl(), $view->avatarUrl);
    }

    public function testThrowsWhenUserNotFound(): void
    {
        $this->expectException(NotFoundException::class);

        $this->handler()->handle(new GetUserPublicProfileQuery(UserId::generate()->value()));
    }

    private function handler(): GetUserPublicProfileHandler
    {
        return new GetUserPublicProfileHandler(
            userRepository: $this->userRepository(),
            assembler: $this->profileHandlerAssembler(),
        );
    }
}

exec
/bin/zsh -lc "sed -n '1,300p' tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php && sed -n '1,220p' tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaConversionUrl;
use App\Modules\Media\Application\Dto\MediaConversionUrlCollection;
use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlHandler;
use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlQuery;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaAudioConversion;
use App\Modules\Media\Domain\Entity\MediaVideoConversion;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaConversionKind;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaDuration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
use App\Modules\Media\Domain\ValueObject\MediaWaveform;
use App\Modules\Media\Infrastructure\FileService\MediaUrlService;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Configuration\Media\MediaConfig;

final class FindMediaUrlHandlerTest extends MediaApplicationTestCase
{
    public function testReturnsNullForMissingMedia(): void
    {
        $result = $this->handler($this->createStub(MediaFileServiceContract::class))->handle(
            new FindMediaUrlQuery(mediaId: UserId::generate()->value(), presignedTtlSeconds: 300),
        );

        self::assertNull($result);
    }

    public function testReturnsNullForMediaThatIsNotFinalized(): void
    {
        // Ещё не ready и не readyOriginalRemoved -> best-effort null, чтобы вызывающий подставил дефолт.
        $media = $this->createMedia(userId: UserId::generate());
        $this->persist($media);
        $this->cleanOrmHeap();

        $result = $this->handler($this->createStub(MediaFileServiceContract::class))->handle(
            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
        );

        self::assertNull($result);
    }

    public function testReturnsOriginalAndAllConversionsForReadyPublicMedia(): void
    {
        $media = $this->readyMedia(MediaVisibility::Public);
        $thumbnail = $this->thumbnailConversion($media);
        $video = $this->videoConversion($media);
        $audio = $this->audioConversion($media);
        $this->persist($media, $thumbnail, $video, $audio);
        $this->cleanOrmHeap();

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::atLeastOnce())->method('publicUrl')->willReturnCallback(
            static fn(MediaStorage $storage, MediaPath $path): string => 'http://minio/media-public/' . $path->value(),
        );
        $fileService->expects(self::never())->method('presignGet');

        $result = $this->handler($fileService)->handle(
            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
        );

        self::assertNotNull($result);

        // Оригинал — прямой публичный URL без срока.
        self::assertNotNull($result->original);
        self::assertSame('http://minio/media-public/' . $media->path->value(), $result->original->url);
        self::assertNull($result->original->expiresAt);

        // Все три конверсии присутствуют, каждая со своим видом, типом и URL по своему пути.
        self::assertCount(3, $result->conversions);

        $thumbnailUrl = $this->conversionByType($result->conversions, MediaImageConversionType::Thumbnail);
        self::assertSame(MediaConversionKind::Image, $thumbnailUrl->kind);
        self::assertSame('http://minio/media-public/' . $thumbnail->path->value(), $thumbnailUrl->url);
        self::assertNull($thumbnailUrl->expiresAt);

        $videoUrl = $this->conversionByType($result->conversions, MediaVideoConversionType::NormalizedMp4H264);
        self::assertSame(MediaConversionKind::Video, $videoUrl->kind);
        self::assertSame('http://minio/media-public/' . $video->path->value(), $videoUrl->url);

        $audioUrl = $this->conversionByType($result->conversions, MediaAudioConversionType::NormalizedAacM4a);
        self::assertSame(MediaConversionKind::Audio, $audioUrl->kind);
        self::assertSame('http://minio/media-public/' . $audio->path->value(), $audioUrl->url);
    }

    public function testReturnsPresignedOriginalAndConversionForReadyPrivateMedia(): void
    {
        $media = $this->readyMedia(MediaVisibility::Private);
        $thumbnail = $this->thumbnailConversion($media);
        $this->persist($media, $thumbnail);
        $this->cleanOrmHeap();

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::never())->method('publicUrl');
        $fileService->expects(self::atLeastOnce())->method('presignGet')->willReturnCallback(
            static fn(MediaStorage $storage, MediaPath $path, \DateTimeImmutable $expiresAt): string
                => 'http://minio/signed/' . $path->value(),
        );

        $result = $this->handler($fileService)->handle(
            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
        );

        self::assertNotNull($result);

        // Оригинал — presigned-ссылка со сроком.
        self::assertNotNull($result->original);
        self::assertSame('http://minio/signed/' . $media->path->value(), $result->original->url);
        self::assertNotNull($result->original->expiresAt);
        self::assertEqualsWithDelta(
            new \DateTimeImmutable('+300 seconds')->getTimestamp(),
            $result->original->expiresAt->getTimestamp(),
            5,
        );

        // Конверсия private тоже presigned со сроком.
        self::assertCount(1, $result->conversions);
        $conversion = $this->conversionByType($result->conversions, MediaImageConversionType::Thumbnail);
        self::assertSame('http://minio/signed/' . $thumbnail->path->value(), $conversion->url);
        self::assertNotNull($conversion->expiresAt);
    }

    public function testOmitsOriginalButKeepsConversionsForReadyOriginalRemovedMedia(): void
    {
        // Ключевое поведение: после удаления оригинала результат не null — original = null,
        // а конверсии продолжают резолвиться. Никакого 404.
        $media = $this->readyMedia(MediaVisibility::Public);
        $thumbnail = $this->thumbnailConversion($media);
        $media->markReadyOriginalRemoved();
        $this->persist($media, $thumbnail);
        $this->cleanOrmHeap();

        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturnCallback(
            static fn(MediaStorage $storage, MediaPath $path): string => 'http://minio/media-public/' . $path->value(),
        );

        $result = $this->handler($fileService)->handle(
            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
        );

        self::assertNotNull($result);
        self::assertNull($result->original);
        self::assertCount(1, $result->conversions);
        self::assertSame(
            'http://minio/media-public/' . $thumbnail->path->value(),
            $this->conversionByType($result->conversions, MediaImageConversionType::Thumbnail)->url,
        );
    }

    public function testReturnsEmptyConversionsForReadyMediaWithoutConversions(): void
    {
        $media = $this->readyMedia(MediaVisibility::Public);
        $this->persist($media);
        $this->cleanOrmHeap();

        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturn('http://minio/media-public/object');

        $result = $this->handler($fileService)->handle(
            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
        );

        self::assertNotNull($result);
        self::assertNotNull($result->original);
        self::assertSame('http://minio/media-public/object', $result->original->url);
        self::assertTrue($result->conversions->isEmpty());
    }

    public function testIgnoresInvalidTtlForPublicMedia(): void
    {
        // Для public-медиа ветка резолвера short-circuit'ит до построения MediaPresignedTtl, поэтому
        // заведомо невалидный TTL (явный 0) проходит как успех без presignGet — причина именно в
        // public short-circuit, а не в самом значении 0 (для private такой 0 бросил бы исключение).
        $media = $this->readyMedia(MediaVisibility::Public);
        $this->persist($media);
        $this->cleanOrmHeap();

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::once())->method('publicUrl')->willReturn('http://minio/media-public/object');
        $fileService->expects(self::never())->method('presignGet');

        $result = $this->handler($fileService)->handle(
            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 0),
        );

        self::assertNotNull($result);
        self::assertNotNull($result->original);
        self::assertSame('http://minio/media-public/object', $result->original->url);
        self::assertNull($result->original->expiresAt);
    }

    public function testUsesDefaultTtlForPrivateMediaWithoutOverride(): void
    {
        // Без presignedTtlSeconds в запросе берётся значение по умолчанию сервиса (3600), а expiresAt
        // считается на каждый вызов (singleton) — now + 3600.
        $media = $this->readyMedia(MediaVisibility::Private);
        $this->persist($media);
        $this->cleanOrmHeap();

        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('presignGet')->willReturnCallback(
            static fn(MediaStorage $storage, MediaPath $path, \DateTimeImmutable $expiresAt): string
                => 'http://minio/signed/' . $path->value(),
        );

        $result = $this->handler($fileService)->handle(
            new FindMediaUrlQuery(mediaId: $media->id->value()),
        );

        self::assertNotNull($result);
        self::assertNotNull($result->original);
        self::assertNotNull($result->original->expiresAt);
        self::assertEqualsWithDelta(
            new \DateTimeImmutable('+3600 seconds')->getTimestamp(),
            $result->original->expiresAt->getTimestamp(),
            5,
        );
    }

    public function testRejectsExplicitZeroTtlForPrivateMedia(): void
    {
        // Строгая семантика !== null: явный 0 для private не уходит в значение по умолчанию, а строит
        // MediaPresignedTtl::fromInt(0) -> вне диапазона (MIN=1) -> InvalidDomainValueException.
        $media = $this->readyMedia(MediaVisibility::Private);
        $this->persist($media);
        $this->cleanOrmHeap();

        $this->expectException(InvalidDomainValueException::class);

        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(
            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 0),
        );
    }

    private function handler(MediaFileServiceContract $fileService): FindMediaUrlHandler
    {
        return new FindMediaUrlHandler(
            mediaRepository: $this->mediaRepository(),
            mediaUrlService: new MediaUrlService(
                mediaFileService: $fileService,
                mediaConfig: $this->getContainer()->get(MediaConfig::class),
            ),
        );
    }

    private function conversionByType(
        MediaConversionUrlCollection $conversions,
        MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType $type,
    ): MediaConversionUrl {
        $conversion = $conversions->ofType($type);
        self::assertNotNull($conversion, \sprintf('Конверсия типа %s не найдена в результате.', $type->value));

        return $conversion;
    }

    private function videoConversion(Media $media): MediaVideoConversion
    {
        return MediaVideoConversion::create(
            media: $media,
            type: MediaVideoConversionType::NormalizedMp4H264,
            status: MediaConversionStatus::Ready,
            storage: $media->storage,
            path: MediaPath::videoConversion(
                storageKey: $media->storageKey,
                type: MediaVideoConversionType::NormalizedMp4H264,
                extension: 'mp4',
            ),
            mimeType: MediaMimeType::fromString('video/mp4'),
            size: MediaFileSize::fromInt(4096),
            width: MediaPixelDimension::fromInt(1280),
            height: MediaPixelDimension::fromInt(720),
            duration: MediaDuration::fromInt(2000),
            bitrate: MediaBitrate::fromInt(900_000),
        );
    }

    private function audioConversion(Media $media): MediaAudioConversion
    {
        return MediaAudioConversion::create(
            media: $media,
            type: MediaAudioConversionType::NormalizedAacM4a,
            status: MediaConversionStatus::Ready,
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Query\FindMediaOriginalUrl\FindMediaOriginalUrlHandler;
use App\Modules\Media\Application\Query\FindMediaOriginalUrl\FindMediaOriginalUrlQuery;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Infrastructure\FileService\MediaUrlService;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Configuration\Media\MediaConfig;

final class FindMediaOriginalUrlHandlerTest extends MediaApplicationTestCase
{
    public function testReturnsNullForMissingMedia(): void
    {
        $result = $this->handler($this->createStub(MediaFileServiceContract::class))->handle(
            new FindMediaOriginalUrlQuery(mediaId: UserId::generate()->value(), presignedTtlSeconds: 300),
        );

        self::assertNull($result);
    }

    public function testReturnsNullForMediaThatIsNotFinalized(): void
    {
        $media = $this->createMedia(userId: UserId::generate());
        $this->persist($media);
        $this->cleanOrmHeap();

        $result = $this->handler($this->createStub(MediaFileServiceContract::class))->handle(
            new FindMediaOriginalUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
        );

        self::assertNull($result);
    }

    public function testReturnsNullWhenOriginalRemovedWithoutTouchingFileService(): void
    {
        // readyOriginalRemoved -> оригинала нет -> null. Конверсия в БД есть, но её не резолвят:
        // путь оригинала вообще не обращается ни к presignGet, ни к publicUrl.
        $media = $this->readyMedia(MediaVisibility::Private);
        $thumbnail = $this->thumbnailConversion($media);
        $media->markReadyOriginalRemoved();
        $this->persist($media, $thumbnail);
        $this->cleanOrmHeap();

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::never())->method('presignGet');
        $fileService->expects(self::never())->method('publicUrl');

        $result = $this->handler($fileService)->handle(
            new FindMediaOriginalUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
        );

        self::assertNull($result);
    }

    public function testReturnsPublicOriginalWithoutResolvingConversions(): void
    {
        // Регрессия №1: у медиа есть конверсия, но аватар-путь строит только оригинал —
        // publicUrl вызывается ровно один раз (на оригинал), конверсия не резолвится.
        $media = $this->readyMedia(MediaVisibility::Public);
        $thumbnail = $this->thumbnailConversion($media);
        $this->persist($media, $thumbnail);
        $this->cleanOrmHeap();

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::never())->method('presignGet');
        $fileService->expects(self::once())->method('publicUrl')->willReturnCallback(
            static fn(MediaStorage $storage, MediaPath $path): string => 'http://minio/media-public/' . $path->value(),
        );

        $result = $this->handler($fileService)->handle(
            new FindMediaOriginalUrlQuery(mediaId: $media->id->value()),
        );

        self::assertNotNull($result);
        self::assertSame('http://minio/media-public/' . $media->path->value(), $result->url);
        self::assertNull($result->expiresAt);
    }

    public function testReturnsPresignedOriginalWithoutSigningConversionsForPrivateMedia(): void
    {
        // Регрессия №1: для private-медиа с конверсией presignGet вызывается ровно один раз (на
        // оригинал) — presigned-ссылки конверсий, которые тут же отбрасывались бы, не строятся.
        $media = $this->readyMedia(MediaVisibility::Private);
        $thumbnail = $this->thumbnailConversion($media);
        $this->persist($media, $thumbnail);
        $this->cleanOrmHeap();

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::never())->method('publicUrl');
        $fileService->expects(self::once())->method('presignGet')->willReturnCallback(
            static fn(MediaStorage $storage, MediaPath $path, \DateTimeImmutable $expiresAt): string
                => 'http://minio/signed/' . $path->value(),
        );

        $result = $this->handler($fileService)->handle(
            new FindMediaOriginalUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
        );

        self::assertNotNull($result);
        self::assertSame('http://minio/signed/' . $media->path->value(), $result->url);
        self::assertNotNull($result->expiresAt);
        self::assertEqualsWithDelta(
            new \DateTimeImmutable('+300 seconds')->getTimestamp(),
            $result->expiresAt->getTimestamp(),
            5,
        );
    }

    public function testUsesDefaultTtlForPrivateMediaWithoutOverride(): void
    {
        $media = $this->readyMedia(MediaVisibility::Private);
        $this->persist($media);
        $this->cleanOrmHeap();

        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('presignGet')->willReturnCallback(
            static fn(MediaStorage $storage, MediaPath $path, \DateTimeImmutable $expiresAt): string
                => 'http://minio/signed/' . $path->value(),
        );

        $result = $this->handler($fileService)->handle(
            new FindMediaOriginalUrlQuery(mediaId: $media->id->value()),
        );

        self::assertNotNull($result);
        self::assertNotNull($result->expiresAt);
        self::assertEqualsWithDelta(
            new \DateTimeImmutable('+3600 seconds')->getTimestamp(),
            $result->expiresAt->getTimestamp(),
            5,
        );
    }

    private function handler(MediaFileServiceContract $fileService): FindMediaOriginalUrlHandler
    {
        return new FindMediaOriginalUrlHandler(
            mediaRepository: $this->mediaRepository(),
            mediaUrlService: new MediaUrlService(
                mediaFileService: $fileService,
                mediaConfig: $this->getContainer()->get(MediaConfig::class),
            ),
        );
    }

}

exec
/bin/zsh -lc "sed -n '1,360p' tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Command\RemoveMediaOriginal\RemoveMediaOriginalCommand;
use App\Modules\Media\Application\Command\RemoveMediaOriginal\RemoveMediaOriginalHandler;
use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Service\MediaConversionsChecker;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaAudioConversion;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\Entity\MediaVideoConversion;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaDuration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
use App\Modules\Media\Domain\ValueObject\MediaWaveform;
use App\Shared\Domain\Exception\ForbiddenException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\ValueObject\UserId;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class RemoveMediaOriginalHandlerTest extends MediaApplicationTestCase
{
    public function testRemovesOriginalOfImageMediaKeepingConversion(): void
    {
        $userId = UserId::generate();
        $media = $this->readyImageMediaWithConversion($userId);
        $originalStorage = $media->storage;
        $originalPath = $media->path->value();

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $deleted = [];
        $fileService->expects(self::once())->method('deleteObject')->willReturnCallback(
            function (MediaStorage $storage, MediaPath $path) use (&$deleted): void {
                $deleted[] = [$storage, $path->value()];
            },
        );

        $result = $this->handler($fileService)->handle(new RemoveMediaOriginalCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
        ));

        // Удалён именно текущий оригинал в целевом бакете (после markReadyMovedTo), не staging.
        self::assertSame([[$originalStorage, $originalPath]], $deleted);
        self::assertSame(MediaStatus::ReadyOriginalRemoved, $media->status);
        self::assertSame(MediaStatus::ReadyOriginalRemoved, $result->status);
        self::assertSame($media->id->value(), $result->mediaId);
        self::assertCount(1, $this->imageConversionRepository()->findByMediaId($media->id));
    }

    public function testRemovesOriginalOfVideoMediaKeepingConversionAndPoster(): void
    {
        $userId = UserId::generate();
        $media = $this->readyVideoMediaWithConversion($userId);
        $originalStorage = $media->storage;
        $originalPath = $media->path->value();

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $deleted = [];
        $fileService->expects(self::once())->method('deleteObject')->willReturnCallback(
            function (MediaStorage $storage, MediaPath $path) use (&$deleted): void {
                $deleted[] = [$storage, $path->value()];
            },
        );

        $this->handler($fileService)->handle(new RemoveMediaOriginalCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
        ));

        // Удалён именно оригинал видео, а не конверсия/постер.
        self::assertSame([[$originalStorage, $originalPath]], $deleted);
        self::assertSame(MediaStatus::ReadyOriginalRemoved, $media->status);
        self::assertCount(1, $this->videoConversionRepository()->findByMediaId($media->id));
        self::assertCount(1, $this->imageConversionRepository()->findByMediaId($media->id));
    }

    public function testRemovesOriginalOfAudioMediaKeepingConversion(): void
    {
        $userId = UserId::generate();
        $media = $this->readyAudioMediaWithConversion($userId);
        $originalStorage = $media->storage;
        $originalPath = $media->path->value();

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $deleted = [];
        $fileService->expects(self::once())->method('deleteObject')->willReturnCallback(
            function (MediaStorage $storage, MediaPath $path) use (&$deleted): void {
                $deleted[] = [$storage, $path->value()];
            },
        );

        $this->handler($fileService)->handle(new RemoveMediaOriginalCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
        ));

        // У audio нет image-конверсии — гард обнаруживает конверсию через аудио-репозиторий.
        // Удалён именно оригинал, а не аудио-конверсия.
        self::assertSame([[$originalStorage, $originalPath]], $deleted);
        self::assertSame(MediaStatus::ReadyOriginalRemoved, $media->status);
        self::assertCount(1, $this->audioConversionRepository()->findByMediaId($media->id));
    }

    public function testRejectsMissingMedia(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('app.media.not_found');

        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new RemoveMediaOriginalCommand(
            userId: UserId::generate()->value(),
            mediaId: UserId::generate()->value(),
        ));
    }

    public function testRejectsForeignOwner(): void
    {
        $media = $this->readyImageMediaWithConversion(UserId::generate());

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('app.media.access_denied');

        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new RemoveMediaOriginalCommand(
            userId: UserId::generate()->value(),
            mediaId: $media->id->value(),
        ));
    }

    public function testRejectsNonReadyMedia(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId);
        $media->markUploaded();
        $this->persist($media);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('app.media.original_not_removable');

        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new RemoveMediaOriginalCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
        ));
    }

    public function testRejectsReadyDocumentWithoutConversions(): void
    {
        $userId = UserId::generate();
        $media = $this->readyMediaWithoutConversions(
            userId: $userId,
            type: MediaType::Document,
            extension: 'pdf',
            mimeType: 'application/pdf',
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('app.media.no_conversions_to_keep');

        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new RemoveMediaOriginalCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
        ));
    }

    public function testRejectsReadyImageWithEmptyConversionPlan(): void
    {
        $userId = UserId::generate();
        $media = $this->readyMediaWithoutConversions(
            userId: $userId,
            type: MediaType::Image,
            extension: 'jpg',
            mimeType: 'image/jpeg',
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('app.media.no_conversions_to_keep');

        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new RemoveMediaOriginalCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
        ));
    }

    public function testIsIdempotentWhenOriginalAlreadyRemoved(): void
    {
        $userId = UserId::generate();
        $media = $this->readyImageMediaWithConversion($userId);
        $media->markReadyOriginalRemoved();
        $this->persist($media);
        $updatedAtBefore = $media->updatedAt;

        // Ранний return до deleteObject: повторный вызов не обращается к S3.
        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::never())->method('deleteObject');

        $result = $this->handler($fileService)->handle(new RemoveMediaOriginalCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
        ));

        self::assertSame(MediaStatus::ReadyOriginalRemoved, $result->status);
        self::assertSame($media->id->value(), $result->mediaId);

        // Ранний return до persist+run(): повторной записи в БД нет — updatedAt не сдвинулся.
        $reloaded = $this->mediaRepository()->findById($media->id);
        self::assertNotNull($reloaded);
        self::assertEquals($updatedAtBefore, $reloaded->updatedAt);
    }

    public function testLogsCompletionContextWithoutRawValueObjects(): void
    {
        // Закрепляет контракт debug_precise: состав лог-контекста завершения (mediaId/userId/storage/path,
        // camelCase, без сырых VO). Без этого случайное удаление ключа или передача VO не уронит тесты.
        $userId = UserId::generate();
        $media = $this->readyImageMediaWithConversion($userId);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('debug')->with(
            'Оригинал медиа удалён.',
            [
                'mediaId' => $media->id->value(),
                'userId' => $userId->value(),
                'storage' => $media->storage->value,
                'path' => $media->path->value(),
            ],
        );

        $this->handler($this->createStub(MediaFileServiceContract::class), $logger)->handle(
            new RemoveMediaOriginalCommand(
                userId: $userId->value(),
                mediaId: $media->id->value(),
            ),
        );
    }

    private function handler(
        MediaFileServiceContract $fileService,
        LoggerInterface|null $logger = null,
    ): RemoveMediaOriginalHandler {
        return new RemoveMediaOriginalHandler(
            mediaRepository: $this->mediaRepository(),
            mediaConversionsChecker: new MediaConversionsChecker(
                mediaImageConversionRepository: $this->imageConversionRepository(),
                mediaVideoConversionRepository: $this->videoConversionRepository(),
                mediaAudioConversionRepository: $this->audioConversionRepository(),
            ),
            mediaFileService: $fileService,
            entityManager: $this->entityManager(),
            logger: $logger ?? new NullLogger(),
        );
    }

    private function readyImageMediaWithConversion(UserId $userId): Media
    {
        $media = $this->readyMediaWithoutConversions(
            userId: $userId,
            type: MediaType::Image,
            extension: 'jpg',
            mimeType: 'image/jpeg',
        );

        $this->persist(MediaImageConversion::create(
            media: $media,
            type: MediaImageConversionType::Thumbnail,
            status: MediaConversionStatus::Ready,
            storage: $media->storage,
            path: MediaPath::imageConversion(
                storageKey: $media->storageKey,
                type: MediaImageConversionType::Thumbnail,
                extension: 'jpg',
            ),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(256),
            width: MediaPixelDimension::fromInt(100),
            height: MediaPixelDimension::fromInt(100),
        ));

        return $media;
    }

    private function readyVideoMediaWithConversion(UserId $userId): Media
    {
        $media = $this->readyMediaWithoutConversions(
            userId: $userId,
            type: MediaType::Video,
            extension: 'mp4',
            mimeType: 'video/mp4',
        );

        $this->persist(MediaVideoConversion::create(
            media: $media,
            type: MediaVideoConversionType::NormalizedMp4H264,
            status: MediaConversionStatus::Ready,
            storage: $media->storage,
            path: MediaPath::videoConversion(
                storageKey: $media->storageKey,
                type: MediaVideoConversionType::NormalizedMp4H264,
                extension: 'mp4',
            ),
            mimeType: MediaMimeType::fromString('video/mp4'),
            size: MediaFileSize::fromInt(4096),
            width: MediaPixelDimension::fromInt(1280),
            height: MediaPixelDimension::fromInt(720),
            duration: MediaDuration::fromInt(2000),
            bitrate: MediaBitrate::fromInt(900_000),
        ));
        $this->persist(MediaImageConversion::create(
            media: $media,
            type: MediaImageConversionType::Poster,
            status: MediaConversionStatus::Ready,
            storage: $media->storage,
            path: MediaPath::imageConversion(
                storageKey: $media->storageKey,
                type: MediaImageConversionType::Poster,
                extension: 'jpg',
            ),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(512),
            width: MediaPixelDimension::fromInt(1280),
            height: MediaPixelDimension::fromInt(720),
        ));

        return $media;
    }

    private function readyAudioMediaWithConversion(UserId $userId): Media
    {
        $media = $this->readyMediaWithoutConversions(
            userId: $userId,
            type: MediaType::Audio,
            extension: 'mp3',
            mimeType: 'audio/mpeg',
        );

        $this->persist(MediaAudioConversion::create(
            media: $media,
            type: MediaAudioConversionType::NormalizedAacM4a,
            status: MediaConversionStatus::Ready,
            storage: $media->storage,
            path: MediaPath::audioConversion(
                storageKey: $media->storageKey,
                type: MediaAudioConversionType::NormalizedAacM4a,
                extension: 'm4a',
            ),

exec
/bin/zsh -lc "sed -n '1,120p' app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlQuery.php && sed -n '1,120p' app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/FindMediaOriginalUrlQuery.php && sed -n '1,220p' tests/Kernel/Modules/User/UserBootloaderTest.php && sed -n '1,160p' tests/Kernel/Modules/Media/MediaBootloaderTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\FindMediaUrl;

final readonly class FindMediaUrlQuery
{
    public function __construct(
        public string $mediaId,
        public int|null $presignedTtlSeconds = null,
    ) {}
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\FindMediaOriginalUrl;

final readonly class FindMediaOriginalUrlQuery
{
    public function __construct(
        public string $mediaId,
        public int|null $presignedTtlSeconds = null,
    ) {}
}
<?php

declare(strict_types=1);

namespace Tests\Kernel\Modules\User;

use App\Modules\User\Application\Profile\UserPublicProfileAssembler;
use Tests\TestCase;

final class UserBootloaderTest extends TestCase
{
    public function testUserPublicProfileAssemblerIsBuiltFromConfig(): void
    {
        // Фабрика бутлоадера читает UserConfig и передаёт готовую строку defaultAvatarUrl в ассемблер.
        $assembler = $this->getContainer()->get(UserPublicProfileAssembler::class);

        self::assertInstanceOf(UserPublicProfileAssembler::class, $assembler);
    }
}
<?php

declare(strict_types=1);

namespace Tests\Kernel\Modules\Media;

use App\Modules\Media\Application\Contract\MediaUploadPlannerContract;
use App\Modules\Media\Application\Contract\MediaUrlServiceContract;
use App\Modules\Media\Infrastructure\FileService\MediaUploadPlanner;
use App\Modules\Media\Infrastructure\FileService\MediaUrlService;
use Tests\TestCase;

final class MediaBootloaderTest extends TestCase
{
    public function testMediaUploadPlannerContractResolvesToInfrastructureImplementation(): void
    {
        // Биндинг const BINDINGS отдаёт реализацию планнера из Infrastructure\FileService; она сама
        // читает MediaConfig через конструктор (как MediaUrlService), без фабрики и settings-объекта.
        $mediaUploadPlanner = $this->getContainer()->get(MediaUploadPlannerContract::class);

        self::assertInstanceOf(MediaUploadPlanner::class, $mediaUploadPlanner);
    }

    public function testMediaUrlServiceContractResolvesToInfrastructureImplementation(): void
    {
        // Биндинг const BINDINGS отдаёт реализацию из Infrastructure\FileService; срок presigned по
        // умолчанию реализация читает из MediaConfig сама (без фабрики и обёртки MediaPresignedTtl).
        $mediaUrlService = $this->getContainer()->get(MediaUrlServiceContract::class);

        self::assertInstanceOf(MediaUrlService::class, $mediaUrlService);
    }
}

exec
/bin/zsh -lc "sed -n '1,240p' tests/Unit/Modules/Media/Infrastructure/MediaUploadPlannerTest.php && sed -n '1,220p' tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Media\Infrastructure;

use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Infrastructure\FileService\MediaUploadPlanner;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MediaUploadPlannerTest extends TestCase
{
    public function testStagingExpirationIsTemporaryAtConfiguredTtl(): void
    {
        $planner = new MediaUploadPlanner($this->mediaConfig(stagingTtlSeconds: 86_400));

        $expiration = $planner->stagingExpiration();

        self::assertTrue($expiration->isTemporary());
        self::assertEqualsWithDelta(
            new \DateTimeImmutable('+86400 seconds')->getTimestamp(),
            $expiration->value()?->getTimestamp(),
            5,
        );
    }

    #[DataProvider('multipartThresholdProvider')]
    public function testIsMultipartComparesSizeWithThreshold(int $size, bool $expected): void
    {
        $planner = new MediaUploadPlanner(
            $this->mediaConfig(multipartThresholdBytes: 5_242_880, multipartPartSizeBytes: 5_242_880),
        );

        self::assertSame($expected, $planner->isMultipart(MediaFileSize::fromInt($size)));
    }

    /**
     * @return list<array{int, bool}>
     */
    public static function multipartThresholdProvider(): array
    {
        return [
            [5_242_880, true],
            [5_242_881, true],
            [5_242_879, false],
        ];
    }

    public function testPartSizeReflectsConfiguredPartSizeBytes(): void
    {
        $planner = new MediaUploadPlanner(
            $this->mediaConfig(multipartThresholdBytes: 16_777_216, multipartPartSizeBytes: 8_388_608),
        );

        self::assertSame(8_388_608, $planner->partSize()->value());
    }

    public function testPartsCountRoundsUp(): void
    {
        $planner = new MediaUploadPlanner(
            $this->mediaConfig(multipartThresholdBytes: 5_242_880, multipartPartSizeBytes: 5_242_880),
        );

        $partSize = $planner->partSize();

        self::assertSame(2, $planner->partsCount(size: MediaFileSize::fromInt(5_242_881), partSize: $partSize)->value());
        self::assertSame(1, $planner->partsCount(size: MediaFileSize::fromInt(5_242_880), partSize: $partSize)->value());
    }

    public function testPartSizeBelowVoMinimumThrowsDomainValueException(): void
    {
        // Конфиг валиден (порог >= размера части), но размер части ниже минимума MediaMultipartPartSize
        // (5 MiB): расчёт не должен обходить валидацию VO.
        $planner = new MediaUploadPlanner(
            $this->mediaConfig(multipartThresholdBytes: 1_048_576, multipartPartSizeBytes: 1_048_576),
        );

        $this->expectException(InvalidDomainValueException::class);

        $planner->partSize();
    }

    private function mediaConfig(
        int $stagingTtlSeconds = 86_400,
        int $multipartThresholdBytes = 5_242_880,
        int $multipartPartSizeBytes = 5_242_880,
    ): MediaConfig {
        return new MediaConfig(
            stagingTtlSeconds: $stagingTtlSeconds,
            multipartThresholdBytes: $multipartThresholdBytes,
            multipartPartSizeBytes: $multipartPartSizeBytes,
            imageProcessingDriver: 'imagick',
            ffmpegBinaryPath: '/usr/bin/ffmpeg',
            ffprobeBinaryPath: '/usr/bin/ffprobe',
            ffmpegTimeoutSeconds: 1800,
            ffmpegThreads: 0,
            presignedTtlSeconds: 3600,
        );
    }
}
<?php

declare(strict_types=1);

namespace Tests\Kernel\Shared\Infrastructure\Configuration;

use App\Shared\Infrastructure\Configuration\Mapping\ConfigMapper;
use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
use App\Shared\Infrastructure\Exception\InvalidConfigValueException;
use CuyZ\Valinor\Mapper\Configurator\ConvertKeysToCamelCase;
use CuyZ\Valinor\MapperBuilder;
use CuyZ\Valinor\Normalizer\Format;
use CuyZ\Valinor\NormalizerBuilder;
use Spiral\Config\ConfiguratorInterface;
use Tests\TestCase;

final class MediaConfigTest extends TestCase
{
    public function testRealMediaConfigIsMappedFromContainer(): void
    {
        $mediaConfig = $this->getContainer()->get(MediaConfig::class);

        self::assertSame(86_400, $mediaConfig->stagingTtlSeconds);
        self::assertSame(3600, $mediaConfig->presignedTtlSeconds);
        self::assertSame(16_777_216, $mediaConfig->multipartThresholdBytes);
        self::assertSame(8_388_608, $mediaConfig->multipartPartSizeBytes);
        self::assertSame('imagick', $mediaConfig->imageProcessingDriver);
        self::assertSame('/usr/bin/ffmpeg', $mediaConfig->ffmpegBinaryPath);
        self::assertSame('/usr/bin/ffprobe', $mediaConfig->ffprobeBinaryPath);
        self::assertSame(1800, $mediaConfig->ffmpegTimeoutSeconds);
        self::assertSame(0, $mediaConfig->ffmpegThreads);
    }

    public function testMapsMediaConfigSection(): void
    {
        $mediaConfig = $this->mapperFor([
            'stagingTtlSeconds' => 3600,
            'presignedTtlSeconds' => 7200,
            'multipartThresholdBytes' => 20_971_520,
            'multipartPartSizeBytes' => 5_242_880,
            'imageProcessingDriver' => 'gd',
            'ffmpegBinaryPath' => '/opt/ffmpeg',
            'ffprobeBinaryPath' => '/opt/ffprobe',
            'ffmpegTimeoutSeconds' => 600,
            'ffmpegThreads' => 4,
        ])->map(section: MediaConfig::configName(), targetClass: MediaConfig::class);

        self::assertSame('media', MediaConfig::configName());
        self::assertSame(3600, $mediaConfig->stagingTtlSeconds);
        self::assertSame(7200, $mediaConfig->presignedTtlSeconds);
        self::assertSame(20_971_520, $mediaConfig->multipartThresholdBytes);
        self::assertSame(5_242_880, $mediaConfig->multipartPartSizeBytes);
        self::assertSame('gd', $mediaConfig->imageProcessingDriver);
        self::assertSame('/opt/ffmpeg', $mediaConfig->ffmpegBinaryPath);
        self::assertSame('/opt/ffprobe', $mediaConfig->ffprobeBinaryPath);
        self::assertSame(600, $mediaConfig->ffmpegTimeoutSeconds);
        self::assertSame(4, $mediaConfig->ffmpegThreads);
    }

    public function testRejectsMultipartThresholdSmallerThanPartSize(): void
    {
        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('media.multipartThresholdBytes');

        new MediaConfig(
            stagingTtlSeconds: 86_400,
            multipartThresholdBytes: 6_291_456,
            multipartPartSizeBytes: 8_388_608,
            imageProcessingDriver: 'imagick',
            ffmpegBinaryPath: '/usr/bin/ffmpeg',
            ffprobeBinaryPath: '/usr/bin/ffprobe',
            ffmpegTimeoutSeconds: 1800,
            ffmpegThreads: 0,
            presignedTtlSeconds: 3600,
        );
    }

    public function testRejectsPresignedTtlAboveUpperBound(): void
    {
        // Срок presigned-ссылки больше 7 суток (604800) отвергается при старте, а не падает 500 на
        // первом построении ссылки для приватного медиа.
        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('media.presignedTtlSeconds');

        new MediaConfig(
            stagingTtlSeconds: 86_400,
            multipartThresholdBytes: 16_777_216,
            multipartPartSizeBytes: 8_388_608,
            imageProcessingDriver: 'imagick',
            ffmpegBinaryPath: '/usr/bin/ffmpeg',
            ffprobeBinaryPath: '/usr/bin/ffprobe',
            ffmpegTimeoutSeconds: 1800,
            ffmpegThreads: 0,
            presignedTtlSeconds: 604_801,
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function mapperFor(array $config): ConfigMapper
    {
        $configurator = $this->createStub(ConfiguratorInterface::class);
        $configurator
            ->method('getConfig')
            ->willReturnMap([[MediaConfig::configName(), $config]]);

        return new ConfigMapper(
            configurator: $configurator,
            mapper: new MapperBuilder()
                ->configureWith(new ConvertKeysToCamelCase())
                ->allowPermissiveTypes()
                ->allowScalarValueCasting()
                ->mapper(),
            normalizer: new NormalizerBuilder()->normalizer(Format::array()),
        );
    }
}

exec
/bin/zsh -lc "sed -n '1,220p' tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php && sed -n '1,180p' tests/Feature/Modules/User/Application/UserApplicationTestCase.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Dto\MediaAudioConversionSpec;
use App\Modules\Media\Application\Dto\MediaConversionPlan;
use App\Modules\Media\Application\Dto\MediaImageConversionSpec;
use App\Modules\Media\Application\Dto\MediaVideoConversionSpec;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\Media\Repository\MediaAudioConversionRepository;
use App\Modules\Media\Repository\MediaImageConversionRepository;
use App\Modules\Media\Repository\MediaMultipartUploadRepository;
use App\Modules\Media\Repository\MediaRepository;
use App\Modules\Media\Repository\MediaVideoConversionRepository;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORMInterface;
use Tests\TestCase;

abstract class MediaApplicationTestCase extends TestCase
{
    protected function createMedia(
        UserId|null $userId = null,
        MediaVisibility $visibility = MediaVisibility::Private,
        MediaType $type = MediaType::Image,
        MediaFileSize|null $size = null,
        string $extension = 'jpg',
        string $mimeType = 'image/jpeg',
    ): Media {
        $storageKey = MediaStorageKey::generate();

        return Media::create(
            storageKey: $storageKey,
            type: $type,
            visibility: $visibility,
            path: MediaPath::originalUpload(storageKey: $storageKey, extension: $extension),
            mimeType: MediaMimeType::fromString($mimeType),
            size: $size ?? MediaFileSize::fromInt(1024),
            uploadedById: $userId ?? UserId::generate(),
            expiration: MediaExpiration::temporaryUntil(new \DateTimeImmutable('+1 hour')),
        );
    }

    protected function readyMedia(MediaVisibility $visibility): Media
    {
        $media = $this->createMedia(userId: UserId::generate(), visibility: $visibility);
        $media->markUploaded();
        $targetStorage = $visibility === MediaVisibility::Public ? MediaStorage::Public : MediaStorage::Private;
        $media->markReadyMovedTo(
            $targetStorage,
            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
        );

        return $media;
    }

    protected function thumbnailConversion(Media $media): MediaImageConversion
    {
        return MediaImageConversion::create(
            media: $media,
            type: MediaImageConversionType::Thumbnail,
            status: MediaConversionStatus::Ready,
            storage: $media->storage,
            path: MediaPath::imageConversion(
                storageKey: $media->storageKey,
                type: MediaImageConversionType::Thumbnail,
                extension: 'jpg',
            ),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(256),
            width: MediaPixelDimension::fromInt(100),
            height: MediaPixelDimension::fromInt(100),
        );
    }

    protected function persist(object ...$entities): void
    {
        foreach ($entities as $entity) {
            $this->entityManager()->persist($entity);
        }

        $this->entityManager()->run();
    }

    protected function imageConversionSpec(
        MediaImageConversionType $type = MediaImageConversionType::Thumbnail,
        int $width = 100,
        int $height = 100,
    ): MediaImageConversionSpec {
        return new MediaImageConversionSpec(type: $type, width: $width, height: $height);
    }

    protected function videoConversionSpec(
        MediaVideoConversionType $type = MediaVideoConversionType::NormalizedMp4H264,
        int $width = 1280,
        int $height = 720,
        int $videoBitrate = 1_000_000,
        int $audioBitrate = 128_000,
    ): MediaVideoConversionSpec {
        return new MediaVideoConversionSpec(
            type: $type,
            width: $width,
            height: $height,
            videoBitrate: $videoBitrate,
            audioBitrate: $audioBitrate,
        );
    }

    protected function audioConversionSpec(
        MediaAudioConversionType $type = MediaAudioConversionType::NormalizedAacM4a,
        int $bitrate = 128_000,
        int $sampleRate = 44_100,
        int $waveformPeaks = 64,
    ): MediaAudioConversionSpec {
        return new MediaAudioConversionSpec(
            type: $type,
            bitrate: $bitrate,
            sampleRate: $sampleRate,
            waveformPeaks: $waveformPeaks,
        );
    }

    protected function emptyPlan(): MediaConversionPlan
    {
        return new MediaConversionPlan(image: [], video: [], audio: []);
    }

    protected function imagePlan(MediaImageConversionSpec ...$specs): MediaConversionPlan
    {
        return new MediaConversionPlan(image: \array_values($specs), video: [], audio: []);
    }

    protected function videoPlan(MediaVideoConversionSpec ...$specs): MediaConversionPlan
    {
        return new MediaConversionPlan(image: [], video: \array_values($specs), audio: []);
    }

    protected function audioPlan(MediaAudioConversionSpec ...$specs): MediaConversionPlan
    {
        return new MediaConversionPlan(image: [], video: [], audio: \array_values($specs));
    }

    protected function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * Сбрасывает ORM identity map, чтобы последующая выборка читала сущности из БД заново (с eager-load
     * связей), а не возвращала закэшированный после persist экземпляр с незагруженными связями.
     */
    protected function cleanOrmHeap(): void
    {
        $this->getContainer()->get(EntityManagerInterface::class)->clean();
        $this->getContainer()->get(ORMInterface::class)->getHeap()->clean();
    }

    protected function mediaRepository(): MediaRepository
    {
        return $this->getContainer()->get(MediaRepository::class);
    }

    protected function multipartUploadRepository(): MediaMultipartUploadRepository
    {
        return $this->getContainer()->get(MediaMultipartUploadRepository::class);
    }

    protected function imageConversionRepository(): MediaImageConversionRepository
    {
        return $this->getContainer()->get(MediaImageConversionRepository::class);
    }

    protected function videoConversionRepository(): MediaVideoConversionRepository
    {
        return $this->getContainer()->get(MediaVideoConversionRepository::class);
    }

    protected function audioConversionRepository(): MediaAudioConversionRepository
    {
        return $this->getContainer()->get(MediaAudioConversionRepository::class);
    }
}
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\User\Application;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Query\FindMediaOriginalUrl\FindMediaOriginalUrlHandler;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\Media\Infrastructure\FileService\MediaUrlService;
use App\Modules\Media\Repository\MediaRepository;
use App\Modules\User\Application\Profile\UserPublicProfileAssembler;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserAvatar;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Repository\UserRepository;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
use App\Shared\Infrastructure\Configuration\User\UserConfig;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\QueryBusInterface;
use Tests\DatabaseTestCase;

/**
 * Общая основа feature-тестов публичного профиля: создаёт пользователей и медиа, собирает
 * UserPublicProfileAssembler со стабом файлового сервиса (URL предсказуем, без обращения к S3).
 */
abstract class UserApplicationTestCase extends DatabaseTestCase
{
    protected const string STUBBED_AVATAR_URL = 'https://cdn.example/real-avatar.jpg';

    private int $userCounter = 0;

    protected function persistUser(UserAvatar|null $avatar = null, Locale $locale = Locale::Ru): User
    {
        $this->userCounter++;

        $user = User::create(
            name: UserName::fromString('Йога Тест'),
            email: Email::fromString(\sprintf('profile.user%d@example.com', $this->userCounter)),
            nickname: UserNickname::fromString(\sprintf('profile.user%d', $this->userCounter)),
            locale: $locale,
        );

        if ($avatar !== null && !$avatar->isEmpty()) {
            $user->setAvatar($avatar);
        }

        $this->persist($user);

        return $user;
    }

    protected function persistReadyPublicMedia(): Media
    {
        $media = $this->createMedia(MediaVisibility::Public);
        $media->markUploaded();
        $media->markReadyMovedTo(
            MediaStorage::Public,
            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
        );
        $this->persist($media);

        return $media;
    }

    protected function persistNotReadyMedia(): Media
    {
        $media = $this->createMedia(MediaVisibility::Public);
        $this->persist($media);

        return $media;
    }

    protected function persistReadyOriginalRemovedMedia(): Media
    {
        $media = $this->createMedia(MediaVisibility::Public);
        $media->markUploaded();
        $media->markReadyMovedTo(
            MediaStorage::Public,
            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
        );
        $media->markReadyOriginalRemoved();
        $this->persist($media);

        return $media;
    }

    protected function profileHandlerAssembler(): UserPublicProfileAssembler
    {
        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturn(self::STUBBED_AVATAR_URL);

        return new UserPublicProfileAssembler(
            queryBus: $this->getContainer()->get(QueryBusInterface::class),
            findMediaOriginalUrlHandler: new FindMediaOriginalUrlHandler(
                mediaRepository: $this->getContainer()->get(MediaRepository::class),
                mediaUrlService: new MediaUrlService(
                    mediaFileService: $fileService,
                    mediaConfig: $this->getContainer()->get(MediaConfig::class),
                ),
            ),
            defaultAvatarUrl: $this->getContainer()->get(UserConfig::class)->defaultAvatarUrl,
        );
    }

    protected function defaultAvatarUrl(): string
    {
        return $this->getContainer()->get(UserConfig::class)->defaultAvatarUrl;
    }

    protected function userRepository(): UserRepository
    {
        return $this->getContainer()->get(UserRepository::class);
    }

    protected function persist(object $entity): void
    {
        $this->entityManager()->persist($entity);
        $this->entityManager()->run();
    }

    protected function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    private function createMedia(MediaVisibility $visibility): Media
    {
        $storageKey = MediaStorageKey::generate();

        return Media::create(
            storageKey: $storageKey,
            type: MediaType::Image,
            visibility: $visibility,
            path: MediaPath::originalUpload(storageKey: $storageKey, extension: 'jpg'),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(1024),
            uploadedById: UserId::generate(),
            expiration: MediaExpiration::temporaryUntil(new \DateTimeImmutable('+1 hour')),
        );
    }
}

codex
Промежуточный вывод: основные ветки URL для `readyOriginalRemoved`, аватара и постов закрыты тестами. Самый подозрительный участок сейчас не в резолверах, а в том, какие именно конверсии попадают в ответ и как это связано со статусом конверсии.
exec
/bin/zsh -lc "sed -n '1,260p' app/src/Modules/Media/Domain/Entity/MediaImageConversion.php && sed -n '1,280p' app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php && sed -n '1,280p' app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php && sed -n '1,120p' app/src/Modules/Media/Domain/Enum/MediaConversionStatus.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Entity;

use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaImageConversionId;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Shared\Infrastructure\Cycle\ValueObjectCast;
use App\Modules\Media\Repository\MediaImageConversionRepository;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\BelongsTo;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'media_image_conversion',
    table: 'media_image_conversions',
    repository: MediaImageConversionRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class MediaImageConversion
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: MediaImageConversionId::class)]
    public private(set) MediaImageConversionId $id;

    #[Column(type: 'uuid', name: 'media_id', typecast: MediaId::class)]
    public private(set) MediaId $mediaId;

    #[Column(type: 'string(64)', typecast: MediaImageConversionType::class)]
    public private(set) MediaImageConversionType $type;

    #[Column(type: 'string(32)', typecast: MediaConversionStatus::class)]
    public private(set) MediaConversionStatus $status;

    #[Column(type: 'string(64)', typecast: MediaStorage::class)]
    public private(set) MediaStorage $storage;

    #[Column(type: 'string(1024)', typecast: MediaPath::class)]
    public private(set) MediaPath $path;

    #[Column(type: 'string(255)', name: 'mime_type', typecast: MediaMimeType::class)]
    public private(set) MediaMimeType $mimeType;

    #[Column(type: 'bigInteger', typecast: MediaFileSize::class)]
    public private(set) MediaFileSize $size;

    #[Column(type: 'integer', typecast: MediaPixelDimension::class)]
    public private(set) MediaPixelDimension $width;

    #[Column(type: 'integer', typecast: MediaPixelDimension::class)]
    public private(set) MediaPixelDimension $height;

    #[BelongsTo(target: Media::class, innerKey: 'media_id', outerKey: 'id', fkOnDelete: 'CASCADE')]
    public private(set) Media $media;

    public static function create(
        Media $media,
        MediaImageConversionType $type,
        MediaConversionStatus $status,
        MediaStorage $storage,
        MediaPath $path,
        MediaMimeType $mimeType,
        MediaFileSize $size,
        MediaPixelDimension $width,
        MediaPixelDimension $height,
    ): self {
        $conversion = new self();
        $conversion->id = MediaImageConversionId::generate();
        $conversion->media = $media;
        $conversion->mediaId = $media->id;
        $conversion->type = $type;
        $conversion->status = $status;
        $conversion->storage = $storage;
        $conversion->path = $path;
        $conversion->mimeType = $mimeType;
        $conversion->size = $size;
        $conversion->width = $width;
        $conversion->height = $height;
        $conversion->initializeTimestamps();

        return $conversion;
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Entity;

use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaDuration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Domain\ValueObject\MediaVideoConversionId;
use App\Shared\Infrastructure\Cycle\ValueObjectCast;
use App\Modules\Media\Repository\MediaVideoConversionRepository;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\BelongsTo;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'media_video_conversion',
    table: 'media_video_conversions',
    repository: MediaVideoConversionRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class MediaVideoConversion
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: MediaVideoConversionId::class)]
    public private(set) MediaVideoConversionId $id;

    #[Column(type: 'uuid', name: 'media_id', typecast: MediaId::class)]
    public private(set) MediaId $mediaId;

    #[Column(type: 'string(64)', typecast: MediaVideoConversionType::class)]
    public private(set) MediaVideoConversionType $type;

    #[Column(type: 'string(32)', typecast: MediaConversionStatus::class)]
    public private(set) MediaConversionStatus $status;

    #[Column(type: 'string(64)', typecast: MediaStorage::class)]
    public private(set) MediaStorage $storage;

    #[Column(type: 'string(1024)', typecast: MediaPath::class)]
    public private(set) MediaPath $path;

    #[Column(type: 'string(255)', name: 'mime_type', typecast: MediaMimeType::class)]
    public private(set) MediaMimeType $mimeType;

    #[Column(type: 'bigInteger', typecast: MediaFileSize::class)]
    public private(set) MediaFileSize $size;

    #[Column(type: 'integer', typecast: MediaPixelDimension::class)]
    public private(set) MediaPixelDimension $width;

    #[Column(type: 'integer', typecast: MediaPixelDimension::class)]
    public private(set) MediaPixelDimension $height;

    #[Column(type: 'bigInteger', name: 'duration_ms', typecast: MediaDuration::class)]
    public private(set) MediaDuration $duration;

    #[Column(type: 'integer', typecast: MediaBitrate::class)]
    public private(set) MediaBitrate $bitrate;

    #[BelongsTo(target: Media::class, innerKey: 'media_id', outerKey: 'id', fkOnDelete: 'CASCADE')]
    public private(set) Media $media;

    public static function create(
        Media $media,
        MediaVideoConversionType $type,
        MediaConversionStatus $status,
        MediaStorage $storage,
        MediaPath $path,
        MediaMimeType $mimeType,
        MediaFileSize $size,
        MediaPixelDimension $width,
        MediaPixelDimension $height,
        MediaDuration $duration,
        MediaBitrate $bitrate,
    ): self {
        $conversion = new self();
        $conversion->id = MediaVideoConversionId::generate();
        $conversion->media = $media;
        $conversion->mediaId = $media->id;
        $conversion->type = $type;
        $conversion->status = $status;
        $conversion->storage = $storage;
        $conversion->path = $path;
        $conversion->mimeType = $mimeType;
        $conversion->size = $size;
        $conversion->width = $width;
        $conversion->height = $height;
        $conversion->duration = $duration;
        $conversion->bitrate = $bitrate;
        $conversion->initializeTimestamps();

        return $conversion;
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Entity;

use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Modules\Media\Domain\ValueObject\MediaAudioConversionId;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaDuration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
use App\Modules\Media\Domain\ValueObject\MediaWaveform;
use App\Modules\Media\Infrastructure\Cycle\MediaWaveformTypecast;
use App\Shared\Infrastructure\Cycle\ValueObjectCast;
use App\Modules\Media\Repository\MediaAudioConversionRepository;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\BelongsTo;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'media_audio_conversion',
    table: 'media_audio_conversions',
    repository: MediaAudioConversionRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class MediaAudioConversion
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: MediaAudioConversionId::class)]
    public private(set) MediaAudioConversionId $id;

    #[Column(type: 'uuid', name: 'media_id', typecast: MediaId::class)]
    public private(set) MediaId $mediaId;

    #[Column(type: 'string(64)', typecast: MediaAudioConversionType::class)]
    public private(set) MediaAudioConversionType $type;

    #[Column(type: 'string(32)', typecast: MediaConversionStatus::class)]
    public private(set) MediaConversionStatus $status;

    #[Column(type: 'string(64)', typecast: MediaStorage::class)]
    public private(set) MediaStorage $storage;

    #[Column(type: 'string(1024)', typecast: MediaPath::class)]
    public private(set) MediaPath $path;

    #[Column(type: 'string(255)', name: 'mime_type', typecast: MediaMimeType::class)]
    public private(set) MediaMimeType $mimeType;

    #[Column(type: 'bigInteger', typecast: MediaFileSize::class)]
    public private(set) MediaFileSize $size;

    #[Column(type: 'bigInteger', name: 'duration_ms', typecast: MediaDuration::class)]
    public private(set) MediaDuration $duration;

    #[Column(type: 'integer', typecast: MediaBitrate::class)]
    public private(set) MediaBitrate $bitrate;

    #[Column(type: 'integer', name: 'sample_rate', typecast: MediaSampleRate::class)]
    public private(set) MediaSampleRate $sampleRate;

    #[Column(type: 'json', typecast: MediaWaveformTypecast::class)]
    public private(set) MediaWaveform $waveform;

    #[BelongsTo(target: Media::class, innerKey: 'media_id', outerKey: 'id', fkOnDelete: 'CASCADE')]
    public private(set) Media $media;

    public static function create(
        Media $media,
        MediaAudioConversionType $type,
        MediaConversionStatus $status,
        MediaStorage $storage,
        MediaPath $path,
        MediaMimeType $mimeType,
        MediaFileSize $size,
        MediaDuration $duration,
        MediaBitrate $bitrate,
        MediaSampleRate $sampleRate,
        MediaWaveform $waveform,
    ): self {
        $conversion = new self();
        $conversion->id = MediaAudioConversionId::generate();
        $conversion->media = $media;
        $conversion->mediaId = $media->id;
        $conversion->type = $type;
        $conversion->status = $status;
        $conversion->storage = $storage;
        $conversion->path = $path;
        $conversion->mimeType = $mimeType;
        $conversion->size = $size;
        $conversion->duration = $duration;
        $conversion->bitrate = $bitrate;
        $conversion->sampleRate = $sampleRate;
        $conversion->waveform = $waveform;
        $conversion->initializeTimestamps();

        return $conversion;
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Enum;

enum MediaConversionStatus: string
{
    case Processing = 'processing';
    case Ready = 'ready';
    case ProcessingFailed = 'processingFailed';
}

exec
/bin/zsh -lc "rg -n \"findByIdWithConversions|findByMediaId\\(|imageConversions|videoConversions|audioConversions\" app/src/Modules/Media app/src/Modules/Posts tests -S" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc 'rg -n "MediaConversionStatus::(Pending|Processing|Failed|Ready)|conversion.*status|->status" app/src/Modules/Media tests/Feature/Modules/Media tests/Unit/Modules/Media -S' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/Media/Application/Query/GetAudioWaveform/GetAudioWaveformHandler.php:42:        $conversion = $this->mediaAudioConversionRepository->findByMediaId($media->id)->first()
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:32:        $media = $this->mediaRepository->findByIdWithConversions(MediaId::fromString($query->mediaId));
tests/Kernel/Shared/Infrastructure/Cycle/LazyGhostMapperExtractTest.php:44:        self::assertArrayHasKey('imageConversions', $extracted);
tests/Kernel/Shared/Infrastructure/Cycle/LazyGhostMapperExtractTest.php:45:        self::assertArrayHasKey('videoConversions', $extracted);
tests/Kernel/Shared/Infrastructure/Cycle/LazyGhostEntityFactoryTest.php:36:        $result = $factory->upgrade(relMap: $relMap, entity: $media, data: ['imageConversions' => $reference]);
tests/Kernel/Shared/Infrastructure/Cycle/LazyGhostEntityFactoryTest.php:39:        self::assertCount(0, $media->imageConversions);
tests/Kernel/Shared/Infrastructure/Cycle/LazyGhostEntityFactoryTest.php:51:            data: ['imageConversions' => new MediaImageConversionCollection()],
tests/Kernel/Shared/Infrastructure/Cycle/LazyGhostEntityFactoryTest.php:54:        self::assertCount(0, $media->imageConversions);
tests/Kernel/Shared/Infrastructure/Cycle/LazyGhostEntityFactoryTest.php:63:        $result = $factory->upgrade(relMap: $relMap, entity: $entity, data: ['imageConversions' => 'whatever']);
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:79:            'imageConversions' => \count($command->plan->image),
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:80:            'videoConversions' => \count($command->plan->video),
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:81:            'audioConversions' => \count($command->plan->audio),
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:89:        $multipartUpload = $this->mediaMultipartUploadRepository->findByMediaId($media->id)
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:127:            'imageConversions' => $this->countByClass(conversions: $conversions, conversionClass: MediaImageConversion::class),
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:128:            'videoConversions' => $this->countByClass(conversions: $conversions, conversionClass: MediaVideoConversion::class),
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:129:            'audioConversions' => $this->countByClass(conversions: $conversions, conversionClass: MediaAudioConversion::class),
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:68:        $conversions = $this->imageConversionRepository()->findByMediaId($media->id);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:88:        self::assertCount(1, $this->imageConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:115:        $conversions = $this->imageConversionRepository()->findByMediaId($media->id);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:150:        $videoConversion = $this->videoConversionRepository()->findByMediaId($media->id)->first();
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:168:        self::assertCount(1, $this->imageConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:190:        $audioConversion = $this->audioConversionRepository()->findByMediaId($media->id)->first();
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:225:        self::assertCount(0, $this->imageConversionRepository()->findByMediaId($media->id));
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:68:        foreach ($this->mediaImageConversionRepository->findByMediaId($media->id) as $imageConversion) {
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:72:        foreach ($this->mediaVideoConversionRepository->findByMediaId($media->id) as $videoConversion) {
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:78:        foreach ($this->mediaAudioConversionRepository->findByMediaId($media->id) as $audioConversion) {
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:85:        $multipartUpload = $this->mediaMultipartUploadRepository->findByMediaId($media->id);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:56:        $conversions = $this->imageConversionRepository()->findByMediaId($media->id);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:75:        $conversion = $this->imageConversionRepository()->findByMediaId($media->id)->first();
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:97:        self::assertCount(0, $this->imageConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:128:        $videoConversion = $this->videoConversionRepository()->findByMediaId($media->id)->first();
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:133:        $poster = $this->imageConversionRepository()->findByMediaId($media->id)->first();
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:165:        $audioConversion = $this->audioConversionRepository()->findByMediaId($media->id)->first();
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:198:        self::assertCount(1, $this->videoConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:227:        $audioConversions = $this->audioConversionRepository()->findByMediaId($media->id);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:228:        self::assertCount(1, $audioConversions);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:229:        self::assertSame(44_100, $audioConversions->first()->sampleRate->value());
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:230:        self::assertSame([0, 64, 128, 255], $audioConversions->first()->waveform->peaks());
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:310:        self::assertCount(0, $this->imageConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:133:        self::assertNotNull($this->multipartUploadRepository()->findByMediaId(MediaId::fromString($result->mediaId)));
app/src/Modules/Posts/Repository/PostMediaRepository.php:30:                ->load('media.imageConversions')
app/src/Modules/Posts/Repository/PostMediaRepository.php:31:                ->load('media.videoConversions')
app/src/Modules/Posts/Repository/PostMediaRepository.php:32:                ->load('media.audioConversions')
app/src/Modules/Posts/Repository/PostMediaRepository.php:54:                ->load('media.imageConversions')
app/src/Modules/Posts/Repository/PostMediaRepository.php:55:                ->load('media.videoConversions')
app/src/Modules/Posts/Repository/PostMediaRepository.php:56:                ->load('media.audioConversions')
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:65:        self::assertCount(1, $this->imageConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:91:        self::assertCount(1, $this->videoConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:92:        self::assertCount(1, $this->imageConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:119:        self::assertCount(1, $this->audioConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:80:        $conversionPath = $this->imageConversionRepository()->findByMediaId($media->id)->first()?->path;
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:99:        self::assertCount(0, $this->imageConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:106:        $conversionPath = $this->videoConversionRepository()->findByMediaId($media->id)->first()?->path;
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:125:        self::assertCount(0, $this->videoConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:132:        $conversionPath = $this->audioConversionRepository()->findByMediaId($media->id)->first()?->path;
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:151:        self::assertCount(0, $this->audioConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:162:        $conversionPath = $this->imageConversionRepository()->findByMediaId($media->id)->first()?->path;
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:182:        self::assertCount(0, $this->imageConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:84:        $imageConversions = $this->imageConversionRepository()->findByMediaId($media->id);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:85:        $videoConversions = $this->videoConversionRepository()->findByMediaId($media->id);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:86:        $audioConversions = $this->audioConversionRepository()->findByMediaId($media->id);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:87:        $restoredMultipartUpload = $this->multipartUploadRepository()->findByMediaId($media->id);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:89:        self::assertInstanceOf(MediaImageConversionCollection::class, $imageConversions);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:90:        self::assertInstanceOf(MediaVideoConversionCollection::class, $videoConversions);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:91:        self::assertInstanceOf(MediaAudioConversionCollection::class, $audioConversions);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:92:        self::assertCount(1, $imageConversions);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:93:        self::assertCount(1, $videoConversions);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:94:        self::assertCount(1, $audioConversions);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:95:        self::assertInstanceOf(MediaAudioConversion::class, $audioConversions->first());
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:96:        self::assertSame([0, 64, 128, 255], $audioConversions->first()->waveform->peaks());
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:97:        self::assertSame(44_100, $audioConversions->first()->sampleRate->value());
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:118:        $restoredImageConversion = $this->imageConversionRepository()->findByMediaId($mediaId)->first();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:130:        self::assertInstanceOf(MediaImageConversionCollection::class, $restoredMedia->imageConversions);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:131:        self::assertInstanceOf(MediaVideoConversionCollection::class, $restoredMedia->videoConversions);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:132:        self::assertCount(1, $restoredMedia->imageConversions);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:133:        self::assertCount(1, $restoredMedia->videoConversions);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:144:        self::assertCount(1, $savedMedia->imageConversions);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:145:        self::assertCount(1, $savedMedia->videoConversions);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:169:        $savedImageConversion = $this->imageConversionRepository()->findByMediaId($mediaId)->first();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:173:        self::assertInstanceOf(MediaImageConversionCollection::class, $savedMedia->imageConversions);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:174:        self::assertCount(1, $savedMedia->imageConversions);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:191:        $restoredImageConversion = $this->imageConversionRepository()->findByMediaId($mediaId)->first();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:200:        $savedImageConversion = $this->imageConversionRepository()->findByMediaId($mediaId)->first();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:272:        self::assertCount(0, $this->imageConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:273:        self::assertCount(0, $this->videoConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:274:        self::assertCount(0, $this->audioConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:275:        self::assertNull($this->multipartUploadRepository()->findByMediaId($media->id));
app/src/Modules/Media/Repository/MediaMultipartUploadRepository.php:16:    public function findByMediaId(MediaId $mediaId): MediaMultipartUpload|null
app/src/Modules/Media/Repository/MediaRepository.php:28:    public function findByIdWithConversions(MediaId $mediaId): Media|null
app/src/Modules/Media/Repository/MediaRepository.php:32:            ->load('imageConversions')
app/src/Modules/Media/Repository/MediaRepository.php:33:            ->load('videoConversions')
app/src/Modules/Media/Repository/MediaRepository.php:34:            ->load('audioConversions')
app/src/Modules/Media/Repository/MediaVideoConversionRepository.php:17:    public function findByMediaId(MediaId $mediaId): MediaVideoConversionCollection
app/src/Modules/Media/Repository/MediaAudioConversionRepository.php:17:    public function findByMediaId(MediaId $mediaId): MediaAudioConversionCollection
app/src/Modules/Media/Repository/MediaImageConversionRepository.php:17:    public function findByMediaId(MediaId $mediaId): MediaImageConversionCollection
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:30: * конверсии. Конверсии берутся из связей сущности (imageConversions/videoConversions/audioConversions),
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:32: * (репозиторий-метод с ->load(...) или ->load('media.imageConversions') у вызывающего модуля).
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:72:     * значение по умолчанию. Не трогает imageConversions/videoConversions/audioConversions, поэтому
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:112:            conversions: $media->imageConversions->toBase(),
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:117:            conversions: $media->videoConversions->toBase(),
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:122:            conversions: $media->audioConversions->toBase(),
app/src/Modules/Media/Domain/Entity/Media.php:90:    public private(set) MediaImageConversionCollection $imageConversions;
app/src/Modules/Media/Domain/Entity/Media.php:99:    public private(set) MediaVideoConversionCollection $videoConversions;
app/src/Modules/Media/Domain/Entity/Media.php:108:    public private(set) MediaAudioConversionCollection $audioConversions;
app/src/Modules/Media/Domain/Entity/Media.php:134:        $media->imageConversions = new MediaImageConversionCollection();
app/src/Modules/Media/Domain/Entity/Media.php:135:        $media->videoConversions = new MediaVideoConversionCollection();
app/src/Modules/Media/Domain/Entity/Media.php:136:        $media->audioConversions = new MediaAudioConversionCollection();

 succeeded in 0ms:
app/src/Modules/Media/Application/Dto/MediaResult.php:26:            status: $media->status,
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:56:        if ($media->status !== MediaStatus::WaitingUpload) {
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:192:                status: MediaConversionStatus::Ready,
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:235:                status: MediaConversionStatus::Ready,
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:249:                status: MediaConversionStatus::Ready,
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:292:                status: MediaConversionStatus::Ready,
app/src/Modules/Media/Application/Command/MakeMediaPermanent/MakeMediaPermanentHandler.php:36:        if ($media->status !== MediaStatus::Uploaded && $media->status !== MediaStatus::Ready) {
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:46:        if ($media->status === MediaStatus::WaitingUpload) {
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:35:        self::assertSame(MediaStatus::WaitingUpload, $media->status);
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:50:        self::assertSame(MediaStatus::CompletingMultipartUpload, $media->status);
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:53:        self::assertSame(MediaStatus::MultipartCompletionFailedCanRetry, $media->status);
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:57:        self::assertSame(MediaStatus::MultipartCompletionFailedNeedReupload, $media->status);
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:65:        self::assertSame(MediaStatus::Uploaded, $media->status);
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:68:        self::assertSame(MediaStatus::Processing, $media->status);
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:71:        self::assertSame(MediaStatus::ProcessingFailed, $media->status);
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:76:        self::assertSame(MediaStatus::Ready, $media->status);
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:80:        self::assertSame(MediaStatus::ReadyOriginalRemoved, $media->status);
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:92:        self::assertSame(MediaStatus::Ready, $media->status);
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:97:        self::assertSame(MediaStatus::Ready, $media->status);
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:145:            self::assertSame($statusValue, $media->status->value);
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:164:        self::assertSame(MediaStatus::ReadyOriginalRemoved, $media->status);
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:169:        self::assertSame(MediaStatus::ReadyOriginalRemoved, $media->status);
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:181:        self::assertSame(MediaStatus::ProcessingFailed, $media->status);
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:207:        self::assertSame(MediaStatus::Ready, $media->status);
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:222:        self::assertSame(MediaStatus::ProcessingFailed, $media->status);
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:255:        self::assertSame(MediaStatus::ReadyOriginalRemoved, $media->status);
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:265:            status: MediaConversionStatus::Ready,
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:281:        self::assertSame(MediaConversionStatus::Ready, $audioConversion->status);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:67:        self::assertSame(MediaStatus::Uploaded, $result->status);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:68:        self::assertSame(MediaStatus::Uploaded, $media->status);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:92:        self::assertSame(MediaStatus::Uploaded, $result->status);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:116:        self::assertSame(MediaStatus::Uploaded, $result->status);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:140:        self::assertSame(MediaStatus::Uploaded, $result->status);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:374:        self::assertSame(MediaStatus::Uploaded, $result->status);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:375:        self::assertSame(MediaStatus::Uploaded, $media->status);
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php:123:            status: MediaConversionStatus::Ready,
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:279:            status: MediaConversionStatus::Ready,
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:300:            status: MediaConversionStatus::Ready,
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:277:        self::assertSame(MediaStatus::ReadyOriginalRemoved, $media->status);
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php:95:        $conversion->status = $status;
app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php:93:        $conversion->status = $status;
app/src/Modules/Media/Domain/Entity/MediaImageConversion.php:83:        $conversion->status = $status;
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:62:        self::assertSame(MediaStatus::ReadyOriginalRemoved, $media->status);
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:63:        self::assertSame(MediaStatus::ReadyOriginalRemoved, $result->status);
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:90:        self::assertSame(MediaStatus::ReadyOriginalRemoved, $media->status);
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:118:        self::assertSame(MediaStatus::ReadyOriginalRemoved, $media->status);
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:217:        self::assertSame(MediaStatus::ReadyOriginalRemoved, $result->status);
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:281:            status: MediaConversionStatus::Ready,
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:309:            status: MediaConversionStatus::Ready,
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:326:            status: MediaConversionStatus::Ready,
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:354:            status: MediaConversionStatus::Ready,
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:253:            status: MediaConversionStatus::Ready,
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:282:            status: MediaConversionStatus::Ready,
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:311:            status: MediaConversionStatus::Ready,
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:77:        self::assertSame(MediaStatus::WaitingUpload, $media->status);
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:202:        self::assertSame(MediaStatus::WaitingUpload, $media->status);
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:31:        self::assertSame(MediaStatus::ProcessingFailed, $media->status);
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:54:        self::assertSame(MediaStatus::Ready, $media->status);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:63:        self::assertSame(MediaStatus::WaitingUpload, $restoredMedia->status);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:143:        self::assertSame(MediaStatus::Ready, $savedMedia->status);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:172:        self::assertSame(MediaStatus::Ready, $savedMedia->status);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:303:            status: MediaConversionStatus::Ready,
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:320:            status: MediaConversionStatus::Ready,
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:339:            status: MediaConversionStatus::Ready,
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php:78:            status: MediaConversionStatus::Ready,
app/src/Modules/Media/Domain/Entity/Media.php:124:        $media->status = MediaStatus::WaitingUpload;
app/src/Modules/Media/Domain/Entity/Media.php:144:        $this->status = MediaStatus::CompletingMultipartUpload;
app/src/Modules/Media/Domain/Entity/Media.php:150:        $this->status = MediaStatus::MultipartCompletionFailedCanRetry;
app/src/Modules/Media/Domain/Entity/Media.php:157:        $this->status = MediaStatus::MultipartCompletionFailedNeedReupload;
app/src/Modules/Media/Domain/Entity/Media.php:164:        $this->status = MediaStatus::Uploaded;
app/src/Modules/Media/Domain/Entity/Media.php:171:        $this->status = MediaStatus::Processing;
app/src/Modules/Media/Domain/Entity/Media.php:208:        $this->status = MediaStatus::ProcessingFailed;
app/src/Modules/Media/Domain/Entity/Media.php:216:        $this->status = MediaStatus::Ready;
app/src/Modules/Media/Domain/Entity/Media.php:228:        if ($this->status === MediaStatus::Ready) {
app/src/Modules/Media/Domain/Entity/Media.php:233:            $this->status !== MediaStatus::Uploaded
app/src/Modules/Media/Domain/Entity/Media.php:234:            && $this->status !== MediaStatus::Processing
app/src/Modules/Media/Domain/Entity/Media.php:235:            && $this->status !== MediaStatus::ProcessingFailed
app/src/Modules/Media/Domain/Entity/Media.php:244:        $this->status = MediaStatus::Ready;
app/src/Modules/Media/Domain/Entity/Media.php:251:        return $this->status === MediaStatus::Ready;
app/src/Modules/Media/Domain/Entity/Media.php:261:        return $this->status === MediaStatus::ReadyOriginalRemoved;
app/src/Modules/Media/Domain/Entity/Media.php:271:        return $this->status === MediaStatus::Ready || $this->status === MediaStatus::ReadyOriginalRemoved;
app/src/Modules/Media/Domain/Entity/Media.php:283:        if ($this->status === MediaStatus::ReadyOriginalRemoved) {
app/src/Modules/Media/Domain/Entity/Media.php:287:        if ($this->status !== MediaStatus::Ready) {
app/src/Modules/Media/Domain/Entity/Media.php:293:        $this->status = MediaStatus::ReadyOriginalRemoved;
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:63:        self::assertSame(MediaStatus::ProcessingFailed, $failedMedia->status);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:65:        self::assertSame(MediaStatus::Ready, $processedMedia->status);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:101:        self::assertSame(MediaStatus::ProcessingFailed, $media->status);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:111:        self::assertSame(MediaStatus::Ready, $readyMedia->status);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:148:        self::assertSame(MediaStatus::Ready, $processedMedia->status);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:188:        self::assertSame(MediaStatus::Ready, $processedMedia->status);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:221:        self::assertSame(MediaStatus::Ready, $processedMedia->status);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:251:        self::assertSame(MediaStatus::ProcessingFailed, $failedMedia->status);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:256:        self::assertSame(OutboxEventStatus::Failed, $outboxEvent->status);

exec
/bin/zsh -lc "rg -n \"markReady\\(|MediaConversionStatus::Ready|start|fail|status\" app/src/Modules/Media/Application app/src/Modules/Media/Infrastructure/FileService -S" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:198:            $start = \intdiv(num1: $peakIndex * $sampleCount, num2: $peaks);
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:201:            // Короткое аудио (waveformPeaks > число сэмплов) даёт пустые интервалы (start === end).
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:204:            if ($end === $start && $sampleCount > 0) {
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:205:                $end = \min($start + 1, $sampleCount);
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:209:            for ($sampleIndex = $start; $sampleIndex < $end; $sampleIndex++) {
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:397:        $statusCode = $exception->getStatusCode();
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:398:        if ($statusCode !== null && $statusCode >= 500) {
app/src/Modules/Media/Application/Service/MediaTypeResolver.php:54:        if (\str_starts_with(haystack: $value, needle: 'image/')) {
app/src/Modules/Media/Application/Service/MediaTypeResolver.php:58:        if (\str_starts_with(haystack: $value, needle: 'video/')) {
app/src/Modules/Media/Application/Service/MediaTypeResolver.php:62:        if (\str_starts_with(haystack: $value, needle: 'audio/')) {
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:56:        if ($media->status !== MediaStatus::WaitingUpload) {
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:192:                status: MediaConversionStatus::Ready,
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:235:                status: MediaConversionStatus::Ready,
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:249:                status: MediaConversionStatus::Ready,
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:292:                status: MediaConversionStatus::Ready,
app/src/Modules/Media/Application/Command/MakeMediaPermanent/MakeMediaPermanentHandler.php:36:        if ($media->status !== MediaStatus::Uploaded && $media->status !== MediaStatus::Ready) {
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:46:        if ($media->status === MediaStatus::WaitingUpload) {
app/src/Modules/Media/Application/Dto/MediaResult.php:18:        public MediaStatus $status,
app/src/Modules/Media/Application/Dto/MediaResult.php:26:            status: $media->status,

exec
/bin/zsh -lc "sed -n '1,140p' app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php && sed -n '1,360p' app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\DeleteMedia;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Repository\MediaAudioConversionRepository;
use App\Modules\Media\Repository\MediaImageConversionRepository;
use App\Modules\Media\Repository\MediaMultipartUploadRepository;
use App\Modules\Media\Repository\MediaRepository;
use App\Modules\Media\Repository\MediaVideoConversionRepository;
use App\Shared\Domain\Exception\ForbiddenException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use Psr\Log\LoggerInterface;

final readonly class DeleteMediaHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaMultipartUploadRepository $mediaMultipartUploadRepository,
        private MediaImageConversionRepository $mediaImageConversionRepository,
        private MediaVideoConversionRepository $mediaVideoConversionRepository,
        private MediaAudioConversionRepository $mediaAudioConversionRepository,
        private MediaFileServiceContract $mediaFileService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[LogOperation]
    public function handle(DeleteMediaCommand $command): void
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($command->mediaId))
            ?? throw new NotFoundException('app.media.not_found');

        if (!$media->uploadedById->equals(UserId::fromString($command->userId))) {
            throw new ForbiddenException('app.media.access_denied');
        }

        if ($media->status === MediaStatus::WaitingUpload) {
            $this->abortActiveMultipartUpload($media);
        }

        // Объекты конверсий лежат в целевом бакете и каскадно удаляются из БД, но не из S3 —
        // удаляем их явно до delete(), иначе остаются осиротевшие файлы. 404 игнорируется сервисом.
        $this->deleteConversionObjects($media);

        // Удаляем текущий оригинал (staging или целевой бакет). 404 игнорируется сервисом.
        $this->mediaFileService->deleteObject(storage: $media->storage, path: $media->path);

        $this->entityManager->delete($media);
        $this->entityManager->run();

        $this->logger->debug(message: 'Медиа удалено.', context: [
            'mediaId' => $command->mediaId,
            'userId' => $command->userId,
        ]);
    }

    private function deleteConversionObjects(Media $media): void
    {
        foreach ($this->mediaImageConversionRepository->findByMediaId($media->id) as $imageConversion) {
            $this->mediaFileService->deleteObject(storage: $imageConversion->storage, path: $imageConversion->path);
        }

        foreach ($this->mediaVideoConversionRepository->findByMediaId($media->id) as $videoConversion) {
            $this->mediaFileService->deleteObject(storage: $videoConversion->storage, path: $videoConversion->path);
        }

        // Постер видео — это MediaImageConversion (Poster), он уже попадёт в image-цикл выше;
        // здесь чистим только аудио-конверсии, чтобы не задваивать постер.
        foreach ($this->mediaAudioConversionRepository->findByMediaId($media->id) as $audioConversion) {
            $this->mediaFileService->deleteObject(storage: $audioConversion->storage, path: $audioConversion->path);
        }
    }

    private function abortActiveMultipartUpload(Media $media): void
    {
        $multipartUpload = $this->mediaMultipartUploadRepository->findByMediaId($media->id);

        if ($multipartUpload === null) {
            return;
        }

        $this->mediaFileService->abortMultipartUpload(
            storage: $media->storage,
            path: $media->path,
            uploadId: $multipartUpload->uploadId,
        );
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\ProcessMedia;

use App\Modules\Media\Application\Contract\MediaAudioProcessorContract;
use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Contract\MediaImageProcessorContract;
use App\Modules\Media\Application\Contract\MediaVideoProcessorContract;
use App\Modules\Media\Application\Dto\MediaConversionPlan;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaAudioConversion;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\Entity\MediaVideoConversion;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Repository\MediaRepository;
use App\Shared\Domain\Exception\NotFoundException;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;

/**
 * Без #[Transactional]: все S3/ffmpeg/Imagick-операции выполняются вне транзакции, затем один
 * атомарный persist+run() с переходом в ready. Идемпотентен: на ready — no-op; конверсии
 * создаются только в финальном flush, поэтому частичного состояния не бывает и повтор
 * пересоздаёт их без конфликта по unique (media_id, type) (процессоры перезаписывают объекты
 * по детерминированным путям).
 */
final readonly class ProcessMediaHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaFileServiceContract $mediaFileService,
        private MediaImageProcessorContract $mediaImageProcessor,
        private MediaVideoProcessorContract $mediaVideoProcessor,
        private MediaAudioProcessorContract $mediaAudioProcessor,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[LogOperation]
    public function handle(ProcessMediaCommand $command): void
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($command->mediaId))
            ?? throw new NotFoundException('app.media.not_found');

        if ($media->isFinalized()) {
            $this->logger->debug(message: 'Обработка медиа пропущена: медиа уже финализировано.', context: [
                'mediaId' => $media->id->value(),
            ]);

            return;
        }

        $this->logger->debug(message: 'Начата обработка медиа.', context: [
            'mediaId' => $media->id->value(),
            'type' => $media->type->value,
        ]);

        $targetStorage = $this->targetStorage($media->visibility);
        $extension = $media->path->extension();

        $conversions = match ($media->type) {
            MediaType::Image => $this->buildImageConversions(
                media: $media,
                plan: $command->plan,
                targetStorage: $targetStorage,
                extension: $extension,
            ),
            MediaType::Video => $this->buildVideoConversions(
                media: $media,
                plan: $command->plan,
                targetStorage: $targetStorage,
            ),
            MediaType::Audio => $this->buildAudioConversions(
                media: $media,
                plan: $command->plan,
                targetStorage: $targetStorage,
            ),
            MediaType::Document => [],
        };

        $this->persistReady(
            media: $media,
            conversions: $conversions,
            targetStorage: $targetStorage,
            extension: $extension,
        );
    }

    /**
     * @param list<MediaImageConversion|MediaVideoConversion|MediaAudioConversion> $conversions
     */
    private function persistReady(
        Media $media,
        array $conversions,
        MediaStorage $targetStorage,
        string $extension,
    ): void {
        $targetPath = MediaPath::originalReady(storageKey: $media->storageKey, type: $media->type, extension: $extension);
        $this->mediaFileService->copyObject(
            fromStorage: $media->storage,
            fromPath: $media->path,
            toStorage: $targetStorage,
            toPath: $targetPath,
        );
        $media->markReadyMovedTo(storage: $targetStorage, path: $targetPath);

        $this->entityManager->persist($media);
        foreach ($conversions as $conversion) {
            $this->entityManager->persist($conversion);
        }
        $this->entityManager->run();

        $this->logger->debug(message: 'Медиа готово.', context: [
            'mediaId' => $media->id->value(),
            'targetStorage' => $targetStorage->value,
            'imageConversions' => $this->countByClass(conversions: $conversions, conversionClass: MediaImageConversion::class),
            'videoConversions' => $this->countByClass(conversions: $conversions, conversionClass: MediaVideoConversion::class),
            'audioConversions' => $this->countByClass(conversions: $conversions, conversionClass: MediaAudioConversion::class),
        ]);
    }

    /**
     * @param list<MediaImageConversion|MediaVideoConversion|MediaAudioConversion> $conversions
     * @param class-string $conversionClass
     */
    private function countByClass(array $conversions, string $conversionClass): int
    {
        return Collection::make($conversions)
            ->filter(static fn(MediaImageConversion|MediaVideoConversion|MediaAudioConversion $conversion): bool
                => $conversion instanceof $conversionClass)
            ->count();
    }

    /**
     * @return list<MediaImageConversion>
     */
    private function buildImageConversions(
        Media $media,
        MediaConversionPlan $plan,
        MediaStorage $targetStorage,
        string $extension,
    ): array {
        if ($plan->image === []) {
            return [];
        }

        $originalContents = $this->mediaFileService->getObjectContents(storage: $media->storage, path: $media->path);

        $conversions = [];

        foreach ($plan->image as $spec) {
            $conversionResult = $this->mediaImageProcessor->resize(
                originalContents: $originalContents,
                width: MediaPixelDimension::fromInt($spec->width),
                height: MediaPixelDimension::fromInt($spec->height),
                targetMimeType: $media->mimeType,
            );
            $conversionPath = MediaPath::imageConversion(
                storageKey: $media->storageKey,
                type: $spec->type,
                extension: $extension,
            );
            $this->mediaFileService->putObject(
                storage: $targetStorage,
                path: $conversionPath,
                contents: $conversionResult->contents,
                mimeType: $conversionResult->mimeType,
            );

            $this->logger->debug(message: 'Создана конверсия изображения.', context: [
                'type' => $spec->type->value,
                'path' => $conversionPath->value(),
                'size' => $conversionResult->size->value(),
            ]);

            // width/height берём из результата процессора (размер реально записанного объекта),
            // а не из spec — устойчиво к смене режима ресайза. См. README.
            $conversions[] = MediaImageConversion::create(
                media: $media,
                type: $spec->type,
                status: MediaConversionStatus::Ready,
                storage: $targetStorage,
                path: $conversionPath,
                mimeType: $conversionResult->mimeType,
                size: $conversionResult->size,
                width: $conversionResult->width,
                height: $conversionResult->height,
            );
        }

        return $conversions;
    }

    /**
     * @return list<MediaVideoConversion|MediaImageConversion>
     */
    private function buildVideoConversions(
        Media $media,
        MediaConversionPlan $plan,
        MediaStorage $targetStorage,
    ): array {
        $conversions = [];

        foreach ($plan->video as $spec) {
            $normalizedPath = MediaPath::videoConversion(storageKey: $media->storageKey, type: $spec->type, extension: 'mp4');
            $posterPath = MediaPath::imageConversion(
                storageKey: $media->storageKey,
                type: MediaImageConversionType::Poster,
                extension: 'jpg',
            );

            $result = $this->mediaVideoProcessor->process(
                sourceStorage: $media->storage,
                sourcePath: $media->path,
                spec: $spec,
                targetStorage: $targetStorage,
                normalizedPath: $normalizedPath,
                posterPath: $posterPath,
            );

            $conversions[] = MediaVideoConversion::create(
                media: $media,
                type: $spec->type,
                status: MediaConversionStatus::Ready,
                storage: $targetStorage,
                path: $normalizedPath,
                mimeType: $result->normalizedMimeType,
                size: $result->normalizedSize,
                width: $result->width,
                height: $result->height,
                duration: $result->duration,
                bitrate: $result->bitrate,
            );
            // Постер видео — MediaImageConversion type=Poster размером транскода (width/height).
            $conversions[] = MediaImageConversion::create(
                media: $media,
                type: MediaImageConversionType::Poster,
                status: MediaConversionStatus::Ready,
                storage: $targetStorage,
                path: $posterPath,
                mimeType: $result->posterMimeType,
                size: $result->posterSize,
                width: $result->width,
                height: $result->height,
            );

            $this->logger->debug(message: 'Создана конверсия видео и постер.', context: [
                'type' => $spec->type->value,
                'path' => $normalizedPath->value(),
                'size' => $result->normalizedSize->value(),
            ]);
        }

        return $conversions;
    }

    /**
     * @return list<MediaAudioConversion>
     */
    private function buildAudioConversions(
        Media $media,
        MediaConversionPlan $plan,
        MediaStorage $targetStorage,
    ): array {
        $conversions = [];

        foreach ($plan->audio as $spec) {
            $normalizedPath = MediaPath::audioConversion(storageKey: $media->storageKey, type: $spec->type, extension: 'm4a');

            $result = $this->mediaAudioProcessor->process(
                sourceStorage: $media->storage,
                sourcePath: $media->path,
                spec: $spec,
                targetStorage: $targetStorage,
                normalizedPath: $normalizedPath,
            );

            $conversions[] = MediaAudioConversion::create(
                media: $media,
                type: $spec->type,
                status: MediaConversionStatus::Ready,
                storage: $targetStorage,
                path: $normalizedPath,
                mimeType: $result->normalizedMimeType,
                size: $result->normalizedSize,
                duration: $result->duration,
                bitrate: $result->bitrate,
                sampleRate: $result->sampleRate,
                waveform: $result->waveform,
            );

            $this->logger->debug(message: 'Создана конверсия аудио с волной.', context: [
                'type' => $spec->type->value,
                'path' => $normalizedPath->value(),
                'size' => $result->normalizedSize->value(),
            ]);
        }

        return $conversions;
    }

    private function targetStorage(MediaVisibility $visibility): MediaStorage
    {
        return match ($visibility) {
            MediaVisibility::Public => MediaStorage::Public,
            MediaVisibility::Private => MediaStorage::Private,
        };
    }
}

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Media/Application/Query/GetAudioWaveform/GetAudioWaveformHandler.php && sed -n '1,140p' app/src/Modules/Media/Application/Command/MakeMediaPermanent/MakeMediaPermanentHandler.php && sed -n '1,140p' app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableHandler.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\GetAudioWaveform;

use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaWaveform;
use App\Modules\Media\Repository\MediaAudioConversionRepository;
use App\Modules\Media\Repository\MediaRepository;
use App\Shared\Domain\Exception\NotFoundException;

/**
 * Возвращает волну амплитуд нормализованной аудио-конверсии (числа для прогресса воспроизведения).
 * Волна не отдаётся через URL — это отдельный запрос. Медиа не финализировано (не ready и не
 * readyOriginalRemoved) / не аудио / без конверсии — 404.
 */
final readonly class GetAudioWaveformHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaAudioConversionRepository $mediaAudioConversionRepository,
    ) {}

    public function handle(GetAudioWaveformQuery $query): MediaWaveform
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($query->mediaId))
            ?? throw new NotFoundException('app.media.not_found');

        // Волна — это конверсия, поэтому переживает удаление оригинала (ready и readyOriginalRemoved).
        if (!$media->isFinalized()) {
            throw new NotFoundException('app.media.not_ready');
        }

        if ($media->type !== MediaType::Audio) {
            throw new NotFoundException('app.media.conversion_not_found');
        }

        // Каталог аудио-типов содержит ровно один профиль (NormalizedAacM4a) и на медиа не больше
        // одной такой конверсии, поэтому берём первую без фильтра по единственному типу.
        $conversion = $this->mediaAudioConversionRepository->findByMediaId($media->id)->first()
            ?? throw new NotFoundException('app.media.conversion_not_found');

        return $conversion->waveform;
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\MakeMediaPermanent;

use App\Modules\Media\Application\Dto\MediaResult;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Repository\MediaRepository;
use App\Shared\Domain\Exception\ForbiddenException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class MakeMediaPermanentHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    public function handle(MakeMediaPermanentCommand $command): MediaResult
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($command->mediaId))
            ?? throw new NotFoundException('app.media.not_found');

        if (!$media->uploadedById->equals(UserId::fromString($command->userId))) {
            throw new ForbiddenException('app.media.access_denied');
        }

        // Статус-guard на Application-границе: домен makePermanent() без guard (бросил бы 500).
        if ($media->status !== MediaStatus::Uploaded && $media->status !== MediaStatus::Ready) {
            throw new ValidationException('app.media.cannot_make_permanent');
        }

        $media->makePermanent();
        $this->entityManager->persist($media);
        $this->entityManager->run();

        $this->logger->debug(message: 'Медиа помечено постоянным.', context: [
            'mediaId' => $media->id->value(),
            'userId' => $command->userId,
        ]);

        return MediaResult::fromEntity($media);
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\CheckMediaAttachable;

use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Repository\MediaRepository;
use App\Shared\Domain\Exception\ForbiddenException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Проверяет, можно ли вложить медиа в запись: оно должно существовать (иначе 404), принадлежать
 * владельцу (иначе 403) и быть обработанным/готовым (иначе 422). Вызывается до MakeMediaPermanent,
 * поэтому тот всегда получает готовое медиа.
 */
final readonly class CheckMediaAttachableHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
    ) {}

    #[LogOperation]
    public function handle(CheckMediaAttachableQuery $query): MediaAttachableResult
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($query->mediaId))
            ?? throw new NotFoundException('app.media.not_found');

        if (!$media->uploadedById->equals(UserId::fromString($query->ownerUserId))) {
            throw new ForbiddenException('app.media.access_denied');
        }

        if (!$media->isReady()) {
            throw new ValidationException('app.media.not_ready');
        }

        return new MediaAttachableResult(mediaId: $media->id->value());
    }
}

exec
/bin/zsh -lc 'rg -n "FindMediaUrlQuery|FindMediaOriginalUrlQuery|RemoveMediaOriginalCommand|RemoveMediaOriginalHandler|MediaUrlsResult|MediaConversionUrl" app/src tests -S' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:8:use App\Modules\Media\Application\Dto\MediaConversionUrl;
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:9:use App\Modules\Media\Application\Dto\MediaConversionUrlCollection;
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:11:use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlQuery;
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:40:            new FindMediaUrlQuery(mediaId: UserId::generate()->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:54:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:76:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:118:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:156:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:178:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:201:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 0),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:225:            new FindMediaUrlQuery(mediaId: $media->id->value()),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:249:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 0),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:265:        MediaConversionUrlCollection $conversions,
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:267:    ): MediaConversionUrl {
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:9:use App\Modules\Media\Application\Query\FindMediaOriginalUrl\FindMediaOriginalUrlQuery;
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:22:            new FindMediaOriginalUrlQuery(mediaId: UserId::generate()->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:35:            new FindMediaOriginalUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:56:            new FindMediaOriginalUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:78:            new FindMediaOriginalUrlQuery(mediaId: $media->id->value()),
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:103:            new FindMediaOriginalUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:129:            new FindMediaOriginalUrlQuery(mediaId: $media->id->value()),
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:9:use App\Modules\Media\Application\Dto\MediaConversionUrl;
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:10:use App\Modules\Media\Application\Dto\MediaConversionUrlCollection;
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:12:use App\Modules\Media\Application\Dto\MediaUrlsResult;
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:52:    public function getUrls(Media $media, int|null $presignedTtlSeconds = null): MediaUrlsResult|null
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:63:        return new MediaUrlsResult(
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:109:    private function conversionUrls(Media $media, MediaUrlResolver $resolver): MediaConversionUrlCollection
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:127:        return new MediaConversionUrlCollection($imageUrls->concat($videoUrls)->concat($audioUrls));
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:135:     * @return Collection<int, MediaConversionUrl>
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:143:            fn(MediaImageConversion|MediaVideoConversion|MediaAudioConversion $conversion): MediaConversionUrl
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:160:    ): MediaConversionUrl {
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:163:        return new MediaConversionUrl(kind: $kind, type: $type, url: $resolved->url, expiresAt: $resolved->expiresAt);
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:7:use App\Modules\Media\Application\Command\RemoveMediaOriginal\RemoveMediaOriginalCommand;
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:8:use App\Modules\Media\Application\Command\RemoveMediaOriginal\RemoveMediaOriginalHandler;
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:38:final class RemoveMediaOriginalHandlerTest extends MediaApplicationTestCase
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:55:        $result = $this->handler($fileService)->handle(new RemoveMediaOriginalCommand(
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:83:        $this->handler($fileService)->handle(new RemoveMediaOriginalCommand(
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:110:        $this->handler($fileService)->handle(new RemoveMediaOriginalCommand(
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:127:        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new RemoveMediaOriginalCommand(
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:140:        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new RemoveMediaOriginalCommand(
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:156:        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new RemoveMediaOriginalCommand(
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:175:        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new RemoveMediaOriginalCommand(
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:194:        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new RemoveMediaOriginalCommand(
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:212:        $result = $this->handler($fileService)->handle(new RemoveMediaOriginalCommand(
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:245:            new RemoveMediaOriginalCommand(
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:255:    ): RemoveMediaOriginalHandler {
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:256:        return new RemoveMediaOriginalHandler(
app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/FindMediaOriginalUrlHandler.php:31:    public function handle(FindMediaOriginalUrlQuery $query): MediaUrlResult|null
app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/FindMediaOriginalUrlQuery.php:7:final readonly class FindMediaOriginalUrlQuery
app/src/Modules/Media/Application/Contract/MediaUrlServiceContract.php:8:use App\Modules\Media\Application\Dto\MediaUrlsResult;
app/src/Modules/Media/Application/Contract/MediaUrlServiceContract.php:26:    public function getUrls(Media $media, int|null $presignedTtlSeconds = null): MediaUrlsResult|null;
app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php:8:use App\Modules\Media\Application\Query\FindMediaOriginalUrl\FindMediaOriginalUrlQuery;
app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php:54:            query: new FindMediaOriginalUrlQuery(mediaId: $mediaId),
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:8:use App\Modules\Media\Application\Dto\MediaUrlsResult;
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:30:    public function handle(FindMediaUrlQuery $query): MediaUrlsResult|null
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlQuery.php:7:final readonly class FindMediaUrlQuery
app/src/Modules/Media/Application/Dto/MediaConversionUrlCollection.php:13: * @extends TypedCollection<int, MediaConversionUrl>
app/src/Modules/Media/Application/Dto/MediaConversionUrlCollection.php:15:final class MediaConversionUrlCollection extends TypedCollection
app/src/Modules/Media/Application/Dto/MediaConversionUrlCollection.php:23:    ): MediaConversionUrl|null {
app/src/Modules/Media/Application/Dto/MediaConversionUrlCollection.php:24:        return $this->first(static fn(MediaConversionUrl $conversion): bool => $conversion->type === $type);
app/src/Modules/Media/README.md:26:| `FindMediaUrl(mediaId, presignedTtlSeconds?)` | Query | `MediaUrlsResult` или `null` (полный набор: `original` — оригинал, `null` если он удалён в `readyOriginalRemoved`; `conversions` — все конверсии, каждая со своим типом; вызывающий выбирает нужное по типу, не зная заранее, какие конверсии есть. Не бросает: медиа нет или не финализировано → `null` для best-effort показа. Для public — прямые URL без срока (`presignedTtlSeconds` игнорируется и не валидируется), для private — presigned со сроком: по умолчанию из конфига, вызывающий может переопределить `presignedTtlSeconds` (явный `0`/вне диапазона 1..604800 → ошибка)) |
app/src/Modules/Media/README.md:56:  `MediaConversionUrl{ kind, type, url, expiresAt? }` (ссылка конверсии: `kind` — вид
app/src/Modules/Media/README.md:58:  `kind = image`), `MediaUrlsResult{ original: MediaUrlResult?, conversions: MediaConversionUrlCollection }`
app/src/Modules/Media/README.md:96:`MediaUrlsResult` с `original = null` и доступными конверсиями (отдельного 404 на удалённый оригинал
app/src/Modules/Media/README.md:181:  (`FindMediaUrlQuery.presignedTtlSeconds`). Ключи `MEDIA_*` — в `.env.sample` и `phpunit.xml`.
app/src/Modules/Media/Application/Dto/MediaUrlsResult.php:12:final readonly class MediaUrlsResult
app/src/Modules/Media/Application/Dto/MediaUrlsResult.php:16:        public MediaConversionUrlCollection $conversions,
app/src/Modules/Media/Application/Dto/MediaConversionUrl.php:18:final readonly class MediaConversionUrl
app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalCommand.php:7:final readonly class RemoveMediaOriginalCommand
app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php:33:final readonly class RemoveMediaOriginalHandler
app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php:44:    public function handle(RemoveMediaOriginalCommand $command): MediaResult

exec
/bin/zsh -lc 'rg -n "GetMediaUrl|FindMediaUrl|media url|media.*url|RemoveMediaOriginal|remove original" app/src/Modules/*/Presentation app/src/Modules/*/Application tests/Feature -S' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/Posts/Presentation/Http/Resource/PostMediaItemResource.php:22:            url: $media->url,
app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/FindMediaOriginalUrlHandler.php:15: * конверсии не нужны (аватар профиля). В отличие от FindMediaUrl, грузит медиа без конверсий
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:5:namespace App\Modules\Media\Application\Query\FindMediaUrl;
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:22:final readonly class FindMediaUrlHandler
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:30:    public function handle(FindMediaUrlQuery $query): MediaUrlsResult|null
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlQuery.php:5:namespace App\Modules\Media\Application\Query\FindMediaUrl;
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlQuery.php:7:final readonly class FindMediaUrlQuery
app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalCommand.php:5:namespace App\Modules\Media\Application\Command\RemoveMediaOriginal;
app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalCommand.php:7:final readonly class RemoveMediaOriginalCommand
app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php:5:namespace App\Modules\Media\Application\Command\RemoveMediaOriginal;
app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php:27: * статус остаётся ready, и любой запрос ссылки на оригинал (FindMediaUrl/FindMediaOriginalUrl/лента/
app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php:33:final readonly class RemoveMediaOriginalHandler
app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php:44:    public function handle(RemoveMediaOriginalCommand $command): MediaResult
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:10:use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlHandler;
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:11:use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlQuery;
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:35:final class FindMediaUrlHandlerTest extends MediaApplicationTestCase
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:40:            new FindMediaUrlQuery(mediaId: UserId::generate()->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:54:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:76:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:83:        self::assertSame('http://minio/media-public/' . $media->path->value(), $result->original->url);
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:91:        self::assertSame('http://minio/media-public/' . $thumbnail->path->value(), $thumbnailUrl->url);
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:96:        self::assertSame('http://minio/media-public/' . $video->path->value(), $videoUrl->url);
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:100:        self::assertSame('http://minio/media-public/' . $audio->path->value(), $audioUrl->url);
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:118:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:125:        self::assertSame('http://minio/signed/' . $media->path->value(), $result->original->url);
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:156:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:178:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:183:        self::assertSame('http://minio/media-public/object', $result->original->url);
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:201:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 0),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:206:        self::assertSame('http://minio/media-public/object', $result->original->url);
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:225:            new FindMediaUrlQuery(mediaId: $media->id->value()),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:249:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 0),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:253:    private function handler(MediaFileServiceContract $fileService): FindMediaUrlHandler
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:255:        return new FindMediaUrlHandler(
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:7:use App\Modules\Media\Application\Command\RemoveMediaOriginal\RemoveMediaOriginalCommand;
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:8:use App\Modules\Media\Application\Command\RemoveMediaOriginal\RemoveMediaOriginalHandler;
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:38:final class RemoveMediaOriginalHandlerTest extends MediaApplicationTestCase
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:55:        $result = $this->handler($fileService)->handle(new RemoveMediaOriginalCommand(
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:83:        $this->handler($fileService)->handle(new RemoveMediaOriginalCommand(
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:110:        $this->handler($fileService)->handle(new RemoveMediaOriginalCommand(
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:127:        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new RemoveMediaOriginalCommand(
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:140:        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new RemoveMediaOriginalCommand(
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:156:        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new RemoveMediaOriginalCommand(
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:175:        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new RemoveMediaOriginalCommand(
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:194:        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new RemoveMediaOriginalCommand(
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:212:        $result = $this->handler($fileService)->handle(new RemoveMediaOriginalCommand(
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:245:            new RemoveMediaOriginalCommand(
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:255:    ): RemoveMediaOriginalHandler {
tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php:256:        return new RemoveMediaOriginalHandler(
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:82:        self::assertSame('http://minio/media-public/' . $media->path->value(), $result->url);
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:107:        self::assertSame('http://minio/signed/' . $media->path->value(), $result->url);

codex
Пока не нашёл явной поведенческой поломки в новой схеме `FindMediaUrl`/`FindMediaOriginalUrl`: контракты связаны через бутлоадеры, старый `GetMediaUrl` в коде больше не используется, а переходы `readyOriginalRemoved` учтены в профиле и постах. Проверяю оставшиеся риски по правилам проекта: зависимость слоёв, типы коллекций и тестовые пробелы.
exec
/bin/zsh -lc "sed -n '1,260p' app/src/Shared/Domain/Collection/TypedCollection.php && sed -n '1,220p' app/src/Modules/Media/Domain/ValueObject/MediaPresignedTtl.php && sed -n '1,220p' app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartsCount.php && sed -n '1,220p' app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartSize.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Collection;

use Illuminate\Support\Collection;

/**
 * Общий базовый класс для всех типизированных коллекций проекта. Наследует
 * Illuminate Collection и добавляет правильно типизированное преобразование
 * коллекции в список, чтобы на местах вызова не писать
 * \array_values($coll->toBase()->map($fn)->all()).
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @extends Collection<TKey, TValue>
 */
abstract class TypedCollection extends Collection
{
    /**
     * Преобразовать элементы в другой тип и вернуть список с последовательными
     * ключами. Идёт через toBase(), чтобы смена типа элемента не ломала
     * обобщённый тип final-коллекции; array_values даёт PHPStan-тип list<TNew>.
     *
     * @template TNew
     *
     * @param callable(TValue): TNew $callback
     *
     * @return list<TNew>
     */
    public function mapToList(callable $callback): array
    {
        return \array_values($this->toBase()->map($callback)->all());
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\ValueObject;

use App\Shared\Domain\ValueObject\AbstractIntegerValue;

final readonly class MediaPresignedTtl extends AbstractIntegerValue
{
    protected const int MIN = 1;
    protected const int MAX = 604_800;
    protected const string NAME = 'TTL presigned-ссылки';
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\ValueObject;

use App\Shared\Domain\ValueObject\AbstractIntegerValue;

final readonly class MediaMultipartPartsCount extends AbstractIntegerValue
{
    protected const int MIN = 1;
    protected const int MAX = 10_000;
    protected const string NAME = 'Количество частей загрузки';
}
<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\ValueObject;

use App\Shared\Domain\ValueObject\AbstractIntegerValue;

final readonly class MediaMultipartPartSize extends AbstractIntegerValue
{
    protected const int MIN = 5_242_880;
    protected const int MAX = 5_497_558_138_880;
    protected const string NAME = 'Размер части загрузки';
}

exec
/bin/zsh -lc 'rg -n "TODO|todo|FIXME|fixme|@todo|перед подключением|пересмотреть|компромисс|без тест|не покры" app/src/Modules/Media app/src/Modules/User app/src/Modules/Posts app/src/Shared tests docs/reviews/2026-06-30_20-54_uncommitted-diff.md -S' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:59:     * php-ffmpeg готовым API не покрывает. Единая точка распространяет лимит потоков
app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php:26: * Осознанный компромисс порядка «удалить в S3 → зафиксировать статус»: пока переход не довыполнен,
app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php:30: * поэтому пока не подключена к прямому запуску пользователем; перед подключением к реальному триггеру компромисс
app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php:31: * пересмотреть (вариант: вынести deleteObject в outbox-шаг после commit-а перехода).
app/src/Modules/Media/README.md:125:  осознанный компромисс — повтор `complete` идемпотентен (`NoSuchUpload` → подтверждение через
app/src/Modules/Media/README.md:224:  путь отхода при упоре — вынос транскодирования в Temporal (осознанный компромисс ради

exec
/bin/zsh -lc 'git diff HEAD -- app/src/Modules/Media/Application/Contract/MediaUrlServiceContract.php app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php app/src/Modules/Posts/Application/View/PostViewAssembler.php docs/arch.md' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
diff --git a/app/src/Modules/Media/Application/Contract/MediaUrlServiceContract.php b/app/src/Modules/Media/Application/Contract/MediaUrlServiceContract.php
new file mode 100644
index 0000000..b7d142f
--- /dev/null
+++ b/app/src/Modules/Media/Application/Contract/MediaUrlServiceContract.php
@@ -0,0 +1,29 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Modules\Media\Application\Contract;
+
+use App\Modules\Media\Application\Dto\MediaUrlResult;
+use App\Modules\Media\Application\Dto\MediaUrlsResult;
+use App\Modules\Media\Domain\Entity\Media;
+
+/**
+ * Контракт построения URL из УЖЕ загруженной сущности Media. Принимает доменную сущность Media и
+ * возвращает Application DTO.
+ *
+ * Срок presigned-ссылки скачивания: значение по умолчанию читает реализация из конфига, а вызывающий
+ * может переопределить его на конкретный вызов через $presignedTtlSeconds.
+ *
+ * getUrls — полный набор (оригинал, если не удалён, и все конверсии). getOriginalUrl — только ссылка
+ * на оригинал, без обращения к связям-конверсиям: для потребителей, которым конверсии не нужны (аватар
+ * профиля, лента) — чтобы не подгружать и не подписывать конверсии впустую.
+ *
+ * Реализация — App\Modules\Media\Infrastructure\FileService\MediaUrlService.
+ */
+interface MediaUrlServiceContract
+{
+    public function getUrls(Media $media, int|null $presignedTtlSeconds = null): MediaUrlsResult|null;
+
+    public function getOriginalUrl(Media $media, int|null $presignedTtlSeconds = null): MediaUrlResult|null;
+}
diff --git a/app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php b/app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php
index 28f9a2a..aad735b 100644
--- a/app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php
+++ b/app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php
@@ -4,49 +4,37 @@ declare(strict_types=1);
 
 namespace App\Modules\Media\Application\Query\FindMediaUrl;
 
-use App\Modules\Media\Application\Contract\MediaFileServiceContract;
-use App\Modules\Media\Application\Dto\MediaUrlResult;
-use App\Modules\Media\Domain\Enum\MediaVisibility;
+use App\Modules\Media\Application\Contract\MediaUrlServiceContract;
+use App\Modules\Media\Application\Dto\MediaUrlsResult;
 use App\Modules\Media\Domain\ValueObject\MediaId;
-use App\Modules\Media\Domain\ValueObject\MediaPresignedTtl;
 use App\Modules\Media\Repository\MediaRepository;
 use GianTiaga\SpiralCqrs\Attribute\LogOperation;
 
 /**
- * Не бросающий вариант GetMediaUrl для best-effort отображения (например, аватара в профиле):
- * если медиа отсутствует или ещё не готово — возвращает null, чтобы вызывающий подставил значение
- * по умолчанию без try-catch. Для публичного медиа отдаёт прямой URL, для приватного — presigned.
+ * Best-effort разрешение всех URL медиа по его id: оригинал (если не удалён) и все конверсии.
+ * Грузит медиа вместе с конверсиями одним набором запросов и делегирует построение URL в
+ * MediaUrlService. Возвращает null, если медиа нет или оно не финализировано, чтобы вызывающий
+ * подставил значение по умолчанию (аватар в профиле) без try-catch.
+ *
+ * Для модулей, которые уже держат сущность Media загруженной (например, лента Posts через relation),
+ * есть прямой путь MediaUrlService::getUrls(Media) — без повторной загрузки.
  */
 final readonly class FindMediaUrlHandler
 {
     public function __construct(
         private MediaRepository $mediaRepository,
-        private MediaFileServiceContract $mediaFileService,
+        private MediaUrlServiceContract $mediaUrlService,
     ) {}
 
     #[LogOperation]
-    public function handle(FindMediaUrlQuery $query): MediaUrlResult|null
+    public function handle(FindMediaUrlQuery $query): MediaUrlsResult|null
     {
-        $media = $this->mediaRepository->findById(MediaId::fromString($query->mediaId));
+        $media = $this->mediaRepository->findByIdWithConversions(MediaId::fromString($query->mediaId));
 
-        if ($media === null || !$media->isReady()) {
+        if ($media === null) {
             return null;
         }
 
-        if ($media->visibility === MediaVisibility::Public) {
-            return new MediaUrlResult(
-                url: $this->mediaFileService->publicUrl(storage: $media->storage, path: $media->path),
-                expiresAt: null,
-            );
-        }
-
-        $expiresAt = new \DateTimeImmutable()->add(
-            new \DateInterval(\sprintf('PT%dS', MediaPresignedTtl::fromInt($query->presignedTtlSeconds)->value())),
-        );
-
-        return new MediaUrlResult(
-            url: $this->mediaFileService->presignGet(storage: $media->storage, path: $media->path, expiresAt: $expiresAt),
-            expiresAt: $expiresAt,
-        );
+        return $this->mediaUrlService->getUrls(media: $media, presignedTtlSeconds: $query->presignedTtlSeconds);
     }
 }
diff --git a/app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php b/app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php
new file mode 100644
index 0000000..1f21556
--- /dev/null
+++ b/app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php
@@ -0,0 +1,165 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Modules\Media\Infrastructure\FileService;
+
+use App\Modules\Media\Application\Contract\MediaFileServiceContract;
+use App\Modules\Media\Application\Contract\MediaUrlServiceContract;
+use App\Modules\Media\Application\Dto\MediaConversionUrl;
+use App\Modules\Media\Application\Dto\MediaConversionUrlCollection;
+use App\Modules\Media\Application\Dto\MediaUrlResult;
+use App\Modules\Media\Application\Dto\MediaUrlsResult;
+use App\Modules\Media\Domain\Entity\Media;
+use App\Modules\Media\Domain\Entity\MediaAudioConversion;
+use App\Modules\Media\Domain\Entity\MediaImageConversion;
+use App\Modules\Media\Domain\Entity\MediaVideoConversion;
+use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
+use App\Modules\Media\Domain\Enum\MediaConversionKind;
+use App\Modules\Media\Domain\Enum\MediaImageConversionType;
+use App\Modules\Media\Domain\Enum\MediaStorage;
+use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
+use App\Modules\Media\Domain\Enum\MediaVisibility;
+use App\Modules\Media\Domain\ValueObject\MediaPath;
+use App\Modules\Media\Domain\ValueObject\MediaPresignedTtl;
+use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
+use Illuminate\Support\Collection;
+
+/**
+ * Строит URL из УЖЕ загруженного медиа. getUrls — полный набор: оригинал (если не удалён) и все
+ * конверсии. Конверсии берутся из связей сущности (imageConversions/videoConversions/audioConversions),
+ * поэтому метод не делает запросов в БД — при условии, что связи загружены eager заранее
+ * (репозиторий-метод с ->load(...) или ->load('media.imageConversions') у вызывающего модуля).
+ * Если связи не загружены, Cycle подгрузит их лениво — это вернёт N+1, поэтому вызывающий обязан
+ * передавать медиа с eager-загруженными конверсиями. getOriginalUrl — только оригинал, к связям
+ * не обращается вовсе: для потребителей, которым конверсии не нужны (аватар, лента).
+ *
+ * Срок presigned-ссылки скачивания: значение по умолчанию реализация читает из MediaConfig напрямую
+ * (прямая инъекция конфига — класс лежит в Infrastructure), а вызывающий может переопределить его на
+ * конкретный вызов. Сервис stateless, поэтому expiresAt всегда считается внутри вызова (не в
+ * конструкторе), иначе все наборы URL получили бы один замороженный срок.
+ *
+ * Не бросает: не финализированное медиа (ещё не ready и не readyOriginalRemoved) -> null. После
+ * удаления оригинала original = null, конверсии резолвятся.
+ */
+final readonly class MediaUrlService implements MediaUrlServiceContract
+{
+    public function __construct(
+        private MediaFileServiceContract $mediaFileService,
+        private MediaConfig $mediaConfig,
+    ) {}
+
+    public function getUrls(Media $media, int|null $presignedTtlSeconds = null): MediaUrlsResult|null
+    {
+        if (!$media->isFinalized()) {
+            return null;
+        }
+
+        $resolver = $this->resolverFor(
+            visibility: $media->visibility,
+            presignedTtlSeconds: $presignedTtlSeconds,
+        );
+
+        return new MediaUrlsResult(
+            original: $media->isReady() ? $resolver->resolve(storage: $media->storage, path: $media->path) : null,
+            conversions: $this->conversionUrls(media: $media, resolver: $resolver),
+        );
+    }
+
+    /**
+     * Только ссылка на оригинал, без обращения к связям-конверсиям. Возвращает null, если оригинала
+     * нет (медиа не финализировано или оригинал удалён в readyOriginalRemoved) — вызывающий подставит
+     * значение по умолчанию. Не трогает imageConversions/videoConversions/audioConversions, поэтому
+     * не подгружает их из БД и не подписывает presigned-ссылки для конверсий, которые потребителю
+     * (аватар, лента) не нужны.
+     */
+    public function getOriginalUrl(Media $media, int|null $presignedTtlSeconds = null): MediaUrlResult|null
+    {
+        if (!$media->isReady()) {
+            return null;
+        }
+
+        $resolver = $this->resolverFor(
+            visibility: $media->visibility,
+            presignedTtlSeconds: $presignedTtlSeconds,
+        );
+
+        return $resolver->resolve(storage: $media->storage, path: $media->path);
+    }
+
+    /**
+     * Резолвер URL по контексту медиа: public — прямые URL без срока (TTL не нужен и не валидируется),
+     * private — presigned с единым сроком на весь набор URL одного вызова. Переопределение TTL строго
+     * по `?? `: null -> значение по умолчанию из конфига; явный 0 или значение вне диапазона ->
+     * исключение MediaPresignedTtl (нельзя писать `?:`, иначе явный 0 тихо ушёл бы в значение по
+     * умолчанию).
+     */
+    private function resolverFor(MediaVisibility $visibility, int|null $presignedTtlSeconds): MediaUrlResolver
+    {
+        if ($visibility === MediaVisibility::Public) {
+            return new PublicMediaUrlResolver($this->mediaFileService);
+        }
+
+        $ttl = MediaPresignedTtl::fromInt($presignedTtlSeconds ?? $this->mediaConfig->presignedTtlSeconds);
+        $expiresAt = new \DateTimeImmutable()->add(new \DateInterval(\sprintf('PT%dS', $ttl->value())));
+
+        return new PresignedMediaUrlResolver(mediaFileService: $this->mediaFileService, expiresAt: $expiresAt);
+    }
+
+    private function conversionUrls(Media $media, MediaUrlResolver $resolver): MediaConversionUrlCollection
+    {
+        $imageUrls = $this->conversionUrlsOf(
+            conversions: $media->imageConversions->toBase(),
+            kind: MediaConversionKind::Image,
+            resolver: $resolver,
+        );
+        $videoUrls = $this->conversionUrlsOf(
+            conversions: $media->videoConversions->toBase(),
+            kind: MediaConversionKind::Video,
+            resolver: $resolver,
+        );
+        $audioUrls = $this->conversionUrlsOf(
+            conversions: $media->audioConversions->toBase(),
+            kind: MediaConversionKind::Audio,
+            resolver: $resolver,
+        );
+
+        return new MediaConversionUrlCollection($imageUrls->concat($videoUrls)->concat($audioUrls));
+    }
+
+    /**
+     * Строит ссылки конверсий одного вида. Обвязка map одинакова для image/video/audio и различается
+     * только видом и типом элемента, поэтому вынесена сюда (раньше — три почти одинаковых map-блока).
+     *
+     * @param Collection<int, MediaImageConversion|MediaVideoConversion|MediaAudioConversion> $conversions
+     * @return Collection<int, MediaConversionUrl>
+     */
+    private function conversionUrlsOf(
+        Collection $conversions,
+        MediaConversionKind $kind,
+        MediaUrlResolver $resolver,
+    ): Collection {
+        return $conversions->map(
+            fn(MediaImageConversion|MediaVideoConversion|MediaAudioConversion $conversion): MediaConversionUrl
+                => $this->conversionUrl(
+                    kind: $kind,
+                    type: $conversion->type,
+                    storage: $conversion->storage,
+                    path: $conversion->path,
+                    resolver: $resolver,
+                ),
+        );
+    }
+
+    private function conversionUrl(
+        MediaConversionKind $kind,
+        MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType $type,
+        MediaStorage $storage,
+        MediaPath $path,
+        MediaUrlResolver $resolver,
+    ): MediaConversionUrl {
+        $resolved = $resolver->resolve(storage: $storage, path: $path);
+
+        return new MediaConversionUrl(kind: $kind, type: $type, url: $resolved->url, expiresAt: $resolved->expiresAt);
+    }
+}
diff --git a/app/src/Modules/Posts/Application/View/PostViewAssembler.php b/app/src/Modules/Posts/Application/View/PostViewAssembler.php
index af99f35..99604d6 100644
--- a/app/src/Modules/Posts/Application/View/PostViewAssembler.php
+++ b/app/src/Modules/Posts/Application/View/PostViewAssembler.php
@@ -4,8 +4,7 @@ declare(strict_types=1);
 
 namespace App\Modules\Posts\Application\View;
 
-use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlHandler;
-use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlQuery;
+use App\Modules\Media\Application\Contract\MediaUrlServiceContract;
 use App\Modules\Posts\Application\Post\PostVisibilityPolicy;
 use App\Modules\Posts\Domain\Collection\PostCollection;
 use App\Modules\Posts\Domain\Collection\PostMediaCollection;
@@ -36,21 +35,18 @@ use GianTiaga\SpiralCqrs\QueryBusInterface;
  * оригинала), если она видна зрителю.
  *
  * В листингах пакетно собираются выборки из БД: авторы, флаги likedByMe, медиа и теги берутся одним
- * запросом на страницу (без N+1 на уровне БД). Разрешение URL каждого вложения остаётся поэлементным
- * (mediaItem -> FindMediaUrl): пакетного контракта разрешения URL в Media сейчас нет, поэтому число
- * вызовов растёт линейно с числом вложений на странице. Это сознательный компромисс, а не «без N+1»
- * на уровне URL медиа.
+ * запросом на страницу (без N+1 на уровне БД). Вложения и их сущности Media грузятся пакетно в
+ * PostMediaRepository (eager media.*), поэтому URL оригинала строится в памяти из уже загруженной
+ * сущности через MediaUrlService::getOriginalUrl — отдельного запроса в базу на вложение нет (для
+ * приватного медиа остаётся только подпись presigned-ссылки на оригинал, не запрос в БД).
  */
 final readonly class PostViewAssembler
 {
-    // TTL ссылки на медиа записи для показа (для приватного медиа — срок presigned-ссылки).
-    private const int POST_MEDIA_URL_TTL_SECONDS = 3600;
-
     public function __construct(
         private QueryBusInterface $queryBus,
         private GetUserPublicProfileHandler $getUserPublicProfileHandler,
         private GetUserPublicProfilesHandler $getUserPublicProfilesHandler,
-        private FindMediaUrlHandler $findMediaUrlHandler,
+        private MediaUrlServiceContract $mediaUrlService,
         private GetTagsHandler $getTagsHandler,
         private PostRepository $postRepository,
         private PostMediaRepository $postMediaRepository,
@@ -268,30 +264,25 @@ final readonly class PostViewAssembler
     }
 
     /**
-     * Разрешает ссылку на медиа записи без бросающего GetMediaUrl: если медиа удалено или ещё не
-     * готово, недоступное вложение исключается из ответа, а не роняет чтение записи/ленты в 500.
-     *
-     * FindMediaUrl вызывается напрямую (минуя QueryBus), как и в UserPublicProfileAssembler: это
-     * единственный сценарий Media с nullable-результатом, а обёртка шины теряет null из вывода типов.
-     * Прямой вызов сохраняет контракт «медиа недоступно -> null -> вложение пропущено» без try-catch
-     * и без подавления статанализа.
+     * Разрешает ссылку на медиа записи из УЖЕ загруженной сущности Media (relation post_media.media
+     * грузится eager в PostMediaRepository вместе с конверсиями — без N+1 на вложение). Построение URL
+     * делегируется MediaUrlService без обращения в БД. Если медиа не готово или его оригинал удалён,
+     * недоступное вложение исключается из ответа, а не роняет чтение ленты в 500. Берётся только
+     * оригинал (getOriginalUrl): конверсии (постер видео, превью) сейчас в ленте не используются, а
+     * подписывать presigned-ссылки на каждую из них впустую для private-медиа не нужно — это отдельная
+     * задача.
      */
     private function mediaItem(PostMedia $postMedia): PostMediaItemView|null
     {
-        $mediaUrl = $this->findMediaUrlHandler->handle(
-            new FindMediaUrlQuery(
-                mediaId: $postMedia->media->value(),
-                presignedTtlSeconds: self::POST_MEDIA_URL_TTL_SECONDS,
-            ),
-        );
+        $originalUrl = $this->mediaUrlService->getOriginalUrl(media: $postMedia->media);
 
-        if ($mediaUrl === null) {
+        if ($originalUrl === null) {
             return null;
         }
 
         return new PostMediaItemView(
-            mediaId: $postMedia->media->value(),
-            url: $mediaUrl->url,
+            mediaId: $postMedia->mediaId->value(),
+            url: $originalUrl->url,
             position: $postMedia->position->value(),
         );
     }
diff --git a/app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php b/app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php
index 3b9b641..d9d7f59 100644
--- a/app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php
+++ b/app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php
@@ -4,30 +4,32 @@ declare(strict_types=1);
 
 namespace App\Modules\User\Application\Profile;
 
-use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlHandler;
-use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlQuery;
+use App\Modules\Media\Application\Query\FindMediaOriginalUrl\FindMediaOriginalUrlHandler;
+use App\Modules\Media\Application\Query\FindMediaOriginalUrl\FindMediaOriginalUrlQuery;
 use App\Modules\User\Application\Dto\UserPublicProfileView;
 use App\Modules\User\Domain\Entity\User;
-use App\Shared\Infrastructure\Configuration\User\UserConfig;
+use GianTiaga\SpiralCqrs\QueryBusInterface;
 
 /**
  * Собирает публичный профиль из доменной сущности User, разрешая ссылку на аватар через модуль
  * Media. Аватар всегда непустой: если у пользователя нет аватара или его медиа недоступно
- * (удалено/не готово) — подставляется значение по умолчанию из UserConfig.
+ * (удалено/не готово) — подставляется готовая ссылка на аватар по умолчанию (её передаёт
+ * Infrastructure при сборке ассемблера, источник Application не знает).
  *
- * FindMediaUrl вызывается напрямую (а не через QueryBus), потому что это единственный сценарий,
- * возвращающий nullable, и обёртка шины теряет null из вывода типов — прямой вызов сохраняет
- * контракт «медиа недоступно -> null -> дефолт» без try-catch и без подавления статанализа.
+ * Используется FindMediaOriginalUrl (только оригинал, без подгрузки и подписания конверсий): для
+ * аватара нужна одна ссылка, а в листингах профилей сборка вызывается на каждого пользователя, поэтому
+ * лишние загрузки связей конверсий множились бы на число пользователей.
+ *
+ * Межмодульный Query идёт через QueryBus (как в PostViewAssembler): шина возвращает ровно тип
+ * Handler::handle() (MediaUrlResult|null), поэтому контракт «медиа недоступно -> null -> значение по
+ * умолчанию» сохраняется без try-catch, а middleware обработчика (в том числе #[LogOperation]) работает.
  */
 final readonly class UserPublicProfileAssembler
 {
-    // Аватары публичны (прямой URL без срока), поэтому TTL фактически не используется; значение
-    // нужно только для приватной ветки FindMediaUrl и берётся техническим дефолтом рядом с местом.
-    private const int AVATAR_URL_TTL_SECONDS = 3600;
-
     public function __construct(
-        private FindMediaUrlHandler $findMediaUrlHandler,
-        private UserConfig $userConfig,
+        private QueryBusInterface $queryBus,
+        private FindMediaOriginalUrlHandler $findMediaOriginalUrlHandler,
+        private string $defaultAvatarUrl,
     ) {}
 
     public function fromUser(User $user): UserPublicProfileView
@@ -45,17 +47,20 @@ final readonly class UserPublicProfileAssembler
         $mediaId = $user->avatar->value();
 
         if ($mediaId === null) {
-            return $this->userConfig->defaultAvatarUrl;
+            return $this->defaultAvatarUrl;
         }
 
-        $mediaUrl = $this->findMediaUrlHandler->handle(
-            new FindMediaUrlQuery(mediaId: $mediaId, presignedTtlSeconds: self::AVATAR_URL_TTL_SECONDS),
+        $originalUrl = $this->queryBus->dispatch(
+            query: new FindMediaOriginalUrlQuery(mediaId: $mediaId),
+            handler: $this->findMediaOriginalUrlHandler->handle(...),
         );
 
-        if ($mediaUrl === null) {
-            return $this->userConfig->defaultAvatarUrl;
+        // Если медиа недоступно или оригинал удалён (readyOriginalRemoved) — null, подставляем
+        // значение по умолчанию.
+        if ($originalUrl === null) {
+            return $this->defaultAvatarUrl;
         }
 
-        return $mediaUrl->url;
+        return $originalUrl->url;
     }
 }
diff --git a/docs/arch.md b/docs/arch.md
index 67b5958..8849b45 100644
--- a/docs/arch.md
+++ b/docs/arch.md
@@ -155,14 +155,36 @@ User/Application/SetAvatar
 
 `Media` не должен знать про аватар. Аватар - это часть `User`.
 
+### Исключение: `Media` — foundational-модуль
+
+`Media` — универсальный (foundational) модуль: хранение и раздача файлов нужны почти любому
+модулю. Поэтому для него действует осознанное исключение из правила «модули общаются только через
+`Application`»: другим модулям разрешено **держать ORM-relation на сущности `Media` (на чтение)** и
+**передавать загруженную сущность `Media` в Application-сервисы `Media`**.
+
+Зачем: чтение списков с вложениями (лента `Posts`) должно грузить медиа и их конверсии вместе с
+основной выборкой (`->load('media.imageConversions'...)`), а не разрешать URL поэлементно (N+1).
+Для этого `PostMedia` объявляет `#[BelongsTo(target: Media::class, ..., cascade: false, fkCreate:
+false)]` и eager-грузит её в репозитории, после чего URL строится из уже загруженной сущности без
+обращений в БД: лента берёт только оригинал через `MediaUrlService::getOriginalUrl(Media $media)`
+(eager-загруженные конверсии держатся под планируемый показ превью), а полный набор «оригинал +
+конверсии» отдаёт `MediaUrlService::getUrls(Media $media)` (путь `FindMediaUrl`).
+
+Что по-прежнему **запрещено** даже для `Media`: использовать `MediaRepository` или `Media/Infrastructure`
+из другого модуля; писать/менять данные `Media` через relation (поэтому `cascade: false`); заводить
+кросс-модульный FK ради такой связи (`fkCreate: false` — FK либо уже есть в миграции, либо его нет).
+Запись и изменение медиа идут только через Command-сценарии `Media/Application`.
+
 `Media` должен давать только свои сценарии:
 
 ```text
 CreateMedia
 DeleteMedia
+RemoveMediaOriginal
 CheckMediaExists
 CheckMediaIsImage
-GetMediaUrl
+FindMediaUrl
+FindMediaOriginalUrl
 ```
 
 ## Локальный Docker-runtime
@@ -264,22 +286,40 @@ Domain         -> PHP standard library, свой Domain, Shared/Domain
 Shared         -> общий доменный и инфраструктурный код без привязки к одному модулю
 ```
 
-Осознанное исключение: типизированный `TypedConfig` из
-`Shared/Infrastructure/Configuration` может инжектиться напрямую в Application-Handler,
-когда сценарию нужны инфра-дефолты (staging-TTL, пороги, размеры, драйвер). Пример —
-`MediaConfig` в `RequestMediaUploadHandler` модуля `Media`.
-Формально `Shared/Infrastructure` не входит в список зависимостей Application выше, но
-`TypedConfig` — это не технический сервис с поведением и не зависимость от чужого модуля:
-правила (`rules.md` «Typed config для каждого config-файла») и эта же `arch.md`
-(«Configuration → app/config → Shared/Infrastructure/Configuration») предписывают единое
-размещение всех config-DTO в `Shared/Infrastructure/Configuration`. Оборачивать такой
-config-DTO в Application-`*Contract` и привязывать его в бутлоадере означало бы создать
-pass-through-обёртку над `TypedConfig` ради формального списка — это запрещённый паттерн
-(ср. «Без pass-through typecast-обёрток») и сделало бы модуль единственным, кто прячет
-собственный typed-config за контрактом. Поэтому прямая инъекция `TypedConfig` в Application
-допускается явно. Если Application нужен именно технический сервис с поведением (S3,
-процессор, внешний клиент) — он по-прежнему идёт через `Application/Contract` + реализацию
-в `Infrastructure`, без исключений.
+Строгое правило: **Domain и Application не зависят от `Shared/Infrastructure/Configuration` и
+не импортируют `*Config`.** Конфиг читается в Infrastructure (бутлоадеры, инфра-сервисы,
+middleware), которая отдаёт в Application уже готовые значения через DI. Допустимы две формы
+передачи самого значения:
+
+- **Единичное готовое значение или VO через фабрику бутлоадера.** Бутлоадер модуля читает нужный
+  `*Config` в фабрике `defineSingletons()` — контейнер подставляет config параметром фабрики, это
+  законное чтение конфига в Infrastructure — собирает из него одно готовое значение и биндит его в
+  Application-класс, куда оно авто-вайрится. Живой образец: `UserBootloader` отдаёт
+  `UserPublicProfileAssembler` готовый `defaultAvatarUrl`.
+- **Доменный сервис над значениями.** Значения конфига собираются в доменный сервис, который
+  Application получает как зависимость. Живой образец: `LocaleResolver` из `LocaleConfig`.
+
+Так слой сценариев не знает ни про источник значения, ни про `Shared/Infrastructure`.
+
+Конфиг-зависимое **поведение** (а не одно значение) выносится в Infrastructure-сервис за
+`Application/Contract`: реализация читает `*Config` сама через конструктор и отдаёт Application готовые
+решения. Образцы — `MediaUrlServiceContract`/`MediaUrlService` (срок presigned-ссылки скачивания по
+умолчанию) и `MediaUploadPlannerContract`/`MediaUploadPlanner` (срок staging-хранения, нужен ли
+multipart, размер и число частей), реализации биндятся `const BINDINGS`. Общее правило —
+«Infrastructure-сервис за контрактом», папка — просто `Infrastructure`; конкретное размещение
+`MediaUploadPlanner` в `Infrastructure/FileService` рядом с `MediaUrlService` — частная деталь модуля
+Media, а не предписание для всех будущих config-зависимых сервисов.
+
+Промежуточный settings-объект, который лишь проецирует набор полей `*Config` в Application-объект,
+запрещён как «конфиг от конфига»: вместо него — одна из двух форм передачи значения выше или
+Infrastructure-сервис за контрактом для конфиг-зависимого поведения.
+
+Если Application нужен именно технический сервис с поведением (S3, процессор, внешний клиент) — он
+по-прежнему идёт через `Application/Contract` + реализацию в `Infrastructure`, без исключений.
+
+Оговорка по охвату: правило адресовано Domain и Application. Некоторые Presentation-адаптеры
+(`HealthController`, `SwaggerController`, `OpenApiGenerateCommand`) пока читают `*Config` напрямую —
+это вне охвата текущей задачи и возможная отдельная чистка.
 
 ## Взаимодействие слоёв
 

exec
/bin/zsh -lc 'git diff HEAD -- app/src/Modules/Media/Domain/Entity/Media.php app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php app/src/Modules/Media/Repository/MediaRepository.php app/src/Modules/Posts/Domain/Entity/PostMedia.php app/src/Shared/Domain/Locale/LocaleResolver.php' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
diff --git a/app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php b/app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php
new file mode 100644
index 0000000..cfe987a
--- /dev/null
+++ b/app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php
@@ -0,0 +1,86 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Modules\Media\Application\Command\RemoveMediaOriginal;
+
+use App\Modules\Media\Application\Contract\MediaFileServiceContract;
+use App\Modules\Media\Application\Dto\MediaResult;
+use App\Modules\Media\Application\Service\MediaConversionsChecker;
+use App\Modules\Media\Domain\ValueObject\MediaId;
+use App\Modules\Media\Repository\MediaRepository;
+use App\Shared\Domain\Exception\ForbiddenException;
+use App\Shared\Domain\Exception\NotFoundException;
+use App\Shared\Domain\Exception\ValidationException;
+use App\Shared\Domain\ValueObject\UserId;
+use Cycle\ORM\EntityManagerInterface;
+use GianTiaga\SpiralCqrs\Attribute\LogOperation;
+use Psr\Log\LoggerInterface;
+
+/**
+ * Удаляет оригинальный объект медиа из целевого бакета, сохраняя конверсии. Без #[Transactional]:
+ * сначала идемпотентный deleteObject оригинала (404 → no-op), затем один атомарный persist+run() с
+ * переходом в readyOriginalRemoved. Идемпотентен: на уже removed-original — ранний no-op без обращения
+ * к S3. На сбое после deleteObject до flush статус остаётся ready, повтор команды довыполнит переход.
+ *
+ * Осознанный компромисс порядка «удалить в S3 → зафиксировать статус»: пока переход не довыполнен,
+ * статус остаётся ready, и любой запрос ссылки на оригинал (FindMediaUrl/FindMediaOriginalUrl/лента/
+ * аватар) вернёт ссылку на уже удалённый объект — короткое окно битой ссылки. Команда предполагает
+ * повторный вызов при сбое (автоматического реиспуска, как у тяжёлой обработки через outbox, тут нет),
+ * поэтому пока не подключена к прямому запуску пользователем; перед подключением к реальному триггеру компромисс
+ * пересмотреть (вариант: вынести deleteObject в outbox-шаг после commit-а перехода).
+ */
+final readonly class RemoveMediaOriginalHandler
+{
+    public function __construct(
+        private MediaRepository $mediaRepository,
+        private MediaConversionsChecker $mediaConversionsChecker,
+        private MediaFileServiceContract $mediaFileService,
+        private EntityManagerInterface $entityManager,
+        private LoggerInterface $logger,
+    ) {}
+
+    #[LogOperation]
+    public function handle(RemoveMediaOriginalCommand $command): MediaResult
+    {
+        $media = $this->mediaRepository->findById(MediaId::fromString($command->mediaId))
+            ?? throw new NotFoundException('app.media.not_found');
+
+        if (!$media->uploadedById->equals(UserId::fromString($command->userId))) {
+            throw new ForbiddenException('app.media.access_denied');
+        }
+
+        if ($media->isOriginalRemoved()) {
+            $this->logger->debug(message: 'Удаление оригинала медиа пропущено: оригинал уже удалён.', context: [
+                'mediaId' => $media->id->value(),
+            ]);
+
+            return MediaResult::fromEntity($media);
+        }
+
+        if (!$media->isReady()) {
+            throw new ValidationException('app.media.original_not_removable');
+        }
+
+        if (!$this->mediaConversionsChecker->hasAnyConversion($media->id)) {
+            throw new ValidationException('app.media.no_conversions_to_keep');
+        }
+
+        // Удаляем текущий оригинал в целевом бакете строго до доменного перехода. 404 идемпотентно
+        // игнорируется сервисом, поэтому повтор команды после частичного сбоя безопасен.
+        $this->mediaFileService->deleteObject(storage: $media->storage, path: $media->path);
+
+        $media->markReadyOriginalRemoved();
+        $this->entityManager->persist($media);
+        $this->entityManager->run();
+
+        $this->logger->debug(message: 'Оригинал медиа удалён.', context: [
+            'mediaId' => $media->id->value(),
+            'userId' => $command->userId,
+            'storage' => $media->storage->value,
+            'path' => $media->path->value(),
+        ]);
+
+        return MediaResult::fromEntity($media);
+    }
+}
diff --git a/app/src/Modules/Media/Domain/Entity/Media.php b/app/src/Modules/Media/Domain/Entity/Media.php
index 89bc5ce..64b229c 100644
--- a/app/src/Modules/Media/Domain/Entity/Media.php
+++ b/app/src/Modules/Media/Domain/Entity/Media.php
@@ -173,30 +173,35 @@ final class Media
     }
 
     /**
-     * Готовое медиа не «ломается» задним числом: повторная/запоздалая фиксация ошибки на уже
-     * ready-медиа — no-op (симметрично guard'у в markReadyMovedTo). Защищает инвариант
-     * «ready без ошибки» независимо от вызывающего, даже если фиксацию сбоя задиспатчат в обход
-     * isReady-guard'а в ProcessMediaHandler (другой relay, ручной перезапуск Job, дубликат в очереди).
+     * Финализированное медиа не «ломается» задним числом: повторная/запоздалая фиксация ошибки на
+     * уже готовом медиа (ready или readyOriginalRemoved) — no-op (симметрично guard'у в
+     * markReadyMovedTo). Защищает инвариант «готовое без ошибки» независимо от вызывающего, даже
+     * если фиксацию сбоя запустят в обход isFinalized-guard'а в ProcessMediaHandler (другой relay,
+     * ручной перезапуск Job, дубликат в очереди после удаления оригинала).
      */
     public function recordTemporaryProcessingError(MediaProcessingError $processingError): void
     {
-        if ($this->status === MediaStatus::Ready) {
-            return;
-        }
-
-        $this->status = MediaStatus::ProcessingFailed;
-        $this->processingAttempts = $this->processingAttempts->increment();
-        $this->processingError = $processingError;
-        $this->touch();
+        $this->recordProcessingError($processingError);
     }
 
     /**
-     * См. recordTemporaryProcessingError: тот же инвариант «ready без ошибки» — на уже
-     * ready-медиа фиксация постоянной ошибки также no-op.
+     * См. recordTemporaryProcessingError: тот же инвариант «готовое без ошибки» — на уже
+     * финализированном медиа (ready или readyOriginalRemoved) фиксация постоянной ошибки также no-op.
      */
     public function recordPermanentProcessingError(MediaProcessingError $processingError): void
     {
-        if ($this->status === MediaStatus::Ready) {
+        $this->recordProcessingError($processingError);
+    }
+
+    /**
+     * Общее тело фиксации ошибки обработки для временной и постоянной ошибки: оба перехода
+     * идентичны (статус processingFailed, инкремент попыток, запись ошибки) и одинаково защищены
+     * guard'ом isFinalized. Остаётся приватным, а recordTemporary/PermanentProcessingError —
+     * публичные точки доменного контракта.
+     */
+    private function recordProcessingError(MediaProcessingError $processingError): void
+    {
+        if ($this->isFinalized()) {
             return;
         }
 
@@ -246,8 +251,45 @@ final class Media
         return $this->status === MediaStatus::Ready;
     }
 
+    /**
+     * Оригинал удалён, но конверсии обслуживаются (readyOriginalRemoved). Прячет сравнение с конкретным
+     * статусом от вызывающих (как isReady()/isFinalized()), чтобы Application не знал конкретный вариант
+     * enum.
+     */
+    public function isOriginalRemoved(): bool
+    {
+        return $this->status === MediaStatus::ReadyOriginalRemoved;
+    }
+
+    /**
+     * Терминальное «готовое» состояние: обработка завершена и конверсии обслуживаются — как при ready,
+     * так и после удаления оригинала (readyOriginalRemoved). Используется там, где важна готовность
+     * конверсий, а не наличие оригинала: конверсионные запросы и защита от поздней переобработки.
+     */
+    public function isFinalized(): bool
+    {
+        return $this->status === MediaStatus::Ready || $this->status === MediaStatus::ReadyOriginalRemoved;
+    }
+
+    /**
+     * Перевод в readyOriginalRemoved после физического удаления оригинала из целевого бакета.
+     * Домен сам отстаивает инвариант источника независимо от вызывающего (симметрично markReadyMovedTo
+     * и guard'у isFinalized в recordProcessingError): допустим только из ready. Идемпотентен: повторный
+     * вызов на уже readyOriginalRemoved — no-op. Из любого другого статуса — InvalidDomainValueException,
+     * чтобы оригинал не оказался «удалён» у медиа, которое никогда не было готовым.
+     */
     public function markReadyOriginalRemoved(): void
     {
+        if ($this->status === MediaStatus::ReadyOriginalRemoved) {
+            return;
+        }
+
+        if ($this->status !== MediaStatus::Ready) {
+            throw new InvalidDomainValueException(
+                'Удаление оригинала медиа допустимо только из ready.',
+            );
+        }
+
         $this->status = MediaStatus::ReadyOriginalRemoved;
         $this->processingError = MediaProcessingError::none();
         $this->touch();
diff --git a/app/src/Modules/Media/Repository/MediaRepository.php b/app/src/Modules/Media/Repository/MediaRepository.php
index 88d1c7a..c040c1c 100644
--- a/app/src/Modules/Media/Repository/MediaRepository.php
+++ b/app/src/Modules/Media/Repository/MediaRepository.php
@@ -20,6 +20,21 @@ final class MediaRepository extends AbstractRepository
         return $this->findByPK($mediaId->value());
     }
 
+    /**
+     * Медиа вместе со всеми конверсиями (image/video/audio), загруженными одним набором запросов,
+     * чтобы построение URL не дёргало репозитории конверсий по одному. Используется там, где нужен
+     * полный набор ссылок (MediaUrlService::getUrls).
+     */
+    public function findByIdWithConversions(MediaId $mediaId): Media|null
+    {
+        return $this->select()
+            ->wherePK($mediaId->value())
+            ->load('imageConversions')
+            ->load('videoConversions')
+            ->load('audioConversions')
+            ->fetchOne();
+    }
+
     public function findByStorageKey(MediaStorageKey $storageKey): Media|null
     {
         return $this->findOne(['storage_key' => $storageKey->value()]);
diff --git a/app/src/Modules/Posts/Domain/Entity/PostMedia.php b/app/src/Modules/Posts/Domain/Entity/PostMedia.php
index 70295e9..b6943a0 100644
--- a/app/src/Modules/Posts/Domain/Entity/PostMedia.php
+++ b/app/src/Modules/Posts/Domain/Entity/PostMedia.php
@@ -4,6 +4,7 @@ declare(strict_types=1);
 
 namespace App\Modules\Posts\Domain\Entity;
 
+use App\Modules\Media\Domain\Entity\Media;
 use App\Modules\Posts\Domain\ValueObject\MediaPosition;
 use App\Modules\Posts\Domain\ValueObject\PostId;
 use App\Modules\Posts\Domain\ValueObject\PostMediaId;
@@ -33,7 +34,7 @@ final class PostMedia
     public private(set) PostId $postId;
 
     #[Column(type: 'uuid', name: 'media_id', typecast: PostMediaReference::class)]
-    public private(set) PostMediaReference $media;
+    public private(set) PostMediaReference $mediaId;
 
     #[Column(type: 'integer', typecast: MediaPosition::class)]
     public private(set) MediaPosition $position;
@@ -41,13 +42,32 @@ final class PostMedia
     #[BelongsTo(target: Post::class, innerKey: 'post_id', outerKey: 'id', fkOnDelete: 'CASCADE')]
     public private(set) Post $post;
 
-    public static function create(Post $post, PostMediaReference $media, MediaPosition $position): self
+    /**
+     * Ссылка на медиа модуля Media. Media — универсальный (foundational) модуль, на сущности которого
+     * другим модулям разрешено держать relation на чтение (см. docs/arch.md). cascade: false — Posts
+     * не сохраняет и не меняет Media; fkCreate/indexCreate: false — FK media_id уже создан миграцией
+     * post_media, повторно его не заводим. Запись идёт по колонке mediaId, связь — для eager-load при
+     * сборке URL: лента строит оригинал через MediaUrlService::getOriginalUrl, а getUrls — общий путь
+     * полного набора (оригинал + конверсии, например для FindMediaUrl). Доступ без eager-load вызовет
+     * ленивую подгрузку.
+     */
+    #[BelongsTo(
+        target: Media::class,
+        innerKey: 'media_id',
+        outerKey: 'id',
+        cascade: false,
+        fkCreate: false,
+        indexCreate: false,
+    )]
+    public private(set) Media $media;
+
+    public static function create(Post $post, PostMediaReference $mediaId, MediaPosition $position): self
     {
         $postMedia = new self();
         $postMedia->id = PostMediaId::generate();
         $postMedia->post = $post;
         $postMedia->postId = $post->id;
-        $postMedia->media = $media;
+        $postMedia->mediaId = $mediaId;
         $postMedia->position = $position;
         $postMedia->initializeTimestamps();
 
diff --git a/app/src/Shared/Domain/Locale/LocaleResolver.php b/app/src/Shared/Domain/Locale/LocaleResolver.php
new file mode 100644
index 0000000..876b63d
--- /dev/null
+++ b/app/src/Shared/Domain/Locale/LocaleResolver.php
@@ -0,0 +1,31 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Shared\Domain\Locale;
+
+/**
+ * Доменный сервис разбора локали: сводит запрошенный код к поддерживаемому или к значению по
+ * умолчанию. Поддерживаемые локали и значение по умолчанию приходят готовыми из Infrastructure
+ * (бутлоадер собирает сервис из LocaleConfig), поэтому Application не знает про конфиг.
+ *
+ * Возвращает строку (код локали), а не enum Locale: результат идёт прямо в translator->setLocale,
+ * а потребителю, которому нужен enum, проще обернуть результат в Locale::from() у себя.
+ */
+final readonly class LocaleResolver
+{
+    /**
+     * @param list<string> $supported
+     */
+    public function __construct(
+        private array $supported,
+        private string $default,
+    ) {}
+
+    public function resolve(string $locale): string
+    {
+        return \in_array(needle: $locale, haystack: $this->supported, strict: true)
+            ? $locale
+            : $this->default;
+    }
+}

exec
/bin/zsh -lc 'rg -n "postMedia->media(?!Id)|->media'"\\b|media:|mediaId:\" app/src/Modules/Posts tests/Feature/Modules/Posts tests/Unit/Modules/Posts -S" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 exited 2 in 0ms:
rg: regex parse error:
    (?:postMedia->media(?!Id)|->media\b|media:|mediaId:)
                       ^^^
error: look-around, including look-ahead and look-behind, is not supported

Consider enabling PCRE2 with the --pcre2 flag, which can handle backreferences
and look-around.

exec
/bin/zsh -lc "sed -n '1,360p' tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php && sed -n '1,380p' tests/Feature/Modules/Posts/Repository/PostRepositoryTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Repository;

use App\Modules\Posts\Domain\Collection\PostMediaCollection;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Entity\PostMedia;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\MediaPosition;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostMediaReference;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Shared\Domain\ValueObject\UserId;
use Tests\Feature\Modules\Posts\PostsRepositoryTestCase;

final class PostMediaRepositoryTest extends PostsRepositoryTestCase
{
    public function testStoresAndRestoresMediaOrderedByPosition(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $firstMedia = $this->createMedia($user->id);
        $this->persist($firstMedia);
        $secondMedia = $this->createMedia($user->id);
        $this->persist($secondMedia);

        $post = $this->newPost($user->id);
        $this->persist($post);

        // Намеренно вставляем во втором/первом порядке, чтобы проверить orderBy position ASC.
        $this->persist(PostMedia::create(
            post: $post,
            mediaId: PostMediaReference::fromString($secondMedia->id->value()),
            position: MediaPosition::fromInt(1),
        ));
        $this->persist(PostMedia::create(
            post: $post,
            mediaId: PostMediaReference::fromString($firstMedia->id->value()),
            position: MediaPosition::fromInt(0),
        ));
        $this->cleanOrmHeap();

        $attachments = $this->postMediaRepository()->findByPostId($post->id);

        self::assertInstanceOf(PostMediaCollection::class, $attachments);
        self::assertCount(2, $attachments);
        self::assertSame(0, $attachments->first()?->position->value());
        self::assertSame($firstMedia->id->value(), $attachments->first()?->mediaId->value());

        $restoredPost = $this->postRepository()->findById($post->id);
        self::assertInstanceOf(Post::class, $restoredPost);
        self::assertCount(2, $restoredPost->media);
        self::assertSame(0, $restoredPost->media->first()?->position->value());
    }

    public function testFindByPostIdsBatchesAcrossPostsOrderedByPostAndPosition(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $firstMedia = $this->createMedia($user->id);
        $this->persist($firstMedia);
        $secondMedia = $this->createMedia($user->id);
        $this->persist($secondMedia);

        $firstPost = $this->newPost($user->id);
        $this->persist($firstPost);
        $secondPost = $this->newPost($user->id);
        $this->persist($secondPost);

        $this->persist(PostMedia::create(
            post: $firstPost,
            mediaId: PostMediaReference::fromString($firstMedia->id->value()),
            position: MediaPosition::fromInt(0),
        ));
        $this->persist(PostMedia::create(
            post: $secondPost,
            mediaId: PostMediaReference::fromString($secondMedia->id->value()),
            position: MediaPosition::fromInt(0),
        ));
        $this->cleanOrmHeap();

        $attachments = $this->postMediaRepository()->findByPostIds($firstPost->id, $secondPost->id);

        self::assertInstanceOf(PostMediaCollection::class, $attachments);
        self::assertCount(2, $attachments);
        $postIds = $attachments->map(static fn(PostMedia $postMedia): string => $postMedia->postId->value())->all();
        self::assertContains($firstPost->id->value(), $postIds);
        self::assertContains($secondPost->id->value(), $postIds);
    }

    public function testFindByPostIdsReturnsEmptyForEmptyInput(): void
    {
        $user = $this->createUser();
        $this->persist($user);

        self::assertCount(0, $this->postMediaRepository()->findByPostIds());
    }

    public function testPostMediaBelongsToLazyLoadsPost(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $media = $this->createMedia($user->id);
        $this->persist($media);
        $post = $this->newPost($user->id);
        $this->persist($post);
        $this->persist(PostMedia::create(
            post: $post,
            mediaId: PostMediaReference::fromString($media->id->value()),
            position: MediaPosition::fromInt(0),
        ));
        $this->cleanOrmHeap();

        $restored = $this->postMediaRepository()->findByPostId($post->id)->first();

        self::assertInstanceOf(PostMedia::class, $restored);
        self::assertInstanceOf(Post::class, $restored->post);
        self::assertTrue($post->id->equals($restored->post->id));
    }

    public function testPostMediaIsUniquePerPostAndMedia(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $media = $this->createMedia($user->id);
        $this->persist($media);
        $post = $this->newPost($user->id);
        $this->persist($post);

        $this->entityManager()->persist(PostMedia::create(
            post: $post,
            mediaId: PostMediaReference::fromString($media->id->value()),
            position: MediaPosition::fromInt(0),
        ));
        $this->entityManager()->persist(PostMedia::create(
            post: $post,
            mediaId: PostMediaReference::fromString($media->id->value()),
            position: MediaPosition::fromInt(1),
        ));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    public function testCannotDeleteMediaReferencedByPostMedia(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $media = $this->createMedia($user->id);
        $this->persist($media);
        $post = $this->newPost($user->id);
        $this->persist($post);
        $this->persist(PostMedia::create(
            post: $post,
            mediaId: PostMediaReference::fromString($media->id->value()),
            position: MediaPosition::fromInt(0),
        ));

        $this->expectException(\Throwable::class);

        $this->entityManager()->delete($media);
        $this->entityManager()->run();
    }

    private function newPost(UserId $userId): Post
    {
        return Post::create(
            userId: $userId,
            text: PostText::none(),
            status: PostStatus::Published,
            attachmentType: AttachmentType::Media,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::none(),
        );
    }
}
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Repository;

use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Entity\PostBlock;
use App\Modules\Posts\Domain\Entity\PostLike;
use App\Modules\Posts\Domain\Entity\PostMedia;
use App\Modules\Posts\Domain\Entity\PostMention;
use App\Modules\Posts\Domain\Entity\PostTag;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\BlockReason;
use App\Modules\Posts\Domain\ValueObject\CommentParent;
use App\Modules\Posts\Domain\ValueObject\CommentText;
use App\Modules\Posts\Domain\ValueObject\MediaPosition;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostMediaReference;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Modules\Tags\Domain\Entity\Tag;
use App\Modules\Tags\Domain\ValueObject\TagText;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\UserId;
use Tests\Feature\Modules\Posts\PostsRepositoryTestCase;

final class PostRepositoryTest extends PostsRepositoryTestCase
{
    public function testStoresAndRestoresPostWithValueObjects(): void
    {
        $user = $this->createUser();
        $this->persist($user);

        $lessonId = (string) PostId::generate();
        $post = $this->newPost($user->id, PostStatus::Published, PostText::fromString('Текст записи'));
        $post->setLesson(PostLesson::pointingTo($lessonId));
        $post->incrementLikes();
        $post->incrementReposts();
        $post->incrementComments();
        $this->persist($post);
        $this->cleanOrmHeap();

        $restored = $this->postRepository()->findById($post->id);

        self::assertInstanceOf(Post::class, $restored);
        self::assertTrue($post->id->equals($restored->id));
        self::assertTrue($user->id->equals($restored->userId));
        self::assertSame('Текст записи', $restored->text->value());
        self::assertSame(PostStatus::Published, $restored->status);
        self::assertSame(AttachmentType::Lesson, $restored->attachmentType);
        self::assertSame($lessonId, $restored->lesson->value());
        self::assertTrue($restored->practice->isEmpty());
        self::assertSame(1, $restored->likesCount->value());
        self::assertSame(1, $restored->repostsCount->value());
        self::assertSame(1, $restored->commentsCount->value());
        self::assertFalse($restored->deletion->isDeleted());
    }

    public function testStoresAndRestoresPostWithEmptyNullObjects(): void
    {
        $user = $this->createUser();
        $this->persist($user);

        $post = $this->newPost($user->id, PostStatus::Draft, PostText::none());
        $this->persist($post);
        $this->cleanOrmHeap();

        $restored = $this->postRepository()->findById($post->id);

        self::assertInstanceOf(Post::class, $restored);
        self::assertTrue($restored->text->isEmpty());
        self::assertTrue($restored->lesson->isEmpty());
        self::assertTrue($restored->practice->isEmpty());
        self::assertTrue($restored->original->isEmpty());
        self::assertFalse($restored->deletion->isDeleted());
        self::assertSame(PostStatus::Draft, $restored->status);
        self::assertSame(AttachmentType::None, $restored->attachmentType);
    }

    public function testCounterRoundTripWithNonZeroValue(): void
    {
        $user = $this->createUser();
        $this->persist($user);

        $post = $this->newPost($user->id);
        $post->incrementLikes();
        $post->incrementLikes();
        $post->incrementLikes();
        $this->persist($post);
        $this->cleanOrmHeap();

        $restored = $this->postRepository()->findById($post->id);

        self::assertInstanceOf(Post::class, $restored);
        self::assertSame(3, $restored->likesCount->value());
    }

    public function testDecrementLikesBelowZeroThrows(): void
    {
        $post = $this->newPost(UserId::generate());

        $this->expectException(InvalidDomainValueException::class);

        $post->decrementLikes();
    }

    public function testSoftDeleteRoundTrip(): void
    {
        $user = $this->createUser();
        $this->persist($user);

        $post = $this->newPost($user->id);
        $post->softDelete(new \DateTimeImmutable('2026-06-17 10:00:00'));
        $this->persist($post);
        $this->cleanOrmHeap();

        $restored = $this->postRepository()->findById($post->id);

        self::assertInstanceOf(Post::class, $restored);
        self::assertTrue($restored->deletion->isDeleted());
        self::assertEquals(new \DateTimeImmutable('2026-06-17 10:00:00'), $restored->deletion->value());
    }

    public function testFindByUserIdFiltersStatusAndPaginates(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $other = $this->createUser();
        $this->persist($other);

        $created = [];
        for ($index = 0; $index < 3; $index++) {
            $post = $this->newPost($user->id, PostStatus::Published);
            $this->persist($post);
            $created[] = $post;
        }

        $blocked = $this->newPost($user->id, PostStatus::Blocked);
        $this->persist($blocked);

        // Чужая запись не попадает в выборку получателя.
        $this->persist($this->newPost($other->id, PostStatus::Published));
        $this->cleanOrmHeap();

        $publishedIdsDesc = $this->idsDesc($created);

        $firstPage = $this->postRepository()->findByUserId($user->id, PostStatus::Published, null, 2);
        self::assertSame(\array_slice($publishedIdsDesc, 0, 2), $this->ids($firstPage->all()));

        $lastOnFirstPage = $firstPage->last();
        self::assertInstanceOf(Post::class, $lastOnFirstPage);

        $secondPage = $this->postRepository()->findByUserId($user->id, PostStatus::Published, $lastOnFirstPage->id, 2);
        self::assertSame(\array_slice($publishedIdsDesc, 2), $this->ids($secondPage->all()));

        $emptyPage = $this->postRepository()->findByUserId($user->id, PostStatus::Published, $secondPage->last()?->id, 2);
        self::assertCount(0, $emptyPage);

        // Без фильтра статуса возвращаются и Published, и Blocked.
        self::assertCount(4, $this->postRepository()->findByUserId($user->id, null, null, 10));
        self::assertCount(1, $this->postRepository()->findByUserId($user->id, PostStatus::Blocked, null, 10));
    }

    public function testFindVisibleByUserIdExcludesBlockedAndSoftDeleted(): void
    {
        $user = $this->createUser();
        $this->persist($user);

        $published = $this->newPost($user->id, PostStatus::Published);
        $this->persist($published);
        $draft = $this->newPost($user->id, PostStatus::Draft);
        $this->persist($draft);
        $this->persist($this->newPost($user->id, PostStatus::Blocked));
        $softDeleted = $this->newPost($user->id, PostStatus::Published);
        $softDeleted->softDelete(new \DateTimeImmutable());
        $this->persist($softDeleted);
        $this->cleanOrmHeap();

        // Лента владельца: все статусы, кроме Blocked, и без мягко удалённых.
        $ownerFeed = $this->postRepository()->findVisibleByUserId(
            userId: $user->id,
            status: null,
            excludeStatus: PostStatus::Blocked,
            cursor: null,
            limit: 10,
        );
        self::assertSame(
            $this->idsDesc([$published, $draft]),
            $this->ids($ownerFeed->all()),
        );

        // Чужая лента: только Published.
        $strangerFeed = $this->postRepository()->findVisibleByUserId(
            userId: $user->id,
            status: PostStatus::Published,
            excludeStatus: null,
            cursor: null,
            limit: 10,
        );
        self::assertSame([$published->id->value()], $this->ids($strangerFeed->all()));
    }

    public function testFindByIdsReturnsRequestedPosts(): void
    {
        $user = $this->createUser();
        $this->persist($user);

        $first = $this->newPost($user->id, PostStatus::Published);
        $this->persist($first);
        $second = $this->newPost($user->id, PostStatus::Published);
        $this->persist($second);
        $this->persist($this->newPost($user->id, PostStatus::Published));
        $this->cleanOrmHeap();

        $found = $this->postRepository()->findByIds($first->id, $second->id);

        self::assertCount(2, $found);
        self::assertSame(
            $this->idsDesc([$first, $second]),
            $this->idsDesc($found->all()),
        );
        self::assertCount(0, $this->postRepository()->findByIds());
    }

    public function testFindRepostsOf(): void
    {
        $user = $this->createUser();
        $this->persist($user);

        $original = $this->newPost($user->id, PostStatus::Published);
        $this->persist($original);

        $repost = Post::create(
            userId: $user->id,
            text: PostText::none(),
            status: PostStatus::Published,
            attachmentType: AttachmentType::None,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::pointingTo($original->id->value()),
        );
        $this->persist($repost);
        $this->cleanOrmHeap();

        $reposts = $this->postRepository()->findRepostsOf($original->id);

        self::assertCount(1, $reposts);
        self::assertTrue($reposts->contains(static fn(Post $post): bool => $post->id->equals($repost->id)));
    }

    public function testParentPostIsSetNullWhenOriginalDeleted(): void
    {
        $user = $this->createUser();
        $this->persist($user);

        $original = $this->newPost($user->id, PostStatus::Published);
        $this->persist($original);

        $repost = Post::create(
            userId: $user->id,
            text: PostText::none(),
            status: PostStatus::Published,
            attachmentType: AttachmentType::None,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::pointingTo($original->id->value()),
        );
        $this->persist($repost);

        $this->entityManager()->delete($original);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        $restoredRepost = $this->postRepository()->findById($repost->id);

        self::assertInstanceOf(Post::class, $restoredRepost);
        self::assertTrue($restoredRepost->original->isEmpty());
        self::assertNull($this->postRepository()->findById($original->id));
    }

    public function testCannotDeleteUserReferencedByPost(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $this->persist($this->newPost($user->id));

        $this->expectException(\Throwable::class);

        $this->entityManager()->delete($user);
        $this->entityManager()->run();
    }

    public function testDeletingPostCascadesAllChildren(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $media = $this->createMedia($user->id);
        $this->persist($media);

        $post = $this->newPost($user->id, PostStatus::Published);
        $this->persist($post);

        $tag = Tag::create(text: TagText::fromString('йога'), createdBy: $user->id);
        $this->persist($tag);

        $this->persist(PostMedia::create(
            post: $post,
            mediaId: PostMediaReference::fromString($media->id->value()),
            position: MediaPosition::fromInt(0),
        ));
        $this->persist(PostLike::create(postId: $post->id, userId: $user->id));
        $this->persist(PostMention::create(postId: $post->id, userId: $user->id));
        $this->persist(PostTag::create(postId: $post->id, tagId: $tag->id));
        $block = PostBlock::create(postId: $post->id, reason: BlockReason::fromString('Нарушение'), blockedBy: $user->id);
        $this->persist($block);
        $this->persist(Comment::create(
            postId: $post->id,
            userId: $user->id,
            text: CommentText::fromString('Комментарий'),
            parent: CommentParent::none(),
        ));

        $this->entityManager()->delete($post);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        self::assertCount(0, $this->postMediaRepository()->findByPostId($post->id));
        self::assertFalse($this->postLikeRepository()->existsByPostAndUser($post->id, $user->id));
        self::assertCount(0, $this->postMentionRepository()->findByPostId($post->id));
        self::assertCount(0, $this->postTagRepository()->findByPostId($post->id));
        self::assertNull($this->postBlockRepository()->findById($block->id));
        self::assertCount(0, $this->commentRepository()->findByPostId($post->id, null, 10));
    }

    private function newPost(
        UserId $userId,
        PostStatus $status = PostStatus::Draft,
        PostText|null $text = null,
    ): Post {
        return Post::create(
            userId: $userId,
            text: $text ?? PostText::none(),
            status: $status,
            attachmentType: AttachmentType::None,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::none(),
        );
    }

    /**
     * @param list<Post> $posts
     *
     * @return list<string>
     */
    private function idsDesc(array $posts): array
    {
        $ids = $this->ids($posts);
        \rsort($ids);

        return $ids;
    }

    /**
     * @param list<Post> $posts
     *
     * @return list<string>
     */
    private function ids(array $posts): array
    {
        return \array_map(static fn(Post $post): string => $post->id->value(), $posts);
    }
}

exec
/bin/zsh -lc "sed -n '1,260p' app/src/Modules/Posts/Domain/Collection/PostMediaCollection.php && rg -n \"PostMedia::create\\(|mediaId|post_media\" app/src/Modules/Posts tests/Feature/Modules/Posts tests/Unit/Modules/Posts -S" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Collection;

use App\Modules\Posts\Domain\Entity\PostMedia;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, PostMedia>
 */
final class PostMediaCollection extends TypedCollection {}
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:20:            'mediaIds' => [$firstMedia->id->value()],
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:25:            'mediaIds' => [$secondMedia->id->value()],
tests/Unit/Modules/Posts/Domain/Entity/JoinEntityTest.php:36:        $postMedia = PostMedia::create(
tests/Unit/Modules/Posts/Domain/Entity/JoinEntityTest.php:38:            mediaId: PostMediaReference::fromString($mediaUuid),
tests/Unit/Modules/Posts/Domain/Entity/JoinEntityTest.php:44:        self::assertSame($mediaUuid, $postMedia->mediaId->value());
tests/Feature/Modules/Posts/Http/RepostPostHttpTest.php:50:            ['mediaIds' => [$media->id->value(), $media->id->value()]],
tests/Feature/Modules/Posts/Http/RepostPostHttpTest.php:57:        self::assertSame($media->id->value(), $data['media'][0]['mediaId']);
app/src/Modules/Posts/Presentation/Http/Resource/PostMediaItemResource.php:13:        public string $mediaId,
app/src/Modules/Posts/Presentation/Http/Resource/PostMediaItemResource.php:21:            mediaId: $media->mediaId,
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:51:            'mediaIds' => [$media->id->value()],
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:58:        self::assertSame($media->id->value(), $data['media'][0]['mediaId']);
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:67:            'mediaIds' => [$media->id->value(), $media->id->value()],
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:74:        self::assertSame($media->id->value(), $data['media'][0]['mediaId']);
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:84:            'mediaIds' => [$media->id->value()],
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:94:            'mediaIds' => [$media->id->value()],
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:103:            'mediaIds' => [UserId::generate()->value()],
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:218:            'mediaIds' => ['not-a-uuid'],
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:22:            'mediaIds' => [$media->id->value()],
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:49:            'mediaIds' => [$media->id->value()],
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:65:    private function makeMediaUnavailable(MediaId $mediaId): void
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:67:        $media = $this->getContainer()->get(MediaRepository::class)->findById($mediaId);
app/src/Modules/Posts/Presentation/Http/Filter/CreatePostFilter.php:36:    public array $mediaIds = [];
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php:74:                mediaIds: $createPostFilter->mediaIds,
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php:146:                mediaIds: $repostPostFilter->mediaIds,
app/src/Modules/Posts/Presentation/Http/Filter/RepostPostFilter.php:38:    public array $mediaIds = [];
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:36:        $this->persist(PostMedia::create(
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:38:            mediaId: PostMediaReference::fromString($secondMedia->id->value()),
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:41:        $this->persist(PostMedia::create(
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:43:            mediaId: PostMediaReference::fromString($firstMedia->id->value()),
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:53:        self::assertSame($firstMedia->id->value(), $attachments->first()?->mediaId->value());
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:75:        $this->persist(PostMedia::create(
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:77:            mediaId: PostMediaReference::fromString($firstMedia->id->value()),
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:80:        $this->persist(PostMedia::create(
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:82:            mediaId: PostMediaReference::fromString($secondMedia->id->value()),
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:112:        $this->persist(PostMedia::create(
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:114:            mediaId: PostMediaReference::fromString($media->id->value()),
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:135:        $this->entityManager()->persist(PostMedia::create(
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:137:            mediaId: PostMediaReference::fromString($media->id->value()),
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:140:        $this->entityManager()->persist(PostMedia::create(
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:142:            mediaId: PostMediaReference::fromString($media->id->value()),
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:159:        $this->persist(PostMedia::create(
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:161:            mediaId: PostMediaReference::fromString($media->id->value()),
tests/Feature/Modules/Posts/Repository/PostRepositoryTest.php:311:        $this->persist(PostMedia::create(
tests/Feature/Modules/Posts/Repository/PostRepositoryTest.php:313:            mediaId: PostMediaReference::fromString($media->id->value()),
app/src/Modules/Posts/Domain/Entity/PostMedia.php:21:    role: 'post_media',
app/src/Modules/Posts/Domain/Entity/PostMedia.php:22:    table: 'post_media',
app/src/Modules/Posts/Domain/Entity/PostMedia.php:37:    public private(set) PostMediaReference $mediaId;
app/src/Modules/Posts/Domain/Entity/PostMedia.php:49:     * post_media, повторно его не заводим. Запись идёт по колонке mediaId, связь — для eager-load при
app/src/Modules/Posts/Domain/Entity/PostMedia.php:64:    public static function create(Post $post, PostMediaReference $mediaId, MediaPosition $position): self
app/src/Modules/Posts/Domain/Entity/PostMedia.php:70:        $postMedia->mediaId = $mediaId;
app/src/Modules/Posts/Application/Post/PostContentComposer.php:73:     * @param list<string> $mediaIds
app/src/Modules/Posts/Application/Post/PostContentComposer.php:75:    public function attachMedia(Post $post, array $mediaIds, string $ownerUserId): void
app/src/Modules/Posts/Application/Post/PostContentComposer.php:79:        foreach (\array_values(\array_unique($mediaIds)) as $mediaId) {
app/src/Modules/Posts/Application/Post/PostContentComposer.php:81:                query: new CheckMediaAttachableQuery(mediaId: $mediaId, ownerUserId: $ownerUserId),
app/src/Modules/Posts/Application/Post/PostContentComposer.php:85:                command: new MakeMediaPermanentCommand(userId: $ownerUserId, mediaId: $mediaId),
app/src/Modules/Posts/Application/Post/PostContentComposer.php:88:            $this->entityManager->persist(PostMedia::create(
app/src/Modules/Posts/Application/Post/PostContentComposer.php:90:                mediaId: PostMediaReference::fromString($mediaId),
app/src/Modules/Posts/Application/View/PostViewAssembler.php:267:     * Разрешает ссылку на медиа записи из УЖЕ загруженной сущности Media (relation post_media.media
app/src/Modules/Posts/Application/View/PostViewAssembler.php:284:            mediaId: $postMedia->mediaId->value(),
app/src/Modules/Posts/Application/View/PostMediaItemView.php:13:        public string $mediaId,
app/src/Modules/Posts/Application/Command/RepostPost/RepostPostCommand.php:8: * @param list<string> $mediaIds
app/src/Modules/Posts/Application/Command/RepostPost/RepostPostCommand.php:15:     * @param list<string> $mediaIds
app/src/Modules/Posts/Application/Command/RepostPost/RepostPostCommand.php:23:        public array $mediaIds,
app/src/Modules/Posts/Application/Command/CreatePost/CreatePostCommand.php:8: * @param list<string> $mediaIds
app/src/Modules/Posts/Application/Command/CreatePost/CreatePostCommand.php:15:     * @param list<string> $mediaIds
app/src/Modules/Posts/Application/Command/CreatePost/CreatePostCommand.php:23:        public array $mediaIds,
app/src/Modules/Posts/Application/Command/RepostPost/RepostPostHandler.php:62:            attachmentType: $command->mediaIds === [] ? AttachmentType::None : AttachmentType::Media,
app/src/Modules/Posts/Application/Command/RepostPost/RepostPostHandler.php:69:        $this->composer->attachMedia(post: $post, mediaIds: $command->mediaIds, ownerUserId: $command->authUserId);
app/src/Modules/Posts/Application/Command/CreatePost/CreatePostHandler.php:49:            attachmentType: $command->mediaIds === [] ? AttachmentType::None : AttachmentType::Media,
app/src/Modules/Posts/Application/Command/CreatePost/CreatePostHandler.php:56:        $this->composer->attachMedia(post: $post, mediaIds: $command->mediaIds, ownerUserId: $command->authUserId);

exec
/bin/zsh -lc "rg -n \"UserPublicProfileAssembler\\(|new PostViewAssembler\\(|MediaUrlServiceContract|FindMediaUrlHandler|FindMediaOriginalUrlHandler\" app/src tests -S" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
tests/Kernel/Modules/Media/MediaBootloaderTest.php:8:use App\Modules\Media\Application\Contract\MediaUrlServiceContract;
tests/Kernel/Modules/Media/MediaBootloaderTest.php:24:    public function testMediaUrlServiceContractResolvesToInfrastructureImplementation(): void
tests/Kernel/Modules/Media/MediaBootloaderTest.php:28:        $mediaUrlService = $this->getContainer()->get(MediaUrlServiceContract::class);
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:10:use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlHandler;
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:35:final class FindMediaUrlHandlerTest extends MediaApplicationTestCase
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:253:    private function handler(MediaFileServiceContract $fileService): FindMediaUrlHandler
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:255:        return new FindMediaUrlHandler(
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:8:use App\Modules\Media\Application\Query\FindMediaOriginalUrl\FindMediaOriginalUrlHandler;
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:17:final class FindMediaOriginalUrlHandlerTest extends MediaApplicationTestCase
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:141:    private function handler(MediaFileServiceContract $fileService): FindMediaOriginalUrlHandler
tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php:143:        return new FindMediaOriginalUrlHandler(
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:8:use App\Modules\Media\Application\Query\FindMediaOriginalUrl\FindMediaOriginalUrlHandler;
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:105:        return new UserPublicProfileAssembler(
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:107:            findMediaOriginalUrlHandler: new FindMediaOriginalUrlHandler(
app/src/Modules/User/Infrastructure/Bootloader/UserBootloader.php:7:use App\Modules\Media\Application\Query\FindMediaOriginalUrl\FindMediaOriginalUrlHandler;
app/src/Modules/User/Infrastructure/Bootloader/UserBootloader.php:30:        FindMediaOriginalUrlHandler $findMediaOriginalUrlHandler,
app/src/Modules/User/Infrastructure/Bootloader/UserBootloader.php:33:        return new UserPublicProfileAssembler(
app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php:7:use App\Modules\Media\Application\Query\FindMediaOriginalUrl\FindMediaOriginalUrlHandler;
app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php:31:        private FindMediaOriginalUrlHandler $findMediaOriginalUrlHandler,
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:8:use App\Modules\Media\Application\Contract\MediaUrlServiceContract;
app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:45:final readonly class MediaUrlService implements MediaUrlServiceContract
app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php:12:use App\Modules\Media\Application\Contract\MediaUrlServiceContract;
app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php:30:        MediaUrlServiceContract::class => MediaUrlService::class,
app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/FindMediaOriginalUrlHandler.php:7:use App\Modules\Media\Application\Contract\MediaUrlServiceContract;
app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/FindMediaOriginalUrlHandler.php:23:final readonly class FindMediaOriginalUrlHandler
app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/FindMediaOriginalUrlHandler.php:27:        private MediaUrlServiceContract $mediaUrlService,
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:7:use App\Modules\Media\Application\Contract\MediaUrlServiceContract;
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:22:final readonly class FindMediaUrlHandler
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:26:        private MediaUrlServiceContract $mediaUrlService,
app/src/Modules/Media/Application/Contract/MediaUrlServiceContract.php:24:interface MediaUrlServiceContract
app/src/Modules/Media/README.md:60:  за контрактом `MediaUrlServiceContract` (реализация в `Infrastructure/FileService` читает
app/src/Modules/Media/README.md:188:  `MediaUrlServiceContract` — это технический сервис с поведением, поэтому он живёт в `Infrastructure`
app/src/Modules/Posts/Application/View/PostViewAssembler.php:7:use App\Modules\Media\Application\Contract\MediaUrlServiceContract;
app/src/Modules/Posts/Application/View/PostViewAssembler.php:49:        private MediaUrlServiceContract $mediaUrlService,

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php | sed -n '24,145p' && nl -ba app/src/Modules/Media/Domain/Enum/MediaConversionStatus.php | sed -n '1,40p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
    24	use App\Modules\Media\Domain\ValueObject\MediaPresignedTtl;
    25	use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
    26	use Illuminate\Support\Collection;
    27	
    28	/**
    29	 * Строит URL из УЖЕ загруженного медиа. getUrls — полный набор: оригинал (если не удалён) и все
    30	 * конверсии. Конверсии берутся из связей сущности (imageConversions/videoConversions/audioConversions),
    31	 * поэтому метод не делает запросов в БД — при условии, что связи загружены eager заранее
    32	 * (репозиторий-метод с ->load(...) или ->load('media.imageConversions') у вызывающего модуля).
    33	 * Если связи не загружены, Cycle подгрузит их лениво — это вернёт N+1, поэтому вызывающий обязан
    34	 * передавать медиа с eager-загруженными конверсиями. getOriginalUrl — только оригинал, к связям
    35	 * не обращается вовсе: для потребителей, которым конверсии не нужны (аватар, лента).
    36	 *
    37	 * Срок presigned-ссылки скачивания: значение по умолчанию реализация читает из MediaConfig напрямую
    38	 * (прямая инъекция конфига — класс лежит в Infrastructure), а вызывающий может переопределить его на
    39	 * конкретный вызов. Сервис stateless, поэтому expiresAt всегда считается внутри вызова (не в
    40	 * конструкторе), иначе все наборы URL получили бы один замороженный срок.
    41	 *
    42	 * Не бросает: не финализированное медиа (ещё не ready и не readyOriginalRemoved) -> null. После
    43	 * удаления оригинала original = null, конверсии резолвятся.
    44	 */
    45	final readonly class MediaUrlService implements MediaUrlServiceContract
    46	{
    47	    public function __construct(
    48	        private MediaFileServiceContract $mediaFileService,
    49	        private MediaConfig $mediaConfig,
    50	    ) {}
    51	
    52	    public function getUrls(Media $media, int|null $presignedTtlSeconds = null): MediaUrlsResult|null
    53	    {
    54	        if (!$media->isFinalized()) {
    55	            return null;
    56	        }
    57	
    58	        $resolver = $this->resolverFor(
    59	            visibility: $media->visibility,
    60	            presignedTtlSeconds: $presignedTtlSeconds,
    61	        );
    62	
    63	        return new MediaUrlsResult(
    64	            original: $media->isReady() ? $resolver->resolve(storage: $media->storage, path: $media->path) : null,
    65	            conversions: $this->conversionUrls(media: $media, resolver: $resolver),
    66	        );
    67	    }
    68	
    69	    /**
    70	     * Только ссылка на оригинал, без обращения к связям-конверсиям. Возвращает null, если оригинала
    71	     * нет (медиа не финализировано или оригинал удалён в readyOriginalRemoved) — вызывающий подставит
    72	     * значение по умолчанию. Не трогает imageConversions/videoConversions/audioConversions, поэтому
    73	     * не подгружает их из БД и не подписывает presigned-ссылки для конверсий, которые потребителю
    74	     * (аватар, лента) не нужны.
    75	     */
    76	    public function getOriginalUrl(Media $media, int|null $presignedTtlSeconds = null): MediaUrlResult|null
    77	    {
    78	        if (!$media->isReady()) {
    79	            return null;
    80	        }
    81	
    82	        $resolver = $this->resolverFor(
    83	            visibility: $media->visibility,
    84	            presignedTtlSeconds: $presignedTtlSeconds,
    85	        );
    86	
    87	        return $resolver->resolve(storage: $media->storage, path: $media->path);
    88	    }
    89	
    90	    /**
    91	     * Резолвер URL по контексту медиа: public — прямые URL без срока (TTL не нужен и не валидируется),
    92	     * private — presigned с единым сроком на весь набор URL одного вызова. Переопределение TTL строго
    93	     * по `?? `: null -> значение по умолчанию из конфига; явный 0 или значение вне диапазона ->
    94	     * исключение MediaPresignedTtl (нельзя писать `?:`, иначе явный 0 тихо ушёл бы в значение по
    95	     * умолчанию).
    96	     */
    97	    private function resolverFor(MediaVisibility $visibility, int|null $presignedTtlSeconds): MediaUrlResolver
    98	    {
    99	        if ($visibility === MediaVisibility::Public) {
   100	            return new PublicMediaUrlResolver($this->mediaFileService);
   101	        }
   102	
   103	        $ttl = MediaPresignedTtl::fromInt($presignedTtlSeconds ?? $this->mediaConfig->presignedTtlSeconds);
   104	        $expiresAt = new \DateTimeImmutable()->add(new \DateInterval(\sprintf('PT%dS', $ttl->value())));
   105	
   106	        return new PresignedMediaUrlResolver(mediaFileService: $this->mediaFileService, expiresAt: $expiresAt);
   107	    }
   108	
   109	    private function conversionUrls(Media $media, MediaUrlResolver $resolver): MediaConversionUrlCollection
   110	    {
   111	        $imageUrls = $this->conversionUrlsOf(
   112	            conversions: $media->imageConversions->toBase(),
   113	            kind: MediaConversionKind::Image,
   114	            resolver: $resolver,
   115	        );
   116	        $videoUrls = $this->conversionUrlsOf(
   117	            conversions: $media->videoConversions->toBase(),
   118	            kind: MediaConversionKind::Video,
   119	            resolver: $resolver,
   120	        );
   121	        $audioUrls = $this->conversionUrlsOf(
   122	            conversions: $media->audioConversions->toBase(),
   123	            kind: MediaConversionKind::Audio,
   124	            resolver: $resolver,
   125	        );
   126	
   127	        return new MediaConversionUrlCollection($imageUrls->concat($videoUrls)->concat($audioUrls));
   128	    }
   129	
   130	    /**
   131	     * Строит ссылки конверсий одного вида. Обвязка map одинакова для image/video/audio и различается
   132	     * только видом и типом элемента, поэтому вынесена сюда (раньше — три почти одинаковых map-блока).
   133	     *
   134	     * @param Collection<int, MediaImageConversion|MediaVideoConversion|MediaAudioConversion> $conversions
   135	     * @return Collection<int, MediaConversionUrl>
   136	     */
   137	    private function conversionUrlsOf(
   138	        Collection $conversions,
   139	        MediaConversionKind $kind,
   140	        MediaUrlResolver $resolver,
   141	    ): Collection {
   142	        return $conversions->map(
   143	            fn(MediaImageConversion|MediaVideoConversion|MediaAudioConversion $conversion): MediaConversionUrl
   144	                => $this->conversionUrl(
   145	                    kind: $kind,
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Domain\Enum;
     6	
     7	enum MediaConversionStatus: string
     8	{
     9	    case Processing = 'processing';
    10	    case Ready = 'ready';
    11	    case ProcessingFailed = 'processingFailed';
    12	}

exec
/bin/zsh -lc "nl -ba docs/arch.md | sed -n '150,190p' && nl -ba app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php | sed -n '18,34p' && nl -ba app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php | sed -n '10,22p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
   150	```text
   151	User/Application/SetAvatar
   152	  -> Media/Application/CheckMediaIsImage
   153	  -> User/Domain/User::setAvatar
   154	```
   155	
   156	`Media` не должен знать про аватар. Аватар - это часть `User`.
   157	
   158	### Исключение: `Media` — foundational-модуль
   159	
   160	`Media` — универсальный (foundational) модуль: хранение и раздача файлов нужны почти любому
   161	модулю. Поэтому для него действует осознанное исключение из правила «модули общаются только через
   162	`Application`»: другим модулям разрешено **держать ORM-relation на сущности `Media` (на чтение)** и
   163	**передавать загруженную сущность `Media` в Application-сервисы `Media`**.
   164	
   165	Зачем: чтение списков с вложениями (лента `Posts`) должно грузить медиа и их конверсии вместе с
   166	основной выборкой (`->load('media.imageConversions'...)`), а не разрешать URL поэлементно (N+1).
   167	Для этого `PostMedia` объявляет `#[BelongsTo(target: Media::class, ..., cascade: false, fkCreate:
   168	false)]` и eager-грузит её в репозитории, после чего URL строится из уже загруженной сущности без
   169	обращений в БД: лента берёт только оригинал через `MediaUrlService::getOriginalUrl(Media $media)`
   170	(eager-загруженные конверсии держатся под планируемый показ превью), а полный набор «оригинал +
   171	конверсии» отдаёт `MediaUrlService::getUrls(Media $media)` (путь `FindMediaUrl`).
   172	
   173	Что по-прежнему **запрещено** даже для `Media`: использовать `MediaRepository` или `Media/Infrastructure`
   174	из другого модуля; писать/менять данные `Media` через relation (поэтому `cascade: false`); заводить
   175	кросс-модульный FK ради такой связи (`fkCreate: false` — FK либо уже есть в миграции, либо его нет).
   176	Запись и изменение медиа идут только через Command-сценарии `Media/Application`.
   177	
   178	`Media` должен давать только свои сценарии:
   179	
   180	```text
   181	CreateMedia
   182	DeleteMedia
   183	RemoveMediaOriginal
   184	CheckMediaExists
   185	CheckMediaIsImage
   186	FindMediaUrl
   187	FindMediaOriginalUrl
   188	```
   189	
   190	## Локальный Docker-runtime
    18	use Psr\Log\LoggerInterface;
    19	
    20	/**
    21	 * Удаляет оригинальный объект медиа из целевого бакета, сохраняя конверсии. Без #[Transactional]:
    22	 * сначала идемпотентный deleteObject оригинала (404 → no-op), затем один атомарный persist+run() с
    23	 * переходом в readyOriginalRemoved. Идемпотентен: на уже removed-original — ранний no-op без обращения
    24	 * к S3. На сбое после deleteObject до flush статус остаётся ready, повтор команды довыполнит переход.
    25	 *
    26	 * Осознанный компромисс порядка «удалить в S3 → зафиксировать статус»: пока переход не довыполнен,
    27	 * статус остаётся ready, и любой запрос ссылки на оригинал (FindMediaUrl/FindMediaOriginalUrl/лента/
    28	 * аватар) вернёт ссылку на уже удалённый объект — короткое окно битой ссылки. Команда предполагает
    29	 * повторный вызов при сбое (автоматического реиспуска, как у тяжёлой обработки через outbox, тут нет),
    30	 * поэтому пока не подключена к прямому запуску пользователем; перед подключением к реальному триггеру компромисс
    31	 * пересмотреть (вариант: вынести deleteObject в outbox-шаг после commit-а перехода).
    32	 */
    33	final readonly class RemoveMediaOriginalHandler
    34	{
    10	use App\Modules\Media\Repository\MediaRepository;
    11	use GianTiaga\SpiralCqrs\Attribute\LogOperation;
    12	
    13	/**
    14	 * Best-effort разрешение всех URL медиа по его id: оригинал (если не удалён) и все конверсии.
    15	 * Грузит медиа вместе с конверсиями одним набором запросов и делегирует построение URL в
    16	 * MediaUrlService. Возвращает null, если медиа нет или оно не финализировано, чтобы вызывающий
    17	 * подставил значение по умолчанию (аватар в профиле) без try-catch.
    18	 *
    19	 * Для модулей, которые уже держат сущность Media загруженной (например, лента Posts через relation),
    20	 * есть прямой путь MediaUrlService::getUrls(Media) — без повторной загрузки.
    21	 */
    22	final readonly class FindMediaUrlHandler

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Application/Service/MediaConversionsChecker.php | sed -n '1,80p' && nl -ba app/src/Modules/Media/Repository/MediaImageConversionRepository.php | sed -n '16,40p' && nl -ba app/src/Modules/Media/Repository/MediaVideoConversionRepository.php | sed -n '16,40p' && nl -ba app/src/Modules/Media/Repository/MediaAudioConversionRepository.php | sed -n '16,40p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Application\Service;
     6	
     7	use App\Modules\Media\Domain\ValueObject\MediaId;
     8	use App\Modules\Media\Repository\MediaAudioConversionRepository;
     9	use App\Modules\Media\Repository\MediaImageConversionRepository;
    10	use App\Modules\Media\Repository\MediaVideoConversionRepository;
    11	
    12	/**
    13	 * Отвечает на вопрос «есть ли у медиа хотя бы одна конверсия любого вида (image/video/audio)».
    14	 * Собирает знание обо всех видах конверсий в одном месте, чтобы вызывающий сценарий не зависел от
    15	 * каждого репозитория конверсий по отдельности: при добавлении нового вида конверсии правка остаётся
    16	 * здесь, а не в каждом обработчике.
    17	 *
    18	 * Ленивая проверка с ранним выходом на первой найденной конверсии: existsForMediaId считает строки без
    19	 * гидрации сущностей — до трёх count-запросов на вызов.
    20	 */
    21	final readonly class MediaConversionsChecker
    22	{
    23	    public function __construct(
    24	        private MediaImageConversionRepository $mediaImageConversionRepository,
    25	        private MediaVideoConversionRepository $mediaVideoConversionRepository,
    26	        private MediaAudioConversionRepository $mediaAudioConversionRepository,
    27	    ) {}
    28	
    29	    public function hasAnyConversion(MediaId $mediaId): bool
    30	    {
    31	        return $this->mediaImageConversionRepository->existsForMediaId($mediaId)
    32	            || $this->mediaVideoConversionRepository->existsForMediaId($mediaId)
    33	            || $this->mediaAudioConversionRepository->existsForMediaId($mediaId);
    34	    }
    35	}
    16	{
    17	    public function findByMediaId(MediaId $mediaId): MediaImageConversionCollection
    18	    {
    19	        return new MediaImageConversionCollection(
    20	            $this->select()
    21	                ->where('media_id', $mediaId->value())
    22	                ->orderBy(expression: 'id', direction: 'ASC')
    23	                ->fetchAll(),
    24	        );
    25	    }
    26	
    27	    /**
    28	     * Есть ли у медиа хоть одна image-конверсия. Считает строки без гидрации сущностей — для
    29	     * булевой проверки не нужно поднимать конверсии целиком.
    30	     */
    31	    public function existsForMediaId(MediaId $mediaId): bool
    32	    {
    33	        return $this->select()
    34	            ->where('media_id', $mediaId->value())
    35	            ->count() > 0;
    36	    }
    37	}
    16	{
    17	    public function findByMediaId(MediaId $mediaId): MediaVideoConversionCollection
    18	    {
    19	        return new MediaVideoConversionCollection(
    20	            $this->select()
    21	                ->where('media_id', $mediaId->value())
    22	                ->orderBy(expression: 'id', direction: 'ASC')
    23	                ->fetchAll(),
    24	        );
    25	    }
    26	
    27	    /**
    28	     * Есть ли у медиа хоть одна video-конверсия. Считает строки без гидрации сущностей — для
    29	     * булевой проверки не нужно поднимать конверсии целиком.
    30	     */
    31	    public function existsForMediaId(MediaId $mediaId): bool
    32	    {
    33	        return $this->select()
    34	            ->where('media_id', $mediaId->value())
    35	            ->count() > 0;
    36	    }
    37	}
    16	{
    17	    public function findByMediaId(MediaId $mediaId): MediaAudioConversionCollection
    18	    {
    19	        return new MediaAudioConversionCollection(
    20	            $this->select()
    21	                ->where('media_id', $mediaId->value())
    22	                ->orderBy(expression: 'id', direction: 'ASC')
    23	                ->fetchAll(),
    24	        );
    25	    }
    26	
    27	    /**
    28	     * Есть ли у медиа хоть одна audio-конверсия. Считает строки без гидрации сущностей — для
    29	     * булевой проверки не нужно поднимать конверсии целиком.
    30	     */
    31	    public function existsForMediaId(MediaId $mediaId): bool
    32	    {
    33	        return $this->select()
    34	            ->where('media_id', $mediaId->value())
    35	            ->count() > 0;
    36	    }
    37	}

exec
/bin/zsh -lc "nl -ba docs/reviews/2026-06-30_20-54_uncommitted-diff.md | sed -n '24,62p' && nl -ba docs/rules.md | sed -n '1,20p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
    24	Проверка плана пропущена: план не указан и не найден.
    25	
    26	## Замечания
    27	
    28	### 1. Папка `Application/Service` не описана в архитектуре, хотя ею пользуются
    29	
    30	Новый сервис-помощник `MediaConversionsChecker` положили в каталог `Application/Service`. Решение
    31	само по себе разумное и повторяет уже существующий `MediaTypeResolver`, то есть в коде это устоявшийся
    32	приём. Проблема не в коде, а в рассинхроне с документацией: описание структуры модуля в архитектуре
    33	перечисляет слои `Domain`, `Application` (с под-каталогом `Contract`), `Repository`,
    34	`Infrastructure`, `Presentation`, но про каталог `Application/Service` не упоминает вовсе. Человек,
    35	который сверяет новый код с архитектурой (а это явно предписанный в проекте шаг перед правками),
    36	не найдёт там санкции на такой каталог и будет вынужден догадываться, легитимен ли он.
    37	
    38	Риск практический и проявится при росте команды/числа модулей: каждый следующий автор будет заново
    39	решать, куда класть подобный сервис-помощник (в `Application` корнем, в `Application/Service`, в
    40	`Domain`), и каталоги расползутся по-разному в разных модулях. Это вопрос предсказуемости структуры,
    41	а не работоспособности — поэтому «на усмотрение».
    42	
    43	Технические детали:
    44	
    45	- **Тип:** `docs`
    46	- **Рекомендация:** `на усмотрение автора`
    47	- **Где:** `app/src/Modules/Media/Application/Service/MediaConversionsChecker.php` (и существующий
    48	  `app/src/Modules/Media/Application/Service/MediaTypeResolver.php`); раздел «Модули» / «Структура
    49	  каталогов» в `docs/arch.md`.
    50	- **Что подтверждает проблему:** в `docs/arch.md` структура модуля и дерево каталогов
    51	  (`Modules/Media/...`) не содержат `Application/Service`; при этом в коде уже два таких сервиса.
    52	- **Как исправить:** одной строкой зафиксировать в `docs/arch.md` конвенцию: внутримодульные
    53	  Application-сервисы-помощники без состояния (резолверы/чекеры, которые инкапсулируют несколько
    54	  репозиториев своего модуля для одного use-case-вопроса) живут в `Application/Service`. Код не
    55	  трогать — менять только документацию. Альтернатива (если каталог решат не узаконивать) дороже:
    56	  переносить оба сервиса, поэтому предпочтительна правка доков.
    57	- **Тесты:** не требуются — изменение только документации.
    58	
    59	## Рекомендации
    60	
    61	- **Править обязательно:** —
    62	- **На усмотрение автора:** 1
     1	# Правила проекта
     2	
     3	## Язык
     4	
     5	- Отвечай всегда на русском
     6	- Исключения пишутся на русском языке.
     7	- Логи пишутся на русском языке.
     8	- Комментарии к коду пишутся на русском языке.
     9	- Ошибки, которые отдаются пользователю, пишутся на языке пользователя.
    10	- **Без англицизмов**: в русском тексте — комментариях, докстрингах, сообщениях логов и исключений, документации — не использовать кальки с английского, если есть обычное русское слово. Например: «временный» вместо «транзиентный», «рассылка»/«отправка» вместо «диспатч», «по умолчанию» вместо «дефолтный», «полезные данные» вместо «payload». Имена классов, методов, переменных, типов и прочие технические идентификаторы остаются на английском (`DispatchNotificationJob`, `isTransient()`, `outbox`) — переводу подлежит только человекочитаемый русский текст.
    11	- **Коммиты на русском**: сообщения коммитов (subject, body) пишутся на русском языке. Тип и scope остаются на английском по Conventional Commits (`feat`, `fix`, `refactor` и т.д.), но описание — на русском. Пример: `feat(tenant): добавить управление тенантами`.
    12	
    13	## Качество кода
    14	
    15	- **Ранний возврат**: guard clauses (`throw`/`return`) в начале метода. Инвертировать условие, выбросить исключение первым, happy path без вложенности. Вложенность 2+ уровней `if/else` — красный флаг.
    16	- **Короткие методы**: `handle()` в Handler — не более ~40 строк. Длиннее — выносить в приватные методы.
    17	- **`match` вместо `switch`**: `switch` не используется. Всегда `match`-выражение. Enum — исчерпывающий `match` без `default`.
    18	- **Именованные аргументы**: обязательны при 2+ обычных параметрах и при любом необязательном/булевом параметре. Позиционные — для 1–2 очевидных параметров. Variadic-вызовы и вызовы с unpack (`...$args`) допускают позиционные аргументы, потому что именование ломает читаемость таких API (`sprintf`, `implode` и т.д.). (Проверяется PHPStan)
    19	- **`sprintf()` для строк**: для пользовательских сообщений и строк ошибок. Интерполяция `"{$a}-{$b}"` — только для компактных ключей/идентификаторов. Конкатенация `.` — избегать.
    20	- **Collection-пайплайны**: `->map()`, `->filter()`, `->groupBy()` для чистых трансформаций. `foreach` — только при побочных эффектах.

codex
~ [docs/reviews/2026-06-30_20-54_uncommitted-diff.md](/Users/gian_tiaga/Code/yoga-loka-spiral-2/docs/reviews/2026-06-30_20-54_uncommitted-diff.md:28) — текущее замечание про `Application/Service` корректное, убирать его не надо. Лучше переформулировать: проблема не в коде, а в том, что `docs/arch.md` не описывает место для внутримодульных Application-сервисов вроде `MediaTypeResolver` и `MediaConversionsChecker`.

+ [docs/arch.md](/Users/gian_tiaga/Code/yoga-loka-spiral-2/docs/arch.md:158) — ревью пропустило нарушение правила «без англицизмов» в новом человекочитаемом тексте: `foundational-модуль`, `ORM-relation`, `eager-грузит`. Похожие новые места есть в комментариях `MediaUrlService` и `FindMediaUrlHandler`: `Best-effort`, `stateless`, `try-catch`. Это надо либо заменить русскими словами, либо явно оставить только неизбежные технические названия.

+ [MediaConversionsChecker.php](/Users/gian_tiaga/Code/yoga-loka-spiral-2/app/src/Modules/Media/Application/Service/MediaConversionsChecker.php:29) — проверка считает любую строку конверсии, не только готовую. При наличии `processing` или `processingFailed` конверсии `RemoveMediaOriginal` может удалить оригинал, хотя usable-конверсии нет. Это стоит либо исправить через `existsReadyForMediaId`, либо закрепить тестом/инвариантом, что у `ready`-медиа не бывает неготовых конверсий.

+ [MediaUrlService.php](/Users/gian_tiaga/Code/yoga-loka-spiral-2/app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:109) — та же проблема на выдаче URL: `conversionUrls()` отдаёт ссылки на все конверсии без проверки `MediaConversionStatus::Ready`, хотя enum содержит `processing` и `processingFailed`. Тесты покрывают только готовые конверсии, поэтому этот риск ревью пропустило.

+ [RemoveMediaOriginalHandler.php](/Users/gian_tiaga/Code/yoga-loka-spiral-2/app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php:26) — ревью слишком уверенно говорит, что обязательных проблем нет. В коде прямо зафиксировано окно сбоя: S3-объект удалён, а статус в БД ещё `ready`, автоматического повтора нет. Если команда считается публичным сценарием модуля, это надо вынести в замечание как риск надёжности или явно зафиксировать, что команда пока не подключается к пользовательскому запуску.

? Проверки `make test` и `make phpstan` я не запускал, поэтому фактический зелёный статус тестов и статического анализа по этому кругу подтвердить не могу.
tokens used
155 301
~ [docs/reviews/2026-06-30_20-54_uncommitted-diff.md](/Users/gian_tiaga/Code/yoga-loka-spiral-2/docs/reviews/2026-06-30_20-54_uncommitted-diff.md:28) — текущее замечание про `Application/Service` корректное, убирать его не надо. Лучше переформулировать: проблема не в коде, а в том, что `docs/arch.md` не описывает место для внутримодульных Application-сервисов вроде `MediaTypeResolver` и `MediaConversionsChecker`.

+ [docs/arch.md](/Users/gian_tiaga/Code/yoga-loka-spiral-2/docs/arch.md:158) — ревью пропустило нарушение правила «без англицизмов» в новом человекочитаемом тексте: `foundational-модуль`, `ORM-relation`, `eager-грузит`. Похожие новые места есть в комментариях `MediaUrlService` и `FindMediaUrlHandler`: `Best-effort`, `stateless`, `try-catch`. Это надо либо заменить русскими словами, либо явно оставить только неизбежные технические названия.

+ [MediaConversionsChecker.php](/Users/gian_tiaga/Code/yoga-loka-spiral-2/app/src/Modules/Media/Application/Service/MediaConversionsChecker.php:29) — проверка считает любую строку конверсии, не только готовую. При наличии `processing` или `processingFailed` конверсии `RemoveMediaOriginal` может удалить оригинал, хотя usable-конверсии нет. Это стоит либо исправить через `existsReadyForMediaId`, либо закрепить тестом/инвариантом, что у `ready`-медиа не бывает неготовых конверсий.

+ [MediaUrlService.php](/Users/gian_tiaga/Code/yoga-loka-spiral-2/app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php:109) — та же проблема на выдаче URL: `conversionUrls()` отдаёт ссылки на все конверсии без проверки `MediaConversionStatus::Ready`, хотя enum содержит `processing` и `processingFailed`. Тесты покрывают только готовые конверсии, поэтому этот риск ревью пропустило.

+ [RemoveMediaOriginalHandler.php](/Users/gian_tiaga/Code/yoga-loka-spiral-2/app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php:26) — ревью слишком уверенно говорит, что обязательных проблем нет. В коде прямо зафиксировано окно сбоя: S3-объект удалён, а статус в БД ещё `ready`, автоматического повтора нет. Если команда считается публичным сценарием модуля, это надо вынести в замечание как риск надёжности или явно зафиксировать, что команда пока не подключается к пользовательскому запуску.

? Проверки `make test` и `make phpstan` я не запускал, поэтому фактический зелёный статус тестов и статического анализа по этому кругу подтвердить не могу.
