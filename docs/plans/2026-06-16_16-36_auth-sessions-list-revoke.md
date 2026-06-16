---
title: Вывод сессий и отзыв конкретной сессии в модуле Auth
date: 2026-06-16 16:36
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
Добавить в модуль `Auth` два HTTP-эндпоинта для управления сессиями текущего пользователя:
- `GET /api/v1/auth/sessions` — список своих сессий с устройством и IP.
- `DELETE /api/v1/auth/sessions/<sessionId>` — отзыв конкретной своей сессии.

Готово, когда: авторизованный пользователь видит список своих активных сессий (с пометкой текущей, устройством и IP), может отозвать любую свою сессию по её id; чужая/несуществующая сессия даёт 404; без access-токена — 401; `make test` и `make phpstan` зелёные; OpenAPI перегенерирован.

## Контекст
Зачем: сейчас пользователь не может посмотреть, с каких устройств он залогинен, и не может завершить «забытую» сессию на чужом устройстве. Есть только `POST /auth/logout` (отзыв **текущей** сессии).

Что уже есть в коде (переиспользуем):
- «Сессия» — это `session_id` (UUID v7), который связывает пару токенов access+refresh в таблице `auth_tokens`. Пара выпускается одним `session_id` в `CycleTokenStorage::issuePair()`; ротация (`rotate()`) создаёт новый `session_id`.
- `App\Modules\Auth\Domain\Entity\AuthToken` (`app/src/Modules/Auth/Domain/Entity/AuthToken.php`) — поля `id, userId, sessionId, type, tokenHash, expiration` + `HasTimestamps` (`createdAt/updatedAt`). **Полей устройства/IP сейчас нет.**
- `App\Modules\Auth\Repository\AuthTokenRepository` — есть `findBySessionIdForUpdate()`, `findByHash*()`. Нет выборки по `userId`.
- `App\Modules\Auth\Infrastructure\Auth\CycleTokenStorage` реализует и `Spiral\Auth\TokenStorageInterface`, и `AuthTokenStorageContract`. `revokeSession(SessionId)` удаляет все токены сессии **без проверки владельца** (для logout это безопасно — `sessionId` берётся из payload собственного токена).
- Аутентификация роута: связка middleware из `logout` — `AuthTransportWithStorageMiddleware(header, cycle)` → `AuthContextAttributeMiddleware` (кладёт `authUserId`/`authSessionId` в request-атрибуты из payload **access**-токена) → `RequireAuthenticatedMiddleware` (401 при отсутствии `authUserId`).
- Контроллер читает auth-данные через Filter с `#[Attribute(key: 'authUserId')]` (см. `LogoutFilter`), route-параметр — как аргумент метода (см. пример `UserController::show(string $id)` в `docs/code-examples.md`).
- IP и User-Agent доступны Filter-ам через `Spiral\Filters\Attribute\Input\RemoteAddress` (`?string`, из `REMOTE_ADDR`) и `Header(key: 'User-Agent')` — без `ServerRequestInterface` в контроллере. Тесты задают IP через `withServerVariables(['REMOTE_ADDR' => ...])`.
- Ответы: `GianTiaga\SpiralOpenApi\Response\CollectionResponse<T>` (список без пагинации), `EmptySuccessResponse` (204), `DataResponse<T>`. Ресурсы — `final readonly` extends `App\Shared\Presentation\Http\Resource\AbstractResource` (сериализация автоматическая).
- Typecast: nullable-колонка ↔ non-null VO делается отдельным классом `ColumnValueTypecast` (образец `ConsumptionTypecast` — `castDatabaseValue`/`uncastValue`, NULL → null-object). Для дат в запросах к БД — `App\Shared\Infrastructure\Database\DatabaseDateTimeFormat::WITH_MICROSECONDS`.
- Места выдачи токенов, куда надо протащить устройство: `ResolveLoginCodeHandler::resolveVerifiedCode()` (вход существующего пользователя), `CompleteRegistrationHandler::handle()` (регистрация), `RefreshTokensHandler` → `CycleTokenStorage::rotate()`.

