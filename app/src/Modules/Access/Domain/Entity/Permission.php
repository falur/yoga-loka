<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain\Entity;

use App\Modules\Access\Domain\ValueObject\PermissionId;
use App\Modules\Access\Domain\ValueObject\PermissionSlug;
use App\Shared\Domain\Trait\HasTimestamps;

final class Permission
{
    use HasTimestamps;

    public private(set) PermissionId $id;

    public private(set) PermissionSlug $slug;

    public static function create(PermissionSlug $slug): self
    {
        $permission = new self();
        $permission->id = PermissionId::generate();
        $permission->slug = $slug;
        $permission->initializeTimestamps();

        return $permission;
    }

    public static function restore(
        PermissionId $id,
        PermissionSlug $slug,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $permission = new self();
        $permission->id = $id;
        $permission->slug = $slug;
        $permission->createdAt = $createdAt;
        $permission->updatedAt = $updatedAt;

        return $permission;
    }
}
