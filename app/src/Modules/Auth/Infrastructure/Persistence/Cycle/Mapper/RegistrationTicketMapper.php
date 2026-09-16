<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\Auth\Domain\Entity\RegistrationTicket;
use App\Modules\Auth\Domain\ValueObject\Consumption;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\RegistrationTicketId;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Entity\CycleRegistrationTicketEntity;

final readonly class RegistrationTicketMapper
{
    public function toDomain(CycleRegistrationTicketEntity $cycleEntity): RegistrationTicket
    {
        return RegistrationTicket::restore(
            id: RegistrationTicketId::fromString($cycleEntity->id),
            email: EmailAddress::fromString($cycleEntity->email),
            ticketHash: SecretHash::fromString($cycleEntity->ticketHash),
            expiration: Expiration::fromDateTime($cycleEntity->expiresAt),
            consumption: $cycleEntity->consumedAt === null
                ? Consumption::notConsumed()
                : Consumption::at($cycleEntity->consumedAt),
            createdAt: $cycleEntity->createdAt,
            updatedAt: $cycleEntity->updatedAt,
        );
    }

    public function toCycleEntity(
        RegistrationTicket $registrationTicket,
        CycleRegistrationTicketEntity|null $cycleEntity = null,
    ): CycleRegistrationTicketEntity {
        $cycleEntity ??= new CycleRegistrationTicketEntity();
        $cycleEntity->id = $registrationTicket->id->value();
        $cycleEntity->email = $registrationTicket->email->value();
        $cycleEntity->ticketHash = $registrationTicket->ticketHash->value();
        $cycleEntity->expiresAt = $registrationTicket->expiration->value();
        $cycleEntity->consumedAt = $registrationTicket->consumption->value();
        $cycleEntity->createdAt = $registrationTicket->createdAt;
        $cycleEntity->updatedAt = $registrationTicket->updatedAt;

        return $cycleEntity;
    }
}
