---
title: Модуль Posts — прикладной и презентационный слой + уведомления на действия
date: 2026-06-18 17:42
mode: strict
plan_size: normal
decision_mode: recommend_and_ask
status: meta-reviewed
reviewer: pending
meta_reviewers: [claude-haiku, claude-sonnet, claude-opus]
test_strategy: after_each_phase
logging_strategy: debug_precise
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  research: интерактивные ответы пользователя (отдельный research-файл не создавался)
---

# План реализации

## Задача

Достроить два внешних слоя модуля `Posts` (`Application` + `Presentation`) поверх
готового и закоммиченного `Domain`/`Repository` (`2f3bc87`): инфраструктурный каркас
(папки, `PostsBootloader`, регистрация в `Kernel`), 15 прикладных сценариев (записи,
реакции, комментарии, чтение), 7 видов уведомлений на действия, минимальные публичные
сценарии в смежных модулях `User`/`Media`/`Tags` и доработка read-фильтров
репозиториев.

Готово, когда: реализованы все маршруты схемы B (единый префикс `/api/v1/posts`),
каждый роут покрыт интеграционным тестом, уведомления 7 видов отправляются по правилам
получателей, `make test` и `make phpstan` зелёные, покрытие 100%.

## Контекст

- `Domain` + `Repository` модулей `Posts`/`Tags` закоммичены (`2f3bc87`). Папок
  `Application`/`Presentation`/`Infrastructure/Bootloader` у обоих модулей нет.
- **Схема БД не меняется, миграции не создаются.** Все изменения репозиториев — только
  методы/фильтры чтения (query-level через `when()`/`WhenSelect`), без правки таблиц.
- Точные доменные сигнатуры (проверено по коду):
  - `Post::create(UserId, PostText, PostStatus, AttachmentType, PostLesson, PostPractice, PostOriginal)`;
    методы `publish()`, `softDelete(\DateTimeImmutable)`, `incrementLikes/decrementLikes`,
    `incrementReposts/decrementReposts`, `incrementComments/decrementComments`,
    `setMediaAttachment()`, `setLesson/ setPractice`; связи `media`/`tags` — `HasMany`.
  - `Comment::create(PostId, UserId, CommentText, CommentParent)`;
    `delete(CommentDeletedBy, CommentDeletedAt, CommentDeletionReason)`,
    `incrementReplies/decrementReplies`, `isDeleted()`.
  - `PostLike::create(PostId, UserId)`, `CommentLike::create(CommentId, UserId)`,
    `PostMention::create(PostId, UserId)`, `CommentMention::create(CommentId, UserId)`,
    `PostMedia::create(Post, PostMediaReference, MediaPosition)`,
    `PostTag::create(PostId, TagId)`.
  - VO-фабрики: `PostText::fromString|none`, `CommentText::fromString`,
    `PostOriginal::pointingTo|none`, `CommentParent::pointingTo|none`,
    `CommentDeletedBy::by|none`, `CommentDeletedAt::at|notDeleted`,
    `CommentDeletionReason::none`, `PostLesson::none`, `PostPractice::none`.
  - `PostStatus { Draft, Published, Blocked }`, `AttachmentType { None, Media, Lesson, Practice }`.
  - Репозитории: `PostRepository::findById|findByUserId(UserId,?PostStatus,?PostId cursor,int limit)|findRepostsOf`;
    `CommentRepository::findById|findByPostId|findReplies`;
    `PostLikeRepository::findByPostAndUser|existsByPostAndUser`;
    `CommentLikeRepository::findByCommentAndUser|existsByCommentAndUser`;
    `PostMentionRepository`, `PostMediaRepository`, `PostTagRepository`;
    `TagRepository::findById|findByText|findByTexts(TagText ...)`.
- Контракты `Notifications` (проверено):
  - `NotificationSenderContract::send(UserId $recipient, NotificationContent): void` —
    стейджит один `NotificationRequested` в outbox и **не** делает свой `run()`.
  - `NotificationContent(NotificationTypeDefinition $type, NotificationTitle, NotificationBody, NotificationAction, NotificationActor)` — принимает VO, не строки.
  - `NotificationTitle::fromString` (≤255), `NotificationBody::fromString` (непустой),
    `NotificationAction::linkTo(string $actionType, string $actionId)`,
    `NotificationActor::of(UserId, string $name, string $avatarUrl)` — **бросает** на пустые
    `name`/`avatarUrl`.
  - `NotificationTypeDefinition { code(): NotificationTypeCode; defaultChannels(): NotificationChannelDefaults }`;
    `NotificationTypeRegistryContract::register(NotificationTypeDefinition ...)` — реестр-синглтон;
    модули-источники регистрируют свои виды в `boot(NotificationTypeRegistryContract)` своего бутлоадера
    (образец — README Notifications/другие модули-источники; сам `NotificationsBootloader` виды **не**
    регистрирует). `send()` делает fail-fast `registry->get()` — незарегистрированный вид падает в момент Command.
  - `NotificationChannelDefaults::of(NotificationChannel ...)`, enum `{ Database, Push, Realtime }`.
  - Образец вида: `tests/Support/Notifications/FixtureNotificationTypeDefinition`.
