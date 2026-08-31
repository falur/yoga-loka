---
date: 2026-05-14 14:24
source: text
status: done
---

# Фикс: PHPStan tooling как отдельный Composer-пакет

## Контекст

Пользователь указал, что `stubFiles` с прямым путём к `tools/phpstan/stubs/SpiralRoutesBootloader.stub` выглядит как неудачная связка, а тесты правил не должны лежать в основной папке `tests`. Нужно оформить `tools/phpstan` как отдельный Composer-пакет со своими правилами, тестами и конфигами.

Учтено правило проекта: PHPStan tooling должен жить отдельно от runtime-кода приложения; тесты и fixtures пакета остаются внутри `tools/phpstan`.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `tools/phpstan/composer.json` | Добавлен локальный пакет `yoga-loka/phpstan-rules` с autoload `Tools\PHPStan\` | Убрать прямой dev-autoload tooling-кода из корневого проекта |
| 2 | `composer.json`, `composer.lock` | Корень подключает пакет через `repositories.path` и `require-dev`; `phpstan/phpstan` стал зависимостью пакета | Сделать приложение потребителем PHPStan rules пакета |
| 3 | `tools/phpstan/bootstrap.php`, `tools/phpstan/extension.neon`, `tools/phpstan/phpunit.xml`, `tools/phpstan/phpstan.neon` | Добавлены bootstrap, экспортируемый PHPStan extension config и отдельные конфиги тестов/статанализа правил | Проверять пакет независимо от app-тестов, грузить autoload из корня или из самого пакета и убрать ручные `stubFiles`/services из app-конфига |
| 4 | `tests/Unit/PHPStan/*` -> `tools/phpstan/tests/Unit/PHPStan/*` | Тесты и fixtures правил перенесены внутрь пакета, namespace изменён на `Tools\PHPStan\Tests\...` | Убрать tooling-тесты из основной папки тестов |
| 5 | `phpstan.neon` | Корневой PHPStan анализирует `app/src` и подключает `vendor/yoga-loka/phpstan-rules/extension.neon` | Снизить прямую связность app-конфига с исходниками tools-пакета |
| 6 | `composer.json` | `composer phpstan` и `composer test` стали агрегирующими командами и запускают проверки `tools/phpstan` | Не потерять проверку правил после выноса тестов из корневого `tests` |
| 7 | `docs/rules.md` | Зафиксировано, что PHPStan tooling является отдельным пакетом, а его tests/fixtures живут внутри `tools/phpstan` | Закрепить новую границу в правилах проекта |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `composer update yoga-loka/phpstan-rules --with-dependencies --no-interaction` | ✓ | Пакет установлен symlink из `tools/phpstan` |
| `composer validate --strict --no-interaction` | ✓ | Корневой Composer валиден |
| `composer validate --strict --no-interaction tools/phpstan/composer.json` | ✓ | Composer пакета валиден |
| `composer show yoga-loka/phpstan-rules --no-interaction` | ✓ | Пакет виден как `dev-main`, path `tools/phpstan` |
| `composer phpstan` | ✓ | Проверяет app `8/8` и пакет `5/5`, ошибок нет |
| `composer test` | ✓ | Корневой `DemoTest` остаётся risky с существующим deprecation из `yiisoft/error-handler`; пакетные тесты `9 tests, 15 assertions` OK |
| `composer cs:fix -- --dry-run --diff --using-cache=no` | ✓ | Изменений стиля нет |

## Открытые вопросы

Нет
