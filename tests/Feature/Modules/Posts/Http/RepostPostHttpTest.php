<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Http;

use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Shared\Domain\ValueObject\UserId;

final class RepostPostHttpTest extends PostsHttpTestCase
{
    public function testCreatesRepostIncrementsOriginalAndNotifiesAuthor(): void
    {
        $author = $this->createUser();
        $reposter = $this->createUser();
        $original = $this->persistPost($author->id, PostStatus::Published);

        $response = $this->authedJson(
            'POST',
            \sprintf('/api/v1/posts/%s/repost', $original->id->value()),
            $reposter->id,
            ['text' => 'Отличная запись'],
        );

        $response->assertOk();
        $data = $this->json($response)['data'];
        self::assertSame('published', $data['status']);
        self::assertNotNull($data['original']);
        self::assertSame($original->id->value(), $data['original']['id']);

        self::assertSame(1, $this->reloadPost($original)->repostsCount->value());

        $reposts = $this->stagedNotifications('posts.post_repost');
        self::assertCount(1, $reposts);
        self::assertSame($author->id->value(), $reposts[0]->userId);
    }

    public function testDeduplicatesRepeatedMediaId(): void
    {
        $author = $this->createUser();
        $reposter = $this->createUser();
        $original = $this->persistPost($author->id, PostStatus::Published);
        $media = $this->createReadyMedia($reposter->id, MediaVisibility::Public);

        $response = $this->authedJson(
            'POST',
            \sprintf('/api/v1/posts/%s/repost', $original->id->value()),
            $reposter->id,
            ['mediaIds' => [$media->id->value(), $media->id->value()]],
        );

        $response->assertOk();
        $data = $this->json($response)['data'];
        self::assertSame('media', $data['attachmentType']);
        self::assertCount(1, $data['media']);
        self::assertSame($media->id->value(), $data['media'][0]['mediaId']);
    }

    public function testSelfRepostDoesNotNotify(): void
    {
        $user = $this->createUser();
        $original = $this->persistPost($user->id, PostStatus::Published);

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/repost', $original->id->value()), $user->id)
            ->assertOk();

        self::assertCount(0, $this->stagedNotifications('posts.post_repost'));
    }

    public function testRejectsRepostOfDraftTarget(): void
    {
        $author = $this->createUser();
        $reposter = $this->createUser();
        $original = $this->persistPost($author->id, PostStatus::Draft);

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/repost', $original->id->value()), $reposter->id)
            ->assertNotFound();
    }

    public function testRejectsRepostOfDeletedTarget(): void
    {
        $author = $this->createUser();
        $reposter = $this->createUser();
        $original = $this->persistPost($author->id, PostStatus::Published, deleted: true);

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/repost', $original->id->value()), $reposter->id)
            ->assertNotFound();
    }

    public function testRejectsRepostOfMissingTarget(): void
    {
        $user = $this->createUser();

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/repost', UserId::generate()->value()), $user->id)
            ->assertNotFound();
    }

    public function testRejectsInvalidTargetId(): void
    {
        $user = $this->createUser();

        $this->authedJson('POST', '/api/v1/posts/not-a-uuid/repost', $user->id)->assertUnprocessable();
    }

    public function testRequiresAuthentication(): void
    {
        $author = $this->createUser();
        $original = $this->persistPost($author->id, PostStatus::Published);

        $this->fakeHttp()->postJson(\sprintf('/api/v1/posts/%s/repost', $original->id->value()))
            ->assertUnauthorized();
    }
}
