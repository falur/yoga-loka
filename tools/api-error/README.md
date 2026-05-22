# API Error Tools

`yoga-loka/api-error-tools` превращает доменные исключения, ошибки Spiral Filter и ненайденные HTTP-маршруты в единый JSON-формат API.

## Подключение

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "tools/api-error",
      "options": {
        "symlink": true
      }
    }
  ],
  "require": {
    "yoga-loka/api-error-tools": "dev-main"
  }
}
```

Пакет использует response-классы из `yoga-loka/openapi-tools`, поэтому `tools/openapi` тоже должен быть production-зависимостью приложения.

## Bootloader

`ApiErrorBootloader` подключает каталог переводов `tools/api-error/locale`, привязывает `Spiral\Filters\ErrorsRendererInterface` к `ApiValidationErrorsRenderer` и создаёт классы пакета с `Spiral\Translator\TranslatorInterface`. Его нужно подключить до bootloader-а маршрутов, чтобы `ValidationHandlerMiddleware` получил JSON renderer ошибок Filter.

```php
use Tools\ApiError\Bootloader\ApiErrorBootloader;

return [
    ApiErrorBootloader::class,
    RoutesBootloader::class,
];
```

## Переводы

Пакет использует текущий locale Spiral translator. Переводчик обязателен и передаётся через `ApiErrorBootloader`.

Каталог переводов: `tools/api-error/locale`.

| Ключ | Locale `en` | Locale `ru` |
|---|---|---|
| `yoga_loka.api_error.route_not_found` | `Route not found.` | `Маршрут не найден.` |
| `yoga_loka.api_error.validation_error` | `Validation error` | `Ошибка валидации` |
| `yoga_loka.api_error.internal_server_error` | `Internal server error` | `Внутренняя ошибка сервера` |

Пакет не переводит сообщения доменных исключений приложения и сообщения конкретных полей Filter-валидации.

## HTTP middleware

`RouteNotFoundMiddleware` ловит `Spiral\Router\Exception\RouteNotFoundException` и возвращает JSON 404. Текст `message` зависит от текущего locale:

```json
{
  "message": "Route not found.",
  "code": 404
}
```

Middleware нужно поставить в глобальную HTTP-цепочку сразу после `Spiral\Http\Middleware\ErrorHandlerMiddleware`.

```php
use Spiral\Http\Middleware\ErrorHandlerMiddleware;
use Tools\ApiError\Middleware\RouteNotFoundMiddleware;

protected function globalMiddleware(): array
{
    return [
        ErrorHandlerMiddleware::class,
        RouteNotFoundMiddleware::class,
    ];
}
```

## Interceptors

`ApiExceptionInterceptor` ставится после `HttpResponseInterceptor` в массиве interceptors. Тогда фактический вызов идёт так: `HttpResponseInterceptor` снаружи, `ApiExceptionInterceptor` ближе к controller/action.

```php
use Tools\ApiError\Interceptor\ApiExceptionInterceptor;
use Tools\OpenApi\Response\Interceptor\HttpResponseInterceptor;

protected const array INTERCEPTORS = [
    HttpResponseInterceptor::class,
    ApiExceptionInterceptor::class,
];
```

## Формат ошибок

Доменная ошибка:

```json
{
  "message": "Ресурс не найден",
  "code": 404
}
```

Ошибка Spiral Filter:

```json
{
  "message": "Validation error",
  "code": 422,
  "errors": [
    {
      "field": "email",
      "message": "Некорректный email"
    }
  ]
}
```

Текст `message` в ошибке Spiral Filter зависит от текущего locale. Тексты внутри `errors` пакет не переводит.

Пакет не зависит от `App\`. Доменные исключения остаются в приложении, например `App\Shared\Domain\Exception\NotFoundException`. Исключения без поддерживаемого 4xx-кода возвращаются как обычная 500-ошибка без внутреннего сообщения.
