<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Notifications\Repository;

use App\Modules\Notifications\Domain\Entity\Notification;
use App\Modules\Notifications\Domain\Entity\NotificationDeviceToken;
use App\Modules\Notifications\Domain\Entity\NotificationSetting;
use App\Modules\Notifications\Domain\Enum\DevicePlatform;
use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Modules\Notifications\Domain\Enum\NotificationSettingStatus;
use App\Modules\Notifications\Domain\ValueObject\DeviceToken;
use App\Modules\Notifications\Domain\ValueObject\NotificationAction;
use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Modules\Notifications\Domain\ValueObject\NotificationBody;
use App\Modules\Notifications\Domain\ValueObject\NotificationOutboxId;
use App\Modules\Notifications\Domain\ValueObject\NotificationTitle;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Modules\Notifications\Domain\Repository\NotificationDeviceTokenRepository;
use App\Modules\Notifications\Domain\Repository\NotificationRepository;
use App\Modules\Notifications\Domain\Repository\NotificationSettingRepository;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use Tests\DatabaseTestCase;

final class NotificationRepositoryTest extends DatabaseTestCase
{
    public function testStoresAndRestoresNotificationWithLink(): void
    {
        $userId = UserId::generate();
        $triggeredAt = new \DateTimeImmutable('2026-06-13 10:00:00');
        $notification = $this->createNotification(
            userId: $userId,
            action: NotificationAction::linkTo('chat', '42'),
            triggeredAt: $triggeredAt,
        );

        $this->persist($notification);
        $this->cleanOrmHeap();

        $restored = $this->notificationRepository()->findByIdForRecipient($notification->id, $userId);

        self::assertInstanceOf(Notification::class, $restored);
        self::assertTrue($notification->id->equals($restored->id));
        self::assertTrue($notification->outboxId->equals($restored->outboxId));
        self::assertSame('chat.message_received', $restored->type->value());
        self::assertSame('Новое сообщение', $restored->title->value());
        self::assertSame('Вам пришло сообщение', $restored->body->value());
        self::assertTrue($restored->action()->hasLink());
        self::assertSame(['actionType' => 'chat', 'actionId' => '42'], $restored->action()->jsonSerialize());
        self::assertFalse($restored->isRead());
        self::assertEquals($triggeredAt, $restored->createdAt);
    }

    public function testStoresNotificationWithoutLink(): void
    {
        $userId = UserId::generate();
        $notification = $this->createNotification(userId: $userId, action: NotificationAction::none());

        $this->persist($notification);
        $this->cleanOrmHeap();

        $restored = $this->notificationRepository()->findByIdForRecipient($notification->id, $userId);

        self::assertInstanceOf(Notification::class, $restored);
        self::assertFalse($restored->action()->hasLink());
        self::assertNull($restored->action()->jsonSerialize());
    }

    public function testMarkReadIsPersisted(): void
    {
        $userId = UserId::generate();
        $notification = $this->createNotification(userId: $userId);
        $this->persist($notification);

        $readAt = new \DateTimeImmutable('2026-06-13 11:00:00');
        $notification->markRead($readAt);
        $this->persist($notification);
        $this->cleanOrmHeap();

        $restored = $this->notificationRepository()->findByIdForRecipient($notification->id, $userId);

        self::assertInstanceOf(Notification::class, $restored);
        self::assertTrue($restored->isRead());
        self::assertEquals($readAt, $restored->readState->markedAt());
    }

    public function testFindByOutboxIdFindsAndMisses(): void
    {
        $notification = $this->createNotification(userId: UserId::generate());
        $this->persist($notification);
        $this->cleanOrmHeap();

        self::assertInstanceOf(
            Notification::class,
            $this->notificationRepository()->findByOutboxId($notification->outboxId),
        );
        self::assertNull($this->notificationRepository()->findByOutboxId(NotificationOutboxId::generate()));
    }

