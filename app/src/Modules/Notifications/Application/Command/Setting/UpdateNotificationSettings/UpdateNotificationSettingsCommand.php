<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\Setting\UpdateNotificationSettings;

final readonly class UpdateNotificationSettingsCommand
{
    /**
     * @param list<NotificationSettingUpdate> $updates
     */
    public function __construct(
        public string $userId,
        public array $updates,
    ) {}
}
