<?php

declare(strict_types=1);

namespace App\Modules\Access\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\Access\Domain\Entity\Permission;
use App\Modules\Access\Domain\ValueObject\PermissionId;
use App\Modules\Access\Domain\ValueObject\PermissionSlug;
use App\Modules\Access\Infrastructure\Persistence\Cycle\Entity\CyclePermissionEntity;

final readonly class PermissionMapper
{
    public function toDomain(CyclePermissionEntity $cycleEntity): Permission
    {
        return Permission::restore(
            id: PermissionId::fromString($cycleEntity->id),
            slug: PermissionSlug::fromString($cycleEntity->slug),
            createdAt: $cycleEntity->createdAt,
            updatedAt: $cycleEntity->updatedAt,
        );
    }

    public function toCycleEntity(
        Permission $permission,
        CyclePermissionEntity|null $cycleEntity = null,
    ): CyclePermissionEntity {
        $cycleEntity ??= new CyclePermissionEntity();
        $cycleEntity->id = $permission->id->value();
        $cycleEntity->slug = $permission->slug->value();
        $cycleEntity->createdAt = $permission->createdAt;
        $cycleEntity->updatedAt = $permission->updatedAt;

        return $cycleEntity;
    }
}