- Каркас HTTP/CQRS (проверено): контроллер `final readonly`, инъекция `Handler` + `CommandBusInterface`/`QueryBusInterface`
  в метод, `#[Route]`, `#[Attribute(key:'authUserId')]` в `Filter` (наследует `AttributesFilter`),
  ресурсы `extends AbstractResource` с `fromEntity`/`from*`, ответы
  `DataResponse<T>`/`CollectionResponse<T>`/`PaginationResponse<T>` + `PaginationMetaResponse(nextCursor, limit)`,
  `EmptySuccessResponse`. Атрибуты `#[Transactional]`/`#[LogOperation]` из `GianTiaga\SpiralCqrs`.
  Cursor-пагинация: репозиторий принимает `cursor`+`limit`, Handler запрашивает `limit+1` и
  вычисляет `nextCursor` (образец `ListNotificationsHandler`). Auth-стек middleware на роуты —
  как у `AuthController::sessions` (`AuthTransportWithStorageMiddleware` + `AuthContextAttributeMiddleware` + `RequireAuthenticatedMiddleware`),
  прописывается **в каждом `#[Route]`** (группового auth для `api` нет). **Параметры пути — угловые скобки
  `<id>`, не `{id}`**; путь-id читается во `Filter` через `#[Route(key:'id')]` + `#[Assert\Uuid]`
  (образец `MarkNotificationReadFilter`). `TranslatorInterface::trans(id, params, domain, locale)`
  поддерживает явную локаль (`Translator.php:113`, паттерн `SendLoginCodeHandler`); `User.locale` — enum
  `Shared\Domain\Enum\Locale` (`->value()` → `'ru'`/`'en'`). Возврат скаляра из репозитория допустим
  только для `count`/`exists`/статуса (`rules.md:73`); списки — доменными коллекциями.
- **Транзакционная семантика (проверено):** вложенный `#[Transactional]`-dispatch через Bus создаёт
  SAVEPOINT внутри текущей транзакции (`TransactionalMiddleware` → `database->transaction()`, докблок
  `CompleteRegistrationHandler`), а не отдельную закоммиченную транзакцию. Несколько `run()` в одной
  транзакции допустимы.
- Регистрация модульных бутлоадеров — `Kernel.php` (~строки 140–146), `PostsBootloader` добавляется
  после `NotificationsBootloader`.
- Локалей `posts.php` нет (есть `en`, `ru`). Конфига `user.php` нет (есть `locale.php`, `media.php`).
- Перевод 4xx-исключений на границе берётся из ключа: домен = второй сегмент (`app.posts.*` → `posts.php`).
- Новых внешних зависимостей план не вводит — проверка версий пакетов не требуется.

## Принятые решения

Источник «research» = интерактивные ответы пользователя, зафиксированные в research
`2026-06-18_15-15_...` (раздел «Ответы на вопросы»). Источник «пользователь (этот запрос)»
= ответы на вопросы при запуске `eda-plan`. Источник «autonomous» = `decision_mode:
recommend_and_ask`, низкие ставки/прямое следствие правил.

1. **Маршруты — схема B, единый префикс `/api/v1/posts`** (комментарии под `/posts/comments/<id>/...`,
   лента под `/posts/user/<id>`). Источник: пользователь (этот запрос).
2. **Межмодульные сценарии включены в этот план** (`User`/`Media`/`Tags`). Источник: пользователь (этот запрос).
3. Запись создаётся сразу `Published` либо как черновик `Draft`; черновик публикуется отдельным
   действием. Источник: research.
4. Медиа-вложения проходят проверку «существует+владелец+`Ready`» и перевод в permanent. Источник: research.
5. Теги — find-or-create через `Tags`. Источник: research.
6. Упоминания — явный список `userId` от клиента (без парсинга `@nick`). Источник: research.
7. Репост = цитата: новый `Post` с заполненным `original` + свой текст/медиа. Источник: research.
8. Уведомления — 7 раздельных видов. Источник: research.
9. Модерация/админка, глобальная лента (Feed), лента по тегу, редактирование, «надгробие»
   удалённого комментария, FK на занятия/практики — в roadmap, вне этой итерации. Источник: research.
10. Лайк идемпотентен (check-then-act, редкий конкурентный дубль допустим как 500 по
    `rules.md:52`); репост множественный. Источник: research / autonomous.
11. Счётчики: `comments_count` = комментарии верхнего уровня, `replies_count` = прямые ответы;
    удаление зеркалит инкремент; повторное удаление — no-op (guard `Comment::isDeleted()`). Источник: research / autonomous.
12. Self-action не уведомляет (`recipient == actor` → `send()` не вызывается). Источник: research / autonomous.
13. Дедуп по получателю в рамках одного действия по приоритету `mention` > `comment_reply` >
    `post_commented`. Источник: research / autonomous.
14. Ответ уведомляет только автора родительского комментария (`comment_reply`); автор записи
    получает `post_commented` только на комментарии верхнего уровня. Источник: research / autonomous.
15. Guard допустимости действия по состоянию цели: лайк/коммент/репост — только над
    `Published` и не удалённой/не `Blocked` записью; ответ — только на не удалённый комментарий
    не удалённой записи; `publish()` — только владелец и только из `Draft`. Источник: research / autonomous.
16. Права: удаление/публикация своей записи и удаление своего комментария — `entity.userId == authUserId`,
    иначе `ForbiddenException`. Источник: research / autonomous.
17. **Граница транзакции — единая и атомарная** (исправлено по мета-ревью, проверено по коду). Вложенный
    `#[Transactional]`-dispatch `Tags ResolveTags` создаёт SAVEPOINT внутри транзакции `CreatePostHandler`-а,
    а не отдельную закоммиченную транзакцию; `Media MakeMediaPermanent` (без `#[Transactional]`) своим
    `run()` флашит изменения **внутри** той же внешней транзакции. Итог: создание записи атомарно — при
    откате теги и permanent-медиа откатываются **вместе** с записью, осиротевших записей не возникает.
    Множественные `run()` в рамках одной транзакции допустимы. (Research ошибочно считал эти вызовы
    отдельными транзакциями — отсюда мнимая проблема осиротевших данных.) Источник: autonomous (исправление research по коду).
18. Лимит упоминаний на запись/комментарий — **≤50**; лимит тегов — **≤20**; лимит медиа-вложений —
    **≤10**. Применяется в `Filter` (`#[Assert\Count]`). Источник: autonomous (research предлагал лимит упоминаний).
