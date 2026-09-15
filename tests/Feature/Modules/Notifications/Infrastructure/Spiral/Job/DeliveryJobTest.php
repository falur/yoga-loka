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
use App\Modules\Notifications\Application\Message\NotificationPushRequested;
use App\Modules\Notifications\Application\Message\NotificationRealtimeRequested;
use App\Modules\Notifications\Application\Message\NotificationRequested;
use App\Modules\Notifications\Domain\Entity\NotificationDeviceToken;
use App\Modules\Notifications\Domain\Enum\DevicePlatform;
use App\Modules\Notifications\Domain\ValueObject\DeviceToken;
use App\Modules\Notifications\Infrastructure\Spiral\Job\DispatchNotificationJob;
use App\Modules\Notifications\Infrastructure\Spiral\Job\PublishRealtimeNotificationJob;
use App\Modules\Notifications\Infrastructure\Spiral\Job\SendPushNotificationJob;
use App\Modules\Notifications\Repository\NotificationDeviceTokenRepository;
use App\Modules\Outbox\Application\Contract\OutboxJobRegistryContract;
use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
use App\Modules\Outbox\Application\Message\OutboxMessage;
use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventType;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralCqrs\QueryBusInterface;
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

        self::assertSame(DispatchNotificationJob::class, $registry->jobFor($this->message(NotificationRequested::class)));
        self::assertSame(SendPushNotificationJob::class, $registry->jobFor($this->message(NotificationPushRequested::class)));
        self::assertSame(PublishRealtimeNotificationJob::class, $registry->jobFor($this->message(NotificationRealtimeRequested::class)));
    }

    private function invokePushJob(UserId $userId, SendPushNotificationHandler $handler): void
    {
        $loader = $this->createStub(OutboxMessageLoaderContract::class);
        $loader->method('load')->willReturn(new NotificationPushRequested(
            userId: $userId->value(),
            type: 'chat.message_received',
            title: 'Новое сообщение',
            body: 'Вам пришло сообщение',
            action: null,
            actor: null,
            createdAt: '2026-06-13T10:00:00+00:00',
        ));

        $this->getContainer()->get(SendPushNotificationJob::class)->invoke(
            payload: $this->envelope(NotificationPushRequested::class),
            id: 'push-job',
            outboxMessageLoader: $loader,
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            sendPushNotificationHandler: $handler,
            logger: new NullLogger(),
        );
    }

    private function invokeRealtimeJob(PublishRealtimeNotificationHandler $handler): void
    {
        $loader = $this->createStub(OutboxMessageLoaderContract::class);
        $loader->method('load')->willReturn(new NotificationRealtimeRequested(
            userId: UserId::generate()->value(),
            type: 'chat.message_received',
            title: 'Новое сообщение',
            body: 'Вам пришло сообщение',
            action: null,
            actor: null,
            createdAt: '2026-06-13T10:00:00+00:00',
        ));

        $this->getContainer()->get(PublishRealtimeNotificationJob::class)->invoke(
            payload: $this->envelope(NotificationRealtimeRequested::class),
            id: 'realtime-job',
            outboxMessageLoader: $loader,
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
            queryBus: $this->getContainer()->get(QueryBusInterface::class),
            findMediaUrlHandler: $this->stubbedFindMediaUrlHandler(),
            entityManager: $this->getContainer()->get(EntityManagerInterface::class),
            logger: new NullLogger(),
        );
    }

    private function realtimeHandler(CentrifugoServiceContract $centrifugoService): PublishRealtimeNotificationHandler
    {
        return new PublishRealtimeNotificationHandler(
            centrifugoService: $centrifugoService,
            queryBus: $this->getContainer()->get(QueryBusInterface::class),
            findMediaUrlHandler: $this->stubbedFindMediaUrlHandler(),
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
     * @param class-string<OutboxMessage> $messageClass
     */
    private function envelope(string $messageClass): OutboxQueueEnvelope
    {
        return new OutboxQueueEnvelope(
            outboxEventId: OutboxEventId::generate(),
            outboxEventType: OutboxEventType::fromString($messageClass),
        );
    }

    /**
     * @param class-string<OutboxMessage> $messageClass
     */
    private function message(string $messageClass): OutboxMessage
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
