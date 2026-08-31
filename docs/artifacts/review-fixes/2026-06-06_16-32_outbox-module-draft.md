---
review: docs/reviews/2026-06-06_16-05_outbox-module-draft.md
date: 2026-06-06 16:32
status: done
mode: apply-optional
---

# Фиксы по ревью: модуль Outbox

Применены оба типа пунктов: «править обязательно» (1) и «на усмотрение автора» (2, 3, 4, 5, 6) — режим `apply-optional`, без интерактивных вопросов.

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Репозиторий ловил исключения и помечал «битые» строки `failed` внутри себя (два запрета `docs/rules.md` + риск зацикливания relay). Detect+mark вынесены в orchestrator `OutboxRelay`; репозиторий даёт чистые методы-запросы | `app/src/Modules/Outbox/Repository/OutboxEventRepository.php`, `app/src/Modules/Outbox/Infrastructure/OutboxRelay.php`, `app/src/Modules/Outbox/Repository/OutboxPendingRow.php`, `app/src/Modules/Outbox/Repository/ValidOutboxPendingRow.php` (new), `app/src/Modules/Outbox/Repository/InvalidOutboxPendingRow.php` (new) | `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php` (+2: mark-invalid-and-keep-processing, second-run-not-recapture), `tests/Feature/Modules/Outbox/Repository/OutboxEventRepositoryTest.php` (перенос/обновление) | ✓ применено |
| 2 | Сущность дублировала трейт `HasTimestamps` и держала бизнес-дату `availableAt` без VO | `app/src/Modules/Outbox/Domain/Entity/StoredOutboxEvent.php`, `app/src/Modules/Outbox/Domain/ValueObject/OutboxAvailableAt.php` (new), `app/src/Modules/Outbox/Infrastructure/Cycle/OutboxAvailableAtTypecast.php` (new) | `tests/Unit/Modules/Outbox/Domain/OutboxValueObjectTest.php` (+round-trip `OutboxAvailableAt`) | ✓ применено |
| 3 | `OutboxLastError` выражал «нет ошибки» пустой строкой и nullable-геттером | `app/src/Modules/Outbox/Domain/ValueObject/OutboxLastError.php`, `app/src/Modules/Outbox/Infrastructure/Cycle/OutboxLastErrorTypecast.php` | `tests/Unit/Modules/Outbox/Domain/OutboxValueObjectTest.php` (обновлены проверки `value()`/`none()`) | ✓ применено |
| 4 | Завершение цикла relay через голый `\RuntimeException` | `app/src/Modules/Outbox/Infrastructure/OutboxRelayWorker.php`, `app/src/Modules/Outbox/Infrastructure/OutboxRelayStoppedException.php` (new) | `tests/Unit/Modules/Outbox/Infrastructure/OutboxRelayWorkerTest.php` (ожидание типизированного исключения) | ✓ применено |
| 5 | Внутренний DTO строки хранил ассоциативный массив `mixed` | `app/src/Modules/Outbox/Repository/OutboxPendingRow.php` + `ValidOutboxPendingRow`/`InvalidOutboxPendingRow` | `tests/Unit/Modules/Outbox/Repository/OutboxPendingRowTest.php` (переписан под типизированный Valid/Invalid API) | ✓ применено |
| 6 | Raw-операции query builder без обоснования причины | `app/src/Modules/Outbox/Repository/OutboxEventRepository.php` (комментарии у `markQueuedIfPublishing`, `markRowFailedById`, `pendingForRelayRows`) | — (документирующее) | ✓ применено |

## Детали решений

- **Пункт 1.** `OutboxEventRepository` теперь предоставляет чистые запросы: `findPendingForRelay()` (ORM Select без try-catch и без восстановления), `pendingForRelayRows()` (типизированный парсинг сырых строк), `markRowFailedById()` (атомарный CAS-переход `failed` по id). Решение «строка битая → `failed`» и `try { ... } catch (TypecastException)` живут в `OutboxRelay` (инфраструктурный orchestrator, держит транзакцию `claimForRelay()`). Добавлена явная защита от зацикливания: после пометки `failed` строка выпадает из pending/publishing, повторная выборка её не захватывает (покрыто тестом `testRelaySecondRunDoesNotRecaptureFailedInvalidRow`).
- **Пункт 2.** `StoredOutboxEvent` использует `use HasTimestamps;` (ручные `createdAt`/`updatedAt`/`initializeTimestamps()`/`touch()` удалены). Введён VO `OutboxAvailableAt` (обязательная дата) с typecast `OutboxAvailableAtTypecast`; сигнатуры доменных методов на границе принимают `\DateTimeImmutable` и оборачивают в VO внутри Entity.
- **Пункт 3.** `OutboxLastError::value()` всегда возвращает `string`; пустота читается через `isEmpty()`. Typecast при пустом VO пишет `NULL` (колонка nullable, пустая строка валидацией запрещена).
- **Пункт 4.** Введён типизированный `OutboxRelayStoppedException` (`extends \DomainException`) вместо голого `\RuntimeException` в guard-ветке `runLoop(): never`.
- **Пункт 5.** `OutboxPendingRow` стал абстрактным результатом парсинга с двумя реализациями: `ValidOutboxPendingRow` (типизированные VO/enum поля) и `InvalidOutboxPendingRow` (сырой id + `OutboxLastError`). Сырой `array<string, mixed>` больше не хранится как состояние объекта; парсинг не мутирует данные и не принимает решение `failed` — это остаётся за orchestrator-ом.
- **Пункт 6.** Добавлены короткие комментарии-обоснования у raw-операций query builder: `markQueuedIfPublishing` и `markRowFailedById` (атомарный условный CAS-переход статуса, ORM Select не выражает), `pendingForRelayRows` (чтение сырых строк до ORM-гидрации для отлова битых данных, связано с пунктом 1).

## Финальная проверка

- **PHPStan:** `make phpstan` (`composer phpstan`, level max) — ✓ No errors
- **Линтер:** `composer cs` (php-cs-fixer dry-run) — ✓ Found 0 of 376 files that can be fixed
- **Тесты:** `make test` (PHPUnit, unit + feature, без coverage по указанию) — ✓ OK, Tests: 232, Assertions: 837
- **Coverage:** не запускался по явному ограничению пользователя.
- **Заметки:** PHPUnit показывает 28 pre-existing deprecation-предупреждений (framework-метадата), не связанных с правками; на статус прогона не влияют (suite завершается OK).
