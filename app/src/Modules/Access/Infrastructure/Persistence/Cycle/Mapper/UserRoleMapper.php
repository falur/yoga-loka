<?php

declare(strict_types=1);

namespace App\Modules\Access\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\Access\Domain\Entity\UserRole;
use App\Modules\Access\Domain\ValueObject\RoleId;
use App\Modules\Access\Domain\ValueObject\UserRoleId;
use App\Modules\Access\Infrastructure\Persistence\Cycle\Entity\CycleUserRoleEntity;
use App\Shared\Domain\ValueObject\UserId;

final readonly class UserRoleMapper
{
    public function toDomain(CycleUserRoleEntity $cycleEntity): UserRole
    {
        return UserRole::restore(
            id: UserRoleId::fromString($cycleEntity->id),
            userId: UserId::fromString($cycleEntity->userId),
            roleId: RoleId::fromString($cycleEntity->roleId),
        );
    }

    public function toCycleEntity(
        UserRole $userRole,
        CycleUserRoleEntity|null $cycleEntity = null,
    ): CycleUserRoleEntity {
        $cycleEntity ??= new CycleUserRoleEntity();
        $cycleEntity->id = $userRole->id->value();
        $cycleEntity->userId = $userRole->userId->value();
        $cycleEntity->roleId = $userRole->roleId->value();

        return $cycleEntity;
    }
}
