<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Http;

use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Posts\Domain\Enum\PostStatus;

final class GetUserFeedHttpTest extends PostsHttpTestCase
{
    public function testFeedBatchesMediaAndTagsPerPost(): void
    {
        $author = $this->createUser();
        $firstMedia = $this->createReadyMedia($author->id, MediaVisibility::Public);
        $secondMedia = $this->createReadyMedia($author->id, MediaVisibility::Public);

        $this->authedJson('POST', '/api/v1/posts', $author->id, [
            'text' => 'Первая запись',
            'mediaIds' => [$firstMedia->id->value()],
            'tags' => ['yoga'],
        ])->assertOk();
        $this->authedJson('POST', '/api/v1/posts', $author->id, [
            'text' => 'Вторая запись',
            'mediaIds' => [$secondMedia->id->value()],
            'tags' => ['yoga', 'медитация'],
        ])->assertOk();

        $response = $this->authedGet(\sprintf('/api/v1/posts/user/%s', $author->id->value()), $author->id);

        $response->assertOk();
        $byText = [];
        foreach ($this->json($response)['data'] as $post) {
            $byText[$post['text']] = $post;
        }

        self::assertCount(1, $byText['Первая запись']['media']);
        self::assertCount(1, $byText['Первая запись']['tags']);
        self::assertCount(1, $byText['Вторая запись']['media']);
        self::assertCount(2, $byText['Вторая запись']['tags']);
    }

    public function testOwnerSeesDraftsAndPublished(): void
    {
        $author = $this->createUser();
        $this->persistPost($author->id, PostStatus::Published);
        $this->persistPost($author->id, PostStatus::Draft);

        $response = $this->authedGet(\sprintf('/api/v1/posts/user/%s', $author->id->value()), $author->id);

        $response->assertOk();
        self::assertCount(2, $this->json($response)['data']);
    }

    public function testOwnerDoesNotSeeOwnBlockedPost(): void
    {
        $author = $this->createUser();
        $this->persistPost($author->id, PostStatus::Published);
        $this->persistPost($author->id, PostStatus::Draft);
        $this->persistPost($author->id, PostStatus::Blocked);

        $response = $this->authedGet(\sprintf('/api/v1/posts/user/%s', $author->id->value()), $author->id);

        $response->assertOk();
        $data = $this->json($response)['data'];
        self::assertCount(2, $data);
        foreach ($data as $post) {
            self::assertNotSame('blocked', $post['status']);
        }
    }

    public function testStrangerSeesOnlyPublished(): void
    {
        $author = $this->createUser();
        $stranger = $this->createUser();
        $this->persistPost($author->id, PostStatus::Published);
        $this->persistPost($author->id, PostStatus::Draft);

        $response = $this->authedGet(\sprintf('/api/v1/posts/user/%s', $author->id->value()), $stranger->id);

        $response->assertOk();
        $data = $this->json($response)['data'];
        self::assertCount(1, $data);
        self::assertSame('published', $data[0]['status']);
    }

    public function testHidesSoftDeletedPosts(): void
    {
        $author = $this->createUser();
        $this->persistPost($author->id, PostStatus::Published);
        $this->persistPost($author->id, PostStatus::Published, deleted: true);

        $response = $this->authedGet(\sprintf('/api/v1/posts/user/%s', $author->id->value()), $author->id);

        $response->assertOk();
        self::assertCount(1, $this->json($response)['data']);
    }

    public function testPaginatesWithCursor(): void
    {
        $author = $this->createUser();
        for ($index = 0; $index < 3; $index++) {
            $this->persistPost($author->id, PostStatus::Published);
        }

        $firstPage = $this->authedGet(
            \sprintf('/api/v1/posts/user/%s?limit=2', $author->id->value()),
            $author->id,
        );
        $firstPage->assertOk();
        $firstBody = $this->json($firstPage);
        self::assertCount(2, $firstBody['data']);
        self::assertNotNull($firstBody['meta']['nextCursor']);

        $secondPage = $this->authedGet(
            \sprintf('/api/v1/posts/user/%s?limit=2&cursor=%s', $author->id->value(), $firstBody['meta']['nextCursor']),
            $author->id,
        );
        $secondPage->assertOk();
        $secondBody = $this->json($secondPage);
        self::assertCount(1, $secondBody['data']);
        self::assertNull($secondBody['meta']['nextCursor']);
    }

    public function testExposesLikedByMeFlagPerPost(): void
    {
        $author = $this->createUser();
        $viewer = $this->createUser();
        $likedPost = $this->persistPost($author->id, PostStatus::Published);
        $this->persistPost($author->id, PostStatus::Published);

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/like', $likedPost->id->value()), $viewer->id)
            ->assertNoContent();

        $response = $this->authedGet(\sprintf('/api/v1/posts/user/%s', $author->id->value()), $viewer->id);

        $response->assertOk();
        $likedFlags = [];
        foreach ($this->json($response)['data'] as $post) {
            $likedFlags[$post['id']] = $post['likedByMe'];
        }

        self::assertTrue($likedFlags[$likedPost->id->value()]);
        self::assertContains(false, $likedFlags);
    }

    public function testFeedEnrichesOriginalsOfRepostsInListing(): void
    {
        $author = $this->createUser();
        $reposter = $this->createUser();
        $firstOriginal = $this->persistPost($author->id, PostStatus::Published);
        $secondOriginal = $this->persistPost($author->id, PostStatus::Published);

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/repost', $firstOriginal->id->value()), $reposter->id)
            ->assertOk();
        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/repost', $secondOriginal->id->value()), $reposter->id)
            ->assertOk();

        $response = $this->authedGet(\sprintf('/api/v1/posts/user/%s', $reposter->id->value()), $reposter->id);

        $response->assertOk();
        $originalIds = [];
        foreach ($this->json($response)['data'] as $post) {
            self::assertNotNull($post['original']);
            $originalIds[] = $post['original']['id'];
        }

        self::assertContains($firstOriginal->id->value(), $originalIds);
        self::assertContains($secondOriginal->id->value(), $originalIds);
    }

    public function testFeedReturnsNullOriginalWhenAllRepostOriginalsInvisible(): void
    {
        $author = $this->createUser();
        $reposter = $this->createUser();
        $original = $this->persistPost($author->id, PostStatus::Published);

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/repost', $original->id->value()), $reposter->id)
            ->assertOk();

        // Оригинал удалён после репоста — в ленте репостера original репоста становится null.
        $this->authedJson('DELETE', \sprintf('/api/v1/posts/%s', $original->id->value()), $author->id)
            ->assertNoContent();

        $response = $this->authedGet(\sprintf('/api/v1/posts/user/%s', $reposter->id->value()), $reposter->id);

        $response->assertOk();
        $data = $this->json($response)['data'];
        self::assertCount(1, $data);
        self::assertNull($data[0]['original']);
    }

    public function testEmptyFeedReturnsEmptyData(): void
    {
        $author = $this->createUser();
        $viewer = $this->createUser();

        $response = $this->authedGet(\sprintf('/api/v1/posts/user/%s', $author->id->value()), $viewer->id);

        $response->assertOk();
        self::assertCount(0, $this->json($response)['data']);
        self::assertNull($this->json($response)['meta']['nextCursor']);
    }

    public function testRequiresAuthentication(): void
    {
        $author = $this->createUser();

        $this->fakeHttp()->getJson(\sprintf('/api/v1/posts/user/%s', $author->id->value()))->assertUnauthorized();
    }
}
