---
title: Карта расхождений кодовой базы с целевой архитектурой
date: 2026-09-15 17:25
mode: normal
decision_mode: ask_each_time
status: draft
reviewer: none
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  business: []
  references: [docs/references/public-contract.md, docs/references/public-attribute.md, docs/references/integration-event.md, docs/references/reader.md, docs/references/data.md, docs/references/result-dto.md, docs/references/application-contract.md, docs/references/domain-service.md, docs/references/domain-event.md, docs/references/entity.md, docs/references/repository.md, docs/references/cycle-entity.md, docs/references/cycle-repository.md, docs/references/entity-columns.md, docs/references/mapper.md, docs/references/typecast.md, docs/references/migration.md, docs/references/bootloader.md, docs/references/typed-config.md, docs/references/http-middleware.md, docs/references/job-consumer.md, docs/references/console-command.md, docs/references/integration-test.md, docs/references/unit-test.md]
---

# Карта расхождений кодовой базы с целевой архитектурой

## Суть

Задача №1 roadmap `docs/artifacts/roadmaps/2026-09-15_17-16_migraciya-na-celevuyu-arhitekturu.md` — зафиксировать полный перечень отклонений `app/src` и `tests` от `docs/arch.md`, чтобы задачи 2–30 планировались по этому документу без повторного исследования.

Обследовано: 706 PHP-файлов в `app/src` (711 файлов всего, включая 2 шаблона Twig и 3 README), 235 файлов в `tests` (182 файла `*Test.php`), 9 миграций, 14 файлов переводов, 16 секций конфигурации, 16 типизированных конфигов, 25 HTTP-маршрутов.

Ни один модуль сейчас не имеет слоя `Public`. Ни одна доменная сущность не отделена от Cycle. Ни одного Reader и ни одного Data в проекте нет. Это не локальные отступления, а системное расхождение по всем девяти модулям сразу.

## Решение

Результат — карта ниже. Она описывает фактическое состояние (с файловыми ссылками), целевое место каждой части и владельца каждого общего ресурса. Плана реализации она не содержит: порядок шагов задаёт roadmap, детали — планы задач 2–30.

### Запуск и развилки

Запуск неинтерактивный: пользователь прямо запретил вопросы и попросил фиксировать предположения в документе. Поэтому `explore.decision_mode: ask_each_time` из `docs/settings.yaml` заменён прямым указанием пользователя; все развилки закрыты в разделе «Закрытые развилки» с причиной и ссылкой. Каждая из них — предположение карты, которое план соответствующей задачи подтверждает или меняет.

### Как читать карту

Роли из `docs/arch.md:88-105` используются как словарь: `Domain Entity`, `Cycle Entity`, `Domain Repository`, `Cycle Repository`, `Reader`, `Data`, `Result`, `{Name}Contract`, `{Name}Provider`, `{Name}Dto`, `{Name}Mapper`, `{Entity}Columns`, `{Value}Typecast`, `{Name}Config`.

Карта опирается на карточки `docs/references.md` как на образец целевой формы: `public-attribute.md` задаёт форму атрибута доступа, `integration-event.md` — форму события в `Public/Event`, `reader.md` и `data.md` — форму чтения под ответ, `cycle-entity.md`, `mapper.md`, `entity-columns.md`, `cycle-repository.md` — форму хранения, `migration.md` — размещение миграции у владельца. Индекс карточек расширялся во время работы над картой; карта сверена с его текущим состоянием.

Оценка объёма даётся в файлах: «сколько файлов сейчас» и «сколько файлов появится». Это нужно для нарезки задач, а не для оценки сроков.

### Сквозные расхождения: семь классов нарушений

```text
№  Класс нарушения                              Масштаб                 Задача roadmap
1  Нет слоя Public ни у одного модуля           9 модулей               5, 6, 7, 8
2  Domain несёт разметку Cycle                  29 сущностей, 29 таблиц 13, 14
3  Repository — верхнеуровневая папка модуля    29 классов              11, 12
4  Нет Reader и Data, есть View/Assembler/Dto   60 классов формы ответа 15
5  Верхнеуровневый Presentation                 68 файлов в 7 модулях   17, 18
6  Модуль не самодостаточен                     9 миграций, 14 локалей,
                                                2 шаблона, 16 секций,
                                                182 теста                20-24, 27
7  Shared содержит код модулей                  9 файлов                 3
```

#### 1. Нет слоя `Public`

`find app/src/Modules -maxdepth 2 -name Public` не находит ничего. Соседи обращаются друг к другу напрямую в `Application` (инъекция чужого handler в конструктор), а в трёх местах — прямо в чужой `Domain`:

- `app/src/Modules/Posts/Domain/Entity/PostMedia.php:66-70` — ORM-relation `BelongsTo(target: App\Modules\Media\Domain\Entity\Media::class)`;
- `app/src/Modules/Posts/Application/Notification/NotificationContentBuilder.php`, `PostNotifier.php`, `PostNotificationType.php` — импорт `App\Modules\Notifications\Domain\ValueObject\*` и `Domain\Enum\NotificationChannel`;
- `app/src/Shared/Application/View/MediaConversionView.php` и `app/src/Shared/Presentation/Http/Resource/MediaConversionResource.php` — импорт четырёх enum из `App\Modules\Media\Domain\Enum`.

Полный перечень межмодульных связей — в разделе «Межмодульные вызовы».

#### 2. Domain несёт разметку Cycle

Все 29 доменных сущностей помечены `#[Entity(role:, table:, repository:, typecast: [Typecast::class, ValueObjectCast::class])]` и `#[Column]`, ссылаются на класс своего Repository и на свои `*Typecast` из `Infrastructure/Cycle`. Общий трейт `app/src/Shared/Domain/Trait/HasTimestamps.php` импортирует `Cycle\Annotated\Annotation\Column` и добавляет колонки `created_at`/`updated_at` в 27 сущностей — нарушение и направления зависимостей `Shared/Domain`, и правила «сущность не содержит атрибуты Cycle» (`docs/arch.md:119`).

Потребуется на каждую сущность: `Cycle{Name}Entity`, `{Name}Mapper`, `{Entity}Columns`; typecast-классы переезжают как есть.

```text
Модуль         Сущностей  Колонок(без timestamps)  Существующих Typecast  Relations
Access                 4                        10                     0  нет
Auth                   3                        19                     4  нет
Media                  5                        54                     4  3 HasMany + 4 BelongsTo (внутри модуля)
Notifications          3                        19                     5  нет
Outbox                 1                        10                     4  нет
Posts                  9                        48                    12  2 HasMany + 1 BelongsTo внутри + 1 BelongsTo в Media
Tags                   1                         3                     0  нет
User                   3                        23                    10  нет
ИТОГО                 29                       186                    39
```

Существующие 39 typecast-классов реализуют `App\Shared\Infrastructure\Cycle\ColumnValueTypecast` и переезжают в `Infrastructure/Persistence/Cycle/Typecast/` своего модуля без изменения логики. Новые Typecast сверх них не нужны: все нестандартные преобразования уже описаны.

Карточка `docs/references/cycle-entity.md` требует, чтобы `#[Entity(table:)]` ссылался на `{Entity}Columns::TABLE`, а `#[Column(name:)]` — на константы того же класса. Значит `{Entity}Columns` создаётся для всех 29 таблиц (29 новых классов), и уже существующие имена колонок из миграций становятся их константами. Карточка `docs/references/reader.md:119` дополнительно требует у Cycle Entity константу `ROLE`.

#### 3. Repository — верхнеуровневая папка модуля

29 классов лежат в `Modules/{M}/Repository/` — ни в `Domain`, ни в `Infrastructure`. Доменных интерфейсов нет ни одного: Cycle-класс и есть «репозиторий» с точки зрения приложения, а связывает их атрибут `repository:` на доменной сущности (обратная зависимость `Domain -> Repository -> Cycle`).

Два базовых класса:
- `App\Shared\Infrastructure\Cycle\AbstractRepository` (`app/src/Shared/Infrastructure/Cycle/AbstractRepository.php`) — используют 19 репозиториев: Media (5), Posts (9), Notifications (3), Outbox (1), Tags (1);
- `Cycle\ORM\Select\Repository` напрямую — 10 репозиториев: Access (4), Auth (3), User (3). У них нет `when()`/`cursorById()`.

Массовая запись в проекте одна: `app/src/Modules/Notifications/Infrastructure/Persistence/NotificationBulkWriter.php` (`implements NotificationBulkWriterContract, SetBasedWrite`, один `UPDATE notifications SET read_at`). Она уже оформлена отдельным портом и требованию задачи 12 соответствует; переезжает в `Notifications/Infrastructure/Persistence/Cycle/`. Других классов с маркером `App\Shared\Infrastructure\Database\SetBasedWrite` в проекте нет.

#### 4. Нет Reader и Data

`{Name}Reader`, `{Name}Data`, `Application/Contract` c портом чтения — отсутствуют полностью. Форму ответа собирают 60 классов: `Application/View/*` (16), `Application/Dto/*` (39), `Application/Service|Profile/*Assembler|Factory` (2), `Shared/Application/View/*` (3). Детали — в разделе «Формы ответа».

#### 5. Верхнеуровневый `Presentation`

68 файлов в семи модулях (у User и Tags и Access `Presentation` нет). `docs/arch.md:84` требует, чтобы верхнеуровневого `Presentation` и `Infrastructure/Spiral/Presentation` не было.

```text
Модуль         Presentation  состав
Auth                     14  Http/Controller(1) Http/Filter(7) Http/Middleware(2) Http/Resource(3) Job(1) views(1)
Media                     1  Job(1)
Notifications            19  Http/Controller(3) Http/Filter(7) Http/Resource(6) Job(3)
Outbox                    2  Console(1) Job(1)
Posts                    21  Http/Controller(2) Http/Filter(15) Http/Resource(4)
System                    9  Console(2) Exception(1) Http(5) Temporal(1) + views(1)
```

#### 6. Модуль не самодостаточен

Миграции, переводы, шаблоны, секции конфигурации, типизированные конфиги и тесты лежат вне модулей. Bootloader есть у шести модулей, нет у Access, Tags, User. Разделы «Владение …» ниже перечисляют владельца каждой единицы.

#### 7. Shared содержит код модулей

Девять файлов и один bootloader:

