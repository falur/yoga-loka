<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\View;

use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, PostView>
 */
final class PostViewCollection extends TypedCollection {}
