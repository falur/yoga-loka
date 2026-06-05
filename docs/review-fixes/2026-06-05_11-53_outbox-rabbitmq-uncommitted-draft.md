---
review: docs/reviews/2026-05-28_14-37_outbox-rabbitmq-uncommitted-draft.md
date: 2026-06-05 11:53
status: blocked
---

# Фиксы по ревью: outbox RabbitMQ uncommitted draft

## Применённые правки
| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Interceptor теперь умеет брать `outboxId` из payload, проверяет расхождение headers и payload и не теряет outbox-статус без headers | `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueStatusInterceptor.php` | `tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorTest.php`, `tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorFailureTest.php` | ✓ применено |
| 2 | Индекс для outbox-связанных файлов приведён к текущему рабочему дереву: ключевые `Outbox`-файлы и связанные тесты добавлены в index, состояние `AM` по ним убрано | `app/src/Modules/Outbox/**`, `tests/Feature/Modules/Outbox/**`, `tests/Unit/Modules/Outbox/**`, `tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php`, `app/config/cycle.php`, `docs/plans/2026-05-24_17-37_outbox-rabbitmq.md` | — | ✓ применено |
| 3 | `OutboxDebugLogJob` работает как тонкий адаптер очереди и делегирует в Application Handler через CommandBus | `app/src/Modules/Outbox/Presentation/Job/OutboxDebugLogJob.php`, `app/src/Modules/Outbox/Application/Command/Outbox/ProcessDebugLogMessage/*` | `tests/Unit/Modules/Outbox/Presentation/Job/OutboxDebugLogJobTest.php` | ✓ применено |
| 4 | Покрытие глобального `LazyGhostMapper` расширено отдельными сценариями на связи и сохранение lazy entity | `tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php`, `app/config/cycle.php`, `app/src/Shared/Infrastructure/Cycle/LazyGhostEntityFactory.php`, `app/src/Shared/Infrastructure/Cycle/LazyGhostMapper.php` | `tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php` | ✓ применено |

## Финальная проверка
- **Тесты:** `make test` — ✗ не запустилось
- **PHPStan:** `make phpstan` — не запускал, потому что Docker daemon недоступен
- **QA:** `make qa` — не запускал, потому что Docker daemon недоступен
- **Заметки:** `make test` завершился ошибкой Docker API: `failed to connect to the docker API at unix:///Users/gian_tiaga/.docker/run/docker.sock`
