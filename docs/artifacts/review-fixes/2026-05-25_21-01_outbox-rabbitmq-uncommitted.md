---
review: docs/reviews/2026-05-25_17-10_outbox-rabbitmq-uncommitted.md
date: 2026-05-25 21:01
status: done
---

# Фиксы по ревью: outbox RabbitMQ

## Область исправления

Применены пункты из сообщения пользователя:

- `Не реализован лимит попыток из outbox-настройки`;
- `RoadRunner-настройка RabbitMQ не следует env-параметрам из плана`;
- замечания 2-6 из ревью.

Пункт `Outbox нужно вынести в отдельный модуль` не применялся в этом проходе, потому что пользователь указал, что он уже сделан.

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Не реализован лимит попыток из outbox-настройки | `app/config/outbox.php`, `app/src/Shared/Infrastructure/Configuration/Outbox/OutboxConfig.php`, `app/src/Modules/Outbox/Domain/Outbox/ValueObject/OutboxMaxAttempts.php`, `app/src/Modules/Outbox/Domain/Outbox/ValueObject/OutboxAttempts.php`, `app/src/Modules/Outbox/Domain/Outbox/Entity/StoredOutboxEvent.php`, `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelay.php`, `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueStatusInterceptor.php`, `.env.sample` | `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php`, `tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorTest.php`, `tests/Unit/Shared/Infrastructure/Configuration/SimpleConfigMapperTest.php`, `tests/Unit/Shared/Infrastructure/Configuration/SimpleConfigBindingTest.php`, `tests/Unit/Shared/Infrastructure/Configuration/ConfigShapeTest.php` | ✓ применено |
| 2 | RoadRunner-настройка RabbitMQ не следует env-параметрам из плана | `docker/rr/http-jobs.yaml`, `.env.sample`, `docker/README.md` | `tests/Unit/Shared/Infrastructure/Configuration/RoadRunnerRabbitMqConfigTest.php` | ✓ применено |
| 3 | Запись длинной ошибки может сама сломать обработку сбоя | `app/src/Modules/Outbox/Domain/Outbox/ValueObject/OutboxLastError.php` | `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php`, `tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorTest.php`, `tests/Unit/Modules/Outbox/Domain/Outbox/OutboxValueObjectTest.php` | ✓ применено |
| 4 | Лимит попыток не переводит событие в `failed` | `app/src/Modules/Outbox/Domain/Outbox/ValueObject/OutboxAttempts.php`, `app/src/Modules/Outbox/Domain/Outbox/Entity/StoredOutboxEvent.php`, `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelay.php`, `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueStatusInterceptor.php` | `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php`, `tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorTest.php` | ✓ применено |
| 5 | Sync-подключение не использует serializer и получает неверный payload | `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelay.php` | `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php` | ✓ применено |
| 6 | RabbitMQ pipeline в RoadRunner расходится с env-контрактом | `docker/rr/http-jobs.yaml`, `.env.sample`, `app/config/queue.php`, `docker/README.md` | `tests/Unit/Shared/Infrastructure/Configuration/RoadRunnerRabbitMqConfigTest.php`, `tests/Unit/Shared/Infrastructure/Configuration/ComplexConfigMapperTest.php`, `tests/Unit/Shared/Infrastructure/Configuration/ComplexConfigBindingTest.php` | ✓ применено |
| 7 | Для outbox-задачи без записи в БД нет безопасной политики | `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueStatusInterceptor.php` | `tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorTest.php` | ✓ применено |

## Финальная проверка

- **Тесты:** `make test` — ✓ 152 теста, 610 assertions, 28 PHPUnit deprecations.
- **Линтер:** `make phpstan` — ✓ ошибок нет.
- **Заметки:** PHPUnit deprecations не связаны с этой правкой и не ломают прогон.
