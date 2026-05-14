---
plan: docs/plans/2026-05-14_13-40_phpstan-require-strict-types-rule.md
started: 2026-05-14 13:52
finished: 2026-05-14 13:55
status: done
---

# Журнал: PHPStan require strict_types rule

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Добавить правило strict_types и тесты | `tools/phpstan/src/Rules/RequireStrictTypesRule.php`, `phpstan.neon`, `tests/Unit/PHPStan/RequireStrictTypesRuleTest.php`, `tests/Unit/PHPStan/Fixtures/StrictTypesValid.php`, `tests/Unit/PHPStan/Fixtures/*.fixture`, `docs/rules.md` | `composer test -- --filter RequireStrictTypesRuleTest`; `composer phpstan`; `composer test -- --filter PhpStan`; `rg -n "RequireStrictTypesRule\|phpstan.rules.rule" phpstan.neon`; `rg -n "app/src\|app/config\|tools/phpstan/src\|tests" tools/phpstan/src/Rules/RequireStrictTypesRule.php` | done |

## Заметки

- `processNode()` оставлен с сигнатурой интерфейса `PhpParser\Node`; фактический `FileNode` задаётся через `@implements Rule<FileNode>` и `getNodeType()`.
- Негативные fixtures сохранены с расширением `.fixture`, чтобы PHPStan `RuleTestCase` проверял содержимое, а php-cs-fixer не переписывал намеренно неверные `declare`.

## Изменения в docs

- В `docs/rules.md` добавлено правило: все PHP-файлы, анализируемые PHPStan, должны начинаться с `declare(strict_types=1)`.

## Финальная проверка

| Команда | Результат |
|---|---|
| `composer test -- --filter RequireStrictTypesRuleTest` | OK: 6 tests, 11 assertions |
| `composer phpstan` | OK: no errors |
| `composer test -- --filter PhpStan` | OK: 9 tests, 15 assertions |
| `rg -n "RequireStrictTypesRule\|phpstan.rules.rule" phpstan.neon` | OK: правило и теги найдены |
| `rg -n "app/src\|app/config\|tools/phpstan/src\|tests" tools/phpstan/src/Rules/RequireStrictTypesRule.php` | OK: совпадений нет |
| `vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no` | OK: files empty |
| `composer test` | Exit code 0: 10 tests, 17 assertions, 1 existing risky test `Tests\Unit\DemoTest::testDemo` |
