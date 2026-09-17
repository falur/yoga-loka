<?php

declare(strict_types=1);

namespace App\Modules\Access\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\Access\Infrastructure\Persistence\Cycle\Columns\UserRoleColumns;
use App\Modules\Access\Infrastructure\Persistence\Cycle\Repository\CycleUserRoleRepository;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'user_role',
    table: UserRoleColumns::TABLE,
    repository: CycleUserRoleRepository::class,
    typecast: [Typecast::class],
)]
final class CycleUserRoleEntity
{
    #[Column(type: 'uuid', name: UserRoleColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'uuid', name: UserRoleColumns::USER_ID)]
    public string $userId;

    #[Column(type: 'uuid', name: UserRoleColumns::ROLE_ID)]
    public string $roleId;
}
