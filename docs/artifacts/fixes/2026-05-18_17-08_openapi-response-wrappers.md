---
date: 2026-05-18 17:08
source: text
status: done
---

# Фикс: response wrappers в openapi-пакете

## Контекст

Пользователь указал, что базовые response-классы должны жить в `tools/openapi`, `SwaggerController` не должен возвращать результат через `HttpResponseFactory::html()`, а header/content-type/status должны быть enum-ами, не приватными magic constants.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `tools/openapi/src/Response/*` | Добавлены `DataResponse`, `CollectionResponse`, `PaginationResponse`, `PaginationMetaResponse`, `HtmlResponse`, `YamlResponse`, `ErrorResponse` | Перенести базовые response wrappers в пакет |
| 2 | `tools/openapi/src/Response/Enum/*` | Добавлены `HttpHeader`, `ContentType`, `HttpStatus` | Убрать header/content-type/status magic constants |
| 3 | `tools/openapi/src/Response/PsrResponse.php` | Добавлен общий PSR-7 wrapper поверх `Nyholm\Psr7\Response` | Не наследоваться от `@final` response-класса |
| 4 | `app/src/Endpoint/Api/V1/Controller/SwaggerController.php` | `index()` возвращает `HtmlResponse`, `spec()` возвращает `YamlResponse` или `ErrorResponse` | Убрать `HttpResponseFactory::html()` из контроллера |
| 5 | `app/src/Endpoint/Api/V1/Resource/AbstractResource.php` | `jsonSerialize()` использует `get_object_vars($this)` | Убрать reflection и ручную сборку DTO |
| 6 | `tools/openapi/src/Response/Enum/HttpStatus.php` | Описаны стандартные HTTP status codes `100`-`511` | Не плодить локальные `STATUS_*` константы |
| 7 | `tools/openapi/src/Response/Enum/ContentType.php` | Описаны основные API, документные, архивные, font, image, audio/video и web content types | Не плодить локальные `CONTENT_TYPE_*` константы |
| 8 | `tools/openapi/src/Response/Enum/HttpHeader.php` | Описаны стандартные и распространённые HTTP header names | Не плодить локальные `HEADER_*` константы |
| 9 | `tools/openapi/README.md` | Добавлено описание response wrappers, `HttpStatus`, `HttpHeader` и `ContentType` | Сделать контракт пакета явным |
| 10 | `tools/openapi/src/Response/AbstractJsonResponse.php` | Добавлены fluent `withStatus()`, `withHeader()`, `withAddedHeader()`, `setHeaders()` и `toResponse()` | Настраивать HTTP status/headers без засорения payload DTO |
| 11 | `tools/openapi/src/Response/Interceptor/HttpResponseInterceptor.php` | Добавлен пакетный interceptor для конвертации `ConvertsToHttpResponse` в PSR response | Не тащить конвертацию в приложение |
| 12 | `app/src/Infrastructure/Framework/Bootloader/AppBootloader.php` | Зарегистрирован пакетный `HttpResponseInterceptor` | Чтобы Spiral отдавал response DTO как HTTP responses |
| 13 | `tools/openapi/src/Response/ErrorResponse.php` | `message` и необязательный `code = null` оставлены payload-данными, HTTP status задаётся через `withStatus()` | Не смешивать код ошибки в body с HTTP status |
| 14 | `tools/openapi/composer.json`, `composer.lock` | Добавлены и зафиксированы зависимости response/interceptor слоя | Синхронизировать path-пакет и root lock |
| 15 | `app/src/Endpoint/Api/V1/Controller/SwaggerController.php` | Добавлены `#[Route]` на Swagger UI и YAML endpoint | Держать маршруты рядом с actions, как у остальных API controller-ов |
| 16 | `app/src/Infrastructure/Framework/Bootloader/RoutesBootloader.php` | Убрана ручная регистрация Swagger routes | Не дублировать route metadata в bootloader-е |
| 17 | `tools/openapi/src/Attribute/OpenApi.php` | Добавлен `ignore: true` для служебных route methods | Не включать Swagger UI routes в генерируемую OpenAPI-спецификацию |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `vendor/bin/phpunit tests/Feature/Endpoint/Api/OpenApiHttpTest.php` | ✓ | HTTP Swagger/health сценарии |
| `composer openapi:test` | ✓ | 83 assertions |
| `composer openapi:phpstan` | ✓ | No errors |
| `composer openapi:generate` | ✓ | Сгенерирована спецификация с 1 operation, Swagger routes пропущены |
| `composer phpstan-rules:test` | ✓ | Magic scalar rule fixtures |
| `composer phpstan-rules:phpstan` | ✓ | No errors |
| `composer phpstan` | ✓ | No errors |
| `vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no --config=.php-cs-fixer.dist.php ...` | ✓ | Изменённые response/app/test файлы |
| `find runtime/cache/listeners -type f -delete` | ✓ | Сброшен stale Tokenizer cache после переноса Swagger routes на attributes |
| `composer test` | ✗ | Инфраструктурный smoke-тест не может резолвить host `minio`; остальные 12 тестов дошли до выполнения |

## Открытые вопросы

Полный `composer test` требует доступный MinIO/Docker runtime.
