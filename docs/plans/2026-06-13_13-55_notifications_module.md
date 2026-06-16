---
title: Модуль уведомлений (Notifications)
date: 2026-06-13 13:55
mode: normal
plan_size: normal
decision_mode: recommend_and_ask
status: meta-reviewed
reviewer: none
meta_reviewers: [haiku, sonnet, opus]
test_strategy: after_each_phase
logging_strategy: debug_precise
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  research: docs/researches/2026-06-13_13-16_notifications_module_design.md
---

# План реализации

## Задача

Создать модуль `App\Modules\Notifications` — модульный монолит-модуль с публичным
Application API, который дают другим модулям отправлять уведомления одному
получателю. Готовый результат:

- три типа доставки (канала): `database` (строка инбокса — in-app список), `push`
  (FCM), `realtime` (Centrifugo, пока приложение открыто);
- открытый набор видов уведомлений (что случилось) — реестр определений видов, которые
  регистрируют модули-источники; ядро уведомлений при добавлении вида не меняется;
- межмодульная отправка прямым вызовом контракта `NotificationSenderContract::send()`,
  встроенная в транзакцию источника (без шины); решение «какие каналы» и создание инбокса
  вынесены в фоновый процесс после commit-а источника;
- пользовательская настройка «вид × канал»;
- текст уведомления готовый (title + body) — его формирует модуль-источник на языке
  получателя; Notifications не переводит, только хранит и доставляет;
- deep-link стандартизирован как `action = {actionType, actionId}`;
- push-токены устройств: хранение и API регистрации/удаления;
- HTTP API из 8 роутов для мобильного клиента, каждый покрыт интеграционным тестом.

«Готово» = `make test` и `make phpstan` зелёные, 100% покрытие нового кода, каждый
из 8 роутов покрыт интеграционным тестом, `openapi:generate` обновляет спецификацию.

## Контекст

Зачем: уведомления нельзя слать напрямую из бизнес-модулей — внешние эффекты (push,
Centrifugo) по `rules.md:86` обязаны идти через transactional outbox после commit-а.
Модуль проектируется «вперёд»: модулей `User`, `Chat`, `Записи` ещё нет (в коде есть
только `Media`, `Outbox`, `System`), поэтому Notifications — стабильный контракт, к
которому будущие модули подключатся.

Поток асинхронный (решение пользователя при ревью): запрос-источник делает минимум —
стейджит один лёгкий `NotificationRequested` на получателя в своей транзакции; тяжёлую
работу (чтение настроек, выбор каналов, создание инбокса, стейджинг push/realtime) делает
фоновый `DispatchNotificationJob` после commit-а. Это снимает нагрузку с источника на
веере (групповой чат / много подписчиков) и повторяет паттерн Media (`arch.md:519-525`).
Гарантия «нет действия — нет уведомления» сохраняется: триггер лежит в транзакции
источника, при откате не коммитится.

Текст — готовый (решение пользователя): источник отдаёт `title`/`body` уже на языке
получателя, Notifications не хранит ключи перевода и не вызывает переводчик. Это упрощает
модуль (нет рендерера, нет резолва языка получателя). Плата: текст фиксируется на момент
создания (если пользователь сменит язык приложения — старые уведомления останутся на
языке создания); пока модуля User нет, источник использует дефолтную локаль.

Подтверждённые факты из кода (паттерны, которые переиспользуем):

- **Outbox**: `OutboxEventStoreContract::add(OutboxMessage): StoredOutboxEventId` только
  стейджит событие через `EntityManager` (не делает `run()`).
  `OutboxJobRegistryContract::register(messageClass, jobClass)` регистрирует пару в
  бутлоадере владельца. `OutboxMessage` — интерфейс-маркер
  (`App\Modules\Outbox\Application\Message\OutboxMessage`). Сообщения сериализуются через
  `ValinorOutboxMessageSerializer` — payload только из примитивов / enum / вложенных
  `readonly` DTO с публичным конструктором (как `MediaUploaded` + `MediaConversionSpec`).
  Job наследует `JobHandler`, принимает `OutboxQueueEnvelope $payload`, грузит сообщение
  `OutboxMessageLoaderContract::load($payload->outboxEventId, X::class)`. У
  `$payload->outboxEventId` стабильный id — используем как ключ идемпотентности.
- **Media (образец)**: `CompleteMediaUploadHandler` под `#[Transactional]` —
  `outboxEventStore->add(...)` → `persist(...)` → `run()`. `ProcessMediaJob` грузит
  сообщение, отправляет Command, классифицирует ошибки (`RetryException` для временных,
  rethrow для терминальных). `MediaBootloader`: `const BINDINGS` + `boot(OutboxJobRegistryContract)`.
  Typecast-классы реализуют `App\Shared\Infrastructure\Cycle\ColumnValueTypecast`
  (статические `castDatabaseValue()`/`uncastValue()`), подключаются через `#[Column(typecast: X::class)]`.
  Миграции — `app/database/migrations/...create_media_domain_tables.php`
  (`$this->table()->addColumn()->setPrimaryKeys()->addIndex()->create()`).
  `RequestMediaUploadHandler` инжектит typed-config `MediaConfig` прямо в Application.
- **Shared**: `UserId extends AbstractUuidV7Id` (`generate()`, `fromString()`, `value()`),
  трейт `HasTimestamps` (`createdAt`/`updatedAt: \DateTimeImmutable`,
  `initializeTimestamps(?now)`, `touch()`), `AbstractResource`, `ValueObjectCast`
  (конвенция `fromString`/`fromInt`/`BackedEnum::from` ↔ `value()`). Response-классы —
  `GianTiaga\SpiralOpenApi\Response\{DataResponse, CollectionResponse, PaginationResponse,
  PaginationMetaResponse}` (`nextCursor`, `limit`). Конфиги: `app/config/*.php` + DTO
  `TypedConfig` в `Shared/Infrastructure/Configuration/<Section>/`, маппинг покрывается
  тестом через `ConfigMapper` (suite `Kernel`).
- **Тесты**: suite `Unit`/`Kernel`/`Feature`; `Tests\DatabaseTestCase` оборачивает тест
  в транзакцию с откатом; HTTP — `FakeHttp` (`get`, `getWithAttributes`, `post/put/delete`,
  `createJsonRequest`+`withAttribute`+`handleRequest`).
- **Centrifugo (dev)**: сервис `centrifugo/centrifugo:v6.7.2` уже поднят; в `.env` уже
  есть `CENTRIFUGO_API_URL=http://centrifugo:8000/api` и `CENTRIFUGO_API_KEY=dev-centrifugo-api-key`.

Найденные ограничения окружения (не блокеры, влияют на «готово»):

- **Нет auth-middleware и `authUserId`**: в проекте пока нет ни одного Filter-контроллера,
  ни JWT-middleware, ни HTTP-интеграционных тестов. Контракт получения пользователя
  фиксирован `rules.md:53` — `#[Attribute(key: 'authUserId')]`. Реальный middleware придёт
  с модулем `Auth`; до тех пор интеграционные тесты подставляют `authUserId` сами
  (`FakeHttp::getWithAttributes()` для GET; `createJsonRequest()`+`withAttribute('authUserId', ...)`+`handleRequest()`
  для POST/PUT/DELETE).
