<?php

declare(strict_types=1);

namespace Tests\App\Modules\System\Http;

use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Exception\ValidationException;
use App\Modules\System\Infrastructure\Spiral\Http\Resource\HealthStatus;
use App\Modules\System\Infrastructure\Spiral\Http\Resource\HealthResource;
use GianTiaga\SpiralOpenApi\Response\DataResponse;

final class ApiErrorTestController
{
    /**
     * @return DataResponse<HealthResource>
     */
    public function domain(): DataResponse
    {
        throw new NotFoundException('app.system.test_resource_not_found');
    }

    /**
     * @return DataResponse<HealthResource>
     */
    public function parametrizedDomain(): DataResponse
    {
        throw new ValidationException(
            translationKey: 'app.media.unsupported_file_type',
            translationParameters: ['type' => 'png'],
        );
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
