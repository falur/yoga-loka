<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\Notifications\Domain\Enum\DevicePlatform;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Columns\NotificationDeviceTokenColumns;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Repository\CycleNotificationDeviceTokenRepository;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

/**
 * Push-токен устройства (открытым текстом, unique). Привязка к пользователю и платформе.
 */
#[Entity(
    role: 'notificationDeviceToken',
    table: NotificationDeviceTokenColumns::TABLE,
    repository: CycleNotificationDeviceTokenRepository::class,
    typecast: [Typecast::class],
)]
final class CycleNotificationDeviceTokenEntity
{
    use HasTimestamps;

    #[Column(type: 'uuid', name: NotificationDeviceTokenColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'uuid', name: NotificationDeviceTokenColumns::USER_ID)]
    public string $userId;

    #[Column(type: 'string(1024)', name: NotificationDeviceTokenColumns::TOKEN)]
    public string $token;

    #[Column(type: 'string(32)', name: NotificationDeviceTokenColumns::PLATFORM, typecast: DevicePlatform::class)]
    public DevicePlatform $platform;
}
