<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Collection;

use App\Modules\User\Domain\Entity\User;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, User>
 */
final class UserCollection extends TypedCollection {}
