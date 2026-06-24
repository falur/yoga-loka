<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Collection;

use App\Modules\Auth\Domain\Entity\AuthToken;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, AuthToken>
 */
final class AuthTokenCollection extends TypedCollection {}
