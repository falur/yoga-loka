<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\User\Domain\Entity\UserBan;
use App\Modules\User\Domain\Repository\UserBanRepository;
use App\Modules\User\Domain\ValueObject\UserBanId;
use App\Modules\User\Infrastructure\Persistence\Cycle\Columns\UserBanColumns;
use App\Modules\User\Infrastructure\Persistence\Cycle\Entity\CycleUserBanEntity;
use App\Modules\User\Infrastructure\Persistence\Cycle\Mapper\UserBanMapper;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;
use Cycle\ORM\Select\QueryBuilder;

/**
 * @extends AbstractRepository<CycleUserBanEntity>
 */
final class CycleUserBanRepository extends AbstractRepository implements UserBanRepository
{
    /**
     * @param Select<CycleUserBanEntity> $select
     */
    public function __construct(
        Select $select,
        ORM $orm,
        string $role,
        private UserBanMapper $userBanMapper,
    ) {
        parent::__construct(select: $select, orm: $orm, role: $role);
    }

    #[\Override]
    public function findById(UserBanId $userBanId): UserBan|null
    {
        /** @var CycleUserBanEntity|null $cycleEntity */
        $cycleEntity = $this->findByPK($userBanId->value());

        return $cycleEntity === null ? null : $this->userBanMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function findActiveByUserId(UserId $userId, \DateTimeImmutable $now): UserBan|null
    {
        /** @var CycleUserBanEntity|null $cycleEntity */
        $cycleEntity = $this->select()
            ->where(UserBanColumns::USER_ID, $userId->value())
            ->where(UserBanColumns::UNBANNED_AT, null)
            ->where(static function (QueryBuilder $queryBuilder) use ($now): void {
                $queryBuilder
                    ->where(UserBanColumns::EXPIRES_AT, null)
                    ->orWhere(UserBanColumns::EXPIRES_AT, '>', $now);
            })
            ->fetchOne();

        return $cycleEntity === null ? null : $this->userBanMapper->toDomain($cycleEntity);
    }
}
