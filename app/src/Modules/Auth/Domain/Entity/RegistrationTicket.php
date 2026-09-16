<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Entity;

use App\Modules\Auth\Domain\ValueObject\Consumption;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\RegistrationTicketId;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Typecast\ConsumptionTypecast;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Typecast\ExpirationTypecast;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Repository\CycleRegistrationTicketRepository;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use App\Shared\Infrastructure\Persistence\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'auth_registration_ticket',
    table: 'auth_registration_tickets',
    repository: CycleRegistrationTicketRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class RegistrationTicket
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: RegistrationTicketId::class)]
    public private(set) RegistrationTicketId $id;

    #[Column(type: 'string(254)', typecast: EmailAddress::class)]
    public private(set) EmailAddress $email;

    #[Column(type: 'text', name: 'ticket_hash', typecast: SecretHash::class)]
    public private(set) SecretHash $ticketHash;

    #[Column(type: 'datetime', name: 'expires_at', typecast: ExpirationTypecast::class)]
    public private(set) Expiration $expiration;

    #[Column(type: 'datetime', name: 'consumed_at', nullable: true, typecast: ConsumptionTypecast::class)]
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
