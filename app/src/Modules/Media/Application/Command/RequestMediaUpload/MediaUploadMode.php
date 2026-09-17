<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\RequestMediaUpload;

enum MediaUploadMode: string
{
    case Single = 'single';
    case Multipart = 'multipart';
}
