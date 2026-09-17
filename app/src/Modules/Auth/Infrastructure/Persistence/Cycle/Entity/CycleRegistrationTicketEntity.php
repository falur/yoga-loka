<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\Auth\Infrastructure\Persistence\Cycle\Columns\RegistrationTicketColumns;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Repository\CycleRegistrationTicketRepository;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'auth_registration_ticket',
    table: RegistrationTicketColumns::TABLE,
    repository: CycleRegistrationTicketRepository::class,
    typecast: [Typecast::class],
)]
final class CycleRegistrationTicketEntity
{
    use HasTimestamps;

    #[Column(type: 'uuid', name: RegistrationTicketColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'string(254)', name: RegistrationTicketColumns::EMAIL)]
    public string $email;

    #[Column(type: 'text', name: RegistrationTicketColumns::TICKET_HASH)]
    public string $ticketHash;

    #[Column(type: 'datetime', name: RegistrationTicketColumns::EXPIRES_AT, typecast: 'datetime')]
    public \DateTimeImmutable $expiresAt;

    #[Column(type: 'datetime', name: RegistrationTicketColumns::CONSUMED_AT, nullable: true, typecast: 'datetime')]
    public \DateTimeImmutable|null $consumedAt;
}
