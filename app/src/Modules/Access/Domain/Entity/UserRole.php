<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain\Entity;

use App\Modules\Access\Domain\ValueObject\RoleId;
use App\Modules\Access\Domain\ValueObject\UserRoleId;
use App\Shared\Domain\ValueObject\UserId;

final class UserRole
{
    public private(set) UserRoleId $id;

    public private(set) UserId $userId;

    public private(set) RoleId $roleId;

    public static function create(UserId $userId, RoleId $roleId): self
    {
        $userRole = new self();
        $userRole->id = UserRoleId::generate();
        $userRole->userId = $userId;
        $userRole->roleId = $roleId;

        return $userRole;
    }

    public static function restore(
        UserRoleId $id,
        UserId $userId,
        RoleId $roleId,
    ): self {
        $userRole = new self();
        $userRole->id = $id;
        $userRole->userId = $userId;
        $userRole->roleId = $roleId;

        return $userRole;
    }
}
