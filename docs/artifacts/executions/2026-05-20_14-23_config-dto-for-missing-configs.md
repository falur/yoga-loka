---
plan: docs/plans/2026-05-20_14-00_config-dto-for-missing-configs.md
started: 2026-05-20 14:23
finished: 2026-05-20 14:50 MSK
status: done
---

# Журнал: DTO для всех недостающих конфигов

## Шаги

| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Зафиксировать итоговую форму конфигов и безопасные ошибки | `ConfigMappingException.php`, `ConfigBootloader.php`, `ConfigMapperTest.php`, `ConfigShapeTest.php` | `composer test -- --filter Infrastructure\\\\Configuration` не нашёл тесты; `composer test -- --filter Configuration` прошёл; `composer phpstan` прошёл | done |
| 2 | Добавить DTO для простых и средних конфигов | `MigrationConfig.php`, `MailerConfig.php`, `TranslatorConfig.php`, `SessionConfig.php`, `ScaffolderConfig.php`, вложенные DTO, `SimpleConfigMapperTest.php`, `SimpleConfigBindingTest.php` | `composer test -- --filter Configuration` прошёл; `composer phpstan` прошёл | done |
| 3 | Добавить DTO для сложных framework-конфигов | `DatabaseConfig.php`, `CycleConfig.php`, `QueueConfig.php`, `StorageConfig.php`, вложенные DTO, `ComplexConfigMapperTest.php`, `ComplexConfigBindingTest.php` | `composer test -- --filter Configuration` прошёл; `PROJECT_NAME=yoga-loka-spiral-2-work-1 make test` прошёл | done |
| 4 | Финальная проверка и документация | `docs/rules.md`, `docs/code-examples.md`, `ConfigShapeTest.php` | `find app/config -maxdepth 1 -type f -name '*.php' \| wc -l` вернул 11; `rg -n "implements TypedConfig\|configName\\(\\)" app/src/Infrastructure/Configuration` подтвердил root DTO; финальные проверки прошли | done |

## Заметки

- Место выполнения: текущая ветка `work-1`.
- Фильтр из плана `Infrastructure\\\\Configuration` в текущем PHPUnit не совпал с именами тестов. Для той же группы тестов использован фильтр `Configuration`.
- Полный `composer test` на хосте ранее падал на smoke-тесте хранилища, потому что имя `minio` доступно внутри Docker-сети, но не резолвится на хосте. Полный набор тестов прогнан через `PROJECT_NAME=yoga-loka-spiral-2-work-1 make test`.

## Изменения в docs

- Для конфигов с уже созданными vendor-объектами Valinor нужно запускать с `allowPermissiveTypes()`, иначе внешние классы вроде `Autowire` с широкими внутренними типами не маппятся.
- Вложенные массивы в PHPDoc запрещены правилами проекта, поэтому `translator.domains` описан через `TranslatorDomainConfig`, а не как `array<string, list<string>>`.
- Для env-значений в config mapper нужен `allowScalarValueCasting()`: в Docker test-runner часть значений приходит строками, хотя DTO ожидают `bool` или `int`.
- Для `cycle.schema.collections.factories` создан `CycleCollectionFactoryConfig` с `object $factory`, потому что generic `CollectionFactoryInterface`/`Illuminate\Support\Collection` нельзя выразить без запрещённого `mixed`.

## Финальная проверка

- `composer test -- --filter Configuration` — прошёл, 46 тестов, 243 assertions, 23 PHPUnit deprecations.
- `PROJECT_NAME=yoga-loka-spiral-2-work-1 make test` — прошёл, 64 теста, 305 assertions, 23 PHPUnit deprecations.
- `composer phpstan` — прошёл, 59 файлов, ошибок нет.
- `PROJECT_NAME=yoga-loka-spiral-2-work-1 make phpstan` — прошёл, ошибок нет.
