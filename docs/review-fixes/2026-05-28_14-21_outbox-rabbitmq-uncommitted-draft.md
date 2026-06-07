---
review: docs/reviews/2026-05-28_13-47_outbox-rabbitmq-uncommitted-draft.md
date: 2026-05-28 14:21
status: done
---

# Фиксы по ревью: outbox RabbitMQ uncommitted draft

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Документация показывала опасный порядок сохранения outbox-события после `EntityManager::run()`. | `docs/arch.md` | `tests/Feature/Modules/Outbox/Application/OutboxEventStoreTransactionTest.php` | ✓ применено |
| 2 | Старый тип outbox-сообщения падал при чтении до места, где можно записать ошибку. | `app/src/Modules/Outbox/Domain/Outbox/ValueObject/OutboxEventType.php` | `tests/Unit/Modules/Outbox/Domain/Outbox/OutboxValueObjectTest.php`, `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php` | ✓ применено |
| 3 | `OutboxQueueSerializer` падал, когда RoadRunner не передал класс payload. | `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueSerializer.php` | `tests/Unit/Modules/Outbox/Infrastructure/Outbox/OutboxQueueSerializerTest.php`, `tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorFailureTest.php` | ✓ применено |
| 4 | Outbox feature-тесты были перегружены локальными fake-классами и разными сценариями. | `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php`, `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php`, `tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorTest.php`, `tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorFailureTest.php`, `tests/Feature/Modules/Outbox/Infrastructure/Fixture/*` | Поведение сохранено в разделённых feature-тестах | ✓ применено |

## Финальная проверка

- **Тесты:** `make test` — ✓, 171 тест, 678 проверок, 28 PHPUnit deprecations.
- **PHPStan:** `make phpstan` — ✓, ошибок нет.
- **Composer QA:** `make qa` — ✓, стиль, PHPStan, тесты и покрытие прошли; 171 тест, 678 проверок, 28 PHPUnit deprecations; покрытие строк 86.51%.
- **Заметки:** PHPUnit deprecations уже были не блокирующими предупреждениями, команды завершились с кодом 0.
