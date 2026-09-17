<?php

declare(strict_types=1);

namespace App\Modules\Posts\Tests\Integration\Spiral;

use App\Modules\Posts\Application\Command\DetachDeletedMedia\DetachDeletedMediaCommand;
use App\Modules\Posts\Application\Command\DetachDeletedMedia\DetachDeletedMediaHandler;
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
use App\Modules\Posts\Tests\Integration\Cycle\PostsRepositoryTestCase;
use App\Shared\Domain\ValueObject\UserId;
use Psr\Log\NullLogger;

/**
 * Реакция Posts на MediaDeletedEvent — см. докблок DetachDeletedMediaHandler. Позитивный сценарий
 * (вложение снято), отрицательный (нет вложений на этот mediaId — no-op) и граничный: повторная
 * доставка того же события (at-least-once) не ломает состояние — идемпотентность обязательна
 * (docs/arch.md, «Взаимодействие модулей»).
 */
final class DetachDeletedMediaHandlerTest extends PostsRepositoryTestCase
{
    public function testDetachesAttachmentReferencingDeletedMedia(): void
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

        $this->handler()->handle(new DetachDeletedMediaCommand(mediaId: $media->id->value()));

        self::assertCount(0, $this->postRepository()->findMediaByPostId($post->id));
    }

    public function testDoesNothingWhenNoAttachmentReferencesMedia(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $media = $this->createMedia($user->id);
        $this->persist($media);
        $otherMedia = $this->createMedia($user->id);
        $this->persist($otherMedia);
        $post = $this->newPost($user->id);
        $this->persist($post);
        $this->persist(PostMedia::create(
            post: $post,
            mediaId: PostMediaReference::fromString($otherMedia->id->value()),
            position: MediaPosition::fromInt(0),
        ));
        $this->cleanOrmHeap();

        // mediaId без единого вложения: сценарий не бросает исключение и не трогает чужие строки.
        $this->handler()->handle(new DetachDeletedMediaCommand(mediaId: $media->id->value()));

        self::assertCount(1, $this->postRepository()->findMediaByPostId($post->id));
    }

    public function testRepeatedDeliveryOfSameEventStaysIdempotent(): void
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

        $command = new DetachDeletedMediaCommand(mediaId: $media->id->value());

        // At-least-once: доставка того же outbox-события может повториться. Второй вызов находит
        // уже пустой набор строк и не бросает исключение.
        $this->handler()->handle($command);
        $this->handler()->handle($command);

        self::assertCount(0, $this->postRepository()->findMediaByPostId($post->id));
    }

    private function handler(): DetachDeletedMediaHandler
    {
        return new DetachDeletedMediaHandler(
            detachMediaAttachments: $this->getContainer()->get(DetachMediaAttachmentsContract::class),
            logger: new NullLogger(),
        );
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
