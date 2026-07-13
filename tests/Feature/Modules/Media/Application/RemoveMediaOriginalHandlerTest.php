<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Command\RemoveMediaOriginal\RemoveMediaOriginalCommand;
use App\Modules\Media\Application\Command\RemoveMediaOriginal\RemoveMediaOriginalHandler;
use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Service\MediaConversionsChecker;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Shared\Domain\Exception\ForbiddenException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\ValueObject\UserId;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class RemoveMediaOriginalHandlerTest extends MediaApplicationTestCase
{
    public function testRemovesOriginalOfImageMediaKeepingConversion(): void
    {
        $userId = UserId::generate();
        $media = $this->readyImageMediaWithConversion($userId);
        $originalStorage = $media->storage;
        $originalPath = $media->path->value();

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $deleted = [];
        $fileService->expects(self::once())->method('deleteObject')->willReturnCallback(
            function (MediaStorage $storage, MediaPath $path) use (&$deleted): void {
                $deleted[] = [$storage, $path->value()];
            },
        );

        $result = $this->handler($fileService)->handle(new RemoveMediaOriginalCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
        ));

        // Удалён именно текущий оригинал в целевом бакете (после markReadyMovedTo), не staging.
        self::assertSame([[$originalStorage, $originalPath]], $deleted);
        self::assertSame(MediaStatus::ReadyOriginalRemoved, $media->status);
        self::assertSame(MediaStatus::ReadyOriginalRemoved, $result->status);
        self::assertSame($media->id->value(), $result->mediaId);
        self::assertCount(1, $this->imageConversionRepository()->findByMediaId($media->id));
    }

    public function testRemovesOriginalOfVideoMediaKeepingConversionAndPoster(): void
    {
        $userId = UserId::generate();
        $media = $this->readyVideoMediaWithConversion($userId);
        $originalStorage = $media->storage;
        $originalPath = $media->path->value();

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $deleted = [];
        $fileService->expects(self::once())->method('deleteObject')->willReturnCallback(
            function (MediaStorage $storage, MediaPath $path) use (&$deleted): void {
                $deleted[] = [$storage, $path->value()];
            },
        );

        $result = $this->handler($fileService)->handle(new RemoveMediaOriginalCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
        ));

        // Удалён именно оригинал видео, а не конверсия/постер.
        self::assertSame([[$originalStorage, $originalPath]], $deleted);
        self::assertSame(MediaStatus::ReadyOriginalRemoved, $media->status);
        self::assertSame(MediaStatus::ReadyOriginalRemoved, $result->status);
        self::assertSame($media->id->value(), $result->mediaId);
        self::assertCount(1, $this->videoConversionRepository()->findByMediaId($media->id));
        self::assertCount(1, $this->imageConversionRepository()->findByMediaId($media->id));
    }

    public function testRemovesOriginalOfAudioMediaKeepingConversion(): void
    {
        $userId = UserId::generate();
        $media = $this->readyAudioMediaWithConversion($userId);
        $originalStorage = $media->storage;
        $originalPath = $media->path->value();

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $deleted = [];
        $fileService->expects(self::once())->method('deleteObject')->willReturnCallback(
            function (MediaStorage $storage, MediaPath $path) use (&$deleted): void {
                $deleted[] = [$storage, $path->value()];
            },
        );

        $result = $this->handler($fileService)->handle(new RemoveMediaOriginalCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
        ));

        // У audio нет image-конверсии — гард обнаруживает конверсию через аудио-репозиторий.
        // Удалён именно оригинал, а не аудио-конверсия.
        self::assertSame([[$originalStorage, $originalPath]], $deleted);
        self::assertSame(MediaStatus::ReadyOriginalRemoved, $media->status);
        self::assertSame(MediaStatus::ReadyOriginalRemoved, $result->status);
        self::assertSame($media->id->value(), $result->mediaId);
        self::assertCount(1, $this->audioConversionRepository()->findByMediaId($media->id));
    }

    public function testRejectsMissingMedia(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('app.media.not_found');

        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new RemoveMediaOriginalCommand(
            userId: UserId::generate()->value(),
            mediaId: UserId::generate()->value(),
        ));
    }

    public function testRejectsForeignOwner(): void
    {
        $media = $this->readyImageMediaWithConversion(UserId::generate());

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('app.media.access_denied');

        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new RemoveMediaOriginalCommand(
            userId: UserId::generate()->value(),
            mediaId: $media->id->value(),
        ));
    }

    public function testRejectsNonReadyMedia(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId);
        $media->markUploaded();
        $this->persist($media);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('app.media.original_not_removable');

        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new RemoveMediaOriginalCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
        ));
    }

    public function testRejectsReadyDocumentWithoutConversions(): void
    {
        $userId = UserId::generate();
        $media = $this->readyMediaWithoutConversions(
            userId: $userId,
            type: MediaType::Document,
            extension: 'pdf',
            mimeType: 'application/pdf',
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('app.media.no_conversions_to_keep');

        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new RemoveMediaOriginalCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
        ));
    }

    public function testRejectsReadyImageWithEmptyConversionPlan(): void
    {
        $userId = UserId::generate();
        $media = $this->readyMediaWithoutConversions(
            userId: $userId,
            type: MediaType::Image,
            extension: 'jpg',
            mimeType: 'image/jpeg',
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('app.media.no_conversions_to_keep');

        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new RemoveMediaOriginalCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
        ));
    }

    public function testRejectsReadyImageWithOnlyNonReadyConversion(): void
    {
        // Строка конверсии есть, но она не Ready (ещё processing) -> готовой к отдаче конверсии нет,
        // поэтому удалять оригинал нельзя: чекер не считает не-Ready за наличие конверсии.
        $userId = UserId::generate();
        $media = $this->readyMediaWithoutConversions(
            userId: $userId,
            type: MediaType::Image,
            extension: 'jpg',
            mimeType: 'image/jpeg',
        );
        $this->persist($this->imageConversion(
            media: $media,
            type: MediaImageConversionType::Thumbnail,
            status: MediaConversionStatus::Processing,
        ));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('app.media.no_conversions_to_keep');

        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new RemoveMediaOriginalCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
        ));
    }

    public function testIsIdempotentWhenOriginalAlreadyRemoved(): void
    {
        $userId = UserId::generate();
        $media = $this->readyImageMediaWithConversion($userId);
        $media->markReadyOriginalRemoved();
        $this->persist($media);
        $updatedAtBefore = $media->updatedAt;

        // Ранний return до deleteObject: повторный вызов не обращается к S3.
        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::never())->method('deleteObject');

        $result = $this->handler($fileService)->handle(new RemoveMediaOriginalCommand(
            userId: $userId->value(),
            mediaId: $media->id->value(),
        ));

        self::assertSame(MediaStatus::ReadyOriginalRemoved, $result->status);
        self::assertSame($media->id->value(), $result->mediaId);

        // Ранний return до persist+run(): повторной записи в БД нет — updatedAt не сдвинулся.
        $reloaded = $this->mediaRepository()->findById($media->id);
        self::assertNotNull($reloaded);
        self::assertEquals($updatedAtBefore, $reloaded->updatedAt);
    }

    public function testLogsCompletionContextWithoutRawValueObjects(): void
    {
        // Закрепляет контракт debug_precise: состав лог-контекста завершения (mediaId/userId/storage/path,
        // camelCase, без сырых VO). Без этого случайное удаление ключа или передача VO не уронит тесты.
        $userId = UserId::generate();
        $media = $this->readyImageMediaWithConversion($userId);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('debug')->with(
            'Оригинал медиа удалён.',
            [
                'mediaId' => $media->id->value(),
                'userId' => $userId->value(),
                'storage' => $media->storage->value,
                'path' => $media->path->value(),
            ],
        );

        $this->handler(fileService: $this->createStub(MediaFileServiceContract::class), logger: $logger)->handle(
            new RemoveMediaOriginalCommand(
                userId: $userId->value(),
                mediaId: $media->id->value(),
            ),
        );
    }

    private function handler(
        MediaFileServiceContract $fileService,
        LoggerInterface|null $logger = null,
    ): RemoveMediaOriginalHandler {
        return new RemoveMediaOriginalHandler(
            mediaRepository: $this->mediaRepository(),
            mediaConversionsChecker: new MediaConversionsChecker(
                mediaImageConversionRepository: $this->imageConversionRepository(),
                mediaVideoConversionRepository: $this->videoConversionRepository(),
                mediaAudioConversionRepository: $this->audioConversionRepository(),
            ),
            mediaFileService: $fileService,
            entityManager: $this->entityManager(),
            logger: $logger ?? new NullLogger(),
        );
    }

    private function readyImageMediaWithConversion(UserId $userId): Media
    {
        $media = $this->readyMediaWithoutConversions(
            userId: $userId,
            type: MediaType::Image,
            extension: 'jpg',
            mimeType: 'image/jpeg',
        );

        $this->persist($this->thumbnailConversion($media));

        return $media;
    }

    private function readyVideoMediaWithConversion(UserId $userId): Media
    {
        $media = $this->readyMediaWithoutConversions(
            userId: $userId,
            type: MediaType::Video,
            extension: 'mp4',
            mimeType: 'video/mp4',
        );

        $this->persist(
            $this->videoConversion($media),
            $this->imageConversion(
                media: $media,
                type: MediaImageConversionType::Poster,
                status: MediaConversionStatus::Ready,
            ),
        );

        return $media;
    }

    private function readyAudioMediaWithConversion(UserId $userId): Media
    {
        $media = $this->readyMediaWithoutConversions(
            userId: $userId,
            type: MediaType::Audio,
            extension: 'mp3',
            mimeType: 'audio/mpeg',
        );

        $this->persist($this->audioConversion($media));

        return $media;
    }

    private function readyMediaWithoutConversions(
        UserId $userId,
        MediaType $type,
        string $extension,
        string $mimeType,
    ): Media {
        $media = $this->createMedia(
            userId: $userId,
            visibility: MediaVisibility::Public,
            type: $type,
            extension: $extension,
            mimeType: $mimeType,
        );
        $media->markUploaded();
        $media->markReadyMovedTo(
            MediaStorage::Public,
            MediaPath::originalReady(storageKey: $media->storageKey, type: $type, extension: $extension),
        );
        $this->persist($media);

        return $media;
    }
}
