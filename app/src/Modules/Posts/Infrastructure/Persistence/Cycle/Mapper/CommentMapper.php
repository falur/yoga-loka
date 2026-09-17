<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\Entity\CommentLike;
use App\Modules\Posts\Domain\Entity\CommentMention;
use App\Modules\Posts\Domain\ValueObject\CommentDeletedAt;
use App\Modules\Posts\Domain\ValueObject\CommentDeletedBy;
use App\Modules\Posts\Domain\ValueObject\CommentDeletionReason;
use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Domain\ValueObject\CommentLikeId;
use App\Modules\Posts\Domain\ValueObject\CommentMentionId;
use App\Modules\Posts\Domain\ValueObject\CommentParent;
use App\Modules\Posts\Domain\ValueObject\CommentText;
use App\Modules\Posts\Domain\ValueObject\LikesCount;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\RepliesCount;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CycleCommentEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CycleCommentLikeEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CycleCommentMentionEntity;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Преобразует корень агрегата Comment и его две внутренние сущности (CommentLike, CommentMention):
 * у обеих нет собственного Repository и Cycle-relation на CycleCommentEntity — выборку по их
 * таблицам ведёт CycleCommentRepository отдельным запросом (тот же приём, что RoleMapper::
 * toRolePermissionDomain() для RolePermission в Access).
 */
final readonly class CommentMapper
{
    public function toDomain(CycleCommentEntity $cycleEntity): Comment
    {
        return Comment::restore(
            id: CommentId::fromString($cycleEntity->id),
            postId: PostId::fromString($cycleEntity->postId),
            userId: UserId::fromString($cycleEntity->userId),
            text: CommentText::fromString($cycleEntity->text),
            parent: $cycleEntity->parentCommentId === null
                ? CommentParent::none()
                : CommentParent::pointingTo($cycleEntity->parentCommentId),
            likesCount: LikesCount::fromInt($cycleEntity->likesCount),
            repliesCount: RepliesCount::fromInt($cycleEntity->repliesCount),
            deletedAt: $cycleEntity->deletedAt === null
                ? CommentDeletedAt::notDeleted()
                : CommentDeletedAt::at($cycleEntity->deletedAt),
            deletedBy: $cycleEntity->deletedById === null
                ? CommentDeletedBy::none()
                : CommentDeletedBy::by(UserId::fromString($cycleEntity->deletedById)),
            deletionReason: $cycleEntity->deletionReason === null
                ? CommentDeletionReason::none()
                : CommentDeletionReason::of($cycleEntity->deletionReason),
            createdAt: $cycleEntity->createdAt,
            updatedAt: $cycleEntity->updatedAt,
        );
    }

    public function toCycleEntity(
        Comment $comment,
        CycleCommentEntity|null $cycleEntity = null,
    ): CycleCommentEntity {
        $cycleEntity ??= new CycleCommentEntity();
        $cycleEntity->id = $comment->id->value();
        $cycleEntity->postId = $comment->postId->value();
        $cycleEntity->userId = $comment->userId->value();
        $cycleEntity->text = $comment->text->value();
        $cycleEntity->parentCommentId = $comment->parent->value();
        $cycleEntity->likesCount = $comment->likesCount->value();
        $cycleEntity->repliesCount = $comment->repliesCount->value();
        $cycleEntity->deletedAt = $comment->deletedAt->value();
        $cycleEntity->deletedById = $comment->deletedBy->value();
        $cycleEntity->deletionReason = $comment->deletionReason->value();
        $cycleEntity->createdAt = $comment->createdAt;
        $cycleEntity->updatedAt = $comment->updatedAt;

        return $cycleEntity;
    }

    public function toCommentLikeDomain(CycleCommentLikeEntity $cycleEntity): CommentLike
    {
        return CommentLike::restore(
            id: CommentLikeId::fromString($cycleEntity->id),
            commentId: CommentId::fromString($cycleEntity->commentId),
            userId: UserId::fromString($cycleEntity->userId),
            createdAt: $cycleEntity->createdAt,
            updatedAt: $cycleEntity->updatedAt,
        );
    }

    public function toCommentLikeCycleEntity(
        CommentLike $commentLike,
        CycleCommentLikeEntity|null $cycleEntity = null,
    ): CycleCommentLikeEntity {
        $cycleEntity ??= new CycleCommentLikeEntity();
        $cycleEntity->id = $commentLike->id->value();
        $cycleEntity->commentId = $commentLike->commentId->value();
        $cycleEntity->userId = $commentLike->userId->value();
        $cycleEntity->createdAt = $commentLike->createdAt;
        $cycleEntity->updatedAt = $commentLike->updatedAt;

        return $cycleEntity;
    }

    public function toCommentMentionDomain(CycleCommentMentionEntity $cycleEntity): CommentMention
    {
        return CommentMention::restore(
            id: CommentMentionId::fromString($cycleEntity->id),
            commentId: CommentId::fromString($cycleEntity->commentId),
            userId: UserId::fromString($cycleEntity->userId),
            createdAt: $cycleEntity->createdAt,
            updatedAt: $cycleEntity->updatedAt,
        );
    }

    public function toCommentMentionCycleEntity(
        CommentMention $commentMention,
        CycleCommentMentionEntity|null $cycleEntity = null,
    ): CycleCommentMentionEntity {
        $cycleEntity ??= new CycleCommentMentionEntity();
        $cycleEntity->id = $commentMention->id->value();
        $cycleEntity->commentId = $commentMention->commentId->value();
        $cycleEntity->userId = $commentMention->userId->value();
        $cycleEntity->createdAt = $commentMention->createdAt;
        $cycleEntity->updatedAt = $commentMention->updatedAt;

        return $cycleEntity;
    }
}
