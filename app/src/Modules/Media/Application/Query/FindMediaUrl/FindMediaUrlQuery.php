<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\FindMediaUrl;

final readonly class FindMediaUrlQuery
{
    public function __construct(
        public string $mediaId,
        public int $presignedTtlSeconds,
    ) {}
}
