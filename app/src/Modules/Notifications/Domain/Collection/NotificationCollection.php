<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Collection;

use App\Modules\Notifications\Domain\Entity\Notification;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, Notification>
 */
final class NotificationCollection extends Collection {}