## Принятые решения
- **Метаданные сессии: устройство + IP** (подтверждено пользователем). В `auth_tokens` добавляются nullable-колонки `ip` и `user_agent`; они захватываются при выдаче пары токенов и при ротации. Источник подтверждения: ответ пользователя.
- **API REST** (подтверждено пользователем): `GET /api/v1/auth/sessions` + `DELETE /api/v1/auth/sessions/<sessionId>`. Источник: ответ пользователя.
- **Отсутствие значений выражаем типами, а не `null`** (rules.md «Явные типы вместо null», прямо приводит `KnownIp`/`UnknownIp`): абстракции `Ip` (→ `KnownIp`/`UnknownIp`) и `UserAgent` (→ `KnownUserAgent`/`UnknownUserAgent`), объединённые в составной VO `SessionDevice`.
- **Список без пагинации**: у пользователя единицы сессий → `CollectionResponse<SessionResource>`, без cursor-пагинации. (`decision_mode: recommend_and_ask`, незначительное решение, причина — малый объём данных.)
- **`AuthSession` — read-model в Application, не доменный VO**: размещается в `Application/Query/GetUserSessions/` (по CQRS-контракту arch.md «Query Handler возвращает Result DTO / типизированную коллекцию» и rules.md о назначении `Domain/ValueObject`). Решение по итогам мета-ревью (`autonomous`, причина — это read-проекция группы токенов без доменных инвариантов).
- **IP берётся из `REMOTE_ADDR`** (через `#[RemoteAddress]`) — осознанное ограничение, как в уже существующем `RateLimitMiddleware`. За обратным прокси/балансировщиком это может быть IP прокси, а не клиента. Доверенные заголовки (`X-Forwarded-For`/trusted proxies) — вне scope этой задачи, отдельное инфраструктурное решение. (`autonomous`, причина — единообразие с текущим поведением проекта.)
- **Контроллер**: новые методы добавляются в существующий `AuthController` (там же `logout`, общая middleware-связка) — без отдельного контроллера.
- **Проверка владельца при отзыве**: новый метод контракта `revokeUserSession(UserId, SessionId)`; чужая/несуществующая/некорректная сессия → `NotFoundException` (404), чтобы не раскрывать чужие `sessionId`. Существующий `revokeSession()` остаётся для logout/rotate без изменений.
- **Ожидаемый объём**: ~5 фаз, новые ~20 файлов + правки ~12 существующих, преимущественно мелкие классы (VO/typecast/CQRS), плюс миграция и тесты.

## Целевой алгоритм

**Захват устройства при выдаче токенов (verify / register / refresh):**
1. Filter читает `clientIp` (`#[RemoteAddress]`, `?string`) и `userAgent` (`#[Header(key: 'User-Agent')]`, `?string`).
2. Контроллер кладёт их в Command (примитивы на границе).
3. Handler строит `SessionDevice::fromRequest(?string $ip, ?string $userAgent)` (внутри `Ip::fromNullable`/`UserAgent::fromNullable`: пусто/`null`/невалид → `Unknown*`).
4. `issuePair(UserId, SessionDevice)` / `rotate(refreshRaw, SessionDevice)` сохраняют `ip`/`user_agent` в обе строки токенов сессии. **При ротации device берётся из текущего запроса (нового refresh), а значения старого отзываемого токена отбрасываются** — IP/устройство сессии обновляются на актуальные.

**Вывод сессий (`GET /auth/sessions`):**
1. Middleware-связка как у logout проверяет access-токен, кладёт `authUserId` и `authSessionId` в атрибуты.
2. `ListSessionsFilter` читает оба атрибута; контроллер вызывает `GetUserSessionsQuery(userId)`.
3. `GetUserSessionsHandler`: `AuthTokenRepository::findActiveByUserId(userId, now)` → плоская коллекция **не истёкших** токенов; группировка по `sessionId`; каждая группа → `AuthSession::fromTokens()` (sessionId, createdAt = min, expiresAt = max, ip, userAgent); возврат `AuthSessionCollection`.
4. Контроллер мапит каждую сессию в `SessionResource::fromSession(session, currentSessionId)` (где `current = session.id === authSessionId`) → `CollectionResponse`.

**Отзыв сессии (`DELETE /auth/sessions/<sessionId>`):**
1. Та же middleware-связка; `RevokeSessionFilter` читает `authUserId`; `sessionId` — аргумент метода контроллера из роута.
2. `RevokeUserSessionCommand(userId, sessionId)` → `RevokeUserSessionHandler` (`#[Transactional]`): guard `AbstractUuidV7Id::isUuidV7($sessionId)` — иначе `NotFoundException`; затем `authTokenStorage->revokeUserSession(UserId, SessionId)`.
3. `CycleTokenStorage::revokeUserSession()`: `findByUserAndSessionForUpdate(userId, sessionId)`; пусто → `NotFoundException('app.auth.session_not_found')`; иначе удалить все токены + `run()`.
4. Ответ 204. Отзыв собственной текущей сессии разрешён (эквивалент logout).

## Контракты реализации

