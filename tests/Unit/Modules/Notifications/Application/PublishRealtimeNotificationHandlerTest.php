<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Notifications\Application;

use App\Modules\Notifications\Application\Command\Realtime\PublishRealtimeNotification\PublishRealtimeNotificationCommand;
use App\Modules\Notifications\Application\Command\Realtime\PublishRealtimeNotification\PublishRealtimeNotificationHandler;
use App\Modules\Notifications\Application\Contract\CentrifugoServiceContract;
use App\Modules\Notifications\Application\Dto\NotificationActionPayload;
use App\Modules\Notifications\Application\Dto\NotificationActorPayload;
use App\Modules\Notifications\Application\Dto\RealtimeNotificationPayload;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class PublishRealtimeNotificationHandlerTest extends TestCase
{
    public function testPublishesToPersonalChannelWithPayload(): void
    {
        $capturedChannel = null;
        $capturedPayload = null;

        $centrifugoService = $this->createMock(CentrifugoServiceContract::class);
        $centrifugoService->expects(self::once())
            ->method('publish')
            ->willReturnCallback(function (string $channel, RealtimeNotificationPayload $payload) use (&$capturedChannel, &$capturedPayload): void {
                $capturedChannel = $channel;
                $capturedPayload = $payload;
            });

        new PublishRealtimeNotificationHandler($centrifugoService, new NullLogger())->handle(
            new PublishRealtimeNotificationCommand(
                userId: 'user-1',
                type: 'chat.message_received',
                title: 'Новое сообщение',
                body: 'Вам пришло сообщение',
                action: new NotificationActionPayload(actionType: 'chat', actionId: '42'),
                actor: new NotificationActorPayload(id: 'actor-1', name: 'Иван', avatarUrl: 'https://cdn/a.jpg'),
                createdAt: '2026-06-13T10:00:00+00:00',
            ),
        );

        self::assertSame('personal:#user_user-1', $capturedChannel);
        self::assertInstanceOf(RealtimeNotificationPayload::class, $capturedPayload);
        self::assertSame('chat.message_received', $capturedPayload->type);
        self::assertSame('Новое сообщение', $capturedPayload->title);
        self::assertNotNull($capturedPayload->action);
        self::assertSame('chat', $capturedPayload->action->actionType);
        self::assertNotNull($capturedPayload->actor);
        self::assertSame('actor-1', $capturedPayload->actor->id);
        self::assertSame('Иван', $capturedPayload->actor->name);
        self::assertSame('https://cdn/a.jpg', $capturedPayload->actor->avatarUrl);
    }
}
