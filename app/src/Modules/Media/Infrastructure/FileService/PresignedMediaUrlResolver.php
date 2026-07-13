<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\FileService;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaUrlResult;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\ValueObject\MediaPath;

/**
 * Резолвер private-медиа: presigned GET-ссылка с единым сроком на весь набор URL одного запроса.
 */
final readonly class PresignedMediaUrlResolver implements MediaUrlResolver
{
    public function __construct(
        private MediaFileServiceContract $mediaFileService,
        private \DateTimeImmutable $expiresAt,
    ) {}

    public function resolve(MediaStorage $storage, MediaPath $path): MediaUrlResult
    {
        return new MediaUrlResult(
            url: $this->mediaFileService->presignGet(storage: $storage, path: $path, expiresAt: $this->expiresAt),
            expiresAt: $this->expiresAt,
        );
    }
}