### Данные и БД
Таблица `auth_tokens` — **изменяется** новой миграцией `app/database/migrations/20260616.HHMMSS_0_add_device_to_auth_tokens.php` (timestamp позже `20260615.141700`):
- `ip` — `string`, length `45` (IPv6), **nullable** (NULL = `UnknownIp`).
- `user_agent` — `text`, **nullable** (NULL = `UnknownUserAgent`).
- ВАЖНО (ALTER существующей таблицы): использовать `$this->table('auth_tokens')->addColumn('ip', 'string', ['length' => 45, 'nullable' => true])->addColumn('user_agent', 'text', ['nullable' => true])->update();` — именно `update()`, не `create()` (в проекте прецедента ALTER нет, все миграции зовут `create()` при создании таблиц). `down()` — `->dropColumn('ip')->dropColumn('user_agent')->update();`.
- Совместимость: существующие строки получают NULL → корректно гидрируются в null-object. Backfill не нужен. Существующий индекс `user_id` (создан в `20260615.141700`) покрывает выборку `findActiveByUserId` — новый индекс не нужен. Rollback — drop колонок.

Прочие таблицы — `Не затрагивается`.

### API и внешние контракты

**`GET /api/v1/auth/sessions`** (новый), `name: api.v1.auth.sessions.index`, group `api`.
- Auth: Bearer access-токен обязателен. Middleware: `AuthTransportWithStorageMiddleware(header,cycle)` + `AuthContextAttributeMiddleware` + `RequireAuthenticatedMiddleware`.
- Request: тело/query не требуются.
- Response 200: `CollectionResponse<SessionResource>` → `{"data":[{"id":<uuid>,"createdAt":<ISO8601>,"expiresAt":<ISO8601>,"ip":<string|null>,"device":<string|null>,"current":<bool>}]}`. `ip`/`device` = `null` для `Unknown*`.
- Ошибки: 401 (`app.auth.unauthenticated`) — нет/невалидный/refresh-токен как Bearer.

**`DELETE /api/v1/auth/sessions/<sessionId>`** (новый), `name: api.v1.auth.sessions.revoke`, group `api`, та же middleware-связка.
- Path: `sessionId` (UUID v7).
- Response 204: `EmptySuccessResponse`.
- Ошибки: 401 (нет access-токена); 404 (`app.auth.session_not_found`) — сессия не принадлежит пользователю, не существует или `sessionId` не UUID v7.

**Внутренний контракт `AuthTokenStorageContract`** (`app/src/Modules/Auth/Application/Contract/AuthTokenStorageContract.php`) — меняется:
- `issuePair(UserId $userId, SessionDevice $device): IssuedTokenPair` (был без `$device`).
- `rotate(string $refreshRaw, SessionDevice $device): IssuedTokenPair` (был без `$device`); `$device` — из текущего запроса, перезаписывает device новой пары.
- `revokeSession(SessionId $sessionId): void` — без изменений.
- `revokeUserSession(UserId $userId, SessionId $sessionId): void` — новый.

Внешний request-контракт verify/register/refresh **не меняется**: клиент ничего нового не присылает, IP/User-Agent берутся из соединения и заголовка автоматически.

## Фазы выполнения

### 1. Доменная модель устройства + миграция
Цель: хранить IP и устройство у токена через типобезопасные VO без `null`.

Что сделать:
- VO в `Modules/Auth/Domain/ValueObject/`:
  - `Ip` — `abstract readonly`, `JsonSerializable`; `fromNullable(?string): self` (NULL/пусто/`!FILTER_VALIDATE_IP` → `UnknownIp`, иначе `KnownIp`); абстрактные `jsonSerialize(): ?string` и `toNullableString(): ?string`; `KnownIp` (фабрика-валидатор, `value()`, `equals()`), `UnknownIp` (оба метода → `null`).
  - `UserAgent` — аналогично: `fromNullable(?string)` (пусто/`null` → `UnknownUserAgent`, иначе `KnownUserAgent` с обрезкой до разумного предела, напр. 1024 символа); `KnownUserAgent`/`UnknownUserAgent`.
  - `SessionDevice` — `final readonly` (`Ip $ip`, `UserAgent $userAgent`); фабрики `fromRequest(?string $ip, ?string $userAgent)` и `unknown()`; `equals()`.
  - **`Ip`/`UserAgent` НЕ реализуют `Stringable`** (осознанное отступление от «простого скалярного VO» в rules.md): у `Unknown*`-варианта нет безопасного строкового представления — `__toString()` вернул бы пустую строку и замаскировал отсутствие значения. Чтение наружу — только через `jsonSerialize(): ?string` / `toNullableString(): ?string`, где отсутствие явно выражено `null`. Это согласуется с rules.md («Явные типы вместо null») и оговоркой «составной VO может не быть Stringable, если нет естественного безопасного строкового представления».
