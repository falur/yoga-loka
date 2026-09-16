<?php

declare(strict_types=1);

namespace App\Modules\Access\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\Access\Domain\Entity\Role;
use App\Modules\Access\Domain\Entity\RolePermission;
use App\Modules\Access\Domain\ValueObject\PermissionId;
use App\Modules\Access\Domain\ValueObject\RoleId;
use App\Modules\Access\Domain\ValueObject\RolePermissionId;
use App\Modules\Access\Domain\ValueObject\RoleSlug;
use App\Modules\Access\Infrastructure\Persistence\Cycle\Entity\CycleRoleEntity;
use App\Modules\Access\Infrastructure\Persistence\Cycle\Entity\CycleRolePermissionEntity;

/**
 * Преобразует и корень агрегата Role, и его внутреннюю сущность RolePermission: у связи роли
 * с правом нет собственного Repository (см. RoleRepository), поэтому граница сохранения
 * агрегата остаётся здесь же, у Mapper-а корня.
 */
final readonly class RoleMapper
{
    public function toDomain(CycleRoleEntity $cycleEntity): Role
    {
        return Role::restore(
            id: RoleId::fromString($cycleEntity->id),
            slug: RoleSlug::fromString($cycleEntity->slug),
            createdAt: $cycleEntity->createdAt,
            updatedAt: $cycleEntity->updatedAt,
        );
    }

    public function toCycleEntity(
        Role $role,
        CycleRoleEntity|null $cycleEntity = null,
    ): CycleRoleEntity {
        $cycleEntity ??= new CycleRoleEntity();
        $cycleEntity->id = $role->id->value();
        $cycleEntity->slug = $role->slug->value();
        $cycleEntity->createdAt = $role->createdAt;
        $cycleEntity->updatedAt = $role->updatedAt;

        return $cycleEntity;
    }

    public function toRolePermissionDomain(CycleRolePermissionEntity $cycleEntity): RolePermission
    {
        return RolePermission::restore(
            id: RolePermissionId::fromString($cycleEntity->id),
            roleId: RoleId::fromString($cycleEntity->roleId),
            permissionId: PermissionId::fromString($cycleEntity->permissionId),
        );
    }

    public function toRolePermissionCycleEntity(
        RolePermission $rolePermission,
        CycleRolePermissionEntity|null $cycleEntity = null,
    ): CycleRolePermissionEntity {
        $cycleEntity ??= new CycleRolePermissionEntity();
        $cycleEntity->id = $rolePermission->id->value();
        $cycleEntity->roleId = $rolePermission->roleId->value();
        $cycleEntity->permissionId = $rolePermission->permissionId->value();

        return $cycleEntity;
    }
}
