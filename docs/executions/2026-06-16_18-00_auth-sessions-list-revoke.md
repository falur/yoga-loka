---
plan: docs/plans/2026-06-16_16-36_auth-sessions-list-revoke.md
started: 2026-06-16 18:00
finished: 2026-06-16 18:40
status: done
---

# Журнал: Вывод сессий и отзыв конкретной сессии в модуле Auth

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Доменная модель устройства + миграция | VO Ip/KnownIp/UnknownIp/UserAgent/KnownUserAgent/UnknownUserAgent/SessionDevice, IpTypecast/UserAgentTypecast, AuthToken (колонки ip/user_agent + device в issue), миграция add_device_to_auth_tokens | AuthValueObjectTest, AuthTypecastTest, AuthEntityTest, AuthRepositoryTest (round-trip persist+hydrate known/unknown) | ✅ |
| 2 | Захват устройства в выдаче токенов | AuthTokenStorageContract, CycleTokenStorage (issuePair/rotate с SessionDevice, create→unknown), фильтры verify/register/refresh (RemoteAddress+Header), команды+handlers verify/resolve/register/refresh, AuthController | CycleTokenStorageTest (device на обоих токенах, ротация берёт device запроса), AuthHttpTest (verify сохраняет IP+UA) | ✅ |
| 3 | GET /api/v1/auth/sessions | findActiveByUserId, AuthSession+AuthSessionCollection, GetUserSessionsQuery/Handler, SessionResource, ListSessionsFilter, AuthController::sessions | AuthSessionTest, GetUserSessionsHandlerTest (2 сессии, истёкший access+живой refresh, пусто), AuthHttpTest (список+current, 401) | ✅ |
| 4 | DELETE /api/v1/auth/sessions/<sessionId> | findByUserAndSessionForUpdate, revokeUserSession (контракт+CycleTokenStorage), RevokeUserSessionCommand/Handler, RevokeSessionFilter, AuthController::revokeSession, переводы session_not_found (ru/en) | RevokeUserSessionHandlerTest (своя/чужая/неизвестная/не-UUIDv7), CycleTokenStorageTest, AuthHttpTest (204+исчезла из списка, 404, 401) | ✅ |
| 5 | OpenAPI path-параметры + финальная проверка | SpecBuilder (нормализация `<name>`→`{name}`), фикстура+тест пакета, AuthOpenApiGenerationTest, перегенерация openapi.yml | OpenApiGeneratorTest (path-param), AuthOpenApiGenerationTest (`/auth/sessions`, `'/auth/sessions/{sessionId}'`, нет `<sessionId>`) | ✅ |

## Заметки
- Вход скила указывал на `_review.md` (ревью плана), сам план — `docs/plans/2026-06-16_16-36_auth-sessions-list-revoke.md`; все 7 замечаний ревью уже внесены в план (раздел «Реакция на ревью»).
- Место выполнения: текущая ветка `main` (выбор пользователя).
- Тестовая БД строится миграциями (`docker/test/migrate-test-databases.sh`), Cycle schema кэшируется из аннотаций в warmup — миграция `add_device_to_auth_tokens` обязательна.
- `ValueObjectCast::uncastFieldByRule` зовёт `uncastValue($value)` уже с объектом-VO; `castDatabaseValue` для string-колонки получает `string|null` — типизирую соответственно (как `ConsumptionTypecast`).
- OpenAPI-генератор: `#[RemoteAddress]`/`#[Header]` → `SOURCE_NONE`, в тело запроса не попадают (как существующий `#[Attribute]` в `LogoutFilter`) — внешний контракт verify/register/refresh не меняется.
- Query-хендлер группирует токены через базовый `Illuminate\Support\Collection` (обёртка `new Collection($tokens->all())`), чтобы `groupBy` не ломал PHPStan-дженерики `AuthTokenCollection`; наружу — `AuthSessionCollection`.

## Изменения в docs
- Правила/архитектуру менять не потребовалось: реализация уложилась в рамку `docs/rules.md`/`docs/arch.md`, а решения (read-model `AuthSession` в Application, `Ip/UserAgent` без `Stringable`, IP из `REMOTE_ADDR`, фикс `SpecBuilder`) уже зафиксированы в самом плане.
- Замечания для возможного будущего `eda-docs`: это первый в проекте `CollectionResponse` с данными и первый роут с path-параметром. Маппинг коллекции в `list<T>` для `CollectionResponse` сделан через `\array_values(\array_map(...))` над `->all()`, потому что у типизированной коллекции (`AuthSessionCollection`) `->map()->all()` теряет дженерик элемента и не выводит `list<T>` под PHPStan strict — это потенциальный кандидат в `docs/code-examples.md`.

## Финальная проверка
Команда: `make qa` + проверки пакета `spiral-openapi`.

- `make qa` (cs + PHPStan + тесты с покрытием): **зелёный**.
  - php-cs-fixer: ошибок нет (4 файла отформатированы `composer cs:fix` — `fn (`→`fn(`).
  - PHPStan приложения (level max): No errors.
  - Тесты: 643 passed, Assertions 2137. Покрытие **100.00%** = порог 100% (PCOV).
  - 1 PHPUnit Notice — предсуществующий, в не-Auth тесте, сборку не валит.
- `composer -d packages/spiral-openapi phpstan`: No errors.
- `composer -d packages/spiral-openapi test`: OK (30 тестов, 571 assertion).
- `php app.php openapi:generate`: `public/openapi/openapi.yml` перегенерирован — 8 операций; добавлены `/auth/sessions` (GET) и `'/auth/sessions/{sessionId}'` (DELETE, фигурные скобки), `SessionResource` с nullable `ip`/`device`.

Зелёный гейт получен без подавления проверок: все ошибки PHPStan (32 шт. — именованные аргументы и `string|null` у `Known*`) и cs устранены по сути.
