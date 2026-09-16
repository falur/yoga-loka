<?php

declare(strict_types=1);

namespace App\Modules\Access\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Access\Domain\Collection\UserRoleCollection;
use App\Modules\Access\Domain\Repository\UserRoleRepository;
use App\Modules\Access\Domain\ValueObject\RoleId;
use App\Modules\Access\Infrastructure\Persistence\Cycle\Columns\UserRoleColumns;
use App\Modules\Access\Infrastructure\Persistence\Cycle\Entity\CycleUserRoleEntity;
use App\Modules\Access\Infrastructure\Persistence\Cycle\Mapper\UserRoleMapper;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<CycleUserRoleEntity>
 */
final class CycleUserRoleRepository extends AbstractRepository implements UserRoleRepository
{
    /**
     * @param Select<CycleUserRoleEntity> $select
     */
    public function __construct(
        Select $select,
        ORM $orm,
        string $role,
        private UserRoleMapper $userRoleMapper,
    ) {
        parent::__construct(select: $select, orm: $orm, role: $role);
    }

    #[\Override]
    public function findByUserId(UserId $userId): UserRoleCollection
    {
        $userRoleCollection = new UserRoleCollection();

        /** @var iterable<CycleUserRoleEntity> $cycleEntities */
        $cycleEntities = $this->select()
            ->where(UserRoleColumns::USER_ID, $userId->value())
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $userRoleCollection->push($this->userRoleMapper->toDomain($cycleEntity));
        }

        return $userRoleCollection;
    }

    #[\Override]
    public function exists(UserId $userId, RoleId $roleId): bool
    {
        return $this->findOne([
            UserRoleColumns::USER_ID => $userId->value(),
            UserRoleColumns::ROLE_ID => $roleId->value(),
        ]) !== null;
    }
}
