<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Command\Media\ProcessMedia\ProcessMediaCommand;
use App\Modules\Media\Application\Command\Media\ProcessMedia\ProcessMediaHandler;
use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Contract\MediaImageProcessorContract;
use App\Modules\Media\Application\Dto\MediaConversionResult;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use Psr\Log\NullLogger;

final class ProcessMediaHandlerTest extends MediaApplicationTestCase
{
    public function testProcessesMediaWithConversions(): void
    {
        $media = $this->uploadedMedia(MediaVisibility::Private);
        $this->persist($media);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->method('getObjectContents')->willReturn('original-bytes');
        $fileService->expects(self::once())->method('putObject');
        $fileService->expects(self::once())->method('copyObject');

        $this->handler($fileService)->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            conversions: [$this->conversionSpec()],
        ));

        self::assertTrue($media->isReady());
        self::assertSame(MediaStorage::Private, $media->storage);

        $conversions = $this->imageConversionRepository()->findByMediaId($media->id);
        self::assertCount(1, $conversions);
        self::assertSame(MediaStorage::Private, $conversions->first()->storage);
    }

    public function testStoresConversionDimensionsFromProcessorResult(): void
    {
        // Контракт Handler'а: в MediaImageConversion ложатся width/height из результата процессора
        // (размер реально записанного объекта), а не запрошенные spec.width/spec.height. Процессор
        // здесь застаблен, чтобы вернуть размеры, отличные от spec, и проверить именно источник
        // данных (результат, а не spec). С реальным cover() результат всегда равен spec, поэтому это
        // расхождение — артефакт стаба, а не поведение продакшен-процессора; смысл теста — зафиксировать,
        // что чтение идёт из результата и переживёт возможную смену режима ресайза на contain/scale.
        $media = $this->uploadedMedia(MediaVisibility::Private);
        $this->persist($media);

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->method('getObjectContents')->willReturn('original-bytes');

        $handler = $this->handler(
            fileService: $fileService,
            resultWidth: 100,
            resultHeight: 56,
        );
        $handler->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            conversions: [$this->conversionSpec(width: 100, height: 100)],
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
            conversions: [],
        ));

        self::assertTrue($media->isReady());
        self::assertSame(MediaStorage::Public, $media->storage);
        self::assertCount(0, $this->imageConversionRepository()->findByMediaId($media->id));
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
            conversions: [$this->conversionSpec()],
        ));

        self::assertTrue($media->isReady());
    }

    public function testRejectsMissingMedia(): void
    {
        $this->expectException(NotFoundException::class);

        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new ProcessMediaCommand(
            mediaId: UserId::generate()->value(),
            conversions: [],
        ));
    }

    public function testRejectsMediaInWaitingUploadStatus(): void
    {
        // Медиа в waitingUpload (загрузка не подтверждена) — markReadyMovedTo запрещает переход.
        $media = $this->createMedia(userId: UserId::generate());
        $this->persist($media);

        $this->expectException(InvalidDomainValueException::class);

        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new ProcessMediaCommand(
            mediaId: $media->id->value(),
            conversions: [],
        ));
    }

    private function handler(
        MediaFileServiceContract $fileService,
        int $resultWidth = 100,
        int $resultHeight = 100,
    ): ProcessMediaHandler {
        $processor = $this->createStub(MediaImageProcessorContract::class);
        $processor->method('resize')->willReturn(new MediaConversionResult(
            contents: 'conversion-bytes',
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(128),
            width: MediaPixelDimension::fromInt($resultWidth),
            height: MediaPixelDimension::fromInt($resultHeight),
        ));

        return new ProcessMediaHandler(
            mediaRepository: $this->mediaRepository(),
            mediaFileService: $fileService,
            mediaImageProcessor: $processor,
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
}
