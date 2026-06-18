<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Collection;

use App\Modules\Posts\Domain\Entity\Comment;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, Comment>
 */
final class CommentCollection extends Collection {}
