<?php

declare(strict_types=1);

namespace Tests\App\Endpoint\Api;

use App\Domain\Exception\NotFoundException;
use App\Endpoint\Api\V1\Enum\HealthStatus;
use App\Endpoint\Api\V1\Resource\HealthResource;
use Tools\OpenApi\Response\DataResponse;

final class ApiErrorTestController
{
    /**
     * @return DataResponse<HealthResource>
     */
    public function domain(): DataResponse
    {
        throw new NotFoundException(message: 'Тестовый ресурс не найден.');
    }

    /**
     * @return DataResponse<HealthResource>
     */
    public function filter(ApiErrorTestFilter $apiErrorTestFilter): DataResponse
    {
        return new DataResponse(data: new HealthResource(status: HealthStatus::Ok));
    }
}
