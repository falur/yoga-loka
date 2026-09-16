<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Notifications\Application;

use App\Modules\Notifications\Application\Command\Push\SendPushNotification\SendPushNotificationCommand;
use App\Modules\Notifications\Application\Command\Push\SendPushNotification\SendPushNotificationHandler;
use App\Modules\Notifications\Application\Contract\FcmPushSenderContract;
use App\Modules\Notifications\Application\Contract\OnlinePresenceContract;
use App\Modules\Notifications\Application\Contract\FcmPushResult;
use App\Modules\Notifications\Public\Dto\NotificationActorDto;
use App\Modules\Notifications\Application\Contract\NotificationPush;
use App\Modules\Notifications\Domain\Entity\NotificationDeviceToken;
use App\Modules\Notifications\Domain\Enum\DevicePlatform;
use App\Modules\Notifications\Domain\ValueObject\DeviceToken;
use App\Modules\Notifications\Domain\Repository\NotificationDeviceTokenRepository;
use App\Shared\Domain\ValueObject\UserId;
use Psr\Log\NullLogger;
use Tests\DatabaseTestCase;
use Tests\Support\Media\PersistsMedia;

final class SendPushNotificationHandlerTest extends DatabaseTestCase
{
    use PersistsMedia;

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
        $avatarMedia = $this->persistReadyPublicMedia();
        $this->handler($fcmPushSender)->handle(new SendPushNotificationCommand(
            userId: $userId->value(),
            title: 'Новое сообщение',
            body: 'Вам пришло сообщение',
            action: null,
            actor: new NotificationActorDto(id: $actorId->value(), name: 'Иван', avatarMediaId: $avatarMedia->id->value()),
        ));

        self::assertInstanceOf(NotificationPush::class, $capturedPush);
        self::assertSame('Новое сообщение', $capturedPush->title);
        self::assertNotNull($capturedPush->actor);
        self::assertSame($actorId->value(), $capturedPush->actor->id);
        self::assertSame('Иван', $capturedPush->actor->name);
        // Аватар хранится как id медиа — для push резолвится в одну ссылку (original) к моменту отправки.
        self::assertSame(self::STUBBED_MEDIA_URL, $capturedPush->actor->avatarUrl);
        // Порядок задаётся репозиторием: ORDER BY id DESC по UUID v7; invalid-token создан позже, поэтому идёт первым.
        self::assertSame(['invalid-token', 'valid-token'], $capturedTokens);

        $remaining = $this->deviceTokenRepository()->findAllForUser($userId);
        self::assertCount(1, $remaining);
        self::assertInstanceOf(NotificationDeviceToken::class, $this->deviceTokenRepository()->findByToken(DeviceToken::fromString('valid-token')));
        self::assertNull($this->deviceTokenRepository()->findByToken(DeviceToken::fromString('invalid-token')));
    }

    public function testResolvesAvatarUrlToNullWhenMediaUnavailable(): void
    {
        $userId = UserId::generate();
        $this->persistToken($userId, 'valid-token');
        $notReadyMedia = $this->persistNotReadyMedia();

        $capturedPush = $this->capturePush(new SendPushNotificationCommand(
            userId: $userId->value(),
            title: 'Новое сообщение',
            body: 'Вам пришло сообщение',
            action: null,
            actor: new NotificationActorDto(id: UserId::generate()->value(), name: 'Иван', avatarMediaId: $notReadyMedia->id->value()),
        ));

        self::assertNotNull($capturedPush->actor);
        // Медиа не финализировано -> ссылки нет; в FCM data ключ actorAvatarUrl не попадёт.
        self::assertNull($capturedPush->actor->avatarUrl);
    }

    public function testResolvesAvatarUrlToNullWhenActorHasNoAvatarMedia(): void
    {
        $userId = UserId::generate();
        $this->persistToken($userId, 'valid-token');

        $capturedPush = $this->capturePush(new SendPushNotificationCommand(
            userId: $userId->value(),
            title: 'Новое сообщение',
            body: 'Вам пришло сообщение',
            action: null,
            actor: new NotificationActorDto(id: UserId::generate()->value(), name: 'Иван', avatarMediaId: null),
        ));

        self::assertNotNull($capturedPush->actor);
        self::assertNull($capturedPush->actor->avatarUrl);
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

    private function capturePush(SendPushNotificationCommand $command): NotificationPush
    {
        $capturedPush = null;
        $fcmPushSender = $this->createMock(FcmPushSenderContract::class);
        $fcmPushSender->expects(self::once())
            ->method('send')
            ->willReturnCallback(function (NotificationPush $push, array $tokens) use (&$capturedPush): FcmPushResult {
                $capturedPush = $push;

                return new FcmPushResult(invalidTokens: []);
            });

        $this->handler($fcmPushSender)->handle($command);

        self::assertInstanceOf(NotificationPush::class, $capturedPush);

        return $capturedPush;
    }

    private function handler(
        FcmPushSenderContract $fcmPushSender,
        OnlinePresenceContract|null $onlinePresence = null,
    ): SendPushNotificationHandler {
        return new SendPushNotificationHandler(
            notificationDeviceTokenRepository: $this->deviceTokenRepository(),
            fcmPushSender: $fcmPushSender,
            onlinePresence: $onlinePresence ?? $this->offlinePresence(),
            media: $this->stubbedMediaContract(),
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
        $this->deviceTokenRepository()->save(NotificationDeviceToken::create(
            userId: $userId,
            token: DeviceToken::fromString($token),
            platform: DevicePlatform::Ios,
        ));
    }

    private function deviceTokenRepository(): NotificationDeviceTokenRepository
    {
        return $this->getContainer()->get(NotificationDeviceTokenRepository::class);
    }
}
