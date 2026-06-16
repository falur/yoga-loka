<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\CheckMediaExists;

final readonly class CheckMediaExistsQuery
{
    public function __construct(
        public string $mediaId,
    ) {}
}