```text
app/src/Shared/Application/View/MediaView.php                        -> Media/Public/Dto/MediaDto
app/src/Shared/Application/View/MediaOriginalView.php                -> Media/Public/Dto/MediaOriginalDto
app/src/Shared/Application/View/MediaConversionView.php              -> Media/Public/Dto/MediaConversionDto
app/src/Shared/Presentation/Http/Resource/MediaResource.php          -> копия в Http/Resource каждого потребителя
app/src/Shared/Presentation/Http/Resource/MediaOriginalResource.php  -> то же
app/src/Shared/Presentation/Http/Resource/MediaConversionResource.php-> то же
app/src/Shared/Domain/ValueObject/TagId.php                          -> Tags/Domain/ValueObject/TagId
app/src/Shared/Domain/Trait/HasTimestamps.php                        -> разделяется: домен остаётся, #[Column] уходит в Cycle Entity
app/src/Shared/Infrastructure/Framework/Bootloader/OpenApiBootloader.php -> System (импортирует App\Modules\System\Presentation\Console\*)
```

`MediaConversionView` и `MediaConversionResource` импортируют `MediaConversionKind`, `MediaImageConversionType`, `MediaVideoConversionType`, `MediaAudioConversionType` из `Media\Domain\Enum`. Эти четыре enum переезжают в `Media/Public/Enum`.

Потребители `Shared\Application\View\Media*`: `Media/Application/Dto/MediaUrlsResult.php` (производитель, метод `toView()`), `Posts/Application/View/{AuthorView,PostView,PostViewAssembler}.php`, `User/Application/Dto/UserPublicProfileView.php`, `User/Application/Profile/UserPublicProfileAssembler.php`, `Notifications/Application/View/{NotificationActorView,NotificationViewAssembler}.php`, `Notifications/Application/Dto/RealtimeMedia{,Original,Conversion}Payload.php`, `Shared/Presentation/Http/Resource/MediaResource.php`.

Потребители `Shared\Presentation\Http\Resource\MediaResource`: `Posts/Presentation/Http/Resource/PostResource.php`, `Posts/Presentation/Http/Resource/AuthorResource.php`, `Notifications/Presentation/Http/Resource/NotificationActorResource.php`.

### Направления зависимостей: где они нарушены

Проверка — по импортам в слоях `Domain`, `Application`, `Public`.

```text
Импорт                               Слой            Файлов  Замена
Cycle\Annotated\*, Cycle\ORM\Parser  Domain              29  Cycle Entity + Mapper (задачи 13, 14)
Cycle\ORM\EntityManagerInterface     Application         31  Domain Repository (задача 11)
Psr\Log\LoggerInterface              Application         35  порт журнала или атрибут #[LogOperation]
GianTiaga\SpiralCqrs\* (84 импорта) Application         52  допустимо: нейтральный контракт своего пакета
Illuminate\Support\Collection        Application          3  TypedCollection своего модуля
Spiral\Translator\TranslatorInterface Application         2  порт в Application/Contract (задача 16)
Ramsey\Uuid                          Domain               2  допустимо: библиотека без фреймворка
Cycle\Annotated\Annotation\Column    Shared/Domain        1  HasTimestamps разделяется
```

Пофайлово по критичным двум:

`Spiral` в Application (2 файла, запрещено `docs/arch.md:170`):
- `app/src/Modules/Auth/Application/Command/SendLoginCode/SendLoginCodeHandler.php`;
- `app/src/Modules/Posts/Application/Notification/NotificationContentBuilder.php`.

`Cycle\ORM\EntityManagerInterface` в Application (31 файл): Auth 3 (`CompleteRegistrationHandler`, `RequestLoginCodeHandler`, `ResolveLoginCodeHandler`), Media 7 (все семь Command handler модуля), Notifications 6, Posts 13 (11 Command handler + `CommentComposer` + `PostContentComposer`), Tags 1 (`ResolveTagsHandler`), User 1 (`CreateUserHandler`).

`Illuminate\Support\Collection` в Application: `Auth/Application/View/SessionViewAssembler.php`, `Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php`, `Media/Application/Command/ProcessMedia/ProcessMediaHandler.php`. В `Shared/Domain` он используется законно как основа `TypedCollection` и `CursorSlice`.

Отдельно: `app/src/Shared/Domain/Exception/DomainTranslatableException.php` импортирует `GianTiaga\SpiralApiErrors\Exception\TranslatableException`. Это нейтральный контракт собственного Composer-пакета, `docs/arch.md:180` такой импорт в `Public` разрешает явно, а для `Domain` — нет. Карта считает его допустимым исключением (см. «Закрытые развилки», п. 7).

### Формы ответа: что заменяется на Reader, Data и Result

Целевая схема (`docs/arch.md:230-256`): `Repository -> Entity`, `Reader -> Data`, `Handler -> Result`; промежуточной read-модели между Entity/Data и Result нет.

Что возвращают сегодня 57 handler-ов:

```text
Возврат                          Handler-ов  Пример
{Action}Result рядом со сценарием       10   VerifyLoginCodeResult, CreateUserResult, UnreadCountResult
void                                    18   LogoutHandler, DeletePostHandler
Application/Dto                         14   IssuedTokenPair, MediaResult, UserPublicProfileView
Application/View                         8   PostView, CommentView, SessionViewCollection, NotificationView
Domain Entity                            2   RegisterNotificationDeviceTokenHandler, RemoveNotificationDeviceTokenHandler
Domain ValueObject                       1   GetAudioWaveformHandler -> MediaWaveform
bool / int                               4   CheckMediaExists, CheckMediaIsImage, CheckUsersExist, RelayOutbox
```

Классы формы ответа, подлежащие замене (60 файлов):

```text
Auth/Application/View/SessionView.php, SessionViewAssembler.php, SessionViewCollection.php
Auth/Application/Dto/IssuedTokenPair.php
Auth/Application/Command/ResolveLoginCode/LoginCodeResolution.php, LoginCodeOutcome.php
Posts/Application/View/PostView.php, PostViewCollection.php, PostViewAssembler.php,
  CommentView.php, CommentViewCollection.php, CommentViewAssembler.php, AuthorView.php, TagView.php
Notifications/Application/View/NotificationView.php, NotificationViewCollection.php,
  NotificationViewAssembler.php, NotificationActionView.php, NotificationActorView.php
Notifications/Application/Service/NotificationSettingsViewFactory.php
Notifications/Application/Dto/NotificationSettingView.php, NotificationSettingViewCollection.php (+12 payload-DTO остаются)
Media/Application/Dto/* (20 файлов: часть -> Public/Dto, часть -> Application/Result, часть остаётся портовыми DTO)
User/Application/Dto/UserAuthView.php, UserPublicProfileView.php, UserPublicProfileCollection.php
User/Application/Profile/UserPublicProfileAssembler.php
Tags/Application/Dto/TagTextCollection.php
Shared/Application/View/MediaView.php, MediaOriginalView.php, MediaConversionView.php
```

Какие Reader и Data понадобятся. Правило `docs/references/reader.md:9`: Reader заводится только там, где ответу нужен признак вне агрегата.

```text
Модуль         Reader                     Data                          Зачем (признак вне агрегата)
Posts          PostFeedReader             PostPageData, PostData        страница ленты: свои поля + mediaIds + tagIds
Posts          PostViewerReader           PostViewerFlagsData           likedByMe по набору записей
Posts          CommentReader              CommentPageData, CommentData  страницы комментариев и ответов
Posts          CommentViewerReader        CommentViewerFlagsData        likedByMe по набору комментариев
Auth           SessionReader              SessionData, SessionDataColl. список сессий: группировка токенов по session_id
Notifications  NotificationReader         NotificationPageData          страница уведомлений
Notifications  NotificationSettingReader  NotificationSettingData       настройки: персональные строки поверх реестра видов
Media          MediaUrlReader             MediaUrlData                  пакетное чтение оригинала и конверсий по набору id
User           —                          —                             ответу хватает полей агрегата User
Tags           —                          —                             ответу хватает полей агрегата Tag
Access         —                          —                             сценариев чтения под ответ нет
Outbox         —                          —                             HTTP-ответов нет
System         —                          —                             своих таблиц нет
```

`docs/references/reader.md:143` требует «разные условия показа — разные методы»: `PostRepository::findVisibleByUserId()` с параметрами `status`/`excludeStatus` (`app/src/Modules/Posts/Repository/PostRepository.php:78`) разворачивается в два метода Reader и два Query — «своя лента» и «чужая лента».

Пять Command handler-ов Posts вызывают ассемблер внутри своей транзакции и возвращают View: `CreatePostHandler`, `PublishPostHandler`, `RepostPostHandler`, `CommentPostHandler`, `ReplyCommentHandler`. `docs/arch.md:252` это запрещает: полную форму читает отдельный Query. Поведение HTTP при этом менять нельзя, поэтому контроллер после команды вызывает Query — это меняет внутренний поток, а не ответ.

---

### Модуль Access

`app/src/Modules/Access`, 20 файлов, только `Domain` (16) и `Repository` (4).

Состояние: модуль не используется продуктивным кодом. Ссылок `App\Modules\Access` вне самого модуля в `app/src` нет; единственные потребители — `tests/Unit/Modules/Access/Domain/AccessDomainTest.php` и `tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php`. Утверждение roadmap подтверждено.

```text
Сейчас                                   Цель
Domain/Entity/{Role,Permission,           Domain/Entity/* без разметки Cycle
  RolePermission,UserRole}.php            + Infrastructure/Persistence/Cycle/Entity/Cycle*Entity (4)
                                          + Mapper/*Mapper (2: Role, UserRole)
                                          + Columns/*Columns (4)
Domain/ValueObject/* (6)                  без изменений
Domain/Enum/RoleName.php                  Domain/Enum + Public/Enum (нужен соседям)
Domain/Enum/PermissionName.php            Public/Enum/PublicPermission (объявляется в маршрутах соседей)
Domain/Collection/* (4)                   без изменений
Repository/RoleRepository.php             Domain/Repository/RoleRepository (интерфейс)
Repository/PermissionRepository.php         + Infrastructure/Persistence/Cycle/Repository/CycleRoleRepository
Repository/RolePermissionRepository.php   RolePermission — внутренняя сущность агрегата Role
Repository/UserRoleRepository.php         UserRole — корень агрегата UserRole
нет                                       Public/Attribute/RequiresPermission (docs/references/public-attribute.md)
нет                                       Public/Contract/AccessContract + Public/Dto
нет                                       Application/Query/CheckPermission/{Query,Handler,Result}
нет                                       Infrastructure/Spiral/Bootloader/AccessBootloader
нет                                       Infrastructure/Spiral/PublicApi/AccessProvider
нет                                       Infrastructure/Persistence/Cycle/Migration/
нет                                       Tests/
```

