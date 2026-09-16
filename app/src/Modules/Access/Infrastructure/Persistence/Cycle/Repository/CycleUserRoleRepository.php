<?php

declare(strict_types=1);

namespace App\Modules\Access\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Access\Domain\Collection\UserRoleCollection;
use App\Modules\Access\Domain\Entity\UserRole;
use App\Modules\Access\Domain\Repository\UserRoleRepository;
use App\Modules\Access\Domain\ValueObject\RoleId;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;

/**
 * @extends AbstractRepository<UserRole>
 */
final class CycleUserRoleRepository extends AbstractRepository implements UserRoleRepository
{
    #[\Override]
    public function findByUserId(UserId $userId): UserRoleCollection
    {
        return new UserRoleCollection(
            $this->select()
                ->where('user_id', $userId->value())
                ->fetchAll(),
        );
    }

    #[\Override]
    public function exists(UserId $userId, RoleId $roleId): bool
    {
        return $this->findOne([
            'user_id' => $userId->value(),
            'role_id' => $roleId->value(),
        ]) !== null;
    }
}
