<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Entity;

use App\Modules\User\Domain\ValueObject\ReservedNicknameHolder;
use App\Modules\User\Domain\ValueObject\ReservedNicknameId;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;

final class ReservedNickname
{
    use HasTimestamps;

    public private(set) ReservedNicknameId $id;

    public private(set) UserNickname $nickname;

    public private(set) ReservedNicknameHolder $holder;

    public static function create(UserNickname $nickname): self
    {
        $reservedNickname = new self();
        $reservedNickname->id = ReservedNicknameId::generate();
        $reservedNickname->nickname = $nickname;
        $reservedNickname->holder = ReservedNicknameHolder::unassigned();
        $reservedNickname->initializeTimestamps();

        return $reservedNickname;
    }

    public static function restore(
        ReservedNicknameId $id,
        UserNickname $nickname,
        ReservedNicknameHolder $holder,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $reservedNickname = new self();
        $reservedNickname->id = $id;
        $reservedNickname->nickname = $nickname;
        $reservedNickname->holder = $holder;
        $reservedNickname->createdAt = $createdAt;
        $reservedNickname->updatedAt = $updatedAt;

        return $reservedNickname;
    }

    public function assignTo(UserId $userId): void
    {
        $this->holder = ReservedNicknameHolder::assignedTo($userId);
        $this->touch();
    }

    public function isAssignedTo(UserId $userId): bool
    {
        return $this->holder->isAssignedTo($userId);
    }
}
