<?php

declare(strict_types=1);

namespace App\Endpoint\Api\V1\Resource;

use App\Endpoint\Api\V1\Enum\HealthStatus;

final readonly class HealthResource extends AbstractResource
{
    public function __construct(
        public HealthStatus $status,
    ) {}
}
