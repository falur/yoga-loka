<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain\Entity;

use App\Modules\Access\Domain\ValueObject\RoleId;
use App\Modules\Access\Domain\ValueObject\RoleSlug;
use App\Shared\Domain\Trait\HasTimestamps;

final class Role
{
    use HasTimestamps;

    public private(set) RoleId $id;

    public private(set) RoleSlug $slug;

    public static function create(RoleSlug $slug): self
    {
        $role = new self();
        $role->id = RoleId::generate();
        $role->slug = $slug;
        $role->initializeTimestamps();

        return $role;
    }

    public static function restore(
        RoleId $id,
        RoleSlug $slug,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $role = new self();
        $role->id = $id;
        $role->slug = $slug;
        $role->createdAt = $createdAt;
        $role->updatedAt = $updatedAt;

        return $role;
    }
}
