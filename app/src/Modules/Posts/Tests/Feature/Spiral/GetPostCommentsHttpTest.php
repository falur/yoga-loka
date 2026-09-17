<?php

declare(strict_types=1);

namespace App\Modules\Posts\Tests\Feature\Spiral;

use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Shared\Domain\ValueObject\UserId;

final class GetPostCommentsHttpTest extends PostsHttpTestCase
{
    public function testReturnsTopLevelCommentsOnly(): void
    {
        $author = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);
        $topLevel = $this->persistComment($author->id, $post);
        $this->persistComment($author->id, $post, $topLevel);

        $response = $this->authedGet(\sprintf('/api/v1/posts/%s/comments', $post->id->value()), $author->id);

        $response->assertOk();
        $data = $this->json($response)['data'];
        self::assertCount(1, $data);
        self::assertSame($topLevel->id->value(), $data[0]['id']);
    }

    public function testHidesDeletedComments(): void
    {
        $author = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);
        $comment = $this->persistComment($author->id, $post);

        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s', $comment->id->value()), $author->id)
            ->assertNoContent();

        $response = $this->authedGet(\sprintf('/api/v1/posts/%s/comments', $post->id->value()), $author->id);

        $response->assertOk();
        self::assertCount(0, $this->json($response)['data']);
    }

    public function testExposesLikedByMeFlag(): void
    {
        $author = $this->createUser();
        $viewer = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);
        $likedComment = $this->persistComment($author->id, $post);
        $this->persistComment($author->id, $post);

        $this->authedJson('POST', \sprintf('/api/v1/posts/comments/%s/like', $likedComment->id->value()), $viewer->id)
            ->assertNoContent();

        $response = $this->authedGet(\sprintf('/api/v1/posts/%s/comments', $post->id->value()), $viewer->id);

        $response->assertOk();
        $likedFlags = [];
        foreach ($this->json($response)['data'] as $comment) {
            $likedFlags[$comment['id']] = $comment['likedByMe'];
        }

        self::assertTrue($likedFlags[$likedComment->id->value()]);
        self::assertContains(false, $likedFlags);
    }

    public function testPaginatesWithCursor(): void
    {
        $author = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);
        for ($index = 0; $index < 3; $index++) {
            $this->persistComment($author->id, $post);
        }

        $firstPage = $this->authedGet(
            \sprintf('/api/v1/posts/%s/comments?limit=2', $post->id->value()),
            $author->id,
        );
        $firstPage->assertOk();
        $firstBody = $this->json($firstPage);
        self::assertCount(2, $firstBody['data']);
        self::assertNotNull($firstBody['meta']['nextCursor']);

        $secondPage = $this->authedGet(
            \sprintf('/api/v1/posts/%s/comments?limit=2&cursor=%s', $post->id->value(), $firstBody['meta']['nextCursor']),
            $author->id,
        );
        $secondPage->assertOk();
        $secondBody = $this->json($secondPage);
        self::assertCount(1, $secondBody['data']);
        self::assertNull($secondBody['meta']['nextCursor']);
    }

    public function testReturns404WhenPostNotVisible(): void
    {
        $author = $this->createUser();
        $viewer = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Draft);

        $this->authedGet(\sprintf('/api/v1/posts/%s/comments', $post->id->value()), $viewer->id)->assertNotFound();
    }

    public function testReturns404WhenPostMissing(): void
    {
        $user = $this->createUser();

        $this->authedGet(\sprintf('/api/v1/posts/%s/comments', UserId::generate()->value()), $user->id)
            ->assertNotFound();
    }

    public function testRequiresAuthentication(): void
    {
        $author = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);

        $this->fakeHttp()->getJson(\sprintf('/api/v1/posts/%s/comments', $post->id->value()))->assertUnauthorized();
    }
}
