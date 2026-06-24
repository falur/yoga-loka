<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Dto;

use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, NotificationSettingView>
 */
final class NotificationSettingViewCollection extends TypedCollection {}
