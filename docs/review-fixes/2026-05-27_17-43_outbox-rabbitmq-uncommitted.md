---
review: docs/reviews/2026-05-27_14-46_outbox-rabbitmq-uncommitted.md
date: 2026-05-27 17:43
status: done
---

# Фиксы по ревью: outbox RabbitMQ

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | `docs/arch.md` оставлял старое описание memory-очереди вместо RabbitMQ | `docs/arch.md` | `docker compose -f docker/docker-compose.dev.yml --env-file .env config` | применено |
| 2 | Финальные проверки не были подтверждены | — | `make test`, `make phpstan`, `make shell CMD="composer cs"` | частично применено, `composer qa` см. ниже |
| 3 | Ошибка sync Job могла быть перезаписана как ошибка publish | `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelay.php`, `app/src/Modules/Outbox/Repository/OutboxEventRepository.php`, `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php` | `make test` | применено |
| 4 | Вызовы `OutboxRelay::relay()` были без именованного аргумента | `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxRelayWorker.php` | `make phpstan` | применено |
| 5 | Лишние пустые строки в конце файлов | `app/src/Modules/Outbox/Application/Outbox/Message/OutboxDebugLogMessage.php`, `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxConsoleBootloader.php`, `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxJobRegistryException.php`, `tests/Unit/Modules/Outbox/Infrastructure/Outbox/OutboxQueueSerializerTest.php` | `git diff --check HEAD`, `make shell CMD="composer cs"` | применено через php-cs-fixer |

## Финальная проверка

- **Docker config:** `docker compose -f docker/docker-compose.dev.yml --env-file .env config` — успешно.
- **Форматирование:** `make shell CMD="composer cs"` — успешно.
- **Diff check:** `git diff --check HEAD` — успешно.
- **Тесты:** `make test` — успешно, 156 тестов, 626 проверок, 28 PHPUnit deprecations.
- **PHPStan:** `make phpstan` — успешно.
- **Composer QA:** `make shell CMD="composer qa"` — не прошёл. Команда запускает PHPUnit в `app-http` с dev-настройками из `.env`, поэтому тесты видят `DB_DATABASE=yoga_loka`, `CACHE_STORAGE=redis`, `STORAGE_DEFAULT=s3` и падают на ожиданиях тестового окружения и отсутствии таблицы `outbox_events`.
- **Повтор QA в test-runner:** `APP_SERVICE=test-runner make shell CMD="composer qa"` — не прошёл на существующей проблеме запуска: после `phpstan` PHPUnit падает в `Tests\Feature\Modules\Media\Repository\MediaRepositoryTest::testFindExpiredReturnsTypedCollection` с ошибкой Cycle ORM про final-сущность `Media`.
- **Coverage script:** `APP_SERVICE=test-runner make shell CMD="composer test-coverage"` — не прошёл, потому что Composer script вызывает `phpunit --coverage`, а PHPUnit 13 считает эту опцию неоднозначной.

## Заметки

`composer cs:fix` дополнительно исправил форматирование в нескольких файлах Media, Shared и System, потому что `composer qa` требовал чистый результат `composer cs` по всему репозиторию.
