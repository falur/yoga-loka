---
title: Модуль уведомлений (Notifications)
date: 2026-06-13 13:16
mode: normal
decision_mode: recommend_and_ask
status: draft
reviewer: none
sources:
  rules: docs/rules.md
  arch: docs/arch.md
---

# Модуль уведомлений (Notifications)

## Суть

Нужен полноценный модуль уведомлений для модульного монолита YogaLoka (PHP 8.5,
Spiral, Cycle ORM, PostgreSQL, RoadRunner). Модуль должен:

- поддерживать **типы доставки** (как именно уведомление доходит): запись в базе
  (in-app список), push, доставка через Centrifugo, когда приложение открыто;
- поддерживать **виды уведомлений** (что произошло): новое сообщение из чата,
  комментарий к записи и т.д.; виды добавляют другие модули, набор открыт;
- дать другим модулям публичный API для отправки уведомления;
- дать пользователю настройку «какой вид уведомления идёт в какой тип доставки»;
- быть расширяемым и по типам доставки, и по видам.

Проблема, которую решаем: сейчас в коде нет ни модуля уведомлений, ни PHP-клиента
Centrifugo, ни push-инфраструктуры. Модули `User`, `Chat`, `Записи` тоже ещё не
созданы (в коде есть только `Media`, `Outbox`, `System`). То есть модуль
проектируется «вперёд» — как стабильный контракт, к которому будущие модули будут
подключаться.

Терминология в документе (чтобы не путать с формулировкой задачи):

```text
вид уведомления   = что случилось  = NotificationType  (новое сообщение, комментарий)
тип доставки      = как доставить   = NotificationChannel (database, push, realtime)
```

## Решение

### Общая картина

```text
Модуль-источник (Chat / Записи), внутри своей транзакции #[Transactional]
  -> сохраняет свою сущность (сообщение, комментарий)
  -> NotificationSenderContract::send(recipientId, NotificationType)   // Application -> Application, прямой вызов, без шины
       реализация NotificationSender (Application):
         1. читает настройки получателя (вид × канал), дефолты берёт у вида
         2. database вкл? -> создаёт Notification (строка инбокса), persist
         3. push     вкл? -> OutboxEventStore::add(NotificationPushRequested)
         4. realtime вкл? -> OutboxEventStore::add(NotificationRealtimeRequested)
         (только стейджит, свой run() НЕ вызывает — flush делает транзакция источника)
  -> EntityManager::run()  // атомарно: бизнес-изменение + строка инбокса + outbox-события

Outbox relay (уже есть в проекте)
  -> NotificationPushRequested     -> SendPushNotificationJob       -> FCM
  -> NotificationRealtimeRequested -> PublishRealtimeNotificationJob -> Centrifugo
```

Ключевая идея — переиспользовать **существующий transactional outbox**
(`docs/arch.md:513-588`, `OutboxEventStoreContract`,
`app/src/Modules/Outbox/Infrastructure/Registry/OutboxJobRegistry.php`). Push и
Centrifugo — внешние эффекты, по правилам проекта они обязаны идти через outbox
(`docs/rules.md:86`). Канал `database` — это просто строка в своей таблице, её
пишем синхронно в той же транзакции, отдельная доставка не нужна.

### Почему именно так, а не иначе

| Развилка | Выбор | Почему |
|---|---|---|
| Доставка push/realtime | через outbox + Job | этого требует `rules.md:86`; готовый relay, retry, статусы, идемпотентность по `outboxId` (`arch.md:586`) |
| Канал `database` | синхронная строка в транзакции | не внешний эффект, outbox избыточен; атомарно с бизнес-изменением |
| Межмодульная отправка | `NotificationSenderContract::send()`, не шина | capability встраивается в транзакцию источника, как `OutboxEventStoreContract::add()` (`arch.md:547`); шина в паре с `#[Transactional]`+`run()` дала бы отдельную транзакцию и сломала атомарность |
| Атомарность | один `run()` у источника | строка инбокса и outbox-события должны записаться одним flush (`arch.md:547-551`) |
| Centrifugo | свой HTTP-клиент | `rules.md:85`: `centrifugal/phpcent` удалён (deprecated `curl_close()` на PHP 8.5) |
| Push | `kreait/firebase-php` | FCM закрывает iOS+Android одним каналом (ответ пользователя); см. версии ниже |

