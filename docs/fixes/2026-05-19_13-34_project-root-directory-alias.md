---
date: 2026-05-19 13:34
source: text
status: done
---

# Фикс: имя alias корня проекта

## Контекст

Пользователь попросил переименовать неочевидную константу `ROOT_DIRECTORY_NAME = 'root'` и проверить остальные такие места.

Учтены `docs/rules.md` и `docs/arch.md`.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `app/src/Infrastructure/Configuration/OpenApi/OpenApiConfig.php` | `ROOT_DIRECTORY_NAME` переименована в `PROJECT_ROOT_DIRECTORY_ALIAS` | Название явно говорит, что `root` — alias директории Spiral |
| 2 | OpenAPI command/controller файлы | Все чтения корня проекта переведены на `OpenApiConfig::PROJECT_ROOT_DIRECTORY_ALIAS` | Убрать дубль и старое неочевидное имя |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `vendor/bin/php-cs-fixer fix --using-cache=no --config=.php-cs-fixer.dist.php ...` | ✓ | Затронутые файлы без дополнительных правок |
| `php -l ...` | ✓ | Синтаксис четырёх файлов корректный |
| `vendor/bin/phpstan analyse ...` | ✓ | Ошибок нет |
| `rg -n "ROOT_DIRECTORY_NAME|PROJECT_ROOT_DIRECTORY_ALIAS|\['root'\s*=>" app.php app/src tests/TestCase.php -S` | ✓ | Старого имени нет; `root` остался только в регистрации alias |

## Открытые вопросы

Нет
