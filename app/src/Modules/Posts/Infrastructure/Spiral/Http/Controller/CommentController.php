<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Spiral\Http\Controller;

use App\Modules\Auth\Public\Attribute\AuthenticatedRoute;
use App\Modules\Posts\Application\Command\CommentPost\CommentPostCommand;
use App\Modules\Posts\Application\Command\CommentPost\CommentPostHandler;
use App\Modules\Posts\Application\Command\DeleteComment\DeleteCommentCommand;
use App\Modules\Posts\Application\Command\DeleteComment\DeleteCommentHandler;
use App\Modules\Posts\Application\Command\LikeComment\LikeCommentCommand;
use App\Modules\Posts\Application\Command\LikeComment\LikeCommentHandler;
use App\Modules\Posts\Application\Command\ReplyComment\ReplyCommentCommand;
use App\Modules\Posts\Application\Command\ReplyComment\ReplyCommentHandler;
use App\Modules\Posts\Application\Command\UnlikeComment\UnlikeCommentCommand;
use App\Modules\Posts\Application\Command\UnlikeComment\UnlikeCommentHandler;
use App\Modules\Posts\Application\Query\GetCommentReplies\GetCommentRepliesHandler;
use App\Modules\Posts\Application\Query\GetCommentReplies\GetCommentRepliesQuery;
use App\Modules\Posts\Application\Query\GetPostComments\GetPostCommentsHandler;
use App\Modules\Posts\Application\Query\GetPostComments\GetPostCommentsQuery;
use App\Modules\Posts\Application\View\CommentView;
use App\Modules\Posts\Infrastructure\Spiral\Http\Filter\CommentPostFilter;
use App\Modules\Posts\Infrastructure\Spiral\Http\Filter\DeleteCommentFilter;
use App\Modules\Posts\Infrastructure\Spiral\Http\Filter\GetCommentRepliesFilter;
use App\Modules\Posts\Infrastructure\Spiral\Http\Filter\GetPostCommentsFilter;
use App\Modules\Posts\Infrastructure\Spiral\Http\Filter\LikeCommentFilter;
use App\Modules\Posts\Infrastructure\Spiral\Http\Filter\ReplyCommentFilter;
use App\Modules\Posts\Infrastructure\Spiral\Http\Filter\UnlikeCommentFilter;
use App\Modules\Posts\Infrastructure\Spiral\Http\Resource\CommentResource;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralCqrs\QueryBusInterface;
use GianTiaga\SpiralOpenApi\Response\DataResponse;
use GianTiaga\SpiralOpenApi\Response\EmptySuccessResponse;
use GianTiaga\SpiralOpenApi\Response\PaginationMetaResponse;
use GianTiaga\SpiralOpenApi\Response\PaginationResponse;
use Spiral\Router\Annotation\Route;

