<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Auth\Domain\Entity\LoginCode;
use App\Modules\Auth\Domain\Repository\LoginCodeRepository;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Columns\LoginCodeColumns;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Entity\CycleLoginCodeEntity;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Mapper\LoginCodeMapper;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<CycleLoginCodeEntity>
 */
final class CycleLoginCodeRepository extends AbstractRepository implements LoginCodeRepository
{
    /**
     * @param Select<CycleLoginCodeEntity> $select
     */
    public function __construct(
        Select $select,
        ORM $orm,
        string $role,
        private LoginCodeMapper $loginCodeMapper,
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
        /** @var CycleLoginCodeEntity|null $cycleEntity */
        $cycleEntity = $this->select()
            ->where(LoginCodeColumns::EMAIL, $email->value())
            ->where(LoginCodeColumns::CONSUMED_AT, null)
            ->orderBy([LoginCodeColumns::ID => 'DESC'])
            ->forUpdate()
            ->fetchOne();

        return $cycleEntity === null ? null : $this->loginCodeMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function findActiveByEmail(EmailAddress $email): LoginCode|null
    {
        /** @var CycleLoginCodeEntity|null $cycleEntity */
        $cycleEntity = $this->select()
            ->where(LoginCodeColumns::EMAIL, $email->value())
            ->where(LoginCodeColumns::CONSUMED_AT, null)
            ->orderBy([LoginCodeColumns::ID => 'DESC'])
            ->fetchOne();

        return $cycleEntity === null ? null : $this->loginCodeMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function add(LoginCode $loginCode): void
    {
        /** @var CycleLoginCodeEntity|null $cycleEntity */
        $cycleEntity = $this->findOne([LoginCodeColumns::ID => $loginCode->id->value()]);

        $this->entityManager->persist($this->loginCodeMapper->toCycleEntity(
            loginCode: $loginCode,
            cycleEntity: $cycleEntity,
        ));
    }

    #[\Override]
    public function save(LoginCode $loginCode): void
    {
        /** @var CycleLoginCodeEntity|null $cycleEntity */
        $cycleEntity = $this->findOne([LoginCodeColumns::ID => $loginCode->id->value()]);

        $this->entityManager
            ->persist($this->loginCodeMapper->toCycleEntity(
                loginCode: $loginCode,
                cycleEntity: $cycleEntity,
            ))
            ->run();
    }
}
