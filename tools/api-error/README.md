# API Error Tools

`yoga-loka/api-error-tools` превращает доменные исключения и ошибки Spiral Filter в единый JSON-формат API.

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
