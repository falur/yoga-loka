<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Repository;

use App\Modules\Notifications\Domain\Collection\NotificationSettingCollection;
use App\Modules\Notifications\Domain\Entity\NotificationSetting;
use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;

/**
 * @extends AbstractRepository<NotificationSetting>
 */
final class NotificationSettingRepository extends AbstractRepository
{
    public function findForUserAndType(UserId $userId, NotificationTypeCode $type): NotificationSettingCollection
    {
        return new NotificationSettingCollection(
            $this->select()
                ->where('user_id', $userId->value())
                ->where('type', $type->value())
                ->fetchAll(),
        );
    }

    public function findForUser(UserId $userId): NotificationSettingCollection
    {
        return new NotificationSettingCollection(
            $this->select()
                ->where('user_id', $userId->value())
                ->fetchAll(),
        );
    }

    public function findOneForUserTypeChannel(
        UserId $userId,
        NotificationTypeCode $type,
        NotificationChannel $channel,
    ): NotificationSetting|null {
        return $this->findOne([
            'user_id' => $userId->value(),
            'type' => $type->value(),
            'channel' => $channel->value,
        ]);
    }
}
