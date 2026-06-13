<?php

declare(strict_types=1);

namespace App\Modules\Access\Repository;

use App\Modules\Access\Domain\Collection\UserRoleCollection;
use App\Modules\Access\Domain\Entity\UserRole;
use App\Modules\Access\Domain\ValueObject\RoleId;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\Select\Repository;

/**
 * @extends Repository<UserRole>
 */
final class UserRoleRepository extends Repository
{
    public function findByUserId(UserId $userId): UserRoleCollection
    {
        return new UserRoleCollection(
            $this->select()
                ->where('user_id', $userId->value())
                ->fetchAll(),
        );
    }

    public function exists(UserId $userId, RoleId $roleId): bool
    {
        return $this->findOne([
            'user_id' => $userId->value(),
            'role_id' => $roleId->value(),
        ]) !== null;
    }
}
