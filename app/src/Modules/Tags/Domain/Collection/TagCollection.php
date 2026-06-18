<?php

declare(strict_types=1);

namespace App\Modules\Tags\Domain\Collection;

use App\Modules\Tags\Domain\Entity\Tag;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, Tag>
 */
final class TagCollection extends Collection {}
