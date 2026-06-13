<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\User\Repository;

use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\User\Domain\Entity\ReservedNickname;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\Entity\UserBan;
use App\Modules\User\Domain\Enum\UserStatus;
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
use App\Modules\User\Repository\ReservedNicknameRepository;
use App\Modules\User\Repository\UserBanRepository;
use App\Modules\User\Repository\UserRepository;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use Tests\DatabaseTestCase;

final class UserRepositoryTest extends DatabaseTestCase
{
    public function testStoresAndRestoresUserWithValueObjects(): void
    {
        $media = $this->createMedia();
        $user = $this->createUser();
        $user->changeSpiritualName(UserSpiritualName::fromString('Шанти'));
        $user->changeBio(UserBio::fromString('Описание'));
        $user->changeLocation(UserLocation::fromString('Москва'));
        $user->setAvatar(UserAvatar::pointingTo($media->id->value()));
        $user->confirmEmail();
        $user->markDeleted(new \DateTimeImmutable('2026-06-13 12:00:00'));

        $this->entityManager()->persist($media);
        $this->entityManager()->persist($user);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        $restoredUser = $this->userRepository()->findById($user->id);

        self::assertInstanceOf(User::class, $restoredUser);
        self::assertTrue($user->id->equals($restoredUser->id));
        self::assertSame('test@example.com', $restoredUser->email->value());
        self::assertSame('yoga.test', $restoredUser->nickname->value());
        self::assertSame('Шанти', $restoredUser->spiritualName->value());
        self::assertSame('Описание', $restoredUser->bio->value());
        self::assertSame('Москва', $restoredUser->location->value());
        self::assertSame($media->id->value(), $restoredUser->avatar->value());
        self::assertTrue($restoredUser->isDeleted());
        self::assertSame(UserStatus::Deleted, $restoredUser->status);
        self::assertSame(Locale::Ru, $restoredUser->locale);
        self::assertInstanceOf(User::class, $this->userRepository()->findByEmail(Email::fromString('TEST@example.com')));
        self::assertInstanceOf(User::class, $this->userRepository()->findByNickname(UserNickname::fromString('YOGA.TEST')));
        self::assertTrue($this->userRepository()->existsByEmail(Email::fromString('test@example.com')));
        self::assertTrue($this->userRepository()->existsByNickname(UserNickname::fromString('yoga.test')));
    }

    public function testStoresAndRestoresUserWithEmptyOptionalValues(): void
    {
        $user = $this->createUser(email: 'empty@example.com', nickname: 'empty.user');

        $this->entityManager()->persist($user);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        $restoredUser = $this->userRepository()->findById($user->id);

        self::assertInstanceOf(User::class, $restoredUser);
        self::assertTrue($restoredUser->spiritualName->isEmpty());
        self::assertTrue($restoredUser->bio->isEmpty());
        self::assertTrue($restoredUser->location->isEmpty());
        self::assertTrue($restoredUser->avatar->isEmpty());
        self::assertFalse($restoredUser->deletion->isDeleted());
    }

    public function testStoresAndFindsActiveUserBans(): void
    {
        $user = $this->createUser();
        $permanentBan = UserBan::create(
            userId: $user->id,
            bannedById: UserId::generate(),
            reason: BanReason::fromString('Навсегда'),
            expiration: BanExpiration::permanent(),
        );

        $this->entityManager()->persist($user);
        $this->entityManager()->persist($permanentBan);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        $restoredBan = $this->userBanRepository()->findById($permanentBan->id);
        $activeBan = $this->userBanRepository()->findActiveByUserId($user->id, new \DateTimeImmutable());

        self::assertInstanceOf(UserBan::class, $restoredBan);
        self::assertInstanceOf(UserBan::class, $activeBan);
        self::assertTrue($permanentBan->id->equals($activeBan->id));
    }

