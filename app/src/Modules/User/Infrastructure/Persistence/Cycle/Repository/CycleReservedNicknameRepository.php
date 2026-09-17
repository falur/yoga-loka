<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\User\Domain\Entity\ReservedNickname;
use App\Modules\User\Domain\Repository\ReservedNicknameRepository;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Infrastructure\Persistence\Cycle\Columns\ReservedNicknameColumns;
use App\Modules\User\Infrastructure\Persistence\Cycle\Entity\CycleReservedNicknameEntity;
use App\Modules\User\Infrastructure\Persistence\Cycle\Mapper\ReservedNicknameMapper;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<CycleReservedNicknameEntity>
 */
final class CycleReservedNicknameRepository extends AbstractRepository implements ReservedNicknameRepository
{
    /**
     * @param Select<CycleReservedNicknameEntity> $select
     */
    public function __construct(
        Select $select,
        ORM $orm,
        string $role,
        private ReservedNicknameMapper $reservedNicknameMapper,
    ) {
        parent::__construct(select: $select, orm: $orm, role: $role);
    }

    #[\Override]
    public function findByNickname(UserNickname $nickname): ReservedNickname|null
    {
        /** @var CycleReservedNicknameEntity|null $cycleEntity */
        $cycleEntity = $this->findOne([ReservedNicknameColumns::NICKNAME => $nickname->value()]);

        return $cycleEntity === null ? null : $this->reservedNicknameMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function isReserved(UserNickname $nickname): bool
    {
        return $this->findByNickname($nickname) !== null;
    }
}
