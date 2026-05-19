---
title: Обработка API-ошибок по образцу старого проекта
date: 2026-05-19 16:59
mode: normal
decision_mode: recommend_and_ask
status: draft
reviewer: none
sources:
  rules: docs/rules.md
  arch: docs/arch.md
---

# Обработка API-ошибок по образцу старого проекта

## Суть

Исследовали, как взять из старого проекта `/Users/gian_tiaga/Code/yoga-loka-spiral` подход к ошибкам: код бросает исключение, исключение всплывает до общей границы API, а там превращается в JSON-ошибку.

В новом проекте это уже описано как целевая архитектура: доменное исключение должно всплывать до endpoint/interceptor boundary и стать `ErrorResponse` (`docs/arch.md:331`, `docs/arch.md:346`). Правила прямо запрещают ловить исключения в контроллерах, handler-ах и бизнес-логике; ловить их можно только на границе системы (`docs/rules.md:44`). Для ValueObject ожидается `ValidationException` с HTTP 422, который должен конвертироваться в `ErrorResponse` (`docs/rules.md:38`).

## Решение

Выбранный вариант: взять из старого проекта не код один в один, а принцип центральной API-обработки исключений. Формат тела ошибки оставить текущий для нового проекта: `{"message":"...","code":422}`. Это подтверждено пользователем в этом исследовании.

Почему не копировать старый код напрямую:

| Что сравнивали | Старый проект | Новый проект | Решение |
|---|---|---|---|
| Где ловится исключение | `ApiInterceptor` ловит ошибки вокруг вызова action (`/Users/gian_tiaga/Code/yoga-loka-spiral/app/src/Endpoint/Api/Interceptor/ApiInterceptor.php:34`) | В правилах уже ожидается `ApiExceptionInterceptor` (`docs/rules.md:44`) | Сделать новый `ApiExceptionInterceptor` под текущую архитектуру |
| Формат ошибки | Старый `ErrorResponse` отдаёт вложенный объект `error.code/message` (`/Users/gian_tiaga/Code/yoga-loka-spiral/app/src/Response/ErrorResponse.php:20`) | Новый `Tools\OpenApi\Response\ErrorResponse` имеет публичные поля `message` и `code` (`tools/openapi/src/Response/ErrorResponse.php:7`) | Оставить новый плоский формат |
| Превращение response DTO в HTTP | Старый `ApiInterceptor` сам создаёт PSR-7 response (`/Users/gian_tiaga/Code/yoga-loka-spiral/app/src/Endpoint/Api/Interceptor/ApiInterceptor.php:90`) | Новый проект уже имеет `HttpResponseInterceptor`, который вызывает `toResponse()` (`tools/openapi/src/Response/Interceptor/HttpResponseInterceptor.php:16`) | Не дублировать сборку HTTP-ответа |
| HTML/XML ошибки | Старый проект имел view-renderer для HTML/JSON (`/Users/gian_tiaga/Code/yoga-loka-spiral/app/src/Application/Exception/Renderer/ViewRenderer.php:36`) | Новый проект API-only, HTML/XML рендеры ошибок запрещены (`docs/rules.md:69`) | Старый `ViewRenderer` не переносить |

Целевая схема:

```text
Controller / Handler / ValueObject
  -> бросает типизированное исключение
  -> ApiExceptionInterceptor
  -> Tools\OpenApi\Response\ErrorResponse
  -> HttpResponseInterceptor
  -> JSON HTTP response
```

`ApiExceptionInterceptor` должен быть обычным infrastructure/endpoint boundary-классом, а не частью Domain. Архитектура отдельно говорит, что framework, config, persistence, logging и error rendering не попадают в `Domain` (`docs/arch.md:351`).

Регистрация важна по порядку. Сейчас `AppBootloader` регистрирует `HttpResponseInterceptor` последним (`app/src/Infrastructure/Framework/Bootloader/AppBootloader.php:23`). Spiral вызывает перехватчики по порядку и передаёт каждому следующий обработчик (`vendor/spiral/framework/src/Hmvc/src/InterceptorPipeline.php:96`). Поэтому будущий `ApiExceptionInterceptor` нужно поставить после `HttpResponseInterceptor`: тогда он будет ближе к controller/action, поймает исключение и вернёт `ErrorResponse`, а `HttpResponseInterceptor` уже превратит этот объект в HTTP-ответ (`tools/openapi/src/Response/Interceptor/HttpResponseInterceptor.php:18`).

