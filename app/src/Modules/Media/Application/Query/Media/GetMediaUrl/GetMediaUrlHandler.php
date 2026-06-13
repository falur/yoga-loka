<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\Media\GetMediaUrl;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaUrlResult;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPresignedTtl;
use App\Modules\Media\Repository\MediaImageConversionRepository;
use App\Modules\Media\Repository\MediaRepository;
use App\Shared\Domain\Exception\NotFoundException;

final readonly class GetMediaUrlHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaImageConversionRepository $mediaImageConversionRepository,
        private MediaFileServiceContract $mediaFileService,
    ) {}

    public function handle(GetMediaUrlQuery $query): MediaUrlResult
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($query->mediaId))
            ?? throw new NotFoundException('app.media.not_found');

        if (!$media->isReady()) {
            throw new NotFoundException('app.media.not_ready');
        }

        if ($query->conversionType !== null) {
            $conversion = $this->findConversion(mediaId: $media->id, type: $query->conversionType)
                ?? throw new NotFoundException('app.media.conversion_not_found');

            return $this->buildUrl(
                visibility: $media->visibility,
                storage: $conversion->storage,
                path: $conversion->path,
                presignedTtlSeconds: $query->presignedTtlSeconds,
            );
        }

        return $this->buildUrl(
            visibility: $media->visibility,
            storage: $media->storage,
            path: $media->path,
            presignedTtlSeconds: $query->presignedTtlSeconds,
        );
    }

    private function findConversion(MediaId $mediaId, MediaImageConversionType $type): MediaImageConversion|null
    {
        return $this->mediaImageConversionRepository->findByMediaId($mediaId)->first(
            static fn(MediaImageConversion $conversion): bool => $conversion->type === $type,
        );
    }

    private function buildUrl(
        MediaVisibility $visibility,
        MediaStorage $storage,
        MediaPath $path,
        int $presignedTtlSeconds,
    ): MediaUrlResult {
        if ($visibility === MediaVisibility::Public) {
            return new MediaUrlResult(
                url: $this->mediaFileService->publicUrl(storage: $storage, path: $path),
                expiresAt: null,
            );
        }

        $expiresAt = new \DateTimeImmutable()->add(
            new \DateInterval(\sprintf('PT%dS', MediaPresignedTtl::fromInt($presignedTtlSeconds)->value())),
        );

        return new MediaUrlResult(
            url: $this->mediaFileService->presignGet(storage: $storage, path: $path, expiresAt: $expiresAt),
            expiresAt: $expiresAt,
        );
    }
}
