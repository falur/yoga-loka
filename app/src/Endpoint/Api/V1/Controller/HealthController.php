<?php

declare(strict_types=1);

namespace App\Endpoint\Api\V1\Controller;

use App\Domain\Exception\NotFoundException;
use App\Endpoint\Api\V1\Enum\HealthStatus;
use App\Endpoint\Api\V1\Resource\HealthResource;
use App\Infrastructure\Configuration\Cache\CacheConfig;
use Spiral\Router\Annotation\Route;
use Tools\OpenApi\Attribute\OpenApi;
use Tools\OpenApi\Response\DataResponse;

final class HealthController
{
    /**
     * Проверка работоспособности API.
     *
     * @return DataResponse<HealthResource>
     */
    #[Route(route: '/api/v1/health', name: 'api.v1.health', methods: ['GET'], group: 'api')]
    #[OpenApi(id: 'health', description: 'Проверка работоспособности API')]
    public function show(): DataResponse
    {
        return new DataResponse(new HealthResource(status: HealthStatus::Ok));
    }
}
