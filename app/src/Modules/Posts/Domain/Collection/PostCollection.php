<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Collection;

use App\Modules\Posts\Domain\Entity\Post;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, Post>
 */
final class PostCollection extends TypedCollection {}
