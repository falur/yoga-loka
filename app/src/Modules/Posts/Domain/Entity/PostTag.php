<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Entity;

use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\PostTagId;
use App\Modules\Posts\Domain\ValueObject\PostTagReference;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use App\Shared\Infrastructure\Persistence\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'post_tag',
    table: 'post_tags',
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class PostTag
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: PostTagId::class)]
    public private(set) PostTagId $id;

    #[Column(type: 'uuid', name: 'post_id', typecast: PostId::class)]
    public private(set) PostId $postId;

    #[Column(type: 'uuid', name: 'tag_id', typecast: PostTagReference::class)]
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
}
