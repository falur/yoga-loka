<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Query\GetComment;

use App\Modules\Posts\Application\Contract\CommentViewerReader;
use App\Modules\Posts\Application\Result\AuthorResult;
use App\Modules\Posts\Application\Result\CommentResult;
use App\Modules\Posts\Domain\Repository\CommentRepository;
use App\Modules\Posts\Domain\Repository\PostRepository;
use App\Modules\Posts\Domain\Service\PostVisibilityPolicy;
use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Domain\Exception\CommentNotFoundException;
use App\Modules\Posts\Domain\Exception\PostNotFoundException;
use App\Modules\User\Public\Contract\UserContract;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Чтение одного комментария. Невидимый зрителю (удалён либо запись комментария невидима зрителю) ->
 * 404. Заводится по образцу GetPost — для дочитывания формы ответа Command handler-ов
 * CommentPost/ReplyComment, у которых сегодня нет своего Query.
 */
final readonly class GetCommentHandler
{
    public function __construct(
        private CommentRepository $commentRepository,
        private PostRepository $postRepository,
        private UserContract $users,
        private CommentViewerReader $commentViewerReader,
        private PostVisibilityPolicy $postVisibilityPolicy,
    ) {}

    #[LogOperation]
    public function handle(GetCommentQuery $query): CommentResult
    {
        $viewer = UserId::fromString($query->authUserId);

        $comment = $this->commentRepository->findById(CommentId::fromString($query->commentId))
            ?? throw new CommentNotFoundException();

        if ($comment->isDeleted()) {
            throw new CommentNotFoundException();
        }

        // Запись существующего комментария всегда есть (FK), но проверку null оставляем в одном
        // условии с видимостью: читать комментарий можно только у видимой зрителю записи.
        $post = $this->postRepository->findById($comment->postId);

        if ($post === null || !$this->postVisibilityPolicy->isVisibleTo(post: $post, viewer: $viewer)) {
            throw new PostNotFoundException();
        }

        $likedByMe = $this->commentViewerReader
            ->likedByMe(commentIds: [$comment->id->value()], viewerId: $viewer->value())
            ->isLiked($comment->id->value());

        return CommentResult::fromEntity(
            comment: $comment,
            author: AuthorResult::fromProfile($this->users->profile($comment->userId->value())),
            likedByMe: $likedByMe,
        );
    }
}
