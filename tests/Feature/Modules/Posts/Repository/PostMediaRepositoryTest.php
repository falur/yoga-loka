<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Repository;

use App\Modules\Posts\Domain\Collection\PostMediaCollection;
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
use Tests\Feature\Modules\Posts\PostsRepositoryTestCase;

final class PostMediaRepositoryTest extends PostsRepositoryTestCase
{
    public function testStoresAndRestoresMediaOrderedByPosition(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $firstMedia = $this->createMedia($user->id);
        $this->persist($firstMedia);
        $secondMedia = $this->createMedia($user->id);
        $this->persist($secondMedia);

        $post = $this->newPost($user->id);
        $this->persist($post);

        // Намеренно вставляем во втором/первом порядке, чтобы проверить orderBy position ASC.
        $this->persist(PostMedia::create(
            post: $post,
            media: PostMediaReference::fromString($secondMedia->id->value()),
            position: MediaPosition::fromInt(1),
        ));
        $this->persist(PostMedia::create(
            post: $post,
            media: PostMediaReference::fromString($firstMedia->id->value()),
            position: MediaPosition::fromInt(0),
        ));
        $this->cleanOrmHeap();

        $attachments = $this->postMediaRepository()->findByPostId($post->id);

        self::assertInstanceOf(PostMediaCollection::class, $attachments);
        self::assertCount(2, $attachments);
        self::assertSame(0, $attachments->first()?->position->value());
        self::assertSame($firstMedia->id->value(), $attachments->first()?->media->value());

        $restoredPost = $this->postRepository()->findById($post->id);
        self::assertInstanceOf(Post::class, $restoredPost);
        self::assertCount(2, $restoredPost->media);
        self::assertSame(0, $restoredPost->media->first()?->position->value());
    }

    public function testPostMediaBelongsToLazyLoadsPost(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $media = $this->createMedia($user->id);
        $this->persist($media);
        $post = $this->newPost($user->id);
        $this->persist($post);
        $this->persist(PostMedia::create(
            post: $post,
            media: PostMediaReference::fromString($media->id->value()),
            position: MediaPosition::fromInt(0),
        ));
        $this->cleanOrmHeap();

        $restored = $this->postMediaRepository()->findByPostId($post->id)->first();

        self::assertInstanceOf(PostMedia::class, $restored);
        self::assertInstanceOf(Post::class, $restored->post);
        self::assertTrue($post->id->equals($restored->post->id));
    }

    public function testPostMediaIsUniquePerPostAndMedia(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $media = $this->createMedia($user->id);
        $this->persist($media);
        $post = $this->newPost($user->id);
        $this->persist($post);

        $this->entityManager()->persist(PostMedia::create(
            post: $post,
            media: PostMediaReference::fromString($media->id->value()),
            position: MediaPosition::fromInt(0),
        ));
        $this->entityManager()->persist(PostMedia::create(
            post: $post,
            media: PostMediaReference::fromString($media->id->value()),
            position: MediaPosition::fromInt(1),
        ));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    public function testCannotDeleteMediaReferencedByPostMedia(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $media = $this->createMedia($user->id);
        $this->persist($media);
        $post = $this->newPost($user->id);
        $this->persist($post);
        $this->persist(PostMedia::create(
            post: $post,
            media: PostMediaReference::fromString($media->id->value()),
            position: MediaPosition::fromInt(0),
        ));

        $this->expectException(\Throwable::class);

        $this->entityManager()->delete($media);
        $this->entityManager()->run();
    }

    private function newPost(UserId $userId): Post
    {
        return Post::create(
            userId: $userId,
            text: PostText::none(),
            status: PostStatus::Published,
            attachmentType: AttachmentType::Media,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::none(),
        );
    }
}
