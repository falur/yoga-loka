<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\Spiral\Http\Controller;

use App\Modules\Auth\Public\Attribute\PublicRoute;
use App\Shared\Domain\Exception\NotFoundException;
use App\Modules\System\Infrastructure\Spiral\Http\Enum\HealthStatus;
use App\Modules\System\Infrastructure\Spiral\Http\Resource\HealthResource;
use App\Shared\Infrastructure\Spiral\Configuration\Cache\CacheConfig;
use Spiral\Router\Annotation\Route;
use GianTiaga\SpiralOpenApi\Attribute\OpenApi;
use GianTiaga\SpiralOpenApi\Response\DataResponse;

final class HealthController
{
    /**
     * Проверка работоспособности API.
     *
     * @return DataResponse<HealthResource>
     */
    #[Route(route: '/api/v1/health', name: 'api.v1.health', methods: ['GET'], group: 'api')]
    #[PublicRoute]
    #[OpenApi(id: 'health', description: 'Проверка работоспособности API')]
    public function show(): DataResponse
    {
        return new DataResponse(new HealthResource(status: HealthStatus::Ok));
    }
}
