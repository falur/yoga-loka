<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\Notifications\Domain\Entity\NotificationDeviceToken;
use App\Modules\Notifications\Domain\ValueObject\DeviceToken;
use App\Modules\Notifications\Domain\ValueObject\NotificationDeviceTokenId;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Entity\CycleNotificationDeviceTokenEntity;
use App\Shared\Domain\ValueObject\UserId;

final readonly class NotificationDeviceTokenMapper
{
    public function toDomain(CycleNotificationDeviceTokenEntity $cycleEntity): NotificationDeviceToken
    {
        return NotificationDeviceToken::restore(
            id: NotificationDeviceTokenId::fromString($cycleEntity->id),
            userId: UserId::fromString($cycleEntity->userId),
            token: DeviceToken::fromString($cycleEntity->token),
            platform: $cycleEntity->platform,
            createdAt: $cycleEntity->createdAt,
            updatedAt: $cycleEntity->updatedAt,
        );
    }

    public function toCycleEntity(
        NotificationDeviceToken $notificationDeviceToken,
        CycleNotificationDeviceTokenEntity|null $cycleEntity = null,
    ): CycleNotificationDeviceTokenEntity {
        $cycleEntity ??= new CycleNotificationDeviceTokenEntity();
        $cycleEntity->id = $notificationDeviceToken->id->value();
        $cycleEntity->userId = $notificationDeviceToken->userId->value();
        $cycleEntity->token = $notificationDeviceToken->token->value();
        $cycleEntity->platform = $notificationDeviceToken->platform;
        $cycleEntity->createdAt = $notificationDeviceToken->createdAt;
        $cycleEntity->updatedAt = $notificationDeviceToken->updatedAt;

        return $cycleEntity;
    }
}
