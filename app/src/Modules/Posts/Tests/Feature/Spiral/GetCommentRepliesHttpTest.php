<?php

declare(strict_types=1);

namespace App\Modules\Posts\Tests\Feature\Spiral;

use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Shared\Domain\ValueObject\UserId;

final class GetCommentRepliesHttpTest extends PostsHttpTestCase
{
    public function testReturnsRepliesOfParent(): void
    {
        $author = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);
        $parent = $this->persistComment($author->id, $post);
        $this->persistComment($author->id, $post, $parent);
        $this->persistComment($author->id, $post, $parent);

        $response = $this->authedGet(
            \sprintf('/api/v1/posts/comments/%s/replies', $parent->id->value()),
            $author->id,
        );

        $response->assertOk();
        self::assertCount(2, $this->json($response)['data']);
    }

    public function testHidesDeletedReplies(): void
    {
        $author = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);
        $parent = $this->persistComment($author->id, $post);
        $reply = $this->persistComment($author->id, $post, $parent);

        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s', $reply->id->value()), $author->id)
            ->assertNoContent();

        $response = $this->authedGet(
            \sprintf('/api/v1/posts/comments/%s/replies', $parent->id->value()),
            $author->id,
        );

        $response->assertOk();
        self::assertCount(0, $this->json($response)['data']);
    }

    public function testPaginatesWithCursor(): void
    {
        $author = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);
        $parent = $this->persistComment($author->id, $post);
        for ($index = 0; $index < 3; $index++) {
            $this->persistComment($author->id, $post, $parent);
        }

        $firstPage = $this->authedGet(
            \sprintf('/api/v1/posts/comments/%s/replies?limit=2', $parent->id->value()),
            $author->id,
        );
        $firstPage->assertOk();
        $firstBody = $this->json($firstPage);
        self::assertCount(2, $firstBody['data']);
        self::assertNotNull($firstBody['meta']['nextCursor']);

        $secondPage = $this->authedGet(
            \sprintf('/api/v1/posts/comments/%s/replies?limit=2&cursor=%s', $parent->id->value(), $firstBody['meta']['nextCursor']),
            $author->id,
        );
        $secondPage->assertOk();
        $secondBody = $this->json($secondPage);
        self::assertCount(1, $secondBody['data']);
        self::assertNull($secondBody['meta']['nextCursor']);
    }

    public function testReturns404WhenParentDeleted(): void
    {
        $author = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);
        $parent = $this->persistComment($author->id, $post);
        $this->persistComment($author->id, $post, $parent);

        // На удалённый комментарий нельзя ни ответить, ни прочитать его ответы (guard симметричен записи).
        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s', $parent->id->value()), $author->id)
            ->assertNoContent();

        $this->authedGet(
            \sprintf('/api/v1/posts/comments/%s/replies', $parent->id->value()),
            $author->id,
        )->assertNotFound();
    }

    public function testReturns404WhenParentMissing(): void
    {
        $user = $this->createUser();

        $this->authedGet(\sprintf('/api/v1/posts/comments/%s/replies', UserId::generate()->value()), $user->id)
            ->assertNotFound();
    }

    public function testReturns404WhenPostNotVisible(): void
    {
        $author = $this->createUser();
        $viewer = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Draft);
        $parent = $this->persistComment($author->id, $post);

        $this->authedGet(\sprintf('/api/v1/posts/comments/%s/replies', $parent->id->value()), $viewer->id)
            ->assertNotFound();
    }

    public function testRequiresAuthentication(): void
    {
        $author = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);
        $parent = $this->persistComment($author->id, $post);

        $this->fakeHttp()->getJson(\sprintf('/api/v1/posts/comments/%s/replies', $parent->id->value()))
            ->assertUnauthorized();
    }
}
