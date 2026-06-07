---
plan: docs/plans/2026-05-24_17-37_outbox-rabbitmq.md
started: 2026-06-06 15:34
finished: 2026-06-06 15:34
status: done
---

# Журнал: Outbox message loader flow

## Шаги

| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Добавить загрузку бизнес-сообщения по outbox id | `OutboxMessageLoaderContract.php`, `OutboxMessageLoader.php`, `OutboxMessageLoadingException.php`, `OutboxBootloader.php` | `make test`, `make phpstan` | done |
| 2 | Убрать технический outbox id из бизнес-команды debug Job | `OutboxDebugLogJob.php`, `ProcessOutboxDebugLogMessageCommand.php`, `ProcessOutboxDebugLogMessageHandler.php` | `make test`, `make phpstan` | done |
| 3 | Обновить инструкцию по модулю Outbox | `app/src/Modules/Outbox/README.md` | прочитано вручную | done |
| 4 | Закрыть покрытие Outbox | `tests/Feature/Modules/Outbox/*`, `tests/Unit/Modules/Outbox/*` | coverage-диагностика Outbox, `make test`, `make phpstan` | done |

## Заметки

- Job получает технический queue envelope и сам загружает бизнес-сообщение через `OutboxMessageLoaderContract`.
- Бизнес-команда получает только бизнес-данные сообщения: текст, дату создания и job id.
- `outboxId` остаётся техническим идентификатором для relay/interceptor/loader и не протекает в бизнес-handler.
- `make qa` не запускался по прямому ограничению пользователя в текущем обсуждении.

## Финальная проверка

- `make test` - passed: 230 tests, 831 assertions, 28 PHPUnit deprecations.
- `make phpstan` - passed: no errors.
- `docker compose -f docker/docker-compose.dev.yml --env-file .env config` - passed.
- Coverage-диагностика по `app/src/Modules/Outbox` - непокрытых строк нет.
