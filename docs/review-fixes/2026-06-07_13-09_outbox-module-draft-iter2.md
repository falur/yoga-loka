---
review: docs/reviews/2026-06-07_12-36_outbox-module-draft-iter2.md
date: 2026-06-07 13:09
status: done
---

# Фиксы по ревью: Второй черновой обзор Outbox-среза после исправлений

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | `OutboxQueueEnvelope` в Application раскрывал transport payload как массив | `app/src/Modules/Outbox/Application/Message/OutboxQueueEnvelope.php`, `app/src/Modules/Outbox/Infrastructure/OutboxQueueSerializer.php`, `app/src/Modules/Outbox/Infrastructure/OutboxQueuePublisher.php`, `app/src/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptor.php` | `tests/Unit/Modules/Outbox/Infrastructure/OutboxQueueSerializerTest.php`, `tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorTest.php`, `tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorFailureTest.php`, `tests/Unit/Modules/Outbox/Infrastructure/OutboxInfrastructureEdgeTest.php` | применено |
| 2 | Application-сценарий debug-сообщения напрямую зависел от `Psr\Log\LoggerInterface` | `app/src/Modules/Outbox/Presentation/Job/OutboxDebugLogJob.php`, удалены `app/src/Modules/Outbox/Application/Command/ProcessDebugLogMessage/ProcessOutboxDebugLogMessageCommand.php` и `app/src/Modules/Outbox/Application/Command/ProcessDebugLogMessage/ProcessOutboxDebugLogMessageHandler.php` | `tests/Unit/Modules/Outbox/Presentation/Job/OutboxDebugLogJobTest.php`, `tests/Feature/Modules/Outbox/Infrastructure/Fixture/QueueStatusDebugLogJobCore.php`, `tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorTest.php`, `tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorFailureTest.php` | применено |
| 3 | Нестандартный RabbitMQ vhost мог сломать RoadRunner AMQP URL | `.env.sample`, `docker/rr/http-jobs.yaml`, `app/src/Modules/Outbox/README.md` | `tests/Unit/Shared/Infrastructure/Configuration/RoadRunnerRabbitMqConfigTest.php` | применено |

## Финальная проверка

- **Проверка diff:** `git diff --check HEAD` — успешно.
- **PHPStan:** `make phpstan` — успешно.
- **Тесты:** `make test` — успешно: 245 тестов, 886 assertions, 28 PHPUnit deprecations.
- **QA:** `make qa` — пропущено по прямому запрету пользователя.
- **Composer QA:** `composer qa` — пропущено по прямому запрету пользователя.
- **Coverage:** coverage / проверки покрытия — пропущены по прямому запрету пользователя.

## Заметки

- Режим `apply-optional` включён, но в ревью не было пунктов «на усмотрение автора».
- `make test` проверен перед запуском: цель не запускает `test-coverage` и не включает `XDEBUG_MODE=coverage`.
