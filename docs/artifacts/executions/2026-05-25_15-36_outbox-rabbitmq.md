---
plan: docs/plans/2026-05-24_17-37_outbox-rabbitmq.md
started: 2026-05-25 15:36
finished: 2026-05-25 16:35
status: done
---

# Журнал: Outbox через RabbitMQ

## Шаги

| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Подключить RabbitMQ и AMQP-очередь | `docker/Dockerfile`, `docker/docker-compose.dev.yml`, `docker/rr/http-jobs.yaml`, `app/config/queue.php`, `.env.sample`, `.env`, `docker/README.md`, `tests/Unit/Shared/Infrastructure/Configuration/ComplexConfigMapperTest.php`, `tests/Unit/Shared/Infrastructure/Configuration/ComplexConfigBindingTest.php`, `~/.ports` | `docker compose -f docker/docker-compose.dev.yml --env-file .env config`; `make test`; `make phpstan` | done |
| 2 | Добавить модель хранения outbox и сериализацию сообщений | `app/database/migrations/20260525.153700_0_create_outbox_events_table.php`, `app/src/Modules/System/Application/Outbox/*`, `app/src/Modules/System/Application/Contract/Outbox*Contract.php`, `app/src/Modules/System/Domain/Outbox/*`, `app/src/Modules/System/Infrastructure/Cycle/Outbox*Typecast.php`, `app/src/Modules/System/Infrastructure/Outbox/*`, `app/src/Modules/System/Repository/OutboxEventRepository.php`, `app/src/Shared/Infrastructure/Framework/Kernel.php`, `tests/Unit/Modules/System/*`, `tests/Feature/Modules/System/Repository/OutboxEventRepositoryTest.php` | `make test`; `make phpstan` | done |
| 3 | Реализовать relay и общий queue status interceptor | `app/src/Modules/System/Infrastructure/Outbox/OutboxRelay.php`, `app/src/Modules/System/Infrastructure/Outbox/OutboxRelayWorker.php`, `app/src/Modules/System/Infrastructure/Outbox/OutboxJobRegistry.php`, `app/src/Modules/System/Infrastructure/Outbox/OutboxQueueEnvelope.php`, `app/src/Modules/System/Infrastructure/Outbox/OutboxQueueHeaders.php`, `app/src/Modules/System/Infrastructure/Outbox/OutboxQueueSerializer.php`, `app/src/Modules/System/Infrastructure/Outbox/OutboxQueueStatusInterceptor.php`, `app/src/Modules/System/Repository/OutboxEventRepository.php`, `app/config/queue.php` | `make test`; `make phpstan` | done |
| 4 | Добавить технический outbox Job для проверки | `app/src/Modules/System/Application/Outbox/Message/OutboxDebugLogMessage.php`, `app/src/Modules/System/Presentation/Job/OutboxDebugLogJob.php`, `app/src/Modules/System/Presentation/Console/OutboxRelayCommand.php`, `app/src/Modules/System/Infrastructure/Outbox/OutboxBootloader.php`, `app/src/Modules/System/Infrastructure/Outbox/OutboxConsoleBootloader.php`, `app/src/Shared/Infrastructure/Framework/Kernel.php`, `tests/Feature/Modules/System/Outbox/*`, `tests/Feature/Modules/System/Console/OutboxRelayCommandTest.php`, `tests/Unit/Modules/System/Infrastructure/Outbox/OutboxQueueSerializerTest.php`, `tests/Unit/Modules/System/Presentation/Job/OutboxDebugLogJobTest.php` | `make test`; `make phpstan` | done |
| 5 | Закрыть эксплуатацию и полный прогон проверок | `README.md`, `docker/README.md`, `docs/arch.md`, `docs/plans/2026-05-24_17-37_outbox-rabbitmq.md`, `docs/executions/2026-05-25_15-36_outbox-rabbitmq.md` | `docker compose -f docker/docker-compose.dev.yml --env-file .env config`; `make test`; `make phpstan` | done |

## Заметки

- Выполнение выбрано в текущей ветке `work-1`.
- Перед правками прочитаны `docs/rules.md` и `docs/arch.md`.
- `~/.ports` дополнен портами RabbitMQ для проекта `yoga-loka-spiral-2`: `60672` и `61672`.
- Для `StoredOutboxEvent` поля сделаны обычными публичными, потому что Cycle proxy не гидрирует `public private(set)` свойства при свежей выборке из БД.
- Relay claim-ит события короткой транзакцией и переводит их в `publishing`; успешный push переводит в `queued`, а sync-сценарий не перезаписывает уже `handled`.
- `OutboxQueueStatusInterceptor` стоит между `ErrorHandlerInterceptor` и `RetryPolicyInterceptor`, чтобы видеть `RetryException` и обычные ошибки Job.
- После замечания пользователя AMQP-параметры RabbitMQ pipeline вынесены из жёстких значений в env с теми же dev-дефолтами.

## Изменения в docs

- В `docker/README.md` описан RabbitMQ, его порты, volume, диагностика и то, что тестовая очередь остаётся `sync`.
- В `README.md` и `docker/README.md` описана команда `outbox:relay`, RabbitMQ management UI и правило идемпотентности по `outboxId`.
- В `docs/arch.md` outbox-поток уточнён как `outbox -> RabbitMQ -> Job -> queue interceptor`.
- В плане уточнено, что AMQP-параметры задаются через env, а не жёстко в коде.

## Финальная проверка

| Команда | Статус | Детали |
|---|---|---|
| `docker compose -f docker/docker-compose.dev.yml --env-file .env config` | passed | Compose config построен успешно. |
| `make test` | passed | 142 tests, 559 assertions, PHPUnit deprecations 26. |
| `make phpstan` | passed | No errors. |
