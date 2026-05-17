---
plan: docs/plans/2026-05-14_16-35_phpstan-disallow-loose-comparisons.md
started: 2026-05-14 16:44
finished: 2026-05-14 16:48
status: done
---

# Журнал: Запрет loose comparisons в PHPStan

## Шаги

| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Добавить правило strict comparisons | `tools/phpstan/src/Rules/DisallowLooseComparisonRule.php`, `tools/phpstan/extension.neon`, `tools/phpstan/tests/Unit/PHPStan/DisallowLooseComparisonRuleTest.php`, `tools/phpstan/tests/Unit/PHPStan/Fixtures/LooseComparisonsAllowed.php`, `tools/phpstan/tests/Unit/PHPStan/Fixtures/LooseComparisonsForbidden.php`, `docs/rules.md` | `composer phpstan-rules:test -- --filter DisallowLooseComparisonRuleTest`; `composer phpstan-rules:phpstan`; `composer phpstan`; `rg -n "DisallowLooseComparisonRule\|project\\.loose(Equal\|NotEqual)Forbidden" tools/phpstan docs/rules.md`; `rg -n --pcre2 '(?<![=!])==(?![=>])\|(?<![=!])!=(?!=)\|<>' app/src tools/phpstan/src` returned no matches; `rg -n "===\|!==\|==\|!=\|<>" docs/rules.md` | done |

## Заметки

- `docs/arch.md` отсутствует; выполнение сверено с `docs/rules.md` и фактической структурой `tools/phpstan`.
- Текст сообщения про `==` собран без литерала `==` в `tools/phpstan/src`, чтобы проектная `rg`-проверка не давала ложное совпадение по строке сообщения.

## Изменения в docs

- В `docs/rules.md` добавлено правило: сравнения значений пишутся только через `===` и `!==`; `==`, `!=` и `<>` запрещены.

## Финальная проверка

| Команда | Результат | Примечание |
|---|---|---|
| `composer test` | passed | Exit code 0; корневой `Tests\Unit\DemoTest::testDemo` помечен PHPUnit как risky, также показан vendor deprecation из `yiisoft/error-handler`; `tools/phpstan` tests passed: 14 tests, 21 assertions |
| `composer phpstan` | passed | Корневой `phpstan.neon` и `tools/phpstan/phpstan.neon`: no errors |
| `vendor/bin/php-cs-fixer fix --dry-run --diff -v` | passed | Изменения форматирования не требуются |
