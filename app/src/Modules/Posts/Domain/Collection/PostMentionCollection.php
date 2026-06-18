<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Collection;

use App\Modules\Posts\Domain\Entity\PostMention;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, PostMention>
 */
final class PostMentionCollection extends Collection {}
