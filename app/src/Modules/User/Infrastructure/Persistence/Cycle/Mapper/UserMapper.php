<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserAvatar;
use App\Modules\User\Domain\ValueObject\UserBio;
use App\Modules\User\Domain\ValueObject\UserDeletion;
use App\Modules\User\Domain\ValueObject\UserLocation;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Domain\ValueObject\UserSpiritualName;
use App\Modules\User\Infrastructure\Persistence\Cycle\Entity\CycleUserEntity;
use App\Shared\Domain\ValueObject\UserId;

final readonly class UserMapper
{
    public function toDomain(CycleUserEntity $cycleEntity): User
    {
        return User::restore(
            id: UserId::fromString($cycleEntity->id),
            name: UserName::fromString($cycleEntity->name),
            spiritualName: $cycleEntity->spiritualName === null
                ? UserSpiritualName::none()
                : UserSpiritualName::fromString($cycleEntity->spiritualName),
            bio: $cycleEntity->bio === null ? UserBio::none() : UserBio::fromString($cycleEntity->bio),
            location: $cycleEntity->location === null
                ? UserLocation::none()
                : UserLocation::fromString($cycleEntity->location),
            email: Email::fromString($cycleEntity->email),
            nickname: UserNickname::fromString($cycleEntity->nickname),
            avatar: $cycleEntity->avatarMediaId === null
                ? UserAvatar::none()
                : UserAvatar::pointingTo($cycleEntity->avatarMediaId),
            verification: $cycleEntity->verification,
            status: $cycleEntity->status,
            locale: $cycleEntity->locale,
            deletion: $cycleEntity->deletedAt === null
                ? UserDeletion::active()
                : UserDeletion::at($cycleEntity->deletedAt),
            createdAt: $cycleEntity->createdAt,
            updatedAt: $cycleEntity->updatedAt,
        );
    }

    public function toCycleEntity(
        User $user,
        CycleUserEntity|null $cycleEntity = null,
    ): CycleUserEntity {
        $cycleEntity ??= new CycleUserEntity();
        $cycleEntity->id = $user->id->value();
        $cycleEntity->name = $user->name->value();
        $cycleEntity->spiritualName = $user->spiritualName->value();
        $cycleEntity->bio = $user->bio->value();
        $cycleEntity->location = $user->location->value();
        $cycleEntity->email = $user->email->value();
        $cycleEntity->nickname = $user->nickname->value();
        $cycleEntity->avatarMediaId = $user->avatar->value();
        $cycleEntity->verification = $user->verification;
        $cycleEntity->status = $user->status;
        $cycleEntity->locale = $user->locale;
        $cycleEntity->deletedAt = $user->deletion->value();
        $cycleEntity->createdAt = $user->createdAt;
        $cycleEntity->updatedAt = $user->updatedAt;

        return $cycleEntity;
    }
}
