<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Notifications\Infrastructure\Spiral\Job;

use App\Modules\Notifications\Application\Command\Notification\DispatchNotification\DispatchNotificationHandler;
use App\Modules\Notifications\Public\Contract\NotificationTypeRegistryContract;
use App\Modules\Notifications\Application\Exception\NotificationTypeRegistryException;
use App\Modules\Notifications\Public\Event\NotificationRequestedEvent;
use App\Modules\Notifications\Domain\Entity\Notification;
use App\Modules\Notifications\Domain\ValueObject\NotificationOutboxId;
use App\Modules\Notifications\Infrastructure\Spiral\Registry\NotificationTypeRegistry;
use App\Modules\Notifications\Infrastructure\Spiral\Job\DispatchNotificationJob;
use App\Modules\Notifications\Repository\NotificationRepository;
use App\Modules\Notifications\Repository\NotificationSettingRepository;
use App\Modules\Outbox\Public\Contract\IntegrationEventStoreContract;
use App\Modules\Outbox\Public\Contract\IntegrationEventLoaderContract;
use App\Modules\Outbox\Public\Dto\OutboxEnvelopeDto;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
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
            integrationEventLoader: $this->getContainer()->get(IntegrationEventLoaderContract::class),
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
            integrationEventLoader: $this->getContainer()->get(IntegrationEventLoaderContract::class),
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
            typeCatalog: $registry,
            integrationEventStore: new RecordingOutboxEventStore(),
            entityManager: $failingEntityManager,
            logger: new NullLogger(),
        );

        $loader = $this->createStub(IntegrationEventLoaderContract::class);
        $loader->method('load')->willReturn(new NotificationRequestedEvent(
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
            integrationEventLoader: $loader,
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            dispatchNotificationHandler: $handler,
            logger: new NullLogger(),
        );
    }

    private function stageNotificationRequested(UserId $userId): OutboxEventId
    {
        $storedOutboxEventId = $this->getContainer()->get(IntegrationEventStoreContract::class)->add(new NotificationRequestedEvent(
            userId: $userId->value(),
            type: self::TYPE,
            title: 'Новое сообщение',
            body: 'Вам пришло сообщение',
            action: null,
            actor: null,
            createdAt: '2026-06-13T10:00:00+00:00',
        ));
        $this->getContainer()->get(EntityManagerInterface::class)->run();

        return OutboxEventId::fromString($storedOutboxEventId);
    }

    private function envelope(OutboxEventId $eventId): OutboxEnvelopeDto
    {
        return new OutboxEnvelopeDto(
            outboxEventId: $eventId->value(),
            outboxEventType: NotificationRequestedEvent::class,
        );
    }

    private function notificationRepository(): NotificationRepository
    {
        return $this->getContainer()->get(NotificationRepository::class);
    }
}
