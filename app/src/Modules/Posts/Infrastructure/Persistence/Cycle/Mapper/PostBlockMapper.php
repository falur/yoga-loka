<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\Posts\Domain\Entity\PostBlock;
use App\Modules\Posts\Domain\ValueObject\BlockReason;
use App\Modules\Posts\Domain\ValueObject\BlockUnblockedAt;
use App\Modules\Posts\Domain\ValueObject\BlockUnblockedBy;
use App\Modules\Posts\Domain\ValueObject\BlockUnblockedReason;
use App\Modules\Posts\Domain\ValueObject\PostBlockId;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CyclePostBlockEntity;
use App\Shared\Domain\ValueObject\UserId;

final readonly class PostBlockMapper
{
    public function toDomain(CyclePostBlockEntity $cycleEntity): PostBlock
    {
        return PostBlock::restore(
            id: PostBlockId::fromString($cycleEntity->id),
            postId: PostId::fromString($cycleEntity->postId),
            reason: BlockReason::fromString($cycleEntity->reason),
            blockedById: UserId::fromString($cycleEntity->blockedById),
            unblockedAt: $cycleEntity->unblockedAt === null
                ? BlockUnblockedAt::notUnblocked()
                : BlockUnblockedAt::at($cycleEntity->unblockedAt),
            unblockedBy: $cycleEntity->unblockedById === null
                ? BlockUnblockedBy::none()
                : BlockUnblockedBy::by(UserId::fromString($cycleEntity->unblockedById)),
            unblockedReason: $cycleEntity->unblockedReason === null
                ? BlockUnblockedReason::none()
                : BlockUnblockedReason::of($cycleEntity->unblockedReason),
            createdAt: $cycleEntity->createdAt,
            updatedAt: $cycleEntity->updatedAt,
        );
    }

    public function toCycleEntity(
        PostBlock $postBlock,
        CyclePostBlockEntity|null $cycleEntity = null,
    ): CyclePostBlockEntity {
        $cycleEntity ??= new CyclePostBlockEntity();
        $cycleEntity->id = $postBlock->id->value();
        $cycleEntity->postId = $postBlock->postId->value();
        $cycleEntity->reason = $postBlock->reason->value();
        $cycleEntity->blockedById = $postBlock->blockedById->value();
        $cycleEntity->unblockedAt = $postBlock->unblockedAt->value();
        $cycleEntity->unblockedById = $postBlock->unblockedBy->value();
        $cycleEntity->unblockedReason = $postBlock->unblockedReason->value();
        $cycleEntity->createdAt = $postBlock->createdAt;
        $cycleEntity->updatedAt = $postBlock->updatedAt;

        return $cycleEntity;
    }
}
