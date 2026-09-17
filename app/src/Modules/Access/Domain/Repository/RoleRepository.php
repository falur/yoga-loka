<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain\Repository;

use App\Modules\Access\Domain\Collection\RoleCollection;
use App\Modules\Access\Domain\Collection\RolePermissionCollection;
use App\Modules\Access\Domain\Entity\Role;
use App\Modules\Access\Domain\ValueObject\PermissionId;
use App\Modules\Access\Domain\ValueObject\RoleId;
use App\Modules\Access\Domain\ValueObject\RoleSlug;

/**
 * Хранение ролей. Корень агрегата — Role, его внутренняя сущность — связь роли с правом:
 * право роли существует только у своей роли и отдельно не заводится, поэтому своего репозитория
 * у связи нет и выборку по её таблице ведёт этот репозиторий.
 */
interface RoleRepository
{
    public function findById(RoleId $roleId): Role|null;

    public function findBySlug(RoleSlug $slug): Role|null;

    public function findByIds(RoleId ...$ids): RoleCollection;

    /**
     * Справочник ролей целиком.
     */
    public function findAll(): RoleCollection;

    /**
     * Права роли — внутренние сущности её агрегата.
     */
    public function findPermissions(RoleId $roleId): RolePermissionCollection;

    public function hasPermission(RoleId $roleId, PermissionId $permissionId): bool;
}
