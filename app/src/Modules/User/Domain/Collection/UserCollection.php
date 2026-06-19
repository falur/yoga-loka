<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Collection;

use App\Modules\User\Domain\Entity\User;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, User>
 */
final class UserCollection extends Collection {}
