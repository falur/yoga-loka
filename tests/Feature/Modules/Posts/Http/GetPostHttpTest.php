<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Http;

use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Repository\MediaRepository;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Shared\Domain\ValueObject\UserId;

final class GetPostHttpTest extends PostsHttpTestCase
{
    public function testReturnsEnrichedPost(): void
    {
        $user = $this->createUser();
        $media = $this->createReadyMedia($user->id, MediaVisibility::Public);

        $createResponse = $this->authedJson('POST', '/api/v1/posts', $user->id, [
            'text' => 'Запись с вложениями',
            'mediaIds' => [$media->id->value()],
            'tags' => ['yoga'],
        ]);
        $postId = $this->json($createResponse)['data']['id'];

        // Лайкнем, чтобы проверить флаг likedByMe в чтении.
        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/like', $postId), $user->id)->assertNoContent();

        $response = $this->authedGet(\sprintf('/api/v1/posts/%s', $postId), $user->id);

        $response->assertOk();
        $data = $this->json($response)['data'];
        self::assertSame('Запись с вложениями', $data['text']);
        self::assertSame($user->id->value(), $data['author']['userId']);
        self::assertCount(1, $data['media']);
        self::assertSame('yoga', $data['tags'][0]['text']);
        self::assertTrue($data['likedByMe']);
        self::assertSame(1, $data['likesCount']);
    }

    public function testUnavailableAttachedMediaIsExcludedWithoutError(): void
    {
        $user = $this->createUser();
        $media = $this->createReadyMedia($user->id, MediaVisibility::Public);

        $postId = $this->json($this->authedJson('POST', '/api/v1/posts', $user->id, [
            'text' => 'Запись с медиа',
            'mediaIds' => [$media->id->value()],
        ]))['data']['id'];

        // Медиа перестаёт быть Ready независимо от записи (перемещение/обработка в проде). Чтение
        // записи не должно падать в 500 — недоступное вложение просто исключается из ответа.
        $this->makeMediaUnavailable($media->id);

        $response = $this->authedGet(\sprintf('/api/v1/posts/%s', $postId), $user->id);

        $response->assertOk();
        $data = $this->json($response)['data'];
        self::assertSame([], $data['media']);
        // Контракт: после деградации media пуст — attachmentType не остаётся "media", а становится "none".
        self::assertSame('none', $data['attachmentType']);
    }

    private function makeMediaUnavailable(MediaId $mediaId): void
    {
        $media = $this->getContainer()->get(MediaRepository::class)->findById($mediaId);
        self::assertNotNull($media);
        $media->markReadyOriginalRemoved();
        $this->entityManager()->persist($media);
        $this->entityManager()->run();
        $this->cleanOrmHeap();
    }

    public function testReturnsOriginalForRepost(): void
    {
        $author = $this->createUser();
        $reposter = $this->createUser();
        $original = $this->persistPost($author->id, PostStatus::Published);

        $repostResponse = $this->authedJson(
            'POST',
            \sprintf('/api/v1/posts/%s/repost', $original->id->value()),
            $reposter->id,
        );
        $repostId = $this->json($repostResponse)['data']['id'];

        $response = $this->authedGet(\sprintf('/api/v1/posts/%s', $repostId), $reposter->id);

        $response->assertOk();
        $data = $this->json($response)['data'];
        self::assertNotNull($data['original']);
        self::assertSame($original->id->value(), $data['original']['id']);
    }

    public function testReturnsNullOriginalWhenOriginalDeleted(): void
    {
        $author = $this->createUser();
        $reposter = $this->createUser();
        $original = $this->persistPost($author->id, PostStatus::Published);

        $repostId = $this->json($this->authedJson(
            'POST',
            \sprintf('/api/v1/posts/%s/repost', $original->id->value()),
            $reposter->id,
        ))['data']['id'];

        $this->authedJson('DELETE', \sprintf('/api/v1/posts/%s', $original->id->value()), $author->id)
            ->assertNoContent();

        $response = $this->authedGet(\sprintf('/api/v1/posts/%s', $repostId), $reposter->id);

        $response->assertOk();
        self::assertNull($this->json($response)['data']['original']);
    }

    public function testForeignDraftReturns404(): void
    {
        $author = $this->createUser();
        $viewer = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Draft);

        $this->authedGet(\sprintf('/api/v1/posts/%s', $post->id->value()), $viewer->id)->assertNotFound();
    }

    public function testOwnerSeesOwnDraft(): void
    {
        $author = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Draft);

        $this->authedGet(\sprintf('/api/v1/posts/%s', $post->id->value()), $author->id)->assertOk();
    }

    public function testBlockedReturns404(): void
    {
        $author = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Blocked);

        $this->authedGet(\sprintf('/api/v1/posts/%s', $post->id->value()), $author->id)->assertNotFound();
    }

    public function testDeletedReturns404(): void
    {
        $author = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published, deleted: true);

        $this->authedGet(\sprintf('/api/v1/posts/%s', $post->id->value()), $author->id)->assertNotFound();
    }

    public function testMissingReturns404(): void
    {
        $user = $this->createUser();

        $this->authedGet(\sprintf('/api/v1/posts/%s', UserId::generate()->value()), $user->id)->assertNotFound();
    }

    public function testInvalidIdReturns422(): void
    {
        $user = $this->createUser();

        $this->authedGet('/api/v1/posts/not-a-uuid', $user->id)->assertUnprocessable();
    }

    public function testRequiresAuthentication(): void
    {
        $author = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);

        $this->fakeHttp()->getJson(\sprintf('/api/v1/posts/%s', $post->id->value()))->assertUnauthorized();
    }
}
