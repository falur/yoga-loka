<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Collection;

use App\Modules\Posts\Domain\Entity\Comment;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, Comment>
 */
final class CommentCollection extends TypedCollection {}
