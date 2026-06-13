<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain\Collection;

use App\Modules\Access\Domain\Entity\Role;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, Role>
 */
final class RoleCollection extends Collection {}
