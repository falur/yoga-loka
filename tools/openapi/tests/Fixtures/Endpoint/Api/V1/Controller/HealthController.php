<?php

declare(strict_types=1);

namespace Tools\OpenApi\Tests\Fixtures\Endpoint\Api\V1\Controller;

use Spiral\Router\Annotation\Route;
use Tools\OpenApi\Attribute\OpenApi;
use Tools\OpenApi\Response\DataResponse;
use Tools\OpenApi\Tests\Fixtures\Endpoint\Api\V1\Enum\HealthStatus;
use Tools\OpenApi\Tests\Fixtures\Endpoint\Api\V1\Resource\HealthResource;

final class HealthController
{
    /**
     * Fixture health check.
     *
     * @return DataResponse<HealthResource>
     */
    #[Route(route: '/api/v1/health', name: 'api.v1.health', methods: ['GET'], group: 'api')]
    #[OpenApi(id: 'health', description: 'Fixture health check')]
    public function show(): DataResponse
    {
        return new DataResponse(new HealthResource(status: HealthStatus::Ok));
    }
}