- **Ноль конкретных видов в проде**: виды принадлежат модулям-источникам, которых ещё нет.
  Notifications поставляет механизм; конкретные определения видов появятся в `Chat`/`Записи`.
  Поэтому поток отправки, рассылка и экран настроек тестируются через фикстурное
  `FixtureNotificationTypeDefinition` (code + defaultChannels) + фикстурный `NotificationContent`
  с готовым тестовым текстом, регистрируемые тестовым бутлоадером.

## Принятые решения

Подтверждены пользователем (ответы на `AskUserQuestion`) и research:

1. **Размер плана — `normal`**; `short` не уместится.
2. **Схема БД и 8 HTTP-роутов — как в research** с правками пользователя по полям (ниже).
   Получатель — `UserId` без FK.
3. **Push через `kreait/firebase-php` 8.2.0** (версия проверена 2026-06-13, требует PHP
   `~8.3 || ~8.4 || ~8.5`, проект на PHP `>=8.5 <8.6` — совместимо).
4. **Push-токены — открытым текстом**: колонка `token` + `unique(token)`.
5. **Межмодульная отправка — прямой вызов `NotificationSenderContract::send()`**, не шина
   (`arch.md:547`). `send()` стейджит **только** один `NotificationRequested` на получателя,
   `run()` не вызывает — flush делает источник.
6. **Решение каналов + создание инбокса + стейджинг push/realtime — в фоне**
   (`DispatchNotificationJob` после commit-а источника).
7. **Список получателей определяет источник**: один `NotificationRequested` на получателя;
   Notifications веер не разворачивает.
8. **Идемпотентность рассылки** — `notifications.outbox_id` хранит id outbox-события
   `NotificationRequested`, `unique`; повторная рассылка того же события — no-op (есть строка
   по `outbox_id`). При выключенном `database` push/realtime — at-least-once (редкий дубль
   при retry допустим).
9. **Каналы — enum** `NotificationChannel { Database, Push, Realtime }` (`rules.md:30`);
   **виды — реестр** определений `NotificationTypeDefinition` (открытый набор).
10. **Текст — готовый, формирует источник** (пользователь, заменяет «ключ + перевод» из
    research): `send()` принимает `NotificationContent{type, title, body, action}` с готовыми
    `title`/`body`. Notifications перевод не делает.
11. **Deep-link — две нормализованные nullable-колонки `action_type` + `action_id`**
    (пользователь). В домене — null-object VO `NotificationAction` — **один `final readonly
    class`** с двумя backing-VO внутри (`NotificationActionType`/`NotificationActionId`,
    null-object), фабрики `none()`/`linkTo(actionType, actionId)`/`fromParts(...)`, предикат
    `hasLink()` (форма «один VO» по `rules.md:21`: переход — одно опциональное значение,
    разных данных у вариантов нет). На Entity это **доменный метод**
    `action(): NotificationAction` поверх двух backing-VO, каждый со своим per-column typecast
    (образец `MediaProcessingErrorTypecast`). **Не property-hook**: в проекте нет прецедента
    property-hook на Cycle Entity, а `LazyGhostEntityFactory` обходит все не-static свойства
    и упал бы на virtual-свойстве без backing storage — вычисление делаем методом, как
    `Media::isReady()` / `StoredOutboxEvent::isFinal()` (мета-ревью). `actionType` — открытый
    строковый код (chat, post, …), не enum. В API/outbox — вложенный `action {actionType,
    actionId}` либо `null`.
12. **Имена полей/таблиц** (пользователь): `notifications.recipient_id` → `user_id`;
    `notifications.source_event_id` → `outbox_id`; таблица `notification_preferences` →
    `notification_settings` (Entity `NotificationSetting`, репозиторий, команды/ресурсы с
    именем Setting).

Решения уровня реализации (минорные, для ревью):

13. **`read_at`** — nullable-колонка на non-null VO `NotificationReadState` — **один
    `final readonly class`** с приватным nullable, фабрики `unread()`/`readAt(DateTimeImmutable)`,
    предикат `isRead()` (форма «один VO» по `rules.md:21`, стиль `MediaExpiration`) — с
    отдельным typecast (`rules.md:81`).
14. **`enabled` настройки** — boolean-колонка, в Entity enum
    `NotificationSettingStatus { Enabled, Disabled }` с отдельным typecast (Entity без
    примитивов, `rules.md:36`).
15. **HTTP-клиент Centrifugo — свой** на PSR-18 (`guzzlehttp/guzzle` уже в зависимостях),
    POST `{CENTRIFUGO_API_URL}/publish` с заголовком `X-API-Key` (`rules.md:85`).
16. **Дефолтные каналы вида при рассылке** берутся из реестра по коду
    (`registry.get(type).defaultChannels()`) — единый источник правды. `send()` валидирует
    код через реестр (fail-fast при незарегистрированном виде) — это дешёвый in-memory
    lookup.
17. **Ярлыки видов на экране настроек рисует клиент** по `type`-коду — Notifications
    человекочитаемых названий не хранит (i18n настроек — забота клиента).
18. **`createdAt` инбокса** = время триггера. В payload `NotificationRequested` и в
    Command — примитив `string` (ISO-8601, как у `MediaUploaded`), Handler делает
    `new \DateTimeImmutable($command->createdAt)` и передаёт в `initializeTimestamps()`
    (мета-ревью: Valinor сериализует дату строкой, тип в payload — `string`).
19. **Регистрация бутлоадера** (мета-ревью): `NotificationsBootloader::class` добавляется в
    `app/src/Shared/Infrastructure/Framework/.../Kernel::defineBootloaders()` явным пунктом
    (автозагрузки бутлоадеров по соглашению нет) — после Outbox-бутлоадеров, т.к.
    `OutboxJobRegistryContract` биндится singleton в `OutboxBootloader`, а `boot()`
    Notifications вызывает `register()` на нём.
20. **Entity автосканируются Cycle** через tokenizer (как Media) — отдельная регистрация
    директории/Entity в `cycle.php` НЕ нужна (мета-ревью подтвердил). Роуты `#[Route]`
    тоже подхватываются глобально (как `HealthController`); при реализации убедиться, что
    namespace модуля попадает в скан — иначе зарегистрировать.
21. **OpenAPI scope** (мета-ревью, блокер): `app/config/openapi.php` сканирует только
    `App\Modules\System\Presentation\Http`. Чтобы 8 роутов попали в спеку, в фазе 7
    расширить `sourcePath`/`apiNamespace` на модуль Notifications (или на список путей) и
    проверить, что генератор `packages/spiral-openapi` поддерживает несколько источников.

Вне области: mute конкретного источника (per-source); шифрование push-токенов; корреляция
realtime↔инбокс; реальный JWT auth-middleware (придёт с модулем Auth).

## Целевой алгоритм

### Отправка (межмодульная, лёгкий триггер в транзакции источника)

```text
Модуль-источник (будущий Chat/Записи) в своём #[Transactional]-Handler:
  1. сохраняет свою сущность — persist, без run
  2. на каждого получателя:
       NotificationSenderContract::send(UserId $recipient, NotificationContent $content)
         // NotificationContent{type, title, body, action} — источник дал готовый текст
         NotificationSender (Application):
           a. DEBUG «триггер вида {type} получателю {userId}»
           b. registry.get(content.type)   // fail-fast: незарегистрированный вид -> исключение
           c. outboxEventStore->add(new NotificationRequested(userId, type, title, body, action, createdAt))
              DEBUG «NotificationRequested {outboxId} застейджен»
           d. свой run() НЕ вызывает
  3. EntityManager::run()   // источник: атомарно бизнес-изменение + N лёгких триггеров
  -> commit -> outbox relay подхватывает NotificationRequested
```

