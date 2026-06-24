<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\View;

use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, CommentView>
 */
final class CommentViewCollection extends TypedCollection {}
