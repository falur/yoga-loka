<?php

declare(strict_types=1);

namespace App\Modules\User\Repository;

use App\Modules\User\Domain\Entity\UserBan;
use App\Modules\User\Domain\ValueObject\UserBanId;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\Select\QueryBuilder;
use Cycle\ORM\Select\Repository;

/**
 * @extends Repository<UserBan>
 */
final class UserBanRepository extends Repository
{
    public function findById(UserBanId $userBanId): UserBan|null
    {
        return $this->findByPK($userBanId->value());
    }

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
