<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\RequestMediaUpload;

use App\Modules\Media\Application\Contract\MediaFileMeta;
use App\Modules\Media\Application\Contract\MediaUploadSpec;

final readonly class RequestMediaUploadCommand
{
    public function __construct(
        public string $userId,
        public MediaUploadSpec $spec,
        public MediaFileMeta $fileMeta,
    ) {}
}
