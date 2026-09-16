<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Query\GetCommentReplies;

use App\Modules\Posts\Application\Contract\CommentViewerReader;
use App\Modules\Posts\Application\Result\AuthorResult;
use App\Modules\Posts\Application\Result\CommentResultCollection;
use App\Modules\Posts\Domain\Collection\CommentCollection;
use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Domain\Repository\CommentRepository;
use App\Modules\Posts\Domain\Repository\PostRepository;
use App\Modules\Posts\Domain\Service\PostVisibilityPolicy;
use App\Modules\Posts\Domain\Exception\CommentNotFoundException;
use App\Modules\Posts\Domain\Exception\PostNotFoundException;
use App\Modules\User\Public\Contract\UserContract;
use App\Shared\Domain\Pagination\CursorSlice;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Ответы на комментарий с cursor-пагинацией. Родительский комментарий должен существовать и быть не
 * удалён, а его запись — быть видна зрителю (иначе 404); удалённые ответы скрыты репозиторием. Guard
 * на удалённого родителя симметричен записи ответа (ReplyComment): на удалённый комментарий нельзя
 * ни ответить, ни прочитать его ответы.
 */
final readonly class GetCommentRepliesHandler
{
    public function __construct(
        private CommentRepository $commentRepository,
        private PostRepository $postRepository,
        private UserContract $users,
        private CommentViewerReader $commentViewerReader,
    ) {}

    #[LogOperation]
    public function handle(GetCommentRepliesQuery $query): GetCommentRepliesResult
    {
        $viewer = UserId::fromString($query->authUserId);

        $parent = $this->commentRepository->findById(CommentId::fromString($query->commentId))
            ?? throw new CommentNotFoundException();

        if ($parent->isDeleted()) {
            throw new CommentNotFoundException();
        }

        // Запись существующего комментария всегда есть (FK), но проверку null оставляем в одном
        // условии с видимостью: читать ответы можно только у видимой зрителю записи.
        $post = $this->postRepository->findById($parent->postId);

        if ($post === null || !PostVisibilityPolicy::isVisibleTo(post: $post, viewer: $viewer)) {
            throw new PostNotFoundException();
        }

        $cursor = $query->cursor !== null ? CommentId::fromString($query->cursor) : null;

        $slice = CursorSlice::fromOverfetched(
            overfetched: $this->commentRepository->findReplies(
                parentId: $parent->id,
                cursor: $cursor,
                limit: $query->limit + 1,
            ),
            limit: $query->limit,
            cursorOf: static fn(Comment $comment): string => $comment->id->value(),
        );

        return new GetCommentRepliesResult(
            replies: $this->fromComments(comments: $slice->items, viewer: $viewer),
            nextCursor: $slice->nextCursor,
        );
    }

    private function fromComments(CommentCollection $comments, UserId $viewer): CommentResultCollection
    {
        if ($comments->isEmpty()) {
            return new CommentResultCollection();
        }

        $authors = AuthorResult::mapFromProfiles($this->users->profilesByIds($this->authorIds($comments)));
        $commentIds = $comments->mapToList(static fn(Comment $comment): string => $comment->id->value());
        $likedCommentIds = $this->commentViewerReader->likedByMe(commentIds: $commentIds, viewerId: $viewer->value());

        return CommentResultCollection::fromComments(
            comments: $comments,
            authors: $authors,
            likedCommentIds: $likedCommentIds,
        );
    }

    /**
     * @return list<string>
     */
    private function authorIds(CommentCollection $comments): array
    {
        return \array_values($comments
            ->toBase()
            ->map(static fn(Comment $comment): string => $comment->userId->value())
            ->unique()
            ->all());
    }
}