### Фоновая рассылка (решение каналов + инбокс + стейджинг доставки)

```text
Outbox relay -> DispatchNotificationJob (JobHandler):
  1. load NotificationRequested по $payload->outboxEventId
  2. CommandBus::dispatch(DispatchNotificationCommand{outboxId(string=$payload->outboxEventId->value()),
       userId, type, title, body, action, createdAt(string)})
       DispatchNotificationHandler (Application, #[Transactional]):
         a. идемпотентность: NotificationRepository::findByOutboxId(NotificationOutboxId::fromString($command->outboxId))
            найдено -> DEBUG «рассылка уже выполнена, no-op» -> выход   // no-op только если database был включён
         b. defaults: registry.get(type).defaultChannels()
         c. настройки: NotificationSettingRepository::findForUserAndType(userId, type)
         d. на каждый NotificationChannel: enabled = строка настройки ? её статус : default
            DEBUG «канал {channel}: enabled={bool}, источник=setting|default»
         e. Database вкл -> Notification::create(outboxId, userId, type, title, body, action,
              triggeredAt = new \DateTimeImmutable($command->createdAt))
            persist; DEBUG «инбокс {notificationId} создан»
         f. Push вкл     -> outboxEventStore->add(NotificationPushRequested{...})     DEBUG «push {outboxId}»
         g. Realtime вкл -> outboxEventStore->add(NotificationRealtimeRequested{...}) DEBUG «realtime {outboxId}»
         h. entityManager->run()   // новая транзакция после commit-а источника
  3. временный сбой -> RetryException; терминальный -> rethrow (outbox -> failed)
```

### Доставка push

```text
Outbox relay -> SendPushNotificationJob -> SendPushNotificationCommand:
  SendPushNotificationHandler (Application):
    a. токены: NotificationDeviceTokenRepository::findActiveForUser(userId); нет -> DEBUG «нет токенов» -> выход
    b. FcmPushSenderContract::send(NotificationPush{title, body, action}, tokens) -> FcmPushResult{invalidTokens}
       WARN при временном сбое FCM
    c. invalidTokens -> entityManager->delete(token) + run(); DEBUG «удалён невалидный токен»
  временный сбой -> RetryException; терминальный -> rethrow
```

### Доставка realtime

```text
Outbox relay -> PublishRealtimeNotificationJob -> PublishRealtimeNotificationCommand:
  PublishRealtimeNotificationHandler:
    a. канал personal:#user_{userId} + payload (camelCase: type, title, body, action, createdAt)
    b. CentrifugoServiceContract::publish(channel, payload); WARN при недоступности
  временный сбой -> RetryException; терминальный -> rethrow
```

### HTTP-сценарии (in-app, мобильный клиент)

```text
GET  /notifications: ListNotificationsQuery -> findPageForRecipient(limit+1) -> nextCursor (UUID v7 id)
     -> PaginationResponse<NotificationResource>  (title/body отдаём как есть, без перевода)
GET  /notifications/unread-count -> GetUnreadCountQuery -> countUnreadForRecipient -> DataResponse<UnreadCountResource>
POST /notifications/{id}/read: MarkNotificationReadCommand #[Transactional]
     -> findByIdForRecipient (нет/чужое -> NotFoundException) -> markRead(now) -> persist+run -> DataResponse<NotificationResource>
POST /notifications/read-all: MarkAllNotificationsReadCommand #[Transactional]
     -> findUnreadForRecipient -> markRead каждой -> один run -> DataResponse<UnreadCountResource>(0)
GET  /notification-settings: GetNotificationSettingsQuery
     -> registry.all() × NotificationChannel с учётом строк -> CollectionResponse<NotificationSettingResource{type,channel,enabled,default}>
PUT  /notification-settings: UpdateNotificationSettingsCommand #[Transactional]
     -> upsert строк (неизвестный вид/канал -> ValidationException 422) -> один run -> CollectionResponse<...>
POST /notification-device-tokens: RegisterNotificationDeviceTokenCommand #[Transactional]
     -> findByToken: есть -> reassignTo(user,platform); нет -> create -> persist+run -> DataResponse<NotificationDeviceTokenResource>
DELETE /notification-device-tokens: RemoveNotificationDeviceTokenCommand #[Transactional]
     -> findByToken (нет -> NotFoundException) -> delete + run -> DataResponse<NotificationDeviceTokenResource>
        (200 с телом удалённого токена; 204/Empty-Response в пакете spiral-openapi нет — мета-ревью)
```

## Контракты реализации

### Данные и БД

Меняется: 3 таблицы одной миграцией
`app/database/migrations/{YYYYMMDD}.{HHMMSS}_0_create_notification_domain_tables.php`
(стиль `create_media_domain_tables`). UUID v7 PK, snake_case, `datetime`, `json`, явные
индексы. FK на `users` нет (`arch.md:140-146`).

```text
notifications                    -- инбокс (канал database), создаётся фоновой рассылкой
  id          uuid    PK   not null
  outbox_id   uuid    not null      -- id outbox-события NotificationRequested (идемпотентность)
  user_id     uuid    not null      -- получатель (UserId)
  type        string(255) not null  -- код вида из реестра
  title       string(255) not null  -- готовый заголовок (язык источника)
  body        text    not null      -- готовый текст
  action_type string(255) nullable  -- deep-link: тип цели (chat, post, …); null = без перехода
  action_id   string(255) nullable  -- deep-link: id цели
  read_at     datetime nullable     -- null-object VO NotificationReadState
  created_at  datetime not null     -- время триггера
  updated_at  datetime not null
  primary key (id)
  unique (outbox_id)                 -- идемпотентность рассылки
  index (user_id, id)                -- cursor-пагинация по UUID v7 (rules.md:32)
  index (user_id, read_at)           -- счётчик непрочитанных

notification_settings            -- вид × канал на пользователя
  id        uuid    PK   not null
  user_id   uuid    not null
  type      string(255) not null
  channel   string(32)  not null   -- значение NotificationChannel
  enabled   boolean not null
  created_at datetime not null
  updated_at datetime not null
  primary key (id)
  unique (user_id, type, channel)

notification_device_tokens       -- push-токены (открытым текстом)
  id          uuid    PK   not null
  user_id     uuid    not null
  token       string(1024) not null
  platform    string(32)   not null  -- DevicePlatform: ios | android
  created_at  datetime not null
  updated_at  datetime not null
  primary key (id)
  unique (token)
  index (user_id)
```

Совместимость/откат: новые таблицы, backfill не нужен; `down()` удаляет три таблицы.
`action_type`/`action_id`, `read_at`, `enabled` гидрируются отдельными typecast-классами (`rules.md:81`).

### API и внешние контракты

**Внутренний (для других модулей)** — `Notifications/Application/Contract`:

```text
NotificationSenderContract::send(UserId $recipient, NotificationContent $content): void
  -- стейджит один NotificationRequested; run() НЕ вызывает.
NotificationTypeRegistryContract::register(NotificationTypeDefinition ...$definitions): void
NotificationTypeRegistryContract::all(): NotificationTypeDefinitionCollection
NotificationTypeRegistryContract::get(NotificationTypeCode $code): NotificationTypeDefinition  -- неизвестный -> исключение

interface NotificationTypeDefinition:   -- регистрируется модулем-источником в его бутлоадере
  code(): NotificationTypeCode
  defaultChannels(): NotificationChannelDefaults

NotificationContent (Application VO, источник строит per-occurrence):
  type: NotificationTypeCode
  title: NotificationTitle
  body: NotificationBody
  action: NotificationAction        -- один VO: none() | linkTo(actionType, actionId)
```

