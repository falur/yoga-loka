<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Dto;

use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;

/**
 * Метаданные загружаемого файла. fileName используется только для извлечения расширения
 * и не хранится; mimeType и size — доменные VO.
 */
final readonly class MediaFileMeta
{
    public function __construct(
        public string $fileName,
        public MediaMimeType $mimeType,
        public MediaFileSize $size,
    ) {}
}
