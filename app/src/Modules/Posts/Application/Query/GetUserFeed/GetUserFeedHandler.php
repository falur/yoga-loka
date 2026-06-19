<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Query\GetUserFeed;

use App\Modules\Posts\Application\View\PostViewAssembler;
use App\Modules\Posts\Domain\Collection\PostCollection;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Repository\PostRepository;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Лента записей автора с cursor-пагинацией. Владельцу видны его черновики и опубликованные (все
 * статусы, кроме заблокированных модерацией), посторонним — только опубликованные. Заблокированные
 * не видны никому, как в PostVisibilityPolicy и GetPost. Мягко удалённые скрыты репозиторием.
 */
final readonly class GetUserFeedHandler
{
    public function __construct(
        private PostRepository $postRepository,
        private PostViewAssembler $postViewAssembler,
    ) {}

    #[LogOperation]
    public function handle(GetUserFeedQuery $query): GetUserFeedResult
    {
        $viewer = UserId::fromString($query->authUserId);
        $owner = UserId::fromString($query->ownerUserId);
        $isOwner = $owner->equals($viewer);
        $status = $isOwner ? null : PostStatus::Published;
        $excludeStatus = $isOwner ? PostStatus::Blocked : null;
        $cursor = $query->cursor !== null ? PostId::fromString($query->cursor) : null;

        $page = $this->postRepository->findVisibleByUserId(
            userId: $owner,
            status: $status,
            excludeStatus: $excludeStatus,
            cursor: $cursor,
            limit: $query->limit + 1,
        )->all();

        if (\count($page) <= $query->limit) {
            return new GetUserFeedResult(
                posts: $this->postViewAssembler->fromPosts(posts: new PostCollection($page), viewer: $viewer),
                nextCursor: null,
            );
        }

        $visible = \array_slice(array: $page, offset: 0, length: $query->limit);

        return new GetUserFeedResult(
            posts: $this->postViewAssembler->fromPosts(posts: new PostCollection($visible), viewer: $viewer),
            nextCursor: $visible[\count($visible) - 1]->id->value(),
        );
    }
}