Все четыре репозитория возвращают Entity, коллекции Entity или `bool` — роли Repository соответствуют, Reader не требуется. Массовых операций нет.

Владение: таблицы `roles`, `permissions`, `role_permissions`, `user_roles`; миграция `app/database/migrations/20260613.143902_0_create_access_domain_tables.php`. В ней межмодульный FK `user_roles.user_id -> users.id`, который по `docs/arch.md:218` снимается. Своего файла переводов и своей секции конфигурации у модуля нет.

Объём: 20 файлов сейчас, ориентировочно +25 новых при переезде; модуль подключается к Kernel впервые.

---

### Модуль Auth

`app/src/Modules/Auth`, 81 файл: Application 30, Domain 22, Infrastructure 12, Presentation 14, Repository 3.

```text
Сейчас                                             Цель
Domain/Entity/{AuthToken,LoginCode,                Domain/Entity/* чистые + Cycle*Entity(3) + Mapper(3) + Columns(3)
  RegistrationTicket}.php
Domain/ValueObject/* (17), Enum(1), Collection(1)  без изменений
Repository/{AuthToken,LoginCode,                   Domain/Repository/*(3) + Infrastructure/Persistence/Cycle/
  RegistrationTicket}Repository.php                  Repository/Cycle*Repository(3)
Application/Command/* (19)                         без изменений, кроме ухода EntityManager и Spiral
Application/Query/GetUserSessions/* (2)            + GetUserSessionsResult; чтение через SessionReader
Application/Contract/* (4)                         без изменений
Application/Dto/IssuedTokenPair.php                Application/Result/IssuedTokenPair (переиспользуемая часть)
Application/Message/LoginCodeRequested.php         Public/Event/LoginCodeRequested
Application/View/* (3)                             Application/Data/SessionData + GetUserSessionsResult
Infrastructure/Auth/CycleTokenStorage.php          делится надвое: Infrastructure/Spiral/Auth/SpiralTokenStorage
                                                   + Infrastructure/Persistence/Cycle/AuthTokenStore
Infrastructure/Auth/{AuthTokenView,AuthenticatedUser,
  UserActorProvider,RandomTokenGenerator}.php      Infrastructure/Spiral/Auth/
Infrastructure/Hash/HmacSecretHasher.php           Infrastructure/Spiral/Auth/
Infrastructure/Mail/SpiralLoginCodeMailer.php      Infrastructure/Spiral/Mail/
Infrastructure/Cycle/*Typecast.php (4)             Infrastructure/Persistence/Cycle/Typecast/
Infrastructure/Bootloader/AuthBootloader.php       Infrastructure/Spiral/Bootloader/
Presentation/Http/Controller/AuthController.php    Infrastructure/Spiral/Http/Controller/
Presentation/Http/Filter/* (7)                     Infrastructure/Spiral/Http/Filter/
Presentation/Http/Middleware/{AuthContextAttribute,
  RequireAuthenticated}Middleware.php              Infrastructure/Spiral/Http/Middleware/ + перестают импортироваться Posts
Presentation/Http/Resource/* (3)                   Infrastructure/Spiral/Http/Resource/
Presentation/Job/SendLoginCodeJob.php              Infrastructure/Spiral/Job/
Presentation/views/login-code.twig                 Infrastructure/Spiral/Resources/views/
нет                                                Public/Contract/SessionContract + Public/Dto
                                                   + Public/Attribute/AuthenticatedRoute
нет                                                Infrastructure/Spiral/PublicApi/
нет                                                Infrastructure/Persistence/Cycle/Migration/
нет                                                Tests/
```

`CycleTokenStorage` (`app/src/Modules/Auth/Infrastructure/Auth/CycleTokenStorage.php`) реализует одновременно `Spiral\Auth\TokenStorageInterface` и `AuthTokenStorageContract` и сам управляет `EntityManagerInterface`. `docs/arch.md:161` требует разделить такой класс на два адаптера.

Три сущности — независимые корни агрегата, relations нет, внутренних сущностей агрегата нет. Все три репозитория возвращают Entity/коллекции — роли соответствуют.

Владение: таблицы `auth_tokens`, `auth_login_codes`, `auth_registration_tickets`; миграции `20260615.141700_0_create_auth_domain_tables.php` и `20260616.180010_0_add_device_to_auth_tokens.php`; переводы `app/locale/{ru,en}/auth.php` (8 ключей, все используются); шаблон `Presentation/views/login-code.twig`; секция `app/config/mailer.php` и `MailerConfig` (почта входа — Auth по `docs/arch.md:302`). Своей секции `auth.php` нет: транспорт и хранилище токенов настраиваются кодом в bootloader.

Объём: 81 файл сейчас, +20 новых (Cycle Entity, Mapper, Columns, интерфейсы Repository, Reader, Data, Public).

---

### Модуль Media

`app/src/Modules/Media`, 129 файлов: Application 58, Domain 45, Infrastructure 20, Presentation 1, Repository 5. Своего HTTP у модуля нет.

```text
Сейчас                                       Цель
Domain/Entity/Media.php (корень)             Domain/Entity/Media чистая + Cycle Entity + Mapper + Columns
Domain/Entity/Media{Image,Video,Audio}       внутренние сущности агрегата Media
  Conversion.php, MediaMultipartUpload.php   -> Cycle Entity + Columns, сохраняются вместе с корнем
Domain/{ValueObject(25),Enum(9),Collection(6)} 4 enum конверсий -> дублируются в Public/Enum
Repository/MediaRepository.php               Domain/Repository/MediaRepository + CycleMediaRepository
Repository/Media{Image,Video,Audio}          схлопываются в CycleMediaRepository (внутренние сущности)
  ConversionRepository.php,                  existsReadyForMediaId -> MediaUrlReader/Data
  MediaMultipartUploadRepository.php
Application/Command/* (14)                   без изменений, EntityManager уходит
Application/Query/* (13)                     +{Action}Result у каждого; FindMediaUrl(s) -> через Reader
Application/Contract/* (6)                   без изменений; MediaUrlServiceContract перестаёт принимать Entity Media
Application/Dto/* (20)                       разделяются: Public/Dto (MediaDto, MediaOriginalDto,
                                             MediaConversionDto, MediaUrlsDto), Application/Result
                                             (MediaResult, RequestMediaUploadResult), портовые DTO остаются
Application/Service/MediaTypeResolver.php    Domain/Service/
Application/Service/MediaConversionsChecker.php Domain/Service/ либо метод Repository агрегата
Application/Exception/* (2)                  без изменений
Application/Message/MediaUploaded.php        Public/Event/MediaUploaded
Infrastructure/FileService/* (13)            Infrastructure/Storage/ (S3, URL, planner) и
                                             Infrastructure/Client/ либо Infrastructure/Ffmpeg (процессоры)
Infrastructure/Cycle/*Typecast.php (4)       Infrastructure/Persistence/Cycle/Typecast/
Infrastructure/Exception/* (2)               рядом с соответствующей границей
Infrastructure/Bootloader/MediaBootloader.php Infrastructure/Spiral/Bootloader/
Presentation/Job/ProcessMediaJob.php         Infrastructure/Spiral/Job/
нет                                          Public/Contract/MediaContract, Public/Dto, Public/Enum, Public/Event
нет                                          Infrastructure/Spiral/PublicApi/MediaProvider
нет                                          Infrastructure/Persistence/Cycle/Migration/
нет                                          Infrastructure/Spiral/Resources/locale/
нет                                          Tests/
```

Media — единственный модуль, к которому `docs/arch.md:213` применяет правило владения данными «без исключений». Сегодня оно нарушено с трёх сторон: relation `PostMedia -> Media`, eager-load `->load('media.imageConversions')` в `app/src/Modules/Posts/Repository/PostMediaRepository.php`, передача Entity `Media` в `MediaUrlServiceContract::getUrls()` из `app/src/Modules/Posts/Application/View/PostViewAssembler.php`, и FK `post_media.media_id -> media.id ON DELETE RESTRICT` и `users.avatar_media_id -> media.id RESTRICT` в миграциях.

Владение: таблицы `media`, `media_image_conversions`, `media_video_conversions`, `media_audio_conversions`, `media_multipart_uploads`; миграции `20260521.184100_0_create_media_domain_tables.php`, `20260620.222100_0_create_media_audio_conversions_table.php`; переводы `app/locale/{ru,en}/media.php` (23 ключа); секции `app/config/media.php` целиком и три бакета `media-upload`, `media-private`, `media-public` из `app/config/storage.php`; конфиги `MediaConfig` (целиком) и `StorageConfig` (частично).

Объём: 129 файлов сейчас, +30 новых.

---

### Модуль Notifications

`app/src/Modules/Notifications`, 129 файлов: Application 59, Domain 23, Infrastructure 14, Presentation 19, Repository 3.

```text
Сейчас                                        Цель
Domain/Entity/{Notification,NotificationSetting,
  NotificationDeviceToken}.php                 три независимых корня + Cycle Entity(3) + Mapper(3) + Columns(3)
Domain/{ValueObject(14),Enum(3),Collection(3)} часть enum и VO дублируется примитивами в Public
Repository/* (3)                               Domain/Repository/*(3) + Cycle*Repository(3)
Application/Command/* (17)                     Command handler перестают возвращать Entity и View
Application/Query/* (8)                        +{Action}Result у GetNotificationSettings
Application/Contract/* (7)                     NotificationSenderContract, NotificationTypeDefinition,
                                               NotificationTypeRegistryContract -> Public/Contract;
                                               Centrifugo, Fcm, BulkWriter, OnlinePresence остаются
Application/Dto/* (14)                         NotificationSettingView* -> Application/Data + Result;
                                               payload-DTO остаются рядом со своими портами
Application/View/* (5)                         Application/Data/NotificationData + ListNotificationsResult
Application/Service/NotificationSettingsViewFactory.php растворяется в Query handler + NotificationSettingReader
Application/Message/* (3)                      Public/Event/{NotificationRequested,
                                               NotificationPushRequested,NotificationRealtimeRequested}
Application/NotificationSender.php (в корне)   Infrastructure/Spiral/PublicApi/NotificationSenderProvider
Application/Exception/* (3)                    без изменений
Infrastructure/Centrifugo/* (4)                Infrastructure/Client/
Infrastructure/Push/KreaitFcmPushSender.php    Infrastructure/Client/
Infrastructure/Registry/NotificationTypeRegistry.php Infrastructure/Spiral/Registry/
Infrastructure/Persistence/NotificationBulkWriter.php Infrastructure/Persistence/Cycle/
Infrastructure/Cycle/*Typecast.php (5)         Infrastructure/Persistence/Cycle/Typecast/
Infrastructure/Exception/CentrifugoPresenceException.php рядом с Client
Infrastructure/Bootloader/NotificationsBootloader.php Infrastructure/Spiral/Bootloader/
Presentation/Http/* (16)                       Infrastructure/Spiral/Http/{Controller,Filter,Resource}/
Presentation/Job/* (3)                         Infrastructure/Spiral/Job/
нет                                            Public/{Contract,Dto,Event,Enum}
нет                                            Infrastructure/Persistence/Cycle/Migration/
нет                                            Infrastructure/Spiral/Resources/locale/
нет                                            Tests/
```

