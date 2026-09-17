---
title: Волна F переезда на целевую архитектуру — Reader вместо представлений и целевая раскладка Application
date: 2026-09-16 13:10
mode: normal
plan_size: normal
decision_mode: autonomous
status: draft
reviewer: none
plan_review: none
plan_review_fix: none
test_strategy: end_of_plan
logging_strategy: standard
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  business: []
  references:
    - docs/references/reader.md
    - docs/references/data.md
    - docs/references/result-dto.md
    - docs/references/query-handler.md
    - docs/references/command-handler.md
    - docs/references/application-contract.md
    - docs/references/domain-collection.md
    - docs/references/api-resource.md
    - docs/references/domain-service.md
  research: docs/artifacts/researches/2026-09-15_17-25_karta-rashozhdenij-s-celevoj-arhitekturoj.md
---

# План реализации

## Задача

Задачи roadmap 15 и 16 (`docs/artifacts/roadmaps/2026-09-15_17-16_migraciya-na-celevuyu-arhitekturu.md`): чтение под ответ выполняет Reader там, где ответу нужен признак вне агрегата; `Application/View` и все `*ViewAssembler` удаляются; `Application` каждого модуля содержит только целевые разделы `Command/{Action}`, `Query/{Action}`, `Contract`, `Data`, `Result`, `Exception`.

Дополнительно волна закрывает два хвоста:
- импорт `Spiral\Translator\TranslatorInterface` в `Auth/Application/Command/SendLoginCode/SendLoginCodeHandler.php` и `Posts/Application/Notification/NotificationContentBuilder.php` — заводится порт перевода в `Application/Contract` каждого из двух модулей, реализация в `Infrastructure/Spiral`;
- проверка «разные условия показа — разные сценарии»: `GetUserFeedQuery`/`GetUserFeedHandler` (`app/src/Modules/Posts/Application/Query/GetUserFeed/`) вычисляет `$isOwner = $owner->equals($viewer)` и ветвится на статусы `null`/`Published`/`Blocked` — это ровно случай из `docs/arch.md`, разделяется на два сценария. Остальные Query-проверки Posts (`GetPost`, `GetPostComments`, `GetCommentReplies`) используют единую `PostVisibilityPolicy::isVisibleTo()` без ветвления на «своя лента»/«чужая лента» — они не разделяются, это одно правило видимости, не два сценария.

Границы (заданы в вызове, не пересматриваются): внешнее поведение (маршруты, формы запросов/ответов, JSON-форма каждого ответа побайтово, ошибки, переводы) не меняется; схема БД не меняется, новых миграций нет; расположение миграций и тестов — задачи 20/24, не эта волна; сплошное переименование классов — задача 25, не эта волна.

Режим проверок изменён пользователем: фазы 1-9 не запускают `make qa`/`make test`/`make phpstan`/`make test-unit`; проверяющий фазы читает код и сверяет с этим планом и карточками `docs/references/`. Полный `make qa` запускается один раз — в фазе 10; при падении причина чинится точечно в этой же волне до зелёного результата, волна не переигрывается. Опыт волны E показывает, что в приёмку приходит сразу много дефектов — фаза 10 закладывает несколько циклов точечных исправлений, а не один прогон.

Известное падение `Tests\Feature\Modules\Media\Infrastructure\S3MediaFileServiceTest::testCopyObjectToPublicBucketIsAnonymouslyReadable` существует до волны, в задачу не входит и не чинится.

## Целевой алгоритм

### Где реально нужен Reader, а где хватает Repository

Правило `docs/references/reader.md`: «Если ответу хватает полей агрегата, Query handler читает через Repository, и Reader не заводится». Чтение фактического кода (а не только карты расхождений, которая писалась до волн A-E и не проверяла код построчно) показывает разный результат по модулям:

```text
Модуль          Нужен Reader?  Почему
Auth            нет            GetUserSessionsHandler уже читает AuthTokenCollection через
                               AuthTokenRepository и группирует по sessionId в PHP; все нужные поля
                               (sessionId, createdAt, expiration, ip, userAgent) есть на AuthToken —
                               признака вне агрегата нет
Media           нет            FindMediaUrlsHandler уже читает Media с конверсиями одним вызовом
                               MediaRepository::findByIdsWithConversions() (агрегат Media включает
                               конверсии как внутренние сущности, решение волны E) и строит URL через
                               MediaUrlServiceContract — признака вне агрегата нет
Notifications   нет            ListNotificationsHandler уже читает Notification через
                               NotificationRepository::findPageForRecipient() постранично; все поля
                               ответа (type, title, body, action, actor-снимок, read, createdAt) есть
                               на Notification, аватар дочитывается через Media/Public в handler-е.
                               GetNotificationSettings строит матрицу «вид × канал» из
                               NotificationSettingRepository (Entity) и NotificationTypeCatalogContract
                               (внутренний реестр Notifications, не таблица соседа) — это чтение через
                               Repository и собственный Contract, не Reader
Posts           да             likedByMe — флаг по зрителю не на агрегате (docs/arch.md называет это
                               канонический случай Reader), post_media/post_tags — образец reader.md и
                               data.md буквально про Posts (PostReader/PostData с mediaIds)
```