**Outbox-сообщения** (`Application/Message`, `implements OutboxMessage`, только примитивы;
`action` — вложенный `?NotificationActionPayload{actionType:string, actionId:string}`,
`Application/Dto`, `readonly`, публичный конструктор; `null` = без перехода):

```text
NotificationRequested        {userId, type, title, body, action, createdAt}
NotificationPushRequested    {userId, type, title, body, action, createdAt}
NotificationRealtimeRequested{userId, type, title, body, action, createdAt}
```

Пары message→Job (регистрируются в `NotificationsBootloader` + `app/config/queue.php`):

```text
NotificationRequested         -> DispatchNotificationJob
NotificationPushRequested     -> SendPushNotificationJob
NotificationRealtimeRequested -> PublishRealtimeNotificationJob
```

**HTTP API (мобильный клиент)** — все под `authUserId` (`#[Attribute(key: 'authUserId')]`),
ответы — типизированные Response/Resource, тонкие контроллеры:

```text
GET    /api/v1/notifications                 PaginationResponse<NotificationResource>   (cursor, limit)
GET    /api/v1/notifications/unread-count     DataResponse<UnreadCountResource>
POST   /api/v1/notifications/{id}/read        DataResponse<NotificationResource>          404 если нет/чужое
POST   /api/v1/notifications/read-all         DataResponse<UnreadCountResource>
GET    /api/v1/notification-settings          CollectionResponse<NotificationSettingResource>
PUT    /api/v1/notification-settings          CollectionResponse<NotificationSettingResource>   422 при неизвестном виде/канале
POST   /api/v1/notification-device-tokens     DataResponse<NotificationDeviceTokenResource>
DELETE /api/v1/notification-device-tokens     DataResponse<NotificationDeviceTokenResource>    404 если токена нет
```

**Внешние сервисы**:

- **FCM HTTP v1** через `kreait/firebase-php` 8.2.0 — `FcmPushSenderContract` + `KreaitFcmPushSender`
  (Infrastructure/Push). Конфиг `app/config/push.php` + `PushConfig` (`projectId`, `credentialsFile`).
  Невалидные токены (`UNREGISTERED`/`INVALID_ARGUMENT`) → удаление; временные → `RetryException`.
- **Centrifugo v6 HTTP API** — `CentrifugoServiceContract` + `CentrifugoService`/`CentrifugoClient`
  (Infrastructure/Centrifugo). POST `{CENTRIFUGO_API_URL}/publish`, `X-API-Key`. Конфиг
  `app/config/centrifugo.php` + `CentrifugoConfig` (`apiUrl`, `apiKey` из существующих env).

## Фазы выполнения

### 1. Домен модуля

Цель: доменная модель без инфраструктуры — VO, enum, состояния, коллекции, 3 Entity.

Что сделать:
- VO-идентификаторы (`Domain/ValueObject`): `NotificationId`, `NotificationSettingId`,
  `NotificationDeviceTokenId`, `NotificationOutboxId` — `extends AbstractUuidV7Id`.
- VO: `NotificationTypeCode` (код вида, валидация формата `module.action`), `NotificationTitle`
  (непустой, ≤255), `NotificationBody` (непустой), `DeviceToken` (непустой).
- Backing-VO для двух колонок deep-link (null-object, образец `MediaProcessingError`):
  `NotificationActionType` (`none()`/`of(code)`), `NotificationActionId` (`none()`/`of(id)`).
- enum (`Domain/Enum`): `NotificationChannel: string { Database, Push, Realtime }`,
  `DevicePlatform: string { Ios, Android }`, `NotificationSettingStatus: string { Enabled, Disabled }`.
- Составной VO `NotificationChannelDefaults` (`readonly`, `JsonSerializable`, фабрика,
  `isEnabled(NotificationChannel): bool`).
- Null-object `NotificationAction` — один `final readonly class` с двумя backing-VO внутри:
  `hasLink(): bool`, аксессоры `actionType()`/`actionId()`; фабрики `none()`/`linkTo(actionType, actionId)`;
  `fromParts(NotificationActionType, NotificationActionId): NotificationAction` (для метода `action()` на Entity).
- Null-object `NotificationReadState` — один `final readonly class` с приватным nullable:
  `isRead(): bool`, `markedAt()` (guard), `value()`; фабрики `unread()`/`readAt(DateTimeImmutable)`.
- Типизированные коллекции на `Illuminate\Support\Collection`: `NotificationCollection`,
  `NotificationSettingCollection`, `NotificationDeviceTokenCollection`,
  `NotificationTypeDefinitionCollection`.
- Entity (`Domain/Entity`) с `create()` и доменными методами:
  - `Notification`: `id, outboxId, userId(UserId), type, title, body, actionType, actionId,
    readState` + доменный метод `action(): NotificationAction` (`NotificationAction::fromParts`
    поверх `actionType`/`actionId`) — **метод, не property-hook** (см. решение 11);
    `use HasTimestamps`; `create(...)` принимает `NotificationAction` и раскладывает в
    `actionType`/`actionId`, ставит `initializeTimestamps($triggeredAt)`; `markRead(now)`,
    predicate `isRead()`.
  - `NotificationSetting`: `id, userId, type, channel, status`; `use HasTimestamps`;
    `enable()`, `disable()`, predicate `isEnabled()`.
  - `NotificationDeviceToken`: `id, userId, token, platform`; `use HasTimestamps`;
    `reassignTo(UserId, DevicePlatform)`.

Результат: компилируемая доменная модель; enum с исчерпывающими `match`; валидация только в VO.

Сценарии тестирования (suite `Unit`):
- VO бросают `InvalidDomainValueException` на невалидном входе; `equals()` корректен.
- `NotificationAction`: `none()->hasLink()===false`, `linkTo(t,i)->hasLink()===true`.
- `NotificationReadState`: `unread()->isRead()===false`, `readAt(x)->isRead()===true`.
- `NotificationChannelDefaults::isEnabled()` по всем каналам.
- Entity: `create()` инициализирует поля и timestamps; `markRead()`→`Read`;
  `enable()/disable()`; `reassignTo()` меняет владельца токена.

Проверка: `make test` (Unit зелёный), `make phpstan`.

### 2. Персистентность: миграция, typecast, репозитории

Цель: модель сохраняется/читается из PostgreSQL по правилам проекта.

Что сделать:
- Миграция `create_notification_domain_tables` (3 таблицы, индексы, unique — см. «Данные и
  БД», включая `notifications.outbox_id` + `unique`); `down()` удаляет три таблицы.
  Мета-ревью: `DatabaseTestCase` не пересоздаёт схему — перед тестами фазы 2 нужно прогнать
  миграции в тестовые БД и прогреть Cycle schema cache (это делают `make`-цели через
  `docker/test/migrate-test-databases.sh` + `warmup.sh`); иначе «relation does not exist» /
  ORM не увидит новые Entity. Критерий фазы 2 включает успешный прогон миграции и warmup.
