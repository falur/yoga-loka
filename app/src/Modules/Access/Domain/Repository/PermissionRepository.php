<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain\Repository;

use App\Modules\Access\Domain\Collection\PermissionCollection;
use App\Modules\Access\Domain\Entity\Permission;
use App\Modules\Access\Domain\ValueObject\PermissionId;
use App\Modules\Access\Domain\ValueObject\PermissionSlug;

/**
 * Хранение прав. Справочник прав имеет собственную идентичность и читается по slug и целиком,
 * без какой-либо роли, поэтому это отдельный корень агрегата.
 */
interface PermissionRepository
{
    public function findById(PermissionId $permissionId): Permission|null;

    public function findBySlug(PermissionSlug $slug): Permission|null;

    public function findByIds(PermissionId ...$ids): PermissionCollection;

    /**
     * Справочник прав целиком.
     */
    public function findAll(): PermissionCollection;
}
