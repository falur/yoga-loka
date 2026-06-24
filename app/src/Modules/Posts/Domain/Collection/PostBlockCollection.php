<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Collection;

use App\Modules\Posts\Domain\Entity\PostBlock;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, PostBlock>
 */
final class PostBlockCollection extends TypedCollection {}
