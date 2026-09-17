<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Spiral\Http\Controller;

use App\Modules\Auth\Public\Attribute\AuthenticatedRoute;
use App\Modules\Posts\Application\Command\CreatePost\CreatePostCommand;
use App\Modules\Posts\Application\Command\CreatePost\CreatePostHandler;
use App\Modules\Posts\Application\Command\DeletePost\DeletePostCommand;
use App\Modules\Posts\Application\Command\DeletePost\DeletePostHandler;
use App\Modules\Posts\Application\Command\LikePost\LikePostCommand;
use App\Modules\Posts\Application\Command\LikePost\LikePostHandler;
use App\Modules\Posts\Application\Command\PublishPost\PublishPostCommand;
use App\Modules\Posts\Application\Command\PublishPost\PublishPostHandler;
use App\Modules\Posts\Application\Command\RepostPost\RepostPostCommand;
use App\Modules\Posts\Application\Command\RepostPost\RepostPostHandler;
use App\Modules\Posts\Application\Command\UnlikePost\UnlikePostCommand;
use App\Modules\Posts\Application\Command\UnlikePost\UnlikePostHandler;
use App\Modules\Posts\Application\Query\GetMyFeed\GetMyFeedHandler;
use App\Modules\Posts\Application\Query\GetMyFeed\GetMyFeedQuery;
use App\Modules\Posts\Application\Query\GetPost\GetPostHandler;
use App\Modules\Posts\Application\Query\GetPost\GetPostQuery;
use App\Modules\Posts\Application\Query\GetUserFeed\GetUserFeedHandler;
use App\Modules\Posts\Application\Query\GetUserFeed\GetUserFeedQuery;
use App\Modules\Posts\Application\Result\PostResult;
use App\Modules\Posts\Infrastructure\Spiral\Http\Filter\CreatePostFilter;
use App\Modules\Posts\Infrastructure\Spiral\Http\Filter\DeletePostFilter;
use App\Modules\Posts\Infrastructure\Spiral\Http\Filter\GetPostFilter;
use App\Modules\Posts\Infrastructure\Spiral\Http\Filter\GetUserFeedFilter;
use App\Modules\Posts\Infrastructure\Spiral\Http\Filter\LikePostFilter;
use App\Modules\Posts\Infrastructure\Spiral\Http\Filter\PublishPostFilter;
use App\Modules\Posts\Infrastructure\Spiral\Http\Filter\RepostPostFilter;
use App\Modules\Posts\Infrastructure\Spiral\Http\Filter\UnlikePostFilter;
use App\Modules\Posts\Infrastructure\Spiral\Http\Resource\PostResource;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralCqrs\QueryBusInterface;
use GianTiaga\SpiralOpenApi\Response\DataResponse;
use GianTiaga\SpiralOpenApi\Response\EmptySuccessResponse;
use GianTiaga\SpiralOpenApi\Response\PaginationMetaResponse;
use GianTiaga\SpiralOpenApi\Response\PaginationResponse;
use Spiral\Router\Annotation\Route;

