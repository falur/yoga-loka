---
review: docs/reviews/2026-05-25_21-07_uncommitted-public-packages.md
date: 2026-05-25 21:27
status: done
---

# Фиксы по ревью: Незакоммиченные изменения публичных пакетов

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | В lock-файлах повреждены ссылки Doctrine | `packages/phpstan-strict-rules/composer.lock`, `packages/spiral-openapi/composer.lock`, `packages/spiral-cqrs/composer.lock`, `packages/spiral-api-errors/composer.lock` | `rg -n "doctrine-gian_tiaga\|gian_tiaga\\.phpstan_strict_rules\\.org" packages/*/composer.lock` — совпадений нет | применено |
| 2 | README описывает не тот формат `OpenApi id`, который проверяет код | `packages/spiral-openapi/src/PHPStan/Rules/OpenApiAttributeRule.php`, `packages/spiral-openapi/tests/PHPStan/OpenApiAttributeRuleTest.php`, `packages/spiral-openapi/tests/PHPStan/Fixtures/OpenApiAttributeValid.fixture`, `packages/spiral-openapi/tests/PHPStan/Fixtures/OpenApiAttributeInvalid.fixture` | `composer -d packages/spiral-openapi test` — 21 тест, 480 проверок; `composer -d packages/spiral-openapi phpstan` — без ошибок | применено |

## Финальная проверка

- **Тесты пакетов:** `composer -d packages/phpstan-strict-rules test` — 15 тестов, 78 проверок; `composer -d packages/spiral-openapi test` — 21 тест, 480 проверок; `composer -d packages/spiral-cqrs test` — 21 тест, 187 проверок; `composer -d packages/spiral-api-errors test` — 21 тест, 117 проверок.
- **PHPStan пакетов:** `composer -d packages/phpstan-strict-rules phpstan`, `composer -d packages/spiral-openapi phpstan`, `composer -d packages/spiral-cqrs phpstan`, `composer -d packages/spiral-api-errors phpstan` — без ошибок.
- **PHPStan приложения:** `make phpstan` — без ошибок.
- **Тесты приложения:** `make test` — успешно; PHPUnit сообщил 26 deprecation-предупреждений.
- **Заметки:** оба обязательных пункта применены; пунктов «на усмотрение автора» и «не править» в ревью нет.
