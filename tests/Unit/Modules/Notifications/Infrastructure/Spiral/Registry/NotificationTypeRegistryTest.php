<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Notifications\Infrastructure\Spiral\Registry;

use App\Modules\Notifications\Application\Exception\NotificationTypeRegistryException;
use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Modules\Notifications\Infrastructure\Spiral\Registry\NotificationTypeRegistry;
use PHPUnit\Framework\TestCase;
use Tests\Support\Notifications\FixtureNotificationTypeDefinition;

final class NotificationTypeRegistryTest extends TestCase
{
    public function testRegistersAndReturnsDefinitions(): void
    {
        $registry = new NotificationTypeRegistry();
        $chat = FixtureNotificationTypeDefinition::allChannels('chat.message_received');
        $post = FixtureNotificationTypeDefinition::withDefaultChannels('post.commented', NotificationChannel::Database);

        $registry->register($chat, $post);

        self::assertCount(2, $registry->all());
        self::assertSame($chat, $registry->get(NotificationTypeCode::fromString('chat.message_received')));
        self::assertSame($post, $registry->get(NotificationTypeCode::fromString('post.commented')));
    }

    public function testRejectsDuplicateRegistration(): void
    {
        $registry = new NotificationTypeRegistry();
        $registry->register(FixtureNotificationTypeDefinition::allChannels('chat.message_received'));

        $this->expectException(NotificationTypeRegistryException::class);

        $registry->register(FixtureNotificationTypeDefinition::allChannels('chat.message_received'));
    }

    public function testGetRejectsUnknownType(): void
    {
        $registry = new NotificationTypeRegistry();

        $this->expectException(NotificationTypeRegistryException::class);

        $registry->get(NotificationTypeCode::fromString('chat.message_received'));
    }
}
