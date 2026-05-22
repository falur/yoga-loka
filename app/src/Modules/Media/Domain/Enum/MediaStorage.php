<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Enum;

enum MediaStorage: string
{
    case Upload = 'media-upload';
    case Private = 'media-private';
    case Public = 'media-public';
}