    public function testFindByIdForRecipientRejectsForeignOwner(): void
    {
        $notification = $this->createNotification(userId: UserId::generate());
        $this->persist($notification);
        $this->cleanOrmHeap();

        self::assertNull($this->notificationRepository()->findByIdForRecipient($notification->id, UserId::generate()));
    }

    public function testFindPageForRecipientOrdersByIdDescAndPaginates(): void
    {
        $userId = UserId::generate();
        $created = [];

        for ($index = 0; $index < 3; $index++) {
            $notification = $this->createNotification(userId: $userId);
            $this->persist($notification);
            $created[] = $notification;
        }

        // Чужое уведомление не попадает в страницу получателя.
        $this->persist($this->createNotification(userId: UserId::generate()));
        $this->cleanOrmHeap();

        $expectedIdsDesc = $this->idsDesc($created);

        $firstPage = $this->notificationRepository()->findPageForRecipient($userId, null, 2);
        self::assertSame(\array_slice($expectedIdsDesc, 0, 2), $this->ids($firstPage->all()));

        $lastOnFirstPage = $firstPage->last();
        self::assertInstanceOf(Notification::class, $lastOnFirstPage);
        $secondPage = $this->notificationRepository()->findPageForRecipient($userId, $lastOnFirstPage->id, 2);

        self::assertSame(\array_slice($expectedIdsDesc, 2), $this->ids($secondPage->all()));
    }

    public function testCountsUnreadOnly(): void
    {
        $userId = UserId::generate();
        $firstUnread = $this->createNotification(userId: $userId);
        $secondUnread = $this->createNotification(userId: $userId);
        $read = $this->createNotification(userId: $userId);
        $read->markRead(new \DateTimeImmutable('2026-06-13 11:00:00'));

        $this->persist($firstUnread);
        $this->persist($secondUnread);
        $this->persist($read);
        $this->cleanOrmHeap();

        self::assertSame(2, $this->notificationRepository()->countUnreadForRecipient($userId));
    }

