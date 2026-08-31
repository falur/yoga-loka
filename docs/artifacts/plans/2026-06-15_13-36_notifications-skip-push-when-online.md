---
title: Не отправлять push, если получатель онлайн (Notifications)
date: 2026-06-15 13:36
mode: normal
plan_size: normal
decision_mode: recommend_and_ask
status: reviewed
reviewer: codex
meta_reviewers: [haiku, sonnet, opus]
test_strategy: after_each_phase
logging_strategy: debug_precise
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  research: —
---

# План реализации

## Задача
В модуле `Notifications` push-уведомление не отправляется, если получатель сейчас онлайн (подключён к Centrifugo по своему персональному каналу `personal:#user_{userId}`). Каналы `database` и `realtime` работают как раньше. Проверка онлайн-статуса выполняется через Centrifugo HTTP API `presence_stats` внутри `SendPushNotificationHandler` перед отправкой push.

Готово, когда: онлайн-получатель push не получает (FCM не вызывается), офлайн-получатель получает push как сейчас, сбой проверки presence не блокирует доставку (push отправляется), 100% покрытие тестами, `make test` и `make phpstan` зелёные.

## Контекст
- Поток push: `SendPushNotificationJob` → `SendPushNotificationHandler::handle()` (`app/src/Modules/Notifications/Application/Command/Push/SendPushNotification/SendPushNotificationHandler.php`). Хендлер помечен `#[LogOperation]`, без `#[Transactional]` — внешние вызовы (FCM) транзакцией не оборачиваются, поэтому добавление ещё одного внешнего вызова (presence) согласуется с текущим стилем.
- Текущий `handle()`: грузит токены устройства, при пустом наборе выходит, иначе шлёт через `FcmPushSenderContract::send()` и удаляет невалидные токены.
- Realtime публикуется в канал `personal:#user_{userId}` (`PublishRealtimeNotificationHandler.php:25`, `\sprintf('personal:#user_%s', $command->userId)`). Это тот же канал, по подключению к которому определяется онлайн.
- Низкоуровневый HTTP-клиент Centrifugo: `app/src/Modules/Notifications/Infrastructure/Centrifugo/CentrifugoClient.php`. Реализует только `publish()`: `POST {apiUrl}/publish`, заголовок `Authorization: apikey <key>`, тело `{channel, data}`; 5xx — временный сбой, 4xx — отказ запроса, логический отказ приходит кодом 200 с объектом `error` в теле (метод `ensureNoApiError`, который бросает `CentrifugoPublishException`). `presence_stats` повторяет HTTP-паттерн `publish()`, но со своим типом исключения.
- Внешний контракт Centrifugo v6.7.2 (зафиксирован в `docker/docker-compose.dev.yml:221`), подтверждён по официальной документации: `POST /api/presence_stats`, тело `{"channel":"..."}`, успех `{"result":{"num_clients":N,"num_users":N}}`. `apiUrl` уже содержит `/api` (`http://centrifugo:8000/api`), поэтому финальный URL — `{apiUrl}/presence_stats` = `http://centrifugo:8000/api/presence_stats`.
- Конфиг приложения `app/config/centrifugo.php` (`apiUrl`, `apiKey`) и `CentrifugoConfig` уже содержат всё нужное для запроса; новые параметры приложения не требуются.
- DI: `NotificationsBootloader` биндит контракты модуля и собирает `CentrifugoClient` фабрикой `centrifugoClient()`.

