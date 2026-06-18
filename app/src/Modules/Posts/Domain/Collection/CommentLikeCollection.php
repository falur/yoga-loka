<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Collection;

use App\Modules\Posts\Domain\Entity\CommentLike;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, CommentLike>
 */
final class CommentLikeCollection extends Collection {}
