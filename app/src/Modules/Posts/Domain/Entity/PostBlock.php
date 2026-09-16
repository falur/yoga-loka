<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Entity;

use App\Modules\Posts\Domain\ValueObject\BlockReason;
use App\Modules\Posts\Domain\ValueObject\BlockUnblockedAt;
use App\Modules\Posts\Domain\ValueObject\BlockUnblockedBy;
use App\Modules\Posts\Domain\ValueObject\BlockUnblockedReason;
use App\Modules\Posts\Domain\ValueObject\PostBlockId;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;

final class PostBlock
{
    use HasTimestamps;

    public private(set) PostBlockId $id;

    public private(set) PostId $postId;

    public private(set) BlockReason $reason;

    public private(set) UserId $blockedById;

    public private(set) BlockUnblockedAt $unblockedAt;

    public private(set) BlockUnblockedBy $unblockedBy;

    public private(set) BlockUnblockedReason $unblockedReason;

    public static function create(PostId $postId, BlockReason $reason, UserId $blockedBy): self
    {
        $postBlock = new self();
        $postBlock->id = PostBlockId::generate();
        $postBlock->postId = $postId;
        $postBlock->reason = $reason;
        $postBlock->blockedById = $blockedBy;
        $postBlock->unblockedAt = BlockUnblockedAt::notUnblocked();
        $postBlock->unblockedBy = BlockUnblockedBy::none();
        $postBlock->unblockedReason = BlockUnblockedReason::none();
        $postBlock->initializeTimestamps();

        return $postBlock;
    }

    public static function restore(
        PostBlockId $id,
        PostId $postId,
        BlockReason $reason,
        UserId $blockedById,
        BlockUnblockedAt $unblockedAt,
        BlockUnblockedBy $unblockedBy,
        BlockUnblockedReason $unblockedReason,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $postBlock = new self();
        $postBlock->id = $id;
        $postBlock->postId = $postId;
        $postBlock->reason = $reason;
        $postBlock->blockedById = $blockedById;
        $postBlock->unblockedAt = $unblockedAt;
        $postBlock->unblockedBy = $unblockedBy;
        $postBlock->unblockedReason = $unblockedReason;
        $postBlock->createdAt = $createdAt;
        $postBlock->updatedAt = $updatedAt;

        return $postBlock;
    }

    public function markUnblocked(
        BlockUnblockedBy $unblockedBy,
        BlockUnblockedAt $unblockedAt,
        BlockUnblockedReason $unblockedReason,
    ): void {
        if (!$unblockedAt->isUnblocked() || $unblockedBy->isEmpty()) {
            throw new InvalidDomainValueException('Разблокировка записи требует даты и пользователя.');
        }

        $this->unblockedBy = $unblockedBy;
        $this->unblockedAt = $unblockedAt;
        $this->unblockedReason = $unblockedReason;
        $this->touch();
    }

    public function isActive(): bool
    {
        return !$this->unblockedAt->isUnblocked();
    }
}
