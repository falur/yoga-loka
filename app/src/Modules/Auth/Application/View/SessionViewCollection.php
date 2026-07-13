<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\View;

use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, SessionView>
 */
final class SessionViewCollection extends TypedCollection {}
