<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\Posts\Domain\Collection\PostMediaCollection;
use App\Modules\Posts\Domain\Collection\PostTagCollection;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Entity\PostLike;
use App\Modules\Posts\Domain\Entity\PostMedia;
use App\Modules\Posts\Domain\Entity\PostMention;
use App\Modules\Posts\Domain\Entity\PostTag;
use App\Modules\Posts\Domain\ValueObject\CommentsCount;
use App\Modules\Posts\Domain\ValueObject\LikesCount;
use App\Modules\Posts\Domain\ValueObject\MediaPosition;
use App\Modules\Posts\Domain\ValueObject\PostDeletion;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostLikeId;
use App\Modules\Posts\Domain\ValueObject\PostMediaId;
use App\Modules\Posts\Domain\ValueObject\PostMediaReference;
use App\Modules\Posts\Domain\ValueObject\PostMentionId;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostTagId;
use App\Modules\Posts\Domain\ValueObject\PostTagReference;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Modules\Posts\Domain\ValueObject\RepostsCount;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CyclePostEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CyclePostLikeEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CyclePostMediaEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CyclePostMentionEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CyclePostTagEntity;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Преобразует корень агрегата Post и четыре его внутренние сущности (PostMedia, PostTag,
 * PostMention, PostLike): ни одна из них не читается через Cycle-relation (только PostMedia/PostTag
 * вообще имеют relation на CyclePostEntity — см. комментарий CyclePostEntity — но
 * CyclePostRepository всегда выбирает их отдельным запросом, как и PostMention/PostLike, у
 * которых relation нет вовсе), поэтому у каждой нет своего Repository и граница сохранения
 * остаётся здесь же, у Mapper-а корня (тот же приём, что RoleMapper::toRolePermissionDomain()
 * для RolePermission в Access).
 *
 * toDomain() никогда не читает media/tags CyclePostEntity: это реальные Cycle HasMany-relation
 * (lazy-ghost вне ->load()), а ни один метод CyclePostRepository по ним ->load() не делает —
 * findMediaByPostId()/findTagsByPostId() и их варианты ходят в mediaSelect()/tagSelect()
 * отдельным запросом. Post::restore() поэтому всегда получает пустые коллекции — как и раньше,
 * ни один потребитель Application не читает $post->media/$post->tags после findById() (подтверждено
 * grep, PostResource работает с App\Modules\Posts\Application\Result\PostResult, а не с доменным Post).
 */
