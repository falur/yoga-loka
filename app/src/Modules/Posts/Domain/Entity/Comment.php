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
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;

final class Comment
{
    use HasTimestamps;

    public private(set) CommentId $id;

    public private(set) PostId $postId;

    public private(set) UserId $userId;

    public private(set) CommentText $text;

    public private(set) CommentParent $parent;

    public private(set) LikesCount $likesCount;

    public private(set) RepliesCount $repliesCount;

    public private(set) CommentDeletedAt $deletedAt;

    public private(set) CommentDeletedBy $deletedBy;

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

    public static function restore(
        CommentId $id,
        PostId $postId,
        UserId $userId,
        CommentText $text,
        CommentParent $parent,
        LikesCount $likesCount,
        RepliesCount $repliesCount,
        CommentDeletedAt $deletedAt,
        CommentDeletedBy $deletedBy,
        CommentDeletionReason $deletionReason,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $comment = new self();
        $comment->id = $id;
        $comment->postId = $postId;
        $comment->userId = $userId;
        $comment->text = $text;
        $comment->parent = $parent;
        $comment->likesCount = $likesCount;
        $comment->repliesCount = $repliesCount;
        $comment->deletedAt = $deletedAt;
        $comment->deletedBy = $deletedBy;
        $comment->deletionReason = $deletionReason;
        $comment->createdAt = $createdAt;
        $comment->updatedAt = $updatedAt;

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

    /**
     * Отменяет мягкое удаление комментария. Названо undelete(), а не restore(): последнее имя
     * занято технической фабрикой восстановления из хранения (docs/rules.md, «Именование»).
     */
    public function undelete(): void
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
