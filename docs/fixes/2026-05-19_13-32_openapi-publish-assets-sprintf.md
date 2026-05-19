---
date: 2026-05-19 13:32
source: text
status: done
---

# Фикс: sprintf в OpenApiPublishAssetsCommand

## Контекст

Пользователь указал, что в `OpenApiPublishAssetsCommand` нужно заменить конкатенацию строк на `sprintf()` по правилу проекта.

Учтены `docs/rules.md` и `docs/arch.md`.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `app/src/Endpoint/Console/OpenApiPublishAssetsCommand.php` | Конкатенация путей и сообщений заменена на `sprintf()` | Соблюсти правило проекта про строки и сообщения |
| 2 | `app/src/Endpoint/Console/OpenApiPublishAssetsCommand.php` | Удалены одноразовые константы для частей путей и разделителя | Упростить код после перехода на форматные строки |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `vendor/bin/php-cs-fixer fix --using-cache=no --config=.php-cs-fixer.dist.php app/src/Endpoint/Console/OpenApiPublishAssetsCommand.php` | ✓ | Дополнительных правок нет |
| `php -l app/src/Endpoint/Console/OpenApiPublishAssetsCommand.php` | ✓ | Синтаксис корректный |
| `vendor/bin/phpstan analyse app/src/Endpoint/Console/OpenApiPublishAssetsCommand.php` | ✓ | Ошибок нет |
| `rg -n "SWAGGER_UI_SOURCE_DIRECTORY|SWAGGER_UI_TARGET_DIRECTORY|DIRECTORY_SEPARATOR| \.[^.]|[^.]\. " app/src/Endpoint/Console/OpenApiPublishAssetsCommand.php` | ✓ | Старых констант и конкатенаций не найдено |

## Открытые вопросы

Нет
