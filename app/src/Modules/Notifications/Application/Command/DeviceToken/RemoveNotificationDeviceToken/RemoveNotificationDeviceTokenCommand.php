<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\DeviceToken\RemoveNotificationDeviceToken;

final readonly class RemoveNotificationDeviceTokenCommand
{
    public function __construct(
        public string $userId,
        public string $token,
    ) {}
}
