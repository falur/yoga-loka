<?php

declare(strict_types=1);

namespace App\Modules\Posts\Tests\Feature\Spiral;

use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Shared\Domain\ValueObject\UserId;

final class CommentReactionHttpTest extends PostsHttpTestCase
{
    public function testLikesCommentAndNotifiesAuthor(): void
    {
        $author = $this->createUser();
        $liker = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);
        $comment = $this->persistComment($author->id, $post);

        $this->authedJson('POST', \sprintf('/api/v1/posts/comments/%s/like', $comment->id->value()), $liker->id)
            ->assertNoContent();

        self::assertSame(1, $this->reloadComment($comment)->likesCount->value());

        $likes = $this->stagedNotifications('posts.comment_like');
        self::assertCount(1, $likes);
        self::assertSame($author->id->value(), $likes[0]->userId);
    }

    public function testLikeCommentIsIdempotent(): void
    {
        $author = $this->createUser();
        $liker = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);
        $comment = $this->persistComment($author->id, $post);

        $this->authedJson('POST', \sprintf('/api/v1/posts/comments/%s/like', $comment->id->value()), $liker->id)->assertNoContent();
        $this->authedJson('POST', \sprintf('/api/v1/posts/comments/%s/like', $comment->id->value()), $liker->id)->assertNoContent();

        self::assertSame(1, $this->reloadComment($comment)->likesCount->value());
        self::assertCount(1, $this->stagedNotifications('posts.comment_like'));
    }

    public function testSelfLikeCommentDoesNotNotify(): void
    {
        $user = $this->createUser();
        $post = $this->persistPost($user->id, PostStatus::Published);
        $comment = $this->persistComment($user->id, $post);

        $this->authedJson('POST', \sprintf('/api/v1/posts/comments/%s/like', $comment->id->value()), $user->id)
            ->assertNoContent();

        self::assertSame(1, $this->reloadComment($comment)->likesCount->value());
        self::assertCount(0, $this->stagedNotifications('posts.comment_like'));
    }

    public function testUnlikeComment(): void
    {
        $author = $this->createUser();
        $liker = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);
        $comment = $this->persistComment($author->id, $post);

        $this->authedJson('POST', \sprintf('/api/v1/posts/comments/%s/like', $comment->id->value()), $liker->id)->assertNoContent();
        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s/like', $comment->id->value()), $liker->id)->assertNoContent();

        self::assertSame(0, $this->reloadComment($comment)->likesCount->value());
        self::assertFalse($this->commentRepository()->existsLikeByCommentAndUser($comment->id, $liker->id));
    }

    public function testUnlikeWithoutPriorLikeIsNoOp(): void
    {
        $author = $this->createUser();
        $liker = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);
        $comment = $this->persistComment($author->id, $post);

        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s/like', $comment->id->value()), $liker->id)
            ->assertNoContent();

        self::assertSame(0, $this->reloadComment($comment)->likesCount->value());
    }

    public function testLikeDeletedCommentReturns404(): void
    {
        $author = $this->createUser();
        $liker = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);
        $comment = $this->persistComment($author->id, $post);

        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s', $comment->id->value()), $author->id)->assertNoContent();

        $this->authedJson('POST', \sprintf('/api/v1/posts/comments/%s/like', $comment->id->value()), $liker->id)
            ->assertNotFound();
    }

    public function testLikeMissingCommentReturns404(): void
    {
        $user = $this->createUser();

        $this->authedJson('POST', \sprintf('/api/v1/posts/comments/%s/like', UserId::generate()->value()), $user->id)
            ->assertNotFound();
    }

    public function testUnlikeMissingCommentReturns404(): void
    {
        $user = $this->createUser();

        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s/like', UserId::generate()->value()), $user->id)
            ->assertNotFound();
    }

    public function testRequiresAuthentication(): void
    {
        $author = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);
        $comment = $this->persistComment($author->id, $post);

        $this->fakeHttp()->postJson(\sprintf('/api/v1/posts/comments/%s/like', $comment->id->value()))
            ->assertUnauthorized();
    }
}
