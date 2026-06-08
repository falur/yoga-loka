<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Outbox\Infrastructure;

use App\Modules\Outbox\Application\Message\OutboxMessage;
use App\Modules\Outbox\Application\Contract\OutboxMessageSerializerContract;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventDate;
use App\Modules\Outbox\Domain\ValueObject\OutboxLastError;
use App\Modules\Outbox\Infrastructure\Cycle\OutboxEventDateTypecast;
use App\Modules\Outbox\Infrastructure\Cycle\OutboxLastErrorTypecast;
use App\Modules\Outbox\Infrastructure\Relay\InfiniteOutboxRelayLoopControl;
use App\Modules\Outbox\Infrastructure\Registry\OutboxJobRegistry;
use App\Modules\Outbox\Infrastructure\Registry\OutboxJobRegistryException;
use App\Modules\Outbox\Infrastructure\Queue\OutboxQueuePublisher;
use App\Modules\Outbox\Infrastructure\Queue\OutboxQueueSerializer;
use PHPUnit\Framework\TestCase;
use Spiral\Queue\Config\QueueConfig;
use Spiral\Queue\QueueConnectionProviderInterface;

final class OutboxInfrastructureEdgeTest extends TestCase
{
    public function testEventDateTypecastAcceptsDateTimeObjects(): void
    {
        $dateTime = new \DateTime('2026-05-25T16:06:00+00:00');
        $dateTimeImmutable = new \DateTimeImmutable('2026-05-25T16:06:00+00:00');

        self::assertTrue(OutboxEventDateTypecast::castDatabaseValue($dateTimeImmutable)->equals(
            OutboxEventDate::fromDateTime($dateTimeImmutable),
        ));
        self::assertTrue(OutboxEventDateTypecast::castDatabaseValue($dateTime)->equals(
            OutboxEventDate::fromDateTime(\DateTimeImmutable::createFromMutable($dateTime)),
        ));
    }

    public function testEventDateTypecastRejectsUnknownDateStateOnUncast(): void
    {
        $this->expectException(\UnexpectedValueException::class);

        OutboxEventDateTypecast::uncastValue(new UnknownOutboxEventDate());
    }

    public function testLastErrorTypecastUncastsNull(): void
    {
        self::assertNull(OutboxLastErrorTypecast::uncastValue(null));
        self::assertNull(OutboxLastErrorTypecast::uncastValue(OutboxLastError::none()));
    }

    public function testLastErrorTypecastTreatsNullAndEmptyDatabaseValueAsNoError(): void
    {
        self::assertTrue(OutboxLastErrorTypecast::castDatabaseValue(null)->isEmpty());
        self::assertTrue(OutboxLastErrorTypecast::castDatabaseValue('')->isEmpty());
        self::assertTrue(OutboxLastErrorTypecast::castDatabaseValue('   ')->isEmpty());
        self::assertSame(
            'Ошибка доставки',
            OutboxLastErrorTypecast::castDatabaseValue('Ошибка доставки')->value(),
        );
    }

    public function testInfiniteLoopControlAlwaysContinues(): void
    {
        self::assertTrue((new InfiniteOutboxRelayLoopControl())->shouldContinue());
    }

    public function testJobRegistryRejectsInvalidMessageClass(): void
    {
        $this->expectException(OutboxJobRegistryException::class);

        (new OutboxJobRegistry())->register(
            outboxMessageClass: \stdClass::class,
            outboxJobClass: \stdClass::class,
        );
    }

    public function testJobRegistryRejectsInvalidJobClass(): void
    {
        $this->expectException(OutboxJobRegistryException::class);

        (new OutboxJobRegistry())->register(
            outboxMessageClass: OutboxInfrastructureEdgeMessage::class,
            outboxJobClass: \stdClass::class,
        );
    }

    public function testJobRegistryRejectsUnregisteredMessage(): void
    {
        $this->expectException(OutboxJobRegistryException::class);

        (new OutboxJobRegistry())->jobFor(new OutboxInfrastructureEdgeMessage());
    }

    public function testQueuePublisherRejectsNonStringConnectionAlias(): void
    {
        $outboxQueuePublisher = new OutboxQueuePublisher(
            outboxMessageSerializer: $this->createStub(OutboxMessageSerializerContract::class),
            outboxJobRegistry: new OutboxJobRegistry(),
            queueConnectionProvider: $this->createStub(QueueConnectionProviderInterface::class),
            queueConfig: new QueueConfig([
                'default' => 'default',
                'aliases' => [
                    'default' => ['broken'],
                ],
                'connections' => [],
            ]),
            outboxQueueSerializer: new OutboxQueueSerializer(),
        );

        $this->expectException(\UnexpectedValueException::class);

        $outboxQueuePublisher->usesSyncConnection();
    }

    public function testQueuePublisherRejectsEmptyHeaderValue(): void
    {
        $outboxQueuePublisher = new OutboxQueuePublisher(
            outboxMessageSerializer: $this->createStub(OutboxMessageSerializerContract::class),
            outboxJobRegistry: new OutboxJobRegistry(),
            queueConnectionProvider: $this->createStub(QueueConnectionProviderInterface::class),
            queueConfig: new QueueConfig([]),
            outboxQueueSerializer: new OutboxQueueSerializer(),
        );
        $nonEmptyHeaderValue = new \ReflectionMethod(OutboxQueuePublisher::class, 'nonEmptyHeaderValue');

        $this->expectException(\UnexpectedValueException::class);

        $nonEmptyHeaderValue->invoke($outboxQueuePublisher, '');
    }
}

final readonly class UnknownOutboxEventDate extends OutboxEventDate
{
    #[\Override]
    public function isEmpty(): bool
    {
        return false;
    }

    #[\Override]
    public function equals(OutboxEventDate $other): bool
    {
        return false;
    }

    #[\Override]
    public function __toString(): string
    {
        return 'unknown';
    }

    #[\Override]
    public function jsonSerialize(): string
    {
        return 'unknown';
    }
}

final readonly class OutboxInfrastructureEdgeMessage implements OutboxMessage {}
