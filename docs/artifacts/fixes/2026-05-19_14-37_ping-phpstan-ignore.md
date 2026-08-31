---
date: 2026-05-19 14:37
source: text
status: done
---

# Фикс: локальное исключение для Ping workflow

## Контекст

Пользователь попросил не менять общее правило для технических литералов, а локально подавить PHPStan через `// @phpstan-ignore ...`.

Учтены `docs/rules.md` и `docs/arch.md`.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `app/src/Endpoint/Temporal/Ping.php` | Удалена константа `RESPONSE`, метод возвращает `'pong'` напрямую с `// @phpstan-ignore project.magicScalarLiteral` | Не плодить инженерную константу ради smoke workflow |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `php -l app/src/Endpoint/Temporal/Ping.php` | ✓ | Синтаксис корректный |
| `vendor/bin/phpstan analyse app/src/Endpoint/Temporal/Ping.php` | ✓ | Inline ignore принят, ошибок нет |
| `vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no --config=.php-cs-fixer.dist.php app/src/Endpoint/Temporal/Ping.php` | ✓ | Diff пустой |

## Открытые вопросы

Нет
