# HTTP Response

## Назначение

Response задаёт оболочку HTTP-ответа: статус, заголовки и верхний уровень JSON. Внутрь оболочки кладётся Resource — типизированная форма данных.

## Когда применять

Применяй общие ответы пакета `spiral-openapi` для обычных маршрутов: `EmptySuccessResponse` для операции без тела, `DataResponse` для одного объекта, `CollectionResponse` для списка, `PaginationResponse` для курсорной страницы. Собственный Response в `Infrastructure/Spiral/Http/Response` заводи, только когда маршруту нужна своя оболочка, которой в общих нет.

```php
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Spiral\Http\Controller;

use App\Modules\Posts\Application\Query\GetUserFeed\GetUserFeedHandler;
use App\Modules\Posts\Application\Query\GetUserFeed\GetUserFeedQuery;
use App\Modules\Posts\Infrastructure\Spiral\Http\Filter\GetUserFeedFilter;
use App\Modules\Posts\Infrastructure\Spiral\Http\Resource\PostResource;
use GianTiaga\SpiralCqrs\QueryBusInterface;
use GianTiaga\SpiralOpenApi\Response\PaginationMetaResponse;
use GianTiaga\SpiralOpenApi\Response\PaginationResponse;
use Spiral\Router\Annotation\Route;

final readonly class PostFeedController
{
    /**
     * Generic обязателен: по нему пакет строит схему OpenAPI.
     *
     * @return PaginationResponse<PostResource>
     */
    #[Route(
        route: '/api/v1/users/<ownerUserId>/posts',
        name: 'api.v1.users.posts.list',
        methods: ['GET'],
        group: 'api',
    )]
    public function feed(
        GetUserFeedFilter $getUserFeedFilter,
        GetUserFeedHandler $getUserFeedHandler,
        QueryBusInterface $queryBus,
    ): PaginationResponse {
        $result = $queryBus->dispatch(
            query: new GetUserFeedQuery(
                ownerUserId: $getUserFeedFilter->ownerUserId,
                cursor: $getUserFeedFilter->cursor,
                limit: $getUserFeedFilter->limit,
            ),
            handler: $getUserFeedHandler->handle(...),
        );

        return new PaginationResponse(
            data: $result->posts->mapToList(PostResource::fromResult(...)),
            meta: new PaginationMetaResponse(
                nextCursor: $result->nextCursor,
                limit: $getUserFeedFilter->limit,
            ),
        );
    }
}
```

## Что повторять

- Возвращаемый тип метода контроллера — конкретный класс Response, а не `ResponseInterface`.
- Для generic Response в PHPDoc указан точный параметр: `@return PaginationResponse<PostResource>`.
- Тело ответа состоит из Resource; Result, Data и Entity в Response не кладутся.
- Список элементов получается из типизированной коллекции Result методом `mapToList()`: `$result->posts` — `PostResultCollection`, а `PaginationResponse` ждёт `list<PostResource>`.
- Курсор страницы отдаётся в `meta` через `PaginationMetaResponse`, а не подмешивается в элементы списка.
- Операция без тела отвечает `EmptySuccessResponse` (204), а не пустым объектом.
- Ошибки в Response не собираются вручную: ожидаемые исключения преобразует `spiral-api-errors` на HTTP-границе.

## Допустимые варианты

Собственный Response модуля лежит в `Infrastructure/Spiral/Http/Response`, наследует `AbstractJsonResponse` и состоит из публичных типизированных свойств — они и становятся верхним уровнем JSON. Изменение формы ответа требует обновления сгенерированной OpenAPI-спецификации и теста её генерации.
