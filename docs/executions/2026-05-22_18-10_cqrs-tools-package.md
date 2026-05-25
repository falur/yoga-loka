---
plan: docs/plans/2026-05-22_17-56_cqrs-tools-package.md
started: 2026-05-22 18:10
finished: 2026-05-22 18:30
status: done
---

# Журнал: CQRS tools-пакет

## Шаги

| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Создать пакет `tools/cqrs` | `tools/cqrs/**` | `composer validate --strict --no-interaction tools/cqrs/composer.json`; `composer -d tools/cqrs install --no-interaction`; `composer -d tools/cqrs test`; `composer -d tools/cqrs phpstan`; `grep -RIn -E 'namespace App\\\\|use App\\\\' tools/cqrs/src` | done |
| 2 | Подключить пакет к приложению | `composer.json`, `composer.lock`, `app/src/Shared/Infrastructure/Framework/Kernel.php`, `docs/code-examples.md`, `docs/arch.md`, `tests/Feature/CqrsContainerTest.php` | `composer update yoga-loka/cqrs-tools --no-interaction --ignore-platform-req=ext-redis`; `make test`; `docker compose ... composer test -- --display-deprecations --filter CqrsContainerTest`; `make phpstan` | done |
| 3 | Проверить полный контур пакета и приложения | `tools/cqrs/**`, `composer.lock`, `app/**`, `tests/**`, `docs/code-examples.md`, `docs/arch.md` | `composer -d tools/cqrs test`; `composer -d tools/cqrs phpstan`; `grep -RIn -E 'App\\\\Shared\\\\Infrastructure\\\\Bus|App\\\\Infrastructure\\\\Bus' app tests tools --exclude-dir=vendor`; `grep -RIn -E 'App\\\\Shared\\\\Infrastructure\\\\Bus' docs/code-examples.md docs/arch.md`; `make test`; `make phpstan` | done |

## Заметки

- Для изолированного `composer install` пакета добавлена зависимость `nyholm/psr7:^1.8`. Без неё `spiral/framework` не может разрешить виртуальную зависимость `psr/http-factory-implementation`; такой же подход уже используется в `tools/openapi`.

## Изменения в docs

- `docs/code-examples.md`: пример контроллера теперь использует `Tools\Cqrs\QueryBusInterface` и создаёт Query DTO до closure.
- `docs/arch.md`: добавлено, что инфраструктура шины живёт в `tools/cqrs`, а прикладные Command, Query и Handler остаются в модулях приложения.

## Финальная проверка

```text
composer -d tools/cqrs test
OK (21 tests, 75 assertions)

composer -d tools/cqrs phpstan
OK, no errors

grep app/tests/tools по старым namespace
OK, совпадений нет

grep docs/code-examples.md docs/arch.md по старому namespace
OK, совпадений нет

make test
OK, 123 tests, 474 assertions, 26 PHPUnit deprecations
Новый CqrsContainerTest отдельно прошёл без deprecations.

make phpstan
OK, no errors

composer.lock
OK, добавлен только path-пакет yoga-loka/cqrs-tools и stability flag
```
