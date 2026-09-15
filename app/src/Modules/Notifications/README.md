# Модуль Notifications

`Notifications` — это ядро доставки уведомлений пользователю. Он умеет три вещи:

- **inbox** (канал `database`) — список уведомлений внутри приложения, который читает
  мобильный клиент;
- **push** (канал `push`) — пуш на устройство через FCM (Firebase Cloud Messaging);
- **realtime** (канал `realtime`) — мгновенная доставка в открытое приложение через
  Centrifugo.

Плюс к этому модуль хранит **push-токены устройств** и **персональные настройки** пользователя
(что и по какому каналу ему присылать).

Ключевая идея: **ядро не знает про конкретные виды уведомлений**. Оно не знает, что такое
«новое сообщение в чате» или «новый подписчик». Виды регистрируют **модули-источники**
(чат, лента, подписки и т.д.), а ядро только хранит их, решает каналы доставки и доставляет
уже готовый текст. Текст уведомления модуль-источник присылает **уже переведённым** на язык
получателя — `Notifications` его не переводит.

## С чего начать

Если ты пишешь модуль-источник и хочешь слать уведомления — тебе нужны два шага:

1. Зарегистрировать вид уведомления (`NotificationTypeDefinition`) в bootloader-е своего модуля.
2. Вызвать `NotificationSenderContract::send()` из своего `#[Transactional]`-Handler-а.

