# Spiral OpenAPI

`gian-tiaga/spiral-openapi` генерирует OpenAPI `3.1.0` из типизированного HTTP-слоя Spiral-приложения.

Генератор читает:

- route attributes Spiral;
- DTO фильтров;
- resource DTO;
- enum;
- PHPDoc `@return` с response wrapper;
- атрибут `#[OpenApi]`.

## Установка

```bash
composer require gian-tiaga/spiral-openapi
```

## Bootloader

```php
use GianTiaga\SpiralOpenApi\Bootloader\OpenApiToolsBootloader;

protected const LOAD = [
    OpenApiToolsBootloader::class,
];
```

Bootloader подключает переводы пакета и регистрирует `OpenApiGenerator`.

## Минимальная генерация

```php
use GianTiaga\SpiralOpenApi\Config\OpenApiGeneratorConfig;
use GianTiaga\SpiralOpenApi\Config\ResponseWrapperMapping;
use GianTiaga\SpiralOpenApi\OpenApiGenerator;
use GianTiaga\SpiralOpenApi\Response\CollectionResponse;
use GianTiaga\SpiralOpenApi\Response\DataResponse;
use GianTiaga\SpiralOpenApi\Response\ErrorResponse;
use GianTiaga\SpiralOpenApi\Response\PaginationResponse;

$result = $generator->generate(new OpenApiGeneratorConfig(
    projectRoot: __DIR__,
    sourcePaths: [__DIR__ . '/app/src/Endpoint/Api/V1'],
    apiNamespace: 'App\\Endpoint\\Api\\V1',
    routePrefix: '/api/v1',
    outputFile: __DIR__ . '/public/openapi/openapi.yml',
    title: 'API',
    version: '1.0.0',
    responseWrapperMapping: new ResponseWrapperMapping(
        dataResponseClass: DataResponse::class,
        collectionResponseClass: CollectionResponse::class,
        paginationResponseClass: PaginationResponse::class,
        errorResponseClass: ErrorResponse::class,
    ),
));
```

`$result` содержит путь к YAML, число операций и число схем.

## Метаданные операции

```php
use GianTiaga\SpiralOpenApi\Attribute\OpenApi;

#[OpenApi(id: 'health', description: 'Проверка работоспособности API')]
public function health(): DataResponse
{
    return new DataResponse(data: new HealthResource(status: 'ok'));
}
```

Если `#[OpenApi]` не указан, `operationId` строится из имени маршрута, а описание берётся из PHPDoc summary метода.

Чтобы исключить action из спецификации:

```php
#[OpenApi(ignore: true)]
```

## Response wrappers

Метод controller-а должен возвращать wrapper и иметь PHPDoc generic:

```php
/**
 * @return DataResponse<HealthResource>
 */
public function show(): DataResponse
```

Поддерживаются:

- `DataResponse<T>` — один объект;
- `CollectionResponse<T>` — список объектов;
- `PaginationResponse<T>` — список с пагинацией;
- `ErrorResponse` — JSON-ошибка;
- `ValidationErrorResponse` — JSON-ошибка валидации;
- `HtmlResponse` — HTML;
- `FileContentResponse` — готовое содержимое файла или текста;
- `FileResponse` — локальный файл для скачивания.

Чтобы Spiral отдавал response DTO как HTTP-ответы, подключите interceptor:

```php
use GianTiaga\SpiralOpenApi\Response\Interceptor\HttpResponseInterceptor;

protected const array INTERCEPTORS = [
    HttpResponseInterceptor::class,
];
```

## File response

```php
use GianTiaga\SpiralOpenApi\Response\Enum\ContentType;
use GianTiaga\SpiralOpenApi\Response\FileContentResponse;
use GianTiaga\SpiralOpenApi\Response\FileResponse;

return new FileContentResponse(
    content: $yaml,
    contentType: ContentType::Yaml,
);

return new FileResponse(
    path: $path,
    contentType: ContentType::Pdf,
    filename: 'report.pdf',
);
```

`FileContentResponse` генерируется как `type: string`. `FileResponse` генерируется как `type: string, format: binary`.

Если локальный файл отсутствует или недоступен для чтения, `FileResponse` бросает техническое исключение на русском языке. Это ошибка разработки, а не пользовательский текст, поэтому она не переводится по locale.

## Locale

OpenAPI YAML создаётся на текущем locale Spiral translator. Для другого языка нужно запустить генерацию с другим locale.

Ключи переводов:

- `gian_tiaga.spiral_openapi.successful_response`;
- `gian_tiaga.spiral_openapi.api_error`.

Поддерживаются `ru` и `en`. Тексты из `#[OpenApi(description: ...)]` и PHPDoc принадлежат приложению, поэтому пакет их не переводит.

## PHPStan

Подключите extension, чтобы проверять `#[OpenApi]`:

```neon
includes:
    - vendor/gian-tiaga/spiral-openapi/extension.neon
```

Правило проверяет, что `id`:

- не пустой;
- содержит только латинские буквы, цифры, `_`, `.`, `-`, `:`;
- не повторяется в одном анализе.

Идентификаторы ошибок:

- `gianTiaga.spiralOpenApi.emptyId`;
- `gianTiaga.spiralOpenApi.invalidId`;
- `gianTiaga.spiralOpenApi.duplicateId`.

## Локальная разработка

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "packages/spiral-openapi",
      "options": {
        "symlink": true
      }
    }
  ]
}
```

Проверки пакета:

```bash
composer -d packages/spiral-openapi install
composer -d packages/spiral-openapi test
composer -d packages/spiral-openapi phpstan
```
