<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Collection;

use App\Modules\Posts\Domain\Entity\CommentLike;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, CommentLike>
 */
final class CommentLikeCollection extends TypedCollection {}
