---
plan: docs/plans/2026-05-20_14-39_tools-multilingual.md
started: 2026-05-20 16:23
finished: 2026-05-20 16:39
status: done
---

# Журнал: Мультиязычность tools-пакетов

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Подключить переводы в `tools/api-error` | `tools/api-error/src`, `tools/api-error/locale`, `tools/api-error/tests`, `tests/Feature/Endpoint/Api/ApiErrorHttpTest.php` | `composer -d tools/api-error test`; `composer -d tools/api-error phpstan`; `composer test -- --filter ApiErrorHttpTest`; `composer phpstan`; `rg 'App\\' tools/api-error/src` | done |
| 2 | Подключить переводы в `tools/openapi` | `tools/openapi/src`, `tools/openapi/locale`, `tools/openapi/tests`, `app/src/Infrastructure/Framework/Kernel.php`, `tests/Feature/Endpoint/Console/OpenApiGenerateCommandTest.php` | `composer -d tools/openapi test`; `composer -d tools/openapi phpstan`; `composer test -- --filter OpenApiHttpTest`; `composer test -- --filter OpenApiGenerateCommandTest`; `composer phpstan`; `rg 'App\\' tools/openapi/src` | done |
| 3 | Обновить документацию и выполнить общую проверку | `tools/api-error/README.md`, `tools/openapi/README.md`, `docs/arch.md`, `public/openapi/openapi.yml`, `docs/plans/2026-05-20_14-39_tools-multilingual.md`, `docs/executions/2026-05-20_16-23_tools-multilingual.md` | `php app.php openapi:generate` с local cache env; `composer -d tools/api-error test`; `composer -d tools/openapi test`; `composer -d tools/api-error phpstan`; `composer -d tools/openapi phpstan`; `composer test -- --filter 'ApiErrorHttpTest\|OpenApiHttpTest\|OpenApiGenerateCommandTest'`; `composer phpstan`; `composer cs`; `make test` | done |

## Заметки
- `tools/api-error/src` не содержит ссылок на `App\`.
- `tools/openapi/src` не содержит ссылок на `App\`.
- По замечанию пользователя переводчик сделан обязательным: `?TranslatorInterface`, `translatedMessage()` и константы пользовательских сообщений убраны из рабочих классов.
- Локальный `composer test` вне Docker падал на `DockerRuntimeSmokeTest::testStorageCanUseTestBucket`, потому что host `minio` не резолвится с хоста. Полная проверка выполнена через `make test` в Docker.

## Изменения в docs
- `tools/api-error/README.md`: описан обязательный Spiral translator, каталог переводов и граница ответственности сообщений.
- `tools/openapi/README.md`: описан `OpenApiToolsBootloader` и язык генерации стандартных response descriptions.
- `docs/arch.md`: зафиксировано, что `tools/api-error` и `tools/openapi` читают текущий locale Spiral translator, а выбор языка пользователя остаётся в request-слое приложения.

## Финальная проверка

| Команда | Результат |
|---|---|
| `composer -d tools/api-error test` | OK: 18 tests, 69 assertions |
| `composer -d tools/openapi test` | OK: 16 tests, 287 assertions |
| `composer -d tools/api-error phpstan` | OK |
| `composer -d tools/openapi phpstan` | OK |
| `composer test -- --filter 'ApiErrorHttpTest\|OpenApiHttpTest\|OpenApiGenerateCommandTest'` | OK: 16 tests, 67 assertions |
| `composer phpstan` | OK |
| `composer cs` | OK |
| `make test` | OK: 29 tests, 110 assertions, 3 PHPUnit deprecations |
