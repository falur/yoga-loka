<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain\Collection;

use App\Modules\Access\Domain\Entity\RolePermission;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, RolePermission>
 */
final class RolePermissionCollection extends TypedCollection {}
