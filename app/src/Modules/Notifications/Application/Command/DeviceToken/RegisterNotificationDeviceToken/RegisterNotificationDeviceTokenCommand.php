<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\DeviceToken\RegisterNotificationDeviceToken;

final readonly class RegisterNotificationDeviceTokenCommand
{
    public function __construct(
        public string $userId,
        public string $token,
        public string $platform,
    ) {}
}
