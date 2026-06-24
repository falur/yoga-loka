<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Collection;

use App\Modules\Posts\Domain\Entity\CommentMention;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, CommentMention>
 */
final class CommentMentionCollection extends TypedCollection {}
