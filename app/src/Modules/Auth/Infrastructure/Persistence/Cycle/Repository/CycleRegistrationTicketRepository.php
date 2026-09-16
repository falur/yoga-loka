<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Auth\Domain\Entity\RegistrationTicket;
use App\Modules\Auth\Domain\Repository\RegistrationTicketRepository;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<RegistrationTicket>
 */
final class CycleRegistrationTicketRepository extends AbstractRepository implements RegistrationTicketRepository
{
    /**
     * @param Select<RegistrationTicket> $select
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
    public function findActiveByHashForUpdate(SecretHash $ticketHash): RegistrationTicket|null
    {
        return $this->select()
            ->where('ticket_hash', $ticketHash->value())
            ->where('consumed_at', null)
            ->forUpdate()
            ->fetchOne();
    }

    #[\Override]
    public function add(RegistrationTicket $registrationTicket): void
    {
        $this->entityManager->persist($registrationTicket);
    }

    #[\Override]
    public function save(RegistrationTicket $registrationTicket): void
    {
        $this->entityManager
            ->persist($registrationTicket)
            ->run();
    }
}