19. Дефолтные каналы видов: `post_mention`/`comment_mention`/`post_commented`/`comment_reply` →
    `Database`+`Push`+`Realtime`; `post_like`/`post_repost`/`comment_like` → `Database`+`Push`.
    Настраиваемо, на архитектуру не влияет. Источник: autonomous (research).
20. **Дефолтный URL аватара** отдаётся через новый типизированный конфиг модуля `User`
    (`app/config/user.php` + `UserConfig` DTO в `Shared/Infrastructure/Configuration/User` +
    тест маппинга `ConfigMapper`), т.к. `env()` допустим только в конфигах и `NotificationActor::of()`
    требует непустой `avatarUrl`. Источник: autonomous (следствие правил «Typed config» и «env() только в конфигах»).
21. **Вложения `lesson`/`practice` отложены** (нет модулей для валидации; FK в roadmap). В этой
    итерации `attachmentType ∈ {None, Media}`; `Post::create` получает `PostLesson::none()`/`PostPractice::none()`.
    Источник: autonomous (следствие roadmap).
22. Текст уведомления рендерится **в локали получателя**: ключи `app.posts.notification.{вид}.{title|body}`
    в `app/locale/{lang}/posts.php`, перевод через `TranslatorInterface::trans(..., locale: $recipientLocale)`.
    Источник: research / autonomous.
23. **`Tags ResolveTags` — check-then-act**, а не insert+catch: `findByTexts` → создать недостающие
    `Tag::create` → `run()`; редкий конкурентный дубль по уникальному `tags.text` допустим как 500
    (`rules.md:52`), как и у лайков. (Уточнение research, который предлагал try-catch reread — он
    противоречит правилу «запрет try-catch в Handler-ах».) Источник: autonomous (следствие правил).
24. Тела ответов: создание записи/комментария → `200 DataResponse<*Resource>`; лайк/снятие лайка,
    публикация, удаление → `EmptySuccessResponse` (как в существующих контроллерах, без 201/204/409).
    «Уже опубликовано»/«уже удалено»/«повторный лайк» → идемпотентный no-op с успешным ответом.
    Источник: autonomous (следствие существующей конвенции и отсутствия 409-типа исключения).
25. **Синтаксис route-параметров — `<id>`** (Spiral), не `{id}`; путь-id читается во `Filter` через
    `#[Route(key:'id')]` + `#[Assert\Uuid]` (образец `MarkNotificationReadFilter`), чтобы кривой id давал
    `422`, а не `500` из доменного VO. Источник: autonomous (исправление по мета-ревью, проверено по коду).
26. **Медиа-вложение требует статус `Ready`** (обработанное). `CheckMediaAttachable`: нет → `404`, чужое →
    `403`, есть но не `Ready` → `422` (`ValidationException`); вызывается **до** `MakeMediaPermanent`,
    поэтому рассогласования кодов ошибок между ними нет. Источник: autonomous (мета-ревью).
27. **`#[LogOperation]` на всех Handler-ах**, включая межмодульные сценарии фаз 1–2. Источник: autonomous (мета-ревью).

## Целевой алгоритм

**Запись действия (Command, например создание поста).**
HTTP → `Controller` (тонкий, `#[Route]` со схемой B + auth-middleware) → `Filter`
(`#[Attribute('authUserId')]` + `#[Post]`/`#[Assert]`) → `Command` DTO → `CommandBus` →
`Handler::handle` (`#[Transactional]`, `#[LogOperation]`):
1. Guard по состоянию цели и правам (ранний `throw` `ForbiddenException`/`NotFoundException`/`ValidationException`).
2. До финального `run()`, **в той же транзакции** (SAVEPOINT/общий flush, решение 17):
   `Tags ResolveTags` (тексты → `TagId[]`), на каждый `mediaId` — `Media CheckMediaAttachable`
   (валидация) + `Media MakeMediaPermanent`.
3. Профили: актор (`authUserId`) и получатели через `User GetUserPublicProfile(s)`
   (имя+аватар актора, локаль получателей; для упоминаний — проверка существования).
4. Доменные операции: `VO → Entity::create()/доменный метод` (счётчики — доменными методами).
5. Стейджинг уведомлений: на каждого получателя (кроме self, после дедупа) —
   `NotificationSenderContract::send($recipient, NotificationContent)`.
6. `EntityManager::persist(...)` + один `EntityManager::run()` (записи `Posts` + стейджинг уведомлений).
7. `Controller` маппит результат в `Resource` → `DataResponse`/`EmptySuccessResponse`.

**Чтение (Query).** HTTP → `Controller` → `Filter` → `Query` DTO → `QueryBus` →
`Handler::handle` (без транзакции, `#[LogOperation]`): репозиторий своего модуля с фильтрами
(soft-delete, статус, верхний уровень, курсор) → сборка read-model `*View` обогащением из
`User`/`Media`/`Tags` (`GetUserPublicProfile(s)`, `GetMediaUrl`, `GetTags`) + флаг `likedByMe`
(`existsBy...AndUser`) → `Resource::fromView` → `DataResponse`/`PaginationResponse`.

**Сборка `NotificationContent`** (helper `Posts/Application/Notification/NotificationContentBuilder`):
`type` = экземпляр вида; `title`/`body` = `NotificationTitle/Body::fromString` из переведённых в
локали получателя ключей `app.posts.notification.*`; `action` = `NotificationAction::linkTo($type, $id->value())`;
`actor` = `NotificationActor::of($actorUserId, $actorProfile->name, $actorProfile->avatarUrl)`.

## Контракты реализации

### Данные и БД

Схема БД не затрагивается, миграции не создаются. Добавляются только методы/фильтры **чтения**
репозиториев (query-level через `when()`):

