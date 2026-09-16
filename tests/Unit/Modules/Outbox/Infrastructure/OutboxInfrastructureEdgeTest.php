<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Outbox\Infrastructure;

use App\Modules\Outbox\Public\Contract\IntegrationEvent;
use App\Modules\Outbox\Application\Contract\OutboxMessageSerializerContract;
use App\Modules\Outbox\Infrastructure\Relay\InfiniteOutboxRelayLoopControl;
use App\Modules\Outbox\Infrastructure\Spiral\Registry\OutboxJobRegistry;
use App\Modules\Outbox\Infrastructure\Exception\OutboxJobRegistryException;
use App\Modules\Outbox\Infrastructure\Spiral\Queue\OutboxQueuePublisher;
use App\Modules\Outbox\Infrastructure\Spiral\Queue\OutboxQueueSerializer;
use PHPUnit\Framework\TestCase;
use Spiral\Queue\Config\QueueConfig;
use Spiral\Queue\QueueConnectionProviderInterface;

final class OutboxInfrastructureEdgeTest extends TestCase
{
    public function testInfiniteLoopControlAlwaysContinues(): void
    {
        self::assertTrue((new InfiniteOutboxRelayLoopControl())->shouldContinue());
    }

    public function testJobRegistryRejectsInvalidMessageClass(): void
    {
        $this->expectException(OutboxJobRegistryException::class);

        (new OutboxJobRegistry())->register(
            integrationEventClass: \stdClass::class,
            jobClass: \stdClass::class,
        );
    }

    public function testJobRegistryRejectsInvalidJobClass(): void
    {
        $this->expectException(OutboxJobRegistryException::class);

        (new OutboxJobRegistry())->register(
            integrationEventClass: OutboxInfrastructureEdgeMessage::class,
            jobClass: \stdClass::class,
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

final readonly class OutboxInfrastructureEdgeMessage implements IntegrationEvent {}
