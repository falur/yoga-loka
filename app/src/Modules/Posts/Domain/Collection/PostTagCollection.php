<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Collection;

use App\Modules\Posts\Domain\Entity\PostTag;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, PostTag>
 */
final class PostTagCollection extends TypedCollection {}