- `PostRepository::findVisibleByUserId(UserId, ?PostStatus, ?PostId cursor, int limit): PostCollection` —
  **новый** метод (существующий `findByUserId` не трогаем — у него докблок «soft-deleted решает вызывающий»
  и тест `PostRepositoryTest`): скрывает soft-deleted (`deletion IS NULL`); статус — параметр (Handler
  передаёт `Published` для чужой ленты, `null` для своей).
- `CommentRepository::findTopLevelByPostId(PostId, ?CommentId cursor, int limit): CommentCollection` —
  **новый** метод: `parent_comment_id IS NULL` + не удалённые, до `limit`/курсора (существующий
  `findByPostId` не трогаем — у него другие потребители).
- `CommentRepository::findReplies(...)` — добавить фильтр «не удалённые» (правка метода + его теста).
- `TagRepository::findByIds(TagId ...$ids): TagCollection` — новый read-метод для `GetTags`.
- `PostLikeRepository::findByUserAndPostIds(UserId, PostId ...$postIds): PostLikeCollection` и
  `CommentLikeRepository::findByUserAndCommentIds(UserId, CommentId ...$commentIds): CommentLikeCollection` —
  батч-выборка лайков пользователя (возвращают доменные коллекции, **не** `list<string>` — `rules.md:73`);
  флаг `likedByMe` собирает Handler, без N+1 в листингах.

### API и внешние контракты

Все роуты — `methods` по таблице, group `api`, auth-middleware-стек прописывается **в каждом `#[Route]`**
(как `AuthController::sessions`). `Filter`: `#[Attribute('authUserId')]` для текущего пользователя,
`#[Route(key:'id')]` + `#[Assert\Uuid]` для path-id, `#[Post]`/`#[Query]` для тела/строки с
`#[Assert\Length]` (PostText ≤5000, CommentText ≤2000) и `#[Assert\Count]`-лимитами (решение 18), чтобы
превышение/кривой id давали `422`, а не `500` из VO. Ответы — типизированные `Resource` в
`DataResponse`/`PaginationResponse`/`EmptySuccessResponse`.

```text
ЗАПИСИ (Command)
  POST   /api/v1/posts                       CreatePost     → DataResponse<PostResource>
  POST   /api/v1/posts/<id>/publish          PublishPost    → DataResponse<PostResource>
  POST   /api/v1/posts/<id>/repost           RepostPost     → DataResponse<PostResource>
  DELETE /api/v1/posts/<id>                  DeletePost     → EmptySuccessResponse
  POST   /api/v1/posts/<id>/like             LikePost       → EmptySuccessResponse
  DELETE /api/v1/posts/<id>/like             UnlikePost     → EmptySuccessResponse
КОММЕНТАРИИ (Command)
  POST   /api/v1/posts/<id>/comments         CommentPost    → DataResponse<CommentResource>
  POST   /api/v1/posts/comments/<id>/replies ReplyComment   → DataResponse<CommentResource>
  DELETE /api/v1/posts/comments/<id>         DeleteComment  → EmptySuccessResponse
  POST   /api/v1/posts/comments/<id>/like    LikeComment    → EmptySuccessResponse
  DELETE /api/v1/posts/comments/<id>/like    UnlikeComment  → EmptySuccessResponse
ЧТЕНИЕ (Query)
  GET    /api/v1/posts/<id>                   GetPost           → DataResponse<PostResource>
  GET    /api/v1/posts/user/<id>             GetUserFeed       → PaginationResponse<PostResource>
  GET    /api/v1/posts/<id>/comments         GetPostComments   → PaginationResponse<CommentResource>
  GET    /api/v1/posts/comments/<id>/replies GetCommentReplies → PaginationResponse<CommentResource>
```

Параметры и ошибки (ключевое):

- `CreatePost` body: `text?:string`, `draft?:bool=false`, `attachmentType?:string('none'|'media')`,
  `mediaIds?:list<uuid>` (≤10), `tags?:list<string>` (≤20), `mentions?:list<uuid>` (≤50). Ошибки:
  `422` (валидация/несуществующее упоминание), `404` (медиа нет/не `Ready`), `403` (медиа чужое).
- `RepostPost` `<id>` — целевая запись; body как у `CreatePost` (text/media/tags/mentions). `404`/`403` если
  цель невидима; уведомление `posts.post_repost` автору оригинала (не self).
- `PublishPost` — `403` не владелец, `404` нет/Blocked/удалена, успешный no-op если уже `Published`.
- `DeletePost` — `403`/`404`; если запись-репост → `decrementReposts` у оригинала; идемпотентно.
- `LikePost`/`UnlikePost` — идемпотентно; `404`/`403` если запись невидима; уведомление `posts.post_like` (не self).
- `CommentPost` body: `text:string` (обяз.), `mentions?:list<uuid>` (≤50). Уведомления `posts.post_commented`
  (автору записи, не self) + `posts.comment_mention` (упомянутым). `incrementComments`.
- `ReplyComment` body как у `CommentPost`; `incrementReplies` родителя; уведомление `posts.comment_reply`
  (автору родителя, не self) + `posts.comment_mention`.
- `DeleteComment` — `403`/`404`; зеркалит счётчик (верхний → `decrementComments` записи; ответ →
  `decrementReplies` родителя); идемпотентно.
- `LikeComment`/`UnlikeComment` — идемпотентно; уведомление `posts.comment_like` (не self).
- Чтение: `GetPost` — `404` если невидима (чужой `Draft`/`Blocked`/удалена). `GetUserFeed` — свои
  черновики видны только владельцу; иначе только `Published`. Листинги — cursor (`cursor?:uuid`, `limit?:int≤100`).

**Новые публичные сценарии смежных модулей** (вызываются Posts через Bus):

