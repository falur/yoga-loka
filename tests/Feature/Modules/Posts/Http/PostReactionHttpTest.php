<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Http;

use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Shared\Domain\ValueObject\UserId;

final class PostReactionHttpTest extends PostsHttpTestCase
{
    public function testLikesPostAndNotifiesAuthor(): void
    {
        $author = $this->createUser();
        $liker = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/like', $post->id->value()), $liker->id)
            ->assertNoContent();

        self::assertSame(1, $this->reloadPost($post)->likesCount->value());

        $likes = $this->stagedNotifications('posts.post_like');
        self::assertCount(1, $likes);
        self::assertSame($author->id->value(), $likes[0]->userId);
    }

    public function testLikeIsIdempotent(): void
    {
        $author = $this->createUser();
        $liker = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/like', $post->id->value()), $liker->id)->assertNoContent();
        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/like', $post->id->value()), $liker->id)->assertNoContent();

        self::assertSame(1, $this->reloadPost($post)->likesCount->value());
        self::assertCount(1, $this->stagedNotifications('posts.post_like'));
    }

    public function testSelfLikeDoesNotNotify(): void
    {
        $user = $this->createUser();
        $post = $this->persistPost($user->id, PostStatus::Published);

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/like', $post->id->value()), $user->id)->assertNoContent();

        self::assertSame(1, $this->reloadPost($post)->likesCount->value());
        self::assertCount(0, $this->stagedNotifications('posts.post_like'));
    }

    public function testUnlikePost(): void
    {
        $author = $this->createUser();
        $liker = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/like', $post->id->value()), $liker->id)->assertNoContent();
        $this->authedJson('DELETE', \sprintf('/api/v1/posts/%s/like', $post->id->value()), $liker->id)->assertNoContent();

        self::assertSame(0, $this->reloadPost($post)->likesCount->value());
        self::assertFalse($this->postRepository()->existsLikeByPostAndUser($post->id, $liker->id));
    }

    public function testUnlikeWithoutPriorLikeIsNoOp(): void
    {
        $author = $this->createUser();
        $liker = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);

        $this->authedJson('DELETE', \sprintf('/api/v1/posts/%s/like', $post->id->value()), $liker->id)->assertNoContent();

        self::assertSame(0, $this->reloadPost($post)->likesCount->value());
    }

    public function testLikeOnDraftReturns404(): void
    {
        $author = $this->createUser();
        $liker = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Draft);

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/like', $post->id->value()), $liker->id)
            ->assertNotFound();
    }

    public function testLikeMissingPostReturns404(): void
    {
        $user = $this->createUser();

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/like', UserId::generate()->value()), $user->id)
            ->assertNotFound();
    }

    public function testUnlikeMissingPostReturns404(): void
    {
        $user = $this->createUser();

        $this->authedJson('DELETE', \sprintf('/api/v1/posts/%s/like', UserId::generate()->value()), $user->id)
            ->assertNotFound();
    }

    public function testRequiresAuthentication(): void
    {
        $author = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);

        $this->fakeHttp()->postJson(\sprintf('/api/v1/posts/%s/like', $post->id->value()))->assertUnauthorized();
    }
}
