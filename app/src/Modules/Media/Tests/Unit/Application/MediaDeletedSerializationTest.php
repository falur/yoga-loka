<?php

declare(strict_types=1);

namespace App\Modules\Media\Tests\Unit\Application;

use App\Modules\Media\Public\Event\MediaDeletedEvent;
use GianTiaga\SpiralOutbox\Store\JsonOutboxEventSerializer;
use PHPUnit\Framework\TestCase;

/**
 * Второе событие модуля: одно поле, но проверка та же — контракт пакета обязан вернуть его
 * без потерь.
 */
final class MediaDeletedSerializationTest extends TestCase
{
    public function testRoundTripsMediaId(): void
    {
        $serializer = new JsonOutboxEventSerializer();

        $serialized = $serializer->serialize(new MediaDeletedEvent(
            mediaId: '0192f2a0-0000-7000-8000-000000000001',
        ));
        $restored = $serializer->deserialize(
            eventClass: MediaDeletedEvent::class,
            payload: $serialized,
        );

        self::assertJson($serialized);
        self::assertInstanceOf(MediaDeletedEvent::class, $restored);
        self::assertSame('0192f2a0-0000-7000-8000-000000000001', $restored->mediaId);
    }
}
