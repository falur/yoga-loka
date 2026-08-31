---
plan: docs/plans/2026-05-15_14-07_phpstan-typed-constants.md
started: 2026-05-15 14:17
finished: 2026-05-15 14:26
status: superseded
---

# Журнал: PHPStan typed constants rule

## Статус

Реализация отменена после повторной проверки: отдельное правило по class-like константам некорректно для текущего проекта, потому что часть типов уже задаётся контрактами родителя в PHPDoc, а реальная проблема шире и касается array-контрактов методов тоже. Правило, его регистрация и тесты удалены.

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Реализовать и зарегистрировать правило | `tools/phpstan/src/Rules/RequireTypedConstantsRule.php`, `tools/phpstan/extension.neon`, `tools/phpstan/tests/Unit/PHPStan/RequireTypedConstantsRuleTest.php`, `tools/phpstan/tests/Unit/PHPStan/Fixtures/TypedConstantsAllowed.php`, `tools/phpstan/tests/Unit/PHPStan/Fixtures/TypedConstantsForbidden.php` | `composer phpstan-rules:test` | done |
| 2 | Привести текущий код приложения к новому контракту | `tools/phpstan/src/TypeContracts/TypeContractViolation.php`, `tools/phpstan/src/TypeContracts/TypeContractInspector.php`, `tools/phpstan/src/Rules/DisallowLooseComparisonRule.php`, `tools/phpstan/src/Rules/RequireNamedArgumentsRule.php`, `tools/phpstan/src/Rules/RequireStrictTypesRule.php`, `app/src/Infrastructure/Framework/Bootloader/AppBootloader.php`, `app/src/Infrastructure/Framework/Bootloader/ConfigBootloader.php`, `app/src/Infrastructure/Framework/Bootloader/ExceptionHandlerBootloader.php`, `app/src/Infrastructure/Framework/Bootloader/RoutesBootloader.php`, `tools/phpstan/tests/Unit/PHPStan/Fixtures/AllowedContracts.php` | `composer phpstan`; `composer phpstan-rules:phpstan`; `composer phpstan-rules:test` | done |
| 3 | Закрепить итог полным прогоном | `docs/rules.md`, `docs/plans/2026-05-15_14-07_phpstan-typed-constants.md`, `docs/executions/2026-05-15_14-17_phpstan-typed-constants.md` | `composer phpstan-rules:test`; `composer phpstan-rules:phpstan`; `composer phpstan`; `composer test` | done |

## Заметки

- Для покрытия enum-констант правило слушает `ClassLike::class` и внутри проверяет `ClassConst`: PHPStan не вызвал `Rule<ClassConst>` для enum-констант в unit-тесте, хотя AST-узел есть.
- После замечания пользователя исключение для Spiral tuple-типа убрано. Binding-константы не должны использовать PHPDoc shapes; места, где Spiral требует callable tuple, перенесены из констант в методы/атрибуты.

## Изменения в docs

- В `docs/rules.md` добавлено правило: все class-like константы должны иметь native type, а array-константы должны иметь точный PHPDoc `@var`.

## Финальная проверка

| Команда | Результат | Заметки |
|---|---|---|
| `composer phpstan-rules:test` | pass | 16 tests, 24 assertions |
| `composer phpstan-rules:phpstan` | pass | No errors |
| `composer phpstan` | pass | No errors по `app/src` и `tools/phpstan/src` |
| `composer test` | pass with issues | Exit code 0; основной PHPUnit: 6 tests, 27 assertions, 3 deprecations, 3 risky tests из-за error/exception handlers; phpstan rules PHPUnit: 16 tests, 24 assertions |
| `git diff --check` | pass | No output |
| `vendor/bin/php-cs-fixer fix --dry-run --diff tools/phpstan/tests/Unit/PHPStan/Fixtures/TypedConstantsForbidden.php` | pass | No files |
| `rg -n "array\\{" app/src tools/phpstan/src tools/phpstan/tests/Unit/PHPStan/Fixtures/AllowedContracts.php docs/rules.md` | pass | `array{` остался только в примере запрета в `docs/rules.md` |

Полный `vendor/bin/php-cs-fixer fix --dry-run -v` вернул код 8 из-за generated-файлов `runtime/cache/listeners/*`. Единственное замечание по изменённой fixture исправлено и проверено отдельным dry-run.