- Typecasts `Modules/Auth/Infrastructure/Cycle/`: `IpTypecast`, `UserAgentTypecast` (implements `ColumnValueTypecast` — это пустой маркер-интерфейс, сигнатуры не enforced; копировать форму `ConsumptionTypecast`). `castDatabaseValue` получает сырое значение string-колонки → объявить как у образца `string|\DateTimeInterface|null` (для string-колонки реально прилетит `string|null`, NULL → `Unknown*`); `uncastValue(Ip|UserAgent $value): ?string` → `$value->toNullableString()`. Перед реализацией свериться с `App\Shared\Infrastructure\Cycle\ValueObjectCast`, как он вызывает typecast.
- `AuthToken` (`Domain/Entity/AuthToken.php`): добавить `#[Column(type:'string(45)', name:'ip', nullable:true, typecast: IpTypecast::class)] public private(set) Ip $ip;` и `#[Column(type:'text', name:'user_agent', nullable:true, typecast: UserAgentTypecast::class)] public private(set) UserAgent $userAgent;`. В `issue()` добавить параметр `SessionDevice $device` и присвоить `$this->ip = $device->ip; $this->userAgent = $device->userAgent;`.
- Миграция `add_device_to_auth_tokens` (см. «Данные и БД»).

Результат: токен хранит устройство и IP; пустые значения — `Unknown*`, а не `null`.

Сценарии тестирования:
- `KnownIp` принимает IPv4/IPv6, `fromNullable(null|''|'bad')` → `UnknownIp`; `jsonSerialize`/`toNullableString` для Known/Unknown.
- `UserAgent` известный/неизвестный, обрезка длинного UA.
- `SessionDevice::fromRequest` и `unknown()`.
- `AuthToken::issue(... device)` устанавливает ip/userAgent.
- `IpTypecast`/`UserAgentTypecast` через **реальный persist+hydrate** (не прямой вызов статических методов): `ValueObjectCast` зовёт typecast через рефлексию и передаёт в `uncastValue` `object|null`. Round-trip для known И unknown: токен с `KnownIp`/`KnownUserAgent` и токен с `Unknown*` сохраняются и восстанавливаются с теми же значениями (для unknown — снова `Unknown*`, в БД NULL).

Проверка (`test_strategy: after_each_phase`): `make test` (новые unit `tests/Unit/.../Domain/ValueObject`, `tests/Unit/.../Infrastructure/Cycle/AuthTypecastTest`, расширенный `AuthRepositoryTest`) + `make phpstan`.

### 2. Захват устройства в выдаче токенов
Цель: реальные IP и User-Agent сохраняются при входе, регистрации и ротации.

Что сделать:
- `AuthTokenStorageContract`: новые сигнатуры `issuePair`/`rotate` с `SessionDevice` (см. контракт выше).
- `CycleTokenStorage`: `issueToken()` принимает и сохраняет `SessionDevice` в `AuthToken::issue()`; `issuePair(userId, device)` и `rotate(refreshRaw, device)` пробрасывают device (в `rotate` — `$device` из аргумента передаётся в новый `issuePair($userId, $device)` после `revokeSession`; device старого токена НЕ переносится); `create(array $payload, …)` (vendor-граница `TokenStorageInterface`) строит `SessionDevice::unknown()`.
- Filter-ы (`Presentation/Http/Filter/`): в `VerifyCodeFilter`, `RegisterFilter`, `RefreshFilter` добавить `#[RemoteAddress] public ?string $clientIp = null;` и `#[Header(key: 'User-Agent')] public ?string $userAgent = null;` (опциональные, без `Assert\NotBlank`).
- Commands: добавить `?string $ip, ?string $userAgent` в `VerifyLoginCodeCommand`, `ResolveLoginCodeCommand`, `CompleteRegistrationCommand`, `RefreshTokensCommand`.
- Handlers: `VerifyLoginCodeHandler` пробрасывает ip/userAgent в `ResolveLoginCodeCommand`; `ResolveLoginCodeHandler::resolveVerifiedCode()` строит `SessionDevice::fromRequest(...)` → `issuePair`; `CompleteRegistrationHandler` → `issuePair` с device; `RefreshTokensHandler` → `rotate` с device.
- `AuthController`: методы `verifyCode`/`register`/`refresh` передают `clientIp`/`userAgent` из Filter в Command.

Результат: каждая выпущенная пара токенов несёт IP и устройство клиента.

