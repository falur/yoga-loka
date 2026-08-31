---
plan: docs/plans/2026-05-14_15-46_phpstan-require-named-arguments.md
started: 2026-05-14 15:55
finished: 2026-05-14 16:10
status: done
---

# Журнал: PHPStan require named arguments

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Добавить правило именованных аргументов | `tools/phpstan/src/Rules/RequireNamedArgumentsRule.php`, `tools/phpstan/extension.neon`, `tools/phpstan/tests/Unit/PHPStan/RequireNamedArgumentsRuleTest.php`, `tools/phpstan/tests/Unit/PHPStan/Fixtures/NamedArgumentsAllowed.php`, `tools/phpstan/tests/Unit/PHPStan/Fixtures/NamedArgumentsForbidden.php`, `app/src/Application/Exception/Renderer/ViewRenderer.php`, `app/src/Endpoint/Web/Middleware/LocaleSelector.php`, `tools/phpstan/src/Rules/TypeContractRule.php`, `tools/phpstan/src/TypeContracts/PhpDocContractTypeCollector.php`, `tools/phpstan/src/TypeContracts/TypeContractInspector.php`, `docs/rules.md` | `composer phpstan-rules:test -- --filter RequireNamedArgumentsRuleTest`, `composer phpstan-rules:phpstan`, `composer phpstan`, `rg -n "RequireNamedArgumentsRule|project\\.namedArgumentsRequired|phpstan.rules.rule" tools/phpstan docs/rules.md` | done |

## Заметки

- `sprintf`-подобные variadic-вызовы нельзя безопасно записать как `format: ..., ...$args`: PHPStan/PHP запрещает unpack после named argument. Для таких случаев использован полный unpack-массив, чтобы не создавать обычные позиционные аргументы.
- `composer test` завершился с кодом 0, но корневой PHPUnit оставляет существующий `Tests\Unit\DemoTest::testDemo` risky из-за не снятых handlers и показывает vendor deprecation `Yiisoft\ErrorHandler\Renderer\XmlRenderer::renderVerbose()`.

## Изменения в docs

- В `docs/rules.md` добавлено правило: вызовы с двумя и более обычными аргументами должны использовать именованные аргументы; variadic unpack не считается обычным аргументом.

## Финальная проверка

| Команда | Результат |
|---|---|
| `composer phpstan-rules:test -- --filter RequireNamedArgumentsRuleTest` | pass: 3 tests, 3 assertions |
| `composer phpstan-rules:phpstan` | pass |
| `composer phpstan` | pass; корневой анализ и анализ `tools/phpstan` без ошибок |
| `rg -n "RequireNamedArgumentsRule|project\\.namedArgumentsRequired|phpstan.rules.rule" tools/phpstan docs/rules.md` | pass; rule, identifier и регистрация найдены |
| `vendor/bin/php-cs-fixer fix --dry-run --diff -v` | pass; после правки порядка методов diff пустой |
| `composer test` | pass with warnings: корневой PHPUnit пометил `Tests\Unit\DemoTest::testDemo` как risky и вывел vendor deprecation; `tools/phpstan` tests pass: 12 tests, 18 assertions |
