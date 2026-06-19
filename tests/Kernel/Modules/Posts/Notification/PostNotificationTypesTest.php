<?php

declare(strict_types=1);

namespace Tests\Kernel\Modules\Posts\Notification;

use App\Modules\Notifications\Application\Contract\NotificationTypeRegistryContract;
use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Modules\Posts\Application\Notification\PostNotificationType;
use Tests\TestCase;

/**
 * Каждый вид уведомлений модуля Posts реально регистрируется PostsBootloader в реестре и резолвится
 * по коду через get() (а не только присутствует в all()); каналы по умолчанию совпадают с решением плана.
 */
final class PostNotificationTypesTest extends TestCase
{
    public function testAllSevenTypesResolveFromRegistryByCode(): void
    {
        $registry = $this->getContainer()->get(NotificationTypeRegistryContract::class);

        self::assertCount(7, PostNotificationType::cases());

        foreach (PostNotificationType::cases() as $type) {
            $resolved = $registry->get($type->code());

            self::assertTrue($resolved->code()->equals($type->code()));
        }
    }

    public function testRealtimeTypesEnableDatabasePushAndRealtime(): void
    {
        foreach ([
            PostNotificationType::PostMention,
            PostNotificationType::CommentMention,
            PostNotificationType::PostCommented,
            PostNotificationType::CommentReply,
        ] as $type) {
            $channels = $type->defaultChannels();

            self::assertTrue($channels->isEnabled(NotificationChannel::Database));
            self::assertTrue($channels->isEnabled(NotificationChannel::Push));
            self::assertTrue($channels->isEnabled(NotificationChannel::Realtime));
        }
    }

    public function testReactionTypesEnableDatabaseAndPushWithoutRealtime(): void
    {
        foreach ([
            PostNotificationType::PostLike,
            PostNotificationType::PostRepost,
            PostNotificationType::CommentLike,
        ] as $type) {
            $channels = $type->defaultChannels();

            self::assertTrue($channels->isEnabled(NotificationChannel::Database));
            self::assertTrue($channels->isEnabled(NotificationChannel::Push));
            self::assertFalse($channels->isEnabled(NotificationChannel::Realtime));
        }
    }
}