- Typecast-классы (`Infrastructure/Cycle`, `implements ColumnValueTypecast`):
  `NotificationActionTypeTypecast` / `NotificationActionIdTypecast` (nullable string ↔ null-object
  `NotificationActionType`/`NotificationActionId`, образец `MediaProcessingErrorTypecast`),
  `NotificationReadStateTypecast` (nullable datetime ↔ `NotificationReadState`),
  `NotificationSettingStatusTypecast` (boolean ↔ `NotificationSettingStatus`).
  Мета-ревью: PostgreSQL отдаёт `enabled` как PHP `bool`, поэтому общий `ValueObjectCast`
  с `BackedEnum::from()` тут не подойдёт (упадёт на `bool`); сигнатура
  `NotificationSettingStatusTypecast::castDatabaseValue(bool|null $value)` (а не `string|null`,
  как у `MediaProcessingErrorTypecast`), `uncastValue` возвращает `bool`.
- Подключить typecast в Entity: `#[Entity(typecast: [Typecast::class, ValueObjectCast::class])]`
  + `#[Column(typecast: …)]` на сложных колонках; простые VO/enum — через `ValueObjectCast`.
- Репозитории (`Repository`, `extends Repository<Entity>`, read-only, доменные методы):
  - `NotificationRepository`: `findByOutboxId(NotificationOutboxId): ?Notification`,
    `findByIdForRecipient(NotificationId, UserId): ?Notification`,
    `findPageForRecipient(UserId, ?NotificationId $cursor, int $limit): NotificationCollection`,
    `findUnreadForRecipient(UserId): NotificationCollection`, `countUnreadForRecipient(UserId): int`.
  - `NotificationSettingRepository`: `findForUserAndType(UserId, NotificationTypeCode)`,
    `findForUser(UserId)`, `findOneForUserTypeChannel(UserId, NotificationTypeCode, NotificationChannel): ?NotificationSetting`.
  - `NotificationDeviceTokenRepository`: `findActiveForUser(UserId): NotificationDeviceTokenCollection`,
    `findByToken(DeviceToken): ?NotificationDeviceToken`.
  Мета-ревью (нет прецедента cursor-пагинации в проекте): `findPageForRecipient` строится
  только через ORM `$this->select()` — `where('user_id', $userId->value())`, при наличии
  курсора `where('id', '<', $cursor->value())`, `orderBy('id', 'DESC')`, `limit($limit)`
  (вызывающий Query берёт `$limit+1` для вычисления `nextCursor`). Критерии всех `findOne`/
  `select` кладутся как скаляры колонок (`->value()` у VO, `$channel->value` у enum), не как
  объекты. Сырой SQL запрещён (`rules.md:74,79`).

Результат: persist/read трёх сущностей; cursor-пагинация по `id`; typecast корректен;
поиск по `outbox_id`.

Сценарии тестирования (suite `Kernel`/`Feature` на `DatabaseTestCase`):
- round-trip каждой Entity; `action_type`/`action_id`: оба null ↔ `action.hasLink()===false`,
  заданы ↔ `NotificationLink(actionType, actionId)`.
- `read_at`: null→`Unread`, после `markRead`→`Read`; `enabled` boolean↔enum.
- `findPageForRecipient` — `id DESC`, режет по `limit`, курсор работает.
- `findByOutboxId` находит/не находит; `unique (outbox_id)`, `unique (user_id,type,channel)`,
  `unique (token)` соблюдаются.
- `countUnreadForRecipient`/`findUnreadForRecipient` считают только непрочитанные.

Проверка: `make test`, `make phpstan`.

### 3. Контракты, реестр видов, NotificationSender (лёгкий триггер)

Цель: публичный контракт отправки, который стейджит один `NotificationRequested`.

Что сделать:
- Контракты (`Application/Contract`): `NotificationTypeDefinition`,
  `NotificationTypeRegistryContract`, `NotificationSenderContract`.
- Application VO `NotificationContent{type, title, body, action}`.
- Реестр `NotificationTypeRegistry` (`Infrastructure/Registry`): хранит определения по `code()`,
  `register()` валидирует дубли, `get()` бросает доменное исключение на неизвестный код.
- outbox-сообщение `NotificationRequested` (`Application/Message`, поля примитивны;
  `createdAt: string`, `action: ?NotificationActionPayload`) + payload-DTO
  `NotificationActionPayload` (`Application/Dto`, `readonly`, публичный конструктор
  `string actionType, string actionId`).
- `NotificationSender` (`Application`, `implements NotificationSenderContract`): инжектит
  `NotificationTypeRegistryContract` (валидация кода) + `OutboxEventStoreContract`; стейджит
  один `NotificationRequested`, `run()` не вызывает.
- Бутлоадер `NotificationsBootloader`: `BINDINGS`
  (`NotificationSenderContract`→`NotificationSender`), `SINGLETONS`
  (`NotificationTypeRegistryContract`→`NotificationTypeRegistry`). **Зарегистрировать
  `NotificationsBootloader::class` в `Kernel::defineBootloaders()`** (после Outbox-бутлоадеров)
  — без этого BINDINGS и регистрация Job не активируются (мета-ревью).
- Фикстуры для тестов: `FixtureNotificationTypeDefinition` (code + defaultChannels) +
  тестовый бутлоадер регистрации.

Результат: `send()` стейджит ровно один `NotificationRequested` с примитивным payload;
незарегистрированный вид падает сразу.

Сценарии тестирования (suite `Feature`/`Unit`):
- `send()` стейджит один `NotificationRequested`; payload содержит `type/title/body/action`.
- `send()` с незарегистрированным кодом → исключение, событие не стейджится.
- `ValinorOutboxMessageSerializer` round-trip `NotificationRequested` (в т.ч. `action=null` и `action` с данными).
- `NotificationTypeRegistry`: дубль→исключение, `get()` неизвестного→исключение, `all()` корректен.

Проверка: `make test`, `make phpstan`.

### 4. Фоновая рассылка: каналы, инбокс, стейджинг доставки

Цель: `DispatchNotificationJob` решает каналы, создаёт инбокс идемпотентно, стейджит push/realtime.

Что сделать:
- outbox-сообщения `NotificationPushRequested`, `NotificationRealtimeRequested`
  (`Application/Message`) — та же форма, что `NotificationRequested`.
- `Notification/DispatchNotification` (`Application/Command`): `DispatchNotificationCommand`
  (outboxId, userId, type, title, body, action, createdAt), `DispatchNotificationHandler`
  `#[Transactional]`: идемпотентность по `findByOutboxId`; defaults из `registry.get(type)`;
  настройки из `NotificationSettingRepository`; `database`→`Notification::create(...)`+persist;
  `push`/`realtime`→`outboxEventStore->add(...)`; один `run()`.
- `DispatchNotificationJob` (`Presentation/Job`, `extends JobHandler`, образец `ProcessMediaJob`):
  load `NotificationRequested`, dispatch `DispatchNotificationCommand`, классификация `RetryException`/rethrow.
- Регистрация пары `NotificationRequested → DispatchNotificationJob` в `NotificationsBootloader.boot()`
  (`OutboxJobRegistryContract::register($messageClass, $jobClass)` — отдельный вызов на каждую
  пару, контракт принимает два string-параметра) + `app/config/queue.php` (`registry.handlers`
  + `serializers` → `OutboxQueueSerializer`).

Результат: после commit-а источника инбокс создаётся (если `database` вкл) и push/realtime
стейджатся по настройкам; повтор того же события — no-op.

Сценарии тестирования (suite `Feature` на `DatabaseTestCase`, фикстуры):
- все каналы по умолчанию → 1 инбокс-строка + застейджены `NotificationPushRequested` и
  `NotificationRealtimeRequested`.
- настройка выключает push → push-событие не стейджится; инбокс и realtime есть.
- настройки нет → действуют `defaultChannels()` определения вида.
- идемпотентность (database вкл): повторная рассылка того же `outboxId` — полный no-op,
  второй инбокс не создаётся, push/realtime повторно не стейджатся.