final readonly class CommentController
{
    /**
     * @return DataResponse<CommentResource>
     */
    #[Route(
        route: '/api/v1/posts/<id>/comments',
        name: 'api.v1.posts.comments.create',
        methods: ['POST'],
        group: 'api',
    )]
    #[AuthenticatedRoute]
    public function comment(
        string $id,
        CommentPostFilter $commentPostFilter,
        CommandBusInterface $commandBus,
        CommentPostHandler $commentPostHandler,
    ): DataResponse {
        $comment = $commandBus->dispatch(
            command: new CommentPostCommand(
                authUserId: $commentPostFilter->authUserId,
                postId: $commentPostFilter->id,
                text: $commentPostFilter->text,
                mentions: $commentPostFilter->mentions,
            ),
            handler: $commentPostHandler->handle(...),
        );

        return new DataResponse(CommentResource::fromView($comment));
    }

    /**
     * @return DataResponse<CommentResource>
     */
    #[Route(
        route: '/api/v1/posts/comments/<id>/replies',
        name: 'api.v1.posts.comments.reply',
        methods: ['POST'],
        group: 'api',
    )]
    #[AuthenticatedRoute]
    public function reply(
        string $id,
        ReplyCommentFilter $replyCommentFilter,
        CommandBusInterface $commandBus,
        ReplyCommentHandler $replyCommentHandler,
    ): DataResponse {
        $comment = $commandBus->dispatch(
            command: new ReplyCommentCommand(
                authUserId: $replyCommentFilter->authUserId,
                commentId: $replyCommentFilter->id,
                text: $replyCommentFilter->text,
                mentions: $replyCommentFilter->mentions,
            ),
            handler: $replyCommentHandler->handle(...),
        );

        return new DataResponse(CommentResource::fromView($comment));
    }

    #[Route(
        route: '/api/v1/posts/comments/<id>',
        name: 'api.v1.posts.comments.delete',
        methods: ['DELETE'],
        group: 'api',
    )]
    #[AuthenticatedRoute]
    public function delete(
        string $id,
        DeleteCommentFilter $deleteCommentFilter,
        CommandBusInterface $commandBus,
        DeleteCommentHandler $deleteCommentHandler,
    ): EmptySuccessResponse {
        $commandBus->dispatch(
            command: new DeleteCommentCommand(
                authUserId: $deleteCommentFilter->authUserId,
                commentId: $deleteCommentFilter->id,
            ),
            handler: $deleteCommentHandler->handle(...),
        );

        return new EmptySuccessResponse();
    }

    #[Route(
        route: '/api/v1/posts/comments/<id>/like',
        name: 'api.v1.posts.comments.like',
        methods: ['POST'],
        group: 'api',
    )]
    #[AuthenticatedRoute]
    public function like(
        string $id,
        LikeCommentFilter $likeCommentFilter,
        CommandBusInterface $commandBus,
        LikeCommentHandler $likeCommentHandler,
    ): EmptySuccessResponse {
        $commandBus->dispatch(
            command: new LikeCommentCommand(
                authUserId: $likeCommentFilter->authUserId,
                commentId: $likeCommentFilter->id,
            ),
            handler: $likeCommentHandler->handle(...),
        );

        return new EmptySuccessResponse();
    }

    #[Route(
        route: '/api/v1/posts/comments/<id>/like',
        name: 'api.v1.posts.comments.unlike',
        methods: ['DELETE'],
        group: 'api',
    )]
    #[AuthenticatedRoute]
    public function unlike(
        string $id,
        UnlikeCommentFilter $unlikeCommentFilter,
        CommandBusInterface $commandBus,
        UnlikeCommentHandler $unlikeCommentHandler,
    ): EmptySuccessResponse {
        $commandBus->dispatch(
            command: new UnlikeCommentCommand(
                authUserId: $unlikeCommentFilter->authUserId,
                commentId: $unlikeCommentFilter->id,
            ),
            handler: $unlikeCommentHandler->handle(...),
        );

        return new EmptySuccessResponse();
    }

    /**
     * @return PaginationResponse<CommentResource>
     */
    #[Route(
        route: '/api/v1/posts/<id>/comments',
        name: 'api.v1.posts.comments.list',
        methods: ['GET'],
        group: 'api',
    )]
    #[AuthenticatedRoute]
    public function list(
        string $id,
        GetPostCommentsFilter $getPostCommentsFilter,
        GetPostCommentsHandler $getPostCommentsHandler,
        QueryBusInterface $queryBus,
    ): PaginationResponse {
        $result = $queryBus->dispatch(
            query: new GetPostCommentsQuery(
                postId: $getPostCommentsFilter->id,
                authUserId: $getPostCommentsFilter->authUserId,
                cursor: $getPostCommentsFilter->cursor,
                limit: $getPostCommentsFilter->limit,
            ),
            handler: $getPostCommentsHandler->handle(...),
        );

        $resources = $result->comments->mapToList(
            static fn(CommentView $comment): CommentResource => CommentResource::fromView($comment),
        );

        return new PaginationResponse(
            data: $resources,
            meta: new PaginationMetaResponse(nextCursor: $result->nextCursor, limit: $getPostCommentsFilter->limit),
        );
    }

    /**
     * @return PaginationResponse<CommentResource>
     */
    #[Route(
        route: '/api/v1/posts/comments/<id>/replies',
        name: 'api.v1.posts.comments.replies',
        methods: ['GET'],
        group: 'api',
    )]
    #[AuthenticatedRoute]
    public function replies(
        string $id,
        GetCommentRepliesFilter $getCommentRepliesFilter,
        GetCommentRepliesHandler $getCommentRepliesHandler,
        QueryBusInterface $queryBus,
    ): PaginationResponse {
        $result = $queryBus->dispatch(
            query: new GetCommentRepliesQuery(
                commentId: $getCommentRepliesFilter->id,
                authUserId: $getCommentRepliesFilter->authUserId,
                cursor: $getCommentRepliesFilter->cursor,
                limit: $getCommentRepliesFilter->limit,
            ),
            handler: $getCommentRepliesHandler->handle(...),
        );

        $resources = $result->replies->mapToList(
            static fn(CommentView $comment): CommentResource => CommentResource::fromView($comment),
        );

        return new PaginationResponse(
            data: $resources,
            meta: new PaginationMetaResponse(nextCursor: $result->nextCursor, limit: $getCommentRepliesFilter->limit),
        );
    }
}
