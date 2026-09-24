<?php

declare(strict_types=1);

namespace App\Modules\Media\Tests\Integration\Spiral;

use App\Modules\Media\Application\Command\CompleteMediaUpload\CompleteMediaUploadCommand;
use App\Modules\Media\Application\Command\CompleteMediaUpload\CompleteMediaUploadHandler;
use App\Modules\Media\Application\Command\ProcessMedia\ProcessMediaCommand;
use App\Modules\Media\Application\Command\ProcessMedia\ProcessMediaHandler;
use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Public\Dto\MediaConversionPlanDto;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Public\Enum\MediaImageConversionType as PublicMediaImageConversionType;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaProcessingError;
use App\Modules\Media\Infrastructure\Spiral\Configuration\MediaConfig;
use App\Modules\Media\Infrastructure\Spiral\Job\ProcessMediaJob;
use Symfony\Component\Process\Process;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralOutbox\OutboxDeliveryStatus;
use Tests\Support\Outbox\CleansOutboxEvents;
use Tests\Support\Outbox\RunsOutboxRelay;

final class MediaProcessingFlowTest extends MediaApplicationTestCase
{
    use CleansOutboxEvents;
    use RunsOutboxRelay;

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

        $this->completeUpload($media, $this->imagePlan($this->imageConversionSpec(PublicMediaImageConversionType::Thumbnail)));
        $this->runOutboxRelayPass();

        // Проход relay создал доставку по маршруту события и выполнил её Job: подключение очереди
        // в тестах — `sync`, поэтому исход доставки известен сразу.
        $delivery = $this->outboxDeliveryOf(ProcessMediaJob::class);
        self::assertSame(OutboxDeliveryStatus::Completed, $delivery->status);

        $processedMedia = $this->mediaRepository()->findById($media->id);
        self::assertNotNull($processedMedia);
        self::assertSame(MediaStatus::Ready, $processedMedia->status);
        self::assertSame(MediaStorage::Public, $processedMedia->storage);