- идемпотентность (database выкл): инбокс не создаётся, и повторная рассылка повторно стейджит
  push/realtime (осознанный at-least-once — тест фиксирует это как ожидаемое поведение, решение 8).
- `database` выключен → инбокс не создаётся; push/realtime по настройкам.
- неизвестный вид при загрузке (вид не в реестре) → `registry.get()` бросает, Job rethrow (терминально).
- `createdAt` инбокса = время триггера из payload (строка → `\DateTimeImmutable`).

Проверка: `make test`, `make phpstan`.

### 5. CQRS use-case'ы модуля (Command + Query)

Цель: собственные сценарии модуля (HTTP-вход подключается в фазе 7).

Что сделать (`Application/Command|Query/{Area}/{Action}`, полные имена — `rules.md:92-93`):
- Commands (`#[Transactional]`, Handler создаёт VO, `persist`+`run`):
  `Notification/MarkNotificationRead` (чужая/нет → `NotFoundException`),
  `Notification/MarkAllNotificationsRead` (загрузить непрочитанные, `markRead` каждой, один
  `run`; батчинг/лимит не делаем — осознанное ограничение MVP, мета-ревью),
  `Setting/UpdateNotificationSettings` (`list<NotificationSettingUpdate{type,channel,enabled}>`,
  upsert; неизвестный вид/канал → `ValidationException` 422),
  `DeviceToken/RegisterNotificationDeviceToken` (upsert по токену),
  `DeviceToken/RemoveNotificationDeviceToken` (нет → `NotFoundException`).
- Queries (без транзакции): `Notification/ListNotifications` (`findPageForRecipient(limit+1)`,
  `ListNotificationsResult{NotificationCollection, ?string nextCursor}`),
  `Notification/GetUnreadCount` (`UnreadCountResult{int}`),
  `Setting/GetNotificationSettings` (`registry.all()` × `NotificationChannel` + строки →
  `NotificationSettingViewCollection`).

Результат: все бизнес-операции модуля доступны через Command/Query Handler-ы.

Сценарии тестирования (suite `Feature` через `CommandBus`/`QueryBus`):
- mark-read одной/всех; чужая строка → 404.
- update-settings создаёт/обновляет строки; неизвестный вид → 422.
- register-token создаёт и переустанавливает (тот же токен новому пользователю).
- remove-token удаляет; отсутствующий → 404.
- list: пагинация и `nextCursor`; get-unread-count; get-settings — матрица с дефолтами
  фикстурного вида и переопределениями.

Проверка: `make test`, `make phpstan`.

### 6. Инфраструктура доставки: конфиги, Centrifugo, FCM, доставочные Jobs

Цель: фактическая отправка push и realtime по образцу `ProcessMediaJob`.

Что сделать:
- `composer require kreait/firebase-php:^8.2`; критерий — `composer.lock` обновился без
  downgrade уже установленных пакетов (мета-ревью: тянет `google/auth`, `google/cloud-storage`,
  совместимо с текущими guzzle 7.10 / valinor 2.4, конфликта версий нет), `make phpstan` на
  PHP 8.5 зелёный.
- Конфиги + typed-config + ConfigMapper-тесты (`rules.md:50`, suite `Kernel`):
  `app/config/centrifugo.php` + `CentrifugoConfig` и `app/config/push.php` + `PushConfig` —
  оба DTO **строго** в `app/src/Shared/Infrastructure/Configuration/{Centrifugo,Push}/` (иначе
  `ConfigBootloader` их не подхватит — мета-ревью); `CentrifugoConfig` (`apiUrl`, `apiKey` из
  существующих env), `PushConfig` (`projectId`, `credentialsFile`); добавить `FCM_PROJECT_ID`,
  `FCM_CREDENTIALS_FILE` в `.env` и `.env.sample`.
- Centrifugo: `CentrifugoClient` (PSR-18, POST `{apiUrl}/publish` + `X-API-Key`) +
  `CentrifugoService` (`implements CentrifugoServiceContract`); `publish()` принимает канал и
  **типизированный** payload-DTO `RealtimeNotificationPayload` (не `array<string,mixed>`,
  `rules.md:100`), который сериализуется в camelCase JSON; временные ошибки →
  `CentrifugoPublishException` (`Infrastructure/Exception`, `isTransient()`). Примечание: в
  проекте есть gRPC-пакет `roadrunner-php/centrifugo` — намеренно НЕ используем, нужен HTTP-клиент
  (`rules.md:85`).
- FCM: `KreaitFcmPushSender` (`implements FcmPushSenderContract`) на `kreait/firebase-php`,
  создаётся из `PushConfig`; классификация → `FcmPushFailedException` (`isTransient()` +
  список невалидных токенов). Покрытие: держать класс тонким адаптером над `Kreait\Firebase\Messaging`
  и тестировать с подменённым `Messaging` (мок), чтобы выполнить 100% без реального
  service-account; альтернатива — вынести классификацию ошибок в отдельный покрытый класс.
- Application Command+Handler доставки: `Push/SendPushNotification` (выборка токенов, отправка
  готового title/body+action, удаление невалидных токенов `delete`+`run`),
  `Realtime/PublishRealtimeNotification` (канал + payload, `publish`).
- Jobs (`Presentation/Job`, `extends JobHandler`): `SendPushNotificationJob`,
  `PublishRealtimeNotificationJob` — load, dispatch, классификация `RetryException`/rethrow.
- Регистрация в `NotificationsBootloader.boot()` пар
  `NotificationPushRequested→SendPushNotificationJob`,
  `NotificationRealtimeRequested→PublishRealtimeNotificationJob`; в `BINDINGS`
  `FcmPushSenderContract`→`KreaitFcmPushSender`, `CentrifugoServiceContract`→`CentrifugoService`;
  в `app/config/queue.php` оба Job (`handlers`+`serializers`→`OutboxQueueSerializer`).

Результат: push и realtime доставляются после commit-а; невалидные токены чистятся;
временные сбои уходят в retry через relay.

Сценарии тестирования (suite `Kernel`/`Feature`):
- ConfigMapper-тесты `CentrifugoConfig` и `PushConfig`.
- `SendPushNotificationHandler` с фейковым `FcmPushSenderContract`: отправка title/body+action,
  удаление токенов из `invalidTokens`, отсутствие токенов → пропуск.
- `PublishRealtimeNotificationHandler` с фейковым `CentrifugoServiceContract`: канал и payload (camelCase).
- `CentrifugoClient` против фейкового PSR-18 client: URL `{apiUrl}/publish`, `X-API-Key`, тело.
- Jobs: временный сбой → `RetryException`, терминальный → rethrow; пары message→Job в реестре.

Проверка: `make test`, `make phpstan`.

### 7. HTTP API и финальная проверка

Цель: 8 роутов мобильного клиента, интеграционные тесты на каждый роут, OpenAPI и docs.

Что сделать:
- Контроллеры (`Presentation/Http/Controller`, тонкие, `#[Route]`, `#[Attribute(key:'authUserId')]`,
  `@return` с дженериком): `NotificationController` (list, unread-count, read, read-all),
  `NotificationSettingController` (get, update), `NotificationDeviceTokenController` (register, remove).
