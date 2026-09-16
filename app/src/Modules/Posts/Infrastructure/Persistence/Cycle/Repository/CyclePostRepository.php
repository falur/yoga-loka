<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Posts\Domain\Collection\PostCollection;
use App\Modules\Posts\Domain\Collection\PostLikeCollection;
use App\Modules\Posts\Domain\Collection\PostMediaCollection;
use App\Modules\Posts\Domain\Collection\PostMentionCollection;
use App\Modules\Posts\Domain\Collection\PostTagCollection;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Entity\PostLike;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\Repository\PostRepository;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\PostTagReference;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns\PostColumns;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns\PostLikeColumns;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns\PostMediaColumns;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns\PostMentionColumns;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns\PostTagColumns;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CyclePostEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CyclePostLikeEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CyclePostMediaEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CyclePostMentionEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CyclePostTagEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Mapper\PostMapper;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use App\Shared\Infrastructure\Persistence\Cycle\WhenSelect;
use Cycle\Database\Injection\Parameter;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<CyclePostEntity>
 */
final class CyclePostRepository extends AbstractRepository implements PostRepository
{
    /**
     * @param Select<CyclePostEntity> $select
     */
    public function __construct(
        Select $select,
        private ORM $orm,
        string $role,
        private EntityManagerInterface $entityManager,
        private PostMapper $postMapper,
    ) {
        parent::__construct(select: $select, orm: $orm, role: $role);
    }

    #[\Override]
    public function findById(PostId $postId): Post|null
    {
        /** @var CyclePostEntity|null $cycleEntity */
        $cycleEntity = $this->findByPK($postId->value());

        return $cycleEntity === null ? null : $this->postMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function findByIds(PostId ...$postIds): PostCollection
    {
        if ($postIds === []) {
            return new PostCollection();
        }

        $postCollection = new PostCollection();

        /** @var iterable<CyclePostEntity> $cycleEntities */
        $cycleEntities = $this->select()
            ->where(PostColumns::ID, 'in', new Parameter(\array_map(
                static fn(PostId $postId): string => $postId->value(),
                $postIds,
            )))
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $postCollection->push($this->postMapper->toDomain($cycleEntity));
        }

        return $postCollection;
    }

    #[\Override]
    public function findByUserId(
        UserId $userId,
        PostStatus|null $status,
        PostId|null $cursor,
        int $limit,
    ): PostCollection {
        $statusValue = $status?->value;

        $postCollection = new PostCollection();

        /** @var iterable<CyclePostEntity> $cycleEntities */
        $cycleEntities = $this->select()
            ->where(PostColumns::USER_ID, $userId->value())
            ->when(
                condition: $statusValue !== null,
                callback: static function (WhenSelect $query) use ($statusValue): void {
                    $query->where(PostColumns::STATUS, $statusValue);
                },
            )
            ->cursorById(cursor: $cursor?->value(), limit: $limit)
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $postCollection->push($this->postMapper->toDomain($cycleEntity));
        }

