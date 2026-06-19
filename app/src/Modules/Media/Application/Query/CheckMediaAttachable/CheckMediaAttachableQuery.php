<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\CheckMediaAttachable;

final readonly class CheckMediaAttachableQuery
{
    public function __construct(
        public string $mediaId,
        public string $ownerUserId,
    ) {}
}
