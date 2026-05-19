---
date: 2026-05-19 14:47
source: text
status: done
---

# Фикс: DIRECTORY_SEPARATOR в OpenApiConfig

## Контекст

Пользователь указал, что в PHP уже есть `DIRECTORY_SEPARATOR`, поэтому локальная константа `OpenApiConfig::DIRECTORY_SEPARATOR` не нужна.

Учтены `docs/rules.md` и `docs/arch.md`.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `app/src/Infrastructure/Configuration/OpenApi/OpenApiConfig.php` | Удалена локальная `DIRECTORY_SEPARATOR`, `rtrim()`/`ltrim()` используют встроенную `\DIRECTORY_SEPARATOR` | Убрать дубль стандартной PHP-константы |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `vendor/bin/php-cs-fixer fix --using-cache=no --config=.php-cs-fixer.dist.php app/src/Infrastructure/Configuration/OpenApi/OpenApiConfig.php` | ✓ | Дополнительных правок нет |
| `php -l app/src/Infrastructure/Configuration/OpenApi/OpenApiConfig.php` | ✓ | Синтаксис корректный |
| `vendor/bin/phpstan analyse app/src/Infrastructure/Configuration/OpenApi/OpenApiConfig.php` | ✓ | Ошибок нет |

## Открытые вопросы

Нет
