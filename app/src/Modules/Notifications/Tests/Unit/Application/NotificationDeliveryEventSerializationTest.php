<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Unit\Application;

use App\Modules\Notifications\Public\Dto\NotificationActionDto;
use App\Modules\Notifications\Public\Dto\NotificationActorDto;
use App\Modules\Notifications\Public\Event\NotificationPushRequestedEvent;
use App\Modules\Notifications\Public\Event\NotificationRealtimeRequestedEvent;
use GianTiaga\SpiralOutbox\Store\JsonOutboxEventSerializer;
use PHPUnit\Framework\TestCase;

/**
 * Два события доставки модуля: push и realtime. Оба несут вложенные DTO перехода и автора,
 * поэтому проверяется именно восстановление вложенных объектов контрактом пакета.
 */
final class NotificationDeliveryEventSerializationTest extends TestCase
{
    public function testRoundTripsPushRequested(): void
    {
        $serializer = new JsonOutboxEventSerializer();

        $serialized = $serializer->serialize(new NotificationPushRequestedEvent(
            userId: '0190f3b1-0000-7000-8000-000000000000',
            type: 'chat.message_received',
            title: 'Новое сообщение',
            body: 'Вам пришло сообщение',
            action: new NotificationActionDto(actionType: 'chat', actionId: '42'),
            actor: new NotificationActorDto(
                id: '0190f3b1-0000-7000-8000-000000000001',
                name: 'Иван',
                avatarMediaId: null,
            ),
            createdAt: '2026-06-13T10:00:00+00:00',
        ));
        $restored = $serializer->deserialize(
            eventClass: NotificationPushRequestedEvent::class,
            payload: $serialized,
        );

        self::assertInstanceOf(NotificationPushRequestedEvent::class, $restored);
        self::assertNotNull($restored->action);
        self::assertSame('42', $restored->action->actionId);
        self::assertNotNull($restored->actor);
        self::assertNull($restored->actor->avatarMediaId);
        self::assertSame('2026-06-13T10:00:00+00:00', $restored->createdAt);
    }

    public function testRoundTripsRealtimeRequested(): void
    {
        $serializer = new JsonOutboxEventSerializer();

        $serialized = $serializer->serialize(new NotificationRealtimeRequestedEvent(
            userId: '0190f3b1-0000-7000-8000-000000000000',
            type: 'chat.message_received',
            title: 'Новое сообщение',
            body: 'Вам пришло сообщение',
            action: null,
            actor: null,
            createdAt: '2026-06-13T10:00:00+00:00',
        ));
        $restored = $serializer->deserialize(
            eventClass: NotificationRealtimeRequestedEvent::class,
            payload: $serialized,
        );

        self::assertInstanceOf(NotificationRealtimeRequestedEvent::class, $restored);
        self::assertSame('0190f3b1-0000-7000-8000-000000000000', $restored->userId);
        self::assertNull($restored->action);
        self::assertNull($restored->actor);
    }
}
