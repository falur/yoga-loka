<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Collection;

use App\Modules\Posts\Domain\Entity\PostLike;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, PostLike>
 */
final class PostLikeCollection extends Collection {}