## Предусловия и риски (важно)
- **Канал `personal:#user_{userId}` — user-limited канал Centrifugo** (символ `#` ограничивает подписку конкретным пользователем). Подтверждено официальной документацией: пример namespace `personal` с `presence: true` и `allow_user_limited_channels: true` и канал `personal:#{userId}`. Для подписки нужны обе опции namespace; без `allow_user_limited_channels` сервер отклоняет подписку ошибкой `103: permission denied`. Сейчас в `docker/centrifugo/config.json` у namespace `personal` задан только `allow_subscribe_for_client: true` — план добавляет обе опписи (`presence`, `allow_user_limited_channels`).
- **Presence имеет смысл, только если клиент реально подписан на свой канал.** Для user-limited канала клиент должен подключаться к Centrifugo с JWT, где `sub = user_{userId}`, и подписываться на `personal:#user_{userId}`. В этом репозитории выпуск connection-токена Centrifugo и клиентская подписка **не реализованы** (grep по `app/src`: есть только серверный publish, нет выдачи JWT для подключения клиента). Серверный `publish` работает независимо от подписки, а вот `presence_stats` вернёт `num_clients=0`, пока клиент не подключён и не подписан.
- **Следствие при текущем состоянии репозитория:** до появления клиентской подписки (вероятно, на стороне мобильного приложения + отдельный эндпоинт выдачи токена Centrifugo) presence будет `0`, и из-за выбранного fail-open push будет уходить всегда. Это безопасно (никто не теряет уведомления) и фича автоматически «включится», как только клиентская подписка заработает и серверный конфиг получит обе опции namespace. Реализацию клиентской аутентификации/подписки этот план **не покрывает** — это отдельная задача. Бэкенд-логика подавления push при этом самодостаточна и корректна.
- **Гонка тайминга осознанно игнорируется:** между `presence_stats` и `send` пользователь может отключиться (получит push) или подключиться (push уйдёт зря). Это inherent и приемлемо, fail-open отклоняется в безопасную сторону. Идемпотентность push по `outboxId` (arch.md) не нарушается — guard просто пропускает отправку.
- **Push-only уведомления — принятый сценарий (решение пользователя: подавлять безусловно).** Конкретные типы регистрируются модулями-источниками во внешнем реестре, а пользователь может в настройках оставить только канал push. Если у уведомления нет ни realtime, ни inbox, онлайн-получатель не получит ничего (push подавлен, других каналов нет). Это сознательно принято: подавление безусловное, как просил пользователь. Если в будущем появятся важные push-only типы, к этому решению стоит вернуться.

## Принятые решения
- **Поведение при сбое presence — fail-open** (подтверждено пользователем): если проверку онлайн-статуса выполнить не удалось (Centrifugo недоступен, 5xx/4xx, логический отказ, некорректный ответ), push **отправляется**, сбой пишется в лог уровня WARN. Доставка уведомления важнее подавления. Так как rules запрещают `try-catch` в Handler-ах, перехват сбоя и fallback живут в инфраструктурной реализации проверки, а контракт онлайн-статуса возвращает `bool` и не бросает исключений. Перехватывается ровно `CentrifugoPresenceException` (а не широкий `\Throwable`): `CentrifugoClient::presenceStats` оборачивает все режимы сбоя в этот тип, «неожиданных» путей не остаётся, поэтому catch-all не нужен.
- **Размер плана — normal**, **fail-open**, **тесты after_each_phase**, **логирование debug_precise** — подтверждено пользователем/настройками.
- **Отдельный Application-контракт `OnlinePresenceContract` с методом `isOnline(UserId $userId): bool`**, реализация в Infrastructure. Причина: смешивать «опубликовать в канал» (`CentrifugoServiceContract::publish`) и «онлайн ли пользователь» нельзя — разные обязанности; повторяет существующий паттерн модуля (`FcmPushSenderContract`, `CentrifugoServiceContract`). `bool` как возврат чистого predicate-метода без побочных эффектов правилами разрешён.
- **`SendPushNotificationHandler` не строит канал и не знает формат presence** — передаёт `UserId`, всё знание о канале и Centrifugo остаётся в Infrastructure (arch: Application/Domain не знают про Centrifugo).
- **Формат канала `personal:#user_%s` дублируется** одной строкой `sprintf` в `CentrifugoOnlinePresence` и существующем `PublishRealtimeNotificationHandler`, с комментарием-ссылкой на канонический хендлер. Выносить в общий слой нельзя: канонический источник (Application-хендлер) и потребитель проверки (Infrastructure) в разных слоях, а arch запрещает транспортные концепты Centrifugo в `Domain`; общий разрешённый слой для одной константы отсутствует. Менять контракт `CentrifugoServiceContract::publish` ради дубля из 20 символов — задеть рабочий протестированный realtime-поток, противоречит точечным изменениям. `PublishRealtimeNotificationHandler` не трогаем.
- **Идентичность канала гарантируется инвариантом нормализации userId.** Realtime строит канал из сырого `$command->userId`, presence — из `UserId::fromString($command->userId)->value()`. `userId` сквозь весь поток — это `UserId->value()` (см. `NotificationSender`), а строковое представление UUID v7 всегда в нижнем регистре, поэтому обе строки байт-в-байт совпадают. В коде presence добавляется комментарий про этот инвариант.
- **`presence_stats` определяет онлайн как `num_clients > 0`**: канал персональный, любое активное подключение означает онлайн.
- **DTO результата хранит только `numClients`.** `num_users` из ответа Centrifugo для решения не нужен и в логике не используется — чтобы не плодить мёртвый код (rules «нет мёртвого кода»), `num_users` в DTO не вносим и не валидируем; проверяется только целостность `num_clients`.
- **`CentrifugoClient::presenceStats` не переиспользует `ensureNoApiError()`**: тот бросает `CentrifugoPublishException` (чужой контракт). У presence — свой разбор тела (объект `error` и структура `result`), бросающий `CentrifugoPresenceException`. Метод `ensureNoApiError` и `publish()` не меняются.
- **Слой новых классов:** `OnlinePresenceContract` — `Application/Contract`. Реализация `CentrifugoOnlinePresence`, DTO `CentrifugoPresenceStats` и исключение `CentrifugoPresenceException` — в `Infrastructure`, т.к. они не входят в публичный Application-контракт (контракт отдаёт `bool`, исключение перехватывается внутри реализации). `CentrifugoPresenceException` кладётся в новую папку `Infrastructure/Exception`: по rules слой исключения определяется контрактом, к которому оно относится; presence-исключение относится к инфраструктурному `CentrifugoClient` и наружу в Application не выходит. (Это отличается от `CentrifugoPublishException`, которое лежит в `Application/Exception`, потому что относится к публичному Application-контракту `CentrifugoServiceContract`.)

