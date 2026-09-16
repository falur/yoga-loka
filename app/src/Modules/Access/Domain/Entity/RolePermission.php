<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain\Entity;

use App\Modules\Access\Domain\ValueObject\PermissionId;
use App\Modules\Access\Domain\ValueObject\RoleId;
use App\Modules\Access\Domain\ValueObject\RolePermissionId;
use App\Shared\Infrastructure\Persistence\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'role_permission',
    table: 'role_permissions',
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class RolePermission
{
    #[Column(type: 'uuid', primary: true, typecast: RolePermissionId::class)]
    public private(set) RolePermissionId $id;

    #[Column(type: 'uuid', name: 'role_id', typecast: RoleId::class)]
    public private(set) RoleId $roleId;

    #[Column(type: 'uuid', name: 'permission_id', typecast: PermissionId::class)]
    public private(set) PermissionId $permissionId;

    public static function create(RoleId $roleId, PermissionId $permissionId): self
    {
        $rolePermission = new self();
        $rolePermission->id = RolePermissionId::generate();
        $rolePermission->roleId = $roleId;
        $rolePermission->permissionId = $permissionId;

        return $rolePermission;
    }
}