Отдельная находка вне списка roadmap: восемь HTTP-маршрутов Notifications не объявляют ни одного middleware, а `app/src/Modules/Notifications/Presentation/Http/Filter/NotificationRecipientFilter.php` читает `authUserId` из атрибута запроса, который на этих маршрутах никто не выставляет. Тест `tests/Feature/Modules/Notifications/Http/NotificationHttpTest.php:57` подставляет атрибут напрямую, поэтому проверка проходит. Задача 8 закрывает это естественным образом: маршрут получает публичный атрибут доступа. Карта фиксирует, что «поведение не меняется» здесь означает «поведение приводится в соответствие с уже заявленным требованием сессии», и это надо отметить в плане задачи 8.

`NotificationBulkWriter` — единственная санкционированная массовая запись проекта, оформлена по требованию задачи 12 уже сейчас.

Владение: таблицы `notifications`, `notification_settings`, `notification_device_tokens`; миграция `20260613.130000_0_create_notification_domain_tables.php`; переводы `app/locale/{ru,en}/notifications.php` (5 ключей); секции `app/config/centrifugo.php` и `app/config/push.php`; конфиги `CentrifugoConfig`, `PushConfig`. Тексты самих уведомлений принадлежат Posts (`app.posts.notification.*`).

Объём: 129 файлов сейчас, +25 новых.

---

### Модуль Outbox

`app/src/Modules/Outbox`, 58 файлов: Application 19, Domain 13, Infrastructure 20, Presentation 2, Repository 1.

```text
Сейчас                                     Цель
Domain/Entity/StoredOutboxEvent.php        корень агрегата + Cycle Entity + Mapper + Columns
Domain/{ValueObject(10),Enum(1),Collection(1)} без изменений
Repository/OutboxEventRepository.php       Domain/Repository/ + Infrastructure/Persistence/Cycle/Repository/
Application/Command/* (4)                  RelayOutboxHandler -> RelayOutboxResult вместо int
Application/Contract/* (8)                 OutboxEventStoreContract, OutboxJobRegistryContract,
                                           OutboxMessageLoaderContract -> Public/Contract;
                                           Relay/Sleeper/Serializer/LoopControl остаются внутренними
Application/Message/OutboxMessage.php      Public/Contract/IntegrationEvent (маркер интеграционного события)
Application/Message/OutboxQueueEnvelope.php Public/Dto/OutboxEnvelopeDto (тип параметра Job соседей)
Application/Message/{SerializedOutboxMessage,
  StoredOutboxEventId}.php                 Application/Result или Public/Dto по месту использования
Application/Message/OutboxDebugLogMessage.php Public/Event
Application/Exception/* (2)                без изменений
Infrastructure/Message/* (3)               OutboxEventStore, OutboxMessageLoader ->
                                           Infrastructure/Persistence/Cycle/; сериализатор -> Infrastructure/Serializer/
Infrastructure/Queue/* (4)                 Infrastructure/Spiral/Queue/
Infrastructure/Relay/* (4)                 Infrastructure/Relay/ (своя явно названная граница)
Infrastructure/Registry/OutboxJobRegistry.php Infrastructure/Spiral/Registry/
Infrastructure/Cycle/*Typecast.php (4)     Infrastructure/Persistence/Cycle/Typecast/
Infrastructure/Exception/* (2)             без изменений
Infrastructure/Bootloader/{Outbox,OutboxConsole}Bootloader.php Infrastructure/Spiral/Bootloader/
Presentation/Console/OutboxRelayCommand.php Infrastructure/Spiral/Console/
Presentation/Job/OutboxDebugLogJob.php     Infrastructure/Spiral/Job/
нет                                        Public/{Contract,Dto,Event}
нет                                        Infrastructure/Persistence/Cycle/Migration/
нет                                        Tests/
```

Outbox — технический модуль-инфраструктура для трёх соседей. Его контракты используют Auth, Media и Notifications в четырёх точках каждый: `OutboxEventStoreContract::add()` в Command handler, `OutboxMessage` как маркер своего сообщения, `OutboxJobRegistryContract::register()` в `boot()` своего bootloader, `OutboxMessageLoaderContract::load()` + `OutboxQueueEnvelope` в своём Job. Именно эти пять типов переезжают в `Outbox/Public` — это и есть содержание задачи 5.

Дубликат знания, который стоит закрыть в задаче 4 или 18: пара «сообщение -> Job» регистрируется дважды — в `OutboxJobRegistry` через bootloader модуля-владельца и в `app/config/queue.php` (`registry.handlers`, `registry.serializers`, шесть Job).

Факт вне roadmap: в `composer.json` подключён пакет `gian-tiaga/spiral-outbox: ^0.1.0`, который содержит собственный transactional outbox (`IntegrationEventContract`, `OutboxEventStoreContract`, свои миграции, команды `outbox:relay` и `outbox:status`, worker-интерсептор). В `app/src` и `tests` нет ни одной ссылки на `GianTiaga\SpiralOutbox`. То есть модуль `Outbox` дублирует уже подключённый пакет. Roadmap выносит изменения в собственных пакетах за рамки, поэтому карта не предлагает переход на пакет; она фиксирует расхождение, чтобы задача 5 решала его сознательно, а не открывала заново.

Владение: таблица `outbox_events`; миграция `20260525.153700_0_create_outbox_events_table.php`; секция `app/config/outbox.php` и `OutboxConfig`. Переводов и шаблонов нет. Секция `app/config/queue.php` остаётся общей (транспорт), но перечисление Job уезжает в bootloader-ы модулей-владельцев.

Объём: 58 файлов сейчас, +15 новых.

---

### Модуль Posts

`app/src/Modules/Posts`, 143 файла: Application 51, Domain 49, Infrastructure 13, Presentation 21, Repository 9. Самый крупный и самый зависимый модуль: он вызывает Media, Tags, User, Notifications и Auth.

```text
Сейчас                                       Цель
Domain/Entity/Post.php (корень)              чистая Entity + Cycle Entity + Mapper + Columns
Domain/Entity/{PostMedia,PostTag,PostMention,
  PostLike}.php                              внутренние сущности агрегата Post
Domain/Entity/Comment.php (корень)           чистая Entity + Cycle Entity + Mapper + Columns
Domain/Entity/{CommentLike,CommentMention}.php внутренние сущности агрегата Comment
Domain/Entity/PostBlock.php (корень модерации) чистая Entity + Cycle Entity + Mapper + Columns
Domain/Entity/PostMedia.php relation -> Media СНИМАЕТСЯ; остаётся PostMediaReference (уже есть)
Domain/{ValueObject(29),Enum(2),Collection(9)} PostStatus, AttachmentType -> дублируются в Public/Enum
Repository/PostRepository.php                Domain/Repository/PostRepository + CyclePostRepository
Repository/{PostMedia,PostTag,PostMention,
  PostLike}Repository.php                    схлопываются в CyclePostRepository; чтение -> Reader
Repository/CommentRepository.php             Domain/Repository/CommentRepository + CycleCommentRepository
Repository/{CommentLike,CommentMention}Repository.php схлопываются в CycleCommentRepository; чтение -> Reader
Repository/PostBlockRepository.php           Domain/Repository/PostBlockRepository + Cycle-реализация
Application/Command/* (22)                   ни одного {Action}Result сейчас: пять возвращают View,
                                             шесть void -> появляются CreatePostResult и т.д.
Application/Query/* (11)                     GetPostResult; три существующих Result перестают
                                             оборачивать ViewCollection
Application/View/* (8)                       Application/Data/* + Application/Result/* (задача 15)
Application/Post/PostContentComposer.php     логика делится: свои правила -> Domain/Service,
                                             вызовы Media и Tags -> через их Public
Application/Post/CommentComposer.php         то же
Application/Post/MentionRecipientResolver.php вызовы User -> через User/Public
Application/Post/PostVisibilityPolicy.php    Domain/Service/PostVisibilityPolicy
Application/Notification/* (6)               PostNotificationType/Action/ActionTarget -> Domain или Public/Enum;
                                             NotificationContentBuilder -> порт перевода в Application/Contract,
                                             реализация в Infrastructure/Spiral;
                                             PostNotifier -> через Notifications/Public/Contract
Infrastructure/Cycle/*Typecast.php (12)      Infrastructure/Persistence/Cycle/Typecast/
Infrastructure/Bootloader/PostsBootloader.php Infrastructure/Spiral/Bootloader/
Presentation/Http/Controller/* (2)           Infrastructure/Spiral/Http/Controller/, middleware Auth
                                             заменяется публичным атрибутом доступа
Presentation/Http/Filter/* (15)              Infrastructure/Spiral/Http/Filter/
Presentation/Http/Resource/* (4)             Infrastructure/Spiral/Http/Resource/ + свои MediaResource
нет                                          Application/{Contract,Data,Result,Exception}
нет                                          Infrastructure/Persistence/Cycle/{Entity,Mapper,Repository,Read,
                                             Columns,Migration}
нет                                          Infrastructure/Spiral/Resources/locale/
нет                                          Tests/
```

Posts никем не вызывается: ни один модуль не импортирует `App\Modules\Posts\*`, единственная внешняя ссылка — `PostsBootloader` в Kernel. Поэтому `Public` у Posts нужен минимально: только публичные enum и, возможно, цели deep-link для уведомлений.

