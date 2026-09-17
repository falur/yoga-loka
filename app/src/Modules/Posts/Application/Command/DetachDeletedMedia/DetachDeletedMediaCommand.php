<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\DetachDeletedMedia;

final readonly class DetachDeletedMediaCommand
{
    public function __construct(
        public string $mediaId,
    ) {}
}
