# Spiral API Errors

`gian-tiaga/spiral-api-errors` приводит ошибки HTTP API к одному JSON-формату в Spiral-приложениях.

Пакет обрабатывает:

- ненайденный HTTP-маршрут;
- ошибки Spiral Filter;
- ожидаемые доменные ошибки с HTTP-кодом `4xx`;
- непредвиденные ошибки как безопасный ответ `500`.

## Установка

```bash
composer require gian-tiaga/spiral-api-errors
```

Пакет использует response-классы из `gian-tiaga/spiral-openapi`; Composer установит эту зависимость автоматически.

## Bootloader

Подключите `ApiErrorBootloader` до bootloader-а маршрутов, чтобы Spiral Filter получил JSON renderer ошибок:

```php
use GianTiaga\SpiralApiErrors\Bootloader\ApiErrorBootloader;

protected const LOAD = [
    ApiErrorBootloader::class,
    RoutesBootloader::class,
];
```

Bootloader подключает каталог переводов и регистрирует:

- `ApiValidationErrorsRenderer`;
- `ApiExceptionInterceptor`;
- `RouteNotFoundMiddleware`.

## Middleware

`RouteNotFoundMiddleware` нужно поставить сразу после стандартного обработчика ошибок Spiral:

```php
use GianTiaga\SpiralApiErrors\Middleware\RouteNotFoundMiddleware;
use Spiral\Http\Middleware\ErrorHandlerMiddleware;

protected function globalMiddleware(): array
{
    return [
        ErrorHandlerMiddleware::class,
        RouteNotFoundMiddleware::class,
    ];
}
```

Ответ `404`:

```json
{
  "message": "Маршрут не найден.",
  "code": 404
}
```

## Interceptor

`ApiExceptionInterceptor` ставится после `HttpResponseInterceptor` в списке interceptors. Тогда response DTO сначала превращаются в HTTP-ответы, а доменные исключения получают единый JSON-формат.

```php
use GianTiaga\SpiralApiErrors\Interceptor\ApiExceptionInterceptor;
use GianTiaga\SpiralOpenApi\Response\Interceptor\HttpResponseInterceptor;

protected const array INTERCEPTORS = [
    HttpResponseInterceptor::class,
    ApiExceptionInterceptor::class,
];
```

Доменная ошибка с кодом `4xx`:

```json
{
  "message": "Ресурс не найден",
  "code": 404
}
```

Непредвиденная ошибка:

```json
{
  "message": "Внутренняя ошибка сервера",
  "code": 500
}
```

Внутреннее сообщение непредвиденного исключения не отдаётся наружу, но пишется в лог.

## Ошибки Filter

`ApiValidationErrorsRenderer` возвращает `422`:

```json
{
  "message": "Ошибка валидации",
  "code": 422,
  "errors": [
    {
      "field": "email",
      "message": "Некорректный email"
    }
  ]
}
```

Сообщение верхнего уровня переводится пакетом. Сообщения отдельных полей уже принадлежат приложению, поэтому пакет их не переводит.

## Locale

Пакет использует текущий locale `Spiral\Translator\TranslatorInterface`.

Ключи переводов:

- `gian_tiaga.spiral_api_errors.route_not_found`;
- `gian_tiaga.spiral_api_errors.validation_error`;
- `gian_tiaga.spiral_api_errors.internal_server_error`.

Поддерживаются `ru` и `en`.

## Граница ответственности

Пакет не содержит доменные исключения приложения и не зависит от его namespace. Для ожидаемых бизнес-ошибок приложение само задаёт сообщение и HTTP-код `4xx`; пакет только оборачивает их в общий response.

## Локальная разработка

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "packages/spiral-api-errors",
      "options": {
        "symlink": true
      }
    }
  ]
}
```

Проверки пакета:

```bash
composer -d packages/spiral-api-errors install
composer -d packages/spiral-api-errors test
composer -d packages/spiral-api-errors phpstan
```
