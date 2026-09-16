<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Entity;

use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Domain\ValueObject\CommentLikeId;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'comment_like',
    table: 'comment_likes',
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class CommentLike
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: CommentLikeId::class)]
    public private(set) CommentLikeId $id;

    #[Column(type: 'uuid', name: 'comment_id', typecast: CommentId::class)]
    public private(set) CommentId $commentId;

    #[Column(type: 'uuid', name: 'user_id', typecast: UserId::class)]
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
}
