---
review: docs/reviews/2026-05-27_17-56_outbox-rabbitmq-uncommitted.md
date: 2026-05-27 19:25
status: done
---

# Фиксы по ревью: outbox RabbitMQ

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Контракт `outbox:relay --loop` и `--sleep` не был покрыт тестами | `tests/Feature/Modules/Outbox/Console/OutboxRelayCommandTest.php` | `make test`, `make qa` | применено |
| 2 | Официальная Composer QA-команда запускалась в dev-контейнере и падала | `Makefile`, `docker/test/run-qa.sh`, `composer.json`, `docker/Dockerfile`, `AGENTS.md`, `app/config/cycle.php`, `app/src/Shared/Infrastructure/Cycle/LazyGhostMapper.php`, `app/src/Shared/Infrastructure/Cycle/LazyGhostEntityFactory.php`, `app/src/Shared/Infrastructure/Cycle/LazyGhostPendingRelationReference.php`, `app/src/Shared/Infrastructure/Cycle/LazyGhostPendingRelationReferenceCollection.php` | `make qa` | применено |
| 3 | Заголовки очереди были вынесены в публичный helper с `array<string, mixed>` | `app/src/Modules/Outbox/Application/Outbox/OutboxQueueHeaders.php`, `app/src/Modules/Outbox/Presentation/Job/OutboxDebugLogJob.php`, `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueStatusInterceptor.php` | `make test`, `make qa` | применено |
| 4 | Repository читал свежий статус через Query Builder без явного основания | `app/src/Modules/Outbox/Repository/OutboxEventRepository.php`, `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelay.php`, `tests/Feature/Modules/Outbox/Repository/OutboxEventRepositoryTest.php` | `make test`, `make qa` | применено |
| 5 | `OutboxRelay` смешивал подготовку queue payload, push и управление статусами | `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelay.php`, `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueuePublisher.php`, `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php` | `make test`, `make qa` | применено |

## Финальная проверка

- **Форматирование:** `make shell CMD="composer cs:fix"` — успешно.
- **Тесты:** `make test` — успешно, 160 тестов, 636 проверок, 28 PHPUnit deprecations.
- **PHPStan:** `make phpstan` — успешно.
- **Composer QA:** `make qa` — успешно; включает `composer cs`, `composer phpstan`, `composer test`, `composer test-coverage`.
- **Заметки:** `make qa` сначала выявил старую несовместимость Cycle proxy с `final` Entity и `private(set)` свойствами при coverage-прогоне. Исправлено переносом подхода из соседнего проекта: `LazyGhostMapper` на базе PHP lazy ghost objects.
