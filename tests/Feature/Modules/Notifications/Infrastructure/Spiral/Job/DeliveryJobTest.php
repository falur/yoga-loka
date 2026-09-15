<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Notifications\Infrastructure\Spiral\Job;

use App\Modules\Notifications\Application\Command\Push\SendPushNotification\SendPushNotificationHandler;
use App\Modules\Notifications\Application\Command\Realtime\PublishRealtimeNotification\PublishRealtimeNotificationHandler;
use App\Modules\Notifications\Application\Contract\CentrifugoServiceContract;
use App\Modules\Notifications\Application\Contract\FcmPushSenderContract;
use App\Modules\Notifications\Application\Contract\OnlinePresenceContract;
use App\Modules\Notifications\Application\Exception\CentrifugoPublishException;
use App\Modules\Notifications\Application\Exception\FcmPushFailedException;
use App\Modules\Notifications\Public\Event\NotificationPushRequestedEvent;
use App\Modules\Notifications\Public\Event\NotificationRealtimeRequestedEvent;
use App\Modules\Notifications\Public\Event\NotificationRequestedEvent;
use App\Modules\Notifications\Domain\Entity\NotificationDeviceToken;
use App\Modules\Notifications\Domain\Enum\DevicePlatform;
use App\Modules\Notifications\Domain\ValueObject\DeviceToken;
use App\Modules\Notifications\Infrastructure\Spiral\Job\DispatchNotificationJob;
use App\Modules\Notifications\Infrastructure\Spiral\Job\PublishRealtimeNotificationJob;
use App\Modules\Notifications\Infrastructure\Spiral\Job\SendPushNotificationJob;
use App\Modules\Notifications\Repository\NotificationDeviceTokenRepository;
use App\Modules\Outbox\Application\Contract\OutboxJobRegistryContract;
use App\Modules\Outbox\Public\Contract\IntegrationEventLoaderContract;
use App\Modules\Outbox\Public\Contract\IntegrationEvent;
use App\Modules\Outbox\Public\Dto\OutboxEnvelopeDto;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Psr\Log\NullLogger;
use Spiral\Queue\Exception\RetryException;
use Tests\DatabaseTestCase;
use Tests\Support\Media\PersistsMedia;

final class DeliveryJobTest extends DatabaseTestCase
{
    use PersistsMedia;


    public function testPushJobRetriesOnTransientFailure(): void
    {
        $userId = UserId::generate();
        $this->persistToken($userId);

        $fcmPushSender = $this->createStub(FcmPushSenderContract::class);
        $fcmPushSender->method('send')->willThrowException(FcmPushFailedException::transient(new \RuntimeException('down')));

        $this->expectException(RetryException::class);

        $this->invokePushJob($userId, $this->pushHandler($fcmPushSender));
    }

    public function testPushJobRethrowsTerminalFailure(): void
    {
        $userId = UserId::generate();
        $this->persistToken($userId);

        $fcmPushSender = $this->createStub(FcmPushSenderContract::class);
        $fcmPushSender->method('send')->willThrowException(new \RuntimeException('terminal'));

        $this->expectException(\RuntimeException::class);

        $this->invokePushJob($userId, $this->pushHandler($fcmPushSender));
    }

    public function testRealtimeJobRetriesOnTransientFailure(): void
    {
        $centrifugoService = $this->createStub(CentrifugoServiceContract::class);
        $centrifugoService->method('publish')->willThrowException(CentrifugoPublishException::serverError(503));

        $this->expectException(RetryException::class);

        $this->invokeRealtimeJob($this->realtimeHandler($centrifugoService));
    }

    public function testRealtimeJobRethrowsTerminalFailure(): void
    {
        $centrifugoService = $this->createStub(CentrifugoServiceContract::class);
        $centrifugoService->method('publish')->willThrowException(new \RuntimeException('terminal'));

        $this->expectException(\RuntimeException::class);

        $this->invokeRealtimeJob($this->realtimeHandler($centrifugoService));
    }

