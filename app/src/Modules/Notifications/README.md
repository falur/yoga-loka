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

1. Зарегистрировать вид уведомления (`NotificationTypeDefinition`) в bootloader-е своего модуля
   через `NotificationTypeRegistryContract`.
2. Вызвать `NotificationContract::send()` из своего `#[Transactional]`-Handler-а.

Все три типа лежат в `App\Modules\Notifications\Public` — это единственная часть модуля, которую
видят соседи.

Всё остальное (каналы, очереди, FCM, Centrifugo, inbox, настройки) ядро делает само.
Подробности — в разделе [«Как слать уведомления из своего модуля»](#как-слать-уведомления-из-своего-модуля).

Если ты делаешь клиент (мобильное приложение) — тебе нужен раздел [«HTTP API»](#http-api).

## Как работает поток

Отправка уведомления **полностью асинхронная** и идёт через Outbox. Прямых вызовов FCM или
Centrifugo из бизнес-кода нет.

```text
1. Handler модуля-источника меняет свои данные и вызывает NotificationContract::send().
2. Сценарий RequestNotification проверяет, что вид зарегистрирован (fail-fast), и пишет одно
   событие NotificationRequestedEvent в outbox сразу, в уже открытую транзакцию источника.
3. Handler-источник сохраняет свои данные и завершает транзакцию — бизнес-данные и
   outbox-событие коммитятся в одной транзакции.
4. outbox:relay после commit ставит DispatchNotificationJob в очередь.
5. DispatchNotificationJob -> DispatchNotificationCommand: решает, по каким каналам слать.
   - канал database включён -> создаёт строку в inbox (notifications);
   - канал push включён     -> кладёт NotificationPushRequestedEvent в outbox;
   - канал realtime включён -> кладёт NotificationRealtimeRequestedEvent в outbox.
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

## Публичные контракты для модулей-источников

Модуль-источник работает только с `Public`-слоем `Notifications`, не с его `Application`,
`Domain`, `Repository`, `Infrastructure` или таблицами. В сигнатурах — только строки, публичные
enum и публичные DTO: доменные объекты-значения через границу не ходят.

### `NotificationContract` — точка отправки

```php
public function send(string $recipientUserId, NotificationContentDto $content): void;
```

Вызывается **на каждого получателя**. Внутри: проверяет вид по реестру и пишет одно
`NotificationRequestedEvent` в outbox. Строка события вставляется сразу, в уже открытую транзакцию
источника, и прогона `EntityManager` не ждёт. Вызов вне транзакции источника не упадёт: у сценария
отправки свой `#[Transactional]`, поэтому событие закрепится его собственной транзакцией — отдельно
от бизнес-данных вызывающего.

Содержимое (`NotificationContentDto`) — примитивы и публичные DTO:

```php
new NotificationContentDto(
    typeCode: 'chat.message_received',          // код вида, тот же, что вернуло определение
    title: $title,                              // уже на языке получателя
    body: $body,                                // уже на языке получателя
    action: new NotificationActionDto(actionType: 'chat', actionId: $chatId), // либо null
    actor: new NotificationActorDto(id: $senderId, name: $senderName, avatarMediaId: $avatarId), // либо null
);
```

### `NotificationTypeDefinition` — описание вида

```php
public function code(): string;                                  // код вида, формат module.action
public function defaultChannels(): NotificationChannelCollection;  // каналы по умолчанию
```

Регистрируется один раз в bootloader-е модуля-источника. Задаёт код вида и набор каналов,
которые включены, пока у пользователя нет персональной настройки по этому каналу. Каналы —
публичный enum `NotificationChannel` (`database`, `push`, `realtime`).

### `NotificationTypeRegistryContract` — регистрация видов

```php
public function register(NotificationTypeDefinition ...$definitions): void;
```

Реестр — синглтон, накапливающий регистрации всех модулей. Чтение реестра соседям не публикуется:
виды читает только само ядро. Повторная регистрация того же кода вида — ошибка.

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

use App\Modules\Notifications\Public\Contract\NotificationTypeDefinition;
use App\Modules\Notifications\Public\Dto\NotificationChannelCollection;
use App\Modules\Notifications\Public\Enum\NotificationChannel;

final readonly class MessageReceivedNotificationType implements NotificationTypeDefinition
{
    #[\Override]
    public function code(): string
    {
        return 'chat.message_received';
    }

    #[\Override]
    public function defaultChannels(): NotificationChannelCollection
    {
        // По умолчанию: в inbox, push и realtime. Любой из каналов пользователь
        // потом сможет выключить через настройки.
        return NotificationChannelCollection::of(
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

use App\Modules\Notifications\Public\Contract\NotificationTypeDefinition;
use App\Modules\Notifications\Public\Dto\NotificationChannelCollection;
use App\Modules\Notifications\Public\Enum\NotificationChannel;

enum ChatNotificationType: string implements NotificationTypeDefinition
{
    case MessageReceived = 'chat.message_received';
    case AddedToChat     = 'chat.added_to_chat';

    #[\Override]
    public function code(): string
    {
        return $this->value;
    }

    #[\Override]
    public function defaultChannels(): NotificationChannelCollection
    {
        // Исчерпывающий match без default: добавишь новый case — статанализ заставит
        // тут же указать его каналы, забыть нельзя.
        return match ($this) {
            self::MessageReceived => NotificationChannelCollection::of(
                NotificationChannel::Database,
                NotificationChannel::Push,
                NotificationChannel::Realtime,
            ),
            self::AddedToChat => NotificationChannelCollection::of(
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
Enum из варианта B использует ещё и сборка содержимого (Application-слой), поэтому его место —
в `Application/Notification`, чтобы Application не зависел от Infrastructure.

Реестр — синглтон и накапливает регистрации всех модулей. Повторная регистрация того же кода
вида — ошибка (`NotificationTypeRegistryException`), поэтому код вида должен быть уникальным.

### Шаг 2. Вызвать `send()` из своего Handler-а

`send()` нужно вызывать внутри `#[Transactional]`-Handler-а. Тогда бизнес-данные и outbox-событие
уведомления коммитятся атомарно: строка события вставляется в ту же транзакцию. `EntityManager` в
Handler не инъектируется: слой Application про Cycle не знает, хранение скрыто за Domain Repository
модуля.

```php
final readonly class SendMessageHandler
{
    public function __construct(
        private MessageRepository $messageRepository,
        private NotificationContract $notifications,
    ) {}

    #[Transactional]
    public function handle(SendMessageCommand $command): void
    {
        $message = Message::create(/* ... */);

        // Текст уже на языке получателя — Notifications его не переводит.
        $this->notifications->send(
            recipientUserId: $recipientId,
            content: new NotificationContentDto(
                // Код вида берётся у определения, зарегистрированного в Шаге 1, — магической строки
                // на месте отправки нет.
                typeCode: ChatNotificationType::MessageReceived->code(),
                title: $title,
                body: $body,
                action: new NotificationActionDto(actionType: 'chat', actionId: $chatId),
                // либо null, если перехода нет
                // снимок автора: id + имя + id медиа-аватара (клиент покажет аватар без запроса к профилю)
                actor: new NotificationActorDto(id: $senderId, name: $senderName, avatarMediaId: $senderAvatarMediaId),
                // либо null, если автора нет (системное уведомление)
            ),
        );

        // Запись агрегата уносит в базу бизнес-данные; событие уведомления уже вставлено
        // вызовом send() в эту же транзакцию.
        $this->messageRepository->save($message);
    }
}
```

Важно:

- **Код вида берётся у определения.** В `NotificationContentDto` уезжает строка, но писать её
  руками не нужно: её отдаёт `code()` зарегистрированного определения (того же, что в Шаге 1),
  поэтому магической строки `'chat.message_received'` на месте отправки нет. Ядро проверит код по
  реестру до постановки события.
- **Текст готовый и переведённый.** `title`/`body` хранятся и доставляются как есть. Локализацию
  делает источник.
- **`action`** — это deep-link (куда вести по тапу): `NotificationActionDto` с парой `actionType`
  + `actionId` (например, `chat` + id чата) либо `null`. Промежуточных состояний нет — задан либо
  весь DTO, либо `null`.
- **`actor`** — от кого пришло уведомление: `NotificationActorDto` инициатора (например, того, кто
  подписался) либо `null` для системного уведомления. Ядро хранит **снимок** автора — `userId`, имя и **id медиа-аватара** (не готовую
  ссылку), — а полное медиа `MediaDto` (оригинал + конверсии) собирается на чтении через публичный
  контракт `Media\Public\Contract\MediaContract` (пакетно: набор id аватаров на страницу инбокса).
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
строит `NotificationSettingResultCollection::build()`, вызываемый из `GetNotificationSettingsHandler`
и `UpdateNotificationSettingsHandler`. Она же отдаётся клиенту на экран настроек: для каждой
ячейки видно текущее значение `enabled` и значение по умолчанию `default`.

## HTTP API

Все маршруты в группе `api` и объявляют требование действующей сессии публичным атрибутом
`Auth\Public\Attribute\AuthenticatedRoute`: без сессии маршрут отвечает 401 и до контроллера
не доходит, а с сессией `authUserId` подставляется в фильтры. Получатель всегда сам пользователь —
чужие уведомления недоступны, и эту проверку владения делает сценарий модуля, а не правило доступа.

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
пользователя, который подписался): `id` (его `userId`), `name` и `avatar` — публичное медиа
`MediaDto` (оригинал + конверсии) либо `null`, если у автора нет аватара или его медиа недоступно.
Аватар собирается на чтении из id медиа-снимка, поэтому это та же форма, что у аватара в профиле, и клиент
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
(его `boot()` дописывает маршруты трёх своих событий патчем секции конфигурации `outbox`:
ключ — класс события, значение — список `OutboxRoute` с Job, очередью, паузами повторов и
таймаутом доставки). Он биндит:

- три доменных интерфейса хранения корней агрегатов модуля — `NotificationRepository`,
  `NotificationSettingRepository` и `NotificationDeviceTokenRepository` — на свои
  `Cycle*`-реализации из `Infrastructure/Persistence/Cycle/Repository`;
- `MarkAllNotificationsReadContract` — отдельный порт единственной массовой записи проекта
  (отметка всех непрочитанных прочитанными одним `UPDATE`) — на `CycleMarkAllNotificationsRead`;
  порт остаётся отдельным от `NotificationRepository` и репозиторием агрегата не притворяется;
- публичные `NotificationContract` и `NotificationTypeRegistryContract` — на адаптеры из
  `Infrastructure/Spiral/PublicApi`; внутренний `NotificationTypeCatalogContract` — на реестр видов
  (синглтон);
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

Три Job модуля в статическом реестре `app/config/queue.php` не перечисляются: `registry.handlers`
и `registry.serializers` пусты, а типом задачи служит полное имя класса Job из маршрута секции
`outbox` — по нему Spiral и находит обработчик:

```text
DispatchNotificationJob
SendPushNotificationJob
PublishRealtimeNotificationJob
```

Все три маршрута идут в очередь `notifications`. Для работы доставки нужен запущенный
`outbox:relay` (один экземпляр на окружение) и consumer очереди. Команды relay и `outbox:status` —
в `docker/README.md`, устройство обмена — в README пакета `gian-tiaga/spiral-outbox`.

## Важные нюансы

- **Идемпотентность рассылки — по `outbox_id`.** В колонке лежит идентификатор доставки outbox.
  Если `DispatchNotificationJob` выполнится повторно (доставка имеет семантику at-least-once), он
  увидит уже созданную строку inbox с тем же `outbox_id` и станет полным no-op. Поэтому повтор не
  задваивает ни inbox, ни push, ни realtime.

- **Fail-fast на незарегистрированный вид.** `send()` бросит исключение **сразу**, до записи
  события, если вид не зарегистрирован в реестре (проверку делает сценарий RequestNotification). Сначала регистрируй `NotificationTypeDefinition`,
  потом шли.

- **Текст не переводится ядром.** `title`/`body` хранятся и доставляются как пришли. Локализация —
  ответственность модуля-источника. Файлы `app/locale/{ru,en}/notifications.php` содержат только
  тексты ошибок самого модуля, не тела уведомлений.

- **Транзакционная дисциплина.** `send()` не делает `run()` и не должен: строка события
  вставляется сразу, в уже открытую транзакцию источника, поэтому бизнес-данные и событие
  попадают в БД одной транзакцией. Забыть `#[Transactional]` у вызывающего Handler-а ошибкой не
  обернётся: у сценария отправки свой `#[Transactional]`, и событие закоммитится его собственной
  транзакцией — но уже независимо от бизнес-данных источника, поэтому наружу может уйти факт,
  которого в данных нет. Отсюда и требование вызывать `send()` внутри транзакции источника.

- **Push не в транзакции.** `SendPushNotificationHandler` намеренно без `#[Transactional]`: внешний
  вызов FCM нельзя оборачивать в транзакцию БД. Токены, признанные FCM невалидными
  (`UNREGISTERED`/`INVALID_ARGUMENT`), **автоматически удаляются** отдельным `run()`. Временный
  сбой FCM переводится в `RetryableOutboxException` — outbox повторит доставку.

- **Push без токенов — тихо ничего не делает.** Если у пользователя нет зарегистрированных токенов,
  push-шаг просто завершается без ошибки.

- **Данные в push.** Через FCM `data` уезжает переход (`actionType` и `actionId`, если есть) и снимок
  автора (`actorId`, `actorName`, а `actorAvatarUrl` — только если у автора есть аватар) — FCM `data`
  плоская строковая карта, поэтому снимок раскладывается по отдельным полям, а аватар остаётся **одной
  ссылкой** (не полным медиа). Ссылка разрешается из id медиа-снимка к моменту отправки через
  `MediaContract`. Заголовок
  и тело идут в стандартный FCM `notification`.

- **Realtime-канал.** Публикация идёт в персональный канал `personal:#user_{userId}`. Payload —
  camelCase JSON: `type`, `title`, `body`, `action` (или `null`), `actor` (объект
  `{id, name, avatar}`, где `avatar` — та же форма `{id, position, original, conversions}`, что
  в HTTP-ответе инбокса, либо `null`; `position` у аватара всегда `null` — позиция принадлежит записи,
  а не медиа) и `createdAt` (ISO-8601). Аватар разрешается из id медиа-снимка к моменту публикации
  через `MediaContract`.

- **Классификация ошибок в Job.** Доменная ошибка (неизвестный вид, битый payload) терминальна —
  доставка уходит в `failed`, повтор не назначается. Инфраструктурный сбой (БД, сеть) временный —
  `RetryableOutboxException`, доставка повторяется по паузам своего маршрута. Это разделение важно:
  не стоит «чинить» терминальные ошибки повторами.

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