### Виды уведомлений — реестр типизированных видов (ответ пользователя)

Виды принадлежат модулям-источникам, набор открыт. Поэтому не central enum, а
реестр — тем же паттерном, что уже принят для outbox
(`OutboxJobRegistry`). Каждый вид — класс, реализующий контракт
`NotificationType` из `Notifications/Application/Contract`:

```text
interface NotificationType
  code(): NotificationTypeCode            // стабильный строковый код, хранится в БД и настройках
  defaultChannels(): NotificationChannelDefaults  // какие каналы включены по умолчанию
  translationKey(): string                // ключ перевода app.{module}.notification.*
  params(): ...                           // типизированные параметры для подстановки в перевод
  data(): ...                             // структурированные данные для клиента (deep-link: chatId, postId, actorId)
```

- Модуль-источник объявляет свой класс вида (например `Chat` →
  `NewChatMessageNotification`, `Записи` → `PostCommentNotification`),
  регистрирует его в своём бутлоадере через `NotificationTypeRegistryContract`
  (как `MediaBootloader` регистрирует `MediaUploaded`).
- Реестр нужен, чтобы экран настроек мог перечислить все виды и их дефолты.
- При отправке источник передаёт **экземпляр** своего вида (типизированный
  payload) в `NotificationSenderContract::send()`. Это in-process вызов
  Application→Application, не сериализуемая граница, поэтому передавать объект здесь
  допустимо.
- В реализации `NotificationSender` вид «рендерится» в примитивы (translationKey +
  params + data) **до** записи в инбокс и до `OutboxEventStore::add()`, потому что
  payload outbox обязан быть примитивным (см. `MediaUploaded`,
  `ValinorOutboxMessageSerializer`).

Расширение видом = новый класс в модуле-источнике + регистрация в его бутлоадере.
Модуль уведомлений при этом не правится — это и есть требование расширяемости.

### Типы доставки (каналы) — enum + привязка доставки

Каналы принадлежат самому модулю уведомлений и набор почти закрыт, поэтому здесь
enum `NotificationChannel { Database, Push, Realtime }` (по `rules.md:30` для
закрытого набора — enum). Расширяемость канала = добавить case enum + привязать
механизм доставки:

```text
Database -> синхронная строка в таблице notifications
Push     -> outbox NotificationPushRequested     -> SendPushNotificationJob
Realtime -> outbox NotificationRealtimeRequested -> PublishRealtimeNotificationJob
(будущий Email -> outbox NotificationEmailRequested -> SendEmailNotificationJob)
```

Различие с видами осознанное: виды владеются чужими модулями (открытый набор →
реестр), каналы владеются модулем уведомлений (закрытый набор → enum, правка
внутри своего модуля ожидаема).

### Текст и язык (ответ пользователя: язык даст будущий модуль User)

Храним **ключ перевода + типизированные параметры + структурированные данные**, а
не готовую строку:

- **In-app список** читается HTTP-запросом, значит есть локаль запроса
  (`LocaleMiddleware`, `arch.md:379-385`) → текст переводится на чтении по
  `Accept-Language`.
- **Push** уходит асинхронно, локали запроса нет → переводится в момент отправки
  по языку получателя. Язык получателя берём из **модуля User** (он «на подходе»,
  ответ пользователя) прямым вызовом `User/Application` (Query) — это разрешено
  (`arch.md:62-71`, Application→Application). Пока модуля User нет, fallback —
  `LocaleConfig.default`. Это явная точка стыковки, а не заглушка в домене.

Почему не «вызывающий передаёт готовый текст»: у источника нет языка получателя
(нет модуля User у источника), он бы дублировал перевод. Почему не «только данные,
текст на клиенте»: OS-push требует готовый title/body, data-only push на iOS
ненадёжен.

### Получатель — один за вызов (ответ пользователя)

