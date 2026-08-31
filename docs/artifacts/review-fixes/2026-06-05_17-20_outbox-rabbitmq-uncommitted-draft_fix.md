---
review: docs/reviews/2026-06-05_17-20_outbox-rabbitmq-uncommitted-draft.md
date: 2026-06-05 12:09
status: done
---

# Фиксы по ревью: Outbox/RabbitMQ

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Исправлена обработка `TypecastException`: теперь каждый pending-ряд outbox валидируется по всем важным полям и помечается как `failed` с диагностикой по конкретной причине (в т.ч. не-полям `type`) | `app/src/Modules/Outbox/Repository/OutboxEventRepository.php` | `testFindPendingForRelayMarksRowsWithInvalidDateAsFailed` (`tests/Feature/Modules/Outbox/Repository/OutboxEventRepositoryTest.php`) | ✓ применено |
| 2 | Ограничена зона `LazyGhostMapper` с глобального `MAPPER` на сущности `Media` через явный `mapper: LazyGhostMapper::class` в `#[Entity(...)]` | `app/config/cycle.php`; `app/src/Modules/Media/Domain/Entity/Media.php`; `app/src/Modules/Media/Domain/Entity/MediaImageConversion.php`; `app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php`; `app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php` | Существующие media-интеграционные тесты по lazy-ghost не затронуты: `tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php` | ✓ применено |

## Финальная проверка
- **Тесты:** `make test` — не выполнен, ошибка доступа к Docker API (`/Users/gian_tiaga/.docker/run/docker.sock`) |
- **Линтер/статический анализ:** `make qa` — не выполнен, ошибка доступа к Docker API (`/Users/gian_tiaga/.docker/run/docker.sock`) |
- **Дополнительно:** `php -l` для изменённых PHP-файлов — пройден успешно |
- **Заметки:** Ошибка инфраструктурная, проверить после запуска Docker.