- Filters (`Presentation/Http/Filter/{Area}`, ООП, без `array<string,mixed>`):
  `ListNotificationsFilter` (`#[Query]` cursor, limit), `UpdateNotificationSettingsFilter`
  (вложенный список через `#[NestedFilter]`/DTO), `RegisterNotificationDeviceTokenFilter`
  (`#[Post]` token, platform enum), `RemoveNotificationDeviceTokenFilter`.
- Resources (`extends AbstractResource`): `NotificationResource` (id, type, title, body,
  `action: ?NotificationActionResource`, read, createdAt — title/body как есть);
  `NotificationActionResource` — отдельный вложенный DTO `{actionType, actionId}` (не inline
  array-shape — иначе OpenAPI-парсер схлопнет union; мета-ревью), `null` при отсутствии перехода;
  `UnreadCountResource`, `NotificationSettingResource` (type, channel, enabled, default),
  `NotificationDeviceTokenResource`.
- OpenAPI (мета-ревью, блокер): расширить `app/config/openapi.php` (`sourcePath`/`apiNamespace`)
  так, чтобы генератор сканировал `App\Modules\Notifications\Presentation\Http`; убедиться, что
  пакет `packages/spiral-openapi` поддерживает несколько источников (иначе доработать пакет
  отдельной задачей). Проверить, что роуты `#[Route]` модуля подхватываются глобальным сканом
  (как `HealthController`); если нет — зарегистрировать namespace.
- `php app.php openapi:generate`, проверить, что 8 роутов появились в `public/openapi/openapi.yml`.

Результат: мобильный клиент работает со списком, счётчиком, отметкой прочитанного, настройками
и push-токенами.

Сценарии тестирования (suite `Feature`, HTTP через `FakeHttp`). `FakeHttp` имеет
`getWithAttributes()` только для GET; для POST/PUT/DELETE завести общий тестовый хелпер
(`createJsonRequest()` → `withAttribute('authUserId', $userId)` → `handleRequest()`) и
зафиксировать тестом, что pipeline не теряет `authUserId` (auth-middleware пока нет — мета-ревью):
- по одному интеграционному тесту на каждый из 8 роутов (`rules.md:105`): успех + ключевая
  ошибка (404 на чужой/несуществующей строке, 422 на неизвестном виде/канале, валидация фильтра).
- list отдаёт title/body/action как сохранено; пагинация `nextCursor`.
- read-all обнуляет счётчик; register/remove токена меняют `findActiveForUser`.

Проверка: полный `make test` (все suite зелёные, 100% покрытие нового кода), `make phpstan`,
`php app.php openapi:generate` без ошибок.

## Тесты

Стратегия: `after_each_phase`. Каждая фаза завершается своими тестами и зелёными `make test`
+ `make phpstan` до перехода к следующей. Контейнерный запуск обязателен (`make test`,
`make phpstan`) — часть тестов требует Docker-сети (`AGENTS.md`). Покрытие нового кода — 100%
(`rules.md:104`), каждый роут — интеграционный тест (`rules.md:105`), дублёры без проверки
вызова — `createStub()` (`rules.md:106`). Конкретных видов в проде нет → поток отправки,
рассылка и экран настроек тестируются через фикстурное `FixtureNotificationTypeDefinition` +
фикстурный `NotificationContent` с готовым тестовым текстом, регистрируемые тестовым
бутлоадером. Между фазами 4 и 6 push/realtime-события стейджатся, но их Job регистрируются
в фазе 6 — тесты фазы 4 проверяют только факт стейджинга в outbox, не доставку.

## Логирование

Стратегия: `debug_precise` (`rules.md:84`). Точные точки:

- **DEBUG** (нормальный поток): `NotificationSender` — триггер (`userId`, `type`), стейджинг
  `NotificationRequested` (`outboxId`); `DispatchNotificationHandler` — no-op при повторе,
  решение по каждому каналу (`channel`, `enabled`, источник setting/default), создание инбокса
  (`notificationId`), стейджинг push/realtime (`outboxId`, тип); `SendPushNotificationHandler`
  — число токенов, результат, удаление каждого невалидного токена; `PublishRealtimeNotificationHandler`
  — канал; mark-read/mark-all, register/remove токена. Контекст — camelCase (`rules.md:61`),
  без значений токенов в логах.
- **WARN**: временная недоступность FCM/Centrifugo перед `RetryException`.
- **ERROR**: терминальный сбой доставки (битый payload, неизвестный вид при загрузке),
  нарушения инвариантов.
- `#[LogOperation]` на Handler-ах, где уместно (как в Media). Логи — на русском (`rules.md:7`).

## Документация и эксплуатация

- `.env` и `.env.sample`: добавить `FCM_PROJECT_ID`, `FCM_CREDENTIALS_FILE` (Centrifugo-переменные
  уже есть). Service-account JSON FCM — вне репозитория, путь через env (`rules.md:49`).
- `AGENTS.md`/`docs/arch.md`: упомянуть модуль `Notifications` и контракт
  `NotificationSenderContract` как точку интеграции для будущих модулей (отдельной задачей,
  не блокирует релиз).
- Релиз: запустить миграцию (3 таблицы); outbox relay уже работает одним процессом
  (`rules.md:87`) — `NotificationRequested`/push/realtime подхватываются им после регистрации
  Job в `queue.php`.
- Зависимость JWT-middleware/`authUserId`: реальная аутентификация появится с модулем `Auth`;
  до этого роуты функционально готовы и покрыты тестами, но в проде требуют установки
  `authUserId` (middleware Auth-модуля).

## Изменения после мета-ревью

### После моделей

- **+ Добавлено:**
  - Явный шаг регистрации `NotificationsBootloader` в `Kernel::defineBootloaders()` (после
    Outbox) — без него BINDINGS и регистрация Job не активируются (решение 19).
  - Шаг расширения OpenAPI-конфига (`app/config/openapi.php`) на namespace Notifications —
    иначе 8 роутов не попадут в `openapi.yml` и критерий «готово» не выполнится (решение 21, фаза 7).
  - Явный критерий фазы 2: прогон миграции в тестовые БД + warmup Cycle schema cache
    (`DatabaseTestCase` схему не пересоздаёт).
  - Стратегия 100% покрытия `KreaitFcmPushSender` (тонкий адаптер над `Messaging` + мок).
  - Критерий `composer require kreait/firebase-php` — без downgrade установленных пакетов.
  - Тестовый хелпер для аутентифицированных POST/PUT/DELETE + тест, что pipeline не теряет `authUserId`.
  - Детализация cursor-пагинации через ORM Select и критериев репозитория как скаляров колонок.
- **~ Изменено:**
  - `action` на Entity — доменный **метод** `action()` вместо property-hook: `LazyGhostEntityFactory`
    обходит все не-static свойства и упал бы на virtual-свойстве (решение 11).
  - `createdAt` в payload/Command — примитив `string` + конверсия в Handler; `outboxId` в Command —
    `string`, в Handler `NotificationOutboxId::fromString(...)`.
  - DELETE-роут — `DataResponse<NotificationDeviceTokenResource>` вместо «DataResponse/204»
    (204/Empty-Response в `spiral-openapi` нет).
  - `NotificationSettingStatusTypecast::castDatabaseValue(bool|null)` (PostgreSQL отдаёт `bool`);
    typed-config Centrifugo/Push — строго в `Shared/Infrastructure/Configuration/{Centrifugo,Push}/`;
    payload Centrifugo — типизированный DTO; `action` в ресурсе — вложенный `NotificationActionResource`.
  - Идемпотентность-тест разнесён на два случая (database on → полный no-op; database off →
    допустимый at-least-once дубль доставки).
