<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Auth\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\Auth\Domain\Entity\RegistrationTicket;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\RegistrationTicketId;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Mapper\RegistrationTicketMapper;
use PHPUnit\Framework\TestCase;

/**
 * Переносит проверки удалённого ConsumptionTypecast (в части RegistrationTicket) на Mapper, куда
 * переехала логика null-bridging между nullable-колонкой consumed_at и null-object Consumption.
 */
final class RegistrationTicketMapperTest extends TestCase
{
    public function testMapsConsumedTicketToAndFromCycleEntity(): void
    {
        $mapper = new RegistrationTicketMapper();
        $now = new \DateTimeImmutable('2026-06-15 12:00:00');
        $consumedAt = new \DateTimeImmutable('2026-06-15 12:05:00');

        $ticket = RegistrationTicket::issue(
            id: RegistrationTicketId::generate(),
            email: EmailAddress::fromString('newcomer@example.com'),
            ticketHash: SecretHash::fromString('ticket-hash'),
            expiration: Expiration::after($now, 900),
            now: $now,
        );
        $ticket->consume($consumedAt);

        $cycleEntity = $mapper->toCycleEntity($ticket);

        self::assertSame($ticket->id->value(), $cycleEntity->id);
        self::assertSame('newcomer@example.com', $cycleEntity->email);
        self::assertSame('ticket-hash', $cycleEntity->ticketHash);
        self::assertSame($consumedAt, $cycleEntity->consumedAt);

        $restoredTicket = $mapper->toDomain($cycleEntity);

        self::assertTrue($ticket->id->equals($restoredTicket->id));
        self::assertTrue($restoredTicket->isConsumed());
        self::assertSame($consumedAt, $restoredTicket->consumption->value());
    }

    public function testMapsNotConsumedTicketToNullColumn(): void
    {
        $mapper = new RegistrationTicketMapper();
        $now = new \DateTimeImmutable('2026-06-15 12:00:00');
        $ticket = RegistrationTicket::issue(
            id: RegistrationTicketId::generate(),
            email: EmailAddress::fromString('fresh@example.com'),
            ticketHash: SecretHash::fromString('fresh-hash'),
            expiration: Expiration::after($now, 900),
            now: $now,
        );

        $cycleEntity = $mapper->toCycleEntity($ticket);

        self::assertNull($cycleEntity->consumedAt);

        $restoredTicket = $mapper->toDomain($cycleEntity);

        self::assertFalse($restoredTicket->isConsumed());
    }

    public function testToCycleEntityUpdatesGivenInstanceInPlace(): void
    {
        $mapper = new RegistrationTicketMapper();
        $now = new \DateTimeImmutable('2026-06-15 12:00:00');
        $ticket = RegistrationTicket::issue(
            id: RegistrationTicketId::generate(),
            email: EmailAddress::fromString('reuse@example.com'),
            ticketHash: SecretHash::fromString('reuse-hash'),
            expiration: Expiration::after($now, 900),
            now: $now,
        );

        $cycleEntity = $mapper->toCycleEntity($ticket);
        $ticket->consume($now);
        $updatedCycleEntity = $mapper->toCycleEntity($ticket, $cycleEntity);

        self::assertSame($cycleEntity, $updatedCycleEntity);
        self::assertNotNull($updatedCycleEntity->consumedAt);
    }
}
