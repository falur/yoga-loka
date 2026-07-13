<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Notifications\Domain\Entity;

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
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class NotificationEntityTest extends TestCase
{
    public function testCreateInitializesNotification(): void
    {
        $triggeredAt = new \DateTimeImmutable('2026-06-13 10:00:00');
        $notification = $this->createNotification(
            action: NotificationAction::linkTo('chat', '42'),
            triggeredAt: $triggeredAt,
        );

        self::assertNotSame('', (string) $notification->id);
        self::assertFalse($notification->isRead());
        self::assertSame($triggeredAt, $notification->createdAt);
        self::assertSame($triggeredAt, $notification->updatedAt);
        self::assertTrue($notification->action()->hasLink());
        self::assertSame(['actionType' => 'chat', 'actionId' => '42'], $notification->action()->jsonSerialize());
    }

    public function testCreateWithoutActionHasNoLink(): void
    {
        $notification = $this->createNotification(action: NotificationAction::none());

        self::assertFalse($notification->action()->hasLink());
        self::assertNull($notification->action()->jsonSerialize());
    }

    public function testCreateStoresActorSnapshot(): void
    {
        $actorId = UserId::generate();
        $avatarMediaId = UserId::generate()->value();
        $notification = $this->createNotification(
            actor: NotificationActor::of(userId: $actorId, name: 'Иван', avatarMediaId: $avatarMediaId),
        );

        self::assertTrue($notification->actor->isPresent());
        self::assertSame($actorId->value(), $notification->actor->presentId());
        self::assertSame('Иван', $notification->actor->presentName());
        self::assertSame($avatarMediaId, $notification->actor->presentAvatarMediaId());
    }

    public function testCreateWithoutActorHasNoActor(): void
    {
        $notification = $this->createNotification(actor: NotificationActor::none());

        self::assertFalse($notification->actor->isPresent());
        self::assertNull($notification->actor->jsonSerialize());
    }

    public function testMarkReadSetsReadStateAndIsIdempotent(): void
    {
        $notification = $this->createNotification();
        $readAt = new \DateTimeImmutable('2026-06-13 11:00:00');

        $notification->markRead($readAt);
        self::assertTrue($notification->isRead());
        self::assertSame($readAt, $notification->readState->markedAt());
        self::assertSame($readAt, $notification->updatedAt);

        // Повторная отметка прочитанного — no-op: дата прочтения не переписывается.
        $notification->markRead($readAt->modify('+1 hour'));
        self::assertSame($readAt, $notification->readState->markedAt());
    }

    public function testSettingCreateEnableDisable(): void
    {
        $setting = NotificationSetting::create(
            userId: UserId::generate(),
            type: NotificationTypeCode::fromString('chat.message_received'),
            channel: NotificationChannel::Push,
            status: NotificationSettingStatus::Enabled,
        );

        self::assertTrue($setting->isEnabled());

        $setting->disable();
        self::assertFalse($setting->isEnabled());
        self::assertSame(NotificationSettingStatus::Disabled, $setting->status);

        $setting->enable();
        self::assertTrue($setting->isEnabled());
        self::assertSame(NotificationSettingStatus::Enabled, $setting->status);
    }

    public function testDeviceTokenCreateAndReassign(): void
    {
        $owner = UserId::generate();
        $deviceToken = NotificationDeviceToken::create(
            userId: $owner,
            token: DeviceToken::fromString('fcm-token'),
            platform: DevicePlatform::Ios,
        );

        self::assertTrue($owner->equals($deviceToken->userId));
        self::assertSame(DevicePlatform::Ios, $deviceToken->platform);

        $newOwner = UserId::generate();
        $deviceToken->reassignTo($newOwner, DevicePlatform::Android);

        self::assertTrue($newOwner->equals($deviceToken->userId));
        self::assertSame(DevicePlatform::Android, $deviceToken->platform);
        self::assertTrue($deviceToken->token->equals(DeviceToken::fromString('fcm-token')));
    }

    private function createNotification(
        NotificationAction|null $action = null,
        NotificationActor|null $actor = null,
        \DateTimeImmutable|null $triggeredAt = null,
    ): Notification {
        return Notification::create(
            outboxId: NotificationOutboxId::generate(),
            userId: UserId::generate(),
            type: NotificationTypeCode::fromString('chat.message_received'),
            title: NotificationTitle::fromString('Новое сообщение'),
            body: NotificationBody::fromString('Вам пришло сообщение'),
            action: $action ?? NotificationAction::none(),
            actor: $actor ?? NotificationActor::none(),
            triggeredAt: $triggeredAt ?? new \DateTimeImmutable('2026-06-13 10:00:00'),
        );
    }
}
