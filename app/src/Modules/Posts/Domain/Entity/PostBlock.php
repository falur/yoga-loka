<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Entity;

use App\Modules\Posts\Domain\ValueObject\BlockReason;
use App\Modules\Posts\Domain\ValueObject\BlockUnblockedAt;
use App\Modules\Posts\Domain\ValueObject\BlockUnblockedBy;
use App\Modules\Posts\Domain\ValueObject\BlockUnblockedReason;
use App\Modules\Posts\Domain\ValueObject\PostBlockId;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast\BlockUnblockedAtTypecast;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast\BlockUnblockedByTypecast;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast\BlockUnblockedReasonTypecast;
use App\Modules\Posts\Repository\PostBlockRepository;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'post_block',
    table: 'post_blocks',
    repository: PostBlockRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class PostBlock
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: PostBlockId::class)]
    public private(set) PostBlockId $id;

    #[Column(type: 'uuid', name: 'post_id', typecast: PostId::class)]
    public private(set) PostId $postId;

    #[Column(type: 'string(500)', typecast: BlockReason::class)]
    public private(set) BlockReason $reason;

    #[Column(type: 'uuid', name: 'blocked_by_id', typecast: UserId::class)]
    public private(set) UserId $blockedById;

    #[Column(type: 'datetime', name: 'unblocked_at', nullable: true, typecast: BlockUnblockedAtTypecast::class)]
    public private(set) BlockUnblockedAt $unblockedAt;

    #[Column(type: 'uuid', name: 'unblocked_by_id', nullable: true, typecast: BlockUnblockedByTypecast::class)]
    public private(set) BlockUnblockedBy $unblockedBy;

    #[Column(type: 'string(500)', name: 'unblocked_reason', nullable: true, typecast: BlockUnblockedReasonTypecast::class)]
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
