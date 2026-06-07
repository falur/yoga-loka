---
review: docs/reviews/2026-06-05_12-22_outbox-rabbitmq-git-diff-head-draft.md
date: 2026-06-05 12:40
status: blocked
---

# Фиксы по ревью: outbox RabbitMQ git diff HEAD

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Job мог выполниться при конфликте outbox-данных в headers и payload | `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueStatusInterceptor.php` | `tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorTest.php` | применено |
| 2 | Пустой `last_error` пропускался ручной проверкой битых строк | `app/src/Modules/Outbox/Repository/OutboxEventRepository.php`, `app/src/Modules/Outbox/Repository/OutboxPendingRow.php` | `tests/Feature/Modules/Outbox/Repository/OutboxEventRepositoryTest.php` | применено |
| 3 | Тестовый лимит relay попадал в общий `catch` | `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelayWorker.php` | `tests/Unit/Modules/Outbox/Infrastructure/Outbox/OutboxRelayWorkerTest.php` | применено |
| 4 | Лишняя пустая строка в конце PHP-файла | `app/src/Modules/Outbox/Application/Command/Outbox/ProcessDebugLogMessage/ProcessOutboxDebugLogMessageHandler.php` | `git diff --check HEAD` | применено |
| 5 | Application-контракт сериализации раскрывал доменные value object outbox | `app/src/Modules/Outbox/Application/Contract/OutboxMessageSerializerContract.php`, `app/src/Modules/Outbox/Application/Outbox/SerializedOutboxMessage.php`, `app/src/Modules/Outbox/Infrastructure/Outbox/ValinorOutboxMessageSerializer.php`, `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxEventStore.php`, `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueuePublisher.php`, `app/src/Modules/Outbox/Application/Command/Outbox/ProcessDebugLogMessage/ProcessOutboxDebugLogMessageHandler.php` | `tests/Unit/Modules/Outbox/Application/Outbox/OutboxMessageSerializerTest.php`, `tests/Feature/Modules/Outbox/Repository/OutboxEventRepositoryTest.php` | применено |
| 6 | Новые outbox-контракты использовали явные `mixed` и `array<string, mixed>` | `app/src/Modules/Outbox/Repository/OutboxEventRepository.php`, `app/src/Modules/Outbox/Repository/OutboxPendingRow.php`, `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueHeaders.php`, `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueStatusInterceptor.php`, `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueSerializer.php`, `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueuePublisher.php`, `app/src/Modules/Outbox/Infrastructure/Cycle/OutboxValueObjectTypecast.php` | `rg "array<string, mixed>" app/src/Modules/Outbox -n` | применено |
| 7 | В консольной команде дублировался разбор целых чисел | `app/src/Modules/Outbox/Presentation/Console/OutboxRelayCommand.php` | `php -l app/src/Modules/Outbox/Presentation/Console/OutboxRelayCommand.php` | применено |

## Финальная проверка

- **`make test`:** не выполнено, команда завершилась с ошибкой 2. Причина: Docker daemon недоступен по `unix:///Users/gian_tiaga/.docker/run/docker.sock`.
- **`make phpstan`:** не выполнено, команда завершилась с ошибкой 2. Причина: Docker daemon недоступен по `unix:///Users/gian_tiaga/.docker/run/docker.sock`.
- **`make qa`:** не выполнено, команда завершилась с ошибкой 2. Причина: Docker daemon недоступен по `unix:///Users/gian_tiaga/.docker/run/docker.sock`.
- **`git diff --check HEAD`:** пройдено.
- **`php -l` по изменённым PHP-файлам:** пройдено.

## Заметки

Финальные проверки проекта заблокированы внешним состоянием: локальный Docker daemon не запущен или недоступен. Код не коммитился.
