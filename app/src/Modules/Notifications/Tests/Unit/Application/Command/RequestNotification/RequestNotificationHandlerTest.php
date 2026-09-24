<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Unit\Application\Command\RequestNotification;

use App\Modules\Notifications\Application\Command\RequestNotification\RequestNotificationCommand;
use App\Modules\Notifications\Application\Command\RequestNotification\RequestNotificationHandler;
use App\Modules\Notifications\Domain\ValueObject\NotificationAction;
use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Modules\Notifications\Domain\ValueObject\NotificationBody;
use App\Modules\Notifications\Domain\ValueObject\NotificationTitle;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Modules\Notifications\Infrastructure\Spiral\Registry\NotificationTypeRegistry;
use App\Modules\Notifications\Public\Event\NotificationRequestedEvent;
use GianTiaga\SpiralOutbox\IntegrationEventContract;
use GianTiaga\SpiralOutbox\OutboxEventStoreContract;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use App\Modules\Notifications\Tests\Unit\Application\Fixture\FixtureNotificationTypeDefinition;

final class RequestNotificationHandlerTest extends TestCase
{
    public function testStagesExactlyOneNotificationRequested(): void
    {
        $userId = UserId::generate();
        $actorId = UserId::generate();
        $avatarMediaId = UserId::generate()->value();
        $captured = null;

        $outboxStore = $this->createMock(OutboxEventStoreContract::class);
        $outboxStore->expects(self::once())
            ->method('add')
            ->willReturnCallback(static function (IntegrationEventContract $message) use (&$captured): string {
                $captured = $message;

                return 'outbox-1';
            });

        $this->handler($outboxStore)->handle($this->command(
            recipient: $userId,
            action: NotificationAction::linkTo('chat', '42'),
            actor: NotificationActor::of(userId: $actorId, name: 'Иван', avatarMediaId: $avatarMediaId),
        ));

        self::assertInstanceOf(NotificationRequestedEvent::class, $captured);
        self::assertSame($userId->value(), $captured->userId);
        self::assertSame('chat.message_received', $captured->type);
        self::assertSame('Новое сообщение', $captured->title);
        self::assertSame('Вам пришло сообщение', $captured->body);
        self::assertNotNull($captured->action);
        self::assertSame('chat', $captured->action->actionType);
        self::assertSame('42', $captured->action->actionId);
        self::assertNotNull($captured->actor);
        self::assertSame($actorId->value(), $captured->actor->id);
        self::assertSame('Иван', $captured->actor->name);
        self::assertSame($avatarMediaId, $captured->actor->avatarMediaId);
        self::assertNotSame('', $captured->createdAt);
    }

    public function testStagesNullActionWhenNoLink(): void
    {
        $captured = null;
        $outboxStore = $this->createStub(OutboxEventStoreContract::class);
        $outboxStore->method('add')->willReturnCallback(
            static function (IntegrationEventContract $message) use (&$captured): string {
                $captured = $message;

                return 'outbox-1';
            },
        );

        $this->handler($outboxStore)->handle($this->command(action: NotificationAction::none()));

        self::assertInstanceOf(NotificationRequestedEvent::class, $captured);
        self::assertNull($captured->action);
        self::assertNull($captured->actor);
    }

    public function testRejectsUnregisteredTypeWithoutStaging(): void
    {
        $outboxStore = $this->createMock(OutboxEventStoreContract::class);
        $outboxStore->expects(self::never())->method('add');

        $handler = new RequestNotificationHandler(
            typeCatalog: new NotificationTypeRegistry(),
            outboxEventStore: $outboxStore,
            logger: new NullLogger(),
        );

        $this->expectException(\DomainException::class);

        $handler->handle($this->command(action: NotificationAction::none()));
    }

    private function handler(OutboxEventStoreContract $outboxStore): RequestNotificationHandler
    {
        $registry = new NotificationTypeRegistry();
        $registry->register(FixtureNotificationTypeDefinition::allChannels('chat.message_received'));

        return new RequestNotificationHandler(
            typeCatalog: $registry,
            outboxEventStore: $outboxStore,
            logger: new NullLogger(),
        );
    }

    private function command(
        NotificationAction $action,
        NotificationActor|null $actor = null,
        UserId|null $recipient = null,
    ): RequestNotificationCommand {
        return new RequestNotificationCommand(
            recipient: $recipient ?? UserId::generate(),
            type: NotificationTypeCode::fromString('chat.message_received'),
            title: NotificationTitle::fromString('Новое сообщение'),
            body: NotificationBody::fromString('Вам пришло сообщение'),
            action: $action,
            actor: $actor ?? NotificationActor::none(),
        );
    }
}