## Целевой алгоритм
1. Relay запускает `SendPushNotificationJob`, тот отправляет `SendPushNotificationCommand` в `SendPushNotificationHandler::handle()`.
2. Хендлер первым делом спрашивает `OnlinePresenceContract::isOnline(UserId::fromString($command->userId))`.
3. Реализация `CentrifugoOnlinePresence::isOnline()`:
   - строит персональный канал `personal:#user_{userId}` тем же `sprintf`, что и realtime;
   - вызывает `CentrifugoClient::presenceStats($channel)` → `CentrifugoPresenceStats`;
   - возвращает `numClients > 0`;
   - при `CentrifugoPresenceException` (любой сбой запроса/ответа) пишет WARN и возвращает `false` (fail-open → push дойдёт).
4. Порядок в хендлере: сперва грузим токены — если их нет, ранний выход как сейчас (Centrifugo не дёргаем). Затем, перед вызовом FCM, спрашиваем `isOnline`.
5. Если `isOnline() === true`: хендлер пишет DEBUG «получатель онлайн, push пропущен» и выходит, не вызывая FCM. Realtime и database уже отработали в своих ветках — пользователь увидит уведомление в приложении.
6. Если `isOnline() === false`: дальше без изменений — шлём FCM, удаляем невалидные токены.

`CentrifugoClient::presenceStats($channel)`:
- `POST {apiUrl}/presence_stats`, заголовки `Authorization: apikey <key>`, `Content-Type: application/json` (заголовки задаются явно конструктором PSR-7 `Request`, как в `publish()`), тело `{"channel": "<channel>"}`;
- транспортный сбой (`ClientExceptionInterface`) → `CentrifugoPresenceException::transport()`;
- статус ≥ 500 → `::serverError()`; статус ≥ 400 → `::requestRejected()`;
- тело с объектом `error` → `::apiError(code, message)`;
- **битый JSON** (`\JsonException` от `json_decode(... JSON_THROW_ON_ERROR)`) либо отсутствует `result`/`result.num_clients` не целое → `::malformedResponse()`. Критично: разбор тела обёрнут так, что `\JsonException` не утекает мимо `CentrifugoPresenceException` — иначе она пробросилась бы из `isOnline` (там перехватывается только `CentrifugoPresenceException`), Job упал бы и push не ушёл, нарушив fail-open. Обёртка `\JsonException` → `malformedResponse()` оправдана контрактом границы (потребитель полагается на единственный тип исключения для fail-open);
- иначе → `new CentrifugoPresenceStats(numClients)`.

