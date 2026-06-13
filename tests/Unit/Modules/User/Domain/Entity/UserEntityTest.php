<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\User\Domain\Entity;

use App\Modules\User\Domain\Entity\ReservedNickname;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\Entity\UserBan;
use App\Modules\User\Domain\Enum\UserStatus;
use App\Modules\User\Domain\Enum\UserVerification;
use App\Modules\User\Domain\ValueObject\BanExpiration;
use App\Modules\User\Domain\ValueObject\BanReason;
use App\Modules\User\Domain\ValueObject\BanUnbannedAt;
use App\Modules\User\Domain\ValueObject\BanUnbannedBy;
use App\Modules\User\Domain\ValueObject\BanUnbannedReason;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserAvatar;
use App\Modules\User\Domain\ValueObject\UserBio;
use App\Modules\User\Domain\ValueObject\UserLocation;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Domain\ValueObject\UserSpiritualName;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class UserEntityTest extends TestCase
{
    public function testCreatesUserWithDefaults(): void
    {
        $user = $this->createUser();

        self::assertSame(UserStatus::WaitingEmailConfirmation, $user->status);
        self::assertSame(UserVerification::Unverified, $user->verification);
        self::assertSame(Locale::Ru, $user->locale);
        self::assertTrue($user->spiritualName->isEmpty());
        self::assertTrue($user->bio->isEmpty());
        self::assertTrue($user->location->isEmpty());
        self::assertTrue($user->avatar->isEmpty());
        self::assertFalse($user->deletion->isDeleted());
        self::assertFalse($user->isActive());
        self::assertFalse($user->isVerified());
        self::assertFalse($user->isBanned());
        self::assertFalse($user->isDeleted());
    }

    public function testChangesUserState(): void
    {
        $user = $this->createUser();
        $avatarMediaId = UserId::generate()->value();

        $user->confirmEmail();
        self::assertTrue($user->isActive());

        $user->rename(UserName::fromString('Новое Имя'));
        $user->changeSpiritualName(UserSpiritualName::fromString('Шанти'));
        $user->changeBio(UserBio::fromString('Описание'));
        $user->changeLocation(UserLocation::fromString('Москва'));
        $user->changeLocale(Locale::En);
        $user->changeNickname(UserNickname::fromString('new.name'));
        $user->setAvatar(UserAvatar::pointingTo($avatarMediaId));
        $user->verify();

        self::assertSame('Новое Имя', $user->name->value());
        self::assertSame('Шанти', $user->spiritualName->value());
        self::assertSame('Описание', $user->bio->value());
        self::assertSame('Москва', $user->location->value());
        self::assertSame(Locale::En, $user->locale);
        self::assertSame('new.name', $user->nickname->value());
        self::assertSame($avatarMediaId, $user->avatar->value());
        self::assertTrue($user->isVerified());

        $user->removeAvatar();
        $user->unverify();
        self::assertTrue($user->avatar->isEmpty());
        self::assertFalse($user->isVerified());
    }

    public function testEmailChangeBanUnbanAndDeletion(): void
    {
        $user = $this->createUser();
        $deletedAt = new \DateTimeImmutable('2026-06-13 12:00:00');

        $user->confirmEmail();
        $user->changeEmail(Email::fromString('changed@example.com'));
        self::assertSame('changed@example.com', $user->email->value());
        self::assertSame(UserStatus::WaitingEmailConfirmation, $user->status);

        $user->ban();
        self::assertTrue($user->isBanned());

        $user->unban();
        self::assertTrue($user->isActive());

        $user->markDeleted($deletedAt);
        self::assertTrue($user->isDeleted());
        self::assertSame($deletedAt, $user->deletion->value());
        self::assertSame($deletedAt, $user->updatedAt);
    }

    public function testCreatesAndUnbansUserBan(): void
    {
        $userId = UserId::generate();
        $moderatorId = UserId::generate();
        $unbannedAt = new \DateTimeImmutable('2026-06-13 12:00:00');
        $userBan = UserBan::create(
            userId: $userId,
            bannedById: $moderatorId,
            reason: BanReason::fromString('Причина'),
            expiration: BanExpiration::permanent(),
        );

        self::assertTrue($userBan->userId->equals($userId));
        self::assertTrue($userBan->bannedById->equals($moderatorId));
        self::assertSame('Причина', $userBan->reason->value());
        self::assertTrue($userBan->expiration->isPermanent());
        self::assertFalse($userBan->unbannedAt->isUnbanned());
        self::assertTrue($userBan->isActive(new \DateTimeImmutable('+10 years')));

        $userBan->markUnbanned(
            unbannedBy: BanUnbannedBy::by($moderatorId),
            unbannedAt: BanUnbannedAt::at($unbannedAt),
            unbannedReason: BanUnbannedReason::of('Исправлено'),
        );

        self::assertTrue($userBan->unbannedAt->isUnbanned());
        self::assertSame($moderatorId->value(), $userBan->unbannedBy->value());
        self::assertSame('Исправлено', $userBan->unbannedReason->value());
        self::assertFalse($userBan->isActive(new \DateTimeImmutable()));
    }

    public function testUserBanActiveDependsOnExpiration(): void
    {
        $activeTemporaryBan = UserBan::create(
            userId: UserId::generate(),
            bannedById: UserId::generate(),
            reason: BanReason::fromString('Причина'),
            expiration: BanExpiration::until(new \DateTimeImmutable('+1 day')),
        );
        $expiredTemporaryBan = UserBan::create(
            userId: UserId::generate(),
            bannedById: UserId::generate(),
            reason: BanReason::fromString('Причина'),
            expiration: BanExpiration::until(new \DateTimeImmutable('-1 day')),
        );

        self::assertTrue($activeTemporaryBan->isActive(new \DateTimeImmutable()));
        self::assertFalse($expiredTemporaryBan->isActive(new \DateTimeImmutable()));
    }

    public function testReservedNicknameCanBeAssigned(): void
    {
        $userId = UserId::generate();
        $reservedNickname = ReservedNickname::create(UserNickname::fromString('reserved'));

        self::assertSame('reserved', $reservedNickname->nickname->value());
        self::assertTrue($reservedNickname->holder->isUnassigned());
        self::assertFalse($reservedNickname->isAssignedTo($userId));

        $reservedNickname->assignTo($userId);

        self::assertTrue($reservedNickname->isAssignedTo($userId));
    }

    private function createUser(): User
    {
        return User::create(
            name: UserName::fromString('Йога Тест'),
            email: Email::fromString('test@example.com'),
            nickname: UserNickname::fromString('yoga.test'),
            locale: Locale::Ru,
        );
    }
}
