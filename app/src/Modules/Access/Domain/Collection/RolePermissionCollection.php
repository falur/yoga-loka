<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain\Collection;

use App\Modules\Access\Domain\Entity\RolePermission;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, RolePermission>
 */
final class RolePermissionCollection extends Collection {}
