<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\GetMediaUrl;

use App\Modules\Media\Domain\Enum\MediaImageConversionType;

final readonly class GetMediaUrlQuery
{
    public function __construct(
        public string $mediaId,
        public int $presignedTtlSeconds,
        public MediaImageConversionType|null $conversionType = null,
    ) {}
}
