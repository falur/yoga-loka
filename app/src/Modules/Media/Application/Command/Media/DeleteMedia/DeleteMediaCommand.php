<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\Media\DeleteMedia;

final readonly class DeleteMediaCommand
{
    public function __construct(
        public string $userId,
        public string $mediaId,
    ) {}
}
