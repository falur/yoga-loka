---
title: Вывод и отзыв сессий в модуле Auth
date: 2026-06-16 19:23
target: git diff HEAD + untracked
plan: docs/plans/2026-06-16_16-36_auth-sessions-list-revoke.md
mode: draft
score: 89
status: meta-reviewed
meta_reviewers: [architecture-check (sonnet), rules-check (sonnet), plan-check (sonnet), quality-check (sonnet)]
---

# Ревью: Вывод и отзыв сессий в модуле Auth

## Оценка

**89/100.** Реализация очень близка к плану: оба эндпоинта, захват устройства при выдаче токенов, типобезопасные VO без `null`, проверка владельца при отзыве, фикс OpenAPI-генератора и широкий набор тестов на месте. Мета-ревью добавило два реальных, хоть и мелких, нарушения явных правил `rules.md` (ручной `array_map` вместо Collection-пайплайна и позиционные аргументы в тестовом хелпере), которые первичное ревью пропустило, — за это снижена оценка. Остальные замечания касаются мелочей: обрезанного описания DELETE-роута в спецификации, сигнатуры typecast чуть уже образца, дублирования `trim` и нескольких необязательных пробелов в проверках. Блокирующих проблем не нашёл.

## Проблемы сверки с планом

Серьёзных отклонений от плана нет. Все пять фаз отражены в коде, контракты API и БД совпадают с заявленными. Ниже — мелкое расхождение по способу тестирования typecast и обрезанное описание в спецификации (вынесены в «Замечания»).

## Замечания

### 1. Описание DELETE-роута в OpenAPI обрезано на полуслове

В сгенерированной спецификации описание удаления сессии заканчивается словами «Чужая/несуществующая» и обрывается. Причина в том, что генератор берёт только первую строку PHPDoc-комментария метода, а сам комментарий в контроллере перенесён на две строки. Для человека, который читает спецификацию или Swagger UI, это выглядит как недописанная фраза и снижает доверие к документации.

Это не ломает работу API и не влияет на валидность спецификации — чисто косметика документации. Но раз спецификация публичная, лучше уместить осмысленное описание в одну строку.

Технические детали:

- **Тип:** `docs`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `public/openapi/openapi.yml` (блок `'/auth/sessions/{sessionId}'` → `delete.description`); источник — PHPDoc метода `AuthController::revokeSession()` (`app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php:243-246`).
- **Что подтверждает проблему:** в YAML значение `description` = `'Отзыв конкретной своей сессии по её id. Требует Bearer access-токен. Чужая/несуществующая'` — фраза обрывается, потому что вторая строка PHPDoc («сессия → 404.») в описание не попала.
- **Как исправить:** сделать первую (и единственную) строку PHPDoc-описания метода законченной фразой, например «Отзыв своей сессии по её id; чужая/несуществующая → 404.», затем перегенерировать `php app.php openapi:generate`. Отдельный тест не нужен — достаточно убедиться, что описание читается целиком.

### 2. Сигнатура typecast уже, чем закладывал план

В `IpTypecast` и `UserAgentTypecast` метод приёма значения из базы объявлен как `string|null`. План просил повторить форму образца (`ConsumptionTypecast`), где параметр шире — `string|\DateTimeInterface|null`. Образец объявлен шире осознанно: он работает с `datetime`-колонкой, откуда реально может прийти `\DateTimeInterface`. Для строковой колонки `ip` и текстовой `user_agent` из базы прилетает только `string|null`, поэтому более широкая сигнатура здесь не нужна и никакого практического выигрыша не даёт.

