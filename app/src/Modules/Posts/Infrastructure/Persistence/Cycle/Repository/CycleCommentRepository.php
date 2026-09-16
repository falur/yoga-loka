<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Posts\Domain\Collection\CommentCollection;
use App\Modules\Posts\Domain\Collection\CommentLikeCollection;
use App\Modules\Posts\Domain\Collection\CommentMentionCollection;
use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\Entity\CommentLike;
use App\Modules\Posts\Domain\Entity\CommentMention;
use App\Modules\Posts\Domain\Repository\CommentRepository;
use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use App\Shared\Infrastructure\Persistence\Cycle\WhenSelect;
use Cycle\Database\Injection\Parameter;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<Comment>
 */
final class CycleCommentRepository extends AbstractRepository implements CommentRepository
{
    /**
     * @param Select<Comment> $select
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
    public function findById(CommentId $commentId): Comment|null
    {
        return $this->findByPK($commentId->value());
    }

    #[\Override]
    public function findByPostId(PostId $postId, CommentId|null $cursor, int $limit): CommentCollection
    {
        return new CommentCollection(
            $this->select()
                ->where('post_id', $postId->value())
                ->cursorById(cursor: $cursor?->value(), limit: $limit)
                ->fetchAll(),
        );
    }

    #[\Override]
    public function findTopLevelByPostId(PostId $postId, CommentId|null $cursor, int $limit): CommentCollection
    {
        return new CommentCollection(
            $this->select()
                ->where('post_id', $postId->value())
                ->where('parent_comment_id', '=', null)
                ->where('deleted_at', '=', null)
                ->cursorById(cursor: $cursor?->value(), limit: $limit)
                ->fetchAll(),
        );
    }

    #[\Override]
    public function findReplies(CommentId $parentId, CommentId|null $cursor, int $limit): CommentCollection
    {
        return new CommentCollection(
            $this->select()
                ->where('parent_comment_id', $parentId->value())
                ->where('deleted_at', '=', null)
                ->cursorById(cursor: $cursor?->value(), limit: $limit)
                ->fetchAll(),
        );
    }

    #[\Override]
    public function findLikeByCommentAndUser(CommentId $commentId, UserId $userId): CommentLike|null
    {
        /** @var CommentLike|null $like */
        $like = $this->likeSelect()->fetchOne([
            'comment_id' => $commentId->value(),
            'user_id' => $userId->value(),
        ]);

        return $like;
    }

    #[\Override]
    public function existsLikeByCommentAndUser(CommentId $commentId, UserId $userId): bool
    {
        return $this->findLikeByCommentAndUser(commentId: $commentId, userId: $userId) !== null;
    }

    #[\Override]
    public function findLikesByUserAndCommentIds(UserId $userId, CommentId ...$commentIds): CommentLikeCollection
    {
        if ($commentIds === []) {
            return new CommentLikeCollection();
        }

        return new CommentLikeCollection(
            $this->likeSelect()
                ->where('user_id', $userId->value())
                ->where('comment_id', 'in', new Parameter(\array_map(
                    static fn(CommentId $commentId): string => $commentId->value(),
                    $commentIds,
                )))
                ->fetchAll(),
        );
    }

    #[\Override]
    public function findMentionsByCommentId(CommentId $commentId): CommentMentionCollection
    {
        return new CommentMentionCollection(
            $this->mentionSelect()
                ->where('comment_id', $commentId->value())
                ->orderBy(expression: 'id', direction: 'DESC')
                ->fetchAll(),
        );
    }

    #[\Override]
    public function add(Comment $comment): void
    {
        $this->entityManager->persist($comment);
    }

    #[\Override]
    public function save(Comment $comment): void
    {
        $this->entityManager
            ->persist($comment)
            ->run();
    }

    #[\Override]
    public function saveWithLike(Comment $comment, CommentLike $like): void
    {
        $this->entityManager
            ->persist($like)
            ->persist($comment)
            ->run();
    }

    #[\Override]
    public function removeLike(CommentLike $like, Comment $comment): void
    {
        $this->entityManager
            ->delete($like)
            ->persist($comment)
            ->run();
    }

    #[\Override]
    public function addWithMentions(Comment $comment, CommentMentionCollection $mentions): void
    {
        $this->entityManager->persist($comment);

        foreach ($mentions as $mention) {
            $this->entityManager->persist($mention);
        }
    }

    /**
     * Выборка по таблице лайков комментария. Собственного репозитория у лайка нет, поэтому запрос
     * строится тем же общим примитивом, что и запрос корня.
     *
     * @return WhenSelect<CommentLike>
     */
    private function likeSelect(): WhenSelect
    {
        return $this->innerSelect(CommentLike::class);
    }

    /**
     * @return WhenSelect<CommentMention>
     */
    private function mentionSelect(): WhenSelect
    {
        return $this->innerSelect(CommentMention::class);
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
