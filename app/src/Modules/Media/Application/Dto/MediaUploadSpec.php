<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Dto;

use App\Modules\Media\Domain\Collection\MediaMimeTypeCollection;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaPresignedTtl;

/**
 * Спецификация ограничений загрузки, которую потребитель передаёт в RequestMediaUpload.
 * In-process DTO: держит политику загрузки на стороне серверного модуля-потребителя.
 */
final readonly class MediaUploadSpec
{
    public function __construct(
        public MediaMimeTypeCollection $allowedMimeTypes,
        public MediaFileSize $maxSize,
        public MediaVisibility $visibility,
        public MediaPresignedTtl $presignedTtl,
    ) {}
}
