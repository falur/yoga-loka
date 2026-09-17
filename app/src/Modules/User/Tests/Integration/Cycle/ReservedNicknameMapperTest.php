<?php

declare(strict_types=1);

namespace App\Modules\User\Tests\Integration\Cycle;

use App\Modules\User\Domain\Entity\ReservedNickname;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Infrastructure\Persistence\Cycle\Mapper\ReservedNicknameMapper;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

/**
 * Переносит проверки удалённого typecast-класса ReservedNicknameHolderTypecast на Mapper, куда
 * переехала его логика null-bridging между nullable-колонкой и value object с сентинелом.
 */
final class ReservedNicknameMapperTest extends TestCase
{
    public function testMapsAssignedHolder(): void
    {
        $mapper = new ReservedNicknameMapper();
        $userId = UserId::generate();
        $reservedNickname = ReservedNickname::create(UserNickname::fromString('reserved'));
        $reservedNickname->assignTo($userId);

        $cycleEntity = $mapper->toCycleEntity($reservedNickname);

        self::assertSame($userId->value(), $cycleEntity->assignedUserId);

        $restored = $mapper->toDomain($cycleEntity);

        self::assertTrue($restored->isAssignedTo($userId));
    }

    public function testMapsUnassignedHolderToNullColumn(): void
    {
        $mapper = new ReservedNicknameMapper();
        $reservedNickname = ReservedNickname::create(UserNickname::fromString('free'));

        $cycleEntity = $mapper->toCycleEntity($reservedNickname);

        self::assertNull($cycleEntity->assignedUserId);

        $restored = $mapper->toDomain($cycleEntity);

        self::assertTrue($restored->holder->isUnassigned());
    }
}
