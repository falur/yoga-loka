---
date: 2026-06-15 16:25
source: text — «Можно же было просто указать общую папку с модулями и не пихать каждый модуль отдельно» про openapi.sourcePaths
status: done
---

# Фикс: openapi.sourcePaths через glob по Http-слою модулей

## Контекст
В `app/config/openapi.php` `sourcePaths` перечислял каждый модуль отдельно
(`Modules/System/Presentation/Http`, `Modules/Notifications/Presentation/Http`).
Пользователь предложил указать общую папку модулей вместо ручного списка.

Разбор показал:
- Операции OpenAPI строятся только от методов с `#[Route]`, а парсер фильтрует
  классы по namespace `App\Modules` (`PhpAstParser`, `SpecBuilder`).
- Но схемы регистрируются по короткому имени класса, и в реестр типов попадают
  все просканированные классы. Поэтому `app/src/Modules` затянул бы в реестр
  весь Domain/Application/Infrastructure всех модулей — лишняя область и риск
  коллизий коротких имён схем; к тому же Http-слой есть только у части модулей
  (Media и Outbox его не имеют), и это расходится с задумкой `arch.md`
  («документация строится из `Presentation/Http`»).

Выбран вариант (подтверждён пользователем): glob `app/src/Modules/*/Presentation/Http`.
Он DRY (новые модули с Http подхватываются сами, модули без Http не матчатся) и
сохраняет узкую область сканирования.

Препятствие: `OpenApiGeneratorConfig::validate()` проверял каждый путь через
`is_dir()`, а glob-строка каталогом не является → бросал бы исключение. При этом
сам `FileScanner` опирается на Symfony Finder `in()`, который glob уже раскрывает
(`Finder.php:644`). Поэтому правка свелась к тому, чтобы валидатор принимал и
обычный каталог, и glob-паттерн — той же логикой флагов, что и Finder.

Учтены `AGENTS.md`, `docs/rules.md`, `docs/arch.md`: ранний возврат, именованные
аргументы, `declare(strict_types=1)`, изоляция пакета `packages/*`, тесты рядом с
правкой, проверки в Docker.

## Что изменено
| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `app/config/openapi.php` | `sourcePaths` → один glob `app/src/Modules/*/Presentation/Http` вместо списка модулей | Убрать ручное перечисление, автоподхват новых модулей с Http |
| 2 | `packages/spiral-openapi/src/Config/OpenApiGeneratorConfig.php` | `validate()` принимает каталог **или** glob через новый приватный `sourcePathHasDirectory()` (флаги `GLOB_ONLYDIR`/`GLOB_BRACE` как в Symfony Finder) | Glob-путь больше не падает на `is_dir`, поведение совпадает с `Finder::in()` |
| 3 | `packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php` | Хелпер `generatorConfig()` принимает `sourcePaths`; добавлены тесты «glob находит фикстуры (4 операции)» и «glob без совпадений → `OpenApiConfigurationException`» | Покрыть новое поведение валидатора/сканера |

## Тесты и проверки
| Команда | Результат | Заметки |
|---------|-----------|---------|
| `composer -d packages/spiral-openapi test` (Docker, PHP 8.5) | ✓ | 23 теста, 484 assertions, включая 2 новых на glob |
| `composer -d packages/spiral-openapi phpstan` (Docker) | ✓ | No errors |
| `phpunit ConfigShapeTest + OpenApiGenerateCommandTest` (Docker) | ✓ | 7 тестов; end-to-end: реальный glob-конфиг генерирует `/health`, missing-path по-прежнему даёт ошибку генерации |
| `composer phpstan` приложения (Docker) | ✓ | No errors |

Пакет требует PHP `>=8.5 <8.6`, хост — 8.4, поэтому все прогоны выполнены в Docker.
`packages/spiral-openapi/vendor` (установлен для прогона) в `.gitignore` — git не засоряется.

## Открытые вопросы
Нет.
