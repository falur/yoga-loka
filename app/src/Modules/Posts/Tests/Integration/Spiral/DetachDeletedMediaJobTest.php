<?php

declare(strict_types=1);

namespace App\Modules\Posts\Tests\Integration\Spiral;

use App\Modules\Media\Public\Event\MediaDeletedEvent;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Public\Contract\IntegrationEventLoaderContract;
use App\Modules\Outbox\Public\Contract\IntegrationEventStoreContract;
use App\Modules\Outbox\Public\Dto\OutboxEnvelopeDto;
use App\Modules\Posts\Application\Command\DetachDeletedMedia\DetachDeletedMediaHandler;
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
use App\Modules\Posts\Infrastructure\Spiral\Job\DetachDeletedMediaJob;
use App\Modules\Posts\Tests\Integration\Cycle\PostsRepositoryTestCase;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Psr\Log\NullLogger;

/**
 * Полный асинхронный путь: MediaDeletedEvent из outbox -> DetachDeletedMediaJob -> сценарий Posts.
 * Позитивный сценарий грузит настоящее событие через реальный IntegrationEventLoaderContract (не
 * стаб), граничный — повторная доставка того же outbox-события (at-least-once) не ломает
 * состояние, отрицательный — событие про медиа без единого вложения не бросает исключение.
 */
final class DetachDeletedMediaJobTest extends PostsRepositoryTestCase
{
    public function testDetachesAttachmentThroughFullOutboxPipeline(): void
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

        $eventId = $this->stageMediaDeleted($media->id->value());

        $this->job()->invoke(
            payload: $this->envelope($eventId),
            id: 'job-1',
            integrationEventLoader: $this->getContainer()->get(IntegrationEventLoaderContract::class),
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            detachDeletedMediaHandler: $this->getContainer()->get(DetachDeletedMediaHandler::class),
        );

        self::assertCount(0, $this->postRepository()->findMediaByPostId($post->id));
    }

    public function testRedeliveryOfSameEventStaysIdempotent(): void
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

        $eventId = $this->stageMediaDeleted($media->id->value());
        $envelope = $this->envelope($eventId);

        // Доставка имеет семантику at-least-once (docs/arch.md, «Взаимодействие модулей»):
        // повторная доставка того же outbox-события не бросает исключение и не меняет состояние.
        $this->job()->invoke(
            payload: $envelope,
            id: 'job-1',
            integrationEventLoader: $this->getContainer()->get(IntegrationEventLoaderContract::class),
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            detachDeletedMediaHandler: $this->getContainer()->get(DetachDeletedMediaHandler::class),
        );
        $this->job()->invoke(
            payload: $envelope,
            id: 'job-2',
            integrationEventLoader: $this->getContainer()->get(IntegrationEventLoaderContract::class),
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            detachDeletedMediaHandler: $this->getContainer()->get(DetachDeletedMediaHandler::class),
        );

        self::assertCount(0, $this->postRepository()->findMediaByPostId($post->id));
    }

    public function testIgnoresMediaWithoutAnyAttachmentAndLeavesUnrelatedAttachmentsUntouched(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $untouchedMedia = $this->createMedia($user->id);
        $this->persist($untouchedMedia);
        $post = $this->newPost($user->id);
        $this->persist($post);
        $this->persist(PostMedia::create(
            post: $post,
            mediaId: PostMediaReference::fromString($untouchedMedia->id->value()),
            position: MediaPosition::fromInt(0),
        ));
        $this->cleanOrmHeap();

        // Событие про совсем другое, ни к чему не привязанное медиа: сценарий не находит вложений,
        // не бросает исключение и не трогает чужую строку.
        $eventId = $this->stageMediaDeleted(UserId::generate()->value());

        $this->job()->invoke(
            payload: $this->envelope($eventId),
            id: 'job-1',
            integrationEventLoader: $this->getContainer()->get(IntegrationEventLoaderContract::class),
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            detachDeletedMediaHandler: $this->getContainer()->get(DetachDeletedMediaHandler::class),
        );

        self::assertCount(1, $this->postRepository()->findMediaByPostId($post->id));
    }

    private function stageMediaDeleted(string $mediaId): OutboxEventId
    {
        $storedOutboxEventId = $this->getContainer()->get(IntegrationEventStoreContract::class)
            ->add(new MediaDeletedEvent(mediaId: $mediaId));
        $this->getContainer()->get(EntityManagerInterface::class)->run();

        return OutboxEventId::fromString($storedOutboxEventId);
    }

    private function envelope(OutboxEventId $eventId): OutboxEnvelopeDto
    {
        return new OutboxEnvelopeDto(
            outboxEventId: $eventId->value(),
            outboxEventType: MediaDeletedEvent::class,
        );
    }

    private function job(): DetachDeletedMediaJob
    {
        return $this->getContainer()->get(DetachDeletedMediaJob::class);
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
