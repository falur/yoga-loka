<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\Media\MakeMediaPermanent;

final readonly class MakeMediaPermanentCommand
{
    public function __construct(
        public string $userId,
        public string $mediaId,
    ) {}
}
