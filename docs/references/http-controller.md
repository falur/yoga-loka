# HTTP Controller

## Назначение

Контроллер преобразует HTTP-вход в Command или Query и результат сценария в типизированный ответ.

## Когда применять

Применяй для публичного или служебного HTTP-маршрута.

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Spiral\Http\Controller;

use App\Modules\Auth\Public\Attribute\AuthenticatedRoute;
use App\Modules\User\Application\Command\RenameUser\RenameUserCommand;
use App\Modules\User\Application\Command\RenameUser\RenameUserHandler;
use App\Modules\User\Infrastructure\Spiral\Http\Filter\RenameUserFilter;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralOpenApi\Response\EmptySuccessResponse;
use Spiral\Router\Annotation\Route;

final readonly class UserController
{
    #[Route(
        route: '/api/v1/users/me/name',
        name: 'api.v1.users.me.name.update',
        methods: ['PUT'],
        group: 'api',
    )]
    #[AuthenticatedRoute]
    public function rename(
        RenameUserFilter $renameUserFilter,
        RenameUserHandler $renameUserHandler,
        CommandBusInterface $commandBus,
    ): EmptySuccessResponse {
        $commandBus->dispatch(
            command: new RenameUserCommand(
                userId: $renameUserFilter->authUserId,
                name: $renameUserFilter->name,
            ),
            handler: $renameUserHandler->handle(...),
        );

        return new EmptySuccessResponse();
    }
}
```

## Что повторять

- Контроллер получает типизированный Filter.
- Метод создаёт один Command или Query и вызывает шину.
- Возврат имеет конкретный Response-класс.
- Требование доступа объявлено публичным атрибутом рядом с маршрутом.
- Класс лежит в `Infrastructure/Spiral/Http/Controller`: контроллер — входной адаптер Spiral, отдельного слоя `Presentation` нет.
- В контроллере нет запросов к БД, доменных ветвлений и `try-catch`.

## Допустимые варианты

Query может быть преобразован в Resource и `DataResponse`, `CollectionResponse` или `PaginationResponse`. Для generic Response обязателен точный generic в PHPDoc.