## Контракты реализации

### Данные и БД
Не затрагивается.

### API и внешние контракты
- **Внешний вызов Centrifugo (новый, исходящий):** `POST http://centrifugo:8000/api/presence_stats` (= `{centrifugo.apiUrl}/presence_stats`).
  - Заголовки: `Authorization: apikey {centrifugo.apiKey}`, `Content-Type: application/json`.
  - Тело: `{"channel": "personal:#user_{userId}"}`.
  - Успех (200): `{"result": {"num_clients": int, "num_users": int}}` (читаем только `num_clients`).
  - Сбои: транспортный, HTTP 5xx, HTTP 4xx, объект `error` в теле при 200, отсутствие/невалидность `result.num_clients`.
- **Серверный конфиг Centrifugo (внешний контракт инфраструктуры):** namespace `personal` в `docker/centrifugo/config.json` получает `"presence": true` И `"allow_user_limited_channels": true` (в дополнение к существующему `"allow_subscribe_for_client": true`). Обе опции обязательны: без `allow_user_limited_channels` клиент не подпишется на `personal:#user_X`, без `presence` недоступен `presence_stats`. То же — в production/staging-конфиге Centrifugo (см. «Документация и эксплуатация»).
- **HTTP API приложения:** не затрагивается (новых маршрутов нет). Конфиг приложения (`app/config/centrifugo.php`, env) не меняется — нужны только серверные опции namespace Centrifugo.

## Фазы выполнения

### 1. Presence на уровне Centrifugo-клиента и инфраструктуры
Цель: дать инфраструктуре возможность спросить у Centrifugo presence-статистику по каналу и включить presence/подписку на сервере.

Что сделать:
- В `docker/centrifugo/config.json` у namespace `personal` добавить `"presence": true` и `"allow_user_limited_channels": true`.
- Создать DTO `App\Modules\Notifications\Infrastructure\Centrifugo\CentrifugoPresenceStats` — обычный `final readonly class` с `public int $numClients` (конструктор без отдельной фабрики; это не VO — `equals()`/`JsonSerializable` не нужны, по образцу `FcmPushResult`).
- Создать `App\Modules\Notifications\Infrastructure\Exception\CentrifugoPresenceException extends \DomainException` (новая папка `Infrastructure/Exception`). По образцу `CentrifugoPublishException`, но **без `isTransient`** (исключение всегда перехватывается в `CentrifugoOnlinePresence` и не ретраится → флаг был бы мёртвым кодом; пояснить это в PHPDoc класса). Приватный конструктор + статические фабрики: `transport(\Throwable)`, `serverError(int)`, `requestRejected(int)`, `apiError(int $code, string $message)`, `malformedResponse()`. Сообщения на русском.
- В `CentrifugoClient` добавить `public function presenceStats(string $channel): CentrifugoPresenceStats` по алгоритму выше: строить PSR-7 `Request` к `{apiUrl}/presence_stats` (тем же стилем, что `publish()`), проверять статусы, разбирать тело собственной логикой и **бросать только `CentrifugoPresenceException`** (не `CentrifugoPublishException`, не переиспользуя `ensureNoApiError`). Битый JSON (`\JsonException`) и неверную структуру тела обязательно конвертировать в `CentrifugoPresenceException::malformedResponse()`, чтобы наружу не утекало никакого другого типа исключения (от этого зависит fail-open). `publish()` и `ensureNoApiError()` не изменять.

Результат: `CentrifugoClient::presenceStats()` возвращает `numClients` или бросает `CentrifugoPresenceException`; на dev-стенде у namespace `personal` включены presence и user-limited каналы.

