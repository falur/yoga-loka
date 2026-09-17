<?php

declare(strict_types=1);

namespace App\Modules\Posts\Tests\Integration\Cycle;

use App\Modules\Posts\Application\Contract\DetachMediaAttachmentsContract;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Entity\PostMedia;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\MediaPosition;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostMediaReference;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Порт массовой записи, которым DetachDeletedMediaHandler снимает вложения удалённого медиа
 * (см. докблок DetachMediaAttachmentsContract). Покрывает саму DELETE-операцию отдельно от
 * сценария: позитивный, отрицательный (нет вложений — no-op) и граничный (одно медиа вложено сразу
 * в несколько записей) случаи.
 */
final class CycleDetachMediaAttachmentsTest extends PostsRepositoryTestCase
{
    public function testDetachesMatchingAttachment(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $media = $this->createMedia($user->id);
        $this->persist($media);
        $post = $this->newPost($user->id);
        $this->persist($post);
        $this->persist(PostMedia::create(
            post: $post,
            mediaId: PostMediaReference::fromString($media->id->value()),
            position: MediaPosition::fromInt(0),
        ));
        $this->cleanOrmHeap();

        $detachedCount = $this->detachMediaAttachments()->detachByMediaId(
            PostMediaReference::fromString($media->id->value()),
        );

        self::assertSame(1, $detachedCount);
        self::assertCount(0, $this->postRepository()->findMediaByPostId($post->id));
    }

    public function testDetachingMediaWithoutAttachmentsIsNoOp(): void
    {
        $detachedCount = $this->detachMediaAttachments()->detachByMediaId(
            PostMediaReference::fromString(UserId::generate()->value()),
        );

        self::assertSame(0, $detachedCount);
    }

    public function testDetachingIsSafeToRepeat(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $media = $this->createMedia($user->id);
        $this->persist($media);
        $post = $this->newPost($user->id);
        $this->persist($post);
        $this->persist(PostMedia::create(
            post: $post,
            mediaId: PostMediaReference::fromString($media->id->value()),
            position: MediaPosition::fromInt(0),
        ));
        $this->cleanOrmHeap();

        $mediaId = PostMediaReference::fromString($media->id->value());
        $firstRun = $this->detachMediaAttachments()->detachByMediaId($mediaId);
        $secondRun = $this->detachMediaAttachments()->detachByMediaId($mediaId);

        self::assertSame(1, $firstRun);
        self::assertSame(0, $secondRun);
        self::assertCount(0, $this->postRepository()->findMediaByPostId($post->id));
    }

    public function testDetachesSameMediaAcrossMultiplePostsWithoutTouchingOtherAttachments(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $sharedMedia = $this->createMedia($user->id);
        $this->persist($sharedMedia);
        $untouchedMedia = $this->createMedia($user->id);
        $this->persist($untouchedMedia);

        $firstPost = $this->newPost($user->id);
        $this->persist($firstPost);
        $secondPost = $this->newPost($user->id);
        $this->persist($secondPost);

        $this->persist(PostMedia::create(
            post: $firstPost,
            mediaId: PostMediaReference::fromString($sharedMedia->id->value()),
            position: MediaPosition::fromInt(0),
        ));
        $this->persist(PostMedia::create(
            post: $secondPost,
            mediaId: PostMediaReference::fromString($sharedMedia->id->value()),
            position: MediaPosition::fromInt(0),
        ));
        $this->persist(PostMedia::create(
            post: $secondPost,
            mediaId: PostMediaReference::fromString($untouchedMedia->id->value()),
            position: MediaPosition::fromInt(1),
        ));
        $this->cleanOrmHeap();

        $detachedCount = $this->detachMediaAttachments()->detachByMediaId(
            PostMediaReference::fromString($sharedMedia->id->value()),
        );

        self::assertSame(2, $detachedCount);
        self::assertCount(0, $this->postRepository()->findMediaByPostId($firstPost->id));
        $remaining = $this->postRepository()->findMediaByPostId($secondPost->id);
        self::assertCount(1, $remaining);
        self::assertSame($untouchedMedia->id->value(), $remaining->first()?->mediaId->value());
    }

    private function detachMediaAttachments(): DetachMediaAttachmentsContract
    {
        return $this->getContainer()->get(DetachMediaAttachmentsContract::class);
    }

    private function newPost(UserId $userId): Post
    {
        return Post::create(
            userId: $userId,
            text: PostText::none(),
            status: PostStatus::Draft,
            attachmentType: AttachmentType::Media,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::none(),
        );
    }
}