Владение: таблицы `posts`, `comments`, `post_likes`, `comment_likes`, `post_mentions`, `comment_mentions`, `post_media`, `post_tags`, `post_blocks`; миграция `20260617.160942_0_create_posts_domain_tables.php`, из которой таблица `tags` уезжает к Tags, а FK `post_media.media_id -> media.id` снимается; переводы `app/locale/{ru,en}/posts.php` (4 ключа ошибок + 14 ключей текстов уведомлений). Своей секции конфигурации и своего типизированного конфига нет. Дополнительно Posts использует чужой ключ `app.user.not_found` в `PostViewAssembler.php:186` и `CommentViewAssembler.php:118` — при переезде переводов ключ заменяется собственным или ошибка приходит от `User/Public`.

Объём: 143 файла сейчас, +60 новых. Самая тяжёлая часть переезда.

---

### Модуль System

`app/src/Modules/System`, 11 файлов: Infrastructure 1, Presentation 9 + 1 шаблон. Ни Domain, ни Application нет.

```text
Сейчас                                              Цель
Infrastructure/Bootloader/SystemBootloader.php      Infrastructure/Spiral/Bootloader/
Presentation/Http/Controller/HealthController.php   Infrastructure/Spiral/Http/Controller/
Presentation/Http/Controller/SwaggerController.php  Infrastructure/Spiral/Http/Controller/
Presentation/Http/Enum/HealthStatus.php             Infrastructure/Spiral/Http/ рядом с Resource
Presentation/Http/Resource/HealthResource.php       Infrastructure/Spiral/Http/Resource/
Presentation/Http/View/SwaggerView.php              Infrastructure/Spiral/Http/View/
Presentation/Console/OpenApiGenerateCommand.php     Infrastructure/Spiral/Console/
Presentation/Console/OpenApiPublishAssetsCommand.php Infrastructure/Spiral/Console/
Presentation/Exception/OpenApiAssetsPublicationException.php Application/Exception/
Presentation/Temporal/Ping.php                      Infrastructure/Spiral/Temporal/
Presentation/views/swagger/index.twig               Infrastructure/Spiral/Resources/views/swagger/
нет                                                 Application/Command/GenerateOpenApi/{Command,Handler,Result}
нет                                                 Application/Command/PublishOpenApiAssets/{Command,Handler,Result}
нет                                                 Application/Query/GetOpenApiSpec/{Query,Handler,Result}
нет                                                 Application/Contract/{OpenApiSpecStorage,AssetsPublisher}
нет                                                 Infrastructure/Spiral/Configuration/OpenApiConfig
нет                                                 Tests/
```

Domain у System не нужен: своих таблиц, инвариантов и миграций у модуля нет. Application нужен ограниченно — три сценария вокруг OpenAPI, потому что сейчас алгоритмы живут прямо в консольных командах и контроллере (проверка флага, вычисление пути, рекурсивное копирование каталога), а `docs/arch.md:166` требует, чтобы входные адаптеры бизнес-правил не содержали.

`SystemBootloader` регистрирует только view-namespace `system`. Консольные команды System регистрирует чужой `app/src/Shared/Infrastructure/Framework/Bootloader/OpenApiBootloader.php`, который импортирует `App\Modules\System\Presentation\Console\*` — это нарушение «Shared не импортирует бизнес-модули» и переезжает в bootloader System (задача 3 или 4).

Владение: маршруты `/api/v1/health`, `/api/docs`, `/api/docs/openapi.yml`; шаблон `swagger/index.twig`; переводы `app/locale/{ru,en}/system.php` (3 ключа); секция `app/config/openapi.php` и `OpenApiConfig`. Таблиц и миграций нет.

Мелочь для чистки при переезде: неиспользуемый импорт `NotFoundException` в `app/src/Modules/System/Presentation/Http/Controller/HealthController.php`.

Объём: 11 файлов сейчас, +12 новых.

---

### Модуль Tags

`app/src/Modules/Tags`, 10 файлов: Application 6, Domain 3, Repository 1. Нет Infrastructure, Presentation и bootloader.

```text
Сейчас                                        Цель
Domain/Entity/Tag.php                         чистая Entity + CycleTagEntity + TagMapper + TagColumns
Domain/ValueObject/TagText.php                без изменений
Domain/Collection/TagCollection.php           без изменений
Shared/Domain/ValueObject/TagId.php           Tags/Domain/ValueObject/TagId
Repository/TagRepository.php                  Domain/Repository/TagRepository + CycleTagRepository
Application/Command/ResolveTags/* (3)         без изменений; EntityManager заменяется Repository
Application/Query/GetTags/* (2)               + GetTagsResult вместо TagTextCollection
Application/Dto/TagTextCollection.php         Public/Dto/TagDtoCollection
нет                                           Public/Contract/TagsContract + Public/Dto
нет                                           Infrastructure/Spiral/Bootloader/TagsBootloader
нет                                           Infrastructure/Spiral/PublicApi/TagsProvider
нет                                           Infrastructure/Persistence/Cycle/{Entity,Mapper,Repository,
                                              Columns,Migration}
нет                                           Tests/
```

Владение: таблица `tags`. Сейчас она создаётся миграцией Posts (`20260617.160942_0_create_posts_domain_tables.php`), поэтому при переезде миграций (задача 20) таблица `tags` выделяется в отдельную миграцию Tags — это единственный случай, когда одна миграция трогает таблицы двух модулей. Своего файла переводов и своей секции конфигурации нет.

Единственный потребитель Tags — Posts, в двух файлах: `PostContentComposer` (инъекция `ResolveTagsHandler`) и `PostViewAssembler` (инъекция `GetTagsHandler`, использование `TagTextCollection`).

Объём: 10 файлов сейчас, +14 новых.

---

### Модуль User

`app/src/Modules/User`, 50 файлов: Application 15, Domain 22, Infrastructure 10, Repository 3. Нет Presentation, нет HTTP-маршрутов, нет bootloader.

```text
Сейчас                                        Цель
Domain/Entity/User.php (корень)               чистая Entity + CycleUserEntity + UserMapper + UserColumns
Domain/Entity/UserBan.php                     корень агрегата UserBan (самостоятельный жизненный цикл)
Domain/Entity/ReservedNickname.php            корень агрегата ReservedNickname
Domain/{ValueObject(16),Enum(2),Collection(1)} без изменений
Repository/{User,UserBan,ReservedNickname}Repository.php Domain/Repository/*(3) + Cycle*Repository(3)
Application/Command/CreateUser/* (3)          без изменений; EntityManager заменяется Repository
Application/Query/* (8)                       {Action}Result у каждого вместо Dto и bool
Application/Dto/UserAuthView.php              Application/Query/FindUserForAuth/FindUserForAuthResult
Application/Dto/UserPublicProfileView.php     Public/Dto/UserProfileDto
Application/Dto/UserPublicProfileCollection.php Public/Dto/UserProfileDtoCollection
Application/Profile/UserPublicProfileAssembler.php растворяется в Query handler; аватар — через Media/Public
Infrastructure/Cycle/*Typecast.php (10)       Infrastructure/Persistence/Cycle/Typecast/
нет                                           Public/Contract/UserContract + Public/Dto + Public/Enum
нет                                           Infrastructure/Spiral/Bootloader/UserBootloader
нет                                           Infrastructure/Spiral/PublicApi/UserProvider
нет                                           Infrastructure/Persistence/Cycle/{Entity,Mapper,Repository,
                                              Columns,Migration}
нет                                           Infrastructure/Spiral/Resources/locale/
нет                                           Tests/
```

Публичная поверхность User фактически уже сложилась: `CreateUser`, `FindUserForAuth`, `CheckUsersExist`, `GetUserPublicProfile`, `GetUserPublicProfiles` и три DTO. Именно это становится `User/Public/Contract` + `User/Public/Dto` + `Infrastructure/Spiral/PublicApi`.

Отдельная проблема качества, которую закрывает задача 7: `UserPublicProfileAssembler` дочитывает аватар по одному медиа на пользователя, поэтому `GetUserPublicProfiles` даёт N+1 обращений к Media. `docs/references/public-contract.md:90` прямо требует пакетный метод по набору идентификаторов.

Владение: таблицы `users`, `user_bans`, `reserved_nicknames`; миграция `20260613.143901_0_create_user_domain_tables.php`, в которой межмодульный FK `users.avatar_media_id -> media.id RESTRICT` снимается; переводы `app/locale/{ru,en}/user.php` (3 ключа). Своей секции конфигурации нет. `UserBanRepository` в продуктивном коде не используется — только в тесте.

Объём: 50 файлов сейчас, +22 новых.

---

### Shared

`app/src/Shared`, 92 файла.

```text
Сейчас                                              Цель
Domain/Collection/TypedCollection.php               остаётся
Domain/Enum/Locale.php                              остаётся
Domain/Exception/* (6)                              остаются (исключения без владельца)
Domain/Locale/LocaleResolver.php                    остаётся
Domain/Pagination/CursorSlice.php                   остаётся (arch.md:171 называет «срез курсорной страницы»)
Domain/Trait/ComparesDateTimeToMicroseconds.php     остаётся
Domain/Trait/HasTimestamps.php                      остаётся без #[Column]; колонки объявляют Cycle Entity
Domain/ValueObject/AbstractUuidV7Id.php             остаётся
Domain/ValueObject/AbstractIntegerValue.php         остаётся
Domain/ValueObject/AbstractRangedIntegerValue.php   остаётся
Domain/ValueObject/UserId.php                       остаётся (см. «Закрытые развилки», п. 1)
Domain/ValueObject/TagId.php                        -> Tags/Domain/ValueObject/TagId
Application/View/Media*.php (3)                     -> Media/Public/Dto
Infrastructure/Cycle/* (9)                          -> Shared/Infrastructure/Persistence/Cycle/ (arch.md:81)
Infrastructure/Database/* (2)                       -> Shared/Infrastructure/Persistence/Cycle/
Infrastructure/Cache/RedisCacheStorage.php          -> Shared/Infrastructure/Spiral/Cache/
Infrastructure/Configuration/* (46)                 9 корневых конфигов остаются,
                                                    7 уезжают к владельцам (см. «Владение конфигурацией»)
Infrastructure/Exception/* (2)                      остаются рядом с ConfigMapper
Infrastructure/Framework/Kernel.php                 -> Shared/Infrastructure/Spiral/Kernel.php
Infrastructure/Framework/DirectoryAlias.php         -> Shared/Infrastructure/Spiral/
Infrastructure/Framework/Bootloader/Annotations,App,
  Config,ExceptionHandler,Logging,Routes (6)        -> Shared/Infrastructure/Spiral/Bootloader/
Infrastructure/Framework/Bootloader/OpenApiBootloader.php -> System
Infrastructure/Framework/Middleware/LocaleMiddleware.php -> Shared/Infrastructure/Spiral/Http/Middleware/
Infrastructure/Framework/Middleware/RateLimitMiddleware.php -> то же (остаётся общим, см. п. 3)
Presentation/Http/Resource/AbstractResource.php     -> Shared/Infrastructure/Spiral/Http/Response/
Presentation/Http/Resource/Media*.php (3)           -> в Http/Resource каждого потребителя
```

