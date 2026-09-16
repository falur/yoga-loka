<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Query\Setting\GetNotificationSettings;

use App\Modules\Notifications\Application\Contract\NotificationTypeCatalogContract;
use App\Modules\Notifications\Application\Result\NotificationSettingResultCollection;
use App\Modules\Notifications\Domain\Repository\NotificationSettingRepository;
use App\Shared\Domain\ValueObject\UserId;

final readonly class GetNotificationSettingsHandler
{
    public function __construct(
        private NotificationSettingRepository $notificationSettingRepository,
        private NotificationTypeCatalogContract $typeCatalog,
    ) {}

    public function handle(GetNotificationSettingsQuery $query): NotificationSettingResultCollection
    {
        $userId = UserId::fromString($query->userId);

        return NotificationSettingResultCollection::build(
            definitions: $this->typeCatalog->all(),
            settings: $this->notificationSettingRepository->findForUser($userId),
        );
    }
}
