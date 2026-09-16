<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Http;

use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\Repository\MediaRepository;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Entity\PostMedia;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\MediaPosition;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\PostMediaReference;
use App\Modules\User\Domain\ValueObject\UserAvatar;
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

    public function testReturnsMediaWithAllConversionsSoClientChoosesWhatToShow(): void
    {
        $user = $this->createUser();
        $media = $this->createReadyMedia($user->id, MediaVisibility::Public);
        $this->attachThumbnailConversion($media);

        $postId = $this->json($this->authedJson('POST', '/api/v1/posts', $user->id, [
            'text' => 'Запись с превью',
            'mediaIds' => [$media->id->value()],
        ]))['data']['id'];
        $this->cleanOrmHeap();

        $data = $this->json($this->authedGet(\sprintf('/api/v1/posts/%s', $postId), $user->id))['data'];

        // Вложение отдаётся полным набором: оригинал + все конверсии, чтобы фронт сам выбрал показ.
        self::assertCount(1, $data['media']);
        // Медиа с преобразованиями — одним объектом MediaResource: id, позиция, оригинал + конверсии.
        $mediaData = $data['media'][0];
        self::assertSame($media->id->value(), $mediaData['id']);
        self::assertSame(0, $mediaData['position']);
        self::assertSame('https://media.test/object.jpg', $mediaData['original']['url']);
        self::assertNull($mediaData['original']['expiresAt']);
        self::assertCount(1, $mediaData['conversions']);
        self::assertSame('image', $mediaData['conversions'][0]['kind']);
        self::assertSame('thumbnail', $mediaData['conversions'][0]['type']);
        self::assertSame('https://media.test/object.jpg', $mediaData['conversions'][0]['url']);

        // Автор без аватара: avatar = null (сервер не выдумывает заглушку, дефолт ставит клиент).
        self::assertNull($data['author']['avatar']);
    }

    public function testAuthorAvatarReturnedWithAllConversions(): void
    {
        $author = $this->createUser();
        $avatarMedia = $this->createReadyMedia($author->id, MediaVisibility::Public);
        $this->attachThumbnailConversion($avatarMedia);
        $author->setAvatar(UserAvatar::pointingTo($avatarMedia->id->value()));
        $this->persist($author);

        $postId = $this->json($this->authedJson('POST', '/api/v1/posts', $author->id, [
            'text' => 'Запись автора с аватаром',
        ]))['data']['id'];
        $this->cleanOrmHeap();

        $authorData = $this->json($this->authedGet(\sprintf('/api/v1/posts/%s', $postId), $author->id))['data']['author'];

        // Аватар автора отдаётся тем же объектом MediaResource: оригинал + полный набор конверсий;
        // позиция вне набора вложений неприменима — null.
        self::assertSame($author->id->value(), $authorData['userId']);
        $avatarData = $authorData['avatar'];
        self::assertSame($avatarMedia->id->value(), $avatarData['id']);
        self::assertNull($avatarData['position']);
        self::assertSame('https://media.test/object.jpg', $avatarData['original']['url']);
        self::assertCount(1, $avatarData['conversions']);
        self::assertSame('image', $avatarData['conversions'][0]['kind']);
        self::assertSame('thumbnail', $avatarData['conversions'][0]['type']);
        self::assertSame('https://media.test/object.jpg', $avatarData['conversions'][0]['url']);
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

    public function testKeepsAttachmentByConversionsWhenOriginalRemoved(): void
    {
        $user = $this->createUser();
        $media = $this->createReadyMedia($user->id, MediaVisibility::Public);
        $this->attachThumbnailConversion($media);

        $postId = $this->json($this->authedJson('POST', '/api/v1/posts', $user->id, [
            'text' => 'Запись с превью',
            'mediaIds' => [$media->id->value()],
        ]))['data']['id'];

        // Оригинал удалён (readyOriginalRemoved), но миниатюра остаётся пригодной — вложение не должно
        // исчезнуть: фронт покажет конверсию. url тогда null, а конверсии на месте.
        $this->makeMediaUnavailable($media->id);

        $data = $this->json($this->authedGet(\sprintf('/api/v1/posts/%s', $postId), $user->id))['data'];

        self::assertCount(1, $data['media']);
        $mediaData = $data['media'][0];
        // Оригинал удалён -> original = null, а конверсии на месте (вложение показывается по ним).
        self::assertNull($mediaData['original']);
        self::assertCount(1, $mediaData['conversions']);
        self::assertSame('thumbnail', $mediaData['conversions'][0]['type']);
        // Показывать есть что (конверсия), поэтому attachmentType не деградирует в none.
        self::assertSame('media', $data['attachmentType']);
    }

    public function testAttachmentOfUnavailableMediaIsExcludedWhileOthersRemain(): void
    {
        $user = $this->createUser();
        $readyMedia = $this->createReadyMedia($user->id, MediaVisibility::Public);
        // Медиа без финализации: контракт Media его не отдаёт вовсе — в наборе ссылок ответа его нет.
        $notFinalizedMedia = $this->createUploadedMedia($user->id);

        $postId = $this->json($this->authedJson('POST', '/api/v1/posts', $user->id, [
            'text' => 'Запись с двумя вложениями',
            'mediaIds' => [$readyMedia->id->value()],
        ]))['data']['id'];

        // Второе вложение заводим напрямую: проверка вложения не пропустила бы нефинализированное
        // медиа, а нам нужна именно запись, у которой такое вложение уже лежит в post_media.
        $post = $this->postRepository()->findById(PostId::fromString($postId));
        self::assertInstanceOf(Post::class, $post);
        $this->persist(PostMedia::create(
            post: $post,
            mediaId: PostMediaReference::fromString($notFinalizedMedia->id->value()),
            position: MediaPosition::fromInt(1),
        ));
        $this->cleanOrmHeap();

        $data = $this->json($this->authedGet(\sprintf('/api/v1/posts/%s', $postId), $user->id))['data'];

        // Недоступное вложение исключено, доступное осталось со своей позицией — без 500.
        self::assertCount(1, $data['media']);
        self::assertSame($readyMedia->id->value(), $data['media'][0]['id']);
        self::assertSame(0, $data['media'][0]['position']);
        self::assertSame('media', $data['attachmentType']);
    }

    public function testRepostResolvesAttachmentsOfItsOriginal(): void
    {
        $author = $this->createUser();
        $reposter = $this->createUser();
        $media = $this->createReadyMedia($author->id, MediaVisibility::Public);

        $originalId = $this->json($this->authedJson('POST', '/api/v1/posts', $author->id, [
            'text' => 'Оригинал с вложением',
            'mediaIds' => [$media->id->value()],
        ]))['data']['id'];

        $repostId = $this->json($this->authedJson(
            'POST',
            \sprintf('/api/v1/posts/%s/repost', $originalId),
            $reposter->id,
        ))['data']['id'];
        $this->cleanOrmHeap();

        $data = $this->json($this->authedGet(\sprintf('/api/v1/posts/%s', $repostId), $reposter->id))['data'];

        // Вложения оригинала репоста разрешаются тем же набором ссылок, что и вложения самой записи.
        self::assertNotNull($data['original']);
        self::assertCount(1, $data['original']['media']);
        self::assertSame($media->id->value(), $data['original']['media'][0]['id']);
        self::assertSame(0, $data['original']['media'][0]['position']);
        self::assertSame('https://media.test/object.jpg', $data['original']['media'][0]['original']['url']);
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