```text
User/Application/Query/GetUserPublicProfile
  GetUserPublicProfileQuery(userId:string) → UserPublicProfileView{userId:string, name:string, avatarUrl:string, locale:string}
  Handler: name = User.name->value(); locale = User.locale->value(); avatarUrl = Media GetMediaUrl(avatar_media_id),
  а если аватар пуст ИЛИ медиа недоступно (удалено/не Ready) → UserConfig.defaultAvatarUrl. avatarUrl
  ВСЕГДА непустой (иначе NotificationActor::of() даёт 500). NotFoundException если пользователя нет.

User/Application/Query/GetUserPublicProfiles  (батч)
  GetUserPublicProfilesQuery(userIds:list<string>) → UserPublicProfileCollection (extends Illuminate Collection)
  Для списков (авторы ленты/комментариев) и проверки упоминаний (вызывающий сверяет
  запрошенные vs найденные; недостающие → ValidationException у Posts).

Media/Application/Query/CheckMediaAttachable
  CheckMediaAttachableQuery(mediaId:string, ownerUserId:string) → MediaAttachableResult{mediaId}
  «существует И принадлежит ownerUserId И статус Ready»: NotFoundException (нет) → 404,
  ForbiddenException (чужое) → 403, ValidationException (есть, но не Ready) → 422. Вызывается ДО
  MakeMediaPermanent, поэтому тот всегда получает Ready. MakeMediaPermanent переиспользуется как есть.

Tags/Application/Command/ResolveTags
  ResolveTagsCommand(texts:list<string>, creatorUserId:string) → ResolveTagsResult{tagIds:list<string>}
  #[Transactional]; check-then-act: findByTexts → недостающие Tag::create + EntityManager::persist → один run();
  результат ПЕРЕСОБРАТЬ в порядке входных текстов (findByTexts через where in порядок не гарантирует);
  дубль текста по уникальному tags.text при гонке → 500 (rules.md:52).

Tags/Application/Query/GetTags
  GetTagsQuery(tagIds:list<string>) → TagTextCollection (id→текст), для ресурса записи.
```

**7 видов уведомлений** (`Posts/Application/Notification/*`, реализуют `NotificationTypeDefinition`,
регистрируются в `PostsBootloader`):

```text
posts.post_mention     posts.comment_mention   posts.post_commented   posts.comment_reply
posts.post_like        posts.post_repost       posts.comment_like
```

`action` (deep-link): `post`→`linkTo('post', postId)`, `comment`/`reply`/`comment_*`→`linkTo('comment', commentId)`.

## Фазы выполнения

### 1. Каркас модуля Posts, бутлоадер и 7 видов уведомлений

Цель: завести инфраструктуру модуля и фундамент уведомлений, на котором стоят остальные фазы.

Что сделать:
- Создать папки `Posts/Application`, `Posts/Presentation/Http/{Controller,Filter,Resource}`,
  `Posts/Application/Notification`, `Posts/Infrastructure/Bootloader`.
- `Posts/Infrastructure/Bootloader/PostsBootloader extends Bootloader`: метод
  `boot(NotificationTypeRegistryContract $typeRegistry)` вызывает `$typeRegistry->register(...)` с 7
  экземплярами видов (образец регистрации — README Notifications/другие модули-источники, **не**
  `NotificationsBootloader`, который виды не регистрирует). Зарегистрировать `PostsBootloader::class`
  в `Kernel.php` после `NotificationsBootloader::class`.
- 7 классов `*NotificationType` (`final readonly`, реализуют `NotificationTypeDefinition`): `code()`
  = `NotificationTypeCode::fromString('posts.<вид>')`, `defaultChannels()` = `NotificationChannelDefaults::of(...)`
  по решению 19.
- `Posts/Application/Notification/NotificationContentBuilder` — сервис сборки `NotificationContent`:
  на вход вид, профиль актора, локаль получателя, тип/`id` действия, параметры текста; рендерит
  `title`/`body` через `TranslatorInterface::trans(key, params, domain:'posts', locale:$recipientLocale)`
  по ключам `app.posts.notification.<вид>.{title|body}`, собирает `NotificationAction`/`NotificationActor`.
- Файлы переводов `app/locale/ru/posts.php` и `app/locale/en/posts.php`. Ключи хранят **полный путь**
  `app.posts.notification.<вид>.{title|body}` (домен выводится из второго сегмента → `posts.php`),
  плюс ключи сообщений доменных ошибок Posts (`app.posts.not_found`, `app.posts.forbidden`,
  `app.posts.validation_*` и т.п.) для переводимых 4xx-исключений.

Результат: `PostsBootloader` поднимается в ядре и регистрирует ровно 7 видов; `NotificationContentBuilder`
отдаёт валидный `NotificationContent` на тестовых данных; переводы доступны на ru/en.

Сценарии тестирования:
- Kernel-тест: контейнер поднимается; каждый из 7 кодов `posts.*` реально резолвится
  `NotificationTypeRegistryContract::get()` (не только присутствует в `all()`); каналы по умолчанию
  совпадают с решением 19.
- Unit/Kernel-тест `NotificationContentBuilder`: для каждого вида `title`/`body` непустые и переведены в
  заданной локали (ru и en дают разный текст); `action`/`actor` собраны корректно; пустой `avatarUrl`
  невозможен (используется дефолт из профиля).

Проверка: `make test`, `make phpstan` зелёные; новые тесты фазы проходят.

### 2. Межмодульные публичные сценарии (User, Media, Tags) + конфиг User

Цель: дать Posts легальные точки входа в чужие модули; без них уведомления, медиа и теги не собираются.

