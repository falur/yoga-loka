<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Integration\Spiral;

use App\Modules\Notifications\Public\Dto\NotificationActionDto;
use App\Modules\Notifications\Application\Contract\NotificationPush;
use App\Modules\Notifications\Application\Contract\NotificationPushActorPayload;
use App\Modules\Notifications\Application\Exception\FcmPushFailedException;
use App\Modules\Notifications\Infrastructure\Client\KreaitFcmPushSender;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Message;
use Kreait\Firebase\Messaging\MulticastSendReport;
use PHPUnit\Framework\TestCase;

final class KreaitFcmPushSenderTest extends TestCase
{
    public function testReturnsEmptyResultWhenNoInvalidTokens(): void
    {
        $messaging = $this->createStub(Messaging::class);
        $messaging->method('sendMulticast')->willReturn(MulticastSendReport::withItems([]));

        $result = new KreaitFcmPushSender($messaging)->send(
            new NotificationPush(title: 'Заголовок', body: 'Текст', action: null, actor: null),
            ['token-1', 'token-2'],
        );

        self::assertSame([], $result->invalidTokens);
    }

    public function testBuildsMessageWithDeepLinkAndActorData(): void
    {
        $capturedMessage = null;
        $messaging = $this->createMock(Messaging::class);
        $messaging->expects(self::once())
            ->method('sendMulticast')
            ->willReturnCallback(function (Message $message) use (&$capturedMessage): MulticastSendReport {
                $capturedMessage = $message;

                return MulticastSendReport::withItems([]);
            });

        new KreaitFcmPushSender($messaging)->send(
            new NotificationPush(
                title: 'Заголовок',
                body: 'Текст',
                action: new NotificationActionDto(actionType: 'chat', actionId: '42'),
                actor: new NotificationPushActorPayload(id: 'actor-1', name: 'Иван', avatarUrl: 'https://cdn/a.jpg'),
            ),
            ['token-1'],
        );

        self::assertInstanceOf(CloudMessage::class, $capturedMessage);
        $payload = $capturedMessage->jsonSerialize();
        self::assertSame('chat', $payload['data']['actionType']);
        self::assertSame('42', $payload['data']['actionId']);
        self::assertSame('actor-1', $payload['data']['actorId']);
        self::assertSame('Иван', $payload['data']['actorName']);
        self::assertSame('https://cdn/a.jpg', $payload['data']['actorAvatarUrl']);
    }

    public function testOmitsAvatarKeyWhenActorHasNoAvatar(): void
    {
        $capturedMessage = null;
        $messaging = $this->createMock(Messaging::class);
        $messaging->expects(self::once())
            ->method('sendMulticast')
            ->willReturnCallback(function (Message $message) use (&$capturedMessage): MulticastSendReport {
                $capturedMessage = $message;

                return MulticastSendReport::withItems([]);
            });

        new KreaitFcmPushSender($messaging)->send(
            new NotificationPush(
                title: 'Заголовок',
                body: 'Текст',
                action: null,
                actor: new NotificationPushActorPayload(id: 'actor-1', name: 'Иван', avatarUrl: null),
            ),
            ['token-1'],
        );

        self::assertInstanceOf(CloudMessage::class, $capturedMessage);
        $data = $capturedMessage->jsonSerialize()['data'];
        // Аватара нет -> id и имя есть, а ключ actorAvatarUrl в плоскую строковую карту не кладём.
        self::assertSame('actor-1', $data['actorId']);
        self::assertSame('Иван', $data['actorName']);
        self::assertArrayNotHasKey('actorAvatarUrl', $data);
    }

    public function testTransientFailureBecomesFcmPushFailedException(): void
    {
        $messaging = $this->createStub(Messaging::class);
        $messaging->method('sendMulticast')->willThrowException(
            new class ('FCM down') extends \RuntimeException implements MessagingException {
                /**
                 * @return array<mixed>
                 */
                public function errors(): array
                {
                    return [];
                }
            },
        );

        $this->expectException(FcmPushFailedException::class);

        new KreaitFcmPushSender($messaging)->send(
            new NotificationPush(title: 'Заголовок', body: 'Текст', action: null, actor: null),
            ['token-1'],
        );
    }
}