    public function testOutboxIdIsUnique(): void
    {
        $outboxId = NotificationOutboxId::generate();

        $this->entityManager()->persist($this->createNotification(userId: UserId::generate(), outboxId: $outboxId));
        $this->entityManager()->persist($this->createNotification(userId: UserId::generate(), outboxId: $outboxId));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    public function testSettingRoundTripAndLookups(): void
    {
        $userId = UserId::generate();
        $type = NotificationTypeCode::fromString('chat.message_received');
        $enabled = NotificationSetting::create(
            userId: $userId,
            type: $type,
            channel: NotificationChannel::Push,
            status: NotificationSettingStatus::Enabled,
        );
        $disabled = NotificationSetting::create(
            userId: $userId,
            type: $type,
            channel: NotificationChannel::Realtime,
            status: NotificationSettingStatus::Disabled,
        );

        $this->persist($enabled);
        $this->persist($disabled);
        $this->cleanOrmHeap();

        $forType = $this->settingRepository()->findForUserAndType($userId, $type);
        self::assertCount(2, $forType);
        self::assertCount(2, $this->settingRepository()->findForUser($userId));

        $restoredEnabled = $this->settingRepository()->findOneForUserTypeChannel($userId, $type, NotificationChannel::Push);
        $restoredDisabled = $this->settingRepository()->findOneForUserTypeChannel($userId, $type, NotificationChannel::Realtime);

        self::assertInstanceOf(NotificationSetting::class, $restoredEnabled);
        self::assertInstanceOf(NotificationSetting::class, $restoredDisabled);
        self::assertTrue($restoredEnabled->isEnabled());
        self::assertFalse($restoredDisabled->isEnabled());
        self::assertNull(
            $this->settingRepository()->findOneForUserTypeChannel($userId, $type, NotificationChannel::Database),
        );
    }

    public function testSettingUserTypeChannelIsUnique(): void
    {
        $userId = UserId::generate();
        $type = NotificationTypeCode::fromString('chat.message_received');

        $this->entityManager()->persist(NotificationSetting::create(
            userId: $userId,
            type: $type,
            channel: NotificationChannel::Push,
            status: NotificationSettingStatus::Enabled,
        ));
        $this->entityManager()->persist(NotificationSetting::create(
            userId: $userId,
            type: $type,
            channel: NotificationChannel::Push,
            status: NotificationSettingStatus::Disabled,
        ));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    public function testDeviceTokenRoundTripReassignAndLookups(): void
    {
        $owner = UserId::generate();
        $deviceToken = NotificationDeviceToken::create(
            userId: $owner,
            token: DeviceToken::fromString('fcm-token'),
            platform: DevicePlatform::Ios,
        );
        $this->persist($deviceToken);
        $this->cleanOrmHeap();

        $restored = $this->deviceTokenRepository()->findByToken(DeviceToken::fromString('fcm-token'));
        self::assertInstanceOf(NotificationDeviceToken::class, $restored);
        self::assertTrue($owner->equals($restored->userId));
        self::assertSame(DevicePlatform::Ios, $restored->platform);
        self::assertCount(1, $this->deviceTokenRepository()->findAllForUser($owner));

        $newOwner = UserId::generate();
        $restored->reassignTo($newOwner, DevicePlatform::Android);
        $this->persist($restored);
        $this->cleanOrmHeap();

        $reassigned = $this->deviceTokenRepository()->findByToken(DeviceToken::fromString('fcm-token'));
        self::assertInstanceOf(NotificationDeviceToken::class, $reassigned);
        self::assertTrue($newOwner->equals($reassigned->userId));
        self::assertSame(DevicePlatform::Android, $reassigned->platform);
        self::assertCount(0, $this->deviceTokenRepository()->findAllForUser($owner));
        self::assertCount(1, $this->deviceTokenRepository()->findAllForUser($newOwner));
        self::assertNull($this->deviceTokenRepository()->findByToken(DeviceToken::fromString('missing-token')));
    }

    public function testTokenIsUnique(): void
    {
        $token = DeviceToken::fromString('fcm-token');

        $this->entityManager()->persist(NotificationDeviceToken::create(
            userId: UserId::generate(),
            token: $token,
            platform: DevicePlatform::Ios,
        ));
        $this->entityManager()->persist(NotificationDeviceToken::create(
            userId: UserId::generate(),
            token: $token,
            platform: DevicePlatform::Android,
        ));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    private function createNotification(
        UserId $userId,
        NotificationAction|null $action = null,
        NotificationOutboxId|null $outboxId = null,
        \DateTimeImmutable|null $triggeredAt = null,
    ): Notification {
        return Notification::create(
            outboxId: $outboxId ?? NotificationOutboxId::generate(),
            userId: $userId,
            type: NotificationTypeCode::fromString('chat.message_received'),
            title: NotificationTitle::fromString('Новое сообщение'),
            body: NotificationBody::fromString('Вам пришло сообщение'),
            action: $action ?? NotificationAction::none(),
            actor: NotificationActor::none(),
            triggeredAt: $triggeredAt ?? new \DateTimeImmutable('2026-06-13 10:00:00'),
        );
    }

    /**
     * @param list<Notification> $notifications
     *
     * @return list<string>
     */
    private function idsDesc(array $notifications): array
    {
        $ids = $this->ids($notifications);
        \rsort($ids);

        return $ids;
    }

    /**
     * @param list<Notification> $notifications
     *
     * @return list<string>
     */
    private function ids(array $notifications): array
    {
        return \array_map(static fn(Notification $notification): string => $notification->id->value(), $notifications);
    }

    private function persist(object $entity): void
    {
        $this->entityManager()->persist($entity);
        $this->entityManager()->run();
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    private function notificationRepository(): NotificationRepository
    {
        return $this->getContainer()->get(NotificationRepository::class);
    }

    private function settingRepository(): NotificationSettingRepository
    {
        return $this->getContainer()->get(NotificationSettingRepository::class);
    }

    private function deviceTokenRepository(): NotificationDeviceTokenRepository
    {
        return $this->getContainer()->get(NotificationDeviceTokenRepository::class);
    }
}
