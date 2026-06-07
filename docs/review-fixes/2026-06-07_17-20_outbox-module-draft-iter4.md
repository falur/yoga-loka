---
review: docs/reviews/2026-06-07_16-52_outbox-module-draft-iter4.md
date: 2026-06-07 17:20
status: done
mode: apply-optional
---

# Фиксы по ревью: Модуль Outbox (итерация 4)

Режим `apply-optional`: применены все три пункта «на усмотрение автора» без интерактивных
вопросов. Обязательных к правке пунктов в ревью не было; пунктов «не править» не было.

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | recovery считал распознанную битую строку прогрессом даже при CAS, затронувшем 0 строк (`markRowFailedById` возвращал `void`, `failedRowsCount++` безусловен) | `app/src/Modules/Outbox/Repository/OutboxEventRepository.php`, `app/src/Modules/Outbox/Infrastructure/OutboxRelay.php` | `tests/Feature/.../Repository/OutboxEventRepositoryTest.php::testMarkRowFailedByIdReturnsAffectedRowCount`, `tests/Feature/.../Infrastructure/OutboxRelayTest.php::testRecoveryRethrowsOriginalErrorWhenNoInvalidRowIsMarkedFailed` | ✓ применено |
| 2 | тайминги claim-lease и publish-backoff (60s) захардкожены, тогда как остальные relay-пороги вынесены в `OutboxConfig`/env | `OutboxConfig.php`, `app/config/outbox.php`, `.env.sample`, `OutboxRelay.php` + тесты маппинга/binding | `SimpleConfigMapperTest`, `SimpleConfigBindingTest`, `OutboxRelayWorkerTest`, `OutboxQueueStatusInterceptorFailureTest`, `OutboxRelayTestHelpers` | ✓ применено |
| 3 | `findFreshStatusById` — доп. SELECT на горячем пути; нужен поясняющий комментарий | `app/src/Modules/Outbox/Infrastructure/OutboxRelay.php` (`syncJobAlreadyRecordedFailure`) | — (комментарий, поведение не меняется) | ✓ применено |

### Детали по пункту 1 (bug/robustness)

- `OutboxEventRepository::markRowFailedById` теперь возвращает `int` — число реально затронутых
  строк (как `markQueuedIfPublishing`). Возврат `->run()` Cycle Query Builder отдаёт affected rows.
- `OutboxRelay::markInvalidPendingRowsAsFailed` инкрементирует `failedRowsCount` только когда CAS
  реально затронул строку (`$markedRowsCount !== 0`). Если CAS затронул 0 строк (id не совпал или
  гонка статуса увела строку из pending/publishing), это больше не считается прогрессом: пишется
  WARN «не смог пометить повреждённую строку failed» (инцидент виден, а не молча копится), и строка
  пропускается. Корректность recovery сохранена: при `failedRowsCount === 0` исходный
  `TypecastException` пробрасывается наверх вместо повторной падающей выборки — relay не зацикливается
  и не жжёт лимит подряд идущих ошибок.
- Тесты на сценарий «CAS затронул 0 строк не считается прогрессом»:
  - Repository-уровень (точный контракт affected rows): `markRowFailedById` возвращает `1` для
    совпадающей pending-строки, `0` для повторной пометки уже-failed строки и `0` для несовпадающего id.
  - Relay-уровень (поведение recovery end-to-end): при гонке статуса recovery не помечает ни одной
    строки (`failedRowsCount` остаётся 0) и пробрасывает исходный `TypecastException`, не зацикливаясь;
    проверяется, что лог «пометил повреждённую строку failed» НЕ был записан, а «вошёл в восстановление»
    был. Точечный «recognized-but-CAS=0» относительно одной строки внутри одного прогона недостижим без
    подмены `final` репозитория, поэтому контракт affected rows покрыт на repository-уровне.

### Детали по пункту 2 (quality/consistency)

