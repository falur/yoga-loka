<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Entity;

use App\Modules\Notifications\Domain\Enum\DevicePlatform;
use App\Modules\Notifications\Domain\ValueObject\DeviceToken;
use App\Modules\Notifications\Domain\ValueObject\NotificationDeviceTokenId;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Push-токен устройства (открытым текстом, unique). Привязка к пользователю и платформе.
 */
final class NotificationDeviceToken
{
    use HasTimestamps;

    public private(set) NotificationDeviceTokenId $id;

    public private(set) UserId $userId;

    public private(set) DeviceToken $token;

    public private(set) DevicePlatform $platform;

    public static function create(
        UserId $userId,
        DeviceToken $token,
        DevicePlatform $platform,
    ): self {
        $deviceToken = new self();
        $deviceToken->id = NotificationDeviceTokenId::generate();
        $deviceToken->userId = $userId;
        $deviceToken->token = $token;
        $deviceToken->platform = $platform;
        $deviceToken->initializeTimestamps();

        return $deviceToken;
    }

    public static function restore(
        NotificationDeviceTokenId $id,
        UserId $userId,
        DeviceToken $token,
        DevicePlatform $platform,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $deviceToken = new self();
        $deviceToken->id = $id;
        $deviceToken->userId = $userId;
        $deviceToken->token = $token;
        $deviceToken->platform = $platform;
        $deviceToken->createdAt = $createdAt;
        $deviceToken->updatedAt = $updatedAt;

        return $deviceToken;
    }

    public function reassignTo(UserId $userId, DevicePlatform $platform): void
    {
        $this->userId = $userId;
        $this->platform = $platform;
        $this->touch();
    }
}
