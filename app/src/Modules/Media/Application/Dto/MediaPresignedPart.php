<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Dto;

/**
 * Одна presigned-ссылка части multipart-загрузки. Именованный DTO вместо array-shape.
 */
final readonly class MediaPresignedPart
{
    public function __construct(
        public int $partNumber,
        public string $url,
    ) {}
}
