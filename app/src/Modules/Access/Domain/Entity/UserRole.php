<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain\Entity;

use App\Modules\Access\Domain\ValueObject\RoleId;
use App\Modules\Access\Domain\ValueObject\UserRoleId;
use App\Modules\Access\Repository\UserRoleRepository;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'user_role',
    table: 'user_roles',
    repository: UserRoleRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class UserRole
{
    #[Column(type: 'uuid', primary: true, typecast: UserRoleId::class)]
    public private(set) UserRoleId $id;

    #[Column(type: 'uuid', name: 'user_id', typecast: UserId::class)]
    public private(set) UserId $userId;

    #[Column(type: 'uuid', name: 'role_id', typecast: RoleId::class)]
    public private(set) RoleId $roleId;

    public static function create(UserId $userId, RoleId $roleId): self
    {
        $userRole = new self();
        $userRole->id = UserRoleId::generate();
        $userRole->userId = $userId;
        $userRole->roleId = $roleId;

        return $userRole;
    }
}
