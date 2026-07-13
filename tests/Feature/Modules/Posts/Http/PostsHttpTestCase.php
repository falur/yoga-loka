<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Http;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\Notifications\Application\Message\NotificationRequested;
use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\CommentParent;
use App\Modules\Posts\Domain\ValueObject\CommentText;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Modules\Posts\Repository\CommentLikeRepository;
use App\Modules\Posts\Repository\CommentRepository;
use App\Modules\Posts\Repository\PostLikeRepository;
use App\Modules\Posts\Repository\PostMentionRepository;
use App\Modules\Posts\Repository\PostRepository;
use App\Modules\Posts\Repository\PostTagRepository;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use Spiral\Testing\Http\TestResponse;
use Tests\DatabaseTestCase;
use Tests\Support\Notifications\RecordingOutboxEventStore;

/**
 * Основа HTTP-тестов модуля Posts. Аутентификация — прямой проброс request-атрибута authUserId
 * (стек auth-middleware его не затирает при отсутствии токена). Outbox подменён записывающим
 * дублёром (проверяем стейджинг уведомлений), а файловый сервис медиа — стабом (URL предсказуем,
 * без обращения к S3).
 */
abstract class PostsHttpTestCase extends DatabaseTestCase
{
    protected RecordingOutboxEventStore $outboxStore;

    private int $userCounter = 0;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->outboxStore = new RecordingOutboxEventStore();
        $this->getContainer()->bindSingleton(OutboxEventStoreContract::class, $this->outboxStore);

        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturn('https://media.test/object.jpg');
        $fileService->method('presignGet')->willReturn('https://media.test/signed.jpg');
        $this->getContainer()->bindSingleton(MediaFileServiceContract::class, $fileService);
    }

    protected function createUser(): User
    {
        $this->userCounter++;

        $user = User::create(
            name: UserName::fromString('Йога Тест'),
            email: Email::fromString(\sprintf('posts.http%d@example.com', $this->userCounter)),
            nickname: UserNickname::fromString(\sprintf('posts.http%d', $this->userCounter)),
            locale: Locale::Ru,
        );
        $this->persist($user);

        return $user;
    }

    protected function createReadyMedia(UserId $owner, MediaVisibility $visibility = MediaVisibility::Private): Media
    {
        $media = $this->newMedia($owner, $visibility);
        $media->markUploaded();
        $targetStorage = $visibility === MediaVisibility::Public ? MediaStorage::Public : MediaStorage::Private;
        $media->markReadyMovedTo(
            $targetStorage,
            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
        );
        $this->persist($media);

        return $media;
    }

    protected function createUploadedMedia(UserId $owner): Media
    {
        $media = $this->newMedia($owner, MediaVisibility::Private);
        $media->markUploaded();
        $this->persist($media);

        return $media;
    }

    protected function attachThumbnailConversion(Media $media): MediaImageConversion
    {
        $conversion = MediaImageConversion::create(
            media: $media,
            type: MediaImageConversionType::Thumbnail,
            status: MediaConversionStatus::Ready,
            storage: $media->storage,
            path: MediaPath::imageConversion(
                storageKey: $media->storageKey,
                type: MediaImageConversionType::Thumbnail,
                extension: 'jpg',
            ),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(256),
            width: MediaPixelDimension::fromInt(100),
            height: MediaPixelDimension::fromInt(100),
        );
        $this->persist($conversion);

        return $conversion;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function authedJson(string $method, string $uri, UserId $userId, array $data = []): TestResponse
    {
        $request = $this->fakeHttp()
            ->createJsonRequest($uri, $method, $data, [], [])
            ->withAttribute('authUserId', $userId->value());

        return $this->fakeHttp()->handleRequest($request);
    }

    protected function authedGet(string $uri, UserId $userId): TestResponse
    {
        return $this->fakeHttp()->getWithAttributes($uri, ['authUserId' => $userId->value()]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(TestResponse $response): array
    {
        $decoded = \json_decode(
            json: (string) $response->getOriginalResponse()->getBody(),
            associative: true,
            flags: \JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @return list<NotificationRequested>
     */
    protected function stagedNotifications(string $type): array
    {
        return \array_values(\array_filter(
            $this->outboxStore->messages,
            static fn(object $message): bool => $message instanceof NotificationRequested && $message->type === $type,
        ));
    }

    protected function persist(object $entity): void
    {
        $this->entityManager()->persist($entity);
        $this->entityManager()->run();
    }

    protected function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    protected function postRepository(): PostRepository
    {
        return $this->getContainer()->get(PostRepository::class);
    }

    protected function postTagRepository(): PostTagRepository
    {
        return $this->getContainer()->get(PostTagRepository::class);
    }

    protected function postMentionRepository(): PostMentionRepository
    {
        return $this->getContainer()->get(PostMentionRepository::class);
    }

    protected function postLikeRepository(): PostLikeRepository
    {
        return $this->getContainer()->get(PostLikeRepository::class);
    }

    protected function persistPost(
        UserId $author,
        PostStatus $status = PostStatus::Published,
        bool $deleted = false,
    ): Post {
        $post = Post::create(
            userId: $author,
            text: PostText::none(),
            status: $status,
            attachmentType: AttachmentType::None,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::none(),
        );

        if ($deleted) {
            $post->softDelete(new \DateTimeImmutable());
        }

        $this->persist($post);

        return $post;
    }

    protected function persistRepost(UserId $author, Post $original): Post
    {
        $original->incrementReposts();
        $this->persist($original);

        $repost = Post::create(
            userId: $author,
            text: PostText::none(),
            status: PostStatus::Published,
            attachmentType: AttachmentType::None,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::pointingTo($original->id->value()),
        );
        $this->persist($repost);

        return $repost;
    }

    protected function persistComment(UserId $author, Post $post, Comment|null $parent = null): Comment
    {
        $comment = Comment::create(
            postId: $post->id,
            userId: $author,
            text: CommentText::fromString('Комментарий'),
            parent: $parent === null ? CommentParent::none() : CommentParent::pointingTo($parent->id->value()),
        );
        $this->persist($comment);

        if ($parent === null) {
            $post->incrementComments();
            $this->persist($post);
        } else {
            $parent->incrementReplies();
            $this->persist($parent);
        }

        return $comment;
    }

    protected function reloadPost(Post $post): Post
    {
        $this->cleanOrmHeap();
        $reloaded = $this->postRepository()->findById($post->id);
        self::assertInstanceOf(Post::class, $reloaded);

        return $reloaded;
    }

    protected function reloadComment(Comment $comment): Comment
    {
        $this->cleanOrmHeap();
        $reloaded = $this->commentRepository()->findById($comment->id);
        self::assertInstanceOf(Comment::class, $reloaded);

        return $reloaded;
    }

    protected function commentRepository(): CommentRepository
    {
        return $this->getContainer()->get(CommentRepository::class);
    }

    protected function commentLikeRepository(): CommentLikeRepository
    {
        return $this->getContainer()->get(CommentLikeRepository::class);
    }

    private function newMedia(UserId $owner, MediaVisibility $visibility): Media
    {
        $storageKey = MediaStorageKey::generate();

        return Media::create(
            storageKey: $storageKey,
            type: MediaType::Image,
            visibility: $visibility,
            path: MediaPath::originalUpload(storageKey: $storageKey, extension: 'jpg'),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(2048),
            uploadedById: $owner,
            expiration: MediaExpiration::temporaryUntil(new \DateTimeImmutable('+1 hour')),
        );
    }
}
