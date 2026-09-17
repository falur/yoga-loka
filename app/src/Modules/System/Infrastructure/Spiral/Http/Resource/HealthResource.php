<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\Spiral\Http\Resource;

use App\Shared\Infrastructure\Spiral\Http\Resource\AbstractResource;

final readonly class HealthResource extends AbstractResource
{
    public function __construct(
        public HealthStatus $status,
    ) {}
}
