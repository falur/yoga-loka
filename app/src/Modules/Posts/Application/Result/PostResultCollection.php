<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Result;

use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, PostResult>
 */
final class PostResultCollection extends TypedCollection {}
