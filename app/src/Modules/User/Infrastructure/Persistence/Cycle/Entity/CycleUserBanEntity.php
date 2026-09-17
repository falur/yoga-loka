<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\User\Infrastructure\Persistence\Cycle\Columns\UserBanColumns;
use App\Modules\User\Infrastructure\Persistence\Cycle\Repository\CycleUserBanRepository;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'user_ban',
    table: UserBanColumns::TABLE,
    repository: CycleUserBanRepository::class,
    typecast: [Typecast::class],
)]
final class CycleUserBanEntity
{
    use HasTimestamps;

    #[Column(type: 'uuid', name: UserBanColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'uuid', name: UserBanColumns::USER_ID)]
    public string $userId;

    #[Column(type: 'uuid', name: UserBanColumns::BANNED_BY_ID)]
    public string $bannedById;

    #[Column(type: 'string(500)', name: UserBanColumns::REASON)]
    public string $reason;

    #[Column(type: 'datetime', name: UserBanColumns::EXPIRES_AT, nullable: true, typecast: 'datetime')]
    public \DateTimeImmutable|null $expiresAt;

    #[Column(type: 'datetime', name: UserBanColumns::UNBANNED_AT, nullable: true, typecast: 'datetime')]
    public \DateTimeImmutable|null $unbannedAt;

    #[Column(type: 'uuid', name: UserBanColumns::UNBANNED_BY_ID, nullable: true)]
    public string|null $unbannedById;

    #[Column(type: 'string(500)', name: UserBanColumns::UNBANNED_REASON, nullable: true)]
    public string|null $unbannedReason;
}
