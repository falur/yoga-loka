<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\Media\CompleteMediaUpload;

use App\Modules\Media\Application\Dto\MediaConversionSpec;
use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;

final readonly class CompleteMediaUploadCommand
{
    /**
     * @param list<MediaConversionSpec> $conversions
     */
    public function __construct(
        public string $userId,
        public string $mediaId,
        public array $conversions,
        public MediaMultipartPartCollection|null $parts,
    ) {}
}
