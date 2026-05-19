<?php

declare(strict_types=1);

namespace Tools\OpenApi\Tests\Fixtures\Endpoint\Api\V1\Controller;

use Spiral\Router\Annotation\Route;
use Tools\OpenApi\Attribute\OpenApi;
use Tools\OpenApi\Response\CollectionResponse;
use Tools\OpenApi\Tests\Fixtures\Endpoint\Api\V1\Filter\UserSearchFilter;
use Tools\OpenApi\Tests\Fixtures\Endpoint\Api\V1\Resource\UserResource;

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