Сценарии тестирования (`tests/Unit/Modules/Notifications/Infrastructure/Centrifugo/CentrifugoClientTest.php`):
- успех: запрос `POST` на полный URL `http://centrifugo:8000/api/presence_stats`, заголовок `apikey ...`, тело содержит `channel`; ответ `{"result":{"num_clients":2,"num_users":1}}` → `numClients === 2`;
- транспортный сбой → `CentrifugoPresenceException` (ветка `transport`);
- статус 500 → `serverError`; статус 400 → `requestRejected`;
- тело с `error` при 200 → `apiError` (код/сообщение пробрасываются в сообщение);
- malformed-структура: ответ `{"result":{}}` (нет `num_clients`) → `malformedResponse`;
- битый JSON: тело `"{not json"` → `malformedResponse` (а не утечка `\JsonException`).

Проверка:
- `make qa` (или `make test-coverage`) — тесты + 100% покрытие зелёные (обычный `make test` покрытие не измеряет);
- `make phpstan` — без ошибок;
- `docker compose -f docker/docker-compose.dev.yml run --rm centrifugo centrifugo checkconfig` (или healthcheck сервиса `centrifugo`) — конфиг с новыми опциями валиден; `make test`/`reset-test` Centrifugo не поднимает, поэтому изменение `config.json` проверяется отдельно через `checkconfig`.

### 2. Контракт онлайн-статуса и его реализация
Цель: дать Application способ узнать «онлайн ли пользователь» одним `bool`, спрятав канал, Centrifugo и fail-open в Infrastructure.

Что сделать:
- Создать `App\Modules\Notifications\Application\Contract\OnlinePresenceContract` с `public function isOnline(UserId $userId): bool;` (PHPDoc на русском: метод не бросает исключений, неопределённость трактуется как «не онлайн»).
- Создать `App\Modules\Notifications\Infrastructure\Centrifugo\CentrifugoOnlinePresence implements OnlinePresenceContract`:
  - конструктор: `CentrifugoClient $centrifugoClient`, `LoggerInterface $logger`;
  - `isOnline()`: строит канал `\sprintf('personal:#user_%s', $userId->value())` (комментарий: формат синхронен с `PublishRealtimeNotificationHandler`; userId — нормализованный UUID v7 в нижнем регистре, см. инвариант), вызывает `presenceStats`, возвращает `$stats->numClients > 0`;
  - перехватывает ровно `CentrifugoPresenceException`: пишет `WARN` «Не удалось проверить онлайн-статус получателя, push будет отправлен.» с контекстом (`userId`, `errorClass`) и возвращает `false`. Это реальная обработка (fallback + лог), а не голый re-throw — допустимо в Infrastructure.
- В `NotificationsBootloader::BINDINGS` добавить `OnlinePresenceContract::class => CentrifugoOnlinePresence::class`. Фабрика не нужна: `CentrifugoClient` уже биндится, `LoggerInterface` резолвится контейнером.

Результат: `OnlinePresenceContract` доступен в DI, отдаёт `true` для онлайн, `false` для офлайн и при любом сбое presence.

Сценарии тестирования (новый `tests/Unit/Modules/Notifications/Infrastructure/Centrifugo/CentrifugoOnlinePresenceTest.php`):
- `CentrifugoClient` — `final readonly`, обычным PHPUnit не мокается. Поэтому в тест внедряется **настоящий** `CentrifugoClient`, собранный с фейковым `ClientInterface` (PSR-18), как в `CentrifugoClientTest` (его helper `client()` создаёт `CentrifugoClient` с мокнутым `ClientInterface`). Канал проверяется по телу запроса, перехваченного фейковым HTTP-клиентом.
- `num_clients > 0` (фейковый HTTP вернул `{"result":{"num_clients":1,...}}`) → `isOnline()` === `true`; в запрос ушёл канал `personal:#user_{userId}`;
- `num_clients === 0` → `false`;
- фейковый `ClientInterface` бросает `ClientExceptionInterface` (→ внутри `CentrifugoPresenceException`) → `isOnline()` === `false` и записан WARN. Логгер — `createMock(LoggerInterface::class)` с `expects(self::once())->method('warning')` (готового `TestLogger` в проекте нет; spy-паттерн при желании — `tests/Feature/Modules/Media/Flow/Fixture/RecordingMediaLogger.php`).
- В `tests/Unit/Modules/Notifications/Infrastructure/Bootloader/NotificationsBootloaderTest.php` (уже существует) добавить проверку, что из контейнера резолвится `OnlinePresenceContract` и это экземпляр `CentrifugoOnlinePresence` — иначе ошибка биндинга не ловится прямыми unit-тестами.

