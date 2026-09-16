<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Posts\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\Posts\Domain\Entity\PostBlock;
use App\Modules\Posts\Domain\ValueObject\BlockReason;
use App\Modules\Posts\Domain\ValueObject\BlockUnblockedAt;
use App\Modules\Posts\Domain\ValueObject\BlockUnblockedBy;
use App\Modules\Posts\Domain\ValueObject\BlockUnblockedReason;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Mapper\PostBlockMapper;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

/**
 * Переносит проверки удалённых typecast-классов PostBlock (BlockUnblockedAtTypecast,
 * BlockUnblockedByTypecast, BlockUnblockedReasonTypecast) на Mapper, куда переехала их логика
 * null-bridging между nullable-колонкой и value object с сентинелом.
 */
final class PostBlockMapperTest extends TestCase
{
    public function testMapsPostBlockWithAllOptionalValuesFilled(): void
    {
        $mapper = new PostBlockMapper();
        $unblockedBy = UserId::generate();
        $unblockedAt = new \DateTimeImmutable('2026-06-17 12:00:00');

        $postBlock = PostBlock::create(
            postId: PostId::generate(),
            reason: BlockReason::fromString('Нарушение'),
            blockedBy: UserId::generate(),
        );
        $postBlock->markUnblocked(
            unblockedBy: BlockUnblockedBy::by($unblockedBy),
            unblockedAt: BlockUnblockedAt::at($unblockedAt),
            unblockedReason: BlockUnblockedReason::of('Ошибка'),
        );

        $cycleEntity = $mapper->toCycleEntity($postBlock);

        self::assertSame($postBlock->id->value(), $cycleEntity->id);
        self::assertSame($unblockedAt, $cycleEntity->unblockedAt);
        self::assertSame($unblockedBy->value(), $cycleEntity->unblockedById);
        self::assertSame('Ошибка', $cycleEntity->unblockedReason);

        $restoredPostBlock = $mapper->toDomain($cycleEntity);

        self::assertTrue($postBlock->id->equals($restoredPostBlock->id));
        self::assertFalse($restoredPostBlock->isActive());
        self::assertSame($unblockedAt, $restoredPostBlock->unblockedAt->value());
        self::assertSame($unblockedBy->value(), $restoredPostBlock->unblockedBy->value());
        self::assertSame('Ошибка', $restoredPostBlock->unblockedReason->value());
    }

    public function testMapsPostBlockWithEmptyOptionalValuesToNullColumns(): void
    {
        $mapper = new PostBlockMapper();
        $postBlock = PostBlock::create(
            postId: PostId::generate(),
            reason: BlockReason::fromString('Нарушение'),
            blockedBy: UserId::generate(),
        );

        $cycleEntity = $mapper->toCycleEntity($postBlock);

        self::assertNull($cycleEntity->unblockedAt);
        self::assertNull($cycleEntity->unblockedById);
        self::assertNull($cycleEntity->unblockedReason);

        $restoredPostBlock = $mapper->toDomain($cycleEntity);

        self::assertTrue($restoredPostBlock->isActive());
        self::assertTrue($restoredPostBlock->unblockedBy->isEmpty());
        self::assertTrue($restoredPostBlock->unblockedReason->isEmpty());
    }

    public function testToCycleEntityUpdatesGivenInstanceInPlace(): void
    {
        $mapper = new PostBlockMapper();
        $postBlock = PostBlock::create(
            postId: PostId::generate(),
            reason: BlockReason::fromString('Нарушение'),
            blockedBy: UserId::generate(),
        );

        $cycleEntity = $mapper->toCycleEntity($postBlock);
        $postBlock->markUnblocked(
            unblockedBy: BlockUnblockedBy::by(UserId::generate()),
            unblockedAt: BlockUnblockedAt::at(new \DateTimeImmutable('2026-06-18 09:00:00')),
            unblockedReason: BlockUnblockedReason::none(),
        );
        $updatedCycleEntity = $mapper->toCycleEntity($postBlock, $cycleEntity);

        self::assertSame($cycleEntity, $updatedCycleEntity);
        self::assertNotNull($updatedCycleEntity->unblockedAt);
    }
}
