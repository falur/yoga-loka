<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\User\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\Enum\UserStatus;
use App\Modules\User\Domain\Enum\UserVerification;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserAvatar;
use App\Modules\User\Domain\ValueObject\UserBio;
use App\Modules\User\Domain\ValueObject\UserLocation;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Domain\ValueObject\UserSpiritualName;
use App\Modules\User\Infrastructure\Persistence\Cycle\Mapper\UserMapper;
use App\Shared\Domain\Enum\Locale;
use PHPUnit\Framework\TestCase;

/**
 * Переносит проверки удалённых typecast-классов User (UserSpiritualNameTypecast, UserBioTypecast,
 * UserLocationTypecast, UserAvatarTypecast, UserDeletionTypecast) на Mapper, куда переехала их
 * логика null-bridging между nullable-колонкой и value object с сентинелом.
 */
final class UserMapperTest extends TestCase
{
    public function testMapsUserWithAllOptionalValuesFilled(): void
    {
        $mapper = new UserMapper();
        $avatarMediaId = '01930000-0000-7000-8000-000000000001';
        $deletedAt = new \DateTimeImmutable('2026-06-13 12:00:00');

        $user = User::create(
            name: UserName::fromString('Йога Тест'),
            email: Email::fromString('test@example.com'),
            nickname: UserNickname::fromString('yoga.test'),
            locale: Locale::Ru,
        );
        $user->changeSpiritualName(UserSpiritualName::fromString('Шанти'));
        $user->changeBio(UserBio::fromString('Описание'));
        $user->changeLocation(UserLocation::fromString('Москва'));
        $user->setAvatar(UserAvatar::pointingTo($avatarMediaId));
        $user->verify();
        $user->markDeleted($deletedAt);

        $cycleEntity = $mapper->toCycleEntity($user);

        self::assertSame($user->id->value(), $cycleEntity->id);
        self::assertSame('Шанти', $cycleEntity->spiritualName);
        self::assertSame('Описание', $cycleEntity->bio);
        self::assertSame('Москва', $cycleEntity->location);
        self::assertSame($avatarMediaId, $cycleEntity->avatarMediaId);
        self::assertSame(UserVerification::Verified, $cycleEntity->verification);
        self::assertSame(UserStatus::Deleted, $cycleEntity->status);
        self::assertSame(Locale::Ru, $cycleEntity->locale);
        self::assertSame($deletedAt, $cycleEntity->deletedAt);

        $restoredUser = $mapper->toDomain($cycleEntity);

        self::assertTrue($user->id->equals($restoredUser->id));
        self::assertSame('Шанти', $restoredUser->spiritualName->value());
        self::assertSame('Описание', $restoredUser->bio->value());
        self::assertSame('Москва', $restoredUser->location->value());
        self::assertSame($avatarMediaId, $restoredUser->avatar->value());
        self::assertTrue($restoredUser->isDeleted());
        self::assertSame($deletedAt, $restoredUser->deletion->value());
    }

    public function testMapsUserWithEmptyOptionalValuesToNullColumns(): void
    {
        $mapper = new UserMapper();
        $user = User::create(
            name: UserName::fromString('Йога Тест'),
            email: Email::fromString('empty@example.com'),
            nickname: UserNickname::fromString('empty.user'),
            locale: Locale::Ru,
        );

        $cycleEntity = $mapper->toCycleEntity($user);

        self::assertNull($cycleEntity->spiritualName);
        self::assertNull($cycleEntity->bio);
        self::assertNull($cycleEntity->location);
        self::assertNull($cycleEntity->avatarMediaId);
        self::assertNull($cycleEntity->deletedAt);

        $restoredUser = $mapper->toDomain($cycleEntity);

        self::assertTrue($restoredUser->spiritualName->isEmpty());
        self::assertTrue($restoredUser->bio->isEmpty());
        self::assertTrue($restoredUser->location->isEmpty());
        self::assertTrue($restoredUser->avatar->isEmpty());
        self::assertFalse($restoredUser->deletion->isDeleted());
    }

    public function testToCycleEntityUpdatesGivenInstanceInPlace(): void
    {
        $mapper = new UserMapper();
        $user = User::create(
            name: UserName::fromString('Йога Тест'),
            email: Email::fromString('reuse@example.com'),
            nickname: UserNickname::fromString('reuse.user'),
            locale: Locale::Ru,
        );

        $cycleEntity = $mapper->toCycleEntity($user);
        $user->rename(UserName::fromString('Новое Имя'));
        $updatedCycleEntity = $mapper->toCycleEntity($user, $cycleEntity);

        self::assertSame($cycleEntity, $updatedCycleEntity);
        self::assertSame('Новое Имя', $updatedCycleEntity->name);
    }
}
