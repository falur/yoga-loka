<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Notifications\Domain\Enum;

use App\Modules\Notifications\Domain\Enum\DevicePlatform;
use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Modules\Notifications\Domain\Enum\NotificationSettingStatus;
use PHPUnit\Framework\TestCase;

final class NotificationEnumTest extends TestCase
{
    public function testNotificationChannelValues(): void
    {
        self::assertSame(['database', 'push', 'realtime'], $this->values(NotificationChannel::cases()));
    }

    public function testDevicePlatformValues(): void
    {
        self::assertSame(['ios', 'android'], $this->values(DevicePlatform::cases()));
    }

    public function testNotificationSettingStatusValues(): void
    {
        self::assertSame(['enabled', 'disabled'], $this->values(NotificationSettingStatus::cases()));
    }

    /**
     * @param list<\BackedEnum> $cases
     *
     * @return list<string>
     */
    private function values(array $cases): array
    {
        return \array_map(static fn(\BackedEnum $case): string => (string) $case->value, $cases);
    }
}