        return $postCollection;
    }

    #[\Override]
    public function findVisibleByUserId(
        UserId $userId,
        PostStatus|null $status,
        PostStatus|null $excludeStatus,
        PostId|null $cursor,
        int $limit,
    ): PostCollection {
        $statusValue = $status?->value;
        $excludeStatusValue = $excludeStatus?->value;

        $postCollection = new PostCollection();

        /** @var iterable<CyclePostEntity> $cycleEntities */
        $cycleEntities = $this->select()
            ->where(PostColumns::USER_ID, $userId->value())
            ->where(PostColumns::DELETED_AT, '=', null)
            ->when(
                condition: $statusValue !== null,
                callback: static function (WhenSelect $query) use ($statusValue): void {
                    $query->where(PostColumns::STATUS, $statusValue);
                },
            )
            ->when(
                condition: $excludeStatusValue !== null,
                callback: static function (WhenSelect $query) use ($excludeStatusValue): void {
                    $query->where(PostColumns::STATUS, '!=', $excludeStatusValue);
                },
            )
            ->cursorById(cursor: $cursor?->value(), limit: $limit)
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $postCollection->push($this->postMapper->toDomain($cycleEntity));
        }

        return $postCollection;
    }

    #[\Override]
    public function findRepostsOf(PostId $postId): PostCollection
    {
        $postCollection = new PostCollection();

        /** @var iterable<CyclePostEntity> $cycleEntities */
        $cycleEntities = $this->select()
            ->where(PostColumns::PARENT_POST_ID, $postId->value())
            ->orderBy(expression: PostColumns::ID, direction: 'DESC')
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $postCollection->push($this->postMapper->toDomain($cycleEntity));
        }

        return $postCollection;
    }

    #[\Override]
    public function findMediaByPostId(PostId $postId): PostMediaCollection
    {
        $mediaCollection = new PostMediaCollection();

        /** @var iterable<CyclePostMediaEntity> $cycleEntities */
        $cycleEntities = $this->mediaSelect()
            ->where(PostMediaColumns::POST_ID, $postId->value())
            ->orderBy(expression: PostMediaColumns::POSITION, direction: 'ASC')
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $mediaCollection->push($this->postMapper->toPostMediaDomain($cycleEntity));
        }

        return $mediaCollection;
    }

    #[\Override]
    public function findMediaByPostIds(PostId ...$postIds): PostMediaCollection
    {
        if ($postIds === []) {
            return new PostMediaCollection();
        }

        $mediaCollection = new PostMediaCollection();

        /** @var iterable<CyclePostMediaEntity> $cycleEntities */
        $cycleEntities = $this->mediaSelect()
            ->where(PostMediaColumns::POST_ID, 'in', new Parameter(\array_map(
                static fn(PostId $postId): string => $postId->value(),
                $postIds,
            )))
            ->orderBy(expression: PostMediaColumns::POST_ID, direction: 'ASC')
            ->orderBy(expression: PostMediaColumns::POSITION, direction: 'ASC')
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $mediaCollection->push($this->postMapper->toPostMediaDomain($cycleEntity));
        }

        return $mediaCollection;
    }

    #[\Override]
    public function findTagsByPostId(PostId $postId): PostTagCollection
    {
        $tagCollection = new PostTagCollection();

        /** @var iterable<CyclePostTagEntity> $cycleEntities */
        $cycleEntities = $this->tagSelect()
            ->where(PostTagColumns::POST_ID, $postId->value())
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $tagCollection->push($this->postMapper->toPostTagDomain($cycleEntity));
        }

        return $tagCollection;
    }

    #[\Override]
    public function findTagsByPostIds(PostId ...$postIds): PostTagCollection
    {
        if ($postIds === []) {
            return new PostTagCollection();
        }

        $tagCollection = new PostTagCollection();

        /** @var iterable<CyclePostTagEntity> $cycleEntities */
        $cycleEntities = $this->tagSelect()
            ->where(PostTagColumns::POST_ID, 'in', new Parameter(\array_map(
                static fn(PostId $postId): string => $postId->value(),
                $postIds,
            )))
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $tagCollection->push($this->postMapper->toPostTagDomain($cycleEntity));
        }

        return $tagCollection;
    }

    #[\Override]
    public function findTagsByTagId(PostTagReference $tagId): PostTagCollection
    {
        $tagCollection = new PostTagCollection();

        /** @var iterable<CyclePostTagEntity> $cycleEntities */
        $cycleEntities = $this->tagSelect()
            ->where(PostTagColumns::TAG_ID, $tagId->value())
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $tagCollection->push($this->postMapper->toPostTagDomain($cycleEntity));
        }

        return $tagCollection;
    }

    #[\Override]
    public function findMentionsByPostId(PostId $postId): PostMentionCollection
    {
        $mentionCollection = new PostMentionCollection();

        /** @var iterable<CyclePostMentionEntity> $cycleEntities */
        $cycleEntities = $this->mentionSelect()
            ->where(PostMentionColumns::POST_ID, $postId->value())
            ->orderBy(expression: PostMentionColumns::ID, direction: 'DESC')
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $mentionCollection->push($this->postMapper->toPostMentionDomain($cycleEntity));
        }

        return $mentionCollection;
    }

    #[\Override]
    public function findMentionsByUserId(UserId $userId): PostMentionCollection
    {
        $mentionCollection = new PostMentionCollection();

        /** @var iterable<CyclePostMentionEntity> $cycleEntities */
        $cycleEntities = $this->mentionSelect()
            ->where(PostMentionColumns::USER_ID, $userId->value())
            ->orderBy(expression: PostMentionColumns::ID, direction: 'DESC')
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $mentionCollection->push($this->postMapper->toPostMentionDomain($cycleEntity));
        }

        return $mentionCollection;
    }

    #[\Override]
    public function findLikeByPostAndUser(PostId $postId, UserId $userId): PostLike|null
    {
        /** @var CyclePostLikeEntity|null $cycleEntity */
        $cycleEntity = $this->likeSelect()->fetchOne([
            PostLikeColumns::POST_ID => $postId->value(),
            PostLikeColumns::USER_ID => $userId->value(),
        ]);

        return $cycleEntity === null ? null : $this->postMapper->toPostLikeDomain($cycleEntity);
    }

    #[\Override]
    public function existsLikeByPostAndUser(PostId $postId, UserId $userId): bool
    {
        return $this->findLikeByPostAndUser(postId: $postId, userId: $userId) !== null;
    }

    #[\Override]
    public function findLikesByUserId(UserId $userId): PostLikeCollection
    {
        $likeCollection = new PostLikeCollection();

        /** @var iterable<CyclePostLikeEntity> $cycleEntities */
        $cycleEntities = $this->likeSelect()
            ->where(PostLikeColumns::USER_ID, $userId->value())
            ->orderBy(expression: PostLikeColumns::ID, direction: 'DESC')
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $likeCollection->push($this->postMapper->toPostLikeDomain($cycleEntity));
        }

        return $likeCollection;
    }

    #[\Override]
    public function findLikesByUserAndPostIds(UserId $userId, PostId ...$postIds): PostLikeCollection
    {
        if ($postIds === []) {
            return new PostLikeCollection();
        }

        $likeCollection = new PostLikeCollection();

        /** @var iterable<CyclePostLikeEntity> $cycleEntities */
        $cycleEntities = $this->likeSelect()
            ->where(PostLikeColumns::USER_ID, $userId->value())
            ->where(PostLikeColumns::POST_ID, 'in', new Parameter(\array_map(
                static fn(PostId $postId): string => $postId->value(),
                $postIds,
            )))
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $likeCollection->push($this->postMapper->toPostLikeDomain($cycleEntity));
        }

        return $likeCollection;
    }

    #[\Override]
    public function add(Post $post): void
    {
        /** @var CyclePostEntity|null $cycleEntity */
        $cycleEntity = $this->findByPK($post->id->value());

        $this->entityManager->persist($this->postMapper->toCycleEntity(post: $post, cycleEntity: $cycleEntity));
    }

    #[\Override]
    public function save(Post $post): void
    {
        /** @var CyclePostEntity|null $cycleEntity */
        $cycleEntity = $this->findByPK($post->id->value());

        $this->entityManager
            ->persist($this->postMapper->toCycleEntity(post: $post, cycleEntity: $cycleEntity))
            ->run();
    }

    #[\Override]
    public function saveWithOriginal(Post $post, Post $original): void
    {
        /** @var CyclePostEntity|null $cyclePost */
        $cyclePost = $this->findByPK($post->id->value());
        /** @var CyclePostEntity|null $cycleOriginal */
        $cycleOriginal = $this->findByPK($original->id->value());

        $this->entityManager
            ->persist($this->postMapper->toCycleEntity(post: $post, cycleEntity: $cyclePost))
            ->persist($this->postMapper->toCycleEntity(post: $original, cycleEntity: $cycleOriginal))
            ->run();
    }

    #[\Override]
    public function saveWithLike(Post $post, PostLike $like): void
    {
        /** @var CyclePostEntity|null $cycleEntity */
        $cycleEntity = $this->findByPK($post->id->value());

        // Лайк здесь всегда свежесоздан (PostLike::create() в LikePostHandler) — отдельного
        // findOne() перед persist() не нужно, как и для внутренних сущностей saveWithAttachments.
        $this->entityManager
            ->persist($this->postMapper->toPostLikeCycleEntity($like))
            ->persist($this->postMapper->toCycleEntity(post: $post, cycleEntity: $cycleEntity))
            ->run();
    }

    #[\Override]
    public function removeLike(PostLike $like, Post $post): void
    {
        /** @var CyclePostLikeEntity|null $cycleLike */
        $cycleLike = $this->likeSelect()->where(PostLikeColumns::ID, $like->id->value())->fetchOne();

        if ($cycleLike !== null) {
            $this->entityManager->delete($cycleLike);
        }

        /** @var CyclePostEntity|null $cycleEntity */
        $cycleEntity = $this->findByPK($post->id->value());

        $this->entityManager
            ->persist($this->postMapper->toCycleEntity(post: $post, cycleEntity: $cycleEntity))
            ->run();
    }

    #[\Override]
    public function saveWithAttachments(
        Post $post,
        PostMediaCollection $media,
        PostTagCollection $tags,
        PostMentionCollection $mentions,
    ): void {
        $this->stagePostWithAttachments(post: $post, media: $media, tags: $tags, mentions: $mentions);

        $this->entityManager->run();
    }

    #[\Override]
    public function saveRepost(
        Post $post,
        PostMediaCollection $media,
        PostTagCollection $tags,
        PostMentionCollection $mentions,
        Post $original,
    ): void {
        $this->stagePostWithAttachments(post: $post, media: $media, tags: $tags, mentions: $mentions);

        /** @var CyclePostEntity|null $cycleOriginal */
        $cycleOriginal = $this->findByPK($original->id->value());

        $this->entityManager->persist($this->postMapper->toCycleEntity(post: $original, cycleEntity: $cycleOriginal));

        $this->entityManager->run();
    }

    /**
     * Кладёт запись и её внутренние сущности в один прогон EntityManager, но не выполняет его:
     * прогон делает вызвавший публичный метод — сам по себе (создание записи) или после оригинала
     * репоста, чтобы порядок записи относительно счётчика оригинала остался прежним. Запись и все
     * вложения здесь всегда свежесозданы (Post::create() и построение коллекций в
     * PostContentComposer) — отдельного findOne() перед persist() не нужно, как и для конверсий
     * Media в CycleMediaRepository::saveWithConversions().
     */
    private function stagePostWithAttachments(
        Post $post,
        PostMediaCollection $media,
        PostTagCollection $tags,
        PostMentionCollection $mentions,
    ): void {
        $this->entityManager->persist($this->postMapper->toCycleEntity($post));

        foreach ($media as $postMedia) {
            $this->entityManager->persist($this->postMapper->toPostMediaCycleEntity($postMedia));
        }

        foreach ($tags as $postTag) {
            $this->entityManager->persist($this->postMapper->toPostTagCycleEntity($postTag));
        }

        foreach ($mentions as $postMention) {
            $this->entityManager->persist($this->postMapper->toPostMentionCycleEntity($postMention));
        }
    }

    /**
     * Выборка по таблице вложений. Собственного репозитория у вложения нет, поэтому запрос строится
     * тем же общим примитивом, что и запрос корня.
     *
     * @return WhenSelect<CyclePostMediaEntity>
     */
    private function mediaSelect(): WhenSelect
    {
        return $this->innerSelect(CyclePostMediaEntity::class);
    }

    /**
     * @return WhenSelect<CyclePostTagEntity>
     */
    private function tagSelect(): WhenSelect
    {
        return $this->innerSelect(CyclePostTagEntity::class);
    }

    /**
     * @return WhenSelect<CyclePostMentionEntity>
     */
    private function mentionSelect(): WhenSelect
    {
        return $this->innerSelect(CyclePostMentionEntity::class);
    }

    /**
     * @return WhenSelect<CyclePostLikeEntity>
     */
    private function likeSelect(): WhenSelect
    {
        return $this->innerSelect(CyclePostLikeEntity::class);
    }

    /**
     * @template TInner of object
     *
     * @param class-string<TInner> $role
     *
     * @return WhenSelect<TInner>
     */
    private function innerSelect(string $role): WhenSelect
    {
        /** @var WhenSelect<TInner> $select */
        $select = new WhenSelect(orm: $this->orm, role: $role);
        $select->scope($this->orm->getSource($role)->getScope());

        return $select;
    }
}