Итог: Reader заводится только у Posts. У Auth, Media, Notifications, Tags, User, Outbox, Access, System Reader не заводится; их волна закрывает переносом View/Dto/Service в целевые разделы и подтверждением, что Repository+Public достаточно.

### Posts: Reader и разделение GetUserFeed

```text
Reader                Метод                         Data                       Признак вне агрегата
PostReader            myFeed(ownerUserId, cursor,    PostPageData, PostData     страница своей ленты:
                       limit)                                                   все статусы кроме
                                                                                Blocked + mediaIds + tagIds
PostReader            userFeed(ownerUserId, cursor,  PostPageData, PostData     страница чужой ленты:
                       limit)                                                   только Published +
                                                                                mediaIds + tagIds
PostViewerReader       likedByMe(postIds, viewerId)  PostViewerFlagsData        лайк по post_likes —
                                                                                зрителю, не автору
CommentViewerReader    likedByMe(commentIds,         CommentViewerFlagsData     лайк по comment_likes —
                       viewerId)                                               зрителю, не автору
```

`GetUserFeedQuery`/`GetUserFeedHandler` разделяется на `GetMyFeedQuery`/`GetMyFeedHandler`/`GetMyFeedResult` (владелец смотрит свою ленту, `PostReader::myFeed()`) и `GetUserFeedQuery`/`GetUserFeedHandler`/`GetUserFeedResult` (посторонний смотрит чужую, `PostReader::userFeed()`) — два маршрута HTTP, объявленных сейчас как один и тот же путь с разным разрешённым набором статусов по признаку «свой/чужой» в контроллере или в двух контроллерных методах; какой из них какой обслуживает сегодня, определяется по фактическому маршруту в исполнении фазы, JSON-форма и правила видимости не меняются ни для одного из двух случаев. `CommentReader` не заводится: страницы комментариев и ответов (`GetPostComments`, `GetCommentReplies`) целиком читаются через `CommentRepository`, только флаг `likedByMe` идёт через `CommentViewerReader`.

Comment/Post-level счётчики (`likesCount`, `repostsCount`, `commentsCount`, `repliesCount`) остаются полями агрегата (уже хранятся денормализованными на `Post`/`Comment`) — Reader их не пересчитывает.

### Что переносится из представлений в Result и Domain/Service

```text
Модуль          Правило переноса
Auth            View -> Application/Result рядом со своим Query/Command (SessionResult(+Collection),
                IssuedTokenPair); логика SessionViewAssembler (группировка токенов по sessionId) —
                приватными методами в GetUserSessionsHandler, отдельного класса не остаётся
Media           Dto делится: форма ответа -> Application/Result рядом со своим Command/Query
                (MediaResult, RequestMediaUploadResult, MakeMediaPermanentResult и т.п.); данные
                технических портов (MediaFileMeta, MediaPresignedPart(Collection), MediaUploadSpec,
                MediaObjectHead, MediaConversionUrl(Collection), Media{Audio,Video}ProcessingResult)
                остаются рядом со своим Contract в Application/Contract — аргумент/результат
                storage/ffmpeg-порта, не HTTP-ответ (решение 6); Service (MediaTypeResolver,
                MediaConversionsChecker) -> Domain/Service, чистые правила без Cycle/Spiral
Notifications   View -> Application/Result рядом со своим Query (NotificationResult с вложенными
                Action/Actor, NotificationSettingResult); логика NotificationViewAssembler (аватар
                через Media/Public) и NotificationSettingsViewFactory (матрица вид × канал) —
                приватными методами в соответствующих Query handler-ах, само чтение (Repository +
                Public + NotificationTypeCatalogContract) не меняется
Tags            Dto/TagTextCollection -> Query/GetTags/GetTagsResult, та же форма карты «id -> текст»
User            Dto -> Application/Result (UserProfileResult(+Collection)) и
                Query/FindUserForAuth/FindUserForAuthResult; логика UserPublicProfileAssembler
                (аватар пакетно через Media/Public, N+1 нет уже сейчас) — приватными методами в
                GetUserPublicProfileHandler/GetUserPublicProfilesHandler
Outbox          Dto/SerializedOutboxMessage остаётся тем же классом, физически переезжает в
                Application/Contract рядом с OutboxMessageSerializerContract, который его использует
                как аргумент/результат порта — не форма HTTP-ответа
Posts           View -> Application/Result рядом с Query/GetPost (PostResult, PostMediaResult,
                AuthorResult, TagResult — переиспользуются GetMyFeed/GetUserFeed/GetPostComments) и
                Query/GetPostComments (CommentResult — переиспользуется GetCommentReplies); логика
                PostViewAssembler/CommentViewAssembler (Public-вызовы, батчинг, уже пакетная) —
                приватными методами Query handler-ов, общие куски — статическими фабриками на
                Result, не новым Assembler-ом. Notification/* и Post/* — классы без собственного
                раздела в целевой структуре, переезжают к своему владельцу: типизация уведомлений
                (PostNotificationType/Action/ActionTarget, CommentNotificationTarget) -> Domain/Enum
                Posts (наружу — только примитивы через Notifications/Public); NotificationContentBuilder
                (на TranslatorContract) и PostNotifier — рядом с уведомляющими Command;
                PostContentComposer/CommentComposer/MentionRecipientResolver — рядом со своими Command
                (CreatePost/RepostPost/CommentPost/ReplyComment, по факту инъекции);
                PostVisibilityPolicy -> Domain/Service, чистое правило видимости без Cycle/Spiral
```

