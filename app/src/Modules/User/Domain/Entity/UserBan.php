<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Entity;

use App\Modules\User\Domain\ValueObject\BanExpiration;
use App\Modules\User\Domain\ValueObject\BanReason;
use App\Modules\User\Domain\ValueObject\BanUnbannedAt;
use App\Modules\User\Domain\ValueObject\BanUnbannedBy;
use App\Modules\User\Domain\ValueObject\BanUnbannedReason;
use App\Modules\User\Domain\ValueObject\UserBanId;
use App\Modules\User\Infrastructure\Persistence\Cycle\Typecast\BanExpirationTypecast;
use App\Modules\User\Infrastructure\Persistence\Cycle\Typecast\BanUnbannedAtTypecast;
use App\Modules\User\Infrastructure\Persistence\Cycle\Typecast\BanUnbannedByTypecast;
use App\Modules\User\Infrastructure\Persistence\Cycle\Typecast\BanUnbannedReasonTypecast;
use App\Modules\User\Repository\UserBanRepository;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'user_ban',
    table: 'user_bans',
    repository: UserBanRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class UserBan
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: UserBanId::class)]
    public private(set) UserBanId $id;

    #[Column(type: 'uuid', name: 'user_id', typecast: UserId::class)]
    public private(set) UserId $userId;

    #[Column(type: 'uuid', name: 'banned_by_id', typecast: UserId::class)]
    public private(set) UserId $bannedById;

    #[Column(type: 'string(500)', typecast: BanReason::class)]
    public private(set) BanReason $reason;

    #[Column(type: 'datetime', name: 'expires_at', nullable: true, typecast: BanExpirationTypecast::class)]
    public private(set) BanExpiration $expiration;

    #[Column(type: 'datetime', name: 'unbanned_at', nullable: true, typecast: BanUnbannedAtTypecast::class)]
    public private(set) BanUnbannedAt $unbannedAt;

    #[Column(type: 'uuid', name: 'unbanned_by_id', nullable: true, typecast: BanUnbannedByTypecast::class)]
    public private(set) BanUnbannedBy $unbannedBy;

    #[Column(type: 'string(500)', name: 'unbanned_reason', nullable: true, typecast: BanUnbannedReasonTypecast::class)]
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
