<?php

declare(strict_types=1);

namespace App\Modules\Tags\Domain\Collection;

use App\Modules\Tags\Domain\Entity\Tag;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, Tag>
 */
final class TagCollection extends TypedCollection {}