Проверка:
- `make qa` (или `make test-coverage`) — тесты + 100% покрытие;
- `make phpstan`.

### 3. Пропуск push для онлайн-получателя в хендлере
Цель: использовать онлайн-статус как guard в `SendPushNotificationHandler` до загрузки токенов и вызова FCM.

Что сделать:
- В `SendPushNotificationHandler` добавить в конструктор зависимость `OnlinePresenceContract $onlinePresence` (конкретное имя свойства, не `$presence`/`$service`).
- Порядок в `handle()`: сохранить существующий ранний выход при пустом наборе токенов (его НЕ двигаем — для пользователя без токенов push невозможен, и Centrifugo дёргать незачем). Затем, **перед** вызовом `fcmPushSender->send()`, добавить guard: если `$this->onlinePresence->isOnline(UserId::fromString($command->userId))` — записать `DEBUG` «Получатель онлайн, push-уведомление пропущено.» (контекст `userId`) и `return;`. Удаление невалидных токенов и отправка остаются после guard'а без изменений. `handle()` остаётся в пределах ~40 строк.
- **Подавление безусловное** (решение пользователя): push пропускается для онлайн-получателя независимо от того, включены ли у этого уведомления другие каналы. Если тип/настройка только-push, онлайн-получатель ничего не получит — это принятый продуктовый сценарий (см. «Предусловия и риски»).

Результат: онлайн-получатель с токенами не получает push (FCM не вызывается); офлайн-получатель — как раньше; сбой presence → push отправляется; пользователь без токенов выходит раньше, не вызывая Centrifugo.

Сценарии тестирования:
- `tests/Feature/Modules/Notifications/Application/SendPushNotificationHandlerTest.php`: обновить helper `handler()` — добавить параметр `?OnlinePresenceContract $onlinePresence = null` с дефолтным офлайн-стабом (`createStub`, `isOnline` → `false`), чтобы три существующих теста остались без правок call-site и сохранили поведение;
- **тот же helper-апдейт сделать в `tests/Feature/Modules/Notifications/Presentation/DeliveryJobTest.php`** (строка ~139 тоже напрямую создаёт `SendPushNotificationHandler`) — иначе после смены конструктора тест сломается;
- новый тест «онлайн → push не уходит»: есть токены, `isOnline` → `true`, `FcmPushSenderContract::send` с `expects(self::never())`, токены не удаляются;
- новый тест «офлайн → push уходит» с явным офлайн-стабом и токенами — детерминированно покрывает ветку `isOnline() === false`.

Проверка:
- `make qa` (или `make test-coverage`) — все тесты зелёные, покрытие 100%;
- `make phpstan` — без ошибок.

## Тесты
Стратегия: `after_each_phase`. Каждая фаза завершается своими тестами и прогоном `make qa` (или `make test-coverage`) + `make phpstan`. Важно: обычный `make test` покрытие не измеряет, а проект требует 100% — финальный гейт именно `make qa`/`make test-coverage`.
- Фаза 1 — unit на `CentrifugoClient::presenceStats` (успех + все ветки сбоев: transport, 5xx, 4xx, `error`-объект, malformed `{"result":{}}`, битый JSON), покрывают `CentrifugoPresenceStats` и фабрики `CentrifugoPresenceException`.
- Фаза 2 — unit на `CentrifugoOnlinePresence` через настоящий `CentrifugoClient` + фейковый `ClientInterface` (онлайн / офлайн / fail-open с WARN) + тест резолва биндинга в `NotificationsBootloaderTest`.
- Фаза 3 — feature на `SendPushNotificationHandler` (онлайн → нет push; офлайн → push) с правкой helper в `SendPushNotificationHandlerTest` и `DeliveryJobTest`.
Требование проекта — 100% покрытие; новые классы и все ветки исключений покрываются перечисленными сценариями. Дублёры без проверки вызова — `createStub()`; где важен факт/отсутствие вызова (`send`, `warning`) — `createMock()` с `expects()`. `CentrifugoClient` (`final`) не мокается — используется реальный экземпляр с фейковым PSR-18 `ClientInterface`. Тесты не требуют живого Centrifugo; отдельный e2e вне scope — в проекте нет такого harness, конфиг проверяется `centrifugo checkconfig`.