### Command handler, который сегодня возвращает View

`CreatePostHandler`, `PublishPostHandler`, `RepostPostHandler`, `CommentPostHandler`, `ReplyCommentHandler` возвращают `PostView`/`CommentView` напрямую из транзакции — запрещено `docs/arch.md` («Command handler не обращается к Reader», а собрать `PostResult`/`CommentResult` без Reader-данных `likedByMe` нельзя). Каждый из пяти переключается на минимальный `{Action}Result` (идентификатор созданного объекта — этого достаточно, потому что вызывающий его контроллер это знает уже сегодня), а HTTP-контроллер после команды вызывает уже существующий/расширяемый Query (`GetPost` для записей, новый `GetComment` для комментариев — сегодня Query на одиночный комментарий нет, заводится по образцу `GetPost`) с идентификатором из Result и `authUserId` = автор действия. Для `RepostPostHandler` и `CommentPostHandler`/`ReplyCommentHandler` это тот же принцип: узнать что показать — минимум из Command, полную форму — из Query. JSON тела ответа не меняется, потому что `GetPostResult`/`GetCommentResult` (переименованные `PostResult`/`CommentResult`) — та же форма, что сегодня строит `PostViewAssembler`/`CommentViewAssembler`. `RelayOutboxHandler` меняет возврат `int` на `RelayOutboxResult` с тем же значением полем.

### Порт перевода вместо `Spiral\Translator\TranslatorInterface`

Каждый из двух модулей заводит собственный порт (по образцу `ClockContract` из `docs/references/application-contract.md` — технический порт без общего владельца дублируется в каждом модуле, `Shared` бизнес- и технические порты модулей не держит):

```text
Auth/Application/Contract/TranslatorContract      Infrastructure/Spiral/Translation/SpiralTranslator
Posts/Application/Contract/TranslatorContract     Infrastructure/Spiral/Translation/SpiralTranslator
```

Сигнатура — `trans(id: string, parameters: array, domain: string, locale: string): string`, зеркалит вызовы `SendLoginCodeHandler`/`NotificationContentBuilder` сегодня. Реализация вызывает `Spiral\Translator\TranslatorInterface::trans()` без изменения аргументов — текст переводов и локали не меняются. Bootloader каждого модуля регистрирует биндинг.

## Контракты реализации

### Данные и БД

Не затрагивается. Reader читает существующие таблицы через `{Entity}Columns` (волна E), новых таблиц и колонок нет.

### API и внешние контракты

Существующие 33 маршрута и формы запросов не меняются. Формы ответов должны остаться побайтово идентичными: каждый `{Name}Result`, на который переименовывается `{Name}View`/`{Name}Dto`, сохраняет набор полей, имена в camelCase и вложенность прежнего класса — переименование и перекладка файлов, а не редизайн ответа. Единственное исключение — путь построения ответа пяти Command handler-ов Posts (Command возвращает минимум, контроллер дочитывает Query) и разделение `GetUserFeed` на два маршрута/два handler-а — в обоих случаях итоговый JSON тот же, меняется только то, кто его строит.

## Фазы выполнения

Порядок — от модулей с меньшим числом представлений к модулям с большим, Posts последним перед приёмкой, как задано в вызове. Каждая фаза выполняется одним исполнителем и проверяется одним проверяющим без `make qa`/`make test`/`make phpstan`/`make test-unit` — проверяющий читает код и построчно сверяет с этим планом, «Целевым алгоритмом» и карточками `reader.md`, `data.md`, `result-dto.md`, `query-handler.md`, `command-handler.md`, `application-contract.md`, `domain-collection.md`, `api-resource.md`, `domain-service.md`; отдельно подтверждает, что JSON-форма ответа задействованных маршрутов не изменилась (сверка полей Resource/Result до и после правки).

### 1. Access

Цель: подтвердить и зафиксировать, что задачи 15 и 16 для модуля неприменимы — открыть путь остальным фазам без ложного «пропущено».

Что сделать: `Access/Application` сегодня не существует (у модуля есть только `Domain` и `Infrastructure/Spiral/Bootloader`), сценариев чтения под ответ нет — заводить `Application/Query`, Reader или Data этой волной не нужно, это задачи 6/8 roadmap, не 15/16. Фаза только подтверждает находку.

Результат: журнал волны фиксирует «Access — нет Application, нет представлений, задачи 15/16 неприменимы» без создания новых файлов.

