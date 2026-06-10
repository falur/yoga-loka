<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Dto;

use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaVisibility;

/**
 * Снимок состояния медиа для потребителя. Media-сценарии не отдают наружу доменную Entity.
 */
final readonly class MediaResult
{
    public function __construct(
        public string $mediaId,
        public MediaStatus $status,
        public MediaVisibility $visibility,
    ) {}

    public static function fromEntity(Media $media): self
    {
        return new self(
            mediaId: $media->id->value(),
            status: $media->status,
            visibility: $media->visibility,
        );
    }
}
