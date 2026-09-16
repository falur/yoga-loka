<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Http;

use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Posts\Domain\Enum\PostStatus;

/**
 * Чужая лента (посторонний смотрит `/api/v1/posts/user/<id>` не своим <id>): только опубликованные
 * записи видны постороннему. Разделена от GetMyFeedHttpTest вместе с разделением
 * GetUserFeedQuery/GetMyFeedQuery — маршрут и JSON-форма прежние, сценарии видимости не потеряны.
 */
final class GetUserFeedHttpTest extends PostsHttpTestCase
{
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

    /**
     * Чужая лента собирает вложения и метки теми же пакетными вызовами соседей, что и своя: сценарий
     * дублируется здесь, потому что после разделения GetUserFeed/GetMyFeed это разные обработчики.
     */
    public function testFeedBatchesMediaAndTagsPerPost(): void
    {
        $author = $this->createUser();
        $stranger = $this->createUser();
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

        $response = $this->authedGet(\sprintf('/api/v1/posts/user/%s', $author->id->value()), $stranger->id);

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

    public function testFeedEnrichesOriginalsOfRepostsWithAttachmentsAndTags(): void
    {
        $author = $this->createUser();
        $reposter = $this->createUser();
        $stranger = $this->createUser();
        $media = $this->createReadyMedia($author->id, MediaVisibility::Public);

        $originalId = $this->json($this->authedJson('POST', '/api/v1/posts', $author->id, [
            'text' => 'Оригинал с вложением и меткой',
            'mediaIds' => [$media->id->value()],
            'tags' => ['yoga'],
        ]))['data']['id'];

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/repost', $originalId), $reposter->id)->assertOk();

        $response = $this->authedGet(\sprintf('/api/v1/posts/user/%s', $reposter->id->value()), $stranger->id);

        $response->assertOk();
        $data = $this->json($response)['data'];
        self::assertCount(1, $data);
        self::assertNotNull($data[0]['original']);
        self::assertSame($originalId, $data[0]['original']['id']);
        self::assertCount(1, $data[0]['original']['media']);
        self::assertSame($media->id->value(), $data[0]['original']['media'][0]['id']);
        self::assertCount(1, $data[0]['original']['tags']);
        self::assertSame('yoga', $data[0]['original']['tags'][0]['text']);
    }

    /**
     * Оригинал репоста без меток: к Tags за метками оригиналов не ходим вовсе, а сам оригинал в
     * ответе остаётся с пустым набором меток.
     */
    public function testFeedEnrichesOriginalWithoutTags(): void
    {
        $author = $this->createUser();
        $reposter = $this->createUser();
        $stranger = $this->createUser();
        $original = $this->persistPost($author->id, PostStatus::Published);

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/repost', $original->id->value()), $reposter->id)
            ->assertOk();

        $response = $this->authedGet(\sprintf('/api/v1/posts/user/%s', $reposter->id->value()), $stranger->id);

        $response->assertOk();
        $data = $this->json($response)['data'];
        self::assertCount(1, $data);
        self::assertNotNull($data[0]['original']);
        self::assertSame($original->id->value(), $data[0]['original']['id']);
        self::assertSame([], $data[0]['original']['tags']);
    }

    public function testFeedReturnsNullOriginalWhenRepostOriginalInvisible(): void
    {
        $author = $this->createUser();
        $reposter = $this->createUser();
        $stranger = $this->createUser();
        $original = $this->persistPost($author->id, PostStatus::Published);

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/repost', $original->id->value()), $reposter->id)
            ->assertOk();

        // Оригинал удалён после репоста — постороннему в чужой ленте original репоста виден как null.
        $this->authedJson('DELETE', \sprintf('/api/v1/posts/%s', $original->id->value()), $author->id)
            ->assertNoContent();

        $response = $this->authedGet(\sprintf('/api/v1/posts/user/%s', $reposter->id->value()), $stranger->id);

        $response->assertOk();
        $data = $this->json($response)['data'];
        self::assertCount(1, $data);
        self::assertNull($data[0]['original']);
    }

    public function testPaginatesWithCursor(): void
    {
        $author = $this->createUser();
        $stranger = $this->createUser();
        for ($index = 0; $index < 3; $index++) {
            $this->persistPost($author->id, PostStatus::Published);
        }

        $firstPage = $this->authedGet(
            \sprintf('/api/v1/posts/user/%s?limit=2', $author->id->value()),
            $stranger->id,
        );
        $firstPage->assertOk();
        $firstBody = $this->json($firstPage);
        self::assertCount(2, $firstBody['data']);
        self::assertNotNull($firstBody['meta']['nextCursor']);

        $secondPage = $this->authedGet(
            \sprintf('/api/v1/posts/user/%s?limit=2&cursor=%s', $author->id->value(), $firstBody['meta']['nextCursor']),
            $stranger->id,
        );
        $secondPage->assertOk();
        $secondBody = $this->json($secondPage);
        self::assertCount(1, $secondBody['data']);
        self::assertNull($secondBody['meta']['nextCursor']);
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
