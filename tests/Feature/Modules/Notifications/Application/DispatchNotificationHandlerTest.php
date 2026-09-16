<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Notifications\Application;

use App\Modules\Notifications\Application\Command\Notification\DispatchNotification\DispatchNotificationCommand;
use App\Modules\Notifications\Application\Command\Notification\DispatchNotification\DispatchNotificationHandler;
use App\Modules\Notifications\Public\Contract\NotificationTypeDefinition;
use App\Modules\Notifications\Public\Dto\NotificationActionDto;
use App\Modules\Notifications\Public\Dto\NotificationActorDto;
use App\Modules\Notifications\Application\Exception\NotificationTypeRegistryException;
use App\Modules\Notifications\Public\Enum\NotificationChannel as PublicNotificationChannel;
use App\Modules\Notifications\Public\Event\NotificationPushRequestedEvent;
use App\Modules\Notifications\Public\Event\NotificationRealtimeRequestedEvent;
use App\Modules\Notifications\Domain\Collection\NotificationSettingCollection;
use App\Modules\Notifications\Domain\Entity\Notification;
use App\Modules\Notifications\Domain\Entity\NotificationSetting;
use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Modules\Notifications\Domain\Enum\NotificationSettingStatus;
use App\Modules\Notifications\Domain\ValueObject\NotificationOutboxId;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Modules\Notifications\Infrastructure\Spiral\Registry\NotificationTypeRegistry;
use App\Modules\Notifications\Domain\Repository\NotificationRepository;
use App\Modules\Notifications\Domain\Repository\NotificationSettingRepository;
use App\Modules\Outbox\Public\Contract\IntegrationEvent;
use App\Shared\Domain\ValueObject\UserId;
use Psr\Log\NullLogger;
use Tests\DatabaseTestCase;
use Tests\Support\Notifications\FixtureNotificationTypeDefinition;
use Tests\Support\Notifications\RecordingOutboxEventStore;

final class DispatchNotificationHandlerTest extends DatabaseTestCase
{
    private const string TYPE = 'chat.message_received';

    public function testAllChannelsDefaultCreatesInboxAndStagesPushAndRealtime(): void
    {
        $userId = UserId::generate();
        $outboxId = NotificationOutboxId::generate();
        $store = new RecordingOutboxEventStore();

        $this->handler(FixtureNotificationTypeDefinition::allChannels(), $store)->handle(
            $this->command($outboxId, $userId, action: new NotificationActionDto('chat', '42')),
        );

        $inbox = $this->notificationRepository()->findByOutboxId($outboxId);
        self::assertInstanceOf(Notification::class, $inbox);
        self::assertTrue($inbox->action()->hasLink());
        self::assertSame(1, $store->countOf(NotificationPushRequestedEvent::class));
        self::assertSame(1, $store->countOf(NotificationRealtimeRequestedEvent::class));
    }

    public function testActorIsStoredInInboxAndStagedToPushAndRealtime(): void
    {
        $userId = UserId::generate();
        $actorId = UserId::generate();
        $avatarMediaId = UserId::generate()->value();
        $outboxId = NotificationOutboxId::generate();
        $store = new RecordingOutboxEventStore();

        $this->handler(FixtureNotificationTypeDefinition::allChannels(), $store)->handle(
            $this->command($outboxId, $userId, actor: new NotificationActorDto(
                id: $actorId->value(),
                name: 'Иван',
                avatarMediaId: $avatarMediaId,
            )),
        );

        $inbox = $this->notificationRepository()->findByOutboxId($outboxId);
        self::assertInstanceOf(Notification::class, $inbox);
        self::assertSame($actorId->value(), $inbox->actor->presentId());
        self::assertSame('Иван', $inbox->actor->presentName());
        self::assertSame($avatarMediaId, $inbox->actor->presentAvatarMediaId());

        $push = $this->messageOf($store, NotificationPushRequestedEvent::class);
        self::assertInstanceOf(NotificationPushRequestedEvent::class, $push);
        self::assertNotNull($push->actor);
        self::assertSame($actorId->value(), $push->actor->id);
        self::assertSame('Иван', $push->actor->name);
        self::assertSame($avatarMediaId, $push->actor->avatarMediaId);

        $realtime = $this->messageOf($store, NotificationRealtimeRequestedEvent::class);
        self::assertInstanceOf(NotificationRealtimeRequestedEvent::class, $realtime);
        self::assertNotNull($realtime->actor);
        self::assertSame($actorId->value(), $realtime->actor->id);
        self::assertSame($avatarMediaId, $realtime->actor->avatarMediaId);
    }

    public function testSettingDisablesPushChannel(): void
    {
        $userId = UserId::generate();
        $outboxId = NotificationOutboxId::generate();
        $store = new RecordingOutboxEventStore();

        $this->persistSetting($userId, NotificationChannel::Push, NotificationSettingStatus::Disabled);

        $this->handler(FixtureNotificationTypeDefinition::allChannels(), $store)->handle(
            $this->command($outboxId, $userId),
        );

        self::assertInstanceOf(Notification::class, $this->notificationRepository()->findByOutboxId($outboxId));
        self::assertSame(0, $store->countOf(NotificationPushRequestedEvent::class));
        self::assertSame(1, $store->countOf(NotificationRealtimeRequestedEvent::class));
    }

