<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Notifications\Application;

use App\Modules\Notifications\Application\Command\Push\SendPushNotification\SendPushNotificationCommand;
use App\Modules\Notifications\Application\Command\Push\SendPushNotification\SendPushNotificationHandler;
use App\Modules\Notifications\Application\Contract\FcmPushSenderContract;
use App\Modules\Notifications\Application\Contract\OnlinePresenceContract;
use App\Modules\Notifications\Application\Dto\FcmPushResult;
use App\Modules\Notifications\Application\Dto\NotificationActorPayload;
use App\Modules\Notifications\Application\Dto\NotificationPush;
use App\Modules\Notifications\Domain\Entity\NotificationDeviceToken;
use App\Modules\Notifications\Domain\Enum\DevicePlatform;
use App\Modules\Notifications\Domain\ValueObject\DeviceToken;
use App\Modules\Notifications\Repository\NotificationDeviceTokenRepository;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Tests\DatabaseTestCase;

final class SendPushNotificationHandlerTest extends DatabaseTestCase
{
    public function testSendsPushAndRemovesInvalidTokens(): void
    {
        $userId = UserId::generate();
        $this->persistToken($userId, 'valid-token');
        $this->persistToken($userId, 'invalid-token');

        $capturedPush = null;
        $capturedTokens = null;
        $fcmPushSender = $this->createMock(FcmPushSenderContract::class);
        $fcmPushSender->expects(self::once())
            ->method('send')
            ->willReturnCallback(function (NotificationPush $push, array $tokens) use (&$capturedPush, &$capturedTokens): FcmPushResult {
                $capturedPush = $push;
                $capturedTokens = $tokens;

                return new FcmPushResult(invalidTokens: ['invalid-token']);
            });

        $actorId = UserId::generate();
        $this->handler($fcmPushSender)->handle(new SendPushNotificationCommand(
            userId: $userId->value(),
            title: 'Новое сообщение',
            body: 'Вам пришло сообщение',
            action: null,
            actor: new NotificationActorPayload(id: $actorId->value(), name: 'Иван', avatarUrl: 'https://cdn/a.jpg'),
        ));

        self::assertInstanceOf(NotificationPush::class, $capturedPush);
        self::assertSame('Новое сообщение', $capturedPush->title);
        self::assertNotNull($capturedPush->actor);
        self::assertSame($actorId->value(), $capturedPush->actor->id);
        self::assertSame('Иван', $capturedPush->actor->name);
        self::assertSame('https://cdn/a.jpg', $capturedPush->actor->avatarUrl);
        // Порядок задаётся репозиторием: ORDER BY id DESC по UUID v7; invalid-token создан позже, поэтому идёт первым.
        self::assertSame(['invalid-token', 'valid-token'], $capturedTokens);

        $remaining = $this->deviceTokenRepository()->findAllForUser($userId);
        self::assertCount(1, $remaining);
        self::assertInstanceOf(NotificationDeviceToken::class, $this->deviceTokenRepository()->findByToken(DeviceToken::fromString('valid-token')));
        self::assertNull($this->deviceTokenRepository()->findByToken(DeviceToken::fromString('invalid-token')));
    }

    public function testKeepsAllTokensWhenNoneInvalid(): void
    {
        $userId = UserId::generate();
        $this->persistToken($userId, 'valid-token');

        $fcmPushSender = $this->createStub(FcmPushSenderContract::class);
        $fcmPushSender->method('send')->willReturn(new FcmPushResult(invalidTokens: []));

        $this->handler($fcmPushSender)->handle(new SendPushNotificationCommand(
            userId: $userId->value(),
            title: 'Новое сообщение',
            body: 'Вам пришло сообщение',
            action: null,
            actor: null,
        ));

        self::assertCount(1, $this->deviceTokenRepository()->findAllForUser($userId));
    }

    public function testSkipsWhenNoTokens(): void
    {
        $fcmPushSender = $this->createMock(FcmPushSenderContract::class);
        $fcmPushSender->expects(self::never())->method('send');

        $this->handler($fcmPushSender)->handle(new SendPushNotificationCommand(
            userId: UserId::generate()->value(),
            title: 'Новое сообщение',
            body: 'Вам пришло сообщение',
            action: null,
            actor: null,
        ));
    }

    public function testSkipsPushWhenRecipientOnline(): void
    {
        $userId = UserId::generate();
        $this->persistToken($userId, 'valid-token');

        $fcmPushSender = $this->createMock(FcmPushSenderContract::class);
        $fcmPushSender->expects(self::never())->method('send');

        $onlinePresence = $this->createStub(OnlinePresenceContract::class);
        $onlinePresence->method('isOnline')->willReturn(true);

        $this->handler($fcmPushSender, $onlinePresence)->handle(new SendPushNotificationCommand(
            userId: $userId->value(),
            title: 'Новое сообщение',
            body: 'Вам пришло сообщение',
            action: null,
            actor: null,
        ));

        self::assertCount(1, $this->deviceTokenRepository()->findAllForUser($userId));
    }

    public function testSendsPushWhenRecipientOffline(): void
    {
        $userId = UserId::generate();
        $this->persistToken($userId, 'valid-token');

        $fcmPushSender = $this->createMock(FcmPushSenderContract::class);
        $fcmPushSender->expects(self::once())
            ->method('send')
            ->willReturn(new FcmPushResult(invalidTokens: []));

        $onlinePresence = $this->createStub(OnlinePresenceContract::class);
        $onlinePresence->method('isOnline')->willReturn(false);

        $this->handler($fcmPushSender, $onlinePresence)->handle(new SendPushNotificationCommand(
            userId: $userId->value(),
            title: 'Новое сообщение',
            body: 'Вам пришло сообщение',
            action: null,
            actor: null,
        ));

        self::assertCount(1, $this->deviceTokenRepository()->findAllForUser($userId));
    }

    private function handler(
        FcmPushSenderContract $fcmPushSender,
        OnlinePresenceContract|null $onlinePresence = null,
    ): SendPushNotificationHandler {
        return new SendPushNotificationHandler(
            notificationDeviceTokenRepository: $this->deviceTokenRepository(),
            fcmPushSender: $fcmPushSender,
            onlinePresence: $onlinePresence ?? $this->offlinePresence(),
            entityManager: $this->getContainer()->get(EntityManagerInterface::class),
            logger: new NullLogger(),
        );
    }

    private function offlinePresence(): OnlinePresenceContract
    {
        $onlinePresence = $this->createStub(OnlinePresenceContract::class);
        $onlinePresence->method('isOnline')->willReturn(false);

        return $onlinePresence;
    }

    private function persistToken(UserId $userId, string $token): void
    {
        $entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(NotificationDeviceToken::create(
            userId: $userId,
            token: DeviceToken::fromString($token),
            platform: DevicePlatform::Ios,
        ));
        $entityManager->run();
    }

    private function deviceTokenRepository(): NotificationDeviceTokenRepository
    {
        return $this->getContainer()->get(NotificationDeviceTokenRepository::class);
    }
}
