<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\Media\ProcessMedia;

use App\Modules\Media\Application\Dto\MediaConversionSpec;

final readonly class ProcessMediaCommand
{
    /**
     * @param list<MediaConversionSpec> $conversions
     */
    public function __construct(
        public string $mediaId,
        public array $conversions,
    ) {}
}
