<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Query\GetPostComments;

use App\Modules\Posts\Application\Post\PostVisibilityPolicy;
use App\Modules\Posts\Application\View\CommentViewAssembler;
use App\Modules\Posts\Domain\Collection\CommentCollection;
use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Repository\CommentRepository;
use App\Modules\Posts\Repository\PostRepository;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Комментарии верхнего уровня записи с cursor-пагинацией. Запись должна быть видна зрителю (иначе
 * 404); удалённые комментарии скрыты репозиторием.
 */
final readonly class GetPostCommentsHandler
{
    public function __construct(
        private PostRepository $postRepository,
        private CommentRepository $commentRepository,
        private CommentViewAssembler $commentViewAssembler,
    ) {}

    #[LogOperation]
    public function handle(GetPostCommentsQuery $query): GetPostCommentsResult
    {
        $viewer = UserId::fromString($query->authUserId);

        $post = $this->postRepository->findById(PostId::fromString($query->postId))
            ?? throw new NotFoundException('app.posts.not_found');

        if (!PostVisibilityPolicy::isVisibleTo(post: $post, viewer: $viewer)) {
            throw new NotFoundException('app.posts.not_found');
        }

        $cursor = $query->cursor !== null ? CommentId::fromString($query->cursor) : null;

        $page = $this->commentRepository->findTopLevelByPostId(
            postId: $post->id,
            cursor: $cursor,
            limit: $query->limit + 1,
        )->all();

        if (\count($page) <= $query->limit) {
            return new GetPostCommentsResult(
                comments: $this->commentViewAssembler->fromComments(comments: new CommentCollection($page), viewer: $viewer),
                nextCursor: null,
            );
        }

        $visible = \array_slice(array: $page, offset: 0, length: $query->limit);

        return new GetPostCommentsResult(
            comments: $this->commentViewAssembler->fromComments(comments: new CommentCollection($visible), viewer: $viewer),
            nextCursor: $visible[\count($visible) - 1]->id->value(),
        );
    }
}
