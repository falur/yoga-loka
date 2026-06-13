<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain\Enum;

use App\Modules\Access\Domain\ValueObject\RoleSlug;

enum RoleName: string
{
    case Admin = 'admin';

    public function slug(): RoleSlug
    {
        return RoleSlug::fromString($this->value);
    }
}
