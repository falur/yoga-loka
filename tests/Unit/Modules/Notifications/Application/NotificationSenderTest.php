<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Notifications\Application;

use App\Modules\Notifications\Application\Dto\NotificationContent;
use App\Modules\Notifications\Application\Message\NotificationRequested;
use App\Modules\Notifications\Application\NotificationSender;
use App\Modules\Notifications\Domain\ValueObject\NotificationAction;
use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Modules\Notifications\Domain\ValueObject\NotificationBody;
use App\Modules\Notifications\Domain\ValueObject\NotificationTitle;
use App\Modules\Notifications\Infrastructure\Registry\NotificationTypeRegistry;
use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
use App\Modules\Outbox\Application\Message\OutboxMessage;
use App\Modules\Outbox\Application\Message\StoredOutboxEventId;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tests\Support\Notifications\FixtureNotificationTypeDefinition;

final class NotificationSenderTest extends TestCase
{
    public function testStagesExactlyOneNotificationRequested(): void
    {
        $userId = UserId::generate();
        $actorId = UserId::generate();
        $captured = null;

        $outboxStore = $this->createMock(OutboxEventStoreContract::class);
        $outboxStore->expects(self::once())
            ->method('add')
            ->willReturnCallback(static function (OutboxMessage $message) use (&$captured): StoredOutboxEventId {
                $captured = $message;

                return StoredOutboxEventId::fromString('outbox-1');
            });

        $this->sender($outboxStore)->send(
            $userId,
            $this->content(
                NotificationAction::linkTo('chat', '42'),
                NotificationActor::of(userId: $actorId, name: 'Иван', avatarUrl: 'https://cdn/a.jpg'),
            ),
        );

        self::assertInstanceOf(NotificationRequested::class, $captured);
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
        self::assertSame('https://cdn/a.jpg', $captured->actor->avatarUrl);
        self::assertNotSame('', $captured->createdAt);
    }

    public function testStagesNullActionWhenNoLink(): void
    {
        $captured = null;
        $outboxStore = $this->createStub(OutboxEventStoreContract::class);
        $outboxStore->method('add')->willReturnCallback(
            static function (OutboxMessage $message) use (&$captured): StoredOutboxEventId {
                $captured = $message;

                return StoredOutboxEventId::fromString('outbox-1');
            },
        );

        $this->sender($outboxStore)->send(UserId::generate(), $this->content(NotificationAction::none()));

        self::assertInstanceOf(NotificationRequested::class, $captured);
        self::assertNull($captured->action);
        self::assertNull($captured->actor);
    }

    public function testRejectsUnregisteredTypeWithoutStaging(): void
    {
        $outboxStore = $this->createMock(OutboxEventStoreContract::class);
        $outboxStore->expects(self::never())->method('add');

        $registry = new NotificationTypeRegistry();
        $sender = new NotificationSender(
            typeRegistry: $registry,
            outboxEventStore: $outboxStore,
            logger: new NullLogger(),
        );

        $this->expectException(\DomainException::class);

        $sender->send(UserId::generate(), $this->content(NotificationAction::none()));
    }

    private function sender(OutboxEventStoreContract $outboxStore): NotificationSender
    {
        $registry = new NotificationTypeRegistry();
        $registry->register(FixtureNotificationTypeDefinition::allChannels('chat.message_received'));

        return new NotificationSender(
            typeRegistry: $registry,
            outboxEventStore: $outboxStore,
            logger: new NullLogger(),
        );
    }

    private function content(NotificationAction $action, NotificationActor|null $actor = null): NotificationContent
    {
        return new NotificationContent(
            type: FixtureNotificationTypeDefinition::allChannels('chat.message_received'),
            title: NotificationTitle::fromString('Новое сообщение'),
            body: NotificationBody::fromString('Вам пришло сообщение'),
            action: $action,
            actor: $actor ?? NotificationActor::none(),
        );
    }
}
