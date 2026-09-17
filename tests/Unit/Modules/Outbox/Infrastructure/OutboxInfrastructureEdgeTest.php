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
use Psr\Container\ContainerInterface;
use Spiral\Core\FactoryInterface;
use Spiral\Queue\Config\QueueConfig;
use Spiral\Queue\HandlerRegistryInterface;
use Spiral\Queue\QueueConnectionProviderInterface;
use Spiral\Queue\QueueRegistry;

final class OutboxInfrastructureEdgeTest extends TestCase
{
    public function testInfiniteLoopControlAlwaysContinues(): void
    {
        self::assertTrue((new InfiniteOutboxRelayLoopControl())->shouldContinue());
    }

    public function testJobRegistryRejectsInvalidMessageClass(): void
    {
        $this->expectException(OutboxJobRegistryException::class);

        $this->outboxJobRegistry()->register(
            integrationEventClass: \stdClass::class,
            jobClass: \stdClass::class,
        );
    }

    public function testJobRegistryRejectsInvalidJobClass(): void
    {
        $this->expectException(OutboxJobRegistryException::class);

        $this->outboxJobRegistry()->register(
            integrationEventClass: OutboxInfrastructureEdgeMessage::class,
            jobClass: \stdClass::class,
        );
    }

    public function testJobRegistryRejectsUnregisteredMessage(): void
    {
        $this->expectException(OutboxJobRegistryException::class);

        $this->outboxJobRegistry()->jobFor(new OutboxInfrastructureEdgeMessage());
    }

    public function testQueuePublisherRejectsNonStringConnectionAlias(): void
    {
        $outboxQueuePublisher = new OutboxQueuePublisher(
            outboxMessageSerializer: $this->createStub(OutboxMessageSerializerContract::class),
            outboxJobRegistry: $this->outboxJobRegistry(),
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
            outboxJobRegistry: $this->outboxJobRegistry(),
            queueConnectionProvider: $this->createStub(QueueConnectionProviderInterface::class),
            queueConfig: new QueueConfig([]),
            outboxQueueSerializer: new OutboxQueueSerializer(),
        );
        $nonEmptyHeaderValue = new \ReflectionMethod(OutboxQueuePublisher::class, 'nonEmptyHeaderValue');

        $this->expectException(\UnexpectedValueException::class);

        $nonEmptyHeaderValue->invoke($outboxQueuePublisher, '');
    }

    /**
     * Настоящий QueueRegistry не нужен: сценарии этого теста либо бросают исключение до обращения
     * к реестру очереди (невалидные классы), либо вовсе не доходят до setHandler()/setSerializer()
     * на переданном OutboxJobRegistry — зависимости QueueRegistry дублёры без проверки факта вызова.
     */
    private function outboxJobRegistry(): OutboxJobRegistry
    {
        return new OutboxJobRegistry(
            queueRegistry: new QueueRegistry(
                container: $this->createStub(ContainerInterface::class),
                factory: $this->createStub(FactoryInterface::class),
                fallbackHandlers: $this->createStub(HandlerRegistryInterface::class),
            ),
            outboxQueueSerializer: new OutboxQueueSerializer(),
        );
    }
}

final readonly class OutboxInfrastructureEdgeMessage implements IntegrationEvent {}
