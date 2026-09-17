<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Auth\Domain\Entity\RegistrationTicket;
use App\Modules\Auth\Domain\Repository\RegistrationTicketRepository;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Columns\RegistrationTicketColumns;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Entity\CycleRegistrationTicketEntity;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Mapper\RegistrationTicketMapper;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<CycleRegistrationTicketEntity>
 */
final class CycleRegistrationTicketRepository extends AbstractRepository implements RegistrationTicketRepository
{
    /**
     * @param Select<CycleRegistrationTicketEntity> $select
     */
    public function __construct(
        Select $select,
        ORM $orm,
        string $role,
        private RegistrationTicketMapper $registrationTicketMapper,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct(select: $select, orm: $orm, role: $role);
    }

    #[\Override]
    public function findActiveByHashForUpdate(SecretHash $ticketHash): RegistrationTicket|null
    {
        /** @var CycleRegistrationTicketEntity|null $cycleEntity */
        $cycleEntity = $this->select()
            ->where(RegistrationTicketColumns::TICKET_HASH, $ticketHash->value())
            ->where(RegistrationTicketColumns::CONSUMED_AT, null)
            ->forUpdate()
            ->fetchOne();

        return $cycleEntity === null ? null : $this->registrationTicketMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function add(RegistrationTicket $registrationTicket): void
    {
        /** @var CycleRegistrationTicketEntity|null $cycleEntity */
        $cycleEntity = $this->findOne([RegistrationTicketColumns::ID => $registrationTicket->id->value()]);

        $this->entityManager->persist($this->registrationTicketMapper->toCycleEntity(
            registrationTicket: $registrationTicket,
            cycleEntity: $cycleEntity,
        ));
    }

    #[\Override]
    public function save(RegistrationTicket $registrationTicket): void
    {
        /** @var CycleRegistrationTicketEntity|null $cycleEntity */
        $cycleEntity = $this->findOne([RegistrationTicketColumns::ID => $registrationTicket->id->value()]);

        $this->entityManager
            ->persist($this->registrationTicketMapper->toCycleEntity(
                registrationTicket: $registrationTicket,
                cycleEntity: $cycleEntity,
            ))
            ->run();
    }
}
