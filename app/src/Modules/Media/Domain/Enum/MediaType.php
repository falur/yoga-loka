<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Enum;

enum MediaType: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';
}