- В `OutboxConfig` добавлены два поля `int $claimTimeoutSeconds`, `int $publishRetryDelaySeconds`
  (по образцу остальных relay-порогов — плоский `int`, который relay читает напрямую).
- `app/config/outbox.php`: `OUTBOX_CLAIM_TIMEOUT_SECONDS` и `OUTBOX_PUBLISH_RETRY_DELAY_SECONDS`
  с `\max(1, (int) \env(..., 60))`-нормализацией, как у соседних значений.
- `.env.sample`: добавлены `OUTBOX_CLAIM_TIMEOUT_SECONDS=60`, `OUTBOX_PUBLISH_RETRY_DELAY_SECONDS=60`.
- `OutboxRelay`: удалены `private const CLAIM_TIMEOUT_SECONDS`/`PUBLISH_RETRY_DELAY_SECONDS`, значения
  читаются из `$this->outboxConfig->claimTimeoutSeconds` / `->publishRetryDelaySeconds`. Комментарий о
  двух разных доменах времени сохранён и обновлён со ссылкой на поля конфига.
- Тесты: `SimpleConfigMapperTest` (маппинг + invalid-provider), `SimpleConfigBindingTest` (значения из
  реального конфигуратора = 60/60), плюс обновлены все прочие конструкторы `OutboxConfig` в тестах и
  фикстурах (`OutboxRelayWorkerTest`, `OutboxQueueStatusInterceptorFailureTest`, `OutboxRelayTestHelpers`).

### Детали по пункту 3 (quality/readability)

- Перед ранним возвратом по `usesSyncConnection()` в `syncJobAlreadyRecordedFailure` добавлен
  комментарий: лишний SELECT через `findFreshStatusById` выполняется ТОЛЬКО для sync-драйвера; в проде
  очередь async (RabbitMQ), проверка отсекается без запроса; явное указание «не выносить
  `findFreshStatusById` за пределы sync-гарда».

## Финальная проверка

- **php-cs-fixer:** `composer cs` (dry-run) — ✓ `Found 0 of 383 files that can be fixed` (после
  `composer cs:fix`, который отформатировал новый тест).
- **PHPStan:** `composer phpstan` — ✓ `[OK] No errors`.
- **Тесты (точечные Outbox Unit + затронутые Feature, без coverage):** ✓ 56 тестов, 242 assertions, 0
  failures. Прогнаны:
  - Feature: `OutboxRelayTest`, `OutboxEventRepositoryTest`, `OutboxQueueStatusInterceptorFailureTest`.
  - Unit: `OutboxRelayWorkerTest`, `SimpleConfigMapperTest`, `SimpleConfigBindingTest`, `ConfigShapeTest`.
- **Заметки:** 12 PHPUnit-deprecation предупреждений — пред-существующие (в SimpleConfig*/ConfigShape
  тестах), не связаны с правками; репозиторный тест прогнан отдельно — 9 тестов, 31 assertion, без
  deprecations. QA / ручные тесты / coverage не запускались по ограничению задачи.

## Состояние git

Не закоммичено. Изменённые в этой сессии файлы:
- `app/src/Modules/Outbox/Infrastructure/OutboxRelay.php`
- `app/src/Modules/Outbox/Repository/OutboxEventRepository.php`
- `app/src/Shared/Infrastructure/Configuration/Outbox/OutboxConfig.php`
- `app/config/outbox.php`
- `.env.sample`
- `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php`
- `tests/Feature/Modules/Outbox/Repository/OutboxEventRepositoryTest.php`
- `tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorFailureTest.php`
- `tests/Feature/Modules/Outbox/Infrastructure/Fixture/OutboxRelayTestHelpers.php`
- `tests/Unit/Modules/Outbox/Infrastructure/OutboxRelayWorkerTest.php`
- `tests/Unit/Shared/Infrastructure/Configuration/SimpleConfigBindingTest.php`
- `tests/Unit/Shared/Infrastructure/Configuration/SimpleConfigMapperTest.php`
</content>
</invoke>
