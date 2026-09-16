<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\User\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\User\Domain\Entity\UserBan;
use App\Modules\User\Domain\ValueObject\BanExpiration;
use App\Modules\User\Domain\ValueObject\BanReason;
use App\Modules\User\Domain\ValueObject\BanUnbannedAt;
use App\Modules\User\Domain\ValueObject\BanUnbannedBy;
use App\Modules\User\Domain\ValueObject\BanUnbannedReason;
use App\Modules\User\Infrastructure\Persistence\Cycle\Mapper\UserBanMapper;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

/**
 * Переносит проверки удалённых typecast-классов UserBan (BanExpirationTypecast,
 * BanUnbannedAtTypecast, BanUnbannedByTypecast, BanUnbannedReasonTypecast) на Mapper, куда
 * переехала их логика null-bridging между nullable-колонкой и value object с сентинелом.
 */
final class UserBanMapperTest extends TestCase
{
    public function testMapsUnbannedBanWithAllOptionalValuesFilled(): void
    {
        $mapper = new UserBanMapper();
        $userId = UserId::generate();
        $bannedById = UserId::generate();
        $unbannedById = UserId::generate();
        $expiresAt = new \DateTimeImmutable('2026-07-01 00:00:00');
        $unbannedAt = new \DateTimeImmutable('2026-06-15 12:00:00');

        $userBan = UserBan::create(
            userId: $userId,
            bannedById: $bannedById,
            reason: BanReason::fromString('Причина'),
            expiration: BanExpiration::until($expiresAt),
        );
        $userBan->markUnbanned(
            unbannedBy: BanUnbannedBy::by($unbannedById),
            unbannedAt: BanUnbannedAt::at($unbannedAt),
            unbannedReason: BanUnbannedReason::of('Исправлено'),
        );

        $cycleEntity = $mapper->toCycleEntity($userBan);

        self::assertSame($userId->value(), $cycleEntity->userId);
        self::assertSame($bannedById->value(), $cycleEntity->bannedById);
        self::assertSame($expiresAt, $cycleEntity->expiresAt);
        self::assertSame($unbannedAt, $cycleEntity->unbannedAt);
        self::assertSame($unbannedById->value(), $cycleEntity->unbannedById);
        self::assertSame('Исправлено', $cycleEntity->unbannedReason);

        $restoredBan = $mapper->toDomain($cycleEntity);

        self::assertTrue($userBan->id->equals($restoredBan->id));
        self::assertFalse($restoredBan->expiration->isPermanent());
        self::assertSame($expiresAt, $restoredBan->expiration->value());
        self::assertTrue($restoredBan->unbannedAt->isUnbanned());
        self::assertSame($unbannedAt, $restoredBan->unbannedAt->value());
        self::assertSame($unbannedById->value(), $restoredBan->unbannedBy->value());
        self::assertSame('Исправлено', $restoredBan->unbannedReason->value());
    }

    public function testMapsPermanentActiveBanToNullColumns(): void
    {
        $mapper = new UserBanMapper();
        $userBan = UserBan::create(
            userId: UserId::generate(),
            bannedById: UserId::generate(),
            reason: BanReason::fromString('Причина'),
            expiration: BanExpiration::permanent(),
        );

        $cycleEntity = $mapper->toCycleEntity($userBan);

        self::assertNull($cycleEntity->expiresAt);
        self::assertNull($cycleEntity->unbannedAt);
        self::assertNull($cycleEntity->unbannedById);
        self::assertNull($cycleEntity->unbannedReason);

        $restoredBan = $mapper->toDomain($cycleEntity);

        self::assertTrue($restoredBan->expiration->isPermanent());
        self::assertFalse($restoredBan->unbannedAt->isUnbanned());
        self::assertTrue($restoredBan->unbannedBy->isEmpty());
        self::assertTrue($restoredBan->unbannedReason->isEmpty());
    }
}
