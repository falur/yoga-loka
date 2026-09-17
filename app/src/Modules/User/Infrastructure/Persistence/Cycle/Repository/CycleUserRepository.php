<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\User\Domain\Collection\UserCollection;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\Repository\UserRepository;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Infrastructure\Persistence\Cycle\Columns\UserColumns;
use App\Modules\User\Infrastructure\Persistence\Cycle\Entity\CycleUserEntity;
use App\Modules\User\Infrastructure\Persistence\Cycle\Mapper\UserMapper;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\Database\Injection\Parameter;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<CycleUserEntity>
 */
final class CycleUserRepository extends AbstractRepository implements UserRepository
{
    /**
     * @param Select<CycleUserEntity> $select
     */
    public function __construct(
        Select $select,
        ORM $orm,
        string $role,
        private UserMapper $userMapper,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct(select: $select, orm: $orm, role: $role);
    }

    #[\Override]
    public function findById(UserId $userId): User|null
    {
        /** @var CycleUserEntity|null $cycleEntity */
        $cycleEntity = $this->findByPK($userId->value());

        return $cycleEntity === null ? null : $this->userMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function findByIds(UserId ...$userIds): UserCollection
    {
        if ($userIds === []) {
            return new UserCollection();
        }

        $userCollection = new UserCollection();

        /** @var iterable<CycleUserEntity> $cycleEntities */
        $cycleEntities = $this->select()
            ->where(UserColumns::ID, 'in', new Parameter(\array_map(
                static fn(UserId $userId): string => $userId->value(),
                $userIds,
            )))
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $userCollection->push($this->userMapper->toDomain($cycleEntity));
        }

        return $userCollection;
    }

    #[\Override]
    public function countByIds(UserId ...$userIds): int
    {
        if ($userIds === []) {
            return 0;
        }

        return $this->select()
            ->where(UserColumns::ID, 'in', new Parameter(\array_map(
                static fn(UserId $userId): string => $userId->value(),
                $userIds,
            )))
            ->count();
    }

    #[\Override]
    public function findByEmail(Email $email): User|null
    {
        /** @var CycleUserEntity|null $cycleEntity */
        $cycleEntity = $this->findOne([UserColumns::EMAIL => $email->value()]);

        return $cycleEntity === null ? null : $this->userMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function findByNickname(UserNickname $nickname): User|null
    {
        /** @var CycleUserEntity|null $cycleEntity */
        $cycleEntity = $this->findOne([UserColumns::NICKNAME => $nickname->value()]);

        return $cycleEntity === null ? null : $this->userMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function existsByEmail(Email $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    #[\Override]
    public function existsByNickname(UserNickname $nickname): bool
    {
        return $this->findByNickname($nickname) !== null;
    }

    #[\Override]
    public function save(User $user): void
    {
        /** @var CycleUserEntity|null $cycleEntity */
        $cycleEntity = $this->findOne([UserColumns::ID => $user->id->value()]);

        $this->entityManager
            ->persist($this->userMapper->toCycleEntity(
                user: $user,
                cycleEntity: $cycleEntity,
            ))
            ->run();
    }
}
