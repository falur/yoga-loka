<?php

declare(strict_types=1);

namespace App\Modules\Access\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\Access\Infrastructure\Persistence\Cycle\Columns\RolePermissionColumns;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

/**
 * Внутренняя сущность агрегата Role: связь роли с правом существует только у своей роли
 * и отдельно не заводится, поэтому у неё нет собственного Cycle Repository — выборку по её
 * таблице ведёт CycleRoleRepository (RoleRepository::findPermissions()/hasPermission()).
 */
#[Entity(
    role: 'role_permission',
    table: RolePermissionColumns::TABLE,
    typecast: [Typecast::class],
)]
final class CycleRolePermissionEntity
{
    #[Column(type: 'uuid', name: RolePermissionColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'uuid', name: RolePermissionColumns::ROLE_ID)]
    public string $roleId;

    #[Column(type: 'uuid', name: RolePermissionColumns::PERMISSION_ID)]
    public string $permissionId;
}
