<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Notifications\Presentation;

use App\Modules\Notifications\Application\Command\Notification\DispatchNotification\DispatchNotificationHandler;
use App\Modules\Notifications\Application\Contract\NotificationTypeRegistryContract;
use App\Modules\Notifications\Application\Exception\NotificationTypeRegistryException;
use App\Modules\Notifications\Application\Message\NotificationRequested;
use App\Modules\Notifications\Domain\Entity\Notification;
use App\Modules\Notifications\Domain\ValueObject\NotificationOutboxId;
use App\Modules\Notifications\Infrastructure\Registry\NotificationTypeRegistry;
use App\Modules\Notifications\Presentation\Job\DispatchNotificationJob;
use App\Modules\Notifications\Repository\NotificationRepository;
use App\Modules\Notifications\Repository\NotificationSettingRepository;
use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventType;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Psr\Log\NullLogger;
use Spiral\Queue\Exception\RetryException;
use Tests\DatabaseTestCase;
use Tests\Support\Notifications\FixtureNotificationTypeDefinition;
use Tests\Support\Notifications\RecordingOutboxEventStore;

final class DispatchNotificationJobTest extends DatabaseTestCase
{
    private const string TYPE = 'chat.message_received';

    public function testDelegatesToDispatchHandlerAndCreatesInbox(): void
    {
        $this->getContainer()->get(NotificationTypeRegistryContract::class)
            ->register(FixtureNotificationTypeDefinition::allChannels(self::TYPE));

        $eventId = $this->stageNotificationRequested(UserId::generate());

        $this->getContainer()->get(DispatchNotificationJob::class)->invoke(
            payload: $this->envelope($eventId),
            id: 'job-1',
            outboxMessageLoader: $this->getContainer()->get(OutboxMessageLoaderContract::class),
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            dispatchNotificationHandler: $this->getContainer()->get(DispatchNotificationHandler::class),
            logger: new NullLogger(),
        );

        $inbox = $this->notificationRepository()->findByOutboxId(NotificationOutboxId::fromString($eventId->value()));
        self::assertInstanceOf(Notification::class, $inbox);
    }

    public function testRethrowsTerminalDomainErrorForUnknownType(): void
    {
        $eventId = $this->stageNotificationRequested(UserId::generate());

        $this->expectException(NotificationTypeRegistryException::class);

        $this->getContainer()->get(DispatchNotificationJob::class)->invoke(
            payload: $this->envelope($eventId),
            id: 'job-2',
            outboxMessageLoader: $this->getContainer()->get(OutboxMessageLoaderContract::class),
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            dispatchNotificationHandler: $this->getContainer()->get(DispatchNotificationHandler::class),
            logger: new NullLogger(),
        );
    }

    public function testConvertsInfrastructureFailureToRetry(): void
    {
        $registry = new NotificationTypeRegistry();
        $registry->register(FixtureNotificationTypeDefinition::allChannels(self::TYPE));

        $failingEntityManager = $this->createStub(EntityManagerInterface::class);
        $failingEntityManager->method('run')->willThrowException(new \RuntimeException('БД недоступна.'));

        $handler = new DispatchNotificationHandler(
            notificationRepository: $this->notificationRepository(),
            notificationSettingRepository: $this->getContainer()->get(NotificationSettingRepository::class),
            typeRegistry: $registry,
            outboxEventStore: new RecordingOutboxEventStore(),
            entityManager: $failingEntityManager,
            logger: new NullLogger(),
        );

        $loader = $this->createStub(OutboxMessageLoaderContract::class);
        $loader->method('load')->willReturn(new NotificationRequested(
            userId: UserId::generate()->value(),
            type: self::TYPE,
            title: 'Новое сообщение',
            body: 'Вам пришло сообщение',
            action: null,
            actor: null,
            createdAt: '2026-06-13T10:00:00+00:00',
        ));

        $this->expectException(RetryException::class);

        $this->getContainer()->get(DispatchNotificationJob::class)->invoke(
            payload: $this->envelope(OutboxEventId::fromString(NotificationOutboxId::generate()->value())),
            id: 'job-3',
            outboxMessageLoader: $loader,
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            dispatchNotificationHandler: $handler,
            logger: new NullLogger(),
        );
    }

    private function stageNotificationRequested(UserId $userId): OutboxEventId
    {
        $storedOutboxEventId = $this->getContainer()->get(OutboxEventStoreContract::class)->add(new NotificationRequested(
            userId: $userId->value(),
            type: self::TYPE,
            title: 'Новое сообщение',
            body: 'Вам пришло сообщение',
            action: null,
            actor: null,
            createdAt: '2026-06-13T10:00:00+00:00',
        ));
        $this->getContainer()->get(EntityManagerInterface::class)->run();

        return OutboxEventId::fromString($storedOutboxEventId->value());
    }

    private function envelope(OutboxEventId $eventId): OutboxQueueEnvelope
    {
        return new OutboxQueueEnvelope(
            outboxEventId: $eventId,
            outboxEventType: OutboxEventType::fromString(NotificationRequested::class),
        );
    }

    private function notificationRepository(): NotificationRepository
    {
        return $this->getContainer()->get(NotificationRepository::class);
    }
}
