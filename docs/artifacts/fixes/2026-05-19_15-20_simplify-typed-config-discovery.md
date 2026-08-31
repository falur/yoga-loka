---
date: 2026-05-19 15:20
source: text
status: done
---

# Фикс: упрощение typed config discovery

## Контекст

Пользователь указал, что предыдущая реализация typed config discovery получилась слишком усложнённой.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `app/src/Infrastructure/Framework/Bootloader/ConfigBootloader.php` | Убран набор мелких helper-ов для Finder discovery; сканирование, построение FQCN и регистрация singleton-ов теперь в одном методе `configSingletons()` | Сделать bootloader проще для чтения |
| 2 | `app/src/Infrastructure/Configuration/Cache/CacheConfig.php`, `app/src/Infrastructure/Configuration/OpenApi/OpenApiConfig.php` | `configName()` оставлен обязательным методом, строка секции помечена локальным `// @phpstan-ignore project.magicScalarLiteral` | Не усложнять общее PHPStan-правило ради двух технических строк |
| 3 | `tools/phpstan/src/Rules/DisallowMagicScalarLiteralRule.php` | Убрано специальное исключение для `configName()` | Правило magic scalar осталось проще |
| 4 | `tools/phpstan/tests/Unit/PHPStan/Fixtures/MagicScalarLiteralsAllowed.php`, `docs/rules.md`, `tools/phpstan/README.md` | Убрано описание и fixture для исключения `configName()` | Документация и тесты соответствуют упрощённому правилу |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `php -l app/src/Infrastructure/Configuration/TypedConfig.php` | ✓ | Синтаксис корректен |
| `php -l app/src/Infrastructure/Configuration/Cache/CacheConfig.php` | ✓ | Синтаксис корректен |
| `php -l app/src/Infrastructure/Configuration/OpenApi/OpenApiConfig.php` | ✓ | Синтаксис корректен |
| `php -l app/src/Infrastructure/Framework/Bootloader/ConfigBootloader.php` | ✓ | Синтаксис корректен |
| `php -l tools/phpstan/src/Rules/DisallowMagicScalarLiteralRule.php` | ✓ | Синтаксис корректен |
| `vendor/bin/phpstan analyse app/src/Infrastructure/Configuration/TypedConfig.php app/src/Infrastructure/Configuration/Cache/CacheConfig.php app/src/Infrastructure/Configuration/OpenApi/OpenApiConfig.php app/src/Infrastructure/Framework/Bootloader/ConfigBootloader.php` | ✓ | Ошибок нет |
| `composer phpstan-rules:test -- --filter DisallowMagicScalarLiteralRuleTest` | ✓ | 2 tests, 3 assertions |
| `composer phpstan-rules:phpstan` | ✓ | Ошибок нет |
| `vendor/bin/phpunit tests/Unit/Infrastructure/Configuration/CacheConfigBindingTest.php tests/Unit/Infrastructure/Configuration/ConfigMapperTest.php` | ✓ | 6 tests, 28 assertions; есть 3 PHPUnit deprecations в текущем окружении |
| `vendor/bin/phpstan clear-result-cache` | ✓ | Очистка после правки собственного PHPStan rule-класса |
| `composer phpstan` | ✓ | Ошибок нет |
| `vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no --config=.php-cs-fixer.dist.php app/src/Infrastructure/Configuration/TypedConfig.php app/src/Infrastructure/Configuration/Cache/CacheConfig.php app/src/Infrastructure/Configuration/OpenApi/OpenApiConfig.php app/src/Infrastructure/Framework/Bootloader/ConfigBootloader.php tests/Unit/Infrastructure/Configuration/ConfigMapperTest.php tools/phpstan/src/Rules/DisallowMagicScalarLiteralRule.php tools/phpstan/tests/Unit/PHPStan/Fixtures/MagicScalarLiteralsAllowed.php` | ✓ | Форматирование чистое |

## Открытые вопросы

Нет
