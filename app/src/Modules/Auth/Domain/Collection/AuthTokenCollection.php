<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Collection;

use App\Modules\Auth\Domain\Entity\AuthToken;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, AuthToken>
 */
final class AuthTokenCollection extends Collection {}
