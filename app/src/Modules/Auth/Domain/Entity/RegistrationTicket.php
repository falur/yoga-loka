<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Entity;

use App\Modules\Auth\Domain\ValueObject\Consumption;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\RegistrationTicketId;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Shared\Domain\Trait\HasTimestamps;

final class RegistrationTicket
{
    use HasTimestamps;

    public private(set) RegistrationTicketId $id;

    public private(set) EmailAddress $email;

    public private(set) SecretHash $ticketHash;

    public private(set) Expiration $expiration;

    public private(set) Consumption $consumption;

    public static function issue(
        RegistrationTicketId $id,
        EmailAddress $email,
        SecretHash $ticketHash,
        Expiration $expiration,
        \DateTimeImmutable $now,
    ): self {
        $ticket = new self();
        $ticket->id = $id;
        $ticket->email = $email;
        $ticket->ticketHash = $ticketHash;
        $ticket->expiration = $expiration;
        $ticket->consumption = Consumption::notConsumed();
        $ticket->initializeTimestamps($now);

        return $ticket;
    }

    public static function restore(
        RegistrationTicketId $id,
        EmailAddress $email,
        SecretHash $ticketHash,
        Expiration $expiration,
        Consumption $consumption,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $ticket = new self();
        $ticket->id = $id;
        $ticket->email = $email;
        $ticket->ticketHash = $ticketHash;
        $ticket->expiration = $expiration;
        $ticket->consumption = $consumption;
        $ticket->createdAt = $createdAt;
        $ticket->updatedAt = $updatedAt;

        return $ticket;
    }

    public function consume(\DateTimeImmutable $now): void
    {
        $this->consumption = Consumption::at($now);
        $this->touch($now);
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $this->expiration->isExpired($now);
    }

    public function isConsumed(): bool
    {
        return $this->consumption->isConsumed();
    }
}
