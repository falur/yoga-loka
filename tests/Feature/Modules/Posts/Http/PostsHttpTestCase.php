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
use App\Modules\Media\Infrastructure\Persistence\Cycle\Entity\CycleMediaEntity;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Entity\CycleMediaImageConversionEntity;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper\MediaImageConversionMapper;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper\MediaMapper;
use App\Modules\Notifications\Public\Event\NotificationRequestedEvent;
use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Entity\PostMedia;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\CommentParent;
use App\Modules\Posts\Domain\ValueObject\CommentText;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Modules\Posts\Domain\Repository\CommentRepository;
use App\Modules\Posts\Domain\Repository\PostRepository;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CycleCommentEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CyclePostEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CyclePostMediaEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Mapper\CommentMapper;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Mapper\PostMapper;
use App\Modules\Tags\Domain\Entity\Tag;
use App\Modules\Tags\Infrastructure\Persistence\Cycle\Entity\CycleTagEntity;
use App\Modules\Tags\Infrastructure\Persistence\Cycle\Mapper\TagMapper;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Infrastructure\Persistence\Cycle\Entity\CycleUserEntity;
use App\Modules\User\Infrastructure\Persistence\Cycle\Mapper\UserMapper;
use App\Modules\Outbox\Public\Contract\IntegrationEventStoreContract;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\Select;
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
        $this->getContainer()->bindSingleton(IntegrationEventStoreContract::class, $this->outboxStore);

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
     * @return list<NotificationRequestedEvent>
     */
    protected function stagedNotifications(string $type): array
    {
        return \array_values(\array_filter(
            $this->outboxStore->messages,
            static fn(object $message): bool => $message instanceof NotificationRequestedEvent && $message->type === $type,
        ));
    }

    /**
     * User, Media, MediaImageConversion, Tag и Post/Comment/PostMedia модуля Posts — чистые
     * доменные сущности без Cycle-разметки, поэтому не могут быть сохранены через generic
     * entityManager()->persist(): EntityManager не знает их роль. Хелпер переводит их в Cycle
     * Entity через Mapper соответствующего модуля перед постановкой в очередь EntityManager — тот
     * же приём, что PostsRepositoryTestCase (Repository-тесты того же модуля). Существующая строка
     * ищется по PK перед каждым сохранением, чтобы повторный persist() (например, обновление
     * счётчика) выполнял UPDATE, а не падал на дубликате первичного ключа.
     */
    protected function persist(object $entity): void
    {
        $this->entityManager()->persist($this->toCycleEntity($entity));
        $this->entityManager()->run();
    }

    protected function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    private function toCycleEntity(object $entity): object
    {
        return match (true) {
            $entity instanceof User => $this->getContainer()->get(UserMapper::class)->toCycleEntity(
                user: $entity,
                cycleEntity: $this->findCycleEntityByClass(CycleUserEntity::class, $entity->id->value()),
            ),
            $entity instanceof Media => $this->getContainer()->get(MediaMapper::class)->toCycleEntity(
                media: $entity,
                cycleEntity: $this->findCycleEntityByClass(CycleMediaEntity::class, $entity->id->value()),
            ),
            $entity instanceof MediaImageConversion => $this->getContainer()->get(MediaImageConversionMapper::class)->toCycleEntity(
                imageConversion: $entity,
                cycleEntity: $this->findCycleEntityByClass(CycleMediaImageConversionEntity::class, $entity->id->value()),
            ),
            $entity instanceof Tag => $this->getContainer()->get(TagMapper::class)->toCycleEntity(
                tag: $entity,
                cycleEntity: $this->findCycleEntityByClass(CycleTagEntity::class, $entity->id->value()),
            ),
            $entity instanceof Post => $this->getContainer()->get(PostMapper::class)->toCycleEntity(
                post: $entity,
                cycleEntity: $this->findCycleEntityByClass(CyclePostEntity::class, $entity->id->value()),
            ),
            $entity instanceof Comment => $this->getContainer()->get(CommentMapper::class)->toCycleEntity(
                comment: $entity,
                cycleEntity: $this->findCycleEntityByClass(CycleCommentEntity::class, $entity->id->value()),
            ),
            $entity instanceof PostMedia => $this->getContainer()->get(PostMapper::class)->toPostMediaCycleEntity(
                postMedia: $entity,
                cycleEntity: $this->findCycleEntityByClass(CyclePostMediaEntity::class, $entity->id->value()),
            ),
            default => throw new \LogicException(\sprintf('Не настроено сохранение сущности %s в тестах.', $entity::class)),
        };
    }

    /**
     * @template TCycleEntity of object
     *
     * @param class-string<TCycleEntity> $cycleClass
     *
     * @return TCycleEntity|null
     */
    private function findCycleEntityByClass(string $cycleClass, string $id): object|null
    {
        /** @var ORMInterface $orm */
        $orm = $this->getContainer()->get(ORMInterface::class);

        /** @var Select<TCycleEntity> $select */
        $select = new Select($orm, $cycleClass);

        return $select->wherePK($id)->fetchOne();
    }

    protected function postRepository(): PostRepository
    {
        return $this->getContainer()->get(PostRepository::class);
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
