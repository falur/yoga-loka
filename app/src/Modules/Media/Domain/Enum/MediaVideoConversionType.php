<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Enum;

enum MediaVideoConversionType: string
{
    case NormalizedMp4H264 = 'normalizedMp4H264';
}