final readonly class PostMapper
{
    public function toDomain(CyclePostEntity $cycleEntity): Post
    {
        return Post::restore(
            id: PostId::fromString($cycleEntity->id),
            userId: UserId::fromString($cycleEntity->userId),
            text: $cycleEntity->text === null ? PostText::none() : PostText::fromString($cycleEntity->text),
            status: $cycleEntity->status,
            attachmentType: $cycleEntity->attachmentType,
            lesson: $cycleEntity->lessonId === null ? PostLesson::none() : PostLesson::pointingTo($cycleEntity->lessonId),
            practice: $cycleEntity->practiceId === null ? PostPractice::none() : PostPractice::pointingTo($cycleEntity->practiceId),
            original: $cycleEntity->parentPostId === null ? PostOriginal::none() : PostOriginal::pointingTo($cycleEntity->parentPostId),
            likesCount: LikesCount::fromInt($cycleEntity->likesCount),
            repostsCount: RepostsCount::fromInt($cycleEntity->repostsCount),
            commentsCount: CommentsCount::fromInt($cycleEntity->commentsCount),
            deletion: $cycleEntity->deletedAt === null ? PostDeletion::notDeleted() : PostDeletion::deletedAt($cycleEntity->deletedAt),
            media: new PostMediaCollection(),
            tags: new PostTagCollection(),
            createdAt: $cycleEntity->createdAt,
            updatedAt: $cycleEntity->updatedAt,
        );
    }

    public function toCycleEntity(
        Post $post,
        CyclePostEntity|null $cycleEntity = null,
    ): CyclePostEntity {
        $cycleEntity ??= new CyclePostEntity();
        $cycleEntity->id = $post->id->value();
        $cycleEntity->userId = $post->userId->value();
        $cycleEntity->text = $post->text->value();
        $cycleEntity->status = $post->status;
        $cycleEntity->attachmentType = $post->attachmentType;
        $cycleEntity->lessonId = $post->lesson->value();
        $cycleEntity->practiceId = $post->practice->value();
        $cycleEntity->parentPostId = $post->original->value();
        $cycleEntity->likesCount = $post->likesCount->value();
        $cycleEntity->repostsCount = $post->repostsCount->value();
        $cycleEntity->commentsCount = $post->commentsCount->value();
        $cycleEntity->deletedAt = $post->deletion->value();
        $cycleEntity->createdAt = $post->createdAt;
        $cycleEntity->updatedAt = $post->updatedAt;

        return $cycleEntity;
    }

    public function toPostMediaDomain(CyclePostMediaEntity $cycleEntity): PostMedia
    {
        return PostMedia::restore(
            id: PostMediaId::fromString($cycleEntity->id),
            postId: PostId::fromString($cycleEntity->postId),
            mediaId: PostMediaReference::fromString($cycleEntity->mediaId),
            position: MediaPosition::fromInt($cycleEntity->position),
            createdAt: $cycleEntity->createdAt,
            updatedAt: $cycleEntity->updatedAt,
        );
    }

    public function toPostMediaCycleEntity(
        PostMedia $postMedia,
        CyclePostMediaEntity|null $cycleEntity = null,
    ): CyclePostMediaEntity {
        $cycleEntity ??= new CyclePostMediaEntity();
        $cycleEntity->id = $postMedia->id->value();
        $cycleEntity->postId = $postMedia->postId->value();
        $cycleEntity->mediaId = $postMedia->mediaId->value();
        $cycleEntity->position = $postMedia->position->value();
        $cycleEntity->createdAt = $postMedia->createdAt;
        $cycleEntity->updatedAt = $postMedia->updatedAt;

        return $cycleEntity;
    }

    public function toPostTagDomain(CyclePostTagEntity $cycleEntity): PostTag
    {
        return PostTag::restore(
            id: PostTagId::fromString($cycleEntity->id),
            postId: PostId::fromString($cycleEntity->postId),
            tagId: PostTagReference::fromString($cycleEntity->tagId),
            createdAt: $cycleEntity->createdAt,
            updatedAt: $cycleEntity->updatedAt,
        );
    }

    public function toPostTagCycleEntity(
        PostTag $postTag,
        CyclePostTagEntity|null $cycleEntity = null,
    ): CyclePostTagEntity {
        $cycleEntity ??= new CyclePostTagEntity();
        $cycleEntity->id = $postTag->id->value();
        $cycleEntity->postId = $postTag->postId->value();
        $cycleEntity->tagId = $postTag->tagId->value();
        $cycleEntity->createdAt = $postTag->createdAt;
        $cycleEntity->updatedAt = $postTag->updatedAt;

        return $cycleEntity;
    }

    public function toPostMentionDomain(CyclePostMentionEntity $cycleEntity): PostMention
    {
        return PostMention::restore(
            id: PostMentionId::fromString($cycleEntity->id),
            postId: PostId::fromString($cycleEntity->postId),
            userId: UserId::fromString($cycleEntity->userId),
            createdAt: $cycleEntity->createdAt,
            updatedAt: $cycleEntity->updatedAt,
        );
    }

    public function toPostMentionCycleEntity(
        PostMention $postMention,
        CyclePostMentionEntity|null $cycleEntity = null,
    ): CyclePostMentionEntity {
        $cycleEntity ??= new CyclePostMentionEntity();
        $cycleEntity->id = $postMention->id->value();
        $cycleEntity->postId = $postMention->postId->value();
        $cycleEntity->userId = $postMention->userId->value();
        $cycleEntity->createdAt = $postMention->createdAt;
        $cycleEntity->updatedAt = $postMention->updatedAt;

        return $cycleEntity;
    }

    public function toPostLikeDomain(CyclePostLikeEntity $cycleEntity): PostLike
    {
        return PostLike::restore(
            id: PostLikeId::fromString($cycleEntity->id),
            postId: PostId::fromString($cycleEntity->postId),
            userId: UserId::fromString($cycleEntity->userId),
            createdAt: $cycleEntity->createdAt,
            updatedAt: $cycleEntity->updatedAt,
        );
    }

    public function toPostLikeCycleEntity(
        PostLike $postLike,
        CyclePostLikeEntity|null $cycleEntity = null,
    ): CyclePostLikeEntity {
        $cycleEntity ??= new CyclePostLikeEntity();
        $cycleEntity->id = $postLike->id->value();
        $cycleEntity->postId = $postLike->postId->value();
        $cycleEntity->userId = $postLike->userId->value();
        $cycleEntity->createdAt = $postLike->createdAt;
        $cycleEntity->updatedAt = $postLike->updatedAt;

        return $cycleEntity;
    }
}
