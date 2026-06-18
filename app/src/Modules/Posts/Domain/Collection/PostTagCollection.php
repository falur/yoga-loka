<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Collection;

use App\Modules\Posts\Domain\Entity\PostTag;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, PostTag>
 */
final class PostTagCollection extends Collection {}