Сценарии тестирования:
- `CycleTokenStorageTest` (`tests/Feature/.../Infrastructure`): `issuePair`/`rotate` с `SessionDevice` сохраняют ip/UA в обеих строках; ротация записывает device из текущего запроса (новый), не из старого токена; `create()`-ветка через `SessionDevice::unknown()` покрыта (для coverage-100 новой строки).
- Обновить под новую сигнатуру `issuePair`/`rotate`/`AuthToken::issue`: `AuthHttpTest::issueTokenPair()` (строка 351); `RefreshTokensAndLogoutTest` (строки 21, 39); `CycleTokenStorageTest` — **5 вызовов** `issuePair` (строки 25, 74, 99, 119, 140); `AuthRepositoryTest::authToken()` — прямой вызов `AuthToken::issue()` (добавить `SessionDevice`). `AuthApplicationTestCase` прямых вызовов `issuePair` не содержит — правка не требуется. `create(...)` в `CycleTokenStorageTest` (строки 47, 59, 66, 128) совместим (device берётся внутри).
- HTTP: verify существующего пользователя и refresh с заданными `REMOTE_ADDR` + заголовком `User-Agent` → токены в БД содержат эти значения; без заголовка/IP → `Unknown*` (ip/device читаются как null на этапе 3).

Проверка: `make test` + `make phpstan`.

### 3. Вывод сессий (`GET /api/v1/auth/sessions`)
Цель: пользователь видит список своих активных сессий с устройством, IP и пометкой текущей.

Что сделать:
- `AuthTokenRepository::findActiveByUserId(UserId $userId, \DateTimeImmutable $now): AuthTokenCollection` — `$this->select()->where('user_id', …)->where('expires_at', '>', $now->format(DatabaseDateTimeFormat::WITH_MICROSECONDS))->orderBy('session_id', 'DESC')->orderBy('created_at')->fetchAll()` в `AuthTokenCollection`. Read-only, без `forUpdate`. `session_id` DESC = новые сессии сверху (UUID v7 хронологичен, см. rules.md). Оператор `>` согласован с `Expiration::isExpired` (`now >= value` = истёк).
- **Read-model в Application, не в Domain**: `AuthSession` — это read-проекция группы токенов (read-model/Result DTO), а не доменный VO, поэтому размещается рядом с Query: `Application/Query/GetUserSessions/AuthSession.php` (`final readonly`: `SessionId $sessionId`, `\DateTimeImmutable $createdAt`, `Expiration $expiresAt`, `Ip $ip`, `UserAgent $userAgent`). Фабрика `fromTokens(AuthTokenCollection $sessionTokens)`: бросает `InvalidDomainValueException` на пустой коллекции; `createdAt` = минимальный `createdAt`, `expiresAt` = максимальный `expiration->value()` (сравнение по `\DateTimeImmutable`, не по VO напрямую). ВНИМАНИЕ (PHPStan strict): `Illuminate\Support\Collection::min()/max()` типизированы как `mixed`, поэтому нельзя сразу передавать результат в `Expiration::fromDateTime()` — использовать типизированный `reduce` с явным `\DateTimeImmutable` (например `reduce(fn (\DateTimeImmutable $carry, AuthToken $t) => $t->expiration->value() > $carry ? $t->expiration->value() : $carry, $first->expiration->value())`) или `instanceof \DateTimeImmutable`-guard после `max()`. `ip`/`userAgent` — из первого токена (в пределах одной сессии оба токена выпущены одним `issuePair`, поэтому device идентичен). `AuthSessionCollection` (extends `Illuminate\Support\Collection`) — там же в `Application/Query/GetUserSessions/`.
- Query `Application/Query/GetUserSessions/`: `GetUserSessionsQuery(string $userId)`, `GetUserSessionsHandler` (`#[LogOperation]`, без транзакции). Пайплайн явно: `$tokens->groupBy(fn (AuthToken $t) => $t->sessionId->value())` → `Collection<string, AuthTokenCollection-подобная группа>`; `->map(fn ($group) => AuthSession::fromTokens(new AuthTokenCollection($group->all())))->values()`; затем `new AuthSessionCollection($mapped->all())`. debug-лог с `userId` и числом сессий. `authSessionId` в Query НЕ передаётся (Query только читает).
- `Presentation/Http/Resource/SessionResource` (extends `AbstractResource`): поля примитивные — `string id, string createdAt, string expiresAt, ?string ip, ?string device, bool current`; `fromSession(AuthSession $session, string $currentSessionId)`: `current = $session->sessionId->value() === $currentSessionId`; даты `->format('c')` (`Expiration` через `->value()->format('c')`); `ip = $session->ip->toNullableString()`, `device = $session->userAgent->toNullableString()`. `?string` гарантирует, что OpenAPI пометит поля nullable (генератор читает nullable из типа свойства).
- `Presentation/Http/Filter/ListSessionsFilter` (`AttributesFilter`): `#[Attribute(key:'authUserId')] public string $authUserId;` + `#[Attribute(key:'authSessionId')] public string $authSessionId;`.
- `AuthController::sessions()` — `GET /api/v1/auth/sessions`, та же middleware-связка, `@return CollectionResponse<SessionResource>`; параметры с конкретными именами (rules.md): `ListSessionsFilter $listSessionsFilter`, `GetUserSessionsHandler $getUserSessionsHandler`, `QueryBusInterface $queryBus`. `GetUserSessionsQuery(userId: $listSessionsFilter->authUserId)`; маппинг коллекции с `currentSessionId: $listSessionsFilter->authSessionId` (используется только здесь, в Presentation) → `new CollectionResponse([...])`.

