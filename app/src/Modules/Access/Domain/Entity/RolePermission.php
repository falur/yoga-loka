<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain\Entity;

use App\Modules\Access\Domain\ValueObject\PermissionId;
use App\Modules\Access\Domain\ValueObject\RoleId;
use App\Modules\Access\Domain\ValueObject\RolePermissionId;

final class RolePermission
{
    public private(set) RolePermissionId $id;

    public private(set) RoleId $roleId;

    public private(set) PermissionId $permissionId;

    public static function create(RoleId $roleId, PermissionId $permissionId): self
    {
        $rolePermission = new self();
        $rolePermission->id = RolePermissionId::generate();
        $rolePermission->roleId = $roleId;
        $rolePermission->permissionId = $permissionId;

        return $rolePermission;
    }

    public static function restore(
        RolePermissionId $id,
        RoleId $roleId,
        PermissionId $permissionId,
    ): self {
        $rolePermission = new self();
        $rolePermission->id = $id;
        $rolePermission->roleId = $roleId;
        $rolePermission->permissionId = $permissionId;

        return $rolePermission;
    }
}
