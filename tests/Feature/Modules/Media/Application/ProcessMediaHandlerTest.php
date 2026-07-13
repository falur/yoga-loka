<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Command\ProcessMedia\ProcessMediaCommand;
use App\Modules\Media\Application\Command\ProcessMedia\ProcessMediaHandler;
use App\Modules\Media\Application\Contract\MediaAudioProcessorContract;
use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Contract\MediaImageProcessorContract;
use App\Modules\Media\Application\Contract\MediaVideoProcessorContract;
use App\Modules\Media\Application\Dto\MediaAudioProcessingResult;
use App\Modules\Media\Application\Dto\MediaConversionResult;
use App\Modules\Media\Application\Dto\MediaVideoProcessingResult;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaDuration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Domain\ValueObject\MediaProcessingError;
use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
use App\Modules\Media\Domain\ValueObject\MediaWaveform;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use Psr\Log\NullLogger;

final class ProcessMediaHandlerTest extends MediaApplicationTestCase
{
    public function testProcessesImageMediaWithConversions(): void
    {
        $media = $this->uploadedMedia(MediaVisibility::Private);
        $this->persist($media);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->method('getObjectContents')->willReturn('original-bytes');
        $fileService->expects(self::once())->method('putObject');
        $fileService->expects(self::once())->method('copyObject');

        $this->handler($fileService)->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            plan: $this->imagePlan($this->imageConversionSpec()),
        ));

        self::assertTrue($media->isReady());
        self::assertSame(MediaStorage::Private, $media->storage);

        $conversions = $this->imageConversionRepository()->findByMediaId($media->id);
        self::assertCount(1, $conversions);
        self::assertSame(MediaStorage::Private, $conversions->first()->storage);
    }

    public function testStoresConversionDimensionsFromProcessorResult(): void
    {
        $media = $this->uploadedMedia(MediaVisibility::Private);
        $this->persist($media);

        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('getObjectContents')->willReturn('original-bytes');

        $handler = $this->handler(fileService: $fileService, resultWidth: 100, resultHeight: 56);
        $handler->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            plan: $this->imagePlan($this->imageConversionSpec(width: 100, height: 100)),
        ));

        $conversion = $this->imageConversionRepository()->findByMediaId($media->id)->first();
        self::assertSame(100, $conversion->width->value());
        self::assertSame(56, $conversion->height->value());
    }

    public function testProcessesMediaWithoutConversionsMovesOriginalOnly(): void
    {
        $media = $this->uploadedMedia(MediaVisibility::Public);
        $this->persist($media);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::never())->method('getObjectContents');
        $fileService->expects(self::never())->method('putObject');
        $fileService->expects(self::once())->method('copyObject');

        $this->handler($fileService)->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            plan: $this->emptyPlan(),
        ));

        self::assertTrue($media->isReady());
        self::assertSame(MediaStorage::Public, $media->storage);
        self::assertCount(0, $this->imageConversionRepository()->findByMediaId($media->id));
    }

    public function testProcessesVideoMediaIntoNormalizedAndPoster(): void
    {
        $media = $this->uploadedVideoMedia();
        $this->persist($media);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::never())->method('getObjectContents');
        $fileService->expects(self::once())->method('copyObject');

        $videoProcessor = $this->createStub(MediaVideoProcessorContract::class);
        $videoProcessor->method('process')->willReturn(new MediaVideoProcessingResult(
            normalizedMimeType: MediaMimeType::fromString('video/mp4'),
            normalizedSize: MediaFileSize::fromInt(4096),
            width: MediaPixelDimension::fromInt(1280),
            height: MediaPixelDimension::fromInt(720),
            duration: MediaDuration::fromInt(2000),
            bitrate: MediaBitrate::fromInt(900_000),
            posterMimeType: MediaMimeType::fromString('image/jpeg'),
            posterSize: MediaFileSize::fromInt(512),
        ));

        $this->handler(fileService: $fileService, videoProcessor: $videoProcessor)->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            plan: $this->videoPlan($this->videoConversionSpec()),
        ));

        self::assertTrue($media->isReady());

        $videoConversion = $this->videoConversionRepository()->findByMediaId($media->id)->first();
        self::assertNotNull($videoConversion);
        self::assertSame(1280, $videoConversion->width->value());
        self::assertSame(2000, $videoConversion->duration->value());

        $poster = $this->imageConversionRepository()->findByMediaId($media->id)->first();
        self::assertNotNull($poster);
        self::assertSame(MediaImageConversionType::Poster, $poster->type);
        self::assertSame(1280, $poster->width->value());
    }

    public function testProcessesAudioMediaIntoNormalizedWithWaveform(): void
    {
        $media = $this->uploadedAudioMedia();
        $this->persist($media);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::never())->method('getObjectContents');
        $fileService->expects(self::once())->method('copyObject');

        $audioProcessor = $this->createStub(MediaAudioProcessorContract::class);
        $audioProcessor->method('process')->willReturn(new MediaAudioProcessingResult(
            normalizedMimeType: MediaMimeType::fromString('audio/mp4'),
            normalizedSize: MediaFileSize::fromInt(2048),
            duration: MediaDuration::fromInt(3000),
            bitrate: MediaBitrate::fromInt(128_000),
            sampleRate: MediaSampleRate::fromInt(44_100),
            waveform: MediaWaveform::fromPeaks([0, 64, 128, 255]),
        ));

        $this->handler(fileService: $fileService, audioProcessor: $audioProcessor)->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            plan: $this->audioPlan($this->audioConversionSpec()),
        ));

        self::assertTrue($media->isReady());

        $audioConversion = $this->audioConversionRepository()->findByMediaId($media->id)->first();
        self::assertNotNull($audioConversion);
        self::assertSame(44_100, $audioConversion->sampleRate->value());
        self::assertSame([0, 64, 128, 255], $audioConversion->waveform->peaks());
    }

    public function testRerunsVideoAfterProcessingFailed(): void
    {
        $media = $this->uploadedVideoMedia();
        $media->recordTemporaryProcessingError(MediaProcessingError::fromString('Временная ошибка обработки.'));
        $this->persist($media);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::once())->method('copyObject');

        $videoProcessor = $this->createStub(MediaVideoProcessorContract::class);
        $videoProcessor->method('process')->willReturn(new MediaVideoProcessingResult(
            normalizedMimeType: MediaMimeType::fromString('video/mp4'),
            normalizedSize: MediaFileSize::fromInt(4096),
            width: MediaPixelDimension::fromInt(640),
            height: MediaPixelDimension::fromInt(480),
            duration: MediaDuration::fromInt(1000),
            bitrate: MediaBitrate::fromInt(500_000),
            posterMimeType: MediaMimeType::fromString('image/jpeg'),
            posterSize: MediaFileSize::fromInt(256),
        ));

        $this->handler(fileService: $fileService, videoProcessor: $videoProcessor)->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            plan: $this->videoPlan($this->videoConversionSpec()),
        ));

        self::assertTrue($media->isReady());
        self::assertCount(1, $this->videoConversionRepository()->findByMediaId($media->id));
    }

    public function testRerunsAudioAfterProcessingFailed(): void
    {
        $media = $this->uploadedAudioMedia();
        $media->recordTemporaryProcessingError(MediaProcessingError::fromString('Временная ошибка обработки.'));
        $this->persist($media);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::once())->method('copyObject');

        $audioProcessor = $this->createStub(MediaAudioProcessorContract::class);
        $audioProcessor->method('process')->willReturn(new MediaAudioProcessingResult(
            normalizedMimeType: MediaMimeType::fromString('audio/mp4'),
            normalizedSize: MediaFileSize::fromInt(2048),
            duration: MediaDuration::fromInt(3000),
            bitrate: MediaBitrate::fromInt(128_000),
            sampleRate: MediaSampleRate::fromInt(44_100),
            waveform: MediaWaveform::fromPeaks([0, 64, 128, 255]),
        ));

        $this->handler(fileService: $fileService, audioProcessor: $audioProcessor)->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            plan: $this->audioPlan($this->audioConversionSpec()),
        ));

        self::assertTrue($media->isReady());

        $audioConversions = $this->audioConversionRepository()->findByMediaId($media->id);
        self::assertCount(1, $audioConversions);
        self::assertSame(44_100, $audioConversions->first()->sampleRate->value());
        self::assertSame([0, 64, 128, 255], $audioConversions->first()->waveform->peaks());
    }

    public function testIsNoOpWhenMediaAlreadyReady(): void
    {
        $media = $this->uploadedMedia(MediaVisibility::Private);
        $media->markReadyMovedTo(
            MediaStorage::Private,
            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
        );
        $this->persist($media);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::never())->method('getObjectContents');
        $fileService->expects(self::never())->method('copyObject');

        $this->handler($fileService)->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            plan: $this->imagePlan($this->imageConversionSpec()),
        ));

        self::assertTrue($media->isReady());
    }

    public function testIsNoOpWhenMediaAlreadyOriginalRemoved(): void
    {
        // Защита финализированного состояния: дубль/повтор ProcessMedia после удаления оригинала —
        // ранний no-op (не читает S3, не меняет статус, не пишет ошибку через запись сбоя).
        $media = $this->uploadedMedia(MediaVisibility::Private);
        $media->markReadyMovedTo(
            MediaStorage::Private,
            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
        );
        $media->markReadyOriginalRemoved();
        $this->persist($media);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::never())->method('getObjectContents');
        $fileService->expects(self::never())->method('copyObject');

        $this->handler($fileService)->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            plan: $this->imagePlan($this->imageConversionSpec()),
        ));

        // Ради этого расширен guard до isFinalized: поздняя запись ошибки на readyOriginalRemoved
        // не должна сработать — статус, число попыток и текст ошибки остаются нетронутыми.
        self::assertSame(MediaStatus::ReadyOriginalRemoved, $media->status);
        self::assertSame(0, $media->processingAttempts->value());
        self::assertNull($media->processingError->value());
    }

    public function testProcessesDocumentByMovingOriginalWithoutConversions(): void
    {
        $media = $this->createMedia(
            userId: UserId::generate(),
            visibility: MediaVisibility::Public,
            type: MediaType::Document,
            extension: 'pdf',
            mimeType: 'application/pdf',
        );
        $media->markUploaded();
        $this->persist($media);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::never())->method('getObjectContents');
        $fileService->expects(self::never())->method('putObject');
        $fileService->expects(self::once())->method('copyObject');

        $this->handler($fileService)->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            plan: $this->emptyPlan(),
        ));

        self::assertTrue($media->isReady());
        self::assertSame(MediaStorage::Public, $media->storage);
        // Кейс с подменённым сервисом проверяет ветку без конверсий: смену пути на documents/
        // и хранилище по видимости. Реальную перекладку оригинала в постоянное хранилище держит
        // сквозной MediaProcessingFlowTest::testCompleteThenRelayProcessesDocumentToReady.
        self::assertStringStartsWith('documents/', $media->path->value());
        self::assertCount(0, $this->imageConversionRepository()->findByMediaId($media->id));
    }

    public function testRejectsMissingMedia(): void
    {
        $this->expectException(NotFoundException::class);

        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new ProcessMediaCommand(
            mediaId: UserId::generate()->value(),
            plan: $this->emptyPlan(),
        ));
    }

    public function testRejectsMediaInWaitingUploadStatus(): void
    {
        $media = $this->createMedia(userId: UserId::generate());
        $this->persist($media);

        $this->expectException(InvalidDomainValueException::class);

        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            plan: $this->emptyPlan(),
        ));
    }

    private function handler(
        MediaFileServiceContract $fileService,
        int $resultWidth = 100,
        int $resultHeight = 100,
        MediaVideoProcessorContract|null $videoProcessor = null,
        MediaAudioProcessorContract|null $audioProcessor = null,
    ): ProcessMediaHandler {
        $imageProcessor = $this->createStub(MediaImageProcessorContract::class);
        $imageProcessor->method('resize')->willReturn(new MediaConversionResult(
            contents: 'conversion-bytes',
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(128),
            width: MediaPixelDimension::fromInt($resultWidth),
            height: MediaPixelDimension::fromInt($resultHeight),
        ));

        return new ProcessMediaHandler(
            mediaRepository: $this->mediaRepository(),
            mediaFileService: $fileService,
            mediaImageProcessor: $imageProcessor,
            mediaVideoProcessor: $videoProcessor ?? $this->createStub(MediaVideoProcessorContract::class),
            mediaAudioProcessor: $audioProcessor ?? $this->createStub(MediaAudioProcessorContract::class),
            entityManager: $this->entityManager(),
            logger: new NullLogger(),
        );
    }

    private function uploadedMedia(MediaVisibility $visibility): Media
    {
        $media = $this->createMedia(userId: UserId::generate(), visibility: $visibility);
        $media->markUploaded();

        return $media;
    }

    private function uploadedVideoMedia(): Media
    {
        $media = $this->createMedia(
            userId: UserId::generate(),
            visibility: MediaVisibility::Public,
            type: MediaType::Video,
            extension: 'mp4',
            mimeType: 'video/mp4',
        );
        $media->markUploaded();

        return $media;
    }

    private function uploadedAudioMedia(): Media
    {
        $media = $this->createMedia(
            userId: UserId::generate(),
            visibility: MediaVisibility::Public,
            type: MediaType::Audio,
            extension: 'mp3',
            mimeType: 'audio/mpeg',
        );
        $media->markUploaded();

        return $media;
    }
}
