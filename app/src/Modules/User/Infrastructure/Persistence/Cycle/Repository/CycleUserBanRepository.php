<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\User\Domain\Entity\UserBan;
use App\Modules\User\Domain\Repository\UserBanRepository;
use App\Modules\User\Domain\ValueObject\UserBanId;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\ORM\Select\QueryBuilder;

/**
 * @extends AbstractRepository<UserBan>
 */
final class CycleUserBanRepository extends AbstractRepository implements UserBanRepository
{
    #[\Override]
    public function findById(UserBanId $userBanId): UserBan|null
    {
        return $this->findByPK($userBanId->value());
    }

    #[\Override]
    public function findActiveByUserId(UserId $userId, \DateTimeImmutable $now): UserBan|null
    {
        return $this->select()
            ->where('user_id', $userId->value())
            ->where('unbanned_at', null)
            ->where(static function (QueryBuilder $queryBuilder) use ($now): void {
                $queryBuilder
                    ->where('expires_at', null)
                    ->orWhere('expires_at', '>', $now);
            })
            ->fetchOne();
    }
}