    public function testJobRegistryMapsMessagesToJobs(): void
    {
        $registry = $this->getContainer()->get(OutboxJobRegistryContract::class);

        self::assertSame(DispatchNotificationJob::class, $registry->jobFor($this->message(NotificationRequestedEvent::class)));
        self::assertSame(SendPushNotificationJob::class, $registry->jobFor($this->message(NotificationPushRequestedEvent::class)));
        self::assertSame(PublishRealtimeNotificationJob::class, $registry->jobFor($this->message(NotificationRealtimeRequestedEvent::class)));
    }

    private function invokePushJob(UserId $userId, SendPushNotificationHandler $handler): void
    {
        $loader = $this->createStub(IntegrationEventLoaderContract::class);
        $loader->method('load')->willReturn(new NotificationPushRequestedEvent(
            userId: $userId->value(),
            type: 'chat.message_received',
            title: 'Новое сообщение',
            body: 'Вам пришло сообщение',
            action: null,
            actor: null,
            createdAt: '2026-06-13T10:00:00+00:00',
        ));

        $this->getContainer()->get(SendPushNotificationJob::class)->invoke(
            payload: $this->envelope(NotificationPushRequestedEvent::class),
            id: 'push-job',
            integrationEventLoader: $loader,
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            sendPushNotificationHandler: $handler,
            logger: new NullLogger(),
        );
    }

    private function invokeRealtimeJob(PublishRealtimeNotificationHandler $handler): void
    {
        $loader = $this->createStub(IntegrationEventLoaderContract::class);
        $loader->method('load')->willReturn(new NotificationRealtimeRequestedEvent(
            userId: UserId::generate()->value(),
            type: 'chat.message_received',
            title: 'Новое сообщение',
            body: 'Вам пришло сообщение',
            action: null,
            actor: null,
            createdAt: '2026-06-13T10:00:00+00:00',
        ));

        $this->getContainer()->get(PublishRealtimeNotificationJob::class)->invoke(
            payload: $this->envelope(NotificationRealtimeRequestedEvent::class),
            id: 'realtime-job',
            integrationEventLoader: $loader,
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            publishRealtimeNotificationHandler: $handler,
            logger: new NullLogger(),
        );
    }

    private function pushHandler(FcmPushSenderContract $fcmPushSender): SendPushNotificationHandler
    {
        $onlinePresence = $this->createStub(OnlinePresenceContract::class);
        $onlinePresence->method('isOnline')->willReturn(false);

        return new SendPushNotificationHandler(
            notificationDeviceTokenRepository: $this->getContainer()->get(NotificationDeviceTokenRepository::class),
            fcmPushSender: $fcmPushSender,
            onlinePresence: $onlinePresence,
            media: $this->stubbedMediaContract(),
            entityManager: $this->getContainer()->get(EntityManagerInterface::class),
            logger: new NullLogger(),
        );
    }

    private function realtimeHandler(CentrifugoServiceContract $centrifugoService): PublishRealtimeNotificationHandler
    {
        return new PublishRealtimeNotificationHandler(
            centrifugoService: $centrifugoService,
            media: $this->stubbedMediaContract(),
            logger: new NullLogger(),
        );
    }

    private function persistToken(UserId $userId): void
    {
        $entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(NotificationDeviceToken::create(
            userId: $userId,
            token: DeviceToken::fromString('fcm-token'),
            platform: DevicePlatform::Ios,
        ));
        $entityManager->run();
    }

    /**
     * @param class-string<IntegrationEvent> $messageClass
     */
    private function envelope(string $messageClass): OutboxEnvelopeDto
    {
        return new OutboxEnvelopeDto(
            outboxEventId: OutboxEventId::generate()->value(),
            outboxEventType: $messageClass,
        );
    }

    /**
     * @param class-string<IntegrationEvent> $messageClass
     */
    private function message(string $messageClass): IntegrationEvent
    {
        return new $messageClass(
            userId: UserId::generate()->value(),
            type: 'chat.message_received',
            title: 'Новое сообщение',
            body: 'Вам пришло сообщение',
            action: null,
            actor: null,
            createdAt: '2026-06-13T10:00:00+00:00',
        );
    }
}
