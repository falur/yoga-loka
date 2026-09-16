<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Query\GetPostComments;

use App\Modules\Posts\Application\Contract\CommentViewerReader;
use App\Modules\Posts\Application\Result\AuthorResult;
use App\Modules\Posts\Application\Result\CommentResultCollection;
use App\Modules\Posts\Domain\Collection\CommentCollection;
use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\Repository\CommentRepository;
use App\Modules\Posts\Domain\Repository\PostRepository;
use App\Modules\Posts\Domain\Service\PostVisibilityPolicy;
use App\Modules\Posts\Domain\Exception\PostNotFoundException;
use App\Modules\User\Public\Contract\UserContract;
use App\Shared\Domain\Pagination\CursorSlice;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Комментарии верхнего уровня записи с cursor-пагинацией. Запись должна быть видна зрителю (иначе
 * 404); удалённые комментарии скрыты репозиторием. Авторы — пакетно через User/Public, флаг
 * likedByMe — пакетно через CommentViewerReader.
 */
final readonly class GetPostCommentsHandler
{
    public function __construct(
        private PostRepository $postRepository,
        private CommentRepository $commentRepository,
        private UserContract $users,
        private CommentViewerReader $commentViewerReader,
    ) {}

    #[LogOperation]
    public function handle(GetPostCommentsQuery $query): GetPostCommentsResult
    {
        $viewer = UserId::fromString($query->authUserId);

        $post = $this->postRepository->findById(PostId::fromString($query->postId))
            ?? throw new PostNotFoundException();

        if (!PostVisibilityPolicy::isVisibleTo(post: $post, viewer: $viewer)) {
            throw new PostNotFoundException();
        }

        $cursor = $query->cursor !== null ? CommentId::fromString($query->cursor) : null;

        $slice = CursorSlice::fromOverfetched(
            overfetched: $this->commentRepository->findTopLevelByPostId(
                postId: $post->id,
                cursor: $cursor,
                limit: $query->limit + 1,
            ),
            limit: $query->limit,
            cursorOf: static fn(Comment $comment): string => $comment->id->value(),
        );

        return new GetPostCommentsResult(
            comments: $this->fromComments(comments: $slice->items, viewer: $viewer),
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