Маппинг ошибок стоит взять из старого `ApiInterceptor`, но вернуть новый `ErrorResponse`:

| Исключение | HTTP status | Сообщение наружу | Лог |
|---|---:|---|---|
| `ValidationException` | 422 | сообщение исключения | debug |
| `AuthenticationException` | 401 | сообщение исключения | debug |
| `ForbiddenException` | 403 | сообщение исключения | debug |
| `NotFoundException` | 404 | сообщение исключения | debug |
| другое `DomainException` с кодом 400-499 | код исключения | сообщение исключения | warning |
| любое другое `Throwable` | 500 | `Внутренняя ошибка сервера` | error |

Такой набор уже был в старом проекте (`/Users/gian_tiaga/Code/yoga-loka-spiral/app/src/Endpoint/Api/Interceptor/ApiInterceptor.php:36`, `/Users/gian_tiaga/Code/yoga-loka-spiral/app/src/Endpoint/Api/Interceptor/ApiInterceptor.php:55`, `/Users/gian_tiaga/Code/yoga-loka-spiral/app/src/Endpoint/Api/Interceptor/ApiInterceptor.php:65`). Он также совпадает с правилами нового проекта про типизированные доменные исключения вместо `RuntimeException` (`docs/rules.md:39`).

Формат ответа должен быть через `Tools\OpenApi\Response\ErrorResponse` и `withStatus(HttpStatus::...)`. У response DTO HTTP-статус хранится отдельно от JSON body (`tools/openapi/src/Response/HasHttpResponseMetadata.php:18`), а JSON собирается по публичным полям (`tools/openapi/src/Response/AbstractJsonResponse.php:16`). Поэтому `code` в body нужно передавать явно, чтобы клиент видел числовой код.

Нужно учесть ещё одну границу: Spiral `ValidationHandlerMiddleware` сейчас сам ловит ошибки фильтров и по умолчанию возвращает `{"errors": ...}` (`vendor/spiral/framework/src/Framework/Filter/ValidationHandlerMiddleware.php:34`, `vendor/spiral/framework/src/Framework/Filter/JsonErrorsRenderer.php:17`). Чтобы API-ошибки были едиными, вместе с `ApiExceptionInterceptor` стоит заменить renderer ошибок фильтров на проектный renderer, который отдаёт тот же `ErrorResponse` с HTTP 422. Это не новый формат, а приведение framework-валидации к выбранному API-формату.

Новые зависимости не нужны. Версии связанного ПО уже зафиксированы в lock-файле: `spiral/framework` 3.16.2 (`composer.lock:5849`), `spiral/roadrunner-bridge` v4.0.0-RC7 (`composer.lock:6343`), `swagger-api/swagger-ui` v5.32.6 (`composer.lock:7300`). Внешняя проверка версий не требуется, потому что решение не выбирает и не обновляет пакеты.

## Ответы на вопросы

Вопрос: какой формат ошибки использовать после переноса идеи из старого проекта?

Варианты:

1. Использовать текущий формат нового проекта и перенести только принцип центральной обработки.
2. Копировать старый формат тела ответа `{"error": {"code": ..., "message": ...}}`.
3. Свой вариант.

Ответ пользователя: вариант 1.

## Итог

Дальше нужно планировать внедрение так: добавить центральный `ApiExceptionInterceptor`, который маппит типизированные исключения в `Tools\OpenApi\Response\ErrorResponse`, зарегистрировать его после `HttpResponseInterceptor`, не переносить старый HTML/view renderer и привести ошибки Spiral Filter к тому же JSON-формату.

Выбранный вариант одной строкой: исключения всплывают до API-перехватчика, а наружу всегда уходит текущий `ErrorResponse` нового проекта с `message`, `code` и правильным HTTP-статусом.
