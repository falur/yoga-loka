<?php

declare(strict_types=1);

namespace App\Modules\Posts\Tests\Integration\Spiral;

use App\Modules\Media\Public\Event\MediaDeletedEvent;
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
use GianTiaga\SpiralOutbox\OutboxDeliveryStatus;
use GianTiaga\SpiralOutbox\OutboxEventStoreContract;
use GianTiaga\SpiralOutbox\OutboxMessageLoaderContract;
use Tests\Support\Outbox\CleansOutboxEvents;
use Tests\Support\Outbox\RunsOutboxRelay;

/**
 * Полный асинхронный путь: MediaDeletedEvent из outbox -> доставка -> DetachDeletedMediaJob ->
 * сценарий Posts. Позитивный сценарий грузит настоящее событие через реальный загрузчик пакета
 * (не стаб), граничный — повторная доставка той же строки (at-least-once) не ломает состояние,
 * отрицательный — событие про медиа без единого вложения не бросает исключение.
 */
final class DetachDeletedMediaJobTest extends PostsRepositoryTestCase
{
    use CleansOutboxEvents;
    use RunsOutboxRelay;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Доставка ищется по классу Job, поэтому тест начинает с пустых таблиц обмена: чужая
        // строка того же Job от соседа по worker-у сделала бы выбор неоднозначным.
        $this->cleanOutboxEvents();
    }

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

        $deliveryId = $this->stageMediaDeleted($media->id->value());

        $this->job()->invoke(
            outboxDeliveryId: $deliveryId,
            outboxMessageLoader: $this->getContainer()->get(OutboxMessageLoaderContract::class),
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            detachDeletedMediaHandler: $this->getContainer()->get(DetachDeletedMediaHandler::class),
        );

        self::assertCount(0, $this->postRepository()->findMediaByPostId($post->id));
    }

    public function testFullRelayPassDetachesAttachmentAndCompletesDelivery(): void
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

        $this->getContainer()->get(OutboxEventStoreContract::class)
            ->add(new MediaDeletedEvent(mediaId: $media->id->value()));
        $this->getContainer()->get(EntityManagerInterface::class)->run();

        // Полный проход relay: доставка создаётся по маршруту и выполняется Job в этом же процессе
        // (подключение очереди в тестах — `sync`).
        $this->runOutboxRelayPass();

        self::assertCount(0, $this->postRepository()->findMediaByPostId($post->id));
        self::assertSame(
            OutboxDeliveryStatus::Completed,
            $this->outboxDeliveryOf(DetachDeletedMediaJob::class)->status,
        );
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

        $deliveryId = $this->stageMediaDeleted($media->id->value());

        // Доставка имеет семантику at-least-once (docs/arch.md, «Взаимодействие модулей»):
        // повторная доставка той же строки не бросает исключение и не меняет состояние.
        $this->job()->invoke(
            outboxDeliveryId: $deliveryId,
            outboxMessageLoader: $this->getContainer()->get(OutboxMessageLoaderContract::class),
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            detachDeletedMediaHandler: $this->getContainer()->get(DetachDeletedMediaHandler::class),
        );
        $this->job()->invoke(
            outboxDeliveryId: $deliveryId,
            outboxMessageLoader: $this->getContainer()->get(OutboxMessageLoaderContract::class),
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
        $deliveryId = $this->stageMediaDeleted(UserId::generate()->value());

        $this->job()->invoke(
            outboxDeliveryId: $deliveryId,
            outboxMessageLoader: $this->getContainer()->get(OutboxMessageLoaderContract::class),
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            detachDeletedMediaHandler: $this->getContainer()->get(DetachDeletedMediaHandler::class),
        );

        self::assertCount(1, $this->postRepository()->findMediaByPostId($post->id));
    }

    /**
     * Кладёт событие и выполняет первый этап relay: маршрут создаёт строку доставки, по которой
     * Job и грузит событие.
     */
    private function stageMediaDeleted(string $mediaId): string
    {
        $this->getContainer()->get(OutboxEventStoreContract::class)
            ->add(new MediaDeletedEvent(mediaId: $mediaId));
        $this->getContainer()->get(EntityManagerInterface::class)->run();
        $this->routeOutboxEvents();

        return $this->outboxDeliveryOf(DetachDeletedMediaJob::class)->outboxDeliveryId;
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
