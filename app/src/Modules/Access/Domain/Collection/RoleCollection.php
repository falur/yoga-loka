<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain\Collection;

use App\Modules\Access\Domain\Entity\Role;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, Role>
 */
final class RoleCollection extends TypedCollection {}
