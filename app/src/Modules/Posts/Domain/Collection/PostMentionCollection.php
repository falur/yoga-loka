<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Collection;

use App\Modules\Posts\Domain\Entity\PostMention;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, PostMention>
 */
final class PostMentionCollection extends TypedCollection {}
