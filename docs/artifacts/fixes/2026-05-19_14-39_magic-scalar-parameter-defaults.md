---
date: 2026-05-19 14:39
source: text
status: done
---

# Фикс: default values параметров в magic scalar rule

## Контекст

Пользователь указал на `RedisCacheStorage`: значения по умолчанию параметров конструктора не нужно выносить в отдельные константы только ради `project.magicScalarLiteral`.

Учтены `docs/rules.md` и `docs/arch.md`.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `tools/phpstan/src/Rules/DisallowMagicScalarLiteralRule.php` | Добавлено исключение для literals, которые являются default value параметра метода/функции | Разрешить самодокументируемые значения в сигнатурах |
| 2 | `tools/phpstan/tests/Unit/PHPStan/Fixtures/MagicScalarLiteralsAllowed.php` | Добавлен allowed-сценарий с Redis-like default values | Зафиксировать новое исключение тестом |
| 3 | `app/src/Infrastructure/Cache/RedisCacheStorage.php` | Удалены `DEFAULT_DSN` и `DEFAULT_NAMESPACE`; значения возвращены в параметры конструктора | Убрать инженерные константы без смысловой нагрузки |
| 4 | `docs/rules.md`, `tools/phpstan/README.md` | Документировано исключение для default values параметров | Синхронизировать правила и tooling docs |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `vendor/bin/php-cs-fixer fix --using-cache=no --config=.php-cs-fixer.dist.php ...` | ✓ | Затронутые PHP-файлы без дополнительных правок |
| `vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no --config=.php-cs-fixer.dist.php ...` | ✓ | Повторный прогон без diff |
| `php -l ...` | ✓ | Синтаксис затронутых PHP-файлов корректный |
| `composer phpstan-rules:test -- --filter DisallowMagicScalarLiteralRuleTest` | ✓ | 2 tests, 3 assertions |
| `composer phpstan-rules:phpstan` | ✓ | Ошибок нет |
| `vendor/bin/phpstan analyse app/src/Infrastructure/Cache/RedisCacheStorage.php` | ✓ | Ошибок нет |

## Открытые вопросы

Нет
