<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Collection;

use App\Modules\Posts\Domain\Entity\PostMedia;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, PostMedia>
 */
final class PostMediaCollection extends TypedCollection {}
