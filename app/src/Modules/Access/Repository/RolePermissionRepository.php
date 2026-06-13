<?php

declare(strict_types=1);

namespace App\Modules\Access\Repository;

use App\Modules\Access\Domain\Collection\RolePermissionCollection;
use App\Modules\Access\Domain\Entity\RolePermission;
use App\Modules\Access\Domain\ValueObject\PermissionId;
use App\Modules\Access\Domain\ValueObject\RoleId;
use Cycle\ORM\Select\Repository;

/**
 * @extends Repository<RolePermission>
 */
final class RolePermissionRepository extends Repository
{
    public function findByRoleId(RoleId $roleId): RolePermissionCollection
    {
        return new RolePermissionCollection(
            $this->select()
                ->where('role_id', $roleId->value())
                ->fetchAll(),
        );
    }

    public function exists(RoleId $roleId, PermissionId $permissionId): bool
    {
        return $this->findOne([
            'role_id' => $roleId->value(),
            'permission_id' => $permissionId->value(),
        ]) !== null;
    }
}
