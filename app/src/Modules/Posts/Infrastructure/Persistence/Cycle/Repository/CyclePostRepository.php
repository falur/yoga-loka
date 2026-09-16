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
use App\Modules\Posts\Domain\Entity\PostMedia;
use App\Modules\Posts\Domain\Entity\PostMention;
use App\Modules\Posts\Domain\Entity\PostTag;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\Repository\PostRepository;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\PostTagReference;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use App\Shared\Infrastructure\Persistence\Cycle\WhenSelect;
use Cycle\Database\Injection\Parameter;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<Post>
 */
final class CyclePostRepository extends AbstractRepository implements PostRepository
{
    /**
     * @param Select<Post> $select
     */
    public function __construct(
        Select $select,
        private ORM $orm,
        string $role,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct(select: $select, orm: $orm, role: $role);
    }

    #[\Override]
    public function findById(PostId $postId): Post|null
    {
        return $this->findByPK($postId->value());
    }

    #[\Override]
    public function findByIds(PostId ...$postIds): PostCollection
    {
        if ($postIds === []) {
            return new PostCollection();
        }

        return new PostCollection(
            $this->select()
                ->where('id', 'in', new Parameter(\array_map(
                    static fn(PostId $postId): string => $postId->value(),
                    $postIds,
                )))
                ->fetchAll(),
        );
    }

    #[\Override]
    public function findByUserId(
        UserId $userId,
        PostStatus|null $status,
        PostId|null $cursor,
        int $limit,
    ): PostCollection {
        $statusValue = $status?->value;

        return new PostCollection(
            $this->select()
                ->where('user_id', $userId->value())
                ->when(
                    condition: $statusValue !== null,
                    callback: static function (WhenSelect $query) use ($statusValue): void {
                        $query->where('status', $statusValue);
                    },
                )
                ->cursorById(cursor: $cursor?->value(), limit: $limit)
                ->fetchAll(),
        );
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

        return new PostCollection(
            $this->select()
                ->where('user_id', $userId->value())
                ->where('deleted_at', '=', null)
                ->when(
                    condition: $statusValue !== null,
                    callback: static function (WhenSelect $query) use ($statusValue): void {
                        $query->where('status', $statusValue);
                    },
                )
                ->when(
                    condition: $excludeStatusValue !== null,
                    callback: static function (WhenSelect $query) use ($excludeStatusValue): void {
                        $query->where('status', '!=', $excludeStatusValue);
                    },
                )
                ->cursorById(cursor: $cursor?->value(), limit: $limit)
                ->fetchAll(),
        );
    }

    #[\Override]
    public function findRepostsOf(PostId $postId): PostCollection
    {
        return new PostCollection(
            $this->select()
                ->where('parent_post_id', $postId->value())
                ->orderBy(expression: 'id', direction: 'DESC')
                ->fetchAll(),
        );
    }

    #[\Override]
    public function findMediaByPostId(PostId $postId): PostMediaCollection
    {
        return new PostMediaCollection(
            $this->mediaSelect()
                ->where('post_id', $postId->value())
                ->orderBy(expression: 'position', direction: 'ASC')
                ->fetchAll(),
        );
    }

    #[\Override]
    public function findMediaByPostIds(PostId ...$postIds): PostMediaCollection
    {
        if ($postIds === []) {
            return new PostMediaCollection();
        }

        return new PostMediaCollection(
            $this->mediaSelect()
                ->where('post_id', 'in', new Parameter(\array_map(
                    static fn(PostId $postId): string => $postId->value(),
                    $postIds,
                )))
                ->orderBy(expression: 'post_id', direction: 'ASC')
                ->orderBy(expression: 'position', direction: 'ASC')
                ->fetchAll(),
        );
    }

    #[\Override]
    public function findTagsByPostId(PostId $postId): PostTagCollection
    {
        return new PostTagCollection(
            $this->tagSelect()
                ->where('post_id', $postId->value())
                ->fetchAll(),
        );
    }

    #[\Override]
    public function findTagsByPostIds(PostId ...$postIds): PostTagCollection
    {
        if ($postIds === []) {
            return new PostTagCollection();
        }

        return new PostTagCollection(
            $this->tagSelect()
                ->where('post_id', 'in', new Parameter(\array_map(
                    static fn(PostId $postId): string => $postId->value(),
                    $postIds,
                )))
                ->fetchAll(),
        );
    }

    #[\Override]
    public function findTagsByTagId(PostTagReference $tagId): PostTagCollection
    {
        return new PostTagCollection(
            $this->tagSelect()
                ->where('tag_id', $tagId->value())
                ->fetchAll(),
        );
    }

