<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Collection;

use App\Modules\Notifications\Domain\Entity\NotificationDeviceToken;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, NotificationDeviceToken>
 */
final class NotificationDeviceTokenCollection extends Collection {}
