<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain\Entity;

use App\Modules\Access\Domain\ValueObject\RoleId;
use App\Modules\Access\Domain\ValueObject\RoleSlug;
use App\Modules\Access\Repository\RoleRepository;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use App\Shared\Infrastructure\Persistence\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'role',
    table: 'roles',
    repository: RoleRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class Role
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: RoleId::class)]
    public private(set) RoleId $id;

    #[Column(type: 'string(64)', typecast: RoleSlug::class)]
    public private(set) RoleSlug $slug;

    public static function create(RoleSlug $slug): self
    {
        $role = new self();
        $role->id = RoleId::generate();
        $role->slug = $slug;
        $role->initializeTimestamps();

        return $role;
    }
}
