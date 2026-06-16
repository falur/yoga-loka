---
review: docs/reviews/2026-06-15_13-10_notifications-module-uncommitted-draft.md
date: 2026-06-15 12:52
status: done
---

# Фиксы по ревью: Модуль уведомлений (Notifications) — шестой круг, финальная верификация

Режим: `apply-optional`. Обязательное замечание №1 применено полностью. Три optional-пункта
разобраны без вопроса: все три приняты и применены (полезные и дешёвые, прямые нарушения rules.md
либо чистая гигиена дерева).

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Доменные исключения доставки лежат в `Infrastructure/Exception`, импортируются из `Presentation` (нарушение `Presentation → Infrastructure` по arch.md и `rules.md:46`). Перенести оба в `Application/Exception` | `app/src/Modules/Notifications/Application/Exception/CentrifugoPublishException.php` (перемещён + namespace + docblock), `.../Application/Exception/FcmPushFailedException.php` (перемещён + namespace + docblock), `.../Infrastructure/Centrifugo/CentrifugoClient.php` (use), `.../Infrastructure/Push/KreaitFcmPushSender.php` (use), `.../Presentation/Job/PublishRealtimeNotificationJob.php` (use), `.../Presentation/Job/SendPushNotificationJob.php` (use) | `tests/Unit/.../Centrifugo/CentrifugoClientTest.php` (use), `tests/Unit/.../Push/KreaitFcmPushSenderTest.php` (use), `tests/Feature/.../Presentation/DeliveryJobTest.php` (use) — переехали на новый namespace, прежние сценарии сохранены | ✓ применено |
| 2 | Stray-артефакт `tools/openapi/runtime/openapi-fixture.yml` (untracked, остаток старого расположения пакета) | удалена папка `tools/` целиком | — | ✓ применено |
| 3 | `->each()` вместо `foreach` для итерации с побочным эффектом (`rules.md:19`) | `app/src/Modules/Notifications/Application/Command/Notification/MarkAllNotificationsRead/MarkAllNotificationsReadHandler.php` (заменён `each(fn)` на `foreach`, убран ставший лишним `use Notification`) | покрыт существующим `NotificationUseCaseTest` (поведение не изменилось) | ✓ применено |
| 4 | Три одноразовых `private const *_FAILURE_MESSAGE` в Job-классах (`rules.md:33`) | `.../Presentation/Job/DispatchNotificationJob.php`, `.../Presentation/Job/SendPushNotificationJob.php`, `.../Presentation/Job/PublishRealtimeNotificationJob.php` (константа убрана, строка подставлена в `RetryException(reason: ...)`) | покрыты существующим `DeliveryJobTest` | ✓ применено |

## Куда перемещены исключения и почему это устраняет нарушение

- `CentrifugoPublishException` и `FcmPushFailedException` перемещены из
  `App\Modules\Notifications\Infrastructure\Exception` в
  `App\Modules\Notifications\Application\Exception`.
- Оба исключения — контрактные сигналы публичных контрактов модуля
  (`CentrifugoServiceContract`, `FcmPushSenderContract`, оба в `Application/Contract`):
  по их `isTransient()` доставочные Job решают, переводить ли сбой в `RetryException`.
  По `rules.md:46` слой исключения определяется контрактом, к которому оно относится, а не
  местом выброса → их место `Application/Exception`.
- После переноса `Presentation/Job` импортирует исключения из `Application/Exception`
  (Presentation → Application — разрешено arch.md «Правила зависимостей»). Зависимость
  Presentation → Infrastructure устранена: проверка `grep -rn "Notifications\\Infrastructure\\Exception"`
  по `app tests packages public` — пусто.
- `Infrastructure → Application` тоже не нарушается: это канонический паттерн проекта
  (`MediaFileServiceFailedException` лежит в `Media/Application/Exception`, бросается из
  инфраструктурного `S3MediaFileService`, потребляется из `ProcessMediaJob`). arch.md прямо
  фиксирует, что исключение контракта живёт в `Application/Exception`, хотя бросается
  инфраструктурной реализацией (пример `OutboxMessageLoadingException`). Размещение в
  `Application/Exception` не нарушает ни одно правило.

## Решения по optional

- **Принято №2:** удаление `tools/` — untracked, не код, остаток старого расположения пакета
  `tools/openapi/`; на файл никто не ссылается (`grep -rn "tools/openapi" app tests` — пусто,
  `git ls-files tools/` — пусто). Чистая гигиена дерева, нулевой риск регрессии.
- **Принято №3:** `each()` → `foreach` — прямое нарушение `rules.md:19` (итерация с побочным
  эффектом), дёшево, поведение сохранено; заодно убран ставший неиспользуемым `use Notification`.
- **Принято №4:** инлайн трёх одноразовых `private const` — прямое нарушение `rules.md:33`
  (одноразовая техническая строка рядом с использованием), дёшево, контракта не ломает.
- **Отклонено:** нет.

## Финальная проверка

- **Полный гейт:** `make qa` (стиль + PHPStan level max + тесты + coverage в одном PCOV-прогоне) — ✓ зелёный.
  - **Стиль (cs):** ✓
  - **PHPStan (level max):** ✓ `[OK] No errors`
  - **Тесты:** ✓ 567 тестов, 1664 ассерта, все прошли (1 не блокирующий PHPUnit Notice, не связан с правками; гейт завершился успешно).
  - **Покрытие:** ✓ 100.00% при пороге 100.00%.
- **OpenAPI:** `composer qa` включает `test-coverage`, в нём прогоняется `OpenApiGenerateCommandTest` —
  генерация openapi не сломалась. Перемещение исключений не затрагивает HTTP-слой, `public/openapi/openapi.yml`
  не меняется.
- **Заметки:** всё зелёное.
