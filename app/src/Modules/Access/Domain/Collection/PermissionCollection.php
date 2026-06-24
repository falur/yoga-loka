<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain\Collection;

use App\Modules\Access\Domain\Entity\Permission;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, Permission>
 */
final class PermissionCollection extends TypedCollection {}
