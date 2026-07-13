<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\View;

use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, NotificationView>
 */
final class NotificationViewCollection extends TypedCollection {}
