<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Entity;

use App\Modules\User\Domain\ValueObject\BanExpiration;
use App\Modules\User\Domain\ValueObject\BanReason;
use App\Modules\User\Domain\ValueObject\BanUnbannedAt;
use App\Modules\User\Domain\ValueObject\BanUnbannedBy;
use App\Modules\User\Domain\ValueObject\BanUnbannedReason;
use App\Modules\User\Domain\ValueObject\UserBanId;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;

final class UserBan
{
    use HasTimestamps;

    public private(set) UserBanId $id;

    public private(set) UserId $userId;

    public private(set) UserId $bannedById;

    public private(set) BanReason $reason;

    public private(set) BanExpiration $expiration;

    public private(set) BanUnbannedAt $unbannedAt;

    public private(set) BanUnbannedBy $unbannedBy;

    public private(set) BanUnbannedReason $unbannedReason;

    public static function create(
        UserId $userId,
        UserId $bannedById,
        BanReason $reason,
        BanExpiration $expiration,
    ): self {
        $userBan = new self();
        $userBan->id = UserBanId::generate();
        $userBan->userId = $userId;
        $userBan->bannedById = $bannedById;
        $userBan->reason = $reason;
        $userBan->expiration = $expiration;
        $userBan->unbannedAt = BanUnbannedAt::notUnbanned();
        $userBan->unbannedBy = BanUnbannedBy::none();
        $userBan->unbannedReason = BanUnbannedReason::none();
        $userBan->initializeTimestamps();

        return $userBan;
    }

    public static function restore(
        UserBanId $id,
        UserId $userId,
        UserId $bannedById,
        BanReason $reason,
        BanExpiration $expiration,
        BanUnbannedAt $unbannedAt,
        BanUnbannedBy $unbannedBy,
        BanUnbannedReason $unbannedReason,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $userBan = new self();
        $userBan->id = $id;
        $userBan->userId = $userId;
        $userBan->bannedById = $bannedById;
        $userBan->reason = $reason;
        $userBan->expiration = $expiration;
        $userBan->unbannedAt = $unbannedAt;
        $userBan->unbannedBy = $unbannedBy;
        $userBan->unbannedReason = $unbannedReason;
        $userBan->createdAt = $createdAt;
        $userBan->updatedAt = $updatedAt;

        return $userBan;
    }

    public function markUnbanned(
        BanUnbannedBy $unbannedBy,
        BanUnbannedAt $unbannedAt,
        BanUnbannedReason $unbannedReason,
    ): void {
        $this->unbannedBy = $unbannedBy;
        $this->unbannedAt = $unbannedAt;
        $this->unbannedReason = $unbannedReason;
        $this->touch();
    }

    public function isActive(\DateTimeImmutable $now): bool
    {
        return !$this->unbannedAt->isUnbanned() && !$this->expiration->isExpired($now);
    }
}
