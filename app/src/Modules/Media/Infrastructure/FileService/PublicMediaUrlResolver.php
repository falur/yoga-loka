<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\FileService;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaUrlResult;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\ValueObject\MediaPath;

/**
 * Резолвер public-медиа: прямой URL из бакета с anonymous-read policy, без срока.
 */
final readonly class PublicMediaUrlResolver implements MediaUrlResolver
{
    public function __construct(private MediaFileServiceContract $mediaFileService) {}

    public function resolve(MediaStorage $storage, MediaPath $path): MediaUrlResult
    {
        return new MediaUrlResult(
            url: $this->mediaFileService->publicUrl(storage: $storage, path: $path),
            expiresAt: null,
        );
    }
}
