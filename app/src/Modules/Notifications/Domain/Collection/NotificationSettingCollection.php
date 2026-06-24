<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Collection;

use App\Modules\Notifications\Domain\Entity\NotificationSetting;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, NotificationSetting>
 */
final class NotificationSettingCollection extends TypedCollection {}
