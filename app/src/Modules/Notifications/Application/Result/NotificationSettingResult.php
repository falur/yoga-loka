<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Result;

use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;

/**
 * Строка экрана настроек «вид × канал»: эффективное значение (enabled) с учётом персональной
 * настройки и значение по умолчанию (default) из определения вида.
 */
final readonly class NotificationSettingResult
{
    public function __construct(
        public NotificationTypeCode $type,
        public NotificationChannel $channel,
        public bool $enabled,
        public bool $default,
    ) {}
}
