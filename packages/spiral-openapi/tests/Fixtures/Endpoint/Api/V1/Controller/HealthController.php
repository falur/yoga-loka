<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Tests\Fixtures\Endpoint\Api\V1\Controller;

use Spiral\Router\Annotation\Route;
use GianTiaga\SpiralOpenApi\Attribute\OpenApi;
use GianTiaga\SpiralOpenApi\Response\DataResponse;
use GianTiaga\SpiralOpenApi\Tests\Fixtures\Endpoint\Api\V1\Enum\HealthStatus;
use GianTiaga\SpiralOpenApi\Tests\Fixtures\Endpoint\Api\V1\Resource\HealthResource;

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