`NotificationSenderContract::send()` адресует одного получателя (`recipientId: UserId`).
Групповые случаи (чат с многими участниками, запись с многими подписчиками)
вызывающий модуль разворачивает в цикл сам. Это упрощает контракт и настройки
(каждый получатель — свои настройки). Получатель хранится как `UserId`
(`Shared/Domain/ValueObject/UserId`), без внешнего ключа на таблицу users —
таблицы users пока нет, а кросс-модульный FK всё равно противоречит границам
модулей (`arch.md:140-146`).

### Настройки пользователя (вид × канал)

Матрица «вид × канал» на пользователя. Строка на каждую пару (user, вид, канал) с
флагом включения — нормализовано и расширяемо и по видам, и по каналам:

- если строки нет → действует дефолт из зарегистрированного вида
  (`defaultChannels()`), пользователь видит и может переопределить;
- экран настроек строится из реестра видов × enum каналов + значений
  пользователя.

Вне области (на будущее, не делаем сейчас): mute конкретного источника (заглушить
один чат/одну запись) — это отдельная фича другого уровня (per-source), не «вид ×
канал». Зафиксировано как осознанное ограничение, чтобы не раздувать схему.

### Push-токены устройств

Часть модуля уведомлений: таблица токенов + API регистрации/удаления. Токен
привязан к пользователю и платформе; на logout клиент удаляет токен. Невалидные
токены (FCM `UNREGISTERED`/`INVALID_ARGUMENT`) Job удаляет при отправке.

### База данных

Три таблицы (стиль миграций — как `create_media_domain_tables`, UUID v7 PK,
snake_case, явные индексы; `rules.md:31`, `rules.md:61`):

```text
notifications                      -- инбокс (канал database)
  id                uuid  PK       -- UUID v7
  recipient_id      uuid  idx      -- UserId, без FK
  type              string idx     -- код вида (из реестра)
  translation_key   string
  params            json           -- типизированные параметры перевода
  data              json           -- структурированные данные для клиента (deep-link)
  read_at           datetime null  -- null-object VO: UnreadAt / ReadAt
  created_at        datetime
  updated_at        datetime
  index (recipient_id, id)         -- cursor-пагинация по UUID v7 (rules.md:32)
  index (recipient_id, read_at)    -- быстрый счётчик непрочитанных

notification_preferences          -- вид × канал на пользователя
  id        uuid PK
  user_id   uuid
  type      string                 -- код вида
  channel   string                 -- значение NotificationChannel
  enabled   boolean
  unique (user_id, type, channel)

notification_device_tokens        -- push-токены
  id          uuid PK
  user_id     uuid idx
  token       string               -- FCM registration token
  platform    string               -- enum: ios | android
  created_at  datetime
  updated_at  datetime
  unique (token)
```

`json`-колонки гидрируются отдельным typecast-классом в
`Notifications/Infrastructure/Cycle` (`rules.md:81`: JSON — отдельный typecast,
не общий `ValueObjectCast`). `read_at` — nullable-колонка на non-null VO →
тоже отдельный typecast (null-object).

### Публичный API модуля (для других модулей)

Межмодульная отправка — не через шину, а прямым вызовом контракта (как уже
устроен `OutboxEventStoreContract::add()`): capability встраивается в транзакцию
источника. Шина в этом проекте идёт в паре с `#[Transactional]`+`run()`, то есть
дала бы отдельную транзакцию и разорвала атомарность с бизнес-изменением источника.

```text
Notifications/Application/Contract/
  NotificationSenderContract
    send(UserId $recipient, NotificationType $type): void
      -- только стейджит инбокс-строку и outbox-события в текущий unit of work;
         свой run() НЕ вызывает — flush делает транзакция источника
  NotificationTypeRegistryContract
    register(NotificationType ...)   -- в бутлоадере модуля-источника

Notifications/Application/<...>/NotificationSender   -- реализация контракта (Application,
  потому что это бизнес-оркестрация: настройки + Entity + outbox, не технический сервис)
```

Источник внутри своего `#[Transactional]`-Handler-а вызывает
`NotificationSenderContract::send(...)` и затем единый `EntityManager::run()`.
Контракт даёт две вещи: соответствие принятой в проекте привычке `*Contract` для
межграничных capability и возможность подменить отправку стабом в юнит-тестах
модуля-источника (`rules.md:69`).