Результат: авторизованный пользователь получает JSON-список своих сессий; текущая помечена `current: true`.

Сценарии тестирования:
- Repository: `findActiveByUserId` возвращает только не истёкшие, исключает чужие, сортирует новые сверху; истёкший refresh → сессия не попадает.
- `AuthSession::fromTokens`: оба токена сессии имеют идентичные ip/userAgent; `createdAt` = min, `expiresAt` = max (refresh-срок, 60 дней, а не access 1ч); пустая коллекция → `InvalidDomainValueException`.
- `GetUserSessionsHandler`: 2 пары токенов одного пользователя → 2 `AuthSession` с верными createdAt/expiresAt/ip/UA.
- HTTP: с access-токеном → 200 и список, текущая сессия `current:true`, отозванная/истёкшая отсутствует, ip/device отражают сохранённое (или `null`); сессия с истёкшим access, но живым refresh — присутствует в списке; без токена/с refresh как Bearer → 401.

Проверка: `make test` + `make phpstan`.

### 4. Отзыв конкретной сессии (`DELETE /api/v1/auth/sessions/<sessionId>`)
Цель: пользователь завершает любую свою сессию по id; чужая/неизвестная — 404.

Что сделать:
- `AuthTokenRepository::findByUserAndSessionForUpdate(UserId $userId, SessionId $sessionId): AuthTokenCollection` — `where('user_id')->where('session_id')->forUpdate()->fetchAll()`.
- `AuthTokenStorageContract::revokeUserSession(UserId, SessionId): void` + реализация в `CycleTokenStorage`: выбрать токены `findByUserAndSessionForUpdate`; пусто → `throw new NotFoundException('app.auth.session_not_found')` **строго до любого `delete()`** (без частичного удаления); иначе `delete()` каждого + `run()`.
- Command `Application/Command/RevokeUserSession/`: `RevokeUserSessionCommand(string $userId, string $sessionId)`, `RevokeUserSessionHandler` (`#[Transactional]`, `#[LogOperation]`): guard `if (!AbstractUuidV7Id::isUuidV7($command->sessionId)) throw new NotFoundException('app.auth.session_not_found');` (публичный статический метод подтверждён в `AbstractUuidV7Id:51`) затем `revokeUserSession(UserId::fromString(...), SessionId::fromString(...))`; debug-лог с `userId`/`sessionId`.
- `Presentation/Http/Filter/RevokeSessionFilter`: `#[Attribute(key:'authUserId')] public string $authUserId;`.
- `AuthController::revokeSession(string $sessionId, RevokeSessionFilter $revokeSessionFilter, CommandBusInterface $commandBus, RevokeUserSessionHandler $revokeUserSessionHandler)` — `DELETE /api/v1/auth/sessions/<sessionId>`, та же middleware-связка; конкретные имена параметров (rules.md); `RevokeUserSessionCommand(userId: $revokeSessionFilter->authUserId, sessionId: $sessionId)` → `EmptySuccessResponse`.
- Переводы: добавить `'app.auth.session_not_found'` в `app/locale/ru/auth.php` и `app/locale/en/auth.php`.

Результат: отзыв своей сессии инвалидирует её токены (повторный запрос с ними → 401); чужая/неизвестная → 404.

Сценарии тестирования:
- Repository: `findByUserAndSessionForUpdate` находит только токены своей пары; чужой `userId` → пусто.
- `RevokeUserSessionHandler`: своя сессия → токены удалены; чужая → `NotFoundException`; несуществующая → `NotFoundException`; не-UUID-v7 → `NotFoundException` (без 500).
- HTTP: отзыв своей сессии → 204, затем её access-токен → 401; чужая сессия → 404; некорректный `sessionId` → 404; без токена → 401.

Проверка: `make test` + `make phpstan`.

### 5. OpenAPI (path-параметры) и финальная проверка
Цель: спецификация валидна (OpenAPI 3.1 path templating) и весь сьют отражают новые роуты.

