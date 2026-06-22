<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\GetMediaUrl;

use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;

final readonly class GetMediaUrlQuery
{
    public function __construct(
        public string $mediaId,
        public int $presignedTtlSeconds,
        public MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType|null $conversionType = null,
    ) {}
}