Что сделать:
- **User**: `app/config/user.php` + `UserConfig` (`Shared/Infrastructure/Configuration/User`, реализует
  `TypedConfig`, `configName()='user'`) с полем `defaultAvatarUrl` (через `env()` в конфиге; **валидировать
  непустоту** — пустой дефолт сорвёт `NotificationActor::of()` в 500) + тест маппинга `ConfigMapper`
  (образец `MediaConfigTest`/`LocaleConfigTest`). `GetUserPublicProfile`/`GetUserPublicProfiles` (батч) по
  контрактам выше; коллекция `UserPublicProfileCollection extends Illuminate\Support\Collection`. Все
  Handler-ы — `#[LogOperation]`. Кросс-модульные Query-Handler-ы — конкретные классы, контейнер Spiral
  автовайрит их в Handler-ы Posts без явных биндингов.
- **Media**: `Media/Application/Query/CheckMediaAttachable` (`Query`+`Handler`+`MediaAttachableResult`):
  `findById` → `NotFoundException` если нет, `ForbiddenException` если `uploadedById != ownerUserId`,
  `ValidationException` если есть, но не `isReady()` (решение 26). (`MakeMediaPermanent` не трогаем —
  переиспользуем.)
- **Tags**: `Tags/Application/Command/ResolveTags` (решение 23, `#[Transactional]`): `findByTexts` →
  недостающие `Tag::create` + `persist` → один `run()`; результат пересобрать **в порядке входных текстов**.
  И `Tags/Application/Query/GetTags`; `TagRepository::findByIds(TagId ...)` (read-метод).

Результат: каждый сценарий вызывается через Bus и возвращает описанный контракт; профиль всегда отдаёт
непустой `avatarUrl` и `locale`; `ResolveTags` идемпотентен по тексту; `CheckMediaAttachable` различает
404/403.

Сценарии тестирования:
- `GetUserPublicProfile`: есть аватар → реальный URL; нет аватара → дефолтный URL из конфига; нет
  пользователя → 404; `locale` отдаётся. Батч: порядок/полнота, отсутствующие исключены.
- `CheckMediaAttachable`: `Ready`+владелец → ok; не `Ready` → 422; чужое → 403; нет → 404.
- `ResolveTags`: новые тексты создаются; существующие переиспользуются (id стабилен); повтор текста
  в одном запросе не плодит дублей; порядок `tagIds` = порядку текстов. `GetTags`: id→текст,
  отсутствующие исключены. `UserConfig` mapping-тест + тест «пустой `defaultAvatarUrl` → ошибка на старте».

Проверка: `make test`, `make phpstan` зелёные.

### 3. Записи: создание, публикация, репост, удаление (Command + HTTP + интеграционные тесты)

Цель: сквозной слой работы с записью, включая медиа-вложения, теги, упоминания и уведомления
`post_mention`/`post_repost`.

Что сделать:
- `Application/Command/{CreatePost,PublishPost,RepostPost,DeletePost}` — `Command` DTO + `Handler`
  (`#[Transactional]`,`#[LogOperation]`). Логика по «Целевому алгоритму»: guard состояния/прав,
  вложенные вызовы (`ResolveTags`, `CheckMediaAttachable`+`MakeMediaPermanent`) внутри той же транзакции
  (решение 17 — атомарно), доменные методы и счётчики. `CreatePost`: `Post::create(...,
  PostLesson::none(), PostPractice::none(), PostOriginal::none())`, при медиа — `setMediaAttachment()` +
  `PostMedia::create`, `PostTag::create`, `PostMention::create` + `send(post_mention)` каждому
  упомянутому (не self). `RepostPost`: загрузить оригинал (`findById`, проверить видимость),
  `Post::create(..., PostOriginal::pointingTo($id->value()))`, `original->incrementReposts()`,
  `send(post_repost)` автору оригинала (не self). `DeletePost`: guard `PostDeletion::isDeleted()`
  (повторное удаление → no-op, без повторного `decrementReposts`); иначе `softDelete($now)` + для репоста
  `original->decrementReposts()`.
- `Presentation/Http`: `PostController` с методами роутов записей (схема B) + auth-middleware;
  `Filter` для каждого действия (`#[Attribute('authUserId')]`, `#[Post]`, `#[Assert\Count]`-лимиты
  решения 18, `attachmentType` через `#[Assert\Choice(['none','media'])]`); `PostResource extends
  AbstractResource` (+ вложенные `AuthorResource`, `PostMediaItemResource`, опциональный `original:
  PostResource|null`) и read-model `PostView`/`AuthorView`/`PostMediaItemView` в `Application`.
  `Handler` записи возвращает `PostView` (собранный из созданной сущности + известных профилей/URL).

Результат: все 6 роутов записей работают и проходят интеграционный тест; счётчики и уведомления
ведут себя по решениям 10–17; счётчики не уходят ниже нуля при повторных операциях; источник времени
`softDelete` — единый (DI `\DateTimeImmutable` / clock).

Сценарии тестирования (интеграционные на каждый роут + поведенческие):
- Создание `Published`/`Draft`; с медиа (валидное → permanent; чужое → 403; не `Ready` → 422; нет → 404);
  с тегами (новые/существующие); с упоминаниями (валидные → `post_mention` в outbox каждому, кроме
  self; несуществующий → 422; >50 → 422). Кривой path-id/превышение лимитов текста → 422.
- Публикация: владелец из `Draft` → `Published`; не владелец → 403; уже `Published` → no-op 200;
  удалённая/`Blocked` → 404.
- Репост: создаёт запись с `original`, `+1` к `reposts` оригинала, `post_repost` автору (не self);
  репост своей записи не уведомляет; цель невидима → 404.
- Удаление: владелец → soft-deleted; повторное → no-op; репост → `-1` reposts оригинала; не владелец → 403.

Проверка: `make test`, `make phpstan` зелёные; интеграционные тесты роутов записей зелёные.

### 4. Комментарии и реакции (Command + HTTP + интеграционные тесты)

