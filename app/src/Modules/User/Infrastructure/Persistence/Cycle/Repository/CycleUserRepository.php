<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\User\Domain\Collection\UserCollection;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\Repository\UserRepository;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\Database\Injection\Parameter;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<User>
 */
final class CycleUserRepository extends AbstractRepository implements UserRepository
{
    /**
     * @param Select<User> $select
     */
    public function __construct(
        Select $select,
        ORM $orm,
        string $role,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct(select: $select, orm: $orm, role: $role);
    }

    #[\Override]
    public function findById(UserId $userId): User|null
    {
        return $this->findByPK($userId->value());
    }

    #[\Override]
    public function findByIds(UserId ...$userIds): UserCollection
    {
        if ($userIds === []) {
            return new UserCollection();
        }

        return new UserCollection(
            $this->select()
                ->where('id', 'in', new Parameter(\array_map(
                    static fn(UserId $userId): string => $userId->value(),
                    $userIds,
                )))
                ->fetchAll(),
        );
    }

    #[\Override]
    public function countByIds(UserId ...$userIds): int
    {
        if ($userIds === []) {
            return 0;
        }

        return $this->select()
            ->where('id', 'in', new Parameter(\array_map(
                static fn(UserId $userId): string => $userId->value(),
                $userIds,
            )))
            ->count();
    }

    #[\Override]
    public function findByEmail(Email $email): User|null
    {
        return $this->findOne(['email' => $email->value()]);
    }

    #[\Override]
    public function findByNickname(UserNickname $nickname): User|null
    {
        return $this->findOne(['nickname' => $nickname->value()]);
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
        $this->entityManager
            ->persist($user)
            ->run();
    }
}
