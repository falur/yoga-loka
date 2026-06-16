<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Enum;

/**
 * Статус пользовательской настройки «вид × канал». Хранится в boolean-колонке enabled
 * через отдельный typecast (Entity без примитивов).
 */
enum NotificationSettingStatus: string
{
    case Enabled = 'enabled';
    case Disabled = 'disabled';
}
