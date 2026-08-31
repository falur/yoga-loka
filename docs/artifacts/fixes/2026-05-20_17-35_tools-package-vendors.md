---
date: 2026-05-20 17:35
source: text
status: done
---

# Фикс: изолировать Composer vendor для tools-пакетов

## Контекст
Пользователь указал, что каждый пакет в `tools/*` должен быть полноценным отдельным Composer-пакетом со своим `vendor`, а не полагаться на корневой `../../vendor`. Также нужно было добавить это правило в проектные правила.

## Что изменено
| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `.gitignore` | Добавлены `tools/*/vendor` и `tools/*/runtime` | Локальные зависимости и runtime-кэш tools-пакетов не попадают в git |
| 2 | `tools/*/bootstrap.php` | Все bootstrap-файлы грузят только `vendor/autoload.php` внутри своего пакета | Убрать зависимость tools-пакетов от корневого `vendor` |
| 3 | `tools/*/composer.json` | Scripts переведены на `vendor/bin/*`, добавлен package-local `allow-plugins` | Запуск тестов и анализа идёт через локальный Composer пакета |
| 4 | `tools/*/phpunit.xml`, `tools/*/phpstan.neon` | Пути к PHPUnit schema, cache/tmp и bootstrap сделаны package-local | Конфиги tools больше не завязаны на корневые пути |
| 5 | `tools/*/composer.lock` | Добавлены lock-файлы для каждого tools-пакета | Пакеты ставятся и проверяются как самостоятельные Composer-пакеты |
| 6 | `tools/openapi/composer.json`, `composer.lock` | `symfony/yaml` обновлён до `v8.0.12`, корневой lock синхронизирован | `v8.0.10` блокировался Composer security audit при отдельной установке пакета |
| 7 | `composer.json` | Удалены root scripts `tools:*:qa` | Проверки tools-пакетов запускаются только через package-local Composer |
| 8 | `docs/rules.md` | Добавлено правило изоляции tools-пакетов | Зафиксировать требование и не возвращаться к корневому `vendor` |
| 9 | `tools/api-error/tests/ConstructorContractTest.php`, `tools/openapi/tests/Generator/OpenApiGeneratorTest.php` | Добавлены проверки обязательного `TranslatorInterface` | Зафиксировать выбранный контракт после отказа от fallback без переводчика |

## Тесты и проверки
| Команда | Результат | Заметки |
|---------|-----------|---------|
| `composer install` в `tools/openapi` | ✓ | Создан локальный `vendor` и `composer.lock` |
| `composer install` в `tools/phpstan` | ✓ | Создан локальный `vendor` и `composer.lock` |
| `composer install` в `tools/api-error` | ✓ | `tools/openapi` подключён как path-зависимость внутри package-local vendor |
| `composer -d tools/api-error phpstan` | ✓ | Ошибок нет |
| `composer -d tools/api-error test` | ✓ | 19 тестов, 87 проверок |
| `composer -d tools/openapi phpstan` | ✓ | Ошибок нет |
| `composer -d tools/openapi test` | ✓ | 17 тестов, 293 проверки |
| `composer -d tools/phpstan phpstan` | ✓ | Ошибок нет |
| `composer -d tools/phpstan test` | ✓ | 16 тестов, 24 проверки |
| `composer phpstan` | ✓ | Ошибок нет |
| `composer cs` | ✓ | Замечаний нет |
| `composer test` | ✗ | Только `DockerRuntimeSmokeTest::testStorageCanUseTestBucket`: локально host `minio` не резолвится |
| `make test` | ✓ | 29 тестов, 110 проверок, 3 PHPUnit deprecations |
| `composer validate` | ✓ | Есть предупреждения по exact-version constraints в корне |
| `composer validate --strict` в `tools/api-error` | ✓ | Без предупреждений |
| `composer validate --strict` в `tools/phpstan` | ✓ | Без предупреждений |
| `composer validate --strict` в `tools/openapi` | ✗ | Только предупреждения по существующим exact-version constraints |

## Открытые вопросы
Нет