## Логирование
Стратегия: `debug_precise`.
- `SendPushNotificationHandler`: при пропуске — `DEBUG` «Получатель онлайн, push-уведомление пропущено.» (`userId`). Уровень DEBUG, а не INFO: это рядовое решение нормального потока (rules резервируют INFO за ключевыми бизнес-событиями вроде регистрации), и согласуется с уже существующими DEBUG-логами отправки/пустых токенов.
- `CentrifugoOnlinePresence`: при сбое presence — `WARN` «Не удалось проверить онлайн-статус получателя, push будет отправлен.» (`userId`, `errorClass`). WARN — реальная проблема инфраструктуры; нормальный офлайн-флоу логом не шумит.
- Все сообщения логов на русском (rules).

## Документация и эксплуатация
- **Production/staging Centrifugo config:** обязательно включить у namespace `personal` обе опции — `"presence": true` и `"allow_user_limited_channels": true`. Без `allow_user_limited_channels` клиент не подпишется на персональный канал, без `presence` не работает `presence_stats`; в обоих случаях (по fail-open) push будет уходить всегда, тихо обнуляя фичу. Зафиксировать в деплой-заметках/runbook Centrifugo (конкретного файла runbook в репозитории нет — фиксировать там, где ведётся эксплуатационная документация Centrifugo).
- **Клиентская подписка (вне scope этого плана):** фича подавления реально срабатывает только когда клиент подключается к Centrifugo с JWT `sub = user_{userId}` и подписан на `personal:#user_{userId}`. Этого механизма (выдача connection-токена + подписка) в репозитории сейчас нет — нужно убедиться, что он существует или планируется отдельно (вероятно на стороне мобильного приложения и/или отдельного эндпоинта токена).
- **Env/конфиг приложения:** новые переменные окружения и параметры `app/config/centrifugo.php` не требуются — используется существующий `apiUrl`/`apiKey`.
- **Оверхед:** presence в Centrifugo хранится в движке (по умолчанию in-memory); включение добавляет небольшой оверхед на персональные каналы — приемлемо для per-user каналов.

## Изменения после мета-ревью

### После моделей (haiku, sonnet, opus)
- **+ Добавлено:** в серверный конфиг Centrifugo добавлена опция `allow_user_limited_channels: true` (не только `presence: true`) — без неё клиент не подпишется на `personal:#user_X` и presence всегда `0` (opus, блокер, подтверждено офиц. документацией Centrifugo).
- **+ Добавлено:** раздел «Предусловия и риски» — клиентская подписка/выдача connection-JWT Centrifugo в репозитории отсутствует; до её появления presence = 0 и push уходит всегда (fail-open). Реализация клиентской части явно вынесена за scope (opus, блокер).
- **+ Добавлено:** инвариант идентичности канала (realtime использует сырой `$command->userId`, presence — `UserId->value()`; совпадают, т.к. userId сквозь поток нормализован, UUID v7 в нижнем регистре) + комментарий в коде (opus).
- **+ Добавлено:** явные детали тестов — полный URL `.../api/presence_stats`, конкретный malformed-кейс `{"result":{}}`, проверка WARN через `createMock(LoggerInterface)`/`expects('warning')`, helper `handler()` с дефолтным офлайн-стабом, отдельный детерминированный тест «офлайн → push» (sonnet, opus).
- **~ Изменено:** `presenceStats` не переиспользует `ensureNoApiError()` и бросает свой `CentrifugoPresenceException` (а не `CentrifugoPublishException`) — устранена двусмысленность «повторить симметрично» (sonnet, opus).
- **~ Изменено:** DTO `CentrifugoPresenceStats` хранит только `numClients`; `num_users` убран как неиспользуемый (мёртвый код) (opus).
- **~ Изменено:** уточнена формулировка про слой `CentrifugoPresenceException` (относится к инфраструктурному `CentrifugoClient`, в отличие от `CentrifugoPublishException` при Application-контракте) и про отсутствие `isTransient` с пояснением в PHPDoc (sonnet, opus).
- **~ Изменено:** зафиксирована осознанная гонка тайминга presence↔send как принятый компромисс (opus).
- **− Убрано:** избыточное пятистрочное обоснование дублирования канала сжато до сути (sonnet).
- **Отклонено:** e2e-тест против живого Centrifugo (haiku) — в проекте нет такого harness, Centrifugo мокается по существующему паттерну, логика полностью покрыта unit/feature. INFO вместо DEBUG для пропуска push (haiku) — это рядовой поток, INFO зарезервирован за ключевыми бизнес-событиями. Catch-all для «неожиданных» исключений в `isOnline` (haiku) — нарушает запрет широкого catch; `presenceStats` оборачивает все сбои в `CentrifugoPresenceException`, неожиданных путей нет. Helper формата канала в Infrastructure (opus) — не убирает межслойный дубль (realtime в Application не может его использовать), поэтому оставлено документированное дублирование.

