<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Entity;

use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\PostTagId;
use App\Modules\Posts\Domain\ValueObject\PostTagReference;
use App\Shared\Domain\Trait\HasTimestamps;

final class PostTag
{
    use HasTimestamps;

    public private(set) PostTagId $id;

    public private(set) PostId $postId;

    public private(set) PostTagReference $tagId;

    public static function create(PostId $postId, PostTagReference $tagId): self
    {
        $postTag = new self();
        $postTag->id = PostTagId::generate();
        $postTag->postId = $postId;
        $postTag->tagId = $tagId;
        $postTag->initializeTimestamps();

        return $postTag;
    }

    public static function restore(
        PostTagId $id,
        PostId $postId,
        PostTagReference $tagId,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $postTag = new self();
        $postTag->id = $id;
        $postTag->postId = $postId;
        $postTag->tagId = $tagId;
        $postTag->createdAt = $createdAt;
        $postTag->updatedAt = $updatedAt;

        return $postTag;
    }
}