Что сделать:
- **Фикс генератора (блокер для валидной спеки)**: `DELETE /auth/sessions/<sessionId>` — первый роут проекта с path-параметром. `GianTiaga\SpiralOpenApi\Spec\SpecBuilder::pathWithoutRoutePrefix()` (`packages/spiral-openapi/src/Spec/SpecBuilder.php:49,61`) отдаёт `route->path` дословно — Spiral-форму `<sessionId>`, тогда как `parameters` уже строятся как `name: sessionId, in: path`. Без нормализации ключ пути выйдет `/auth/sessions/<sessionId>` — невалидный OpenAPI 3.1. Добавить в `SpecBuilder` нормализацию `<name>` → `{name}` при построении ключа пути (например в `pathWithLeadingSlash`/`pathWithoutRoutePrefix`, `preg_replace('/<([^>]+)>/', '{$1}', $path)`). Это правка изолированного пакета `packages/spiral-openapi` — покрыть unit-тестом пакета (путь с параметром → `{...}`) и прогнать `composer -d packages/spiral-openapi test` + `composer -d packages/spiral-openapi phpstan`.
- Расширить `tests/Feature/Modules/Auth/Console/AuthOpenApiGenerationTest::testGeneratesAllAuthRoutes` — assert наличия `/auth/sessions:` (GET) и `/auth/sessions/{sessionId}:` (DELETE, в фигурных скобках), И assert ОТСУТСТВИЯ `/auth/sessions/<sessionId>` (чтобы фикс `SpecBuilder` не был частичным).
- Перегенерировать спецификацию: `php app.php openapi:generate` (обновляет `public/openapi/openapi.yml`); проверить, что DELETE-путь записан как `/auth/sessions/{sessionId}`.
- Финальный gate качества: `make qa` (полный гейт — тесты с покрытием 100% + PHPStan; `make test` сам по себе порог покрытия НЕ проверяет — нужен `make qa`/`make test-coverage`). Для изменённого пакета — `composer -d packages/spiral-openapi install` (если локального `vendor` нет), затем `composer -d packages/spiral-openapi test` и `composer -d packages/spiral-openapi phpstan`.

Результат: документация API включает оба эндпоинта с валидным path-параметром; 100% покрытие сохранено (через `make qa`); каждый новый роут покрыт интеграционным тестом.

Сценарии тестирования:
- Unit пакета `spiral-openapi`: `<param>` в пути → `{param}` в ключе спеки.
- `AuthOpenApiGenerationTest` подтверждает присутствие `/auth/sessions:` и `/auth/sessions/{sessionId}:` и отсутствие старой `<sessionId>`-формы.
- Зелёный полный сьют + зелёный `packages/spiral-openapi` (test + phpstan).

Проверка: `make qa` + `composer -d packages/spiral-openapi test` + `composer -d packages/spiral-openapi phpstan`.

## Тесты
Стратегия: `after_each_phase` — каждая фаза завершается своими тестами и зелёными `make test`/`make phpstan`. Unit — VO и typecast (фаза 1); feature/Infrastructure — `CycleTokenStorage`, repository (фазы 1–4); Application — query/command handlers; HTTP-интеграция — оба новых роута + сохранение устройства в verify/refresh (требование «каждый роут покрыт интеграционным тестом»). Существующие тесты, вызывающие `issuePair`/`rotate`/`AuthToken::issue`, обновляются под новую сигнатуру (`SessionDevice`): `AuthHttpTest`, `RefreshTokensAndLogoutTest`, `CycleTokenStorageTest` (5 вызовов), `AuthRepositoryTest`. Фикс пакета `packages/spiral-openapi` покрывается его собственным suite (`composer -d packages/spiral-openapi test` + `phpstan`, при отсутствии `vendor` — сначала `install`). Финальный гейт качества — `make qa` (тесты с покрытием 100% + PHPStan); `make test` сам по себе порог покрытия не проверяет. Coverage-100 — не допустить непокрытых новых веток (`Unknown*`, `SessionDevice::unknown()` в `create()`).

## Логирование
Стратегия: `debug_precise`. Бизнес-flow — DEBUG: `GetUserSessionsHandler` логирует `userId` и число сессий; `RevokeUserSessionHandler` — `userId`+`sessionId` отозванной сессии (как `LogoutHandler`). 404 при чужой/неизвестной сессии — нормальный flow пользователя (`NotFoundException`), не логируется как ошибка. Контекст логгера — camelCase. `#[LogOperation]` на новых хендлерах включает debug-лог старта/времени.

## Документация и эксплуатация
- `public/openapi/openapi.yml` перегенерировать (`php app.php openapi:generate`); проверить появление `/auth/sessions` (GET) и `/auth/sessions/{sessionId}` (DELETE, фигурные скобки после фикса генератора).
- Переводы `app.auth.session_not_found` (ru + en) — обязательны для корректного 404-сообщения на языке пользователя (`app.auth.*` → файл `auth.php` по доменной маршрутизации исключений).
- Миграцию `add_device_to_auth_tokens` прогнать на dev/CI (`migrate`); ALTER через `->update()`; колонки nullable — обратная совместимость с существующими токенами обеспечена.
- Изменён переносимый пакет `packages/spiral-openapi` (нормализация path-параметров) — прогнать его собственные `composer -d packages/spiral-openapi test`/`phpstan`.
- Релизных секретов/env-переменных не добавляется.

