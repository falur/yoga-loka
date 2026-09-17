<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Entity;

use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\PostLikeId;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;

final class PostLike
{
    use HasTimestamps;

    public private(set) PostLikeId $id;

    public private(set) PostId $postId;

    public private(set) UserId $userId;

    public static function create(PostId $postId, UserId $userId): self
    {
        $postLike = new self();
        $postLike->id = PostLikeId::generate();
        $postLike->postId = $postId;
        $postLike->userId = $userId;
        $postLike->initializeTimestamps();

        return $postLike;
    }

    public static function restore(
        PostLikeId $id,
        PostId $postId,
        UserId $userId,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $postLike = new self();
        $postLike->id = $id;
        $postLike->postId = $postId;
        $postLike->userId = $userId;
        $postLike->createdAt = $createdAt;
        $postLike->updatedAt = $updatedAt;

        return $postLike;
    }
}
