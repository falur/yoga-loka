<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Command\RecordMediaProcessingFailure\RecordMediaProcessingFailureCommand;
use App\Modules\Media\Application\Command\RecordMediaProcessingFailure\RecordMediaProcessingFailureHandler;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\Exception\MediaNotFoundException;
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

        // Media — чистая доменная сущность без Cycle-разметки: handler мутировал свою отдельно
        // загруженную через Mapper копию, а не переменную $media теста, поэтому итоговое
        // состояние проверяется перечитыванием через репозиторий.
        $reloaded = $this->mediaRepository()->findById($media->id);
        self::assertNotNull($reloaded);
        self::assertSame(MediaStatus::ProcessingFailed, $reloaded->status);
        self::assertSame(1, $reloaded->processingAttempts->value());
        self::assertSame('Не удалось обработать изображение', $reloaded->processingError->value());
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

        $reloaded = $this->mediaRepository()->findById($media->id);
        self::assertNotNull($reloaded);
        self::assertSame(MediaStatus::Ready, $reloaded->status);
        self::assertSame(0, $reloaded->processingAttempts->value());
        self::assertTrue($reloaded->processingError->isEmpty());
    }

    public function testRejectsMissingMedia(): void
    {
        $this->expectException(MediaNotFoundException::class);

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
            logger: new NullLogger(),
        );
    }
}
