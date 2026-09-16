<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Query\GetPost;

use App\Modules\Posts\Application\Post\PostVisibilityPolicy;
use App\Modules\Posts\Application\View\PostView;
use App\Modules\Posts\Application\View\PostViewAssembler;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\Repository\PostRepository;
use App\Modules\Posts\Domain\Exception\PostNotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Чтение одной записи. Невидимая зрителю (чужой черновик/заблокированная/удалённая) -> 404.
 */
final readonly class GetPostHandler
{
    public function __construct(
        private PostRepository $postRepository,
        private PostViewAssembler $postViewAssembler,
    ) {}

    #[LogOperation]
    public function handle(GetPostQuery $query): PostView
    {
        $viewer = UserId::fromString($query->authUserId);

        $post = $this->postRepository->findById(PostId::fromString($query->postId))
            ?? throw new PostNotFoundException();

        if (!PostVisibilityPolicy::isVisibleTo(post: $post, viewer: $viewer)) {
            throw new PostNotFoundException();
        }

        return $this->postViewAssembler->fromPost(post: $post, viewer: $viewer);
    }
}
