---
review: docs/reviews/2026-06-07_11-57_outbox-module-draft.md
date: 2026-06-07 12:32
status: done
---

# Фиксы по ревью: Черновое ревью Outbox-среза незакоммиченных изменений

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | `OutboxQueueStatusInterceptor` мог перезаписать финальный статус после конкурентной обработки | `app/src/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptor.php`, `app/src/Modules/Outbox/Repository/OutboxEventRepository.php` | `tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorTest.php` | применено |
| 2 | README заставлял внешние модули зависеть от `Infrastructure` Outbox | `app/src/Modules/Outbox/Application/Contract/OutboxJobRegistryContract.php`, `app/src/Modules/Outbox/Application/Message/OutboxQueueEnvelope.php`, `app/src/Modules/Outbox/Infrastructure/OutboxJobRegistry.php`, `app/src/Modules/Outbox/Infrastructure/OutboxBootloader.php`, `app/src/Modules/Outbox/Infrastructure/OutboxQueuePublisher.php`, `app/src/Modules/Outbox/Infrastructure/OutboxQueueSerializer.php`, `app/src/Modules/Outbox/Presentation/Job/OutboxDebugLogJob.php`, `app/src/Modules/Outbox/README.md` | `tests/Unit/Modules/Outbox/Infrastructure/OutboxQueueSerializerTest.php`, `tests/Unit/Modules/Outbox/Presentation/Job/OutboxDebugLogJobTest.php` | применено |
| 3 | `OutboxMessage` требовал `JsonSerializable` и ручной массив payload | `app/src/Modules/Outbox/Application/Message/OutboxMessage.php`, `app/src/Modules/Outbox/Application/Message/OutboxDebugLogMessage.php`, `app/src/Modules/Outbox/Infrastructure/ValinorOutboxMessageSerializer.php`, `app/src/Modules/Outbox/README.md` | `tests/Unit/Modules/Outbox/Application/Message/OutboxMessageSerializerTest.php` | применено |
| 4 | `make qa` не запускался | — | `make phpstan`, `make test` | выполнено с ограничением: `make qa` и coverage пропущены по прямому запрету пользователя |
| 5 | Outbox-дерево было в состоянии `AD`/`??` после переноса файлов | индекс git по `app/src/Modules/Outbox`, `tests/Feature/Modules/Outbox`, `tests/Unit/Modules/Outbox` | — | применено |
| 6 | Часть Outbox-тестов была привязана к приватным деталям через Reflection | `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php` | `make test` | частично применено: убран Reflection-тест приватного `OutboxRelay::outboxMaxAttempts`; остальное оставлено для недоступных через обычный публичный сценарий граничных случаев |

## Финальная проверка

- **PHPStan:** `make phpstan` — успешно.
- **Тесты:** `make test` — успешно: 244 теста, 879 проверок, 28 PHPUnit deprecations.
- **QA:** `make qa` — пропущен по прямому запрету пользователя.
- **Coverage:** пропущен по прямому запрету пользователя. Ранее начатый `make qa` был остановлен на этапе coverage после нового запрета.
- **Заметки:** красных финальных проверок нет. Предупреждения PHPUnit deprecations остались, но тестовый запуск завершился успешно.