    public function testFindActiveUserBanIgnoresExpiredAndUnbannedRows(): void
    {
        $expiredUser = $this->createUser(email: 'expired@example.com', nickname: 'expired.user');
        $unbannedUser = $this->createUser(email: 'unbanned@example.com', nickname: 'unbanned.user');
        $activeTemporaryUser = $this->createUser(email: 'active@example.com', nickname: 'active.user');
        $expiredBan = UserBan::create(
            userId: $expiredUser->id,
            bannedById: UserId::generate(),
            reason: BanReason::fromString('Истёк'),
            expiration: BanExpiration::until(new \DateTimeImmutable('-1 hour')),
        );
        $unbannedBan = UserBan::create(
            userId: $unbannedUser->id,
            bannedById: UserId::generate(),
            reason: BanReason::fromString('Снят'),
            expiration: BanExpiration::permanent(),
        );
        $activeTemporaryBan = UserBan::create(
            userId: $activeTemporaryUser->id,
            bannedById: UserId::generate(),
            reason: BanReason::fromString('Активен'),
            expiration: BanExpiration::until(new \DateTimeImmutable('+1 hour')),
        );
        $unbannedBan->markUnbanned(
            unbannedBy: BanUnbannedBy::by(UserId::generate()),
            unbannedAt: BanUnbannedAt::at(new \DateTimeImmutable()),
            unbannedReason: BanUnbannedReason::of('Снят'),
        );

        $this->entityManager()->persist($expiredUser);
        $this->entityManager()->persist($unbannedUser);
        $this->entityManager()->persist($activeTemporaryUser);
        $this->entityManager()->persist($expiredBan);
        $this->entityManager()->persist($unbannedBan);
        $this->entityManager()->persist($activeTemporaryBan);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        self::assertNull($this->userBanRepository()->findActiveByUserId($expiredUser->id, new \DateTimeImmutable()));
        self::assertNull($this->userBanRepository()->findActiveByUserId($unbannedUser->id, new \DateTimeImmutable()));
        self::assertInstanceOf(
            UserBan::class,
            $this->userBanRepository()->findActiveByUserId($activeTemporaryUser->id, new \DateTimeImmutable()),
        );
    }

    public function testStoresReservedNicknameWithHolder(): void
    {
        $user = $this->createUser();
        $reservedNickname = ReservedNickname::create(UserNickname::fromString('reserved'));
        $reservedNickname->assignTo($user->id);

        $this->entityManager()->persist($user);
        $this->entityManager()->persist($reservedNickname);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        $restoredReservedNickname = $this->reservedNicknameRepository()
            ->findByNickname(UserNickname::fromString('reserved'));

        self::assertInstanceOf(ReservedNickname::class, $restoredReservedNickname);
        self::assertTrue($restoredReservedNickname->isAssignedTo($user->id));
        self::assertTrue($this->reservedNicknameRepository()->isReserved(UserNickname::fromString('reserved')));
    }

    public function testDuplicateEmailFails(): void
    {
        $this->entityManager()->persist($this->createUser());
        $this->entityManager()->persist($this->createUser(email: 'TEST@example.com', nickname: 'other.nick'));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    public function testDuplicateNicknameFails(): void
    {
        $this->entityManager()->persist($this->createUser());
        $this->entityManager()->persist($this->createUser(email: 'other@example.com', nickname: 'YOGA.TEST'));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    public function testDuplicateReservedNicknameFails(): void
    {
        $this->entityManager()->persist(ReservedNickname::create(UserNickname::fromString('reserved')));
        $this->entityManager()->persist(ReservedNickname::create(UserNickname::fromString('RESERVED')));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    private function createUser(
        string $email = 'test@example.com',
        string $nickname = 'yoga.test',
    ): User {
        return User::create(
            name: UserName::fromString('Йога Тест'),
            email: Email::fromString($email),
            nickname: UserNickname::fromString($nickname),
            locale: Locale::Ru,
        );
    }

    private function createMedia(): Media
    {
        $storageKey = MediaStorageKey::generate();

        return Media::create(
            storageKey: $storageKey,
            type: MediaType::Image,
            visibility: MediaVisibility::Private,
            path: MediaPath::originalUpload(storageKey: $storageKey, extension: 'jpg'),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(1024),
            uploadedById: UserId::generate(),
            expiration: MediaExpiration::temporaryUntil(new \DateTimeImmutable('+1 hour')),
        );
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    private function userRepository(): UserRepository
    {
        return $this->getContainer()->get(UserRepository::class);
    }

    private function userBanRepository(): UserBanRepository
    {
        return $this->getContainer()->get(UserBanRepository::class);
    }

    private function reservedNicknameRepository(): ReservedNicknameRepository
    {
        return $this->getContainer()->get(ReservedNicknameRepository::class);
    }
}
