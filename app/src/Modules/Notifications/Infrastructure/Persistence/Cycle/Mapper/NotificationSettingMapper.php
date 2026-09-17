<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\Notifications\Domain\Entity\NotificationSetting;
use App\Modules\Notifications\Domain\ValueObject\NotificationSettingId;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Entity\CycleNotificationSettingEntity;
use App\Shared\Domain\ValueObject\UserId;

final readonly class NotificationSettingMapper
{
    public function toDomain(CycleNotificationSettingEntity $cycleEntity): NotificationSetting
    {
        return NotificationSetting::restore(
            id: NotificationSettingId::fromString($cycleEntity->id),
            userId: UserId::fromString($cycleEntity->userId),
            type: NotificationTypeCode::fromString($cycleEntity->type),
            channel: $cycleEntity->channel,
            status: $cycleEntity->status,
            createdAt: $cycleEntity->createdAt,
            updatedAt: $cycleEntity->updatedAt,
        );
    }

    public function toCycleEntity(
        NotificationSetting $notificationSetting,
        CycleNotificationSettingEntity|null $cycleEntity = null,
    ): CycleNotificationSettingEntity {
        $cycleEntity ??= new CycleNotificationSettingEntity();
        $cycleEntity->id = $notificationSetting->id->value();
        $cycleEntity->userId = $notificationSetting->userId->value();
        $cycleEntity->type = $notificationSetting->type->value();
        $cycleEntity->channel = $notificationSetting->channel;
        $cycleEntity->status = $notificationSetting->status;
        $cycleEntity->createdAt = $notificationSetting->createdAt;
        $cycleEntity->updatedAt = $notificationSetting->updatedAt;

        return $cycleEntity;
    }
}