## Реакция на ревью (strict, кросс-CLI: Codex)
Файл ревью: `docs/plans/2026-06-15_13-36_notifications-skip-push-when-online_review.md`. Бесспорные замечания внесены в план, спорное вынесено пользователю, отклонённые зафиксированы ниже.

### Внесено
- **+ Битый JSON мимо fail-open (bug):** `presenceStats` обязан оборачивать `\JsonException` и неверную структуру в `CentrifugoPresenceException::malformedResponse()`; добавлен тест на битый JSON. Иначе исключение утекло бы мимо перехвата в `isOnline`, Job упал бы и push не ушёл.
- **~ `CentrifugoClient` — `final`, не мокается:** тест `CentrifugoOnlinePresence` использует настоящий `CentrifugoClient` с фейковым PSR-18 `ClientInterface` (как `CentrifugoClientTest`), а не `createMock` на клиент.
- **+ Второй call-site:** `DeliveryJobTest.php:139` тоже создаёт `SendPushNotificationHandler` напрямую — его helper тоже обновляется офлайн-стабом.
- **~ Команды проверки:** гейт 100% покрытия — `make qa`/`make test-coverage` (обычный `make test` покрытие не меряет). Конфиг Centrifugo проверяется `centrifugo checkconfig` — `reset-test` поднимает только postgres/redis/minio/mailpit, Centrifugo не стартует.
- **+ Тест биндинга:** в существующий `NotificationsBootloaderTest` добавлен резолв `OnlinePresenceContract` → `CentrifugoOnlinePresence`.
- **~ Порядок проверок:** presence проверяется после раннего выхода «нет токенов», перед FCM — для пользователя без токенов Centrifugo не вызывается.

### Вынесено пользователю
- **Push-only тип (скрытый риск):** пользователь выбрал **безусловное подавление** — зафиксировано в «Принятые решения» и «Предусловия и риски».

### Отклонено
- **Заголовок `Authorization: apikey` vs `X-API-Key` (блокер по версии Codex):** presence повторяет существующий рабочий `publish`, который использует `Authorization: apikey <key>` — это валидный формат HTTP API Centrifugo и осознанный выбор проекта. Если `publish` аутентифицируется, то и `presence_stats` тем же заголовком. Менять/проверять заголовок — отдельная задача про существующий клиент, не вводится этим изменением.
- **Клиентская подписка/connection token (блокер):** не отклонено по сути, а уже учтено — вынесено в «Предусловия и риски» как обязательная внешняя зависимость вне scope (бэкенд-guard самодостаточен).
- **Обновление README модуля:** в модуле нет README; требование к prod-конфигу Centrifugo уже зафиксировано в «Документация и эксплуатация».

## Прогресс выполнения
Журнал: `docs/executions/2026-06-15_14-31_notifications-skip-push-when-online.md`

- [x] Фаза 1: Presence на уровне Centrifugo-клиента и инфраструктуры (config.json, `CentrifugoPresenceStats`, `CentrifugoPresenceException`, `CentrifugoClient::presenceStats`)
- [x] Фаза 2: Контракт онлайн-статуса и его реализация (`OnlinePresenceContract`, `CentrifugoOnlinePresence`, биндинг в бутлоадере)
- [x] Фаза 3: Пропуск push для онлайн-получателя в `SendPushNotificationHandler`
