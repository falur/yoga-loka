---
date: 2026-05-19 15:53
source: text
status: done
---

# Фикс: строковые литералы в sprintf и str()

## Контекст

Пользователь попросил разрешить строковые литералы внутри `sprintf()` и внутри `str()` и его методов. Учитывались `docs/rules.md` и `docs/arch.md`.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `tools/phpstan/src/Rules/DisallowMagicScalarLiteralRule.php` | Добавлено разрешение для строковых литералов в `sprintf()`, `str()` и цепочках методов, начинающихся с `str()` | Не требовать константы для технических шаблонов и фрагментов строковой сборки |
| 2 | `tools/phpstan/tests/Unit/PHPStan/Fixtures/MagicScalarLiteralsAllowed.php` | Добавлен разрешённый сценарий с `sprintf()` и `str(...)->replace(...)->append(...)` | Зафиксировать новое допустимое поведение |
| 3 | `tools/phpstan/tests/Unit/PHPStan/Fixtures/MagicScalarLiteralsForbidden.php`, `tools/phpstan/tests/Unit/PHPStan/DisallowMagicScalarLiteralRuleTest.php` | Добавлен отрицательный сценарий для обычного метода `replace()` вне `str()`-цепочки | Убедиться, что правило не разрешило произвольные method calls |
| 4 | `docs/rules.md` | Уточнён список разрешённых технических контекстов для `project.magicScalarLiteral` | Синхронизировать документацию с PHPStan-правилом |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `composer phpstan-rules:test -- --filter DisallowMagicScalarLiteralRuleTest` | ✓ | 2 tests, 3 assertions |
| `composer phpstan-rules:phpstan` | ✓ | No errors |
| `vendor/bin/php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.dist.php tools/phpstan/src/Rules/DisallowMagicScalarLiteralRule.php tools/phpstan/tests/Unit/PHPStan/DisallowMagicScalarLiteralRuleTest.php tools/phpstan/tests/Unit/PHPStan/Fixtures/MagicScalarLiteralsAllowed.php tools/phpstan/tests/Unit/PHPStan/Fixtures/MagicScalarLiteralsForbidden.php` | ✓ | No files |
| `composer phpstan-rules:test` | ✗ | Несвязанное падение: `DisallowLooseComparisonRuleTest::testRejectsLooseComparisons` получает 0 ошибок, потому что `Fixtures/LooseComparisonsForbidden.php` в рабочем дереве уже переписан на строгие сравнения |

## Открытые вопросы

Нет.