То есть это не риск, а просто несоответствие форме образца. Менять не обязательно: внутри метод всё равно зовёт `fromNullable`, которая ждёт `?string`, и для текущих колонок поведение корректно. Если же когда-нибудь колонку поменяют на другой тип (например, дату), несовпадение сигнатуры с образцом всплывёт обычной ошибкой типа PHP при `strict_types`, а не аккуратным доменным исключением — но это гипотетический сценарий вне текущей задачи.

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/src/Modules/Auth/Infrastructure/Cycle/IpTypecast.php:18`, `app/src/Modules/Auth/Infrastructure/Cycle/UserAgentTypecast.php:18`; образец — `app/src/Modules/Auth/Infrastructure/Cycle/ConsumptionTypecast.php` (там `string|\DateTimeInterface|null`); вызов — `ValueObjectCast::invokeColumnCast()` передаёт `bool|int|float|string|\DateTimeInterface|null`.
- **Что подтверждает проблему:** `castDatabaseValue(string|null $value)` против заявленного в плане (фаза 1) «объявить как у образца `string|\DateTimeInterface|null`».
- **Как исправить:** при желании расширить сигнатуру до `string|\DateTimeInterface|null` для единообразия с `ConsumptionTypecast` и плановой формой; на содержании метода это не сказывается (внутри всё равно вызывается `fromNullable`, которая ждёт `?string`). Тесты не требуются — текущие round-trip-проверки уже покрывают known/unknown.

### 3. Прямой вызов статических методов typecast вместо реального persist+hydrate

В `AuthTypecastTest` новые проверки `IpTypecast`/`UserAgentTypecast` вызывают статические методы напрямую (`IpTypecast::castDatabaseValue(null)` и т.п.). План явно просил тестировать typecast «через реальный persist+hydrate, не прямым вызовом статических методов», потому что в бою `ValueObjectCast` зовёт их рефлексией.

Само по себе это не пробел: реальный round-trip для known и unknown уже покрыт в `AuthRepositoryTest::testHydratesAuthTokenDeviceForKnownAndUnknownValues` (сохранение и восстановление через `findByHash` после очистки heap). То есть требование плана по сути выполнено в другом файле, а прямые вызовы здесь — дополнительная быстрая проверка, а не замена. Поэтому это замечание про точность следования формулировке плана, а не про дыру в покрытии.

Технические детали:

- **Статус:** требование плана выполнено, но в другом файле (вопрос структуры тестов, не дыра в покрытии и не отклонение от сути плана)
- **Где:** `tests/Unit/Modules/Auth/Infrastructure/Cycle/AuthTypecastTest.php` (`testIpTypecast`, `testUserAgentTypecast`); реальный round-trip — `tests/Feature/Modules/Auth/Repository/AuthRepositoryTest.php` (`testHydratesAuthTokenDeviceForKnownAndUnknownValues`).
- **Что подтверждает проблему:** прямые вызовы `IpTypecast::castDatabaseValue(...)` / `::uncastValue(...)` без участия `ValueObjectCast`; план (фаза 1, «Сценарии тестирования») требовал именно persist+hydrate.
- **Как исправить:** ничего менять не обязательно — round-trip покрыт. Если хочется буквального соответствия плану, прямые unit-проверки можно оставить как есть (они дёшевы и читаемы), а в журнале/комментарии отметить, что реальный round-trip живёт в `AuthRepositoryTest`.

### 4. Нет интеграционной проверки, что сессия без устройства отдаёт `ip:null`/`device:null`

HTTP-тесты списка сессий проверяют случай с известными IP и User-Agent (`current:true/false`), но ни один интеграционный тест не подтверждает, что сессия, выпущенная без устройства, в JSON-ответе списка отдаёт `"ip":null` и `"device":null`.

Сама сериализация тривиально корректна (`AbstractResource` через `get_object_vars` включает null-поля), а отсутствие значения проверено на уровне VO, typecast и репозитория. Поэтому риск минимальный — это про полноту контракта ответа на HTTP-границе, а не про реальный баг. Один компактный ассерт закрыл бы и формат `null` в выдаче.

Технические детали:

- **Тип:** `tests`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `tests/Feature/Modules/Auth/Http/AuthHttpTest.php` (`testSessionsListsOwnSessionsWithCurrentFlag` проверяет только known-устройство).
- **Что подтверждает проблему:** в HTTP-тестах списка нет случая `SessionDevice::unknown()` с ассертом `"ip":null,"device":null` в теле ответа; nullable-сериализация покрыта только косвенно (VO/typecast/resource-конструктор).
- **Как исправить:** добавить в существующий тест (или отдельный) сессию, выпущенную через `issuePairFor` с unknown-устройством либо через `SessionDevice::unknown()`, и проверить `assertBodyContains('"ip":null,"device":null')` для неё. Заготовка уже есть: приватный хелпер `issueTokenPair()` (`AuthHttpTest.php`) выпускает пару именно с `SessionDevice::unknown()`, но ни один тест списка сессий им не пользуется.

### 5. Ручной `array_map`/`array_values` вместо Collection-пайплайна в контроллере

В `AuthController::sessions()` коллекция сессий маппится в ресурсы через `\array_values(\array_map(...))`, хотя `$userSessions` — это `AuthSessionCollection`, наследник `Illuminate\Support\Collection`, и у него есть `->map()->values()`. Правило `rules.md` прямо требует: «Collection-пайплайны: `->map()`, `->filter()`, `->groupBy()` для чистых трансформаций. `foreach` — только при побочных эффектах». Трансформация здесь чистая (каждая сессия → ресурс), побочных эффектов нет, значит `array_map` не на месте.

Это не баг — выдача формируется корректно. Но это прямое нарушение явного правила проекта, которое к тому же расходится со стилем соседнего кода (в том же диффе `GetUserSessionsHandler` использует `->groupBy()->map()->values()`). Заодно строка `$currentSessionId = $listSessionsFilter->authSessionId;` заводит одноразовую переменную из простого обращения к свойству — правило «Инлайн одноразовых переменных» советует подставлять выражение напрямую (но при переходе на `->map()` `$currentSessionId` удобно оставить как захват в лямбде через `use`, так что этот микропункт второстепенен).

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php:233-240` (метод `sessions()`).
- **Что подтверждает проблему:** `\array_values(\array_map(static fn (AuthSession $session) => SessionResource::fromSession(...), $userSessions->all()))` поверх `AuthSessionCollection extends Collection`; в этом же диффе `GetUserSessionsHandler` для аналогичной трансформации использует методы Collection.
- **Как исправить:** заменить на `$userSessions->map(static fn (AuthSession $session) => SessionResource::fromSession(session: $session, currentSessionId: $listSessionsFilter->authSessionId))->values()->all()` (или передать сразу `CollectionResponse(...)` тем, что он принимает). Отдельный тест не нужен — поведение не меняется, существующий HTTP-тест списка покрывает результат.

