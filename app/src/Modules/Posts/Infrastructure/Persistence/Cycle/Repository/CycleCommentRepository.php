<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Posts\Domain\Collection\CommentCollection;
use App\Modules\Posts\Domain\Collection\CommentLikeCollection;
use App\Modules\Posts\Domain\Collection\CommentMentionCollection;
use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\Entity\CommentLike;
use App\Modules\Posts\Domain\Repository\CommentRepository;
use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns\CommentColumns;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns\CommentLikeColumns;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns\CommentMentionColumns;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CycleCommentEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CycleCommentLikeEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CycleCommentMentionEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Mapper\CommentMapper;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use App\Shared\Infrastructure\Persistence\Cycle\WhenSelect;
use Cycle\Database\Injection\Parameter;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<CycleCommentEntity>
 */
final class CycleCommentRepository extends AbstractRepository implements CommentRepository
{
    /**
     * @param Select<CycleCommentEntity> $select
     */
    public function __construct(
        Select $select,
        private ORM $orm,
        string $role,
        private EntityManagerInterface $entityManager,
        private CommentMapper $commentMapper,
    ) {
        parent::__construct(select: $select, orm: $orm, role: $role);
    }

    #[\Override]
    public function findById(CommentId $commentId): Comment|null
    {
        /** @var CycleCommentEntity|null $cycleEntity */
        $cycleEntity = $this->findByPK($commentId->value());

        return $cycleEntity === null ? null : $this->commentMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function findByPostId(PostId $postId, CommentId|null $cursor, int $limit): CommentCollection
    {
        $commentCollection = new CommentCollection();

        /** @var iterable<CycleCommentEntity> $cycleEntities */
        $cycleEntities = $this->select()
            ->where(CommentColumns::POST_ID, $postId->value())
            ->cursorById(cursor: $cursor?->value(), limit: $limit)
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $commentCollection->push($this->commentMapper->toDomain($cycleEntity));
        }

        return $commentCollection;
    }

    #[\Override]
    public function findTopLevelByPostId(PostId $postId, CommentId|null $cursor, int $limit): CommentCollection
    {
        $commentCollection = new CommentCollection();

        /** @var iterable<CycleCommentEntity> $cycleEntities */
        $cycleEntities = $this->select()
            ->where(CommentColumns::POST_ID, $postId->value())
            ->where(CommentColumns::PARENT_COMMENT_ID, '=', null)
            ->where(CommentColumns::DELETED_AT, '=', null)
            ->cursorById(cursor: $cursor?->value(), limit: $limit)
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $commentCollection->push($this->commentMapper->toDomain($cycleEntity));
        }