Собственные use-case модуля (с HTTP-входом) остаются на CQRS+шине:

```text
Notifications/Application/Command/  MarkRead, MarkAllRead, UpdatePreferences,
                                    RegisterDeviceToken, RemoveDeviceToken
Notifications/Application/Query/    ListNotifications, GetUnreadCount, GetPreferences
```

### HTTP API (для мобильного клиента)

```text
GET    /api/v1/notifications                 -- список, cursor-пагинация, перевод по Accept-Language
GET    /api/v1/notifications/unread-count    -- число непрочитанных
POST   /api/v1/notifications/{id}/read       -- отметить одно прочитанным
POST   /api/v1/notifications/read-all        -- отметить все прочитанными
GET    /api/v1/notification-preferences      -- матрица видов × каналов (значения + дефолты)
PUT    /api/v1/notification-preferences      -- обновить переключатели
POST   /api/v1/notification-device-tokens    -- зарегистрировать push-токен
DELETE /api/v1/notification-device-tokens    -- удалить push-токен (logout)
```

Каждый роут — интеграционный тест (`rules.md:105`), контроллеры тонкие, ответы —
типизированные Response/Resource (`rules.md:57-65`).

### Инфраструктура доставки

**Centrifugo** (свой HTTP-клиент, `rules.md:85`): `CentrifugoClient` +
`CentrifugoServiceContract` в `Notifications/Infrastructure/Centrifugo`. Публикация
— POST `/api/publish` с заголовком `X-API-Key` (Centrifugo v6, см. версии).
Канал на пользователя, например `personal:#user_<recipientId>`. Конфиг —
`app/config/centrifugo.php` + typed `CentrifugoConfig` (url, api_key);
`env()` только в конфиге (`rules.md:49`), typed config обязателен (`rules.md:50`).
В docker Centrifugo уже поднят (`docker/docker-compose.dev.yml`,
`docker/centrifugo/config.json`: `http_api.key = dev-centrifugo-api-key`).

**Push** (`kreait/firebase-php`): `FcmPushSenderContract` (Application/Contract) +
реализация `KreaitFcmPushSender` (Infrastructure). Конфиг —
`app/config/push.php` + typed `PushConfig` (путь/JSON service-account, projectId).
Job классифицирует ошибки: временные (5xx/таймаут) → `RetryException`
(как `ProcessMediaJob`), постоянные (битый токен) → удалить токен, терминально.

**Jobs** регистрируются в `app/config/queue.php` (`handlers` + `serializers` →
`OutboxQueueSerializer`) и в бутлоадере модуля через `OutboxJobRegistryContract`,
как `ProcessMediaJob`.

### Структура модуля

```text
Modules/Notifications/
  Domain/        Entity (Notification, NotificationPreference, NotificationDeviceToken),
                 Enum (NotificationChannel, DevicePlatform), ValueObject, Collection
  Application/   NotificationSender (реализация NotificationSenderContract — межмодульная отправка),
                 Command (MarkRead, MarkAllRead, UpdatePreferences, RegisterDeviceToken, RemoveDeviceToken),
                 Query (ListNotifications, GetUnreadCount, GetPreferences),
                 Contract (NotificationSenderContract, NotificationTypeRegistryContract,
                           FcmPushSenderContract, CentrifugoServiceContract, NotificationType),
                 Message (NotificationPushRequested, NotificationRealtimeRequested)
  Repository/    NotificationRepository, NotificationPreferenceRepository,
                 NotificationDeviceTokenRepository
  Infrastructure/ Bootloader, Cycle (typecast json/read_at), Centrifugo, Push, Registry
  Presentation/  Http (Controller, Filter, Resource), Job (SendPushNotificationJob,
                 PublishRealtimeNotificationJob)
```

### Версии и источники

