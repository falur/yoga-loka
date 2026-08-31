---
review: docs/reviews/2026-06-05_13-24_outbox-rabbitmq-uncommitted-draft.md
date: 2026-06-05 12:00
status: done
---

# Фиксы по ревью: outbox RabbitMQ uncommitted draft

## Применённые правки
| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Добавить устойчивый retry в `OutboxRelayWorker::runLoop` при временной ошибке и продолжать дальше, с паузой и лимитом подряд инициал | `app/src/Modules/Outbox/Application/Contract/OutboxRelayContract.php`, `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelay.php`, `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelayWorker.php`, `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxBootloader.php` | `tests/Unit/Modules/Outbox/Infrastructure/Outbox/OutboxRelayWorkerTest.php` | ✓ применено |

## Финальная проверка
- **Тесты:** `make test` — не запускались (не удалось запустить Docker)
- **PHPStan:** `make phpstan` — не запускался (не удалось запустить Docker)
- **QA:** `make qa` — ✗ `unable to get image 'redis:8.6.3-trixie': failed to connect to the docker API at unix:///Users/gian_tiaga/.docker/run/docker.sock` |
- **Примечание:** `make qa` остановился на `make reset-test` до выполнения проверок из-за недоступного Docker daemon
