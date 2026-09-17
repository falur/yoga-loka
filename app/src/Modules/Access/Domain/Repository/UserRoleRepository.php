<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain\Repository;

use App\Modules\Access\Domain\Collection\UserRoleCollection;
use App\Modules\Access\Domain\ValueObject\RoleId;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Хранение назначений ролей пользователям. Назначение заводится и снимается независимо от роли,
 * поэтому это отдельный корень агрегата.
 */
interface UserRoleRepository
{
    public function findByUserId(UserId $userId): UserRoleCollection;

    public function exists(UserId $userId, RoleId $roleId): bool;
}
