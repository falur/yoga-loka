---
date: 2026-05-18 18:33
source: text
status: done
---

# Фикс: правила PHP CS Fixer для `new`

## Контекст

Пользователь попросил добавить последние правила CS Fixer, чтобы убирать скобки вокруг `new`.
Учтены `docs/rules.md` и `docs/arch.md`.

Проверены актуальные правила PHP CS Fixer:

- `new_expression_parentheses` убирает внешние скобки у `new` с дальнейшим вызовом: `(new Foo())->bar()` -> `new Foo()->bar()`.
- `new_with_parentheses` убирает пустые скобки после `new`: `new Foo()` -> `new Foo`.

Локальная версия инструмента: PHP CS Fixer 3.95.1.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `.php-cs-fixer.dist.php` | Базовый Spiral-конфиг сохраняется в `$config`, затем через `setRules()` добавлены `new_expression_parentheses` и `new_with_parentheses` | Включить актуальные правила для удаления лишних скобок вокруг `new` |
| 2 | `app.php`, `app/config/*`, `app/src/*`, `tests/*`, `tools/*`, `docker/smoke/redis-cache.php` | `php-cs-fixer` применил новый стиль `new`: убраны пустые `()` и внешние скобки у chained `new` | Привести текущий код к обновлённому стилю |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `vendor/bin/php-cs-fixer --version` | ✓ | PHP CS Fixer 3.95.1 |
| `vendor/bin/php-cs-fixer describe new_with_parentheses` | ✓ | Правило существует и поддерживает `anonymous_class`, `named_class` |
| `vendor/bin/php-cs-fixer describe new_expression_parentheses` | ✓ | Правило существует и поддерживает `use_parentheses` |
| `vendor/bin/php-cs-fixer fix --using-cache=no --config=.php-cs-fixer.dist.php` | ✓ | Применил форматирование текущего рабочего дерева |
| `vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no --config=.php-cs-fixer.dist.php` | ✓ | После применения diff пустой |
| `php -l ...` по PHP-файлам, изменённым fixer | ✓ | Синтаксических ошибок нет |
| `composer phpstan` | ✗ | 378 ошибок в текущем незакоммиченном дереве после включения `tools` в проверки; не связано с добавлением CS Fixer правил |

## Открытые вопросы

Нет
