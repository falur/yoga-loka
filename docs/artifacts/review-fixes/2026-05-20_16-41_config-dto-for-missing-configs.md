---
review: docs/reviews/2026-05-20_16-20_config-dto-for-missing-configs.md
date: 2026-05-20 16:41
status: done
---

# Фиксы по ревью: DTO для всех недостающих конфигов

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Покрыть `cycle.schema.generators` с группами генераторов | `app/src/Infrastructure/Configuration/Cycle/CycleSchemaConfig.php`, `app/src/Infrastructure/Configuration/Cycle/CycleSchemaGeneratorGroupConfig.php`, `tests/Unit/Infrastructure/Configuration/ComplexConfigMapperTest.php`, `tests/Unit/Infrastructure/Configuration/ComplexConfigBindingTest.php` | `composer test -- --filter Configuration` (48 тестов) | ✓ применено |
| 2 | Сузить тип фабрики коллекций Cycle и проверить ошибку для неверного объекта | `app/src/Infrastructure/Configuration/Cycle/CycleCollectionFactoryConfig.php`, `app/src/Infrastructure/Configuration/Cycle/CycleCollectionsConfig.php`, `app/src/Infrastructure/Configuration/Mapping/InvalidConfigValueException.php`, `app/src/Infrastructure/Configuration/Mapping/ConfigMapper.php`, `app/src/Infrastructure/Configuration/Mapping/ConfigMappingException.php`, `tests/Unit/Infrastructure/Configuration/ComplexConfigMapperTest.php` | `composer test -- --filter Configuration` (48 тестов), `composer phpstan` | ✓ применено |
| 3 | Проверить, что ошибка маппинга не раскрывает `key` и `token` storage-конфига | `tests/Unit/Infrastructure/Configuration/ComplexConfigMapperTest.php` | `composer test -- --filter Configuration` (48 тестов) | ✓ применено |

## Финальная проверка

- **Тесты:** `PROJECT_NAME=yoga-loka-spiral-2-work-1 make test` — ✓, 66 тестов, 322 проверки, 25 PHPUnit deprecations
- **Статический анализ:** `composer phpstan` — ✓
- **Статический анализ в Docker:** `PROJECT_NAME=yoga-loka-spiral-2-work-1 make phpstan` — ✓
- **Линтер:** `composer cs` — ✓
- **Заметки:** `composer test` на хосте падает на `DockerRuntimeSmokeTest`, потому что имя `minio` не резолвится вне Docker-сети. Тот же полный набор через Docker прошёл.
