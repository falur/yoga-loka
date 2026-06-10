<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\Media\CheckMediaIsImage;

final readonly class CheckMediaIsImageQuery
{
    public function __construct(
        public string $mediaId,
    ) {}
}