Цель: сквозной слой комментариев, ответов и лайков с уведомлениями `post_commented`/`comment_reply`/
`comment_mention`/`post_like`/`comment_like` и дедупом.

Что сделать:
- `Application/Command/{LikePost,UnlikePost,CommentPost,ReplyComment,DeleteComment,LikeComment,UnlikeComment}`
  — `Command` DTO + `Handler` (`#[Transactional]`,`#[LogOperation]`). Идемпотентность лайков
  (`existsByPostAndUser`/`existsByCommentAndUser` → no-op), снятие лайка через `EntityManager::delete`.
  `CommentPost`: `Comment::create(..., CommentParent::none())` + `incrementComments` записи;
  `ReplyComment`: `CommentParent::pointingTo($parentId)` + `incrementReplies` родителя.
  Упоминания в комментариях → `CommentMention::create` + `send(comment_mention)`.
  Уведомления адресатам по решениям 12–14, **дедуп** перед `send()`: собрать кандидатов `(получатель, вид)`,
  на каждого получателя оставить один вид с наибольшим приоритетом (`mention` > `comment_reply` >
  `post_commented`), исключить self, затем `send()`.
  `DeleteComment`: `Comment::delete(CommentDeletedBy::by, CommentDeletedAt::at, CommentDeletionReason::none())`,
  зеркальный счётчик (верхний → запись, ответ → родитель), идемпотентно.
- `Presentation/Http`: `CommentController` (роуты комментариев/лайков схемы B) + auth-middleware;
  `Filter` для каждого действия; `CommentResource extends AbstractResource` (+ `AuthorResource`,
  `parentId`, `likedByMe`) и read-model `CommentView` в `Application`. `Handler` создания
  комментария/ответа возвращает `CommentView`.

Результат: 7 роутов комментариев/реакций работают и покрыты интеграционными тестами; счётчики
верхнего уровня и ответов раздельны; дедуп и self-suppression уведомлений соблюдены.

Сценарии тестирования (интеграционные на каждый роут + поведенческие):
- Комментарий верхнего уровня: `+1 comments_count`, `post_commented` автору (не self), `comment_mention`
  упомянутым; guard невидимой/удалённой записи → 404/403.
- Ответ: `+1 replies_count` родителя, `comment_reply` автору родителя (не self), автор записи
  `post_commented` на ответ **не** получает; ответ на удалённый комментарий → 404/403.
- Дедуп: автор записи упомянут в комментарии к своей записи → получает **один** `comment_mention`
  (приоритет mention), не `post_commented`.
- Лайки записи/комментария: идемпотентность (двойной лайк = один `*_like` и один инкремент),
  снятие отсутствующего = no-op, self-лайк не уведомляет.
- Удаление комментария: верхний → `-1 comments_count`; ответ → `-1 replies_count` родителя;
  повторное → no-op; не владелец → 403.

Проверка: `make test`, `make phpstan` зелёные; интеграционные тесты роутов фазы зелёные.

### 5. Чтение: запись, лента автора, комментарии, ответы (Query + HTTP + интеграционные тесты)

Цель: cursor-чтение с обогащением read-model из User/Media/Tags и флагом `likedByMe`, с фильтрами в репозиториях.

Что сделать:
- Доработать репозитории (см. «Данные и БД»): новый `findVisibleByUserId` (не менять `findByUserId`),
  новый `findTopLevelByPostId`, фильтр удалённых в `findReplies`, `TagRepository::findByIds`,
  батч-методы `findByUserAndPostIds`/`findByUserAndCommentIds` (возвращают коллекции лайков).
- `Application/Query/{GetPost,GetUserFeed,GetPostComments,GetCommentReplies}` — `Query` DTO + `Handler`
  (`#[LogOperation]`, без транзакции). Cursor `limit+1` (образец `ListNotificationsHandler`).
  Обогащение: авторы через `GetUserPublicProfile(s)` (в листингах — батч), URL медиа через
  `Media GetMediaUrl` на каждый `PostMedia`, тексты тегов через `Tags GetTags`, `likedByMe` через
  батч-методы лайков, данные оригинала для репоста. `GetPost`: guard видимости (чужой `Draft`/`Blocked`/
  удалённая → 404). `GetUserFeed`: свои черновики только владельцу (статус `null` если `authUserId==id`,
  иначе `Published`). `GetPostComments`: только верхний уровень. Возвращают `PostView`/`PostView[]+nextCursor`/
  `CommentView[]+nextCursor`.
- `Presentation/Http`: добавить read-методы в `PostController`/`CommentController` (роуты чтения схемы B) +
  auth-middleware; `Filter` чтения (`#[Attribute('authUserId')]`, `#[Query]` `cursor`/`limit` с
  `#[Assert\Uuid]`/`#[Assert\LessThanOrEqual(100)]`). Переиспользовать `PostResource`/`CommentResource`
  из фаз 3–4 (`fromView`), ответы `DataResponse`/`PaginationResponse`+`PaginationMetaResponse`.

Результат: 4 роута чтения работают и покрыты интеграционными тестами; листинги отдают ровно `limit`
строк с корректным `nextCursor`; soft-deleted и чужие черновики не утекают; read-model обогащён.

Сценарии тестирования (интеграционные на каждый роут + поведенческие):
- `GetPost`: своя/чужая `Published` с автором, URL медиа, тегами, счётчиками, `likedByMe`, оригиналом
  (для репоста); чужой `Draft`/`Blocked`/удалённая → 404.
- `GetUserFeed`: владелец видит свои черновики; посторонний — только `Published`; soft-deleted скрыты;
  пагинация по курсору отдаёт `limit` и `nextCursor`, последняя страница → `nextCursor=null`.
- `GetPostComments`: только верхний уровень (`parent IS NULL`), удалённые скрыты, cursor стабилен.
- `GetCommentReplies`: только ответы родителя, удалённые скрыты, cursor.

