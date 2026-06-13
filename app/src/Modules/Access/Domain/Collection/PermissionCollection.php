<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain\Collection;

use App\Modules\Access\Domain\Entity\Permission;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, Permission>
 */
final class PermissionCollection extends Collection {}
