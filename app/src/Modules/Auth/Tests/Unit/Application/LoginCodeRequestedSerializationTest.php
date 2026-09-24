<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Unit\Application;

use App\Modules\Auth\Public\Event\LoginCodeRequestedEvent;
use GianTiaga\SpiralOutbox\Store\JsonOutboxEventSerializer;
use PHPUnit\Framework\TestCase;

/**
 * Событие модуля проходит сериализацию и восстановление контрактом пакета без потери полей:
 * иначе Job получил бы неполное событие уже в рантайме.
 */
final class LoginCodeRequestedSerializationTest extends TestCase
{
    public function testRoundTripsAllFields(): void
    {
        $serializer = new JsonOutboxEventSerializer();

        $serialized = $serializer->serialize(new LoginCodeRequestedEvent(
            email: 'user@example.com',
            code: '123456',
            locale: 'ru',
        ));
        $restored = $serializer->deserialize(
            eventClass: LoginCodeRequestedEvent::class,
            payload: $serialized,
        );

        self::assertJson($serialized);
        self::assertInstanceOf(LoginCodeRequestedEvent::class, $restored);
        self::assertSame('user@example.com', $restored->email);
        self::assertSame('123456', $restored->code);
        self::assertSame('ru', $restored->locale);
    }
}
