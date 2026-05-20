---
date: 2026-05-20 16:49
source: text
status: done
---

# Фикс: исключение magic scalar для TypedConfig

## Контекст

Пользователь попросил добавить исключения для `TypedConfig`, чтобы в `public static function configName(): string` не требовался локальный `// @phpstan-ignore project.magicScalarLiteral`. Учитывались `docs/rules.md` и `docs/arch.md`.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `tools/phpstan/src/Rules/DisallowMagicScalarLiteralRule.php` | Добавлено узкое исключение для прямого `return '...'` из `public static configName(): string` у классов, реализующих `App\Infrastructure\Configuration\TypedConfig` | Разрешить имя config-секции без локального подавления PHPStan |
| 2 | `tools/phpstan/tests/Unit/PHPStan/Fixtures/MagicScalarLiteralsAllowed.php`, `tools/phpstan/tests/Unit/PHPStan/Fixtures/MagicScalarLiteralsForbidden.php`, `tools/phpstan/tests/Unit/PHPStan/DisallowMagicScalarLiteralRuleTest.php` | Добавлены проверки разрешённого `TypedConfig::configName()` и запрета похожего метода без `TypedConfig` | Зафиксировать границы исключения |
| 3 | `app/src/Infrastructure/Configuration/*/*Config.php`, `docs/code-examples.md` | Убраны локальные `// @phpstan-ignore project.magicScalarLiteral` у `configName()` | Исключение теперь работает на уровне правила |
| 4 | `docs/rules.md`, `tools/phpstan/README.md` | Документировано исключение для `TypedConfig::configName()` | Синхронизировать правила и документацию с поведением PHPStan |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `composer tools:phpstan:qa` | ✓ | PHPStan для пакета правил и 16 unit-тестов прошли |
| `vendor/bin/phpstan analyse --debug` | ✓ | Полная проверка `app/src` без кэша прошла |
| `composer phpstan` | ✓ | Полная проверка `app/src` прошла |
| `vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no --config=.php-cs-fixer.dist.php ...` | ✓ | Форматирование затронутых PHP-файлов чистое |

## Открытые вопросы

Нет
