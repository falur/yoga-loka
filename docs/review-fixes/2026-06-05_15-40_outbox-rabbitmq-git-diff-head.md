---
title: Фиксы к ревью 2026-06-05_15-40_outbox-rabbitmq-git-diff-head
review: docs/reviews/2026-06-05_15-40_outbox-rabbitmq-git-diff-head-draft.md
date: 2026-06-05 16:45
status: done
---

# Фиксы по ревью: outbox RabbitMQ (git diff HEAD)

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Проверка `OutboxEventType` на существующий класс и реализацию `OutboxMessage` | `app/src/Modules/Outbox/Domain/Outbox/ValueObject/OutboxEventType.php` | `tests/Unit/Modules/Outbox/Domain/Outbox/OutboxValueObjectTest.php` (`testOutboxEventTypeAcceptsOutboxMessageClass`, `...RejectsEmptyType`, `...RejectsMissingClassType`, `...RejectsClassWithoutOutboxMessageContract`) | ✓ применено |
| 2 | Интеграционный кейс: некорректный тип в `outbox_events.type` переводится в ошибку без падения пайплайна | `app/src/Modules/Outbox/Repository/OutboxEventRepository.php`, `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php` | `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php` (`testRelayMarksOldRemovedMessageTypeAsFailed`, `testRelayMarksNonOutboxMessageTypeAsFailed`) | ✓ применено |

## Финальная проверка
- **Тесты:** `make qa` — не выполнен: `reset-test` не смог подключиться к Docker (`unable to get image 'redis:8.6.3-trixie': failed to connect to the docker API ...`).
- **Примечание:** Требуется повторить `make qa` в окружении с доступным Docker для полного подтверждения. Изменения затронули файлы outbox, где ранее этот сценарий уже покрыт интеграционными тестами.
