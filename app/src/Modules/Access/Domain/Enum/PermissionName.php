<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain\Enum;

use App\Modules\Access\Domain\ValueObject\PermissionSlug;

enum PermissionName: string
{
    case UserBan = 'user.ban';
    case UserVerify = 'user.verify';
    case UserRoleAssign = 'user.role.assign';

    public function slug(): PermissionSlug
    {
        return PermissionSlug::fromString($this->value);
    }
}
