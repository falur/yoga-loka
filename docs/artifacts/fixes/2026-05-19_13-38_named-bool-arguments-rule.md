---
date: 2026-05-19 13:38
source: text
status: done
---

# Фикс: bool literals в именованных аргументах

## Контекст

Пользователь указал, что bool-значения вроде `recursive: true` не нужно выносить в отдельные константы только ради имени. Нужно поправить проектное правило и привести текущие технические bool-константы к новому стилю.

Учтены `docs/rules.md` и `docs/arch.md`.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `docs/rules.md` | Правило magic values уточнено: `true`/`false` допустимы в технических именованных аргументах, доменные флаги остаются запрещены как голый bool | Зафиксировать новый стиль проекта |
| 2 | `tools/phpstan/src/Rules/DisallowMagicScalarLiteralRule.php` | Разрешены bool literals в именованных аргументах function/method/static calls, но не в `new ...(...)` | Синхронизировать PHPStan-правило с документацией |
| 3 | `tools/phpstan/tests/Unit/PHPStan/Fixtures/MagicScalarLiteralsAllowed.php` | Добавлен allowed-сценарий `recursive: true`, `bubble: false` | Закрыть новый разрешённый кейс тестом |
| 4 | `app/src/Endpoint/Console/OpenApiPublishAssetsCommand.php`, `app/src/Infrastructure/Framework/Bootloader/LoggingBootloader.php` | Удалены bool-константы, значения перенесены в именованные аргументы | Убрать лишнюю абстракцию |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `vendor/bin/php-cs-fixer fix --using-cache=no --config=.php-cs-fixer.dist.php ...` | ✓ | Затронутые PHP-файлы без дополнительных правок |
| `vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no --config=.php-cs-fixer.dist.php ...` | ✓ | Повторный прогон без diff |
| `php -l ...` | ✓ | Синтаксис затронутых PHP-файлов корректный |
| `composer phpstan-rules:test -- --filter DisallowMagicScalarLiteralRuleTest` | ✓ | 2 tests, 3 assertions |
| `composer phpstan-rules:phpstan` | ✓ | Ошибок нет |
| `vendor/bin/phpstan analyse app/src/Endpoint/Console/OpenApiPublishAssetsCommand.php app/src/Infrastructure/Framework/Bootloader/LoggingBootloader.php` | ✓ | Ошибок нет |
| `rg -n "const bool|CREATE_DIRECTORIES_RECURSIVELY|ERROR_LOG_BUBBLE" app/src -S` | ✓ | Совпадений нет |

## Открытые вопросы

Нет
