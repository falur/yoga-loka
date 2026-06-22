<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Flow;

use App\Modules\Media\Application\Command\CompleteMediaUpload\CompleteMediaUploadCommand;
use App\Modules\Media\Application\Command\CompleteMediaUpload\CompleteMediaUploadHandler;
use App\Modules\Media\Application\Command\ProcessMedia\ProcessMediaCommand;
use App\Modules\Media\Application\Command\ProcessMedia\ProcessMediaHandler;
use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaConversionPlan;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaProcessingError;
use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
use Symfony\Component\Process\Process;
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

        $this->completeUpload($media, $this->imagePlan($this->imageConversionSpec(MediaImageConversionType::Thumbnail)));
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
            plan: $this->imagePlan($this->imageConversionSpec(MediaImageConversionType::Thumbnail)),
        ));
        self::assertCount(1, $this->imageConversionRepository()->findByMediaId($media->id));
    }

    public function testProcessingRecoversFromProcessingFailedToReady(): void
    {
        $bytes = $this->jpegBytes();
        $media = $this->uploadedOriginal($bytes, MediaVisibility::Public);

        // Имитируем зафиксированный временный сбой: медиа осталось в processingFailed.
        $media->recordTemporaryProcessingError(
            MediaProcessingError::fromString('Временная ошибка обработки.'),
        );
        $this->persist($media);
        self::assertSame(MediaStatus::ProcessingFailed, $media->status);

        // Повторная доставка: ProcessMedia с валидным оригиналом доводит медиа до ready.
        $this->getContainer()->get(ProcessMediaHandler::class)->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            plan: $this->imagePlan($this->imageConversionSpec(MediaImageConversionType::Thumbnail)),
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

    public function testCompleteThenRelayProcessesVideoToReady(): void
    {
        $media = $this->uploadedOriginal(
            $this->videoBytes(),
            MediaVisibility::Public,
            MediaType::Video,
            'mp4',
            'video/mp4',
        );

        $this->completeUpload($media, $this->videoPlan($this->videoConversionSpec(width: 640, height: 480)));
        self::assertSame(1, $this->relay());

        $processedMedia = $this->mediaRepository()->findById($media->id);
        self::assertNotNull($processedMedia);
        self::assertSame(MediaStatus::Ready, $processedMedia->status);

        $videoConversion = $this->videoConversionRepository()->findByMediaId($media->id)->first();
        self::assertNotNull($videoConversion);

        $normalizedPath = MediaPath::videoConversion(
            storageKey: $media->storageKey,
            type: MediaVideoConversionType::NormalizedMp4H264,
            extension: 'mp4',
        );
        $posterPath = MediaPath::imageConversion(
            storageKey: $media->storageKey,
            type: MediaImageConversionType::Poster,
            extension: 'jpg',
        );
        $readyPath = MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Video, extension: 'mp4');
        $this->track(MediaStorage::Public, $normalizedPath);
        $this->track(MediaStorage::Public, $posterPath);
        $this->track(MediaStorage::Public, $readyPath);

        self::assertCount(1, $this->imageConversionRepository()->findByMediaId($media->id));
        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $normalizedPath));
        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $posterPath));
    }

    public function testCompleteThenRelayProcessesAudioToReady(): void
    {
        $media = $this->uploadedOriginal(
            $this->audioBytes(),
            MediaVisibility::Public,
            MediaType::Audio,
            'wav',
            'audio/wav',
        );

        $this->completeUpload($media, $this->audioPlan($this->audioConversionSpec(waveformPeaks: 48)));
        self::assertSame(1, $this->relay());

        $processedMedia = $this->mediaRepository()->findById($media->id);
        self::assertNotNull($processedMedia);
        self::assertSame(MediaStatus::Ready, $processedMedia->status);

        $audioConversion = $this->audioConversionRepository()->findByMediaId($media->id)->first();
        self::assertNotNull($audioConversion);
        self::assertCount(48, $audioConversion->waveform->peaks());

        $normalizedPath = MediaPath::audioConversion(
            storageKey: $media->storageKey,
            type: MediaAudioConversionType::NormalizedAacM4a,
            extension: 'm4a',
        );
        $readyPath = MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Audio, extension: 'wav');
        $this->track(MediaStorage::Public, $normalizedPath);
        $this->track(MediaStorage::Public, $readyPath);

        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $normalizedPath));
    }

    public function testProcessingFailureRecordsErrorAndFailsOutbox(): void
    {
        $corruptBytes = \random_bytes(2048);
        $media = $this->uploadedOriginal($corruptBytes, MediaVisibility::Public);

        $outboxEventId = $this->completeUpload(
            $media,
            $this->imagePlan($this->imageConversionSpec(MediaImageConversionType::Thumbnail)),
        );
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

    private function uploadedOriginal(
        string $bytes,
        MediaVisibility $visibility,
        MediaType $type = MediaType::Image,
        string $extension = 'jpg',
        string $mimeType = 'image/jpeg',
    ): Media {
        $media = $this->createMedia(
            userId: UserId::generate(),
            visibility: $visibility,
            type: $type,
            size: MediaFileSize::fromInt(\strlen($bytes)),
            extension: $extension,
            mimeType: $mimeType,
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

    private function completeUpload(Media $media, MediaConversionPlan $plan): OutboxEventId
    {
        $this->getContainer()->get(CommandBusInterface::class)->dispatch(
            command: new CompleteMediaUploadCommand(
                userId: $media->uploadedById->value(),
                mediaId: $media->id->value(),
                plan: $plan,
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

    private function videoBytes(): string
    {
        return $this->ffmpegFixture('mp4', [
            '-f', 'lavfi', '-i', 'testsrc=duration=1:size=320x240:rate=15',
            '-f', 'lavfi', '-i', 'sine=frequency=440:duration=1',
            '-shortest', '-pix_fmt', 'yuv420p',
        ]);
    }

    private function audioBytes(): string
    {
        return $this->ffmpegFixture('wav', ['-f', 'lavfi', '-i', 'sine=frequency=440:duration=1']);
    }

    /**
     * @param list<string> $inputArgs
     */
    private function ffmpegFixture(string $extension, array $inputArgs): string
    {
        $ffmpegBinary = $this->getContainer()->get(MediaConfig::class)->ffmpegBinaryPath;
        $path = \sprintf('%s/flow_fixture_%s.%s', \sys_get_temp_dir(), \bin2hex(\random_bytes(8)), $extension);

        $process = new Process([$ffmpegBinary, ...$inputArgs, '-y', $path]);
        $process->mustRun();

        $bytes = (string) \file_get_contents($path);
        \unlink($path);

        return $bytes;
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
