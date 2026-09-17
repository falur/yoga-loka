<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Entity;

use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\PostMentionId;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;

final class PostMention
{
    use HasTimestamps;

    public private(set) PostMentionId $id;

    public private(set) PostId $postId;

    public private(set) UserId $userId;

    public static function create(PostId $postId, UserId $userId): self
    {
        $postMention = new self();
        $postMention->id = PostMentionId::generate();
        $postMention->postId = $postId;
        $postMention->userId = $userId;
        $postMention->initializeTimestamps();

        return $postMention;
    }

    public static function restore(
        PostMentionId $id,
        PostId $postId,
        UserId $userId,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $postMention = new self();
        $postMention->id = $id;
        $postMention->postId = $postId;
        $postMention->userId = $userId;
        $postMention->createdAt = $createdAt;
        $postMention->updatedAt = $updatedAt;

        return $postMention;
    }
}
