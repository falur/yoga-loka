<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Command\CompleteMediaUpload\CompleteMediaUploadCommand;
use App\Modules\Media\Application\Command\CompleteMediaUpload\CompleteMediaUploadHandler;
use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaAudioConversionSpec;
use App\Modules\Media\Application\Dto\MediaConversionPlan;
use App\Modules\Media\Application\Dto\MediaImageConversionSpec;
use App\Modules\Media\Application\Dto\MediaObjectHead;
use App\Modules\Media\Application\Dto\MediaVideoConversionSpec;
use App\Modules\Media\Application\Message\MediaUploaded;
use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaMultipartUpload;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPart;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartETag;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartNumber;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartUploadIdValue;
use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
use App\Modules\Outbox\Application\Message\StoredOutboxEventId;
use App\Shared\Domain\Exception\ForbiddenException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Exception\ValidationException;
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
        $outboxStore = $this->createMock(OutboxEventStoreContract::class);
        $outboxStore->expects(self::once())->method('add')->willReturnCallback(
            function (MediaUploaded $message) use (&$captured): StoredOutboxEventId {
                $captured = $message;

                return StoredOutboxEventId::fromString('outbox-1');
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
        self::assertSame(MediaStatus::Uploaded, $media->status);
        self::assertInstanceOf(MediaUploaded::class, $captured);
        self::assertInstanceOf(MediaConversionPlan::class, $captured->plan);
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

        $this->expectException(ValidationException::class);

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

        $this->expectException(ValidationException::class);

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

        $this->expectException(ValidationException::class);

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

        $this->expectException(ValidationException::class);

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

        $this->expectException(ValidationException::class);

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

        $this->expectException(ValidationException::class);

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

        $this->expectException(ValidationException::class);

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

        $this->expectException(ValidationException::class);

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

        $this->expectException(ValidationException::class);

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

        $this->expectException(ValidationException::class);

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

        $this->expectException(ValidationException::class);

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

        $this->expectException(ValidationException::class);

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

        $this->expectException(ValidationException::class);

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
        $outboxStore = $this->createMock(OutboxEventStoreContract::class);
        $outboxStore->expects(self::once())->method('add')->willReturnCallback(
            function (MediaUploaded $message) use (&$captured): StoredOutboxEventId {
                $captured = $message;

                return StoredOutboxEventId::fromString('outbox-1');
            },
        );

        $result = $this->handler($this->fileServiceWithHead(1024), $outboxStore)->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->emptyPlan(),
            parts: null,
        ));

        self::assertSame(MediaStatus::Uploaded, $result->status);
        self::assertSame(MediaStatus::Uploaded, $media->status);
        self::assertInstanceOf(MediaUploaded::class, $captured);
    }

    #[DataProvider('nonEmptyDocumentPlanProvider')]
    public function testRejectsNonEmptyPlanForDocument(MediaConversionPlan $plan): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId, type: MediaType::Document, extension: 'pdf', mimeType: 'application/pdf');
        $this->persist($media);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('app.media.conversion_plan_type_mismatch');

        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $plan,
            parts: null,
        ));
    }

    /**
     * @return array<string, array{MediaConversionPlan}>
     */
    public static function nonEmptyDocumentPlanProvider(): array
    {
        return [
            'непустой список image' => [new MediaConversionPlan(
                image: [new MediaImageConversionSpec(type: MediaImageConversionType::Thumbnail, width: 100, height: 100)],
                video: [],
                audio: [],
            )],
            'непустой список video' => [new MediaConversionPlan(
                image: [],
                video: [new MediaVideoConversionSpec(
                    type: MediaVideoConversionType::NormalizedMp4H264,
                    width: 1280,
                    height: 720,
                    videoBitrate: 1_000_000,
                    audioBitrate: 128_000,
                )],
                audio: [],
            )],
            'непустой список audio' => [new MediaConversionPlan(
                image: [],
                video: [],
                audio: [new MediaAudioConversionSpec(
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
        $this->expectException(NotFoundException::class);

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

        $this->expectException(ForbiddenException::class);

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

        $this->expectException(ValidationException::class);

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

        $this->expectException(ValidationException::class);

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

        $this->expectException(ValidationException::class);

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

        $this->expectException(ValidationException::class);

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

        $this->expectException(ValidationException::class);

        $this->handler($this->fileServiceWithHead(999), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
            plan: $this->emptyPlan(),
            parts: null,
        ));
    }

    private function handler(
        MediaFileServiceContract $fileService,
        OutboxEventStoreContract $outboxStore,
    ): CompleteMediaUploadHandler {
        return new CompleteMediaUploadHandler(
            mediaRepository: $this->mediaRepository(),
            mediaMultipartUploadRepository: $this->multipartUploadRepository(),
            mediaFileService: $fileService,
            outboxEventStore: $outboxStore,
            entityManager: $this->entityManager(),
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

    private function outboxStore(): OutboxEventStoreContract
    {
        $outboxStore = $this->createStub(OutboxEventStoreContract::class);
        $outboxStore->method('add')->willReturn(StoredOutboxEventId::fromString('outbox-1'));

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