Проверка: `find app/src/Modules/Access/Application` пуст; в модуле нет `View`, `*ViewAssembler`, `Dto` вне `Public`.

### 2. System

Цель: то же самое для System.

Что сделать: `System/Application` сегодня содержит только `Exception` (целевой раздел) — `Command`/`Query` для OpenAPI (roadmap задача 18, «остальные входные адаптеры») в эту волну не создаются, потому что не относятся к задачам 15/16. Фаза подтверждает находку.

Результат: журнал волны фиксирует «System — Application уже состоит только из Exception, задачи 15/16 неприменимы».

Проверка: `find app/src/Modules/System/Application -maxdepth 1 -type d` содержит только `Exception`.

### 3. Tags

Цель: убрать единственный `Application/Dto` модуля, подтвердить отсутствие Reader.

Что сделать: `Application/Dto/TagTextCollection` переезжает в `Application/Query/GetTags/GetTagsResult` (тот же класс — типизированная карта «id -> текст», `GetTagsHandler` возвращает его вместо `TagTextCollection`); `TagsProvider` (`Infrastructure/Spiral/PublicApi`), если использует `TagTextCollection`, переключается на новый тип. `ResolveTagsResult`/`GetTagsResult` уже лежат в `Command`/`Query` своих действий — раздел `Dto` пустеет и удаляется.

Результат: `Tags/Application` содержит только `Command`, `Query`; Reader не заводится (Tag — единственная своя таблица, ответу хватает Entity).

Проверка: `find app/src/Modules/Tags/Application -maxdepth 1 -type d` — только `Command`, `Query`; `GetTagsResult` в `Query/GetTags`; форма ответа `GetTagsHandler` (карта id -> текст) не изменилась.

### 4. Outbox

Цель: убрать `Application/Dto`, `RelayOutboxHandler` возвращает Result вместо `int`.

Что сделать: `SerializedOutboxMessage` переезжает в `Application/Contract` рядом с `OutboxMessageSerializerContract`, который его использует как тип аргумента/результата — это техническая форма порта, а не форма HTTP-ответа (у Outbox нет HTTP-маршрутов), поэтому `result-dto.md` к нему неприменим буквально, но карточка `application-contract.md` («сигнатура порта выражена доменными типами и скалярами») требует, чтобы такой класс данных лежал рядом с портом, а не в отдельном `Dto`. `RelayOutboxHandler` возвращает `RelayOutboxResult` с тем же числом обработанных событий, что сегодня возвращает `int`; единственный потребитель — консольная команда `outbox:relay`, читающая число из Result вместо `int`.

Результат: `Outbox/Application` содержит только `Command`, `Query`, `Contract`, `Exception`; Reader не заводится (у Outbox нет HTTP-ответов, только внутренние Command/Query по одной таблице).

Проверка: `find app/src/Modules/Outbox/Application -maxdepth 1 -type d` — без `Dto`; `RelayOutboxResult` в `Command/RelayOutbox`; консольная команда компилируется против нового типа.

### 5. User

Цель: убрать `Application/Dto` и `Application/Profile`, растворить `UserPublicProfileAssembler` в Query handler-ах, подтвердить отсутствие Reader.

Что сделать: по строке «User» раздела «Что переносится» «Целевого алгоритма» — `Dto` делится на `Application/Result` (переиспользуется двумя Query, поэтому не в папке одного действия) и `Query/FindUserForAuth/FindUserForAuthResult`; `UserPublicProfileAssembler` растворяется в обоих Query handler-ах без изменения числа вызовов к Media.

Результат: `User/Application` содержит только `Command`, `Query`, `Result`; Reader не заводится (User — ответу хватает Entity + Media/Public).

Проверка: `find app/src/Modules/User/Application -maxdepth 1 -type d` — без `Dto`, `Profile`; число вызовов `MediaContract::urlsByIds()` в `GetUserPublicProfilesHandler` не изменилось (один на вызов, не на пользователя).

### 6. Auth

Цель: убрать `Application/View` и `Application/Dto`, растворить `SessionViewAssembler` в `GetUserSessionsHandler`, завести порт перевода вместо `Spiral\Translator`, подтвердить отсутствие Reader.

Что сделать: по строке «Auth» раздела «Что переносится» — `View`/`Dto` переезжают в `Application/Result`, логика `SessionViewAssembler` — приватными методами в `GetUserSessionsHandler` (источник данных не меняется: `AuthTokenRepository::findActiveByUserId()`, все поля есть на `AuthToken`). `Auth/Application/Contract/TranslatorContract` заводится по разделу «Порт перевода», `SendLoginCodeHandler` переключается на него; `Infrastructure/Spiral/Translation/SpiralTranslator` реализует контракт, `AuthBootloader` регистрирует биндинг.

Результат: `Auth/Application` содержит только `Command`, `Query`, `Contract`, `Result`; ни один класс `Auth/Application` не импортирует `Spiral\...`; Reader не заводится.

