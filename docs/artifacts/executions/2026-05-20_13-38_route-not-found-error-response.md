---
plan: docs/plans/2026-05-20_13-28_route-not-found-error-response.md
started: 2026-05-20 13:38
finished: 2026-05-20 13:46
status: done
---

# Журнал: ErrorResponse для ненайденных маршрутов

## Шаги

| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Добавить middleware в `tools/api-error` | `tools/api-error/composer.json`, `tools/api-error/src/Middleware/RouteNotFoundMiddleware.php`, `tools/api-error/tests/Middleware/RouteNotFoundMiddlewareTest.php` | `composer validate --strict --no-interaction tools/api-error/composer.json`; `composer -d tools/api-error test -- --filter RouteNotFoundMiddlewareTest`; `composer -d tools/api-error phpstan`; `rg -n 'App\\' tools/api-error/src -S` | done |
| 2 | Подключить middleware в приложение | `app/src/Infrastructure/Framework/Bootloader/RoutesBootloader.php`, `tests/Feature/Endpoint/Api/ApiErrorHttpTest.php`, `tools/api-error/README.md`, `docs/arch.md`, `composer.lock` | `composer test -- --filter ApiErrorHttpTest`; `composer test -- --filter OpenApiHttpTest`; `composer phpstan`; `composer cs` | done |
| 3 | Финальная проверка | проект | `composer -d tools/api-error test`; `composer -d tools/api-error phpstan`; `composer test -- --filter ApiErrorHttpTest`; `composer test -- --filter OpenApiHttpTest`; `composer test`; `composer phpstan`; `composer cs`; `make test` | done |

## Заметки

- `composer update yoga-loka/api-error-tools --no-interaction --no-install` не прошёл из-за отсутствующего локально `ext-redis`.
- `composer.lock` обновлён командой `composer update yoga-loka/api-error-tools --no-interaction --no-install --ignore-platform-req=ext-redis`.

## Изменения в docs

- `tools/api-error/README.md`: добавлено описание `RouteNotFoundMiddleware` и порядка подключения после `ErrorHandlerMiddleware`.
- `docs/arch.md`: уточнено, что `tools/api-error` закрывает ошибки controller/action через interceptor и router 404 через middleware.

## Финальная проверка

| Команда | Результат |
|---|---|
| `composer -d tools/api-error test` | OK: 15 tests, 59 assertions |
| `composer -d tools/api-error phpstan` | OK |
| `composer test -- --filter ApiErrorHttpTest` | OK: 7 tests, 24 assertions |
| `composer test -- --filter OpenApiHttpTest` | OK: 6 tests, 32 assertions |
| `composer phpstan` | OK |
| `composer cs` | OK |
| `composer test` | fail: локально не резолвится Docker hostname `minio` в `DockerRuntimeSmokeTest::testStorageCanUseTestBucket` |
| `make test` | OK: 26 tests, 99 assertions, 3 PHPUnit deprecations |
