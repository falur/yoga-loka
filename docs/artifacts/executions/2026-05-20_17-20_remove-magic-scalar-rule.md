---
plan: docs/plans/2026-05-20_17-07_remove-magic-scalar-rule.md
started: 2026-05-20 17:20
finished: 2026-05-20 17:26
status: done
---

# Журнал: убрать правило magic scalar и упростить строковые константы

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Удалить PHPStan-правило | `tools/phpstan/extension.neon`, `tools/phpstan/src/Rules/DisallowMagicScalarLiteralRule.php`, `tools/phpstan/tests/Unit/PHPStan/DisallowMagicScalarLiteralRuleTest.php`, `tools/phpstan/tests/Unit/PHPStan/Fixtures/MagicScalarLiteralsAllowed.php`, `tools/phpstan/tests/Unit/PHPStan/Fixtures/MagicScalarLiteralsForbidden.php`, `tools/phpstan/README.md`, `docs/rules.md` | `composer tools:phpstan:qa`; `rg -n "DisallowMagicScalarLiteralRule\|project\\.magicScalarLiteral" tools/phpstan/src tools/phpstan/tests tools/phpstan/extension.neon tools/phpstan/README.md docs/rules.md` | Готово |
| 2 | Упростить строковые константы по таблице | `app/src/Endpoint/Temporal/Ping.php`, `app/src/Infrastructure/Framework/Bootloader/LoggingBootloader.php`, `tools/api-error/src/Interceptor/ApiExceptionInterceptor.php`, `tools/openapi/src/Response/FileContentResponse.php`, `tools/openapi/src/Response/HtmlResponse.php`, `tools/openapi/src/Response/FileResponse.php`, `tools/openapi/tests/Response/FileResponseTest.php` | `composer phpstan`; `vendor/bin/phpunit -c tools/api-error/phpunit.xml --filter ApiExceptionInterceptorTest`; `vendor/bin/phpstan analyse -c tools/api-error/phpstan.neon`; `vendor/bin/phpunit -c tools/openapi/phpunit.xml --filter FileResponseTest`; `vendor/bin/phpunit --filter 'ConfigShapeTest\|SimpleConfigMapperTest\|ComplexConfigMapperTest'` | Готово, typed config тесты прошли с PHPUnit deprecations |
| 3 | Финальная проверка и отчётность | `docs/executions/2026-05-20_17-20_remove-magic-scalar-rule.md`, `docs/plans/2026-05-20_17-07_remove-magic-scalar-rule.md` | См. раздел «Финальная проверка» | Готово |

## Заметки

- Удаление правила также удалило текущую незакоммиченную доработку про исключение для `TypedConfig::configName()`. Это не откат чужой работы, а следствие удаления всего правила.
- Поиск по целевым константам и `@phpstan-ignore project.magicScalarLiteral` в затронутых файлах не нашёл совпадений.
- `composer test` на хосте падает на smoke-тесте MinIO, потому что имя `minio` не резолвится вне Docker-сети. Штатный `make test` прошёл через Docker test-runner.
- Прямой запуск `docker compose -f docker/docker-compose.dev.yml exec -T app-http composer test` падает из-за dev-переменных контейнера `app-http`; для полного тестового запуска нужен `test-runner` через `make test`.

## Изменения в docs

- В `docs/rules.md` общий запрет magic scalar заменён на правило не плодить одноразовые технические константы.
- Из `tools/phpstan/README.md` удалено описание правила `project.magicScalarLiteral`.

## Финальная проверка

| Команда | Результат |
|---------|-----------|
| `composer tools:phpstan:qa` | OK: PHPStan без ошибок, 14 tests, 21 assertions |
| `vendor/bin/phpstan analyse -c tools/api-error/phpstan.neon` | OK: no errors |
| `vendor/bin/phpunit -c tools/api-error/phpunit.xml` | OK: 11 tests, 43 assertions |
| `composer tools:openapi:qa` | OK: PHPStan без ошибок, 15 tests, 154 assertions |
| `composer phpstan` | OK: no errors |
| `composer cs` | OK: исправления не нужны |
| `PROJECT_NAME=yoga-loka-spiral-2-work-1 make test` | OK: 66 tests, 322 assertions, 25 PHPUnit deprecations |
| `PROJECT_NAME=yoga-loka-spiral-2-work-1 make phpstan` | OK: no errors |
| `rg -n "@phpstan-ignore project\\.magicScalarLiteral\|DisallowMagicScalarLiteralRule\|project\\.magicScalarLiteral" app/src tools docs/rules.md docs/code-examples.md` | OK: совпадений нет |