        $conversions = $this->mediaRepository()->findImageConversionsByMediaId($media->id);
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
            plan: $this->imagePlan($this->imageConversionSpec(PublicMediaImageConversionType::Thumbnail)),
        ));
        self::assertCount(1, $this->mediaRepository()->findImageConversionsByMediaId($media->id));
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
            plan: $this->imagePlan($this->imageConversionSpec(PublicMediaImageConversionType::Thumbnail)),
        ));

        $readyMedia = $this->mediaRepository()->findById($media->id);
        self::assertNotNull($readyMedia);
        self::assertSame(MediaStatus::Ready, $readyMedia->status);
        self::assertSame(MediaStorage::Public, $readyMedia->storage);
        self::assertTrue($readyMedia->processingError->isEmpty());

        $conversions = $this->mediaRepository()->findImageConversionsByMediaId($media->id);
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
        $this->runOutboxRelayPass();
        self::assertSame(OutboxDeliveryStatus::Completed, $this->outboxDeliveryOf(ProcessMediaJob::class)->status);

        $processedMedia = $this->mediaRepository()->findById($media->id);
        self::assertNotNull($processedMedia);
        self::assertSame(MediaStatus::Ready, $processedMedia->status);

        $videoConversion = $this->mediaRepository()->findVideoConversionsByMediaId($media->id)->first();
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

        self::assertCount(1, $this->mediaRepository()->findImageConversionsByMediaId($media->id));
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
        $this->runOutboxRelayPass();
        self::assertSame(OutboxDeliveryStatus::Completed, $this->outboxDeliveryOf(ProcessMediaJob::class)->status);

        $processedMedia = $this->mediaRepository()->findById($media->id);
        self::assertNotNull($processedMedia);
        self::assertSame(MediaStatus::Ready, $processedMedia->status);

        $audioConversion = $this->mediaRepository()->findAudioConversionsByMediaId($media->id)->first();
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

    public function testCompleteThenRelayProcessesDocumentToReady(): void
    {
        $media = $this->uploadedOriginal(
            $this->documentBytes(),
            MediaVisibility::Public,
            MediaType::Document,
            'pdf',
            'application/pdf',
        );

        $this->completeUpload($media, $this->emptyPlan());
        $this->runOutboxRelayPass();
        self::assertSame(OutboxDeliveryStatus::Completed, $this->outboxDeliveryOf(ProcessMediaJob::class)->status);

        $processedMedia = $this->mediaRepository()->findById($media->id);
        self::assertNotNull($processedMedia);
        self::assertSame(MediaStatus::Ready, $processedMedia->status);
        self::assertSame(MediaStorage::Public, $processedMedia->storage);

        // У документа конверсий нет.
        self::assertCount(0, $this->mediaRepository()->findImageConversionsByMediaId($media->id));

        $readyPath = MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Document, extension: 'pdf');
        self::assertStringStartsWith('documents/', $readyPath->value());
        $this->track(MediaStorage::Public, $readyPath);

        // Реальный оригинал переложен по пути готового оригинала documents/<shard>/<key>/source.pdf без конверсий.
        self::assertSame($readyPath->value(), $processedMedia->path->value());
        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $readyPath));
    }

    public function testPermanentProcessingFailureRecordsErrorAndClosesDeliveryFinally(): void
    {
        $corruptBytes = \random_bytes(2048);
        $media = $this->uploadedOriginal($corruptBytes, MediaVisibility::Public);

        $this->completeUpload(
            $media,
            $this->imagePlan($this->imageConversionSpec(PublicMediaImageConversionType::Thumbnail)),
        );
        $this->runOutboxRelayPass();

        $failedMedia = $this->mediaRepository()->findById($media->id);
        self::assertNotNull($failedMedia);
        self::assertSame(MediaStatus::ProcessingFailed, $failedMedia->status);
        self::assertFalse($failedMedia->processingError->isEmpty());

        // Битый файл — постоянный сбой обработчика, повтора он не заслуживает: доставка закрыта
        // окончательно, а не возвращена в очередь.
        $delivery = $this->outboxDeliveryOf(ProcessMediaJob::class);
        self::assertSame(OutboxDeliveryStatus::Failed, $delivery->status);
    }

    public function testFailedScenarioDoesNotStageEventAndLeavesNeighbourDeliveriesAlone(): void
    {
        // Соседняя доставка уже закрыта успехом: её состояние не должно измениться от чужого отказа.
        $readyMedia = $this->uploadedOriginal($this->jpegBytes(), MediaVisibility::Public);
        $this->completeUpload($readyMedia, $this->emptyPlan());
        $this->runOutboxRelayPass();
        $neighbourDelivery = $this->outboxDeliveryOf(ProcessMediaJob::class);
        self::assertSame(OutboxDeliveryStatus::Completed, $neighbourDelivery->status);

        $eventCountBeforeFailure = $this->outboxEventCount();

        // Отказ сценария: подтверждение загрузки для несуществующего медиа.
        try {
            $this->getContainer()->get(CommandBusInterface::class)->dispatch(
                command: new CompleteMediaUploadCommand(
                    userId: $readyMedia->uploadedById->value(),
                    mediaId: UserId::generate()->value(),
                    plan: $this->emptyPlan(),
                    parts: null,
                ),
                handler: $this->getContainer()->get(CompleteMediaUploadHandler::class)->handle(...),
            );
            self::fail('Ожидался отказ подтверждения загрузки.');
        } catch (\Throwable) {
            // Отказ ожидаем: проверяется его след в обмене, а не тип исключения.
        }

        self::assertSame($eventCountBeforeFailure, $this->outboxEventCount());
        self::assertSame(
            $neighbourDelivery->status,
            $this->outboxDeliveryOf(ProcessMediaJob::class)->status,
        );
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

    private function completeUpload(Media $media, MediaConversionPlanDto $plan): void
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

    private function documentBytes(): string
    {
        return "%PDF-1.4\nТестовое содержимое документа для сквозного теста.\n%%EOF";
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

        // Тест не обёрнут в транзакцию с откатом, поэтому убирает свои строки обмена сам: иначе
        // они дожили бы до следующего теста того же worker-а и сломали его счёт событий.
        $this->cleanOutboxEvents();

        parent::tearDown();
    }
}
