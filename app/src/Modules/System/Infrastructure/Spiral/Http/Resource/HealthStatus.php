<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\Spiral\Http\Resource;

enum HealthStatus: string
{
    case Ok = 'ok';
}
