---
date: 2026-05-19 15:15
source: text
status: done
---

# Фикс: configName вместо CONFIG_NAME

## Контекст

Пользователь предложил заменить `TypedConfig::CONFIG_NAME` на обязательный метод, чтобы реализацию нельзя было забыть. Учитывались `docs/rules.md` и `docs/arch.md`.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `app/src/Infrastructure/Configuration/TypedConfig.php` | Контракт теперь требует `public static function configName(): string` вместо константы `CONFIG_NAME` | Метод интерфейса явно обязателен для каждого typed config DTO |
| 2 | `app/src/Infrastructure/Configuration/Cache/CacheConfig.php` | Добавлен `configName()` с возвратом `cache` | Имя секции остаётся рядом с DTO и проверяется контрактом |
| 3 | `app/src/Infrastructure/Configuration/OpenApi/OpenApiConfig.php` | Добавлен `configName()` с возвратом `openapi` | Имя секции остаётся рядом с DTO и проверяется контрактом |
| 4 | `app/src/Infrastructure/Framework/Bootloader/ConfigBootloader.php` | Discovery-регистрация использует `$configClass::configName()` | Bootloader больше не зависит от константы |
| 5 | `tests/Unit/Infrastructure/Configuration/ConfigMapperTest.php` | Тесты используют `CacheConfig::configName()` | Тесты следуют новому контракту |
| 6 | `tools/phpstan/src/Rules/DisallowMagicScalarLiteralRule.php` | Добавлено узкое исключение для прямого `return '...'` в `public static configName(): string` | Не заставлять выносить имя config-секции обратно в константу |
| 7 | `tools/phpstan/tests/Unit/PHPStan/Fixtures/MagicScalarLiteralsAllowed.php`, `docs/rules.md`, `tools/phpstan/README.md` | Добавлен fixture и документация исключения | Зафиксировать и описать новое разрешённое место |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `php -l app/src/Infrastructure/Configuration/TypedConfig.php && php -l app/src/Infrastructure/Configuration/Cache/CacheConfig.php && php -l app/src/Infrastructure/Configuration/OpenApi/OpenApiConfig.php && php -l app/src/Infrastructure/Framework/Bootloader/ConfigBootloader.php && php -l tools/phpstan/src/Rules/DisallowMagicScalarLiteralRule.php && php -l tools/phpstan/tests/Unit/PHPStan/Fixtures/MagicScalarLiteralsAllowed.php` | ✓ | Синтаксис корректен |
| `vendor/bin/phpstan analyse app/src/Infrastructure/Configuration/TypedConfig.php app/src/Infrastructure/Configuration/Cache/CacheConfig.php app/src/Infrastructure/Configuration/OpenApi/OpenApiConfig.php app/src/Infrastructure/Framework/Bootloader/ConfigBootloader.php` | ✓ | Ошибок нет |
| `composer phpstan-rules:test -- --filter DisallowMagicScalarLiteralRuleTest` | ✓ | 2 tests, 3 assertions |
| `composer phpstan-rules:phpstan` | ✓ | Ошибок нет |
| `vendor/bin/phpunit tests/Unit/Infrastructure/Configuration/CacheConfigBindingTest.php tests/Unit/Infrastructure/Configuration/ConfigMapperTest.php` | ✓ | 6 tests, 28 assertions; есть 3 PHPUnit deprecations в текущем окружении |
| `vendor/bin/phpstan clear-result-cache` | ✓ | Очистка потребовалась после правки собственного PHPStan rule-класса |
| `composer phpstan` | ✓ | Ошибок нет |
| `vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no --config=.php-cs-fixer.dist.php app/src/Infrastructure/Configuration/TypedConfig.php app/src/Infrastructure/Configuration/Cache/CacheConfig.php app/src/Infrastructure/Configuration/OpenApi/OpenApiConfig.php app/src/Infrastructure/Framework/Bootloader/ConfigBootloader.php tests/Unit/Infrastructure/Configuration/ConfigMapperTest.php tools/phpstan/src/Rules/DisallowMagicScalarLiteralRule.php tools/phpstan/tests/Unit/PHPStan/Fixtures/MagicScalarLiteralsAllowed.php` | ✓ | Форматирование чистое |

## Открытые вопросы

Нет
