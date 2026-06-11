<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Flow;

use App\Modules\Media\Application\Command\Media\CompleteMediaUpload\CompleteMediaUploadCommand;
use App\Modules\Media\Application\Command\Media\CompleteMediaUpload\CompleteMediaUploadHandler;
use App\Modules\Media\Application\Command\Media\ProcessMedia\ProcessMediaCommand;
use App\Modules\Media\Application\Command\Media\ProcessMedia\ProcessMediaHandler;
use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaConversionSpec;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaProcessingError;
use App\Modules\Outbox\Domain\Enum\OutboxEventStatus;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelayBatchSize;
use App\Modules\Outbox\Infrastructure\Relay\OutboxRelay;
use App\Modules\Outbox\Repository\OutboxEventRepository;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Tests\Feature\Modules\Media\Application\MediaApplicationTestCase;
use Tests\Feature\Modules\Outbox\CleansOutboxEvents;

final class MediaProcessingFlowTest extends MediaApplicationTestCase
{
    use CleansOutboxEvents;

    /**
     * @var list<array{storage: MediaStorage, path: MediaPath}>
     */
    private array $createdObjects = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanOutboxEvents();
    }

    public function testCompleteThenRelayProcessesMediaToReady(): void
    {
        $bytes = $this->jpegBytes();
        $media = $this->uploadedOriginal($bytes, MediaVisibility::Public);

        $this->completeUpload($media, [$this->conversionSpec(MediaImageConversionType::Thumbnail)]);
        $publishedCount = $this->relay();

        self::assertSame(1, $publishedCount);

        $processedMedia = $this->mediaRepository()->findById($media->id);
        self::assertNotNull($processedMedia);
        self::assertSame(MediaStatus::Ready, $processedMedia->status);
        self::assertSame(MediaStorage::Public, $processedMedia->storage);

        $conversions = $this->imageConversionRepository()->findByMediaId($media->id);
        self::assertCount(1, $conversions);

        $conversionPath = MediaPath::imageConversion(
            storageKey: $media->storageKey,
            type: MediaImageConversionType::Thumbnail,
            extension: 'jpg',
        );
        $readyPath = MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg');
        $this->track(MediaStorage::Public, $conversionPath);
        $this->track(MediaStorage::Public, $readyPath);

        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $conversionPath));
        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $readyPath));

        // Идемпотентность повторной обработки: на ready — no-op, без дублей конверсий.
        $this->getContainer()->get(ProcessMediaHandler::class)->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            conversions: [$this->conversionSpec(MediaImageConversionType::Thumbnail)],
        ));
        self::assertCount(1, $this->imageConversionRepository()->findByMediaId($media->id));
    }

    public function testProcessingRecoversFromProcessingFailedToReady(): void
    {
        $bytes = $this->jpegBytes();
        $media = $this->uploadedOriginal($bytes, MediaVisibility::Public);

        // Имитируем зафиксированный транзиентный сбой: медиа осталось в processingFailed.
        $media->recordTemporaryProcessingError(
            MediaProcessingError::fromString('Временная ошибка обработки.'),
        );
        $this->persist($media);
        self::assertSame(MediaStatus::ProcessingFailed, $media->status);

        // Повторная доставка: ProcessMedia с валидным оригиналом доводит медиа до ready.
        $this->getContainer()->get(ProcessMediaHandler::class)->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            conversions: [$this->conversionSpec(MediaImageConversionType::Thumbnail)],
        ));

        $readyMedia = $this->mediaRepository()->findById($media->id);
        self::assertNotNull($readyMedia);
        self::assertSame(MediaStatus::Ready, $readyMedia->status);
        self::assertSame(MediaStorage::Public, $readyMedia->storage);
        self::assertTrue($readyMedia->processingError->isEmpty());

        $conversions = $this->imageConversionRepository()->findByMediaId($media->id);
        self::assertCount(1, $conversions);

        $conversionPath = MediaPath::imageConversion(
            storageKey: $media->storageKey,
            type: MediaImageConversionType::Thumbnail,
            extension: 'jpg',
        );
        $readyPath = MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg');
        $this->track(MediaStorage::Public, $conversionPath);
        $this->track(MediaStorage::Public, $readyPath);

        // Оригинал переложен в целевой бакет, конверсия залита.
        self::assertSame($readyPath->value(), $readyMedia->path->value());
        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $readyPath));
        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $conversionPath));
    }

    public function testProcessingFailureRecordsErrorAndFailsOutbox(): void
    {
        $corruptBytes = \random_bytes(2048);
        $media = $this->uploadedOriginal($corruptBytes, MediaVisibility::Public);

        $outboxEventId = $this->completeUpload($media, [$this->conversionSpec(MediaImageConversionType::Thumbnail)]);
        $publishedCount = $this->relay();

        self::assertSame(0, $publishedCount);

        $failedMedia = $this->mediaRepository()->findById($media->id);
        self::assertNotNull($failedMedia);
        self::assertSame(MediaStatus::ProcessingFailed, $failedMedia->status);
        self::assertFalse($failedMedia->processingError->isEmpty());

        $outboxEvent = $this->getContainer()->get(OutboxEventRepository::class)->findById($outboxEventId);
        self::assertNotNull($outboxEvent);
        self::assertSame(OutboxEventStatus::Failed, $outboxEvent->status);
    }

    private function uploadedOriginal(string $bytes, MediaVisibility $visibility): Media
    {
        $media = $this->createMedia(
            userId: UserId::generate(),
            visibility: $visibility,
            type: MediaType::Image,
            size: MediaFileSize::fromInt(\strlen($bytes)),
        );
        $this->persist($media);

        $this->fileService()->putObject(
            storage: MediaStorage::Upload,
            path: $media->path,
            contents: $bytes,
            mimeType: $media->mimeType,
        );
        $this->track(MediaStorage::Upload, $media->path);

        return $media;
    }

    /**
     * @param list<MediaConversionSpec> $conversions
     */
    private function completeUpload(Media $media, array $conversions): OutboxEventId
    {
        $this->getContainer()->get(CommandBusInterface::class)->dispatch(
            command: new CompleteMediaUploadCommand(
                userId: $media->uploadedById->value(),
                mediaId: $media->id->value(),
                conversions: $conversions,
                parts: null,
            ),
            handler: $this->getContainer()->get(CompleteMediaUploadHandler::class)->handle(...),
        );

        $outboxEvent = $this->getContainer()->get(OutboxEventRepository::class)->findPendingForRelay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: new \DateTimeImmutable(),
        )->first();

        self::assertNotNull($outboxEvent);

        return $outboxEvent->id;
    }

    private function relay(): int
    {
        return $this->getContainer()->get(OutboxRelay::class)->relay(
            outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(10),
            now: new \DateTimeImmutable('2099-01-01 00:00:00'),
        );
    }

    private function fileService(): MediaFileServiceContract
    {
        return $this->getContainer()->get(MediaFileServiceContract::class);
    }

    private function jpegBytes(): string
    {
        $image = \imagecreatetruecolor(200, 150);
        \imagefill($image, 0, 0, (int) \imagecolorallocate($image, 40, 160, 90));

        \ob_start();
        \imagejpeg($image);

        return (string) \ob_get_clean();
    }

    private function track(MediaStorage $storage, MediaPath $path): void
    {
        $this->createdObjects[] = ['storage' => $storage, 'path' => $path];
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach ($this->createdObjects as $createdObject) {
            $this->fileService()->deleteObject($createdObject['storage'], $createdObject['path']);
        }

        $this->createdObjects = [];

        parent::tearDown();
    }
}