Два механизма Shared ломают самодостаточность модулей и должны получить реестр путей, пополняемый bootloader-ами модулей (задачи 20, 21, 23):
- `app/src/Shared/Infrastructure/Framework/Bootloader/ConfigBootloader.php` сканирует жёстко заданный каталог `app/src/Shared/Infrastructure/Configuration` маской `*Config.php` и регистрирует всё, что реализует `TypedConfig`. После переезда конфигов в модули он их не найдёт;
- `app/config/migration.php` задаёт единственный каталог `app/database/migrations`.

`Kernel` (`app/src/Shared/Infrastructure/Framework/Kernel.php`) регистрирует 7 bootloader-ов модулей: `OutboxBootloader`, `MediaBootloader`, `AuthBootloader`, `SystemBootloader`, `NotificationsBootloader`, `PostsBootloader` в основном списке и `OutboxConsoleBootloader` в блоке консольных команд. Порядок значим: Notifications после Outbox (нужен `OutboxJobRegistryContract`), Posts после Notifications (нужен `NotificationTypeRegistryContract`). Плюс `Bootloader\OpenApiBootloader` — фактически System. Bootloader-ов нет у Access, Tags, User.

`RoutesBootloader` маршрутов не объявляет: маршрутизация аннотированная. Он задаёт глобальную цепочку (`ErrorHandlerMiddleware`, `LocaleMiddleware`, `RouteNotFoundMiddleware`, `DumperMiddleware`, `JsonPayloadMiddleware`, `HttpCollector`) и группы `api` / `web`. Именно сюда встраивается общий HTTP-адаптер, применяющий публичный атрибут доступа (задача 8).

Общий примитив запроса для Reader уже есть: `App\Shared\Infrastructure\Cycle\WhenSelect` (`when()`, `cursorById()`). Карточка `docs/references/reader.md:43` ожидает его по пути `App\Shared\Infrastructure\Persistence\Cycle\WhenSelect` — это часть переезда Shared, новый класс писать не нужно. `CursorSlice::fromOverfetched()` тоже существует и используется четырьмя handler-ами.

### Владение миграциями

```text
Файл в app/database/migrations                        Таблицы                                Владелец
20260521.184100_0_create_media_domain_tables.php      media, media_image_conversions,        Media
                                                      media_video_conversions,
                                                      media_multipart_uploads
20260525.153700_0_create_outbox_events_table.php      outbox_events                          Outbox
20260613.130000_0_create_notification_domain_tables.php notifications, notification_settings,  Notifications
                                                      notification_device_tokens
20260613.143901_0_create_user_domain_tables.php       users, user_bans, reserved_nicknames    User
20260613.143902_0_create_access_domain_tables.php     roles, permissions, role_permissions,   Access
                                                      user_roles
20260615.141700_0_create_auth_domain_tables.php       auth_tokens, auth_login_codes,          Auth
                                                      auth_registration_tickets
20260616.180010_0_add_device_to_auth_tokens.php       auth_tokens                             Auth
20260617.160942_0_create_posts_domain_tables.php      posts, comments, post_likes,            Posts
                                                      comment_likes, post_mentions,           + таблица tags
                                                      comment_mentions, post_media,           уезжает к Tags
                                                      post_tags, post_blocks, tags
20260620.222100_0_create_media_audio_conversions_table.php media_audio_conversions             Media
```

Межмодульные FK, которые снимаются по `docs/arch.md:218`:
- `user_roles.user_id -> users.id` (Access -> User);
- `users.avatar_media_id -> media.id RESTRICT` (User -> Media);
- `post_media.media_id -> media.id RESTRICT` (Posts -> Media).

Правило `docs/rules.md` «не редактируй уже применённую миграцию» означает, что снятие FK и выделение `tags` делаются новыми миграциями, а перенос файлов — переносом без правки их содержимого. Единый порядок имён в рамках приложения при этом сохраняется: имена файлов не меняются.

### Владение переводами

```text
Файл                                  Ключей  Владелец  Потребители ключей
app/locale/{ru,en}/auth.php                8  Auth      только Auth
app/locale/{ru,en}/media.php              23  Media     только Media
app/locale/{ru,en}/notifications.php       5  Notifications только Notifications
app/locale/{ru,en}/posts.php              18  Posts     только Posts (4 ошибки + 14 текстов уведомлений)
app/locale/{ru,en}/system.php              3  System    только System
app/locale/{ru,en}/user.php                3  User      User + 2 файла Posts (app.user.not_found)
app/locale/{ru,en}/shared.php              1  общий     RateLimitMiddleware (app.shared.rate_limit_exceeded)
```

Файлов для Tags, Access и Outbox нет, и они не нужны: у этих модулей нет пользовательских сообщений. `shared.php` остаётся в `app/locale` как перевод без владельца.

Механизм: `app/config/translator.php` задаёт `directory('locale')`; после переезда (задача 21) каталоги локалей модулей регистрируются их bootloader-ами.

### Владение шаблонами

```text
Файл                                                     Владелец  Namespace  Регистрирует
app/src/Modules/Auth/Presentation/views/login-code.twig   Auth      auth       AuthBootloader::init()
app/src/Modules/System/Presentation/views/swagger/index.twig System   system     SystemBootloader::init()
app/views/.gitkeep                                        —         —          пустой общий каталог
```

Оба шаблона уже лежат внутри модулей и регистрируются их bootloader-ами; переезд ограничивается сменой пути на `Infrastructure/Spiral/Resources/views`. Общий `app/views` пуст и модульных файлов не содержит — требование задачи 22 в этой части уже выполнено.

### Владение конфигурацией

```text
app/config           Типизированный конфиг                          Владелец
cache.php            Cache/CacheConfig (+2)                         общий
database.php         Database/DatabaseConfig (+2)                   общий
cycle.php            Cycle/CycleConfig (+5)                         общий
migration.php        Migration/MigrationConfig                      общий (каталоги пополняют модули)
queue.php            Queue/QueueConfig (+4)                         общий (реестр Job уезжает в модули)
session.php          Session/SessionConfig                          общий
translator.php       Translator/TranslatorConfig (+1)               общий
locale.php           Locale/LocaleConfig                            общий
scaffolder.php       Scaffolder/ScaffolderConfig (+4)               общий
media.php            Media/MediaConfig                              Media
storage.php          Storage/StorageConfig (+5)                     общий сервер + бакеты Media у Media
outbox.php           Outbox/OutboxConfig                            Outbox
push.php             Push/PushConfig                                Notifications
centrifugo.php       Centrifugo/CentrifugoConfig                    Notifications
mailer.php           Mailer/MailerConfig                            Auth
openapi.php          OpenApi/OpenApiConfig                          System
```

Шесть секций и шесть корневых конфигов уезжают к владельцам целиком (`media`, `outbox`, `push`, `centrifugo`, `mailer`, `openapi`), `storage` делится. Итого из 16 типизированных конфигов 9 остаются общими, 6 переезжают, 1 делится.

Потребители подтверждают владение: `MediaConfig` читают только четыре класса Media; `StorageConfig` — только `S3MediaFileService`; `CentrifugoConfig` и `PushConfig` — только Notifications; `OutboxConfig` — только Outbox; `OpenApiConfig` — только System. `CacheConfig` читает `System/Presentation/Http/Controller/HealthController.php` — это диагностика, владение не меняет.

### Владение тестами

182 файла `*Test.php` и 53 вспомогательных файла.

```text
Владелец   Unit  Kernel  Feature  Итого  Целевое место
Access        1       0        1      2  Access/Tests/{Unit,Integration}
Auth          9       1       13     23  Auth/Tests/{Unit,Integration,Feature}
Media        12       1       17     30  Media/Tests/{Unit,Integration,Feature}
Notifications 12      0        9     21  Notifications/Tests/{Unit,Integration,Feature}
Outbox        8       1        8     17  Outbox/Tests/{Unit,Integration,Feature}
Posts        12       2       19     33  Posts/Tests/{Unit,Integration,Feature}
System        1       0        5      6  System/Tests/{Unit,Integration,Feature}
Tags          3       0        3      6  Tags/Tests/{Unit,Integration}
User          3       0        6      9  User/Tests/{Unit,Integration}
Shared       18      13        2     33  остаются в tests/ (общесистемные)
общие         0       1        1      2  tests/Kernel/DemoTest.php, tests/Feature/CqrsContainerTest.php
```

Соответствие текущих наборов целевым:
- `tests/Unit/Modules/{M}/Domain|Application/*` -> `{M}/Tests/Unit/{Domain,Application}` — прямое соответствие;
- `tests/Unit/Modules/{M}/Infrastructure/*` -> `{M}/Tests/Unit` либо `Integration/Cycle` по тому, поднимает ли тест базу;
- `tests/Kernel/Modules/{M}/*` -> `{M}/Tests/Integration/Spiral`;
- `tests/Feature/Modules/{M}/Repository/*` -> `{M}/Tests/Integration/Cycle`;
- `tests/Feature/Modules/{M}/Http/*` -> `{M}/Tests/Feature/Spiral`;
- `tests/Feature/Modules/{M}/Application|Console|Presentation|Flow|Infrastructure/*` -> `{M}/Tests/Integration/Spiral`.

