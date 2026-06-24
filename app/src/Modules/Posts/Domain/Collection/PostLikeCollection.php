<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Collection;

use App\Modules\Posts\Domain\Entity\PostLike;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, PostLike>
 */
final class PostLikeCollection extends TypedCollection {}
