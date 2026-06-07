---
review: docs/reviews/2026-06-06_17-01_outbox-module-draft-iter3.md
date: 2026-06-06 17:28
status: done
mode: apply-optional
---

# Фиксы по ревью: модуль Outbox (итерация 3)

Режим `apply-optional`: все 6 замечаний «на усмотрение автора» применены без
интерактивных вопросов. Архитектурные решения модуля (плоская структура `Command/`,
relay как инфраструктурный orchestrator, единый источник `last_error` в Domain VO)
сохранены без изменений.

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Реальные инфраструктурные сбои publish/финального failed на DEBUG вместо WARN/ERROR | `app/src/Modules/Outbox/Infrastructure/OutboxRelay.php` (publish: WARN при возврате в Pending, ERROR при переходе в Failed), `app/src/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptor.php` (recordJobFailure: DEBUG при retry→Queued, ERROR при переходе в Failed) | `OutboxRelayPublishTest` (2 ✓: WARN-pending, ERROR-final), `OutboxQueueStatusInterceptorFailureTest` (2 ✓: DEBUG-retry, ERROR-final) | ✓ применено |
| 2 | Recovery-выборка повреждённых строк не ограничена размером пачки | `app/src/Modules/Outbox/Repository/OutboxEventRepository.php` (`pendingForRelayRows` принимает `OutboxRelayBatchSize` и применяет `->limit(...)`), `app/src/Modules/Outbox/Infrastructure/OutboxRelay.php` (проброс batchSize в `markInvalidPendingRowsAsFailed`) | `OutboxEventRepositoryTest::testPendingForRelayRowsLimitsResultByBatchSize` (1 ✓) | ✓ применено |
| 3 | Валидация `limit` разнесена по слоям, `sleep` без нижней границы (busy-spin при `--sleep=0`) | новый VO `app/src/Modules/Outbox/Domain/ValueObject/OutboxRelaySleepSeconds.php` (MIN=1), VO создаётся в `RelayOutboxHandler`, sleep вынесен в `OutboxRelaySleeperContract`/`SystemOutboxRelaySleeper` | `OutboxValueObjectTest::testRelaySleepSecondsRejectsBusySpinZero` (1 ✓), `OutboxRelayWorkerTest` (sleep через recording sleeper) | ✓ применено |
| 4 | Claim-lease и retry-backoff используют одну колонку `available_at` | `app/src/Modules/Outbox/Infrastructure/OutboxRelay.php` (комментарий о двойном назначении `available_at` рядом с обеими константами) | — (по ревью тесты не требуются) | ✓ применено |
| 5 | Recovery-выборка возвращает голый `array` вместо типизированной коллекции | новый `app/src/Modules/Outbox/Repository/OutboxPendingRowCollection.php`, `pendingForRelayRows()` возвращает его | `OutboxEventRepositoryTest` (assertInstanceOf коллекции), `OutboxRelayTest` (isEmpty на коллекции) | ✓ применено |
| 6 | `RelayOutboxCommand` несёт VO `OutboxRelayBatchSize`, `sleepSeconds` — примитив | `RelayOutboxCommand.php` (примитивы `int $batchSize`, `int $sleepSeconds`), VO создаются в `RelayOutboxHandler.php`, `OutboxRelayCommand.php` оставлена только граница mixed→int | `OutboxRelayCommandTest` (обновлён worker-fixture и ассерты на VO) | ✓ применено |

### Детали реализации

- **Замечание 1.** Уровень лога привязан к фактическому переходу статуса
  (`isFinal()`), а не к типу исключения — как и просило ревью (RetryException на
  последней попытке тоже даёт failed). Переход в `Failed` (нарушение инварианта
  доставки) → ERROR; возврат в `Pending`/`Queued` (событие остаётся в обороте) →
  WARN для реальной ошибки push и DEBUG для штатного retry.
- **Замечания 3 и 6 связаны.** `RelayOutboxCommand` теперь несёт только примитивы;
  оба VO (`OutboxRelayBatchSize`, `OutboxRelaySleepSeconds`) создаются в
  `RelayOutboxHandler`. Граница консоли (`integerArgument`/`integerOption`) оставлена
  как осознанное сужение `mixed`→`int`, смысловая валидация диапазона делегирована VO.
- **Замечание 3, дополнительно.** Чтобы зафиксировать запрет busy-spin на уровне
  входа (MIN=1) и при этом сохранить быстрые тесты без реальных `sleep`, паузы
  вынесены за абстракцию `OutboxRelaySleeperContract` (реализация
  `SystemOutboxRelaySleeper`, зарегистрирована в `OutboxBootloader`). Это в одном
  ряду с уже существующим `OutboxRelayLoopControlContract`, который введён ровно для
  тестируемости цикла.
- **Контракт изменён.** `OutboxRelayWorkerContract::runLoop()` теперь принимает
  `OutboxRelaySleepSeconds` вместо `int`; добавлен конструкторный параметр
  `OutboxRelaySleeperContract` у `OutboxRelayWorker`.

## Финальная проверка

- **php-cs-fixer:** `composer cs:fix` — ✓ (Fixed 0 of 381 files, стиль чистый)
- **PHPStan (level max):** `composer phpstan` — ✓ (No errors)
- **Тесты:** `make test` — ✓ (240 тестов, 868 ассертов, OK)
- **Coverage:** не запускался по явному ограничению пользователя.
- **Заметки:** 28 PHPUnit-deprecations — преэкзистинговые, не связаны с правками
  (рассеяны по сторонним тестам, не из новых outbox-тестов).

## Git

Не закоммичено. Изменения только в модуле Outbox и его тестах + новый отчёт.
Незакоммиченное состояние git показывает старые вложенные пути модуля как `AD`
(результат уже идущего на ветке рефакторинга в плоскую структуру) и новые плоские
файлы как `??` — это преэкзистинговое состояние индекса, не внесённое этими фиксами.
Реальные файлы на диске лежат только по плоским путям, дублей нет.