Остаются в корневом `tests/` (общесистемные и межмодульные): `TestCase.php`, `DatabaseTestCase.php`, `NonTransactionalDatabaseTestCase.php`, `RealStorageTestCase.php`, `TestRuntime.php`, `bootstrap.php`, `warmup.php`, `Storage/FakeStorage.php`, `App/TestKernel.php`, `App/Bootloader/ApiErrorTestRoutesBootloader.php`, `App/Modules/System/Http/*` (тестовые маршруты для проверки формата ошибок), `Feature/CqrsContainerTest.php`, `Kernel/DemoTest.php`, весь `Unit/Shared` и `Kernel/Shared`, `Feature/Shared/Infrastructure/DockerRuntimeSmokeTest.php`.

Требуют решения о владельце при переезде:
- `tests/Support/Media/PersistsMedia.php` — фикстура Media, используется тестами Media, User, Notifications и Posts; остаётся общей или превращается в публичный тестовый помощник Media;
- `tests/Support/Notifications/{FixtureNotificationTypeDefinition,RecordingOutboxEventStore}.php` — то же для Notifications;
- `tests/Feature/Modules/Outbox/CleansOutboxEvents.php` — трейт очистки, используется тестами Auth, Media и Notifications.

Инструменты (задача 28): `phpunit.xml` объявляет три набора по каталогам `tests/Unit`, `tests/Kernel`, `tests/Feature`, `composer.json` считает покрытие по `app/src` с порогом 100%, `phpstan.neon` анализирует `app/src`. После переезда тестов внутрь модулей все три файла меняют пути, а покрытие должно исключить `{Module}/Tests` из измеряемого кода.

### Межмодульные вызовы и публичные контракты, которые их заменят

Всего 83 межмодульных импорта в 36 файлах модулей (11 пар «модуль -> модуль») плюс 3 файла `Shared`, импортирующих бизнес-модули. Ниже — по вызывающему файлу.

```text
#   Кто -> Кому        Файл вызывающего                                          Что вызывает сейчас                               Чем заменяется
1   Auth -> User       Application/Command/CompleteRegistration/                  CreateUserCommand, CreateUserHandler              User/Public/Contract/UserContract::createUser()
                       CompleteRegistrationHandler.php                                                                             -> User/Public/Dto/CreatedUserDto
2   Auth -> User       Application/Command/ResolveLoginCode/                      FindUserForAuthQuery, FindUserForAuthHandler,     User/Public/Contract/UserContract::findForSignIn()
                       ResolveLoginCodeHandler.php                                UserAuthView                                     -> User/Public/Dto/UserSignInDto|null
3   Auth -> Outbox     Application/Command/RequestLoginCode/                      OutboxEventStoreContract::add()                  Outbox/Public/Contract/IntegrationEventStoreContract
                       RequestLoginCodeHandler.php
4   Auth -> Outbox     Application/Message/LoginCodeRequested.php                 implements OutboxMessage                          implements Outbox/Public/Contract/IntegrationEvent
5   Auth -> Outbox     Infrastructure/Bootloader/AuthBootloader.php               OutboxJobRegistryContract::register()             Outbox/Public/Contract/IntegrationEventRoutingContract
6   Auth -> Outbox     Presentation/Job/SendLoginCodeJob.php                      OutboxMessageLoaderContract, OutboxQueueEnvelope  Outbox/Public/Contract + Outbox/Public/Dto/OutboxEnvelopeDto
7   Media -> Outbox    Application/Command/CompleteMediaUpload/                   OutboxEventStoreContract::add()                  то же, что №3
                       CompleteMediaUploadHandler.php
8   Media -> Outbox    Application/Message/MediaUploaded.php                      implements OutboxMessage                          то же, что №4
9   Media -> Outbox    Infrastructure/Bootloader/MediaBootloader.php              OutboxJobRegistryContract::register()             то же, что №5
10  Media -> Outbox    Presentation/Job/ProcessMediaJob.php                       OutboxMessageLoaderContract, OutboxQueueEnvelope  то же, что №6
11  Notifications      Application/Command/Notification/DispatchNotification/     OutboxEventStoreContract::add()                  то же, что №3
    -> Outbox          DispatchNotificationHandler.php
12  Notifications      Application/NotificationSender.php                         OutboxEventStoreContract::add()                  то же, что №3
    -> Outbox
13  Notifications      Application/Message/NotificationRequested.php,             implements OutboxMessage                          то же, что №4
    -> Outbox          NotificationPushRequested.php,
                       NotificationRealtimeRequested.php
14  Notifications      Infrastructure/Bootloader/NotificationsBootloader.php      OutboxJobRegistryContract::register() x3          то же, что №5
    -> Outbox
15  Notifications      Presentation/Job/DispatchNotificationJob.php,              OutboxMessageLoaderContract, OutboxQueueEnvelope  то же, что №6
    -> Outbox          SendPushNotificationJob.php,
                       PublishRealtimeNotificationJob.php
16  Notifications      Application/View/NotificationViewAssembler.php             FindMediaUrlsQuery, FindMediaUrlsHandler,         Media/Public/Contract/MediaContract::urlsByIds()
    -> Media                                                                      MediaUrlsResultCollection                        -> Media/Public/Dto/MediaDtoCollection
17  Notifications      Application/Command/Push/SendPushNotification/             FindMediaUrlQuery, FindMediaUrlHandler            Media/Public/Contract/MediaContract::urlsByIds()
    -> Media           SendPushNotificationHandler.php                                                                             (набор из одного элемента)
18  Notifications      Application/Command/Realtime/PublishRealtimeNotification/  FindMediaUrlQuery, FindMediaUrlHandler            то же, что №17
    -> Media           PublishRealtimeNotificationHandler.php
19  User -> Media      Application/Profile/UserPublicProfileAssembler.php         FindMediaUrlQuery, FindMediaUrlHandler            Media/Public/Contract/MediaContract::urlsByIds()
                                                                                  (по одному на пользователя — N+1)                одним пакетным вызовом на ответ
20  Posts -> Media     Application/Post/PostContentComposer.php                   CheckMediaAttachableQuery/Handler,                Media/Public/Contract/MediaContract::
                                                                                  MakeMediaPermanentCommand/Handler (в цикле)       ensureAttachable() + makePermanent(), набором id
21  Posts -> Media     Application/View/PostViewAssembler.php                     MediaUrlServiceContract::getUrls(Media $entity)   Media/Public/Contract/MediaContract::urlsByIds()
22  Posts -> Media     Domain/Entity/PostMedia.php                                ORM relation BelongsTo(Media::class)              снимается; остаётся PostMediaReference
23  Posts -> Media     Repository/PostMediaRepository.php                         ->load('media.imageConversions' и т.д.)           снимается; Reader читает только post_media
24  Posts -> Media     app/database/migrations/2026...posts_domain_tables.php     FK post_media.media_id -> media.id RESTRICT       снимается новой миграцией
25  Posts -> Tags      Application/Post/PostContentComposer.php                   ResolveTagsCommand, ResolveTagsHandler            Tags/Public/Contract/TagsContract::resolve()
26  Posts -> Tags      Application/View/PostViewAssembler.php                     GetTagsQuery, GetTagsHandler, TagTextCollection   Tags/Public/Contract/TagsContract::textsByIds()
                                                                                                                                   -> Tags/Public/Dto/TagDtoCollection
27  Posts -> Tags      Domain/Entity/PostTag.php, Repository/PostTagRepository.php Shared\Domain\ValueObject\TagId                   Posts/Domain/ValueObject/PostTagReference
28  Posts -> User      Application/Post/MentionRecipientResolver.php              CheckUsersExistQuery/Handler,                     User/Public/Contract/UserContract::existsAll()
                                                                                  GetUserPublicProfile(s)Query/Handler,             + profilesByIds()
                                                                                  UserPublicProfileView/Collection                  -> User/Public/Dto/UserProfileDto(Collection)
29  Posts -> User      Application/View/PostViewAssembler.php                     GetUserPublicProfile(s)Query/Handler              то же, что №28
30  Posts -> User      Application/View/CommentViewAssembler.php                  GetUserPublicProfile(s)Query/Handler              то же, что №28
31  Posts -> User      Application/View/AuthorView.php                            UserPublicProfileView                             User/Public/Dto/UserProfileDto
32  Posts -> User      Application/Post/PostContentComposer.php                   UserPublicProfileCollection                       User/Public/Dto/UserProfileDtoCollection
33  Posts -> User      Application/Notification/PostNotifier.php,                 UserPublicProfileView                              User/Public/Dto/UserProfileDto
                       CommentNotificationTarget.php
34  Posts -> User      Application/View/{Post,Comment}ViewAssembler.php           ключ перевода app.user.not_found                  собственный ключ Posts либо ошибка User/Public
35  Posts ->           Application/Notification/PostNotifier.php                  NotificationSenderContract::send(),               Notifications/Public/Contract/
    Notifications                                                                 NotificationAction, NotificationActor             NotificationContract::send(NotificationDto)
36  Posts ->           Application/Notification/NotificationContentBuilder.php    NotificationContent, NotificationTitle,           Notifications/Public/Dto/NotificationContentDto
    Notifications                                                                 NotificationBody, NotificationAction,             (только примитивы)
                                                                                  NotificationActor
37  Posts ->           Application/Notification/PostNotificationType.php          NotificationTypeDefinition, NotificationChannel,  Notifications/Public/Contract/
    Notifications                                                                 NotificationChannelDefaults,                      NotificationTypeDefinition + Public/Enum/
                                                                                  NotificationTypeCode                              NotificationChannel
38  Posts ->           Infrastructure/Bootloader/PostsBootloader.php              NotificationTypeRegistryContract::register()      Notifications/Public/Contract/
    Notifications                                                                                                                  NotificationTypeRegistryContract
39  Posts -> Auth      Presentation/Http/Controller/PostController.php,           AuthContextAttributeMiddleware,                   Auth/Public/Attribute/AuthenticatedRoute
                       CommentController.php                                      RequireAuthenticatedMiddleware                    + общий HTTP-адаптер (задача 8)
40  Shared -> Media    Shared/Application/View/MediaConversionView.php            MediaConversionKind, MediaImageConversionType,    Media/Public/Enum/* после переезда View
                                                                                  MediaVideoConversionType,                         в Media/Public/Dto
                                                                                  MediaAudioConversionType
41  Shared -> Media    Shared/Presentation/Http/Resource/                         те же четыре enum                                 Resource переезжает в модули-потребители,
                       MediaConversionResource.php                                                                                 enum берётся из Media/Public/Enum
42  Shared -> System   Shared/Infrastructure/Framework/Bootloader/                OpenApiGenerateCommand,                           регистрация уезжает в bootloader System
                       OpenApiBootloader.php                                      OpenApiPublishAssetsCommand
```