Всё остальное (каналы, очереди, FCM, Centrifugo, inbox, настройки) ядро делает само.
Подробности — в разделе [«Как слать уведомления из своего модуля»](#как-слать-уведомления-из-своего-модуля).

Если ты делаешь клиент (мобильное приложение) — тебе нужен раздел [«HTTP API»](#http-api).

## Как работает поток

Отправка уведомления **полностью асинхронная** и идёт через Outbox. Прямых вызовов FCM или
Centrifugo из бизнес-кода нет.

```text
1. Handler модуля-источника меняет свои данные и вызывает NotificationSender::send().
2. send() проверяет, что вид зарегистрирован (fail-fast), и кладёт одно событие
   NotificationRequested в outbox. Свой run() он НЕ вызывает.
3. Handler-источник делает свой run() — бизнес-данные и outbox-событие
   коммитятся в одной транзакции.
4. outbox:relay после commit ставит DispatchNotificationJob в очередь.
5. DispatchNotificationJob -> DispatchNotificationCommand: решает, по каким каналам слать.
   - канал database включён -> создаёт строку в inbox (notifications);
   - канал push включён     -> кладёт NotificationPushRequested в outbox;
   - канал realtime включён -> кладёт NotificationRealtimeRequested в outbox.
6. outbox:relay ставит SendPushNotificationJob и/или PublishRealtimeNotificationJob.
7. SendPushNotificationJob      -> FCM multicast на все токены пользователя.
   PublishRealtimeNotificationJob -> публикация в личный канал Centrifugo.
```

То есть на одно `send()` приходится:

- один шаг рассылки (решение каналов + запись в inbox);
- и до двух отдельных шагов доставки (push и realtime), каждый — своя outbox-задача со
  своими повторами.

Почему так: тяжёлую работу (опрос настроек, обращения к FCM/Centrifugo) нельзя делать прямо в
транзакции источника. Рассылка и каждая внешняя доставка — это отдельные, независимо повторяемые
шаги. Если упадёт push, realtime и inbox не пострадают, и наоборот.

## Два контракта для модулей-источников

Модуль-источник работает только с `Application`-слоем `Notifications`, не с `Repository`,
`Infrastructure` или таблицами.

### `NotificationSenderContract` — точка отправки

```php
public function send(UserId $recipient, NotificationContent $content): void;
```

Вызывается **на каждого получателя**. Внутри: проверяет вид по реестру и стейджит одно
`NotificationRequested` в outbox. Свой `EntityManager::run()` не делает — flush выполняет
Handler источника.

### `NotificationTypeDefinition` — описание вида

```php
public function code(): NotificationTypeCode;            // код вида, формат module.action
public function defaultChannels(): NotificationChannelDefaults;  // каналы по умолчанию
```

Регистрируется один раз в bootloader-е модуля-источника. Задаёт код вида и набор каналов,
которые включены, пока у пользователя нет персональной настройки по этому каналу.

## Как слать уведомления из своего модуля

### Шаг 1. Описать вид и зарегистрировать его в bootloader-е

Код вида — строка формата `module.action` (например, `chat.message_received`). Только нижний
регистр, цифры и `_`, хотя бы одна точка-разделитель.

Описать вид можно двумя способами — ядру всё равно, оба реализуют один и тот же контракт
`NotificationTypeDefinition`. Выбирай по числу видов в модуле.

#### Вариант A — отдельный класс на каждый вид

Прямолинейно, когда видов мало или у каждого своя логика.

```php
<?php

declare(strict_types=1);

namespace App\Modules\Chat\Infrastructure\Notification;

use App\Modules\Notifications\Application\Contract\NotificationTypeDefinition;
use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Modules\Notifications\Domain\ValueObject\NotificationChannelDefaults;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;

final readonly class MessageReceivedNotificationType implements NotificationTypeDefinition
{
    #[\Override]
    public function code(): NotificationTypeCode
    {
        return NotificationTypeCode::fromString('chat.message_received');
    }

    #[\Override]
    public function defaultChannels(): NotificationChannelDefaults
    {
        // По умолчанию: в inbox, push и realtime. Любой из каналов пользователь
        // потом сможет выключить через настройки.
        return NotificationChannelDefaults::of(
            NotificationChannel::Database,
            NotificationChannel::Push,
            NotificationChannel::Realtime,
        );
    }
}
```

Регистрация — в bootloader-е твоего модуля, через `NotificationTypeRegistryContract`:

```php
final class ChatBootloader extends Bootloader
{
    public function boot(NotificationTypeRegistryContract $typeRegistry): void
    {
        $typeRegistry->register(new MessageReceivedNotificationType());
    }
}
```

#### Вариант B — один enum на все виды модуля

Компактнее, когда видов несколько: все коды и их каналы лежат в одном файле, регистрируются одной
строкой. Enum реализует тот же интерфейс `NotificationTypeDefinition`.

```php
<?php

declare(strict_types=1);

namespace App\Modules\Chat\Application\Notification;

use App\Modules\Notifications\Application\Contract\NotificationTypeDefinition;
use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Modules\Notifications\Domain\ValueObject\NotificationChannelDefaults;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;

enum ChatNotificationType: string implements NotificationTypeDefinition
{
    case MessageReceived = 'chat.message_received';
    case AddedToChat     = 'chat.added_to_chat';

    #[\Override]
    public function code(): NotificationTypeCode
    {
        return NotificationTypeCode::fromString($this->value);
    }

    #[\Override]
    public function defaultChannels(): NotificationChannelDefaults
    {
        // Исчерпывающий match без default: добавишь новый case — статанализ заставит
        // тут же указать его каналы, забыть нельзя.
        return match ($this) {
            self::MessageReceived => NotificationChannelDefaults::of(
                NotificationChannel::Database,
                NotificationChannel::Push,
                NotificationChannel::Realtime,
            ),
            self::AddedToChat => NotificationChannelDefaults::of(
                NotificationChannel::Database,
                NotificationChannel::Push,
            ),
        };
    }
}
```

Регистрация — все виды разом через `cases()`:

```php
final class ChatBootloader extends Bootloader
{
    public function boot(NotificationTypeRegistryContract $typeRegistry): void
    {
        $typeRegistry->register(...ChatNotificationType::cases());
    }
}
```

Про слои: класс из варианта A нужен только в bootloader-е, поэтому лежит в `Infrastructure/Notification`.
Enum из варианта B использует ещё и Handler отправки (Application-слой), поэтому его место —
в `Application/Notification`, чтобы Application не зависел от Infrastructure.

Реестр — синглтон и накапливает регистрации всех модулей. Повторная регистрация того же кода
вида — ошибка (`NotificationTypeRegistryException`), поэтому код вида должен быть уникальным.

### Шаг 2. Вызвать `send()` из своего Handler-а

`send()` нужно вызывать внутри `#[Transactional]`-Handler-а **до** своего `run()`. Тогда
бизнес-данные и outbox-событие уведомления коммитятся атомарно.

```php
final readonly class SendMessageHandler
{
    public function __construct(
        private MessageRepository $messageRepository,
        private NotificationSenderContract $notificationSender,
        private EntityManagerInterface $entityManager,
    ) {}

    #[Transactional]
    public function handle(SendMessageCommand $command): void
    {
        $message = Message::create(/* ... */);
        $this->entityManager->persist($message);

        // Текст уже на языке получателя — Notifications его не переводит.
        $this->notificationSender->send(
            recipient: $recipientId,
            content: new NotificationContent(
                // type — это само определение вида (его же зарегистрировали в Шаге 1), а не строка-код.
                // Магической строки на месте отправки нет, код вида ядро возьмёт из определения само.
                type: new MessageReceivedNotificationType(), // вариант A (класс)
                // либо с enum из варианта B:
                // type: ChatNotificationType::MessageReceived,
                title: NotificationTitle::fromString($title),
                body: NotificationBody::fromString($body),
                action: NotificationAction::linkTo(actionType: 'chat', actionId: $chatId),
                // либо NotificationAction::none(), если перехода нет
                // снимок автора: id + имя + id медиа-аватара (клиент покажет аватар без запроса к профилю)
                actor: NotificationActor::of(userId: $senderId, name: $senderName, avatarMediaId: $senderAvatarMediaId),
                // либо NotificationActor::none(), если автора нет (системное уведомление)
            ),
        );

        $this->entityManager->run(); // один flush на бизнес-данные + событие уведомления
    }
}
```

Важно:

- **`type` — это само определение вида, а не строка-код.** В `NotificationContent` передаётся
  зарегистрированное `NotificationTypeDefinition` (тот же объект, что в Шаге 1). На месте отправки нет
  магической строки `'chat.message_received'` — код вида ядро берёт из определения (`code()`) само.
- **Текст готовый и переведённый.** `title`/`body` хранятся и доставляются как есть. Локализацию
  делает источник.
- **`action`** — это deep-link (куда вести по тапу): пара `actionType` + `actionId`
  (например, `chat` + id чата) либо `NotificationAction::none()`. Промежуточных состояний нет —
  заданы либо обе части, либо ни одной.
- **`actor`** — от кого пришло уведомление: `NotificationActor::of(userId: ..., name: ..., avatarMediaId: ...)`
  инициатора (например, того, кто подписался) либо `NotificationActor::none()` для системного
  уведомления. Ядро хранит **снимок** автора — `userId`, имя и **id медиа-аватара** (не готовую
  ссылку), — а полный `MediaView` (оригинал + конверсии) собирается на чтении через модуль Media.
  Поэтому клиент показывает аватар автора **без отдельного запроса к профилю**, а ссылка всегда
  валидна (у private-медиа presigned-ссылки временные — замороженная протухла бы). Аватар опционален:
  `avatarMediaId` может быть `null`, если у автора его нет (клиент подставит заглушку сам). Снимок
  фиксирует, **какое** медиа было аватаром на момент отправки; сами ссылки берутся актуальными на чтении.
- **Один получатель — один вызов.** Если адресатов несколько, вызови `send()` на каждого.
- **Свой `run()` для уведомления не нужен** — его делает твой Handler одним общим `run()`.

## Каналы и настройки

Каналов три (`NotificationChannel`):

```text
database - строка в inbox (in-app список);
push     - пуш через FCM;
realtime - публикация в Centrifugo.
```

Для каждой пары «вид × канал» решение «слать или нет» принимается так:

```text
есть персональная настройка пользователя -> берём её;
нет настройки                            -> берём defaultChannels() из описания вида.
```

Матрицу настроек (все зарегистрированные виды × все каналы, наложенные на персональные строки)
строит `NotificationSettingsViewFactory`. Она же отдаётся клиенту на экран настроек: для каждой
ячейки видно текущее значение `enabled` и значение по умолчанию `default`.

## HTTP API

Все маршруты в группе `api`, ожидают аутентифицированного пользователя (`authUserId`
подставляется в фильтры). Получатель всегда сам пользователь — чужие уведомления недоступны.

| Метод и путь | Назначение |
|---|---|
| `GET /api/v1/notifications` | Список уведомлений (inbox), курсорная пагинация |
| `GET /api/v1/notifications/unread-count` | Число непрочитанных |
| `POST /api/v1/notifications/<id>/read` | Отметить одно прочитанным |
| `POST /api/v1/notifications/read-all` | Отметить все прочитанными |
| `POST /api/v1/notification-device-tokens` | Зарегистрировать push-токен |
| `DELETE /api/v1/notification-device-tokens` | Удалить push-токен |
| `GET /api/v1/notification-settings` | Получить матрицу настроек |
| `PUT /api/v1/notification-settings` | Обновить настройки |

### Список уведомлений

`GET /api/v1/notifications?cursor=<uuid>&limit=20`

- `cursor` — id последней отданной строки (UUID v7), необязателен;
- `limit` — 1..100, по умолчанию 20.

Элемент ответа (`NotificationResource`):

```json
{
  "id": "...",
  "type": "chat.message_received",
  "title": "...",
  "body": "...",
  "action": { "actionType": "chat", "actionId": "..." },
  "actor": {
    "id": "0190f3b1-0000-7000-8000-000000000000",
    "name": "Иван Петров",
    "avatar": {
      "id": "0190f3b1-1111-7000-8000-000000000000",
      "position": null,
      "original": { "url": "https://cdn.example/avatars/ivan.jpg", "expiresAt": null },
      "conversions": []
    }
  },
  "read": false,
  "createdAt": "2026-06-15T12:00:00+00:00"
}
```

`action` равен `null`, если перехода нет. `actor` — снимок автора-инициатора (например,
пользователя, который подписался): `id` (его `userId`), `name` и `avatar` — общий `MediaView`
(оригинал + конверсии) либо `null`, если у автора нет аватара или его медиа недоступно. Аватар
собирается на чтении из id медиа-снимка, поэтому это та же форма, что у аватара в профиле, и клиент
показывает автора **без отдельного запроса к профилю**. `actor` равен `null`, если у уведомления нет
автора (системное уведомление).

### Счётчик и отметки о прочтении

- `unread-count` -> `{ "count": <int> }`.
- `read` -> отмечает уведомление прочитанным и возвращает его же ресурс. Повторная отметка
  уже прочитанного — безопасный no-op.
- `read-all` -> отмечает все прочитанными и возвращает `{ "count": 0 }`. **Нюанс:** ноль здесь
  договорный — он не перезапрашивает счётчик. Если ровно в этот момент придёт новое уведомление,
  клиент актуализирует счётчик ближайшим `unread-count`.

### Push-токены устройств

`POST /api/v1/notification-device-tokens` с телом `{ "token": "...", "platform": "ios|android" }`.

- Токен уникален. Если он уже есть в базе — у него **переустанавливается владелец и платформа**
  (устройство сменило аккаунт), новая строка не создаётся.
- `platform` — только `ios` или `android`; иначе 422 (`app.notifications.unknown_platform`).

`DELETE /api/v1/notification-device-tokens` с телом `{ "token": "..." }` — удаление токена
(например, при выходе из аккаунта).

### Настройки

`GET /api/v1/notification-settings` -> коллекция строк (`NotificationSettingResource`):

```json
{ "type": "chat.message_received", "channel": "push", "enabled": true, "default": true }
```

`PUT /api/v1/notification-settings` с телом:

```json
{
  "settings": [
    { "type": "chat.message_received", "channel": "push", "enabled": false }
  ]
}
```

- Семантика toggle: отсутствие `enabled` = выключено.
- Неизвестный вид (нет в реестре) или неизвестный канал -> 422
  (`app.notifications.unknown_type` / `app.notifications.unknown_channel`).
- Не более 300 пунктов в одном запросе (≈ 100 видов × 3 канала) — защита от раздутого тела.
- В ответ возвращается **полная матрица** настроек после обновления, а не только изменённые
  пункты.

## Подключение и эксплуатация

### Bootloader

`NotificationsBootloader` уже зарегистрирован в `App\Shared\Infrastructure\Spiral\Kernel`
**после** Outbox-бутлоадеров (его `boot()` регистрирует пары «сообщение → Job» через
`OutboxJobRegistryContract`). Он биндит:

- `NotificationSenderContract`, `NotificationTypeRegistryContract` (реестр — синглтон);
- `CentrifugoServiceContract`, `FcmPushSenderContract` и ленивые фабрики HTTP-клиента
  Centrifugo и FCM `Messaging`.

FCM `Messaging` и HTTP-клиент Centrifugo создаются **лениво** — реальный service-account FCM
нужен только при фактической отправке, не на старте приложения и не в тестах с дублёрами.

### Миграция

```text
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php
```

Создаёт три таблицы: `notifications` (inbox), `notification_settings`, `notification_device_tokens`.
На новом окружении применить обычной проектной командой миграций.

Ключевые индексы:

- `notifications.outbox_id` — **unique**, это ключ идемпотентности рассылки;
- `notifications (user_id, id)` и `(user_id, read_at)` — под список и счётчик;
- `notification_settings (user_id, type, channel)` — **unique** (upsert настроек);
- `notification_device_tokens.token` — **unique** (переустановка владельца).

### Переменные окружения

```dotenv
# FCM (push). Service-account JSON хранится вне репозитория.
FCM_PROJECT_ID=
FCM_CREDENTIALS_FILE=

# Centrifugo (realtime).
CENTRIFUGO_API_URL=http://centrifugo:8000/api
CENTRIFUGO_API_KEY=
```

`FCM_CREDENTIALS_FILE` — путь к JSON service-account для аутентификации в FCM.
`CENTRIFUGO_API_URL` — базовый URL HTTP API Centrifugo **без** хвостового `/publish`.

### Очередь

Три Job модуля зарегистрированы в `app/config/queue.php` — каждая в `registry.handlers` и
в `registry.serializers` с `OutboxQueueSerializer` (в очередь идёт короткий envelope, сам
payload лежит в `outbox_events`):

```text
DispatchNotificationJob
SendPushNotificationJob
PublishRealtimeNotificationJob
```

Для работы доставки нужен запущенный `outbox:relay` (один экземпляр на окружение) и consumer
очереди. Подробности про relay — в README модуля Outbox.

## Важные нюансы

- **Идемпотентность рассылки — по `outbox_id`.** Если `DispatchNotificationJob` выполнится
  повторно (ретрай очереди), он увидит уже созданную строку inbox с тем же `outbox_id` и станет
  полным no-op. Поэтому повтор не задваивает ни inbox, ни push, ни realtime.

- **Fail-fast на незарегистрированный вид.** `send()` бросит исключение **сразу**, до записи
  события, если вид не зарегистрирован в реестре. Сначала регистрируй `NotificationTypeDefinition`,
  потом шли.

- **Текст не переводится ядром.** `title`/`body` хранятся и доставляются как пришли. Локализация —
  ответственность модуля-источника. Файлы `app/locale/{ru,en}/notifications.php` содержат только
  тексты ошибок самого модуля, не тела уведомлений.

- **Транзакционная дисциплина.** `send()` не делает `run()`. Это осознанно: бизнес-данные источника
  и событие уведомления должны попасть в БД одной транзакцией. Если вызвать `send()` вне
  `#[Transactional]`-Handler-а и забыть `run()`, событие не сохранится.

- **Push не в транзакции.** `SendPushNotificationHandler` намеренно без `#[Transactional]`: внешний
  вызов FCM нельзя оборачивать в транзакцию БД. Токены, признанные FCM невалидными
  (`UNREGISTERED`/`INVALID_ARGUMENT`), **автоматически удаляются** отдельным `run()`. Временный
  сбой FCM переводится в `RetryException` — outbox повторит доставку.

- **Push без токенов — тихо ничего не делает.** Если у пользователя нет зарегистрированных токенов,
  push-шаг просто завершается без ошибки.

- **Данные в push.** Через FCM `data` уезжает переход (`actionType` и `actionId`, если есть) и снимок
  автора (`actorId`, `actorName`, а `actorAvatarUrl` — только если у автора есть аватар) — FCM `data`
  плоская строковая карта, поэтому снимок раскладывается по отдельным полям, а аватар остаётся **одной
  ссылкой** (не полным `MediaView`). Ссылка разрешается из id медиа-снимка к моменту отправки. Заголовок
  и тело идут в стандартный FCM `notification`.

- **Realtime-канал.** Публикация идёт в персональный канал `personal:#user_{userId}`. Payload —
  camelCase JSON: `type`, `title`, `body`, `action` (или `null`), `actor` (объект
  `{id, name, avatar}`, где `avatar` — тот же `MediaView` `{id, position, original, conversions}`, что
  в HTTP-ответе инбокса, либо `null`) и `createdAt` (ISO-8601). Аватар разрешается из id медиа-снимка к
  моменту публикации.

- **Классификация ошибок в Job.** Доменная ошибка (неизвестный вид, битый payload) терминальна —
  событие уходит в `failed`, повтор не назначается. Инфраструктурный сбой (БД, сеть) временный —
  `RetryException`, событие повторяется. Это разделение важно: не стоит «чинить» терминальные
  ошибки повторами.

- **Примеров видов в коде пока нет.** На момент написания ни один модуль ещё не регистрирует
  `NotificationTypeDefinition` — первым это сделает модуль-источник (чат и т.п.). Образец
  регистрации — выше в этом README.

## Проверка после изменений

```bash
make test
make phpstan
```

После завершения задачи — полный набор:

```bash
make qa
```

Все команды запускаются через Docker, как требуют правила проекта.
