<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Notifications\Application;

use App\Modules\Notifications\Application\Dto\NotificationActionPayload;
use App\Modules\Notifications\Application\Dto\NotificationActorPayload;
use App\Modules\Notifications\Application\Message\NotificationRequested;
use App\Modules\Outbox\Infrastructure\Serializer\ValinorOutboxMessageSerializer;
use PHPUnit\Framework\TestCase;

final class NotificationMessageSerializerTest extends TestCase
{
    public function testRoundTripsNotificationRequestedWithAction(): void
    {
        $serializer = new ValinorOutboxMessageSerializer();

        $serialized = $serializer->serialize(new NotificationRequested(
            userId: '0190f3b1-0000-7000-8000-000000000000',
            type: 'chat.message_received',
            title: 'Новое сообщение',
            body: 'Вам пришло сообщение',
            action: new NotificationActionPayload(actionType: 'chat', actionId: '42'),
            actor: new NotificationActorPayload(
                id: '0190f3b1-0000-7000-8000-000000000001',
                name: 'Иван',
                avatarMediaId: '0190f3b1-0000-7000-8000-000000000002',
            ),
            createdAt: '2026-06-13T10:00:00+00:00',
        ));
        $restored = $serializer->deserialize($serialized);

        self::assertSame(NotificationRequested::class, $serialized->type);
        self::assertJson($serialized->payload);
        self::assertInstanceOf(NotificationRequested::class, $restored);
        self::assertSame('chat.message_received', $restored->type);
        self::assertSame('Новое сообщение', $restored->title);
        self::assertNotNull($restored->action);
        self::assertSame('chat', $restored->action->actionType);
        self::assertSame('42', $restored->action->actionId);
        self::assertNotNull($restored->actor);
        self::assertSame('0190f3b1-0000-7000-8000-000000000001', $restored->actor->id);
        self::assertSame('Иван', $restored->actor->name);
        self::assertSame('0190f3b1-0000-7000-8000-000000000002', $restored->actor->avatarMediaId);
        self::assertSame('2026-06-13T10:00:00+00:00', $restored->createdAt);
    }

    public function testRoundTripsNotificationRequestedWithoutAction(): void
    {
        $serializer = new ValinorOutboxMessageSerializer();

        $serialized = $serializer->serialize(new NotificationRequested(
            userId: '0190f3b1-0000-7000-8000-000000000000',
            type: 'chat.message_received',
            title: 'Новое сообщение',
            body: 'Вам пришло сообщение',
            action: null,
            actor: null,
            createdAt: '2026-06-13T10:00:00+00:00',
        ));
        $restored = $serializer->deserialize($serialized);

        self::assertInstanceOf(NotificationRequested::class, $restored);
        self::assertNull($restored->action);
        self::assertNull($restored->actor);
    }
}
