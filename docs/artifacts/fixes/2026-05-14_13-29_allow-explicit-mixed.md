---
date: 2026-05-14 13:29
source: text
status: done
---

# Фикс: разрешить явный mixed

## Контекст

Пользователь уточнил правило: полностью запрещать `mixed` нельзя, нужно запрещать только ситуацию, когда в PHPDoc не указан generic и PHPStan получает неявный `mixed`.

Учтены `docs/rules.md`; `docs/arch.md` отсутствует.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `tools/phpstan/src/TypeContractInspector.php` | Проверка `mixed` заменена на проверку только неявного `MixedType`; template-типы не считаются нарушением | Разрешить осознанный явный `mixed` и ловить пропущенные generic-параметры |
| 2 | `tools/phpstan/src/Rules/TypeContractRule.php` | Убрана проверка native `mixed` | Native `mixed` больше не является ошибкой сам по себе |
| 3 | `tools/phpstan/src/TypeContractViolation.php` | Identifier заменён на `project.noImplicitMixedType` | Название ошибки теперь соответствует смыслу |
| 4 | `tests/Unit/PHPStan/*` | Обновлены fixtures и ожидания: явный `mixed` разрешён, `@return array` и похожие PHPDoc без generic запрещены | Зафиксировать новую семантику правила |
| 5 | `docs/rules.md` | Описано, что запрещён неявный `mixed` из-за пропущенного generic, а явный `mixed` допустим | Документация соответствует текущему quality gate |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `composer validate --strict` | ✓ | `composer.json` валиден |
| `composer phpstan` | ✓ | No errors |
| `composer test -- --filter PhpStan` | ✓ | 3 tests, 4 assertions |
| `composer test` | ✓ | Exit 0; есть существующий risky `Tests\Unit\DemoTest::testDemo` |
| `composer cs:fix -- --dry-run --diff` | ✓ | Изменений форматирования нет |

## Открытые вопросы

Нет.