| ПО / пакет | Версия | Проверка | Назначение |
|---|---|---|---|
| kreait/firebase-php | 8.2.0 (2026-03-04), требует PHP ~8.3/8.4/8.5 | Packagist (web, 2026-06-13) | отправка FCM (HTTP v1, iOS+Android) |
| Centrifugo server | v6.7.2 | `docker/docker-compose.dev.yml` | realtime, уже в стенде |
| Centrifugo HTTP API | POST `/api/publish`, заголовок `X-API-Key` | docs Centrifugo v6 (web, 2026-06-13) | публикация realtime |

Риск версий: `kreait/firebase-php` тянет `google/auth` + guzzle (тяжёлая
зависимость). Принимаем: FCM HTTP v1 требует OAuth2-подписи service-account, свой
HTTP-клиент здесь дал бы много низкоуровневого кода ради подписи токенов;
библиотека — де-факто стандарт и официально поддерживает PHP 8.5. Для Centrifugo
наоборот — свой клиент (простой POST + заголовок), как требует `rules.md:85`.

## Ответы на вопросы

| Вопрос | Ответ пользователя | Как учтено |
|---|---|---|
| Через какой сервис слать push? | FCM (`kreait/firebase-php`) | `FcmPushSenderContract` + `KreaitFcmPushSender`, конфиг `push.php`, версия 8.2.0 |
| Как формируется текст и перевод? | «Язык даст модуль User, он на подходе» | Храним ключ перевода + params + data; in-app переводится по `Accept-Language`, push — по языку из `User/Application` (fallback `LocaleConfig.default`) |
| Сколько получателей за вызов? | Один получатель за вызов | `NotificationSenderContract::send(recipientId, ...)`, групповые случаи источник разворачивает в цикл |
| Как добавлять виды уведомлений? | Реестр типизированных видов | `NotificationType` + `NotificationTypeRegistryContract`, регистрация в бутлоадере модуля-источника, по образцу `OutboxJobRegistry` |
| Межмодульная отправка: сервис или шина? (обсуждение) | Сервис вместо `CommandBus` | `NotificationSenderContract::send()` прямым вызовом, по образцу `OutboxEventStoreContract::add()`; встраивается в транзакцию источника, шина оставлена для HTTP-сценариев модуля |

Принято мной (без отдельного вопроса, как следствие выбранного направления и
правил проекта):

- Канал `database` — синхронная строка инбокса в транзакции источника; push и
  realtime — через существующий outbox.
- Каналы — enum внутри модуля + привязка доставки; расширение канала = case + Job.
- Настройки — матрица «вид × канал», дефолты из вида; mute конкретного источника
  (per-source) вынесен за рамки.
- Push-токены устройств — таблица + API внутри модуля уведомлений.

## Итог

Делаем модуль `Notifications` как модульный монолит-модуль с публичным
Application API. Дальше на уровне подхода:

1. Межмодульная отправка — **через `NotificationSenderContract::send()`** прямым
   вызовом (как `OutboxEventStoreContract::add()`), встраивается в транзакцию
   источника; шина остаётся для HTTP-сценариев модуля. Доставка push/realtime —
   через **существующий outbox** (`NotificationPushRequested`,
   `NotificationRealtimeRequested` → Jobs), канал `database` — синхронная строка в
   транзакции источника; всё атомарно одним `run()` источника.
2. Виды уведомлений — **реестр типизированных классов** `NotificationType`,
   которые регистрируют модули-источники; модуль уведомлений при добавлении вида
   не меняется.
3. Каналы — **enum** `NotificationChannel` + привязка механизма доставки;
   расширение канала локально внутри модуля.
4. Текст — **ключ перевода + params + data**; in-app переводится по `Accept-Language`,
   push — по языку из будущего `User/Application` (пока fallback на дефолтную локаль).
5. Три таблицы (`notifications`, `notification_preferences`,
   `notification_device_tokens`), получатель — `UserId` без FK.
6. Centrifugo — **свой HTTP-клиент** (`/api/publish` + `X-API-Key`), push —
   **`kreait/firebase-php` 8.2.0**; оба сервиса конфигурируются typed-config.

Следующий шаг — `eda-plan` по этому отчёту: пошаговый план (домен, миграции,
typecast, Application/Repository, Jobs+outbox-регистрация, HTTP, конфиги,
интеграционные тесты на роуты).