Циклических межмодульных зависимостей нет. Граф после переезда:

```text
Auth  -> User/Public, Outbox/Public
Media -> Outbox/Public
User  -> Media/Public
Notifications -> Media/Public, Outbox/Public
Posts -> Media/Public, Tags/Public, User/Public, Notifications/Public, Access/Public (атрибуты)
Tags, Access, Outbox, System -> никого
```

Итоговый список публичных контрактов, который нужно завести (задача 6):

```text
Модуль        Public/Contract               Операции (из фактических вызовов)
Outbox        IntegrationEventStoreContract  add(IntegrationEvent): OutboxEventIdDto
Outbox        IntegrationEventRoutingContract register(eventClass, jobClass)
Outbox        IntegrationEventLoaderContract  load(outboxEventId, expectedClass): IntegrationEvent
User          UserContract                   createUser(...), findForSignIn(email),
                                             existsAll(userIds), profilesByIds(userIds), profile(userId)
Media         MediaContract                  urlsByIds(mediaIds), ensureAttachable(mediaId, ownerId),
                                             makePermanent(mediaId, ownerId)
Tags          TagsContract                   resolve(texts, creatorUserId), textsByIds(tagIds)
Notifications NotificationContract           send(recipientUserId, NotificationContentDto)
Notifications NotificationTypeRegistryContract register(NotificationTypeDefinition ...)
Access        AccessContract                 hasPermission(userId, PublicPermission)
Auth          SessionContract                (по мере надобности; сегодня синхронных вызовов нет;
                                             атрибут AuthenticatedRoute нужен всем защищённым маршрутам)
```

### Закрытые развилки

Запуск неинтерактивный, поэтому развилки закрыты здесь с причиной. Каждая — предположение карты; план соответствующей задачи её подтверждает.

1. **`UserId` остаётся в `Shared/Domain/ValueObject`.** Он используется примерно в 97 файлах всех семи бизнес-модулей, пустой, без поведения и без зависимости от модуля User. `docs/arch.md:171` прямо относит «базовые идентификаторы» к Shared. Перенос к User потребовал бы собственного объекта-ссылки в каждом соседе и затронул бы около сотни файлов ради формальной чистоты. Решение: оставить и записать как осознанное отступление в задаче 30. Риск — правило «межмодульный идентификатор хранится как собственный объект-значение ссылки» (`docs/arch.md:216`) читается как нарушение; снимается тем, что отступление перечислено явно с причиной.

2. **`TagId` уезжает в `Tags/Domain/ValueObject`,** а Posts заводит `PostTagReference` по образцу существующего `PostMediaReference`. Продуктивных потребителей всего шесть файлов, оправдания «базового идентификатора» у него нет.

3. **`RateLimitMiddleware` остаётся общим** в `Shared/Infrastructure/Spiral/Http/Middleware`. Единственный потребитель сегодня — четыре маршрута Auth, но класс параметризован, не знает домена и переводит ключ `app.shared.*`. Перенос в Auth заставил бы любой другой модуль импортировать чужой модуль ради ограничения частоты.

4. **`StorageConfig` делится.** Сервер `s3`, сервер `local` и бакеты `default`, `s3`, `s3-test` остаются общими (их использует и тестовое окружение); бакеты `media-upload`, `media-private`, `media-public` и их разбор уезжают в Media. Альтернатива «весь storage к Media» отклонена: `local`-сервер и `default`-бакет к медиа отношения не имеют.

5. **Границы агрегатов.** Media — один агрегат (корень `Media`, внутренние `Media*Conversion` и `MediaMultipartUpload`; FK `ON DELETE CASCADE`, самостоятельного жизненного цикла нет). Posts — три агрегата: `Post` (внутренние `PostMedia`, `PostTag`, `PostMention`, `PostLike`), `Comment` (внутренние `CommentLike`, `CommentMention`), `PostBlock` (самостоятельный жизненный цикл модерации). Access — два: `Role` (внутренняя `RolePermission`) и `UserRole`. User — три независимых (`User`, `UserBan`, `ReservedNickname`): у бана и у брони псевдонима свой жизненный цикл, переживающий удаление аккаунта. Auth — три независимых. Notifications — три независимых. Это сокращает 29 репозиториев до 15 доменных интерфейсов.

6. **`Notifications` отдаёт наружу примитивный DTO.** Сегодня Posts строит `NotificationContent` из доменных VO соседа. `docs/arch.md:109` запрещает доменные VO в `Public`, поэтому `NotificationContentDto` в `Public/Dto` содержит только скаляры и публичные enum, а сборку доменных значений делает `NotificationSenderProvider` внутри Notifications.

7. **`Shared/Domain/Exception/DomainTranslatableException` сохраняет импорт `GianTiaga\SpiralApiErrors`.** Это нейтральный контракт собственного Composer-пакета, а не фреймворк; заменять его собственным интерфейсом ради буквы правила значило бы дублировать механизм перевода ошибок, уже принятый в проекте (`docs/arch.md:290`). Записывается как отступление в задаче 30.

8. **Инструмент проверки границ для задачи 29 — `deptrac/deptrac`,** последняя стабильная версия `4.7.2` от 15 сентября 2026 (проверено по `https://packagist.org/packages/deptrac/deptrac.json` 15.09.2026; пакет активно поддерживается, требует PHP `^8.2`, проект на 8.5). Он описывает слои и разрешённые направления декларативно, что точно ложится на таблицу `docs/arch.md:187-196`. Правило «нет фреймворка в Domain, Application и Public» выражается тем же конфигом. Альтернатива — собственные правила поверх уже подключённого `gian-tiaga/phpstan-strict-rules`; она отклонена как более трудоёмкая при том же результате, но остаётся запасной, если deptrac не справится с межмодульным правилом «только через Public». Сейчас в проекте инструмента проверки границ нет: в `vendor/` нет ни deptrac, ни phparkitect, в `phpstan.neon` архитектурных правил нет.

9. **Пакет `gian-tiaga/spiral-outbox` в переезде не используется.** Он подключён в `composer.json`, но ни одной ссылки на `GianTiaga\SpiralOutbox` в `app/src` и `tests` нет; модуль `Outbox` реализует тот же механизм сам. Roadmap выносит изменения в собственных пакетах за рамки, поэтому карта оставляет модуль как есть и лишь фиксирует дублирование, чтобы оно не всплыло как открытый вопрос в задаче 5.

10. **Notifications получают защиту маршрутов в задаче 8.** Сегодня восемь маршрутов модуля не применяют auth-middleware, а фильтр ждёт атрибут `authUserId`. Это не «изменение внешнего поведения» в смысле предположений roadmap, а приведение маршрута к уже заявленному требованию сессии. Записывается как согласованное исключение из «внешнее поведение не меняется».

### Привязка карты к задачам roadmap

```text
Задача  Что берёт из карты
2       Shared: Framework -> Spiral, Presentation -> Infrastructure/Spiral/Http/Response,
        Cycle+Database -> Infrastructure/Persistence/Cycle
3       Раздел «Shared содержит код модулей»: 9 файлов + OpenApiBootloader
4       Раздел «Shared»: Kernel регистрирует 7 bootloader-ов; нет у Access, Tags, User
5       Раздел «Модуль Outbox» + вызовы №3-15 в таблице межмодульных вызовов
6       Таблица межмодульных вызовов, итоговый список публичных контрактов
7       Вызовы №16-21, 26, 28-33, 35-37; раздел «Формы ответа»
8       Вызов №39, раздел «Модуль Access», маршруты Notifications; владельцы атрибутов:
        Auth — AuthenticatedRoute, Access — RequiresPermission (docs/references/public-attribute.md)
9       Вызовы №22-24, 27; межмодульные FK в разделе «Владение миграциями»
10      Разделы «Формы ответа» и per-module (Domain/Exception, Domain/Event)
11      Раздел «Repository — верхнеуровневая папка модуля», п. 5 закрытых развилок
12      NotificationBulkWriter — единственный случай, уже соответствует
13      Access, Tags, Auth, User, Outbox, Notifications (таблица сущностей)
14      Media, Posts (таблица сущностей)
15      Раздел «Формы ответа»: 60 классов, таблица Reader и Data
16      Per-module: подпапки Application вне целевых
17, 18  Раздел «Верхнеуровневый Presentation»: 68 файлов
19      Per-module: FileService, Centrifugo, Push, Relay, Queue, Registry, Auth, Mail
20      Раздел «Владение миграциями»
21      Раздел «Владение переводами»
22      Раздел «Владение шаблонами»
23      Раздел «Владение конфигурацией»
24      Раздел «Владение тестами»
25      Раздел «Как читать карту»: таблица ролей docs/arch.md:88-105
26, 27  Per-module таблицы «Сейчас -> Цель»
28      Раздел «Владение тестами», хвост про phpunit.xml, phpstan.neon, composer.json
29      П. 8 закрытых развилок
30      П. 1, 7, 9, 10 закрытых развилок — список отступлений с причиной
```

## Ответы на вопросы

Вопросы пользователю не задавались: запуск неинтерактивный, пользователь прямо попросил не спрашивать и фиксировать предположения в документе. Все развилки закрыты в разделе «Закрытые развилки» — десять пунктов с причиной и ссылкой. Настройка `explore.decision_mode: ask_each_time` из `docs/settings.yaml` заменена прямым указанием пользователя в текущем сообщении.

## Итог

Кодовая база расходится с `docs/arch.md` по всем крупным границам сразу, поэтому переезд имеет смысл вести не «по модулям», а «по классам нарушений»: сначала общая часть и bootloader-ы (задачи 2-4), затем публичный слой и межмодульные вызовы (5-9), затем домен и хранение (11-14), затем чтение и сценарии (15-16), затем адаптеры (17-19), затем ресурсы и тесты (20-24), и только потом наведение имён, проверок и статуса (25-30).

Три места задают критический путь и должны планироваться первыми внутри своих задач: связь `PostMedia -> Media` вместе с FK и eager-load (она держит Posts, Media и две миграции), механизм Outbox как источник `Public/Event` для трёх модулей, и `Shared/Application/View/Media*` вместе с `ConfigBootloader` — без них Shared не отвяжется от модулей.

Работа с картой дальше: пункты закрываются по мере переезда, а её раздел «Закрытые развилки» служит входом для задачи 30 — именно эти отступления описываются явно с причиной.
