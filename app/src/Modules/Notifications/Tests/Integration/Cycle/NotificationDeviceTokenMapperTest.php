<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Integration\Cycle;

use App\Modules\Notifications\Domain\Entity\NotificationDeviceToken;
use App\Modules\Notifications\Domain\Enum\DevicePlatform;
use App\Modules\Notifications\Domain\ValueObject\DeviceToken;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Mapper\NotificationDeviceTokenMapper;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class NotificationDeviceTokenMapperTest extends TestCase
{
    public function testMapsDeviceTokenToAndFromCycleEntity(): void
    {
        $mapper = new NotificationDeviceTokenMapper();
        $userId = UserId::generate();
        $deviceToken = NotificationDeviceToken::create(
            userId: $userId,
            token: DeviceToken::fromString('fcm-token'),
            platform: DevicePlatform::Ios,
        );

        $cycleEntity = $mapper->toCycleEntity($deviceToken);
        self::assertSame($deviceToken->id->value(), $cycleEntity->id);
        self::assertSame($userId->value(), $cycleEntity->userId);
        self::assertSame('fcm-token', $cycleEntity->token);
        self::assertSame(DevicePlatform::Ios, $cycleEntity->platform);

        $restored = $mapper->toDomain($cycleEntity);
        self::assertTrue($deviceToken->id->equals($restored->id));
        self::assertTrue($userId->equals($restored->userId));
        self::assertTrue($deviceToken->token->equals($restored->token));
        self::assertSame(DevicePlatform::Ios, $restored->platform);
    }

    public function testToCycleEntityUpdatesGivenInstanceInPlace(): void
    {
        $mapper = new NotificationDeviceTokenMapper();
        $deviceToken = NotificationDeviceToken::create(
            userId: UserId::generate(),
            token: DeviceToken::fromString('fcm-token'),
            platform: DevicePlatform::Ios,
        );

        $cycleEntity = $mapper->toCycleEntity($deviceToken);
        $newOwner = UserId::generate();
        $deviceToken->reassignTo($newOwner, DevicePlatform::Android);
        $updatedCycleEntity = $mapper->toCycleEntity($deviceToken, $cycleEntity);

        self::assertSame($cycleEntity, $updatedCycleEntity);
        self::assertSame($newOwner->value(), $updatedCycleEntity->userId);
        self::assertSame(DevicePlatform::Android, $updatedCycleEntity->platform);
    }
}
