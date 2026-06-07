---
review: docs/reviews/2026-06-06_16-37_outbox-module-draft-iter2.md
date: 2026-06-06 17:05
status: done
---

# Фиксы по ревью: модуль Outbox (итерация 2)

Режим: `apply-optional`. Все три замечания помечены «на усмотрение автора» и применены
без интерактивных вопросов. Перед правками прочитаны `docs/rules.md` и `docs/arch.md`.

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Единая трактовка пустого `last_error` для всего модуля. Выбран вариант «устойчивости»: `''`/пробелы трактуются как «ошибки нет» и в typecast-слое (гидрация по id больше не падает), и в парсере сырых строк (для консистентности `''` больше не «повреждение») | `app/src/Modules/Outbox/Infrastructure/Cycle/OutboxLastErrorTypecast.php`, `app/src/Modules/Outbox/Repository/OutboxPendingRow.php` | `tests/Unit/Modules/Outbox/Infrastructure/OutboxInfrastructureEdgeTest.php` (`testLastErrorTypecastTreatsNullAndEmptyDatabaseValueAsNoError`), `tests/Unit/Modules/Outbox/Repository/OutboxPendingRowTest.php` (`testEmptyLastErrorIsTreatedAsNoError`) | ✓ применено |
| 2 | Recovery-ветка relay: логировать вход в восстановление с первопричиной (`TypecastException` класс/сообщение) на WARN; поднять лог пометки строки failed с DEBUG до WARN; если ни одной повреждённой строки не нашлось — пробросить исходное исключение, не делая вторую выборку вслепую | `app/src/Modules/Outbox/Infrastructure/OutboxRelay.php` | `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php` (`testRelayLogsRecoveryEntryAndInvalidRowAsWarning`), фикстура `tests/Feature/Modules/Outbox/Infrastructure/Fixture/RecordingOutboxLogger.php` | ✓ применено |
| 3 | Единый контракт хранения `OutboxLastError` в колонке БД. Добавлен метод `OutboxLastError::toDatabaseValue()` (Domain VO — единый источник); typecast `uncastValue()` и ручной UPDATE по сырому id теперь используют его | `app/src/Modules/Outbox/Domain/ValueObject/OutboxLastError.php`, `app/src/Modules/Outbox/Infrastructure/Cycle/OutboxLastErrorTypecast.php`, `app/src/Modules/Outbox/Repository/OutboxEventRepository.php` | `tests/Unit/Modules/Outbox/Domain/OutboxValueObjectTest.php` (assert на `toDatabaseValue()`) | ✓ применено |

### Детали решений

- **Пункт 1.** Из двух предложенных в ревью вариантов выбран первый («устойчивость»):
  `castDatabaseValue('')` → `OutboxLastError::none()`, а парсер сырых строк
  (`OutboxPendingRow::lastErrorField`) приведён к той же трактовке. Так одинаковое
  повреждение (`''` в колонке) ведёт себя согласованно во всех путях чтения —
  ORM-гидрация по id (interceptor, message loader) больше не падает, и парсер сырых
  строк больше не уводит такую запись в `InvalidOutboxPendingRow`. Удалён прежний
  кейс `testInvalidRowFromEmptyLastError` (поведение изменилось), заменён на
  `testEmptyLastErrorIsTreatedAsNoError`.

- **Пункт 3.** Единый источник вынесен в Domain VO `OutboxLastError::toDatabaseValue()`,
  а не в `OutboxLastErrorTypecast`, чтобы не нарушать правило зависимостей слоёв
  (`Repository -> Domain, Cycle ORM`; Repository не зависит от `Infrastructure`).
  И typecast, и ручной UPDATE репозитория зависят от Domain, поэтому оба используют
  один метод VO.

- **Пункт 2.** `markInvalidPendingRowsAsFailed()` теперь возвращает количество
  помеченных строк; при нуле `fetchPendingForRelay()` пробрасывает исходный
  `TypecastException` (первопричина не теряется, повторная выборка вслепую не
  выполняется). Вход в recovery и пометка строки failed логируются на WARN
  согласно правилу логирования из `docs/rules.md` (реальная проблема инфраструктуры
  данных).

## Финальная проверка

- **PHPStan:** `make phpstan` (`composer phpstan`, level max) — ✓ `No errors`
- **php-cs-fixer:** `composer cs` (dry-run по всему проекту, 377 файлов) — ✓ `Found 0 of 377 files that can be fixed`
- **Тесты:** `make test` (`phpunit`, весь сьют) — ✓ `OK` `Tests: 234, Assertions: 846`. Отдельный прогон Outbox-тестов — ✓ `Tests: 108, Assertions: 321`.
- **Покрытие (coverage):** не запускалось по явному указанию пользователя.
- **Заметки:** PHPUnit-deprecations (28 по всему сьюту, 2 в Outbox-подмножестве) —
  предсуществующие и не связаны с правками; новая фикстура `RecordingOutboxLogger`
  повторяет уже используемый в проекте паттерн `AbstractLogger`. Все тесты зелёные.
