---
review: docs/reviews/2026-06-05_17-15_outbox-draft-review.md
date: 2026-06-05 17:42
status: failed-checks
---

# Фиксы по ревью: Черновое ревью outbox и QA-изменений

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | QA останавливался на PHPStan из-за result cache | `composer.json` | `make phpstan` | применено |
| 2 | QA не проверял обязательный порог покрытия 100% | `composer.json`, `docker/test/assert-coverage.php` | `make qa` | применено, проверка теперь падает при покрытии ниже 100% |
| 3 | Доменный `OutboxEventType` зависел от Application-слоя | `app/src/Modules/Outbox/Domain/Outbox/Contract/OutboxMessage.php`, `app/src/Modules/Outbox/Domain/Outbox/ValueObject/OutboxEventType.php`, `app/src/Modules/Outbox/Infrastructure/Outbox/ValinorOutboxMessageSerializer.php` | `tests/Unit/Modules/Outbox/Application/Outbox/OutboxMessageSerializerTest.php` | применено |
| 4 | Доменная outbox-дата протаскивала `null` | `app/src/Modules/Outbox/Domain/Outbox/ValueObject/OutboxEventDate.php`, `app/src/Modules/Outbox/Domain/Outbox/ValueObject/EmptyOutboxEventDate.php`, `app/src/Modules/Outbox/Domain/Outbox/ValueObject/KnownOutboxEventDate.php`, `app/src/Modules/Outbox/Infrastructure/Cycle/OutboxEventDateTypecast.php`, `app/src/Modules/Outbox/Domain/Outbox/Entity/StoredOutboxEvent.php` | `tests/Unit/Modules/Outbox/Domain/Outbox/OutboxValueObjectTest.php` | применено |
| 5 | `findPendingForRelay()` скрыто менял БД во время чтения | `app/src/Modules/Outbox/Repository/OutboxEventRepository.php`, `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelay.php` | `tests/Feature/Modules/Outbox/Repository/OutboxEventRepositoryTest.php` | применено |
| 6 | Production relay worker содержал тестовый лимит цикла | `app/src/Modules/Outbox/Application/Contract/OutboxRelayLoopControlContract.php`, `app/src/Modules/Outbox/Infrastructure/Outbox/InfiniteOutboxRelayLoopControl.php`, `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelayWorker.php`, `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxBootloader.php` | `tests/Unit/Modules/Outbox/Infrastructure/Outbox/OutboxRelayWorkerTest.php` | применено |

## Финальная проверка

- **PHPStan:** `make phpstan` — прошло
- **Точечные unit-тесты:** `vendor/bin/phpunit tests/Unit/Modules/Outbox/Domain/Outbox/OutboxValueObjectTest.php tests/Unit/Modules/Outbox/Application/Outbox/OutboxMessageSerializerTest.php tests/Unit/Modules/Outbox/Infrastructure/Outbox/OutboxRelayWorkerTest.php` — прошло, 11 тестов, 39 проверок
- **QA:** `make qa` — не прошло
- **Заметки:** стиль, PHPStan и обычные тесты внутри `make qa` прошли. Обычные тесты: 183 теста, 733 проверки, 28 PHPUnit deprecations. Новый шаг покрытия корректно остановил QA: покрытие строк 87.86% ниже обязательного порога 100.00%.
