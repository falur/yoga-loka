---
date: 2026-05-19 14:52
source: text
status: done
---

# Фикс: исключение для callable tuple

## Контекст

Пользователь указал на `AppBootloader`: строковое имя метода внутри callable tuple `[self::class, 'domainCore']` не должно требовать отдельной константы. Учитывались `docs/rules.md` и `docs/arch.md`.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `app/src/Infrastructure/Framework/Bootloader/AppBootloader.php` | Удалена константа `DOMAIN_CORE_FACTORY_METHOD`, callable tuple теперь использует `[self::class, 'domainCore']` | Не плодить техническую константу ради имени метода callable |
| 2 | `tools/phpstan/src/Rules/DisallowMagicScalarLiteralRule.php` | Добавлено исключение для строкового имени метода во втором элементе callable tuple с `::class` первым элементом | Разрешить технический callable-контекст без ослабления правила для произвольных массивов |
| 3 | `tools/phpstan/tests/Unit/PHPStan/Fixtures/MagicScalarLiteralsAllowed.php` | Добавлен allowed fixture для callable tuple | Зафиксировать поведение правила тестом |
| 4 | `docs/rules.md`, `tools/phpstan/README.md` | Документировано исключение для callable tuple | Синхронизировать правило проекта и описание tooling |
| 5 | `app/src/Infrastructure/Configuration/Mapping/ConfigMappingException.php` | Исправлены имена аргументов `strtr()` на `string`/`from` | Полный `composer phpstan` выявил ошибку в соседней ранее внесённой правке |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `php -l app/src/Infrastructure/Framework/Bootloader/AppBootloader.php` | ✓ | Синтаксис корректен |
| `php -l tools/phpstan/src/Rules/DisallowMagicScalarLiteralRule.php` | ✓ | Синтаксис корректен |
| `php -l tools/phpstan/tests/Unit/PHPStan/Fixtures/MagicScalarLiteralsAllowed.php` | ✓ | Синтаксис корректен |
| `php -l app/src/Infrastructure/Configuration/Mapping/ConfigMappingException.php` | ✓ | Синтаксис корректен |
| `composer phpstan-rules:test -- --filter DisallowMagicScalarLiteralRuleTest` | ✓ | 2 tests, 3 assertions |
| `composer phpstan-rules:phpstan` | ✓ | Ошибок нет |
| `vendor/bin/phpstan analyse app/src/Infrastructure/Framework/Bootloader/AppBootloader.php` | ✓ | Ошибок нет |
| `composer phpstan` | ✓ | Ошибок нет |
| `vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no --config=.php-cs-fixer.dist.php app/src/Infrastructure/Framework/Bootloader/AppBootloader.php app/src/Infrastructure/Configuration/Mapping/ConfigMappingException.php tools/phpstan/src/Rules/DisallowMagicScalarLiteralRule.php tools/phpstan/tests/Unit/PHPStan/Fixtures/MagicScalarLiteralsAllowed.php` | ✓ | Форматирование чистое |

## Открытые вопросы

Нет
