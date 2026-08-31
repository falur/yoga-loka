---
plan: docs/plans/2026-05-21_17-59_media-domain-entities-vo-migrations.md
started: 2026-05-21 18:41
finished: 2026-05-21 19:11
status: done
---

# Журнал: Сущности, Value Object и миграции медиа-домена

## Шаги

| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Базовая поддержка доменных типов для Cycle ORM | `app/src/Infrastructure/Cycle/*`, `app/src/Domain/Trait/HasTimestamps.php`, `tests/Unit/Infrastructure/Cycle/*`, `tests/Unit/Domain/Trait/*` | `make test`, `make phpstan` | Готово |
| 2 | Enum, Value Object и storage config медиа-домена | `app/src/Domain/Enum/*`, `app/src/Domain/ValueObject/*`, `app/src/Domain/Collection/MediaMultipartPartCollection.php`, `app/config/storage.php`, `.env.sample`, `docker/docker-compose.dev.yml`, тесты VO/config | `make test`, `make phpstan` | Готово |
| 3 | Entity, связи, коллекции и репозитории | `app/src/Domain/Entity/*`, `app/src/Domain/Collection/*`, `app/src/Repository/*`, тесты entity/repository | `make shell CMD='php app.php cycle'`, `make test`, `make phpstan` | Готово |
| 4 | Миграция базы и проверка схемы | `app/database/migrations/20260521.184100_0_create_media_domain_tables.php`, интеграционные тесты ограничений и чтения | `make migrate`, `make test`, `make phpstan` | Готово |

## Заметки

- Имя миграции использует фактический формат установленного Cycle Migrations: `YYYYMMDD.HHMMSS_<chunk>_<name>.php`.
- Для внешних ключей в миграции отключено автоматическое создание индекса, потому что план требует явные индексы `media_id`.
- `MediaImageConversionType` оставлен без переименования по запросу пользователя.

## Изменения в docs

- Обновлён `docs/code-examples.md`: пример Entity теперь использует конкретный id Value Object, `ValueObjectCast` и `HasTimestamps`, без общего `HasUuid`.

## Финальная проверка

- `make shell CMD='php app.php cycle'` — успешно.
- `make migrate` — успешно, повторный запуск показал отсутствие новых миграций.
- `make test` — успешно: 113 тестов, 446 проверок. Остались 26 предупреждений PHPUnit.
- `make phpstan` — успешно, ошибок нет.
