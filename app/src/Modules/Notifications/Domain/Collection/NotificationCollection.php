<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Collection;

use App\Modules\Notifications\Domain\Entity\Notification;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, Notification>
 */
final class NotificationCollection extends TypedCollection {}
