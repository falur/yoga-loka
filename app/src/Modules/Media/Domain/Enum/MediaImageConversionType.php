<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Enum;

enum MediaImageConversionType: string
{
    case Thumbnail = 'thumbnail';
    case Preview = 'preview';
    case Large = 'large';
    case Poster = 'poster';
}
