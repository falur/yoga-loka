---
date: 2026-05-19 12:21
source: text
status: done
---

# Фикс: генерация OpenAPI для файловых response

## Контекст

Пользователь попросил проверить наличие тестов, что генерация по файлу и `contentType` формирует корректный OpenAPI. Проверка показала, что таких тестов и поддержки в генераторе не было: генератор требовал generic JSON response и всегда описывал `application/json`.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `tools/openapi/src/Model/FileResponseMetadata.php`, `tools/openapi/src/Model/MethodMetadata.php` | Добавлена metadata файлового response | Передать из AST-парсера в SpecBuilder тип response и content type |
| 2 | `tools/openapi/src/Parser/PhpAstParser.php` | Парсер находит `FileContentResponse`/`FileResponse` и читает `contentType: ContentType::*` | Генератор узнаёт media type без ручного OpenAPI-описания |
| 3 | `tools/openapi/src/Spec/SpecBuilder.php` | Для `FileContentResponse` генерируется `type: string`, для `FileResponse` — `type: string, format: binary` | Описать non-JSON responses в OpenAPI |
| 4 | `tools/openapi/tests/Fixtures/Endpoint/Api/V1/Controller/ExportController.php` | Добавлены fixture endpoints для YAML и PDF | Покрыть оба файловых response сценария |
| 5 | `tools/openapi/tests/Generator/OpenApiGeneratorTest.php` | Добавлены проверки `application/yaml` и `application/pdf` в generated spec | Зафиксировать корректную генерацию по content type |
| 6 | `tools/openapi/README.md` | Уточнено, как генератор описывает `FileContentResponse` и `FileResponse` | Документировать новый контракт |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `composer openapi:test` | ✓ | 12 tests, 136 assertions |
| `composer openapi:phpstan` | ✓ | No errors |
| `composer phpstan` | ✓ | Приложение, `tools/phpstan` и `tools/openapi` без ошибок |

## Открытые вопросы

Нет
