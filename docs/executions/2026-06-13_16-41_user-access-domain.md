---
plan: docs/plans/2026-06-13_14-39_user-access-domain.md
started: 2026-06-13 16:41
finished: 2026-06-13 17:16
status: done
---

# Журнал: слой данных/домена User и Access

## Шаги

| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Общая основа: ext-intl и enum Locale | `composer.json`, `composer.lock`, `app/src/Shared/Domain/Enum/Locale.php`, `tests/Unit/Shared/Domain/Enum/LocaleTest.php`, `tests/Kernel/Shared/Infrastructure/Configuration/LocaleConfigTest.php` | `make phpstan`; `make test`; `make test-coverage` | done |
| 2 | Модуль User: домен | `app/src/Modules/User/Domain/**`, `tests/Unit/Modules/User/Domain/**` | `make test-unit`; `make phpstan`; `make test`; `make test-coverage` | done |
| 3 | Модуль User: персистентность | `app/database/migrations/20260613.143901_0_create_user_domain_tables.php`, `app/src/Modules/User/Infrastructure/Cycle/**`, `app/src/Modules/User/Repository/**`, `tests/Unit/Modules/User/Infrastructure/Cycle/**`, `tests/Feature/Modules/User/Repository/**` | `make phpstan`; `make migrate`; `make test-feature`; `make test`; `make test-coverage` | done |
| 4 | Модуль Access: домен и персистентность | `app/database/migrations/20260613.143902_0_create_access_domain_tables.php`, `app/src/Modules/Access/**`, `tests/Unit/Modules/Access/**`, `tests/Feature/Modules/Access/**` | `make phpstan`; `make migrate`; `make test-feature`; `make test`; `make test-coverage` | done |

## Заметки

- Выполнение идёт в текущей ветке `main`, как выбрал пользователь.
- Перед правками прочитаны `docs/rules.md`, `docs/arch.md`, `docs/code-examples.md` и план.
- Найден конфликт плана с правилами: `Locale::fromString()` с переоборачиванием `ValueError` нарушал запрет `try-catch` в домене и был pass-through фабрикой. По решению пользователя метод не добавляется, используется стандартный enum API.

## Изменения в docs

- Новых архитектурных правил по итогам выполнения не добавлялось.

## Финальная проверка

- `make phpstan` — успешно.
- `make migrate` — успешно, применены миграции `create_user_domain_tables` и `create_access_domain_tables`.
- `make test-feature` — успешно.
- `make test` — успешно, есть 1 существующий PHPUnit notice.
- `make test-coverage` — успешно, покрытие 100.00%.
