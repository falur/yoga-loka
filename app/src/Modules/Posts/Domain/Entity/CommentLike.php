<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Entity;

use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Domain\ValueObject\CommentLikeId;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;

final class CommentLike
{
    use HasTimestamps;

    public private(set) CommentLikeId $id;

    public private(set) CommentId $commentId;

    public private(set) UserId $userId;

    public static function create(CommentId $commentId, UserId $userId): self
    {
        $commentLike = new self();
        $commentLike->id = CommentLikeId::generate();
        $commentLike->commentId = $commentId;
        $commentLike->userId = $userId;
        $commentLike->initializeTimestamps();

        return $commentLike;
    }

    public static function restore(
        CommentLikeId $id,
        CommentId $commentId,
        UserId $userId,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $commentLike = new self();
        $commentLike->id = $id;
        $commentLike->commentId = $commentId;
        $commentLike->userId = $userId;
        $commentLike->createdAt = $createdAt;
        $commentLike->updatedAt = $updatedAt;

        return $commentLike;
    }
}
