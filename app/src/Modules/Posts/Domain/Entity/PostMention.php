<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Entity;

use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\PostMentionId;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'post_mention',
    table: 'post_mentions',
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class PostMention
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: PostMentionId::class)]
    public private(set) PostMentionId $id;

    #[Column(type: 'uuid', name: 'post_id', typecast: PostId::class)]
    public private(set) PostId $postId;

    #[Column(type: 'uuid', name: 'user_id', typecast: UserId::class)]
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
}
