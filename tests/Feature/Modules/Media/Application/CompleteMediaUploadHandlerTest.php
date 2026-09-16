<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Command\CompleteMediaUpload\CompleteMediaUploadCommand;
use App\Modules\Media\Application\Command\CompleteMediaUpload\CompleteMediaUploadHandler;
use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Public\Dto\MediaAudioConversionSpecDto;
use App\Modules\Media\Public\Dto\MediaConversionPlanDto;
use App\Modules\Media\Public\Dto\MediaImageConversionSpecDto;
use App\Modules\Media\Application\Dto\MediaObjectHead;
use App\Modules\Media\Public\Dto\MediaVideoConversionSpecDto;
use App\Modules\Media\Public\Event\MediaUploadedEvent;
use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaMultipartUpload;
use App\Modules\Media\Public\Enum\MediaAudioConversionType;
use App\Modules\Media\Public\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Public\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\Exception\MediaAccessDeniedException;
use App\Modules\Media\Domain\Exception\MediaAudioConversionProfileRequiredException;
use App\Modules\Media\Domain\Exception\MediaConversionBitrateOutOfRangeException;
use App\Modules\Media\Domain\Exception\MediaConversionDimensionsOutOfRangeException;
use App\Modules\Media\Domain\Exception\MediaConversionDuplicateTypeException;
use App\Modules\Media\Domain\Exception\MediaConversionPlanTypeMismatchException;
use App\Modules\Media\Domain\Exception\MediaConversionSampleRateOutOfRangeException;
use App\Modules\Media\Domain\Exception\MediaConversionWaveformPeaksOutOfRangeException;
use App\Modules\Media\Domain\Exception\MediaMultipartUploadNotFoundException;
use App\Modules\Media\Domain\Exception\MediaNotFoundException;
use App\Modules\Media\Domain\Exception\MediaUploadNotPendingException;
use App\Modules\Media\Domain\Exception\MediaUploadedObjectMismatchException;
use App\Modules\Media\Domain\Exception\MediaVideoConversionProfileRequiredException;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPart;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartETag;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartNumber;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartUploadIdValue;
use App\Modules\Outbox\Public\Contract\IntegrationEventStoreContract;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;

final class CompleteMediaUploadHandlerTest extends MediaApplicationTestCase
{
    public function testCompletesSingleUploadAndQueuesProcessing(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, size: MediaFileSize::fromInt(2048));
        $this->persist($media);

        $captured = null;
        $outboxStore = $this->createMock(IntegrationEventStoreContract::class);
        $outboxStore->expects(self::once())->method('add')->willReturnCallback(
            function (MediaUploadedEvent $message) use (&$captured): string {
                $captured = $message;

                return 'outbox-1';
            },
        );

        $result = $this->handler($this->fileServiceWithHead(2048), $outboxStore)->handle(
            new CompleteMediaUploadCommand(
                userId: $userId->value(),
                mediaId: $media->id->value(),
                plan: $this->imagePlan($this->imageConversionSpec()),
                parts: null,
            ),
        );

