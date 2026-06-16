<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Enum;

/**
 * Платформа устройства push-токена.
 */
enum DevicePlatform: string
{
    case Ios = 'ios';
    case Android = 'android';
}
