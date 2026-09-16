<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain\Entity;

use App\Modules\Access\Domain\ValueObject\PermissionId;
use App\Modules\Access\Domain\ValueObject\PermissionSlug;
use App\Modules\Access\Infrastructure\Persistence\Cycle\Repository\CyclePermissionRepository;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use App\Shared\Infrastructure\Persistence\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'permission',
    table: 'permissions',
    repository: CyclePermissionRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class Permission
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: PermissionId::class)]
    public private(set) PermissionId $id;

    #[Column(type: 'string(64)', typecast: PermissionSlug::class)]
    public private(set) PermissionSlug $slug;

    public static function create(PermissionSlug $slug): self
    {
        $permission = new self();
        $permission->id = PermissionId::generate();
        $permission->slug = $slug;
        $permission->initializeTimestamps();

        return $permission;
    }
}
