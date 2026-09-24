<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Integration\Spiral;

use App\Modules\Notifications\Application\Command\Push\SendPushNotification\SendPushNotificationHandler;
use App\Modules\Notifications\Application\Command\Realtime\PublishRealtimeNotification\PublishRealtimeNotificationHandler;
use App\Modules\Notifications\Application\Contract\CentrifugoServiceContract;
use App\Modules\Notifications\Application\Contract\FcmPushSenderContract;
use App\Modules\Notifications\Application\Contract\OnlinePresenceContract;
use App\Modules\Notifications\Application\Exception\CentrifugoPublishException;
use App\Modules\Notifications\Application\Exception\FcmPushFailedException;
use App\Modules\Notifications\Public\Event\NotificationPushRequestedEvent;
use App\Modules\Notifications\Public\Event\NotificationRealtimeRequestedEvent;
use App\Modules\Notifications\Domain\Entity\NotificationDeviceToken;
use App\Modules\Notifications\Domain\Enum\DevicePlatform;
use App\Modules\Notifications\Domain\ValueObject\DeviceToken;
use App\Modules\Notifications\Infrastructure\Spiral\Job\PublishRealtimeNotificationJob;
use App\Modules\Notifications\Infrastructure\Spiral\Job\SendPushNotificationJob;
use App\Modules\Notifications\Domain\Repository\NotificationDeviceTokenRepository;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralOutbox\Exception\RetryableOutboxException;
use GianTiaga\SpiralOutbox\OutboxMessageLoaderContract;
use Psr\Log\NullLogger;
use Tests\DatabaseTestCase;
use App\Modules\Notifications\Tests\Support\PersistsMedia;

final class DeliveryJobTest extends DatabaseTestCase
{
    use PersistsMedia;

    /** Доставки с таким идентификатором нет: сценарии проверяют только классификацию сбоя. */
    private const string DELIVERY_ID = '0190f3b1-0000-7000-8000-0000000000de';

    public function testPushJobRetriesOnTransientFailure(): void
    {
        $userId = UserId::generate();
        $this->persistToken($userId);

        $fcmPushSender = $this->createStub(FcmPushSenderContract::class);
        $fcmPushSender->method('send')->willThrowException(FcmPushFailedException::transient(new \RuntimeException('down')));

        $this->expectException(RetryableOutboxException::class);

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

        $this->expectException(RetryableOutboxException::class);

        $this->invokeRealtimeJob($this->realtimeHandler($centrifugoService));
    }

    public function testRealtimeJobRethrowsTerminalFailure(): void
    {
        $centrifugoService = $this->createStub(CentrifugoServiceContract::class);
        $centrifugoService->method('publish')->willThrowException(new \RuntimeException('terminal'));

        $this->expectException(\RuntimeException::class);

        $this->invokeRealtimeJob($this->realtimeHandler($centrifugoService));
    }

    private function invokePushJob(UserId $userId, SendPushNotificationHandler $handler): void
    {
        $loader = $this->createStub(OutboxMessageLoaderContract::class);
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
            outboxDeliveryId: self::DELIVERY_ID,
            outboxMessageLoader: $loader,
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            sendPushNotificationHandler: $handler,
            logger: new NullLogger(),
        );
    }

    private function invokeRealtimeJob(PublishRealtimeNotificationHandler $handler): void
    {
        $loader = $this->createStub(OutboxMessageLoaderContract::class);
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
            outboxDeliveryId: self::DELIVERY_ID,
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
            media: $this->stubbedMediaContract(),
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
        $this->getContainer()->get(NotificationDeviceTokenRepository::class)->save(NotificationDeviceToken::create(
            userId: $userId,
            token: DeviceToken::fromString('fcm-token'),
            platform: DevicePlatform::Ios,
        ));
    }
}
