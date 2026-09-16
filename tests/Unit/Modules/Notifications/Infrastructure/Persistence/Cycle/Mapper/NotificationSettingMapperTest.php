<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Notifications\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\Notifications\Domain\Entity\NotificationSetting;
use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Modules\Notifications\Domain\Enum\NotificationSettingStatus;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Mapper\NotificationSettingMapper;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

/**
 * NotificationSettingMapper не занимается null-bridging-ом статуса: колонка enabled остаётся на
 * отдельном NotificationSettingStatusTypecast (вторая категория «Правила переноса значений
 * колонок» — boolean-представление PostgreSQL зависит от драйвера, поэтому наивный встроенный
 * typecast: 'bool' Cycle небезопасен; см. NotificationSettingStatusTypecastTest). Mapper передаёт
 * уже собранный typecast-ом NotificationSettingStatus дальше без изменений.
 */
final class NotificationSettingMapperTest extends TestCase
{
    public function testMapsEnabledSettingToAndFromCycleEntity(): void
    {
        $mapper = new NotificationSettingMapper();
        $setting = NotificationSetting::create(
            userId: UserId::generate(),
            type: NotificationTypeCode::fromString('chat.message_received'),
            channel: NotificationChannel::Push,
            status: NotificationSettingStatus::Enabled,
        );

        $cycleEntity = $mapper->toCycleEntity($setting);
        self::assertSame(NotificationSettingStatus::Enabled, $cycleEntity->status);
        self::assertSame(NotificationChannel::Push, $cycleEntity->channel);

        $restored = $mapper->toDomain($cycleEntity);
        self::assertTrue($restored->isEnabled());
        self::assertSame(NotificationSettingStatus::Enabled, $restored->status);
    }

    public function testMapsDisabledSettingToAndFromCycleEntity(): void
    {
        $mapper = new NotificationSettingMapper();
        $setting = NotificationSetting::create(
            userId: UserId::generate(),
            type: NotificationTypeCode::fromString('chat.message_received'),
            channel: NotificationChannel::Realtime,
            status: NotificationSettingStatus::Disabled,
        );

        $cycleEntity = $mapper->toCycleEntity($setting);
        self::assertSame(NotificationSettingStatus::Disabled, $cycleEntity->status);

        $restored = $mapper->toDomain($cycleEntity);
        self::assertFalse($restored->isEnabled());
    }

    public function testToCycleEntityUpdatesGivenInstanceInPlace(): void
    {
        $mapper = new NotificationSettingMapper();
        $setting = NotificationSetting::create(
            userId: UserId::generate(),
            type: NotificationTypeCode::fromString('chat.message_received'),
            channel: NotificationChannel::Push,
            status: NotificationSettingStatus::Disabled,
        );

        $cycleEntity = $mapper->toCycleEntity($setting);
        $setting->enable();
        $updatedCycleEntity = $mapper->toCycleEntity($setting, $cycleEntity);

        self::assertSame($cycleEntity, $updatedCycleEntity);
        self::assertSame(NotificationSettingStatus::Enabled, $updatedCycleEntity->status);
    }
}