### 6. Позиционные аргументы в тестовом хелпере `issuePairFor` (3 параметра)

Новый приватный хелпер `issuePairFor(UserId $userId, string $ip, string $userAgent)` в HTTP-тестах вызывается с позиционными аргументами (`$this->issuePairFor($userId, '203.0.113.10', 'CurrentAgent')`) в нескольких местах. Правило `rules.md` «Именованные аргументы: обязательны при 2+ обычных параметрах» исключений для тестового кода не делает, а здесь три параметра, причём два из них — голые строки `ip`/`userAgent`, которые легко перепутать местами.

Это не влияет на корректность тестов сейчас, но именно такие позиционные вызовы строк правило и призвано исключить (читаемость + защита от перестановки). PHPStan-правило проекта это ловит, так что без правки гейт качества может ругаться.

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `tests/Feature/Modules/Auth/Http/AuthHttpTest.php` — определение `issuePairFor()` и его вызовы (например строки 279-280, 309-310, 333).
- **Что подтверждает проблему:** `$this->issuePairFor($userId, '203.0.113.10', 'CurrentAgent')` — три позиционных аргумента, два из них строковые.
- **Как исправить:** перейти на именованные аргументы: `$this->issuePairFor(userId: $userId, ip: '203.0.113.10', userAgent: 'CurrentAgent')`.

### 7. Двойной `trim` при нормализации IP и User-Agent

`Ip::fromNullable()` сначала делает `\trim($value)`, а затем передаёт уже обрезанную строку в `KnownIp::fromString()`, которая повторно вызывает `\trim()`. Та же пара операций — в `UserAgent::fromNullable()` → `KnownUserAgent::fromString()`. На каждом приведении из БД и из запроса строка триммится дважды.

