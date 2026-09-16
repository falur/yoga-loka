<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Result;

use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, SessionResult>
 */
final class SessionResultCollection extends TypedCollection {}
