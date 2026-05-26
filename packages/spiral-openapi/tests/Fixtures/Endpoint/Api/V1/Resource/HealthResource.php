<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Tests\Fixtures\Endpoint\Api\V1\Resource;

use GianTiaga\SpiralOpenApi\Tests\Fixtures\Endpoint\Api\V1\Enum\HealthStatus;
final readonly class HealthResource extends AbstractResource
{
    public function __construct(public HealthStatus $status)
    {
    }
}
