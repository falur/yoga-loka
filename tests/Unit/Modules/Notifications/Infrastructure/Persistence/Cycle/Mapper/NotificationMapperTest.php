<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Notifications\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\Notifications\Domain\Entity\Notification;
use App\Modules\Notifications\Domain\ValueObject\NotificationAction;
use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Modules\Notifications\Domain\ValueObject\NotificationBody;
use App\Modules\Notifications\Domain\ValueObject\NotificationOutboxId;
use App\Modules\Notifications\Domain\ValueObject\NotificationTitle;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Mapper\NotificationMapper;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

/**
 * Переносит проверки трёх удалённых typecast-классов (NotificationActionTypeTypecast,
 * NotificationActionIdTypecast, NotificationReadStateTypecast) на Mapper: все три — простые
 * null-bridging колонки (nullable-колонка <-> non-null VO с null-object), правило переноса
 * значений колонок волны E. actor остаётся на отдельном NotificationActorTypecast (составной
 * JSON) и передаётся Mapper-ом без изменений — см. NotificationActorTypecastTest.
 */
final class NotificationMapperTest extends TestCase
{
    public function testMapsNotificationWithLinkToAndFromCycleEntity(): void
    {
        $mapper = new NotificationMapper();
        $triggeredAt = new \DateTimeImmutable('2026-06-13 10:00:00');
        $notification = Notification::create(
            outboxId: NotificationOutboxId::generate(),
            userId: UserId::generate(),
            type: NotificationTypeCode::fromString('chat.message_received'),
            title: NotificationTitle::fromString('Новое сообщение'),
            body: NotificationBody::fromString('Вам пришло сообщение'),
            action: NotificationAction::linkTo('chat', '42'),
            actor: NotificationActor::none(),
            triggeredAt: $triggeredAt,
        );

        $cycleEntity = $mapper->toCycleEntity($notification);

        self::assertSame($notification->id->value(), $cycleEntity->id);
        self::assertSame($notification->outboxId->value(), $cycleEntity->outboxId);
        self::assertSame($notification->userId->value(), $cycleEntity->userId);
        self::assertSame('chat.message_received', $cycleEntity->type);
        self::assertSame('chat', $cycleEntity->actionType);
        self::assertSame('42', $cycleEntity->actionId);
        self::assertNull($cycleEntity->readAt);
        self::assertSame($triggeredAt, $cycleEntity->createdAt);

        $restored = $mapper->toDomain($cycleEntity);

        self::assertTrue($notification->id->equals($restored->id));
        self::assertTrue($restored->action()->hasLink());
        self::assertSame(['actionType' => 'chat', 'actionId' => '42'], $restored->action()->jsonSerialize());
        self::assertFalse($restored->isRead());
    }

    public function testMapsNotificationWithoutLinkUsesNullActionColumns(): void
    {
        $mapper = new NotificationMapper();
        $notification = Notification::create(
            outboxId: NotificationOutboxId::generate(),
            userId: UserId::generate(),
            type: NotificationTypeCode::fromString('chat.message_received'),
            title: NotificationTitle::fromString('Новое сообщение'),
            body: NotificationBody::fromString('Вам пришло сообщение'),
            action: NotificationAction::none(),
            actor: NotificationActor::none(),
            triggeredAt: new \DateTimeImmutable('2026-06-13 10:00:00'),
        );

        $cycleEntity = $mapper->toCycleEntity($notification);
        self::assertNull($cycleEntity->actionType);
        self::assertNull($cycleEntity->actionId);

        $restored = $mapper->toDomain($cycleEntity);
        self::assertFalse($restored->action()->hasLink());
        self::assertNull($restored->action()->jsonSerialize());
    }

    public function testMapsReadStateNullAndSet(): void
    {
        $mapper = new NotificationMapper();
        $notification = Notification::create(
            outboxId: NotificationOutboxId::generate(),
            userId: UserId::generate(),
            type: NotificationTypeCode::fromString('chat.message_received'),
            title: NotificationTitle::fromString('Новое сообщение'),
            body: NotificationBody::fromString('Вам пришло сообщение'),
            action: NotificationAction::none(),
            actor: NotificationActor::none(),
            triggeredAt: new \DateTimeImmutable('2026-06-13 10:00:00'),
        );

        $unreadCycleEntity = $mapper->toCycleEntity($notification);
        self::assertNull($unreadCycleEntity->readAt);
        self::assertFalse($mapper->toDomain($unreadCycleEntity)->isRead());

        $readAt = new \DateTimeImmutable('2026-06-13 11:00:00');
        $notification->markRead($readAt);
        $readCycleEntity = $mapper->toCycleEntity($notification);

        self::assertSame($readAt, $readCycleEntity->readAt);
        $restored = $mapper->toDomain($readCycleEntity);
        self::assertTrue($restored->isRead());
        self::assertSame($readAt, $restored->readState->markedAt());
    }

    public function testToCycleEntityUpdatesGivenInstanceInPlace(): void
    {
        $mapper = new NotificationMapper();
        $notification = Notification::create(
            outboxId: NotificationOutboxId::generate(),
            userId: UserId::generate(),
            type: NotificationTypeCode::fromString('chat.message_received'),
            title: NotificationTitle::fromString('Новое сообщение'),
            body: NotificationBody::fromString('Вам пришло сообщение'),
            action: NotificationAction::none(),
            actor: NotificationActor::none(),
            triggeredAt: new \DateTimeImmutable('2026-06-13 10:00:00'),
        );

        $cycleEntity = $mapper->toCycleEntity($notification);
        $notification->markRead(new \DateTimeImmutable('2026-06-13 11:00:00'));
        $updatedCycleEntity = $mapper->toCycleEntity($notification, $cycleEntity);

        self::assertSame($cycleEntity, $updatedCycleEntity);
        self::assertNotNull($updatedCycleEntity->readAt);
    }
}
