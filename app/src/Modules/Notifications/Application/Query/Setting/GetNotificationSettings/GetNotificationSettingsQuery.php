<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Query\Setting\GetNotificationSettings;

final readonly class GetNotificationSettingsQuery
{
    public function __construct(
        public string $userId,
    ) {}
}
