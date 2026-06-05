---
review: docs/reviews/2026-05-26_13-48_outbox-rabbitmq-all.md
date: 2026-05-26 14:34
status: done
---

# Фиксы по ревью: Ревью всех текущих изменений outbox RabbitMQ

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Relay может перезаписать `handled` обратно в `queued` | `app/src/Modules/Outbox/Repository/OutboxEventRepository.php`, `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelay.php` | `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php` | ✓ применено |
| 2 | Для `SKIP LOCKED` не выбран единый контракт | `docs/plans/2026-05-24_17-37_outbox-rabbitmq.md`, `docs/rules.md`, `docs/arch.md`, `README.md`, `docker/README.md` | `make test`, `make phpstan` | ✓ применено |
| 3 | Presentation-слой зависит от Infrastructure-слоя | `app/src/Modules/Outbox/Application/Command/Outbox/RelayOutbox/*`, `app/src/Modules/Outbox/Application/Contract/OutboxRelayWorkerContract.php`, `app/src/Modules/Outbox/Application/Outbox/OutboxQueueHeaders.php`, `app/src/Modules/Outbox/Presentation/Console/OutboxRelayCommand.php`, `app/src/Modules/Outbox/Presentation/Job/OutboxDebugLogJob.php` | `tests/Feature/Modules/Outbox/Console/OutboxRelayCommandTest.php`, `tests/Unit/Modules/Outbox/Presentation/Job/OutboxDebugLogJobTest.php` | ✓ применено |
| 4 | Outbox feature-тесты зависят от порядка запуска | `tests/Feature/Modules/Outbox/CleansOutboxEvents.php`, `tests/Feature/Modules/Outbox/Console/OutboxRelayCommandTest.php`, `tests/Feature/Modules/Outbox/Repository/OutboxEventRepositoryTest.php`, `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php`, `tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorTest.php` | `make test` | ✓ применено |

## Детали

- `queued` теперь выставляется атомарным `update()` с условием `id = ...` и `status = publishing`.
- После успешного `push()` relay больше не сохраняет старую Entity через `persist()`.
- Для `SKIP LOCKED` выбран контракт одного постоянного relay-процесса, потому что проект запрещает ручной SQL, а Cycle ORM Select не даёт отдельный API для `SKIP LOCKED`.
- `OutboxRelayCommand` теперь создаёт Application Command и вызывает Handler через `CommandBusInterface`.
- Контракт headers перенесён в Application-слой.
- Общая очистка `outbox_events` вынесена в trait и используется всеми outbox feature-тестами.

## Финальная проверка

- **Тесты:** `make test` — ✓ 154 теста, 614 assertions, 28 PHPUnit deprecations.
- **Линтер:** `make phpstan` — ✓ No errors.
- **Заметки:** первый прогон `make test` нашёл устаревшие ожидания тестов после прямого DB update; тесты поправлены, финальный полный прогон зелёный.
