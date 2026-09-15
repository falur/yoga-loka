<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\ProcessMedia;

use App\Modules\Media\Public\Dto\MediaConversionPlanDto;

final readonly class ProcessMediaCommand
{
    public function __construct(
        public string $mediaId,
        public MediaConversionPlanDto $plan,
    ) {}
}
