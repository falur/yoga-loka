<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\Media\DeleteMedia;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Repository\MediaImageConversionRepository;
use App\Modules\Media\Repository\MediaMultipartUploadRepository;
use App\Modules\Media\Repository\MediaRepository;
use App\Modules\Media\Repository\MediaVideoConversionRepository;
use App\Shared\Domain\Exception\ForbiddenException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use Psr\Log\LoggerInterface;

final readonly class DeleteMediaHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaMultipartUploadRepository $mediaMultipartUploadRepository,
        private MediaImageConversionRepository $mediaImageConversionRepository,
        private MediaVideoConversionRepository $mediaVideoConversionRepository,
        private MediaFileServiceContract $mediaFileService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[LogOperation]
    public function handle(DeleteMediaCommand $command): void
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($command->mediaId))
            ?? throw new NotFoundException('Медиа не найдено.');

        if (!$media->uploadedById->equals(UserId::fromString($command->userId))) {
            throw new ForbiddenException('Нет доступа к этому медиа.');
        }

        if ($media->status === MediaStatus::WaitingUpload) {
            $this->abortActiveMultipartUpload($media);
        }

        // Объекты конверсий лежат в целевом бакете и каскадно удаляются из БД, но не из S3 —
        // удаляем их явно до delete(), иначе остаются осиротевшие файлы. 404 игнорируется сервисом.
        $this->deleteConversionObjects($media);

        // Удаляем текущий оригинал (staging или целевой бакет). 404 игнорируется сервисом.
        $this->mediaFileService->deleteObject(storage: $media->storage, path: $media->path);

        $this->entityManager->delete($media);
        $this->entityManager->run();

        $this->logger->debug(message: 'Медиа удалено.', context: [
            'mediaId' => $command->mediaId,
            'userId' => $command->userId,
        ]);
    }

    private function deleteConversionObjects(Media $media): void
    {
        foreach ($this->mediaImageConversionRepository->findByMediaId($media->id) as $imageConversion) {
            $this->mediaFileService->deleteObject(storage: $imageConversion->storage, path: $imageConversion->path);
        }

        foreach ($this->mediaVideoConversionRepository->findByMediaId($media->id) as $videoConversion) {
            $this->mediaFileService->deleteObject(storage: $videoConversion->storage, path: $videoConversion->path);
        }
    }

    private function abortActiveMultipartUpload(Media $media): void
    {
        $multipartUpload = $this->mediaMultipartUploadRepository->findByMediaId($media->id);

        if ($multipartUpload === null) {
            return;
        }

        $this->mediaFileService->abortMultipartUpload(
            storage: $media->storage,
            path: $media->path,
            uploadId: $multipartUpload->uploadId,
        );
    }
}
