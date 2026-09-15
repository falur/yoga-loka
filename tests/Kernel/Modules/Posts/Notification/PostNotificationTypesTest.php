<?php

declare(strict_types=1);

namespace Tests\Kernel\Modules\Posts\Notification;

use App\Modules\Notifications\Application\Contract\NotificationTypeCatalogContract;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Modules\Notifications\Public\Enum\NotificationChannel;
use App\Modules\Posts\Application\Notification\PostNotificationType;
use Tests\TestCase;

/**
 * Каждый вид уведомлений модуля Posts реально регистрируется PostsBootloader в каталоге и резолвится
 * по коду через get() (а не только присутствует в all()); каналы по умолчанию совпадают с решением плана.
 *
 * Проверка смотрит на внутренний каталог Notifications намеренно: публичный
 * NotificationTypeRegistryContract объявляет только регистрацию, чтение реестра соседям не
 * публикуется, поэтому увидеть результат регистрации можно лишь изнутри Notifications. Это сквозная
 * проверка приложения (каталог tests/), а не код модуля Posts, поэтому граница модулей не нарушена.
 */
final class PostNotificationTypesTest extends TestCase
{
    public function testAllSevenTypesResolveFromRegistryByCode(): void
    {
        $catalog = $this->getContainer()->get(NotificationTypeCatalogContract::class);

        self::assertCount(7, PostNotificationType::cases());

        foreach (PostNotificationType::cases() as $type) {
            $resolved = $catalog->get(NotificationTypeCode::fromString($type->code()));

            self::assertSame($type->code(), $resolved->code());
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

            self::assertTrue($channels->includes(NotificationChannel::Database));
            self::assertTrue($channels->includes(NotificationChannel::Push));
            self::assertTrue($channels->includes(NotificationChannel::Realtime));
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

            self::assertTrue($channels->includes(NotificationChannel::Database));
            self::assertTrue($channels->includes(NotificationChannel::Push));
            self::assertFalse($channels->includes(NotificationChannel::Realtime));
        }
    }
}