Бага здесь нет, и сам `trim` внутри публичной фабрики `KnownIp::fromString()`/`KnownUserAgent::fromString()` оправдан — фабрика вызывается и напрямую (typecast, тесты), поэтому должна сама себя защищать. Это чистая косметика: дублирование выглядит как непреднамеренное и слегка шумит. Можно оставить как есть.

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/src/Modules/Auth/Domain/ValueObject/Ip.php:22` + `KnownIp.php:20`; `app/src/Modules/Auth/Domain/ValueObject/UserAgent.php` + `KnownUserAgent.php` (аналогично).
- **Что подтверждает проблему:** `Ip::fromNullable` нормализует `$normalized = \trim($value)` и отдаёт его в `KnownIp::fromString`, где снова `$normalized = \trim($value)`.
- **Как исправить:** при желании убрать `trim` в `fromNullable` (оставив его только в фабрике `Known*`), либо наоборот — это вопрос вкуса, на поведение не влияет. Тесты не требуются.

## Рекомендации

- **Править обязательно:** —
- **На усмотрение автора:** 1, 2, 3, 4, 5, 6, 7

## Изменения после мета-ревью

Запущены `architecture-check`, `rules-check`, `plan-check`, `quality-check` (все на `sonnet`) одним batch. План указан в front matter, поэтому `plan-check` запускался. `quality-check` включён, т.к. `review.include_code_quality: true`. Кросс-CLI не запускался (режим `normal`).

### После rules-check / architecture-check / plan-check / quality-check
- **+ Добавлено:**
  - п.5 — ручной `array_map`/`array_values` вместо Collection-пайплайна в `AuthController::sessions()` (нарушение `rules.md` «Collection-пайплайны»), плюс одноразовая переменная `$currentSessionId` (rules-check, подтверждено quality-check).
  - п.6 — позиционные аргументы в тестовом хелпере `issuePairFor` (3 параметра, два строковых) — нарушение `rules.md` «Именованные аргументы» (rules-check).
  - п.7 — двойной `trim` в `Ip::fromNullable`→`KnownIp::fromString` и `UserAgent`→`KnownUserAgent` (quality-check, мелкое дублирование).
  - в п.4 добавлена ссылка на готовый хелпер `issueTokenPair()` (выпускает `SessionDevice::unknown()`) как заготовку для недостающего null-теста (plan-check, quality-check).
- **~ Изменено:**
  - п.2 (сигнатура typecast) — переформулирован: убрана неточная трактовка «риск чисто теоретический / рефлексия упадёт»; теперь это «несоответствие форме образца без практического риска для string-колонки» (консенсус architecture-check, rules-check, quality-check).
  - п.3 — статус «отклонение (несущественное)» заменён на «требование плана выполнено, но в другом файле»: план не предписывал тестировать typecast исключительно в `AuthTypecastTest`, реальный round-trip есть в `AuthRepositoryTest` (plan-check).
  - оценка 92 → 89: найдены два реальных нарушения явных правил (п.5, п.6), пропущенных первичным ревью.
- **− Убрано:** ничего.
- **Отклонено:**
  - architecture-check: «`run()` внутри `CycleTokenStorage::revokeUserSession` вместо Handler-а» — паттерн предшествует диффу (так же в `revokeSession`/`issueToken`), этим изменением не введён; по `CLAUDE.md`/`AGENTS.md` «точечные изменения» предсуществующий код не отмечаем.
  - quality-check: вынести дублирующийся `foreach delete + run` из `revokeUserSession`/`revokeSession` в приватный метод — `revokeSession` предсуществует, дублирование 3 строк, «не рефакторить не сломанное».
  - quality-check: хрупкий `assertBodyContains` по порядку полей JSON — это принятый в проекте стиль ассертов, ценность низкая.
  - quality-check: `AuthEntityTest` создаёт access/refresh с разными `SessionDevice` — это unit-тест predicates с намеренной изоляцией, доменный инвариант обеспечивает `issuePair`.
  - наблюдения architecture/quality про корректность `AuthSession::fromTokens` (reduce по `DateTimeImmutable`) — реализация корректна и типобезопасна, проблемы нет, добавлять нечего.
  - plan-check `?` про «`make qa` 100% зелёный» и rules-check `?` про PHPStan-чистоту `reduce` — невозможно подтвердить без запуска проверок; в файл ревью не выносится как проблема.

## Применённые фиксы
Отчёт: `docs/review-fixes/2026-06-16_19-50_auth-sessions-list-revoke.md`

Применены пункты 1, 4, 5, 6. Финальный гейт `make qa` зелёный (644 теста, покрытие 100%, PHPStan чист), пакет `spiral-openapi` (test + phpstan) зелёный, OpenAPI перегенерирован.

Отклонённые optional-решения (режим `apply-optional`, не поднимать повторно без новых аргументов):
- **п.2** — сигнатура typecast `string|null` оставлена: для строковых колонок `ip`/`user_agent` более широкая форма `string|\DateTimeInterface|null` не даёт практического выигрыша (само ревью это подтверждает); текущая сигнатура точнее отражает контракт колонки.
- **п.3** — прямые статические вызовы typecast в `AuthTypecastTest` оставлены: ревью само фиксирует «требование плана выполнено, но в другом файле» — реальный round-trip persist+hydrate уже покрыт в `AuthRepositoryTest`; прямые вызовы дёшевы и не являются дырой в покрытии.
- **п.7** — двойной `trim` оставлен: ревью квалифицирует это как «чистую косметику»/«вопрос вкуса»; `trim` в публичной фабрике `Known*::fromString()` оправдан (самозащита фабрики), убирать его из `fromNullable` — риск регрессии без эффекта на поведение.
