<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\RecordMediaProcessingFailure;

use App\Modules\Media\Domain\Exception\MediaNotFoundException;
use App\Modules\Media\Domain\Repository\MediaRepository;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaProcessingError;
use Psr\Log\LoggerInterface;

/**
 * Фиксирует ошибку обработки на Media. Доменные методы записи временной и постоянной
 * ошибки идентичны (ProcessingFailed + инкремент попыток), поэтому вызывается один; флаг
 * isTransient влияет только на решение ProcessMediaJob о повторе и идёт сюда лишь как контекст
 * лога.
 */
final readonly class RecordMediaProcessingFailureHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private LoggerInterface $logger,
    ) {}

    public function handle(RecordMediaProcessingFailureCommand $command): void
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($command->mediaId))
            ?? throw new MediaNotFoundException();

        $media->recordTemporaryProcessingError(MediaProcessingError::fromString($command->error));
        $this->mediaRepository->save($media);

        $this->logger->debug(message: 'Зафиксирована ошибка обработки медиа.', context: [
            'mediaId' => $media->id->value(),
            'isTransient' => $command->isTransient,
            'attempts' => $media->processingAttempts->value(),
        ]);
    }
}
