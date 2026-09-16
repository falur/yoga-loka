<?php

declare(strict_types=1);

namespace App\Modules\Access\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\Access\Infrastructure\Persistence\Cycle\Columns\RoleColumns;
use App\Modules\Access\Infrastructure\Persistence\Cycle\Repository\CycleRoleRepository;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'role',
    table: RoleColumns::TABLE,
    repository: CycleRoleRepository::class,
    typecast: [Typecast::class],
)]
final class CycleRoleEntity
{
    use HasTimestamps;

    #[Column(type: 'uuid', name: RoleColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'string(64)', name: RoleColumns::SLUG)]
    public string $slug;
}
