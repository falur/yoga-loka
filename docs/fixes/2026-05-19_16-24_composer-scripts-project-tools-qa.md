---
date: 2026-05-19 16:24
source: text
status: done
---

# Фикс: разделить Composer scripts проекта и tools

## Контекст

Пользователь попросил оставить в корневых Composer scripts только проектные команды `phpstan`, `cs`, `cs:fix`, `qa`, `test`, `test-coverage`, а проверки локальных пакетов вынести в `tools:phpstan:qa` и `tools:openapi:qa`.

Учтены `docs/rules.md` и `docs/arch.md`. Изменения ограничены конфигурацией Composer и документацией по затронутым командам.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `composer.json` | Удалены смешанные package/project scripts, добавлены `phpstan`, `cs`, `cs:fix`, `qa`, `test`, `test-coverage`, `tools:phpstan:qa`, `tools:openapi:qa` | Разделить проверки приложения и локальных пакетов |
| 2 | `phpstan.neon` | Убран путь `tools/phpstan/src/Rules` из корневого анализа | Корневой PHPStan должен анализировать только приложение |
| 3 | `tools/phpstan/README.md` | Обновлены команды проверки пакета | Документация должна соответствовать новым scripts |
| 4 | `tools/openapi/README.md` | Обновлена команда проверки пакета | Документация должна соответствовать новым scripts |
| 5 | `docs/rules.md` | Команда генерации OpenAPI заменена на прямой `php app.php openapi:generate` | Удалён composer script `openapi:generate` |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `composer validate` | ✓ | JSON валиден, есть старые warning про exact versions `league/flysystem-aws-s3-v3` и `swagger-api/swagger-ui` |
| `composer validate --strict` | ✗ | Падает только на тех же warning про exact versions |
| `composer run-script --list` | ✓ | В списке только нужные scripts |
| `composer phpstan` | ✓ | Корневой анализ `app/src`, ошибок нет |
| `composer cs` | ✓ | Dry-run без изменений |
| `composer tools:phpstan:qa` | ✓ | PHPStan OK, PHPUnit: 16 tests, 24 assertions |
| `composer tools:openapi:qa` | ✓ | PHPStan OK, PHPUnit: 12 tests, 141 assertions |
| `composer test` | ✗ | Падает на текущих проектных проблемах: не резолвятся `CacheConfig`/`OpenApiConfig`, MinIO host `minio` недоступен локально |

## Открытые вопросы

Нет.
