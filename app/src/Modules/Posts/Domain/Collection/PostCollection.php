<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Collection;

use App\Modules\Posts\Domain\Entity\Post;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, Post>
 */
final class PostCollection extends Collection {}
