<?php

declare(strict_types=1);

namespace App\Modules\Posts\Tests\Feature\Spiral;

use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Shared\Domain\ValueObject\UserId;

final class DeletePostHttpTest extends PostsHttpTestCase
{
    public function testDeletesOwnPost(): void
    {
        $user = $this->createUser();
        $post = $this->persistPost($user->id, PostStatus::Published);

        $this->authedJson('DELETE', \sprintf('/api/v1/posts/%s', $post->id->value()), $user->id)
            ->assertNoContent();

        self::assertTrue($this->reloadPost($post)->deletion->isDeleted());
    }

    public function testRepeatedDeleteIsIdempotent(): void
    {
        $user = $this->createUser();
        $post = $this->persistPost($user->id, PostStatus::Published);

        $this->authedJson('DELETE', \sprintf('/api/v1/posts/%s', $post->id->value()), $user->id)->assertNoContent();
        $this->authedJson('DELETE', \sprintf('/api/v1/posts/%s', $post->id->value()), $user->id)->assertNoContent();
    }

    public function testDeletingRepostDecrementsOriginal(): void
    {
        $author = $this->createUser();
        $reposter = $this->createUser();
        $original = $this->persistPost($author->id, PostStatus::Published);
        $repost = $this->persistRepost($reposter->id, $original);

        self::assertSame(1, $this->reloadPost($original)->repostsCount->value());

        $this->authedJson('DELETE', \sprintf('/api/v1/posts/%s', $repost->id->value()), $reposter->id)
            ->assertNoContent();

        self::assertSame(0, $this->reloadPost($original)->repostsCount->value());
    }

    public function testRejectsDeleteByNonOwner(): void
    {
        $owner = $this->createUser();
        $stranger = $this->createUser();
        $post = $this->persistPost($owner->id, PostStatus::Published);

        $this->authedJson('DELETE', \sprintf('/api/v1/posts/%s', $post->id->value()), $stranger->id)
            ->assertStatus(403);
    }

    public function testRejectsDeleteOfMissingPost(): void
    {
        $user = $this->createUser();

        $this->authedJson('DELETE', \sprintf('/api/v1/posts/%s', UserId::generate()->value()), $user->id)
            ->assertNotFound();
    }

    public function testRejectsInvalidPostId(): void
    {
        $user = $this->createUser();

        $this->authedJson('DELETE', '/api/v1/posts/not-a-uuid', $user->id)->assertUnprocessable();
    }

    public function testRequiresAuthentication(): void
    {
        $user = $this->createUser();
        $post = $this->persistPost($user->id, PostStatus::Published);

        $this->fakeHttp()->deleteJson(\sprintf('/api/v1/posts/%s', $post->id->value()))->assertUnauthorized();
    }
}
