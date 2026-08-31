---
review: docs/reviews/2026-05-20_17-08_tools-multilingual-uncommitted.md
date: 2026-05-20 17:20
status: done
---

# Фиксы по ревью: tools-мультиязычность

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Конфиг меняет язык приложения по умолчанию | — | — | — пропущено по прямому указанию пользователя |
| 2 | Контракт конструкторов разошёлся с планом и публичным примером | `docs/plans/2026-05-20_14-39_tools-multilingual.md`, `tools/api-error/tests/ConstructorContractTest.php`, `tools/openapi/tests/Generator/OpenApiGeneratorTest.php` | `composer -d tools/api-error test` (19 ✓), `composer -d tools/openapi test` (17 ✓), `composer -d tools/api-error phpstan` (✓), `composer -d tools/openapi phpstan` (✓) | ✓ применено |
| 3 | Bootstrap пакетов зависит от тестового helper-а | `tools/api-error/bootstrap.php`, `tools/openapi/bootstrap.php`, `tools/api-error/phpunit.xml`, `tools/openapi/phpunit.xml`, `tools/api-error/tests/bootstrap.php`, `tools/openapi/tests/bootstrap.php` | `composer -d tools/api-error test` (19 ✓), `composer -d tools/openapi test` (17 ✓), `composer -d tools/api-error phpstan` (✓), `composer -d tools/openapi phpstan` (✓) | ✓ применено |

## Финальная проверка

- **Тесты пакетов:** `composer -d tools/api-error test` — ✓; `composer -d tools/openapi test` — ✓
- **Статический анализ пакетов:** `composer -d tools/api-error phpstan` — ✓; `composer -d tools/openapi phpstan` — ✓
- **Тесты приложения локально:** `composer test` — ✗, только `DockerRuntimeSmokeTest::testStorageCanUseTestBucket`, потому что host `minio` не резолвится с хоста
- **Тесты приложения в Docker:** `make test` — ✓, 29 тестов, 110 проверок, 3 PHPUnit deprecations
- **Статический анализ приложения:** `composer phpstan` — ✓
- **Стиль:** `composer cs` — ✓
- **Заметки:** язык по умолчанию не менялся, потому что пользователь подтвердил, что изменил его специально