## Изменения после мета-ревью

### После моделей
- **+ Добавлено:** (крит) фикс OpenAPI-генератора `SpecBuilder` — нормализация Spiral `<param>` → OpenAPI `{param}` (иначе путь DELETE невалиден; первый роут проекта с path-параметром), с тестом пакета и проверкой `/auth/sessions/{sessionId}`; (крит) миграция через `->update()`, а не `->create()` (ALTER существующей таблицы); в список обновляемых тестов добавлен `AuthRepositoryTest` (прямой `AuthToken::issue()`) и явно перечислены 5 вызовов `issuePair` в `CycleTokenStorageTest`; новые тест-сценарии — идентичность ip/UA обоих токенов сессии, сессия с истёкшим access но живым refresh, покрытие `SessionDevice::unknown()` через `create()`.
- **~ Изменено:** `AuthSession` + `AuthSessionCollection` перенесены из `Domain/ValueObject` в `Application/Query/GetUserSessions/` как read-model/Result DTO (по CQRS-контракту arch.md и rules.md о VO); `AuthSession::expiresAt` зафиксирован типом `Expiration`; уточнён пайплайн `groupBy → map → new AuthSessionCollection` и сравнение `expiration->value()` (DateTimeImmutable), а не VO; `SessionResource.ip/device` — `?string` через `toNullableString()`; ротация явно берёт device из текущего запроса; конкретные имена параметров контроллеров (`$listSessionsFilter`, `$revokeSessionFilter`, `$getUserSessionsHandler`, `$revokeUserSessionHandler`); `findActiveByUserId` сортирует `session_id DESC` (новые сверху); `throw NotFound` строго до `delete()`; сигнатуры typecast сверены с `ConsumptionTypecast`/`ValueObjectCast`.
- **− Убрано:** ничего.
- **Отклонено:** объединение `ListSessionsFilter`/`RevokeSessionFilter` в один фильтр (читают разный набор атрибутов, следуют паттерну `LogoutFilter`, дублирование минимально); претензия «методы репозитория/Query не описаны в фазе 1» (план фазовый — элементы создаются в своих фазах); «убрать `DatabaseDateTimeFormat`, сравнивать `\DateTimeImmutable` напрямую» (противоречит rules.md «Формат даты для БД через общий контракт»).

## Реакция на ревью

Кросс-CLI ревью (strict): Codex (`codex-cli 0.139.0`), файл `docs/plans/2026-06-16_16-36_auth-sessions-list-revoke_review.md`. Все 7 замечаний приняты — спорных, требующих выбора пользователя, не было.

- **Принято (внесено в план):**
  - [важно] Финальный gate качества — `make qa` (тесты с покрытием 100% + PHPStan), а не `make test`; добавлен `make qa` в фазу 5 и раздел «Тесты».
  - [важно] `AuthSession::fromTokens`: `Collection::min()/max()` дают `mixed` → типизированный `reduce` с `\DateTimeImmutable` или `instanceof`-guard (PHPStan strict).
  - [важно] Тесты typecast — через реальный persist+hydrate (а не прямой вызов статических методов), т.к. `ValueObjectCast` зовёт их рефлексией и передаёт `object|null`; покрыты known и unknown.
  - [важно] `Ip`/`UserAgent` без `Stringable` — зафиксировано обоснование (нет безопасного строкового представления у `Unknown*`; чтение через `?string`).
  - [важно] Проверка пакета `packages/spiral-openapi` — добавлены `phpstan` и `install` (если нет `vendor`), не только `test`.
  - [мелочь] OpenAPI-тест дополнительно проверяет ОТСУТСТВИЕ старой формы `/auth/sessions/<sessionId>`.
  - [мелочь] IP из `REMOTE_ADDR` — зафиксировано как осознанное ограничение (за прокси — IP прокси; trusted-proxy вне scope).
- **Спорное (вынесено пользователю):** нет.
- **Отклонено:** нет.

Вердикт Codex: «план близок к готовому» — все названные блокеры (`coverage-gate`, `min/max`, typecast через реальный `ValueObjectCast`) устранены в плане.

## Прогресс выполнения
Журнал: `docs/executions/2026-06-16_18-00_auth-sessions-list-revoke.md`

- [x] Фаза 1: Доменная модель устройства + миграция
- [x] Фаза 2: Захват устройства в выдаче токенов
- [x] Фаза 3: Вывод сессий (`GET /api/v1/auth/sessions`)
- [x] Фаза 4: Отзыв конкретной сессии (`DELETE /api/v1/auth/sessions/<sessionId>`)
- [x] Фаза 5: OpenAPI path-параметры + финальная проверка
