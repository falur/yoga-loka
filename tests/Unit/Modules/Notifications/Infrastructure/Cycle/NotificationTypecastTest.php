<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Notifications\Infrastructure\Cycle;

use App\Modules\Notifications\Domain\Enum\NotificationSettingStatus;
use App\Modules\Notifications\Domain\ValueObject\NotificationActionId;
use App\Modules\Notifications\Domain\ValueObject\NotificationActionType;
use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Modules\Notifications\Domain\ValueObject\NotificationReadState;
use App\Modules\Notifications\Infrastructure\Cycle\NotificationActionIdTypecast;
use App\Modules\Notifications\Infrastructure\Cycle\NotificationActionTypeTypecast;
use App\Modules\Notifications\Infrastructure\Cycle\NotificationActorTypecast;
use App\Modules\Notifications\Infrastructure\Cycle\NotificationReadStateTypecast;
use App\Modules\Notifications\Infrastructure\Cycle\NotificationSettingStatusTypecast;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NotificationTypecastTest extends TestCase
{
    public function testActionTypeTypecastHandlesNullableString(): void
    {
        self::assertFalse(NotificationActionTypeTypecast::castDatabaseValue(null)->isPresent());
        self::assertSame('chat', NotificationActionTypeTypecast::castDatabaseValue('chat')->value());
        self::assertSame('chat', NotificationActionTypeTypecast::uncastValue(NotificationActionType::of('chat')));
        self::assertNull(NotificationActionTypeTypecast::uncastValue(NotificationActionType::none()));
        self::assertNull(NotificationActionTypeTypecast::uncastValue(null));
    }

    public function testActionIdTypecastHandlesNullableString(): void
    {
        self::assertFalse(NotificationActionIdTypecast::castDatabaseValue(null)->isPresent());
        self::assertSame('42', NotificationActionIdTypecast::castDatabaseValue('42')->value());
        self::assertSame('42', NotificationActionIdTypecast::uncastValue(NotificationActionId::of('42')));
        self::assertNull(NotificationActionIdTypecast::uncastValue(NotificationActionId::none()));
        self::assertNull(NotificationActionIdTypecast::uncastValue(null));
    }

    public function testActorTypecastHandlesNullableJsonSnapshot(): void
    {
        $userId = UserId::generate();
        $snapshot = \json_encode(
            ['id' => $userId->value(), 'name' => 'Иван', 'avatarUrl' => 'https://cdn/a.jpg'],
            \JSON_THROW_ON_ERROR,
        );

        self::assertFalse(NotificationActorTypecast::castDatabaseValue(null)->isPresent());

        $restored = NotificationActorTypecast::castDatabaseValue($snapshot);
        self::assertTrue($restored->isPresent());
        self::assertSame($userId->value(), $restored->presentId());
        self::assertSame('Иван', $restored->presentName());
        self::assertSame('https://cdn/a.jpg', $restored->presentAvatarUrl());

        $uncast = NotificationActorTypecast::uncastValue(
            NotificationActor::of(userId: $userId, name: 'Иван', avatarUrl: 'https://cdn/a.jpg'),
        );
        self::assertIsString($uncast);
        self::assertSame(
            ['id' => $userId->value(), 'name' => 'Иван', 'avatarUrl' => 'https://cdn/a.jpg'],
            \json_decode($uncast, associative: true, flags: \JSON_THROW_ON_ERROR),
        );

        self::assertNull(NotificationActorTypecast::uncastValue(NotificationActor::none()));
        self::assertNull(NotificationActorTypecast::uncastValue(null));
    }

    public function testActorTypecastRejectsNonObjectJson(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        NotificationActorTypecast::castDatabaseValue('"plain-string"');
    }

    public function testActorTypecastRejectsMalformedSnapshot(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        NotificationActorTypecast::castDatabaseValue(
            \json_encode(['id' => 'x', 'name' => 'Иван'], \JSON_THROW_ON_ERROR),
        );
    }

    public function testReadStateTypecastHandlesNullableDate(): void
    {
        $readAt = new \DateTimeImmutable('2026-06-13 12:00:00');

        self::assertFalse(NotificationReadStateTypecast::castDatabaseValue(null)->isRead());
        self::assertSame($readAt, NotificationReadStateTypecast::castDatabaseValue($readAt)->value());
        self::assertSame($readAt, NotificationReadStateTypecast::uncastValue(NotificationReadState::readAt($readAt)));
        self::assertNull(NotificationReadStateTypecast::uncastValue(NotificationReadState::unread()));
        self::assertNull(NotificationReadStateTypecast::uncastValue(null));
    }

    public function testReadStateTypecastConvertsMutableAndStringDate(): void
    {
        $mutableDate = new \DateTime('2026-06-13 12:00:00');

        self::assertEquals(
            \DateTimeImmutable::createFromInterface($mutableDate),
            NotificationReadStateTypecast::castDatabaseValue($mutableDate)->value(),
        );
        self::assertEquals(
            new \DateTimeImmutable('2026-06-13 12:00:00'),
            NotificationReadStateTypecast::castDatabaseValue('2026-06-13 12:00:00')->value(),
        );
    }

    /**
     * @param bool|int|string|null $databaseValue
     */
    #[DataProvider('settingStatusProvider')]
    public function testSettingStatusTypecastNormalizesDatabaseValue(
        bool|int|string|null $databaseValue,
        NotificationSettingStatus $expected,
    ): void {
        self::assertSame($expected, NotificationSettingStatusTypecast::castDatabaseValue($databaseValue));
    }

    public function testSettingStatusTypecastUncastsToBool(): void
    {
        self::assertTrue(NotificationSettingStatusTypecast::uncastValue(NotificationSettingStatus::Enabled));
        self::assertFalse(NotificationSettingStatusTypecast::uncastValue(NotificationSettingStatus::Disabled));
        self::assertFalse(NotificationSettingStatusTypecast::uncastValue(null));
    }

    /**
     * @return iterable<string, array{bool|int|string|null, NotificationSettingStatus}>
     */
    public static function settingStatusProvider(): iterable
    {
        yield 'bool true' => [true, NotificationSettingStatus::Enabled];
        yield 'bool false' => [false, NotificationSettingStatus::Disabled];
        yield 'int 1' => [1, NotificationSettingStatus::Enabled];
        yield 'int 0' => [0, NotificationSettingStatus::Disabled];
        yield 'string t' => ['t', NotificationSettingStatus::Enabled];
        yield 'string true' => ['true', NotificationSettingStatus::Enabled];
        yield 'string f' => ['f', NotificationSettingStatus::Disabled];
        yield 'null' => [null, NotificationSettingStatus::Disabled];
    }
}
