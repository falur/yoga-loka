<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Entity;

use App\Modules\Notifications\Domain\Enum\DevicePlatform;
use App\Modules\Notifications\Domain\ValueObject\DeviceToken;
use App\Modules\Notifications\Domain\ValueObject\NotificationDeviceTokenId;
use App\Modules\Notifications\Repository\NotificationDeviceTokenRepository;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

/**
 * Push-токен устройства (открытым текстом, unique). Привязка к пользователю и платформе.
 */
#[Entity(
    role: 'notificationDeviceToken',
    table: 'notification_device_tokens',
    repository: NotificationDeviceTokenRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class NotificationDeviceToken
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: NotificationDeviceTokenId::class)]
    public private(set) NotificationDeviceTokenId $id;

    #[Column(type: 'uuid', name: 'user_id', typecast: UserId::class)]
    public private(set) UserId $userId;

    #[Column(type: 'string(1024)', typecast: DeviceToken::class)]
    public private(set) DeviceToken $token;

    #[Column(type: 'string(32)', typecast: DevicePlatform::class)]
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

    public function reassignTo(UserId $userId, DevicePlatform $platform): void
    {
        $this->userId = $userId;
        $this->platform = $platform;
        $this->touch();
    }
}
