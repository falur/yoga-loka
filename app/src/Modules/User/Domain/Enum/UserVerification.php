<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Enum;

enum UserVerification: string
{
    case Unverified = 'unverified';
    case Verified = 'verified';
}
