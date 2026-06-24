<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Collection;

use App\Modules\Notifications\Domain\Entity\NotificationDeviceToken;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, NotificationDeviceToken>
 */
final class NotificationDeviceTokenCollection extends TypedCollection {}
