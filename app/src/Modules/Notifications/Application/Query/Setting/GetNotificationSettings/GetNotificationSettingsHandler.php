<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Query\Setting\GetNotificationSettings;

use App\Modules\Notifications\Application\Dto\NotificationSettingViewCollection;
use App\Modules\Notifications\Application\Service\NotificationSettingsViewFactory;
use App\Shared\Domain\ValueObject\UserId;

final readonly class GetNotificationSettingsHandler
{
    public function __construct(
        private NotificationSettingsViewFactory $settingsViewFactory,
    ) {}

    public function handle(GetNotificationSettingsQuery $query): NotificationSettingViewCollection
    {
        return $this->settingsViewFactory->build(UserId::fromString($query->userId));
    }
}
