<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox\Application;

use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\Media\Domain\Repository\MediaRepository;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper\MediaMapper;
use App\Modules\Outbox\Public\Contract\IntegrationEventStoreContract;
use App\Modules\Outbox\Public\Event\OutboxDebugLogRequestedEvent;
use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
use App\Modules\Outbox\Domain\Enum\OutboxEventStatus;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\Repository\StoredOutboxEventRepository;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Tests\Feature\Modules\Outbox\CleansOutboxEvents;
use Tests\TestCase;

final class OutboxEventStoreTransactionTest extends TestCase
{
    use CleansOutboxEvents;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanOutboxEvents();
    }

    public function testTransactionalHandlerPersistsDomainEntityAndOutboxEventTogether(): void
    {
        $storeMediaAndOutboxHandler = new StoreMediaAndOutboxHandler(
            entityManager: $this->getContainer()->get(EntityManagerInterface::class),
            outboxEventStore: $this->getContainer()->get(IntegrationEventStoreContract::class),
            mediaMapper: $this->getContainer()->get(MediaMapper::class),
        );

        $storeMediaAndOutboxResult = $this->getContainer()->get(CommandBusInterface::class)->dispatch(
            command: new StoreMediaAndOutboxCommand(),
            handler: $storeMediaAndOutboxHandler->handle(...),
        );
        $storedMedia = $this->getContainer()->get(MediaRepository::class)->findById($storeMediaAndOutboxResult->mediaId);
        $storedOutboxEvent = $this->getContainer()
            ->get(StoredOutboxEventRepository::class)
            ->findById($storeMediaAndOutboxResult->outboxEventId);

        self::assertInstanceOf(Media::class, $storedMedia);
        self::assertSame(MediaStatus::WaitingUpload, $storedMedia->status);
        self::assertInstanceOf(StoredOutboxEvent::class, $storedOutboxEvent);
        self::assertSame(OutboxEventStatus::Pending, $storedOutboxEvent->status);
    }
}

final readonly class StoreMediaAndOutboxCommand {}

final readonly class StoreMediaAndOutboxResult
{
    public function __construct(
        public MediaId $mediaId,
        public OutboxEventId $outboxEventId,
    ) {}
}

final readonly class StoreMediaAndOutboxHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private IntegrationEventStoreContract $outboxEventStore,
        private MediaMapper $mediaMapper,
    ) {}

    #[Transactional]
    public function handle(StoreMediaAndOutboxCommand $storeMediaAndOutboxCommand): StoreMediaAndOutboxResult
    {
        $media = $this->createMedia();
        // Media — чистая доменная сущность без Cycle-разметки, поэтому в отличие от прежнего
        // (Cycle-нативного) состояния не может быть сохранена через generic persist():
        // EntityManager не знает её роль. Персист идёт через MediaMapper, как это делает
        // CycleMediaRepository.
        $this->entityManager->persist($this->mediaMapper->toCycleEntity($media));
        $storedOutboxEventId = $this->outboxEventStore->add(
            new OutboxDebugLogRequestedEvent(
                text: 'transaction check',
                createdAt: new \DateTimeImmutable('2026-05-25 16:20:00'),
            ),
        );
        $this->entityManager->run();

        return new StoreMediaAndOutboxResult(
            mediaId: $media->id,
            outboxEventId: OutboxEventId::fromString($storedOutboxEventId),
        );
    }

    private function createMedia(): Media
    {
        $storageKey = MediaStorageKey::generate();

        return Media::create(
            storageKey: $storageKey,
            type: MediaType::Image,
            visibility: MediaVisibility::Private,
            path: MediaPath::originalUpload(storageKey: $storageKey, extension: 'jpg'),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(1024),
            uploadedById: UserId::generate(),
            expiration: MediaExpiration::temporaryUntil(new \DateTimeImmutable('+1 hour')),
        );
    }
}
