<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Collection;

use App\Modules\Posts\Domain\Entity\PostMedia;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, PostMedia>
 */
final class PostMediaCollection extends Collection {}
