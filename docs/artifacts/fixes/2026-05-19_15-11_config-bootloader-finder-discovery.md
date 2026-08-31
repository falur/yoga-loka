---
date: 2026-05-19 15:11
source: text
status: done
---

# Фикс: автодискавери typed config DTO

## Контекст

Пользователь предложил не держать ручной массив config-классов в `ConfigBootloader`, а искать такие файлы через Finder. Перед правками учтены `docs/rules.md` и `docs/arch.md`.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `app/src/Infrastructure/Configuration/TypedConfig.php` | Добавлен общий контракт typed config DTO с `CONFIG_NAME` | Дать bootloader-у явный признак, какие найденные `*Config.php` регистрировать |
| 2 | `app/src/Infrastructure/Configuration/Cache/CacheConfig.php` | Класс реализует `TypedConfig` и объявляет `CONFIG_NAME = 'cache'` | Имя секции хранится рядом с DTO конфига |
| 3 | `app/src/Infrastructure/Configuration/OpenApi/OpenApiConfig.php` | Класс реализует `TypedConfig` и объявляет `CONFIG_NAME = 'openapi'` | Имя секции хранится рядом с DTO конфига |
| 4 | `app/src/Infrastructure/Framework/Bootloader/ConfigBootloader.php` | Ручной список config-классов заменён на Symfony Finder discovery по `Infrastructure/Configuration/**/*Config.php` с фильтром `TypedConfig` | Новые config DTO будут регистрироваться без правки bootloader-а |
| 5 | `tests/Unit/Infrastructure/Configuration/CacheConfigBindingTest.php` | Добавлена проверка singleton-регистрации `OpenApiConfig` | Зафиксировать, что discovery регистрирует оба текущих typed config DTO |
| 6 | `tests/Unit/Infrastructure/Configuration/ConfigMapperTest.php` | Используется `CacheConfig::CONFIG_NAME` вместо строковой секции | Тест следует новому контракту |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `php -l app/src/Infrastructure/Configuration/TypedConfig.php && php -l app/src/Infrastructure/Configuration/Cache/CacheConfig.php && php -l app/src/Infrastructure/Configuration/OpenApi/OpenApiConfig.php && php -l app/src/Infrastructure/Framework/Bootloader/ConfigBootloader.php` | ✓ | Синтаксис корректен |
| `vendor/bin/phpstan analyse app/src/Infrastructure/Configuration/TypedConfig.php app/src/Infrastructure/Configuration/Cache/CacheConfig.php app/src/Infrastructure/Configuration/OpenApi/OpenApiConfig.php app/src/Infrastructure/Framework/Bootloader/ConfigBootloader.php` | ✓ | Ошибок нет |
| `vendor/bin/phpunit tests/Unit/Infrastructure/Configuration/CacheConfigBindingTest.php tests/Unit/Infrastructure/Configuration/ConfigMapperTest.php` | ✓ | 6 tests, 28 assertions; есть 3 PHPUnit deprecations в текущем окружении |
| `composer phpstan` | ✓ | Ошибок нет |
| `vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no --config=.php-cs-fixer.dist.php app/src/Infrastructure/Configuration/TypedConfig.php app/src/Infrastructure/Configuration/Cache/CacheConfig.php app/src/Infrastructure/Configuration/OpenApi/OpenApiConfig.php app/src/Infrastructure/Framework/Bootloader/ConfigBootloader.php tests/Unit/Infrastructure/Configuration/CacheConfigBindingTest.php tests/Unit/Infrastructure/Configuration/ConfigMapperTest.php` | ✓ | Форматирование чистое |

## Открытые вопросы

Нет
