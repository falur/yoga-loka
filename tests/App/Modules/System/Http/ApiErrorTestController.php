<?php

declare(strict_types=1);

namespace Tests\App\Modules\System\Http;

use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Modules\System\Presentation\Http\Enum\HealthStatus;
use App\Modules\System\Presentation\Http\Resource\HealthResource;
use GianTiaga\SpiralOpenApi\Response\DataResponse;

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
    public function invalidDomainValue(): DataResponse
    {
        throw new InvalidDomainValueException(message: 'Некорректное доменное значение.');
    }

    /**
     * @return DataResponse<HealthResource>
     */
    public function filter(ApiErrorTestFilter $apiErrorTestFilter): DataResponse
    {
        return new DataResponse(data: new HealthResource(status: HealthStatus::Ok));
    }
}
