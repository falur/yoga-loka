---
date: 2026-05-18 19:04
source: text
status: done
---

# Фикс: файловые response-классы OpenAPI-пакета

## Контекст

По обсуждённому решению добавлены универсальные `FileContentResponse` и `FileResponse`, `YamlResponse` удалён, `HtmlResponse` оставлен. Учтены правила проекта из `docs/rules.md` и архитектура OpenAPI-пакета из `docs/arch.md`.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `tools/openapi/src/Response/HasHttpResponseMetadata.php` | Вынесены общий status/header API и дефолтные headers через `defaultHeaders()` | Не дублировать `withStatus()`, `withHeader()`, `withAddedHeader()`, `setHeaders()` |
| 2 | `tools/openapi/src/Response/AbstractJsonResponse.php` | JSON wrappers используют общий trait и сохраняют default `Content-Type` | Сохранить поведение JSON-ответов без `parent::__construct()` |
| 3 | `tools/openapi/src/Response/FileContentResponse.php` | Добавлен inline response для готового content | Заменить частный `YamlResponse` универсальным классом |
| 4 | `tools/openapi/src/Response/FileResponse.php` | Добавлен attachment response для локального файла с filename и `Content-Length` | Поддержать скачивание файлов из пакета |
| 5 | `tools/openapi/src/Response/HtmlResponse.php` | Убрано наследование от `PsrResponse`, используется `ConvertsToHttpResponse` | Убрать лишний PSR proxy слой |
| 6 | `tools/openapi/src/Response/YamlResponse.php`, `tools/openapi/src/Response/PsrResponse.php` | Классы удалены | `YamlResponse` заменён `FileContentResponse`, `PsrResponse` больше не нужен |
| 7 | `app/src/Endpoint/Api/V1/Controller/SwaggerController.php` | YAML endpoint возвращает `FileContentResponse` | Использовать универсальный response для YAML |
| 8 | `tools/openapi/tests/Response/FileResponseTest.php`, `tests/Feature/Endpoint/Api/OpenApiHttpTest.php` | Добавлены проверки file/content/html responses и inline headers Swagger | Зафиксировать новое runtime-поведение |
| 9 | `tools/openapi/README.md` | Обновлено описание response helpers | Документировать новый публичный API |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `composer openapi:test` | ✓ | 12 tests, 101 assertions |
| `vendor/bin/phpunit tests/Feature/Endpoint/Api/OpenApiHttpTest.php` | ✓ | 5 tests, 24 assertions |
| `composer openapi:phpstan` | ✓ | No errors |
| `composer phpstan` | ✓ | Приложение, `tools/phpstan` и `tools/openapi` без ошибок |

## Открытые вопросы

Нет
