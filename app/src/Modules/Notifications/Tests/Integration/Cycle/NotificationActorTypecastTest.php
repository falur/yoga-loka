<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Integration\Cycle;

use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Typecast\NotificationActorTypecast;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

/**
 * NotificationActorTypecast — один из двух (наряду с NotificationSettingStatusTypecast) из пяти
 * typecast-классов Notifications, отнесённых ко второй категории «Правила переноса значений
 * колонок»: колонка actor хранит составной JSON (снимок автора {id, name, avatarMediaId}), а не
 * один примитив, поэтому Cycle обязан собрать его до Mapper-а. Остальные три
 * (NotificationActionIdTypecast, NotificationActionTypeTypecast, NotificationReadStateTypecast)
 * удалены — их проверки перенесены в NotificationMapperTest.
 */
final class NotificationActorTypecastTest extends TestCase
{
    public function testHandlesNullableJsonSnapshot(): void
    {
        $userId = UserId::generate();
        $avatarMediaId = UserId::generate()->value();
        $snapshot = \json_encode(
            ['id' => $userId->value(), 'name' => 'Иван', 'avatarMediaId' => $avatarMediaId],
            \JSON_THROW_ON_ERROR,
        );

        self::assertFalse(NotificationActorTypecast::castDatabaseValue(null)->isPresent());

        $restored = NotificationActorTypecast::castDatabaseValue($snapshot);
        self::assertTrue($restored->isPresent());
        self::assertSame($userId->value(), $restored->presentId());
        self::assertSame('Иван', $restored->presentName());
        self::assertSame($avatarMediaId, $restored->presentAvatarMediaId());

        $uncast = NotificationActorTypecast::uncastValue(
            NotificationActor::of(userId: $userId, name: 'Иван', avatarMediaId: $avatarMediaId),
        );
        self::assertIsString($uncast);
        self::assertSame(
            ['id' => $userId->value(), 'name' => 'Иван', 'avatarMediaId' => $avatarMediaId],
            \json_decode($uncast, associative: true, flags: \JSON_THROW_ON_ERROR),
        );

        self::assertNull(NotificationActorTypecast::uncastValue(NotificationActor::none()));
        self::assertNull(NotificationActorTypecast::uncastValue(null));
    }

    public function testHandlesSnapshotWithoutAvatar(): void
    {
        $userId = UserId::generate();

        // avatarMediaId = null в JSON: автор есть, но без аватара.
        $restored = NotificationActorTypecast::castDatabaseValue(\json_encode(
            ['id' => $userId->value(), 'name' => 'Иван', 'avatarMediaId' => null],
            \JSON_THROW_ON_ERROR,
        ));
        self::assertTrue($restored->isPresent());
        self::assertNull($restored->presentAvatarMediaId());

        // Ключа avatarMediaId нет вовсе — трактуем так же: аватара нет.
        $withoutKey = NotificationActorTypecast::castDatabaseValue(\json_encode(
            ['id' => $userId->value(), 'name' => 'Иван'],
            \JSON_THROW_ON_ERROR,
        ));
        self::assertNull($withoutKey->presentAvatarMediaId());

        $uncast = NotificationActorTypecast::uncastValue(
            NotificationActor::of(userId: $userId, name: 'Иван', avatarMediaId: null),
        );
        self::assertIsString($uncast);
        self::assertSame(
            ['id' => $userId->value(), 'name' => 'Иван', 'avatarMediaId' => null],
            \json_decode($uncast, associative: true, flags: \JSON_THROW_ON_ERROR),
        );
    }

    public function testRejectsNonObjectJson(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        NotificationActorTypecast::castDatabaseValue('"plain-string"');
    }

    public function testRejectsMalformedSnapshot(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // id отсутствует — обязательные поля снимка (id, name) должны быть строками.
        NotificationActorTypecast::castDatabaseValue(
            \json_encode(['name' => 'Иван'], \JSON_THROW_ON_ERROR),
        );
    }

    public function testRejectsNonStringAvatarMediaId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // avatarMediaId опционален (null допустим), но не другой тип: число — битый снимок.
        NotificationActorTypecast::castDatabaseValue(
            \json_encode(['id' => 'x', 'name' => 'Иван', 'avatarMediaId' => 123], \JSON_THROW_ON_ERROR),
        );
    }
}