Проверка: `make test`, `make phpstan` зелёные; интеграционные тесты роутов чтения зелёные; общий прогон
покрытия (`make qa`/`test-coverage`) — 100%.

## Тесты

Стратегия: `after_each_phase`. Каждая фаза завершается своими тестами и зелёными `make test`/`make phpstan`;
фаза не считается готовой, пока тесты не проходят. Фазы 1–2 — Kernel/Unit-тесты сценариев и контрактов;
фазы 3–5 — **интеграционный тест на каждый HTTP-роут** (`rules.md:116`) плюс поведенческие тесты счётчиков,
идемпотентности, guard-ов и уведомлений (проверка стейджинга в outbox). Тесты приложения запускаются только
через Docker (`make test`); адрес `minio:9000` доступен лишь в Docker-сети. Итоговая цель — 100% покрытие
**всего проекта** (гейт `make qa`/`test-coverage` зелёный), не только новых строк.

Обязательные доп. кейсы (по мета-ревью): каждый из 7 видов резолвится `NotificationTypeRegistryContract::get()`
(не только `all()`); пустой `defaultAvatarUrl` → ошибка на старте; повторное удаление записи/комментария не
уводит счётчик в минус и не шлёт повторное уведомление; ответ на комментарий не порождает `post_commented`
автору записи; кривой path-id → 422; превышение лимитов текста/тегов/упоминаний/медиа → 422; медиа не `Ready`
при вложении → 422.

## Логирование

Стратегия: `debug_precise`. Каждый `Handler` помечается `#[LogOperation]` (debug-лог старта и времени).
Дополнительно — точечные `logger->debug` на ключевых развилках с контекстом-идентификаторами (camelCase):
guard-отказы (`forbidden`/`not found`/`already published`/`already deleted` — нормальный flow → DEBUG, не WARN),
идемпотентные no-op лайков, изменения счётчиков, стейджинг каждого уведомления (вид, получатель), результат
дедупа (кого схлопнули). Тексты логов — на русском,
без англицизмов; идентификаторы (`postId`, `commentId`, `recipientId`, `typeCode`) остаются техническими.
Бизнес-успехи остаются на DEBUG; INFO/WARN/ERROR не вводятся (нет ключевых бизнес-событий уровня INFO и
инфраструктурных сбоев в этих сценариях).

## Изменения после мета-ревью

### После моделей

- **+ Добавлено:** транзакционная семантика SAVEPOINT (решение 17 переписано: создание записи атомарно,
  «осиротевших тег/медиа» не бывает); синтаксис роутов `<id>` + чтение path-id во `Filter` через
  `#[Route(key:'id')]`+`#[Assert\Uuid]` (решение 25); коды ошибок `CheckMediaAttachable` 404/403/422 и
  требование `Ready` (решение 26); `#[LogOperation]` на всех Handler-ах (решение 27); валидация непустого
  `defaultAvatarUrl` и fallback аватара при недоступном медиа; персист новых тегов + пересборка порядка в
  `ResolveTags`; guard `isDeleted()` в `DeletePost`; явный алгоритм дедупа; доп. тест-кейсы (резолв вида
  через `get()`, счётчик не в минус, ответ не шлёт `post_commented`, кривой id/лимиты → 422).
- **~ Изменено:** паттерн регистрации видов — `boot(NotificationTypeRegistryContract)` модуля-источника,
  а не `NotificationsBootloader`; `findLikedPostIds(): list<string>` → батч-методы, возвращающие коллекции
  лайков (`rules.md:73`); `findByUserId`/`findByPostId` не переопределяем, а добавляем `findVisibleByUserId`/
  `findTopLevelByPostId` (есть тесты у существующих); ключи переводов хранят полный путь `app.posts.*`;
  `UserPublicProfileView` поля — строки (`name->value()`, `locale->value()`); 100% покрытие — всего проекта.
- **Отклонено:** «добавить `#[Transactional]` в `MakeMediaPermanentHandler`» — не нужно, его `run()`
  участвует во внешней транзакции через SAVEPOINT (решение 17). «Регистрировать кросс-модульные
  Query-Handler-ы биндингами в бутлоадерах» — Spiral автовайрит конкретные классы Handler-ов без биндингов.

## Документация и эксплуатация

- `app/config/user.php` + env-переменная дефолтного URL аватара (`UserConfig.defaultAvatarUrl`) — добавить
  в `.env`/`.env.sample` и описать в эксплуатации (значение зависит от окружения/CDN).
- Новые ключи переводов `app/locale/{ru,en}/posts.php` — поддерживать оба языка синхронно.
- После добавления роутов прогнать `php app.php openapi:generate` и проверить обновление
  `public/openapi/openapi.yml` (15 новых операций под `/api/v1/posts`).
- Уведомления требуют работающего `outbox:relay` (один экземпляр) — без него `send()` только стейджит,
  доставки не будет; учесть в релизе.
- `docs/roadmaps/posts.md` (вне этого плана): модерация/админка, Feed, лента по тегу/поиск тегов,
  редактирование записей/комментариев, «надгробие» удалённого комментария, FK на занятия/практики,
  вложения `lesson`/`practice`.

## Прогресс выполнения

Журнал: `docs/executions/2026-06-19_12-51_posts-application-presentation.md`

- [x] Шаг 1: Каркас модуля Posts, бутлоадер и 7 видов уведомлений
- [x] Шаг 2: Межмодульные публичные сценарии User, Media, Tags и конфиг User
- [x] Шаг 3: Записи: создание, публикация, репост, удаление
- [x] Шаг 4: Комментарии и реакции
- [x] Шаг 5: Чтение: запись, лента автора, комментарии, ответы
- [x] Финальная проверка: `make qa` (стиль + `make phpstan` + 100% покрытие) зелёный
