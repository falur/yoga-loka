<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Entity;

use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Domain\ValueObject\CommentMentionId;
use App\Modules\Posts\Repository\CommentMentionRepository;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'comment_mention',
    table: 'comment_mentions',
    repository: CommentMentionRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class CommentMention
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: CommentMentionId::class)]
    public private(set) CommentMentionId $id;

    #[Column(type: 'uuid', name: 'comment_id', typecast: CommentId::class)]
    public private(set) CommentId $commentId;

    #[Column(type: 'uuid', name: 'user_id', typecast: UserId::class)]
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
}
