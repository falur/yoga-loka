<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Query\GetCommentReplies;

use App\Modules\Posts\Application\Post\PostVisibilityPolicy;
use App\Modules\Posts\Application\View\CommentViewAssembler;
use App\Modules\Posts\Domain\Collection\CommentCollection;
use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Repository\CommentRepository;
use App\Modules\Posts\Repository\PostRepository;
use App\Shared\Domain\Exception\NotFoundException;
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
        private CommentViewAssembler $commentViewAssembler,
    ) {}

    #[LogOperation]
    public function handle(GetCommentRepliesQuery $query): GetCommentRepliesResult
    {
        $viewer = UserId::fromString($query->authUserId);

        $parent = $this->commentRepository->findById(CommentId::fromString($query->commentId))
            ?? throw new NotFoundException('app.posts.comment_not_found');

        if ($parent->isDeleted()) {
            throw new NotFoundException('app.posts.comment_not_found');
        }

        // Запись существующего комментария всегда есть (FK), но проверку null оставляем в одном
        // условии с видимостью: читать ответы можно только у видимой зрителю записи.
        $post = $this->postRepository->findById($parent->postId);

        if ($post === null || !PostVisibilityPolicy::isVisibleTo(post: $post, viewer: $viewer)) {
            throw new NotFoundException('app.posts.not_found');
        }

        $cursor = $query->cursor !== null ? CommentId::fromString($query->cursor) : null;

        $page = $this->commentRepository->findReplies(
            parentId: $parent->id,
            cursor: $cursor,
            limit: $query->limit + 1,
        )->all();

        if (\count($page) <= $query->limit) {
            return new GetCommentRepliesResult(
                replies: $this->commentViewAssembler->fromComments(comments: new CommentCollection($page), viewer: $viewer),
                nextCursor: null,
            );
        }

        $visible = \array_slice(array: $page, offset: 0, length: $query->limit);

        return new GetCommentRepliesResult(
            replies: $this->commentViewAssembler->fromComments(comments: new CommentCollection($visible), viewer: $viewer),
            nextCursor: $visible[\count($visible) - 1]->id->value(),
        );
    }
}
