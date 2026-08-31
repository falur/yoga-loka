---
plan: docs/plans/2026-05-11_15-37_replace-psalm-with-phpstan-max.md
started: 2026-05-11 15:46
finished: 2026-05-11 15:51
status: done
---

# Журнал: Заменить Psalm на PHPStan max level

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Переключить tooling с Psalm на PHPStan | `composer.json`, `composer.lock`, `phpstan.neon`, удалены `psalm.xml`, `psalm-baseline.xml` | `composer update phpstan/phpstan vimeo/psalm --with-all-dependencies`; `composer validate --strict`; `composer phpstan` стартует, нашёл 5 ошибок; `test ! -e psalm.xml && test ! -e psalm-baseline.xml`; `rg ...` нашёл только `@psalm`-аннотации в коде | done |
| 2 | Довести код до чистого PHPStan max без baseline | `app/src/Application/Bootloader/LoggingBootloader.php`, `app/src/Application/Bootloader/RoutesBootloader.php`, `app/src/Application/Kernel.php`, `app/src/Endpoint/Temporal/Ping.php`, `app/src/Endpoint/Web/Middleware/LocaleSelector.php`, `tests/App/TestKernel.php`; php-cs-fixer также поправил стиль в `app/config/database.php`, `app/src/Application/Bootloader/AppBootloader.php`, `app/src/Application/Bootloader/ExceptionHandlerBootloader.php` | `composer phpstan` OK; `composer test` exit 0 с существующим risky/deprecation; `composer cs:fix` применил формат; `composer cs:fix -- --dry-run --diff` OK; `rg ...` без совпадений | done |
| 3 | Финальная проверка | проект | `composer validate --strict` OK; `composer phpstan` OK; `composer test` exit 0 с risky/deprecation; `composer cs:fix -- --dry-run --diff` OK; `test ! -e psalm.xml && test ! -e psalm-baseline.xml` OK; `rg ...` без совпадений | done |

## Заметки

- Локальный PHP: `8.5.2`; целевой runtime из плана и `phpstan.neon`: PHP `8.5.6` (`phpVersion: 80506`). Локальный PHP не обновлялся, расхождение фиксируется в журнале.
- Установлен `phpstan/phpstan 2.1.54`.
- Первичный `composer phpstan` после фазы 1 нашёл категории: `missingType.generics`, `property.onlyWritten`, `missingType.return`, `assign.propertyType`.
- Spiral helper `directory()` загружается через Composer files autoload из `vendor/autoload.php`; отдельный `scanFiles` в `phpstan.neon` не нужен и удалён после проверки.
- Исправления PHPStan: удалена неиспользуемая зависимость `ConfiguratorInterface`, уточнён generic PHPDoc для route middleware, добавлен `string` return type для Temporal workflow, список локалей нормализован до `string[]`, Psalm-аннотации удалены.
- PHPUnit сохранил существующее состояние: 1 risky test `Tests\Unit\DemoTest::testDemo`, vendor deprecation `Yiisoft\ErrorHandler\Renderer\XmlRenderer::renderVerbose()`.

## Изменения в docs

Нет изменений в `docs/rules.md` или `docs/arch.md`: эти файлы отсутствуют, а архитектурных правил для закрепления не появилось.

## Финальная проверка

| Команда | Результат | Примечание |
|---|---|---|
| `composer validate --strict` | OK | `composer.json is valid` |
| `composer phpstan` | OK | PHPStan `2.1.54`, `level: max`, 8 файлов, без ошибок |
| `composer test` | OK with issues | PHPUnit exit 0; 1 risky test `Tests\Unit\DemoTest::testDemo`; vendor deprecation `Yiisoft\ErrorHandler\Renderer\XmlRenderer::renderVerbose()` |
| `composer cs:fix -- --dry-run --diff` | OK | После применения `composer cs:fix` diff больше нет |
| `test ! -e psalm.xml && test ! -e psalm-baseline.xml` | OK | Psalm config files удалены |
| `rg -n "@psalm|vimeo/psalm|psalm-baseline|\"psalm\"|\"psalm:baseline\"" app tests composer.json README.md docs/settings.yaml` | OK | Совпадений нет |