final readonly class PostController
{
    /**
     * @return DataResponse<PostResource>
     */
    #[Route(
        route: '/api/v1/posts',
        name: 'api.v1.posts.create',
        methods: ['POST'],
        group: 'api',
    )]
    #[AuthenticatedRoute]
    public function create(
        CreatePostFilter $createPostFilter,
        CommandBusInterface $commandBus,
        CreatePostHandler $createPostHandler,
        QueryBusInterface $queryBus,
        GetPostHandler $getPostHandler,
    ): DataResponse {
        $result = $commandBus->dispatch(
            command: new CreatePostCommand(
                authUserId: $createPostFilter->authUserId,
                text: $createPostFilter->text,
                draft: $createPostFilter->draft,
                mediaIds: $createPostFilter->mediaIds,
                tags: $createPostFilter->tags,
                mentions: $createPostFilter->mentions,
            ),
            handler: $createPostHandler->handle(...),
        );

        $post = $this->getPost(postId: $result->postId, authUserId: $createPostFilter->authUserId, queryBus: $queryBus, getPostHandler: $getPostHandler);

        return new DataResponse(PostResource::fromResult($post));
    }

    /**
     * @return DataResponse<PostResource>
     */
    #[Route(
        route: '/api/v1/posts/<id>/publish',
        name: 'api.v1.posts.publish',
        methods: ['POST'],
        group: 'api',
    )]
    #[AuthenticatedRoute]
    public function publish(
        string $id,
        PublishPostFilter $publishPostFilter,
        CommandBusInterface $commandBus,
        PublishPostHandler $publishPostHandler,
        QueryBusInterface $queryBus,
        GetPostHandler $getPostHandler,
    ): DataResponse {
        $result = $commandBus->dispatch(
            command: new PublishPostCommand(
                authUserId: $publishPostFilter->authUserId,
                postId: $publishPostFilter->id,
            ),
            handler: $publishPostHandler->handle(...),
        );

        $post = $this->getPost(postId: $result->postId, authUserId: $publishPostFilter->authUserId, queryBus: $queryBus, getPostHandler: $getPostHandler);

        return new DataResponse(PostResource::fromResult($post));
    }

    /**
     * @return DataResponse<PostResource>
     */
    #[Route(
        route: '/api/v1/posts/<id>/repost',
        name: 'api.v1.posts.repost',
        methods: ['POST'],
        group: 'api',
    )]
    #[AuthenticatedRoute]
    public function repost(
        string $id,
        RepostPostFilter $repostPostFilter,
        CommandBusInterface $commandBus,
        RepostPostHandler $repostPostHandler,
        QueryBusInterface $queryBus,
        GetPostHandler $getPostHandler,
    ): DataResponse {
        $result = $commandBus->dispatch(
            command: new RepostPostCommand(
                authUserId: $repostPostFilter->authUserId,
                postId: $repostPostFilter->id,
                text: $repostPostFilter->text,
                mediaIds: $repostPostFilter->mediaIds,
                tags: $repostPostFilter->tags,
                mentions: $repostPostFilter->mentions,
            ),
            handler: $repostPostHandler->handle(...),
        );

        $post = $this->getPost(postId: $result->postId, authUserId: $repostPostFilter->authUserId, queryBus: $queryBus, getPostHandler: $getPostHandler);

        return new DataResponse(PostResource::fromResult($post));
    }

    #[Route(
        route: '/api/v1/posts/<id>',
        name: 'api.v1.posts.delete',
        methods: ['DELETE'],
        group: 'api',
    )]
    #[AuthenticatedRoute]
    public function delete(
        string $id,
        DeletePostFilter $deletePostFilter,
        CommandBusInterface $commandBus,
        DeletePostHandler $deletePostHandler,
    ): EmptySuccessResponse {
        $commandBus->dispatch(
            command: new DeletePostCommand(
                authUserId: $deletePostFilter->authUserId,
                postId: $deletePostFilter->id,
            ),
            handler: $deletePostHandler->handle(...),
        );

        return new EmptySuccessResponse();
    }

    #[Route(
        route: '/api/v1/posts/<id>/like',
        name: 'api.v1.posts.like',
        methods: ['POST'],
        group: 'api',
    )]
    #[AuthenticatedRoute]
    public function like(
        string $id,
        LikePostFilter $likePostFilter,
        CommandBusInterface $commandBus,
        LikePostHandler $likePostHandler,
    ): EmptySuccessResponse {
        $commandBus->dispatch(
            command: new LikePostCommand(
                authUserId: $likePostFilter->authUserId,
                postId: $likePostFilter->id,
            ),
            handler: $likePostHandler->handle(...),
        );

        return new EmptySuccessResponse();
    }

    #[Route(
        route: '/api/v1/posts/<id>/like',
        name: 'api.v1.posts.unlike',
        methods: ['DELETE'],
        group: 'api',
    )]
    #[AuthenticatedRoute]
    public function unlike(
        string $id,
        UnlikePostFilter $unlikePostFilter,
        CommandBusInterface $commandBus,
        UnlikePostHandler $unlikePostHandler,
    ): EmptySuccessResponse {
        $commandBus->dispatch(
            command: new UnlikePostCommand(
                authUserId: $unlikePostFilter->authUserId,
                postId: $unlikePostFilter->id,
            ),
            handler: $unlikePostHandler->handle(...),
        );

        return new EmptySuccessResponse();
    }

    /**
     * @return DataResponse<PostResource>
     */
    #[Route(
        route: '/api/v1/posts/<id>',
        name: 'api.v1.posts.show',
        methods: ['GET'],
        group: 'api',
    )]
    #[AuthenticatedRoute]
    public function show(
        string $id,
        GetPostFilter $getPostFilter,
        GetPostHandler $getPostHandler,
        QueryBusInterface $queryBus,
    ): DataResponse {
        $post = $this->getPost(postId: $getPostFilter->id, authUserId: $getPostFilter->authUserId, queryBus: $queryBus, getPostHandler: $getPostHandler);

        return new DataResponse(PostResource::fromResult($post));
    }

    /**
     * @return PaginationResponse<PostResource>
     */
    #[Route(
        route: '/api/v1/posts/user/<id>',
        name: 'api.v1.posts.user_feed',
        methods: ['GET'],
        group: 'api',
    )]
    #[AuthenticatedRoute]
    public function userFeed(
        string $id,
        GetUserFeedFilter $getUserFeedFilter,
        GetMyFeedHandler $getMyFeedHandler,
        GetUserFeedHandler $getUserFeedHandler,
        QueryBusInterface $queryBus,
    ): PaginationResponse {
        // Один и тот же маршрут обслуживает оба случая — контроллер решает по совпадению <id> пути
        // с текущим авторизованным пользователем, какой из двух Query вызвать; JSON-форма и правила
        // видимости в обоих случаях не меняются.
        if ($getUserFeedFilter->id === $getUserFeedFilter->authUserId) {
            $result = $queryBus->dispatch(
                query: new GetMyFeedQuery(
                    authUserId: $getUserFeedFilter->authUserId,
                    cursor: $getUserFeedFilter->cursor,
                    limit: $getUserFeedFilter->limit,
                ),
                handler: $getMyFeedHandler->handle(...),
            );
        } else {
            $result = $queryBus->dispatch(
                query: new GetUserFeedQuery(
                    ownerUserId: $getUserFeedFilter->id,
                    authUserId: $getUserFeedFilter->authUserId,
                    cursor: $getUserFeedFilter->cursor,
                    limit: $getUserFeedFilter->limit,
                ),
                handler: $getUserFeedHandler->handle(...),
            );
        }

        $resources = $result->posts->mapToList(
            static fn(PostResult $post): PostResource => PostResource::fromResult($post),
        );

        return new PaginationResponse(
            data: $resources,
            meta: new PaginationMetaResponse(nextCursor: $result->nextCursor, limit: $getUserFeedFilter->limit),
        );
    }

    private function getPost(string $postId, string $authUserId, QueryBusInterface $queryBus, GetPostHandler $getPostHandler): PostResult
    {
        return $queryBus->dispatch(
            query: new GetPostQuery(postId: $postId, authUserId: $authUserId),
            handler: $getPostHandler->handle(...),
        );
    }
}
