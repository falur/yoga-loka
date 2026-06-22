<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\CompleteMediaUpload;

use App\Modules\Media\Application\Dto\MediaConversionPlan;
use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;

final readonly class CompleteMediaUploadCommand
{
    public function __construct(
        public string $userId,
        public string $mediaId,
        public MediaConversionPlan $plan,
        public MediaMultipartPartCollection|null $parts,
    ) {}
}
