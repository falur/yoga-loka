<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\Media\RequestMediaUpload;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaFileMeta;
use App\Modules\Media\Application\Dto\RequestMediaUploadResult;
use App\Modules\Media\Application\Dto\MediaUploadSpec;
use App\Modules\Media\Application\Service\MediaTypeResolver;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaMultipartUpload;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use Psr\Log\LoggerInterface;

final readonly class RequestMediaUploadHandler
{
    public function __construct(
        private MediaFileServiceContract $mediaFileService,
        private MediaTypeResolver $mediaTypeResolver,
        private MediaConfig $mediaConfig,
        private EntityManagerInterface $entityManager,
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
            expiration: MediaExpiration::temporaryUntil($this->expiresIn($this->mediaConfig->stagingTtlSeconds)),
        );

        $presignedExpiresAt = $this->expiresIn($command->spec->presignedTtl->value());
        $preparedUpload = $this->isMultipart($command->fileMeta->size)
            ? $this->prepareMultipartUpload(media: $media, presignedExpiresAt: $presignedExpiresAt)
            : $this->prepareSingleUpload(media: $media, presignedExpiresAt: $presignedExpiresAt);

        $this->entityManager->run();

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
        $this->entityManager->persist($media);

        return RequestMediaUploadResult::single(
            mediaId: $media->id->value(),
            putUrl: $putUrl,
            expiresAt: $presignedExpiresAt,
        );
    }

    private function prepareMultipartUpload(Media $media, \DateTimeImmutable $presignedExpiresAt): RequestMediaUploadResult
    {
        $partSize = MediaMultipartPartSize::fromInt($this->mediaConfig->multipartPartSizeBytes);
        $partsCount = MediaMultipartPartsCount::fromInt($this->partsCount(size: $media->size, partSize: $partSize));
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

        $this->entityManager->persist($media);
        $this->entityManager->persist(MediaMultipartUpload::create(
            media: $media,
            uploadId: $uploadId,
            partsCount: $partsCount,
            partSize: $partSize,
            fileSize: $media->size,
        ));

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
            throw new ValidationException(
                translationKey: 'app.media.mime_not_allowed',
                translationParameters: ['mimeType' => $fileMeta->mimeType->value()],
            );
        }

        if ($fileMeta->size->value() > $spec->maxSize->value()) {
            throw new ValidationException('app.media.file_size_exceeded');
        }
    }

    private function extension(MediaFileMeta $fileMeta): string
    {
        $extension = \pathinfo(path: $fileMeta->fileName, flags: \PATHINFO_EXTENSION);

        if ($extension === '') {
            throw new ValidationException('app.media.file_name_without_extension');
        }

        return $extension;
    }

    private function isMultipart(MediaFileSize $size): bool
    {
        return $size->value() >= $this->mediaConfig->multipartThresholdBytes;
    }

    private function partsCount(MediaFileSize $size, MediaMultipartPartSize $partSize): int
    {
        return (int) \ceil($size->value() / $partSize->value());
    }

    private function expiresIn(int $seconds): \DateTimeImmutable
    {
        return new \DateTimeImmutable()->add(new \DateInterval(\sprintf('PT%dS', $seconds)));
    }
}
