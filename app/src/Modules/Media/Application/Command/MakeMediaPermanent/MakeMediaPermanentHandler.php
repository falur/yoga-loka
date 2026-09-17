<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\MakeMediaPermanent;

use App\Modules\Media\Domain\Collection\MediaCollection;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Exception\MediaAccessDeniedException;
use App\Modules\Media\Domain\Exception\MediaCannotBeMadePermanentException;
use App\Modules\Media\Domain\Exception\MediaNotFoundException;
use App\Modules\Media\Domain\Repository\MediaRepository;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Shared\Domain\ValueObject\UserId;
use Psr\Log\LoggerInterface;

/**
 * Переводит набор медиа в постоянное состояние. Набор обходится в порядке передачи, поэтому ошибку
 * даёт первое непригодное медиа, а изменения всего набора фиксируются одной записью после обхода.
 */
final readonly class MakeMediaPermanentHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private LoggerInterface $logger,
    ) {}

    public function handle(MakeMediaPermanentCommand $command): MakeMediaPermanentResult
    {
        $owner = UserId::fromString($command->userId);
        $mediaCollection = new MediaCollection();

        foreach ($command->mediaIds as $mediaId) {
            $media = $this->mediaRepository->findById(MediaId::fromString($mediaId))
                ?? throw new MediaNotFoundException();

            if (!$media->uploadedById->equals($owner)) {
                throw new MediaAccessDeniedException();
            }

            // Статус-guard на Application-границе: домен makePermanent() без guard (бросил бы 500).
            if ($media->status !== MediaStatus::Uploaded && $media->status !== MediaStatus::Ready) {
                throw new MediaCannotBeMadePermanentException();
            }

            $media->makePermanent();
            $mediaCollection->push($media);

            $this->logger->debug(message: 'Медиа помечено постоянным.', context: [
                'mediaId' => $media->id->value(),
                'userId' => $command->userId,
            ]);
        }

        $this->mediaRepository->saveAll($mediaCollection);

        return new MakeMediaPermanentResult(mediaIds: $command->mediaIds);
    }
}
