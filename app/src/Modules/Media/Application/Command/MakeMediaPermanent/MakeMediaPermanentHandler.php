<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\MakeMediaPermanent;

use App\Modules\Media\Application\Dto\MediaResult;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Repository\MediaRepository;
use App\Shared\Domain\Exception\ForbiddenException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class MakeMediaPermanentHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    public function handle(MakeMediaPermanentCommand $command): MediaResult
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($command->mediaId))
            ?? throw new NotFoundException('app.media.not_found');

        if (!$media->uploadedById->equals(UserId::fromString($command->userId))) {
            throw new ForbiddenException('app.media.access_denied');
        }

        // Статус-guard на Application-границе: домен makePermanent() без guard (бросил бы 500).
        if ($media->status !== MediaStatus::Uploaded && $media->status !== MediaStatus::Ready) {
            throw new ValidationException('app.media.cannot_make_permanent');
        }

        $media->makePermanent();
        $this->entityManager->persist($media);
        $this->entityManager->run();

        $this->logger->debug(message: 'Медиа помечено постоянным.', context: [
            'mediaId' => $media->id->value(),
            'userId' => $command->userId,
        ]);

        return MediaResult::fromEntity($media);
    }
}
