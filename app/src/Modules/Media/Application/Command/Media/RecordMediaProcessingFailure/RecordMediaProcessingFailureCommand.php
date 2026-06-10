<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\Media\RecordMediaProcessingFailure;

final readonly class RecordMediaProcessingFailureCommand
{
    public function __construct(
        public string $mediaId,
        public string $error,
        public bool $isTransient,
    ) {}
}
