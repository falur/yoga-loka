---
plan: docs/plans/2026-05-19_17-11_api-error-handling-tools-package.md
started: 2026-05-19 17:29
finished: 2026-05-19 17:45
status: done
---

# Журнал: Пакет обработки API-ошибок

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Расширить `tools/openapi` ответом для ошибок валидации | `tools/openapi/src/Response/ValidationErrorItemResponse.php`, `tools/openapi/src/Response/ValidationErrorResponse.php`, `tools/openapi/tests/Response/ValidationErrorResponseTest.php`, `tools/openapi/README.md` | `composer -d tools/openapi test -- --filter ValidationErrorResponseTest`; `composer -d tools/openapi phpstan` | done |
| 2 | Создать переносимый пакет обработки API-ошибок | `tools/api-error`, `composer.json`, `composer.lock` | `composer validate --strict --no-interaction tools/api-error/composer.json`; `docker compose -f docker/docker-compose.dev.yml exec -T app-http composer update yoga-loka/api-error-tools yoga-loka/openapi-tools --with-dependencies --no-interaction`; `composer -d tools/api-error test`; `composer -d tools/api-error phpstan` | done |
| 3 | Подключить пакет в приложение и добавить доменные исключения | `app/src/Domain/Exception/*Exception.php`, `app/src/Infrastructure/Framework/Kernel.php`, `app/src/Infrastructure/Framework/Bootloader/AppBootloader.php`, `app/src/Infrastructure/Framework/Bootloader/ConfigBootloader.php`, `tests/App`, `tests/Unit/Domain/Exception/ApiDomainExceptionTest.php`, `tests/Feature/Endpoint/Api/ApiErrorHttpTest.php`, `tools/api-error/src/Interceptor/ApiExceptionInterceptor.php` | `docker compose -f docker/docker-compose.dev.yml exec -T app-http composer show yoga-loka/api-error-tools --no-interaction`; `docker compose -f docker/docker-compose.dev.yml exec -T app-http composer test -- --filter ApiDomainExceptionTest`; `docker compose -f docker/docker-compose.dev.yml exec -T app-http composer test -- --filter ApiErrorHttpTest`; `docker compose -f docker/docker-compose.dev.yml exec -T app-http composer test -- --filter OpenApiHttpTest`; `docker compose -f docker/docker-compose.dev.yml exec -T app-http composer phpstan` | done |
| 4 | Перевести `SwaggerController` на исключения | `app/src/Endpoint/Api/V1/Controller/SwaggerController.php`, `tests/Feature/Endpoint/Api/OpenApiHttpTest.php` | `docker compose -f docker/docker-compose.dev.yml exec -T app-http composer test -- --filter OpenApiHttpTest`; `docker compose -f docker/docker-compose.dev.yml exec -T app-http composer phpstan`; `docker compose -f docker/docker-compose.dev.yml exec -T app-http composer cs` | done |
| 5 | Финальная проверка и документация | `tools/api-error/README.md`, `tools/openapi/README.md`, `docs/arch.md`, `docs/rules.md` | `composer -d tools/api-error test`; `composer -d tools/api-error phpstan`; `composer -d tools/openapi test`; `composer -d tools/openapi phpstan`; `docker compose -f docker/docker-compose.dev.yml exec -T app-http composer phpstan`; `docker compose -f docker/docker-compose.dev.yml exec -T app-http composer cs`; `docker compose -f docker/docker-compose.dev.yml exec -T app-http composer install --no-dev --dry-run --no-interaction`; `make test` | done |

## Заметки

- Корневой `composer update` на локальном PHP не прошёл из-за отсутствующего расширения `ext-redis`; повтор выполнен в Docker-контейнере `app-http`, как описано в архитектуре проекта.
- `composer validate --strict --no-interaction` для корня возвращает предупреждения по уже существующим точным версиям `league/flysystem-aws-s3-v3` и `swagger-api/swagger-ui`.
- При подключении тестовых маршрутов обнаружилась старая проблема регистрации типизированных конфигов из подпапок. Исправлено построение FQCN в `ConfigBootloader`, иначе `OpenApiConfig` не резолвился в Swagger routes.
- `ApiExceptionInterceptor` пропускает `Spiral\Filters\Exception\ValidationException` дальше до `ValidationHandlerMiddleware`, чтобы ошибки Filter рендерились через `ApiValidationErrorsRenderer`.

## Изменения в docs

| Файл | Изменение |
|---|---|
| `tools/api-error/README.md` | Добавлено описание подключения пакета, порядка interceptors, bootloader-а renderer-а Filter-ошибок и JSON-формата ошибок. |
| `tools/openapi/README.md` | Обновлено подключение через `require`, добавлено описание `ValidationErrorResponse` и порядок `HttpResponseInterceptor` перед `ApiExceptionInterceptor`. |
| `docs/arch.md` | Добавлена роль `tools/api-error`, граница обработки controller/action и зависимость от `tools/openapi`. |
| `docs/rules.md` | Уточнено, что API-ошибки обрабатывает `Tools\ApiError\Interceptor\ApiExceptionInterceptor` из локального пакета. |

## Финальная проверка

| Проверка | Результат |
|---|---|
| `composer -d tools/api-error test` | OK, 11 tests, 43 assertions |
| `composer -d tools/api-error phpstan` | OK |
| `composer -d tools/openapi test` | OK, 15 tests, 154 assertions |
| `composer -d tools/openapi phpstan` | OK |
| `docker compose -f docker/docker-compose.dev.yml exec -T app-http composer phpstan` | OK |
| `docker compose -f docker/docker-compose.dev.yml exec -T app-http composer cs` | OK |
| `docker compose -f docker/docker-compose.dev.yml exec -T app-http composer install --no-dev --dry-run --no-interaction` | OK |
| `make test` | OK, 24 tests, 90 assertions, 3 PHPUnit deprecations |
| `composer validate --strict --no-interaction` | Предупреждения по уже существующим точным версиям `league/flysystem-aws-s3-v3` и `swagger-api/swagger-ui`; файл валиден. |
