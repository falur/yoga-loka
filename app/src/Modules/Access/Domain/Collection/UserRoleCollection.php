<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain\Collection;

use App\Modules\Access\Domain\Entity\UserRole;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, UserRole>
 */
final class UserRoleCollection extends TypedCollection {}