    #[\Override]
    public function findMentionsByPostId(PostId $postId): PostMentionCollection
    {
        return new PostMentionCollection(
            $this->mentionSelect()
                ->where('post_id', $postId->value())
                ->orderBy(expression: 'id', direction: 'DESC')
                ->fetchAll(),
        );
    }

    #[\Override]
    public function findMentionsByUserId(UserId $userId): PostMentionCollection
    {
        return new PostMentionCollection(
            $this->mentionSelect()
                ->where('user_id', $userId->value())
                ->orderBy(expression: 'id', direction: 'DESC')
                ->fetchAll(),
        );
    }

    #[\Override]
    public function findLikeByPostAndUser(PostId $postId, UserId $userId): PostLike|null
    {
        /** @var PostLike|null $like */
        $like = $this->likeSelect()->fetchOne([
            'post_id' => $postId->value(),
            'user_id' => $userId->value(),
        ]);

        return $like;
    }

    #[\Override]
    public function existsLikeByPostAndUser(PostId $postId, UserId $userId): bool
    {
        return $this->findLikeByPostAndUser(postId: $postId, userId: $userId) !== null;
    }

    #[\Override]
    public function findLikesByUserId(UserId $userId): PostLikeCollection
    {
        return new PostLikeCollection(
            $this->likeSelect()
                ->where('user_id', $userId->value())
                ->orderBy(expression: 'id', direction: 'DESC')
                ->fetchAll(),
        );
    }

    #[\Override]
    public function findLikesByUserAndPostIds(UserId $userId, PostId ...$postIds): PostLikeCollection
    {
        if ($postIds === []) {
            return new PostLikeCollection();
        }

        return new PostLikeCollection(
            $this->likeSelect()
                ->where('user_id', $userId->value())
                ->where('post_id', 'in', new Parameter(\array_map(
                    static fn(PostId $postId): string => $postId->value(),
                    $postIds,
                )))
                ->fetchAll(),
        );
    }

    #[\Override]
    public function add(Post $post): void
    {
        $this->entityManager->persist($post);
    }

    #[\Override]
    public function save(Post $post): void
    {
        $this->entityManager
            ->persist($post)
            ->run();
    }

    #[\Override]
    public function saveWithOriginal(Post $post, Post $original): void
    {
        $this->entityManager
            ->persist($post)
            ->persist($original)
            ->run();
    }

    #[\Override]
    public function saveWithLike(Post $post, PostLike $like): void
    {
        $this->entityManager
            ->persist($like)
            ->persist($post)
            ->run();
    }

    #[\Override]
    public function removeLike(PostLike $like, Post $post): void
    {
        $this->entityManager
            ->delete($like)
            ->persist($post)
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

        $this->entityManager->persist($original);

        $this->entityManager->run();
    }

    /**
     * Кладёт запись и её внутренние сущности в один прогон EntityManager, но не выполняет его:
     * прогон делает вызвавший публичный метод — сам по себе (создание записи) или после оригинала
     * репоста, чтобы порядок записи относительно счётчика оригинала остался прежним.
     */
    private function stagePostWithAttachments(
        Post $post,
        PostMediaCollection $media,
        PostTagCollection $tags,
        PostMentionCollection $mentions,
    ): void {
        $this->entityManager->persist($post);

        foreach ($media as $postMedia) {
            $this->entityManager->persist($postMedia);
        }

        foreach ($tags as $postTag) {
            $this->entityManager->persist($postTag);
        }

        foreach ($mentions as $postMention) {
            $this->entityManager->persist($postMention);
        }
    }

    /**
     * Выборка по таблице вложений. Собственного репозитория у вложения нет, поэтому запрос строится
     * тем же общим примитивом, что и запрос корня.
     *
     * @return WhenSelect<PostMedia>
     */
    private function mediaSelect(): WhenSelect
    {
        return $this->innerSelect(PostMedia::class);
    }

    /**
     * @return WhenSelect<PostTag>
     */
    private function tagSelect(): WhenSelect
    {
        return $this->innerSelect(PostTag::class);
    }

    /**
     * @return WhenSelect<PostMention>
     */
    private function mentionSelect(): WhenSelect
    {
        return $this->innerSelect(PostMention::class);
    }

    /**
     * @return WhenSelect<PostLike>
     */
    private function likeSelect(): WhenSelect
    {
        return $this->innerSelect(PostLike::class);
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