Проверка: `find app/src/Modules/Auth/Application -maxdepth 1 -type d` — без `View`, `Dto`; `grep -rl "Spiral\\\\" app/src/Modules/Auth/Application` пуст; текст письма (тема/тело, ключи `app.auth.code_email_subject`/`app.auth.code_email_body`, домен `auth`) не изменился — сверка вызова `TranslatorContract::trans()` против прежнего `TranslatorInterface::trans()` один в один по аргументам.

### 7. Media

Цель: убрать `Application/Dto` и `Application/Service`, разложить 16 Dto-классов на Result/портовые данные, вынести `MediaTypeResolver`/`MediaConversionsChecker` в `Domain/Service`, подтвердить отсутствие Reader.

Что сделать: по разделу «Что переносится» «Целевого алгоритма» — форма ответа переезжает в `Application/Result`, данные технических портов остаются с портом в `Application/Contract`, `MediaTypeResolver`/`MediaConversionsChecker` переезжают в `Domain/Service` без изменения логики (чистые правила без Cycle/Spiral-зависимостей — подтверждается чтением тела класса перед переносом; если зависимость от инфраструктуры найдётся, класс остаётся в `Application` и это фиксируется в журнале фазы как отклонение от плана с причиной).

Результат: `Media/Application` содержит только `Command`, `Query`, `Contract`, `Result`, `Exception`; Reader не заводится (Media — ответу хватает Entity с конверсиями + `MediaUrlServiceContract`).

Проверка: `find app/src/Modules/Media/Application -maxdepth 1 -type d` — без `Dto`, `Service`; `find app/src/Modules/Media/Domain/Service` содержит два новых класса; число вызовов `MediaUrlServiceContract::getUrls()` в `FindMediaUrlsHandler` не изменилось.

### 8. Notifications

Цель: убрать `Application/View`, `Application/Dto` (в части форм ответа) и `Application/Service`, растворить `NotificationViewAssembler` и `NotificationSettingsViewFactory` в Query handler-ах, подтвердить отсутствие Reader.

Что сделать: по разделу «Что переносится» «Целевого алгоритма» — `View`-классы и `NotificationSettingView(Collection)` переезжают в `Application/Result` рядом с `ListNotifications`/`GetNotificationSettings`; логика резолва аватара (`NotificationViewAssembler`) и сборки матрицы «вид × канал» (`NotificationSettingsViewFactory`) переезжает приватными методами в соответствующие Query handler-ы — само чтение (Repository + `Media/Public` + `NotificationTypeCatalogContract`) не меняется. Payload-классы push/realtime (`FcmPushResult`, `NotificationPush(ActorPayload)`, `Realtime*Payload`, `NotificationTypeDefinitionCollection`) остаются рядом со своими техническими портами в `Application/Contract` — не HTTP-форма ответа.

Результат: `Notifications/Application` содержит только `Command`, `Query`, `Contract`, `Result`, `Exception`; Reader не заводится (Notification/NotificationSetting — ответу хватает Entity + Media/Public + собственный Contract-реестр).

Проверка: `find app/src/Modules/Notifications/Application -maxdepth 1 -type d` — без `View`, `Service`, без `Dto` в части форм ответа; число вызовов `MediaContract::urlsByIds()` в `ListNotificationsHandler` не изменилось (один пакетный вызов на страницу).

### 9. Posts

Цель: самая тяжёлая фаза — завести `PostReader`, `PostViewerReader`, `CommentViewerReader` и Data по «Целевому алгоритму»; разделить `GetUserFeed` на `GetMyFeed`/`GetUserFeed`; убрать `Application/View`, `Application/Notification`, `Application/Post`; переключить пять Command handler-ов на минимальный Result + Query-дочитывание; завести порт перевода вместо `Spiral\Translator`.

Что сделать по разделам «Целевого алгоритма» «Posts: Reader и разделение GetUserFeed» и «Что переносится»:
- `CyclePostReader`/`CyclePostViewerReader`/`CycleCommentViewerReader` в `Infrastructure/Persistence/Cycle/Read` реализуют свои интерфейсы из `Application/Contract` поверх Cycle Entity волны E, колонки — из соответствующих `{Entity}Columns`, страницы — через `WhenSelect::cursorById()` + `CursorSlice::fromOverfetched()`, буквально по образцу `docs/references/reader.md`.
- `GetMyFeedHandler`/`GetUserFeedHandler` вызывают свой метод `PostReader`, дочитывают автора/медиа/теги через `User`/`Media`/`Tags` `Public` (один пакетный вызов на соседа на страницу — как сегодня в `PostViewAssembler`) и `likedByMe` через `PostViewerReader`. `GetPostHandler`, `GetPostCommentsHandler`, `GetCommentRepliesHandler` продолжают читать через `PostRepository`/`CommentRepository` (Entity — `PostMedia`/`PostTag` их внутренние сущности агрегата, Repository вправе их возвращать для одной записи), `likedByMe` — через `PostViewerReader`/`CommentViewerReader`.
- Общая сборка (батч через `Public`, вложенный `original` репоста) переезжает в Query handler-ы приватными методами; дублирование между `GetPost`/`GetMyFeed`/`GetUserFeed`/`GetPostComments`/`GetCommentReplies` закрывается статическими фабриками на `PostResult`/`CommentResult`, а не новым Assembler-ом.
- Пять Command handler-ов Posts переключаются на минимальный `{Action}Result` + Query-дочитывание контроллером (новый `GetCommentQuery` заводится по образцу `GetPost` для одиночного комментария), по решению 5 «Принятых решений».
- `PostContentComposer`, `CommentComposer`, `MentionRecipientResolver` переезжают рядом со своими Command по факту использования (инъекция проверяется перед переносом); `PostVisibilityPolicy` -> `Domain/Service`.
- Типизация уведомлений Posts (`PostNotificationType`/`Action`/`ActionTarget`, `CommentNotificationTarget`) -> `Domain/Enum`/`Domain/ValueObject`; `NotificationContentBuilder` (на `TranslatorContract`, как Auth) и `PostNotifier` переезжают рядом с уведомляющими Command по факту использования.

