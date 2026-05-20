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

`ApiErrorBootloader` привязывает `Spiral\Filters\ErrorsRendererInterface` к `ApiValidationErrorsRenderer`. Его нужно подключить до bootloader-а маршрутов, чтобы `ValidationHandlerMiddleware` получил JSON renderer ошибок Filter.

```php
use Tools\ApiError\Bootloader\ApiErrorBootloader;

return [
    ApiErrorBootloader::class,
    RoutesBootloader::class,
];
```

## HTTP middleware

`RouteNotFoundMiddleware` ловит `Spiral\Router\Exception\RouteNotFoundException` и возвращает JSON 404:

```json
{
  "message": "Маршрут не найден.",
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

Пакет не зависит от `App\`. Доменные исключения остаются в приложении, например `App\Domain\Exception\NotFoundException`.
