---
date: 2026-05-19 14:44
source: text
status: done
---

# Фикс: magic literals внутри exception-классов

## Контекст

Пользователь указал на `ConfigMappingException`: внутри exception-классов строковые шаблоны сообщений не нужно выносить в константы только ради `project.magicScalarLiteral`.

Учтены `docs/rules.md` и `docs/arch.md`.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `tools/phpstan/src/Rules/DisallowMagicScalarLiteralRule.php` | Добавлено исключение для классов с именем `*Exception` | Разрешить человекочитаемые сообщения и технические шаблоны внутри exception-классов |
| 2 | `tools/phpstan/tests/Unit/PHPStan/Fixtures/MagicScalarLiteralsAllowed.php` | Добавлен allowed-сценарий с exception factory и inline шаблоном сообщения | Зафиксировать новое исключение тестом |
| 3 | `app/src/Infrastructure/Configuration/Mapping/ConfigMappingException.php` | Удалены message constants, шаблоны возвращены в место использования | Убрать инженерные константы без смысловой нагрузки |
| 4 | `docs/rules.md`, `tools/phpstan/README.md` | Документировано исключение для `*Exception` | Синхронизировать правила и tooling docs |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `vendor/bin/php-cs-fixer fix --using-cache=no --config=.php-cs-fixer.dist.php ...` | ✓ | Затронутые PHP-файлы без дополнительных правок |
| `vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no --config=.php-cs-fixer.dist.php ...` | ✓ | Повторный прогон без diff |
| `php -l ...` | ✓ | Синтаксис затронутых PHP-файлов корректный |
| `composer phpstan-rules:test -- --filter DisallowMagicScalarLiteralRuleTest` | ✓ | 2 tests, 3 assertions |
| `composer phpstan-rules:phpstan` | ✓ | Ошибок нет |
| `vendor/bin/phpstan analyse app/src/Infrastructure/Configuration/Mapping/ConfigMappingException.php` | ✓ | Ошибок нет |

## Открытые вопросы

Нет
