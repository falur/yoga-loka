<?php

declare(strict_types=1);

namespace App\Modules\Auth\Repository;

use App\Modules\Auth\Domain\Entity\RegistrationTicket;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use Cycle\ORM\Select\Repository;

/**
 * @extends Repository<RegistrationTicket>
 */
final class RegistrationTicketRepository extends Repository
{
    /**
     * Непогашенный талон по хэшу с блокировкой строки. Срок проверяет доменный сценарий.
     */
    public function findActiveByHashForUpdate(SecretHash $ticketHash): RegistrationTicket|null
    {
        return $this->select()
            ->where('ticket_hash', $ticketHash->value())
            ->where('consumed_at', null)
            ->forUpdate()
            ->fetchOne();
    }
}