Результат: `Posts/Application` содержит только `Command`, `Query`, `Contract`, `Data`, `Result`, `Exception`; ни один класс `Posts/Application` не импортирует `Spiral\...`; `GetUserFeed` разделён на два сценария с двумя методами `PostReader` и двумя Result.

Проверка: `find app/src/Modules/Posts/Application -maxdepth 1 -type d` — только целевые разделы; `grep -rl "Spiral\\\\" app/src/Modules/Posts/Application` пуст; JSON-форма `PostResult`/`CommentResult` (поля, вложенность `original`) не изменилась относительно прежних `PostView`/`CommentView`; число вызовов `UserContract`/`MediaContract`/`TagsContract` на страницу ленты/комментариев не увеличилось (один пакетный вызов на соседа, как сегодня).

### 10. Приёмка волны

Цель: подтвердить целиком критерии приёмки волны и провести единственный за волну полный прогон `make qa`, циклами точечных исправлений до зелёного результата.

Что сделать: подтверждается отсутствие `Application/View` и `*ViewAssembler` во всём `app/src`; для каждого модуля — что `Application` содержит только `Command`, `Query`, `Contract`, `Data` (где заведён), `Result`, `Exception`; что ни один класс `Application` не импортирует `Spiral\...` (включая закрытые хвосты Auth/Posts). Запускается `php app.php route:list` (33 маршрута, тот же состав, что фиксировала волна E) и `php app.php openapi:generate` (побайтовое совпадение, md5 `fd8d4a16c3994dddcfbf915caa85157b`). Запускается `make qa`; падения не на известном S3-тесте чинятся точечно в затронутом модуле без отката предыдущих фаз, `make qa` перезапускается; так — несколько циклов до зелёного результата, как в волне E (там потребовался один цикл на Media).

Результат: волна закрыта, `make qa` зелёный кроме известного S3-падения, PHPStan level max без ошибок, php-cs-fixer чисто, покрытие 100%, маршруты и OpenAPI не изменились; журнал волны перечисляет число созданных Reader/Data и удалённых View/ViewAssembler и новое незакрытое, если есть.

Проверка:
- `make qa` зелёный (тесты/PHPStan/cs-fixer), единственное ожидаемое падение — `S3MediaFileServiceTest::testCopyObjectToPublicBucketIsAnonymouslyReadable`, покрытие 100.00%;
- `find app/src -path "*Application/View*"` и `grep -rl "ViewAssembler" app/src` пусты;
- для каждого модуля `find app/src/Modules/{M}/Application -maxdepth 1 -type d` — подмножество `{Command,Query,Contract,Data,Result,Exception}`;
- `grep -rl "use Spiral\\\\" app/src/Modules/*/Application` пуст;
- `php app.php route:list` — 33 маршрута, тот же состав, что в волне E;
- `php app.php openapi:generate` — md5 `fd8d4a16c3994dddcfbf915caa85157b`.

## Тесты

`test_strategy: end_of_plan` — единственный запуск тестов за волну в фазе 10, по прямому указанию пользователя (переопределяет `plan.test_strategy: after_each_phase` из `docs/settings.yaml`). Фазы 1-9 переносят существующую логику сборки ответа (перекладка классов, перенос группировки/резолва из Assembler-ов в handler-ы, замена одного Reader-чтения на два метода) без изменения набора проверяемых случаев — существующие тесты Assembler-ов переносятся на новые Result/handler-тесты один в один по числу проверок; тесты Reader (`PostReader`, `PostViewerReader`, `CommentViewerReader`) — новые интеграционные тесты на реальной БД, по образцу `docs/references/reader.md` (страница, признак вне агрегата, пустой набор идентификаторов). Тест на разделение `GetUserFeed` разбивается на `GetMyFeedTest`/`GetUserFeedTest` без потери сценариев (владелец видит черновики, посторонний — нет, заблокированные не видит никто). Пять Command-тестов Posts (`CreatePost`, `PublishPost`, `RepostPost`, `CommentPost`, `ReplyComment`), сегодня проверяющих форму ответа Command напрямую, дополняются HTTP-тестом всего маршрута (Command + последующий Query), чтобы проверить итоговый JSON, а не промежуточный `{Action}Result`. Фаза 10 запускает `make test` в составе `make qa`; при падении — точечное исправление и повтор, без переигровки волны.

