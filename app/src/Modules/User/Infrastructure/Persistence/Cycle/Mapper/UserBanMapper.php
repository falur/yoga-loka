<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\User\Domain\Entity\UserBan;
use App\Modules\User\Domain\ValueObject\BanExpiration;
use App\Modules\User\Domain\ValueObject\BanReason;
use App\Modules\User\Domain\ValueObject\BanUnbannedAt;
use App\Modules\User\Domain\ValueObject\BanUnbannedBy;
use App\Modules\User\Domain\ValueObject\BanUnbannedReason;
use App\Modules\User\Domain\ValueObject\UserBanId;
use App\Modules\User\Infrastructure\Persistence\Cycle\Entity\CycleUserBanEntity;
use App\Shared\Domain\ValueObject\UserId;

final readonly class UserBanMapper
{
    public function toDomain(CycleUserBanEntity $cycleEntity): UserBan
    {
        return UserBan::restore(
            id: UserBanId::fromString($cycleEntity->id),
            userId: UserId::fromString($cycleEntity->userId),
            bannedById: UserId::fromString($cycleEntity->bannedById),
            reason: BanReason::fromString($cycleEntity->reason),
            expiration: $cycleEntity->expiresAt === null
                ? BanExpiration::permanent()
                : BanExpiration::until($cycleEntity->expiresAt),
            unbannedAt: $cycleEntity->unbannedAt === null
                ? BanUnbannedAt::notUnbanned()
                : BanUnbannedAt::at($cycleEntity->unbannedAt),
            unbannedBy: $cycleEntity->unbannedById === null
                ? BanUnbannedBy::none()
                : BanUnbannedBy::by(UserId::fromString($cycleEntity->unbannedById)),
            unbannedReason: $cycleEntity->unbannedReason === null
                ? BanUnbannedReason::none()
                : BanUnbannedReason::of($cycleEntity->unbannedReason),
            createdAt: $cycleEntity->createdAt,
            updatedAt: $cycleEntity->updatedAt,
        );
    }

    public function toCycleEntity(
        UserBan $userBan,
        CycleUserBanEntity|null $cycleEntity = null,
    ): CycleUserBanEntity {
        $cycleEntity ??= new CycleUserBanEntity();
        $cycleEntity->id = $userBan->id->value();
        $cycleEntity->userId = $userBan->userId->value();
        $cycleEntity->bannedById = $userBan->bannedById->value();
        $cycleEntity->reason = $userBan->reason->value();
        $cycleEntity->expiresAt = $userBan->expiration->value();
        $cycleEntity->unbannedAt = $userBan->unbannedAt->value();
        $cycleEntity->unbannedById = $userBan->unbannedBy->value();
        $cycleEntity->unbannedReason = $userBan->unbannedReason->value();
        $cycleEntity->createdAt = $userBan->createdAt;
        $cycleEntity->updatedAt = $userBan->updatedAt;

        return $cycleEntity;
    }
}
