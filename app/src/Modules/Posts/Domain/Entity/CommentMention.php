<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Entity;

use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Domain\ValueObject\CommentMentionId;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;

final class CommentMention
{
    use HasTimestamps;

    public private(set) CommentMentionId $id;

    public private(set) CommentId $commentId;

    public private(set) UserId $userId;

    public static function create(CommentId $commentId, UserId $userId): self
    {
        $commentMention = new self();
        $commentMention->id = CommentMentionId::generate();
        $commentMention->commentId = $commentId;
        $commentMention->userId = $userId;
        $commentMention->initializeTimestamps();

        return $commentMention;
    }

    public static function restore(
        CommentMentionId $id,
        CommentId $commentId,
        UserId $userId,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $commentMention = new self();
        $commentMention->id = $id;
        $commentMention->commentId = $commentId;
        $commentMention->userId = $userId;
        $commentMention->createdAt = $createdAt;
        $commentMention->updatedAt = $updatedAt;

        return $commentMention;
    }
}
