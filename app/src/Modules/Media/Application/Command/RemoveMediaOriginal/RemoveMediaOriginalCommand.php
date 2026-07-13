<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\RemoveMediaOriginal;

final readonly class RemoveMediaOriginalCommand
{
    public function __construct(
        public string $userId,
        public string $mediaId,
    ) {}
}
