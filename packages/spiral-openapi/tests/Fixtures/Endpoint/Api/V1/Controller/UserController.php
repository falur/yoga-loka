<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Tests\Fixtures\Endpoint\Api\V1\Controller;

use Spiral\Router\Annotation\Route;
use GianTiaga\SpiralOpenApi\Attribute\OpenApi;
use GianTiaga\SpiralOpenApi\Response\CollectionResponse;
use GianTiaga\SpiralOpenApi\Tests\Fixtures\Endpoint\Api\V1\Filter\UserSearchFilter;
use GianTiaga\SpiralOpenApi\Tests\Fixtures\Endpoint\Api\V1\Resource\UserResource;

final class UserController
{
    /**
     * Search users.
     *
     * @return CollectionResponse<UserResource>
     */
    #[Route(route: '/api/v1/users', name: 'api.v1.users.search', methods: ['GET'], group: 'api')]
    public function search(UserSearchFilter $userSearchFilter): CollectionResponse
    {
        return new CollectionResponse([]);
    }
    #[Route(route: '/api/v1/internal-docs', name: 'api.v1.internal.docs', methods: ['GET'], group: 'api')]
    #[OpenApi(ignore: true)]
    public function internalDocs(): string
    {
        return '';
    }
}
