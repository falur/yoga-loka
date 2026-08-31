---
date: 2026-05-12 15:03
source: text
status: done
---

# Фикс: namespace для PHPStan tooling

## Контекст

Пользователь указал, что `App\PHPStan` создаёт путаницу, потому что `App\` в проекте соответствует runtime-коду из `app/src`. Нужно переименовать tooling namespace в `Tools\PHPStan` без изменения поведения правил.

Учтены `docs/rules.md`; `docs/arch.md` отсутствует.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `composer.json` | Dev-autoload заменён с `App\PHPStan\` на `Tools\PHPStan\` | Разделить runtime `App\` и tooling-код |
| 2 | `phpstan.neon` | PHPStan services переведены на `Tools\PHPStan\*` | PHPStan должен загружать правила по новому namespace |
| 3 | `tools/phpstan/src/*` | Namespace и imports переименованы в `Tools\PHPStan` | Убрать семантическую путаницу |
| 4 | `tests/Unit/PHPStan/TypeContractRuleTest.php` | Imports обновлены на `Tools\PHPStan` | Сохранить тестовое покрытие правила |
| 5 | `docs/plans/*`, `docs/executions/*` | Упоминания namespace обновлены | Документация должна соответствовать текущему коду |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `composer dump-autoload` | ✓ | Autoload пересобран |
| `composer validate --strict` | ✓ | `composer.json` валиден |
| `composer phpstan` | ✓ | No errors |
| `composer test -- --filter PhpStan` | ✓ | 2 tests, 3 assertions |
| `composer cs:fix -- --dry-run --diff` | ✓ | Изменений форматирования нет |

## Открытые вопросы

Нет.
