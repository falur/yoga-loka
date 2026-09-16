<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Auth\Domain\Entity\LoginCode;
use App\Modules\Auth\Domain\Repository\LoginCodeRepository;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<LoginCode>
 */
final class CycleLoginCodeRepository extends AbstractRepository implements LoginCodeRepository
{
    /**
     * @param Select<LoginCode> $select
     */
    public function __construct(
        Select $select,
        ORM $orm,
        string $role,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct(select: $select, orm: $orm, role: $role);
    }

    /**
     * forUpdate применяется прямо в цепочке select(): через Repository::forUpdate() флаг
     * блокировки теряется при clone.
     */
    #[\Override]
    public function findActiveByEmailForUpdate(EmailAddress $email): LoginCode|null
    {
        return $this->select()
            ->where('email', $email->value())
            ->where('consumed_at', null)
            ->orderBy(['id' => 'DESC'])
            ->forUpdate()
            ->fetchOne();
    }

    #[\Override]
    public function findActiveByEmail(EmailAddress $email): LoginCode|null
    {
        return $this->select()
            ->where('email', $email->value())
            ->where('consumed_at', null)
            ->orderBy(['id' => 'DESC'])
            ->fetchOne();
    }

    #[\Override]
    public function add(LoginCode $loginCode): void
    {
        $this->entityManager->persist($loginCode);
    }

    #[\Override]
    public function save(LoginCode $loginCode): void
    {
        $this->entityManager
            ->persist($loginCode)
            ->run();
    }
}
