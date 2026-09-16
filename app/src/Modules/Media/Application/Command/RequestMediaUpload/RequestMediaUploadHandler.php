<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\RequestMediaUpload;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Contract\MediaUploadPlannerContract;
use App\Modules\Media\Application\Dto\MediaFileMeta;
use App\Modules\Media\Application\Dto\RequestMediaUploadResult;
use App\Modules\Media\Application\Dto\MediaUploadSpec;
use App\Modules\Media\Application\Service\MediaTypeResolver;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaMultipartUpload;
use App\Modules\Media\Domain\Exception\MediaFileNameWithoutExtensionException;
use App\Modules\Media\Domain\Exception\MediaFileSizeExceededException;
use App\Modules\Media\Domain\Exception\MediaMimeTypeNotAllowedException;
use App\Modules\Media\Domain\Repository\MediaRepository;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use Psr\Log\LoggerInterface;

final readonly class RequestMediaUploadHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaFileServiceContract $mediaFileService,
        private MediaTypeResolver $mediaTypeResolver,
        private MediaUploadPlannerContract $uploadPlanner,
        private LoggerInterface $logger,
    ) {}

    #[LogOperation]
    public function handle(RequestMediaUploadCommand $command): RequestMediaUploadResult
    {
        $mediaType = $this->mediaTypeResolver->resolve($command->fileMeta->mimeType);
        $this->assertUploadAllowed(spec: $command->spec, fileMeta: $command->fileMeta);

        $storageKey = MediaStorageKey::generate();
        $media = Media::create(
            storageKey: $storageKey,
            type: $mediaType,
            visibility: $command->spec->visibility,
            path: MediaPath::originalUpload(storageKey: $storageKey, extension: $this->extension($command->fileMeta)),
            mimeType: $command->fileMeta->mimeType,
            size: $command->fileMeta->size,
            uploadedById: UserId::fromString($command->userId),
            expiration: $this->uploadPlanner->stagingExpiration(),
        );

        $presignedExpiresAt = $this->expiresIn($command->spec->presignedTtl->value());
        $preparedUpload = $this->uploadPlanner->isMultipart($command->fileMeta->size)
            ? $this->prepareMultipartUpload(media: $media, presignedExpiresAt: $presignedExpiresAt)
            : $this->prepareSingleUpload(media: $media, presignedExpiresAt: $presignedExpiresAt);

        $this->logger->debug(message: 'Запрошена загрузка медиа.', context: [
            'mediaId' => $media->id->value(),
            'userId' => $command->userId,
            'uploadMode' => $preparedUpload->uploadMode->value,
            'size' => $command->fileMeta->size->value(),
            'mimeType' => $command->fileMeta->mimeType->value(),
            'presignedTtlSeconds' => $command->spec->presignedTtl->value(),
        ]);

        return $preparedUpload;
    }

    private function prepareSingleUpload(Media $media, \DateTimeImmutable $presignedExpiresAt): RequestMediaUploadResult
    {
        $putUrl = $this->mediaFileService->presignPut(
            storage: $media->storage,
            path: $media->path,
            mimeType: $media->mimeType,
            expiresAt: $presignedExpiresAt,
        );
        $this->mediaRepository->save($media);

        return RequestMediaUploadResult::single(
            mediaId: $media->id->value(),
            putUrl: $putUrl,
            expiresAt: $presignedExpiresAt,
        );
    }

    private function prepareMultipartUpload(Media $media, \DateTimeImmutable $presignedExpiresAt): RequestMediaUploadResult
    {
        $partSize = $this->uploadPlanner->partSize();
        $partsCount = $this->uploadPlanner->partsCount(size: $media->size, partSize: $partSize);
        $uploadId = $this->mediaFileService->createMultipartUpload(
            storage: $media->storage,
            path: $media->path,
            mimeType: $media->mimeType,
        );
        $parts = $this->mediaFileService->presignUploadParts(
            storage: $media->storage,
            path: $media->path,
            uploadId: $uploadId,
            partsCount: $partsCount,
            expiresAt: $presignedExpiresAt,
        );

        $this->mediaRepository->saveWithMultipartUpload(
            media: $media,
            multipartUpload: MediaMultipartUpload::create(
                media: $media,
                uploadId: $uploadId,
                partsCount: $partsCount,
                partSize: $partSize,
                fileSize: $media->size,
            ),
        );

        return RequestMediaUploadResult::multipart(
            mediaId: $media->id->value(),
            uploadId: $uploadId->value(),
            parts: $parts,
            expiresAt: $presignedExpiresAt,
        );
    }

    private function assertUploadAllowed(MediaUploadSpec $spec, MediaFileMeta $fileMeta): void
    {
        if (!$spec->allowedMimeTypes->containsMimeType($fileMeta->mimeType)) {
            throw new MediaMimeTypeNotAllowedException($fileMeta->mimeType->value());
        }

        if ($fileMeta->size->value() > $spec->maxSize->value()) {
            throw new MediaFileSizeExceededException();
        }
    }

    private function extension(MediaFileMeta $fileMeta): string
    {
        $extension = \pathinfo(path: $fileMeta->fileName, flags: \PATHINFO_EXTENSION);

        if ($extension === '') {
            throw new MediaFileNameWithoutExtensionException();
        }

        return $extension;
    }

    private function expiresIn(int $seconds): \DateTimeImmutable
    {
        return new \DateTimeImmutable()->add(new \DateInterval(\sprintf('PT%dS', $seconds)));
    }
}
