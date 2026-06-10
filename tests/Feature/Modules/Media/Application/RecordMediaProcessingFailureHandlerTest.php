<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Command\Media\RecordMediaProcessingFailure\RecordMediaProcessingFailureCommand;
use App\Modules\Media\Application\Command\Media\RecordMediaProcessingFailure\RecordMediaProcessingFailureHandler;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use Psr\Log\NullLogger;

final class RecordMediaProcessingFailureHandlerTest extends MediaApplicationTestCase
{
    public function testRecordsProcessingFailure(): void
    {
        $media = $this->createMedia(userId: UserId::generate());
        $media->markUploaded();
        $this->persist($media);

        $this->handler()->handle(new RecordMediaProcessingFailureCommand(
            mediaId: $media->id->value(),
            error: 'Не удалось обработать изображение',
            isTransient: true,
        ));

        self::assertSame(MediaStatus::ProcessingFailed, $media->status);
        self::assertSame(1, $media->processingAttempts->value());
        self::assertSame('Не удалось обработать изображение', $media->processingError->value());
    }

    public function testKeepsReadyMediaIntact(): void
    {
        // Готовое медиа не должно «ломаться» задним числом: запоздалая/повторная фиксация ошибки
        // на уже ready-медиа — no-op (инвариант «ready без ошибки»).
        $media = $this->createMedia(userId: UserId::generate());
        $media->markUploaded();
        $media->markReadyMovedTo(
            MediaStorage::Private,
            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
        );
        $this->persist($media);

        $this->handler()->handle(new RecordMediaProcessingFailureCommand(
            mediaId: $media->id->value(),
            error: 'Не удалось обработать изображение',
            isTransient: true,
        ));

        self::assertSame(MediaStatus::Ready, $media->status);
        self::assertSame(0, $media->processingAttempts->value());
        self::assertTrue($media->processingError->isEmpty());
    }

    public function testRejectsMissingMedia(): void
    {
        $this->expectException(NotFoundException::class);

        $this->handler()->handle(new RecordMediaProcessingFailureCommand(
            mediaId: UserId::generate()->value(),
            error: 'Ошибка',
            isTransient: false,
        ));
    }

    private function handler(): RecordMediaProcessingFailureHandler
    {
        return new RecordMediaProcessingFailureHandler(
            mediaRepository: $this->mediaRepository(),
            entityManager: $this->entityManager(),
            logger: new NullLogger(),
        );
    }
}
