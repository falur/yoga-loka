---
review: docs/reviews/2026-05-28_12-53_outbox-rabbitmq-uncommitted-draft.md
date: 2026-05-28 13:40
status: done
---

# Фиксы по ревью: outbox RabbitMQ uncommitted draft

## Применённые правки
| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Ошибка восстановления payload могла оставить событие в `queued` | `OutboxQueueSerializer.php`, `OutboxQueueEnvelope.php`, `OutboxQueuePublisher.php`, `OutboxDebugLogJob.php`, `ValinorOutboxMessageSerializer.php` | `OutboxQueueSerializerTest.php`, `OutboxQueueStatusInterceptorTest.php`, `OutboxRelayTest.php`, `OutboxDebugLogJobTest.php` | применено |
| 2 | Сущность outbox была открыта для произвольной записи состояния | `StoredOutboxEvent.php` | `OutboxRelayTest.php`, `OutboxQueueStatusInterceptorTest.php` | применено |
| 3 | Публичная граница outbox раскрывала Domain-типы | `OutboxMessage.php`, `StoredOutboxEventId.php`, `OutboxEventStoreContract.php`, `OutboxEventStore.php`, `OutboxEventType.php`, `docs/arch.md` | `OutboxEventRepositoryTest.php`, `OutboxMessageSerializerTest.php`, `OutboxValueObjectTest.php` | применено |
| 4 | Реальный RabbitMQ/RoadRunner путь почти не проверялся | `OutboxQueueSerializer.php`, `OutboxQueuePublisher.php` | `OutboxQueueStatusInterceptorTest.php`, `OutboxRelayTest.php` | применено |
| 5 | Глобальная замена Cycle mapper-а не была покрыта прямыми тестами | `MediaRepositoryTest.php` | `MediaRepositoryTest.php` | применено |
| 6 | `make qa` запускал весь suite с coverage | `docker/test/run-qa.sh` | `make qa` | применено |
| 7 | Транспортные заголовки очереди лежали в Application-слое | `OutboxQueueHeaders.php`, `OutboxQueuePublisher.php`, `OutboxQueueStatusInterceptor.php`, `OutboxDebugLogJob.php` | `OutboxQueueHeadersTest.php`, `OutboxQueueStatusInterceptorTest.php`, `OutboxRelayTest.php`, `OutboxDebugLogJobTest.php` | применено |
| 8 | `outbox:relay` записывал Entity вне Handler-а без явного правила | `docs/rules.md`, `docs/arch.md` | `make test`, `make phpstan`, `make qa` | применено |
| 9 | Разбор queue-заголовков был продублирован | `OutboxQueueHeaders.php`, `OutboxQueueStatusInterceptor.php`, `OutboxDebugLogJob.php` | `OutboxQueueHeadersTest.php` | применено |
| 10 | `LazyGhostEntityFactory` слишком много делал для глобального mapper-а | `LazyGhostEntityFactory.php`, `LazyGhostReflectionRegistry.php` | `MediaRepositoryTest.php` | применено |

## Финальная проверка
- **Тесты:** `make test` — прошло, 166 тестов, 661 assertion, 28 PHPUnit deprecations
- **PHPStan:** `make phpstan` — прошло без ошибок
- **QA:** `make qa` — прошло: CS, PHPStan, тесты и coverage завершились успешно
- **Заметки:** PHPUnit deprecations остались предупреждениями и не ломают проверки
