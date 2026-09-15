<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\User\Infrastructure\Persistence\Cycle\Typecast;

use App\Modules\User\Domain\ValueObject\BanExpiration;
use App\Modules\User\Domain\ValueObject\BanUnbannedAt;
use App\Modules\User\Domain\ValueObject\BanUnbannedBy;
use App\Modules\User\Domain\ValueObject\BanUnbannedReason;
use App\Modules\User\Domain\ValueObject\ReservedNicknameHolder;
use App\Modules\User\Domain\ValueObject\UserAvatar;
use App\Modules\User\Domain\ValueObject\UserBio;
use App\Modules\User\Domain\ValueObject\UserDeletion;
use App\Modules\User\Domain\ValueObject\UserLocation;
use App\Modules\User\Domain\ValueObject\UserSpiritualName;
use App\Modules\User\Infrastructure\Persistence\Cycle\Typecast\BanExpirationTypecast;
use App\Modules\User\Infrastructure\Persistence\Cycle\Typecast\BanUnbannedAtTypecast;
use App\Modules\User\Infrastructure\Persistence\Cycle\Typecast\BanUnbannedByTypecast;
use App\Modules\User\Infrastructure\Persistence\Cycle\Typecast\BanUnbannedReasonTypecast;
use App\Modules\User\Infrastructure\Persistence\Cycle\Typecast\ReservedNicknameHolderTypecast;
use App\Modules\User\Infrastructure\Persistence\Cycle\Typecast\UserAvatarTypecast;
use App\Modules\User\Infrastructure\Persistence\Cycle\Typecast\UserBioTypecast;
use App\Modules\User\Infrastructure\Persistence\Cycle\Typecast\UserDeletionTypecast;
use App\Modules\User\Infrastructure\Persistence\Cycle\Typecast\UserLocationTypecast;
use App\Modules\User\Infrastructure\Persistence\Cycle\Typecast\UserSpiritualNameTypecast;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class UserTypecastTest extends TestCase
{
    public function testStringNullObjectTypecasts(): void
    {
        self::assertTrue(UserSpiritualNameTypecast::castDatabaseValue(null)->isEmpty());
        self::assertSame('Шанти', UserSpiritualNameTypecast::castDatabaseValue('Шанти')->value());
        self::assertNull(UserSpiritualNameTypecast::uncastValue(null));
        self::assertNull(UserSpiritualNameTypecast::uncastValue(UserSpiritualName::none()));
        self::assertSame('Шанти', UserSpiritualNameTypecast::uncastValue(UserSpiritualName::fromString('Шанти')));

        self::assertTrue(UserBioTypecast::castDatabaseValue(null)->isEmpty());
        self::assertSame('Описание', UserBioTypecast::castDatabaseValue('Описание')->value());
        self::assertNull(UserBioTypecast::uncastValue(null));
        self::assertNull(UserBioTypecast::uncastValue(UserBio::none()));
        self::assertSame('Описание', UserBioTypecast::uncastValue(UserBio::fromString('Описание')));

        self::assertTrue(UserLocationTypecast::castDatabaseValue(null)->isEmpty());
        self::assertSame('Москва', UserLocationTypecast::castDatabaseValue('Москва')->value());
        self::assertNull(UserLocationTypecast::uncastValue(null));
        self::assertNull(UserLocationTypecast::uncastValue(UserLocation::none()));
        self::assertSame('Москва', UserLocationTypecast::uncastValue(UserLocation::fromString('Москва')));

        self::assertTrue(BanUnbannedReasonTypecast::castDatabaseValue(null)->isEmpty());
        self::assertSame('Исправлено', BanUnbannedReasonTypecast::castDatabaseValue('Исправлено')->value());
        self::assertNull(BanUnbannedReasonTypecast::uncastValue(null));
        self::assertNull(BanUnbannedReasonTypecast::uncastValue(BanUnbannedReason::none()));
        self::assertSame('Исправлено', BanUnbannedReasonTypecast::uncastValue(BanUnbannedReason::of('Исправлено')));
    }

    public function testUuidNullObjectTypecasts(): void
    {
        $userId = UserId::generate();
        $mediaId = UserId::generate()->value();

        self::assertTrue(UserAvatarTypecast::castDatabaseValue(null)->isEmpty());
        self::assertSame($mediaId, UserAvatarTypecast::castDatabaseValue($mediaId)->value());
        self::assertNull(UserAvatarTypecast::uncastValue(null));
        self::assertNull(UserAvatarTypecast::uncastValue(UserAvatar::none()));
        self::assertSame($mediaId, UserAvatarTypecast::uncastValue(UserAvatar::pointingTo($mediaId)));

        self::assertTrue(BanUnbannedByTypecast::castDatabaseValue(null)->isEmpty());
        self::assertSame($userId->value(), BanUnbannedByTypecast::castDatabaseValue($userId->value())->value());
        self::assertNull(BanUnbannedByTypecast::uncastValue(null));
        self::assertNull(BanUnbannedByTypecast::uncastValue(BanUnbannedBy::none()));
        self::assertSame($userId->value(), BanUnbannedByTypecast::uncastValue(BanUnbannedBy::by($userId)));

        self::assertTrue(ReservedNicknameHolderTypecast::castDatabaseValue(null)->isUnassigned());
        self::assertSame($userId->value(), ReservedNicknameHolderTypecast::castDatabaseValue($userId->value())->value());
        self::assertNull(ReservedNicknameHolderTypecast::uncastValue(null));
        self::assertNull(ReservedNicknameHolderTypecast::uncastValue(ReservedNicknameHolder::unassigned()));
        self::assertSame(
            $userId->value(),
            ReservedNicknameHolderTypecast::uncastValue(ReservedNicknameHolder::assignedTo($userId)),
        );
    }

    public function testDateNullObjectTypecasts(): void
    {
        $immutable = new \DateTimeImmutable('2026-06-13 12:00:00');
        $mutable = new \DateTime('2026-06-14 12:00:00');

        self::assertFalse(UserDeletionTypecast::castDatabaseValue(null)->isDeleted());
        self::assertSame($immutable, UserDeletionTypecast::castDatabaseValue($immutable)->value());
        self::assertSame($mutable->format('c'), UserDeletionTypecast::castDatabaseValue($mutable)->value()?->format('c'));
        self::assertSame('2026-06-15T12:00:00+00:00', UserDeletionTypecast::castDatabaseValue('2026-06-15 12:00:00')->value()?->format('c'));
        self::assertNull(UserDeletionTypecast::uncastValue(null));
        self::assertNull(UserDeletionTypecast::uncastValue(UserDeletion::active()));
        self::assertSame($immutable, UserDeletionTypecast::uncastValue(UserDeletion::at($immutable)));

        self::assertTrue(BanExpirationTypecast::castDatabaseValue(null)->isPermanent());
        self::assertSame($immutable, BanExpirationTypecast::castDatabaseValue($immutable)->value());
        self::assertSame($mutable->format('c'), BanExpirationTypecast::castDatabaseValue($mutable)->value()?->format('c'));
        self::assertSame('2026-06-15T12:00:00+00:00', BanExpirationTypecast::castDatabaseValue('2026-06-15 12:00:00')->value()?->format('c'));
        self::assertNull(BanExpirationTypecast::uncastValue(null));
        self::assertNull(BanExpirationTypecast::uncastValue(BanExpiration::permanent()));
        self::assertSame($immutable, BanExpirationTypecast::uncastValue(BanExpiration::until($immutable)));

        self::assertFalse(BanUnbannedAtTypecast::castDatabaseValue(null)->isUnbanned());
        self::assertSame($immutable, BanUnbannedAtTypecast::castDatabaseValue($immutable)->value());
        self::assertSame($mutable->format('c'), BanUnbannedAtTypecast::castDatabaseValue($mutable)->value()?->format('c'));
        self::assertSame('2026-06-15T12:00:00+00:00', BanUnbannedAtTypecast::castDatabaseValue('2026-06-15 12:00:00')->value()?->format('c'));
        self::assertNull(BanUnbannedAtTypecast::uncastValue(null));
        self::assertNull(BanUnbannedAtTypecast::uncastValue(BanUnbannedAt::notUnbanned()));
        self::assertSame($immutable, BanUnbannedAtTypecast::uncastValue(BanUnbannedAt::at($immutable)));
    }
}
