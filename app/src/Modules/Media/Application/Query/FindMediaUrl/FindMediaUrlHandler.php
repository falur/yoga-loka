<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\FindMediaUrl;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaUrlResult;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaPresignedTtl;
use App\Modules\Media\Repository\MediaRepository;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Не бросающий вариант GetMediaUrl для best-effort отображения (например, аватара в профиле):
 * если медиа отсутствует или ещё не готово — возвращает null, чтобы вызывающий подставил значение
 * по умолчанию без try-catch. Для публичного медиа отдаёт прямой URL, для приватного — presigned.
 */
final readonly class FindMediaUrlHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaFileServiceContract $mediaFileService,
    ) {}

    #[LogOperation]
    public function handle(FindMediaUrlQuery $query): MediaUrlResult|null
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($query->mediaId));

        if ($media === null || !$media->isReady()) {
            return null;
        }

        if ($media->visibility === MediaVisibility::Public) {
            return new MediaUrlResult(
                url: $this->mediaFileService->publicUrl(storage: $media->storage, path: $media->path),
                expiresAt: null,
            );
        }

        $expiresAt = new \DateTimeImmutable()->add(
            new \DateInterval(\sprintf('PT%dS', MediaPresignedTtl::fromInt($query->presignedTtlSeconds)->value())),
        );

        return new MediaUrlResult(
            url: $this->mediaFileService->presignGet(storage: $media->storage, path: $media->path, expiresAt: $expiresAt),
            expiresAt: $expiresAt,
        );
    }
}