        return $commentCollection;
    }

    #[\Override]
    public function findReplies(CommentId $parentId, CommentId|null $cursor, int $limit): CommentCollection
    {
        $commentCollection = new CommentCollection();

        /** @var iterable<CycleCommentEntity> $cycleEntities */
        $cycleEntities = $this->select()
            ->where(CommentColumns::PARENT_COMMENT_ID, $parentId->value())
            ->where(CommentColumns::DELETED_AT, '=', null)
            ->cursorById(cursor: $cursor?->value(), limit: $limit)
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $commentCollection->push($this->commentMapper->toDomain($cycleEntity));
        }

        return $commentCollection;
    }

    #[\Override]
    public function findLikeByCommentAndUser(CommentId $commentId, UserId $userId): CommentLike|null
    {
        /** @var CycleCommentLikeEntity|null $cycleEntity */
        $cycleEntity = $this->likeSelect()->fetchOne([
            CommentLikeColumns::COMMENT_ID => $commentId->value(),
            CommentLikeColumns::USER_ID => $userId->value(),
        ]);

        return $cycleEntity === null ? null : $this->commentMapper->toCommentLikeDomain($cycleEntity);
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

        $likeCollection = new CommentLikeCollection();

        /** @var iterable<CycleCommentLikeEntity> $cycleEntities */
        $cycleEntities = $this->likeSelect()
            ->where(CommentLikeColumns::USER_ID, $userId->value())
            ->where(CommentLikeColumns::COMMENT_ID, 'in', new Parameter(\array_map(
                static fn(CommentId $commentId): string => $commentId->value(),
                $commentIds,
            )))
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $likeCollection->push($this->commentMapper->toCommentLikeDomain($cycleEntity));
        }

        return $likeCollection;
    }

    #[\Override]
    public function findMentionsByCommentId(CommentId $commentId): CommentMentionCollection
    {
        $mentionCollection = new CommentMentionCollection();

        /** @var iterable<CycleCommentMentionEntity> $cycleEntities */
        $cycleEntities = $this->mentionSelect()
            ->where(CommentMentionColumns::COMMENT_ID, $commentId->value())
            ->orderBy(expression: CommentMentionColumns::ID, direction: 'DESC')
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $mentionCollection->push($this->commentMapper->toCommentMentionDomain($cycleEntity));
        }

        return $mentionCollection;
    }

    #[\Override]
    public function add(Comment $comment): void
    {
        /** @var CycleCommentEntity|null $cycleEntity */
        $cycleEntity = $this->findByPK($comment->id->value());

        $this->entityManager->persist($this->commentMapper->toCycleEntity(comment: $comment, cycleEntity: $cycleEntity));
    }

    #[\Override]
    public function save(Comment $comment): void
    {
        /** @var CycleCommentEntity|null $cycleEntity */
        $cycleEntity = $this->findByPK($comment->id->value());

        $this->entityManager
            ->persist($this->commentMapper->toCycleEntity(comment: $comment, cycleEntity: $cycleEntity))
            ->run();
    }

    #[\Override]
    public function saveWithLike(Comment $comment, CommentLike $like): void
    {
        /** @var CycleCommentEntity|null $cycleEntity */
        $cycleEntity = $this->findByPK($comment->id->value());

        // Лайк здесь всегда свежесоздан (CommentLike::create() в LikeCommentHandler) — отдельного
        // findOne() перед persist() не нужно.
        $this->entityManager
            ->persist($this->commentMapper->toCommentLikeCycleEntity($like))
            ->persist($this->commentMapper->toCycleEntity(comment: $comment, cycleEntity: $cycleEntity))
            ->run();
    }

    #[\Override]
    public function removeLike(CommentLike $like, Comment $comment): void
    {
        /** @var CycleCommentLikeEntity|null $cycleLike */
        $cycleLike = $this->likeSelect()->where(CommentLikeColumns::ID, $like->id->value())->fetchOne();

        if ($cycleLike !== null) {
            $this->entityManager->delete($cycleLike);
        }

        /** @var CycleCommentEntity|null $cycleEntity */
        $cycleEntity = $this->findByPK($comment->id->value());

        $this->entityManager
            ->persist($this->commentMapper->toCycleEntity(comment: $comment, cycleEntity: $cycleEntity))
            ->run();
    }

    #[\Override]
    public function addWithMentions(Comment $comment, CommentMentionCollection $mentions): void
    {
        // Комментарий и упоминания здесь всегда свежесозданы (Comment::create() и построение
        // коллекции в CommentComposer) — отдельного findOne() перед persist() не нужно, как и для
        // вложений записи в CyclePostRepository::stagePostWithAttachments().
        $this->entityManager->persist($this->commentMapper->toCycleEntity($comment));

        foreach ($mentions as $mention) {
            $this->entityManager->persist($this->commentMapper->toCommentMentionCycleEntity($mention));
        }
    }

    /**
     * Выборка по таблице лайков комментария. Собственного репозитория у лайка нет, поэтому запрос
     * строится тем же общим примитивом, что и запрос корня.
     *
     * @return WhenSelect<CycleCommentLikeEntity>
     */
    private function likeSelect(): WhenSelect
    {
        return $this->innerSelect(CycleCommentLikeEntity::class);
    }

    /**
     * @return WhenSelect<CycleCommentMentionEntity>
     */
    private function mentionSelect(): WhenSelect
    {
        return $this->innerSelect(CycleCommentMentionEntity::class);
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
