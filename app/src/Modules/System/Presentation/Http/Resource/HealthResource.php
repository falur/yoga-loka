<?php

declare(strict_types=1);

namespace App\Modules\System\Presentation\Http\Resource;

use App\Modules\System\Presentation\Http\Enum\HealthStatus;
use App\Shared\Presentation\Http\Resource\AbstractResource;

final readonly class HealthResource extends AbstractResource
{
    public function __construct(
        public HealthStatus $status,
    ) {}
}
