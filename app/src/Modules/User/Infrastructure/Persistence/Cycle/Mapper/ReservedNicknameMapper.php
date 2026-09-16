<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\User\Domain\Entity\ReservedNickname;
use App\Modules\User\Domain\ValueObject\ReservedNicknameHolder;
use App\Modules\User\Domain\ValueObject\ReservedNicknameId;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Infrastructure\Persistence\Cycle\Entity\CycleReservedNicknameEntity;
use App\Shared\Domain\ValueObject\UserId;

final readonly class ReservedNicknameMapper
{
    public function toDomain(CycleReservedNicknameEntity $cycleEntity): ReservedNickname
    {
        return ReservedNickname::restore(
            id: ReservedNicknameId::fromString($cycleEntity->id),
            nickname: UserNickname::fromString($cycleEntity->nickname),
            holder: $cycleEntity->assignedUserId === null
                ? ReservedNicknameHolder::unassigned()
                : ReservedNicknameHolder::assignedTo(UserId::fromString($cycleEntity->assignedUserId)),
            createdAt: $cycleEntity->createdAt,
            updatedAt: $cycleEntity->updatedAt,
        );
    }

    public function toCycleEntity(
        ReservedNickname $reservedNickname,
        CycleReservedNicknameEntity|null $cycleEntity = null,
    ): CycleReservedNicknameEntity {
        $cycleEntity ??= new CycleReservedNicknameEntity();
        $cycleEntity->id = $reservedNickname->id->value();
        $cycleEntity->nickname = $reservedNickname->nickname->value();
        $cycleEntity->assignedUserId = $reservedNickname->holder->value();
        $cycleEntity->createdAt = $reservedNickname->createdAt;
        $cycleEntity->updatedAt = $reservedNickname->updatedAt;

        return $cycleEntity;
    }
}
