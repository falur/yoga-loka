<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\User\Domain\ValueObject;

use App\Modules\User\Domain\ValueObject\BanExpiration;
use App\Modules\User\Domain\ValueObject\BanReason;
use App\Modules\User\Domain\ValueObject\BanUnbannedAt;
use App\Modules\User\Domain\ValueObject\BanUnbannedBy;
use App\Modules\User\Domain\ValueObject\BanUnbannedReason;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\ReservedNicknameHolder;
use App\Modules\User\Domain\ValueObject\ReservedNicknameId;
use App\Modules\User\Domain\ValueObject\UserAvatar;
use App\Modules\User\Domain\ValueObject\UserBanId;
use App\Modules\User\Domain\ValueObject\UserBio;
use App\Modules\User\Domain\ValueObject\UserDeletion;
use App\Modules\User\Domain\ValueObject\UserLocation;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Domain\ValueObject\UserSpiritualName;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class UserValueObjectTest extends TestCase
{
    public function testEmailNormalizesAndRejectsInvalidValue(): void
    {
        $email = Email::fromString(' TEST@Example.COM ');

        self::assertSame('test@example.com', $email->value());
        self::assertTrue($email->equals(Email::fromString('test@example.com')));
        self::assertSame('test@example.com', (string) $email);
        self::assertSame('test@example.com', $email->jsonSerialize());

        $this->expectException(InvalidDomainValueException::class);

        Email::fromString('not-email');
    }

    public function testEmailRejectsTooLongValue(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        Email::fromString(\str_repeat('a', 245) . '@example.com');
    }

    public function testNicknameNormalizesAndRejectsInvalidValue(): void
    {
        $nickname = UserNickname::fromString(' Yoga.User ');

        self::assertSame('yoga.user', $nickname->value());
        self::assertTrue($nickname->equals(UserNickname::fromString('yoga.user')));
        self::assertSame('yoga.user', (string) $nickname);
        self::assertSame('yoga.user', $nickname->jsonSerialize());

        $this->expectException(InvalidDomainValueException::class);

        UserNickname::fromString('-bad');
    }

    public function testNicknameRejectsDoubleDot(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        UserNickname::fromString('bad..name');
    }

    public function testNameNormalizesUnicodeAndRejectsInvalidValue(): void
    {
        $name = UserName::fromString(" Йога   Тест ");

        self::assertSame('Йога Тест', $name->value());
        self::assertTrue($name->equals(UserName::fromString('Йога Тест')));
        self::assertSame('Йога Тест', (string) $name);
        self::assertSame('Йога Тест', $name->jsonSerialize());

        $this->expectException(InvalidDomainValueException::class);

        UserName::fromString('Name 1');
    }

    public function testNameRejectsInvalidLength(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        UserName::fromString(\str_repeat('я', 101));
    }

    public function testNameRejectsInvalidUtf8(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        UserName::fromString("\xC3\x28");
    }

    public function testSpiritualNameSupportsNoneAndValue(): void
    {
        $empty = UserSpiritualName::none();
        $spiritualName = UserSpiritualName::fromString(" Шанти   Деви ");

        self::assertNull($empty->value());
        self::assertTrue($empty->isEmpty());
        self::assertSame('', (string) $empty);
        self::assertNull($empty->jsonSerialize());
        self::assertSame('Шанти Деви', $spiritualName->value());
        self::assertTrue($spiritualName->equals(UserSpiritualName::fromString('Шанти Деви')));
        self::assertSame('Шанти Деви', (string) $spiritualName);
        self::assertSame('Шанти Деви', $spiritualName->jsonSerialize());

        $this->expectException(InvalidDomainValueException::class);

        UserSpiritualName::fromString('Name 1');
    }

    public function testSpiritualNameRejectsInvalidLength(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        UserSpiritualName::fromString('');
    }

    public function testSpiritualNameRejectsInvalidUtf8(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        UserSpiritualName::fromString("\xC3\x28");
    }

    public function testBioSupportsNoneAndRejectsInvalidValue(): void
    {
        $empty = UserBio::none();
        $bio = UserBio::fromString(" Практикую йогу\nкаждый день 🧘 ");

        self::assertNull($empty->value());
        self::assertTrue($empty->isEmpty());
        self::assertSame('', (string) $empty);
        self::assertNull($empty->jsonSerialize());
        self::assertSame("Практикую йогу\nкаждый день 🧘", $bio->value());
        self::assertTrue($bio->equals(UserBio::fromString("Практикую йогу\nкаждый день 🧘")));
        self::assertSame("Практикую йогу\nкаждый день 🧘", (string) $bio);
        self::assertSame("Практикую йогу\nкаждый день 🧘", $bio->jsonSerialize());

        $this->expectException(InvalidDomainValueException::class);

        UserBio::fromString("bad\x01");
    }

    public function testBioRejectsInvalidLength(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        UserBio::fromString('');
    }

    public function testBioRejectsUnicodeC1ControlCharacter(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        UserBio::fromString("bad\u{0085}");
    }

    public function testLocationSupportsNoneAndRejectsInvalidValue(): void
    {
        $empty = UserLocation::none();
        $location = UserLocation::fromString(' Москва, Центр-1 ');

        self::assertNull($empty->value());
        self::assertTrue($empty->isEmpty());
        self::assertSame('', (string) $empty);
        self::assertNull($empty->jsonSerialize());
        self::assertSame('Москва, Центр-1', $location->value());
        self::assertTrue($location->equals(UserLocation::fromString('Москва, Центр-1')));
        self::assertSame('Москва, Центр-1', (string) $location);
        self::assertSame('Москва, Центр-1', $location->jsonSerialize());

        $this->expectException(InvalidDomainValueException::class);

        UserLocation::fromString('Москва 🙂');
    }

    public function testLocationRejectsInvalidLength(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        UserLocation::fromString('');
    }

    public function testAvatarSupportsNoneAndMediaReference(): void
    {
        $mediaId = UserId::generate()->value();
        $empty = UserAvatar::none();
        $avatar = UserAvatar::pointingTo($mediaId);

        self::assertNull($empty->value());
        self::assertTrue($empty->isEmpty());
        self::assertSame('', (string) $empty);
        self::assertNull($empty->jsonSerialize());
        self::assertSame($mediaId, $avatar->value());
        self::assertTrue($avatar->equals(UserAvatar::pointingTo($mediaId)));
        self::assertSame($mediaId, (string) $avatar);
        self::assertSame($mediaId, $avatar->jsonSerialize());

        $this->expectException(InvalidDomainValueException::class);

        UserAvatar::pointingTo('not-uuid');
    }

    public function testDeletionSupportsActiveAndDeleted(): void
    {
        $now = new \DateTimeImmutable('2026-06-13 12:00:00.123456');
        $active = UserDeletion::active();
        $deleted = UserDeletion::at($now);

        self::assertNull($active->value());
        self::assertFalse($active->isDeleted());
        self::assertSame('', (string) $active);
        self::assertNull($active->jsonSerialize());
        self::assertSame($now, $deleted->value());
        self::assertTrue($deleted->isDeleted());
        self::assertTrue($deleted->equals(UserDeletion::at($now)));
        self::assertSame($now->format(\DateTimeInterface::ATOM), (string) $deleted);
        self::assertSame($now->format(\DateTimeInterface::ATOM), $deleted->jsonSerialize());
    }

    public function testBanReasonRejectsInvalidValue(): void
    {
        $reason = BanReason::fromString(' Нарушение правил ');

        self::assertSame('Нарушение правил', $reason->value());
        self::assertTrue($reason->equals(BanReason::fromString('Нарушение правил')));
        self::assertSame('Нарушение правил', (string) $reason);
        self::assertSame('Нарушение правил', $reason->jsonSerialize());

        $this->expectException(InvalidDomainValueException::class);

        BanReason::fromString('');
    }

    public function testBanExpirationSupportsPermanentAndTemporary(): void
    {
        $expiresAt = new \DateTimeImmutable('+1 day');
        $permanent = BanExpiration::permanent();
        $temporary = BanExpiration::until($expiresAt);

        self::assertNull($permanent->value());
        self::assertTrue($permanent->isPermanent());
        self::assertFalse($permanent->isExpired(new \DateTimeImmutable('+10 years')));
        self::assertSame('', (string) $permanent);
        self::assertNull($permanent->jsonSerialize());
        self::assertSame($expiresAt, $temporary->value());
        self::assertFalse($temporary->isPermanent());
        self::assertFalse($temporary->isExpired(new \DateTimeImmutable()));
        self::assertTrue($temporary->isExpired(new \DateTimeImmutable('+2 days')));
        self::assertTrue($temporary->equals(BanExpiration::until($expiresAt)));
        self::assertSame($expiresAt->format(\DateTimeInterface::ATOM), (string) $temporary);
        self::assertSame($expiresAt->format(\DateTimeInterface::ATOM), $temporary->jsonSerialize());
    }

    public function testUnbanValueObjectsSupportEmptyAndFilledValues(): void
    {
        $userId = UserId::generate();
        $unbannedAt = new \DateTimeImmutable('2026-06-13 12:00:00.123456');

        self::assertFalse(BanUnbannedAt::notUnbanned()->isUnbanned());
        self::assertNull(BanUnbannedAt::notUnbanned()->value());
        self::assertSame('', (string) BanUnbannedAt::notUnbanned());
        self::assertNull(BanUnbannedAt::notUnbanned()->jsonSerialize());
        self::assertTrue(BanUnbannedAt::at($unbannedAt)->isUnbanned());
        self::assertTrue(BanUnbannedAt::at($unbannedAt)->equals(BanUnbannedAt::at($unbannedAt)));
        self::assertSame($unbannedAt->format(\DateTimeInterface::ATOM), (string) BanUnbannedAt::at($unbannedAt));
        self::assertSame($unbannedAt->format(\DateTimeInterface::ATOM), BanUnbannedAt::at($unbannedAt)->jsonSerialize());

        self::assertTrue(BanUnbannedBy::none()->isEmpty());
        self::assertNull(BanUnbannedBy::none()->value());
        self::assertSame('', (string) BanUnbannedBy::none());
        self::assertNull(BanUnbannedBy::none()->jsonSerialize());
        self::assertSame($userId->value(), BanUnbannedBy::by($userId)->value());
        self::assertTrue(BanUnbannedBy::by($userId)->equals(BanUnbannedBy::by($userId)));
        self::assertSame($userId->value(), (string) BanUnbannedBy::by($userId));
        self::assertSame($userId->value(), BanUnbannedBy::by($userId)->jsonSerialize());

        self::assertTrue(BanUnbannedReason::none()->isEmpty());
        self::assertNull(BanUnbannedReason::none()->value());
        self::assertSame('', (string) BanUnbannedReason::none());
        self::assertNull(BanUnbannedReason::none()->jsonSerialize());
        self::assertSame('Ошибка исправлена', BanUnbannedReason::of(' Ошибка исправлена ')->value());
        self::assertTrue(BanUnbannedReason::of('Ошибка исправлена')->equals(BanUnbannedReason::of('Ошибка исправлена')));
        self::assertSame('Ошибка исправлена', (string) BanUnbannedReason::of('Ошибка исправлена'));
        self::assertSame('Ошибка исправлена', BanUnbannedReason::of('Ошибка исправлена')->jsonSerialize());
    }

    public function testUnbanReasonRejectsInvalidValue(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        BanUnbannedReason::of('');
    }

    public function testReservedNicknameHolderSupportsUnassignedAndAssigned(): void
    {
        $userId = UserId::generate();
        $holder = ReservedNicknameHolder::assignedTo($userId);

        self::assertTrue(ReservedNicknameHolder::unassigned()->isUnassigned());
        self::assertNull(ReservedNicknameHolder::unassigned()->value());
        self::assertSame('', (string) ReservedNicknameHolder::unassigned());
        self::assertNull(ReservedNicknameHolder::unassigned()->jsonSerialize());
        self::assertFalse(ReservedNicknameHolder::unassigned()->isAssignedTo($userId));
        self::assertSame($userId->value(), $holder->value());
        self::assertFalse($holder->isUnassigned());
        self::assertTrue($holder->isAssignedTo($userId));
        self::assertTrue($holder->equals(ReservedNicknameHolder::assignedTo($userId)));
        self::assertSame($userId->value(), (string) $holder);
        self::assertSame($userId->value(), $holder->jsonSerialize());
    }

    public function testUserSpecificIdsAreUuidV7(): void
    {
        self::assertSame(7, \Ramsey\Uuid\Uuid::fromString(UserBanId::generate()->value())->getVersion());
        self::assertSame(7, \Ramsey\Uuid\Uuid::fromString(ReservedNicknameId::generate()->value())->getVersion());
    }
}
