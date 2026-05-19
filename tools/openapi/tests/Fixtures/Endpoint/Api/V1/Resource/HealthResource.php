<?php

declare(strict_types=1);

namespace Tools\OpenApi\Tests\Fixtures\Endpoint\Api\V1\Resource;

use Tools\OpenApi\Tests\Fixtures\Endpoint\Api\V1\Enum\HealthStatus;

final readonly class HealthResource extends AbstractResource
{
    public function __construct(
        public HealthStatus $status,
    ) {}
}
