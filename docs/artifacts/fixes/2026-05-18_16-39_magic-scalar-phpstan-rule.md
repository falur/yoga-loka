---
started: 2026-05-18 16:24
finished: 2026-05-18 16:39
status: done_with_external_test_failure
---

# Фикс: универсальная проверка magic scalar literals

## Что изменено

- Добавлено PHPStan-правило `project.magicScalarLiteral` в `tools/phpstan`.
- Правило анализирует runtime-код `app/src` и запрещает inline string/int/float/bool literals.
- Разрешены технические контексты: class constants, enum backing values, attributes, сообщения исключений/логов и аргументы технических функций.
- `status: 'ok'` и аналогичные runtime-литералы теперь падают на `composer phpstan`.
- Найденные срабатывания в `app/src` вынесены в именованные константы.
- `docs/rules.md` обновлён: правило magic values теперь проверяется PHPStan.

## Проверки

| Команда | Результат |
|---|---|
| `composer phpstan-rules:test` | pass |
| `composer phpstan-rules:phpstan` | pass |
| `composer phpstan` | pass |
| `composer openapi:test` | pass |
| `vendor/bin/phpunit tests/Feature/Endpoint/Api/OpenApiHttpTest.php` | pass |
| `vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php ... --dry-run --diff --using-cache=no` | pass на затронутых PHP-файлах |
| `composer test` | fail: прежняя инфраструктурная ошибка `Could not resolve host: minio` в `DockerRuntimeSmokeTest::testStorageCanUseTestBucket` |
