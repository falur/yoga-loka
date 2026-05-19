---
plan: docs/plans/2026-05-18_15-23_openapi-tools-package.md
started: 2026-05-18 15:57
finished: 2026-05-18 16:23
status: done_with_external_test_failure
---

# Журнал: OpenAPI tools package and Swagger UI

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Каркас переиспользуемого пакета и базовые контракты | `tools/openapi/composer.json`, `tools/openapi/src/**`, `composer.json`, `composer.lock` | `composer -d tools/openapi test`, `composer -d tools/openapi phpstan` | done |
| 2 | Статический разбор HTTP-слоя и построение схем | `tools/openapi/src/Parser/**`, `tools/openapi/src/Schema/**`, `tools/openapi/src/Spec/**`, `tools/openapi/tests/Fixtures/**` | `composer -d tools/openapi test`, `composer -d tools/openapi phpstan` | done |
| 3 | Генерация YAML и интеграция в Spiral-приложение | `app/config/openapi.php`, `app/src/Endpoint/Console/**`, `app/src/Endpoint/Api/V1/**`, `app/src/Infrastructure/**`, `public/openapi/openapi.yml` | `composer openapi:generate`, `vendor/bin/phpunit tests/Feature/Endpoint/Api/OpenApiHttpTest.php`, `composer phpstan` | done |
| 4 | Swagger UI для чтения спецификации | `app/src/Endpoint/Api/V1/Controller/SwaggerController.php`, `app/src/Infrastructure/Framework/Bootloader/RoutesBootloader.php`, `public/swagger-ui/**` | `composer openapi:publish-assets`, `vendor/bin/phpunit tests/Feature/Endpoint/Api/OpenApiHttpTest.php` | done |
| 5 | Переиспользование, документация и контроль качества | `tools/openapi/README.md`, `docs/rules.md`, `docs/arch.md`, `tests/Feature/Endpoint/Api/OpenApiHttpTest.php` | `composer validate`, `composer phpstan`, `composer test` | done, полный `composer test` упал только на MinIO smoke-test |

## Заметки
- `composer update` запускался с `--ignore-platform-req=ext-redis`, потому что локальный CLI PHP не содержит `ext-redis`; lock-файл обновлён и `composer install --ignore-platform-req=ext-redis` проходит.
- `composer test` доходит до OpenAPI-тестов успешно, но падает на существующем `DockerRuntimeSmokeTest::testStorageCanUseTestBucket`: локально не резолвится Docker hostname `minio`.

## Изменения в docs
- `docs/arch.md`: добавлено, что генератор живёт в `tools/openapi`, приложение только конфигурирует пакет, Swagger UI читает YAML.
- `docs/rules.md`: добавлены правила использования `#[OpenApi]` и обязательной генерации YAML перед релизом.

## Финальная проверка
| Команда | Результат |
|---|---|
| `composer validate` | pass, есть предупреждения Composer про точные версии `league/flysystem-aws-s3-v3` и `swagger-api/swagger-ui` |
| `composer install --ignore-platform-req=ext-redis` | pass |
| `composer phpstan` | pass |
| `composer -d tools/openapi test` | pass |
| `composer -d tools/openapi phpstan` | pass |
| `composer openapi:test` | pass |
| `composer openapi:phpstan` | pass |
| `vendor/bin/phpunit tests/Feature/Endpoint/Api/OpenApiHttpTest.php` | pass |
| `composer openapi:generate` | pass |
| `composer openapi:publish-assets` | pass |
| `composer test` | fail: `minio` host не резолвится в существующем Docker storage smoke-test |