- **− Убрано:** формат ответа «DataResponse/204» для DELETE (нет такого Response в пакете).
- **Отклонено:**
  - Бамп `token` до `text` (sonnet/opus) — оставлено `string(1024)`: достаточно для FCM-токенов и
    безопасно для unique btree-индекса (`text` рискует превысить лимит размера индекса).
  - Регистрация Entity-директории в `cycle.php` — не нужна: Cycle сканирует Entity через tokenizer
    автоматически (подтверждено мета-ревью, решение 20).
  - Отдельная проверка версии Centrifugo (haiku) — версия уже зафиксирована в docker-compose и `.env`.

## Изменения при исполнении

- **Уточнено правило `rules.md:21`** (по согласованию с пользователем): добавлен явный критерий
  выбора формы null-object VO. Одно опциональное значение, поведение «есть/нет» → один
  `final readonly class` с приватным nullable (образец `MediaExpiration`); несколько состояний с
  разными данными/поведением → абстракция + реализации (образец `Ip`→`KnownIp`/`UnknownIp`).
- Следствие для решений 11 и 13: `NotificationAction` и `NotificationReadState` делаются **одним
  классом** (а не абстракция + наследники). Поведение и контракты не меняются.
- **Проектный рефактор под новое правило**: существующий `OutboxEventDate` (был
  `Empty`/`Known`) сведён к одному `final readonly class`; `OutboxEventDateTypecast` упрощён
  (убрана недостижимая throw-ветка и тестовый подкласс `UnknownOutboxEventDate`). Тесты Outbox
  обновлены, поведение сохранено.

## Прогресс выполнения
Журнал: `docs/executions/2026-06-13_17-13_notifications_module.md`

Сделано вне фаз (рефактор под уточнённое `rules.md:21`, по согласованию с пользователем):
- [x] `rules.md:21` обновлён: явный критерий выбора формы null-object VO.
- [x] `NotificationAction` и `NotificationReadState` сведены к одному `final readonly class`.
- [x] Существующий `OutboxEventDate` (был `Empty`/`Known`) сведён к одному классу; `OutboxEventDateTypecast`
      упрощён; тесты Outbox обновлены.
- [x] План синхронизирован с правилом (решения 11/13, Фаза 1, контракт, раздел «Изменения при исполнении»).
- [x] Locale-тесты (`LocaleConfigTest`, `SimpleConfigBindingTest`) сделаны независимыми от `env(LOCALE)`.
- [x] Доустановлены зависимости (`make composer-install` — path-пакеты не были слинкованы в worktree).
- [x] Проверки зелёные: `make phpstan` ok, `make test` 424/424.

Фазы плана:
- [x] Фаза 1: Домен модуля — VO, enum, коллекции, 3 Entity (доменные классы без Cycle-аннотаций —
      они в Фазе 2) + Unit-тесты (3 файла: ValueObject/Entity/Enum). `make test-unit` 262 зелёных,
      `make phpstan` ok.
- [x] Фаза 2: Персистентность — миграция `create_notification_domain_tables` (3 таблицы), Cycle-аннотации
      на 3 Entity, 4 typecast-класса (ActionType/ActionId/ReadState/SettingStatus), 3 репозитория.
      Unit-тест typecast + Feature round-trip репозиториев. Миграция применяется, warmup ok,
      `make test-feature` 173 ok, `make test-unit` 275 ok, `make phpstan` ok.
- [x] Фаза 3: Контракты (`NotificationTypeDefinition`, `NotificationTypeRegistryContract`,
      `NotificationSenderContract`), DTO (`NotificationContent`, `NotificationActionPayload`,
      `NotificationTypeDefinitionCollection`), message `NotificationRequested`, `NotificationTypeRegistry`
      (singleton), `NotificationSender`, `NotificationsBootloader` (+ регистрация в Kernel после Outbox),
      фикстура `FixtureNotificationTypeDefinition`. Unit-тесты: реестр, sender, serializer round-trip.
      `make test-unit` 283 ok, `make test-kernel` 42 ok, `make phpstan` ok.
      Уточнение по arch: `NotificationTypeDefinitionCollection` положен в `Application/Dto` (а не
      `Domain/Collection`, как в Фазе 1 плана) — он держит Application-контракт, а Domain не зависит от Application.
- [x] Фаза 4: Фоновая рассылка — messages `NotificationPushRequested`/`NotificationRealtimeRequested`,
      `DispatchNotificationCommand`+`DispatchNotificationHandler` (#[Transactional], идемпотентность по
      outbox_id, решение каналов, инбокс, стейджинг push/realtime), `DispatchNotificationJob`
      (классификация domain→terminal / прочее→RetryException), регистрация пары в `NotificationsBootloader.boot()`
      + `queue.php`. Feature-тесты: handler (7 сценариев) + Job (happy/terminal/transient). `RecordingOutboxEventStore`
      в tests/Support. `make test-feature` 183 ok, `make phpstan` ok.
- [x] Фаза 5: CQRS use-case'ы — Commands (MarkNotificationRead, MarkAllNotificationsRead,
      UpdateNotificationSettings, Register/RemoveNotificationDeviceToken), Queries (ListNotifications,
      GetUnreadCount, GetNotificationSettings), общий `NotificationSettingsViewFactory` +
      `NotificationSettingView`/Collection, locale `notifications.php` (ru/en). Feature-тест через
      Command/Query шину (13 сценариев: mark-read/all, 404, settings upsert + 422 вид/канал,
      register/reassign + 422 платформа, remove + 404, list-пагинация, unread-count, settings-матрица).
      `make test-feature` 196 ok, `make phpstan` ok.
- [x] Фаза 6: Инфраструктура доставки — `kreait/firebase-php:^8.2` (без downgrade), конфиги
      centrifugo/push + `CentrifugoConfig`/`PushConfig` + .env(.sample) FCM_*, Centrifugo (контракт,
      `RealtimeNotificationPayload`, `CentrifugoClient` на Guzzle PSR-7, `CentrifugoService`,
      `CentrifugoPublishException`), FCM (`FcmPushSenderContract`, `NotificationPush`, `FcmPushResult`,
      `KreaitFcmPushSender`, `FcmPushFailedException`), доставочные Command+Handler (Push/Realtime),
      Jobs (`SendPushNotificationJob`, `PublishRealtimeNotificationJob`), бинды + boot-пары + queue.php.
      Тесты: Unit (CentrifugoClient/FCM-adapter/realtime-handler), Kernel (Config-мапперы + ConfigShape),
      Feature (push-handler + delivery jobs transient/terminal + пары реестра).
      `make test-unit` 291 ok, `make test-kernel` 46 ok, `make test-feature` 203 ok, `make phpstan` ok.
- [x] Фаза 7: HTTP API — 3 контроллера/8 роутов, Filters (recipient/list/update+nested/register/remove,
      на `AttributesFilter`+Symfony), Resources (Notification/Action/UnreadCount/Setting/DeviceToken),
      расширён `OpenApiConfig` до списка sourcePaths + `apiNamespace=App\Modules`, `openapi:generate` →
      8 роутов в `openapi.yml`. Интеграционные тесты на каждый роут (success + 404/422). Починен
      пакетный `ApiValidationErrorsRenderer` под форму ошибок Symfony-валидатора (первый Filter-контроллер
      в проекте). **Финальная проверка `make qa`: php-cs-fixer ok, PHPStan ok, покрытие 100.00%,
      553 теста зелёные; `openapi:generate` без ошибок.**
