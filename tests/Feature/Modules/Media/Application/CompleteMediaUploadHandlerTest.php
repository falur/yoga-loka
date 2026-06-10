<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Command\Media\CompleteMediaUpload\CompleteMediaUploadCommand;
use App\Modules\Media\Application\Command\Media\CompleteMediaUpload\CompleteMediaUploadHandler;
use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaObjectHead;
use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaMultipartUpload;
use App\Modules\Media\Domain\Enum\MediaStatus;
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

        $outboxStore = $this->createMock(OutboxEventStoreContract::class);
        $outboxStore->expects(self::once())->method('add')->willReturn(StoredOutboxEventId::fromString('outbox-1'));

        $result = $this->handler($this->fileServiceWithHead(2048), $outboxStore)->handle(
            new CompleteMediaUploadCommand(
                userId: $userId->value(),
                mediaId: $media->id->value(),
                conversions: [$this->conversionSpec()],
                parts: null,
            ),
        );

        self::assertSame(MediaStatus::Uploaded, $result->status);
        self::assertSame(MediaStatus::Uploaded, $media->status);
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
            conversions: [],
            parts: $this->parts(),
        ));

        self::assertSame(MediaStatus::Uploaded, $result->status);
    }

    public function testRejectsMissingMedia(): void
    {
        $this->expectException(NotFoundException::class);

        $this->handler($this->fileServiceWithHead(2048), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
            userId: UserId::generate()->value(),
            mediaId: UserId::generate()->value(),
            conversions: [],
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
            conversions: [],
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
            conversions: [],
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
            conversions: [$this->conversionSpec(width: $width, height: $height)],
            parts: null,
        ));
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function outOfRangeConversionDimensionProvider(): array
    {
        // Симметрия с MediaPixelDimension (MIN=1, MAX=100_000): ноль/негатив снизу, 200000 сверху.
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
            conversions: [],
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
            conversions: [],
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
            conversions: [],
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
