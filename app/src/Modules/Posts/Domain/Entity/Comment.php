<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Entity;

use App\Modules\Posts\Domain\ValueObject\CommentDeletedAt;
use App\Modules\Posts\Domain\ValueObject\CommentDeletedBy;
use App\Modules\Posts\Domain\ValueObject\CommentDeletionReason;
use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Domain\ValueObject\CommentParent;
use App\Modules\Posts\Domain\ValueObject\CommentText;
use App\Modules\Posts\Domain\ValueObject\LikesCount;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\RepliesCount;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast\CommentDeletedAtTypecast;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast\CommentDeletedByTypecast;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast\CommentDeletionReasonTypecast;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast\CommentParentTypecast;
use App\Modules\Posts\Repository\CommentRepository;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'comment',
    table: 'comments',
    repository: CommentRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class Comment
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: CommentId::class)]
    public private(set) CommentId $id;

    #[Column(type: 'uuid', name: 'post_id', typecast: PostId::class)]
    public private(set) PostId $postId;

    #[Column(type: 'uuid', name: 'user_id', typecast: UserId::class)]
    public private(set) UserId $userId;

    #[Column(type: 'text', typecast: CommentText::class)]
    public private(set) CommentText $text;

    #[Column(type: 'uuid', name: 'parent_comment_id', nullable: true, typecast: CommentParentTypecast::class)]
    public private(set) CommentParent $parent;

    #[Column(type: 'integer', name: 'likes_count', typecast: LikesCount::class)]
    public private(set) LikesCount $likesCount;

    #[Column(type: 'integer', name: 'replies_count', typecast: RepliesCount::class)]
    public private(set) RepliesCount $repliesCount;

    #[Column(type: 'datetime', name: 'deleted_at', nullable: true, typecast: CommentDeletedAtTypecast::class)]
    public private(set) CommentDeletedAt $deletedAt;

    #[Column(type: 'uuid', name: 'deleted_by_id', nullable: true, typecast: CommentDeletedByTypecast::class)]
    public private(set) CommentDeletedBy $deletedBy;

    #[Column(type: 'string(500)', name: 'deletion_reason', nullable: true, typecast: CommentDeletionReasonTypecast::class)]
    public private(set) CommentDeletionReason $deletionReason;

    public static function create(PostId $postId, UserId $userId, CommentText $text, CommentParent $parent): self
    {
        $comment = new self();
        $comment->id = CommentId::generate();
        $comment->postId = $postId;
        $comment->userId = $userId;
        $comment->text = $text;
        $comment->parent = $parent;
        $comment->likesCount = LikesCount::zero();
        $comment->repliesCount = RepliesCount::zero();
        $comment->deletedAt = CommentDeletedAt::notDeleted();
        $comment->deletedBy = CommentDeletedBy::none();
        $comment->deletionReason = CommentDeletionReason::none();
        $comment->initializeTimestamps();

        return $comment;
    }

    public function delete(
        CommentDeletedBy $deletedBy,
        CommentDeletedAt $deletedAt,
        CommentDeletionReason $deletionReason,
    ): void {
        if (!$deletedAt->isDeleted() || $deletedBy->isEmpty()) {
            throw new InvalidDomainValueException('Удаление комментария требует даты и пользователя.');
        }

        $this->deletedBy = $deletedBy;
        $this->deletedAt = $deletedAt;
        $this->deletionReason = $deletionReason;
        $this->touch();
    }

    public function restore(): void
    {
        $this->deletedAt = CommentDeletedAt::notDeleted();
        $this->deletedBy = CommentDeletedBy::none();
        $this->deletionReason = CommentDeletionReason::none();
        $this->touch();
    }

    public function incrementLikes(): void
    {
        $this->likesCount = $this->likesCount->increment();
        $this->touch();
    }

    public function decrementLikes(): void
    {
        $this->likesCount = $this->likesCount->decrement();
        $this->touch();
    }

    public function incrementReplies(): void
    {
        $this->repliesCount = $this->repliesCount->increment();
        $this->touch();
    }

    public function decrementReplies(): void
    {
        $this->repliesCount = $this->repliesCount->decrement();
        $this->touch();
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt->isDeleted();
    }
}
