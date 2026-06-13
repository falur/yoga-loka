<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain\Collection;

use App\Modules\Access\Domain\Entity\UserRole;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, UserRole>
 */
final class UserRoleCollection extends Collection {}