    public function testDefaultsApplyWhenNoSetting(): void
    {
        $userId = UserId::generate();
        $outboxId = NotificationOutboxId::generate();
        $store = new RecordingOutboxEventStore();
        $definition = FixtureNotificationTypeDefinition::withDefaultChannels(
            self::TYPE,
            PublicNotificationChannel::Database,
            PublicNotificationChannel::Push,
        );

        $this->handler($definition, $store)->handle($this->command($outboxId, $userId));

        self::assertInstanceOf(Notification::class, $this->notificationRepository()->findByOutboxId($outboxId));
        self::assertSame(1, $store->countOf(NotificationPushRequestedEvent::class));
        self::assertSame(0, $store->countOf(NotificationRealtimeRequestedEvent::class));
    }

    public function testRepeatedDispatchIsNoOpWhenDatabaseEnabled(): void
    {
        $userId = UserId::generate();
        $outboxId = NotificationOutboxId::generate();
        $command = $this->command($outboxId, $userId);

        $this->handler(FixtureNotificationTypeDefinition::allChannels(), new RecordingOutboxEventStore())->handle($command);

        $secondStore = new RecordingOutboxEventStore();
        $this->handler(FixtureNotificationTypeDefinition::allChannels(), $secondStore)->handle($command);

        self::assertSame([], $secondStore->messages);
        self::assertInstanceOf(Notification::class, $this->notificationRepository()->findByOutboxId($outboxId));
    }

    public function testRepeatedDispatchRestagesWhenDatabaseDisabled(): void
    {
        $userId = UserId::generate();
        $outboxId = NotificationOutboxId::generate();
        $command = $this->command($outboxId, $userId);
        $definition = FixtureNotificationTypeDefinition::withDefaultChannels(self::TYPE, PublicNotificationChannel::Push);

        $firstStore = new RecordingOutboxEventStore();
        $this->handler($definition, $firstStore)->handle($command);

        $secondStore = new RecordingOutboxEventStore();
        $this->handler($definition, $secondStore)->handle($command);

        self::assertNull($this->notificationRepository()->findByOutboxId($outboxId));
        self::assertSame(1, $firstStore->countOf(NotificationPushRequestedEvent::class));
        self::assertSame(1, $secondStore->countOf(NotificationPushRequestedEvent::class));
    }

    public function testInboxKeepsTriggerCreatedAt(): void
    {
        $userId = UserId::generate();
        $outboxId = NotificationOutboxId::generate();
        $createdAt = '2026-06-13T09:30:00+00:00';

        $this->handler(FixtureNotificationTypeDefinition::allChannels(), new RecordingOutboxEventStore())->handle(
            $this->command($outboxId, $userId, createdAt: $createdAt),
        );

        $inbox = $this->notificationRepository()->findByOutboxId($outboxId);
        self::assertInstanceOf(Notification::class, $inbox);
        self::assertEquals(new \DateTimeImmutable($createdAt), $inbox->createdAt);
    }

    public function testRejectsUnknownType(): void
    {
        $registry = new NotificationTypeRegistry();

        $this->expectException(NotificationTypeRegistryException::class);

        $this->handlerWithRegistry($registry, new RecordingOutboxEventStore())->handle(
            $this->command(NotificationOutboxId::generate(), UserId::generate()),
        );
    }

    private function command(
        NotificationOutboxId $outboxId,
        UserId $userId,
        NotificationActionDto|null $action = null,
        NotificationActorDto|null $actor = null,
        string $createdAt = '2026-06-13T10:00:00+00:00',
    ): DispatchNotificationCommand {
        return new DispatchNotificationCommand(
            outboxId: $outboxId->value(),
            userId: $userId->value(),
            type: self::TYPE,
            title: 'Новое сообщение',
            body: 'Вам пришло сообщение',
            action: $action,
            actor: $actor,
            createdAt: $createdAt,
        );
    }

    private function handler(
        NotificationTypeDefinition $definition,
        RecordingOutboxEventStore $store,
    ): DispatchNotificationHandler {
        $registry = new NotificationTypeRegistry();
        $registry->register($definition);

        return $this->handlerWithRegistry($registry, $store);
    }

    private function handlerWithRegistry(
        NotificationTypeRegistry $registry,
        RecordingOutboxEventStore $store,
    ): DispatchNotificationHandler {
        return new DispatchNotificationHandler(
            notificationRepository: $this->notificationRepository(),
            notificationSettingRepository: $this->getContainer()->get(NotificationSettingRepository::class),
            typeCatalog: $registry,
            integrationEventStore: $store,
            logger: new NullLogger(),
        );
    }

    private function persistSetting(UserId $userId, NotificationChannel $channel, NotificationSettingStatus $status): void
    {
        $setting = NotificationSetting::create(
            userId: $userId,
            type: NotificationTypeCode::fromString(self::TYPE),
            channel: $channel,
            status: $status,
        );
        $this->getContainer()->get(NotificationSettingRepository::class)
            ->saveAll(new NotificationSettingCollection([$setting]));
    }

    private function notificationRepository(): NotificationRepository
    {
        return $this->getContainer()->get(NotificationRepository::class);
    }

    /**
     * @param class-string $messageClass
     */
    private function messageOf(RecordingOutboxEventStore $store, string $messageClass): IntegrationEvent|null
    {
        foreach ($store->messages as $message) {
            if ($message::class === $messageClass) {
                return $message;
            }
        }

        return null;
    }
}