## Логирование

Логирование не меняется по содержанию: `#[LogOperation]` и точечный `LoggerInterface` (там, где он уже есть, например `GetUserSessionsHandler`) остаются на прежних местах — волна переносит сборку ответа, не поведение логирования. Если исполнитель фазы обнаружит логирование внутри переносимого `Application/View`/`*ViewAssembler`-класса, он переносит вызов вместе с остальной логикой в Query handler и фиксирует находку в журнале фазы.

## Документация и эксплуатация

- README модулей актуализируются там, где описывают удалённые `View`/`*ViewAssembler`/`Application/Dto`/`Application/Service`/`Application/Notification`/`Application/Post`/`Application/Profile` разделы — по факту находки в каждой фазе.
- Карточки `reader.md`, `data.md`, `result-dto.md` не меняются: Posts — буквальный пример `reader.md`/`data.md`, остальные модули подтверждают правило «Reader не заводится, если хватает Entity» без изменения самой карточки.
- Журнал волны прямо перечисляет: сколько Reader и Data создано (ожидание — 3 Reader: `PostReader`, `PostViewerReader`, `CommentViewerReader`; Data — `PostPageData`, `PostData`, `PostViewerFlagsData`, `CommentViewerFlagsData`), сколько классов `View`/`*ViewAssembler` удалено (17 View + 3 Assembler), решение по хвостам (`Spiral\Translator` в двух модулях, разделение `GetUserFeed`).

## Принятые решения

1. **Reader заводится только у Posts.** Прямое чтение кода `GetUserSessionsHandler`, `FindMediaUrlsHandler`, `ListNotificationsHandler`, `GetNotificationSettingsHandler` показывает, что во всех случаях ответу хватает полей агрегата (Entity от Repository) плюс данных соседа через `Public` — карта расхождений предполагала Reader для Auth/Media/Notifications, но она писалась до проверки конкретного кода и до решения волны E о границах агрегата Media (конверсии — внутренние сущности). Источник: `docs/references/reader.md` («Если ответу хватает полей агрегата... Reader не заводится»), прямое чтение перечисленных handler-ов; autonomous, карта расхождений не нормативна для этой развилки.
2. **`PostMedia`/`PostTag` в ленте читаются через `PostReader`, хотя формально внутренние сущности агрегата Post.** Карточки `reader.md` и `data.md` — буквальный рабочий пример именно этого случая (`PostData` с `mediaIds`), они нормативны по правилу карты чтения `docs/references.md` («их кодовые примеры задают форму реализации»); теоретическая альтернатива «Repository возвращает медиа/теги как часть агрегата» отклонена, потому что противоречит явному образцу. Для одиночной записи (`GetPost`) и страниц комментариев альтернатива не противоречит образцу (там нет буквального примера с Reader) и остаётся через Repository — минимизирует объём новых Reader без нарушения карточки.
3. **`GetUserFeed` разделяется на `GetMyFeed`/`GetUserFeed`, остальные Query Posts — нет.** `docs/arch.md`: «Своя лента и чужая лента это два Query... а не один сценарий с вычислением роли зрителя» — `GetUserFeedHandler` буквально вычисляет `$isOwner` и ветвится на разные статусы; `GetPost`/`GetPostComments`/`GetCommentReplies` используют одно правило видимости (`PostVisibilityPolicy::isVisibleTo()`) без ветвления на два разных набора статусов — не подпадают под правило. Источник: `docs/arch.md`, чтение `GetUserFeedHandler.php`; autonomous.
4. **Порт перевода заводится отдельно в каждом из двух модулей (Auth, Posts), не в Shared.** `docs/references/application-contract.md`: «Порт без владельца среди модулей остаётся у того модуля, чей сценарий им пользуется: Shared бизнес-портов не держит» — пример `ClockContract` в той же карточке показывает именно дублирование технического порта по модулям, не общий Shared-интерфейс. Источник: `docs/references/application-contract.md`; autonomous.
5. **Пять Command handler-ов Posts переключаются на «минимальный Result + Query-дочитывание через контроллер», а не на «Command вызывает Reader».** `docs/arch.md`: «Command handler не обращается к Reader. Если ответу нужна полная форма изменённого объекта, её читает отдельный Query» — прямая формулировка; JSON не меняется, потому что Query строит тот же `PostResult`/`CommentResult`, что раньше строил Command через `PostViewAssembler`/`CommentViewAssembler`. Источник: `docs/arch.md`; autonomous.
6. **Media/Application/Dto делится на Result (форма ответа) и данные рядом с портом (форма технической зависимости), а не весь переезжает в Result.** Карточка `result-dto.md` описывает форму ответа Command/Query; `application-contract.md` требует, чтобы данные порта лежали рядом с портом. Классы вроде `MediaPresignedPart` — аргумент/результат storage-контракта, не HTTP-ответ; смешивать их с `Application/Result` нарушило бы «один факт — одно место». Источник: `docs/references/result-dto.md`, `docs/references/application-contract.md`; autonomous.
7. **Access и System получают в этой волне только фазу-подтверждение без создания файлов.** У обоих модулей нет `Application`-содержимого, подпадающего под задачи 15/16 (Access — Application нет вообще, System — только `Exception`, уже целевой раздел); заведение `Command`/`Query` для System (OpenAPI) относится к roadmap-задаче 18, вне границ этой волны. Источник: границы волны из вызова («миграции и тесты — задачи 20/24»), прямое чтение файловой структуры модулей; autonomous.
8. **`logging_strategy: standard`, а не `debug_precise` из `docs/settings.yaml`.** Волна не вводит новых точек принятия решений и не меняет поведение — переносит существующую сборку ответа между классами; `debug_precise` предназначен для нового поведения с новыми ветвлениями, которых здесь нет за пределами разделения `GetUserFeed` (само по себе не новая ветвь, а разъединение существующей). Источник: назначение волны из вызова; autonomous, по аналогии с решением волны E (тот же класс волны, то же обоснование).
9. **`test_strategy: end_of_plan` вместо `plan.test_strategy: after_each_phase`.** Прямое указание пользователя в вызове («Фазы НЕ запускают проверки... Полный make qa... запускается ОДИН раз») важнее файла настроек. Источник: сообщение пользователя.
10. **Порядок фаз — по модулям от простого к сложному**, задано в вызове: Access, System, Tags, Outbox, User, Auth, Media, Notifications, Posts, приёмка — считано по числу представлений/Dto/Service-классов на модуль (0, 0, 1, 1, 4, 4, 18, 17, 19 соответственно). Источник: сообщение пользователя.
11. **План превышает мягкий предел 40 КБ (`plan.size: normal`) и не разбивается на вехи.** Roadmap явно объединяет задачи 15 и 16 в одну волну переезда; девять модулей с разным набором View/Dto/Service и два хвоста требуют зафиксировать по каждому модулю, нужен ли Reader (с обоснованием чтением кода) — без этого разбора план не проходил бы по карточкам. Дробление волны противоречило бы прямому заданию «волна F: задачи roadmap 15 и 16» и требованию работать автономно без уточняющих вопросов; прецедент — волна E тоже вышла за 40 КБ (41.5 КБ) при меньшем числе модулей. Источник: сообщение пользователя (явный объём волны, запрет на вопросы); autonomous.

