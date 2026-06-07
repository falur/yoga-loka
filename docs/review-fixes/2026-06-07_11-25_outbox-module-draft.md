---
review: docs/reviews/2026-06-07_10-09_outbox-module-draft.md
date: 2026-06-07 11:25
status: done
mode: apply-optional
---

# Фиксы по ревью: модуль Outbox

Режим `apply-optional`: все 7 замечаний помечены «на усмотрение автора», применяются
без интерактивных вопросов. Перед правками прочитаны `docs/rules.md` и `docs/arch.md`,
правки сделаны в их рамках.

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | DTO валидной pending-строки удерживает неиспользуемые поля | `app/src/Modules/Outbox/Repository/ValidOutboxPendingRow.php`, `app/src/Modules/Outbox/Repository/OutboxPendingRow.php` | `tests/Unit/Modules/Outbox/Repository/OutboxPendingRowTest.php` | ✓ применено |
| 2 | Контракт паузы relay принимает примитив вместо VO + недостижимый guard | `app/src/Modules/Outbox/Application/Contract/OutboxRelaySleeperContract.php`, `app/src/Modules/Outbox/Infrastructure/SystemOutboxRelaySleeper.php`, `app/src/Modules/Outbox/Infrastructure/OutboxRelayWorker.php` | `tests/Unit/Modules/Outbox/Infrastructure/OutboxRelayWorkerTest.php` | ✓ применено |
| 3 | В target попало глобальное изменение mapper-а ORM (`cycle.php`) | — | — | ✗ пропущено (см. ниже) |
| 4 | Порядок consume-interceptor-ов держится на ручной синхронизации без rationale | `app/config/queue.php`, `app/src/Modules/Outbox/README.md` | — | ✓ применено (rationale) |
| 5 | Recovery держится на согласованности двух реализаций разбора строки | `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php` | `OutboxRelayTest::testManualParserAndOrmTypecastAgreeOnSameRows` | ✓ применено (тест-согласованность) |
| 6 | Повреждённая строка с нестроковым `id` молча пропускается recovery | `app/src/Modules/Outbox/Repository/OutboxPendingRow.php`, `app/src/Modules/Outbox/Repository/OutboxEventRepository.php` | `OutboxPendingRowTest` (новые кейсы non-string/null/array id) | ✓ применено |
| 7 | `OutboxRelay::publish()` игнорирует переданное в `relay()` время | `app/src/Modules/Outbox/Infrastructure/OutboxRelay.php` | покрыто существующими `OutboxRelayPublishTest` (детерминированный `$now`) | ✓ применено |

## Детали по каждому пункту

### 1 — Валидная pending-строка сведена к маркеру
`ValidOutboxPendingRow` стал пустым маркером «строка распарсилась». Полный разбор полей
оставлен в `OutboxPendingRow::fromDatabaseRow` как зонд порчи (вынесен в приватный
`assertRowParses`), но разобранные значения больше не удерживаются — поведение их не
читает. Парсерный тест переписан на различение валидной/битой строки вместо чтения
неиспользуемых полей.

### 2 — Контракт паузы переведён на VO, недостижимый guard удалён
Выбран вариант «доменный тип вместо примитива» (консистентно с правилом «явные
доменные типы вместо примитивов»). `OutboxRelaySleeperContract::sleep()` и
`SystemOutboxRelaySleeper` принимают `OutboxRelaySleepSeconds`; недостижимый guard
`$seconds <= 0` удалён (минимум 1с гарантирует VO). `calculateRetryDelaySeconds`
возвращает `OutboxRelaySleepSeconds` (диапазон ретрая 1..30 укладывается в VO).
Диапазоны проверены: VO допускает 1..3600.

### 3 — Пропущено осознанно
Замечание чисто организационное: «вынести изменение `cycle.php` в отдельный
коммит/ревью». Это решается на этапе коммита (`eda-commit`), а данный скил коммиты не
делает. Сам `LazyGhostMapper` не дефектен — кодовой правки в рамках fix-by-review нет.

### 4 — Rationale про порядок interceptor-ов
README дополнен объяснением, почему `RetryPolicyInterceptor` обязан стоять ниже
(внутри) `OutboxQueueStatusInterceptor`, и какой будет мис-классификация при
перестановке. Тот же rationale добавлен комментарием прямо в `app/config/queue.php`
рядом с массивом `consume`, где порядок и задаётся.

### 5 — Тест согласованности двух разборов
Добавлен feature-тест `testManualParserAndOrmTypecastAgreeOnSameRows`: на одном наборе
(валидная + повреждённая строка) ручной парсер возвращает по одному
`ValidOutboxPendingRow`/`InvalidOutboxPendingRow`, а ORM-гидрация (`findPendingForRelay`)
падает `TypecastException` на той же повреждённой строке. Фиксирует, что разборы не
расходятся и recovery не зациклит relay (`failedRowsCount` не останется 0). Полное
сведение к одному источнику истины не делалось: ORM Select не умеет изолировать битую
строку, поэтому ручной парсер существует намеренно; ревью предлагает тест как
низкорисковую альтернативу.

### 6 — Нестроковый `id` представляется как InvalidOutboxPendingRow
`fromDatabaseRow` больше не возвращает `null`: при нестроковом `id` возвращает
`InvalidOutboxPendingRow` с id, приведённым к строке (`is_scalar` → `(string)`, иначе
`get_debug_type`), и понятной ошибкой. Молчаливый пропуск `if ($pendingRow !== null)`
в репозитории удалён. Теперь такая строка попадает в `failedRowsCount` и помечается
failed, relay не зацикливается. Добавлены кейсы для скалярного, `null` и `array` id.

### 7 — Единый источник времени в relay
`$now` из `relay()` прокидывается в `publish()`; собственный
`$now = new \DateTimeImmutable()` в `publish()` удалён. Захват и публикация одного
прогона теперь используют одно время; существующие `OutboxRelayPublishTest` фиксируют
время через аргумент `relay($now)`.

## Финальная проверка

Команда: `make qa` (миграции + `composer cs` + `composer phpstan` + `composer test` +
`composer test-coverage` со 100%-гейтом).

- **Стиль (`composer cs`, PHP CS Fixer):** ✓ прошёл (после правки пробела `fn (` → `fn(`).
- **PHPStan (`composer phpstan`, level max):** ✓ `[OK] No errors`.
- **Тесты (`composer test`):** ✓ 244 теста, 875 ассертов, все зелёные (28 PHPUnit
  deprecations — предсуществующие, не валят прогон).
- **Покрытие (100%-гейт):** ✗ 92.10% < 100%.

### Заметки по покрытию

Гейт 100% не закрыт, но это **предсуществующий долг WIP-ветки**, не регрессия от этих
фиксов. Основной дефицит — нетронутые мной классы Shared-инфраструктуры
(`LazyGhostMapper` ~50%, `ValueObjectCast` ~25%, `LazyGhostEntityFactory`,
`ExceptionHandlerBootloader`, `LazyGhostReflectionRegistry`). По изменённым outbox-файлам
покрытие почти полное; остались две объективно нетестируемые строки, существовавшие до
правок: реальный вызов `\sleep()` в `SystemOutboxRelaySleeper` и ветка повторного
`throw` recovery в `OutboxRelay` при `failedRowsCount === 0`. Добавление теста на
`\DateTime`-ветку парсера слегка подняло покрытие (92.05% → 92.10%).
</content>
</invoke>
