---
date: 2026-05-19 13:44
source: text
status: done
---

# Фикс: helper для создания каталогов Swagger UI assets

## Контекст

Пользователь указал на вложенный `if` в `OpenApiPublishAssetsCommand` при создании каталогов и предложил упростить код.

Учтены `docs/rules.md` и `docs/arch.md`.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `app/src/Endpoint/Console/OpenApiPublishAssetsCommand.php` | Добавлен `ensureDirectoryExists()` и оба места создания каталога переведены на него | Убрать вложенный `if` и дублирование ошибки |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `vendor/bin/php-cs-fixer fix --using-cache=no --config=.php-cs-fixer.dist.php app/src/Endpoint/Console/OpenApiPublishAssetsCommand.php` | ✓ | Дополнительных правок нет |
| `php -l app/src/Endpoint/Console/OpenApiPublishAssetsCommand.php` | ✓ | Синтаксис корректный |
| `vendor/bin/phpstan analyse app/src/Endpoint/Console/OpenApiPublishAssetsCommand.php` | ✓ | Ошибок нет |

## Открытые вопросы

Нет