        self::assertSame(MediaStatus::Uploaded, $result->status);
        // Media — чистая доменная сущность без Cycle-разметки: handler мутировал свою
        // отдельно загруженную через Mapper копию, а не переменную $media теста, поэтому
        // статус в БД проверяется перечитыванием через репозиторий.
        self::assertSame(MediaStatus::Uploaded, $this->mediaRepository()->findById($media->id)?->status);
        self::assertInstanceOf(MediaUploadedEvent::class, $captured);
        self::assertInstanceOf(MediaConversionPlanDto::class, $captured->plan);
        self::assertCount(1, $captured->plan->image);
    }

    public function testCompletesMultipartUpload(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, size: MediaFileSize::fromInt(2048));
        $multipartUpload = $this->multipartUploadFor($media);
        $this->persist($media, $multipartUpload);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->method('headObject')->willReturn(new MediaObjectHead(contentLength: MediaFileSize::fromInt(2048)));
        $fileService->expects(self::once())->method('completeMultipartUpload');

        $result = $this->handler($fileService, $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->emptyPlan(),
            parts: $this->parts(),
        ));

        self::assertSame(MediaStatus::Uploaded, $result->status);
    }

    public function testCompletesVideoUploadWithVideoPlan(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(
            userId: $userId,
            type: MediaType::Video,
            size: MediaFileSize::fromInt(2048),
            extension: 'mp4',
            mimeType: 'video/mp4',
        );
        $this->persist($media);

        $result = $this->handler($this->fileServiceWithHead(2048), $this->outboxStore())->handle(
            new CompleteMediaUploadCommand(
                userId: $userId->value(),
                mediaId: $media->id->value(),
                plan: $this->videoPlan($this->videoConversionSpec()),
                parts: null,
            ),
        );

        self::assertSame(MediaStatus::Uploaded, $result->status);
    }

    public function testCompletesAudioUploadWithAudioPlan(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(
            userId: $userId,
            type: MediaType::Audio,
            size: MediaFileSize::fromInt(2048),
            extension: 'mp3',
            mimeType: 'audio/mpeg',
        );
        $this->persist($media);

        $result = $this->handler($this->fileServiceWithHead(2048), $this->outboxStore())->handle(
            new CompleteMediaUploadCommand(
                userId: $userId->value(),
                mediaId: $media->id->value(),
                plan: $this->audioPlan($this->audioConversionSpec()),
                parts: null,
            ),
        );

        self::assertSame(MediaStatus::Uploaded, $result->status);
    }

    public function testRejectsCrossTypePlan(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId);
        $this->persist($media);

        $this->expectException(MediaConversionPlanTypeMismatchException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->videoPlan($this->videoConversionSpec()),
            parts: null,
        ));
    }

    public function testRejectsDuplicateImageConversionTypes(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId);
        $this->persist($media);

        $this->expectException(MediaConversionDuplicateTypeException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->imagePlan($this->imageConversionSpec(), $this->imageConversionSpec()),
            parts: null,
        ));
    }

    public function testRejectsEmptyVideoPlanForVideoMedia(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
        $this->persist($media);

        $this->expectException(MediaVideoConversionProfileRequiredException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->emptyPlan(),
            parts: null,
        ));
    }

    public function testRejectsMultipleVideoProfiles(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
        $this->persist($media);

        $this->expectException(MediaVideoConversionProfileRequiredException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->videoPlan($this->videoConversionSpec(), $this->videoConversionSpec()),
            parts: null,
        ));
    }

    public function testRejectsVideoBitrateOutOfRange(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
        $this->persist($media);

        $this->expectException(MediaConversionBitrateOutOfRangeException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->videoPlan($this->videoConversionSpec(videoBitrate: 0)),
            parts: null,
        ));
    }

    public function testRejectsAudioSampleRateOutOfRange(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
        $this->persist($media);

        $this->expectException(MediaConversionSampleRateOutOfRangeException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->audioPlan($this->audioConversionSpec(sampleRate: 1)),
            parts: null,
        ));
    }

    public function testRejectsAudioWaveformPeaksOutOfRange(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
        $this->persist($media);

        $this->expectException(MediaConversionWaveformPeaksOutOfRangeException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->audioPlan($this->audioConversionSpec(waveformPeaks: 100_000)),
            parts: null,
        ));
    }

    public function testRejectsForeignListForVideoMedia(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
        $this->persist($media);

        $this->expectException(MediaConversionPlanTypeMismatchException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->audioPlan($this->audioConversionSpec()),
            parts: null,
        ));
    }

    public function testRejectsVideoDimensionOutOfRange(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
        $this->persist($media);

        $this->expectException(MediaConversionDimensionsOutOfRangeException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->videoPlan($this->videoConversionSpec(width: 0)),
            parts: null,
        ));
    }

    public function testRejectsForeignListForAudioMedia(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
        $this->persist($media);

        $this->expectException(MediaConversionPlanTypeMismatchException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->videoPlan($this->videoConversionSpec()),
            parts: null,
        ));
    }

    public function testRejectsEmptyAudioPlanForAudioMedia(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
        $this->persist($media);

        $this->expectException(MediaAudioConversionProfileRequiredException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->emptyPlan(),
            parts: null,
        ));
    }

    public function testRejectsMultipleAudioProfiles(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
        $this->persist($media);

        $this->expectException(MediaAudioConversionProfileRequiredException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->audioPlan($this->audioConversionSpec(), $this->audioConversionSpec()),
            parts: null,
        ));
    }

    public function testRejectsAudioBitrateOutOfRange(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
        $this->persist($media);

        $this->expectException(MediaConversionBitrateOutOfRangeException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->audioPlan($this->audioConversionSpec(bitrate: 0)),
            parts: null,
        ));
    }

    public function testCompletesDocumentUploadWithEmptyPlan(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, type: MediaType::Document, extension: 'pdf', mimeType: 'application/pdf');
        $this->persist($media);

        $captured = null;
        $outboxStore = $this->createMock(IntegrationEventStoreContract::class);
        $outboxStore->expects(self::once())->method('add')->willReturnCallback(
            function (MediaUploadedEvent $message) use (&$captured): string {
                $captured = $message;

                return 'outbox-1';
            },
        );

        $result = $this->handler($this->fileServiceWithHead(1024), $outboxStore)->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->emptyPlan(),
            parts: null,
        ));

        self::assertSame(MediaStatus::Uploaded, $result->status);
        // Media — чистая доменная сущность без Cycle-разметки: handler мутировал свою
        // отдельно загруженную через Mapper копию, а не переменную $media теста, поэтому
        // статус в БД проверяется перечитыванием через репозиторий.
        self::assertSame(MediaStatus::Uploaded, $this->mediaRepository()->findById($media->id)?->status);
        self::assertInstanceOf(MediaUploadedEvent::class, $captured);
    }

    #[DataProvider('nonEmptyDocumentPlanProvider')]
    public function testRejectsNonEmptyPlanForDocument(MediaConversionPlanDto $plan): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, type: MediaType::Document, extension: 'pdf', mimeType: 'application/pdf');
        $this->persist($media);

        $this->expectException(MediaConversionPlanTypeMismatchException::class);
        $this->expectExceptionMessage('app.media.conversion_plan_type_mismatch');

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $plan,
            parts: null,
        ));
    }

    /**
     * @return array<string, array{MediaConversionPlanDto}>
     */
    public static function nonEmptyDocumentPlanProvider(): array
    {
        return [
            'непустой список image' => [new MediaConversionPlanDto(
                image: [new MediaImageConversionSpecDto(type: MediaImageConversionType::Thumbnail, width: 100, height: 100)],
                video: [],
                audio: [],
            )],
            'непустой список video' => [new MediaConversionPlanDto(
                image: [],
                video: [new MediaVideoConversionSpecDto(
                    type: MediaVideoConversionType::NormalizedMp4H264,
                    width: 1280,
                    height: 720,
                    videoBitrate: 1_000_000,
                    audioBitrate: 128_000,
                )],
                audio: [],
            )],
            'непустой список audio' => [new MediaConversionPlanDto(
                image: [],
                video: [],
                audio: [new MediaAudioConversionSpecDto(
                    type: MediaAudioConversionType::NormalizedAacM4a,
                    bitrate: 128_000,
                    sampleRate: 44_100,
                    waveformPeaks: 64,
                )],
            )],
        ];
    }

    public function testRejectsMissingMedia(): void
    {
        $this->expectException(MediaNotFoundException::class);

        $this->handler($this->fileServiceWithHead(2048), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: UserId::generate()->value(),
            mediaId: UserId::generate()->value(),
            plan: $this->emptyPlan(),
            parts: null,
        ));
    }

    public function testRejectsForeignOwner(): void
    {
        $media = $this->createMedia(userId: UserId::generate());
        $this->persist($media);

        $this->expectException(MediaAccessDeniedException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: UserId::generate()->value(),
            mediaId: $media->id->value(),
            plan: $this->emptyPlan(),
            parts: null,
        ));
    }

    public function testRejectsMediaNotWaitingUpload(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId);
        $media->markUploaded();
        $this->persist($media);

        $this->expectException(MediaUploadNotPendingException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->emptyPlan(),
            parts: null,
        ));
    }

    #[DataProvider('outOfRangeConversionDimensionProvider')]
    public function testRejectsConversionDimensionsOutOfDomainRange(int $width, int $height): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId);
        $this->persist($media);

        $this->expectException(MediaConversionDimensionsOutOfRangeException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->imagePlan($this->imageConversionSpec(width: $width, height: $height)),
            parts: null,
        ));
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function outOfRangeConversionDimensionProvider(): array
    {
        return [
            'нулевая ширина (нижняя граница)' => [0, 100],
            'нулевая высота (нижняя граница)' => [100, 0],
            'ширина выше максимума (верхняя граница)' => [200_000, 100],
            'высота выше максимума (верхняя граница)' => [100, 200_000],
        ];
    }

    public function testRejectsMultipartWithoutUploadRecord(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId);
        $this->persist($media);

        $this->expectException(MediaMultipartUploadNotFoundException::class);

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->emptyPlan(),
            parts: $this->parts(),
        ));
    }

    public function testRejectsWhenUploadedObjectMissing(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId);
        $this->persist($media);

        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('headObject')->willReturn(null);

        $this->expectException(MediaUploadedObjectMismatchException::class);

        $this->handler($fileService, $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->emptyPlan(),
            parts: null,
        ));
    }

    public function testRejectsWhenUploadedObjectSizeMismatches(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, size: MediaFileSize::fromInt(2048));
        $this->persist($media);

        $this->expectException(MediaUploadedObjectMismatchException::class);

        $this->handler($this->fileServiceWithHead(999), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->emptyPlan(),
            parts: null,
        ));
    }

    private function handler(
        MediaFileServiceContract $fileService,
        IntegrationEventStoreContract $outboxStore,
    ): CompleteMediaUploadHandler {
        return new CompleteMediaUploadHandler(
            mediaRepository: $this->mediaRepository(),
            mediaFileService: $fileService,
            integrationEventStore: $outboxStore,
            logger: new NullLogger(),
        );
    }

    private function fileServiceWithHead(int $contentLength): MediaFileServiceContract
    {
        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('headObject')->willReturn(
            new MediaObjectHead(contentLength: MediaFileSize::fromInt($contentLength)),
        );

        return $fileService;
    }

    private function outboxStore(): IntegrationEventStoreContract
    {
        $outboxStore = $this->createStub(IntegrationEventStoreContract::class);
        $outboxStore->method('add')->willReturn('outbox-1');

        return $outboxStore;
    }

    private function multipartUploadFor(Media $media): MediaMultipartUpload
    {
        return MediaMultipartUpload::create(
            media: $media,
            uploadId: MediaMultipartUploadIdValue::fromString('upload-1'),
            partsCount: MediaMultipartPartsCount::fromInt(1),
            partSize: MediaMultipartPartSize::fromInt(5_242_880),
            fileSize: $media->size,
        );
    }

    private function parts(): MediaMultipartPartCollection
    {
        return new MediaMultipartPartCollection([
            MediaMultipartPart::create(
                partNumber: MediaMultipartPartNumber::fromInt(1),
                eTag: MediaMultipartPartETag::fromString('etag-1'),
            ),
        ]);
    }
}
