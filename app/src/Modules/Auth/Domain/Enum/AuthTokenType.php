<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Enum;

enum AuthTokenType: string
{
    case Access = 'access';
    case Refresh = 'refresh';
}
