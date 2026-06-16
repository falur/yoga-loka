<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Collection;

use App\Modules\Notifications\Domain\Entity\NotificationSetting;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, NotificationSetting>
 */
final class NotificationSettingCollection extends Collection {}
