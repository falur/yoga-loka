<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Collection;

use App\Modules\Posts\Domain\Entity\CommentMention;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, CommentMention>
 */
final class CommentMentionCollection extends Collection {}