## Прогресс выполнения

Журнал: `docs/artifacts/executions/2026-09-16_16-22_volna-f-reader-i-scenarnyj-sloy.md`. Режим: `subagents`.

| Фаза | Статус |
|---|---|
| 1. Access | завершена, проверка passed |
| 2. System | завершена, проверка passed |
| 3. Tags | завершена, проверка passed |
| 4. Outbox | завершена, проверка passed |
| 5. User | завершена, проверка passed |
| 6. Auth | завершена, проверка passed |
| 7. Media | завершена, проверка passed (1 цикл исправления) |
| 8. Notifications | завершена, проверка passed |
| 9. Posts | завершена, проверка passed (2 некритичные заметки) |
| 10. Приёмка волны | завершена, проверка passed (4 цикла исправлений `make qa`, риск OpenAPI закрыт) |

Финальные цифры волны (подтверждены исполнителем и независимым проверяющим фазы 10 без расхождений):
- Reader: 3 порта (`PostReader`, `PostViewerReader`, `CommentViewerReader`) + 3 реализации Cycle (`CyclePostReader`, `CyclePostViewerReader`, `CycleCommentViewerReader`).
- `Application/Data`: 6 классов (`PostData`, `PostDataCollection`, `PostPageData`, `PostViewerFlagsData`, `CommentViewerFlagsData`, `PostRelatedIds` — последний заведён в фазе 10 взамен запрещённой вложенной карты `array<string, list<string>>`; план ожидал 4).
- Удалено `View`/`*ViewCollection`: 17 (совпадает с ожиданием плана: Auth 2, Notifications 6, Posts 7, User 2).
- Удалено «сборщиков представления»: 5 буквальных `*ViewAssembler.php` (`SessionViewAssembler`, `NotificationViewAssembler`, `PostViewAssembler`, `CommentViewAssembler`, `UserPublicProfileAssembler`) + 1 фабрика представлений (`NotificationSettingsViewFactory`) — план ожидал 3, фактически 5 (план не учитывал `SessionViewAssembler` и `UserPublicProfileAssembler`).
- `make qa`: cs-fixer 0 правок, PHPStan level max 0 ошибок, тесты 1475/Assertions 4932/Failures 1 (только известный до-волновой `S3MediaFileServiceTest::testCopyObjectToPublicBucketIsAnonymouslyReadable`), покрытие 100.00%.
- `route:list`: 33 маршрута, состав не изменился.
- OpenAPI: `fd8d4a16c3994dddcfbf915caa85157b`, совпадает с эталоном волн A-E.
