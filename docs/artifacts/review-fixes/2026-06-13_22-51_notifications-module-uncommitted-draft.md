---
review: docs/reviews/2026-06-13_22-28_notifications-module-uncommitted-draft.md
date: 2026-06-13 22:51
status: done
---

# Фиксы по ревью: Модуль уведомлений (Notifications) — незакоммиченный diff

Режим `apply-optional`. Обязательные замечания (1, 2) исправлены полностью. По каждому
optional-замечанию (3–7) решение принято автономно: применены полезные и дешёвые, отклонены
нецелесообразные/рискованные/конфликтующие с гейтом качества.

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Удаление push-токена не проверяет владельца (IDOR) | `Repository/NotificationDeviceTokenRepository.php` (новый `findByTokenForUser`), `Application/Command/DeviceToken/RemoveNotificationDeviceToken/RemoveNotificationDeviceTokenCommand.php` (+`userId`), `RemoveNotificationDeviceTokenHandler.php` (скоуп по пользователю), `Presentation/Http/Filter/DeviceToken/RemoveNotificationDeviceTokenFilter.php` (+`authUserId`), `Presentation/Http/Controller/NotificationDeviceTokenController.php` | `Http/NotificationHttpTest.php::testRemoveDeviceTokenReturns404ForForeignUserAndKeepsToken` (1 ✓) | ✓ применено |
| 2 | Невалидный `cursor` отдаётся как 500, а не 422 | `Presentation/Http/Filter/Notification/ListNotificationsFilter.php` (`#[Assert\Uuid]` на `cursor`) | `Http/NotificationHttpTest.php::testListNotificationsReturns422ForInvalidCursor` (1 ✓) | ✓ применено |
| 3 | Репозиторий `findActiveForUser` обещает несуществующую фильтрацию | `Repository/NotificationDeviceTokenRepository.php`, `SendPushNotificationHandler.php` + вызовы в тестах | переименование вызовов в `SendPushNotificationHandlerTest`, `NotificationRepositoryTest`, `NotificationUseCaseTest` | ✓ применено (→ `findAllForUser`) |
| 4 | `NotificationActionType::__toString()` отдаёт `''` для `none()` | — | — | ✗ отклонено (на усмотрение; см. ниже) |
| 5 | `MarkAllNotificationsRead` грузит все непрочитанные в память | — | — | ✗ отклонено (на усмотрение; осознанное ограничение MVP по плану) |
| 6 | Enum `platform`/`channel` кастится вручную в Handler, а не в Filter | — | — | ✗ отклонено (на усмотрение; ломает `openapi:generate`, см. ниже) |
| 7 | `array_map`/`array_values` вместо collection-пайплайна | — | — | ✗ отклонено (на усмотрение; ломает PHPStan, см. ниже) |

## Решения по optional

### Принято

- **3 — переименование `findActiveForUser` → `findAllForUser`.** Имя обещало фильтрацию
  «активных» токенов, которой нет (в схеме нет признака состояния). Дешёвый, безопасный rename,
  убирает ложное обещание и будущую ошибку. Обновлены вызовы в коде и тестах.

### Отклонено (чтобы следующие ревьюеры не повторяли без новых аргументов)

- **4 — согласовать `__toString()` с контрактом VO.** Риск признан самим ревью «гипотетическим»:
  VO наружу ходит только через `value()`/`presentValue()`/`jsonSerialize()` (все корректно
  различают присутствие/отсутствие), в строковую интерполяцию `(string)` не попадает нигде.
  Предложенное направление правки расплывчато, а `__toString()`, бросающий исключение на `none()`,
  сам по себе антипаттерн и риск регрессии. Польза спорная при нулевом реальном вызове — не применяю.

- **5 — батчинг `MarkAllNotificationsRead`.** Ревью прямо фиксирует: план осознанно отказался от
  батчинга как ограничение MVP, «для MVP оставить как есть», комментарий-ограничение уже в коде.
  Применение расширяет scope и противоречит плану. Не применяю.

- **6 — типизировать enum прямо в Filter (`DevicePlatform`/`NotificationChannel`).** Применил, прогнал
  гейт — `openapi:generate` падает: `Ошибка генерации OpenAPI: Неизвестный тип для OpenAPI-схемы:
  App\Modules\Notifications\Domain\Enum\DevicePlatform`. Генератор `packages/spiral-openapi`
  (`Schema/SchemaBuilder::schemaForType`) поддерживает только скаляры и классы с собранными
  метаданными; BackedEnum как тип свойства Filter он не умеет и бросает исключение. Это первый
  Filter-модуль в проекте, и enum в Filter не заработает, пока в генератор не добавят поддержку
  enum-схемы (отдельная задача в переносимом пакете). Ревью прямо допускало этот вариант: «либо
  явно задокументировать сознательное отступление с причиной». Оставлен ручной `tryFrom()` в Handler
  (поведение пользователя корректно — 422 на неизвестном значении сохранён), причина зафиксирована в
  PHPDoc обоих фильтров (`RegisterNotificationDeviceTokenFilter`, `NotificationSettingUpdateInput`).
  Прежде чем повторять это замечание — сначала добавить enum-поддержку в `packages/spiral-openapi`.

- **7 — collection-пайплайн `->map()->values()->all()` вместо `array_values(array_map(...))`.**
  Применил, прогнал гейт — PHPStan (level max) падает: типизированная доменная коллекция
  (`NotificationCollection`/`NotificationDeviceTokenCollection` с `@extends Collection<int, T>`)
  фиксирует generic-тип элемента, поэтому `->map(...)` сохраняет исходный тип `T` (Notification /
  NotificationDeviceToken), а не тип результата маппинга — итог не сводится к `list<NotificationResource>`
  / `list<string>` (`should return list<...> but returns array<int, Notification/...>`). Форма
  `array_values(array_map(...))` — единственный type-safe способ получить `list<U>` другого типа
  элемента из такой коллекции. Гейт качества требует «PHPStan level max» зелёным, поэтому правило
  collection-пайплайнов здесь уступает. Оставлено `array_values(array_map(...))` с поясняющим
  комментарием в `SendPushNotificationHandler::tokenValues()`. Прежде чем повторять — нужно решение
  на уровне типизации доменных коллекций (например, generic-`map` с переопределением типа результата).

## Сопутствующее

- Перегенерирован `public/openapi/openapi.yml` через `openapi:generate`: в working-tree спека ещё не
  содержала ни одного эндпоинта модуля Notifications. Добавлены все 9 операций (включая
  `/notifications` с query-параметром `cursor`). Это снимает падение
  `OpenApiGenerateCommandTest::testGenerateCommandUsesCurrentTranslatorLocale`, которое
  возникало из-за отсутствия/несвежести артефакта и при попытке применить optional-6.

## Финальная проверка

- **Тесты:** `make qa` (Unit+Kernel+Feature, 560 тестов, 1654 ассерта) — ✓ зелёные.
- **Покрытие:** 100.00% при пороге 100% — ✓.
- **PHPStan (level max):** `[OK] No errors` — ✓.
- **Code style:** ✓.
- **Заметки:** 1 PHPUnit Notice (deprecation, помечен `N`) — пред-существующий, гейт не валит.
