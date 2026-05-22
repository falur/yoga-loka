<?php

declare(strict_types=1);

namespace App\Modules\System\Presentation\Http\Controller;

use App\Shared\Domain\Exception\NotFoundException;
use App\Modules\System\Presentation\Http\Enum\HealthStatus;
use App\Modules\System\Presentation\Http\Resource\HealthResource;
use App\Shared\Infrastructure\Configuration\Cache\CacheConfig;
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
