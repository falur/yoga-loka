<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Http;

use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Shared\Domain\ValueObject\UserId;

final class PublishPostHttpTest extends PostsHttpTestCase
{
    public function testPublishesOwnDraft(): void
    {
        $user = $this->createUser();
        $post = $this->persistPost($user->id, PostStatus::Draft);

        $response = $this->authedJson('POST', \sprintf('/api/v1/posts/%s/publish', $post->id->value()), $user->id);

        $response->assertOk();
        self::assertSame('published', $this->json($response)['data']['status']);
        self::assertSame(PostStatus::Published, $this->reloadPost($post)->status);
    }

    public function testPublishingDraftNotifiesMentionedUsers(): void
    {
        $author = $this->createUser();
        $mentioned = $this->createUser();

        $createResponse = $this->authedJson('POST', '/api/v1/posts', $author->id, [
            'text' => 'Черновик с упоминанием',
            'draft' => true,
            'mentions' => [$mentioned->id->value()],
        ]);
        $createResponse->assertOk();
        $postId = $this->json($createResponse)['data']['id'];

        self::assertCount(0, $this->stagedNotifications('posts.post_mention'));

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/publish', $postId), $author->id)->assertOk();

        $mentions = $this->stagedNotifications('posts.post_mention');
        self::assertCount(1, $mentions);
        self::assertSame($mentioned->id->value(), $mentions[0]->userId);
    }

    public function testAlreadyPublishedIsIdempotentNoOp(): void
    {
        $user = $this->createUser();
        $post = $this->persistPost($user->id, PostStatus::Published);

        $response = $this->authedJson('POST', \sprintf('/api/v1/posts/%s/publish', $post->id->value()), $user->id);

        $response->assertOk();
        self::assertSame('published', $this->json($response)['data']['status']);
    }

    public function testRejectsPublishByNonOwner(): void
    {
        $owner = $this->createUser();
        $stranger = $this->createUser();
        $post = $this->persistPost($owner->id, PostStatus::Draft);

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/publish', $post->id->value()), $stranger->id)
            ->assertStatus(403);
    }

    public function testRejectsPublishOfDeletedPost(): void
    {
        $user = $this->createUser();
        $post = $this->persistPost($user->id, PostStatus::Draft, deleted: true);

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/publish', $post->id->value()), $user->id)
            ->assertNotFound();
    }

    public function testRejectsPublishOfBlockedPost(): void
    {
        $user = $this->createUser();
        $post = $this->persistPost($user->id, PostStatus::Blocked);

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/publish', $post->id->value()), $user->id)
            ->assertNotFound();
    }

    public function testRejectsPublishOfMissingPost(): void
    {
        $user = $this->createUser();

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/publish', UserId::generate()->value()), $user->id)
            ->assertNotFound();
    }

    public function testRejectsInvalidPostId(): void
    {
        $user = $this->createUser();

        $this->authedJson('POST', '/api/v1/posts/not-a-uuid/publish', $user->id)->assertUnprocessable();
    }
}
