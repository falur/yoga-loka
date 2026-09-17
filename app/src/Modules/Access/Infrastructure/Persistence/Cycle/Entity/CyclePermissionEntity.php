<?php

declare(strict_types=1);

namespace App\Modules\Access\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\Access\Infrastructure\Persistence\Cycle\Columns\PermissionColumns;
use App\Modules\Access\Infrastructure\Persistence\Cycle\Repository\CyclePermissionRepository;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'permission',
    table: PermissionColumns::TABLE,
    repository: CyclePermissionRepository::class,
    typecast: [Typecast::class],
)]
final class CyclePermissionEntity
{
    use HasTimestamps;

    #[Column(type: 'uuid', name: PermissionColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'string(64)', name: PermissionColumns::SLUG)]
    public string $slug;
}
